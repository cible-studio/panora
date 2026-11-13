<?php

namespace App\Console\Commands;

use App\Enums\PoseTaskStatus;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Services\Axes\AxeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Découpe les AXES ENTIERS des campagnes en cours (2026-11-13, validé par
 * l'utilisateur : « faisons le découpage pour que dans l'espace tech ce
 * soit comme notre nouvelle refonte » ; factures pas encore générées).
 *
 * Les contrats signés avant le découpage (BEM, MOOV, LADILI) tiennent la
 * LIGNE de l'axe : une seule pose pour 9 ou 10 pieds. Pour chaque axe
 * entier d'une campagne planifiée, active ou en pause :
 *
 *   1. la campagne tient désormais ses PIEDS (campaign_panels) au lieu de
 *      la ligne de l'axe — les pieds déjà pris ailleurs sur la période sont
 *      signalés et laissés de côté. La RÉSERVATION garde la ligne de l'axe
 *      et son prix : la facture générée reste celle d'avant, au franc près
 *      (InvoiceFromCampaignBuilder::grouperParAxe). Un axe dont la ligne
 *      n'est pas sur la réservation de la campagne n'est pas découpé ;
 *   2. chaque pose OUVERTE de l'axe devient une pose par pied — même
 *      technicien, équipe, date, statut, avancement, vague — et la pose de
 *      l'axe passe « annulée » avec la mention du découpage ;
 *   3. les poses déjà réalisées et leurs photos restent telles quelles
 *      (historique) ;
 *   4. une pose ouverte qui a DÉJÀ des photos n'est pas découpée (on ne
 *      saurait pas quel pied elles montrent) : à terminer avec « Terminé »
 *      ou à traiter à la main.
 *
 * Les poses des pieds sont créées sans événement : ni alerte « Nouvelle
 * pose », ni WhatsApp au technicien (c'est la même pose, découpée). Les
 * factures et les réservations ne sont pas touchées.
 *
 * Simulation par défaut ; --appliquer écrit, axe par axe, en transaction.
 */
class DecouperAxesDesCampagnes extends Command
{
    protected $signature = 'axes:decouper-campagnes
        {--appliquer : écrit réellement en base (sans cette option, simple simulation)}
        {--campagne=* : limiter à une ou plusieurs campagnes (id)}';

    protected $description = 'Remplace, dans les campagnes en cours, chaque axe entier par ses pieds (une pose par pied) — simulation par défaut';

    /** Colonnes recopiées de la pose de l'axe vers la pose de chaque pied. */
    private const COPIEES = [
        'campaign_id', 'assigned_user_id', 'pose_team_id', 'team_name', 'scheduled_at',
        'status', 'progress_percent', 'started_at', 'arrived_at', 'pose_kind', 'vague_id',
    ];

    public function handle(AxeService $axes): int
    {
        if (!$axes->colonnesPresentes()) {
            $this->error('Les colonnes des axes ne sont pas en base (migration des axes non jouée).');
            return self::FAILURE;
        }

        $appliquer = (bool) $this->option('appliquer');
        $this->info($appliquer ? '▶ Découpage — ÉCRITURE en base' : '▶ Découpage — SIMULATION (rien n\'est écrit ; --appliquer pour écrire)');

        $lignes = DB::table('campaign_panels')
            ->join('panels', 'panels.id', '=', 'campaign_panels.panel_id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->where('panels.est_axe', true)
            ->where('campaign_panels.type', 'interne')
            ->whereNull('campaigns.deleted_at')
            ->whereIn('campaigns.status', ['planifie', 'actif', 'pause'])
            ->when($this->option('campagne'), fn ($q, $ids) => $q->whereIn('campaigns.id', array_map('intval', $ids)))
            ->orderBy('campaigns.id')->orderBy('panels.reference')
            ->get([
                'campaign_panels.id as cp_id', 'campaigns.id as campagne_id', 'campaigns.name as campagne',
                'campaigns.reservation_id', 'campaigns.start_date', 'campaigns.end_date',
                'panels.id as axe_id', 'panels.reference',
            ]);

        if ($lignes->isEmpty()) {
            $this->info('Aucun axe entier dans une campagne en cours : rien à faire.');
            return self::SUCCESS;
        }

        $colonnes = array_values(array_filter(self::COPIEES, fn ($c) => Schema::hasColumn('pose_tasks', $c)));
        $totaux = ['axes' => 0, 'pieds' => 0, 'poses' => 0, 'ecartes' => 0];

        foreach ($lignes as $l) {
            $this->newLine();
            $this->line("<options=bold>#{$l->campagne_id} {$l->campagne} — {$l->reference}</>");

            $pieds = Panel::where('axe_id', $l->axe_id)->orderBy('pied_numero')->get(['id', 'reference', 'pied_numero']);
            if ($pieds->isEmpty()) {
                $this->warn('  ✗ cet axe n\'a pas de pieds (axes:decouper non passé) — laissé tel quel');
                $totaux['ecartes']++;
                continue;
            }

            // Le prix de l'axe au contrat vit sur la réservation (ligne de
            // l'axe). C'est lui qui garde la facture inchangée une fois les
            // pieds dans la campagne (InvoiceFromCampaignBuilder::grouperParAxe) :
            // sans lui, la facture additionnerait le tarif catalogue des pieds.
            $prixContrat = $l->reservation_id
                ? DB::table('reservation_panels')->where('reservation_id', $l->reservation_id)
                    ->where('panel_id', $l->axe_id)->value('unit_price')
                : null;
            if ($prixContrat === null) {
                $this->warn('  ✗ la ligne de l\'axe n\'est pas sur la réservation de la campagne (prix du contrat introuvable) — axe laissé tel quel');
                $totaux['ecartes']++;
                continue;
            }
            if (DB::table('reservation_panels')->where('reservation_id', $l->reservation_id)
                    ->whereIn('panel_id', $pieds->pluck('id'))->exists()) {
                $this->warn('  ✗ la réservation tient déjà des pieds de cet axe à côté de la ligne de l\'axe — à traiter à la main');
                $totaux['ecartes']++;
                continue;
            }

            // Pose ouverte qui a déjà des photos : on ne sait pas quel pied
            // chacune montre — on n'y touche pas.
            $ouvertes = PoseTask::where('campaign_id', $l->campagne_id)->where('panel_id', $l->axe_id)
                ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
                ->get();
            $avecPhotos = $ouvertes->filter(fn ($t) => Pige::where('pose_task_id', $t->id)->where('status', '!=', 'rejete')->exists());
            if ($avecPhotos->isNotEmpty()) {
                $this->warn('  ✗ la pose ouverte #' . $avecPhotos->pluck('id')->implode(', #')
                    . ' a déjà des photos : à fermer avec « Terminé » (ou à traiter à la main) — axe laissé tel quel');
                $totaux['ecartes']++;
                continue;
            }

            // Pieds déjà pris par une AUTRE campagne en cours sur la période.
            $pris = DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->whereIn('campaign_panels.panel_id', $pieds->pluck('id'))
                ->where('campaign_panels.campaign_id', '!=', $l->campagne_id)
                ->whereNull('campaigns.deleted_at')
                ->whereIn('campaigns.status', ['planifie', 'actif', 'pause'])
                ->where('campaigns.start_date', '<=', $l->end_date)
                ->where('campaigns.end_date', '>=', $l->start_date)
                ->pluck('campaigns.id', 'campaign_panels.panel_id')
                ->map(fn ($id) => "la campagne #{$id}")->all();
            // … ou par la réservation d'un autre contrat sur la période.
            DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->whereIn('reservation_panels.panel_id', $pieds->pluck('id'))
                ->when($l->reservation_id, fn ($q, $r) => $q->where('reservations.id', '!=', $r))
                ->whereNull('reservations.deleted_at')
                ->whereIn('reservations.status', ['en_attente', 'confirme'])
                ->where('reservations.start_date', '<=', $l->end_date)
                ->where('reservations.end_date', '>=', $l->start_date)
                ->get(['reservation_panels.panel_id', 'reservations.id'])
                ->each(function ($r) use (&$pris) { $pris[$r->panel_id] ??= "la réservation #{$r->id}"; });
            $deja = DB::table('campaign_panels')->where('campaign_id', $l->campagne_id)
                ->whereIn('panel_id', $pieds->pluck('id'))->pluck('panel_id')->all();

            $aAjouter = $pieds->reject(fn ($p) => isset($pris[$p->id]) || in_array($p->id, $deja));
            foreach ($pieds->filter(fn ($p) => isset($pris[$p->id])) as $p) {
                $this->warn("  ⚠ {$p->reference} déjà pris par {$pris[$p->id]} — laissé de côté");
            }
            $piedsCampagne = $pieds->reject(fn ($p) => isset($pris[$p->id]));
            if ($piedsCampagne->isEmpty()) {
                $this->warn('  ✗ aucun pied libre pour cette campagne — axe laissé tel quel');
                $totaux['ecartes']++;
                continue;
            }

            $this->line('  + ' . $aAjouter->count() . ' pied(s) rattaché(s) à la campagne : '
                . ($aAjouter->pluck('reference')->implode(', ') ?: '—'));
            $this->line('  − la ligne de l\'axe quitte la campagne ; elle reste sur la réservation #' . $l->reservation_id
                . ' au prix du contrat (' . number_format((int) $prixContrat, 0, ',', ' ') . ' FCFA/mois) : facture inchangée');
            foreach ($ouvertes as $t) {
                $statut = (string) ($t->status instanceof PoseTaskStatus ? $t->status->value : $t->status);
                $this->line("  ↳ pose #{$t->id} ({$statut}, technicien " . ($t->assigned_user_id ?? '—') . ') → '
                    . $piedsCampagne->count() . ' poses de pied, puis annulée « découpée »');
            }

            $totaux['axes']++;
            $totaux['pieds'] += $aAjouter->count();
            $totaux['poses'] += $ouvertes->count() * $piedsCampagne->count();

            if (!$appliquer) {
                continue;
            }

            DB::transaction(function () use ($l, $aAjouter, $piedsCampagne, $ouvertes, $colonnes) {
                $maintenant = now();
                foreach ($aAjouter as $p) {
                    DB::table('campaign_panels')->insert([
                        'campaign_id' => $l->campagne_id, 'panel_id' => $p->id, 'type' => 'interne',
                        'created_at' => $maintenant, 'updated_at' => $maintenant,
                    ]);
                }
                DB::table('campaign_panels')->where('id', $l->cp_id)->delete();

                foreach ($ouvertes as $t) {
                    foreach ($piedsCampagne as $p) {
                        // Un pied qui a déjà une pose ouverte sur la campagne n'en reçoit pas d'autre.
                        $existe = PoseTask::where('campaign_id', $l->campagne_id)->where('panel_id', $p->id)
                            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
                            ->exists();
                        if ($existe) {
                            continue;
                        }
                        $pose = new PoseTask();
                        foreach ($colonnes as $c) {
                            $pose->{$c} = $t->getRawOriginal($c);
                        }
                        $pose->panel_id = $p->id;
                        // La consigne de la pose, que le technicien lit ; la
                        // trace du découpage est sur la pose de l'axe et au journal.
                        $pose->notes = $t->notes;
                        $pose->saveQuietly(); // ni alerte « Nouvelle pose », ni WhatsApp : même pose, découpée
                    }
                    $t->forceFill([
                        'status' => PoseTaskStatus::CANCELLED->value,
                        'notes'  => trim(($t->notes ? $t->notes . "\n" : '')
                            . "Découpée en {$piedsCampagne->count()} pieds le " . $maintenant->format('d/m/Y') . ' (axes:decouper-campagnes).'),
                    ])->saveQuietly();
                }

                Log::info('axes.decouper_campagnes', [
                    'campagne_id' => $l->campagne_id, 'axe_id' => $l->axe_id,
                    'pieds_ajoutes' => $aAjouter->pluck('id')->all(), 'poses_decoupees' => $ouvertes->pluck('id')->all(),
                ]);
            });
            $this->info('  ✓ fait');
        }

        $this->newLine();
        $this->info("Axes découpés : {$totaux['axes']} · pieds rattachés : {$totaux['pieds']} · poses de pied : {$totaux['poses']}"
            . ($totaux['ecartes'] ? " · laissés tels quels : {$totaux['ecartes']}" : ''));
        if (!$appliquer) {
            $this->comment('Simulation terminée. Relance avec --appliquer pour écrire.');
        }

        return self::SUCCESS;
    }
}

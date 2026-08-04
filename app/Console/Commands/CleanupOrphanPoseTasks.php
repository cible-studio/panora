<?php

namespace App\Console\Commands;

use App\Enums\CampaignStatus;
use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Nettoie les PoseTasks orphelines — celles encore en statut actif
 * (planifiee / en_route / en_cours) alors que leur campagne est
 * terminée, annulée ou soft-deleted.
 *
 * Contexte (2026-08-04) :
 *   - Le bug historique `CampaignService::cancelPendingPoseTasks`
 *     oubliait le statut EN_ROUTE au moment de la clôture campagne
 *     (corrigé dans le même chantier). Résultat : des poses fantômes
 *     traînent en BDD, visibles côté tech, uploadables via ancien
 *     lien WhatsApp.
 *   - Cette commande one-shot nettoie l'historique produit AVANT le fix.
 *
 * Idempotent : rejouable sans risque. Ne touche que les poses ACTIVES
 * (planifiee / en_route / en_cours) — les poses déjà réalisées ou
 * annulées restent intactes (audit préservé).
 *
 * Usage :
 *   php artisan poses:cleanup-orphans           # dry-run : liste ce qui serait annulé
 *   php artisan poses:cleanup-orphans --apply   # applique l'annulation
 */
class CleanupOrphanPoseTasks extends Command
{
    protected $signature = 'poses:cleanup-orphans
                            {--apply : Applique l\'annulation (sinon dry-run)}';

    protected $description = 'Annule les PoseTasks actives dont la campagne est terminée/annulée/supprimée';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $active = [
            PoseTaskStatus::PLANNED->value,
            PoseTaskStatus::EN_ROUTE->value,
            PoseTaskStatus::IN_PROGRESS->value,
        ];

        // Requête cohérente avec le scope onOperableCampaign — on cible
        // TOUT ce qui devrait avoir été annulé mais ne l'a pas été.
        $query = PoseTask::whereIn('status', $active)
            ->where(function ($q) {
                // Campagne existante mais terminée/annulée
                $q->whereHas('campaign', function ($qq) {
                    $qq->whereIn('status', [
                        CampaignStatus::TERMINE->value,
                        CampaignStatus::ANNULE->value,
                    ])->orWhereNotNull('deleted_at');
                });
            })
            ->with(['campaign:id,name,status,deleted_at', 'panel:id,reference']);

        $orphans = $query->get();
        $count = $orphans->count();

        if ($count === 0) {
            $this->info('✓ Aucune pose orpheline détectée. Base propre.');
            return self::SUCCESS;
        }

        $this->line('');
        $this->info(($apply ? 'À ANNULER' : 'DRY-RUN — serait annulé') . " : {$count} pose(s) orpheline(s)");
        $this->line('');

        // Aperçu (limité à 20 lignes pour rester lisible en console)
        $preview = $orphans->take(20);
        $rows = $preview->map(fn(PoseTask $t) => [
            'ID'         => $t->id,
            'Panneau'    => $t->panel?->reference ?? '#'.$t->panel_id,
            'Campagne'   => $t->campaign?->name ?? '#'.$t->campaign_id,
            'Cmp statut' => $t->campaign?->deleted_at
                ? 'supprimée'
                : ($t->campaign?->status?->value ?? '—'),
            'Pose stat'  => $t->status,
            'Planifiée'  => $t->scheduled_at?->format('d/m/Y') ?? '—',
        ])->toArray();
        $this->table(array_keys($rows[0] ?? []), $rows);

        if ($count > 20) {
            $this->line("  … et " . ($count - 20) . " autres.");
            $this->line('');
        }

        if (!$apply) {
            $this->warn("Dry-run — aucun changement en BDD. Relance avec --apply pour appliquer.");
            return self::SUCCESS;
        }

        // Application — annulation propre + note traçable + audit log.
        $now = now();
        $note = "[Auto cleanup 2026-08-04] Campagne terminée/annulée — pose fantôme rattrapée.";

        $applied = 0;
        DB::transaction(function () use ($orphans, $now, $note, &$applied) {
            foreach ($orphans as $task) {
                $task->update([
                    'status' => PoseTaskStatus::CANCELLED->value,
                    'notes'  => trim(($task->notes ? $task->notes."\n" : '') . $note),
                ]);
                $applied++;
            }
        });

        Log::info('poses.cleanup_orphans.applied', [
            'count'    => $applied,
            'ran_by'   => 'artisan_cli',
            'at'       => $now->toIso8601String(),
        ]);

        $this->info("✓ {$applied} pose(s) annulée(s). Log Laravel : 'poses.cleanup_orphans.applied'.");
        return self::SUCCESS;
    }
}

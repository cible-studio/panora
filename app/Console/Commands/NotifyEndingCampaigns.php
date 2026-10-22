<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\RelanceFinCampagne;
use App\Services\Diffusion\RelanceFinCampagneService;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Console\Command;

/**
 * Mails aux clients avant l'échéance de leur campagne — chaque jour à 10h
 * (routes/console.php § 9).
 *
 *   · Campagnes de 12 mois ou plus (2026-10-22) : séquence de
 *     renouvellement J-60, J-45, J-30, J-15, J-7, arrêtée dès que le client
 *     répond (RenouvellementService).
 *   · Autres campagnes : mail J-7 simple (RelanceFinCampagneService,
 *     refait le 2026-10-21).
 *
 * Un seul mail par campagne et par étape, garanti par le journal
 * relances_fin_campagne. Mode test tant que RELANCE_FIN_MODE ≠ production.
 *
 *   php artisan campaigns:notify-ending --dry-run      ce qui partirait aujourd'hui
 *   php artisan campaigns:notify-ending --campagne=42  traite une campagne précise,
 *                                                      quelle que soit sa date de fin
 *   … --campagne=42 --etape=j30                         impose une étape (campagne annuelle)
 */
class NotifyEndingCampaigns extends Command
{
    protected $signature = 'campaigns:notify-ending
                            {--days= : Mail J-7 simple : jours avant la fin (défaut : 7)}
                            {--campagne= : ID d\'une campagne à traiter, quelle que soit sa date de fin}
                            {--etape= : Avec --campagne, campagne annuelle : étape imposée (j60, j45, j30, j15, j7)}
                            {--dry-run : Affiche sans envoyer}';

    protected $description = 'Prévient par mail les clients dont la campagne arrive à échéance (séquence de renouvellement ou J-7)';

    private array $bilan = ['envoye' => 0, 'exclu' => 0, 'echec' => 0];

    public function handle(RelanceFinCampagneService $fin, RenouvellementService $renouvellement): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $jours  = $this->option('days') !== null ? (int) $this->option('days') : $fin->jours();

        $this->line(sprintf(
            'Mode %s%s',
            $fin->estModeTest() ? 'TEST (adresses de test uniquement)' : 'PRODUCTION',
            $dryRun ? ' — simulation' : ''
        ));

        if ($id = $this->option('campagne')) {
            $campagne = Campaign::with(['client.contacts', 'commercial', 'user'])->find($id);
            if (!$campagne || !$campagne->end_date) {
                $this->error("Campagne #{$id} introuvable ou sans date de fin.");

                return self::FAILURE;
            }

            if ($campagne->estAnnuelle()) {
                $this->sequence(collect([$campagne]), $renouvellement, $fin, $dryRun, $this->option('etape'));
            } else {
                $this->finSimple(collect([$campagne]), $fin, $dryRun);
            }
        } else {
            $this->sequence($renouvellement->campagnesDuJour(), $renouvellement, $fin, $dryRun, null);
            $this->finSimple($fin->campagnesDuJour($jours), $fin, $dryRun);
        }

        if (!$dryRun) {
            $this->line('');
            $this->info("Bilan : {$this->bilan['envoye']} envoyé(s) · {$this->bilan['exclu']} non envoyé(s) · {$this->bilan['echec']} à retenter");
        }

        return self::SUCCESS;
    }

    private function sequence($campagnes, RenouvellementService $service, RelanceFinCampagneService $fin, bool $dryRun, ?string $etapeImposee): void
    {
        $this->line('');
        $this->line("Renouvellement (12 mois et plus) — {$campagnes->count()} campagne(s) dans les 60 jours");

        foreach ($campagnes as $campagne) {
            $titre = $this->titre($campagne);
            $etape = $etapeImposee ?: RenouvellementService::etapePour($fin->joursRestants($campagne));

            if ($dryRun) {
                $dossier = $service->dossierExistant($campagne);
                $statut  = $dossier?->statut ?? 'en_attente';
                $a       = $fin->analyser($campagne, regleAutresCampagnes: false);
                $this->line(match (true) {
                    $etape === null           => "  [—] {$titre} : aucune étape due",
                    $statut !== 'en_attente'  => "  [—] {$titre} : séquence arrêtée ({$dossier->libelle()})",
                    $fin->ligne($campagne, $etape, $fin->mode())->statut === RelanceFinCampagne::STATUT_ENVOYE
                                              => "  [=] {$titre} : {$etape} déjà envoyé",
                    $a['statut'] !== null     => "  [—] {$titre} : {$etape} — {$a['motif']}",
                    default                   => "  [→] {$titre} : {$etape} → {$a['destinataire']}",
                });
                continue;
            }

            $ligne = $service->traiter($campagne, etape: $etapeImposee);
            if ($ligne === null) {
                continue; // séquence arrêtée ou rien de dû aujourd'hui
            }
            $this->afficher($titre . " [{$ligne->etape}]", $ligne);
        }
    }

    private function finSimple($campagnes, RelanceFinCampagneService $service, bool $dryRun): void
    {
        $this->line('');
        $this->line("Fin de campagne J-{$service->jours()} (moins de 12 mois) — {$campagnes->count()} campagne(s)");

        foreach ($campagnes as $campagne) {
            $titre = $this->titre($campagne);

            if ($dryRun) {
                $a = $service->analyser($campagne);
                $this->line($a['statut']
                    ? "  [—] {$titre} : {$a['motif']}"
                    : "  [→] {$titre} → {$a['destinataire']} · copie " . (implode(', ', $a['copie']) ?: '—') . " · réponse à {$a['repondre_a']}");
                continue;
            }

            $this->afficher($titre, $service->traiter($campagne));
        }
    }

    private function afficher(string $titre, RelanceFinCampagne $ligne): void
    {
        $this->bilan[$ligne->statut] = ($this->bilan[$ligne->statut] ?? 0) + 1;

        match ($ligne->statut) {
            RelanceFinCampagne::STATUT_ENVOYE => $this->info("  ✓ {$titre} → {$ligne->destinataire} ({$ligne->canal})" . ($ligne->motif ? " — {$ligne->motif}" : '')),
            RelanceFinCampagne::STATUT_EXCLU  => $this->line("  — {$titre} : {$ligne->motif}"),
            default                           => $this->warn("  ✗ {$titre} : {$ligne->motif}"),
        };
    }

    private function titre(Campaign $c): string
    {
        return "#{$c->id} {$c->name} (fin {$c->end_date->format('d/m/Y')})";
    }
}

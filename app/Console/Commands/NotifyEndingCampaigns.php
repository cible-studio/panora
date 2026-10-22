<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\RelanceFinCampagne;
use App\Services\Diffusion\RelanceFinCampagneService;
use Illuminate\Console\Command;

/**
 * Prévient les clients dont la campagne se termine dans N jours (J-7).
 *
 * Refaite le 2026-10-21 : toute la logique (destinataire, exclusions,
 * mode test, envoi par Brevo, journal) est dans
 * RelanceFinCampagneService. Un seul mail par campagne, garanti par le
 * journal relances_fin_campagne (et non plus par un drapeau en cache).
 *
 * Planifiée chaque jour à 10h (routes/console.php § 9).
 *
 *   php artisan campaigns:notify-ending --dry-run      ce qui partirait aujourd'hui
 *   php artisan campaigns:notify-ending --campagne=42  traite une campagne précise,
 *                                                      quelle que soit sa date de fin
 */
class NotifyEndingCampaigns extends Command
{
    protected $signature = 'campaigns:notify-ending
                            {--days= : Jours avant la fin (défaut : 7, config diffusion.fin_campagne.jours)}
                            {--campagne= : ID d\'une campagne à traiter, quelle que soit sa date de fin}
                            {--dry-run : Affiche sans envoyer}';

    protected $description = 'Prévient par mail les clients dont la campagne se termine dans N jours';

    public function handle(RelanceFinCampagneService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $jours  = $this->option('days') !== null ? (int) $this->option('days') : $service->jours();

        if ($id = $this->option('campagne')) {
            $campagne = Campaign::with(['client.contacts', 'commercial', 'user'])->find($id);
            if (!$campagne || !$campagne->end_date) {
                $this->error("Campagne #{$id} introuvable ou sans date de fin.");

                return self::FAILURE;
            }
            $campagnes = collect([$campagne]);
        } else {
            $campagnes = $service->campagnesDuJour($jours);
        }

        $this->line(sprintf(
            'Fin de campagne J-%d — mode %s — %d campagne(s)%s',
            $jours,
            $service->estModeTest() ? 'TEST (adresses de test uniquement)' : 'PRODUCTION',
            $campagnes->count(),
            $dryRun ? ' — simulation' : ''
        ));

        $bilan = ['envoye' => 0, 'exclu' => 0, 'echec' => 0];

        foreach ($campagnes as $campagne) {
            $titre = "#{$campagne->id} {$campagne->name} (fin {$campagne->end_date->format('d/m/Y')})";

            if ($dryRun) {
                $a = $service->analyser($campagne);
                $this->line($a['statut']
                    ? "  [—] {$titre} : {$a['motif']}"
                    : "  [→] {$titre} → {$a['destinataire']} · copie " . (implode(', ', $a['copie']) ?: '—') . " · réponse à {$a['repondre_a']}");
                continue;
            }

            $ligne = $service->traiter($campagne);
            $bilan[$ligne->statut] = ($bilan[$ligne->statut] ?? 0) + 1;

            match ($ligne->statut) {
                RelanceFinCampagne::STATUT_ENVOYE => $this->info("  ✓ {$titre} → {$ligne->destinataire} ({$ligne->canal})" . ($ligne->motif ? " — {$ligne->motif}" : '')),
                RelanceFinCampagne::STATUT_EXCLU  => $this->line("  — {$titre} : {$ligne->motif}"),
                default                           => $this->warn("  ✗ {$titre} : {$ligne->motif}"),
            };
        }

        if (!$dryRun) {
            $this->line('');
            $this->info("Bilan : {$bilan['envoye']} envoyé(s) · {$bilan['exclu']} non envoyé(s) · {$bilan['echec']} à retenter");
        }

        return self::SUCCESS;
    }
}

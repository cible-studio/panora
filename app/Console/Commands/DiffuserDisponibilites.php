<?php

namespace App\Console\Commands;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionCalendrier;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Diffusion automatique des disponibilités aux clients (2026-09-29).
 *
 * Planifiée chaque jour (cf. routes/console.php) :
 *   09:00  dispos:diffuser --preparer   génère le PDF si c'est un jour d'envoi
 *   10:00  dispos:diffuser              envoie (réutilise le PDF de 9h)
 *   chaque minute  dispos:diffuser --demandes   traite les envois manuels
 *                                                demandés depuis l'écran admin
 *
 * Ne fait rien si ce n'est pas un jour d'envoi, si DIFFUSION_AUTO=false,
 * ou si le créneau est déjà parti en automatique.
 *
 * --simuler=2026-11-02 affiche ce que ferait la commande ce jour-là,
 * sans rien générer ni envoyer.
 */
class DiffuserDisponibilites extends Command
{
    protected $signature = 'dispos:diffuser
        {--preparer : Génère seulement le PDF (lancé à 9h)}
        {--demandes : Traite les envois manuels demandés depuis l\'écran admin}
        {--simuler= : Date (AAAA-MM-JJ) — affiche la décision sans rien faire}';

    protected $description = 'Diffuse les disponibilités aux clients via Brevo (le 1er et le 15, jours ouvrables)';

    public function handle(DiffusionDisponibilitesService $diffusion): int
    {
        if ($date = $this->option('simuler')) {
            return $this->simuler($diffusion, Carbon::parse($date));
        }

        if ($this->option('demandes')) {
            $n = $diffusion->traiterDemandes();
            if ($n > 0) {
                $this->line("{$n} demande(s) d'envoi manuel traitée(s).");
            }
            return self::SUCCESS;
        }

        $envoi = $diffusion->executerAutomatique(now(), (bool) $this->option('preparer'));

        if (!$envoi) {
            $this->line('Rien à faire aujourd\'hui (pas un jour d\'envoi, automatique désactivé, ou créneau déjà parti).');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Envoi #%d — créneau %s — statut : %s — %d panneaux, %d destinataires%s',
            $envoi->id,
            $envoi->creneau->toDateString(),
            $envoi->statut,
            $envoi->nb_panneaux,
            $envoi->nb_destinataires,
            $envoi->erreur ? ' — ' . $envoi->erreur : ''
        ));

        return $envoi->statut === DiffusionEnvoi::STATUT_ECHEC ? self::FAILURE : self::SUCCESS;
    }

    private function simuler(DiffusionDisponibilitesService $diffusion, Carbon $jour): int
    {
        $cal = $diffusion->calendrier();

        $this->info('Simulation du ' . $jour->translatedFormat('l d/m/Y'));
        $this->line('  Mode         : ' . ($diffusion->estModeTest() ? 'TEST (liste Tests internes)' : 'PRODUCTION (clients)'));
        $this->line('  Automatique  : ' . (config('diffusion.auto') ? 'activé' : 'désactivé (DIFFUSION_AUTO=false)'));
        $this->line('  Jour ouvrable: ' . ($cal->estOuvrable($jour) ? 'oui' : 'non'));

        $creneau = $cal->creneauDuJour($jour);
        if (!$creneau) {
            $p = $cal->prochainEnvoi($jour);
            $this->line('  → Pas d\'envoi ce jour-là. Prochain : '
                . $p['date_envoi']->format('d/m/Y') . ' (créneau du ' . $p['creneau']->format('d/m') . ')');
            return self::SUCCESS;
        }

        [$debut, $fin] = $cal->periode($creneau);
        $this->line('  → ENVOI : créneau du ' . $creneau->format('d/m/Y')
            . ', période ' . DiffusionCalendrier::libellePeriode($debut, $fin));
        $this->line('  Destinataires: ' . ($diffusion->estModeTest()
            ? 'la liste Tests internes de Brevo'
            : $diffusion->destinataires()->count() . ' client(s)'));

        return self::SUCCESS;
    }
}

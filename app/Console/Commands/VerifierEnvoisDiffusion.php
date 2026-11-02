<?php

namespace App\Console\Commands;

use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Console\Command;

/**
 * Relit chez Brevo l'état des envois de disponibilités récents (2026-11-02).
 *
 * Brevo accepte l'ordre d'envoi puis peut suspendre la campagne après coup
 * (quota épuisé, trop d'adresses invalides) : l'envoi passe alors
 * « Suspendu par Brevo » dans le journal, avec la cause probable, et
 * l'équipe reçoit une alerte.
 *
 * Planifiée toutes les 15 minutes (cf. routes/console.php), sur les envois
 * des dernières 24 h. --heures=48 pour rattraper des envois plus anciens,
 * une fois, à la main.
 */
class VerifierEnvoisDiffusion extends Command
{
    protected $signature = 'dispos:verifier-envois
        {--heures=24 : Relit les envois partis depuis ce nombre d\'heures}';

    protected $description = 'Repère les envois de disponibilités suspendus par Brevo après leur départ';

    public function handle(DiffusionDisponibilitesService $diffusion): int
    {
        $suspendus = $diffusion->verifierEnvoisRecents(max(1, (int) $this->option('heures')));
        if ($suspendus > 0) {
            $this->warn("{$suspendus} envoi(s) suspendu(s) par Brevo : journal mis à jour, équipe prévenue.");
        }

        return self::SUCCESS;
    }
}

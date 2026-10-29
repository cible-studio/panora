<?php

namespace App\Console\Commands;

use App\Services\Diffusion\DiffusionDisponibilitesService;
use Illuminate\Console\Command;

/**
 * Refait les catalogues de disponibilités envoyés dont le PDF a disparu
 * (2026-10-28) : même fichier, même lien, statut inchangé. Depuis le
 * 2026-10-29, un envoi porte jusqu'à trois catalogues (complet, Abidjan,
 * intérieur) : seuls les fichiers réellement manquants sont refaits.
 *
 * Planifiée chaque minute (cf. routes/console.php) : sans rien à refaire,
 * elle ne fait qu'une requête. Après une mise en ligne qui a effacé les
 * fichiers, les liens des clients remarchent en quelques minutes.
 */
class ReparerPdfDiffusion extends Command
{
    protected $signature = 'dispos:reparer-pdf';

    protected $description = 'Refait les PDF de disponibilités envoyés qui ont disparu du disque (même lien pour les clients)';

    public function handle(DiffusionDisponibilitesService $diffusion): int
    {
        $refaits = $diffusion->reparerPdfsManquants();
        if ($refaits > 0) {
            $this->info("{$refaits} catalogue(s) refait(s) : les liens reçus par les clients remarchent.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DiffusionEnvoi;
use App\Services\Diffusion\DiffusionCalendrier;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lien public du PDF envoyé aux clients avec la diffusion des
 * disponibilités (2026-09-29).
 *
 * Sécurité : le jeton fait 48 caractères aléatoires, impossible à deviner.
 * Le PDF ne contient aucun prix ni aucune donnée client — seulement le
 * catalogue des emplacements disponibles, destiné à être diffusé.
 */
class PublicDiffusionController extends Controller
{
    /**
     * Trois jetons possibles depuis le 2026-10-29 : le catalogue complet,
     * celui d'Abidjan et celui de l'intérieur du pays. Chacun a son propre
     * jeton, donc Brevo compte les clics séparément et on sait laquelle des
     * deux zones intéresse les clients.
     *
     * @var array<string, array{0: string, 1: ?string}>  colonne du jeton →
     *      [colonne du fichier, suffixe du nom de téléchargement]
     */
    private const CATALOGUES = [
        'pdf_token'           => ['pdf_path', null],
        'pdf_token_abidjan'   => ['pdf_path_abidjan', '-abidjan'],
        'pdf_token_interieur' => ['pdf_path_interieur', '-interieur'],
    ];

    public function pdf(string $token): Response
    {
        // Une seule requête pour les trois jetons : ils sont uniques et
        // tirés sur 48 caractères, aucune collision possible entre eux.
        $envoi = DiffusionEnvoi::where(function ($q) use ($token) {
            foreach (array_keys(self::CATALOGUES) as $colonne) {
                $q->orWhere($colonne, $token);
            }
        })->first();

        $chemin = null;
        $suffixe = null;

        if ($envoi) {
            foreach (self::CATALOGUES as $colonneJeton => [$colonneChemin, $suffixeZone]) {
                if ($envoi->{$colonneJeton} === $token) {
                    $chemin  = $envoi->{$colonneChemin};
                    $suffixe = $suffixeZone;
                    break;
                }
            }
        }

        if (!$chemin || !Storage::disk('local')->exists($chemin)) {
            abort(404, 'Ce catalogue de disponibilités n\'existe plus.');
        }

        $nom = 'disponibilites-cible' . $suffixe . '-'
             . str_replace(' ', '-', DiffusionCalendrier::libelleMois($envoi->periode_debut))
             . ($envoi->periode_debut->day === 15 ? '-seconde-quinzaine' : '') . '.pdf';

        // inline : le PDF s'ouvre dans le navigateur, le client choisit
        // ensuite de l'enregistrer.
        return Storage::disk('local')->response($chemin, $nom, [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }
}

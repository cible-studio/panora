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
    public function pdf(string $token): Response
    {
        $envoi = DiffusionEnvoi::where('pdf_token', $token)->first();

        if (!$envoi || !$envoi->pdf_path || !Storage::disk('local')->exists($envoi->pdf_path)) {
            abort(404, 'Ce catalogue de disponibilités n\'existe plus.');
        }

        $nom = 'disponibilites-cible-' . str_replace(' ', '-', DiffusionCalendrier::libelleMois($envoi->periode_debut))
             . ($envoi->periode_debut->day === 15 ? '-seconde-quinzaine' : '') . '.pdf';

        // inline : le PDF s'ouvre dans le navigateur, le client choisit
        // ensuite de l'enregistrer.
        return Storage::disk('local')->response($envoi->pdf_path, $nom, [
            'Content-Type'  => 'application/pdf',
            'Cache-Control' => 'private, max-age=3600',
        ], 'inline');
    }
}

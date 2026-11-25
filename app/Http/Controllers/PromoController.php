<?php

namespace App\Http\Controllers;

use App\Services\Promos\PromoService;
use Illuminate\Http\Request;

/**
 * Page d'arrivée des mails de promotion (2026-11-25) : /offre/{code}[/{format}].
 * Compte le clic, puis ouvre la réponse préremplie vers le service
 * commercial (cf. PromoService, config/promos.php).
 */
class PromoController extends Controller
{
    public function show(Request $request, PromoService $promos, string $code, ?string $format = null)
    {
        $promo = $promos->promo($code);
        abort_if($promo === null, 404);
        // Un format inconnu (lien abîmé) mène à la page sans format, pas à une erreur.
        if ($format !== null && !isset($promo['formats'][$format])) {
            $format = null;
        }

        $jeton = $promos->compter($code, $format, $request);

        return response()->view('public.promo', [
            'promo'   => $promo,
            'format'  => $format,
            'jeton'   => $jeton,
            'reponse' => $promos->lienReponse($promo, $format),
            'parFormat' => collect($promo['formats'])->map(fn ($f, $cle) => $f + ['reponse' => $promos->lienReponse($promo, $cle)])->all(),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /**
     * La page s'est affichée dans un navigateur (appel de son script) : le
     * clic vient d'un client, pas d'un filtre qui vérifie les liens (25/11).
     */
    public function vu(Request $request, PromoService $promos)
    {
        $promos->confirmer((string) $request->input('jeton', ''));

        return response()->noContent();
    }
}

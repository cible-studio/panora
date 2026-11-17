<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Barometre\BarometreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Porte d'entrée du script du questionnaire Google (2026-11-17) —
 * docs/outils/barometre-panora.gs.
 *
 *   POST /api/barometre/reponse : une réponse vient d'arriver (nom de la
 *        structure, heure, référence). Panora ne reçoit RIEN d'autre : les
 *        réponses restent dans la feuille Google.
 *   GET  /api/barometre/etat    : date limite et clôture — le script ferme
 *        le formulaire tout seul quand elle est passée.
 *
 * Sécurité : jeton secret dans l'en-tête Authorization (BAROMETRE_JETON_GOOGLE),
 * jamais dans l'adresse ; absent ou faux → 404, pour ne pas révéler la
 * route. Débit limité (routes/admin.php). CSRF exempté (bootstrap/app.php) :
 * c'est un script, pas un navigateur, qui appelle.
 */
class BarometreGoogleController extends Controller
{
    public function reponse(Request $request, BarometreService $service): JsonResponse
    {
        $this->authentifier($request);

        $data = $request->validate([
            'reference'  => ['required', 'string', 'max:191'],
            'structure'  => ['present', 'nullable', 'string', 'max:500'],
            'repondu_le' => ['nullable', 'date'],
        ]);

        $reponse = $service->enregistrerReponse(
            $data['reference'],
            (string) ($data['structure'] ?? ''),
            isset($data['repondu_le']) ? Carbon::parse($data['repondu_le']) : null,
        );

        return response()->json([
            'ok'      => true,
            'reconnu' => $reponse->client_id !== null,
            'test'    => $reponse->test,
        ]);
    }

    public function etat(Request $request, BarometreService $service): JsonResponse
    {
        $this->authentifier($request);
        $edition = $service->edition();

        return response()->json([
            'edition'     => $edition->edition,
            'date_limite' => $edition->date_limite->toDateString(),
            'fin'         => $edition->finDuDelai()->toIso8601String(),
            'clos'        => $edition->estClose(),
        ]);
    }

    private function authentifier(Request $request): void
    {
        $attendu = (string) config('barometre.jeton_google');

        if ($attendu === '' || !hash_equals($attendu, (string) $request->bearerToken())) {
            abort(404);
        }
    }
}

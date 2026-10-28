<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Contacts\ContactsGmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Porte d'entrée du Google Sheets (2026-10-28) : le bouton « Envoyer à
 * Panora » et les listes que le Sheets reprend de Panora.
 *
 * Sécurité : jeton secret dans l'en-tête Authorization (GMAIL_SYNC_TOKEN),
 * jamais dans l'adresse. Jeton absent ou faux : 404, pour ne pas révéler
 * que la route existe. L'expéditeur doit figurer dans GMAIL_SYNC_AUTORISES.
 * CSRF désactivé (cf. bootstrap/app.php) : c'est un script, pas un
 * navigateur, qui appelle.
 */
class ContactsGmailController extends Controller
{
    /** Secteurs, statuts, messageries personnelles, personnes autorisées. */
    public function listes(Request $request): JsonResponse
    {
        $this->authentifier($request);

        return response()->json([
            'secteurs'                 => Client::SECTORS,
            'statuts'                  => array_values(Client::STATUTS),
            'messageries_personnelles' => config('contacts.messageries_personnelles'),
            'domaines_internes'        => config('contacts.domaines_internes'),
            'autorises'                => config('contacts.gmail.autorises'),
            'max_lignes'               => (int) config('contacts.gmail.max_lignes'),
        ]);
    }

    /** Envoi (ou vérification sans écriture) d'un lot de contacts. */
    public function envoyer(Request $request, ContactsGmailService $service): JsonResponse
    {
        $this->authentifier($request);

        $par = mb_strtolower(trim((string) $request->input('envoye_par')));
        if (!in_array($par, config('contacts.gmail.autorises', []), true)) {
            return response()->json(['ok' => false, 'erreur' => ($par ?: 'Cette adresse') . " n'est pas autorisée à envoyer à Panora."], 403);
        }

        $contacts = $request->input('contacts');
        $max = (int) config('contacts.gmail.max_lignes');
        if (!is_array($contacts) || !array_is_list($contacts) || $contacts === []) {
            return response()->json(['ok' => false, 'erreur' => 'Aucun contact reçu.'], 422);
        }
        if (count($contacts) > $max) {
            return response()->json(['ok' => false, 'erreur' => "Trop de lignes : {$max} au plus par envoi."], 422);
        }

        return response()->json(['ok' => true] + $service->traiter($contacts, $par, $request->boolean('simulation')));
    }

    private function authentifier(Request $request): void
    {
        $attendu = (string) config('contacts.gmail.token');

        if ($attendu === '' || !hash_equals($attendu, (string) $request->bearerToken())) {
            abort(404);
        }
    }
}

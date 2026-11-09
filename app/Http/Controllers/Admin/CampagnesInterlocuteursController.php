<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\ClientContact;
use App\Rules\InterlocuteurDuClient;
use App\Services\Campagnes\CampagnesSansInterlocuteur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Écran « Interlocuteurs à renseigner » (2026-11-09) : chaque commercial
 * choisit ou saisit l'interlocuteur de ses campagnes sur une seule page,
 * sans ouvrir chaque campagne. Le commercial ne voit que les siennes ; il
 * n'a pas le droit de modifier une campagne, seulement ce champ
 * (CampaignPolicy::definirInterlocuteur).
 */
class CampagnesInterlocuteursController extends Controller
{
    public function index(Request $request, CampagnesSansInterlocuteur $service): View
    {
        $user = $request->user();
        $estCommercial = $user->role === UserRole::COMMERCIAL;
        $exclues = $service->adressesExclues();

        $lignes = $service->campagnes($estCommercial ? (int) $user->id : null)
            ->map(fn (Campaign $c) => [
                'id'           => $c->id,
                'client_id'    => $c->client_id,
                'client'       => $c->client->name,
                'campagne'     => $c->name,
                'echeance'     => $c->end_date?->format('d/m/Y') ?? '—',
                'reconduction' => $c->estAnnuelle(),
                'commercial'   => $service->nomCommercial($c),
                'contacts'     => $service->contactsProposes($c->client, $exclues)
                    ->map(fn (ClientContact $k) => [
                        'id'      => $k->id,
                        'libelle' => $k->name . ($k->position ? ' — ' . $k->position : '') . ' (' . mb_strtolower(trim($k->email)) . ')',
                    ])
                    ->values(),
            ]);

        return view('admin.campaigns.interlocuteurs', [
            'lignes'      => $lignes,
            'commerciaux' => $estCommercial ? collect() : $lignes->pluck('commercial')->unique()->sort()->values(),
        ]);
    }

    public function update(Request $request, Campaign $campaign, CampagnesSansInterlocuteur $service): JsonResponse
    {
        $this->authorize('definirInterlocuteur', $campaign);

        $data = $request->validate(
            ['client_contact_id' => InterlocuteurDuClient::regles($campaign->client_id)],
            InterlocuteurDuClient::MESSAGES
        );

        $contact = ClientContact::find($data['client_contact_id']);
        if (!$campaign->client?->interlocuteurRetenu($contact)) {
            return response()->json(['message' => "Cette personne n'a pas d'adresse mail valide."], 422);
        }
        if ($service->adressesExclues()->has(mb_strtolower(trim($contact->email)))) {
            return response()->json(['message' => 'Cette adresse ne reçoit plus nos mails (morte ou désinscrite). Choisissez une autre personne.'], 422);
        }

        $campaign->update(['client_contact_id' => $contact->id, 'updated_by' => $request->user()->id]);

        return response()->json(['ok' => true]);
    }
}

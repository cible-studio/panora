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
 * Écran « Interlocuteurs des campagnes » (2026-11-09) : l'interlocuteur
 * actuel de chaque campagne en cours, à choisir, créer ou modifier sur une
 * seule page. Rien n'est enregistré sans un clic sur Appliquer. Le commercial
 * ne voit que ses campagnes ; il n'a pas le droit de modifier une campagne,
 * seulement ce champ (CampaignPolicy::definirInterlocuteur).
 */
class CampagnesInterlocuteursController extends Controller
{
    public function index(Request $request, CampagnesSansInterlocuteur $service): View
    {
        $user = $request->user();
        $estCommercial = $user->role === UserRole::COMMERCIAL;
        $exclues = $service->adressesExclues();

        $lignes = $service->enCours($estCommercial ? (int) $user->id : null)
            ->map(function (Campaign $c) use ($service, $exclues) {
                $actuel = $service->interlocuteurActuel($c);

                return [
                    'id'            => $c->id,
                    'client_id'     => $c->client_id,
                    'client'        => $c->client->name,
                    'campagne'      => $c->name,
                    'echeance'      => $c->end_date?->format('d/m/Y') ?? '—',
                    'reconduction'  => $c->estAnnuelle(),
                    'commercial'    => $service->nomCommercial($c),
                    'interlocuteur' => $actuel ? $this->fiche($actuel) + [
                        'exclu' => $exclues->has(mb_strtolower(trim($actuel->email))),
                    ] : null,
                    'contacts'      => $service->contactsProposes($c->client, $exclues)
                        ->map(fn (ClientContact $k) => ['id' => $k->id, 'libelle' => $service->libelleContact($k)])
                        ->values(),
                ];
            })
            // Celles à renseigner d'abord ; l'échéance reste l'ordre dans chaque groupe.
            ->sortBy(fn (array $l) => $l['interlocuteur'] ? 1 : 0)
            ->values();

        return view('admin.campaigns.interlocuteurs', [
            'lignes'      => $lignes,
            'aRenseigner' => $lignes->whereNull('interlocuteur')->count(),
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

        return response()->json(['ok' => true, 'libelle' => $service->libelleContact($contact)] + $this->fiche($contact));
    }

    /** @return array{id: int, nom: string, detail: string} */
    private function fiche(ClientContact $contact): array
    {
        return [
            'id'     => $contact->id,
            'nom'    => $contact->name,
            'detail' => implode(' · ', array_filter([$contact->position, mb_strtolower(trim($contact->email)), $contact->phone])),
        ];
    }
}

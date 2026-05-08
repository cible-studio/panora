<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Gestion des interlocuteurs d'un client. Endpoints AJAX-first pour
 * permettre l'édition inline depuis la fiche client (pas de full reload).
 *
 * Toutes les actions sont gardées par scope client → un contact ne peut
 * pas être muté hors de son client (paramètre `Client $client` dans les
 * routes parent imbriquée).
 */
class ClientContactController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $this->validateData($request);
        $data['client_id'] = $client->id;

        $contact = ClientContact::create($data);

        // Si on marque ce nouveau contact primary, on s'assure qu'il
        // est seul à l'être (atomique via makePrimary).
        if (!empty($data['is_primary'])) {
            $contact->makePrimary();
        } elseif (!$client->contacts()->where('is_primary', true)->exists()) {
            // Aucun primary existant → on désigne ce nouveau comme primary
            // par défaut, sinon le client n'aurait aucun contact "officiel".
            $contact->makePrimary();
        }

        Log::info('client.contact.created', [
            'client_id'  => $client->id,
            'contact_id' => $contact->id,
            'role'       => $contact->role,
            'by'         => auth()->id(),
        ]);

        return $this->respond($request, $contact, 'Interlocuteur ajouté.');
    }

    public function update(Request $request, Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);

        $data = $this->validateData($request, $contact->id);

        $contact->update($data);

        if (!empty($data['is_primary'])) {
            $contact->makePrimary();
        }

        return $this->respond($request, $contact, 'Interlocuteur mis à jour.');
    }

    public function destroy(Request $request, Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);

        $wasPrimary = $contact->is_primary;
        $contact->delete();

        // Si on supprimait le contact principal, on en désigne un autre
        // automatiquement pour ne pas laisser le client sans référent.
        if ($wasPrimary) {
            $next = $client->contacts()->whereNull('deleted_at')->first();
            $next?->makePrimary();
        }

        Log::info('client.contact.deleted', [
            'client_id'  => $client->id,
            'contact_id' => $contact->id,
            'was_primary'=> $wasPrimary,
            'by'         => auth()->id(),
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'message' => 'Interlocuteur supprimé.']);
        }
        return back()->with('success', 'Interlocuteur supprimé.');
    }

    /**
     * Action explicite "définir comme principal" — utile depuis l'UI sans
     * passer par un PUT complet du contact.
     */
    public function setPrimary(Request $request, Client $client, ClientContact $contact)
    {
        abort_unless($contact->client_id === $client->id, 404);

        $contact->makePrimary();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $contact->name . ' est désormais le contact principal.',
            ]);
        }
        return back()->with('success', 'Contact principal mis à jour.');
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'                   => 'required|string|max:120',
            'email'                  => 'nullable|email|max:150',
            'phone'                  => 'nullable|string|max:30',
            'role'                   => 'nullable|string|in:' . implode(',', array_keys(ClientContact::ROLES)),
            'position'               => 'nullable|string|max:100',
            'is_primary'             => 'sometimes|boolean',
            'receives_notifications' => 'sometimes|boolean',
            'notes'                  => 'nullable|string|max:1000',
        ]);
    }

    private function respond(Request $request, ClientContact $contact, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => $message,
                'contact' => [
                    'id'                       => $contact->id,
                    'name'                     => $contact->name,
                    'email'                    => $contact->email,
                    'phone'                    => $contact->phone,
                    'role'                     => $contact->role,
                    'role_label'               => $contact->role_label,
                    'position'                 => $contact->position,
                    'is_primary'               => $contact->is_primary,
                    'receives_notifications'   => $contact->receives_notifications,
                    'notes'                    => $contact->notes,
                ],
            ]);
        }
        return back()->with('success', $message);
    }
}

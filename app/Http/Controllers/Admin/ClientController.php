<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Notifications\ClientAccountCreated;

class ClientController extends Controller
{

    public function index(Request $request)
    {
        $query = Client::withCount(['campaigns', 'reservations'])
            ->with(['campaigns' => function($q) {
                $q->whereIn('status', ['actif', 'pose']);
            }]);

        // Filtres
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('ncc', 'like', "%{$request->search}%")
                ->orWhere('email', 'like', "%{$request->search}%")
                ->orWhere('contact_name', 'like', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%");
            });
        }
        if ($request->sector) {
            $query->where('sector', $request->sector);
        }

        // Tri
        $sort = $request->sort ?? 'name';
        $query->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');

        // Statistiques
        $stats = [
            'total' => Client::count(),
            'actifs' => Client::whereHas('campaigns', function($q) {
                $q->whereIn('status', ['actif', 'pose']);
            })->count(),
            'ca_total' => Client::with('campaigns')->get()->sum(function($client) {
                return $client->campaigns->sum('total_amount');
            }),
        ];

        $clients = $query->paginate(20)->withQueryString();
        $sectors = Client::SECTORS;

        if ($request->ajax()) {
            return response()->json([
                'html' => view('admin.clients.partials.table-rows', compact('clients'))->render(),
                'pagination' => $clients->links()->render(),
                'total' => $clients->total(),
            ]);
        }

        return view('admin.clients.index', compact('clients', 'stats', 'sectors'));
    }

    // ── CRÉER UN COMPTE CLIENT ────────────────────────────────────
    /**
     * POST /admin/clients/{client}/account
     * Crée le compte espace client (mot de passe initial généré).
     */
    public function createAccount(Client $client)
    {
        if ($client->hasAccount()) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Ce client a déjà un compte actif.']);
            }
            return back()->with('error', 'Ce client a déjà un compte actif.');
        }

        if (empty($client->email)) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Ce client n\'a pas d\'email. Ajoutez-en un d\'abord.']);
            }
            return back()->with('error', 'Ce client n\'a pas d\'email. Ajoutez-en un d\'abord.');
        }

        $motDePasseInitial = $this->generateReadablePassword();

        $client->update([
            'password'             => Hash::make($motDePasseInitial),
            'must_change_password' => true,
            'password_changed_at'  => null,
        ]);

        try {
            \Mail::to($client->email)->send(new \App\Mail\ClientAccountMail($client, $motDePasseInitial));
            $msg = "✅ Compte créé. Identifiants envoyés à {$client->email}.";
        } catch (\Exception $e) {
            $msg = "✅ Compte créé. Impossible d'envoyer l'email. Mot de passe initial : {$motDePasseInitial}";
        }

        \Log::info('client.account_created', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }


    public function store(StoreClientRequest $request)
    {
        $client = Client::create($request->validated());

        Log::info('client.created', [
            'client_id' => $client->id,
            'ncc'       => $client->ncc,
            'user_id'   => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', "Client {$client->name} créé avec succès. NCC : {$client->ncc}");
    }

    public function show(Client $client)
    {
        $client->load([
            'reservations' => fn($q) => $q->latest()->limit(5),
            'campaigns'    => fn($q) => $q->latest()->limit(8),
            'invoices'     => fn($q) => $q->latest()->limit(5),
        ]);

        // Montant total facturé — requête directe sans N+1
        $totalFacture = $client->invoices()->sum('amount_ttc');

        $sectors = Client::SECTORS;

        return view('admin.clients.show', compact('client', 'totalFacture', 'sectors'));
    }

    public function edit(Client $client)
    {
        $sectors = Client::SECTORS;
        return view('admin.clients.edit', compact('client', 'sectors'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client->update($request->validated());

        Log::info('client.updated', [
            'client_id' => $client->id,
            'user_id'   => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client mis à jour avec succès.');
    }

    public function destroy(Client $client)
    {
        // Bloquer suppression si campagnes actives
        if ($client->hasActiveCampaigns()) {
            return back()->with('error',
                'Impossible de supprimer ce client : il a des campagnes actives en cours.'
            );
        }

        $name = $client->name;
        $client->delete();

        Log::info('client.deleted', [
            'client_id'   => $client->id,
            'client_name' => $name,
            'user_id'     => auth()->id(),
        ]);

        return redirect()
            ->route('admin.clients.index')
            ->with('success', "Client {$name} supprimé.");
    }

    // ── RÉINITIALISER MOT DE PASSE ────────────────────────────────
    /**
     * POST /admin/clients/{client}/account/reset
     * Génère un nouveau mot de passe et l'envoie au client.
     */
    public function resetPassword(Client $client)
    {
        if (!$client->hasAccount()) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Ce client n\'a pas encore de compte.']);
            }
            return back()->with('error', 'Ce client n\'a pas encore de compte.');
        }

        if (empty($client->email)) {
            if (request()->ajax()) {
                return response()->json(['success' => false, 'message' => 'Ce client n\'a pas d\'email.']);
            }
            return back()->with('error', 'Ce client n\'a pas d\'email.');
        }

        $nouveauMotDePasse = $this->generateReadablePassword();

        $client->update([
            'password'             => Hash::make($nouveauMotDePasse),
            'must_change_password' => true,
            'password_changed_at'  => null,
        ]);

        try {
            \Mail::to($client->email)->send(new \App\Mail\ClientAccountMail($client, $nouveauMotDePasse, true));
            $msg = "🔑 Mot de passe réinitialisé. Envoyé à {$client->email}.";
        } catch (\Exception $e) {
            $msg = "🔑 Mot de passe réinitialisé. Erreur email. MDP : {$nouveauMotDePasse}";
        }

        \Log::info('client.password_reset', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }
        return back()->with('success', $msg);
    }

    
    // ── SUPPRIMER LE COMPTE ───────────────────────────────────────
    /**
     * DELETE /admin/clients/{client}/account
     * Désactive l'accès espace client (ne supprime pas le client).
     */
    public function revokeAccount(Client $client)
    {
        $client->update([
            'password'      => null,
            'remember_token'=> null,
        ]);

        \Log::info('client.account_revoked', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => 'Accès espace client révoqué.']);
        }
        return back()->with('success', 'Accès espace client révoqué.');
    }
    
    // ── HELPER PRIVÉ ─────────────────────────────────────────────
    private function generateReadablePassword(): string
    {
        // Format lisible : 3 mots + chiffres ex: "Bleu-Soleil-42"
        $adj    = ['Bleu', 'Rouge', 'Vert', 'Grand', 'Vif', 'Fort', 'Clair', 'Beau'];
        $nom    = ['Soleil', 'Lion', 'Fleuve', 'Arbre', 'Aigle', 'Mont', 'Pont', 'Phare'];
        $chiffr = rand(10, 99);
    
        return $adj[array_rand($adj)] . '-' . $nom[array_rand($nom)] . '-' . $chiffr;
    }
 
}
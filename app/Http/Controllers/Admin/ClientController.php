<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Imports\ClientsImport;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Services\AlertService;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ClientController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // INDEX
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $query = Client::withCount(['campaigns', 'reservations'])
            ->withCount([
                'campaigns as active_campaigns_count' => function ($q) {
                    $q->whereIn('status', ['actif', 'pose']);
                }
            ])
            ->with([
                'campaigns' => function ($q) {
                    $q->whereIn('status', ['actif', 'pose']);
                }
            ]);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
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

        $sort = $request->sort ?? 'name';
        $query->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');

        $stats = [
            'total' => Client::count(),
            'actifs' => Client::whereHas('campaigns', fn($q) => $q->whereIn('status', ['actif', 'pose']))->count(),
            'ca_total' => \App\Models\Campaign::sum('total_amount'),
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

    public function create()
    {
        $sectors = Client::SECTORS;
        return view('admin.clients.create', compact('sectors'));
    }

    // ══════════════════════════════════════════════════════════════
    // STORE
    // ══════════════════════════════════════════════════════════════

    public function store(StoreClientRequest $request)
    {
        $client = Client::create($request->validated());

        Log::info('client.created', [
            'client_id' => $client->id,
            'ncc' => $client->ncc,
            'user_id' => auth()->id(),
        ]);

        AlertService::create(
            'client',
            'info',
            '👥 Nouveau client — ' . $client->name,
            auth()->user()->name . ' a créé le client ' . $client->name . ' (NCC : ' . $client->ncc . ').',
            $client
        );
        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', "Client {$client->name} créé avec succès. NCC : {$client->ncc}");
    }

    // ══════════════════════════════════════════════════════════════
    // SHOW
    // ══════════════════════════════════════════════════════════════

    public function show(Client $client)
    {
        $client->load([
            'reservations' => fn($q) => $q->withCount('panels')->latest()->limit(5),
            'campaigns' => fn($q) => $q->latest()->limit(8),
            'invoices' => fn($q) => $q->latest()->limit(5),
        ]);

        $totalFacture = $client->invoices()->sum('amount_ttc');
        $sectors = Client::SECTORS;

        // ── Inventaire panneaux du client (Dev A) ─────────────────
        $panneauxReservations = \App\Models\ReservationPanel::with([
                'panel.commune', 'panel.format', 'reservation',
            ])
            ->whereHas('reservation', fn($q) => $q->where('client_id', $client->id))
            ->get()
            ->map(fn($rp) => [
                'panel'            => $rp->panel,
                'source'           => 'reservation',
                'reference_source' => $rp->reservation->reference ?? '—',
                'source_id'        => $rp->reservation->id,
                'start_date'       => $rp->reservation->start_date,
                'end_date'         => $rp->reservation->end_date,
                'status'           => $rp->reservation->status->value ?? 'inconnu',
                'status_label'     => $rp->reservation->status->label() ?? '—',
            ]);

        $panneauxCampagnes = \App\Models\CampaignPanel::with([
            'panel.commune',
            'panel.format',
            'campaign',
        ])
            ->where('type', 'interne')
            ->whereHas('campaign', fn($q) => $q->where('client_id', $client->id))
            ->get()
            ->map(fn($cp) => [
                'panel' => $cp->panel,
                'source' => 'campaign',
                'reference_source' => $cp->campaign->name ?? '—',
                'source_id' => $cp->campaign->id,
                'start_date' => $cp->campaign->start_date,
                'end_date' => $cp->campaign->end_date,
                'status' => $cp->campaign->status->value ?? 'inconnu',
                'status_label' => $cp->campaign->status->label() ?? '—',
            ]);

        $panneauxClient = $panneauxCampagnes
            ->unique(fn($item) => $item['panel']?->id . '-' . $item['source_id'])
            ->filter(fn($item) => $item['panel'] !== null)
            ->sortBy('panel.reference')
            ->values();

        return view('admin.clients.show', compact(
            'client',
            'totalFacture',
            'sectors',
            'panneauxClient'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // EDIT / UPDATE
    // ══════════════════════════════════════════════════════════════

    public function edit(Client $client)
    {
        $sectors = Client::SECTORS;
        return view('admin.clients.edit', compact('client', 'sectors'));
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $oldName = $client->name;
        $client->update($request->validated());

        Log::info('client.updated', [
            'client_id' => $client->id,
            'user_id' => auth()->id(),
        ]);

        // Alerte modification client
        AlertService::create(
            'client',
            'info',
            '✏️ Client modifié — ' . $client->name,
            auth()->user()->name . ' a modifié le client ' . $oldName . '.',
            $client
        );

        return redirect()
            ->route('admin.clients.show', $client)
            ->with('success', 'Client mis à jour avec succès.');
    }
    // ══════════════════════════════════════════════════════════════
    // DESTROY
    // ══════════════════════════════════════════════════════════════

    public function destroy(Client $client)
    {
        if ($client->hasActiveCampaigns()) {
            return back()->with(
                'error',
                'Impossible de supprimer ce client : il a des campagnes actives en cours.'
            );
        }

        $name = $client->name;
        $client->delete();

        Log::info('client.deleted', [
            'client_id' => $client->id,
            'client_name' => $name,
            'user_id' => auth()->id(),
        ]);

        // Alerte suppression client
        AlertService::create(
            'client',
            'danger',
            '🗑 Client supprimé — ' . $name,
            auth()->user()->name . ' a supprimé le client ' . $name . '.',
            null
        );

        return redirect()
            ->route('admin.clients.index')
            ->with('success', "Client {$name} supprimé.");
    }

    // ══════════════════════════════════════════════════════════════
    // COMPTE CLIENT — CRÉER
    // ══════════════════════════════════════════════════════════════

    public function createAccount(Client $client)
    {
        if ($client->hasAccount()) {
            return $this->accountResponse(false, 'Ce client a déjà un compte actif.');
        }

        if (empty($client->email)) {
            return $this->accountResponse(false, "Ce client n'a pas d'email. Ajoutez-en un d'abord.");
        }

        $motDePasse = $this->generateReadablePassword();

        $client->update([
            'password' => Hash::make($motDePasse),
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        try {
            \Mail::to($client->email)->send(new \App\Mail\ClientAccountMail($client, $motDePasse));
            $msg = "✅ Compte créé. Identifiants envoyés à {$client->email}.";
        } catch (\Exception $e) {
            $msg = "✅ Compte créé. Erreur email. Mot de passe initial : {$motDePasse}";
        }

        Log::info('client.account_created', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        return $this->accountResponse(true, $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // COMPTE CLIENT — RESET MOT DE PASSE
    // ══════════════════════════════════════════════════════════════

    public function resetPassword(Client $client)
    {
        if (!$client->hasAccount()) {
            return $this->accountResponse(false, "Ce client n'a pas encore de compte.");
        }

        if (empty($client->email)) {
            return $this->accountResponse(false, "Ce client n'a pas d'email.");
        }

        $motDePasse = $this->generateReadablePassword();

        $client->update([
            'password' => Hash::make($motDePasse),
            'must_change_password' => true,
            'password_changed_at' => null,
        ]);

        try {
            \Mail::to($client->email)->send(
                new \App\Mail\ClientAccountMail($client, $motDePasse, true)
            );
            $msg = "🔑 Mot de passe réinitialisé. Envoyé à {$client->email}.";
        } catch (\Exception $e) {
            $msg = "🔑 Mot de passe réinitialisé. Erreur email. MDP : {$motDePasse}";
        }

        Log::info('client.password_reset', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        return $this->accountResponse(true, $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // COMPTE CLIENT — RÉVOQUER
    // ══════════════════════════════════════════════════════════════

    public function revokeAccount(Client $client)
    {
        $client->update([
            'password' => null,
            'remember_token' => null,
        ]);

        Log::info('client.account_revoked', ['client_id' => $client->id, 'user_id' => auth()->id()]);

        return $this->accountResponse(true, 'Accès espace client révoqué.');
    }

    // ══════════════════════════════════════════════════════════════
    // DONNÉES CLIENT (AJAX — autocomplete)
    // ══════════════════════════════════════════════════════════════

    public function getClientData(Client $client)
    {
        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'contact_name' => $client->contact_name,
            'has_account' => $client->hasAccount(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════

    /**
     * Réponse unifiée AJAX / web pour les actions de compte.
     */
    private function accountResponse(bool $success, string $message)
    {
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['success' => $success, 'message' => $message]);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }

    /**
     * Génère un mot de passe lisible de type "Bleu-Soleil-42".
     */
    private function generateReadablePassword(): string
    {
        return 'password';
    }



    // ══════════════════════════════════════════════════════════════
    // STORE RAPIDE (AJAX) ; CREER CLIENT DEPUIS SELECT2 (ex: lors de la création d'une reservation)
    // ══════════════════════════════════════════════════════════════


    public function storeQuick(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'nullable|email|unique:clients,email',
            'phone' => 'nullable|string|max:20',
            'contact_name' => 'nullable|string|max:150',
            'ncc' => 'nullable|string|max:50|unique:clients,ncc',

        ]);

        $client = Client::create($data);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'text' => $client->name,
            'ncc' => $client->ncc,

        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // IMPORT EXCEL — admin/clients/import
    // ══════════════════════════════════════════════════════════════════
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120', // 5 Mo
        ], [
            'file.required' => 'Veuillez sélectionner un fichier.',
            'file.mimes'    => 'Format invalide. Acceptés : .xlsx, .xls, .csv',
            'file.max'      => 'Fichier trop volumineux (max 5 Mo).',
        ]);

        $importer = new ClientsImport();

        try {
            Excel::import($importer, $request->file('file'));
        } catch (\Throwable $e) {
            Log::error('clients.import.failed', ['error' => $e->getMessage()]);
            return back()->with('error',
                '❌ Erreur d\'import : ' . mb_substr($e->getMessage(), 0, 200));
        }

        $errors = method_exists($importer, 'errors') ? $importer->errors() : collect();
        $errorCount = $errors->count();

        $msg = "✅ {$importer->imported} client(s) importé(s).";
        if ($importer->skipped > 0) {
            $msg .= " {$importer->skipped} ignoré(s) (doublons ou lignes vides).";
        }
        if ($errorCount > 0) {
            $msg .= " ⚠️ {$errorCount} ligne(s) en erreur.";
        }

        Log::info('clients.import.success', [
            'imported' => $importer->imported,
            'skipped'  => $importer->skipped,
            'errors'   => $errorCount,
            'user_id'  => auth()->id(),
        ]);

        AlertService::create(
            'client',
            'info',
            '📥 Import clients — ' . $importer->imported . ' nouveau(x)',
            auth()->user()?->name . ' a importé ' . $importer->imported . ' client(s) depuis un fichier Excel/CSV.',
            null
        );

        return redirect()->route('admin.clients.index')
            ->with($importer->imported > 0 ? 'success' : 'warning', $msg);
    }

    /**
     * Modèle CSV téléchargeable pour l'import.
     * GET admin/clients/import/template
     */
    public function importTemplate(): StreamedResponse
    {
        $headers = ['nom', 'email', 'telephone', 'entreprise', 'ncc', 'contact', 'secteur', 'adresse'];
        $sample  = [
            ['EXEMPLE SARL', 'contact@exemple.ci', '0707070707', 'EXEMPLE GROUP', 'NCC-2026-001', 'Mr KOFFI', 'Telecom', 'Plateau, Abidjan'],
            ['CIBLE TEST',   'test@cible.ci',     '0102030405', '',                '',           '',          '',         ''],
        ];

        $callback = function () use ($headers, $sample) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel français
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($sample as $row) fputcsv($out, $row, ';');
            fclose($out);
        };

        return response()->streamDownload($callback, 'modele-import-clients.csv', [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-clients.csv"',
        ]);
    }

}

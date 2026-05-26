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
        $query = $this->buildClientsQuery($request);

        $stats = [
            'total'    => Client::count(),
            'actifs'   => Client::whereHas('campaigns', fn($q) => $q->where('status', 'actif'))->count(),
            'ca_total' => \App\Models\Campaign::sum('total_amount'),
        ];

        $clients = $query->paginate(20)->withQueryString();
        $sectors = Client::SECTORS;

        if ($request->ajax()) {
            return response()->json([
                'html'       => view('admin.clients.partials.table-rows', compact('clients'))->render(),
                'pagination' => $clients->links()->render(),
                'total'      => $clients->total(),
            ]);
        }

        return view('admin.clients.index', compact('clients', 'stats', 'sectors'));
    }

    /**
     * Source unique de vérité pour la query "liste clients" — utilisée par
     * index(), exportCsv(), exportPdf(). Garantit que les exports reflètent
     * exactement le filtrage actif côté UI.
     *
     * Filtres supportés :
     *   - search (nom/ncc/email/contact/téléphone)
     *   - sector
     *   - sort (name|created_at|campaigns_count)
     *   - active_only=1 → ne garder que les clients ayant au moins 1
     *     campagne en statut actif|pose. C'est ce filtre qui résout le
     *     bug 5.1 où "Avec campagne active" ne produisait aucun delta.
     */
    private function buildClientsQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Client::withCount(['campaigns', 'reservations'])
            ->withCount([
                'campaigns as active_campaigns_count' => function ($q) {
                    $q->where('status', 'actif');
                }
            ])
            ->with([
                'campaigns' => function ($q) {
                    $q->where('status', 'actif');
                }
            ]);

        // Bug 5.1 — filtre "Clients avec campagne active"
        if ($request->boolean('active_only')) {
            $query->whereHas('campaigns', fn($q) => $q->where('status', 'actif'));
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name',         'like', "%{$request->search}%")
                  ->orWhere('ncc',          'like', "%{$request->search}%")
                  ->orWhere('email',        'like', "%{$request->search}%")
                  ->orWhere('contact_name', 'like', "%{$request->search}%")
                  ->orWhere('phone',        'like', "%{$request->search}%");
            });
        }

        if ($request->sector) {
            $query->where('sector', $request->sector);
        }

        $sort = in_array($request->sort, ['name', 'created_at', 'campaigns_count'], true)
            ? $request->sort : 'name';
        $query->orderBy($sort, $sort === 'name' ? 'asc' : 'desc');

        return $query;
    }

    // ══════════════════════════════════════════════════════════════
    // EXPORTS — CSV + PDF (5.2)
    //
    // Les deux respectent les filtres appliqués sur la liste : on
    // récupère les MÊMES IDs que ceux affichés (toutes pages), pas
    // seulement la page courante. Logique commune via buildClientsQuery.
    // ══════════════════════════════════════════════════════════════

    /**
     * Export CSV (UTF-8 + BOM pour ouverture propre dans Excel FR).
     * Streamé pour supporter de gros volumes sans saturer la mémoire.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $clients = $this->buildClientsQuery($request)->get();
        $filename = 'clients-' . now()->format('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($clients) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 pour Excel/LibreOffice → accents OK
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Nom', 'NCC', 'Secteur', 'Contact', 'Email', 'Téléphone',
                'Adresse', 'Campagnes', 'Campagnes actives', 'Réservations',
                'Compte client', 'Créé le',
            ], ';');

            foreach ($clients as $c) {
                fputcsv($out, [
                    $c->name,
                    $c->ncc            ?? '',
                    $c->sector         ?? '',
                    $c->contact_name   ?? '',
                    $c->email          ?? '',
                    $c->phone          ?? '',
                    $c->address        ?? '',
                    (int) ($c->campaigns_count ?? 0),
                    (int) ($c->active_campaigns_count ?? 0),
                    (int) ($c->reservations_count ?? 0),
                    method_exists($c, 'hasAccount') && $c->hasAccount() ? 'Oui' : 'Non',
                    $c->created_at?->format('d/m/Y') ?? '',
                ], ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    /**
     * Export PDF (paysage A4 — 12 colonnes, lisible).
     */
    public function exportPdf(Request $request)
    {
        $clients = $this->buildClientsQuery($request)->get();

        $logoSrc = (new class {
            use \App\Support\PdfAssets;
            public function go(): string { return $this->getLogoPdf(); }
        })->go();

        $filters = [
            'search'      => $request->input('search'),
            'sector'      => $request->input('sector'),
            'active_only' => $request->boolean('active_only'),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.clients.pdf.list', [
            'clients'   => $clients,
            'filters'   => $filters,
            'generated' => now()->format('d/m/Y à H:i'),
            'logoSrc'   => $logoSrc,
        ])->setPaper('A4', 'landscape')
          ->setOptions([
              'isHtml5ParserEnabled' => true,
              'isRemoteEnabled'      => false,
              'defaultFont'          => 'DejaVu Sans',
              'dpi'                  => 96,
          ]);

        return $pdf->download('clients-' . now()->format('Ymd_His') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $clients = $this->buildClientsQuery($request)->get();
        $filters = [
            'search'      => $request->input('search'),
            'sector'      => $request->input('sector'),
            'active_only' => $request->boolean('active_only'),
        ];
        $filename = 'clients-' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download(new \App\Exports\ClientsExport($clients, $filters), $filename);
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
            'contacts',
            // Utilisateurs de l'espace client (owner + members) pour le
            // nouveau panneau « Équipe espace client » sur la fiche admin.
            // Tri : owner d'abord, puis par dernière connexion.
            'users' => fn($q) => $q->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
                                   ->orderByDesc('last_login_at')
                                   ->orderBy('name'),
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
        try {
            $data = $request->validate([
                'name'         => 'required|string|max:150',
                'email'        => 'nullable|email|unique:clients,email',
                'phone'        => 'nullable|string|max:25',
                'contact_name' => 'nullable|string|max:150',
                'ncc'          => 'nullable|string|max:80|unique:clients,ncc',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Premier message d'erreur lisible (ex: "Le NCC existe déjà")
            $first = collect($e->errors())->flatten()->first() ?? 'Données invalides.';
            return response()->json([
                'success' => false,
                'message' => $first,
                'errors'  => $e->errors(),
            ], 422);
        }

        // NCC laissé null si non fourni — plus d'auto-génération forcée.
        $client = Client::create($data);

        // Structure attendue par le JS du modal campagne : { success, client: {...} }
        return response()->json([
            'success' => true,
            'client'  => [
                'id'           => $client->id,
                'name'         => $client->name,
                'text'         => $client->name,
                'ncc'          => $client->ncc,
                'phone'        => $client->phone,
                'email'        => $client->email,
                'contact_name' => $client->contact_name,
            ],
            // Champs racine conservés pour rétro-compat avec d'autres appelants
            'id'   => $client->id,
            'name' => $client->name,
            'text' => $client->name,
            'ncc'  => $client->ncc,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRESSION GROUPÉE — admin/clients/bulk-destroy (POST AJAX)
    // ══════════════════════════════════════════════════════════════
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:clients,id',
        ]);

        $ids = $data['ids'];

        // On exclut les clients ayant des campagnes actives (sécurité métier
        // — éviter de casser des opérations en cours). À la place, on
        // renvoie la liste des clients refusés pour info utilisateur.
        $blocked = Client::whereIn('id', $ids)
            ->whereHas('campaigns', fn($q) => $q->whereIn('status', ['actif', 'planifie', 'pause']))
            ->pluck('name', 'id');

        $deletable = collect($ids)->reject(fn($id) => $blocked->has($id))->values()->all();

        $deleted = 0;
        if (!empty($deletable)) {
            $deleted = Client::whereIn('id', $deletable)->delete(); // soft delete
        }

        AlertService::create(
            'client',
            'warning',
            '🗑 Suppression groupée — ' . $deleted . ' client(s)',
            auth()->user()?->name . ' a supprimé ' . $deleted . ' client(s) en lot.',
            null
        );

        return response()->json([
            'success'      => true,
            'deleted'      => $deleted,
            'blocked'      => $blocked->values(),
            'blocked_count'=> $blocked->count(),
            'message'      => $deleted . ' client(s) supprimé(s)' .
                              ($blocked->count() > 0
                                  ? ' · ' . $blocked->count() . ' bloqué(s) (campagnes actives)'
                                  : ''),
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

        // Auto-détection de la ligne d'entête (gère les fichiers Excel exportés
        // de Panora qui ont des lignes décoratives — logo, titre — au-dessus
        // des entêtes). On scanne les 10 premières lignes à la recherche d'une
        // cellule contenant "nom" / "name".
        try {
            $detectedRow = $this->detectHeadingRow($request->file('file')->getRealPath());
            $importer->setHeadingRow($detectedRow);
        } catch (\Throwable $e) {
            Log::warning('clients.import.heading_detect_failed', ['error' => $e->getMessage()]);
        }

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
     * Scanne les 15 premières lignes d'un fichier (CSV ou Excel) et retourne
     * le numéro de la ligne contenant les entêtes (celle qui contient "nom"
     * ou "name" dans une cellule). Permet d'importer un Excel exporté de
     * Panora même s'il a un bandeau décoratif (logo, titre, date) au-dessus.
     *
     * @return int Numéro 1-based de la ligne d'entête (1 par défaut).
     */
    private function detectHeadingRow(string $path): int
    {
        $rows = [];

        // Charge les 15 premières lignes selon le format
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['csv', 'txt'], true)) {
            $handle = @fopen($path, 'r');
            if (!$handle) return 1;
            // BOM UTF-8 éventuel
            $first = fread($handle, 3);
            if ($first !== "\xEF\xBB\xBF") { rewind($handle); }
            // Détection du séparateur (; ou ,) sur la 1ère ligne lue
            $sample = fgets($handle);
            $sep = (substr_count($sample, ';') > substr_count($sample, ',')) ? ';' : ',';
            rewind($handle);
            if ($first !== "\xEF\xBB\xBF") { rewind($handle); } else { fread($handle, 3); }
            $i = 0;
            while ($i < 15 && ($row = fgetcsv($handle, 0, $sep)) !== false) {
                $rows[] = $row;
                $i++;
            }
            fclose($handle);
        } else {
            // Excel — utilise PhpSpreadsheet via Maatwebsite
            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = min(15, $sheet->getHighestRow());
                for ($r = 1; $r <= $highestRow; $r++) {
                    $rows[] = $sheet->rangeToArray('A' . $r . ':L' . $r, null, false, false)[0] ?? [];
                }
            } catch (\Throwable $e) {
                return 1;
            }
        }

        // Cherche la ligne contenant "nom" ou "name" dans une cellule
        foreach ($rows as $idx => $row) {
            foreach ($row as $cell) {
                if ($cell === null) continue;
                $normalized = mb_strtolower(trim((string) $cell));
                // Strip accents pour matcher "nom"/"name"
                $normalized = $this->removeAccents($normalized);
                if ($normalized === 'nom' || $normalized === 'name') {
                    return $idx + 1; // 1-based
                }
            }
        }
        return 1;
    }

    private function removeAccents(string $s): string
    {
        return strtr($s, [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c',
        ]);
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

    // ══════════════════════════════════════════════════════════════
    // OUTIL ONE-SHOT — Corrige les clients importés avec le pattern
    // "PERSONNE / ENTREPRISE" dans le name (ancienne logique d'import).
    // ══════════════════════════════════════════════════════════════

    public function fixImportNamesPreview()
    {
        $candidates = Client::query()
            ->where('name', 'like', '% / %')
            ->orderBy('id')
            ->get(['id', 'name', 'contact_name', 'ncc'])
            ->map(function (Client $c) {
                [$newName, $newContact] = $this->splitClientName($c->name);
                return [
                    'id'              => $c->id,
                    'ncc'             => $c->ncc,
                    'old_name'        => $c->name,
                    'new_name'        => $newName,
                    'new_contact'     => $newContact,
                    'existing_contact'=> $c->contact_name,
                    'preserved'       => !empty($c->contact_name),
                ];
            });

        return view('admin.clients.fix-import-names', compact('candidates'));
    }

    public function fixImportNamesApply(Request $request)
    {
        $candidates = Client::query()->where('name', 'like', '% / %')->get();

        $updated = 0;
        \DB::transaction(function () use ($candidates, &$updated) {
            foreach ($candidates as $client) {
                [$newName, $newContact] = $this->splitClientName($client->name);
                $payload = ['name' => $newName];
                if (empty($client->contact_name) && $newContact !== '') {
                    $payload['contact_name'] = $newContact;
                }
                $client->update($payload);
                $updated++;
            }
        });

        Log::info('clients.fix_import_names.applied', [
            'updated' => $updated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('admin.clients.index')
            ->with('success', "{$updated} client(s) corrigé(s) — name = entreprise, contact_name = personne.");
    }

    private function splitClientName(string $name): array
    {
        $pos = mb_strrpos($name, ' / ');
        if ($pos === false) return [$name, ''];
        $left  = trim(mb_substr($name, 0, $pos));
        $right = trim(mb_substr($name, $pos + 3));
        return [$right, $left];
    }

    // ══════════════════════════════════════════════════════════════
    // OUTIL ONE-SHOT — Efface les NCC auto-générés par l'app pendant
    // l'import (pattern CLT-YYYY-NNNN). La patronne préfère ne pas
    // avoir de NCC bidon en base.
    // ══════════════════════════════════════════════════════════════

    public function clearAutoNccPreview()
    {
        $candidates = Client::query()
            ->where('ncc', 'regexp', '^CLT-[0-9]{4}-[0-9]+$')
            ->orderBy('id')
            ->get(['id', 'name', 'contact_name', 'ncc']);

        return view('admin.clients.clear-auto-ncc', compact('candidates'));
    }

    public function clearAutoNccApply(Request $request)
    {
        $updated = Client::query()
            ->where('ncc', 'regexp', '^CLT-[0-9]{4}-[0-9]+$')
            ->update(['ncc' => null]);

        Log::info('clients.clear_auto_ncc.applied', [
            'updated' => $updated,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('admin.clients.index')
            ->with('success', "{$updated} NCC auto-générés effacés.");
    }
}

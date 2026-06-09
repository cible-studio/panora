<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Client;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $user = auth()->user();
        $isCommercial = $user?->role?->value === 'commercial';
        $uid = (int) ($user?->id ?? 0);

        // RBAC : un commercial ne voit que les factures de SES campagnes.
        // Délégué au scope canonique Invoice::scopeForCommercialUser
        // (source unique, déjà alignée sur les autres controllers).
        // Eager-load payments + schedules pour le tableau de suivi
        // (payé / reste / prochaine échéance / statut paiement).
        $query = Invoice::with(['client', 'campaign', 'creator', 'payments', 'schedules'])
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid));

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        // Filtre par campagne (déeplinking depuis la fiche campagne
        // pour voir TOUTES les factures d'une campagne donnée — utile
        // quand acompte + solde + complémentaire sur la même campagne).
        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->where('issued_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('issued_at', '<=', $request->date_to);
        }

        // Filtre statut PAIEMENT (dérivé) — on charge la page complète
        // puis on filtre côté collection. Acceptable car paginé.
        // 'en_retard' est maintenant un paymentStatus() à part entière.
        $payStatusFilter = $request->input('pay_status');
        // Alias 'overdue' (legacy) → 'en_retard'
        if ($payStatusFilter === 'overdue') $payStatusFilter = 'en_retard';
        $invoices = $query->latest()->paginate(15)->withQueryString();
        if (in_array($payStatusFilter, ['non_payee', 'partielle', 'soldee', 'en_retard'], true)) {
            $invoices->setCollection($invoices->getCollection()->filter(function ($inv) use ($payStatusFilter) {
                return $inv->paymentStatus() === $payStatusFilter;
            })->values());
        }

        // Liste des clients : si commercial, on ne propose dans le filtre
        // que les clients pour lesquels il a au moins une facture (sinon
        // il voyait tous les clients de l'entreprise dans la combobox).
        $clients = Client::query()
            ->when($isCommercial, fn($q) =>
                $q->whereHas('invoices', fn($i) => $i->forCommercialUser($uid))
            )
            ->orderBy('name')
            ->get();

        // ⚠ Compteurs KPI : AVANT ils étaient calculés sur Invoice::query()
        // sans scope → un commercial voyait le nb total entreprise + le CA
        // payé GLOBAL (leak métier majeur). Maintenant scopés au commercial.
        $kpiQuery = fn() => Invoice::query()
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid));
        $totalBrouillons = (clone $kpiQuery())->where('status', 'brouillon')->count();
        $totalEnvoyees   = (clone $kpiQuery())->where('status', 'envoyee')->count();
        $totalPayees     = (clone $kpiQuery())->where('status', 'payee')->count();
        $montantTotal    = (clone $kpiQuery())->where('status', 'payee')->sum('amount_ttc');
        
        // ✅ AJAX response
        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.invoices.partials.table-rows', compact('invoices'))->render();
            $paginationHtml = $invoices->hasPages() ? $invoices->links()->render() : '';
            return response()->json([
                'html' => $html,
                'pagination' => $paginationHtml,
                'total' => $invoices->total(),
            ]);
        }

        // Bandeau "facturation campagne X" si on est arrivé via
        // /admin/invoices?campaign_id=X depuis la fiche campagne.
        $contextCampaign = $request->filled('campaign_id')
            ? Campaign::with('client')->find($request->campaign_id)
            : null;

        return view('admin.invoices.index', compact(
            'invoices', 'clients', 'contextCampaign',
            'totalBrouillons', 'totalEnvoyees',
            'totalPayees', 'montantTotal'
        ));
    }

    public function create(Request $request)
    {
        $clients   = Client::orderBy('name')->get();
        $campaigns = Campaign::with('client:id,name')->orderBy('name')->get();

        // Générer référence automatique
        $reference = 'FAC-' . date('Y') . '-' . str_pad(
            Invoice::whereYear('created_at', date('Y'))->count() + 1,
            3, '0', STR_PAD_LEFT
        );

        // Préselection depuis querystring (ex: bouton "Facturer" sur la fiche
        // campagne admin → /admin/invoices/create?campaign_id=42). Le JS du
        // formulaire récupère ensuite client + montant via les lookups.
        $preselect = [
            'client_id'   => $request->query('client_id'),
            'campaign_id' => $request->query('campaign_id'),
        ];

        // Si une campagne est préselectionnée, on peut résoudre client +
        // montant ici pour éviter un round-trip JS dès le chargement.
        $preselectAmount = null;
        if ($preselect['campaign_id']) {
            $camp = Campaign::with('reservation')->find($preselect['campaign_id']);
            if ($camp) {
                $preselect['client_id'] = $preselect['client_id'] ?? $camp->client_id;
                $preselectAmount = $camp->remainingToBillHt();
                if ($preselectAmount <= 0) {
                    // Déjà tout facturé : on remet le montant complet pour
                    // qu'il soit éditable, et on prévient l'admin en bandeau.
                    $preselectAmount = $camp->computedAmountHt();
                }
            }
        }

        return view('admin.invoices.create', compact(
            'clients', 'campaigns', 'reference',
            'preselect', 'preselectAmount'
        ));
    }

    /**
     * Lookup JSON : campagnes d'un client donné, avec montant HT de
     * référence, déjà facturé et reste à facturer. Sert au select
     * "Campagne" du formulaire qui se refiltre quand le client change.
     *
     * Réponse :
     *   { campaigns: [ { id, name, status, period, amount_ht,
     *                    billed_ht, remaining_ht, fully_billed }, ... ] }
     */
    public function lookupClientCampaigns(Client $client)
    {
        $rows = Campaign::with('reservation:id,total_amount')
            ->where('client_id', $client->id)
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status', 'start_date', 'end_date', 'client_id', 'reservation_id', 'total_amount']);

        return response()->json([
            'campaigns' => $rows->map(function (Campaign $c) {
                $ht        = $c->computedAmountHt();
                $billed    = $c->alreadyBilledHt();
                $remaining = max(0.0, $ht - $billed);
                return [
                    'id'           => $c->id,
                    'name'         => $c->name,
                    'status'       => is_object($c->status) ? $c->status->value : $c->status,
                    'period'       => optional($c->start_date)->format('d/m/Y') . ' → ' . optional($c->end_date)->format('d/m/Y'),
                    'amount_ht'    => round($ht, 2),
                    'billed_ht'    => round($billed, 2),
                    'remaining_ht' => round($remaining, 2),
                    'fully_billed' => $billed >= $ht - 0.01 && $ht > 0,
                ];
            })->values(),
        ]);
    }

    /**
     * Lookup JSON : info d'une campagne donnée pour pré-remplir le
     * formulaire facture (client + montant HT suggéré).
     *
     * Réponse :
     *   { client: {id,name,email}, amount_ht, billed_ht, remaining_ht,
     *     suggested_amount_ht, fully_billed, name, period }
     */
    /**
     * Lookup AJAX : recherche un panneau dans une CAMPAGNE pour
     * pré-remplir une ligne de facture (auto-complète commune, m², PU).
     *
     * ⚠ Politique métier validée : la recherche est STRICTEMENT limitée
     *   aux panneaux de la campagne sélectionnée. Sans campagne, on ne
     *   propose AUCUN panneau — l'admin doit soit choisir une campagne,
     *   soit taper une désignation libre (tags Select2 côté front).
     *
     * Renvoie pour chaque panneau (interne ou externe) :
     *   id, ref, name, designation (ref + name + dim), commune_id,
     *   commune_name, dimension_m2, monthly_rate (catalogue),
     *   pu_negotiated (pivot reservation_panels), pu_suggested
     *   (négocié si dispo, sinon catalogue).
     *
     * Format Select2 (results + pagination.more).
     */
    public function lookupPanels(Request $request)
    {
        $q          = trim((string) $request->input('q', ''));
        $campaignId = $request->input('campaign_id');
        $page       = max(1, (int) $request->input('page', 1));
        $perPage    = 20;

        // Sans campagne sélectionnée → pas de recherche panneau possible.
        // Le front affiche un hint et bascule en mode "tag libre" pour
        // permettre quand même la saisie d'une désignation manuelle.
        if (!$campaignId) {
            return response()->json([
                'results'    => [],
                'pagination' => ['more' => false],
                'hint'       => 'Sélectionne d\'abord une campagne pour rechercher ses panneaux. Sinon, tape une désignation libre.',
            ]);
        }

        $campaign = Campaign::with([
            'panels.commune:id,name',
            'panels.format:id,name,width,height,surface',
            'externalPanels.commune:id,name',
            'externalPanels.format:id,name,width,height,surface',
            'reservation',
        ])->find($campaignId);

        if (!$campaign) {
            return response()->json(['results' => [], 'pagination' => ['more' => false]]);
        }

        // Récup prix négocié pivot (panel_id ou external_panel_id)
        $negotiated = [];
        if ($campaign->reservation_id) {
            $rows = \Illuminate\Support\Facades\DB::table('reservation_panels')
                ->where('reservation_id', $campaign->reservation_id)
                ->get(['panel_id', 'external_panel_id', 'unit_price']);
            foreach ($rows as $r) {
                if ($r->panel_id)          $negotiated['int_' . $r->panel_id] = (float) $r->unit_price;
                if ($r->external_panel_id) $negotiated['ext_' . $r->external_panel_id] = (float) $r->unit_price;
            }
        }

        $all = collect();
        foreach ($campaign->panels as $p) {
            $all->push($this->panelToOption($p, 'int', $negotiated['int_' . $p->id] ?? null));
        }
        foreach ($campaign->externalPanels as $p) {
            $all->push($this->panelToOption($p, 'ext', $negotiated['ext_' . $p->id] ?? null));
        }

        // Filtre texte côté collection (volumes raisonnables : 1 campagne)
        if ($q !== '') {
            $needle = mb_strtolower($q);
            $all = $all->filter(fn($r) => str_contains(mb_strtolower($r['text']), $needle));
        }
        $total = $all->count();
        $items = $all->forPage($page, $perPage)->values();

        return response()->json([
            'results'    => $items,
            'pagination' => ['more' => $total > $page * $perPage],
        ]);
    }

    /**
     * Transforme un Panel (interne ou externe) en option Select2 avec
     * tous les champs utiles pour pré-remplir la ligne de facture.
     */
    protected function panelToOption($panel, string $source, ?float $negotiatedPu): array
    {
        $m2          = (float) ($panel->format?->surface_m2 ?? 0);
        $catalogRate = (float) ($panel->monthly_rate ?? 0);
        $pu          = $negotiatedPu ?? $catalogRate;
        $ref         = $panel->reference ?? ('#' . $panel->id);
        $name        = $panel->name ?? '';
        $communeName = $panel->commune?->name ?? '—';
        $dimLabel    = $panel->format?->dimensions_label ?? '';

        $designation = trim("{$ref} — {$name}" . ($dimLabel ? " {$dimLabel}" : ''));
        $text        = trim("{$ref} · {$communeName}" . ($name ? " — {$name}" : ''));

        return [
            'id'             => $source . '_' . $panel->id,
            'text'           => $text,
            'designation'    => $designation,
            'ref'            => $ref,
            'name'           => $name,
            'commune_id'     => $panel->commune?->id,
            'commune_name'   => $communeName,
            'dimension_m2'   => $m2,
            'monthly_rate'   => $catalogRate,
            'pu_negotiated'  => $negotiatedPu,
            'pu_suggested'   => $pu,
            'source'         => $source,
            'is_external'    => $source === 'ext',
        ];
    }

    public function lookupCampaignInfo(Campaign $campaign)
    {
        $campaign->loadMissing('client:id,name,email', 'reservation:id,total_amount');

        $ht        = $campaign->computedAmountHt();
        $billed    = $campaign->alreadyBilledHt();
        $remaining = max(0.0, $ht - $billed);

        // Montant suggéré : reste à facturer si > 0, sinon montant complet
        // (cas où l'admin veut sciemment refacturer ou avoir-éditer).
        $suggested = $remaining > 0 ? $remaining : $ht;

        return response()->json([
            'id'                  => $campaign->id,
            'name'                => $campaign->name,
            'period'              => optional($campaign->start_date)->format('d/m/Y') . ' → ' . optional($campaign->end_date)->format('d/m/Y'),
            'status'              => is_object($campaign->status) ? $campaign->status->value : $campaign->status,
            'client'              => $campaign->client ? [
                'id'    => $campaign->client->id,
                'name'  => $campaign->client->name,
                'email' => $campaign->client->email,
            ] : null,
            'amount_ht'           => round($ht, 2),
            'billed_ht'           => round($billed, 2),
            'remaining_ht'        => round($remaining, 2),
            'suggested_amount_ht' => round($suggested, 2),
            'fully_billed'        => $billed >= $ht - 0.01 && $ht > 0,
        ]);
    }

    public function store(Request $request, \App\Services\InvoiceCalculator $calculator)
    {
        $data = $request->validate([
            'client_id'             => 'required|exists:clients,id',
            'campaign_id'           => 'nullable|exists:campaigns,id',
            'reference'             => 'required|unique:invoices,reference',
            'issued_at'             => 'required|date',
            'notes_client'          => 'nullable|string|max:2000',
            'remise_pct'            => 'nullable|numeric|min:0|max:100',
            // Services annexes libres (prompt v2). Champs LEGACY conservés
            // pour rétrocompat (factures historiques) mais ignorés si on
            // reçoit le tableau moderne 'services'.
            'services'              => 'nullable|array|max:30',
            'services.*.label'      => 'required_with:services|string|max:200',
            'services.*.prix_ht'    => 'required_with:services|numeric|min:0',
            'services_impression'   => 'nullable|numeric|min:0',
            'services_pose_depose'  => 'nullable|numeric|min:0',
            'lines'                 => 'required|array|min:1',
            'lines.*.designation'   => 'required|string|max:200',
            'lines.*.commune_id'    => 'required|exists:communes,id',
            'lines.*.dimension_m2'  => 'required|numeric|min:0',
            'lines.*.pu_ht_mensuel' => 'required|numeric|min:0',
            'lines.*.quantite'      => 'required|integer|min:1',
            'lines.*.duree_mois'    => 'required|numeric|min:0.5',
        ], [
            'lines.required'        => 'Au moins une ligne de facturation est requise.',
            'lines.*.commune_id.required' => 'Chaque ligne doit avoir une commune (pour résoudre ODP).',
            'services.*.label.required_with'   => 'Chaque service annexe doit avoir un libellé.',
            'services.*.prix_ht.required_with' => 'Chaque service annexe doit avoir un prix HT.',
        ]);

        // Cohérence campagne ↔ client : le client de la campagne fait foi.
        $clientId = (int) $data['client_id'];
        if (!empty($data['campaign_id'])) {
            $camp = Campaign::find($data['campaign_id']);
            if ($camp && (int) $camp->client_id !== $clientId) {
                $clientId = (int) $camp->client_id;
            }
        }

        $issuedAt = \Carbon\Carbon::parse($data['issued_at']);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $clientId, $issuedAt, $calculator) {
            // commercial_user_id : on dérive depuis la campagne si liée,
            // sinon depuis l'utilisateur courant (cas saisie manuelle d'une
            // facture non rattachée à une campagne — rare mais possible).
            $commercialUserId = auth()->id();
            if (!empty($data['campaign_id'])) {
                $camp = Campaign::find($data['campaign_id']);
                $commercialUserId = $camp?->commercial_user_id
                                  ?? $camp?->user_id
                                  ?? auth()->id();
            }

            $invoice = Invoice::create([
                'reference'            => $data['reference'],
                'client_id'            => $clientId,
                'campaign_id'          => $data['campaign_id'] ?? null,
                'commercial_user_id'   => $commercialUserId,
                'created_by'           => auth()->id(),
                'issued_at'            => $data['issued_at'],
                'status'               => 'brouillon',
                'tva'                  => $calculator->tvaRate(),
                'remise_pct'           => (float) ($data['remise_pct'] ?? 0),
                // Champs legacy : 0 si on a la forme moderne 'services',
                // sinon copie pour rétrocompat (entiers FCFA).
                'services_impression'  => !empty($data['services']) ? 0 : (int) round((float) ($data['services_impression'] ?? 0)),
                'services_pose_depose' => !empty($data['services']) ? 0 : (int) round((float) ($data['services_pose_depose'] ?? 0)),
                'notes_client'         => $data['notes_client'] ?? null,
                'campaign_year'        => $issuedAt->year,
            ]);

            $this->syncLines($invoice, $data['lines'], $issuedAt, $calculator);
            $this->syncServices($invoice, $data['services'] ?? []);

            $invoice = $calculator->recalculateAndPersist($invoice);

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('success', "Facture {$invoice->reference} créée — {$invoice->lines->count()} ligne(s), total " . number_format($invoice->total_a_payer, 0, ',', ' ') . ' FCFA.');
        });
    }

    /**
     * Sync les services annexes libres (replace, pas merge).
     * Filtre les lignes vides (label vide ou prix 0).
     */
    protected function syncServices(Invoice $invoice, array $servicesInput): void
    {
        $invoice->services()->delete();
        $idx = 0;
        foreach ($servicesInput as $s) {
            $label = trim((string) ($s['label'] ?? ''));
            $prix  = (float) ($s['prix_ht'] ?? 0);
            if ($label === '' || $prix <= 0) continue;
            $invoice->services()->create([
                'label'       => $label,
                'prix_ht'     => $prix,
                'order_index' => $idx++,
            ]);
        }
    }

    /**
     * Crée les InvoiceLines depuis le payload form, en résolvant les
     * tarifs ODP/TM historisés via Commune::ratesAt(issued_at).
     * Utilisé par store() ET update() (qui delete d'abord).
     */
    protected function syncLines(Invoice $invoice, array $linesInput, \Carbon\Carbon $issuedAt, \App\Services\InvoiceCalculator $calculator): void
    {
        $issuedDate = $issuedAt->toDateString();
        $orderIdx = 0;
        foreach ($linesInput as $l) {
            $commune = \App\Models\Commune::find($l['commune_id']);
            $rates = $commune?->ratesAt($issuedDate) ?? ['odp' => 0, 'tm' => 1000];

            $lineData = [
                'designation'           => $l['designation'],
                'commune_id'            => $commune?->id,
                'snapshot_commune_name' => $commune?->name,
                'dimension_m2'          => (float) $l['dimension_m2'],
                'pu_ht_mensuel'         => (float) $l['pu_ht_mensuel'],
                'quantite'              => (int) $l['quantite'],
                'duree_mois'            => (float) $l['duree_mois'],
                'odp_rate_applique'     => (float) $rates['odp'],
                'tm_rate_applique'      => (float) $rates['tm'],
                'order_index'           => $orderIdx++,
            ];

            // Pré-calcul des totaux ligne (montant_ht, odp, tm)
            $calc = $calculator->calculateLine($lineData);
            $lineData = array_merge($lineData, $calc);

            $invoice->lines()->create($lineData);
        }
    }

    /**
     * Phase 7 cahier §13 — Consultation timeline d'audit d'une facture.
     * Affiche :
     *   - Historique status (invoice_status_history Phase 1)
     *   - Historique des modifications (audits Phase 7)
     *   - Historique des paiements et leur audits
     */
    public function auditTimeline(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'client:id,name',
            'statusHistory.user:id,name',
            'audits.user',
            'payments.audits.user',
            'schedules.audits.user',
        ]);

        return view('admin.invoices.audit', compact('invoice'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'client',
            'campaign:id,name,client_id,status,start_date,end_date,total_amount,total_panels,total_amount_overridden_at',
            'campaign.reservation:id,reference',
            'creator',
            'lines.commune:id,name',
            'payments.creator:id,name',
            'schedules',
            'services',
            'statusHistory.user:id,name',
            'lockedBy:id,name',
            'creditNoteFor:id,reference',
            'creditNotes',
        ]);

        $user = auth()->user();
        $isCommercial = $user?->role?->value === 'commercial';
        $uid = (int) ($user?->id ?? 0);

        // Autres factures du même client (max 6, exclut celle-ci) — colonne
        // droite. ⚠ Scoped au commercial : sinon il voyait les factures
        // d'AUTRES commerciaux sur le même client (leak inter-commerciaux).
        $otherInvoices = $invoice->client_id
            ? Invoice::where('client_id', $invoice->client_id)
                ->where('id', '!=', $invoice->id)
                ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
                ->orderByDesc('issued_at')->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'reference', 'amount', 'amount_ttc', 'tva', 'status', 'issued_at', 'paid_at', 'campaign_id'])
            : collect();

        // Stats globales client : nb factures + total payé + total dû.
        // Même scope que ci-dessus pour cohérence.
        $clientStats = null;
        if ($invoice->client_id) {
            $allClientInvoices = Invoice::where('client_id', $invoice->client_id)
                ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
                ->select('status', 'amount_ttc')
                ->get();
            $clientStats = [
                'count_total'   => $allClientInvoices->count(),
                'count_paid'    => $allClientInvoices->where('status', 'payee')->count(),
                'count_pending' => $allClientInvoices->whereIn('status', ['brouillon', 'envoyee'])->count(),
                'sum_paid_ttc'  => (float) $allClientInvoices->where('status', 'payee')->sum('amount_ttc'),
                'sum_pending_ttc' => (float) $allClientInvoices->whereIn('status', ['brouillon', 'envoyee'])->sum('amount_ttc'),
                'scope'         => $isCommercial ? 'commercial' : 'global',
            ];
        }

        // Récap campagne si liée : déjà facturé / reste à facturer +
        // détection de drift (la campagne a-t-elle été modifiée APRÈS
        // l'émission de cette facture ? si oui, le montant peut être
        // décorrélé du réel attendu — on alerte l'admin pour qu'il
        // décide : refaire la facture, ou la conserver en l'état).
        $campaignBilling = null;
        $billingDrift    = null;
        if ($invoice->campaign) {
            $expectedHt   = (float) $invoice->campaign->computedAmountHt();
            $billedHt     = (float) $invoice->campaign->alreadyBilledHt();
            $remainingHt  = max(0.0, $expectedHt - $billedHt);
            $campaignBilling = [
                'expected_ht'  => round($expectedHt, 2),
                'billed_ht'    => round($billedHt, 2),
                'remaining_ht' => round($remainingHt, 2),
            ];

            // Cas drift détectables :
            //   1) total_amount_overridden_at > invoice.issued_at
            //      → l'admin a force un override APRÈS l'émission
            //   2) campaign.updated_at > invoice.issued_at ET
            //      computedAmountHt() != invoice.amount (±1 FCFA)
            //      → dates campagne modifiées, montant attendu a bougé
            $issuedAt = $invoice->issued_at;
            $invAmount = (float) $invoice->amount;
            $diff = round($expectedHt - $invAmount, 2);
            $matches = abs($diff) < 1.0;
            $overriddenAfter = $invoice->campaign->total_amount_overridden_at
                && $issuedAt
                && $invoice->campaign->total_amount_overridden_at->gt($issuedAt);
            if (!$matches && $issuedAt) {
                $billingDrift = [
                    'invoice_amount_ht' => round($invAmount, 2),
                    'expected_now_ht'   => round($expectedHt, 2),
                    'diff'              => $diff,
                    'overridden_after'  => (bool) $overriddenAfter,
                    'campaign_updated_at' => $invoice->campaign->updated_at?->toIso8601String(),
                ];
            }
        }

        // Phase 8B cahier §9 — relances de cette facture (timeline)
        $invoiceRelances = \App\Models\Relance::where('invoice_id', $invoice->id)
            ->with('user:id,name')
            ->orderByDesc('relance_date')
            ->orderByDesc('id')
            ->get();

        return view('admin.invoices.show', compact(
            'invoice', 'otherInvoices', 'clientStats',
            'campaignBilling', 'billingDrift', 'invoiceRelances'
        ));
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $clients   = Client::orderBy('name')->get();
        $campaigns = Campaign::with('client:id,name')->orderBy('name')->get();
        return view('admin.invoices.edit', compact(
            'invoice', 'clients', 'campaigns'
        ));
    }

    public function update(Request $request, Invoice $invoice, \App\Services\InvoiceCalculator $calculator)
    {
        $this->authorize('update', $invoice);

        // ⚠ Lock : interdit de modifier une facture verrouillée. L'admin
        // doit déverrouiller d'abord (action tracée dans les logs).
        if ($invoice->isLocked()) {
            return back()->with('error',
                '🔒 Facture verrouillée le ' . $invoice->locked_at->format('d/m/Y')
                . ' — déverrouille-la d\'abord pour la modifier.'
            );
        }

        $data = $request->validate([
            'client_id'             => 'required|exists:clients,id',
            'campaign_id'           => 'nullable|exists:campaigns,id',
            'reference'             => 'required|unique:invoices,reference,' . $invoice->id,
            'issued_at'             => 'required|date',
            'notes_client'          => 'nullable|string|max:2000',
            'remise_pct'            => 'nullable|numeric|min:0|max:100',
            // Services annexes libres (prompt v2)
            'services'              => 'nullable|array|max:30',
            'services.*.label'      => 'required_with:services|string|max:200',
            'services.*.prix_ht'    => 'required_with:services|numeric|min:0',
            // Legacy : conservés pour rétrocompat factures historiques
            'services_impression'   => 'nullable|numeric|min:0',
            'services_pose_depose'  => 'nullable|numeric|min:0',
            'lines'                 => 'required|array|min:1',
            'lines.*.designation'   => 'required|string|max:200',
            'lines.*.commune_id'    => 'required|exists:communes,id',
            'lines.*.dimension_m2'  => 'required|numeric|min:0',
            'lines.*.pu_ht_mensuel' => 'required|numeric|min:0',
            'lines.*.quantite'      => 'required|integer|min:1',
            'lines.*.duree_mois'    => 'required|numeric|min:0.5',
        ], [
            'services.*.label.required_with'   => 'Chaque service annexe doit avoir un libellé.',
            'services.*.prix_ht.required_with' => 'Chaque service annexe doit avoir un prix HT.',
        ]);

        // Cohérence campagne ↔ client (cf. store).
        $clientId = (int) $data['client_id'];
        if (!empty($data['campaign_id'])) {
            $camp = Campaign::find($data['campaign_id']);
            if ($camp && (int) $camp->client_id !== $clientId) {
                $clientId = (int) $camp->client_id;
            }
        }

        $issuedAt = \Carbon\Carbon::parse($data['issued_at']);

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $invoice, $clientId, $issuedAt, $calculator) {
            $invoice->update([
                'reference'            => $data['reference'],
                'client_id'            => $clientId,
                'campaign_id'          => $data['campaign_id'] ?? null,
                'issued_at'            => $data['issued_at'],
                'remise_pct'           => (float) ($data['remise_pct'] ?? 0),
                'services_impression'  => !empty($data['services']) ? 0 : (float) ($data['services_impression'] ?? 0),
                'services_pose_depose' => !empty($data['services']) ? 0 : (float) ($data['services_pose_depose'] ?? 0),
                'notes_client'         => $data['notes_client'] ?? null,
                'campaign_year'        => $issuedAt->year,
            ]);

            // Sync lignes + services : delete tout puis recrée. Plus simple
            // que de matcher ligne par ligne, et la facture n'a pas encore
            // été envoyée (lock empêche update sinon).
            $invoice->lines()->delete();
            $this->syncLines($invoice, $data['lines'], $issuedAt, $calculator);
            $this->syncServices($invoice, $data['services'] ?? []);

            $invoice = $calculator->recalculateAndPersist($invoice);

            return redirect()->route('admin.invoices.show', $invoice)
                ->with('success', "Facture {$invoice->reference} modifiée — total " . number_format($invoice->total_a_payer, 0, ',', ' ') . ' FCFA.');
        });
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();
        return redirect()->route('admin.invoices.index')
            ->with('success', 'Facture supprimée !');
    }

    // ══════════════════════════════════════════════════════════════
    // GÉNÉRATION DEPUIS UNE CAMPAGNE
    //
    // Construit une facture FNE pré-remplie à partir des panneaux de
    // la campagne (internes + externes), résout les tarifs ODP/TM
    // historisés via Commune::ratesAt, génère les lignes et calcule
    // les agrégats. Statut initial 'brouillon' — l'admin ajuste avant
    // d'envoyer.
    // ══════════════════════════════════════════════════════════════
    public function fromCampaign(Request $request, Campaign $campaign, \App\Services\InvoiceFromCampaignBuilder $builder)
    {
        $this->authorize('create', Invoice::class);

        if ($campaign->status?->value === 'annule') {
            return back()->with('error', '🚫 Campagne annulée — facturation bloquée.');
        }

        // Prévention sur-facturation : si la campagne a déjà des factures
        // non-annulées, on prévient (mais on n'empêche pas — facturation
        // partielle / complémentaire reste possible).
        $existingCount = $campaign->invoices()
            ->whereNotIn('status', ['annulee'])->count();

        $opts = $request->validate([
            'remise_pct'           => 'nullable|numeric|min:0|max:100',
            'services_impression'  => 'nullable|numeric|min:0',
            'services_pose_depose' => 'nullable|numeric|min:0',
            'notes_client'         => 'nullable|string|max:2000',
            'issued_at'            => 'nullable|date',
        ]);

        try {
            $invoice = $builder->build($campaign, $opts);
        } catch (\DomainException $e) {
            // Erreur métier propre (pas de panneau, pas de client, etc.)
            return back()->with('error', '⚠ ' . $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('invoice.from_campaign.failed', [
                'campaign_id' => $campaign->id,
                'error'       => $e->getMessage(),
            ]);
            return back()->with('error', 'Erreur technique lors de la génération : ' . $e->getMessage());
        }

        \Illuminate\Support\Facades\Log::info('invoice.from_campaign.success', [
            'campaign_id'         => $campaign->id,
            'invoice_id'          => $invoice->id,
            'reference'           => $invoice->reference,
            'lines'               => $invoice->lines->count(),
            'total'               => $invoice->total_a_payer,
            'is_complementary'    => $existingCount > 0,
        ]);

        $msg = "Facture {$invoice->reference} générée — {$invoice->lines->count()} ligne(s), total "
             . number_format($invoice->total_a_payer, 0, ',', ' ') . ' FCFA. Vérifie et envoie.';
        if ($existingCount > 0) {
            $msg .= " ⚠ Cette campagne a déjà {$existingCount} facture(s) — vérifie qu'il ne s'agit pas d'un doublon.";
        }

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', $msg);
    }

    // ══════════════════════════════════════════════════════════════
    // Phase 8A cahier §3 — Actions manuelles cycle de vie complet
    // ══════════════════════════════════════════════════════════════

    /** Brouillon → Générée. Étape intermédiaire avant validation. */
    public function markGenerated(Request $request, Invoice $invoice)
    {
        $this->authorize('markSent', $invoice);
        try {
            $invoice->transitionStatusTo('generee', reason: 'Marquée générée manuellement', auto: false);
        } catch (\DomainException $e) {
            return back()->with('error', '🚫 ' . $e->getMessage());
        }
        return back()->with('success', '✅ Facture marquée GÉNÉRÉE.');
    }

    /**
     * Générée → Validée. Verrouille la facture (cf. transitionStatusTo)
     * et fige les taux ODP/TM sur les lignes (déjà fait à syncLines via
     * Commune::ratesAt(issued_at)). Le verrou interdit toute modif
     * ultérieure des lignes/services jusqu'à un unlock explicite.
     */
    public function markValidated(Request $request, Invoice $invoice)
    {
        $this->authorize('markSent', $invoice);
        try {
            $invoice->transitionStatusTo('validee', reason: 'Validée manuellement (verrouille la facture)', auto: false);
        } catch (\DomainException $e) {
            return back()->with('error', '🚫 ' . $e->getMessage());
        }
        return back()->with('success', '🔒 Facture VALIDÉE et verrouillée.');
    }

    /** Bascule en litige (facture contestée par le client). */
    public function markLitige(Request $request, Invoice $invoice)
    {
        $this->authorize('markSent', $invoice);

        $data = $request->validate(['reason' => 'nullable|string|max:500']);
        try {
            $invoice->transitionStatusTo(
                'litige',
                reason: $data['reason'] ?? 'Bascule en litige (manuelle)',
                auto: false
            );
        } catch (\DomainException $e) {
            return back()->with('error', '🚫 ' . $e->getMessage());
        }
        return back()->with('success', '⚠ Facture marquée EN LITIGE.');
    }

    public function markSent(Request $request, Invoice $invoice)
    {
        $this->authorize('markSent', $invoice);

        if ($invoice->status !== 'brouillon') {
            return $this->statusResponse($request, $invoice, false,
                "Seules les factures en brouillon peuvent être envoyées (statut actuel : {$invoice->status}).");
        }

        // ⚠ Même garde : pas d'envoi au client si la campagne est annulée
        // (le client serait surpris de recevoir une facture pour quelque
        // chose qu'on lui a dit ne pas exécuter).
        $invoice->loadMissing('campaign:id,status');
        if ($invoice->campaign?->status?->value === 'annule') {
            return $this->statusResponse($request, $invoice, false,
                "🚫 Campagne liée annulée — envoi au client bloqué. Annule la facture ou recrée-en une après reprise de la campagne.");
        }

        $invoice->update([
            'status'    => 'envoyee',
            'issued_at' => $invoice->issued_at ?? now(),
        ]);

        // ⚠ Verrouillage automatique à l'envoi : une facture envoyée est
        // un document fiscal — elle ne doit plus être modifiable (lignes,
        // remise, services) sauf déverrouillage explicite admin (action
        // tracée). L'unlock() reste réservé à l'admin via la policy.
        $invoice->lock(auth()->id());

        // ── Notification email client (lien public 30 jours sécurisé) ──
        $this->notifyClientInvoiceIssued($invoice);

        return $this->statusResponse($request, $invoice, true, 'Facture envoyée au client par email. 🔒 Verrouillée.');
    }

    // ══════════════════════════════════════════════════════════════
    // VERROUILLAGE / DÉVERROUILLAGE (admin only via policy)
    // ══════════════════════════════════════════════════════════════
    public function lock(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $invoice->lock(auth()->id());
        return back()->with('success', '🔒 Facture verrouillée.');
    }

    public function unlock(Request $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $invoice->unlock();
        \Illuminate\Support\Facades\Log::warning('invoice.unlocked', [
            'invoice_id' => $invoice->id,
            'by'         => auth()->id(),
            'previous_lock' => $invoice->locked_at?->toIso8601String(),
        ]);
        return back()->with('warning', '🔓 Facture déverrouillée — modifications possibles, mais l\'action est tracée.');
    }

    // ══════════════════════════════════════════════════════════════
    // VERSEMENTS — endpoints CRUD minimal
    //
    // Acompte / mensualités / solde — plusieurs paiements par facture
    // sont autorisés. Le statut de paiement de la facture est dérivé
    // (cf. Invoice::paymentStatus) — pas besoin de le maintenir
    // manuellement. Si la somme >= total_a_payer, la facture passe
    // automatiquement à 'payee' + paid_at = dernier versement.
    // ══════════════════════════════════════════════════════════════
    public function addPayment(Request $request, Invoice $invoice, \App\Services\PaymentService $payments)
    {
        $this->authorize('markPaid', $invoice);

        if ($invoice->status === 'annulee') {
            return back()->with('error', 'Impossible d\'ajouter un versement à une facture annulée.');
        }

        $data = $request->validate([
            'paid_at'    => 'required|date|before_or_equal:today',
            'montant'    => 'required|numeric|min:1',
            'mode'       => 'required|in:especes,cheque,virement,mobile_money,carte_bancaire,compensation,autre',
            'reference'  => 'nullable|string|max:100',
            'bank'       => 'nullable|string|max:100',
            'is_acompte' => 'sometimes|boolean',
            'attachment' => 'nullable|file|mimes:pdf,png,jpg,jpeg,webp|max:5120',
            'note'      => 'nullable|string|max:1000',
        ]);

        // Délégation au PaymentService (Phase 2 cahier §4 + §5)
        try {
            $payment = $payments->register(
                $invoice,
                $data,
                $request->file('attachment')
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', '🚫 ' . $e->getMessage());
        }

        $type = $payment->is_acompte ? 'Acompte' : 'Versement';
        return back()->with('success',
            '✅ ' . $type . ' de ' . number_format($payment->montant, 0, ',', ' ') . ' FCFA enregistré.'
        );
    }

    // ══════════════════════════════════════════════════════════════
    // ÉCHÉANCIER PRÉVISIONNEL
    //
    // L'admin configure des échéances de paiement (acompte / solde /
    // mensualités). Le rapprochement avec les versements réels est
    // manuel pour rester simple : l'admin coche "payée" sur une
    // échéance quand le versement correspondant est enregistré.
    //
    // Presets rapides :
    //   - 30 / 70 : 30% à commande + 70% à J+30
    //   - 50 / 50 : 50% à commande + 50% à J+30
    //   - 3 mensualités : 33% × 3 (J0, J+30, J+60)
    //   - custom : saisie libre
    // ══════════════════════════════════════════════════════════════
    public function generateSchedule(Request $request, Invoice $invoice, \App\Services\ScheduleGenerator $generator)
    {
        $this->authorize('markPaid', $invoice);

        // Phase 3 cahier §6 : 3 modes principaux + 3 presets legacy
        $data = $request->validate([
            'mode'       => 'required|in:custom_milestones,quarterly,monthly,30_70,50_50,monthly_3',
            'start_date' => 'nullable|date',
            'count'      => 'nullable|integer|min:2|max:24',
            // Pour custom_milestones :
            'milestones'              => 'nullable|array|min:1|max:24',
            'milestones.*.due_date'   => 'required_with:milestones|date',
            'milestones.*.amount'     => 'required_with:milestones|numeric|min:1',
            'milestones.*.label'      => 'nullable|string|max:100',
        ]);

        try {
            $generator->generate($invoice, [
                'mode'       => $data['mode'],
                'start_date' => $data['start_date'] ?? null,
                'count'      => $data['count'] ?? null,
                'milestones' => $data['milestones'] ?? null,
            ]);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', '🚫 ' . $e->getMessage());
        }

        return back()->with('success', '✅ Échéancier généré ('
            . $invoice->schedules()->count() . ' échéance(s)).');
    }

    public function markScheduleEntryPaid(Request $request, Invoice $invoice, \App\Models\InvoiceSchedule $schedule)
    {
        $this->authorize('markPaid', $invoice);
        if ($schedule->invoice_id !== $invoice->id) abort(404);

        if ($schedule->isPaid()) {
            return back()->with('info', 'Échéance déjà marquée payée.');
        }

        $data = $request->validate([
            'payment_id' => 'nullable|exists:invoice_payments,id',
            'paid_at'    => 'nullable|date|before_or_equal:today',
        ]);

        $schedule->update([
            'paid_at'          => $data['paid_at'] ?? now()->toDateString(),
            'paid_payment_id'  => $data['payment_id'] ?? null,
        ]);

        return back()->with('success', '✓ Échéance marquée payée.');
    }

    public function unmarkScheduleEntryPaid(Invoice $invoice, \App\Models\InvoiceSchedule $schedule)
    {
        $this->authorize('markPaid', $invoice);
        if ($schedule->invoice_id !== $invoice->id) abort(404);

        $schedule->update(['paid_at' => null, 'paid_payment_id' => null]);
        return back()->with('success', 'Échéance redevenue à payer.');
    }

    public function deleteSchedule(Invoice $invoice)
    {
        $this->authorize('markPaid', $invoice);
        $invoice->schedules()->delete();
        return back()->with('success', 'Échéancier supprimé.');
    }

    /**
     * Télécharge la pièce justificative d'un paiement (scan chèque,
     * reçu, etc.). Auth basique + check d'appartenance.
     */
    public function downloadPaymentAttachment(Invoice $invoice, \App\Models\InvoicePayment $payment)
    {
        $this->authorize('view', $invoice);

        if ($payment->invoice_id !== $invoice->id) abort(404);
        if (!$payment->attachment_path) abort(404, 'Aucune pièce jointe pour ce versement.');

        $disk = \App\Services\PaymentService::ATTACHMENT_DISK;
        if (!\Illuminate\Support\Facades\Storage::disk($disk)->exists($payment->attachment_path)) {
            abort(404, 'Fichier introuvable sur le serveur.');
        }

        return \Illuminate\Support\Facades\Storage::disk($disk)->download(
            $payment->attachment_path,
            $payment->attachment_original_name ?: basename($payment->attachment_path)
        );
    }

    public function removePayment(Request $request, Invoice $invoice, \App\Models\InvoicePayment $payment, \App\Services\PaymentService $payments)
    {
        $this->authorize('markPaid', $invoice);

        // Sécurité : le paiement doit appartenir à cette facture
        if ($payment->invoice_id !== $invoice->id) {
            abort(404);
        }

        // Délégation au PaymentService (suppression + nettoyage pièce
        // jointe + resync statut auto).
        $payments->delete($payment);

        \Illuminate\Support\Facades\Log::warning('invoice.payment.deleted', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'by'         => auth()->id(),
        ]);

        return back()->with('warning', 'Versement supprimé.');
    }

    /**
     * Envoie la facture au client par email avec un lien public sécurisé.
     * Try/catch — n'interrompt jamais le flux métier en cas d'erreur SMTP.
     */
    protected function notifyClientInvoiceIssued(Invoice $invoice, bool $isReminder = false, ?int $reminderNumber = null): void
    {
        $client = $invoice->client;
        if (!$client?->email) {
            \Illuminate\Support\Facades\Log::info('invoice.notify.skipped', [
                'invoice_id' => $invoice->id, 'reason' => 'no_client_email',
            ]);
            return;
        }

        try {
            $link = \App\Services\PublicLinkService::findOrCreate(
                target: $invoice,
                type:   'invoice',
                expiresAt: now()->addDays(30),
            );

            $mailer = app(\App\Services\NotificationMailer::class);
            $mailer->sendSilently(
                $client->email,
                new \App\Mail\InvoiceIssuedMail(
                    invoice: $invoice->loadMissing('client', 'campaign'),
                    link:    $link,
                    isReminder: $isReminder,
                    reminderNumber: $reminderNumber,
                ),
                cc: null,
                context: ['invoice_id' => $invoice->id, 'client_id' => $client->id, 'reminder' => $isReminder],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('invoice.notify.failed', [
                'invoice_id' => $invoice->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $this->authorize('markPaid', $invoice);

        if (!in_array($invoice->status, ['envoyee', 'brouillon'])) {
            return $this->statusResponse($request, $invoice, false,
                "Cette facture est déjà {$invoice->status}.");
        }

        // ⚠ Garde anti-incohérence : on ne marque pas une facture comme
        // payée si la campagne liée a été annulée. Le client n'est plus
        // censé devoir cette somme — la voie correcte est d'annuler la
        // facture (markCancelled). Si un paiement réel a déjà été reçu,
        // l'admin doit clarifier la situation (avoir, remboursement).
        $invoice->loadMissing('campaign:id,name,status');
        $campStatus = $invoice->campaign?->status?->value;
        if ($campStatus === 'annule') {
            return $this->statusResponse($request, $invoice, false,
                "🚫 Campagne liée annulée — paiement bloqué. Annule la facture (🚫) ou clarifie la situation avant de marquer payée.");
        }

        $invoice->update([
            'status'  => 'payee',
            'paid_at' => now(),
        ]);
        return $this->statusResponse($request, $invoice, true, 'Facture marquée comme payée. ✅');
    }

    /**
     * Annule une facture (équivalent "remboursée / abandonnée").
     * Conserve la trace pour l'historique fiscal.
     */
    public function markCancelled(Request $request, Invoice $invoice)
    {
        $this->authorize('markCancelled', $invoice);

        if ($invoice->status === 'annulee') {
            return $this->statusResponse($request, $invoice, false, 'Facture déjà annulée.');
        }

        $invoice->update([
            'status'  => 'annulee',
            'paid_at' => null,
        ]);
        return $this->statusResponse($request, $invoice, true, 'Facture annulée.');
    }

    /**
     * Bascule la facture vers brouillon — utile en cas d'erreur de saisie
     * (ex: marquée payée par erreur, à corriger avant pagination comptable).
     * Réinitialise paid_at pour rester cohérent avec le statut.
     */
    public function revertDraft(Request $request, Invoice $invoice)
    {
        $this->authorize('revertDraft', $invoice);

        if ($invoice->status === 'brouillon') {
            return $this->statusResponse($request, $invoice, false, 'Facture déjà en brouillon.');
        }

        $invoice->update([
            'status'  => 'brouillon',
            'paid_at' => null,
        ]);
        return $this->statusResponse($request, $invoice, true, 'Facture rebasculée en brouillon.');
    }

    /**
     * Réponse unifiée pour les actions de changement de statut.
     * En AJAX : renvoie le HTML de la ligne + les compteurs KPI rafraîchis,
     * pour permettre la mise à jour sans full page reload.
     */
    private function statusResponse(Request $request, Invoice $invoice, bool $ok, string $message)
    {
        if (!$request->expectsJson() && !$request->ajax()) {
            return back()->with($ok ? 'success' : 'error', $message);
        }

        $invoice->load('client', 'campaign', 'creator');

        // ⚠ Compteurs scopés au commercial — avant ils étaient globaux,
        // donc la réponse AJAX leakait le CA total entreprise même quand
        // c'était l'admin qui agissait sur la facture d'un commercial
        // (l'admin garde son contexte mais on évite l'incohérence).
        $user = auth()->user();
        $isCommercial = $user?->role?->value === 'commercial';
        $uid = (int) ($user?->id ?? 0);
        $base = fn() => Invoice::query()
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid));

        return response()->json([
            'success'  => $ok,
            'message'  => $message,
            'row_html' => view('admin.invoices.partials.row', ['invoice' => $invoice])->render(),
            'counts'   => [
                'brouillon' => (clone $base())->where('status', 'brouillon')->count(),
                'envoyee'   => (clone $base())->where('status', 'envoyee')->count(),
                'payee'     => (clone $base())->where('status', 'payee')->count(),
                'ca'        => (float) (clone $base())->where('status', 'payee')->sum('amount_ttc'),
            ],
        ], $ok ? 200 : 422);
    }

    public function exportPdf(Invoice $invoice)
    {
        $this->authorize('exportPdf', $invoice);

        $invoice->load('client', 'campaign', 'creator');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("facture-{$invoice->reference}.pdf");
    }

    /**
     * Construit la query partagée par les exports : reprend les filtres
     * client_id / status / date_from / date_to qu'on retrouve sur l'index.
     * Centralisé ici pour éviter la divergence index ↔ export.
     */
    private function filteredQuery(Request $request)
    {
        $user = auth()->user();
        $isCommercial = $user?->role?->value === 'commercial';
        $uid = (int) ($user?->id ?? 0);

        // ⚠ Source partagée par exportListPdf + exportListExcel +
        // InvoicesExport (Excel streaming). Avant : un commercial pouvait
        // appeler /admin/invoices/export/pdf et obtenir un PDF de TOUTES
        // les factures entreprise. Maintenant scopé à son périmètre.
        $q = Invoice::with(['client', 'campaign', 'creator'])
            ->when($isCommercial, fn($qq) => $qq->forCommercialUser($uid));

        if ($request->filled('client_id')) {
            $q->where('client_id', $request->client_id);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $q->where('issued_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->where('issued_at', '<=', $request->date_to);
        }

        return $q->orderByDesc('issued_at')->orderByDesc('id');
    }

    public function exportListPdf(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        return $this->exportListPdfInner($request);
    }

    public function exportListExcel(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $user = auth()->user();
        $isCommercial = $user?->role?->value === 'commercial';
        $filters = $request->only(['client_id', 'status', 'date_from', 'date_to']);

        // Propage le scope commercial au job Export (utilise les mêmes
        // filtres + un commercial_user_id si fourni). Sans ça, l'export
        // Excel streamait toutes les factures via une query parallèle.
        if ($isCommercial) {
            $filters['commercial_user_id'] = (int) $user->id;
        }

        $stamp = now()->format('Ymd-Hi');
        return (new \App\Exports\InvoicesExport($filters))
            ->download("factures-{$stamp}.xlsx");
    }

    /**
     * Implémentation PDF (séparée pour permettre l'override d'authz).
     * Garde la signature publique exportListPdf() inchangée pour les routes.
     */
    protected function exportListPdfInner(Request $request)
    {
        $invoices = $this->filteredQuery($request)->get();

        $clientName = null;
        if ($request->filled('client_id')) {
            $clientName = Client::where('id', $request->client_id)->value('name');
        }

        $filters = [
            'client_id'   => $request->client_id,
            'client_name' => $clientName,
            'status'      => $request->status,
            'date_from'   => $request->date_from,
            'date_to'     => $request->date_to,
        ];

        $pdf = Pdf::loadView('pdf.invoices-list', compact('invoices', 'filters'))
            ->setPaper('A4', 'landscape');

        $stamp = now()->format('Ymd-Hi');
        return $pdf->download("factures-{$stamp}.pdf");
    }
}

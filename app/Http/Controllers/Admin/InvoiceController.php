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
        $query = Invoice::with('client', 'campaign', 'creator');

        // RBAC : un commercial ne voit que les factures de SES campagnes
        // (via campaign.reservation.commercial_user_id ou campaign.user_id).
        if (auth()->user()?->role?->value === 'commercial') {
            $uid = auth()->id();
            $query->whereHas('campaign', function ($q) use ($uid) {
                $q->where(function ($qq) use ($uid) {
                    $qq->whereHas('reservation', fn($r) =>
                            $r->where('commercial_user_id', $uid)
                              ->orWhere(function ($rr) use ($uid) {
                                  $rr->whereNull('commercial_user_id')
                                     ->where('user_id', $uid);
                              })
                       )
                       ->orWhere(function ($qqq) use ($uid) {
                           $qqq->whereDoesntHave('reservation')
                               ->where('user_id', $uid);
                       });
                });
            });
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
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

        $invoices = $query->latest()->paginate(15)->withQueryString();
        $clients  = Client::orderBy('name')->get();

        $totalBrouillons = Invoice::where('status', 'brouillon')->count();
        $totalEnvoyees   = Invoice::where('status', 'envoyee')->count();
        $totalPayees     = Invoice::where('status', 'payee')->count();
        $montantTotal    = Invoice::where('status', 'payee')->sum('amount_ttc');
        
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

        return view('admin.invoices.index', compact(
            'invoices', 'clients',
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

    public function store(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'reference'   => 'required|unique:invoices,reference',
            'amount'      => 'required|numeric|min:0',
            'tva'         => 'required|numeric|min:0|max:100',
            'issued_at'   => 'required|date',
            'paid_at'     => 'nullable|date',
        ]);

        // Cohérence campagne ↔ client : si une campagne est liée à la
        // facture, son client_id DOIT correspondre à celui de la facture.
        // Sinon on a une facture orpheline : campagne X facturée au client Y.
        // On corrige d'autorité (le client de la campagne fait foi).
        $clientId = (int) $request->input('client_id');
        if ($request->filled('campaign_id')) {
            $camp = Campaign::find($request->input('campaign_id'));
            if ($camp && (int) $camp->client_id !== $clientId) {
                $clientId = (int) $camp->client_id;
            }
        }

        $amountTtc = $request->amount * (1 + $request->tva / 100);

        Invoice::create([
            ...$request->all(),
            'client_id'  => $clientId,
            'amount_ttc' => $amountTtc,
            'created_by' => auth()->id(),
            'status'     => 'brouillon',
        ]);

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Facture créée avec succès !');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load([
            'client',
            'campaign:id,name,client_id,status,start_date,end_date,total_amount,total_panels',
            'campaign.reservation:id,reference',
            'creator',
        ]);

        // Autres factures du même client (max 6, exclut celle-ci) — colonne droite
        $otherInvoices = $invoice->client_id
            ? Invoice::where('client_id', $invoice->client_id)
                ->where('id', '!=', $invoice->id)
                ->orderByDesc('issued_at')->orderByDesc('id')
                ->limit(6)
                ->get(['id', 'reference', 'amount', 'amount_ttc', 'tva', 'status', 'issued_at', 'paid_at', 'campaign_id'])
            : collect();

        // Stats globales client : nb factures + total payé + total dû
        $clientStats = null;
        if ($invoice->client_id) {
            $allClientInvoices = Invoice::where('client_id', $invoice->client_id)
                ->select('status', 'amount_ttc')
                ->get();
            $clientStats = [
                'count_total'   => $allClientInvoices->count(),
                'count_paid'    => $allClientInvoices->where('status', 'payee')->count(),
                'count_pending' => $allClientInvoices->whereIn('status', ['brouillon', 'envoyee'])->count(),
                'sum_paid_ttc'  => (float) $allClientInvoices->where('status', 'payee')->sum('amount_ttc'),
                'sum_pending_ttc' => (float) $allClientInvoices->whereIn('status', ['brouillon', 'envoyee'])->sum('amount_ttc'),
            ];
        }

        // Récap campagne si liée : déjà facturé / reste à facturer
        $campaignBilling = null;
        if ($invoice->campaign) {
            $campaignBilling = [
                'expected_ht'  => $invoice->campaign->computedAmountHt(),
                'billed_ht'    => $invoice->campaign->alreadyBilledHt(),
                'remaining_ht' => $invoice->campaign->remainingToBillHt(),
            ];
        }

        return view('admin.invoices.show', compact(
            'invoice', 'otherInvoices', 'clientStats', 'campaignBilling'
        ));
    }

    public function edit(Invoice $invoice)
    {
        $clients   = Client::orderBy('name')->get();
        $campaigns = Campaign::with('client:id,name')->orderBy('name')->get();
        return view('admin.invoices.edit', compact(
            'invoice', 'clients', 'campaigns'
        ));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'amount'      => 'required|numeric|min:0',
            'tva'         => 'required|numeric|min:0|max:100',
            'issued_at'   => 'required|date',
            'status'      => 'required|in:brouillon,envoyee,payee,annulee',
        ]);

        // Cohérence campagne ↔ client (cf. store).
        $clientId = (int) $request->input('client_id');
        if ($request->filled('campaign_id')) {
            $camp = Campaign::find($request->input('campaign_id'));
            if ($camp && (int) $camp->client_id !== $clientId) {
                $clientId = (int) $camp->client_id;
            }
        }

        $amountTtc = $request->amount * (1 + $request->tva / 100);

        $invoice->update([
            ...$request->except('_token', '_method'),
            'client_id'  => $clientId,
            'amount_ttc' => $amountTtc,
        ]);

        return redirect()->route('admin.invoices.show', $invoice)
            ->with('success', 'Facture modifiée !');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return redirect()->route('admin.invoices.index')
            ->with('success', 'Facture supprimée !');
    }

    public function markSent(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'brouillon') {
            return $this->statusResponse($request, $invoice, false,
                "Seules les factures en brouillon peuvent être envoyées (statut actuel : {$invoice->status}).");
        }

        $invoice->update([
            'status'    => 'envoyee',
            'issued_at' => $invoice->issued_at ?? now(),
        ]);

        // ── Notification email client (lien public 30 jours sécurisé) ──
        $this->notifyClientInvoiceIssued($invoice);

        return $this->statusResponse($request, $invoice, true, 'Facture envoyée au client par email.');
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
        if (!in_array($invoice->status, ['envoyee', 'brouillon'])) {
            return $this->statusResponse($request, $invoice, false,
                "Cette facture est déjà {$invoice->status}.");
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

        return response()->json([
            'success'  => $ok,
            'message'  => $message,
            'row_html' => view('admin.invoices.partials.row', ['invoice' => $invoice])->render(),
            'counts'   => [
                'brouillon' => Invoice::where('status', 'brouillon')->count(),
                'envoyee'   => Invoice::where('status', 'envoyee')->count(),
                'payee'     => Invoice::where('status', 'payee')->count(),
                'ca'        => (float) Invoice::where('status', 'payee')->sum('amount_ttc'),
            ],
        ], $ok ? 200 : 422);
    }

    public function exportPdf(Invoice $invoice)
    {
        $invoice->load('client', 'campaign', 'creator');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("facture-{$invoice->reference}.pdf");
    }

    /**
     * Export PDF du listing complet (filtres index appliqués).
     * A4 paysage pour caser les 10 colonnes sans tronquer.
     */
    public function exportListPdf(Request $request)
    {
        $invoices = $this->filteredQuery($request)->get();

        // Récupère le nom du client filtré pour l'afficher dans le bandeau
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

    /**
     * Export Excel du listing (filtres index appliqués). Streaming via
     * FromQuery pour gérer les gros volumes sans saturer la RAM.
     */
    public function exportListExcel(Request $request)
    {
        $filters = $request->only(['client_id', 'status', 'date_from', 'date_to']);
        $stamp = now()->format('Ymd-Hi');
        return (new \App\Exports\InvoicesExport($filters))
            ->download("factures-{$stamp}.xlsx");
    }

    /**
     * Construit la query partagée par les exports : reprend les filtres
     * client_id / status / date_from / date_to qu'on retrouve sur l'index.
     * Centralisé ici pour éviter la divergence index ↔ export.
     */
    private function filteredQuery(Request $request)
    {
        $q = Invoice::with(['client', 'campaign', 'creator']);

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
}

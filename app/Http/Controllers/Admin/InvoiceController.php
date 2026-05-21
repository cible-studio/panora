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

    public function create()
    {
        $clients   = Client::orderBy('name')->get();
        $campaigns = Campaign::with('client')->orderBy('name')->get();

        // Générer référence automatique
        $reference = 'FAC-' . date('Y') . '-' . str_pad(
            Invoice::whereYear('created_at', date('Y'))->count() + 1,
            3, '0', STR_PAD_LEFT
        );

        return view('admin.invoices.create', compact(
            'clients', 'campaigns', 'reference'
        ));
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

        $amountTtc = $request->amount * (1 + $request->tva / 100);

        Invoice::create([
            ...$request->all(),
            'amount_ttc' => $amountTtc,
            'created_by' => auth()->id(),
            'status'     => 'brouillon',
        ]);

        return redirect()->route('admin.invoices.index')
            ->with('success', 'Facture créée avec succès !');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('client', 'campaign', 'creator');
        return view('admin.invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $clients   = Client::orderBy('name')->get();
        $campaigns = Campaign::orderBy('name')->get();
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

        $amountTtc = $request->amount * (1 + $request->tva / 100);

        $invoice->update([
            ...$request->except('_token', '_method'),
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

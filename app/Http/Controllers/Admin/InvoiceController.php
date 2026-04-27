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

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
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

    public function markSent(Invoice $invoice)
    {
        $invoice->update(['status' => 'envoyee']);
        return back()->with('success', 'Facture marquée comme envoyée !');
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice->update([
            'status'  => 'payee',
            'paid_at' => now(),
        ]);
        return back()->with('success', 'Facture marquée comme payée ! ✅');
    }

    public function exportPdf(Invoice $invoice)
    {
        $invoice->load('client', 'campaign', 'creator');

        $pdf = Pdf::loadView('pdf.invoice', compact('invoice'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("facture-{$invoice->reference}.pdf");
    }
}

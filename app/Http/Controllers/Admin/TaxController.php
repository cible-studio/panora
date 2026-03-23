<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Commune;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $query = Tax::with('commune');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $taxes   = $query->latest()->paginate(15)->withQueryString();
        $communes = Commune::orderBy('name')->get();

        $totalEnAttente = Tax::where('status', 'en_attente')->count();
        $totalPayees    = Tax::where('status', 'payee')->count();
        $totalEnRetard  = Tax::where('status', 'en_retard')->count();
        $montantTotal   = Tax::where('status', 'en_attente')
                             ->orWhere('status', 'en_retard')
                             ->sum('amount');

        return view('admin.taxes.index', compact(
            'taxes', 'communes',
            'totalEnAttente', 'totalPayees',
            'totalEnRetard', 'montantTotal'
        ));
    }

    public function create()
    {
        $communes = Commune::orderBy('name')->get();
        return view('admin.taxes.create', compact('communes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'year'       => 'required|integer|min:2000|max:2099',
            'type'       => 'required|in:odp,tm',
            'amount'     => 'required|numeric|min:0',
            'due_date'   => 'nullable|date',
        ]);

        Tax::create($request->all());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe créée avec succès !');
    }

    public function show(Tax $tax)
    {
        $tax->load('commune');
        return view('admin.taxes.show', compact('tax'));
    }

    public function edit(Tax $tax)
    {
        $communes = Commune::orderBy('name')->get();
        return view('admin.taxes.edit', compact('tax', 'communes'));
    }

    public function update(Request $request, Tax $tax)
    {
        $request->validate([
            'commune_id' => 'required|exists:communes,id',
            'year'       => 'required|integer|min:2000|max:2099',
            'type'       => 'required|in:odp,tm',
            'amount'     => 'required|numeric|min:0',
            'due_date'   => 'nullable|date',
            'status'     => 'required|in:en_attente,payee,en_retard',
        ]);

        $tax->update($request->all());

        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe modifiée !');
    }

    public function destroy(Tax $tax)
    {
        $tax->delete();
        return redirect()->route('admin.taxes.index')
            ->with('success', 'Taxe supprimée !');
    }

    public function markPaid(Request $request, Tax $tax)
    {
        $tax->update([
            'status'  => 'payee',
            'paid_at' => now(),
        ]);

        return back()->with('success', 'Taxe marquée comme payée ! ✅');
    }

    public function exportPdf(Request $request)
    {
        $query = Tax::with('commune');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        $taxes    = $query->orderBy('year', 'desc')->get();
        $commune  = $request->filled('commune_id')
            ? Commune::find($request->commune_id)
            : null;

        $pdf = Pdf::loadView('pdf.taxes-report', compact('taxes', 'commune'));
        $pdf->setPaper('A4', 'landscape');

        return $pdf->download('rapport-taxes.pdf');
    }
}

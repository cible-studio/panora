<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tax;
use App\Models\Commune;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class TaxController extends Controller
{
    // Dans TaxController.php - méthode index()
    public function index(Request $request)
    {
        $query = Tax::with('commune');

        if ($request->filled('commune_id')) {
            $query->where('commune_id', $request->commune_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $taxes = $query->orderBy('year', 'desc')->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        
        $communes = Commune::orderBy('name')->get();
        
        // Stats
        $totalEnAttente = Tax::where('status', 'en_attente')->count();
        $totalPayees = Tax::where('status', 'payee')->count();
        $totalEnRetard = Tax::where('status', 'en_retard')->count();
        $montantTotal = Tax::where('status', '!=', 'payee')->sum('amount');
        
        // ✅ AJAX response
        if ($request->ajax() || $request->input('ajax')) {
            $html = view('admin.taxes.partials.table-rows', compact('taxes'))->render();
            $paginationHtml = $taxes->hasPages() ? $taxes->links()->render() : '';
            return response()->json([
                'html' => $html,
                'pagination' => $paginationHtml,
                'total' => $taxes->total(),
            ]);
        }
        
        return view('admin.taxes.index', compact('taxes', 'communes', 'totalEnAttente', 'totalPayees', 'totalEnRetard', 'montantTotal'));
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

    /**
     * Pré-visualise les lignes Tax qui seraient créées pour l'année donnée.
     * Sans effet de bord — l'admin déclenche la création via generateAuto().
     */
    public function previewAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        $year = (int) $request->input('year', date('Y'));
        if ($year < 2000 || $year > 2099) {
            return response()->json(['error' => 'Année invalide.'], 422);
        }
        return response()->json($service->preview($year));
    }

    /**
     * Applique la génération automatique des taxes pour l'année donnée.
     * Idempotent : ré-appel sur la même année ne crée rien si les lignes
     * existent déjà.
     */
    public function generateAuto(Request $request, \App\Services\TaxAutoService $service)
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2099',
        ]);

        $result = $service->apply((int) $request->input('year'));

        $msg = $result['created'] > 0
            ? sprintf('%d taxe(s) créée(s) pour %d (%s FCFA).',
                     $result['created'], $result['year'], number_format($result['total_amount'], 0, ',', ' '))
            : 'Aucune nouvelle taxe à créer pour ' . $result['year'] . '.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(array_merge(['success' => true, 'message' => $msg], $result));
        }
        return redirect()->route('admin.taxes.index', ['year' => $result['year']])
            ->with('success', $msg);
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

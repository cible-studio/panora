<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Proposition;
use App\Models\Client;
use App\Models\Panel;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PropositionController extends Controller
{
    public function index(Request $request)
    {
        $query = Proposition::with('client', 'creator');

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('search')) {
            $query->where('numero', 'like', '%'.$request->search.'%')
                  ->orWhereHas('client', function($q) use ($request) {
                      $q->where('name', 'like', '%'.$request->search.'%');
                  });
        }

        $propositions = $query->latest()->paginate(15)->withQueryString();
        $clients      = Client::orderBy('name')->get();

        $totalEnAttente = Proposition::where('statut', 'en_attente')->count();
        $totalAcceptees = Proposition::where('statut', 'acceptee')->count();
        $totalRefusees  = Proposition::where('statut', 'refusee')->count();
        $totalExpirees  = Proposition::where('statut', 'expiree')->count();

        return view('admin.propositions.index', compact(
            'propositions', 'clients',
            'totalEnAttente', 'totalAcceptees',
            'totalRefusees', 'totalExpirees'
        ));
    }

    public function create()
    {
        $clients = Client::orderBy('name')->get();
        $panels  = Panel::with('commune', 'format')
                        ->where('status', 'libre')
                        ->orderBy('reference')
                        ->get();

        // Générer numéro automatique
        $numero = 'PRO-' . str_pad(
            Proposition::count() + 1, 3, '0', STR_PAD_LEFT
        );

        return view('admin.propositions.create', compact(
            'clients', 'panels', 'numero'
        ));
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'numero'      => 'required|unique:propositions,numero',
            'date_debut'  => 'required|date',
            'date_fin'    => 'required|date|after:date_debut',
            'montant'     => 'required|numeric|min:0',
            'nb_panneaux' => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        Proposition::create([
            ...$request->all(),
            'created_by' => auth()->id(),
            'statut'     => 'en_attente',
        ]);

        return redirect()->route('admin.propositions.index')
            ->with('success', 'Proposition créée avec succès !');
    }

    public function show(Proposition $proposition)
    {
        $proposition->load('client', 'creator');
        return view('admin.propositions.show', compact('proposition'));
    }

    public function edit(Proposition $proposition)
    {
        $clients = Client::orderBy('name')->get();
        return view('admin.propositions.edit', compact('proposition', 'clients'));
    }

    public function update(Request $request, Proposition $proposition)
    {
        $request->validate([
            'client_id'   => 'required|exists:clients,id',
            'date_debut'  => 'required|date',
            'date_fin'    => 'required|date|after:date_debut',
            'montant'     => 'required|numeric|min:0',
            'nb_panneaux' => 'required|integer|min:1',
            'notes'       => 'nullable|string',
        ]);

        $proposition->update($request->all());

        return redirect()->route('admin.propositions.show', $proposition)
            ->with('success', 'Proposition modifiée !');
    }

    public function destroy(Proposition $proposition)
    {
        $proposition->delete();
        return redirect()->route('admin.propositions.index')
            ->with('success', 'Proposition supprimée !');
    }

    public function updateStatus(Request $request, Proposition $proposition)
    {
        $request->validate([
            'statut' => 'required|in:en_attente,acceptee,refusee,expiree'
        ]);

        $proposition->update(['statut' => $request->statut]);

        return back()->with('success', 'Statut mis à jour !');
    }

    public function exportPdf(Proposition $proposition)
    {
        $proposition->load('client', 'creator');

        $pdf = Pdf::loadView('pdf.proposition', compact('proposition'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download("proposition-{$proposition->numero}.pdf");
    }
}

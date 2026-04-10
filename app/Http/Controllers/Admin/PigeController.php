<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pige;
use App\Models\Panel;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PigeController extends Controller
{
    public function index(Request $request)
    {
        $query = Pige::with('panel', 'campaign', 'takenBy', 'verifiedBy');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }
        if ($request->filled('panel_id')) {
            $query->where('panel_id', $request->panel_id);
        }
        if ($request->filled('is_verified')) {
            $query->where('is_verified', $request->is_verified);
        }

        $piges = $query->latest()->paginate(20)->withQueryString();
        $campaigns = Campaign::orderBy('name')->get();
        $panels = Panel::orderBy('reference')->get();

        $totalPiges = Pige::count();
        $totalVerifiees = Pige::where('is_verified', true)->count();
        $totalEnAttente = Pige::where('is_verified', false)->count();

        return view('admin.piges.index', compact(
            'piges',
            'campaigns',
            'panels',
            'totalPiges',
            'totalVerifiees',
            'totalEnAttente'
        ));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'panel_id' => 'required|exists:panels,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'photo' => 'required|image|mimes:jpeg,jpg,png,webp|max:8192',
            'taken_at' => 'required|date|before_or_equal:now',
            'gps_lat' => 'nullable|numeric|between:-90,90',   // ← ajouter
            'gps_lng' => 'nullable|numeric|between:-180,180', // ← ajouter
            'notes' => 'nullable|string|max:1000',
        ]);

        $path = $request->file('photo')->store('piges', 'public');

        Pige::create([
            'panel_id' => $request->panel_id,
            'campaign_id' => $request->campaign_id,
            'user_id' => auth()->id(),
            'photo_path' => $path,
            'taken_at' => $request->date_prise,
            'gps_lat' => $request->gps_lat,
            'gps_lng' => $request->gps_lng,
            'notes' => $request->notes,
            'is_verified' => false,
        ]);

        return redirect()->route('admin.piges.index')
            ->with('success', 'Pige uploadée avec succès !');
    }

    public function show(Pige $pige)
    {
        $pige->load('panel', 'campaign', 'takenBy', 'verifiedBy');
        return view('admin.piges.show', compact('pige'));
    }

    public function verify(Request $request, Pige $pige)
    {
        $pige->update([
            'is_verified' => true,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Pige vérifiée avec succès ! ✅');
    }

    public function destroy(Pige $pige)
    {
        // Supprimer le fichier
        \Storage::disk('public')->delete($pige->photo_path);
        $pige->delete();

        return redirect()->route('admin.piges.index')
            ->with('success', 'Pige supprimée !');
    }

    public function exportPdf(Request $request)
    {
        $query = Pige::with('panel', 'campaign', 'takenBy');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->campaign_id);
        }

        $piges = $query->latest()->get();
        $campaign = $request->filled('campaign_id')
            ? Campaign::find($request->campaign_id)
            : null;

        $pdf = Pdf::loadView('pdf.piges-report', compact('piges', 'campaign'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('rapport-piges.pdf');
    }
}

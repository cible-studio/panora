<?php
// app/Http/Controllers/Admin/AlertController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // INDEX — avec filtres et stats
    // ══════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        $query = Alert::latest();

        if ($request->filled('niveau')) {
            $query->where('niveau', $request->niveau);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->boolean('non_lues')) {
            $query->where('is_read', false);
        }

        $alertes = $query->paginate(25)->withQueryString();

        // Stats globales (1 seule requête)
        $raw = Alert::selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as non_lues,
            SUM(CASE WHEN niveau = 'danger'  AND is_read = 0 THEN 1 ELSE 0 END) as danger,
            SUM(CASE WHEN niveau = 'warning' AND is_read = 0 THEN 1 ELSE 0 END) as warning,
            SUM(CASE WHEN niveau = 'info'    AND is_read = 0 THEN 1 ELSE 0 END) as info
        ")->first();

        $totalNonLues = (int) ($raw->non_lues ?? 0);
        $totalDanger  = (int) ($raw->danger   ?? 0);
        $totalWarning = (int) ($raw->warning  ?? 0);
        $totalInfo    = (int) ($raw->info     ?? 0);

        // Types distincts pour le filtre
        $types = Alert::distinct()->pluck('type')->sort()->values();

        return view('admin.alertes.index', compact(
            'alertes', 'totalNonLues', 'totalDanger', 'totalWarning', 'totalInfo', 'types'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // MARQUER UNE ALERTE LUE
    // ══════════════════════════════════════════════════════════════
    public function markRead(Alert $alert)
    {
        $alert->update(['is_read' => true]);
        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Alerte marquée comme lue.');
    }

    // ══════════════════════════════════════════════════════════════
    // TOUT MARQUER LU
    // ══════════════════════════════════════════════════════════════
    public function markAllRead()
    {
        Alert::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'Toutes les alertes ont été marquées comme lues.');
    }

    // ══════════════════════════════════════════════════════════════
    // SUPPRIMER
    // ══════════════════════════════════════════════════════════════
    public function destroy(Alert $alert)
    {
        $alert->delete();
        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Alerte supprimée.');
    }

    // ══════════════════════════════════════════════════════════════
    // API — badge sidebar (non lues par module)
    // GET /admin/alerts/summary
    // ══════════════════════════════════════════════════════════════
    public function summary()
    {
        $data = Alert::where('is_read', false)
            ->selectRaw('type, niveau, COUNT(*) as count')
            ->groupBy('type', 'niveau')
            ->get();

        $result = [];
        foreach ($data as $row) {
            $result[$row->type][$row->niveau] = $row->count;
        }
        $result['_total'] = Alert::where('is_read', false)->count();

        return response()->json($result);
    }
}
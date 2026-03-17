<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use App\Models\Commune;
use Illuminate\Http\Request;

class ZoneController extends Controller
{
    public function index()
    {
        $zones = Zone::with('commune')->latest()->paginate(15);
        return view('settings.zones.index', compact('zones'));
    }

    public function create()
    {
        $communes = Commune::orderBy('name')->get();
        return view('settings.zones.create', compact('communes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'commune_id'   => 'nullable|exists:communes,id',
            'description'  => 'nullable|string',
            'demand_level' => 'required|in:faible,normale,haute,tres_haute',
        ]);

        Zone::create($request->all());

        return redirect()->route('admin.settings.zones.index')
            ->with('success', 'Zone créée avec succès !');
    }

    public function edit(Zone $zone)
    {
        $communes = Commune::orderBy('name')->get();
        return view('settings.zones.edit', compact('zone', 'communes'));
    }

    public function update(Request $request, Zone $zone)
    {
        $request->validate([
            'name'         => 'required|string|max:100',
            'commune_id'   => 'nullable|exists:communes,id',
            'description'  => 'nullable|string',
            'demand_level' => 'required|in:faible,normale,haute,tres_haute',
        ]);

        $zone->update($request->all());

        return redirect()->route('admin.settings.zones.index')
            ->with('success', 'Zone modifiée avec succès !');
    }

    public function destroy(Zone $zone)
    {
        $zone->delete();
        return redirect()->route('admin.settings.zones.index')
            ->with('success', 'Zone supprimée !');
    }
}

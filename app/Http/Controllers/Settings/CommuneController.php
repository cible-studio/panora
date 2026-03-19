<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use Illuminate\Http\Request;

class CommuneController extends Controller
{
    public function index()
    {
        $communes = Commune::latest()->paginate(15);
        return view('settings.communes.index', compact('communes'));
    }

    public function create()
    {
        return view('settings.communes.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
        ]);

        Commune::create($request->all());

        return redirect()->route('admin.settings.communes.index')
            ->with('success', 'Commune créée avec succès !');
    }

    public function edit(Commune $commune)
    {
        return view('settings.communes.edit', compact('commune'));
    }

    public function update(Request $request, Commune $commune)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
        ]);

        $commune->update($request->all());

        return redirect()->route('admin.settings.communes.index')
            ->with('success', 'Commune modifiée avec succès !');
    }

    public function destroy(Commune $commune)
    {
        $commune->delete();
        return redirect()->route('admin.settings.communes.index')
            ->with('success', 'Commune supprimée !');
    }
}

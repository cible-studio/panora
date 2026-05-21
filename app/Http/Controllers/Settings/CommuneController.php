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
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
            'db_rate'  => 'nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;
        $data['db_rate']  = $data['db_rate']  ?? 0;

        Commune::create($data);

        return redirect()->route('admin.settings.communes.index')
            ->with('success', 'Commune créée avec succès !');
    }

    public function edit(Commune $commune)
    {
        // Charge l'historique tarifaire pour timeline dans le formulaire.
        $commune->load('rateHistory.createdBy:id,name');
        return view('settings.communes.edit', compact('commune'));
    }

    public function update(Request $request, Commune $commune)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'city'     => 'nullable|string|max:100',
            'region'   => 'nullable|string|max:100',
            'odp_rate' => 'nullable|numeric|min:0',
            'tm_rate'  => 'nullable|numeric|min:0',
            'db_rate'  => 'nullable|numeric|min:0',
        ]);

        $data['odp_rate'] = $data['odp_rate'] ?? 0;
        $data['tm_rate']  = $data['tm_rate']  ?? 0;
        $data['db_rate']  = $data['db_rate']  ?? 0;

        $commune->update($data);

        // L'observer CommuneObserver historise automatiquement les
        // changements de tarif — on précise juste ça à l'admin si un
        // tarif a effectivement bougé.
        $changed = array_intersect_key($commune->getChanges(), array_flip(['odp_rate', 'tm_rate', 'db_rate']));
        $msg = 'Commune modifiée avec succès !';
        if (!empty($changed)) {
            $msg .= ' Tarifs historisés (les calculs rétroactifs gardent les anciennes valeurs).';
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('admin.settings.communes.index')
            ->with('success', $msg);
    }

    public function destroy(Commune $commune)
    {
        $commune->delete();
        return redirect()->route('admin.settings.communes.index')
            ->with('success', 'Commune supprimée !');
    }
}

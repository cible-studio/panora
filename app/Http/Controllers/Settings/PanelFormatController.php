<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PanelFormat;
use Illuminate\Http\Request;

class PanelFormatController extends Controller
{
    public function index()
    {
        $formats = PanelFormat::latest()->paginate(15);
        return view('settings.formats.index', compact('formats'));
    }

    public function create()
    {
        return view('settings.formats.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'width'      => 'nullable|numeric|min:0',
            'height'     => 'nullable|numeric|min:0',
            'surface'    => 'nullable|numeric|min:0',
            'print_type' => 'nullable|string|max:80',
        ]);

        PanelFormat::create($request->all());

        return redirect()->route('admin.settings.formats.index')
            ->with('success', 'Format créé avec succès !');
    }

    public function edit(PanelFormat $format)
    {
        return view('settings.formats.edit', compact('format'));
    }

    public function update(Request $request, PanelFormat $format)
    {
        $request->validate([
            'name'       => 'required|string|max:50',
            'width'      => 'nullable|numeric|min:0',
            'height'     => 'nullable|numeric|min:0',
            'surface'    => 'nullable|numeric|min:0',
            'print_type' => 'nullable|string|max:80',
        ]);

        $format->update($request->all());

        return redirect()->route('admin.settings.formats.index')
            ->with('success', 'Format modifié avec succès !');
    }

    public function destroy(PanelFormat $format)
    {
        $format->delete();
        return redirect()->route('admin.settings.formats.index')
            ->with('success', 'Format supprimé !');
    }
}

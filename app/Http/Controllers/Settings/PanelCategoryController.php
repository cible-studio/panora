<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PanelCategory;
use Illuminate\Http\Request;

class PanelCategoryController extends Controller
{
    public function index()
    {
        $categories = PanelCategory::latest()->paginate(15);
        return view('settings.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('settings.categories.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        PanelCategory::create($request->all());

        return redirect()->route('admin.settings.categories.index')
            ->with('success', 'Catégorie créée avec succès !');
    }

    public function edit(PanelCategory $category)
    {
        return view('settings.categories.edit', compact('category'));
    }

    public function update(Request $request, PanelCategory $category)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $category->update($request->all());

        return redirect()->route('admin.settings.categories.index')
            ->with('success', 'Catégorie modifiée avec succès !');
    }

    public function destroy(PanelCategory $category)
    {
        $category->delete();
        return redirect()->route('admin.settings.categories.index')
            ->with('success', 'Catégorie supprimée !');
    }
}

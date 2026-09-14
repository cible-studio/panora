<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PanelCategory;
use Illuminate\Http\Request;

class PanelCategoryController extends Controller
{
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

        $category = PanelCategory::create($request->all());

        return $this->redirectToSettingsRecord($category, 'categories')
            ->with('success', 'Catégorie « ' . $category->name . ' » créée avec succès !');
    }

    public function update(Request $request, PanelCategory $category)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);

        $category->update($request->all());

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Catégorie modifiée avec succès !']);
        }

        return $this->redirectToSettingsRecord($category, 'categories')
            ->with('success', 'Catégorie « ' . $category->name . ' » modifiée avec succès !');
    }

    public function destroy(PanelCategory $category)
    {
        $category->delete();
        return redirect()->route('admin.settings.index')
            ->with('success', 'Catégorie supprimée !');
    }
}

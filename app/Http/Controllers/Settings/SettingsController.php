<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\PanelCategory;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 8;

        $communes   = Commune::orderBy('name')
            ->paginate($perPage, ['*'], 'communes_page');

        $zones      = Zone::with('commune')->orderBy('name')
            ->paginate($perPage, ['*'], 'zones_page');

        $formats    = PanelFormat::orderBy('name')
            ->paginate($perPage, ['*'], 'formats_page');

        $categories = PanelCategory::orderBy('name')
            ->paginate($perPage, ['*'], 'categories_page');

        // Liste complète des communes — utilisée par le modal d'édition rapide
        // d'une zone pour peupler le select sans recharger la page.
        $allCommunes = Commune::orderBy('name')->get(['id', 'name']);

        return view('settings.index', compact(
            'communes', 'zones', 'formats', 'categories', 'allCommunes'
        ));
    }
}

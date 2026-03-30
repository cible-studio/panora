<?php
namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\PanelCategory;

class SettingsController extends Controller
{
    public function index()
    {
        $communes   = Commune::orderBy('name')->get();
        $zones      = Zone::with('commune')->orderBy('name')->get();
        $formats    = PanelFormat::orderBy('name')->get();
        $categories = PanelCategory::orderBy('name')->get();

        return view('settings.index', compact(
            'communes', 'zones', 'formats', 'categories'
        ));
    }
}

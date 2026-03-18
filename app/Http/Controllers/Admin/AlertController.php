<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index()
    {
        $alertes = Alert::latest()->paginate(20);

        $totalNonLues = Alert::where('is_read', false)->count();
        $totalDanger  = Alert::where('niveau', 'danger')->where('is_read', false)->count();
        $totalWarning = Alert::where('niveau', 'warning')->where('is_read', false)->count();
        $totalInfo    = Alert::where('niveau', 'info')->where('is_read', false)->count();

        return view('admin.alertes.index', compact(
            'alertes',
            'totalNonLues',
            'totalDanger',
            'totalWarning',
            'totalInfo'
        ));
    }

    public function markRead(Alert $alert)
    {
        $alert->update(['is_read' => true]);
        return back()->with('success', 'Alerte marquée comme lue !');
    }

    public function markAllRead()
    {
        Alert::where('is_read', false)->update(['is_read' => true]);
        return back()->with('success', 'Toutes les alertes marquées comme lues !');
    }

    public function destroy(Alert $alert)
    {
        $alert->delete();
        return back()->with('success', 'Alerte supprimée !');
    }
}

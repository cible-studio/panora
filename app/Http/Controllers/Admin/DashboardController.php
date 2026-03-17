<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Panel;
use App\Models\Reservation;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Maintenance;
use App\Models\Alert;

class DashboardController extends Controller
{
    public function index()
    {
        // ── STATS PANNEAUX ──
        $totalPanneaux       = Panel::count();
        $panneauxLibres      = Panel::where('status', 'libre')->count();
        $panneauxOccupes     = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $panneauxMaintenance = Panel::where('status', 'maintenance')->count();

        // ── STATS RÉSERVATIONS ──
        $reservationsEnAttente  = Reservation::where('status', 'en_attente')->count();
        $reservationsConfirmees = Reservation::where('status', 'confirme')->count();

        // ── STATS CAMPAGNES ──
        $campagnesActives   = Campaign::where('status', 'actif')->count();
        $campagnesTerminees = Campaign::where('status', 'termine')->count();

        // ── STATS CLIENTS ──
        $totalClients = Client::count();

        // ── MAINTENANCES URGENTES ──
        $maintenancesUrgentes = Maintenance::where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')
            ->count();

        // ── ALERTES NON LUES ──
        $alertesNonLues = Alert::where('is_read', false)->count();

        // ── DERNIÈRES RÉSERVATIONS ──
        $dernieresReservations = Reservation::with('client', 'agent')
            ->latest()
            ->take(5)
            ->get();

        // ── DERNIÈRES MAINTENANCES ──
        $dernieresMaintenances = Maintenance::with('panel', 'signaledBy')
            ->where('statut', '!=', 'resolu')
            ->orderBy('priorite', 'desc')
            ->take(5)
            ->get();

        // ── TAUX D'OCCUPATION ──
        $tauxOccupation = $totalPanneaux > 0
            ? round(($panneauxOccupes / $totalPanneaux) * 100, 1)
            : 0;

        return view('dashboard', compact(
            'totalPanneaux',
            'panneauxLibres',
            'panneauxOccupes',
            'panneauxMaintenance',
            'reservationsEnAttente',
            'reservationsConfirmees',
            'campagnesActives',
            'campagnesTerminees',
            'totalClients',
            'maintenancesUrgentes',
            'alertesNonLues',
            'dernieresReservations',
            'dernieresMaintenances',
            'tauxOccupation'
        ));
    }
}

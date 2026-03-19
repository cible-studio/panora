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
        $totalPanneaux       = Panel::count();
        $panneauxLibres      = Panel::where('status', 'libre')->count();
        $panneauxOccupes     = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $panneauxMaintenance = Panel::where('status', 'maintenance')->count();

        $reservationsEnAttente  = Reservation::where('status', 'en_attente')->count();
        $reservationsConfirmees = Reservation::where('status', 'confirme')->count();

        $campagnesActives   = Campaign::where('status', 'actif')->count();
        $campagnesTerminees = Campaign::where('status', 'termine')->count();

        $totalClients = Client::count();

        $maintenancesUrgentes = Maintenance::where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')->count();

        $alertesNonLues = Alert::where('is_read', false)->count();

        $dernieresReservations = Reservation::with('client', 'panels')
            ->where('status', 'en_attente')
            ->latest()->take(5)->get();

        $dernieresMaintenances = Maintenance::with('panel')
            ->where('statut', '!=', 'resolu')
            ->orderByRaw("FIELD(priorite, 'urgente', 'haute', 'normale', 'faible')")
            ->take(5)->get();

        $campagnesRecentes = Campaign::with('client')
            ->where('status', 'actif')
            ->latest()->take(5)->get();

        $dernieresAlertes = Alert::where('is_read', false)
            ->latest()->take(5)->get();

        $tauxOccupation = $totalPanneaux > 0
            ? round(($panneauxOccupes / $totalPanneaux) * 100, 1) : 0;

        $tauxParCommune = [
            ['nom' => 'Plateau',  'taux' => 94],
            ['nom' => 'Cocody',   'taux' => 81],
            ['nom' => 'Marcory',  'taux' => 67],
            ['nom' => 'Yopougon','taux' => 52],
            ['nom' => 'Adjamé',  'taux' => 38],
        ];

        return view('dashboard', compact(
            'totalPanneaux', 'panneauxLibres', 'panneauxOccupes',
            'panneauxMaintenance', 'reservationsEnAttente',
            'reservationsConfirmees', 'campagnesActives',
            'campagnesTerminees', 'totalClients',
            'maintenancesUrgentes', 'alertesNonLues',
            'dernieresReservations', 'dernieresMaintenances',
            'campagnesRecentes', 'dernieresAlertes',
            'tauxOccupation', 'tauxParCommune'
        ));
    }
}

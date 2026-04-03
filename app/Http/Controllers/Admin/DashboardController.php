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

        $tauxParCommune = \App\Models\Commune::withCount([
            'panels',
            'panels as panels_occupes_count' => fn($q) =>
                $q->whereIn('status', ['occupe', 'option', 'confirme']),
        ])
        ->having('panels_count', '>', 0)
        ->orderByDesc('panels_occupes_count')
        ->take(6)
        ->get()
        ->map(fn($c) => [
            'nom'  => $c->name,
            'taux' => $c->panels_count > 0
                ? round(($c->panels_occupes_count / $c->panels_count) * 100)
                : 0,
        ])
        ->toArray();

        // CA mensuel réel = somme des tarifs des panneaux occupés
        $caMensuel = \App\Models\Panel::whereIn('status', ['occupe', 'option', 'confirme'])
            ->sum('monthly_rate');

        // CA mois précédent pour la variation
        $caMoisPrecedent = \App\Models\CampaignPanel::with('panel')
            ->where('type', 'interne')
            ->whereHas('campaign', fn($q) =>
                $q->where('start_date', '<=', now()->subMonth()->endOfMonth())
                  ->where('end_date', '>=', now()->subMonth()->startOfMonth())
                  ->whereNotIn('status', ['annule'])
            )
            ->get()
            ->sum(fn($cp) => (float)($cp->panel?->monthly_rate ?? 0));

        $variationCA = $caMoisPrecedent > 0
            ? round((($caMensuel - $caMoisPrecedent) / $caMoisPrecedent) * 100, 1)
            : null;

        return view('dashboard', compact(
            'totalPanneaux', 'panneauxLibres', 'panneauxOccupes',
            'panneauxMaintenance', 'reservationsEnAttente',
            'reservationsConfirmees', 'campagnesActives',
            'campagnesTerminees', 'totalClients',
            'maintenancesUrgentes', 'alertesNonLues',
            'dernieresReservations', 'dernieresMaintenances',
            'campagnesRecentes', 'dernieresAlertes',
            'tauxOccupation', 'tauxParCommune',
            'caMensuel', 'variationCA'
        ));
    }
}

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
        // RBAC : un commercial voit son portefeuille (réservations/campagnes
        // qui lui sont assignées). Admin/MP voient tout.
        $isCommercial = auth()->user()?->role?->value === 'commercial';
        $uid          = auth()->id();

        $totalPanneaux       = Panel::count();
        $panneauxLibres      = Panel::where('status', 'libre')->count();
        $panneauxOccupes     = Panel::whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $panneauxMaintenance = Panel::where('status', 'maintenance')->count();

        $reservationsEnAttente  = Reservation::where('status', 'en_attente')
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
            ->count();
        $reservationsConfirmees = Reservation::where('status', 'confirme')
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
            ->count();

        // Campagnes du commercial — 4 cas couverts :
        //  1) campaign.commercial_user_id == uid (assignation directe par
        //     admin/MP — cas le plus fréquent en pratique)
        //  2) Via résa source : reservation.commercial_user_id == uid
        //  3) Résa sans commercial : reservation.user_id == uid (créateur)
        //  4) Campagne manuelle sans résa : campaign.user_id == uid
        $scopeCampaignCommercial = function ($q) use ($uid) {
            $q->where(function ($qq) use ($uid) {
                $qq->where('commercial_user_id', $uid)
                   ->orWhereHas('reservation', fn($r) =>
                        $r->where('commercial_user_id', $uid)
                          ->orWhere(function ($rr) use ($uid) {
                              $rr->whereNull('commercial_user_id')
                                 ->where('user_id', $uid);
                          })
                   )
                   ->orWhere(function ($qqq) use ($uid) {
                       $qqq->whereDoesntHave('reservation')->where('user_id', $uid);
                   });
            });
        };

        $campagnesActives   = Campaign::where('status', 'actif')
            ->when($isCommercial, $scopeCampaignCommercial)
            ->count();
        $campagnesTerminees = Campaign::where('status', 'termine')
            ->when($isCommercial, $scopeCampaignCommercial)
            ->count();

        // RBAC commercial : clients créés OU rattachés à une de ses campagnes
        // (assignation directe par admin/MP, via résa, ou créateur fallback)
        $totalClients = Client::when($isCommercial, function ($q) use ($uid) {
                $q->where(function ($qq) use ($uid) {
                    $qq->where('user_id', $uid)
                       ->orWhereHas('campaigns', function ($c) use ($uid) {
                           $c->where(function ($cc) use ($uid) {
                               $cc->where('commercial_user_id', $uid)
                                  ->orWhereHas('reservation', fn($r) =>
                                       $r->where('commercial_user_id', $uid)
                                         ->orWhere(function ($rr) use ($uid) {
                                             $rr->whereNull('commercial_user_id')
                                                ->where('user_id', $uid);
                                         })
                                  )
                                  ->orWhere(function ($qqq) use ($uid) {
                                      $qqq->whereDoesntHave('reservation')
                                          ->where('user_id', $uid);
                                  });
                           });
                       });
                });
            })
            ->count();

        $maintenancesUrgentes = Maintenance::where('priorite', 'urgente')
            ->where('statut', '!=', 'resolu')->count();

        $alertesNonLues = Alert::where('is_read', false)
            ->when($isCommercial, fn($q) => $q->where('user_id', $uid))
            ->count();

        $dernieresReservations = Reservation::with('client', 'panels')
            ->where('status', 'en_attente')
            ->when($isCommercial, fn($q) => $q->forCommercialUser($uid))
            ->latest()->take(5)->get();

        $dernieresMaintenances = Maintenance::with('panel')
            ->where('statut', '!=', 'resolu')
            ->orderByRaw("FIELD(priorite, 'urgente', 'haute', 'normale', 'faible')")
            ->take(5)->get();

        $campagnesRecentes = Campaign::with('client')
            ->where('status', 'actif')
            ->when($isCommercial, $scopeCampaignCommercial)
            ->latest()->take(5)->get();

        // ⚠ Scope commercial : avant, un commercial voyait les 5 dernières
        // alertes GLOBALES de l'entreprise (résa/campagne d'autres commerciaux,
        // problèmes terrain, etc.) — incohérent avec le KPI $alertesNonLues
        // déjà scopé. Maintenant aligné.
        $dernieresAlertes = Alert::where('is_read', false)
            ->when($isCommercial, fn($q) => $q->where('user_id', $uid))
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
            'id'   => $c->id,
            'nom'  => $c->name,
            'taux' => $c->panels_count > 0
                ? round(($c->panels_occupes_count / $c->panels_count) * 100)
                : 0,
        ])
        ->toArray();

        // ── CA Mensuel ─────────────────────────────────────────────
        // Pour ADMIN/MP : CA global = somme des tarifs des panneaux occupés
        // Pour COMMERCIAL : CA personnel = somme des tarifs des panneaux
        // dans ses campagnes actives (commercial assigné OU créateur).
        if ($isCommercial) {
            $caMensuel = \App\Models\CampaignPanel::with('panel')
                ->where('type', 'interne')
                ->whereHas('campaign', function ($q) use ($scopeCampaignCommercial) {
                    $q->where('status', 'actif');
                    $scopeCampaignCommercial($q);
                })
                ->get()
                ->sum(fn($cp) => (float) ($cp->panel?->monthly_rate ?? 0));

            // CA mois précédent commercial (même périmètre, période m-1)
            $caMoisPrecedent = \App\Models\CampaignPanel::with('panel')
                ->where('type', 'interne')
                ->whereHas('campaign', function ($q) use ($scopeCampaignCommercial) {
                    $q->where('start_date', '<=', now()->subMonth()->endOfMonth())
                      ->where('end_date',   '>=', now()->subMonth()->startOfMonth())
                      ->whereNotIn('status', ['annule']);
                    $scopeCampaignCommercial($q);
                })
                ->get()
                ->sum(fn($cp) => (float) ($cp->panel?->monthly_rate ?? 0));
        } else {
            $caMensuel = \App\Models\Panel::whereIn('status', ['occupe', 'option', 'confirme'])
                ->sum('monthly_rate');

            $caMoisPrecedent = \App\Models\CampaignPanel::with('panel')
                ->where('type', 'interne')
                ->whereHas('campaign', fn($q) =>
                    $q->where('start_date', '<=', now()->subMonth()->endOfMonth())
                      ->where('end_date',   '>=', now()->subMonth()->startOfMonth())
                      ->whereNotIn('status', ['annule'])
                )
                ->get()
                ->sum(fn($cp) => (float) ($cp->panel?->monthly_rate ?? 0));
        }

        $variationCA = $caMoisPrecedent > 0
            ? round((($caMensuel - $caMoisPrecedent) / $caMoisPrecedent) * 100, 1)
            : null;

        // Label de la carte CA — adapté au rôle pour clarté
        $caLabel = $isCommercial ? 'Mon CA Mensuel (FCFA)' : 'CA Mensuel (FCFA)';

        return view('dashboard', compact(
            'totalPanneaux', 'panneauxLibres', 'panneauxOccupes',
            'panneauxMaintenance', 'reservationsEnAttente',
            'reservationsConfirmees', 'campagnesActives',
            'campagnesTerminees', 'totalClients',
            'maintenancesUrgentes', 'alertesNonLues',
            'dernieresReservations', 'dernieresMaintenances',
            'campagnesRecentes', 'dernieresAlertes',
            'tauxOccupation', 'tauxParCommune',
            'caMensuel', 'variationCA', 'caLabel', 'isCommercial'
        ));
    }
}

<?php
// app/Http/Controllers/Admin/RapportController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Reservation;
use App\Models\ReservationPanel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    // INDEX — Vue principale rapports (5 onglets)
    // Toutes les données sont calculées ici en 1 passage
    // ══════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        // ── Période d'analyse (filtre global)
        $annee  = (int)($request->get('annee',  now()->year));
        $moisDu = (int)($request->get('mois_du', 1));
        $moisAu = (int)($request->get('mois_au', now()->month));

        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo   = Carbon::create($annee, $moisAu, 1)->endOfMonth();

        // ── Données de référence
        $totalPanneaux = Panel::whereNull('deleted_at')->count();
        $totalClients  = Client::count();
        $totalCampagnes = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])
            ->orWhereBetween('end_date', [$dateFrom, $dateTo])
            ->count();

        // ══════════════════════════════════════════════
        // ONGLET 1 — TAUX D'OCCUPATION
        // ══════════════════════════════════════════════

        // Taux global sur la période
        $occupation = $this->calcOccupation($dateFrom, $dateTo, $totalPanneaux);

        // Occupation par commune
        $occParCommune = $this->occParCommune($dateFrom, $dateTo);

        // Évolution mensuelle (12 derniers mois)
        $evolMensuelle = $this->evolMensuelle();

        // ══════════════════════════════════════════════
        // ONGLET 2 — PÉRIODES DE CAMPAGNES
        // ══════════════════════════════════════════════

        // Répartition des durées
        $repartitionDurees = $this->repartitionDurees($dateFrom, $dateTo);

        // Tableau mensuel (campagnes + panneaux + CA par mois)
        $tableauMensuel = $this->tableauMensuel($annee);

        // ══════════════════════════════════════════════
        // ONGLET 3 — CA & REVENUS
        // ══════════════════════════════════════════════

        $caTotal     = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->sum('total_amount');
        $caTicketMoy = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->avg('total_amount') ?? 0;

        // Top clients par CA
        $topClients = $this->topClients($dateFrom, $dateTo, 10);

        // CA par mois pour graphique
        $caMensuel = $this->caMensuel($annee);

        // ══════════════════════════════════════════════
        // ONGLET 4 — ZONES / COMMUNES
        // ══════════════════════════════════════════════

        $statsCommunes = $this->statsCommunes();

        // ══════════════════════════════════════════════
        // ONGLET 5 — CLIENTS
        // ══════════════════════════════════════════════

        $statsClients = $this->statsClients($dateFrom, $dateTo);

        // ── Panneaux à décaper (fin de campagne < 30 jours)
        $aDecaper = $this->panneauxADecaper();

        // ── Pour le filtre années
        $anneesDisponibles = Campaign::selectRaw('YEAR(start_date) as annee')
            ->groupBy('annee')
            ->orderByDesc('annee')
            ->pluck('annee');

        if ($anneesDisponibles->isEmpty()) {
            $anneesDisponibles = collect([now()->year]);
        }

        return view('admin.rapports.index', compact(
            // Référence
            'totalPanneaux', 'totalClients', 'totalCampagnes',
            'dateFrom', 'dateTo', 'annee', 'moisDu', 'moisAu',
            'anneesDisponibles',
            // Occupation
            'occupation', 'occParCommune', 'evolMensuelle',
            // Périodes
            'repartitionDurees', 'tableauMensuel',
            // CA
            'caTotal', 'caTicketMoy', 'topClients', 'caMensuel',
            // Zones
            'statsCommunes',
            // Clients
            'statsClients',
            // Décaper
            'aDecaper'
        ));
    }

    // ══════════════════════════════════════════════════════════════
    // AJAX — données pour un onglet précis (rechargement partiel)
    // ══════════════════════════════════════════════════════════════

    public function ajax(Request $request)
    {
        $annee  = (int)$request->get('annee', now()->year);
        $moisDu = (int)$request->get('mois_du', 1);
        $moisAu = (int)$request->get('mois_au', now()->month);
        $onglet = $request->get('onglet', 'occupation');

        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo   = Carbon::create($annee, $moisAu, 1)->endOfMonth();
        $totalPanneaux = Panel::whereNull('deleted_at')->count();

        $data = match($onglet) {
            'occupation' => [
                'occupation'    => $this->calcOccupation($dateFrom, $dateTo, $totalPanneaux),
                'occParCommune' => $this->occParCommune($dateFrom, $dateTo),
                'evolMensuelle' => $this->evolMensuelle(),
            ],
            'periodes' => [
                'repartitionDurees' => $this->repartitionDurees($dateFrom, $dateTo),
                'tableauMensuel'    => $this->tableauMensuel($annee),
            ],
            'ca' => [
                'caTotal'     => Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->sum('total_amount'),
                'caTicketMoy' => Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->avg('total_amount') ?? 0,
                'topClients'  => $this->topClients($dateFrom, $dateTo, 10),
                'caMensuel'   => $this->caMensuel($annee),
            ],
            'zones'   => ['statsCommunes' => $this->statsCommunes()],
            'clients' => ['statsClients'  => $this->statsClients($dateFrom, $dateTo)],
            default   => [],
        };

        return response()->json($data);
    }

    // ══════════════════════════════════════════════════════════════
    // MÉTHODES PRIVÉES — calculs BDD
    // ══════════════════════════════════════════════════════════════

    /**
     * Taux d'occupation global sur une période.
     * Logique : panneau occupé = au moins 1 réservation confirmée active sur la période.
     */
    private function calcOccupation(Carbon $from, Carbon $to, int $total): array
    {
        if ($total === 0) {
            return ['taux' => 0, 'occupes' => 0, 'libres' => 0, 'maintenance' => 0, 'total' => 0];
        }

        $occupesIds = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->whereIn('reservations.status', ['confirme', 'en_attente'])
            ->where('reservations.start_date', '<', $to)
            ->where('reservations.end_date',   '>', $from)
            ->whereNull('reservations.deleted_at')
            ->distinct()
            ->pluck('reservation_panels.panel_id');

        $maintenance = Panel::where('status', 'maintenance')->whereNull('deleted_at')->count();
        $occupes     = $occupesIds->count();
        $libres      = max(0, $total - $occupes - $maintenance);
        $taux        = round(($occupes / $total) * 100, 1);

        return compact('taux', 'occupes', 'libres', 'maintenance', 'total');
    }

    /**
     * Occupation par commune — tableau trié par taux desc
     */
    private function occParCommune(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        // Compter les panneaux par commune
        $panneauxParCommune = Panel::whereNull('deleted_at')
            ->select('commune_id', DB::raw('COUNT(*) as total'))
            ->groupBy('commune_id')
            ->pluck('total', 'commune_id');

        // Panneaux occupés sur la période par commune
        $occupesParCommune = DB::table('reservation_panels')
            ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
            ->join('panels', 'panels.id', '=', 'reservation_panels.panel_id')
            ->whereIn('reservations.status', ['confirme', 'en_attente'])
            ->where('reservations.start_date', '<', $to)
            ->where('reservations.end_date',   '>', $from)
            ->whereNull('reservations.deleted_at')
            ->whereNull('panels.deleted_at')
            ->select('panels.commune_id', DB::raw('COUNT(DISTINCT panels.id) as occupes'))
            ->groupBy('panels.commune_id')
            ->pluck('occupes', 'commune_id');

        return Commune::orderBy('name')
            ->get(['id', 'name'])
            ->map(function ($commune) use ($panneauxParCommune, $occupesParCommune) {
                $total   = $panneauxParCommune[$commune->id] ?? 0;
                $occupes = $occupesParCommune[$commune->id]  ?? 0;
                $taux    = $total > 0 ? round(($occupes / $total) * 100, 1) : 0;
                return [
                    'commune' => $commune->name,
                    'total'   => $total,
                    'occupes' => $occupes,
                    'libres'  => max(0, $total - $occupes),
                    'taux'    => $taux,
                    'color'   => $taux >= 80 ? '#ef4444' : ($taux >= 60 ? '#f97316' : ($taux >= 40 ? '#e8a020' : '#22c55e')),
                ];
            })
            ->filter(fn($c) => $c['total'] > 0)
            ->sortByDesc('taux')
            ->values();
    }

    /**
     * Évolution mensuelle du taux d'occupation (12 derniers mois)
     */
    private function evolMensuelle(): \Illuminate\Support\Collection
    {
        $totalPanneaux = Panel::whereNull('deleted_at')->count();
        $mois = collect();

        for ($i = 11; $i >= 0; $i--) {
            $debut = now()->subMonths($i)->startOfMonth();
            $fin   = now()->subMonths($i)->endOfMonth();

            $occupes = DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->whereIn('reservations.status', ['confirme', 'en_attente'])
                ->where('reservations.start_date', '<', $fin)
                ->where('reservations.end_date',   '>', $debut)
                ->whereNull('reservations.deleted_at')
                ->distinct()
                ->count('reservation_panels.panel_id');

            $taux = $totalPanneaux > 0 ? round(($occupes / $totalPanneaux) * 100, 1) : 0;

            $mois->push([
                'label'   => $debut->locale('fr')->isoFormat('MMM YY'),
                'taux'    => $taux,
                'occupes' => $occupes,
            ]);
        }

        return $mois;
    }

    /**
     * Répartition des durées de campagnes
     */
    private function repartitionDurees(Carbon $from, Carbon $to): array
    {
        $campaigns = Campaign::whereBetween('start_date', [$from, $to])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->selectRaw('DATEDIFF(end_date, start_date) as duree_jours')
            ->get();

        $total = $campaigns->count();
        if ($total === 0) return [];

        $buckets = [
            '1 mois (≤30j)'   => $campaigns->where('duree_jours', '<=', 30)->count(),
            '2–3 mois'        => $campaigns->whereBetween('duree_jours', [31, 90])->count(),
            '4–6 mois'        => $campaigns->whereBetween('duree_jours', [91, 180])->count(),
            '6–12 mois'       => $campaigns->whereBetween('duree_jours', [181, 365])->count(),
            '> 12 mois'       => $campaigns->where('duree_jours', '>', 365)->count(),
        ];

        return collect($buckets)->map(fn($count, $label) => [
            'label'   => $label,
            'count'   => $count,
            'pct'     => $total > 0 ? round(($count / $total) * 100) : 0,
        ])->values()->toArray();
    }

    /**
     * Tableau mensuel de l'année (nb campagnes, panneaux mobilisés, CA, taux)
     */
    private function tableauMensuel(int $annee): \Illuminate\Support\Collection
    {
        $totalPanneaux = Panel::whereNull('deleted_at')->count();
        $mois = collect();

        $labelsMois = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

        for ($m = 1; $m <= 12; $m++) {
            $debut = Carbon::create($annee, $m, 1)->startOfMonth();
            $fin   = Carbon::create($annee, $m, 1)->endOfMonth();

            $nbCampagnes = Campaign::where(fn($q) =>
                    $q->whereBetween('start_date', [$debut, $fin])
                      ->orWhereBetween('end_date',   [$debut, $fin])
                      ->orWhere(fn($q2) => $q2->where('start_date', '<', $debut)->where('end_date', '>', $fin))
                )->count();

            $panneauxMobilises = DB::table('reservation_panels')
                ->join('reservations', 'reservations.id', '=', 'reservation_panels.reservation_id')
                ->whereIn('reservations.status', ['confirme'])
                ->where('reservations.start_date', '<', $fin)
                ->where('reservations.end_date',   '>', $debut)
                ->distinct()
                ->count('reservation_panels.panel_id');

            $ca = Campaign::where(fn($q) =>
                    $q->whereBetween('start_date', [$debut, $fin])
                      ->orWhereBetween('end_date', [$debut, $fin])
                )->sum('total_amount');

            $taux = $totalPanneaux > 0 ? round(($panneauxMobilises / $totalPanneaux) * 100, 1) : 0;

            $mois->push([
                'mois'               => $labelsMois[$m - 1] . ' ' . $annee,
                'nb_campagnes'       => $nbCampagnes,
                'panneaux_mobilises' => $panneauxMobilises,
                'ca'                 => (float)$ca,
                'taux'               => $taux,
            ]);
        }

        return $mois;
    }

    /**
     * Top clients par CA sur la période
     */
    private function topClients(Carbon $from, Carbon $to, int $limit = 10): \Illuminate\Support\Collection
    {
        return DB::table('campaigns')
            ->join('clients', 'clients.id', '=', 'campaigns.client_id')
            ->where(fn($q) =>
                $q->whereBetween('campaigns.start_date', [$from, $to])
                  ->orWhereBetween('campaigns.end_date',   [$from, $to])
            )
            ->whereNull('campaigns.deleted_at')
            ->select(
                'clients.id',
                'clients.name',
                DB::raw('SUM(campaigns.total_amount) as ca_total'),
                DB::raw('COUNT(campaigns.id) as nb_campagnes'),
                DB::raw('SUM(campaigns.total_panels) as total_panneaux')
            )
            ->groupBy('clients.id', 'clients.name')
            ->orderByDesc('ca_total')
            ->limit($limit)
            ->get();
    }

    /**
     * CA mensuel pour l'année (graphique barres)
     */
    private function caMensuel(int $annee): \Illuminate\Support\Collection
    {
        $rawData = DB::table('campaigns')
            ->selectRaw('MONTH(start_date) as mois, SUM(total_amount) as ca')
            ->whereYear('start_date', $annee)
            ->whereNull('deleted_at')
            ->groupBy('mois')
            ->pluck('ca', 'mois');

        $labels = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];

        return collect(range(1, 12))->map(fn($m) => [
            'label' => $labels[$m - 1],
            'ca'    => (float)($rawData[$m] ?? 0),
        ]);
    }

    /**
     * Statistiques par commune (inventaire + CA + taux)
     */
  private function statsCommunes(): \Illuminate\Support\Collection
{
    $panneaux = Panel::whereNull('deleted_at')
        ->select(
            'commune_id',
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN status = "libre" THEN 1 ELSE 0 END) as libres'),
            DB::raw('SUM(CASE WHEN status IN ("confirme","option") THEN 1 ELSE 0 END) as occupes'),
            DB::raw('SUM(CASE WHEN status = "maintenance" THEN 1 ELSE 0 END) as maintenance'),
            DB::raw('AVG(monthly_rate) as tarif_moyen')
        )
        ->groupBy('commune_id')
        ->get()
        ->keyBy('commune_id');

    $caParCommune = collect();
    try {
        $campaigns = Campaign::with('panels:id,commune_id,monthly_rate')
            ->whereNull('deleted_at')
            ->whereYear('start_date', now()->year)
            ->whereNotNull('total_panels')
            ->where('total_panels', '>', 0)
            ->get(['id', 'total_amount', 'total_panels']);

        foreach ($campaigns as $campaign) {
            $partParPanneau = $campaign->total_panels > 0
                ? $campaign->total_amount / $campaign->total_panels
                : 0;

            foreach ($campaign->panels as $panel) {
                $communeId = $panel->commune_id;
                $caParCommune[$communeId] = ($caParCommune[$communeId] ?? 0) + $partParPanneau;
            }
        }
    } catch (\Exception $e) {
        \Log::warning('statsCommunes: CA non calculé', ['error' => $e->getMessage()]);
    }

    return Commune::orderBy('name')
        ->get(['id', 'name'])
        ->map(function ($commune) use ($panneaux, $caParCommune) {
            $p = $panneaux[$commune->id] ?? null;
            return [
                'commune'     => $commune->name,
                'total'       => $p?->total       ?? 0,
                'libres'      => $p?->libres       ?? 0,
                'occupes'     => $p?->occupes      ?? 0,
                'maintenance' => $p?->maintenance  ?? 0,
                'tarif_moyen' => round($p?->tarif_moyen ?? 0),
                'ca_annee'    => round($caParCommune[$commune->id] ?? 0),
                'taux'        => ($p && $p->total > 0)
                    ? round(($p->occupes / $p->total) * 100, 1)
                    : 0,
            ];
        })
        ->filter(fn($c) => $c['total'] > 0)
        ->values();
}

    /**
     * Statistiques détaillées par client
     */
    private function statsClients(Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return DB::table('clients')
            ->leftJoin('campaigns', fn($j) =>
                $j->on('campaigns.client_id', '=', 'clients.id')
                  ->whereNull('campaigns.deleted_at')
            )
            ->select(
                'clients.id',
                'clients.name',
                'clients.ncc',
                DB::raw('COUNT(DISTINCT campaigns.id) as total_campagnes'),
                DB::raw('SUM(CASE WHEN campaigns.status IN ("actif","pose") THEN 1 ELSE 0 END) as campagnes_actives'),
                DB::raw('SUM(campaigns.total_amount) as ca_total'),
                DB::raw('SUM(campaigns.total_panels) as total_panneaux'),
                DB::raw('MAX(campaigns.created_at) as derniere_campagne')
            )
            ->groupBy('clients.id', 'clients.name', 'clients.ncc')
            ->orderByDesc('ca_total')
            ->get()
            ->map(fn($c) => (array)$c);
    }

    /**
     * Panneaux à décaper dans les 30 prochains jours
     */
    private function panneauxADecaper(): \Illuminate\Support\Collection
    {
        return DB::table('reservations')
            ->join('reservation_panels', 'reservation_panels.reservation_id', '=', 'reservations.id')
            ->join('panels', 'panels.id', '=', 'reservation_panels.panel_id')
            ->join('clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
            ->where('reservations.status', 'confirme')
            ->where('reservations.end_date', '>=', now()->startOfDay())
            ->where('reservations.end_date', '<=', now()->addDays(30)->endOfDay())
            ->whereNull('reservations.deleted_at')
            ->select(
                'panels.reference',
                'panels.name as panel_name',
                'communes.name as commune',
                'clients.name as client_name',
                'reservations.end_date',
                DB::raw('DATEDIFF(reservations.end_date, NOW()) as jours_restants')
            )
            ->orderBy('reservations.end_date')
            ->get();
    }
}

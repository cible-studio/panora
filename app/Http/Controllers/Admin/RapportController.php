<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CampaignPanel;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    public function index(Request $request)
    {
        $annee = (int) ($request->annee ?? date('Y'));
        $moisDu = (int) ($request->mois_du ?? 1);
        $moisAu = (int) ($request->mois_au ?? 12);

        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo = Carbon::create($annee, $moisAu, 1)->endOfMonth();

        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        // ── Stats globales ──────────────────────────────────────
        // Périmètre du parc : intemporel (le parc lui-même ne change pas
        // selon la période sélectionnée). En revanche occupation, CA,
        // clients actifs sont scopés sur [dateFrom, dateTo] pour que le
        // filtre Période ait un vrai effet (Lot 8.1).
        $totalPanneaux  = Panel::count();
        $totalCampagnes = Campaign::where('start_date', '<=', $dateTo)
                                  ->where('end_date',   '>=', $dateFrom)
                                  ->count();

        // Clients actifs sur la période = clients ayant au moins 1
        // campagne qui chevauche la période.
        $totalClients = Client::whereHas('campaigns', fn($q) =>
            $q->where('start_date', '<=', $dateTo)
              ->where('end_date',   '>=', $dateFrom)
        )->count();

        // ── Occupation globale SUR LA PÉRIODE ───────────────────
        // Un panneau est "occupé sur la période" s'il appartient à au
        // moins 1 campagne actif/pose qui chevauche [dateFrom, dateTo].
        $occupes = Panel::whereHas('campaigns', fn($q) =>
            $q->whereIn('status', ['actif', 'planifie', 'termine'])
              ->where('start_date', '<=', $dateTo)
              ->where('end_date',   '>=', $dateFrom)
        )->count();

        $maintenance = Panel::where('status', 'maintenance')->count();
        $libres      = max(0, $totalPanneaux - $occupes - $maintenance);
        $taux        = $totalPanneaux > 0 ? round(($occupes / $totalPanneaux) * 100) : 0;

        $occupation = [
            'taux'        => $taux,
            'occupes'     => $occupes,
            'libres'      => $libres,
            'maintenance' => $maintenance,
            'total'       => $totalPanneaux,
        ];

        // ── CA total période ────────────────────────────────────
        // Inclut toute campagne qui chevauche la période (pas seulement
        // celles qui démarrent dans la période). Cohérent avec occupes.
        $caTotal = Campaign::where('start_date', '<=', $dateTo)
            ->where('end_date',   '>=', $dateFrom)
            ->sum('total_amount');

        $caTicketMoy = $totalCampagnes > 0 ? round($caTotal / $totalCampagnes) : 0;

        // ── Occupation par commune SUR LA PÉRIODE ───────────────
        $communes = Commune::withCount(['panels as total_panels'])->get();
        $occParCommune = $communes->filter(fn($c) => $c->total_panels > 0)
            ->map(function ($commune) use ($dateFrom, $dateTo) {
                $total = Panel::where('commune_id', $commune->id)->count();
                $occ = Panel::where('commune_id', $commune->id)
                    ->whereHas('campaigns', fn($q) =>
                        $q->whereIn('status', ['actif', 'planifie', 'termine'])
                          ->where('start_date', '<=', $dateTo)
                          ->where('end_date',   '>=', $dateFrom)
                    )->count();
                $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
                $color = $taux >= 75 ? '#ef4444' : ($taux >= 50 ? '#f97316' : ($taux >= 25 ? '#e8a020' : '#22c55e'));
                return ['id' => $commune->id, 'commune' => $commune->name, 'total' => $total, 'occupes' => $occ, 'taux' => $taux, 'color' => $color];
            })->sortByDesc('taux')->values();

        // ── Évolution mensuelle (12 derniers mois) ──────────────
        $evolMensuelle = collect();
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $total = Panel::count();
            $occ = Campaign::where('status', 'actif')
                ->where('start_date', '<=', $end)
                ->where('end_date', '>=', $start)
                ->withCount('panels')->get()->sum('panels_count');
            $taux = $total > 0 ? min(round(($occ / $total) * 100), 100) : 0;
            $evolMensuelle->push(['label' => $date->format('M'), 'taux' => $taux, 'mois' => $date->month, 'annee' => $date->year]);
        }

        // ── CA mensuel ──────────────────────────────────────────
        $caMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $ca = Campaign::whereYear('start_date', $annee)->whereMonth('start_date', $m)->sum('total_amount');
            $caMensuel->push(['label' => Carbon::create($annee, $m, 1)->format('M'), 'ca' => (float) $ca]);
        }

        // ── Tableau mensuel ─────────────────────────────────────
        $tableauMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($annee, $m, 1)->startOfMonth();
            $end = Carbon::create($annee, $m, 1)->endOfMonth();
            $camps = Campaign::where('start_date', '<=', $end)->where('end_date', '>=', $start)->get();
            $ca = Campaign::whereYear('start_date', $annee)->whereMonth('start_date', $m)->sum('total_amount');
            $panneaux = $camps->sum(fn($c) => $c->panels()->count());
            $taux = $totalPanneaux > 0 ? min(round(($panneaux / $totalPanneaux) * 100), 100) : 0;
            $tableauMensuel->push([
                'mois' => Carbon::create($annee, $m, 1)->format('F Y'),
                'nb_campagnes' => $camps->count(),
                'panneaux_mobilises' => $panneaux,
                'ca' => (float) $ca,
                'taux' => $taux,
            ]);
        }

        // ── Top clients SUR LA PÉRIODE ─────────────────────────
        $topClients = Client::with(['campaigns' => fn($q) =>
                $q->where('start_date', '<=', $dateTo)
                  ->where('end_date',   '>=', $dateFrom)
            ])
            ->get()
            ->map(function ($client) {
                $camps = $client->campaigns; // déjà filtrées par eager load
                return (object) [
                    'id' => $client->id,
                    'name' => $client->name,
                    'nb_campagnes' => $camps->count(),
                    'ca_total' => $camps->sum('total_amount'),
                    'total_panneaux' => $camps->sum(fn($c) => $c->panels()->count()),
                ];
            })
            ->filter(fn($c) => $c->nb_campagnes > 0)
            ->sortByDesc('ca_total')
            ->take(10)
            ->values();

        // ── Stats communes (occupation ET CA scopés période) ────
        $statsCommunes = Commune::withCount('panels')->get()->map(function ($commune) use ($dateFrom, $dateTo) {
            $total = Panel::where('commune_id', $commune->id)->count();
            $occ = Panel::where('commune_id', $commune->id)
                ->whereHas('campaigns', fn($q) =>
                    $q->whereIn('status', ['actif', 'planifie', 'termine'])
                      ->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                )->count();
            $maint  = Panel::where('commune_id', $commune->id)->where('status', 'maintenance')->count();
            $libres = max(0, $total - $occ - $maint);
            $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
            $tarifMoyen = Panel::where('commune_id', $commune->id)->avg('monthly_rate') ?? 0;
            $caAnnee = Campaign::where('start_date', '<=', $dateTo)
                ->where('end_date',   '>=', $dateFrom)
                ->whereHas('panels', fn($q) => $q->where('commune_id', $commune->id))
                ->sum('total_amount');
            return [
                'id' => $commune->id, // utilisé pour le drilldown AJAX
                'commune' => $commune->name,
                'total' => $total,
                'occupes' => $occ,
                'libres' => $libres,
                'maintenance' => $maint,
                'taux' => $taux,
                'tarif_moyen' => round($tarifMoyen),
                'ca_annee' => (float) $caAnnee,
            ];
        })->filter(fn($r) => $r['total'] > 0)->sortByDesc('taux')->values();

        // ── Stats clients ───────────────────────────────────────
        $statsClients = Client::with('campaigns')->get()->map(function ($client) {
            $campagnesActives = $client->campaigns->where('status', 'actif')->count();
            $derniere = $client->campaigns->sortByDesc('created_at')->first()?->created_at;
            return [
                'id' => $client->id,
                'name' => $client->name,
                'ncc' => $client->ncc,
                'total_campagnes' => $client->campaigns->count(),
                'campagnes_actives' => $campagnesActives,
                'ca_total' => $client->campaigns->sum('total_amount'),
                'total_panneaux' => $client->campaigns->sum(fn($c) => $c->panels()->count()),
                'derniere_campagne' => $derniere,
            ];
        })->sortByDesc('ca_total')->values();

        // ── Répartition durées ──────────────────────────────────
        $camps = Campaign::whereBetween('start_date', [$dateFrom, $dateTo])->get();
        $durees = ['< 1 mois' => 0, '1-3 mois' => 0, '3-6 mois' => 0, '> 6 mois' => 0];
        foreach ($camps as $c) {
            $j = $c->start_date->diffInDays($c->end_date);
            if ($j < 30)
                $durees['< 1 mois']++;
            elseif ($j < 90)
                $durees['1-3 mois']++;
            elseif ($j < 180)
                $durees['3-6 mois']++;
            else
                $durees['> 6 mois']++;
        }
        $total = array_sum($durees);
        $repartitionDurees = collect($durees)->map(fn($count, $label) => [
            'label' => $label,
            'count' => $count,
            'pct' => $total > 0 ? round(($count / $total) * 100) : 0,
        ])->values();

        // ── Panneaux à décaper (30j) ────────────────────────────
        $aDecaper = collect(DB::select("
            SELECT p.reference, c2.name as commune, cl.name as client_name, cp.end_date,
                   DATEDIFF(cp.end_date, NOW()) as jours_restants
            FROM campaigns cp
            JOIN clients cl ON cl.id = cp.client_id
            LEFT JOIN campaign_panels cpan ON cpan.campaign_id = cp.id
            LEFT JOIN panels p ON p.id = cpan.panel_id
            LEFT JOIN communes c2 ON c2.id = p.commune_id
            WHERE cp.status = 'actif'
              AND cp.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)
            ORDER BY cp.end_date ASC
        "));

        return view('admin.rapports.index', compact(
            'annee',
            'moisDu',
            'moisAu',
            'dateFrom',
            'dateTo',
            'anneesDisponibles',
            'totalPanneaux',
            'totalClients',
            'totalCampagnes',
            'occupation',
            'caTotal',
            'caTicketMoy',
            'occParCommune',
            'evolMensuelle',
            'caMensuel',
            'tableauMensuel',
            'topClients',
            'statsCommunes',
            'statsClients',
            'repartitionDurees',
            'aDecaper'
        ));
    }

    public function ajax(Request $request)
    {
        return response()->json(['status' => 'ok']);
    }

    /**
     * Rapport taxes communales (Lot 7) — auto-calculé depuis les
     * campagnes effectivement actives sur l'année. Affiche :
     *   - matrice mensuelle (mois × commune)
     *   - synthèse trimestrielle par commune (Q1/Q2/Q3/Q4)
     *   - synthèse annuelle (par commune + total général)
     */
    public function taxes(Request $request, \App\Services\TaxReportService $service)
    {
        $year = (int) ($request->annee ?? date('Y'));
        if ($year < 2000 || $year > 2099) $year = (int) date('Y');

        $filters = array_filter([
            'client_id'   => $request->input('client_id')   ?: null,
            'campaign_id' => $request->input('campaign_id') ?: null,
            'commune_id'  => $request->input('commune_id')  ?: null,
        ]);

        $monthly  = $service->monthlyMatrix($year, $filters);

        // Si filtres client/campagne → on recalcule annual à partir de
        // monthlyMatrix filtré (annualByCommune ne supporte pas encore
        // les filtres et serait incohérent avec la matrice).
        if (!empty($filters)) {
            $annual = $this->annualFromMonthly($monthly);
            $totals = $this->totalsFromMonthly($monthly);
        } else {
            $annual = $service->annualByCommune($year);
            $totals = $service->totals($year);
        }

        // Filtre commune appliqué en sortie (la requête de fond charge
        // tout, on filtre l'affichage — plus simple à maintenir).
        if (!empty($filters['commune_id'])) {
            $cid = (int) $filters['commune_id'];
            $monthly = $monthly->where('commune_id', $cid)->values();
            $annual  = $annual->where('commune_id', $cid)->values();
        }

        // Matrice 12 colonnes × N lignes (commune)
        $months = ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $byCommune = $monthly->groupBy('commune_id');
        $matrix = $annual->map(function ($row) use ($byCommune) {
            $cells = [];
            $rows  = $byCommune[$row['commune_id']] ?? collect();
            for ($m = 1; $m <= 12; $m++) {
                $cell = $rows->firstWhere('month', $m);
                $cells[$m] = $cell ? (float) $cell['total'] : 0;
            }
            return [
                'commune'     => $row['commune'],
                'commune_id'  => $row['commune_id'],
                'cells'       => $cells,
                'odp'         => $row['odp'],
                'tm'          => $row['tm'],
                'total'       => $row['total'],
                'q1'          => $row['q1'],
                'q2'          => $row['q2'],
                'q3'          => $row['q3'],
                'q4'          => $row['q4'],
                'panel_max'   => $row['panel_max'],
            ];
        });

        $anneesDisponibles = range(date('Y') + 1, max(2020, date('Y') - 5));

        // Selects pour les filtres UI
        $clients   = \App\Models\Client::orderBy('name')->get(['id', 'name']);
        $campaigns = \App\Models\Campaign::whereYear('start_date', '<=', $year)
            ->whereYear('end_date', '>=', $year)
            ->orderBy('name')
            ->get(['id', 'name', 'client_id']);
        $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name']);

        return view('admin.rapports.taxes', compact(
            'year', 'months', 'matrix', 'totals', 'anneesDisponibles',
            'clients', 'campaigns', 'communes', 'filters'
        ));
    }

    /** Agrégation annuelle par commune à partir d'une matrice mensuelle filtrée. */
    private function annualFromMonthly(\Illuminate\Support\Collection $monthly): \Illuminate\Support\Collection
    {
        return $monthly->groupBy('commune_id')->map(function ($rows, $cid) {
            $first = $rows->first();
            $q1 = $rows->whereIn('month', [1,2,3])->sum('total');
            $q2 = $rows->whereIn('month', [4,5,6])->sum('total');
            $q3 = $rows->whereIn('month', [7,8,9])->sum('total');
            $q4 = $rows->whereIn('month', [10,11,12])->sum('total');
            return [
                'commune_id' => (int) $cid,
                'commune'    => $first['commune'] ?? '',
                'odp'        => (float) $rows->sum('odp'),
                'tm'         => (float) $rows->sum('tm'),
                'total'      => (float) $rows->sum('total'),
                'q1'         => $q1, 'q2' => $q2, 'q3' => $q3, 'q4' => $q4,
                'panel_max'  => (int) ($rows->max('panel_count') ?? 0),
            ];
        })->values();
    }

    /** Totaux annuels globaux à partir d'une matrice mensuelle filtrée.
     *  Aligné sur TaxReportService::totals() pour que la vue n'ait pas
     *  à se soucier d'où viennent les données. */
    private function totalsFromMonthly(\Illuminate\Support\Collection $monthly): array
    {
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $byMonth[$m] = (float) $monthly->where('month', $m)->sum('total');
        }
        return [
            'year'      => (int) ($monthly->first()['year'] ?? date('Y')),
            'odp'       => (float) $monthly->sum('odp'),
            'tm'        => (float) $monthly->sum('tm'),
            'total'     => (float) $monthly->sum('total'),
            'by_month'  => $byMonth,
            'q1'        => array_sum(array_slice($byMonth, 0, 3, true)),
            'q2'        => array_sum(array_slice($byMonth, 3, 3, true)),
            'q3'        => array_sum(array_slice($byMonth, 6, 3, true)),
            'q4'        => array_sum(array_slice($byMonth, 9, 3, true)),
            'communes'  => $monthly->groupBy('commune_id')->count(),
            'panel_max' => (int) ($monthly->max('panel_count') ?? 0),
        ];
    }

    /**
     * Drilldown commune (AJAX) — appelé depuis le tableau / la heatmap des
     * rapports quand l'admin clique sur une commune. Renvoie un détail
     * exploitable sans rechargement de la page rapports :
     *   - stats résumées (occ/libre/maint/CA/tarif moyen)
     *   - panneaux de la commune (réf, statut, prix)
     *   - campagnes actives et planifiées impliquant cette commune
     *   - top 5 clients sur l'année (par CA généré sur cette commune)
     */
    public function communeDetail(Request $request, Commune $commune)
    {
        $annee = (int) ($request->annee ?? date('Y'));
        // Période d'évaluation du taux d'occupation par panneau — par
        // défaut l'année entière, ajustable via mois_du / mois_au.
        $moisDu = (int) ($request->mois_du ?? 1);
        $moisAu = (int) ($request->mois_au ?? 12);
        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo   = Carbon::create($annee, $moisAu, 1)->endOfMonth();
        $totalDays = max(1, (int) $dateFrom->diffInDays($dateTo) + 1);

        $panels = Panel::where('commune_id', $commune->id)
            ->with([
                'format:id,name',
                'zone:id,name',
                // Lot 8.2 : on charge les campagnes liées au panneau qui
                // chevauchent la période → calcul taux occupation panneau.
                'campaigns' => fn($q) =>
                    $q->whereIn('status', ['actif', 'planifie', 'termine'])
                      ->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                      ->select(['campaigns.id', 'campaigns.start_date', 'campaigns.end_date', 'campaigns.status']),
            ])
            ->orderBy('reference')
            ->get(['id','reference','name','status','monthly_rate','format_id','zone_id','is_lit']);

        // Eloquent\Collection::only() prend des clés de modèle ; pour compter
        // par status on filtre directement (pas de groupBy + only).
        $total = $panels->count();
        $occ   = $panels->whereIn('status', ['occupe', 'option', 'confirme'])->count();
        $libre = $panels->where('status', 'libre')->count();
        $maint = $panels->where('status', 'maintenance')->count();
        $taux  = $total > 0 ? round(($occ / $total) * 100) : 0;

        // Campagnes touchant la commune (via panels) + externalPanels
        $campagnes = Campaign::query()
            ->whereYear('start_date', '<=', $annee)
            ->whereYear('end_date', '>=', $annee)
            ->where(function ($q) use ($commune) {
                $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                  ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
            })
            ->with('client:id,name')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get(['id','name','client_id','status','start_date','end_date','total_amount']);

        // Top 5 clients : CA cumulé sur l'année pour cette commune.
        // On regroupe à partir des campagnes ci-dessus pour rester cohérent
        // avec ce qu'affiche le tableau drilldown.
        $topClients = Campaign::query()
            ->whereYear('start_date', $annee)
            ->where(function ($q) use ($commune) {
                $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                  ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
            })
            ->select('client_id', DB::raw('SUM(total_amount) as ca'), DB::raw('COUNT(*) as nb'))
            ->groupBy('client_id')
            ->orderByDesc('ca')
            ->limit(5)
            ->with('client:id,name')
            ->get();

        // Tarif moyen + CA total commune (toutes campagnes confondues sur l'année)
        $tarifMoyen = (float) Panel::where('commune_id', $commune->id)->avg('monthly_rate') ?? 0;
        $caAnnee = Campaign::whereYear('start_date', $annee)
            ->where(function ($q) use ($commune) {
                $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                  ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
            })
            ->sum('total_amount');

        return response()->json([
            'commune' => [
                'id'         => $commune->id,
                'name'       => $commune->name,
                'odp_rate'   => (float) ($commune->odp_rate ?? 0),
                'tm_rate'    => (float) ($commune->tm_rate  ?? 0),
            ],
            'stats' => [
                'total'       => $total,
                'occupes'     => $occ,
                'libres'      => $libre,
                'maintenance' => $maint,
                'taux'        => $taux,
                'tarif_moyen' => round($tarifMoyen),
                'ca_annee'    => (float) $caAnnee,
            ],
            'period' => [
                'from'       => $dateFrom->format('d/m/Y'),
                'to'         => $dateTo->format('d/m/Y'),
                'total_days' => $totalDays,
            ],
            'panels' => $panels->map(function ($p) use ($dateFrom, $dateTo, $totalDays) {
                // Lot 8.2 — Taux d'occupation par panneau sur la période.
                // Calcul : somme des jours d'occupation effective
                // (intersection campagnes ∩ période) / total_days × 100.
                $busyDays = 0;
                foreach ($p->campaigns as $c) {
                    $cStart = $c->start_date->lt($dateFrom) ? $dateFrom->copy() : $c->start_date->copy();
                    $cEnd   = $c->end_date->gt($dateTo)     ? $dateTo->copy()   : $c->end_date->copy();
                    if ($cStart->lte($cEnd)) {
                        $busyDays += (int) $cStart->diffInDays($cEnd) + 1;
                    }
                }
                // Si plusieurs campagnes se chevauchent, on cape à 100 %.
                $busyDays = min($busyDays, $totalDays);
                $tauxOcc  = $totalDays > 0 ? round(($busyDays / $totalDays) * 100) : 0;

                return [
                    'id'           => $p->id,
                    'reference'    => $p->reference,
                    'name'         => $p->name,
                    'format'       => $p->format?->name ?? '—',
                    'zone'         => $p->zone?->name ?? '—',
                    'status'       => $p->status,
                    'is_lit'       => (bool) $p->is_lit,
                    'rate'         => (float) ($p->monthly_rate ?? 0),
                    'url'          => route('admin.panels.show', $p->id),
                    // Lot 8.2 — taux d'occupation panneau sur la période
                    'taux'         => $tauxOcc,
                    'busy_days'    => $busyDays,
                    'campaigns'    => $p->campaigns->count(),
                ];
            }),
            'campagnes' => $campagnes->map(fn($c) => [
                'id'         => $c->id,
                'name'       => $c->name,
                'client'     => $c->client?->name ?? '—',
                'status'     => $c->status?->value ?? (string) $c->status,
                'start_date' => $c->start_date?->format('d/m/Y'),
                'end_date'   => $c->end_date?->format('d/m/Y'),
                'amount'     => (float) ($c->total_amount ?? 0),
                'url'        => route('admin.campaigns.show', $c->id),
            ]),
            'top_clients' => $topClients->map(fn($r) => [
                'name' => $r->client?->name ?? '—',
                'id'   => $r->client_id,
                'ca'   => (float) $r->ca,
                'nb'   => (int) $r->nb,
                'url'  => $r->client_id ? route('admin.clients.show', $r->client_id) : null,
            ]),
        ]);
    }

    // ── Rapport motifs d'annulation ────────────────────────────
    public function annulations(Request $request)
    {
        $annee = (int) ($request->annee ?? date('Y'));
        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        $cancelledAll = Campaign::where('status', 'annule')
            ->whereYear('updated_at', $annee)
            ->with(['client:id,name', 'user:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        $total = $cancelledAll->count();

        $reasonLabels = [
            'budget'     => 'Budget insuffisant',
            'zone'       => 'Zone non pertinente',
            'strategie'  => 'Changement de stratégie',
            'report'     => 'Report de campagne',
            'concurrent' => 'Choix concurrent',
            'autre'      => 'Autre',
            ''           => 'Non renseigné',
        ];
        $reasonColors = [
            'budget'     => '#ef4444',
            'zone'       => '#f97316',
            'strategie'  => '#8b5cf6',
            'report'     => '#3b82f6',
            'concurrent' => '#06b6d4',
            'autre'      => '#6b7280',
            ''           => '#374151',
        ];

        $byReason = $cancelledAll->groupBy(fn($c) => $c->cancellation_reason ?? '')
            ->map(fn($group, $key) => [
                'key'   => $key,
                'label' => $reasonLabels[$key] ?? 'Autre',
                'color' => $reasonColors[$key] ?? '#6b7280',
                'count' => $group->count(),
                'pct'   => $total > 0 ? round($group->count() / $total * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        return view('admin.rapports.annulations', compact(
            'annee', 'anneesDisponibles',
            'cancelledAll', 'total', 'byReason', 'reasonLabels', 'reasonColors'
        ));
    }

    // ════════════════════════════════════════════════════════════════
    // RAPPORT CAMPAGNES — KPI, top performers, motifs annulation, tendance
    // ════════════════════════════════════════════════════════════════
    public function campagnes(Request $request)
    {
        $annee  = (int) ($request->annee  ?? date('Y'));
        $moisDu = (int) ($request->mois_du ?? 1);
        $moisAu = (int) ($request->mois_au ?? 12);

        $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
        $dateTo   = Carbon::create($annee, $moisAu, 1)->endOfMonth();
        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        // Périmètre : campagnes qui CHEVAUCHENT la période (commencent
        // avant la fin et finissent après le début).
        $baseQuery = fn() => Campaign::where('start_date', '<=', $dateTo)
                                    ->where('end_date',   '>=', $dateFrom);

        // ── 1. Comptes par statut ──────────────────────────────────
        $byStatus = $baseQuery()
            ->select('status', DB::raw('COUNT(*) as nb'))
            ->groupBy('status')
            ->pluck('nb', 'status');

        $total        = (int) $byStatus->sum();
        $actives      = (int) ($byStatus['actif']    ?? 0);
        $terminees    = (int) ($byStatus['termine']  ?? 0);
        $annulees     = (int) ($byStatus['annule']   ?? 0);
        $planifiees   = (int) ($byStatus['planifie'] ?? 0);
        $enPause      = (int) ($byStatus['pause']    ?? 0);
        $tauxAnnulation = $total > 0 ? round(($annulees / $total) * 100, 1) : 0;

        // ── 2. Motifs d'annulation (groupés) ───────────────────────
        $reasonLabels = [
            'budget'     => 'Budget insuffisant',
            'zone'       => 'Zone non pertinente',
            'strategie'  => 'Changement de stratégie',
            'report'     => 'Report de campagne',
            'concurrent' => 'Choix concurrent',
            'autre'      => 'Autre',
            ''           => 'Non renseigné',
        ];
        $reasonColors = [
            'budget'     => '#ef4444',
            'zone'       => '#f97316',
            'strategie'  => '#8b5cf6',
            'report'     => '#3b82f6',
            'concurrent' => '#06b6d4',
            'autre'      => '#6b7280',
            ''           => '#374151',
        ];

        $cancelledOnPeriod = $baseQuery()
            ->where('status', 'annule')
            ->get(['id', 'cancellation_reason']);

        $motifsAnnulation = $cancelledOnPeriod
            ->groupBy(fn($c) => $c->cancellation_reason ?? '')
            ->map(fn($group, $key) => [
                'key'   => $key,
                'label' => $reasonLabels[$key] ?? 'Autre',
                'color' => $reasonColors[$key] ?? '#6b7280',
                'count' => $group->count(),
                'pct'   => $annulees > 0 ? round($group->count() / $annulees * 100) : 0,
            ])
            ->sortByDesc('count')
            ->values();

        // ── 3. Top 10 par chiffre d'affaires (total_amount) ────────
        $topByCA = $baseQuery()
            ->whereNotNull('total_amount')
            ->where('total_amount', '>', 0)
            ->with('client:id,name')
            ->orderByDesc('total_amount')
            ->limit(10)
            ->get();

        // ── 4. Top 10 par nombre de panneaux ───────────────────────
        $topByPanels = $baseQuery()
            ->whereNotNull('total_panels')
            ->where('total_panels', '>', 0)
            ->with('client:id,name')
            ->orderByDesc('total_panels')
            ->limit(10)
            ->get();

        // ── 5. Top 10 par durée (en jours) ─────────────────────────
        $topByDuration = $baseQuery()
            ->with('client:id,name')
            ->select('*', DB::raw('DATEDIFF(end_date, start_date) + 1 as duree_jours'))
            ->orderByDesc('duree_jours')
            ->limit(10)
            ->get();

        // ── 6. Tendance mensuelle : lancées vs annulées par mois ──
        $tendance = $baseQuery()
            ->select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as mois'),
                DB::raw('SUM(CASE WHEN status = "annule"   THEN 1 ELSE 0 END) as annulees'),
                DB::raw('SUM(CASE WHEN status <> "annule"  THEN 1 ELSE 0 END) as actives')
            )
            ->groupBy('mois')
            ->orderBy('mois')
            ->get();

        // ── 7. CA total réalisé sur la période ─────────────────────
        $caTotal = $baseQuery()
            ->whereIn('status', ['actif', 'termine', 'pause'])
            ->sum('total_amount');

        return view('admin.rapports.campagnes', compact(
            'annee', 'moisDu', 'moisAu', 'dateFrom', 'dateTo', 'anneesDisponibles',
            'total', 'actives', 'terminees', 'annulees', 'planifiees', 'enPause',
            'tauxAnnulation', 'motifsAnnulation',
            'topByCA', 'topByPanels', 'topByDuration', 'tendance',
            'caTotal'
        ));
    }
}

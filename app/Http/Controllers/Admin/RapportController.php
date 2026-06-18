<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Panel;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Commune;
use App\Models\CampaignPanel;
use App\Services\DashboardKpiService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RapportController extends Controller
{
    /**
     * RBAC commerciaux : un commercial ne voit dans les rapports QUE ses
     * propres campagnes (commercial assigné OU créateur fallback). Admin/MP
     * voient tout.
     *
     * Application : $this->scopeUserCampaigns(Campaign::query()->where(...))
     * sur CHAQUE point d'entrée Campaign de ce controller.
     */
    protected function scopeUserCampaigns($query)
    {
        $user = auth()->user();
        if ($user?->role?->value !== 'commercial') return $query;

        $uid = $user->id;
        // 4 cas couverts :
        //  1) campaign.commercial_user_id == uid (assignation directe admin/MP)
        //  2) Via résa source : reservation.commercial_user_id == uid
        //  3) Résa sans commercial : reservation.user_id == uid (créateur)
        //  4) Campagne manuelle sans résa : campaign.user_id == uid
        return $query->where(function ($q) use ($uid) {
            $q->where('commercial_user_id', $uid)
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
    }

    protected function isCommercial(): bool
    {
        return auth()->user()?->role?->value === 'commercial';
    }

    /**
     * Page principale — délégué à buildReportData() pour qu'index() et
     * ajax() partagent EXACTEMENT la même logique de calcul (zéro drift,
     * RBAC commercial appliqué via $applyCampaignFilters dans le helper).
     */
    public function index(Request $request, DashboardKpiService $kpi)
    {
        return view('admin.rapports.index', $this->buildReportData($request, $kpi));
    }

    /**
     * Construit l'intégralité des variables affichées sur la page Rapports.
     * Source de vérité unique — appelée par index() (rendu full Blade) ET
     * par ajax() (rendu de partials pour live-update).
     *
     * Toute la logique de filtres (zone, commune, ville, client, catégorie,
     * période preset/custom + scope RBAC commercial) est encapsulée ici.
     */
    protected function buildReportData(Request $request, DashboardKpiService $kpi): array
    {
        // ── Résolution de la période : presets OU dates custom ─────
        // Presets : today, week, month, quarter, year, all
        // Sinon : annee + mois_du/mois_au (rétrocompat)
        // Override : from/to en YYYY-MM-DD pour date picker custom
        $preset = $request->input('preset');
        if ($request->filled('from') && $request->filled('to')) {
            // Date picker custom (priorité 1)
            try {
                $dateFrom = Carbon::parse($request->input('from'))->startOfDay();
                $dateTo   = Carbon::parse($request->input('to'))->endOfDay();
                $annee = $dateFrom->year;
                $moisDu = $dateFrom->month;
                $moisAu = $dateTo->month;
            } catch (\Throwable) {
                $preset = 'year';
            }
        }
        if (in_array($preset, ['today', 'week', 'month', 'quarter', 'year', 'all'], true)) {
            $kpi->setPreset($preset);
            ['from' => $dateFrom, 'to' => $dateTo] = $kpi->getPeriod();
            $annee = $dateFrom->year;
            $moisDu = $dateFrom->month;
            $moisAu = $dateTo->month;
        } elseif (!isset($dateFrom)) {
            // Fallback rétrocompat : annee + mois
            $annee  = (int) ($request->annee ?? date('Y'));
            $moisDu = (int) ($request->mois_du ?? 1);
            $moisAu = (int) ($request->mois_au ?? 12);
            $dateFrom = Carbon::create($annee, $moisDu, 1)->startOfMonth();
            $dateTo   = Carbon::create($annee, $moisAu, 1)->endOfMonth();
        }

        // ── Filtres additionnels (commune, ville, client, type panneau, zone) ──
        $filterCommune  = $request->input('filter_commune_id');
        $filterCity     = $request->input('filter_city');
        $filterClient   = $request->input('filter_client_id');
        $filterCategory = $request->input('filter_category_id');
        // Zone : raccourci UX "Abidjan" / "Intérieur" (= toutes villes hors Abidjan)
        $filterZone     = in_array($request->input('filter_zone'), ['abidjan', 'interieur'], true)
                          ? $request->input('filter_zone') : null;

        $kpi->setFilters([
            'commune_id'  => $filterCommune,
            'city'        => $filterCity,
            'client_id'   => $filterClient,
            'category_id' => $filterCategory,
            'zone'        => $filterZone,
        ]);

        // Variables pour les selects du formulaire
        $allCommunes   = Commune::orderBy('name')->get(['id', 'name', 'city']);
        $allClients    = Client::orderBy('name')->get(['id', 'name']);
        $allCategories = \App\Models\PanelCategory::orderBy('name')->get(['id', 'name']);
        $allCities     = $allCommunes->pluck('city')->filter()->unique()->sort()->values();

        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        // Helpers pour appliquer les filtres dimensionnels aux queries legacy
        $applyPanelFilters = function ($query) use ($filterCommune, $filterCity, $filterCategory, $filterZone) {
            if ($filterCommune)  $query->where('commune_id', $filterCommune);
            if ($filterCategory) $query->where('category_id', $filterCategory);
            if ($filterCity)     $query->whereHas('commune', fn($c) => $c->where('city', $filterCity));
            if ($filterZone)     $query->whereHas('commune', fn($c) => $filterZone === 'abidjan'
                                    ? $c->where('city', 'Abidjan')
                                    : $c->where('city', '!=', 'Abidjan'));
            return $query;
        };
        // Le scope RBAC commercial est injecté dans applyCampaignFilters
        // → toutes les queries Campaign de ce controller sont filtrées
        // automatiquement (pas besoin de modifier chaque ligne).
        $self = $this;
        $applyCampaignFilters = function ($query) use ($filterClient, $filterCommune, $filterCity, $filterCategory, $filterZone, $applyPanelFilters, $self) {
            if ($filterClient) $query->where('client_id', $filterClient);
            if ($filterCommune || $filterCity || $filterCategory || $filterZone) {
                $query->whereHas('panels', fn($p) => $applyPanelFilters($p));
            }
            return $self->scopeUserCampaigns($query);
        };

        // ── Stats globales ──────────────────────────────────────
        $totalPanneaux  = $applyPanelFilters(Panel::query())->count();
        $totalCampagnes = $applyCampaignFilters(
            Campaign::where('start_date', '<=', $dateTo)
                    ->where('end_date',   '>=', $dateFrom)
        )->count();

        // Clients actifs sur la période = clients ayant au moins 1
        // campagne qui chevauche la période (filtre dimensionnel appliqué).
        $totalClients = Client::query()
            ->when($filterClient, fn($q) => $q->where('id', $filterClient))
            ->whereHas('campaigns', fn($q) =>
                $applyCampaignFilters(
                    $q->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                )
            )->count();

        // ── Occupation globale SUR LA PÉRIODE ───────────────────
        $occupes = $applyPanelFilters(Panel::query())
            ->whereHas('campaigns', fn($q) =>
                $applyCampaignFilters(
                    $q->whereIn('status', ['actif', 'planifie', 'termine'])
                      ->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                )
            )->count();

        $maintenance = $applyPanelFilters(Panel::where('status', 'maintenance'))->count();
        $libres      = max(0, $totalPanneaux - $occupes - $maintenance);
        $taux        = $totalPanneaux > 0 ? round(($occupes / $totalPanneaux) * 100) : 0;

        $occupation = [
            'taux'        => $taux,
            'occupes'     => $occupes,
            'libres'      => $libres,
            'maintenance' => $maintenance,
            'total'       => $totalPanneaux,
        ];

        // ── CA total période (filtres appliqués) ────────────────
        // ATTENTION (Bloc 4 — Famille B, 2026-06-18) :
        //   $caTotal = CA CONTRACTUEL = somme de Campaign.total_amount sur les
        //   campagnes actives dans la période. Sert encore aux tableaux secondaires
        //   (Top clients, CA par commune, ca_annee, etc.) — cf. arbitrage Q4
        //   patronne.
        //   Les NOUVEAUX KPIs "CA réel" (HT facturé + Encaissé TTC) sont
        //   calculés plus bas via CaRealService — ils ignorent commune/zone/
        //   category (cf. Q2). Le libellé "CA contractuel" est désormais
        //   imposé dans les vues pour lever toute ambiguïté.
        $caTotal = $applyCampaignFilters(
            Campaign::where('start_date', '<=', $dateTo)
                    ->where('end_date',   '>=', $dateFrom)
        )->sum('total_amount');

        $caTicketMoy = $totalCampagnes > 0 ? round($caTotal / $totalCampagnes) : 0;

        // ── CA RÉEL période (HT facturé + TTC encaissé) — Bloc 4 ─────────
        //   Le service délègue le calcul à FinancialDashboardService pour
        //   garantir la cohérence Finance ↔ Rapports (test de cohérence
        //   au franc près, cf. CaRealServiceConsistencyTest).
        //   Filtres ignorés : commune, zone, category — la facturation suit
        //   le client, pas le panneau. Le bandeau d'info (Garde-fou 1) est
        //   généré côté vue via `$caReelIgnoredFilters`.
        $caRealSvc = app(\App\Services\CaRealService::class);
        $caReel = $caRealSvc->kpis(
            $dateFrom,
            $dateTo,
            null, // commercial_id : RBAC déjà géré au niveau Invoice::forCommercialUser via le scope
            $filterClient ? (int) $filterClient : null,
            [
                'commune_id'  => $filterCommune,
                'zone'        => $filterZone,
                'category_id' => $filterCategory,
            ]
        );
        $caReelIgnoredFilters = $caReel['ignored_filters'];

        // ── Occupation par commune SUR LA PÉRIODE (filtres appliqués) ──
        $communeQuery = Commune::query();
        if ($filterCommune) $communeQuery->where('id', $filterCommune);
        if ($filterCity)    $communeQuery->where('city', $filterCity);
        if ($filterZone === 'abidjan')   $communeQuery->where('city', 'Abidjan');
        if ($filterZone === 'interieur') $communeQuery->where('city', '!=', 'Abidjan');
        $communes = $communeQuery->get();

        $occParCommune = $communes->map(function ($commune) use ($dateFrom, $dateTo, $filterCategory, $filterClient, $applyCampaignFilters) {
            $total = Panel::where('commune_id', $commune->id)
                ->when($filterCategory, fn($q) => $q->where('category_id', $filterCategory))
                ->count();
            if ($total === 0) return null;
            $occ = Panel::where('commune_id', $commune->id)
                ->when($filterCategory, fn($q) => $q->where('category_id', $filterCategory))
                ->whereHas('campaigns', fn($q) =>
                    $applyCampaignFilters(
                        $q->whereIn('status', ['actif', 'planifie', 'termine'])
                          ->where('start_date', '<=', $dateTo)
                          ->where('end_date',   '>=', $dateFrom)
                    )
                )->count();
            $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
            $color = $taux >= 75 ? '#ef4444' : ($taux >= 50 ? '#f97316' : ($taux >= 25 ? '#e8a020' : '#22c55e'));
            return ['id' => $commune->id, 'commune' => $commune->name, 'total' => $total, 'occupes' => $occ, 'taux' => $taux, 'color' => $color];
        })->filter()->sortByDesc('taux')->values();

        // ── Évolution mensuelle (12 derniers mois, filtres appliqués) ──
        $evolMensuelle = collect();
        $totalParcFiltered = $totalPanneaux;
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $occ = $applyCampaignFilters(
                Campaign::where('status', 'actif')
                    ->where('start_date', '<=', $end)
                    ->where('end_date', '>=', $start)
            )->withCount('panels')->get()->sum('panels_count');
            $taux = $totalParcFiltered > 0 ? min(round(($occ / $totalParcFiltered) * 100), 100) : 0;
            $moisFrCourt = [1=>'janv.', 2=>'févr.', 3=>'mars', 4=>'avr.', 5=>'mai', 6=>'juin', 7=>'juil.', 8=>'août', 9=>'sept.', 10=>'oct.', 11=>'nov.', 12=>'déc.'];
            $evolMensuelle->push(['label' => $moisFrCourt[$date->month] ?? $date->format('M'), 'taux' => $taux, 'mois' => $date->month, 'annee' => $date->year]);
        }

        // ── CA mensuel (filtres appliqués) ──────────────────────
        // Sélecteur d'année INTERNE au bloc : indépendant du filtre période
        // global (cf. Q1 brief — "garder fenêtre globale + sous-titre +
        // sélecteur année interne pour explorer plusieurs années").
        $caMensuelYear = (int) ($request->input('ca_year') ?: $annee);
        if ($caMensuelYear < 2020 || $caMensuelYear > (int) date('Y') + 1) $caMensuelYear = $annee;
        $caMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $ca = $applyCampaignFilters(
                Campaign::whereYear('start_date', $caMensuelYear)->whereMonth('start_date', $m)
            )->sum('total_amount');
            $moisFrCourtCa = [1=>'janv.', 2=>'févr.', 3=>'mars', 4=>'avr.', 5=>'mai', 6=>'juin', 7=>'juil.', 8=>'août', 9=>'sept.', 10=>'oct.', 11=>'nov.', 12=>'déc.'];
            $caMensuel->push(['label' => $moisFrCourtCa[$m] ?? '?', 'ca' => (float) $ca]);
        }

        // ── CA RÉEL mensuel (HT facturé + TTC encaissé) — Bloc 4 Commit 13
        //   Source unique CaRealService (cohérent avec FinancialDashboardService).
        //   Ignore commune/zone/category (cf. Q2) — bandeau d'info géré côté vue.
        //   Suit le même sélecteur d'année que $caMensuel (ca_year) pour rester
        //   cohérent : 1 sélecteur, 2 graphiques côte à côte.
        $caMensuelHt  = $caRealSvc->mensuelHtFacture(
            $caMensuelYear,
            null,
            $filterClient ? (int) $filterClient : null
        );
        $caMensuelTtc = $caRealSvc->mensuelTtcEncaisse(
            $caMensuelYear,
            null,
            $filterClient ? (int) $filterClient : null
        );

        // ── Tableau mensuel (filtres appliqués) ─────────────────
        // Même logique : sélecteur d'année interne pour explorer plusieurs années.
        $tableauMensuelYear = (int) ($request->input('tableau_year') ?: $annee);
        if ($tableauMensuelYear < 2020 || $tableauMensuelYear > (int) date('Y') + 1) $tableauMensuelYear = $annee;
        $tableauMensuel = collect();
        for ($m = 1; $m <= 12; $m++) {
            $start = Carbon::create($tableauMensuelYear, $m, 1)->startOfMonth();
            $end = Carbon::create($tableauMensuelYear, $m, 1)->endOfMonth();
            $camps = $applyCampaignFilters(
                Campaign::where('start_date', '<=', $end)->where('end_date', '>=', $start)
            )->get();
            $ca = $applyCampaignFilters(
                Campaign::whereYear('start_date', $tableauMensuelYear)->whereMonth('start_date', $m)
            )->sum('total_amount');
            $panneaux = $camps->sum(fn($c) => $c->panels()->count());
            $taux = $totalPanneaux > 0 ? min(round(($panneaux / $totalPanneaux) * 100), 100) : 0;
            $moisFrLong = [1=>'janvier', 2=>'février', 3=>'mars', 4=>'avril', 5=>'mai', 6=>'juin', 7=>'juillet', 8=>'août', 9=>'septembre', 10=>'octobre', 11=>'novembre', 12=>'décembre'];
            $tableauMensuel->push([
                'mois' => ($moisFrLong[$m] ?? '?') . ' ' . $tableauMensuelYear,
                'nb_campagnes' => $camps->count(),
                'panneaux_mobilises' => $panneaux,
                'ca' => (float) $ca,
                'taux' => $taux,
            ]);
        }

        // ── Top clients SUR LA PÉRIODE (filtres appliqués) ─────
        $topClients = Client::query()
            ->when($filterClient, fn($q) => $q->where('id', $filterClient))
            ->with(['campaigns' => function ($q) use ($dateFrom, $dateTo, $applyCampaignFilters) {
                $applyCampaignFilters(
                    $q->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                );
            }])
            ->get()
            ->map(function ($client) {
                $camps = $client->campaigns;
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

        // ── Stats communes (occupation ET CA scopés période, filtres) ──
        $communesForStats = Commune::query()
            ->when($filterCommune, fn($q) => $q->where('id', $filterCommune))
            ->when($filterCity,    fn($q) => $q->where('city', $filterCity))
            ->when($filterZone === 'abidjan',   fn($q) => $q->where('city', 'Abidjan'))
            ->when($filterZone === 'interieur', fn($q) => $q->where('city', '!=', 'Abidjan'))
            ->get();

        $statsCommunes = $communesForStats->map(function ($commune) use ($dateFrom, $dateTo, $filterCategory, $applyCampaignFilters) {
            $base = Panel::where('commune_id', $commune->id)
                ->when($filterCategory, fn($q) => $q->where('category_id', $filterCategory));
            $total = (clone $base)->count();
            $occ = (clone $base)->whereHas('campaigns', fn($q) =>
                $applyCampaignFilters(
                    $q->whereIn('status', ['actif', 'planifie', 'termine'])
                      ->where('start_date', '<=', $dateTo)
                      ->where('end_date',   '>=', $dateFrom)
                )
            )->count();
            $maint  = (clone $base)->where('status', 'maintenance')->count();
            $libres = max(0, $total - $occ - $maint);
            $taux = $total > 0 ? round(($occ / $total) * 100) : 0;
            $tarifMoyen = (clone $base)->avg('monthly_rate') ?? 0;
            $caAnnee = $applyCampaignFilters(
                Campaign::where('start_date', '<=', $dateTo)
                        ->where('end_date',   '>=', $dateFrom)
            )->whereHas('panels', fn($q) => $q->where('commune_id', $commune->id))
              ->sum('total_amount');
            return [
                'id' => $commune->id,
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

        // ── Stats clients (filtres appliqués) ───────────────────
        // Décision UX : on n'affiche QUE les clients avec un CA > 0 sur la
        // période filtrée — un client présent en base mais qui n'a aucun
        // chiffre d'affaires sur la fenêtre choisie est du bruit dans un
        // rapport, et brouille les rangs / la lecture des podiums.
        $statsClients = Client::query()
            ->when($filterClient, fn($q) => $q->where('id', $filterClient))
            ->with(['campaigns' => fn($q) => $applyCampaignFilters($q)])
            ->get()
            ->map(function ($client) {
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
            })
            ->filter(fn($c) => ($c['ca_total'] ?? 0) > 0)
            ->sortByDesc('ca_total')
            ->values();

        // ── Répartition durées (filtres appliqués) ──────────────
        $camps = $applyCampaignFilters(
            Campaign::whereBetween('start_date', [$dateFrom, $dateTo])
        )->get();
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

        // ── Panneaux à décaper (30j, filtres appliqués) ─────────
        $decapQuery = DB::table('campaigns as cp')
            ->join('clients as cl', 'cl.id', '=', 'cp.client_id')
            ->leftJoin('campaign_panels as cpan', 'cpan.campaign_id', '=', 'cp.id')
            ->leftJoin('panels as p', 'p.id', '=', 'cpan.panel_id')
            ->leftJoin('communes as c2', 'c2.id', '=', 'p.commune_id')
            ->where('cp.status', 'actif')
            ->whereBetween('cp.end_date', [now(), now()->addDays(30)])
            ->orderBy('cp.end_date');
        if ($filterClient)   $decapQuery->where('cp.client_id', $filterClient);
        if ($filterCommune)  $decapQuery->where('p.commune_id', $filterCommune);
        if ($filterCategory) $decapQuery->where('p.category_id', $filterCategory);
        if ($filterCity)     $decapQuery->where('c2.city', $filterCity);
        // BUG A : le filtre zone (abidjan/intérieur) n'était pas propagé ici.
        // → quand le user filtrait Intérieur, des panneaux Abidjan apparaissaient
        //   quand même dans la liste à décaper.
        if ($filterZone === 'abidjan')   $decapQuery->where('c2.city', 'Abidjan');
        if ($filterZone === 'interieur') $decapQuery->where('c2.city', '!=', 'Abidjan');
        $aDecaper = $decapQuery
            ->select('p.reference', 'c2.name as commune', 'cl.name as client_name', 'cp.end_date',
                     DB::raw('DATEDIFF(cp.end_date, NOW()) as jours_restants'))
            ->get();

        // ── Nouveaux KPIs analytics via DashboardKpiService ─────────
        // Le service est instancié avec la période sélectionnée et cache
        // automatiquement chaque KPI (TTL 5 min). Performance acceptable
        // même sur gros parc (cf. Service docblock).
        $kpi->setPeriod($dateFrom, $dateTo);

        // Performance panneaux (top loués / sous-performants / inactifs / alertes)
        $topPanels      = $kpi->topPanels(20);
        $lowPanels      = $kpi->lowPanels(20);
        $inactivePanels = $kpi->inactivePanels(60, 30);
        $panelAlerts    = $kpi->panelAlerts();

        // Analyse clients (top CA + volume + fréquence + inactifs)
        $topClientsKpi      = $kpi->topClients(10);
        $topClientsByRev    = $kpi->topClientsByCriteria('revenue', 5);
        $topClientsByVol    = $kpi->topClientsByCriteria('volume', 5);
        $topClientsByFreq   = $kpi->topClientsByCriteria('frequency', 5);
        $clientRevenueDist  = $kpi->clientRevenueDistribution(8);
        $inactivityBucket   = $kpi->inactivityBuckets();
        $inactiveClients3   = $kpi->inactiveClients(3, 50);
        $inactiveClients6   = $kpi->inactiveClients(6, 50);
        $inactiveClients12  = $kpi->inactiveClients(12, 50);

        // Campagnes (stats + motifs annulation + analyse patterns)
        $campaignStats         = $kpi->campaignStats();
        $cancelReasons         = $kpi->cancelReasons();
        $cancellationTrend     = $kpi->cancellationTrend(12);
        $cancellationPatterns  = $kpi->cancellationPatterns();
        $cancellationRecos     = $kpi->cancellationRecommendations();

        // Décappages
        $decapList         = $kpi->decapList(50);
        $upcomingEndings   = $kpi->upcomingEndings(14);
        $decapStats        = $kpi->decapStats();

        // Prévisions (COMMIT D — régression linéaire 3 mois)
        $forecaster        = new \App\Services\KpiForecastService($kpi);
        $forecastRevenue   = $forecaster->revenueForecast(3);
        $forecastOccupation= $forecaster->occupationForecast(3);

        // Financier
        $totalRevenueKpi   = $kpi->totalRevenue();
        $revenueByMonth    = $kpi->revenueByMonth(12);
        $revenueByCommune  = $kpi->revenueByCommune(15);
        $revenueByCity     = $kpi->revenueByCity(15);

        // Corrélation occupation × revenus par commune (scatter chart)
        $occVsRevenue      = $kpi->occupationVsRevenue();

        // Taxes par commune
        $taxesByCommune    = $kpi->taxesByCommune();

        // Évolution mensuelle occupation (12 mois)
        $occupationTrend   = $kpi->occupationTrend(12);

        // Répartition du parc par commune (pour graphique Chart.js)
        $parcByCommune     = $kpi->parcByCommune();

        // Insights auto
        $insights          = $kpi->insights();

        // Benchmarks sectoriels + synthèse exécutive direction (COMMIT E)
        $marketBenchmarks  = $kpi->marketBenchmarks();
        $execSummary       = $kpi->executiveSummary();

        // $delayStats retiré le 2026-06-17 — l'onglet 'SLA & Retards' n'existe
        // plus dans Rapports (page dédiée /admin/sla/retards accessible via
        // sidebar). Calcul délégué à SlaDelaysController::index() côté page
        // dédiée. Économie de perf sur chaque buildReportData() admin/MP.

        // Variables filtres exposées à la vue
        $currentPreset = $preset ?? null;

        return compact(
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
            // Bloc 4 — CA réel (HT facturé + TTC encaissé) + bandeau filtres ignorés
            'caReel',
            'caReelIgnoredFilters',
            'occParCommune',
            'evolMensuelle',
            'caMensuel',
            // Bloc 4 — Commit 13 : séries CA réel (HT facturé + TTC encaissé)
            'caMensuelHt',
            'caMensuelTtc',
            'tableauMensuel',
            'topClients',
            'statsCommunes',
            'statsClients',
            'repartitionDurees',
            'aDecaper',
            // Nouveaux KPIs analytics
            'topPanels',
            'lowPanels',
            'inactivePanels',
            'panelAlerts',
            'topClientsKpi',
            'topClientsByRev',
            'topClientsByVol',
            'topClientsByFreq',
            'clientRevenueDist',
            'inactivityBucket',
            'inactiveClients3',
            'inactiveClients6',
            'inactiveClients12',
            'campaignStats',
            'cancelReasons',
            'cancellationTrend',
            'cancellationPatterns',
            'cancellationRecos',
            'decapList',
            'upcomingEndings',
            'decapStats',
            'forecastRevenue',
            'forecastOccupation',
            'totalRevenueKpi',
            'revenueByMonth',
            'revenueByCommune',
            'revenueByCity',
            'occVsRevenue',
            'taxesByCommune',
            'insights',
            'marketBenchmarks',
            'execSummary',
            'occupationTrend',
            'parcByCommune',
            // Filtres exposés
            'currentPreset',
            'filterCommune',
            'filterCity',
            'filterClient',
            'filterCategory',
            'filterZone',
            'caMensuelYear',
            'tableauMensuelYear',
            'allCommunes',
            'allClients',
            'allCategories',
            'allCities',
        );
    }

    /**
     * Endpoint AJAX — renvoie tous les blocs de la page Rapports en HTML
     * pré-rendu via partials Blade, prêts pour innerHTML côté client.
     *
     * Source de vérité = buildReportData() (mêmes filtres / mêmes
     * calculs / même RBAC que la page complète).
     */
    public function ajax(Request $request, DashboardKpiService $kpi)
    {
        $t0   = microtime(true);
        $data = $this->buildReportData($request, $kpi);

        $exportFilters = $request->only([
            'preset','from','to','annee','mois_du','mois_au',
            'filter_commune_id','filter_city','filter_client_id','filter_category_id','filter_zone',
            'ca_year','tableau_year',
        ]);

        $render = fn(string $partial) => view("admin.rapports.partials.$partial", $data)->render();

        // chartData = source unique pour ré-instancier TOUS les charts
        // Chart.js côté client. Reproduit la structure de window.__RPT__
        // servie par index.blade.php au 1er load. Couvre les 10 charts +
        // les 2 rendus customs (renderEvol, renderCa).
        $chartData = [
            'occParCommune'     => $data['occParCommune']->values(),
            'evolMensuelle'     => $data['evolMensuelle']->values(),
            'caMensuel'         => $data['caMensuel']->values(),
            // Bloc 4 — Commit 13 : séries CA réel pour le graphique 2 lignes
            'caMensuelHt'       => $data['caMensuelHt']->values(),
            'caMensuelTtc'      => $data['caMensuelTtc']->values(),
            'tableauMensuel'    => $data['tableauMensuel']->values(),
            'topClients'        => $data['topClients']->values(),
            'statsCommunes'     => $data['statsCommunes']->values(),
            'annee'             => $data['annee'],
            'moisDu'            => $data['moisDu'],
            'moisAu'            => $data['moisAu'],
            'occupationTrend'   => $data['occupationTrend']->values(),
            'topPanels'         => $data['topPanels']->values(),
            'cancelReasons'     => $data['cancelReasons']->values(),
            'cancellationTrend' => $data['cancellationTrend']->values(),
            'revenueByMonth'    => $data['revenueByMonth']->values(),
            'inactivityBucket'  => $data['inactivityBucket'],
            'parcByCommune'     => $data['parcByCommune']->values(),
            'occVsRevenue'      => $data['occVsRevenue']->values(),
            'revenueByCity'     => $data['revenueByCity']->values(),
            'revenueByCommune'  => $data['revenueByCommune']->values(),
            'clientRevenueDist' => $data['clientRevenueDist'],
            'campaignStats'     => $data['campaignStats'],
            // slaByMotif retiré le 2026-06-17 — onglet SLA plus exposé via Rapports.
        ];

        $response = response()->json([
            'summary'     => $render('_summary'),
            'topcards'    => $render('_topcards'),
            'kpis'        => $render('_kpis'),
            'tabs' => [
                'occupation'  => $render('_tab_occupation'),
                'performance' => $render('_tab_panneaux'),
                'periodes'    => $render('_tab_periodes'),
                // tabs.campagnes + tabs.sla retirés le 2026-06-17
                // (onglets eux-mêmes retirés dans la nav).
                'ca'          => $render('_tab_ca'),
                'zones'       => $render('_tab_zones'),
                'clients'     => $render('_tab_clients'),
                'decappages'  => $render('_tab_decappages'),
                'insights'    => $render('_tab_insights'),
            ],
            'chartData'   => $chartData,
            'exports_qs'  => http_build_query($exportFilters),
            'fingerprint' => md5(json_encode($exportFilters)),
        ]);

        $ms = (int) round((microtime(true) - $t0) * 1000);
        if ($ms > 2000) {
            \Log::warning('rapports.ajax slow', ['ms' => $ms, 'filters' => $exportFilters]);
        }
        return $response->header('X-Rapports-Ms', $ms);
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

        // monthlyMatrix supporte maintenant tous les filtres (client/
        // campagne/commune) à la source, donc tout est cohérent en aval.
        $monthly = $service->monthlyMatrix($year, $filters);

        if (!empty($filters)) {
            $annual = $this->annualFromMonthly($monthly);
            $totals = $this->totalsFromMonthly($monthly);
        } else {
            $annual = $service->annualByCommune($year);
            $totals = $service->totals($year);
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
        $campaigns = $this->scopeUserCampaigns(
                \App\Models\Campaign::whereYear('start_date', '<=', $year)
                    ->whereYear('end_date', '>=', $year)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'client_id']);
        $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name']);

        // COMMIT C — Comparaison collectées (théoriques) vs reversées (payées)
        //
        // ⚠ Deux sources de paiement coexistent dans le système :
        //   1. Table legacy `taxes` (paiement individuel par écriture/année)
        //   2. Table `commune_tax_payments` (paiement par commune × période,
        //      utilisée par /admin/taxes/* refonte 2026).
        //
        // On agrège les DEUX pour éviter qu'un paiement enregistré via la
        // page Taxes Communales n'apparaisse pas dans ce rapport, ce qui
        // donnait l'illusion que rien n'était payé.
        $legacyPaid = DB::table('taxes')
            ->where('year', $year)
            ->whereNotNull('paid_at')
            ->select('commune_id',
                DB::raw("SUM(CASE WHEN type = 'odp' THEN amount ELSE 0 END) as paid_odp"),
                DB::raw("SUM(CASE WHEN type = 'tm'  THEN amount ELSE 0 END) as paid_tm"),
                DB::raw('SUM(amount) as paid_total'),
                DB::raw('MAX(paid_at) as last_paid_at'),
                DB::raw('COUNT(*) as paid_count'),
            )
            ->groupBy('commune_id')
            ->get()
            ->keyBy('commune_id');

        $newPaid = DB::table('commune_tax_payments')
            ->whereNull('deleted_at')
            ->where('period_year', $year)
            ->select('commune_id',
                DB::raw('SUM(odp_paye) as paid_odp'),
                DB::raw('SUM(tm_paye)  as paid_tm'),
                DB::raw('SUM(odp_paye + tm_paye) as paid_total'),
                DB::raw('MAX(paid_at) as last_paid_at'),
                DB::raw('COUNT(*) as paid_count'),
            )
            ->groupBy('commune_id')
            ->get()
            ->keyBy('commune_id');

        // Fusion des deux sources : on additionne montants et on prend la
        // date max comme last_paid_at.
        $paidByCommune = collect();
        $allCids = $legacyPaid->keys()->merge($newPaid->keys())->unique();
        foreach ($allCids as $cid) {
            $a = $legacyPaid->get($cid);
            $b = $newPaid->get($cid);
            $lastA = $a?->last_paid_at;
            $lastB = $b?->last_paid_at;
            $lastDate = match (true) {
                !$lastA           => $lastB,
                !$lastB           => $lastA,
                $lastA >= $lastB  => $lastA,
                default           => $lastB,
            };
            $paidByCommune->put($cid, (object) [
                'commune_id'   => (int) $cid,
                'paid_odp'     => (float) ($a->paid_odp ?? 0) + (float) ($b->paid_odp ?? 0),
                'paid_tm'      => (float) ($a->paid_tm  ?? 0) + (float) ($b->paid_tm  ?? 0),
                'paid_total'   => (float) ($a->paid_total ?? 0) + (float) ($b->paid_total ?? 0),
                'last_paid_at' => $lastDate,
                'paid_count'   => (int) ($a->paid_count ?? 0) + (int) ($b->paid_count ?? 0),
            ]);
        }

        // Synthèse comparaison par commune : enrichit le matrix
        $comparison = $matrix->map(function ($row) use ($paidByCommune) {
            $paid = $paidByCommune->get($row['commune_id']);
            $paidTotal = (float) ($paid->paid_total ?? 0);
            $due       = (float) $row['total'];
            $balance   = $due - $paidTotal;
            return [
                'commune_id'   => $row['commune_id'],
                'commune'      => $row['commune'],
                'due_total'    => $due,
                'paid_odp'     => (float) ($paid->paid_odp ?? 0),
                'paid_tm'      => (float) ($paid->paid_tm  ?? 0),
                'paid_total'   => $paidTotal,
                'balance'      => $balance,
                'rate'         => $due > 0 ? round(($paidTotal / $due) * 100, 1) : ($paidTotal > 0 ? 100 : 0),
                'last_paid_at' => $paid->last_paid_at ?? null,
                'status'       => $balance <= 0 && $due > 0 ? 'paid' : ($paidTotal > 0 ? 'partial' : 'pending'),
            ];
        })->sortByDesc('due_total')->values();

        $comparisonTotals = [
            'due'     => (float) $comparison->sum('due_total'),
            'paid'    => (float) $comparison->sum('paid_total'),
            'balance' => (float) $comparison->sum('balance'),
            'rate'    => $comparison->sum('due_total') > 0
                ? round(($comparison->sum('paid_total') / $comparison->sum('due_total')) * 100, 1)
                : 0,
            'paid_communes'    => $comparison->where('status', 'paid')->count(),
            'partial_communes' => $comparison->where('status', 'partial')->count(),
            'pending_communes' => $comparison->where('status', 'pending')->count(),
        ];

        // Évolution multi-années : 5 dernières années — ODP / TM / Reversé.
        // Respecte le filtre commune (sinon le trend serait tout commune
        // pendant qu'on affiche le détail d'une seule, créant l'illusion
        // que cette commune génère le total global).
        $cidFilter = !empty($filters['commune_id']) ? (int) $filters['commune_id'] : null;
        $yearlyTrend = collect();
        for ($y = $year - 4; $y <= $year; $y++) {
            try {
                $yTotals = $service->totals($y, $filters);

                // Source 1 : table legacy `taxes`
                $yLegacyQuery = DB::table('taxes')
                    ->where('year', $y)->whereNotNull('paid_at');
                if ($cidFilter) $yLegacyQuery->where('commune_id', $cidFilter);
                $yLegacy = $yLegacyQuery
                    ->select(
                        DB::raw("SUM(CASE WHEN type = 'odp' THEN amount ELSE 0 END) as paid_odp"),
                        DB::raw("SUM(CASE WHEN type = 'tm'  THEN amount ELSE 0 END) as paid_tm"),
                        DB::raw("SUM(amount) as paid_total"),
                    )->first();

                // Source 2 : table refonte `commune_tax_payments`
                $yNewQuery = DB::table('commune_tax_payments')
                    ->whereNull('deleted_at')
                    ->where('period_year', $y);
                if ($cidFilter) $yNewQuery->where('commune_id', $cidFilter);
                $yNew = $yNewQuery
                    ->select(
                        DB::raw('SUM(odp_paye) as paid_odp'),
                        DB::raw('SUM(tm_paye)  as paid_tm'),
                        DB::raw('SUM(odp_paye + tm_paye) as paid_total'),
                    )->first();

                $yPaid = (object) [
                    'paid_odp'   => (float) ($yLegacy->paid_odp ?? 0) + (float) ($yNew->paid_odp ?? 0),
                    'paid_tm'    => (float) ($yLegacy->paid_tm  ?? 0) + (float) ($yNew->paid_tm  ?? 0),
                    'paid_total' => (float) ($yLegacy->paid_total ?? 0) + (float) ($yNew->paid_total ?? 0),
                ];
                $yearlyTrend->push([
                    'year'       => $y,
                    'odp'        => (float) ($yTotals['odp'] ?? 0),
                    'tm'         => (float) ($yTotals['tm'] ?? 0),
                    'total'      => (float) ($yTotals['total'] ?? 0),
                    'paid_odp'   => (float) ($yPaid->paid_odp ?? 0),
                    'paid_tm'    => (float) ($yPaid->paid_tm ?? 0),
                    'paid_total' => (float) ($yPaid->paid_total ?? 0),
                ]);
            } catch (\Throwable) {
                // Année non encore initialisée
                $yearlyTrend->push([
                    'year' => $y, 'odp' => 0, 'tm' => 0, 'total' => 0,
                    'paid_odp' => 0, 'paid_tm' => 0, 'paid_total' => 0,
                ]);
            }
        }

        return view('admin.rapports.taxes', compact(
            'year', 'months', 'matrix', 'totals', 'anneesDisponibles',
            'clients', 'campaigns', 'communes', 'filters',
            'comparison', 'comparisonTotals', 'yearlyTrend'
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
        // RBAC commercial : seules ses campagnes
        $campagnes = $this->scopeUserCampaigns(
                Campaign::query()
                    ->whereYear('start_date', '<=', $annee)
                    ->whereYear('end_date', '>=', $annee)
                    ->where(function ($q) use ($commune) {
                        $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                          ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
                    })
            )
            ->with('client:id,name')
            ->orderByDesc('start_date')
            ->limit(20)
            ->get(['id','name','client_id','status','start_date','end_date','total_amount']);

        // Top 5 clients : CA cumulé sur l'année pour cette commune.
        // On regroupe à partir des campagnes ci-dessus pour rester cohérent
        // avec ce qu'affiche le tableau drilldown.
        // RBAC commercial : limité à ses campagnes
        $topClients = $this->scopeUserCampaigns(
                Campaign::query()
                    ->whereYear('start_date', $annee)
                    ->where(function ($q) use ($commune) {
                        $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                          ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
                    })
            )
            ->select('client_id', DB::raw('SUM(total_amount) as ca'), DB::raw('COUNT(*) as nb'))
            ->groupBy('client_id')
            ->orderByDesc('ca')
            ->limit(5)
            ->with('client:id,name')
            ->get();

        // Tarif moyen + CA total commune (toutes campagnes confondues sur l'année)
        $tarifMoyen = (float) Panel::where('commune_id', $commune->id)->avg('monthly_rate') ?? 0;
        $caAnnee = $this->scopeUserCampaigns(
            Campaign::whereYear('start_date', $annee)
            ->where(function ($q) use ($commune) {
                $q->whereHas('panels', fn($p) => $p->where('commune_id', $commune->id))
                  ->orWhereHas('externalPanels', fn($p) => $p->where('commune_id', $commune->id));
            })
        )->sum('total_amount');

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

    /**
     * Drill-down client (COMMIT B) — historique complet d'un client :
     * campagnes (toutes confondues), top panneaux loués, communes
     * exploitées, CA mensuel 12 mois, synthèse churn.
     *
     * Délègue intégralement à DashboardKpiService::clientDetail() qui est
     * mis en cache. Permet d'éviter de surcharger la vue principale.
     */
    /**
     * Marque (ou démarque) un panneau comme décappé pour une campagne donnée.
     * Endpoint AJAX appelé depuis le rapport décappage.
     *
     * Vérifications :
     *   - Le pivot doit exister (campaign × panel)
     *   - La campagne doit être terminée (status='termine' && end_date <= now)
     *   - L'utilisateur doit avoir le droit (admin ou superadmin)
     */
    /**
     * GET endpoint léger : renvoie les KPI décappage frais en JSON.
     * Appelé par le JS après chaque mark/unmark pour rafraîchir la
     * bannière "PANNEAUX CONCERNÉS / DÉCAPPÉS / EN ATTENTE / EN RETARD"
     * sans recharger toute la page (qui ferait perdre l'état des
     * <details> ouverts et le scroll position).
     */
    public function decapSummary(DashboardKpiService $kpi)
    {
        $stats = $kpi->decapStats();
        return response()->json([
            'ok'        => true,
            'total'     => (int) ($stats['total']    ?? 0),
            'decapped'  => (int) ($stats['decapped'] ?? 0),
            'pending'   => (int) ($stats['pending']  ?? 0),
            'overdue'   => (int) ($stats['overdue']  ?? 0),
            'rate'      => (float) ($stats['rate']   ?? 0),
        ]);
    }

    public function markDecapped(Request $request, DashboardKpiService $kpi)
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'panel_id'    => 'required|integer|exists:panels,id',
            'action'      => 'nullable|in:mark,unmark',
            'notes'       => 'nullable|string|max:500',
        ]);

        $action = $request->input('action', 'mark');
        $userId = $request->user()->id;

        if ($action === 'unmark') {
            $ok = $kpi->unmarkDecapped($request->campaign_id, $request->panel_id);
            return response()->json([
                'ok'      => $ok,
                'message' => $ok ? 'Décappage annulé.' : "Aucune ligne à mettre à jour.",
            ]);
        }

        $ok = $kpi->markDecapped(
            $request->campaign_id,
            $request->panel_id,
            $userId,
            $request->notes,
        );

        if ($ok) {
            $this->notifyClientIfCampaignFullyDecapped((int) $request->campaign_id);
        }

        return response()->json([
            'ok'      => $ok,
            'message' => $ok ? 'Panneau marqué comme décappé.' : "Aucune ligne à mettre à jour.",
            'at'      => now()->format('d/m/Y H:i'),
            'by'      => $request->user()->name,
        ]);
    }

    /**
     * Bulk action — marque tous les panneaux d'une campagne comme décappés.
     */
    public function markAllDecapped(Request $request, DashboardKpiService $kpi)
    {
        $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'notes'       => 'nullable|string|max:500',
        ]);

        $count = $kpi->markAllDecapped(
            $request->campaign_id,
            $request->user()->id,
            $request->notes,
        );

        if ($count > 0) {
            $this->notifyClientIfCampaignFullyDecapped((int) $request->campaign_id);
        }

        return response()->json([
            'ok'      => $count > 0,
            'count'   => $count,
            'message' => $count > 0 ? "{$count} panneaux marqués décappés." : "Aucun panneau à décapper.",
        ]);
    }

    /**
     * Notifie le client par email SI tous les panneaux de la campagne sont
     * désormais décappés (transition vers 100%). Évite les doublons : on
     * vérifie l'absence d'un PublicLink 'decap' existant pour cette campagne.
     */
    protected function notifyClientIfCampaignFullyDecapped(int $campaignId): void
    {
        $total    = \Illuminate\Support\Facades\DB::table('campaign_panels')
            ->where('campaign_id', $campaignId)->count();
        $decapped = \Illuminate\Support\Facades\DB::table('campaign_panels')
            ->where('campaign_id', $campaignId)
            ->whereNotNull('decapped_at')->count();

        if ($total === 0 || $decapped < $total) return; // pas encore 100 %

        $campaign = \App\Models\Campaign::with('client', 'panels.commune')->find($campaignId);
        if (!$campaign || !$campaign->client?->email) return;

        // Idempotence : si on a déjà notifié, on ne renotifie pas
        $already = \App\Models\PublicLink::where('type', 'decap')
            ->where('target_type', \App\Models\Campaign::class)
            ->where('target_id', $campaignId)
            ->exists();
        if ($already) return;

        try {
            $link = \App\Services\PublicLinkService::create(
                target: $campaign,
                type: 'decap',
                expiresAt: now()->addDays(30),
                clientId: $campaign->client_id,
            );

            app(\App\Services\NotificationMailer::class)->sendSilently(
                $campaign->client->email,
                new \App\Mail\CampaignDecappedMail($campaign, $link, $decapped),
                cc: null,
                context: ['campaign_id' => $campaignId, 'client_id' => $campaign->client_id],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('decap.notify.failed', [
                'campaign_id' => $campaignId,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /**
     * Export Excel multi-feuilles du dashboard analytique (COMMIT D).
     * Une feuille par module : synthèse, panneaux, clients, campagnes,
     * communes, décappages, CA mensuel, prévisions.
     */
    public function exportExcel(Request $request, DashboardKpiService $kpi)
    {
        $this->applyPeriodAndFilters($request, $kpi);
        $filename = 'dashboard-panora-' . now()->format('Ymd_His') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\RapportDashboardExport($kpi),
            $filename
        );
    }

    /**
     * Export PDF — synthèse exécutive 1 page (COMMIT D).
     * Reprend les KPIs clés + prévisions linéaires sur 3 mois.
     */
    public function exportPdf(Request $request, DashboardKpiService $kpi)
    {
        $this->applyPeriodAndFilters($request, $kpi);

        $parc       = $kpi->parcOverview();
        $stats      = $kpi->campaignStats();
        $revenue    = $kpi->totalRevenue();
        $inactivity = $kpi->inactivityBuckets();
        $decapStats = $kpi->decapStats();
        $topClients = $kpi->topClients(10);
        $insights   = $kpi->insights();
        $period     = $kpi->getPeriod();
        $forecaster = new \App\Services\KpiForecastService($kpi);
        $forecast   = [
            'revenue'    => $forecaster->revenueForecast(3),
            'occupation' => $forecaster->occupationForecast(3),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.rapports.synthese-pdf', compact(
            'parc', 'stats', 'revenue', 'inactivity', 'decapStats',
            'topClients', 'insights', 'period', 'forecast',
        ) + ['user' => $request->user()])
            ->setPaper('a4', 'portrait');

        return $pdf->download('synthese-executive-' . now()->format('Ymd_His') . '.pdf');
    }

    /**
     * Export Excel — Liste complète des panneaux avec leur taux
     * d'occupation sur la période, filtrable par zone (Abidjan / Intérieur).
     */
    public function exportPanelsOccupationExcel(Request $request, DashboardKpiService $kpi)
    {
        $this->applyPeriodAndFilters($request, $kpi);
        $panels = $kpi->panelsOccupationFull();
        $period = $kpi->getPeriod();
        $zoneLabel = $this->zoneLabel($request->input('filter_zone'));

        $filename = 'occupation-panneaux-' . now()->format('Ymd_His') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PanelsOccupationExport($panels, $period['from'], $period['to'], $zoneLabel),
            $filename
        );
    }

    /**
     * Export PDF — Même rapport en synthèse imprimable (A4 paysage).
     */
    public function exportPanelsOccupationPdf(Request $request, DashboardKpiService $kpi)
    {
        $this->applyPeriodAndFilters($request, $kpi);
        $panels = $kpi->panelsOccupationFull();
        $period = $kpi->getPeriod();
        $zoneLabel = $this->zoneLabel($request->input('filter_zone'));

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.rapports.panels-occupation-pdf', [
            'panels'    => $panels,
            'from'      => $period['from'],
            'to'        => $period['to'],
            'zoneLabel' => $zoneLabel,
            'user'      => $request->user(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('occupation-panneaux-' . now()->format('Ymd_His') . '.pdf');
    }

    private function zoneLabel(?string $zone): ?string
    {
        return match ($zone) {
            'abidjan'   => 'Abidjan',
            'interieur' => 'Intérieur (hors Abidjan)',
            default     => null,
        };
    }

    /**
     * Helper : applique période + filtres au service KPI à partir du
     * Request (mêmes paramètres que la vue principale).
     * Permet aux exports de respecter les filtres de la page rapports.
     */
    private function applyPeriodAndFilters(Request $request, DashboardKpiService $kpi): void
    {
        $preset = $request->input('preset');
        if ($request->filled('from') && $request->filled('to')) {
            try {
                $from = Carbon::parse($request->input('from'))->startOfDay();
                $to   = Carbon::parse($request->input('to'))->endOfDay();
                $kpi->setPeriod($from, $to);
            } catch (\Throwable) {
                $kpi->setPreset('year');
            }
        } elseif (in_array($preset, ['today', 'week', 'month', 'quarter', 'year', 'all'], true)) {
            $kpi->setPreset($preset);
        } else {
            $annee  = (int) ($request->annee ?? date('Y'));
            $moisDu = (int) ($request->mois_du ?? 1);
            $moisAu = (int) ($request->mois_au ?? 12);
            $kpi->setPeriod(
                Carbon::create($annee, $moisDu, 1)->startOfMonth(),
                Carbon::create($annee, $moisAu, 1)->endOfMonth(),
            );
        }

        $kpi->setFilters([
            'commune_id'  => $request->input('filter_commune_id'),
            'city'        => $request->input('filter_city'),
            'client_id'   => $request->input('filter_client_id'),
            'category_id' => $request->input('filter_category_id'),
            'zone'        => in_array($request->input('filter_zone'), ['abidjan', 'interieur'], true)
                                ? $request->input('filter_zone') : null,
        ]);
    }

    /**
     * Drill-down panneau (AJAX) — historique des occupations.
     * Délègue à DashboardKpiService::panelDetail() (cacheable).
     */
    public function panelDetail(Request $request, \App\Models\Panel $panel, DashboardKpiService $kpi)
    {
        $this->applyPeriodAndFilters($request, $kpi);
        $data = $kpi->panelDetail($panel->id);
        if (!$data) return response()->json(['error' => 'Panneau introuvable'], 404);
        return response()->json($data);
    }

    public function clientDetail(Request $request, \App\Models\Client $client, DashboardKpiService $kpi)
    {
        $data = $kpi->clientDetail($client->id);
        if (!$data) {
            return response()->json(['error' => 'Client introuvable'], 404);
        }

        // Format dates en français pour affichage direct dans le modal
        $data['campaigns'] = collect($data['campaigns'])->map(fn($c) => [
            'id'                 => $c->id,
            'name'               => $c->name,
            'status'             => $c->status,
            'start_date'         => $c->start_date ? Carbon::parse($c->start_date)->format('d/m/Y') : '—',
            'end_date'           => $c->end_date   ? Carbon::parse($c->end_date)->format('d/m/Y')   : '—',
            'total_amount'       => (float) $c->total_amount,
            'panels_count'       => (int) $c->panels_count,
            'cancellation_reason'=> $c->cancellation_reason,
            'url'                => route('admin.campaigns.show', $c->id),
        ])->values();

        if ($data['summary']['last_campaign']) {
            $data['summary']['last_campaign'] = Carbon::parse($data['summary']['last_campaign'])->format('d/m/Y');
        }

        return response()->json($data);
    }

    // ── Rapport motifs d'annulation ────────────────────────────
    public function annulations(Request $request)
    {
        $annee = (int) ($request->annee ?? date('Y'));
        $anneesDisponibles = range(date('Y'), max(2020, date('Y') - 5));

        // Filtres période (mission user) : preset + intervalle perso
        $periode    = $request->string('periode')->toString() ?: 'annee';
        $filterMois = $request->integer('mois');
        $filterTrim = $request->integer('trimestre');
        $dateFrom   = $request->date('from');
        $dateTo     = $request->date('to');
        $filterMotif = $request->string('motif')->toString() ?: '';

        $query = $this->scopeUserCampaigns(
                Campaign::where('status', 'annule')
            )
            ->with(['client:id,name', 'user:id,name']);

        // Application des filtres de période
        if ($dateFrom && $dateTo) {
            $query->whereBetween('updated_at', [$dateFrom, $dateTo->endOfDay()]);
        } elseif ($filterMois && $filterMois >= 1 && $filterMois <= 12) {
            $query->whereYear('updated_at', $annee)
                  ->whereMonth('updated_at', $filterMois);
        } elseif ($filterTrim && $filterTrim >= 1 && $filterTrim <= 4) {
            $startMonth = ($filterTrim - 1) * 3 + 1;
            $query->whereYear('updated_at', $annee)
                  ->whereMonth('updated_at', '>=', $startMonth)
                  ->whereMonth('updated_at', '<=', $startMonth + 2);
        } else {
            $query->whereYear('updated_at', $annee);
        }

        if ($filterMotif !== '') {
            // motif '' = "Non renseigné" → on cherche NULL ou string vide
            if ($filterMotif === '__empty') {
                $query->where(function ($q) {
                    $q->whereNull('cancellation_reason')->orWhere('cancellation_reason', '');
                });
            } else {
                $query->where('cancellation_reason', $filterMotif);
            }
        }

        $cancelledAll = $query->orderByDesc('updated_at')->get();
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
            'cancelledAll', 'total', 'byReason', 'reasonLabels', 'reasonColors',
            'periode', 'filterMois', 'filterTrim', 'dateFrom', 'dateTo', 'filterMotif'
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
        // RBAC commercial : filtré automatiquement via scopeUserCampaigns.
        $baseQuery = fn() => $this->scopeUserCampaigns(
            Campaign::where('start_date', '<=', $dateTo)
                    ->where('end_date',   '>=', $dateFrom)
        );

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

        // ── 4. Top 10 PANNEAUX (références) les plus utilisés ─────
        // Compte le nombre de campagnes distinctes (sur la période) dans
        // lesquelles chaque panneau apparaît via campaign_panels. Permet
        // d'identifier les emplacements stars du parc — info plus utile
        // pour la direction que "quelle campagne a le plus de panneaux".
        $topPanels = DB::table('panels')
            ->join('campaign_panels', 'campaign_panels.panel_id', '=', 'panels.id')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->where('campaigns.start_date', '<=', $dateTo)
            ->where('campaigns.end_date',   '>=', $dateFrom)
            ->whereNull('campaigns.deleted_at')
            ->leftJoin('communes', 'communes.id', '=', 'panels.commune_id')
            ->select(
                'panels.id',
                'panels.reference',
                'panels.name',
                'communes.name as commune_name',
                DB::raw('COUNT(DISTINCT campaigns.id) as nb_campagnes')
            )
            ->groupBy('panels.id', 'panels.reference', 'panels.name', 'communes.name')
            ->orderByDesc('nb_campagnes')
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
            'topByCA', 'topPanels', 'topByDuration', 'tendance',
            'caTotal'
        ));
    }

}

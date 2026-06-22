<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Commune;
use App\Models\Invoice;
use App\Models\Relance;
use App\Models\User;
use App\Services\FinancialDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * FinanceDashboardController — page de pilotage financier.
 *
 * Délègue tout le calcul à FinancialDashboardService (source unique).
 * Le scope commercial est passé en argument au service ($commercialUid)
 * pour rester cohérent avec Invoice::forCommercialUser.
 */
class FinanceDashboardController extends Controller
{
    public function __construct(
        protected FinancialDashboardService $svc,
    ) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->parsePeriod($request);
        $bucket = $request->string('bucket')->toString() ?: null;
        $commercialUid = $this->resolveCommercialScope();

        $kpis              = $this->svc->kpis($from, $to, $commercialUid);
        $series            = $this->svc->encaissementsByPeriod($from, $to, $bucket, $commercialUid);
        $topClients        = $this->svc->encaissementsByClient($from, $to, 10, $commercialUid);
        $byCommune         = $this->svc->encaissementsByCommune($from, $to, $commercialUid);
        $byCommercial      = $this->svc->encaissementsByCommercial($from, $to, $commercialUid);
        // Liste détaillée des versements (dates + montants) — cahier §4 / §11
        $recentPayments    = $this->svc->recentPayments($from, $to, 200, $commercialUid);
        $aging             = $this->svc->agingBalance($commercialUid);
        // Phase 8D cahier §8 — Filtres dynamiques recouvrement
        $clientsToFollow   = $this->svc->clientsToFollow(
            $request->string('sort')->toString() ?: 'total_du',
            $commercialUid,
            $request->filled('filter_commercial') ? (int) $request->input('filter_commercial') : null,
            $request->filled('filter_commune')    ? (int) $request->input('filter_commune')    : null,
            $request->filled('filter_client')     ? (int) $request->input('filter_client')     : null
        );
        // "Factures avec reste à payer" : on EXCLUT les factures soldées
        // (remainingAmount ≤ 0) — directive métier : l'onglet Créances
        // n'affiche QUE les vraies créances ouvertes, pas les factures
        // déjà entièrement payées. Le filtre se fait en PHP parce que
        // remainingAmount() est une méthode (somme - versements), pas une
        // colonne SQL. Le limit(200) s'applique APRÈS le filtre pour
        // garantir 200 vraies créances et pas 200 lignes pré-filtrage.
        //
        // 2026-06-18 (Hotfix patronne) : flag `only_overdue` activé depuis
        // le KPI "En retard" → restreint la liste aux créances dont au
        // moins une échéance est dépassée (paymentStatus === 'en_retard').
        $onlyOverdue       = (bool) $request->boolean('only_overdue');
        $creances          = $this->svc->creancesQuery($commercialUid)
                                  ->orderBy('issued_at')
                                  ->get()
                                  ->filter(fn($inv) => $inv->remainingAmount() > 0.01)
                                  ->when($onlyOverdue, fn($c) => $c->filter(fn($inv) => $inv->paymentStatus() === 'en_retard'))
                                  ->take(200)
                                  ->values();

        $clientsList = Client::orderBy('name')->get(['id', 'name']);
        $communes    = Commune::orderBy('name')->get(['id', 'name']);
        $commerciaux = User::whereIn('role', ['admin', 'commercial'])->orderBy('name')->get(['id', 'name']);

        return view('admin.finance.index', [
            'kpis'             => $kpis,
            'series'           => $series,
            'topClients'       => $topClients,
            'byCommune'        => $byCommune,
            'byCommercial'     => $byCommercial,
            'recentPayments'   => $recentPayments,
            'aging'            => $aging,
            'clientsToFollow'  => $clientsToFollow,
            'creances'         => $creances,
            'clientsList'      => $clientsList,
            'communes'         => $communes,
            'commerciaux'      => $commerciaux,
            'from'             => $from,
            'to'               => $to,
            'bucket'           => $bucket ?? 'auto',
            'isCommercialView' => $commercialUid !== null,
            'onlyOverdue'      => $onlyOverdue,
        ]);
    }

    /**
     * Endpoint JSON pour rafraîchir le graphique d'évolution (changement
     * de période ou de bucket côté front). Renvoie aussi les KPI pour
     * éviter un second appel.
     */
    public function series(Request $request)
    {
        [$from, $to] = $this->parsePeriod($request);
        $bucket = $request->string('bucket')->toString() ?: null;
        $commercialUid = $this->resolveCommercialScope();

        return response()->json([
            'kpis'   => $this->svc->kpis($from, $to, $commercialUid),
            'series' => $this->svc->encaissementsByPeriod($from, $to, $bucket, $commercialUid),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // EXPORTS RÉCAP FINANCE (2026-06-19)
    // Excel multi-feuilles + PDF synthèse. Sources : FinancialDashboardService
    // — identique à la page Finance affichée à l'écran (pas de doublure).
    // ══════════════════════════════════════════════════════════════════

    /**
     * Charge en une fois toutes les sections du dashboard pour les exports.
     * Évite la duplication entre exportRecapExcel() et exportRecapPdf().
     */
    private function loadRecapData(Request $request): array
    {
        [$from, $to]    = $this->parsePeriod($request);
        $commercialUid  = $this->resolveCommercialScope();
        return [
            'from'             => $from,
            'to'               => $to,
            'kpis'             => $this->svc->kpis($from, $to, $commercialUid),
            'recentPayments'   => $this->svc->recentPayments($from, $to, 1000, $commercialUid),
            'creances'         => $this->svc->creancesQuery($commercialUid)
                                       ->orderBy('issued_at')
                                       ->get()
                                       ->filter(fn ($inv) => $inv->remainingAmount() > 0.01)
                                       ->values(),
            'clientsToFollow'  => $this->svc->clientsToFollow('total_du', $commercialUid),
            'topClients'       => $this->svc->encaissementsByClient($from, $to, 50, $commercialUid),
        ];
    }

    /** Excel multi-feuilles : Synthèse + Versements + Créances + Recouvrement + Top clients. */
    public function exportRecapExcel(Request $request)
    {
        $data = $this->loadRecapData($request);
        $filename = 'finance-recap-' . now()->format('Ymd_His') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\FinanceRecapExport(
                $data['kpis'],
                $data['recentPayments'],
                $data['creances'],
                $data['clientsToFollow'],
                $data['topClients'],
                $data['from'],
                $data['to'],
                $request->user()?->name,
            ),
            $filename
        );
    }

    /** PDF synthèse exécutive : KPI globaux + top 10 par section. */
    public function exportRecapPdf(Request $request)
    {
        $data = $this->loadRecapData($request);
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.finance.recap-pdf', [
            'kpis'             => $data['kpis'],
            'recentPayments'   => $data['recentPayments'],
            'creances'         => $data['creances'],
            'clientsToFollow'  => $data['clientsToFollow'],
            'from'             => $data['from'],
            'to'               => $data['to'],
            'user'             => $request->user(),
            'operatorName'     => 'CIBLE CI',
        ])->setPaper('a4', 'portrait');
        return $pdf->download('finance-recap-' . now()->format('Ymd_His') . '.pdf');
    }

    // ══════════════════════════════════════════════════════════════════
    // RELANCES
    // ══════════════════════════════════════════════════════════════════

    /**
     * Source UNIQUE des filtres pour toutes les vues/exports de relances
     * (page Historique, export Excel, export PDF). CLAUDE.md règle 1 —
     * pas de duplication entre l'affichage et les exports.
     */
    private function applyRelanceFiltersFromRequest(Request $request): \Closure
    {
        $commercialUid = $this->resolveCommercialScope();
        return function ($q) use ($request, $commercialUid) {
            if ($commercialUid !== null) {
                $q->forCommercialUser($commercialUid);
            }
            if ($cid = $request->integer('client_id')) {
                $q->where('client_id', $cid);
            }
            if ($iid = $request->integer('invoice_id')) {
                $q->where('invoice_id', $iid);
            }
            if ($canal = $request->string('canal')->toString()) {
                $q->where('canal', $canal);
            }
            if ($outcome = $request->string('outcome')->toString()) {
                if ($outcome === '__empty') {
                    $q->whereNull('outcome');
                } else {
                    $q->where('outcome', $outcome);
                }
            }
            if ($from = $request->date('from')) {
                $q->where('relance_date', '>=', $from);
            }
            if ($to = $request->date('to')) {
                $q->where('relance_date', '<=', $to->endOfDay());
            }
        };
    }

    public function relances(Request $request)
    {
        $commercialUid = $this->resolveCommercialScope();
        $applyFilters = $this->applyRelanceFiltersFromRequest($request);

        // 2026-06-18 (refonte historique) — agrégation par CLIENT :
        // une ligne par client (au lieu d'une par relance), avec :
        //   - la dernière relance (date + canal + résultat + facture)
        //   - le nombre total de relances du client (sur le filtre actif)
        //   - la dette actuelle du client (snapshot live)
        // Le détail d'une ligne ouvre le drawer qui liste TOUTES les relances
        // du client dans l'ordre chronologique descendant.
        //
        // Approche pragmatique : on charge jusqu'à 2000 relances filtrées,
        // puis groupBy en PHP. À 30 clients à relancer avec ~10 relances
        // chacun = 300 lignes, on est très loin du plafond. Si la régie
        // dépasse 1000 clients actifs un jour, on rebasculera sur une
        // sous-query SQL avec MAX(relance_date) + COUNT.
        $query = Relance::query()->with(['client:id,name,phone', 'invoice:id,reference', 'user:id,name']);
        $applyFilters($query);
        $allFiltered = $query->orderByDesc('relance_date')->orderByDesc('id')->take(2000)->get();

        $byClient = $allFiltered->groupBy('client_id')->map(function ($group) {
            $last = $group->first(); // déjà triées DESC
            return [
                'client_id'    => $last->client_id,
                'client'       => $last->client,
                'count'        => $group->count(),
                'last_relance' => $last,
                'has_invoice'  => $group->whereNotNull('invoice_id')->count(),
            ];
        })->values();

        // Dette actuelle par client (snapshot live) — on réutilise
        // creancesQuery() (public wrapper de openInvoicesQuery qui est
        // protected) puis on agrège côté PHP avec remainingAmount() qui
        // n'est pas une colonne SQL. creancesQuery précharge payments
        // ce qui évite le N+1 sur remainingAmount.
        $clientIds = $byClient->pluck('client_id')->all();
        $duByClient = [];
        if (!empty($clientIds)) {
            $invoices = $this->svc->creancesQuery($commercialUid)
                ->whereIn('client_id', $clientIds)
                ->get();
            foreach ($invoices as $inv) {
                $cid = $inv->client_id;
                $rem = $inv->remainingAmount();
                if ($rem <= 0) continue;
                if (!isset($duByClient[$cid])) {
                    $duByClient[$cid] = ['total_du' => 0.0, 'factures_open' => 0];
                }
                $duByClient[$cid]['total_du']      += $rem;
                $duByClient[$cid]['factures_open'] += 1;
            }
        }
        // Brouillons en attente par client — hotfix 2026-06-22 : la patronne
        // voyait "✓ soldé" sur un client qui venait d'avoir une nouvelle
        // facture en BROUILLON. Techniquement le brouillon n'est pas une
        // dette (pas envoyée au client) mais l'UX doit le signaler pour
        // éviter la confusion ("j'ai créé une facture, pourquoi soldé ?").
        $brouillonsByClient = [];
        if (!empty($clientIds)) {
            $drafts = \App\Models\Invoice::query()
                ->where('status', 'brouillon')
                ->whereNull('credit_note_for_id')
                ->whereIn('client_id', $clientIds)
                ->when($commercialUid !== null, fn($q) => $q->forCommercialUser($commercialUid))
                // Hotfix 2026-06-22 : colonne réelle 'total_a_payer' (cf.
                // Invoice::$fillable). 'total_amount' n'existe pas → 1054
                // sur /admin/finance/relances.
                ->get(['id', 'client_id', 'total_a_payer']);
            foreach ($drafts as $d) {
                $cid = $d->client_id;
                if (!isset($brouillonsByClient[$cid])) {
                    $brouillonsByClient[$cid] = ['brouillons_count' => 0, 'brouillons_total' => 0.0];
                }
                $brouillonsByClient[$cid]['brouillons_count'] += 1;
                $brouillonsByClient[$cid]['brouillons_total'] += (float) ($d->total_a_payer ?? 0);
            }
        }
        $byClient = $byClient->map(function ($row) use ($duByClient, $brouillonsByClient) {
            $row['total_du']         = $duByClient[$row['client_id']]['total_du']      ?? 0.0;
            $row['factures_open']    = $duByClient[$row['client_id']]['factures_open'] ?? 0;
            $row['brouillons_count'] = $brouillonsByClient[$row['client_id']]['brouillons_count'] ?? 0;
            $row['brouillons_total'] = $brouillonsByClient[$row['client_id']]['brouillons_total'] ?? 0.0;
            return $row;
        })->sortByDesc(fn($r) => $r['last_relance']->relance_date->timestamp)->values();

        // Pagination manuelle : 30 clients par page (pas 30 relances).
        $page    = max(1, (int) $request->input('page', 1));
        $perPage = 30;
        $total   = $byClient->count();
        $clientsPage = $byClient->slice(($page - 1) * $perPage, $perPage)->values();
        $relances = new \Illuminate\Pagination\LengthAwarePaginator(
            $clientsPage, $total, $perPage, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // ── KPI cards : total, ce mois, à relancer (outcome=a_relancer)
        $kpiBase = fn() => Relance::query()->tap($applyFilters);
        $kpis = [
            'total'          => (clone $kpiBase())->count(),
            'ce_mois'        => (clone $kpiBase())->whereYear('relance_date', now()->year)
                                                  ->whereMonth('relance_date', now()->month)->count(),
            'a_relancer'     => (clone $kpiBase())->where('outcome', 'a_relancer')->count(),
            'promesses'      => (clone $kpiBase())->where('outcome', 'promesse_paiement')->count(),
        ];

        // ── Stats par canal + par résultat (outcome) sur le périmètre filtré
        $byCanal = (clone $kpiBase())
            ->selectRaw('canal, COUNT(*) as c')
            ->groupBy('canal')
            ->pluck('c', 'canal')
            ->toArray();

        $byOutcome = (clone $kpiBase())
            ->selectRaw('outcome, COUNT(*) as c')
            ->groupBy('outcome')
            ->pluck('c', 'outcome')
            ->toArray();

        // ── "Relances à venir" : on liste les clients dont la dernière
        // relance a outcome='a_relancer' OU 'promesse_paiement', triés par
        // date de la dernière relance ASC (les plus anciennes en premier).
        $aVenir = collect();
        if (!$request->hasAny(['client_id', 'invoice_id'])) {
            $lastByClient = Relance::query()
                ->when($commercialUid !== null, fn($q) => $q->forCommercialUser($commercialUid))
                ->orderByDesc('relance_date')
                ->orderByDesc('id')
                ->get()
                ->groupBy('client_id')
                ->map(fn($g) => $g->first());
            $aVenir = $lastByClient
                ->filter(fn($r) => in_array($r->outcome, ['a_relancer', 'promesse_paiement'], true))
                ->take(50);
            $aVenir->load(['client:id,name', 'user:id,name']);
        }

        return view('admin.finance.relances', [
            'relances'    => $relances,
            'clientsList' => Client::orderBy('name')->get(['id', 'name']),
            'canaux'      => Relance::CANAUX,
            'kpis'        => $kpis,
            'byCanal'     => $byCanal,
            'byOutcome'   => $byOutcome,
            'aVenir'      => $aVenir,
        ]);
    }

    /**
     * Drawer "Voir le détail" d'une relance (Bloc 3 — Famille D, 2026-06-18).
     * Endpoint AJAX consommé par le panneau latéral sur :
     *   - /admin/finance/relances (page recouvrement complète)
     *   - /admin/clients/{client} (onglet Relances)
     *
     * Retourne du HTML partiel (pas du JSON) pour rester sobre — le drawer
     * fait juste un fetch + innerHTML, pas de re-rendu côté JS.
     */
    public function relanceDetail(\App\Models\Relance $relance)
    {
        // 2026-06-18 (refonte historique) — le drawer affiche maintenant
        // TOUTES les relances du client dont fait partie la relance
        // demandée, dans l'ordre chronologique descendant. La relance
        // demandée est mise en évidence (`is-current`) dans la vue.
        // En-tête : dette actuelle du client + compteur.
        $relance->load([
            'client:id,name,phone,email',
            'invoice:id,reference,total_a_payer,status,issued_at',
            'schedule:id,invoice_id,due_date,amount,paid_at',
            'user:id,name',
        ]);

        $clientId = $relance->client_id;
        $clientRelances = \App\Models\Relance::query()
            ->where('client_id', $clientId)
            ->with([
                'invoice:id,reference,total_a_payer,status,issued_at',
                'schedule:id,invoice_id,due_date,amount,paid_at',
                'user:id,name',
            ])
            ->orderByDesc('relance_date')
            ->orderByDesc('id')
            ->get();

        // Dette actuelle du client (snapshot live). Voir relances() :
        // creancesQuery() = wrapper public d'openInvoicesQuery().
        $commercialUid = $this->resolveCommercialScope();
        $totalDu = 0.0;
        $facturesOpen = 0;
        $invoices = $this->svc->creancesQuery($commercialUid)
            ->where('client_id', $clientId)
            ->get();
        foreach ($invoices as $inv) {
            $rem = $inv->remainingAmount();
            if ($rem > 0) {
                $totalDu      += $rem;
                $facturesOpen += 1;
            }
        }

        return view('admin.finance.partials.relance-detail', [
            'relance'        => $relance, // mise en évidence
            'clientRelances' => $clientRelances,
            'totalDu'        => $totalDu,
            'facturesOpen'   => $facturesOpen,
        ]);
    }

    /**
     * Construit la ligne récap des filtres actifs (pour bandeau Excel/PDF).
     * Cohérent avec le formulaire de la vue Historique.
     */
    private function buildRelanceFilterRecap(Request $request): string
    {
        $parts = [];
        if ($from = $request->date('from')) $parts[] = 'Du ' . $from->format('d/m/Y');
        if ($to   = $request->date('to'))   $parts[] = 'au ' . $to->format('d/m/Y');
        if ($cid  = $request->integer('client_id')) {
            $name = \App\Models\Client::whereKey($cid)->value('name');
            if ($name) $parts[] = 'Client : ' . $name;
        }
        if ($canal = $request->string('canal')->toString()) {
            $parts[] = 'Canal : ' . (\App\Models\Relance::CANAUX[$canal] ?? $canal);
        }
        if ($outcome = $request->string('outcome')->toString()) {
            $labels = [
                'promesse_paiement' => 'Promesse paiement',
                'paiement_recu'     => 'Paiement reçu',
                'a_relancer'        => 'À relancer',
                'sans_reponse'      => 'Sans réponse',
                'desaccord'         => 'Désaccord',
                'autre'             => 'Autre',
                '__empty'           => 'Sans résultat',
            ];
            $parts[] = 'Résultat : ' . ($labels[$outcome] ?? $outcome);
        }
        return $parts ? implode(' · ', $parts) : 'Toutes les relances (aucun filtre actif)';
    }

    /**
     * Charge les relances filtrées (collection complète, sans pagination)
     * avec relations utiles pour l'export — partagé entre Excel et PDF.
     */
    private function loadRelancesForExport(Request $request): \Illuminate\Support\Collection
    {
        $applyFilters = $this->applyRelanceFiltersFromRequest($request);
        return \App\Models\Relance::query()
            ->with(['client:id,name,phone', 'invoice:id,reference', 'user:id,name'])
            ->tap($applyFilters)
            ->orderBy('client_id')
            ->orderByDesc('relance_date')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Export Excel — 1 ligne par relance avec tous les champs (motif/note,
     * suite à donner, canal, résultat, auteur, facture). Respecte les filtres.
     */
    public function exportRelancesExcel(Request $request)
    {
        $relances = $this->loadRelancesForExport($request);
        $recapLine = $this->buildRelanceFilterRecap($request);

        $filename = 'relances-' . now()->format('Ymd_His') . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\RelancesExport($relances, $recapLine),
            $filename
        );
    }

    /**
     * Export PDF — synthèse groupée par client (1 section par client avec
     * historique chronologique). Respecte les filtres.
     */
    public function exportRelancesPdf(Request $request)
    {
        $relances = $this->loadRelancesForExport($request);
        $byClient = $relances->groupBy('client_id');
        $recapLine = $this->buildRelanceFilterRecap($request);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.finance.relances-pdf', [
            'byClient'        => $byClient,
            'totalRelances'   => $relances->count(),
            'filterRecapLine' => $recapLine,
            'user'            => $request->user(),
            'operatorName'    => 'CIBLE CI',
        ])->setPaper('a4', 'landscape');

        return $pdf->download('relances-' . now()->format('Ymd_His') . '.pdf');
    }

    public function storeRelance(Request $request, \App\Services\ReminderService $reminders)
    {
        $data = $request->validate([
            'client_id'    => 'required|exists:clients,id',
            'invoice_id'   => 'nullable|exists:invoices,id',
            'schedule_id'  => 'nullable|exists:invoice_schedules,id',
            'relance_date' => 'required|date',
            'canal'        => 'required|in:' . implode(',', \App\Services\ReminderService::CANALS),
            'note'         => 'required|string|max:2000',
            'outcome'      => 'nullable|in:' . implode(',', \App\Services\ReminderService::OUTCOMES),
            'suite_donnee' => 'nullable|string|max:200',
        ]);

        // RBAC : un commercial ne peut enregistrer une relance que sur une
        // de SES factures (ou sur ses clients si invoice_id null).
        if (auth()->user()?->role?->value === 'commercial' && !empty($data['invoice_id'])) {
            $inv = Invoice::find($data['invoice_id']);
            if (!$inv || !Invoice::query()->forCommercialUser(auth()->id())->whereKey($inv->id)->exists()) {
                abort(403, 'Cette facture n\'est pas dans votre périmètre.');
            }
        }

        try {
            $reminders->register($data);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', '🚫 ' . $e->getMessage());
        }

        return redirect()->back()->with('success', '✅ Relance enregistrée.');
    }

    // ══════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════

    /**
     * Parse la période depuis la query string. Formats supportés :
     *   ?period=today | this_week | this_month | this_quarter | this_year | last_30 | last_90
     *   ?from=YYYY-MM-DD&to=YYYY-MM-DD (intervalle perso)
     *
     * Défaut : 30 derniers jours.
     */
    protected function parsePeriod(Request $request): array
    {
        $from = $request->date('from');
        $to   = $request->date('to');
        if ($from && $to) {
            return [Carbon::parse($from), Carbon::parse($to)];
        }

        $period = $request->string('period')->toString();
        $today  = Carbon::today();
        return match ($period) {
            'today'        => [$today->copy(),                          $today->copy()],
            'this_week'    => [$today->copy()->startOfWeek(),           $today->copy()->endOfWeek()],
            'this_month'   => [$today->copy()->startOfMonth(),          $today->copy()->endOfMonth()],
            'this_quarter' => [$today->copy()->firstOfQuarter(),        $today->copy()->lastOfQuarter()],
            'this_year'    => [$today->copy()->startOfYear(),           $today->copy()->endOfYear()],
            'last_90'      => [$today->copy()->subDays(89),             $today->copy()],
            default        => [$today->copy()->subDays(29),             $today->copy()],
        };
    }

    /**
     * Renvoie l'ID du commercial à scoper, ou null si admin (= vue globale).
     * MP/Technique sont bloqués au niveau middleware → on n'arrive pas ici.
     */
    protected function resolveCommercialScope(): ?int
    {
        $user = auth()->user();
        if ($user?->role?->value === 'commercial') {
            return (int) $user->id;
        }
        return null; // admin → pas de scope
    }
}

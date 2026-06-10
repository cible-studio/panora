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
        $creances          = $this->svc->creancesQuery($commercialUid)
                                  ->orderBy('issued_at')
                                  ->limit(200)
                                  ->get();

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
    // RELANCES
    // ══════════════════════════════════════════════════════════════════

    public function relances(Request $request)
    {
        $commercialUid = $this->resolveCommercialScope();
        $query = Relance::query()->with(['client:id,name', 'invoice:id,reference', 'user:id,name']);

        if ($commercialUid !== null) {
            $query->forCommercialUser($commercialUid);
        }
        if ($cid = $request->integer('client_id')) {
            $query->where('client_id', $cid);
        }
        if ($iid = $request->integer('invoice_id')) {
            $query->where('invoice_id', $iid);
        }
        if ($canal = $request->string('canal')->toString()) {
            $query->where('canal', $canal);
        }

        $relances = $query->orderByDesc('relance_date')->orderByDesc('id')->paginate(30);

        return view('admin.finance.relances', [
            'relances'    => $relances,
            'clientsList' => Client::orderBy('name')->get(['id', 'name']),
            'canaux'      => Relance::CANAUX,
        ]);
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

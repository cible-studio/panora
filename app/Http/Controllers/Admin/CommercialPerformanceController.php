<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CommercialPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Pages Performance Commerciale — M1.
 *
 * Routes :
 *   GET /admin/performance/commerciaux         → leaderboard (admin/MP)
 *   GET /admin/performance/commerciaux/me      → alias auto-bind sur auth user
 *   GET /admin/performance/commerciaux/{user}  → drill-down 1 commercial
 *
 * RBAC :
 *   admin/mediaplanner → toutes les vues, n'importe quel commercial
 *   commercial         → uniquement sa propre fiche (forçage dans le service)
 *   technique          → bloqué (403)
 */
class CommercialPerformanceController extends Controller
{
    public function __construct(protected CommercialPerformanceService $perf) {}

    /** GET /admin/performance/commerciaux — leaderboard direction. */
    public function index(Request $request)
    {
        // Commercial → redirige automatiquement vers sa propre fiche.
        $currentRole = $request->user()->role?->value;
        if ($currentRole === 'commercial') {
            return redirect()->route('admin.performance.commercial.show', $request->user());
        }
        if (!in_array($currentRole, ['admin', 'mediaplanner'], true)) {
            abort(403, 'Accès réservé à l\'administration.');
        }

        [$from, $to] = $this->resolvePeriod($request);
        $leaderboard = $this->perf->leaderboard($from, $to);
        // Top commercial par secteur d'activité — vue "qui domine quoi"
        $topBySector = $this->perf->topCommercialBySector($from, $to);
        // Courbe globale équipe — évolution CA sur 12 mois
        $globalTrend = $this->perf->globalMonthlyTrend(12);

        return view('admin.performance.commerciaux.index', [
            'leaderboard' => $leaderboard,
            'topBySector' => $topBySector,
            'globalTrend' => $globalTrend,
            'from'        => $from,
            'to'          => $to,
            'preset'      => $request->input('preset'),
        ]);
    }

    /** GET /admin/performance/commerciaux/me — alias self. */
    public function me(Request $request)
    {
        return redirect()->route('admin.performance.commercial.show', $request->user());
    }

    /** GET /admin/performance/commerciaux/{user} — drill-down 1 commercial. */
    public function show(Request $request, User $user)
    {
        // RBAC : commercial ne voit que soi-même
        $effectiveId = $this->perf->resolveCommercialIdForCurrentUser($user->id, $request->user());
        if ($effectiveId === null) {
            abort(403, 'Accès refusé.');
        }
        if ($effectiveId !== $user->id) {
            // Un commercial a tenté de voir un autre commercial → on redirige
            // silencieusement vers sa propre fiche.
            return redirect()->route('admin.performance.commercial.show', $request->user());
        }
        // Cible doit être un commercial actif
        if ($user->role?->value !== 'commercial') {
            abort(404, 'Cet utilisateur n\'est pas un commercial.');
        }

        [$from, $to] = $this->resolvePeriod($request);

        $kpis            = $this->perf->kpis($user->id, $from, $to);
        $bySector        = $this->perf->bySector($user->id, $from, $to);
        $diversification = $this->perf->diversificationScore($user->id, $from, $to);
        $topClient       = $this->perf->topClientShare($user->id, $from, $to);
        $monthlyTrend    = $this->perf->monthlyTrend($user->id, 12);
        $yearComp        = $this->perf->yearComparison($user->id, now()->year);
        $campaigns       = $this->perf->campaignsList($user->id, $from, $to, 15);

        return view('admin.performance.commerciaux.show', compact(
            'user', 'kpis', 'bySector', 'diversification', 'topClient',
            'monthlyTrend', 'yearComp', 'campaigns', 'from', 'to'
        ));
    }

    /** Période : preset OU dates custom from/to. Défaut : année courante. */
    protected function resolvePeriod(Request $request): array
    {
        // 1) Range personnalisé : si l'utilisateur a saisi au moins UNE date
        //    (from OU to), on respecte sa saisie sans exiger les 2 champs.
        //    Avant : il fallait remplir les 2 dates sinon le preset par
        //    défaut écrasait tout, ce qui donnait l'impression que le
        //    filtre date "ne marchait pas".
        $hasFrom = $request->filled('from');
        $hasTo   = $request->filled('to');
        if ($hasFrom || $hasTo) {
            try {
                $from = $hasFrom
                    ? Carbon::parse($request->input('from'))->startOfDay()
                    : Carbon::parse($request->input('to'))->copy()->subYear()->startOfDay();
                $to = $hasTo
                    ? Carbon::parse($request->input('to'))->endOfDay()
                    : Carbon::now()->endOfDay();
                // Sécurité : si from > to (saisie inversée), on swap.
                if ($from->gt($to)) {
                    [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
                }
                return [$from, $to];
            } catch (\Throwable) { /* fallback preset ↓ */ }
        }

        // 2) Pas de date saisie → preset
        return match ($request->input('preset')) {
            'month'   => [now()->startOfMonth(), now()->endOfMonth()],
            'quarter' => [now()->firstOfQuarter(), now()->lastOfQuarter()],
            'all'     => [Carbon::create(2020, 1, 1), now()],
            default   => [now()->startOfYear(), now()->endOfYear()],
        };
    }
}

<?php
// app/Http/Controllers/Admin/AlertController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\AlertService;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function __construct(protected AlertService $alertService) {}

    // ══════════════════════════════════════════════════════════════════
    // INDEX — page principale.
    //
    // Comportement clé : à l'ouverture, on marque TOUTES les alertes non
    // lues comme lues atomiquement (1 UPDATE), pour que le badge cloche
    // tombe à 0 instantanément. La liste reste visible (les alertes ne
    // disparaissent pas, juste leur état "lu" change).
    // ══════════════════════════════════════════════════════════════════
    public function index(Request $request)
    {
        // Mark-all-as-read seulement sur le rendu HTML initial (pas sur
        // chaque AJAX paginate / filtre — sinon on tape la BD pour rien).
        $markedCount = 0;
        if (!$request->ajax() && !$request->boolean('ajax')) {
            $markedCount = $this->alertService->markAllAsRead();
        }

        // Liste paginée avec filtres
        $query = Alert::active()->latest('triggered_at');

        if ($request->filled('niveau')) {
            $query->ofNiveau($request->niveau);
        }
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }
        if ($request->boolean('non_lues')) {
            // Subtilité : juste après le mark-all-as-read, "non_lues" = 0
            // résultat. C'est cohérent (les alertes existantes deviennent
            // historiques). Ce filtre devient utile pour les nouvelles
            // alertes qui arriveront ensuite via les triggers.
            $query->where('is_read', false);
        }

        $alertes = $query->paginate(25)->withQueryString();

        // KPI : compteurs sur toutes les alertes actives selon les filtres.
        // ⚠️ Pas `unreadSummary()` ici — ça vient juste d'être mis à 0 par
        // markAllAsRead(). On utilise activeSummary() qui reste pertinent
        // (lues ET non lues) pour que l'admin voie l'activité réelle.
        $summary = $this->alertService->activeSummary([
            'type'     => $request->input('type'),
            'niveau'   => $request->input('niveau'),
            'non_lues' => $request->boolean('non_lues'),
        ]);

        $types = collect(AlertService::TYPES)
            ->map(fn ($meta, $code) => ['code' => $code, ...$meta])
            ->values();

        // Réponse AJAX (filtre/pagination dynamique)
        if ($request->ajax() || $request->boolean('ajax')) {
            $html = view('admin.alertes.partials.alerts-list', [
                'alertes' => $alertes,
            ])->render();

            return response()->json([
                'html'    => $html,
                'total'   => $alertes->total(),
                'summary' => $summary, // pour rafraîchir les KPI sans reload
            ]);
        }

        return view('admin.alertes.index', [
            'alertes' => $alertes,
            'summary' => $summary,
            'types'   => $types,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // ACTIONS UNITAIRES
    // ══════════════════════════════════════════════════════════════════

    public function markRead(Alert $alert)
    {
        $alert->markRead();
        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true, 'unread_count' => $this->alertService->unreadCount()])
            : back()->with('success', 'Alerte marquée comme lue.');
    }

    public function markAllRead()
    {
        $count = $this->alertService->markAllAsRead();

        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true, 'marked' => $count, 'unread_count' => 0])
            : back()->with('success', "{$count} alerte(s) marquée(s) comme lues.");
    }

    public function destroy(Alert $alert)
    {
        $alert->delete();
        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true, 'unread_count' => $this->alertService->unreadCount()])
            : back()->with('success', 'Alerte supprimée.');
    }

    /**
     * Archive l'alerte (soft hide) — alternative au delete dur.
     * L'alerte reste en BD mais n'apparaît plus dans la liste active.
     */
    public function archive(Alert $alert)
    {
        $alert->archive();
        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true])
            : back()->with('success', 'Alerte archivée.');
    }

    /**
     * Purge toutes les alertes lues (vidage rapide). Garde les non lues.
     */
    public function clearRead()
    {
        $count = Alert::read()->delete();
        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true, 'deleted' => $count])
            : back()->with('success', "{$count} alerte(s) lue(s) supprimée(s).");
    }

    // ══════════════════════════════════════════════════════════════════
    // API — endpoints AJAX pour le polling navigation
    // ══════════════════════════════════════════════════════════════════

    /**
     * GET /api/alerts/count → JSON léger { count, by_niveau }.
     * Utilisé par le polling badge cloche (toutes les 30s).
     */
    public function apiCount()
    {
        $summary = $this->alertService->unreadSummary();
        return response()->json([
            'count'     => $summary['total'],
            'by_niveau' => [
                'danger'  => $summary['danger'],
                'warning' => $summary['warning'],
                'info'    => $summary['info'],
            ],
        ]);
    }

    /**
     * GET /api/alerts/latest → JSON liste pour les toasts.
     * Bornée à 8 par défaut.
     */
    public function apiLatest(Request $request)
    {
        $limit  = max(1, min(20, (int) $request->input('limit', 8)));
        $alerts = $this->alertService->latest($limit)->map(fn ($a) => [
            'id'           => $a->id,
            'type'         => $a->type,
            'niveau'       => $a->niveau,
            'title'        => $a->title,
            'message'      => $a->message,
            'lien'         => $a->lien,
            'triggered_at' => $a->triggered_at?->toIso8601String(),
            'meta'         => AlertService::TYPES[$a->type] ?? AlertService::DEFAULT_META,
        ]);

        return response()->json($alerts);
    }

    /**
     * Résumé global par module/niveau (déjà existant, gardé pour rétrocompat).
     */
    public function summary()
    {
        $data = Alert::unread()
            ->selectRaw('type, niveau, COUNT(*) as count')
            ->groupBy('type', 'niveau')
            ->get();

        $result = [];
        foreach ($data as $row) {
            $result[$row->type][$row->niveau] = $row->count;
        }
        $result['_total'] = $this->alertService->unreadCount();

        return response()->json($result);
    }
}

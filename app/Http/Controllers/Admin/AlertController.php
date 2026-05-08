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

        // Filtres "neutres" appliqués au périmètre + au calcul des compteurs
        if ($request->filled('type')) {
            $query->ofType($request->type);
        }
        if ($request->boolean('non_lues')) {
            $query->where('is_read', false);
        }

        // ─── COMPTEURS KPI sur le périmètre AVANT filtre niveau ───
        // On NE passe PAS le filtre niveau à activeSummary — sinon cliquer
        // "Danger" ferait tomber les counts Avertissements/Informations à 0.
        // Chaque carte garde sa vraie valeur dans le périmètre (type/non_lues).
        $summary = $this->alertService->activeSummary([
            'type'     => $request->input('type'),
            'non_lues' => $request->boolean('non_lues'),
        ]);

        // Filtre niveau appliqué APRÈS le calcul des compteurs
        if ($request->filled('niveau')) {
            $query->ofNiveau($request->niveau);
        }

        $alertes = $query->paginate(25)->withQueryString();

        // Total de la carte "Total alertes" = ce qui est réellement affiché
        // (avec tous les filtres y compris niveau). Permet à l'utilisateur
        // de voir le périmètre courant sans que les autres cartes bougent.
        $summary['total'] = $alertes->total();

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

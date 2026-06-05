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

    /**
     * Scope RBAC : admin voit tout, MP/Technique voient leurs alertes
     * ciblées + les broadcasts pertinents à leur rôle, commercial reste
     * strict. Mirroirise AlertService::scopeCurrentUser() — DOIT rester
     * identique pour cohérence.
     */
    protected function scopeCurrentUser($query)
    {
        $user = auth()->user();
        if (!$user) return $query->whereRaw('1 = 0');
        if ($user->role?->value === 'admin') return $query;

        $uid       = (int) $user->id;
        $broadcast = \App\Services\AlertService::broadcastTypesForRole($user->role->value);

        return $query->where(function ($q) use ($uid, $broadcast) {
            $q->where('user_id', $uid);
            if (!empty($broadcast)) {
                $q->orWhere(function ($qq) use ($broadcast) {
                    $qq->whereNull('user_id')->whereIn('type', $broadcast);
                });
            }
        });
    }

    /**
     * Vérifie qu'une alerte peut être manipulée par l'utilisateur courant :
     *   - Admin              : oui sur tout
     *   - Alerte ciblée      : OK si user_id == auth()->id()
     *   - Broadcast (NULL)   : OK si le type est dans la liste de son rôle
     *
     * Renvoie 403 sinon — empêche un commercial d'agir sur l'alerte d'un autre,
     * mais autorise MP/Technique à marquer comme lue / archiver les broadcasts
     * système qu'ils sont censés voir.
     */
    protected function authorizeAlert(Alert $alert): void
    {
        $user = auth()->user();
        if (!$user) abort(403);
        if ($user->role?->value === 'admin') return;

        // Alerte ciblée à moi → OK
        if ($alert->user_id !== null && (int) $alert->user_id === (int) $user->id) {
            return;
        }

        // Alerte broadcast → OK si pertinent pour mon rôle
        if ($alert->user_id === null) {
            $broadcast = \App\Services\AlertService::broadcastTypesForRole($user->role->value);
            if (in_array($alert->type, $broadcast, true)) {
                return;
            }
        }

        abort(403, "Cette alerte ne vous appartient pas.");
    }

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

        // Liste paginée avec filtres — scope RBAC : non-admin ne voit que ses alertes
        $query = $this->scopeCurrentUser(Alert::active())->latest('triggered_at');

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
        $this->authorizeAlert($alert);
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
        $this->authorizeAlert($alert);
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
        $this->authorizeAlert($alert);
        $alert->archive();
        return request()->wantsJson() || request()->ajax()
            ? response()->json(['success' => true])
            : back()->with('success', 'Alerte archivée.');
    }

    /**
     * Action groupée — mark-read, archive, ou delete pour N alertes.
     * Idempotent : skip silencieusement les items déjà dans l'état cible.
     */
    public function bulkAction(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:mark-read,archive,delete',
            'ids'    => 'required|array|min:1|max:500',
            'ids.*'  => 'integer|exists:alerts,id',
        ]);

        // RBAC : non-admin ne peut agir que sur SES alertes
        $alerts = $this->scopeCurrentUser(Alert::whereIn('id', $data['ids']))->get();
        $applied = 0;

        foreach ($alerts as $a) {
            try {
                match ($data['action']) {
                    'mark-read' => $a->markRead(),
                    'archive'   => $a->archive(),
                    'delete'    => $a->delete(),
                };
                $applied++;
            } catch (\Throwable) {
                // Skip silencieux — l'alerte est déjà dans l'état cible
                // ou pré-supprimée. Pas critique.
            }
        }

        $verbs = ['mark-read' => 'marquée(s) comme lue(s)', 'archive' => 'archivée(s)', 'delete' => 'supprimée(s)'];
        return $request->wantsJson() || $request->ajax()
            ? response()->json(['success' => true, 'applied' => $applied])
            : redirect()->route('admin.alerts.index')->with('success', "{$applied} alerte(s) " . $verbs[$data['action']] . '.');
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

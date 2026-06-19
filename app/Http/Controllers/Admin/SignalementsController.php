<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PanelStatus;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Panel;
use App\Models\PoseTaskAction;
use App\Services\MaintenanceNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Workflow "Signalements terrain" :
 *
 *   tech signale problème (panneau cassé / accès / adresse / autre)
 *       └─ PoseTaskAction::log(action='problem_reported', payload[type,note])
 *           └─ vu ici par admin
 *               ├─ "Mettre en maintenance" → statut panneau MAINTENANCE
 *               │                            + Maintenance créée + résolution liée
 *               └─ "Marquer traité"          → juste résolu (faux signalement…)
 *
 * Le badge sidebar compte les pose_task_actions où
 * action='problem_reported' AND resolved_at IS NULL.
 */
class SignalementsController extends Controller
{
    /**
     * Mapping problem_type → libellé + type_panne maintenance.
     *
     * Source unique = App\Enums\DelayReason (9 motifs depuis mission M3 SLA enrichi).
     * On garde un accesseur statique le temps qu'il n'y ait plus aucun usage interne
     * de PROBLEM_MAP. À supprimer une fois les vues migrées vers DelayReason.
     */
    private static function problemMap(): array
    {
        $map = [];
        foreach (\App\Enums\DelayReason::cases() as $m) {
            $map[$m->value] = ['label' => $m->label(), 'type_panne' => $m->panneType()];
        }
        return $map;
    }

    public function index(Request $request)
    {
        // SM2-fusion (2026-06-19) : onglet 'analyse' fusionne l'ancienne page
        // /admin/sla/retards. Le paramètre 'view' peut être :
        //   - 'todo' (défaut) : liste pending
        //   - 'all'            : liste complète
        //   - 'resolved'       : liste résolus
        //   - 'analyse'        : KPIs analytiques + motif dominant + croisements
        $view = $request->input('view', $request->input('status', 'todo'));
        if (!in_array($view, ['todo', 'all', 'resolved', 'analyse'], true)) {
            $view = 'todo';
        }
        // Backward-compat : les 3 premiers status sont mappés
        $status = match ($view) {
            'todo'     => 'pending',
            'all'      => 'all',
            'resolved' => 'resolved',
            default    => 'pending', // pour 'analyse' on n'utilise pas $status
        };

        $query = PoseTaskAction::query()
            ->where('action', 'problem_reported')
            ->with([
                'task:id,panel_id,campaign_id,assigned_user_id',
                'task.panel:id,reference,name,commune_id,adresse,quartier',
                'task.panel.commune:id,name',
                'task.panel.photos:id,panel_id,path,ordre',
                'task.campaign:id,name,client_id',
                'task.campaign.client:id,name',
                'task.technicien:id,name',
                'resolvedBy:id,name',
                'maintenance:id,statut,date_signalement',
            ]);

        if ($status === 'pending')  $query->whereNull('resolved_at');
        if ($status === 'resolved') $query->whereNotNull('resolved_at');

        // Filtre type
        if ($request->filled('type')) {
            $query->whereJsonContains('payload->type', $request->type);
        }

        $signalements = $query->latest('created_at')->paginate(30)->withQueryString();

        // Stats KPI
        $kpi = [
            'pending'     => PoseTaskAction::where('action', 'problem_reported')->whereNull('resolved_at')->count(),
            'maintenance' => PoseTaskAction::where('action', 'problem_reported')->where('resolution_action', 'maintenance')->count(),
            'dismissed'   => PoseTaskAction::where('action', 'problem_reported')->where('resolution_action', 'dismissed')->count(),
            'total'       => PoseTaskAction::where('action', 'problem_reported')->count(),
        ];

        $problemLabels = collect(self::problemMap())->map(fn($v) => $v['label']);

        // ─── Données complémentaires pour l'onglet "Analyse" ──────────
        // On les charge uniquement si view=analyse pour ne pas alourdir
        // les autres onglets.
        $analyse = null;
        if ($view === 'analyse') {
            $analyse = $this->buildAnalysePayload($request);
        }

        return view('admin.signalements.index', compact(
            'signalements', 'status', 'view', 'kpi', 'problemLabels', 'analyse'
        ));
    }

    /**
     * Compose la data nécessaire au rendu de l'onglet Analyse.
     * Reprend la logique de SlaDelaysController::index() sans dupliquer :
     * on appelle le DelayReasonsService et on récupère stats + filtres.
     */
    protected function buildAnalysePayload(Request $request): array
    {
        $service = app(\App\Services\DelayReasonsService::class);

        $to   = $request->filled('to')   ? \Carbon\Carbon::parse($request->input('to'))->endOfDay()
                                         : now();
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->input('from'))->startOfDay()
                                         : now()->subDays(89);

        $filters = array_filter([
            'commune_id'  => $request->input('commune_id'),
            'client_id'   => $request->input('client_id'),
            'zone'        => in_array($request->input('zone'), ['abidjan','interieur'], true) ? $request->input('zone') : null,
        ]);
        $motifFilter = \App\Enums\DelayReason::tryFrom((string) $request->input('motif'));

        $stats = $service->stats($from, $to, $filters);

        return [
            'stats'       => $stats,
            'from'        => $from,
            'to'          => $to,
            'filters'     => $filters,
            'motifFilter' => $motifFilter,
            'allCommunes' => \App\Models\Commune::orderBy('name')->get(['id','name','city']),
            'allClients'  => \App\Models\Client::orderBy('name')->get(['id','name']),
        ];
    }

    /**
     * POST /admin/signalements/{action}/maintenance
     * Passe le panneau en MAINTENANCE + crée la Maintenance + lie au signalement.
     */
    public function resolveToMaintenance(Request $request, PoseTaskAction $action, MaintenanceNotifier $notifier)
    {
        $this->ensureActiveSignal($action);

        $request->validate([
            'priorite'           => 'nullable|in:basse,normale,haute,urgente',
            'description'        => 'nullable|string|max:1000',
            'duree_prevue_jours' => 'required|integer|min:1|max:365',
        ], [
            'duree_prevue_jours.required' => 'Indique la durée estimée pour informer le client.',
            'duree_prevue_jours.min'      => 'La durée doit être d\'au moins 1 jour.',
            'duree_prevue_jours.max'      => 'Durée maximale : 365 jours.',
        ]);

        $type      = $action->payload['type'] ?? 'autre';
        $note      = $action->payload['note'] ?? null;
        $mapping   = self::problemMap()[$type] ?? self::problemMap()['autre'];

        $panel = $action->task?->panel;
        if (!$panel) {
            return back()->with('error', 'Panneau introuvable — signalement orphelin.');
        }

        $description = trim(
            $mapping['label']
            . ($note ? "\n\n— Note tech : " . $note : '')
            . "\n\n— Signalé depuis le terrain par " . ($action->actor ?? 'technicien')
            . " le " . $action->created_at->format('d/m/Y H:i')
        );

        $durationDays = (int) $request->input('duree_prevue_jours');
        $dateFinPrevue = now()->startOfDay()->addDays($durationDays);

        $createdMaintenance = null;

        DB::transaction(function () use ($action, $panel, $mapping, $description, $request, $durationDays, $dateFinPrevue, &$createdMaintenance) {
            // 1. Création de la Maintenance liée
            $maintenance = Maintenance::create([
                'panel_id'           => $panel->id,
                'signale_par'        => auth()->id(),
                'type_panne'         => $mapping['type_panne'],
                'priorite'           => $request->input('priorite', 'normale'),
                'statut'             => 'signale',
                'date_signalement'   => now()->toDateString(),
                'duree_prevue_jours' => $durationDays,
                'date_fin_prevue'    => $dateFinPrevue->toDateString(),
                'description'        => $request->input('description') ?: $description,
            ]);

            // 2. Bascule statut panneau en MAINTENANCE (sort de dispo)
            $panel->update(['status' => PanelStatus::MAINTENANCE->value]);

            // 3. Marque le signalement résolu et lié
            $action->update([
                'resolved_at'       => now(),
                'resolved_by'       => auth()->id(),
                'resolution_action' => 'maintenance',
                'maintenance_id'    => $maintenance->id,
            ]);

            Log::info('signalement.resolved.maintenance', [
                'action_id'      => $action->id,
                'panel_id'       => $panel->id,
                'maintenance_id' => $maintenance->id,
                'admin_id'       => auth()->id(),
            ]);

            $createdMaintenance = $maintenance;
        });

        // Invalide le cache du badge sidebar
        Cache::forget('admin.signalements.pending_count');

        // Best-effort : prévient les clients des campagnes en cours touchées
        // par ce panneau. Si la notif casse, le métier ne tombe pas.
        if ($createdMaintenance) {
            try {
                $notifier->notifyClientCampaignDown($createdMaintenance);
            } catch (\Throwable $e) {
                Log::warning('signalement.notify_client_down.failed', [
                    'maintenance_id' => $createdMaintenance->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        return back()->with('success', "Panneau {$panel->reference} mis en maintenance. Le signalement est traité, les clients ont été informés.");
    }

    /**
     * POST /admin/signalements/{action}/dismiss
     * Clôture sans toucher au panneau (faux signalement, déjà connu…).
     */
    public function dismiss(Request $request, PoseTaskAction $action)
    {
        $this->ensureActiveSignal($action);

        $action->update([
            'resolved_at'       => now(),
            'resolved_by'       => auth()->id(),
            'resolution_action' => 'dismissed',
        ]);

        Cache::forget('admin.signalements.pending_count');

        Log::info('signalement.dismissed', [
            'action_id' => $action->id,
            'admin_id'  => auth()->id(),
        ]);

        return back()->with('success', 'Signalement marqué comme traité (sans maintenance).');
    }

    private function ensureActiveSignal(PoseTaskAction $action): void
    {
        if ($action->action !== 'problem_reported') {
            abort(422, 'Cette action n\'est pas un signalement.');
        }
        if ($action->resolved_at !== null) {
            abort(422, 'Ce signalement a déjà été traité.');
        }
    }

    /**
     * GET /admin/signalements/heartbeat
     *
     * Endpoint JSON ultra-léger appelé en polling par le layout admin pour :
     *   - mettre à jour le badge sidebar "Signalements" en quasi temps réel
     *   - déclencher un toast + son quand un nouveau signal arrive pendant
     *     que l'admin est sur une autre page
     *   - sur /admin/signalements, détecter qu'il faut prepend de nouvelles
     *     cartes sans full reload
     *
     * Réponse :
     *   {
     *     pending_count: int,
     *     latest_id:     int|null,   // id du dernier signal non résolu
     *     latest_at:     iso8601,    // pour comparer côté JS
     *     recent: [                   // 3 derniers signaux pour les toasts
     *       { id, type, type_label, panel_ref, panel_name, ago, actor }
     *     ]
     *   }
     */
    public function heartbeat(Request $request)
    {
        $count = PoseTaskAction::where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->count();

        // Bypass cache : le cache du badge sidebar est ré-écrit ici pour
        // que les pages SANS polling restent à jour à leur prochain refresh.
        Cache::put('admin.signalements.pending_count', $count, 60);

        $recent = PoseTaskAction::where('action', 'problem_reported')
            ->whereNull('resolved_at')
            ->with([
                'task:id,panel_id',
                'task.panel:id,reference,name',
            ])
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'pose_task_id', 'payload', 'actor', 'created_at']);

        return response()->json([
            'pending_count' => $count,
            'latest_id'     => $recent->first()?->id,
            'latest_at'     => optional($recent->first()?->created_at)->toIso8601String(),
            'recent'        => $recent->map(function (PoseTaskAction $a) {
                $type = $a->payload['type'] ?? 'autre';
                return [
                    'id'         => $a->id,
                    'type'       => $type,
                    'type_label' => self::problemMap()[$type]['label'] ?? 'Problème',
                    'panel_ref'  => $a->task?->panel?->reference,
                    'panel_name' => $a->task?->panel?->name,
                    'actor'      => $a->actor ?? 'tech',
                    'ago'        => $a->created_at?->diffForHumans(),
                    'at'         => $a->created_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }
}

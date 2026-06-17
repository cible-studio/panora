<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DelayReason;
use App\Http\Controllers\Controller;
use App\Models\PoseTaskAction;
use App\Services\DelayReasonsService;
use Illuminate\Http\Request;

/**
 * Page admin /admin/sla/retards — analyse des motifs de retard,
 * édition a posteriori (avec audit), recurring panels.
 *
 * RBAC : admin + mediaplanner uniquement (PoseTaskActionPolicy).
 * Le commercial et le technique reçoivent un 403.
 */
class SlaDelaysController extends Controller
{
    public function __construct(protected DelayReasonsService $service) {}

    /**
     * GET /admin/sla/retards — page analytique des retards.
     * Filtres : motif, commune, période, statut (pending/all/resolved).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PoseTaskAction::class);

        // Période : par défaut 90 derniers jours, override via from/to YYYY-MM-DD.
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
        $status      = $request->input('status', 'all'); // pending|all|resolved

        $stats = $this->service->stats($from, $to, $filters);

        // Liste paginée des signalements (avec amendements affichés)
        $query = PoseTaskAction::query()
            ->where('action', PoseTaskAction::ACTION_PROBLEM_REPORTED)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->with([
                'task.panel:id,reference,name,commune_id',
                'task.panel.commune:id,name,city',
                'task.campaign:id,name,client_id',
                'task.campaign.client:id,name',
                'task.technicien:id,name',
                'resolvedBy:id,name',
                'maintenance:id,statut',
            ]);

        if ($status === 'pending')  $query->whereNull('resolved_at')->whereNull('maintenance_id');
        if ($status === 'resolved') $query->where(fn($q) => $q->whereNotNull('resolved_at')->orWhereNotNull('maintenance_id'));
        if (!empty($filters['commune_id'])) $query->whereHas('task.panel', fn($p) => $p->where('commune_id', $filters['commune_id']));
        if (!empty($filters['client_id']))  $query->whereHas('task.campaign', fn($c) => $c->where('client_id', $filters['client_id']));
        if (($filters['zone'] ?? null) === 'abidjan')   $query->whereHas('task.panel.commune', fn($c) => $c->where('city', 'Abidjan'));
        if (($filters['zone'] ?? null) === 'interieur') $query->whereHas('task.panel.commune', fn($c) => $c->where('city', '!=', 'Abidjan'));

        $signalements = $query->orderByDesc('created_at')->paginate(25)->withQueryString();

        // Filtre motif post-paginate (effectiveMotif est PHP, pas SQL)
        if ($motifFilter) {
            $signalements->setCollection(
                $signalements->getCollection()->filter(fn($a) => $a->effectiveMotif() === $motifFilter)->values()
            );
        }

        $allCommunes = \App\Models\Commune::orderBy('name')->get(['id','name','city']);
        $allClients  = \App\Models\Client::orderBy('name')->get(['id','name']);

        return view('admin.sla.index', compact(
            'stats', 'signalements', 'from', 'to', 'filters', 'motifFilter', 'status',
            'allCommunes', 'allClients',
        ));
    }

    /**
     * PUT /admin/sla/retards/{action}
     * Amende le motif d'un signalement a posteriori — crée un audit
     * pose_task_actions.action='motif_modified' SANS écraser l'original.
     */
    public function updateMotif(Request $request, PoseTaskAction $action)
    {
        $this->authorize('amend', $action);

        $data = $request->validate([
            'motif'       => 'required|string|in:' . collect(DelayReason::cases())->pluck('value')->implode(','),
            'reason_text' => 'required|string|min:10|max:500',
        ], [
            'motif.required'       => 'Sélectionne un motif.',
            'motif.in'             => 'Motif inconnu.',
            'reason_text.required' => 'La justification est obligatoire.',
            'reason_text.min'      => 'La justification doit faire au moins 10 caractères.',
            'reason_text.max'      => 'La justification ne peut dépasser 500 caractères.',
        ]);

        try {
            $amendment = $this->service->amend(
                $action,
                DelayReason::from($data['motif']),
                $data['reason_text'],
                $request->user(),
            );
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', '🚫 ' . $e->getMessage());
        }

        return back()->with('success', '✅ Motif modifié et tracé dans l\'historique.');
    }
}

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

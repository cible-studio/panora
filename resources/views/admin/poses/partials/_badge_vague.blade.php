{{-- Badge « 🌊 V2 · Phase 2 » d'une pose (2026-11-04). Rien pour une
     campagne sans vagues. Paramètre : $task (PoseTask). Pour une liste,
     charger la relation `vague` en amont (cf. PoseController::index). --}}
@if(\App\Services\Vagues\VagueService::disponible() && $task->vague_id && $task->vague)
    <span title="Vague {{ $task->vague->numero }} : {{ $task->vague->nom }}"
          style="display:inline-block;margin-top:3px;font-size:9.5px;font-weight:700;padding:1px 7px;border-radius:999px;background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#3b82f6;line-height:1.4;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle">🌊 {{ $task->vague->badge() }}</span>
@endif

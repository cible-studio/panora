<x-admin-layout>
<x-slot name="title">Tâche — {{ $poseTask->panel->reference }}</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.pose-tasks.edit', $poseTask) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
    @if($poseTask->status !== 'realisee')
    <form method="POST" action="{{ route('admin.pose.complete', $poseTask) }}">
        @csrf
        <button type="submit" class="btn btn-success btn-sm">
            ✅ Marquer réalisée
        </button>
    </form>
    @endif
</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">🏗️ Tâche de pose</div>
                <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                    Panneau :
                    <span style="color:var(--accent); font-family:monospace;">
                        {{ $poseTask->panel->reference }}
                    </span>
                </div>
            </div>
            @if($poseTask->status === 'planifiee')
                <span class="badge badge-orange" style="font-size:13px;">Planifiée</span>
            @elseif($poseTask->status === 'en_cours')
                <span class="badge badge-blue" style="font-size:13px;">En cours</span>
            @elseif($poseTask->status === 'realisee')
                <span class="badge badge-green" style="font-size:13px;">Réalisée ✓</span>
            @else
                <span class="badge badge-gray" style="font-size:13px;">Annulée</span>
            @endif
        </div>
        <div class="card-body">
            <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:16px;">
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">PANNEAU</div>
                    <div style="font-weight:600;">{{ $poseTask->panel->name }}</div>
                    <div style="font-size:12px; color:var(--text3);">
                        {{ $poseTask->panel->commune->name }}
                    </div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CAMPAGNE</div>
                    <div style="font-weight:600;">{{ $poseTask->campaign?->name ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TECHNICIEN</div>
                    <div style="font-weight:600;">{{ $poseTask->technicien?->name ?? 'Non assigné' }}</div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">ÉQUIPE</div>
                    <div style="font-weight:600;">{{ $poseTask->team_name ?? '—' }}</div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE PLANIFIÉE</div>
                    <div style="font-weight:600;">
                        {{ $poseTask->scheduled_at?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>
                <div>
                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DATE RÉALISÉE</div>
                    <div style="font-weight:600; color:var(--green);">
                        {{ $poseTask->done_at?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</x-admin-layout>


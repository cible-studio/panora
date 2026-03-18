<x-admin-layout>
<x-slot name="title">Gestion Pose OOH</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.pose-tasks.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle tâche
    </a>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">Planifiées</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalPlanifies }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">En cours</div>
        <div class="stat-value" style="color:var(--blue);">{{ $totalEnCours }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Réalisées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalRealises }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Annulées</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalAnnules }}</div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.pose-tasks.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="status" class="filter-select">
                    <option value="">Tous</option>
                    <option value="planifiee" {{ request('status') === 'planifiee' ? 'selected' : '' }}>Planifiée</option>
                    <option value="en_cours"  {{ request('status') === 'en_cours'  ? 'selected' : '' }}>En cours</option>
                    <option value="realisee"  {{ request('status') === 'realisee'  ? 'selected' : '' }}>Réalisée</option>
                    <option value="annulee"   {{ request('status') === 'annulee'   ? 'selected' : '' }}>Annulée</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Technicien</label>
                <select name="technicien_id" class="filter-select">
                    <option value="">Tous</option>
                    @foreach($techniciens as $tech)
                    <option value="{{ $tech->id }}"
                        {{ request('technicien_id') == $tech->id ? 'selected' : '' }}>
                        {{ $tech->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🏗️ Tâches de pose ({{ $poseTasks->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Panneau</th>
                    <th>Campagne</th>
                    <th>Technicien</th>
                    <th>Équipe</th>
                    <th>Planifié le</th>
                    <th>Réalisé le</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($poseTasks as $task)
                <tr>
                    <td>
                        <div style="font-weight:600; color:var(--accent); font-family:monospace;">
                            {{ $task->panel->reference }}
                        </div>
                        <div style="font-size:11px; color:var(--text3);">
                            {{ $task->panel->commune->name }}
                        </div>
                    </td>
                    <td>{{ $task->campaign?->name ?? '—' }}</td>
                    <td>{{ $task->technicien?->name ?? '—' }}</td>
                    <td>{{ $task->team_name ?? '—' }}</td>
                    <td style="font-size:12px;">
                        {{ $task->scheduled_at->format('d/m/Y H:i') }}
                    </td>
                    <td style="font-size:12px; color:var(--text3);">
                        {{ $task->done_at?->format('d/m/Y H:i') ?? '—' }}
                    </td>
                    <td>
                        @if($task->status === 'planifiee')
                            <span class="badge badge-orange">Planifiée</span>
                        @elseif($task->status === 'en_cours')
                            <span class="badge badge-blue">En cours</span>
                        @elseif($task->status === 'realisee')
                            <span class="badge badge-green">Réalisée ✓</span>
                        @else
                            <span class="badge badge-gray">Annulée</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            @if($task->status !== 'realisee')
                            <form method="POST"
                                  action="{{ route('admin.pose.complete', $task) }}">
                                @csrf
                                <button class="btn btn-success btn-sm" title="Marquer réalisée">
                                    ✓
                                </button>
                            </form>
                            @endif
                            <a href="{{ route('admin.pose-tasks.edit', $task) }}"
                               class="btn btn-ghost btn-sm">✏️</a>
                            <form method="POST"
                                  action="{{ route('admin.pose-tasks.destroy', $task) }}"
                                  onsubmit="return confirm('Supprimer ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucune tâche de pose
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $poseTasks->links() }}
    </div>
</div>

</x-admin-layout>

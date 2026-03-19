<x-admin-layout>
<x-slot name="title">Maintenances</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.maintenances.create') }}" class="btn btn-primary btn-sm">
        ＋ Signaler une panne
    </a>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">Signalées</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalSignales }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">En cours</div>
        <div class="stat-value" style="color:var(--blue);">{{ $totalEnCours }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Urgentes</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalUrgentes }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Résolues</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalResolus }}</div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.maintenances.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Recherche panneau</label>
                <input type="text" name="search" class="filter-input"
                       value="{{ request('search') }}"
                       placeholder="Référence, nom...">
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="statut" class="filter-select">
                    <option value="">Tous</option>
                    <option value="signale"  {{ request('statut') === 'signale'  ? 'selected' : '' }}>Signalé</option>
                    <option value="en_cours" {{ request('statut') === 'en_cours' ? 'selected' : '' }}>En cours</option>
                    <option value="resolu"   {{ request('statut') === 'resolu'   ? 'selected' : '' }}>Résolu</option>
                    <option value="annule"   {{ request('statut') === 'annule'   ? 'selected' : '' }}>Annulé</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Priorité</label>
                <select name="priorite" class="filter-select">
                    <option value="">Toutes</option>
                    <option value="urgente" {{ request('priorite') === 'urgente' ? 'selected' : '' }}>🔴 Urgente</option>
                    <option value="haute"   {{ request('priorite') === 'haute'   ? 'selected' : '' }}>🟠 Haute</option>
                    <option value="normale" {{ request('priorite') === 'normale' ? 'selected' : '' }}>🔵 Normale</option>
                    <option value="faible"  {{ request('priorite') === 'faible'  ? 'selected' : '' }}>⚪ Faible</option>
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.maintenances.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔧 Maintenances ({{ $maintenances->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Panneau</th>
                    <th>Type de panne</th>
                    <th>Priorité</th>
                    <th>Statut</th>
                    <th>Technicien</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($maintenances as $maintenance)
                <tr>
                    <td>
                        <div style="font-weight:600; color:var(--accent); font-family:monospace;">
                            {{ $maintenance->panel->reference }}
                        </div>
                        <div style="font-size:11px; color:var(--text3);">
                            {{ $maintenance->panel->commune->name }}
                        </div>
                    </td>
                    <td>{{ $maintenance->type_panne }}</td>
                    <td>
                        @if($maintenance->priorite === 'urgente')
                            <span class="badge badge-red">🔴 Urgente</span>
                        @elseif($maintenance->priorite === 'haute')
                            <span class="badge badge-orange">🟠 Haute</span>
                        @elseif($maintenance->priorite === 'normale')
                            <span class="badge badge-blue">🔵 Normale</span>
                        @else
                            <span class="badge badge-gray">⚪ Faible</span>
                        @endif
                    </td>
                    <td>
                        @if($maintenance->statut === 'signale')
                            <span class="badge badge-orange">Signalé</span>
                        @elseif($maintenance->statut === 'en_cours')
                            <span class="badge badge-blue">En cours</span>
                        @elseif($maintenance->statut === 'resolu')
                            <span class="badge badge-green">Résolu ✓</span>
                        @else
                            <span class="badge badge-gray">Annulé</span>
                        @endif
                    </td>
                    <td>
                        {{ $maintenance->technicien?->name ?? '—' }}
                    </td>
                    <td style="font-size:12px; color:var(--text3);">
                        {{ $maintenance->date_signalement->format('d/m/Y') }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.maintenances.show', $maintenance) }}"
                               class="btn btn-ghost btn-sm">👁️</a>
                            <a href="{{ route('admin.maintenances.edit', $maintenance) }}"
                               class="btn btn-ghost btn-sm">✏️</a>
                            @if($maintenance->statut !== 'resolu')
                            <form method="POST"
                                  action="{{ route('admin.maintenances.destroy', $maintenance) }}"
                                  onsubmit="return confirm('Supprimer ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucune maintenance 🎉
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $maintenances->links() }}
    </div>
</div>

</x-admin-layout>

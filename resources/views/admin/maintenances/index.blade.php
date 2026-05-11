<x-admin-layout>
<x-slot name="title">Maintenances</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.maintenances.create') }}" class="btn btn-primary btn-sm">
        ＋ Signaler une panne
    </a>
</x-slot>

{{-- ════ KPI cards (pattern unifié : bordure latérale colorée, état actif) ══ --}}
@php
    $hasAnyMaintFilter = request('statut') || request('priorite') || request('search');
    $kpis = [
        ['key'=>'all',      'label'=>'Total signalées', 'icon'=>'🔔', 'color'=>'var(--accent)', 'value'=>$totalSignales + $totalEnCours + $totalResolus, 'url'=>route('admin.maintenances.index'), 'active'=>!$hasAnyMaintFilter],
        ['key'=>'signale',  'label'=>'Signalées',       'icon'=>'⚠️', 'color'=>'#f97316',       'value'=>$totalSignales, 'url'=>route('admin.maintenances.index', ['statut'=>'signale']), 'active'=>request('statut')==='signale'],
        ['key'=>'en_cours', 'label'=>'En cours',        'icon'=>'🔧', 'color'=>'#3b82f6',       'value'=>$totalEnCours,  'url'=>route('admin.maintenances.index', ['statut'=>'en_cours']), 'active'=>request('statut')==='en_cours'],
        ['key'=>'urgentes', 'label'=>'Urgentes',        'icon'=>'🚨', 'color'=>'#ef4444',       'value'=>$totalUrgentes, 'url'=>route('admin.maintenances.index', ['priorite'=>'urgente']), 'active'=>request('priorite')==='urgente'],
        ['key'=>'resolu',   'label'=>'Résolues',        'icon'=>'✅', 'color'=>'#22c55e',       'value'=>$totalResolus,  'url'=>route('admin.maintenances.index', ['statut'=>'resolu']), 'active'=>request('statut')==='resolu'],
    ];
@endphp
<div class="stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:20px">
    @foreach($kpis as $k)
    <a href="{{ $k['url'] }}"
       class="stat-card {{ $k['active'] ? 'active' : '' }}"
       style="background:var(--surface);border:1px solid var(--border);border-left:4px solid {{ $k['color'] }};border-radius:14px;padding:14px 18px;text-decoration:none;display:block;transition:all .15s;{{ $k['active'] ? 'box-shadow:0 0 0 2px '.$k['color'].'33;' : '' }}">
        <div style="font-size:18px;color:{{ $k['color'] }};margin-bottom:4px">{{ $k['icon'] }}</div>
        <div style="font-size:26px;font-weight:800;color:{{ $k['color'] }};line-height:1;margin-bottom:6px">{{ number_format($k['value']) }}</div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3)">{{ $k['label'] }}</div>
    </a>
    @endforeach
</div>

{{-- FILTRES AUTO --}}
<div class="card" style="margin-bottom:16px;">
    <form id="filter-form" method="GET" action="{{ route('admin.maintenances.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Recherche panneau</label>
                <input type="text" name="search" class="filter-input"
                       value="{{ request('search') }}"
                       placeholder="Référence, nom..."
                       oninput="debounceSubmit()">
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="statut" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="signale"  {{ request('statut') === 'signale'  ? 'selected' : '' }}>Signalé</option>
                    <option value="en_cours" {{ request('statut') === 'en_cours' ? 'selected' : '' }}>En cours</option>
                    <option value="resolu"   {{ request('statut') === 'resolu'   ? 'selected' : '' }}>Résolu</option>
                    <option value="annule"   {{ request('statut') === 'annule'   ? 'selected' : '' }}>Annulé</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Priorité</label>
                <select name="priorite" class="filter-select" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    <option value="urgente" {{ request('priorite') === 'urgente' ? 'selected' : '' }}>🔴 Urgente</option>
                    <option value="haute"   {{ request('priorite') === 'haute'   ? 'selected' : '' }}>🟠 Haute</option>
                    <option value="normale" {{ request('priorite') === 'normale' ? 'selected' : '' }}>🔵 Normale</option>
                    <option value="faible"  {{ request('priorite') === 'faible'  ? 'selected' : '' }}>⚪ Faible</option>
                </select>
            </div>
            @if(request()->hasAny(['search', 'statut', 'priorite']))
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <a href="{{ route('admin.maintenances.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
            </div>
            @endif
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
                        @if($maintenance->technicien)
                            {{ $maintenance->technicien->name }}
                        @elseif($maintenance->isUnassigned())
                            <span class="badge badge-red" title="Maintenance signalée sans technicien attribué — à assigner rapidement.">⚠️ Non assigné</span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="font-size:12px; color:var(--text3);">
                        {{ $maintenance->date_signalement->format('d/m/Y') }}
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.maintenances.show', $maintenance) }}"
                               class="btn btn-ghost btn-sm" title="Voir">👁️</a>
                            @if(!$maintenance->isLocked())
                                <a href="{{ route('admin.maintenances.edit', $maintenance) }}"
                                   class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                                <form method="POST"
                                      action="{{ route('admin.maintenances.destroy', $maintenance) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                                </form>
                            @else
                                <span class="badge badge-gray" title="Maintenance verrouillée — utilisez Rouvrir depuis la fiche">🔒</span>
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

@push('scripts')
<script>
let debounceTimer = null;
function debounceSubmit() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        document.getElementById('filter-form').submit();
    }, 500);
}
</script>
@endpush

</x-admin-layout>

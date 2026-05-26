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
        [
            'key'=>'all', 'label'=>'Total signalées', 'sub'=>'toutes maintenances',
            'color'=>'var(--accent)',
            'value'=>$totalSignales + $totalEnCours + $totalResolus,
            'url'=>route('admin.maintenances.index'), 'active'=>false,
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
        ],
        [
            'key'=>'signale', 'label'=>'Signalées', 'sub'=>'à traiter',
            'color'=>'#f97316',
            'value'=>$totalSignales,
            'url'=>route('admin.maintenances.index', ['statut'=>'signale']),
            'active'=>request('statut')==='signale',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        ],
        [
            'key'=>'en_cours', 'label'=>'En cours', 'sub'=>'interventions actives',
            'color'=>'#3b82f6',
            'value'=>$totalEnCours,
            'url'=>route('admin.maintenances.index', ['statut'=>'en_cours']),
            'active'=>request('statut')==='en_cours',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
        ],
        [
            'key'=>'urgentes', 'label'=>'Urgentes', 'sub'=>'priorité maximale',
            'color'=>'#ef4444',
            'value'=>$totalUrgentes,
            'url'=>route('admin.maintenances.index', ['priorite'=>'urgente']),
            'active'=>request('priorite')==='urgente',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
        ],
        [
            'key'=>'resolu', 'label'=>'Résolues', 'sub'=>'pannes corrigées',
            'color'=>'#22c55e',
            'value'=>$totalResolus,
            'url'=>route('admin.maintenances.index', ['statut'=>'resolu']),
            'active'=>request('statut')==='resolu',
            'svg'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        ],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    @foreach($kpis as $k)
    <a href="{{ $k['url'] }}"
       class="kpi-card {{ $k['active'] ? 'is-active' : '' }}"
       style="--kpi-color:{{ $k['color'] }}"
       onmouseenter="this.style.borderColor='{{ $k['color'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:{{ $k['color'] }}"></div>
        <div class="kpi-card__icon" style="color:{{ $k['color'] }}">{!! $k['svg'] !!}</div>
        <div class="kpi-card__value" style="color:{{ $k['color'] }}">{{ number_format($k['value']) }}</div>
        <div class="kpi-card__label">{{ $k['label'] }}</div>
        <div class="kpi-card__sub">{{ $k['sub'] }}</div>
        <div class="kpi-card__arrow" style="color:{{ $k['color'] }}">→</div>
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
                <a href="{{ route('admin.maintenances.index') }}"
                   style="display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:10px;background:var(--surface2);border:1px solid var(--border2);color:var(--text2);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap"
                   onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--text)'"
                   onmouseout="this.style.borderColor='var(--border2)';this.style.color='var(--text2)'">
                    ✕ Réinitialiser
                </a>
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
                        Aucune maintenance
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

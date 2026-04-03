<x-admin-layout>
<x-slot name="title">Inventaire Panneaux</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.panels.export.list') }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    <a href="{{ route('admin.panels.export.network') }}" class="btn btn-ghost btn-sm">
        📊 Rapport réseau
    </a>
    <a href="{{ route('admin.panels.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouveau panneau
    </a>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(5,1fr);">
    <a href="{{ route('admin.panels.index', ['source' => 'all']) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;
              {{ ($source ?? 'all') === 'all' && !request('status') ? 'border-color:var(--accent);' : '' }}">
        <div class="stat-label">Total CIBLE CI</div>
        <div class="stat-value">{{ $totalPanneaux }}</div>
    </a>
    <a href="{{ route('admin.panels.index', ['source' => 'cible', 'status' => 'libre']) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;
              {{ request('status') === 'libre' ? 'border-color:var(--green);' : '' }}">
        <div class="stat-label">Libres</div>
        <div class="stat-value" style="color:var(--green);">{{ $panneauxLibres }}</div>
    </a>
    <a href="{{ route('admin.panels.index', ['source' => 'cible', 'status' => 'occupe']) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;
              {{ request('status') === 'occupe' ? 'border-color:var(--accent);' : '' }}">
        <div class="stat-label">Occupés</div>
        <div class="stat-value" style="color:var(--accent);">{{ $panneauxOccupes }}</div>
    </a>
    <a href="{{ route('admin.panels.index', ['source' => 'cible', 'status' => 'maintenance']) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;
              {{ request('status') === 'maintenance' ? 'border-color:var(--red);' : '' }}">
        <div class="stat-label">Maintenance</div>
        <div class="stat-value" style="color:var(--red);">{{ $enMaintenance }}</div>
    </a>
    <a href="{{ route('admin.panels.index', ['source' => 'externe']) }}"
       class="stat-card" style="text-decoration:none;cursor:pointer;
              border-color:{{ ($source ?? '') === 'externe' ? 'var(--purple)' : 'rgba(168,85,247,0.3)' }};
              background:rgba(168,85,247,0.05);">
        <div class="stat-label" style="color:var(--purple);">Régies externes</div>
        <div class="stat-value" style="color:var(--purple);">{{ $totalExternes }}</div>
    </a>
</div>

{{-- FILTRE SOURCE --}}
<div style="display:flex;gap:8px;margin-bottom:16px;">
    <a href="{{ route('admin.panels.index', array_merge(request()->except('source'), ['source' => 'all'])) }}"
       class="btn {{ ($source ?? 'all') === 'all' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
        🪧 Tous ({{ $totalPanneaux + $totalExternes }})
    </a>
    <a href="{{ route('admin.panels.index', array_merge(request()->except('source'), ['source' => 'cible'])) }}"
       class="btn {{ ($source ?? '') === 'cible' ? 'btn-primary' : 'btn-ghost' }} btn-sm">
        ✅ CIBLE CI ({{ $totalPanneaux }})
    </a>
    <a href="{{ route('admin.panels.index', array_merge(request()->except('source'), ['source' => 'externe'])) }}"
       class="btn {{ ($source ?? '') === 'externe' ? 'btn-primary' : 'btn-ghost' }} btn-sm"
       style="{{ ($source ?? '') === 'externe' ? '' : 'color:var(--purple);border-color:rgba(168,85,247,0.3);' }}">
        🏢 Régies externes ({{ $totalExternes }})
    </a>
</div>

{{-- FILTRES AUTO — masqués si vue externe --}}
@if(($source ?? 'all') !== 'externe')
<div class="card" style="margin-bottom:16px;">
    <form id="filter-form" method="GET" action="{{ route('admin.panels.index') }}">
        <input type="hidden" name="source" value="{{ $source ?? 'all' }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Recherche</label>
                <input type="text" name="search" class="filter-input"
                       value="{{ request('search') }}"
                       placeholder="Référence, nom..."
                       oninput="debounceSubmit()">
            </div>
            <div class="filter-group">
                <label class="filter-label">Commune</label>
                <select name="commune_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach($communes as $commune)
                    <option value="{{ $commune->id }}"
                        {{ request('commune_id') == $commune->id ? 'selected' : '' }}>
                        {{ $commune->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="libre"       {{ request('status') === 'libre'       ? 'selected' : '' }}>Libre</option>
                    <option value="occupe"      {{ request('status') === 'occupe'      ? 'selected' : '' }}>Occupé</option>
                    <option value="option"      {{ request('status') === 'option'      ? 'selected' : '' }}>Option</option>
                    <option value="confirme"    {{ request('status') === 'confirme'    ? 'selected' : '' }}>Confirmé</option>
                    <option value="maintenance" {{ request('status') === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Catégorie</label>
                <select name="category_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}"
                        {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @if(request()->hasAny(['search','commune_id','status','category_id']))
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <a href="{{ route('admin.panels.index', ['source' => $source ?? 'all']) }}"
                   class="btn btn-ghost btn-sm">✕ Reset</a>
            </div>
            @endif
        </div>
    </form>
</div>
@endif

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">
            @if(($source ?? 'all') === 'externe')
                🏢 Panneaux Régies externes ({{ $externalPanels->count() }})
            @else
                🪧 Panneaux ({{ $panels->total() }})
            @endif
        </div>
        <a href="{{ route('admin.map') }}" class="btn btn-ghost btn-sm">🗺️ Voir carte</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Photo</th>
                    <th>Référence</th>
                    <th>Nom</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Faces</th>
                    <th>Adresse / Quartier</th>
                    <th>Tarif/mois</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>

                {{-- ══ PANNEAUX CIBLE CI ══ --}}
                @if(($source ?? 'all') !== 'externe')
                @forelse($panels as $panel)
                <tr>
                    <td>
                        @if($panel->photos->first())
                            <img src="{{ asset('storage/'.$panel->photos->first()->path) }}"
                                 style="width:60px;height:45px;object-fit:cover;
                                        border-radius:6px;border:1px solid var(--border);">
                        @else
                            <div style="width:60px;height:45px;border-radius:6px;
                                        border:1px solid var(--border);background:var(--surface2);
                                        display:flex;align-items:center;justify-content:center;
                                        color:var(--text3);font-size:18px;">🪧</div>
                        @endif
                    </td>
                    <td>
                        <span style="font-family:monospace;color:var(--accent);font-weight:700;">
                            {{ $panel->reference }}
                        </span>
                    </td>
                    <td>
                        <div style="font-weight:500;">{{ $panel->name }}</div>
                        <div style="font-size:11px;color:var(--text3);">
                            {{ $panel->category?->name ?? '—' }}
                            @if($panel->is_lit) · 💡 @endif
                        </div>
                    </td>
                    <td>{{ $panel->commune->name }}</td>
                    <td>
                        <div>{{ $panel->format->name }}</div>
                        @if($panel->format->surface)
                        <div style="font-size:11px;color:var(--text3);">{{ $panel->format->surface }}m²</div>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <span style="font-weight:700;color:var(--text2);">{{ $panel->nombre_faces ?? 1 }}</span>
                    </td>
                    <td>
                        @if($panel->quartier)
                            <div style="font-weight:500;font-size:12px;">{{ $panel->quartier }}</div>
                        @endif
                        @if($panel->adresse)
                            <div style="font-size:11px;color:var(--text3);">{{ $panel->adresse }}</div>
                        @endif
                        @if(!$panel->quartier && !$panel->adresse)
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td style="color:var(--accent);font-weight:600;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </td>
                    <td>
                        @if($panel->status->value === 'libre')
                            <span class="badge badge-green">Libre</span>
                        @elseif($panel->status->value === 'option')
                            <span class="badge badge-orange">Option</span>
                        @elseif($panel->status->value === 'confirme')
                            <span class="badge badge-blue">Confirmé</span>
                        @elseif($panel->status->value === 'occupe')
                            <span class="badge badge-purple">Occupé</span>
                        @else
                            <span class="badge badge-red">Maintenance</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="{{ route('admin.panels.show', $panel) }}"
                               class="btn btn-ghost btn-sm" title="Voir">👁️</a>
                            <a href="{{ route('admin.panels.edit', $panel) }}"
                               class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                            <a href="{{ route('admin.panels.pdf', $panel) }}"
                               class="btn btn-ghost btn-sm" title="PDF">📄</a>
                            <form method="POST" action="{{ route('admin.panels.destroy', $panel) }}"
                                  onsubmit="return confirm('Supprimer ce panneau ?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                @if(($source ?? 'all') === 'cible' || $externalPanels->isEmpty())
                <tr>
                    <td colspan="11" style="text-align:center;color:var(--text3);padding:32px;">
                        Aucun panneau trouvé
                    </td>
                </tr>
                @endif
                @endforelse
                @endif

                {{-- ══ PANNEAUX EXTERNES ══ --}}
                @if(($source ?? 'all') !== 'cible' && $externalPanels->isNotEmpty())

                @if(($source ?? 'all') === 'all' && $panels->isNotEmpty())
                <tr>
                    <td colspan="11" style="padding:8px 12px;background:rgba(168,85,247,0.06);
                        border-top:2px solid rgba(168,85,247,0.3);border-bottom:1px solid rgba(168,85,247,0.2);">
                        <span style="font-size:11px;font-weight:700;color:var(--purple);
                            text-transform:uppercase;letter-spacing:1px;">
                            🏢 Panneaux — Régies externes ({{ $externalPanels->count() }})
                        </span>
                    </td>
                </tr>
                @endif

                @foreach($externalPanels as $ext)
                <tr style="background:rgba(168,85,247,0.02);">
                    <td>
                        <div style="width:60px;height:45px;border-radius:6px;
                                    border:1px solid rgba(168,85,247,0.2);background:rgba(168,85,247,0.08);
                                    display:flex;align-items:center;justify-content:center;
                                    color:var(--purple);font-size:16px;">🏢</div>
                    </td>
                    <td>
                        <span style="font-family:monospace;color:var(--purple);font-weight:700;">
                            {{ $ext->code_panneau }}
                        </span>
                        <div style="margin-top:2px;">
                            <span style="font-size:10px;padding:1px 6px;border-radius:4px;
                                background:rgba(168,85,247,0.12);color:var(--purple);font-weight:600;">
                                {{ $ext->agency->name }}
                            </span>
                        </div>
                    </td>
                    <td>
                        <div style="font-weight:500;">{{ $ext->designation }}</div>
                        <div style="font-size:11px;color:var(--text3);">
                            {{ $ext->category?->name ?? '—' }}
                            @if($ext->is_lit) · 💡 @endif
                        </div>
                    </td>
                    <td>{{ $ext->commune?->name ?? '—' }}</td>
                    <td>
                        <div>{{ $ext->format?->name ?? '—' }}</div>
                        @if($ext->format?->surface)
                        <div style="font-size:11px;color:var(--text3);">{{ $ext->format->surface }}m²</div>
                        @endif
                    </td>
                    <td style="text-align:center;">
                        <span style="font-weight:700;color:var(--text2);">{{ $ext->nombre_faces ?? 1 }}</span>
                    </td>
                    <td>
                        @if($ext->orientation)
                            <span class="badge badge-gray">{{ ucfirst($ext->orientation) }}</span>
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($ext->quartier)
                            <div style="font-weight:500;font-size:12px;">{{ $ext->quartier }}</div>
                        @endif
                        @if($ext->adresse)
                            <div style="font-size:11px;color:var(--text3);">{{ $ext->adresse }}</div>
                        @endif
                        @if(!$ext->quartier && !$ext->adresse)
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td style="color:var(--purple);font-weight:600;">
                        @if($ext->monthly_rate > 0)
                            {{ number_format($ext->monthly_rate, 0, ',', ' ') }} FCFA
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-size:11px;padding:2px 8px;border-radius:20px;
                            background:rgba(168,85,247,0.12);color:var(--purple);
                            border:1px solid rgba(168,85,247,0.3);font-weight:600;">
                            🏢 Externe
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <a href="{{ route('admin.external-agencies.show', $ext->agency_id) }}"
                               class="btn btn-ghost btn-sm" title="Voir la régie">👁️</a>
                        </div>
                    </td>
                </tr>
                @endforeach
                @endif

            </tbody>
        </table>
    </div>

    @if(($source ?? 'all') !== 'externe')
    <div style="padding:16px;">
        {{ $panels->links() }}
    </div>
    @endif
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

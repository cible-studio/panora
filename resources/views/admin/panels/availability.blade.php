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
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card">
        <div class="stat-label">Total Panneaux</div>
        <div class="stat-value">{{ $totalPanneaux }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Libres</div>
        <div class="stat-value" style="color:var(--green);">{{ $panneauxLibres }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Occupés</div>
        <div class="stat-value" style="color:var(--accent);">{{ $panneauxOccupes }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Maintenance</div>
        <div class="stat-value" style="color:var(--red);">{{ $enMaintenance }}</div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.panels.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Recherche</label>
                <input type="text" name="search" class="filter-input"
                       value="{{ request('search') }}"
                       placeholder="Référence, nom...">
            </div>
            <div class="filter-group">
                <label class="filter-label">Commune</label>
                <select name="commune_id" class="filter-select">
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
                <select name="status" class="filter-select">
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
                <select name="category_id" class="filter-select">
                    <option value="">Toutes</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat->id }}"
                        {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.panels.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- TOGGLE VUE --}}
<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
    <div style="color:var(--text3); font-size:13px;">
        {{ $panels->total() }} panneau(x) trouvé(s)
    </div>
    <div style="display:flex; gap:8px;">
        <a href="{{ route('admin.map') }}" class="btn btn-ghost btn-sm">🗺️ Carte</a>
        <button onclick="setView('grid')" id="btn-grid" class="btn btn-primary btn-sm">
            ⊞ Grille
        </button>
        <button onclick="setView('table')" id="btn-table" class="btn btn-ghost btn-sm">
            ☰ Tableau
        </button>
    </div>
</div>

{{-- VUE GRILLE --}}
<div id="view-grid"
     style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
    @forelse($panels as $panel)
    <div style="border-radius:12px; overflow:hidden; border:1px solid var(--border);
                background:var(--surface); position:relative;">

        {{-- IMAGE --}}
        <div style="position:relative; height:200px;">
            @if($panel->photos->first())
                <img src="{{ asset('storage/'.$panel->photos->first()->path) }}"
                     style="width:100%; height:200px; object-fit:cover; display:block;">
            @else
                <div style="width:100%; height:200px; background:var(--surface2);
                            display:flex; align-items:center; justify-content:center;
                            color:var(--text3); font-size:40px;">
                    🪧
                </div>
            @endif

            {{-- OVERLAY GRADIENT --}}
            <div style="position:absolute; bottom:0; left:0; right:0; height:100%;
                        background:linear-gradient(to top, rgba(0,0,0,0.85) 0%,
                        rgba(0,0,0,0.3) 50%, transparent 100%);">
            </div>

            {{-- RÉFÉRENCE SUR L'IMAGE --}}
            <div style="position:absolute; bottom:12px; left:12px; right:12px;">
                <div style="font-family:monospace; font-weight:800; font-size:16px;
                            color:var(--accent);">
                    {{ $panel->reference }}
                </div>
                <div style="font-size:12px; color:rgba(255,255,255,0.85); margin-top:2px;">
                    {{ $panel->name }}
                </div>
            </div>

            {{-- BADGE STATUT --}}
            <div style="position:absolute; top:10px; right:10px;">
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
            </div>

            {{-- BADGE ÉCLAIRÉ --}}
            @if($panel->is_lit)
            <div style="position:absolute; top:10px; left:10px;">
                <span class="badge badge-orange">💡</span>
            </div>
            @endif
        </div>

        {{-- INFOS --}}
        <div style="padding:14px;">
            <div style="display:flex; justify-content:space-between;
                        align-items:center; margin-bottom:10px;">
                <div>
                    <div style="font-size:12px; color:var(--text3);">
                        📍 {{ $panel->commune->name }}
                        @if($panel->quartier)
                            — {{ $panel->quartier }}
                        @endif
                    </div>
                    <div style="font-size:11px; color:var(--text3); margin-top:2px;">
                        📐 {{ $panel->format->name }}
                        @if($panel->format->surface)
                            ({{ $panel->format->surface }}m²)
                        @endif
                        · {{ $panel->nombre_faces ?? 1 }} face(s)
                    </div>
                </div>
            </div>

            {{-- TARIF --}}
            <div style="font-size:15px; font-weight:700; color:var(--accent); margin-bottom:12px;">
                {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                <span style="font-size:11px; color:var(--text3); font-weight:400;">/mois</span>
            </div>

            {{-- ACTIONS --}}
            <div style="display:flex; gap:6px;">
                <a href="{{ route('admin.panels.show', $panel) }}"
                   class="btn btn-ghost btn-sm" style="flex:1; text-align:center;">
                    👁️ Voir
                </a>
                <a href="{{ route('admin.panels.edit', $panel) }}"
                   class="btn btn-ghost btn-sm">✏️</a>
                <a href="{{ route('admin.panels.pdf', $panel) }}"
                   class="btn btn-ghost btn-sm">📄</a>
                <form method="POST"
                      action="{{ route('admin.panels.destroy', $panel) }}"
                      onsubmit="return confirm('Supprimer ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm">🗑️</button>
                </form>
            </div>
        </div>

    </div>
    @empty
    <div style="grid-column:span 3; text-align:center;
                color:var(--text3); padding:40px;">
        <div style="font-size:40px; margin-bottom:12px;">🪧</div>
        <div style="font-size:16px; font-weight:600;">Aucun panneau trouvé</div>
    </div>
    @endforelse
</div>

{{-- VUE TABLEAU --}}
<div id="view-table" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🪧 Panneaux ({{ $panels->total() }})</div>
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
                        <th>Orientation</th>
                        <th>Adresse / Quartier</th>
                        <th>Tarif/mois</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($panels as $panel)
                    <tr>
                        <td>
                            @if($panel->photos->first())
                                <img src="{{ asset('storage/'.$panel->photos->first()->path) }}"
                                     style="width:60px; height:45px; object-fit:cover;
                                            border-radius:6px; border:1px solid var(--border);">
                            @else
                                <div style="width:60px; height:45px; border-radius:6px;
                                            border:1px solid var(--border); background:var(--surface2);
                                            display:flex; align-items:center; justify-content:center;
                                            color:var(--text3); font-size:18px;">
                                    🪧
                                </div>
                            @endif
                        </td>
                        <td>
                            <span style="font-family:monospace; color:var(--accent); font-weight:700;">
                                {{ $panel->reference }}
                            </span>
                        </td>
                        <td>
                            <div style="font-weight:500;">{{ $panel->name }}</div>
                            <div style="font-size:11px; color:var(--text3);">
                                {{ $panel->category?->name ?? '—' }}
                                @if($panel->is_lit) · 💡 @endif
                            </div>
                        </td>
                        <td>{{ $panel->commune->name }}</td>
                        <td>
                            <div>{{ $panel->format->name }}</div>
                            @if($panel->format->surface)
                            <div style="font-size:11px; color:var(--text3);">
                                {{ $panel->format->surface }}m²
                            </div>
                            @endif
                        </td>
                        <td style="text-align:center;">
                            <span style="font-weight:700; color:var(--text2);">
                                {{ $panel->nombre_faces ?? 1 }}
                            </span>
                        </td>
                        <td>
                            @if($panel->orientation)
                                <span class="badge badge-gray">
                                    {{ ucfirst($panel->orientation) }}
                                </span>
                            @else
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>
                        <td>
                            @if($panel->quartier)
                                <div style="font-weight:500; font-size:12px;">
                                    {{ $panel->quartier }}
                                </div>
                            @endif
                            @if($panel->adresse)
                                <div style="font-size:11px; color:var(--text3);">
                                    {{ $panel->adresse }}
                                </div>
                            @endif
                            @if(!$panel->quartier && !$panel->adresse)
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>
                        <td style="color:var(--accent); font-weight:600;">
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
                            <div style="display:flex; gap:6px;">
                                <a href="{{ route('admin.panels.show', $panel) }}"
                                   class="btn btn-ghost btn-sm" title="Voir">👁️</a>
                                <a href="{{ route('admin.panels.edit', $panel) }}"
                                   class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
                                <a href="{{ route('admin.panels.pdf', $panel) }}"
                                   class="btn btn-ghost btn-sm" title="PDF">📄</a>
                                <form method="POST"
                                      action="{{ route('admin.panels.destroy', $panel) }}"
                                      onsubmit="return confirm('Supprimer ce panneau ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="11" style="text-align:center; color:var(--text3); padding:32px;">
                            Aucun panneau trouvé
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- PAGINATION --}}
<div style="margin-top:20px;">
    {{ $panels->links() }}
</div>

@push('scripts')
<script>
function setView(view) {
    if (view === 'grid') {
        document.getElementById('view-grid').style.display  = 'grid';
        document.getElementById('view-table').style.display = 'none';
        document.getElementById('btn-grid').className  = 'btn btn-primary btn-sm';
        document.getElementById('btn-table').className = 'btn btn-ghost btn-sm';
        localStorage.setItem('panels-view', 'grid');
    } else {
        document.getElementById('view-grid').style.display  = 'none';
        document.getElementById('view-table').style.display = 'block';
        document.getElementById('btn-grid').className  = 'btn btn-ghost btn-sm';
        document.getElementById('btn-table').className = 'btn btn-primary btn-sm';
        localStorage.setItem('panels-view', 'table');
    }
}

// Restaurer la vue sauvegardée
const savedView = localStorage.getItem('panels-view') || 'grid';
setView(savedView);
</script>
@endpush

</x-admin-layout>

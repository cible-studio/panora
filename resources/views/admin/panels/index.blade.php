<x-admin-layout>
<x-slot name="title">Inventaire Panneaux</x-slot>

<x-slot name="topbarActions">
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

{{-- TABLEAU --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🪧 Panneaux ({{ $panels->total() }})</div>
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
                    <th>Tarif/mois</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($panels as $panel)
                <tr>
                    {{-- PHOTO --}}
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

                    {{-- RÉFÉRENCE --}}
                    <td>
                        <span style="font-family:monospace; color:var(--accent); font-weight:700;">
                            {{ $panel->reference }}
                        </span>
                    </td>

                    {{-- NOM --}}
                    <td>
                        <div style="font-weight:500;">{{ $panel->name }}</div>
                        <div style="font-size:11px; color:var(--text3);">
                            {{ $panel->category?->name ?? '—' }}
                            @if($panel->is_lit) · 💡 Éclairé @endif
                        </div>
                    </td>

                    {{-- COMMUNE --}}
                    <td>{{ $panel->commune->name }}</td>

                    {{-- FORMAT --}}
                    <td>{{ $panel->format->name }}</td>

                    {{-- TARIF --}}
                    <td style="color:var(--accent); font-weight:600;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </td>

                    {{-- STATUT --}}
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

                    {{-- ACTIONS --}}
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.panels.show', $panel) }}"
                               class="btn btn-ghost btn-sm" title="Voir">👁️</a>
                            <a href="{{ route('admin.panels.edit', $panel) }}"
                               class="btn btn-ghost btn-sm" title="Modifier">✏️</a>
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
                    <td colspan="8" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucun panneau trouvé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $panels->links() }}
    </div>
</div>

</x-admin-layout>

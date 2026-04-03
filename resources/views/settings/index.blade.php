<x-admin-layout title="Paramètres">

<x-slot:topbarActions>
</x-slot:topbarActions>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    {{-- ══ COMMUNES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🏙️ Communes ({{ $communes->count() }})</div>
            <a href="{{ route('admin.settings.communes.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Taux ODP</th>
                        <th>Taux TM</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($communes as $commune)
                    <tr>
                        <td><strong>{{ $commune->name }}</strong></td>
                        <td style="color:var(--text2);font-size:12px;">{{ $commune->city ?? '—' }}</td>
                        <td style="font-size:12px;">{{ number_format($commune->odp_rate, 0, ',', ' ') }} FCFA</td>
                        <td style="font-size:12px;">{{ number_format($commune->tm_rate, 0, ',', ' ') }} FCFA</td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="{{ route('admin.settings.communes.edit', $commune) }}"
                                   class="btn btn-ghost btn-sm">✏️</a>
                                <form method="POST"
                                      action="{{ route('admin.settings.communes.destroy', $commune) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune commune
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($communes->count() > 8)
        <div style="padding:10px 16px;">
            <a href="{{ route('admin.settings.communes.index') }}"
               style="font-size:12px;color:var(--accent);">
                Voir toutes les communes →
            </a>
        </div>
        @endif
    </div>

    {{-- ══ ZONES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📍 Zones ({{ $zones->count() }})</div>
            <a href="{{ route('admin.settings.zones.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Commune</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                    <tr>
                        <td><strong>{{ $zone->name }}</strong></td>
                        <td style="color:var(--text2);font-size:12px;">{{ $zone->commune?->name ?? '—' }}</td>
                        <td style="font-size:11px;color:var(--text3);">
                            {{ \Illuminate\Support\Str::limit($zone->description ?? '', 30) ?: '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="{{ route('admin.settings.zones.edit', $zone) }}"
                                   class="btn btn-ghost btn-sm">✏️</a>
                                <form method="POST"
                                      action="{{ route('admin.settings.zones.destroy', $zone) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune zone
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($zones->count() > 8)
        <div style="padding:10px 16px;">
            <a href="{{ route('admin.settings.zones.index') }}"
               style="font-size:12px;color:var(--accent);">
                Voir toutes les zones →
            </a>
        </div>
        @endif
    </div>

    {{-- ══ FORMATS ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📐 Formats ({{ $formats->count() }})</div>
            <a href="{{ route('admin.settings.formats.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Dimensions</th>
                        <th>Surface</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formats as $format)
                    <tr>
                        <td><strong>{{ $format->name }}</strong></td>
                        <td style="font-size:12px;color:var(--text2);">
                            @if($format->width && $format->height)
                                {{ $format->width }}m × {{ $format->height }}m
                            @else
                                —
                            @endif
                        </td>
                        <td style="font-size:12px;">
                            {{ $format->surface ? $format->surface . ' m²' : '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="{{ route('admin.settings.formats.edit', $format) }}"
                                   class="btn btn-ghost btn-sm">✏️</a>
                                <form method="POST"
                                      action="{{ route('admin.settings.formats.destroy', $format) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucun format
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($formats->count() > 8)
        <div style="padding:10px 16px;">
            <a href="{{ route('admin.settings.formats.index') }}"
               style="font-size:12px;color:var(--accent);">
                Voir tous les formats →
            </a>
        </div>
        @endif
    </div>

    {{-- ══ CATÉGORIES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🏷️ Catégories ({{ $categories->count() }})</div>
            <a href="{{ route('admin.settings.categories.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td style="font-size:11px;color:var(--text3);">
                            {{ \Illuminate\Support\Str::limit($category->description ?? '', 40) ?: '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <a href="{{ route('admin.settings.categories.edit', $category) }}"
                                   class="btn btn-ghost btn-sm">✏️</a>
                                <form method="POST"
                                      action="{{ route('admin.settings.categories.destroy', $category) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune catégorie
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->count() > 8)
        <div style="padding:10px 16px;">
            <a href="{{ route('admin.settings.categories.index') }}"
               style="font-size:12px;color:var(--accent);">
                Voir toutes les catégories →
            </a>
        </div>
        @endif
    </div>

</div>

</x-admin-layout>

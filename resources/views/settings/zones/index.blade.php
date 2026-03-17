<x-admin-layout>
<x-slot name="title">Zones</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.zones.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle zone
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">🗺️ Zones ({{ $zones->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Commune</th>
                    <th>Niveau demande</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($zones as $zone)
                <tr>
                    <td><strong>{{ $zone->name }}</strong></td>
                    <td>{{ $zone->commune?->name ?? '—' }}</td>
                    <td>
                        @if($zone->demand_level === 'tres_haute')
                            <span class="badge badge-red">Très haute</span>
                        @elseif($zone->demand_level === 'haute')
                            <span class="badge badge-orange">Haute</span>
                        @elseif($zone->demand_level === 'normale')
                            <span class="badge badge-blue">Normale</span>
                        @else
                            <span class="badge badge-gray">Faible</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.settings.zones.edit', $zone) }}"
                               class="btn btn-ghost btn-sm">✏️ Modifier</a>
                            <form method="POST"
                                  action="{{ route('admin.settings.zones.destroy', $zone) }}"
                                  onsubmit="return confirm('Supprimer cette zone ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:var(--text3); padding:24px;">
                        Aucune zone créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $zones->links() }}
    </div>
</div>

</x-admin-layout>

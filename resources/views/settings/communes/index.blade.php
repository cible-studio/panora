<x-admin-layout>
<x-slot name="title">Communes</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.communes.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle commune
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">🏙️ Communes ({{ $communes->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Ville</th>
                    <th>Région</th>
                    <th>Taux ODP</th>
                    <th>Taux TM</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($communes as $commune)
                <tr>
                    <td><strong>{{ $commune->name }}</strong></td>
                    <td>{{ $commune->city ?? '—' }}</td>
                    <td>{{ $commune->region ?? '—' }}</td>
                    <td>{{ number_format($commune->odp_rate, 0, ',', ' ') }} FCFA</td>
                    <td>{{ number_format($commune->tm_rate, 0, ',', ' ') }} FCFA</td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.settings.communes.edit', $commune) }}"
                               class="btn btn-ghost btn-sm">✏️ Modifier</a>
                            <form method="POST"
                                  action="{{ route('admin.settings.communes.destroy', $commune) }}"
                                  onsubmit="return confirm('Supprimer cette commune ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:var(--text3); padding:24px;">
                        Aucune commune créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $communes->links() }}
    </div>
</div>

</x-admin-layout>

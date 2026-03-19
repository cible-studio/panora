<x-admin-layout>
<x-slot name="title">Formats Panneaux</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.formats.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouveau format
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">📐 Formats ({{ $formats->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Largeur</th>
                    <th>Hauteur</th>
                    <th>Surface</th>
                    <th>Type impression</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($formats as $format)
                <tr>
                    <td><strong>{{ $format->name }}</strong></td>
                    <td>{{ $format->width ? $format->width . ' m' : '—' }}</td>
                    <td>{{ $format->height ? $format->height . ' m' : '—' }}</td>
                    <td>{{ $format->surface ? $format->surface . ' m²' : '—' }}</td>
                    <td>{{ $format->print_type ?? '—' }}</td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.settings.formats.edit', $format) }}"
                               class="btn btn-ghost btn-sm">✏️ Modifier</a>
                            <form method="POST"
                                  action="{{ route('admin.settings.formats.destroy', $format) }}"
                                  onsubmit="return confirm('Supprimer ce format ?')">
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
                        Aucun format créé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $formats->links() }}
    </div>
</div>

</x-admin-layout>

<x-admin-layout>
<x-slot name="title">Catégories Panneaux</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.settings.categories.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvelle catégorie
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">🏷️ Catégories ({{ $categories->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Description</th>
                    <th>Panneaux</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>{{ $category->description ?? '—' }}</td>
                    <td>
                        <span class="badge badge-blue">
                            {{ $category->panels->count() }} panneaux
                        </span>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.settings.categories.edit', $category) }}"
                               class="btn btn-ghost btn-sm">✏️ Modifier</a>
                            <form method="POST"
                                  action="{{ route('admin.settings.categories.destroy', $category) }}"
                                  onsubmit="return confirm('Supprimer cette catégorie ?')">
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
                        Aucune catégorie créée
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $categories->links() }}
    </div>
</div>

</x-admin-layout>

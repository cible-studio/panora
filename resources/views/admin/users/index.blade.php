<x-admin-layout>
<x-slot name="title">Utilisateurs</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">
        ＋ Nouvel utilisateur
    </a>
    <a href="{{ route('admin.audit.logs') }}" class="btn btn-ghost btn-sm">
        📋 Logs d'audit
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">👥 Utilisateurs ({{ $users->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Utilisateur</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Code agent</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div class="avatar-circle">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div style="font-weight:500;">{{ $user->name }}</div>
                        </div>
                    </td>
                    <td style="color:var(--text2);">{{ $user->email }}</td>
                    <td>
                        @if($user->role->value === 'admin')
                            <span class="badge badge-red">🛡️ Admin</span>
                        @elseif($user->role->value === 'commercial')
                            <span class="badge badge-blue">💼 Commercial</span>
                        @elseif($user->role->value === 'mediaplanner')
                            <span class="badge badge-purple">🗓️ Media Planner</span>
                        @else
                            <span class="badge badge-orange">🔧 Technicien</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-family:monospace; color:var(--text2);">
                            {{ $user->agent_code ?? '—' }}
                        </span>
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge-green">Actif</span>
                        @else
                            <span class="badge badge-gray">Inactif</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a href="{{ route('admin.users.edit', $user) }}"
                               class="btn btn-ghost btn-sm">✏️</a>

                            {{-- Toggle actif --}}
                            @if($user->id !== auth()->id())
                            <form method="POST"
                                  action="{{ route('admin.users.toggle', $user) }}">
                                @csrf
                                <button class="btn btn-sm {{ $user->is_active ? 'btn-danger' : 'btn-success' }}">
                                    {{ $user->is_active ? '🔒' : '🔓' }}
                                </button>
                            </form>

                            {{-- Supprimer --}}
                            <form method="POST"
                                  action="{{ route('admin.users.destroy', $user) }}"
                                  onsubmit="return confirm('Supprimer cet utilisateur ?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">🗑️</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucun utilisateur
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $users->links() }}
    </div>
</div>

</x-admin-layout>

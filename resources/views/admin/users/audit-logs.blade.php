<x-admin-layout>
<x-slot name="title">Logs d'Audit</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">
        ← Retour utilisateurs
    </a>
</x-slot>

<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Logs d'audit ({{ $logs->total() }})</div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td style="color:var(--text3); font-size:12px;">
                        {{ $log->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td>
                        @if($log->user)
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="avatar-circle" style="width:24px; height:24px; font-size:10px;">
                                    {{ strtoupper(substr($log->user->name, 0, 1)) }}
                                </div>
                                {{ $log->user->name }}
                            </div>
                        @else
                            <span style="color:var(--text3);">Système</span>
                        @endif
                    </td>
                    <td>
                        <span style="font-family:monospace; font-size:12px; color:var(--text2);">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td style="font-family:monospace; font-size:12px; color:var(--text3);">
                        {{ $log->ip_address ?? '—' }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" style="text-align:center; color:var(--text3); padding:32px;">
                        Aucun log d'audit
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px;">
        {{ $logs->links() }}
    </div>
</div>

</x-admin-layout>


<x-admin-layout title="Logs d'audit">

<div class="card">
    <div class="card-header">
        <span class="card-title">🔍 Logs d'audit</span>
        <span style="font-size:12px;color:var(--text2);">{{ $logs->total() }} entrée(s)</span>
    </div>

    {{-- Filtres --}}
    <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;width:100%;">
            <div class="filter-group">
                <label class="filter-label">Utilisateur</label>
                <select name="user_id" class="filter-select" style="min-width:160px;">
                    <option value="">Tous</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                            {{ $u->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Action</label>
                <input type="text" name="action" class="filter-input"
                       value="{{ request('action') }}" placeholder="Ex: created, updated…"
                       style="min-width:160px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filtrer</button>
            @if(request()->hasAny(['user_id','action']))
                <a href="{{ route('admin.audit.logs') }}" class="btn btn-ghost btn-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Modèle</th>
                    <th>IP</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td style="white-space:nowrap;color:var(--text2);font-size:12px;">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </td>
                    <td>
                        @if($log->user)
                            <span style="font-weight:500;">{{ $log->user->name }}</span>
                        @else
                            <span style="color:var(--text3);font-style:italic;">Système</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $color = match(true) {
                                str_contains($log->action, 'created') => 'var(--green)',
                                str_contains($log->action, 'deleted') => 'var(--red)',
                                str_contains($log->action, 'updated') => 'var(--accent)',
                                default => 'var(--text2)',
                            };
                        @endphp
                        <span style="font-family:monospace;font-size:12px;font-weight:600;color:{{ $color }};">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--text2);">
                        @if($log->model_type)
                            {{ class_basename($log->model_type) }}
                            @if($log->model_id)
                                <span style="color:var(--text3);">#{{ $log->model_id }}</span>
                            @endif
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td style="font-family:monospace;font-size:11px;color:var(--text3);">
                        {{ $log->ip_address ?? '—' }}
                    </td>
                    <td>
                        @if($log->old_values || $log->new_values)
                        <button type="button"
                                onclick="toggleAuditDetail({{ $log->id }})"
                                class="btn btn-ghost btn-sm">
                            Voir
                        </button>
                        @else
                            <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                </tr>
                @if($log->old_values || $log->new_values)
                <tr id="audit-detail-{{ $log->id }}" style="display:none;">
                    <td colspan="6" style="padding:0;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0;background:var(--surface2);">
                            @if($log->old_values)
                            <div style="padding:12px 16px;border-right:1px solid var(--border);">
                                <div style="font-size:10px;font-weight:700;color:var(--text3);
                                            text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
                                    Avant
                                </div>
                                <pre style="font-size:11px;color:var(--text2);white-space:pre-wrap;
                                            word-break:break-all;margin:0;">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            @endif
                            @if($log->new_values)
                            <div style="padding:12px 16px;">
                                <div style="font-size:10px;font-weight:700;color:var(--text3);
                                            text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">
                                    Après
                                </div>
                                <pre style="font-size:11px;color:var(--text2);white-space:pre-wrap;
                                            word-break:break-all;margin:0;">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                            @endif
                        </div>
                    </td>
                </tr>
                @endif
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;padding:40px;color:var(--text3);">
                        Aucun log trouvé.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div style="padding:16px 20px;border-top:1px solid var(--border);">
        {{ $logs->links() }}
    </div>
    @endif
</div>

<script>
function toggleAuditDetail(id) {
    const row = document.getElementById('audit-detail-' + id);
    if (row) row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
}
</script>

</x-admin-layout>

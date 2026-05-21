<x-admin-layout>
<x-slot name="title">Logs d'audit</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">
        ← Retour utilisateurs
    </a>
</x-slot>

{{-- ════ KPI cards (pattern unifié : clic = filtre par famille d'actions) ══ --}}
@php
    $currentKind = request('kind');
    $cards = [
        [
            'key'   => 'all',
            'label' => 'Total entrées',
            'sub'   => 'toute l\'activité',
            'color' => 'var(--accent)',
            'value' => $kpis['total'],
            'url'   => route('admin.audit.logs'),
            'active'=> false,
            'svg'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="15" y2="17"/></svg>',
        ],
        [
            'key'   => 'created',
            'label' => 'Créations',
            'sub'   => 'enregistrements ajoutés',
            'color' => '#22c55e',
            'value' => $kpis['created'],
            'url'   => route('admin.audit.logs', ['kind' => 'created']),
            'active'=> $currentKind === 'created',
            'svg'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        ],
        [
            'key'   => 'updated',
            'label' => 'Modifications',
            'sub'   => 'enregistrements édités',
            'color' => '#3b82f6',
            'value' => $kpis['updated'],
            'url'   => route('admin.audit.logs', ['kind' => 'updated']),
            'active'=> $currentKind === 'updated',
            'svg'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>',
        ],
        [
            'key'   => 'deleted',
            'label' => 'Suppressions',
            'sub'   => 'enregistrements retirés',
            'color' => '#ef4444',
            'value' => $kpis['deleted'],
            'url'   => route('admin.audit.logs', ['kind' => 'deleted']),
            'active'=> $currentKind === 'deleted',
            'svg'   => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
        ],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
    @foreach($cards as $k)
    <a href="{{ $k['url'] }}"
       class="kpi-card {{ $k['active'] ? 'is-active' : '' }}"
       style="--kpi-color:{{ $k['color'] }}"
       onmouseenter="this.style.borderColor='{{ $k['color'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:{{ $k['color'] }}"></div>
        <div class="kpi-card__icon" style="color:{{ $k['color'] }}">{!! $k['svg'] !!}</div>
        <div class="kpi-card__value" style="color:{{ $k['color'] }}">{{ number_format($k['value']) }}</div>
        <div class="kpi-card__label">{{ $k['label'] }}</div>
        <div class="kpi-card__sub">{{ $k['sub'] }}</div>
        <div class="kpi-card__arrow" style="color:{{ $k['color'] }}">→</div>
    </a>
    @endforeach
</div>

{{-- ════ FILTRES ═════════════════════════════════════════════════ --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.audit.logs') }}">
        {{-- conserve le filtre KPI courant si l'utilisateur change les autres champs --}}
        @if($currentKind)
            <input type="hidden" name="kind" value="{{ $currentKind }}">
        @endif
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Utilisateur</label>
                <select name="user_id" class="filter-select" onchange="this.form.submit()" style="min-width:180px;">
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
                       style="min-width:180px;">
            </div>
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">
                    🔍 Filtrer
                </button>
            </div>
            @if(request()->hasAny(['user_id', 'action', 'kind']))
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <a href="{{ route('admin.audit.logs') }}"
                   style="display:inline-flex;align-items:center;gap:6px;height:38px;padding:0 14px;border-radius:10px;background:var(--surface2);border:1px solid var(--border2);color:var(--text2);font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap"
                   onmouseover="this.style.borderColor='var(--accent)';this.style.color='var(--text)'"
                   onmouseout="this.style.borderColor='var(--border2)';this.style.color='var(--text2)'">
                    ✕ Réinitialiser
                </a>
            </div>
            @endif
        </div>
    </form>
</div>

{{-- ════ LISTE ════════════════════════════════════════════════════ --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔍 Logs d'audit ({{ $logs->total() }})</div>
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
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div class="avatar-circle" style="width:24px; height:24px; font-size:10px;">
                                    {{ strtoupper(substr($log->user->name, 0, 1)) }}
                                </div>
                                <span style="font-weight:500;">{{ $log->user->name }}</span>
                            </div>
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
    <div style="padding:16px;">
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

{{-- resources/views/admin/alertes/partials/alerts-list.blade.php --}}
<div style="padding:12px 18px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
    <span style="font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.73 21a2 2 0 0 1-3.46 0" />
        </svg>
        Alertes
        @if ($totalNonLues > 0)
            <span style="background:#ef4444;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px">{{ $totalNonLues }}</span>
        @endif
    </span>
    <span style="font-size:12px;color:var(--text3)">page {{ $alertes->currentPage() }}/{{ $alertes->lastPage() }}</span>
</div>

@if ($alertes->isEmpty())
    <div style="text-align:center;padding:60px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:14px">🎉</div>
        <div style="font-size:16px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune alerte !</div>
        <div style="font-size:13px">Tout est en ordre. Revenez plus tard.</div>
    </div>
@else
    <div style="display:flex;flex-direction:column">
        @foreach ($alertes as $alerte)
            @php
                $niveauCfg = match ($alerte->niveau) {
                    'danger' => ['c' => '#ef4444', 'bg' => 'rgba(239,68,68,.08)', 'bd' => 'rgba(239,68,68,.2)', 'icon' => '🔴'],
                    'warning' => ['c' => '#f97316', 'bg' => 'rgba(249,115,22,.08)', 'bd' => 'rgba(249,115,22,.2)', 'icon' => '🟠'],
                    default => ['c' => '#3b82f6', 'bg' => 'rgba(59,130,246,.08)', 'bd' => 'rgba(59,130,246,.2)', 'icon' => '🔵'],
                };
                $typeCfg = match ($alerte->type) {
                    'pose' => ['label' => 'Pose OOH', 'c' => '#e8a020', 'bg' => 'rgba(232,160,32,.1)', 'route' => 'admin.pose-tasks.index'],
                    'pige' => ['label' => 'Pige photo', 'c' => '#a855f7', 'bg' => 'rgba(168,85,247,.1)', 'route' => 'admin.piges.index'],
                    'campagne' => ['label' => 'Campagne', 'c' => '#22c55e', 'bg' => 'rgba(34,197,94,.1)', 'route' => 'admin.campaigns.index'],
                    'reservation' => ['label' => 'Réservation', 'c' => '#3b82f6', 'bg' => 'rgba(59,130,246,.1)', 'route' => 'admin.reservations.index'],
                    'panneau' => ['label' => 'Panneau', 'c' => '#6b7280', 'bg' => 'rgba(107,114,128,.1)', 'route' => 'admin.panels.index'],
                    'facture' => ['label' => 'Facture', 'c' => '#f59e0b', 'bg' => 'rgba(245,158,11,.1)', 'route' => 'admin.invoices.index'],
                    default => ['label' => ucfirst($alerte->type ?? 'Système'), 'c' => 'var(--text3)', 'bg' => 'var(--surface2)', 'route' => null],
                };
            @endphp
            <div id="alert-{{ $alerte->id }}" style="display:flex;align-items:flex-start;gap:14px;padding:14px 18px;border-bottom:1px solid var(--border);transition:background .15s;
                {{ !$alerte->is_read ? 'background:' . $niveauCfg['bg'] . '40;border-left:3px solid ' . $niveauCfg['c'] . ';' : '' }}">
                <div style="font-size:18px;flex-shrink:0;margin-top:1px">{{ $niveauCfg['icon'] }}</div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:5px">
                        <span style="font-size:13px;font-weight:700;color:var(--text)">{{ $alerte->title }}</span>
                        @if (!$alerte->is_read)
                            <span style="padding:1px 7px;border-radius:20px;font-size:9px;font-weight:800;background:{{ $niveauCfg['c'] }};color:#fff;text-transform:uppercase;letter-spacing:.4px">Nouveau</span>
                        @endif
                        <span style="padding:1px 8px;border-radius:20px;font-size:9px;font-weight:700;background:{{ $typeCfg['bg'] }};color:{{ $typeCfg['c'] }}">{{ $typeCfg['label'] }}</span>
                    </div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.5;margin-bottom:6px">{{ $alerte->message }}</div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <span style="font-size:11px;color:var(--text3)">{{ $alerte->created_at->diffForHumans() }}</span>
                        @if ($typeCfg['route'])
                            <a href="{{ route($typeCfg['route']) }}" style="font-size:11px;color:{{ $typeCfg['c'] }};text-decoration:none;font-weight:600">Voir le module →</a>
                        @endif
                    </div>
                </div>
                <div style="display:flex;gap:6px;flex-shrink:0;align-items:flex-start">
                    @if (!$alerte->is_read)
                        <button data-id="{{ $alerte->id }}" class="mark-read-btn" style="padding:5px 10px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap">✓ Lu</button>
                    @endif
                    <button data-id="{{ $alerte->id }}" class="delete-alert-btn" style="padding:5px 9px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);color:#ef4444;border-radius:8px;font-size:11px;cursor:pointer">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6" />
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2" />
                        </svg>
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    @if ($alertes->hasPages())
        <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
            {{ $alertes->links() }}
        </div>
    @endif
@endif
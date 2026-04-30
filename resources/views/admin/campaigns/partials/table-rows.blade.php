@forelse($campaigns as $campaign)
@php
    $statusCfg     = $campaign->status->uiConfig();
    $isRunning     = in_array($campaign->status->value, ['actif', 'pose']);
    $daysLeft      = $campaign->daysRemaining();
    $pct           = $isRunning ? $campaign->progressPercent() : 0;
    $endingSoon    = $campaign->isEndingSoon();
    $isNonFacturee = in_array($campaign->status->value, ['actif','pose','termine']) && ($campaign->invoices_count ?? 0) === 0;

    $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#e8a020' : '#22c55e');
@endphp
<tr style="{{ $endingSoon ? 'background:rgba(232,160,32,0.03);' : '' }}">
    <td>
        <a href="{{ route('admin.campaigns.show', $campaign) }}" class="campaign-name">
            {{ $campaign->name }}
        </a>
        @if($endingSoon)
        <div class="days-left" style="color:var(--accent);">⚠️ Dans {{ $daysLeft }} jour(s)</div>
        @endif
    </td>
    <td>
        @if($campaign->client?->trashed())
        <span class="client-deleted">{{ $campaign->client->name ?? '—' }}</span>
        <span class="deleted-badge">Supprimé</span>
        @else
        <a href="{{ route('admin.clients.show', $campaign->client) }}" class="client-link">
            {{ $campaign->client?->name ?? '—' }}
        </a>
        @endif
    </td>
    <td class="date-range">
        {{ $campaign->start_date->format('d/m/Y') }}
        <span>→</span>
        {{ $campaign->end_date->format('d/m/Y') }}
    </td>
    <td class="duration">{{ $campaign->durationHuman() }}</td>
    <td class="text-center">
        <span class="badge-panels">{{ $campaign->panels_count ?? 0 }} 🪧</span>
    </td>
    <td class="amount">
        {{ number_format($campaign->total_amount, 0, ',', ' ') }} <span>FCFA</span>
    </td>
    <td>
        <span class="status-badge" style="background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};border-color:{{ $statusCfg['border'] }}">
            {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
        </span>
        @if($isRunning)
        <div class="progress-bar">
            <div class="progress-fill" style="background:{{ $barColor }}; width:{{ $pct }}%;"></div>
        </div>
        <div class="days-left">{{ number_format($pct, 1, ',', '') }}% écoulé · {{ $daysLeft }}j restants</div>
        @endif
    </td>
    <td>
        @if($isNonFacturee)
        <span class="badge-warning">💰 À facturer</span>
        @elseif(($campaign->invoices_count ?? 0) > 0)
        <span class="badge-success">✅ {{ $campaign->invoices_count }} facture(s)</span>
        @else
        <span class="badge-muted">—</span>
        @endif
    </td>
    <td>
        <div>{{ $campaign->user?->name ?? '—' }}</div>
        <div class="date-small">{{ $campaign->created_at->format('d/m/Y H:i') }}</div>
    </td>
    <td>
        <div class="actions">
            <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn-icon" title="Voir">👁</a>
            @can('update', $campaign)
            <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn-icon" title="Modifier">✏️</a>
            @endcan
            @can('delete', $campaign)
            <button class="btn-icon btn-delete" onclick="openDeleteCampaign({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')" title="Supprimer">🗑</button>
            @endcan
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="10" class="empty-state">
        <div>📋</div>
        <div>Aucune campagne trouvée.</div>
        <div class="empty-action">
            <a href="{{ route('admin.campaigns.create') }}">+ Créer une campagne</a>
        </div>
    </td>
</tr>
@endforelse

<style>
    .badge-warning {
    background: rgba(249,115,22,0.1);
    color: var(--warning);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.badge-success {
    background: rgba(34,197,94,0.1);
    color: var(--success);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}
.badge-muted {
    color: var(--text3);
    font-size: 12px;
}
.client-deleted {
    color: var(--text2);
}
.deleted-badge {
    font-size: 9px;
    margin-left: 4px;
    padding: 1px 4px;
    background: rgba(239,68,68,0.1);
    color: var(--red);
    border-radius: 4px;
}
.date-range {
    font-size: 12px;
    white-space: nowrap;
    color: var(--text2);
}
.date-range span {
    color: var(--text3);
    margin: 0 2px;
}
.duration {
    font-size: 12px;
    color: var(--text3);
    white-space: nowrap;
}
.text-center {
    text-align: center;
}
.date-small {
    font-size: 10px;
    color: var(--text3);
    margin-top: 2px;
}
.empty-state {
    text-align: center;
    padding: 60px;
    color: var(--text3);
}
.empty-state div:first-child {
    font-size: 48px;
    margin-bottom: 12px;
}
.empty-action {
    margin-top: 12px;
}
.empty-action a {
    color: var(--accent);
    text-decoration: none;
}
</style>
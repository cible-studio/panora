@forelse($campaigns as $campaign)
    @include('admin.campaigns.partials.row', ['campaign' => $campaign])
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
.billing-btn {
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    display: inline-block;
    transition: opacity 0.15s, transform 0.1s;
    white-space: nowrap;
    text-align: left;
    line-height: 1.4;
}
.billing-btn:hover { opacity: 0.82; transform: scale(1.03); }
.billing-btn--new {
    background: rgba(249,115,22,0.08);
    color: #f97316;
    border: 1px solid rgba(249,115,22,0.25);
}
</style>
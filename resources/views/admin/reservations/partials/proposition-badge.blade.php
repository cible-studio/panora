@if($reservation->proposition_expires_at?->isPast())
    <span class="badge expired">⏰ Expirée</span>
@elseif($reservation->proposition_viewed_at)
    <span class="badge viewed">👁️ Vue par le client</span>
@else
    <span class="badge sent">📤 Envoyée · En attente</span>
@endif

<style>
.badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge.expired { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge.viewed  { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge.sent    { background: #dbeafe; color: #1e40af; border: 1px solid #bfdbfe; }

[data-theme="dark"] .badge.expired { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
[data-theme="dark"] .badge.viewed  { background: #14532d; color: #bbf7d0; border-color: #166534; }
[data-theme="dark"] .badge.sent    { background: #1e3a8a; color: #bfdbfe; border-color: #1e40af; }
</style>
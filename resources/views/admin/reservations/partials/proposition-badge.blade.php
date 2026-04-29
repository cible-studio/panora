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
    white-space: nowrap;
}

.badge.expired {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.25);
    color: #fca5a5;
}

.badge.viewed {
    background: rgba(34, 197, 94, 0.1);
    border: 1px solid rgba(34, 197, 94, 0.25);
    color: #86efac;
}

.badge.sent {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.25);
    color: #93c5fd;
}
</style>
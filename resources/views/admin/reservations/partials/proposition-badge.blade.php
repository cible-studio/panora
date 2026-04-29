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

/* Mode Light (par défaut) */
.badge.expired {
    background: rgba(220, 38, 38, 0.08);
    border: 1px solid rgba(220, 38, 38, 0.2);
    color: #b91c1c;
}

.badge.viewed {
    background: rgba(22, 163, 74, 0.08);
    border: 1px solid rgba(22, 163, 74, 0.2);
    color: #15803d;
}

.badge.sent {
    background: rgba(37, 99, 235, 0.08);
    border: 1px solid rgba(37, 99, 235, 0.2);
    color: #1d4ed8;
}

/* Mode Dark - basé sur l'attribut data-theme */
[data-theme="dark"] .badge.expired {
    background: rgba(248, 113, 113, 0.12);
    border: 1px solid rgba(248, 113, 113, 0.25);
    color: #fca5a5;
}

[data-theme="dark"] .badge.viewed {
    background: rgba(74, 222, 128, 0.12);
    border: 1px solid rgba(74, 222, 128, 0.25);
    color: #86efac;
}

[data-theme="dark"] .badge.sent {
    background: rgba(96, 165, 250, 0.12);
    border: 1px solid rgba(96, 165, 250, 0.25);
    color: #93c5fd;
}
</style>
{{-- A1 §spec — Bandeau orange "événement live" affiché par le JS quand un
     événement nouveau arrive (photo envoyée, signalement, etc.) sur le
     polling 20s. Auto-hide après 30s. --}}
<aside class="live-event-banner" data-event-banner hidden>
    <span class="live-event-pulse" aria-hidden="true"></span>
    <div class="live-event-body">
        <strong class="live-event-label" data-field="event-label">—</strong>
        <span class="live-event-detail" data-field="event-detail">—</span>
    </div>
    <a class="live-event-cta" data-field="event-cta" href="#" hidden>Voir →</a>
    <button type="button" class="live-event-close" data-action="dismiss-event" aria-label="Fermer">✕</button>
</aside>

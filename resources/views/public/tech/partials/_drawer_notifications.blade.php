{{-- _drawer_notifications.blade.php — SM2c B3 spec §5.
     Drawer du centre de notifications. Affichage piloté par
     features/sm2c.js qui poll tech.space.notifications (60s).
     Le bouton aide "?" affiche un badge rouge si notifs non lues +
     ouvre B3 au lieu de T8 dans ce cas. --}}
<div id="sm2c-b3-overlay" class="sm2c-b3-overlay" hidden aria-hidden="true">
    <aside class="sm2c-b3-drawer" role="dialog" aria-modal="true">
        <header class="sm2c-b3-head">
            <h2 class="sm2c-b3-title">🔔 Mes notifications</h2>
            <button type="button" class="sm2c-b3-close" data-action="close-b3" aria-label="Fermer">✕</button>
        </header>

        <div class="sm2c-b3-filters">
            <button type="button" class="sm2c-b3-filter is-active" data-b3-filter="all">Toutes</button>
            <button type="button" class="sm2c-b3-filter" data-b3-filter="rejects">🚫 Refusées</button>
            <button type="button" class="sm2c-b3-filter" data-b3-filter="newposes">🆕 Nouvelles poses</button>
        </div>

        <ul class="sm2c-b3-list" data-field="b3-list"></ul>

        <div class="sm2c-b3-empty" data-field="b3-empty" hidden>
            <div class="sm2c-b3-empty-icon" aria-hidden="true">🌞</div>
            <div>Rien à signaler ! Continue comme ça.</div>
        </div>

        <footer class="sm2c-b3-foot">
            <button type="button" class="sm2c-b3-mark-all" data-action="b3-mark-all">
                ✓ Tout marquer comme lu
            </button>
        </footer>
    </aside>
</div>

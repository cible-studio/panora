{{-- _modal_off_schedule.blade.php — SM2c B1 spec §5.
     Modale "Tu commences cette pose tôt / tard" qui s'affiche au-dessus
     du drawer T2 quand le tech ouvre une pose hors créneau (tolérance
     60 min par défaut, configurable via tech_space.schedule_tolerance).

     Le partial est rendu UNE fois dans tech-space.blade.php et
     show/hidden via class is-open. Piloté par features/off-schedule.js
     qui calcule la tolérance au tap sur une .pose-line + lit/écrit
     localStorage `off_schedule_ack_{taskId}` pour éviter de re-demander
     la même pose dans la même session. --}}
<div id="sm2c-b1-overlay" class="sm2c-b1-overlay" hidden aria-hidden="true">
    <div class="sm2c-b1-modal" role="dialog" aria-modal="true" aria-labelledby="sm2c-b1-title">
        <div class="sm2c-b1-icon" aria-hidden="true">⏰</div>
        <h2 id="sm2c-b1-title" class="sm2c-b1-title" data-field="title">Tu commences cette pose hors créneau</h2>
        <p class="sm2c-b1-sub" data-field="sub">—</p>
        <div class="sm2c-b1-actions">
            <button type="button" class="sm2c-b1-btn sm2c-b1-btn-cancel" data-action="b1-cancel">
                ← Non, je reviens
            </button>
            <button type="button" class="sm2c-b1-btn sm2c-b1-btn-confirm" data-action="b1-confirm">
                ✓ Oui, je continue
            </button>
        </div>
    </div>
</div>

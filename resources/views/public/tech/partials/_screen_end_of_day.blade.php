{{-- _screen_end_of_day.blade.php — SM2c B2 spec §5.
     Plein écran de félicitations affiché quand le tech termine sa
     dernière pose du jour. Animation confettis pure CSS (20 particules
     position absolute + keyframes confetti-fall). Pas de boucle infinie.

     Piloté par features/upload.js qui vérifie response.is_last_pose_of_day
     dans showSuccessScreenT4. Si true → affiche B2 au lieu de T4. --}}
<div id="sm2c-b2-overlay" class="sm2c-b2-overlay" hidden aria-hidden="true">
    {{-- 24 particules confettis générées en HTML — chacune avec un delay
         aléatoire posé via style inline pour étaler la chute sur 3s. --}}
    @for($i = 0; $i < 24; $i++)
        @php
            $left  = random_int(0, 100);
            $delay = round(mt_rand(0, 2500) / 1000, 1);
            $hue   = [16, 250, 145, 50, 270][$i % 5]; // alterne 5 teintes
            $size  = random_int(6, 12);
        @endphp
        <span class="sm2c-b2-confetti"
              style="left:{{ $left }}%;animation-delay:{{ $delay }}s;background:hsl({{ $hue * 7 }}deg,80%,55%);width:{{ $size }}px;height:{{ $size }}px"></span>
    @endfor

    <div class="sm2c-b2-content">
        <div class="sm2c-b2-trophy" aria-hidden="true">🎉</div>
        <h1 class="sm2c-b2-title">Bravo <span data-field="b2-first-name">!</span></h1>
        <p class="sm2c-b2-sub">Tu as fini toutes tes poses du jour</p>

        <div class="sm2c-b2-stats">
            <div class="sm2c-b2-stat">
                <div class="sm2c-b2-stat-value" data-field="b2-total">—</div>
                <div class="sm2c-b2-stat-label">PANNEAUX POSÉS</div>
            </div>
            <div class="sm2c-b2-stat">
                <div class="sm2c-b2-stat-value" data-field="b2-duration">—</div>
                <div class="sm2c-b2-stat-label">DURÉE TOTALE</div>
            </div>
            <div class="sm2c-b2-stat">
                <div class="sm2c-b2-stat-value" data-field="b2-rate">—</div>
                <div class="sm2c-b2-stat-label">POSES / HEURE</div>
            </div>
        </div>

        <div class="sm2c-b2-actions">
            <button type="button" class="sm2c-b2-btn sm2c-b2-btn-primary" data-action="b2-home">
                🏠 Retour à l'accueil
            </button>
            <button type="button" class="sm2c-b2-btn sm2c-b2-btn-ghost" data-action="b2-request">
                📤 Demander une nouvelle tournée
            </button>
        </div>
    </div>
</div>

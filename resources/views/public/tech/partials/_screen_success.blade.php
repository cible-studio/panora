{{-- _screen_success.blade.php — SM2a Lot 3.2.
     Écran T4 "Succès + pose suivante" (spec §3 T4). Plein écran vert
     après l'envoi réussi d'une photo. Auto-disparaît au bout de 4s ou
     sur action utilisateur.

     Pas d'inline JS — piloté par features/upload.js qui :
       1. Capture les infos de la pose suivante AVANT que la card
          courante soit retirée du DOM
       2. Peuple les data-field (firstName, count, next pose, etc.)
       3. Au tap "Continuer" → ouvre le drawer T2 sur la pose suivante
       4. Au tap "Choisir une autre" OU expiration 4s → ferme silencieusement

     Variables consommées : aucune (shell vide). --}}
<div id="sm2-t4-overlay" class="sm2-t4-overlay" hidden aria-hidden="true">
    <div class="sm2-t4-panel" role="dialog" aria-modal="true" aria-labelledby="sm2-t4-title">
        {{-- Bandeau hero vert plein écran avec check géant + nom du tech --}}
        <div class="sm2-t4-hero">
            <div class="sm2-t4-check" aria-hidden="true">
                <svg viewBox="0 0 52 52" width="56" height="56" fill="none" stroke-width="5"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="26" cy="26" r="22" stroke="#fff" opacity=".4"/>
                    <path d="M14 27l8 8 16-16" stroke="#fff"/>
                </svg>
            </div>
            <h1 id="sm2-t4-title" class="sm2-t4-title">
                Bravo <span data-field="first-name">!</span>
            </h1>
            <div class="sm2-t4-sub">Photo bien envoyée</div>
            <div class="sm2-t4-progress">
                <strong data-field="done-count">—</strong> / <span data-field="total-count">—</span> panneaux faits
            </div>
        </div>

        {{-- Section "pose suivante" : visible s'il reste au moins une
             pose à faire. Sinon : message de fin de tournée. --}}
        <div class="sm2-t4-next" data-field="next-block" hidden>
            <div class="sm2-t4-next-label">POSE SUIVANTE</div>
            <div class="sm2-t4-next-card">
                <span class="sm2-t4-next-thumb" data-field="next-thumb">🪧</span>
                <div class="sm2-t4-next-info">
                    <div class="sm2-t4-next-ref" data-field="next-ref">—</div>
                    <div class="sm2-t4-next-name" data-field="next-name">—</div>
                    <div class="sm2-t4-next-meta">
                        <span data-field="next-commune">—</span>
                    </div>
                </div>
            </div>
            <button type="button" class="sm2-t4-btn sm2-t4-btn-go" data-action="t4-continue">
                → Continuer avec celle-ci
            </button>
            <button type="button" class="sm2-t4-btn sm2-t4-btn-ghost" data-action="t4-other">
                📋 Choisir une autre
            </button>
        </div>

        {{-- Cas fin de journée : aucune pose restante --}}
        <div class="sm2-t4-done" data-field="done-block" hidden>
            <div class="sm2-t4-done-icon" aria-hidden="true">🎉</div>
            <div class="sm2-t4-done-title">Tu as fini toutes tes poses du jour</div>
            <button type="button" class="sm2-t4-btn sm2-t4-btn-go" data-action="t4-other">
                🏠 Revenir à l'accueil
            </button>
        </div>

        {{-- 2026-07-08 (feedback patronne) : bouton "Retour" TOUJOURS
             visible pour que le tech puisse fermer manuellement l'écran
             de succès sans attendre le reload auto (4.5s). Utile surtout
             sur iOS où l'écran peut sembler figé. --}}
        <button type="button" class="sm2-t4-close-btn" data-action="t4-other"
                aria-label="Fermer et revenir à la liste">
            ← Retour à la liste
        </button>
    </div>
</div>

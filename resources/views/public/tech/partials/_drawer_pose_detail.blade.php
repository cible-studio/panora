{{-- _drawer_pose_detail.blade.php — SM2a Lot 2.1A.
     Drawer T2 "Détail d'une pose" (spec §3 T2). S'ouvre par-dessus le
     carnet T1 quand le tech tape sur une ligne de pose ou via le
     paramètre URL `?focus=task_X` (Lot 2.1B — TechUrlResolverService).

     Architecture délibérée :
       - Le markup est UN SHELL VIDE. Le contenu (réf, nom, commune,
         thumbnail, dates) est rempli côté JS par features/pose-drawer.js
         depuis le DOM de la card sélectionnée. Aucune requête réseau,
         tout est lu sur place.
       - Le wrapper #sm2-t2-drawer porte data-task-id ET data-task-status
         dynamiquement. Les boutons internes ont les MÊMES data-action
         que ceux du _pose_card existant → les handlers JS de upload.js
         / status-changes.js / report.js fonctionnent SANS modification
         via event delegation + closest('[data-task-id]').
       - Le partial est inclus une seule fois (en bas de tech-space)
         et persiste dans le DOM ; show/hide via la classe is-open. --}}
<div id="sm2-t2-overlay" class="sm2-t2-overlay" hidden aria-hidden="true">
    <aside id="sm2-t2-drawer" class="sm2-t2-drawer"
           role="dialog" aria-modal="true" aria-labelledby="sm2-t2-title"
           data-task-id="">
        <header class="sm2-t2-head">
            <button type="button" class="sm2-t2-back" data-action="close-detail" aria-label="Revenir au carnet">
                <span aria-hidden="true">←</span> Retour
            </button>
            <button type="button" class="sm2-t2-close" data-action="close-detail" aria-label="Fermer">✕</button>
        </header>

        <div class="sm2-t2-body">
            <div class="sm2-t2-pill" data-field="commune">—</div>
            <h2 id="sm2-t2-title" class="sm2-t2-name" data-field="name">—</h2>
            <div class="sm2-t2-ref" data-field="ref">—</div>

            <figure class="sm2-t2-photo-wrap">
                <img class="sm2-t2-photo" data-field="photo" alt="Photo de référence du panneau">
                <span class="sm2-t2-photo-fallback" data-field="photo-fallback" aria-hidden="true">🪧</span>
            </figure>

            {{-- Bandeau jaune d'aide contextuelle. Affiché si le tech est
                 marqué "débutant" via localStorage tech_first_use (flag
                 géré par features/help.js Phase 5). Pour l'instant : caché
                 par défaut, sera activé en Phase 5. --}}
            <div class="sm2-t2-help" data-field="help-banner" hidden>
                <span aria-hidden="true">💡</span>
                <span>Touche le bouton orange pour ouvrir la caméra. Vert pour ouvrir Google Maps.</span>
            </div>

            {{-- ══════════════════════════════════════════════════════════
                 3 BOUTONS CHRONOLOGIQUES + CHRONO EN DIRECT (2026-07-07)
                 Refonte demandée par la patronne : remplacement de la
                 barre de 5 paliers par 3 jalons clairs + timer live.
                 Chaque bouton pose un jalon en BDD :
                   Y aller (existant)  → started_at + status en_route
                   Je suis arrivé      → arrived_at + status en_cours
                   Photo (existant)    → done_at + status realisee
                 ══════════════════════════════════════════════════════════ --}}
            <div class="sm2-t2-actions">
                <a class="sm2-t2-btn sm2-t2-btn-go" data-go-maps href="#" target="_blank" rel="noopener">
                    🗺️ Y aller en voiture
                </a>
                <button type="button" class="sm2-t2-btn sm2-t2-btn-arrived" data-action="mark-arrived">
                    📍 Je suis arrivé sur place
                </button>
                <label class="sm2-t2-btn sm2-t2-btn-cam" data-action="photo">
                    <input type="file" accept="image/*" capture="environment" data-photo-input>
                    📷 Photo (fin de pose)
                </label>
                <button type="button" class="sm2-t2-btn sm2-t2-btn-warn" data-action="report">
                    ⚠ Il y a un problème
                </button>
            </div>

            {{-- Panneau chrono en direct — étape actuelle + temps écoulé.
                 Mis à jour toutes les 5 secondes par pose-drawer.js. --}}
            <div class="sm2-t2-timer" data-field="timer-panel">
                <div class="sm2-t2-timer-stage" data-field="timer-stage">
                    ⏳ Prêt à démarrer
                </div>
                <div class="sm2-t2-timer-value" data-field="timer-value">
                    Touche « Y aller en voiture » pour démarrer.
                </div>
            </div>
        </div>
    </aside>
</div>

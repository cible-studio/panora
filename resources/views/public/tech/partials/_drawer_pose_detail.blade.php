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

            <div class="sm2-t2-actions">
                <a class="sm2-t2-btn sm2-t2-btn-go" data-go-maps href="#" target="_blank" rel="noopener">
                    🗺️ Y aller en voiture
                </a>
                <label class="sm2-t2-btn sm2-t2-btn-cam" data-action="photo">
                    <input type="file" accept="image/*" capture="environment" data-photo-input>
                    📷 Je suis arrivé → photo
                </label>
                <button type="button" class="sm2-t2-btn sm2-t2-btn-warn" data-action="report">
                    ⚠ Il y a un problème
                </button>
            </div>

            {{-- ══════════════════════════════════════════════════════════
                 BARRE DE PROGRESSION MANUELLE — 5 paliers (2026-07-06)
                 Feature demandée par la patronne : le tech marque où il
                 en est pendant la pose (25/50/75%) et l'admin voit la
                 progression temps réel dans le pilotage.
                 Un tap sur un palier → POST AJAX vers l'endpoint
                 tech.space.progress → progress_percent mis à jour + le
                 statut auto-sync si sauté (25→en_route, 50→en_cours).
                 ══════════════════════════════════════════════════════════ --}}
            <div class="sm2-t2-progress" data-field="progress-panel">
                <div class="sm2-t2-progress-head">
                    <span class="sm2-t2-progress-title">📊 Où tu en es</span>
                    <span class="sm2-t2-progress-value" data-field="progress-percent">0%</span>
                </div>
                <div class="sm2-t2-progress-bar" role="group" aria-label="Choisis ton avancement">
                    <button type="button" class="sm2-t2-progress-step" data-progress="0">
                        <span class="sm2-t2-progress-dot">◯</span>
                        <span class="sm2-t2-progress-label">0%<br><small>Pas commencé</small></span>
                    </button>
                    <button type="button" class="sm2-t2-progress-step" data-progress="25">
                        <span class="sm2-t2-progress-dot">◯</span>
                        <span class="sm2-t2-progress-label">25%<br><small>En route</small></span>
                    </button>
                    <button type="button" class="sm2-t2-progress-step" data-progress="50">
                        <span class="sm2-t2-progress-dot">◯</span>
                        <span class="sm2-t2-progress-label">50%<br><small>Arrivé</small></span>
                    </button>
                    <button type="button" class="sm2-t2-progress-step" data-progress="75">
                        <span class="sm2-t2-progress-dot">◯</span>
                        <span class="sm2-t2-progress-label">75%<br><small>Collage</small></span>
                    </button>
                    <button type="button" class="sm2-t2-progress-step" data-progress="100">
                        <span class="sm2-t2-progress-dot">◯</span>
                        <span class="sm2-t2-progress-label">100%<br><small>Fini</small></span>
                    </button>
                </div>
                <div class="sm2-t2-progress-hint" data-field="progress-hint">
                    Touche un palier pour dire à l'admin où tu en es.
                </div>
            </div>
        </div>
    </aside>
</div>

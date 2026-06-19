{{-- _modal_y_aller.blade.php — SM2a Lot 2.2.
     Modale T7 "Confirmation Y aller" (spec §3 T7). S'ouvre avant
     l'ouverture de Google Maps : montre une mini-carte stylisée + une
     estimation distance/temps + confirmation. Le tech valide ou annule.

     Pas d'inline JS — piloté par features/y-aller-modal.js qui :
       1. Intercepte les clics sur [data-go-maps] (carnet T1 et drawer T2)
       2. Peuple les data-field avec les infos de la pose ciblée
       3. Calcule distance haversine côté JS si GPS dispo
       4. Au "Ouvrir Google Maps" → re-clique sur le bouton original
          en bypass de l'intercepteur → handlers existants fire (bump
          status en_route + ouverture du lien).

     Variables consommées : aucune (shell vide). --}}
<div id="sm2-t7-overlay" class="sm2-t7-overlay" hidden aria-hidden="true">
    <div class="sm2-t7-modal" role="dialog" aria-modal="true" aria-labelledby="sm2-t7-title">
        <header class="sm2-t7-head">
            <button type="button" class="sm2-t7-cancel" data-action="t7-cancel" aria-label="Annuler">
                ✗ <span>Annuler</span>
            </button>
        </header>

        <div class="sm2-t7-body">
            <h2 id="sm2-t7-title" class="sm2-t7-title">Je t'emmène là-bas</h2>
            <div class="sm2-t7-sub" data-field="pose-name">—</div>

            {{-- Mini-carte stylisée (PAS Leaflet, juste un visuel) :
                 dégradé bleu + 2 badges + ligne pointillée SVG. Cf. spec
                 T7 §3 — "vignette dégradée bleu clair". --}}
            <div class="sm2-t7-map" aria-hidden="true">
                <svg class="sm2-t7-line" viewBox="0 0 200 100" preserveAspectRatio="none">
                    <path d="M20 20 Q 100 50 180 80" stroke="#2563eb" stroke-width="2"
                          stroke-dasharray="5 5" fill="none" stroke-linecap="round"/>
                </svg>
                <span class="sm2-t7-badge sm2-t7-badge-you">
                    <span class="sm2-t7-badge-dot" style="background:#2563eb"></span>
                    Toi
                </span>
                <span class="sm2-t7-badge sm2-t7-badge-panel">
                    <span aria-hidden="true">📍</span>
                    Panneau
                </span>
            </div>

            <div class="sm2-t7-stats">
                <div class="sm2-t7-stat">
                    <div class="sm2-t7-stat-value" data-field="time-est">—</div>
                    <div class="sm2-t7-stat-label">À PIED</div>
                </div>
                <div class="sm2-t7-stat">
                    <div class="sm2-t7-stat-value" data-field="dist-est">—</div>
                    <div class="sm2-t7-stat-label">DISTANCE</div>
                </div>
            </div>

            <a class="sm2-t7-btn sm2-t7-btn-primary" data-action="t7-confirm" href="#">
                🗺️ Ouvrir Google Maps
            </a>
            <button type="button" class="sm2-t7-btn sm2-t7-btn-ghost" data-action="t7-cancel">
                ← Je connais le chemin, retour
            </button>
        </div>
    </div>
</div>

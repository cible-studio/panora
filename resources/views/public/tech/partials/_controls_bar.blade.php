{{-- _controls_bar.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Barre de contrôles sticky : recherche Select2 AJAX paginée + boutons
     Carte / Près de moi / Mon chemin / Papier / Badge sync offline.

     - Select2 init côté JS bloc 2 (lignes ~2492). dropdownParent référence
       `.controls-bar` — préserver le wrapper exact.
     - `id="ts-distance-btn"`, `id="ts-tour-btn"`, `id="ts-sync-badge"` :
       ids consommés par le JS (ne PAS les changer).

     Variables passées via @include :
       - $token (pour les routes Carte + Papier). --}}
<div class="controls-bar">
    <div class="controls-bar-row">
        <select id="ts-search" data-placeholder="🔍 Cherche un panneau, une rue, une ville…"></select>
        <a class="ctrl-btn" href="{{ route('tech.space.map', $token) }}" title="Voir tous les panneaux sur une carte">
            🗺<span style="margin-left:2px;font-size:11px">Carte</span>
        </a>
        <button type="button" class="ctrl-btn" id="ts-distance-btn" title="Voir les panneaux les plus proches de moi en premier">
            📍<span style="margin-left:2px;font-size:11px" id="ts-distance-label">Près de moi</span>
        </button>
        <button type="button" class="ctrl-btn" id="ts-tour-btn" title="Calculer le meilleur ordre pour visiter tous les panneaux">
            🚀<span style="margin-left:2px;font-size:11px" id="ts-tour-label">Mon chemin</span>
        </button>
        <a class="ctrl-btn" href="{{ route('tech.space.route-sheet', $token) }}" target="_blank" rel="noopener" title="Liste à imprimer ou à garder sur le téléphone">
            🖨<span style="margin-left:2px;font-size:11px">Papier</span>
        </a>
        <span class="ctrl-btn" id="ts-sync-badge" style="display:none;background:rgba(245,158,11,.15);color:#b45309;border-color:rgba(245,158,11,.4);cursor:pointer" title="Photos à envoyer dès que tu as du réseau">
            📤<span style="margin-left:2px;font-size:11px" id="ts-sync-count">0</span>
        </span>
    </div>
</div>

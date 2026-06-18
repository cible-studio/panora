{{-- _filters_chips.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Chips de filtrage rapide + empty state quand aucune card ne matche.

     État géré côté JS (bloc 2 du <script>, lignes 2315-2456 environ) :
       - filterState global, combinable AND
       - Lecture/écriture URL (?late=1&today=1&...) pour bookmark / share
       - Compteurs live mis à jour selon les cards SSR matchant
       - Bouton "Tout voir" visible si ≥ 1 filtre actif

     Aucune variable Blade consommée — chips purement statiques. --}}
<div class="filters-row" id="ts-filters">
    <button type="button" class="filter-chip" data-filter="late">
        <span>⏰</span> En retard <span class="chip-count" data-cnt="late">0</span>
    </button>
    <button type="button" class="filter-chip" data-filter="today">
        <span>📅</span> Aujourd'hui <span class="chip-count" data-cnt="today">0</span>
    </button>
    <button type="button" class="filter-chip" data-filter="problem">
        <span>⚠️</span> Avec souci <span class="chip-count" data-cnt="problem">0</span>
    </button>
    <button type="button" class="filter-chip" data-filter="reject">
        <span>🚫</span> Photo à refaire <span class="chip-count" data-cnt="reject">0</span>
    </button>
    <button type="button" class="filter-chip" data-filter="en_route" data-filter-kind="status">
        <span>🚗</span> En route <span class="chip-count" data-cnt="en_route">0</span>
    </button>
    <button type="button" class="filter-chip" data-filter="en_cours" data-filter-kind="status">
        <span>🔧</span> Sur place <span class="chip-count" data-cnt="en_cours">0</span>
    </button>
    <button type="button" class="filter-clear" id="ts-filter-clear" style="display:none">Tout voir</button>
</div>

<div id="ts-empty-filter"
     style="display:none;margin:14px 0;padding:18px;text-align:center;color:var(--text3);background:var(--surface);border:1px dashed var(--border);border-radius:12px;font-size:13px">
    Aucun panneau ne correspond. Touche « Tout voir » pour effacer le filtre.
</div>

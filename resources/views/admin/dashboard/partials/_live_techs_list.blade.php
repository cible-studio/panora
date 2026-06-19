{{-- A1 §spec — Liste "Techs en activité (N)". Le titre + le contenu sont
     entièrement rendus côté JS depuis le payload. Squelette minimal ici. --}}
<section class="live-techs">
    <header class="live-techs-head">
        <h2 class="live-techs-title">
            Techs en activité <span class="live-techs-count" data-field="techs-count">(0)</span>
        </h2>
        <a href="{{ route('admin.pilotage.map') }}" class="live-techs-map-link" hidden>🗺️ Carte live</a>
    </header>

    <div class="live-techs-empty" data-field="techs-empty">
        <div class="live-techs-empty-icon" aria-hidden="true">😴</div>
        <div class="live-techs-empty-text">Aucun tech en ligne pour l'instant.</div>
    </div>

    <ul class="live-techs-list" data-field="techs-list" hidden>
        {{-- Lignes injectées par JS. Template d'une ligne : --}}
        <template data-field="tech-row-tpl">
            <li class="live-tech-row">
                <div class="live-tech-avatar" data-field="tech-initials">??</div>
                <div class="live-tech-info">
                    <div class="live-tech-name" data-field="tech-name">—</div>
                    <div class="live-tech-status" data-field="tech-status">—</div>
                </div>
                <div class="live-tech-progress">
                    <div class="live-tech-progress-text" data-field="tech-progress">0/0</div>
                    <div class="live-tech-progress-bar">
                        <div class="live-tech-progress-fill" data-field="tech-progress-fill" style="width:0%"></div>
                    </div>
                </div>
                <div class="live-tech-seen" data-field="tech-seen">—</div>
                <a class="live-tech-open" data-field="tech-open" href="#">Voir →</a>
            </li>
        </template>
    </ul>
</section>

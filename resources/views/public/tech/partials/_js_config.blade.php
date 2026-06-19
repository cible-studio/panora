{{-- _js_config.blade.php — Phase 3 SM1.
     Publie window.TECH_CONFIG AVANT le chargement des modules JS extraits
     dans public/js/tech/. Pont entre le contexte Blade (csrf, token, routes,
     labels DelayReason) et le code JS modulaire qui n'y a plus accès direct.

     Variables Blade consommées :
       - $token (tech_public_token, 32 chars)

     Routes avec ID variable (POST /poses/{id}/{action}) : on publie le
     template avec __TASK__ comme placeholder remplacé côté JS :
        const url = TECH_CONFIG.routes.uploadPhoto.replace('__TASK__', taskId);
     Cohérent avec le pattern recommandé en brief Phase 3. --}}
<script>
    window.TECH_CONFIG = {
        csrfToken: @json(csrf_token()),
        techToken: @json($token),
        routes: {
            heartbeat:   @json(route('tech.space.heartbeat',   ['token' => $token])),
            search:      @json(route('tech.space.search',      ['token' => $token])),
            routeSheet:  @json(route('tech.space.route-sheet', ['token' => $token])),
            map:         @json(route('tech.space.map',         ['token' => $token])),
            optimize:    @json(route('tech.space.optimize',    ['token' => $token])),
            piges:       @json(route('tech.space.piges',       ['token' => $token])),
            {{-- SM2c B3 : centre de notifications tech --}}
            notifications:         @json(route('tech.space.notifications',          ['token' => $token])),
            notificationsMarkRead: @json(route('tech.space.notifications.mark-read', ['token' => $token])),
            {{-- Routes paramétrées : __TASK__ remplacé côté JS au moment de l'appel --}}
            statusTpl:   @json(route('tech.space.status', ['token' => $token, 'task' => '__TASK__'])),
            photoTpl:    @json(route('tech.space.photo',  ['token' => $token, 'task' => '__TASK__'])),
            reportTpl:   @json(route('tech.space.report', ['token' => $token, 'task' => '__TASK__'])),
        },
        {{-- Labels DelayReason FR (utilisés par le bandeau "déjà signalé" en JS) --}}
        motifLabels: @json(collect(\App\Enums\DelayReason::cases())->mapWithKeys(fn($m) => [$m->value => $m->label()])),
        {{-- SM2a Phase 5 : contacts pour la modale T8 "Besoin d'aide ?".
             Valeurs servies par config si dispo, sinon null (les boutons
             correspondants sont automatiquement masqués par features/help.js). --}}
        contacts: {
            chiefPhone:       @json(config('tech_space.chief_phone', null)),
            tutorialVideoUrl: @json(config('tech_space.tutorial_url', null)),
        },
        bootstrap: {
            heartbeatInterval: 20000,
            ssrCap: {{ (int) config('tech_space.ssr_cap', 200) }},
        },
    };
</script>

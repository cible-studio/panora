{{-- ═══════════════════════════════════════════════════════════════════
     Helper JS partagé — filtre le select "technicien" en fonction du
     select "équipe" sélectionné.

     Contexte (2026-08-10) : après la refonte pose_team_id, quand un MP
     attribue une pose à une équipe, il ne doit pouvoir choisir COMME
     tech assigné qu'un membre de cette équipe (sinon incohérence :
     pose créditée à EQUIPE TEST mais physiquement faite par un tech
     d'une autre équipe). Cache/montre les <option> du select tech en
     temps réel au change du select équipe.

     Usage — inclus dans une @push('scripts') :
       @include('admin.partials._filter_tech_by_team', [
           'selTeamId'     => 'sel-team',
           'selTechId'     => 'sel-technicien',
           'membersByTeam' => $membersByTeam,
           'hintId'        => 'tech-filter-hint',  // optionnel — div pour message
       ])

     Comportement :
       - team_id vide → tous les techs visibles (aucun filtre)
       - team_id renseigné → seuls les membres visibles
       - Si tech déjà sélectionné n'est pas membre → reset à "" (Non
         assigné) + affichage du hint (si hintId fourni)

     Idempotent : plusieurs @include sur la même page = plusieurs paires
     séparées, chacune avec ses propres IDs.

     Note DX : window.PanoraFilterTechByTeam est exposée globalement pour
     être réutilisable dans du JS ad-hoc (ex: initialisation après
     ouverture d'un modal). ═══════════════════════════════════════ --}}
<script>
(function () {
    'use strict';
    // Enregistre l'instance sur window pour réutilisation.
    window.PanoraFilterTechByTeam = window.PanoraFilterTechByTeam || function (opts) {
        var selTeam = document.getElementById(opts.selTeamId);
        var selTech = document.getElementById(opts.selTechId);
        var hint    = opts.hintId ? document.getElementById(opts.hintId) : null;
        if (!selTeam || !selTech) return;

        var membersByTeam = opts.membersByTeam || {};

        function apply() {
            var teamId = selTeam.value ? String(selTeam.value) : '';
            var allowed = teamId && membersByTeam[teamId]
                ? membersByTeam[teamId].map(String)
                : null; // null = pas de filtre

            var techCurrent = selTech.value ? String(selTech.value) : '';
            var techStillValid = true;

            Array.from(selTech.options).forEach(function (opt) {
                if (opt.value === '' || opt.value === '__unset__' || opt.value === '0') {
                    opt.hidden = false;
                    opt.disabled = false;
                    return;
                }
                var isMember = allowed === null || allowed.indexOf(String(opt.value)) !== -1;
                opt.hidden = !isMember;
                opt.disabled = !isMember;
                if (!isMember && opt.value === techCurrent) techStillValid = false;
            });

            // Reset si le tech courant n'est plus dans la liste filtrée.
            if (!techStillValid) {
                selTech.value = '';
                if (hint) {
                    hint.textContent = '↻ Technicien réinitialisé — il n\'était pas membre de l\'équipe sélectionnée.';
                    hint.style.display = 'block';
                    hint.style.color = '#0ea5e9';
                    setTimeout(function () { hint.style.display = 'none'; }, 5000);
                }
            } else if (hint && allowed !== null) {
                hint.textContent = '👥 Filtré aux ' + allowed.length + ' membre(s) de cette équipe.';
                hint.style.display = 'block';
                hint.style.color = 'var(--text3)';
            } else if (hint) {
                hint.style.display = 'none';
            }
        }

        selTeam.addEventListener('change', apply);
        apply(); // initialisation au chargement
    };

    // Auto-init pour cette inclusion précise
    window.PanoraFilterTechByTeam({
        selTeamId:     @json($selTeamId),
        selTechId:     @json($selTechId),
        membersByTeam: @json($membersByTeam),
        hintId:        @json($hintId ?? null),
    });
})();
</script>

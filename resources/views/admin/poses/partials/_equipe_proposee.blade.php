{{-- ═══════════════════════════════════════════════════════════════════
     Équipe PROPOSÉE d'après le technicien — formulaires créer / modifier
     une pose. Source unique de ce comportement (avant : une copie du
     script dans chaque formulaire).

     Règle validée le 2026-11-04 (option « b ») :
       · choisir un technicien membre d'UNE seule équipe propose cette
         équipe dans le select, avec une ligne d'aide visible ;
       · un choix fait à la main dans le select Équipe, « Aucune (pose
         solo) » compris, n'est plus jamais écrasé ;
       · une proposition qui ne colle plus (autre technicien, sans équipe
         ou avec plusieurs) est retirée : le select revient à sa valeur
         de départ ;
       · le serveur, lui, ne choisit plus jamais l'équipe (cf. PoseTask).
     Avant, l'équipe remplaçait tout choix, y compris « Aucune », et le
     serveur créditait l'équipe en silence : un technicien membre d'une
     seule équipe ne pouvait plus faire de pose solo.

     La proposition ne déclenche pas « change » sur le select Équipe :
     le filtre des techniciens (_filter_tech_by_team) ne s'applique donc
     qu'à un choix manuel. Elle émet « equipe-proposee » quand la valeur
     change, pour qui doit réagir (alerte piges du formulaire Modifier).

     Usage — dans un @push('scripts') :
       @include('admin.poses.partials._equipe_proposee', [
           'selTechId' => 'sel-technicien',   // porte data-team-by-user
           'selTeamId' => 'sel-team',
           'hintId'    => 'team-auto-hint',
       ])
     ═══════════════════════════════════════════════════════════════════ --}}
<script>
(function () {
    var selTech = document.getElementById(@json($selTechId));
    var selTeam = document.getElementById(@json($selTeamId));
    var hint    = document.getElementById(@json($hintId));
    if (!selTech || !selTeam) return;

    // { user_id: [{ id, name }, …] } — cf. PoseController create/edit.
    var teamByUser = {};
    try { teamByUser = JSON.parse(selTech.dataset.teamByUser || '{}'); } catch (e) {}

    var depart   = selTeam.value;   // édition : l'équipe enregistrée
    var choisie  = false;           // choix manuel dans ce formulaire
    var proposee = false;           // la valeur affichée vient d'une proposition

    function aide(texte) {
        if (!hint) return;
        hint.textContent = texte;
        hint.style.display = texte ? 'block' : 'none';
    }

    selTeam.addEventListener('change', function () {
        choisie  = true;
        proposee = false;
        aide('');
    });

    selTech.addEventListener('change', function () {
        if (choisie) return;

        var equipes = teamByUser[selTech.value];
        var equipe  = Array.isArray(equipes) && equipes.length === 1 ? equipes[0] : null;
        var option  = equipe
            ? Array.from(selTeam.options).find(function (o) { return o.value === String(equipe.id); })
            : null;
        var avant = selTeam.value;

        if (option) {
            selTeam.value = option.value;
            proposee = true;
            var nom = (selTech.options[selTech.selectedIndex] || {}).text || 'Ce technicien';
            aide('👥 Proposée : ' + nom.trim() + ' est membre de cette équipe. « Aucune » = pose solo.');
        } else {
            if (proposee) selTeam.value = depart;
            proposee = false;
            aide('');
        }

        if (selTeam.value !== avant) {
            selTeam.dispatchEvent(new CustomEvent('equipe-proposee'));
        }
    });
})();
</script>

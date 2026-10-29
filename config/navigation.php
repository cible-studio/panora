<?php

/*
|--------------------------------------------------------------------------
| Navigation — retour intelligent
|--------------------------------------------------------------------------
|
| SOURCE UNIQUE de la navigation arrière (2026-10-29).
|
| Avant cette date, la même carte était recopiée à QUATRE endroits :
| le partial _smart_back, les vues teams/create et teams/edit, et
| PoseTeamController::redirectBack — chacun portant un commentaire
| « single source of truth » qui ne l'était pas. Une clé ajoutée à un
| seul endroit donnait un bouton retour là, et une redirection ailleurs.
|
| Qui lit ce fichier :
|   · resources/views/admin/partials/_smart_back.blade.php  (le bouton)
|   · resources/views/admin/teams/create|edit.blade.php     (champ caché)
|   · app/Http/Controllers/Admin/PoseTeamController.php     (redirection
|     après enregistrement)
|
*/

return [

    /*
    | Provenances acceptées dans ?back=<clé>.
    |
    | LISTE BLANCHE STRICTE : une clé inconnue n'affiche aucun bouton et
    | ne redirige nulle part. C'est ce qui interdit qu'un lien forgé
    | renvoie l'utilisateur hors du site.
    */
    'retours' => [

        // Tableaux de pilotage
        'finance'                => ['route' => 'admin.finance.index',                'label' => 'Tableau de bord financier'],
        'finance.relances'       => ['route' => 'admin.finance.relances',             'label' => 'Historique des relances'],
        'rapports'               => ['route' => 'admin.rapports.index',               'label' => 'Rapports & Analyses'],
        'pilotage'               => ['route' => 'admin.pilotage',                     'label' => 'Pilotage'],

        // Performance
        'performance.commercial' => ['route' => 'admin.performance.commercial.index', 'label' => 'Performance commerciale'],
        'performance.tech'       => ['route' => 'admin.performance.tech.index',       'label' => 'Performance techniciens'],
        'performance.team'       => ['route' => 'admin.performance.team.index',       'label' => 'Performance équipes'],

        // Équipes et techniciens
        'teams'                  => ['route' => 'admin.teams.index',                  'label' => 'Gérer équipes'],
        'posetasks'              => ['route' => 'admin.pose-tasks.index',             'label' => 'Tâches de pose'],
        'posetasks.techniciens'  => ['route' => 'admin.pose-tasks.techniciens.index', 'label' => 'Techniciens'],

        // Métier courant
        'clients'                => ['route' => 'admin.clients.index',                'label' => 'Clients'],
        'campaigns'              => ['route' => 'admin.campaigns.index',              'label' => 'Campagnes'],
        'invoices'               => ['route' => 'admin.invoices.index',               'label' => 'Factures'],
        'quotes'                 => ['route' => 'admin.quotes.index',                 'label' => 'Devis'],
        'panels'                 => ['route' => 'admin.panels.index',                 'label' => 'Panneaux'],
        'propositions'           => ['route' => 'admin.propositions.index',           'label' => 'Propositions'],
        'reservations'           => ['route' => 'admin.reservations.index',           'label' => 'Réservations'],
        'maintenances'           => ['route' => 'admin.maintenances.index',           'label' => 'Maintenances'],
        'piges'                  => ['route' => 'admin.piges.index',                  'label' => 'Piges photos'],
        'taxes'                  => ['route' => 'admin.taxes.index',                  'label' => 'Taxes communales'],
        'renouvellements'        => ['route' => 'admin.renouvellements.index',        'label' => 'Renouvellements'],
        'diffusion'              => ['route' => 'admin.diffusion-dispos.index',       'label' => 'Diffusion des disponibilités'],
        'external-agencies'      => ['route' => 'admin.external-agencies.index',      'label' => 'Régies externes'],
        'users'                  => ['route' => 'admin.users.index',                  'label' => 'Utilisateurs'],
        'signalements'           => ['route' => 'admin.signalements.index',           'label' => 'Signalements'],

        // 2026-06-19 : 'sla' a été fusionné dans la page Signalements
        // (onglet « Analyse »). L'alias reste pour les anciens liens.
        'sla'                    => ['route' => 'admin.signalements.index',           'label' => 'Analyse des signalements', 'params' => ['view' => 'analyse']],
    ],

    /*
    | Pages atteignables d'un clic depuis le menu latéral.
    |
    | On n'y propose pas de retour DÉDUIT de la page précédente : on y
    | arrive normalement par le menu, et le bouton y serait presque
    | toujours présent pour rien. Un ?back= explicite reste honoré — c'est
    | ce qui permet d'ouvrir les factures depuis « CA HT équipe » avec un
    | vrai retour vers Performance commerciale.
    |
    | À tenir à jour avec le menu de resources/views/layouts/admin.blade.php.
    */
    'pages_du_menu' => [
        'admin.dashboard',
        'admin.alerts.index',
        'admin.audit.logs',
        'admin.campaigns.index',
        'admin.clients.index',
        'admin.conflicts.index',
        'admin.diffusion-dispos.index',
        'admin.external-agencies.index',
        'admin.finance.index',
        'admin.invoices.index',
        'admin.journal-mails.index',
        'admin.maintenances.index',
        'admin.map',
        'admin.messages.index',
        'admin.panels.index',
        'admin.performance.commercial.index',
        'admin.performance.commercial.me',
        'admin.performance.team.index',
        'admin.performance.tech.index',
        'admin.performance.tech.me',
        'admin.piges.index',
        'admin.pilotage',
        'admin.pose-tasks.index',
        'admin.quotes.index',
        'admin.rapports.index',
        'admin.renouvellements.index',
        'admin.reservations.disponibilites',
        'admin.reservations.index',
        'admin.settings.communes.tariffs',
        'admin.settings.index',
        'admin.signalements.index',
        'admin.taxes.index',
        'admin.users.index',
    ],

];

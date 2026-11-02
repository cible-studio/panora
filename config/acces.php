<?php

/*
|--------------------------------------------------------------------------
| QUI VOIT QUOI — source unique des droits par module
|--------------------------------------------------------------------------
|
| Validé avec la direction le 2026-11-02. Pour chaque module : les rôles qui
| le voient dans le menu ET peuvent l'ouvrir, et la page du menu.
| L'admin est toujours inclus. Le technicien travaille dans son espace
| terrain : il n'apparaît dans aucun module.
|
| Lu par App\Support\Acces, lui-même appelé par :
|   - le menu (layouts/admin.blade.php) ;
|   - les routes de lecture des factures et des devis (routes/admin.php) ;
|   - InvoicePolicy, QuotePolicy ;
|   - le tableau de bord, les fiches campagne et client, la liste des
|     campagnes, le classement des commerciaux.
|
| Garde-fou : tests/Unit/AccesParRoleTest vérifie que chaque page du menu
| s'ouvre exactement pour les rôles listés ici. Changer un droit = changer
| cette liste ET la route ; le test refuse l'un sans l'autre.
|
*/

$tous = ['admin', 'commercial', 'mediaplanner', 'comptable'];

return [

    'modules' => [

        // ── Principal ─────────────────────────────────────────────
        'tableau_de_bord' => ['roles' => $tous, 'route' => 'dashboard'],
        'alertes'         => ['roles' => $tous, 'route' => 'admin.alerts.index'],
        // 2026-11-02 (2ᵉ série) : plus le MP.
        'messages'        => ['roles' => ['admin', 'commercial', 'comptable'], 'route' => 'admin.messages.index'],

        // ── Commercial ────────────────────────────────────────────
        // Pages Clients (liste, fiche, modification, exports, import).
        // 2026-11-02 : plus le MP — il garde dans ses formulaires la création
        // d'un client, la vérification du nom et le choix de l'interlocuteur.
        'clients'         => ['roles' => ['admin', 'commercial', 'comptable'], 'route' => 'admin.clients.index'],
        'disponibilites'  => ['roles' => ['admin', 'commercial', 'mediaplanner'], 'route' => 'admin.reservations.disponibilites'],
        // Commercial : ses devis. MP : lecture seule (des devis bloquent des
        // panneaux). Comptable : plus d'accès (2026-11-02).
        'devis'           => ['roles' => ['admin', 'commercial', 'mediaplanner'], 'route' => 'admin.quotes.index'],
        'reservations'    => ['roles' => $tous, 'route' => 'admin.reservations.index'],
        'campagnes'       => ['roles' => $tous, 'route' => 'admin.campaigns.index'],
        'renouvellements' => ['roles' => ['admin', 'commercial', 'mediaplanner'], 'route' => 'admin.renouvellements.index'],
        'diffusion_dispos' => ['roles' => ['admin'], 'route' => 'admin.diffusion-dispos.index'],

        // ── Parc & terrain ────────────────────────────────────────
        'inventaire'      => ['roles' => $tous, 'route' => 'admin.panels.index'],
        'regies'          => ['roles' => $tous, 'route' => 'admin.external-agencies.index'],
        'carte'           => ['roles' => $tous, 'route' => 'admin.map'],
        'poses'           => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.pose-tasks.index'],
        'pilotage'        => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.pilotage'],
        'piges'           => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.piges.index'],
        'signalements'    => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.signalements.index'],
        'conflits'        => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.conflicts.index'],
        'maintenance'     => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.maintenances.index'],

        // ── Finance ───────────────────────────────────────────────
        // Factures : liste, fiche, PDF, exports, et les blocs « Facturation »
        // des autres écrans. Commercial : les siennes. MP : plus d'accès
        // (2026-11-02 — avant, la page s'ouvrait par l'adresse).
        'factures'        => ['roles' => ['admin', 'commercial', 'comptable'], 'route' => 'admin.invoices.index'],
        'finance'         => ['roles' => ['admin', 'commercial', 'comptable'], 'route' => 'admin.finance.index'],
        'taxes'           => ['roles' => ['admin', 'mediaplanner', 'comptable'], 'route' => 'admin.taxes.index'],
        'tarifs_communes' => ['roles' => ['admin', 'comptable'], 'route' => 'admin.settings.communes.tariffs'],

        // ── Analyse ───────────────────────────────────────────────
        'rapports'                => ['roles' => $tous, 'route' => 'admin.rapports.index'],
        // Le MP garde le classement ET ses montants (2026-11-02).
        'performance_commerciale' => ['roles' => ['admin', 'commercial', 'mediaplanner'], 'route' => 'admin.performance.commercial.index'],
        'performance_techniciens' => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.performance.tech.index'],
        'performance_equipes'     => ['roles' => ['admin', 'mediaplanner'], 'route' => 'admin.performance.team.index'],

        // ── Administration ────────────────────────────────────────
        'utilisateurs'    => ['roles' => ['admin'], 'route' => 'admin.users.index'],
        'journal_mails'   => ['roles' => ['admin'], 'route' => 'admin.journal-mails.index'],
        'audit'           => ['roles' => ['admin'], 'route' => 'admin.audit.logs'],
        'parametres'      => ['roles' => ['admin'], 'route' => 'admin.settings.index'],

        // ── Sans page propre ──────────────────────────────────────
        // Modifier le prix d'un panneau : inventaire, campagne, réservation
        // (le commercial, ses réservations, via ReservationPolicy).
        // 2026-11-02 : le comptable passe en lecture seule. Ne couvre pas les
        // tarifs des communes (module « tarifs_communes »).
        'prix_panneaux' => ['roles' => ['admin', 'mediaplanner'], 'route' => null],

        // Chiffres financiers du tableau de bord : CA, encaissements,
        // factures en retard, à recouvrer, prévision, top clients et
        // communes. Le MP ne les voit plus (2026-11-02), le comptable oui.
        'chiffres_financiers' => ['roles' => ['admin', 'commercial', 'comptable'], 'route' => null],
    ],

];

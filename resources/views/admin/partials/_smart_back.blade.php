{{-- ════ Bouton retour intelligent — partial mutualisé ════
     2026-06-18 (feedback patronne) : navigation croisée entre pages.

     Détecte ?back=<key> avec whitelist STRICTE (pas d'open-redirect).
     Affiche "← <Label>" en topbarLeft si la clé est connue, sinon
     rien (le slot reste libre pour son contenu par défaut).

     **Migration depuis l'ancien chemin** : ce partial existait à
     resources/views/admin/performance/partials/_smart_back.blade.php
     pour des raisons historiques (créé pour les pages Performance d'abord).
     Le partial là-bas est maintenant un alias vers ce fichier pour ne
     pas casser les @include existants.

     Utilisation :
       @include('admin.partials._smart_back')

     Pour propager `back` sur un lien cross-page :
       route('admin.x.y', ['back' => 'clients'])
--}}
@php
    $backMap = [
        // Tableaux de pilotage
        'finance'                => ['route' => 'admin.finance.index',                    'label' => 'Tableau de bord financier'],
        'finance.relances'       => ['route' => 'admin.finance.relances',                 'label' => 'Historique des relances'],
        'rapports'               => ['route' => 'admin.rapports.index',                   'label' => 'Rapports & Analyses'],

        // Performance
        'performance.commercial' => ['route' => 'admin.performance.commercial.index',     'label' => 'Performance commerciale'],
        'performance.tech'       => ['route' => 'admin.performance.tech.index',           'label' => 'Performance techniciens'],
        'performance.team'       => ['route' => 'admin.performance.team.index',           'label' => 'Performance équipes'],

        // Gestion équipes / techniciens
        'teams'                  => ['route' => 'admin.teams.index',                      'label' => 'Gérer équipes'],
        'posetasks'              => ['route' => 'admin.pose-tasks.index',                 'label' => 'Tâches de pose'],
        'posetasks.techniciens'  => ['route' => 'admin.pose-tasks.techniciens.index',     'label' => 'Techniciens'],

        // Métier courant
        'clients'                => ['route' => 'admin.clients.index',                    'label' => 'Clients'],
        'campaigns'              => ['route' => 'admin.campaigns.index',                  'label' => 'Campagnes'],
        'invoices'               => ['route' => 'admin.invoices.index',                   'label' => 'Factures'],
        'panels'                 => ['route' => 'admin.panels.index',                     'label' => 'Panneaux'],
        'propositions'           => ['route' => 'admin.propositions.index',               'label' => 'Propositions'],
        'reservations'           => ['route' => 'admin.reservations.index',               'label' => 'Réservations'],
        'maintenances'           => ['route' => 'admin.maintenances.index',               'label' => 'Maintenances'],
        'piges'                  => ['route' => 'admin.piges.index',                      'label' => 'Piges photos'],
        'signalements'           => ['route' => 'admin.signalements.index',               'label' => 'Signalements'],
        'sla'                    => ['route' => 'admin.sla.retards.index',                'label' => 'Analyse des signalements'],
    ];
    $backKey = (string) request()->query('back', '');
    $backCfg = $backMap[$backKey] ?? null;
@endphp

@if($backCfg)
    @php
        // Le route() peut échouer si la route n'existe pas pour le rôle courant.
        // On essaie, et on tombe silencieusement si problème (au pire pas de bouton).
        try { $backUrl = route($backCfg['route']); } catch (\Throwable $e) { $backUrl = null; }
    @endphp
    @if($backUrl)
        <a href="{{ $backUrl }}"
           class="btn btn-ghost btn-sm"
           style="display:inline-flex;align-items:center;gap:6px"
           title="Retour à {{ $backCfg['label'] }}">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            {{ $backCfg['label'] }}
        </a>
    @endif
@endif

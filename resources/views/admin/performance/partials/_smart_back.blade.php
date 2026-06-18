{{-- ════ Bouton retour intelligent — partial mutualisé ════
     2026-06-18 (feedback patronne) : "ajoute des boutons retour entre les
     pages pour faciliter [la navigation]".

     Détecte la query string ?back=<key> (whitelist stricte côté serveur,
     pas d'open-redirect possible) et affiche un bouton ← qui ramène
     vers la page d'origine. Si pas de back ou key inconnue, n'affiche
     rien (le slot topbarLeft reste libre pour son contenu par défaut).

     Whitelist alignée avec PoseTeamController::redirectBack — single
     source of truth des sources autorisées. Si tu ajoutes une nouvelle
     destination, mets à jour les 2 endroits.

     Usage : @include('admin.performance.partials._smart_back')
     Pas de paramètre — lit request()->query('back') directement.

     Pour propager le back sur un lien cliquable (cross-page) :
       <a href="{{ route('admin.x.y', ['back' => 'performance.team']) }}">
--}}
@php
    $backMap = [
        'posetasks'             => ['route' => 'admin.pose-tasks.index',                 'label' => 'Tâches de pose',          'icon' => '🔧'],
        'performance.commercial'=> ['route' => 'admin.performance.commercial.index',     'label' => 'Performance commerciale', 'icon' => '📊'],
        'performance.tech'      => ['route' => 'admin.performance.tech.index',           'label' => 'Performance techniciens', 'icon' => '📋'],
        'performance.team'      => ['route' => 'admin.performance.team.index',           'label' => 'Performance équipes',     'icon' => '👥'],
        'teams'                 => ['route' => 'admin.teams.index',                      'label' => 'Gérer équipes',           'icon' => '⚙'],
        'finance'               => ['route' => 'admin.finance.index',                    'label' => 'Tableau de bord financier','icon' => '💰'],
        'clients'               => ['route' => 'admin.clients.index',                    'label' => 'Clients',                  'icon' => '👤'],
    ];
    $backKey = (string) request()->query('back', '');
    $backCfg = $backMap[$backKey] ?? null;
@endphp

@if($backCfg)
    <a href="{{ route($backCfg['route']) }}"
       class="btn btn-ghost btn-sm"
       style="display:inline-flex;align-items:center;gap:6px"
       title="Retour à {{ $backCfg['label'] }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        {{ $backCfg['label'] }}
    </a>
@endif

{{-- A1 §spec — 5 KPIs en grid : Total / Faites / En cours / À valider / Signalements.
     Valeurs initiales à "—" : le 1er tick du polling JS les remplira dans la
     seconde qui suit le load.
     Hotfix 2026-06-19 : chaque carte est un lien vers la liste filtrée
     correspondante. La patronne tape sur "Faites" → /admin/pose-tasks avec
     filtre status=realisee + date du jour, etc. --}}
@php
    $today = \Carbon\Carbon::today()->toDateString();
@endphp
<section class="live-kpi-grid">
    <a href="{{ route('admin.pose-tasks.index', ['date_from' => $today, 'date_to' => $today]) }}"
       class="live-kpi live-kpi--total"
       title="Voir toutes les poses planifiées aujourd'hui">
        <div class="live-kpi-icon">📋</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="total_poses_today">—</div>
            <div class="live-kpi-label">Poses du jour</div>
        </div>
    </a>
    <a href="{{ route('admin.pose-tasks.index', ['status' => 'realisee', 'date_from' => $today, 'date_to' => $today]) }}"
       class="live-kpi live-kpi--done"
       title="Voir les poses livrées aujourd'hui">
        <div class="live-kpi-icon">✅</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="done">—</div>
            <div class="live-kpi-label">Faites</div>
        </div>
    </a>
    <a href="{{ route('admin.pose-tasks.index', ['status' => 'en_cours']) }}"
       class="live-kpi live-kpi--progress"
       title="Voir les poses en cours">
        <div class="live-kpi-icon">🚗</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="in_progress">—</div>
            <div class="live-kpi-label">En cours</div>
        </div>
    </a>
    <a href="{{ route('admin.piges.validation') }}"
       class="live-kpi live-kpi--pending"
       title="Voir les piges à valider">
        <div class="live-kpi-icon">⏳</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="pending_validation">—</div>
            <div class="live-kpi-label">À valider</div>
        </div>
    </a>
    <a href="{{ route('admin.signalements.index', ['view' => 'todo']) }}"
       class="live-kpi live-kpi--problem"
       title="Voir les signalements à traiter">
        <div class="live-kpi-icon">⚠️</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="problems_open">—</div>
            <div class="live-kpi-label">Signalements</div>
        </div>
    </a>
</section>

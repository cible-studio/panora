{{-- A1 §spec — 5 KPIs en grid : Total / Faites / En cours / À valider / Signalements.
     Valeurs initiales à "—" : le 1er tick du polling JS les remplira dans la
     seconde qui suit le load. --}}
<section class="live-kpi-grid">
    <div class="live-kpi live-kpi--total">
        <div class="live-kpi-icon">📋</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="total_poses_today">—</div>
            <div class="live-kpi-label">Poses du jour</div>
        </div>
    </div>
    <div class="live-kpi live-kpi--done">
        <div class="live-kpi-icon">✅</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="done">—</div>
            <div class="live-kpi-label">Faites</div>
        </div>
    </div>
    <div class="live-kpi live-kpi--progress">
        <div class="live-kpi-icon">🚗</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="in_progress">—</div>
            <div class="live-kpi-label">En cours</div>
        </div>
    </div>
    <div class="live-kpi live-kpi--pending">
        <div class="live-kpi-icon">⏳</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="pending_validation">—</div>
            <div class="live-kpi-label">À valider</div>
        </div>
    </div>
    <div class="live-kpi live-kpi--problem">
        <div class="live-kpi-icon">⚠️</div>
        <div class="live-kpi-body">
            <div class="live-kpi-value" data-kpi="problems_open">—</div>
            <div class="live-kpi-label">Signalements</div>
        </div>
    </div>
</section>

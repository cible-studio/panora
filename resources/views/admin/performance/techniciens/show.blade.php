<x-admin-layout>
<x-slot name="title">Performance — {{ $user->name }}</x-slot>

<x-slot:topbarLeft>
    @include('admin.performance.partials._smart_back')
    @if(!request()->query('back') && in_array(auth()->user()?->role?->value, ['admin','mediaplanner'], true))
        <a href="{{ route('admin.performance.tech.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Classement
        </a>
    @endif
</x-slot:topbarLeft>

@php
    // 2026-06-19 — Multi-équipe : on prend la 1ère équipe du tech pour la
    // couleur dominante du hero (couleur "principale"). Les autres équipes
    // apparaissent ensuite en chips dans la ligne descriptive.
    $primaryTeam = $user->poseTeams->first();
    $teamHex = $primaryTeam?->colorHex() ?? '#6366f1';
    $teamBg  = $primaryTeam?->colorBgHex() ?? 'rgba(99,102,241,.10)';
@endphp

<div class="perf-page">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,{{ $teamBg }},rgba(99,102,241,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:60px;height:60px;border-radius:14px;background:{{ $teamBg }};border:2px solid {{ $teamHex }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:28px;font-weight:800;color:{{ $teamHex }}">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:20px;font-weight:800;color:var(--text);display:flex;align-items:center;gap:10px">
                {{ $user->name }}
                @if($user->teamLeading)
                    <span style="padding:2px 8px;border-radius:999px;background:rgba(245,158,11,.15);color:#b45309;font-size:10.5px;font-weight:700">⭐ Leader {{ $user->teamLeading->name }}</span>
                @endif
            </div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px">
                <span style="font-family:monospace">{{ $user->agent_code }}</span> · Technicien
                {{-- 2026-06-19 — Multi-équipe : N chips, 1 par équipe avec sa couleur propre. --}}
                @if($user->poseTeams->isNotEmpty())
                    @foreach($user->poseTeams as $tm)
                        · <span style="padding:1px 8px;border-radius:999px;background:{{ $tm->colorBgHex() }};color:{{ $tm->colorHex() }};font-weight:700">{{ $tm->name }}</span>
                    @endforeach
                @else
                    · <span style="font-style:italic">Sans équipe</span>
                @endif
                · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- Filtres période — partial partagé (compact pour /show) Bloc 2 Famille A. --}}
    @include('admin.performance.partials._period_filters', [
        'action_route' => route('admin.performance.tech.show', $user),
        'reset_route'  => route('admin.performance.tech.show', $user),
        'from'         => $from,
        'to'           => $to,
        'preset'       => request('preset'),
        'compact'      => true,
    ])

    {{-- 6 KPI cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px">
        <div class="perf-kpi" style="border-left-color:#16a34a">
            <div class="perf-kpi-label">Poses réalisées</div>
            <div class="perf-kpi-val" style="color:#15803d">{{ $kpis['nb_poses_realisees'] }}</div>
            <div class="perf-kpi-sub">{{ $kpis['nb_poses_total'] }} au total</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#0ea5e9">
            <div class="perf-kpi-label">Réactivité moy</div>
            <div class="perf-kpi-val" style="color:#0369a1">{{ \App\Support\HumanDuration::fromMinutes($kpis['reactivite_avg_min']) }}</div>
            <div class="perf-kpi-sub">attribution → début</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#a855f7">
            <div class="perf-kpi-label">Durée pose moy</div>
            <div class="perf-kpi-val" style="color:#7c3aed">{{ \App\Support\HumanDuration::fromMinutes($kpis['duree_pose_avg_min']) }}</div>
            <div class="perf-kpi-sub">{{ $kpis['respect_estimation_pct'] !== null ? 'vs estimation : '.$kpis['respect_estimation_pct'].' %' : '' }}</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#6366f1">
            <div class="perf-kpi-label">Délai pige moy</div>
            <div class="perf-kpi-val" style="color:#4338ca">{{ \App\Support\HumanDuration::fromHours($kpis['delai_pige_avg_h']) }}</div>
            <div class="perf-kpi-sub">pose → pige envoyée</div>
        </div>
        <div class="perf-kpi" style="border-left-color:{{ $kpis['taux_poses_en_retard'] <= 5 ? '#16a34a' : ($kpis['taux_poses_en_retard'] <= 15 ? '#f59e0b' : '#ef4444') }}">
            <div class="perf-kpi-label">% Retard</div>
            <div class="perf-kpi-val" style="color:{{ $kpis['taux_poses_en_retard'] <= 5 ? '#15803d' : ($kpis['taux_poses_en_retard'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $kpis['taux_poses_en_retard'] }} %</div>
            <div class="perf-kpi-sub">{{ $kpis['nb_poses_en_retard'] }} pose(s) scheduled_at &lt; now</div>
        </div>
        <div class="perf-kpi" style="border-left-color:{{ $kpis['taux_piges_rejetees'] <= 5 ? '#16a34a' : ($kpis['taux_piges_rejetees'] <= 15 ? '#f59e0b' : '#ef4444') }}">
            <div class="perf-kpi-label">% Piges rejetées</div>
            <div class="perf-kpi-val" style="color:{{ $kpis['taux_piges_rejetees'] <= 5 ? '#15803d' : ($kpis['taux_piges_rejetees'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $kpis['taux_piges_rejetees'] }} %</div>
            <div class="perf-kpi-sub">{{ $kpis['nb_piges_rejetees'] }} rejet(s) · {{ $kpis['nb_signalements'] }} signalement(s)</div>
        </div>
    </div>

    {{-- Charts --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px">
        <div class="perf-card">
            <div class="perf-card-head"><div><div class="perf-card-title">📅 Poses par jour — 30 derniers jours</div><div class="perf-card-sub">Création des poses (status indifférent)</div></div></div>
            <div style="padding:18px"><div style="position:relative;width:100%;height:240px"><canvas id="chart-daily-poses"></canvas></div></div>
        </div>
        <div class="perf-card">
            <div class="perf-card-head"><div><div class="perf-card-title">⚡ Distribution réactivité</div><div class="perf-card-sub">attribution → début (histogramme 6 buckets)</div></div></div>
            <div style="padding:18px"><div style="position:relative;width:100%;height:240px"><canvas id="chart-reactivity"></canvas></div></div>
        </div>
    </div>

    {{-- Tableau poses --}}
    <div class="perf-card">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">📋 Poses du technicien</div>
                <div class="perf-card-sub">{{ $poses->total() }} pose(s) · page {{ $poses->currentPage() }}/{{ $poses->lastPage() }}</div>
                {{-- Garde-fou A : sous-titre explicatif sur le team_name historique. --}}
                <div style="font-size:11px;color:var(--text3);font-style:italic;margin-top:6px;line-height:1.5">
                    ℹ️ L'équipe affichée dans chaque ligne correspond à celle du technicien
                    <strong>au moment de la pose</strong>. Si le tech a changé d'équipe depuis,
                    les anciennes poses gardent leur ancien rattachement (préservation historique).
                </div>
            </div>
        </div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>Panneau</th>
                        <th>Commune</th>
                        <th>Campagne</th>
                        <th>Équipe (au moment de la pose)</th>
                        <th>Planifié</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th>Durée réelle</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        // 2026-06-19 — Multi-équipe : "is old team" = team_name de la pose
                        // n'est dans AUCUNE des équipes actuelles du tech (= ancienne équipe).
                        $currentTeamNames = $user->poseTeams->pluck('name')->all();
                    @endphp
                    @forelse($poses as $pt)
                        @php
                            $teamAtPose = $pt->team_name;
                            $isOldTeam  = $teamAtPose && !empty($currentTeamNames) && !in_array($teamAtPose, $currentTeamNames, true);
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.pose-tasks.show', $pt) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $pt->panel?->reference ?? '—' }}</a></td>
                            <td style="color:var(--text2)">{{ $pt->panel?->commune?->name ?? '—' }}</td>
                            <td style="color:var(--text2);font-size:12px">{{ $pt->campaign?->name ?? '—' }}</td>
                            <td>
                                @if($teamAtPose)
                                    <span style="font-size:11.5px;color:var(--text2)">{{ $teamAtPose }}</span>
                                    @if($isOldTeam)
                                        <span style="display:inline-block;margin-left:4px;padding:1px 6px;border-radius:999px;background:rgba(245,158,11,.15);color:#b45309;font-size:10px;font-weight:700" title="Le tech a changé d'équipe depuis cette pose">Ancienne équipe</span>
                                    @endif
                                @else
                                    <span style="color:var(--text3);font-style:italic;font-size:11.5px">—</span>
                                @endif
                            </td>
                            <td style="color:var(--text3);font-size:12px">{{ $pt->scheduled_at?->format('d/m/Y H:i') }}</td>
                            <td style="color:var(--text3);font-size:12px">{{ $pt->started_at?->format('d/m H:i') ?? '—' }}</td>
                            <td style="color:var(--text3);font-size:12px">{{ $pt->done_at?->format('d/m H:i') ?? '—' }}</td>
                            <td style="text-align:right;color:var(--text2);font-size:12px">{{ \App\Support\HumanDuration::fromMinutes($pt->real_minutes) }}</td>
                            <td><span style="padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700;background:var(--surface2);color:var(--text2)">{{ $pt->status }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text3);font-style:italic">Aucune pose sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($poses->hasPages())
            <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">{{ $poses->withQueryString()->links() }}</div>
        @endif
    </div>
</div>

<style>
.perf-page input[type="date"] { height:38px; padding:0 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; font-family:inherit; outline:none; box-sizing:border-box; }
.perf-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.perf-kpi { background:var(--surface); border:1px solid var(--border); border-left:4px solid; border-radius:14px; padding:14px 18px; }
.perf-kpi-label { font-size:10.5px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; }
.perf-kpi-val { font-size:22px; font-weight:800; color:var(--text); margin-top:2px; }
.perf-kpi-sub { font-size:11px; color:var(--text3); margin-top:2px; }
.perf-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.perf-card-head { padding:14px 18px; border-bottom:1px solid var(--border); background:var(--surface2); }
.perf-card-title { font-size:14px; font-weight:800; color:var(--text); }
.perf-card-sub { font-size:11.5px; color:var(--text3); margin-top:3px; }
.perf-card-body--flush { padding:0; overflow-x:auto; }
.perf-table { width:100%; border-collapse:collapse; font-size:13px; }
.perf-table th { text-align:left; padding:10px 14px; background:var(--surface2); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); border-bottom:1px solid var(--border); }
.perf-table td { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text); }
.perf-table tr:hover td { background:rgba(232,160,32,.04); }
</style>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;

    const daily = @json($dailyPoses->values());
    const c1 = document.getElementById('chart-daily-poses');
    if (c1 && daily?.length) {
        new Chart(c1, {
            type: 'bar',
            data: { labels: daily.map(d => d.label),
                    datasets: [{ label: 'Poses', data: daily.map(d => d.count), backgroundColor: '#6366f1', borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    const reactivity = @json($reactivity->values());
    const c2 = document.getElementById('chart-reactivity');
    if (c2 && reactivity?.length) {
        new Chart(c2, {
            type: 'bar',
            data: { labels: reactivity.map(b => b.label),
                    datasets: [{ data: reactivity.map(b => b.count),
                                 backgroundColor: reactivity.map(b => b.color),
                                 borderRadius: 4 }] },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' pose(s)' } } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }
})();
</script>
@endpush

</x-admin-layout>

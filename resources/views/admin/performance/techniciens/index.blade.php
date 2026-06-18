<x-admin-layout>
<x-slot name="title">Performance techniciens</x-slot>

{{-- 2026-06-18 (feedback patronne) : topbarActions désormais réservé aux
     exports PDF. Les boutons cross-pages sont déplacés dans l'action bar
     sous le hero. topbarLeft affiche le retour intelligent si ?back= --}}
<x-slot:topbarLeft>
    @include('admin.performance.partials._smart_back')
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.performance.tech.export.pdf', array_filter(request()->only(['from', 'to', 'preset']))) }}"
       class="btn btn-ghost btn-sm"
       title="Télécharger le PDF du leaderboard avec la période courante">📄 Exporter PDF</a>
</x-slot>

<div class="perf-page">
    <div style="background:linear-gradient(135deg,rgba(99,102,241,.10),rgba(168,85,247,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(99,102,241,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">📋</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text)">Performance techniciens</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                Classement des techniciens · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- ════ Action bar — anciens boutons topbar + cross-links avec ?back= ════ --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px">
        <a href="{{ route('admin.performance.team.index', ['back' => 'performance.tech']) }}" class="btn btn-ghost btn-sm">👥 Performance équipes</a>
        <a href="{{ route('admin.performance.commercial.index', ['back' => 'performance.tech']) }}" class="btn btn-ghost btn-sm">📊 Performance commerciale</a>
        <a href="{{ route('admin.teams.index', ['back' => 'performance.tech']) }}" class="btn btn-ghost btn-sm">⚙ Gérer équipes</a>
    </div>

    {{-- Filtres période — partial partagé (Bloc 2 — Famille A). --}}
    @include('admin.performance.partials._period_filters', [
        'action_route' => route('admin.performance.tech.index'),
        'reset_route'  => route('admin.performance.tech.index'),
        'from'         => $from,
        'to'           => $to,
        'preset'       => $preset ?? 'year',
    ])

    {{-- 6 KPI cards globaux équipe — équivalent design Performance Commerciale --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:18px">
        <div class="perf-kpi" style="border-left-color:#16a34a">
            <div class="perf-kpi-label">Poses réalisées</div>
            <div class="perf-kpi-val" style="color:#15803d">{{ number_format($globalKpis['nb_poses_realisees']) }}</div>
            <div class="perf-kpi-sub">sur la période</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#0ea5e9">
            <div class="perf-kpi-label">Réactivité moyenne</div>
            <div class="perf-kpi-val" style="color:#0369a1">{{ $globalKpis['reactivite_avg_min'] !== null ? $globalKpis['reactivite_avg_min'].' min' : '—' }}</div>
            <div class="perf-kpi-sub">attribution → début</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#a855f7">
            <div class="perf-kpi-label">Durée moyenne pose</div>
            <div class="perf-kpi-val" style="color:#7c3aed">{{ $globalKpis['duree_pose_avg_min'] !== null ? $globalKpis['duree_pose_avg_min'].' min' : '—' }}</div>
            <div class="perf-kpi-sub">début → fin</div>
        </div>
        <div class="perf-kpi" style="border-left-color:{{ $globalKpis['taux_poses_en_retard'] <= 5 ? '#16a34a' : ($globalKpis['taux_poses_en_retard'] <= 15 ? '#f59e0b' : '#ef4444') }}">
            <div class="perf-kpi-label">% Poses en retard</div>
            <div class="perf-kpi-val" style="color:{{ $globalKpis['taux_poses_en_retard'] <= 5 ? '#15803d' : ($globalKpis['taux_poses_en_retard'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $globalKpis['taux_poses_en_retard'] }} %</div>
            <div class="perf-kpi-sub">scheduled &lt; now</div>
        </div>
        <div class="perf-kpi" style="border-left-color:{{ $globalKpis['taux_piges_rejetees'] <= 5 ? '#16a34a' : ($globalKpis['taux_piges_rejetees'] <= 15 ? '#f59e0b' : '#ef4444') }}">
            <div class="perf-kpi-label">% Piges rejetées</div>
            <div class="perf-kpi-val" style="color:{{ $globalKpis['taux_piges_rejetees'] <= 5 ? '#15803d' : ($globalKpis['taux_piges_rejetees'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $globalKpis['taux_piges_rejetees'] }} %</div>
            <div class="perf-kpi-sub">qualité reporting</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#6366f1">
            <div class="perf-kpi-label">Techniciens actifs</div>
            <div class="perf-kpi-val" style="color:#4338ca">{{ $globalKpis['nb_techs_actifs'] }}</div>
            <div class="perf-kpi-sub">role=technique · is_active</div>
        </div>
    </div>

    {{-- Courbe 12 mois — équivalent commercial mais sur nb poses --}}
    <div class="perf-card" style="margin-bottom:18px">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">📈 Évolution mensuelle — 12 derniers mois</div>
                <div class="perf-card-sub">Nb poses réalisées par mois (toutes équipes confondues)</div>
            </div>
            @php $trendTotal = $monthlyTrend->sum('count'); @endphp
            <div style="font-size:11.5px;color:var(--text3);text-align:right;line-height:1.5">
                <div>Total 12 mois : <strong style="color:#6366f1;font-size:14px">{{ number_format($trendTotal) }}</strong></div>
                <div style="font-size:10px;color:var(--text3)">poses réalisées</div>
            </div>
        </div>
        <div style="padding:18px 20px">
            <div style="position:relative;height:240px"><canvas id="perfTechTrendChart"></canvas></div>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="perf-card">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">🏆 Classement techniciens</div>
                <div class="perf-card-sub">{{ $leaderboard->count() }} technicien(s) — ordonné par nb poses réalisées</div>
            </div>
        </div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Technicien</th>
                        <th>Équipe</th>
                        <th style="text-align:right">Réalisées</th>
                        <th style="text-align:right">Planifiées</th>
                        <th style="text-align:right">Réactivité</th>
                        <th style="text-align:right">Durée moy</th>
                        <th style="text-align:right">% Retard</th>
                        <th style="text-align:right">% Piges rejetées</th>
                        <th style="text-align:right">Signalements</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaderboard as $idx => $row)
                        @php
                            $u = $row['user'];
                            $k = $row['kpis'];
                            $medals = ['🥇','🥈','🥉'];
                            $rank   = $medals[$idx] ?? ($idx + 1);
                            $retardCol = $k['taux_poses_en_retard'] <= 5 ? '#15803d' : ($k['taux_poses_en_retard'] <= 15 ? '#b45309' : '#b91c1c');
                            $rejetCol  = $k['taux_piges_rejetees']   <= 5 ? '#15803d' : ($k['taux_piges_rejetees']   <= 15 ? '#b45309' : '#b91c1c');
                        @endphp
                        <tr>
                            <td style="font-weight:800">{{ $rank }}</td>
                            <td>
                                <div style="font-weight:700">{{ $u->name }}</div>
                                <div style="font-size:10.5px;color:var(--text3);font-family:monospace">{{ $u->agent_code }}</div>
                            </td>
                            <td>
                                @if($u->poseTeam)
                                    <span style="display:inline-flex;align-items:center;gap:6px;padding:2px 8px;border-radius:999px;background:{{ $u->poseTeam->colorBgHex() }};color:{{ $u->poseTeam->colorHex() }};font-size:11px;font-weight:700">{{ $u->poseTeam->name }}</span>
                                @else
                                    <span style="font-size:11px;color:var(--text3);font-style:italic">—</span>
                                @endif
                            </td>
                            <td style="text-align:right;font-weight:800;color:#16a34a">{{ $k['nb_poses_realisees'] }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['nb_poses_planifiees'] }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['reactivite_avg_min'] !== null ? $k['reactivite_avg_min'].' min' : '—' }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['duree_pose_avg_min'] !== null ? $k['duree_pose_avg_min'].' min' : '—' }}</td>
                            <td style="text-align:right;font-weight:700;color:{{ $retardCol }}">{{ $k['taux_poses_en_retard'] }} %</td>
                            <td style="text-align:right;font-weight:700;color:{{ $rejetCol }}">{{ $k['taux_piges_rejetees'] }} %</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k['nb_signalements'] }}</td>
                            <td style="text-align:right"><a href="{{ route('admin.performance.tech.show', $u) }}" class="btn btn-ghost btn-sm" style="font-size:11px">Détail →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" style="text-align:center;padding:40px;color:var(--text3);font-style:italic">Aucun technicien actif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 2 sections "Top tech par X" — Bloc 2 Famille A (2026-06-17) --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:18px">
        {{-- Top tech par commune --}}
        <div class="perf-card">
            <div class="perf-card-head">
                <div>
                    <div class="perf-card-title">🗺 Top technicien par commune</div>
                    <div class="perf-card-sub">Qui pose le plus dans chaque commune — zones d'expertise</div>
                </div>
            </div>
            <div class="perf-card-body--flush">
                @if($topByCommune->isEmpty())
                    <div style="padding:30px;text-align:center;color:var(--text3);font-style:italic;font-size:13px">Aucune pose sur la période.</div>
                @else
                    <table class="perf-table">
                        <thead>
                            <tr><th>#</th><th>Technicien</th><th>Commune</th><th style="text-align:right">Poses</th></tr>
                        </thead>
                        <tbody>
                            @foreach($topByCommune as $idx => $row)
                                <tr>
                                    <td style="font-weight:800">{{ ['🥇','🥈','🥉'][$idx] ?? ($idx + 1) }}</td>
                                    <td style="font-weight:700">{{ $row['user_name'] }}</td>
                                    <td style="color:var(--text2)">{{ $row['commune'] }}</td>
                                    <td style="text-align:right;font-weight:800;color:#16a34a">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        {{-- Top tech par campagne --}}
        <div class="perf-card">
            <div class="perf-card-head">
                <div>
                    <div class="perf-card-title">🎯 Top technicien par campagne</div>
                    <div class="perf-card-sub">Qui porte le business sur les campagnes de la période</div>
                </div>
            </div>
            <div class="perf-card-body--flush">
                @if($topByCampaign->isEmpty())
                    <div style="padding:30px;text-align:center;color:var(--text3);font-style:italic;font-size:13px">Aucune pose sur la période.</div>
                @else
                    <table class="perf-table">
                        <thead>
                            <tr><th>#</th><th>Technicien</th><th>Campagne</th><th style="text-align:right">Poses</th></tr>
                        </thead>
                        <tbody>
                            @foreach($topByCampaign as $idx => $row)
                                <tr>
                                    <td style="font-weight:800">{{ ['🥇','🥈','🥉'][$idx] ?? ($idx + 1) }}</td>
                                    <td style="font-weight:700">{{ $row['user_name'] }}</td>
                                    <td style="color:var(--text2)">
                                        {{ \Illuminate\Support\Str::limit($row['campaign'], 30) }}
                                        <span style="display:block;font-size:10.5px;color:var(--text3)">{{ $row['client'] }}</span>
                                    </td>
                                    <td style="text-align:right;font-weight:800;color:#16a34a">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
.perf-page select, .perf-page input[type="date"] { height:38px; width:100%; padding:0 10px; background:var(--surface2); border:1px solid var(--border); border-radius:8px; color:var(--text); font-size:13px; font-family:inherit; outline:none; box-sizing:border-box; }
.perf-page select { padding-right:28px; cursor:pointer; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat:no-repeat; background-position:right 8px center; }
.perf-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.perf-filter-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:14px 18px; }
.perf-card { background:var(--surface); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
.perf-card-head { padding:14px 18px; border-bottom:1px solid var(--border); background:var(--surface2); }
.perf-card-title { font-size:14px; font-weight:800; color:var(--text); }
.perf-card-sub { font-size:11.5px; color:var(--text3); margin-top:3px; }
.perf-card-body--flush { padding:0; overflow-x:auto; }
.perf-table { width:100%; border-collapse:collapse; font-size:13px; }
.perf-table th { text-align:left; padding:10px 14px; background:var(--surface2); font-size:10.5px; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--text3); border-bottom:1px solid var(--border); }
.perf-table td { padding:10px 14px; border-bottom:1px solid var(--border); color:var(--text); }
.perf-table tr:hover td { background:rgba(232,160,32,.04); }
/* KPI cards — alignement Performance Commerciale (Bloc 2 — Famille A) */
.perf-kpi { background:var(--surface); border:1px solid var(--border); border-left:4px solid; border-radius:14px; padding:14px 18px; }
.perf-kpi-label { font-size:10.5px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.5px; }
.perf-kpi-val { font-size:24px; font-weight:800; color:var(--text); margin-top:2px; }
.perf-kpi-sub { font-size:11px; color:var(--text3); margin-top:2px; }
</style>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') return;
    const data = @json($monthlyTrend->values());
    const canvas = document.getElementById('perfTechTrendChart');
    if (!canvas || !data?.length) return;
    const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
    const gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
    const tickC = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                label: 'Poses réalisées',
                data:  data.map(d => d.count),
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,.18)',
                borderWidth: 2.5, tension: .35, fill: true,
                pointBackgroundColor: '#6366f1', pointRadius: 4, pointHoverRadius: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ' ' + ctx.parsed.y + ' pose(s)' } } },
            scales: {
                x: { ticks: { color: tickC, font: { size: 11 } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: tickC, font: { size: 11 }, precision: 0 }, grid: { color: gridC } },
            }
        }
    });
})();
</script>
@endpush

</x-admin-layout>

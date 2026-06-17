<x-admin-layout>
<x-slot name="title">Performance techniciens</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.performance.team.index') }}" class="btn btn-ghost btn-sm">👥 Performance équipes</a>
    <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost btn-sm">⚙ Gérer équipes</a>
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

    {{-- Filtres période --}}
    <form method="GET" class="perf-filter-card" style="margin-bottom:16px">
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="fne-field" style="min-width:140px">
                <label>Période</label>
                <select name="preset" onchange="this.form.submit()">
                    <option value="year"    {{ ($preset ?? '') === 'year'    ? 'selected' : '' }}>Cette année</option>
                    <option value="quarter" {{ ($preset ?? '') === 'quarter' ? 'selected' : '' }}>Ce trimestre</option>
                    <option value="month"   {{ ($preset ?? '') === 'month'   ? 'selected' : '' }}>Ce mois</option>
                    <option value="all"     {{ ($preset ?? '') === 'all'     ? 'selected' : '' }}>Tout</option>
                </select>
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Du</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Au</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
        </div>
    </form>

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
</style>

</x-admin-layout>

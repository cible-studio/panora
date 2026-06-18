<x-admin-layout>
<x-slot name="title">Équipe — {{ $team->name }}</x-slot>

<x-slot:topbarLeft>
    @include('admin.performance.partials._smart_back')
    @if(!request()->query('back'))
        <a href="{{ route('admin.performance.team.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Classement équipes
        </a>
    @endif
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    {{-- ?back=performance.team.show pour retour smart depuis teams/edit. --}}
    <a href="{{ route('admin.teams.edit', ['team' => $team, 'back' => 'performance.team']) }}" class="btn btn-ghost btn-sm">✏️ Modifier l'équipe</a>
</x-slot>

@php
    $k = $aggregated['kpis'];
    $teamHex = $team->colorHex();
    $teamBg  = $team->colorBgHex();
@endphp

<div class="perf-page">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,{{ $teamBg }},rgba(99,102,241,.06));border:1px solid var(--border);border-left:5px solid {{ $teamHex }};border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:60px;height:60px;border-radius:14px;background:{{ $teamBg }};border:2px solid {{ $teamHex }};display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:28px;font-weight:800;color:{{ $teamHex }}">
            {{ strtoupper(substr($team->name, 0, 1)) }}
        </div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:20px;font-weight:800;color:var(--text)">{{ $team->name }}</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px">
                Leader : <strong>{{ $team->leader?->name ?? '—' }}</strong>
                · {{ $aggregated['members_count'] }} membre(s)
                · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            </div>
            @if($team->description)
                <div style="font-size:11.5px;color:var(--text3);margin-top:4px;font-style:italic">{{ $team->description }}</div>
            @endif
        </div>
    </div>

    {{-- KPI cards agrégés --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px">
        <div class="perf-kpi" style="border-left-color:#16a34a">
            <div class="perf-kpi-label">Poses réalisées</div>
            <div class="perf-kpi-val" style="color:#15803d">{{ $k['nb_poses_realisees'] }}</div>
            <div class="perf-kpi-sub">{{ $k['nb_poses_total'] }} au total</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#0ea5e9">
            <div class="perf-kpi-label">Réactivité moy équipe</div>
            <div class="perf-kpi-val" style="color:#0369a1">{{ $k['reactivite_avg_min'] !== null ? $k['reactivite_avg_min'].' min' : '—' }}</div>
            <div class="perf-kpi-sub">attribution → début</div>
        </div>
        <div class="perf-kpi" style="border-left-color:{{ $k['taux_poses_en_retard'] <= 5 ? '#16a34a' : ($k['taux_poses_en_retard'] <= 15 ? '#f59e0b' : '#ef4444') }}">
            <div class="perf-kpi-label">% Retard équipe</div>
            <div class="perf-kpi-val" style="color:{{ $k['taux_poses_en_retard'] <= 5 ? '#15803d' : ($k['taux_poses_en_retard'] <= 15 ? '#b45309' : '#b91c1c') }}">{{ $k['taux_poses_en_retard'] }} %</div>
            <div class="perf-kpi-sub">{{ $k['nb_poses_en_retard'] }} pose(s)</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#a855f7">
            <div class="perf-kpi-label">Signalements</div>
            <div class="perf-kpi-val" style="color:#7c3aed">{{ $k['nb_signalements'] }}</div>
            <div class="perf-kpi-sub">{{ $k['nb_piges_rejetees'] }} piges rejetées</div>
        </div>
    </div>

    {{-- Classement membres --}}
    <div class="perf-card">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">🏆 Classement des membres</div>
                <div class="perf-card-sub">{{ $memberRanking->count() }} technicien(s) — ordonné par poses réalisées</div>
                {{-- Garde-fou A : sous-titre explicatif pour le drill équipe. --}}
                <div style="font-size:11px;color:var(--text3);font-style:italic;margin-top:6px;line-height:1.5">
                    ℹ️ Stats calculées sur les poses dont <code style="background:var(--surface2);padding:1px 5px;border-radius:4px">assigned_user_id</code>
                    pointe sur un membre <strong>actuel</strong> de l'équipe. Les anciens membres
                    rattachés à cette équipe par le passé ne sont plus comptés ici.
                </div>
            </div>
        </div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Technicien</th>
                        <th style="text-align:right">Réalisées</th>
                        <th style="text-align:right">Réactivité</th>
                        <th style="text-align:right">% Retard</th>
                        <th style="text-align:right">% Piges rejetées</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($memberRanking as $idx => $row)
                        @php
                            $u = $row['user'];
                            $k2 = $row['kpis'];
                            $rank = ['🥇','🥈','🥉'][$idx] ?? ($idx + 1);
                        @endphp
                        <tr>
                            <td style="font-weight:800">{{ $rank }}</td>
                            <td>
                                <div style="font-weight:700">{{ $u->name }}</div>
                                <div style="font-size:10.5px;color:var(--text3);font-family:monospace">{{ $u->agent_code }}</div>
                            </td>
                            <td style="text-align:right;font-weight:800;color:#16a34a">{{ $k2['nb_poses_realisees'] }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k2['reactivite_avg_min'] !== null ? $k2['reactivite_avg_min'].' min' : '—' }}</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k2['taux_poses_en_retard'] }} %</td>
                            <td style="text-align:right;color:var(--text2)">{{ $k2['taux_piges_rejetees'] }} %</td>
                            <td style="text-align:right"><a href="{{ route('admin.performance.tech.show', $u) }}" class="btn btn-ghost btn-sm" style="font-size:11px">Détail →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text3);font-style:italic">Aucun membre dans cette équipe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
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

</x-admin-layout>

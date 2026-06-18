<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance techniciens — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 14mm 12mm 22mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 17px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 11.5px; color: #111827; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #e8a020; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .period { font-size: 11px; color: #6b7280; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #f3f4f6; padding: 5px 7px; text-align: left; font-size: 8.5px; font-weight: bold; color: #374151; border-bottom: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 5px 7px; font-size: 9.5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: #6b7280; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 8.5px; font-weight: bold; }
    .b-1 { background: #fef3c7; color: #92400e; }
    .b-2 { background: #e5e7eb; color: #4b5563; }
    .b-3 { background: #fed7aa; color: #c2410c; }
    .kpi-grid { display: table; width: 100%; margin-bottom: 10px; border-collapse: separate; border-spacing: 4px; }
    .kpi-row { display: table-row; }
    .kpi { display: table-cell; padding: 8px 10px; background: #fafafa; border-left: 3px solid #6366f1; border-radius: 4px; width: 16%; }
    .kpi-label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.6px; }
    .kpi-value { font-size: 13px; font-weight: bold; color: #111827; margin-top: 2px; }
    .kpi-sub { font-size: 8px; color: #6b7280; margin-top: 1px; }
    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;margin-bottom:5px;">
        @endif
        <h1>PERFORMANCE TECHNICIENS</h1>
        <div class="period">Classement des techniciens · CIBLE CI</div>
        <div class="period">Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)</div>
    </div>
    <div class="right">
        Édité le {{ $generatedAt->format('d/m/Y à H:i') }}<br>
        Par {{ $user->name ?? '—' }}
    </div>
</div>

{{-- KPIs globaux équipe --}}
<div class="kpi-grid">
    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Techniciens actifs</div>
            <div class="kpi-value">{{ $globalKpis['nb_techs_actifs'] }}</div>
        </div>
        <div class="kpi" style="border-left-color:#16a34a">
            <div class="kpi-label">Poses réalisées</div>
            <div class="kpi-value">{{ $globalKpis['nb_poses_realisees'] }}</div>
        </div>
        <div class="kpi" style="border-left-color:#0ea5e9">
            <div class="kpi-label">Réactivité moyenne</div>
            <div class="kpi-value">{{ $globalKpis['reactivite_avg_min'] !== null ? $globalKpis['reactivite_avg_min'].' min' : '—' }}</div>
            <div class="kpi-sub">attribution → début</div>
        </div>
        <div class="kpi" style="border-left-color:#a855f7">
            <div class="kpi-label">Durée pose moyenne</div>
            <div class="kpi-value">{{ $globalKpis['duree_pose_avg_min'] !== null ? $globalKpis['duree_pose_avg_min'].' min' : '—' }}</div>
        </div>
        <div class="kpi" style="border-left-color:#f59e0b">
            <div class="kpi-label">% en retard</div>
            <div class="kpi-value">{{ $globalKpis['taux_poses_en_retard'] }} %</div>
        </div>
        <div class="kpi" style="border-left-color:#ef4444">
            <div class="kpi-label">% piges rejetées</div>
            <div class="kpi-value">{{ $globalKpis['taux_piges_rejetees'] }} %</div>
        </div>
    </div>
</div>

{{-- Leaderboard détaillé --}}
<h2>🏆 Classement des techniciens</h2>
<table>
    <thead>
        <tr>
            <th class="c" style="width:30px">#</th>
            <th>Technicien</th>
            <th>Équipe</th>
            <th class="r">Poses</th>
            <th class="r">Réalisées</th>
            <th class="r">Planifiées</th>
            <th class="r">En retard</th>
            <th class="r">Réactivité moy.</th>
            <th class="r">Durée pose moy.</th>
            <th class="r">% piges rejetées</th>
            <th class="r">Signalements</th>
        </tr>
    </thead>
    <tbody>
        @forelse($leaderboard as $i => $row)
            @php
                $rank = $i + 1;
                $rankBadge = $rank <= 3 ? 'b-' . $rank : '';
                $k = $row['kpis'];
                $rejetCol = $k['taux_piges_rejetees'] <= 5 ? '#16a34a' : ($k['taux_piges_rejetees'] <= 15 ? '#f59e0b' : '#dc2626');
            @endphp
            <tr>
                <td class="c">
                    @if($rank <= 3)
                        <span class="badge {{ $rankBadge }}">{{ $rank }}</span>
                    @else
                        {{ $rank }}
                    @endif
                </td>
                <td class="b">{{ $row['user']->name ?? '—' }}</td>
                <td class="muted">{{ $row['user']->poseTeam?->name ?? '—' }}</td>
                <td class="r">{{ $k['nb_poses_total'] }}</td>
                <td class="r b" style="color:#15803d">{{ $k['nb_poses_realisees'] }}</td>
                <td class="r muted">{{ $k['nb_poses_planifiees'] }}</td>
                <td class="r" style="color:{{ $k['nb_poses_en_retard'] > 0 ? '#dc2626' : '#6b7280' }}">{{ $k['nb_poses_en_retard'] }}</td>
                <td class="r">{{ $k['reactivite_avg_min'] !== null ? $k['reactivite_avg_min'].' min' : '—' }}</td>
                <td class="r">{{ $k['duree_pose_avg_min'] !== null ? $k['duree_pose_avg_min'].' min' : '—' }}</td>
                <td class="r b" style="color:{{ $rejetCol }}">{{ $k['taux_piges_rejetees'] }} %</td>
                <td class="r muted">{{ $k['nb_signalements'] }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="c muted" style="padding:20px;font-style:italic">Aucun technicien actif sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Top par commune --}}
@if($topByCommune->isNotEmpty())
    <h2>🌍 Top techniciens par commune</h2>
    <table>
        <thead>
            <tr>
                <th>Commune</th>
                <th>Technicien top</th>
                <th class="r">Poses réalisées</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topByCommune as $row)
                <tr>
                    <td class="b">{{ $row['commune'] ?? '—' }}</td>
                    <td>{{ $row['user_name'] ?? '—' }}</td>
                    <td class="r b">{{ $row['nb_poses'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Top par campagne --}}
@if($topByCampaign->isNotEmpty())
    <h2>🎯 Top techniciens par campagne</h2>
    <table>
        <thead>
            <tr>
                <th>Campagne</th>
                <th>Technicien top</th>
                <th class="r">Poses réalisées</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topByCampaign as $row)
                <tr>
                    <td class="b">{{ $row['campaign'] ?? '—' }}</td>
                    <td>{{ $row['user_name'] ?? '—' }}</td>
                    <td class="r b">{{ $row['nb_poses'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    CIBLE CI — Performance techniciens · Édité par Panora le {{ $generatedAt->format('d/m/Y à H:i') }}
    · Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
</div>

</body>
</html>

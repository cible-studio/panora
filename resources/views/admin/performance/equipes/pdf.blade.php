<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance équipes — CIBLE CI</title>
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
    .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; } .muted { color: #6b7280; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 8.5px; font-weight: bold; }
    .b-1 { background: #fef3c7; color: #92400e; }
    .b-2 { background: #e5e7eb; color: #4b5563; }
    .b-3 { background: #fed7aa; color: #c2410c; }
    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;margin-bottom:5px;">
        @endif
        <h1>PERFORMANCE ÉQUIPES</h1>
        <div class="period">Classement des équipes de pose · CIBLE CI</div>
        <div class="period">Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)</div>
    </div>
    <div class="right">
        Édité le {{ $generatedAt->format('d/m/Y à H:i') }}<br>
        Par {{ $user->name ?? '—' }}
    </div>
</div>

<h2>🏆 Classement des équipes</h2>
<table>
    <thead>
        <tr>
            <th class="c" style="width:30px">#</th>
            <th>Équipe</th>
            <th>Leader</th>
            <th class="r">Membres</th>
            <th class="r">Poses réalisées</th>
            <th class="r">Réactivité moy.</th>
            <th class="r">% en retard</th>
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
                $team = $row['team'] ?? null;
                $rejetCol = ($k['taux_piges_rejetees'] ?? 0) <= 5 ? '#16a34a' : (($k['taux_piges_rejetees'] ?? 0) <= 15 ? '#f59e0b' : '#dc2626');
            @endphp
            <tr>
                <td class="c">
                    @if($rank <= 3)
                        <span class="badge {{ $rankBadge }}">{{ $rank }}</span>
                    @else
                        {{ $rank }}
                    @endif
                </td>
                <td class="b">{{ $team?->name ?? '—' }}</td>
                <td class="muted">{{ $team?->leader?->name ?? '—' }}</td>
                <td class="r">{{ $row['members_count'] ?? 0 }}</td>
                <td class="r b" style="color:#15803d">{{ $k['nb_poses_realisees'] ?? 0 }}</td>
                <td class="r">{{ \App\Support\HumanDuration::fromMinutes($k['reactivite_avg_min'] ?? null) }}</td>
                <td class="r">{{ ($k['taux_poses_en_retard'] ?? 0) }} %</td>
                <td class="r b" style="color:{{ $rejetCol }}">{{ ($k['taux_piges_rejetees'] ?? 0) }} %</td>
                <td class="r muted">{{ $k['nb_signalements'] ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="c muted" style="padding:20px;font-style:italic">Aucune équipe active sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    CIBLE CI — Performance équipes · Édité par Panora le {{ $generatedAt->format('d/m/Y à H:i') }}
    · Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
</div>

</body>
</html>

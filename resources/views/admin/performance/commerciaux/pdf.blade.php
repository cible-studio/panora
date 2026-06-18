<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance commerciale — CIBLE CI</title>
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
    td { padding: 5px 7px; font-size: 9.5px; border-bottom: 1px solid #f3f4f6; }
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
    .kpi { display: table-cell; padding: 8px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 4px; width: 20%; }
    .kpi-label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.6px; }
    .kpi-value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }
    .kpi-sub { font-size: 8px; color: #6b7280; margin-top: 1px; }
    .color-ht  { border-left-color: #f59e0b; }
    .color-ttc { border-left-color: #e8a020; }
    .color-rec { border-left-color: #16a34a; }
    .color-pan { border-left-color: #3b82f6; }
    .color-act { border-left-color: #a855f7; }
    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
</style>
</head>
<body>

@php
    $fmt  = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtM = fn ($n) => $n >= 1_000_000 ? number_format($n / 1_000_000, 1, ',', ' ') . ' M' : $fmt($n);

    // Agrégats équipe (mêmes calculs que la page web pour cohérence visuelle)
    $caHtEquipe   = $leaderboard->sum('ca_ht');
    $caTtcEquipe  = $leaderboard->sum('ca_ttc');
    $encEquipe    = $leaderboard->sum('encaisse');
    $tauxMoyen    = $caTtcEquipe > 0 ? round(($encEquipe / $caTtcEquipe) * 100, 1) : 0.0;
    $nbCamps      = $leaderboard->sum('nb_campagnes');
    $panierMoy    = $nbCamps > 0 ? round($caTtcEquipe / $nbCamps) : 0;
    $nbActifs     = $leaderboard->count();
@endphp

<div class="header">
    <div class="left">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;margin-bottom:5px;">
        @endif
        <h1>PERFORMANCE COMMERCIALE</h1>
        <div class="period">Classement des commerciaux · CIBLE CI</div>
        <div class="period">Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)</div>
    </div>
    <div class="right">
        Édité le {{ $generatedAt->format('d/m/Y à H:i') }}<br>
        Par {{ $user->name ?? '—' }}
    </div>
</div>

{{-- KPIs équipe --}}
<div class="kpi-grid">
    <div class="kpi-row">
        <div class="kpi color-ht">
            <div class="kpi-label">CA HT équipe</div>
            <div class="kpi-value">{{ $fmtM($caHtEquipe) }}</div>
            <div class="kpi-sub">FCFA · net_ht facturé</div>
        </div>
        <div class="kpi color-ttc">
            <div class="kpi-label">CA TTC équipe</div>
            <div class="kpi-value">{{ $fmtM($caTtcEquipe) }}</div>
            <div class="kpi-sub">FCFA · total campagnes</div>
        </div>
        <div class="kpi color-rec">
            <div class="kpi-label">Taux recouvrement</div>
            <div class="kpi-value">{{ number_format($tauxMoyen, 1, ',', ' ') }} %</div>
            <div class="kpi-sub">encaissé / facturé période</div>
        </div>
        <div class="kpi color-pan">
            <div class="kpi-label">Panier moyen</div>
            <div class="kpi-value">{{ $fmtM($panierMoy) }}</div>
            <div class="kpi-sub">FCFA / campagne</div>
        </div>
        <div class="kpi color-act">
            <div class="kpi-label">Commerciaux actifs</div>
            <div class="kpi-value">{{ $nbActifs }}</div>
            <div class="kpi-sub">{{ $nbCamps }} campagnes au total</div>
        </div>
    </div>
</div>

{{-- Leaderboard détaillé --}}
<h2>🏆 Classement des commerciaux</h2>
<table>
    <thead>
        <tr>
            <th class="c" style="width:30px">#</th>
            <th>Commercial</th>
            <th>Code</th>
            <th class="r">CA HT</th>
            <th class="r">CA TTC</th>
            <th class="r">Encaissé</th>
            <th class="r">Reste dû</th>
            <th class="r">Taux</th>
            <th class="r">Panier moyen</th>
            <th class="r">Campagnes</th>
        </tr>
    </thead>
    <tbody>
        @forelse($leaderboard as $i => $row)
            @php
                $rank = $i + 1;
                $rankBadge = $rank <= 3 ? 'b-' . $rank : '';
                $tauxCol = $row['taux_recouvrement'] >= 70 ? '#16a34a'
                         : ($row['taux_recouvrement'] >= 40 ? '#f59e0b' : '#dc2626');
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
                <td class="muted">{{ $row['user']->employee_code ?? '—' }}</td>
                <td class="r">{{ $fmtM($row['ca_ht']) }}</td>
                <td class="r b">{{ $fmtM($row['ca_ttc']) }}</td>
                <td class="r" style="color:#16a34a">{{ $fmtM($row['encaisse']) }}</td>
                <td class="r" style="color:{{ $row['reste_du'] > 0 ? '#dc2626' : '#6b7280' }}">{{ $fmtM($row['reste_du']) }}</td>
                <td class="r b" style="color:{{ $tauxCol }}">{{ number_format($row['taux_recouvrement'], 1, ',', ' ') }} %</td>
                <td class="r muted">{{ $fmtM($row['panier_moyen']) }}</td>
                <td class="r b">{{ $row['nb_campagnes'] }}</td>
            </tr>
        @empty
            <tr><td colspan="10" class="c muted" style="padding:20px;font-style:italic">Aucun commercial actif sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Top commerciaux par secteur d'activité.
     Clés réelles renvoyées par CommercialPerformanceService::topCommercialBySector() :
       sector · commercial_id · commercial_name · ca · count · sector_total_ca · share_pct --}}
@if($topBySector->isNotEmpty())
    <h2>🎯 Top commercial par secteur d'activité</h2>
    <table>
        <thead>
            <tr>
                <th>Secteur</th>
                <th>Commercial dominant</th>
                <th class="r">CA secteur (top)</th>
                <th class="r">CA secteur total</th>
                <th class="r">Campagnes</th>
                <th class="r">Part du top</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topBySector as $sector)
                <tr>
                    <td class="b">{{ $sector['sector'] ?? '—' }}</td>
                    <td>{{ $sector['commercial_name'] ?? '—' }}</td>
                    <td class="r b">{{ $fmtM($sector['ca'] ?? 0) }}</td>
                    <td class="r muted">{{ $fmtM($sector['sector_total_ca'] ?? 0) }}</td>
                    <td class="r">{{ $sector['count'] ?? 0 }}</td>
                    <td class="r muted">{{ number_format($sector['share_pct'] ?? 0, 1, ',', ' ') }} %</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    CIBLE CI — Performance commerciale · Édité par Panora le {{ $generatedAt->format('d/m/Y à H:i') }}
    · Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
</div>

</body>
</html>

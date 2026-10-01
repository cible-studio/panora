<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance commerciale — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
@include('pdf.partials.charte-styles')
<style>
    @page { size: A4 landscape; margin: 14mm 12mm 22mm 12mm !important; }
    body { font-size: 10px; color: {{ $charte['noir'] }}; line-height: 1.25; }
    h2 {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 11.5px; font-weight: 700; color: {{ $charte['noir'] }};
        margin: 14px 0 6px; padding: 0 0 4px 8px;
        border-left: 3px solid {{ $charte['rouge'] }};
        border-bottom: 1px solid {{ $charte['gris'] }};
    }
    table.ch-table { margin-bottom: 8px; font-size: 9.5px; }
    .ch-table th.r, .ch-table td.r { text-align: right; }
    .ch-table th.c, .ch-table td.c { text-align: center; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: {{ $charte['texte_doux'] }}; }
    /* Médailles du podium : or → jaune, argent → gris, bronze → jaune clair. */
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .b-1 { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .b-2 { background: {{ $charte['gris'] }}; color: {{ $charte['noir'] }}; }
    .b-3 { background: {{ $charte['jaune_clair'] }}; color: {{ $charte['noir'] }}; }
    .ch-kpi { width: 20%; }
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

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre + méta). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'PERFORMANCE COMMERCIALE',
    'docSubtitle' => 'Classement des commerciaux · CIBLE CI',
    'docMeta'     => [
        'Édité le ' . $generatedAt->format('d/m/Y à H:i'),
        'Par ' . ($user->name ?? '—'),
    ],
])

<div class="ch-info">
    <strong>Période :</strong> {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)
</div>

{{-- KPIs équipe --}}
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-jaune">
            <div class="ch-kpi-label">CA HT équipe</div>
            <div class="ch-kpi-value">{{ $fmtM($caHtEquipe) }}</div>
            <div class="ch-kpi-sub">FCFA · net_ht facturé</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">CA TTC équipe</div>
            <div class="ch-kpi-value">{{ $fmtM($caTtcEquipe) }}</div>
            <div class="ch-kpi-sub">FCFA · total campagnes</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="ch-kpi-label">Taux recouvrement</div>
            <div class="ch-kpi-value">{{ number_format($tauxMoyen, 1, ',', ' ') }} %</div>
            <div class="ch-kpi-sub">encaissé / facturé période</div>
        </td>
        <td class="ch-kpi k-bleu">
            <div class="ch-kpi-label">Panier moyen</div>
            <div class="ch-kpi-value">{{ $fmtM($panierMoy) }}</div>
            <div class="ch-kpi-sub">FCFA / campagne</div>
        </td>
        <td class="ch-kpi k-violet">
            <div class="ch-kpi-label">Commerciaux actifs</div>
            <div class="ch-kpi-value">{{ $nbActifs }}</div>
            <div class="ch-kpi-sub">{{ $nbCamps }} campagnes au total</div>
        </td>
    </tr>
</table>

{{-- Leaderboard détaillé --}}
<h2>Classement des commerciaux</h2>
<table class="ch-table">
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
                // Charte : ≥ 70 % vert · ≥ 40 % jaune (badge, jamais de texte jaune) · sinon rouge.
                $tauxTone = $row['taux_recouvrement'] >= 70 ? 'vert'
                          : ($row['taux_recouvrement'] >= 40 ? 'jaune' : 'rouge');
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
                <td class="r c-vert">{{ $fmtM($row['encaisse']) }}</td>
                <td class="r {{ $row['reste_du'] > 0 ? 'c-rouge' : 'c-doux' }}">{{ $fmtM($row['reste_du']) }}</td>
                <td class="r b"><span class="ch-badge ch-badge-{{ $tauxTone }}">{{ number_format($row['taux_recouvrement'], 1, ',', ' ') }} %</span></td>
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
    <h2>Top commercial par secteur d'activité</h2>
    <table class="ch-table">
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

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Performance commerciale · Édité par Panora le ' . $generatedAt->format('d/m/Y à H:i')
                  . ' · Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
])

</body>
</html>

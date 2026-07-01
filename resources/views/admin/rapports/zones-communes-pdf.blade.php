<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Zones & Communes — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 20mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 16px; color: #e8a020; margin: 0 0 4px; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left  { display: table-cell; vertical-align: middle; }
    .header .mid   { display: table-cell; vertical-align: middle; }
    .header .right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8.5px; color: #6b7280; }
    .header .logo { height: 38px; margin-right: 14px; }
    .period { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .meta { font-size: 9px; color: #374151; margin-top: 8px; padding: 6px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 3px; }
    .meta strong { color: #111827; }
    .kpis { display: table; width: 100%; margin: 8px 0 12px; border-collapse: separate; border-spacing: 5px 0; }
    .kpis .cell { display: table-cell; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 4px; padding: 6px 10px; text-align: center; }
    .kpis .cell .n { font-size: 15px; font-weight: bold; color: #b45309; display: block; }
    .kpis .cell .l { font-size: 7.5px; text-transform: uppercase; color: #78716c; letter-spacing: .4px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #0a0c10; padding: 6px 8px; text-align: left; font-size: 8px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    th.r, td.r { text-align: right; }
    th.c, td.c { text-align: center; }
    td { padding: 5px 8px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #fafafa; }
    .pct { font-weight: bold; }
    .pct-hi { color: #16a34a; }
    .pct-mid { color: #f97316; }
    .pct-lo { color: #dc2626; }
    .badge-zone { display: inline-block; padding: 1px 6px; font-size: 8px; border-radius: 999px; }
    .badge-abj { background: rgba(59,130,246,.15); color: #1d4ed8; }
    .badge-int { background: rgba(16,185,129,.15); color: #047857; }
    .footer { position: fixed; bottom: 6mm; left: 10mm; right: 10mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
    .footer .pagenum:before { content: counter(page) " / " counter(pages); }
    .totals { background: #fef3c7; }
    .totals td { font-weight: bold; color: #92400e; border-top: 2px solid #f59e0b; }
</style>
</head>
<body>

<div class="header">
    @if(!empty($logoCibleLight))
        <div class="mid">
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" class="logo">
        </div>
    @endif
    <div class="left">
        <h1>ZONES & COMMUNES</h1>
        <div class="period">Occupation du parc par commune · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

@include('admin.rapports.partials._filter_recap_pdf')

<div class="kpis">
    <div class="cell"><span class="n">{{ number_format($summary['nb_communes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Communes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_panels'] ?? 0, 0, ',', ' ') }}</span><span class="l">Panneaux</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_occupes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Occupés</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_libres'] ?? 0, 0, ',', ' ') }}</span><span class="l">Libres</span></div>
    <div class="cell"><span class="n">{{ ($summary['taux_moyen'] ?? 0) }} %</span><span class="l">Taux moyen</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['ca_total'] ?? 0, 0, ',', ' ') }}</span><span class="l">CA FCFA</span></div>
</div>

<div class="meta">
    Période : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
    · Statuts campagne inclus : actif, planifié, terminé.
    · Trié par taux d'occupation décroissant.
</div>

<table>
    <thead>
        <tr>
            <th>Commune</th>
            <th>Ville</th>
            <th>Zone</th>
            <th class="c">Total pann.</th>
            <th class="c">Occupés</th>
            <th class="c">Libres</th>
            <th class="c">Maint.</th>
            <th class="r">Taux occup.</th>
            <th class="r">Tarif moy./mois</th>
            <th class="r">CA contractuel (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            @php
                $rate = $r['taux'] ?? 0;
                $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
                $zoneClass = $r['zone'] === 'Abidjan' ? 'badge-abj' : 'badge-int';
            @endphp
            <tr>
                <td><strong>{{ $r['commune'] }}</strong></td>
                <td>{{ $r['city'] }}</td>
                <td><span class="badge-zone {{ $zoneClass }}">{{ $r['zone'] }}</span></td>
                <td class="c">{{ (int) $r['total'] }}</td>
                <td class="c">{{ (int) $r['occupes'] }}</td>
                <td class="c">{{ (int) $r['libres'] }}</td>
                <td class="c">{{ (int) $r['maintenance'] }}</td>
                <td class="r pct {{ $rateClass }}">{{ $rate }} %</td>
                <td class="r">{{ $r['tarif_moyen'] ? number_format((float) $r['tarif_moyen'], 0, ',', ' ') : '—' }}</td>
                <td class="r">{{ number_format((float) $r['ca_annee'], 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="10" style="text-align:center;color:#6b7280;font-style:italic;padding:24px">Aucune commune avec des panneaux sur ces filtres.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="totals">
                <td colspan="3" class="r">TOTAL ({{ $rows->count() }} communes)</td>
                <td class="c">{{ $rows->sum('total') }}</td>
                <td class="c">{{ $rows->sum('occupes') }}</td>
                <td class="c">{{ $rows->sum('libres') }}</td>
                <td class="c">{{ $rows->sum('maintenance') }}</td>
                <td class="r">{{ ($summary['taux_moyen'] ?? 0) }} %</td>
                <td class="r">—</td>
                <td class="r">{{ number_format($rows->sum('ca_annee'), 0, ',', ' ') }}</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora · Page <span class="pagenum"></span>
</div>

</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
<style>
    /* margin-bottom 20mm = espace réservé pour le footer fixed
       (sans ça, le tableau se mélange avec le footer en bas de page 2+). */
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
    table { width: 100%; border-collapse: collapse; }
    th { background: #0a0c10; padding: 6px 8px; text-align: left; font-size: 8px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    th.r, td.r { text-align: right; }
    th.c, td.c { text-align: center; }
    td { padding: 5px 8px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #fafafa; }
    .ref { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; }
    .pct { font-weight: bold; }
    .pct-hi { color: #16a34a; }
    .pct-mid { color: #f97316; }
    .pct-lo { color: #dc2626; }
    .badge-zone { display: inline-block; padding: 1px 6px; font-size: 8px; border-radius: 999px; }
    .badge-abj { background: rgba(59,130,246,.15); color: #1d4ed8; }
    .badge-int { background: rgba(16,185,129,.15); color: #047857; }
    /* footer dans l'espace réservé par margin-bottom 20mm de @page */
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
        <h1>OCCUPATION DES PANNEAUX</h1>
        <div class="period">Rapport détaillé par panneau · {{ $operatorName ?? 'CIBLE CI' }}</div>
        {{-- Période retirée du header : elle est désormais portée par le
             bandeau "Type d'export" plus bas (plus complet, avec le preset). --}}
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

{{-- ════ Récap filtres actifs ════
     Source unique : RapportFilterContextService — cohérent avec l'Excel
     et le PDF Synthèse exécutive. Le bloc "stats agrégées" qui suit
     reste à part : ce sont des chiffres calculés, pas du contexte filtre.
     La ligne "Zone :" est retirée d'ici car déjà présente dans le récap. --}}
@include('admin.rapports.partials._filter_recap_pdf')

<div class="meta">
    <strong>{{ $panels->count() }}</strong> panneau(x) ·
    <strong>Total jours occupés :</strong> {{ number_format($panels->sum('days_occupied'), 0, ',', ' ') }} ·
    <strong>Taux moyen :</strong> {{ $panels->count() > 0 ? round($panels->avg('occupation_rate'), 1) : 0 }} % ·
    <strong>CA estimé :</strong> {{ number_format($panels->sum('estimated_revenue'), 0, ',', ' ') }} FCFA
</div>

<table>
    <thead>
        <tr>
            <th>Référence</th>
            <th>Emplacement</th>
            <th>Commune</th>
            <th>Zone</th>
            <th class="r">Tarif/mois</th>
            <th class="c">Camp.</th>
            <th class="r">Jours occupés</th>
            <th class="r">Taux occup.</th>
            <th class="r">CA estimé (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($panels as $p)
            @php
                $rate = $p->occupation_rate ?? 0;
                $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
                $zoneClass = ($p->zone ?? '') === 'Abidjan' ? 'badge-abj' : 'badge-int';
            @endphp
            <tr>
                <td class="ref">{{ $p->reference }}</td>
                <td>{{ \Illuminate\Support\Str::limit($p->name ?? '—', 38) }}</td>
                <td>{{ $p->commune_name ?? '—' }}</td>
                <td><span class="badge-zone {{ $zoneClass }}">{{ $p->zone ?? '—' }}</span></td>
                <td class="r">{{ $p->monthly_rate ? number_format((float)$p->monthly_rate, 0, ',', ' ') : '—' }}</td>
                <td class="c">{{ (int)$p->campaigns_count }}</td>
                <td class="r">{{ (int)$p->days_occupied }} j</td>
                <td class="r pct {{ $rateClass }}">{{ $rate }} %</td>
                <td class="r">{{ number_format((float)$p->estimated_revenue, 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;color:#6b7280;font-style:italic;padding:24px">Aucune donnée sur la période et les filtres choisis.</td></tr>
        @endforelse
        @if($panels->isNotEmpty())
            <tr class="totals">
                <td colspan="5" class="r">TOTAL ({{ $panels->count() }} panneaux)</td>
                <td class="c">{{ $panels->sum('campaigns_count') }}</td>
                <td class="r">{{ number_format($panels->sum('days_occupied'), 0, ',', ' ') }} j</td>
                <td class="r">{{ $panels->count() > 0 ? round($panels->avg('occupation_rate'), 1) : 0 }} %</td>
                <td class="r">{{ number_format($panels->sum('estimated_revenue'), 0, ',', ' ') }}</td>
            </tr>
        @endif
    </tbody>
</table>

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora · Page <span class="pagenum"></span>
</div>

</body>
</html>

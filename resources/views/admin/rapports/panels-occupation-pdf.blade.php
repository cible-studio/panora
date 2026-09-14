<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 14mm 10mm 22mm 10mm; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9px;
        color: #1f2937;
        line-height: 1.35;
        padding-bottom: 4mm;
    }

    /* ═══ HEADER pro ═══ */
    .doc-header {
        display: table;
        width: 100%;
        margin-bottom: 10px;
        background: #0a0c10;
        border-radius: 4px;
        overflow: hidden;
    }
    .doc-header .left {
        display: table-cell;
        vertical-align: middle;
        padding: 10px 14px;
        border-left: 4px solid #e8a020;
    }
    .doc-header .right {
        display: table-cell;
        vertical-align: middle;
        text-align: right;
        padding: 10px 14px;
        color: #cbd5e1;
        font-size: 8.5px;
    }
    .doc-header h1 {
        margin: 0;
        color: #fff;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }
    .doc-header .subtitle {
        margin-top: 3px;
        font-size: 9.5px;
        color: #e8a020;
        letter-spacing: 0.3px;
    }
    .doc-header .right .lbl { color: #94a3b8; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .doc-header .right .val { color: #fff; font-size: 9.5px; font-weight: 600; }

    /* ═══ Meta stats (tuiles) ═══ */
    .meta {
        display: table;
        width: 100%;
        margin: 8px 0 10px;
        border-collapse: separate;
        border-spacing: 5px 0;
    }
    .meta .cell {
        display: table-cell;
        background: #fefaf1;
        border: 1px solid #f3d999;
        border-left: 3px solid #e8a020;
        border-radius: 4px;
        padding: 6px 10px;
        text-align: center;
    }
    .meta .val { font-size: 13px; font-weight: bold; color: #92400e; line-height: 1; }
    .meta .lbl { font-size: 7.5px; color: #78350f; margin-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

    /* ═══ Table — CSS minimal pour éviter les bugs DomPDF ═══
       Fix bug 2026-09-14 : sans page-break-inside sur tr, DomPDF
       ne saute pas de lignes à cause de calculs de hauteur foireux
       sur les inline-block avec padding. Tableau compact et fluide. */
    table.data { width: 100%; border-collapse: collapse; }
    table.data th {
        background: #0a0c10;
        color: #fff;
        padding: 5px 6px;
        text-align: left;
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    table.data th.r, table.data td.r { text-align: right; }
    table.data th.c, table.data td.c { text-align: center; }
    table.data td {
        padding: 3px 6px;
        font-size: 8.5px;
        border-bottom: 1px solid #f3f4f6;
    }
    table.data tr.even td { background: #fafafa; }
    table.data tr.maint td { background: #fffbeb; }
    .num  { color: #6b7280; font-size: 8px; font-family: 'Courier New', monospace; }
    .ref  { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 8.5px; }
    .pct-hi  { color: #16a34a; font-weight: bold; }
    .pct-mid { color: #f97316; font-weight: bold; }
    .pct-lo  { color: #dc2626; font-weight: bold; }
    .fmt { color: #4b5563; font-size: 8px; }
    /* Zone : texte simple coloré, PAS de span inline-block (bug DomPDF) */
    .zone-abj { color: #1d4ed8; font-weight: bold; font-size: 8px; }
    .zone-int { color: #047857; font-weight: bold; font-size: 8px; }
    .maint-tag { color: #92400e; font-weight: bold; font-size: 7.5px; }

    /* Ligne totaux */
    tr.totals td {
        font-weight: bold;
        color: #92400e;
        border-top: 2px solid #f59e0b;
        background: #fef3c7;
        padding: 6px;
    }

    /* Footer fixed */
    .footer {
        position: fixed;
        bottom: 4mm;
        left: 10mm;
        right: 10mm;
        height: 12mm;
        font-size: 7.5px;
        color: #6b7280;
        background: #fff;
        border-top: 1px solid #d1d5db;
        padding-top: 4mm;
    }
    .footer .l { float: left; }
    .footer .r { float: right; }
    .footer .c { text-align: center; }
</style>
</head>
<body>

<div class="doc-header">
    <div class="left">
        <h1>Occupation des panneaux</h1>
        <div class="subtitle">Rapport détaillé par panneau · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="right">
        <div><span class="lbl">Édité le</span> <span class="val">{{ now()->format('d/m/Y H:i') }}</span></div>
        <div><span class="lbl">Par</span> <span class="val">{{ $user->name ?? '—' }}</span></div>
        <div><span class="lbl">Réf.</span> <span class="val">C{{ strtoupper(substr(md5(now()), 0, 8)) }}</span></div>
    </div>
</div>

@include('admin.rapports.partials._filter_recap_pdf')

@php
    $nbMaintenance   = $panels->filter(fn ($p) => $p->status === 'maintenance')->count();
    $totalJours      = $panels->sum('days_occupied');
    $tauxMoyen       = $panels->count() > 0 ? round($panels->avg('occupation_rate'), 1) : 0;
    $totalCampagnes  = $panels->sum('campaigns_count');
@endphp

<div class="meta">
    <div class="cell">
        <div class="val">{{ $panels->count() }}</div>
        <div class="lbl">Panneaux</div>
    </div>
    <div class="cell">
        <div class="val">{{ number_format($totalJours, 0, ',', ' ') }}</div>
        <div class="lbl">Jours occupés</div>
    </div>
    <div class="cell">
        <div class="val">{{ $tauxMoyen }} %</div>
        <div class="lbl">Taux moyen</div>
    </div>
    <div class="cell">
        <div class="val">{{ $totalCampagnes }}</div>
        <div class="lbl">Campagnes cumulées</div>
    </div>
    @if($nbMaintenance > 0)
        <div class="cell" style="background:#fef3c7;border-color:#fbbf24;border-left-color:#d97706">
            <div class="val" style="color:#92400e">{{ $nbMaintenance }}</div>
            <div class="lbl">En maintenance</div>
        </div>
    @endif
</div>

<table class="data">
    <thead>
        <tr>
            <th style="width:22px" class="c">N°</th>
            <th style="width:78px">Référence</th>
            <th>Emplacement</th>
            <th style="width:95px">Commune</th>
            <th style="width:55px" class="c">Zone</th>
            <th style="width:60px" class="c">Format</th>
            <th style="width:45px" class="c">Camp.</th>
            <th style="width:65px" class="r">Jours occ.</th>
            <th style="width:55px" class="r">Taux occ.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($panels as $index => $p)
            @php
                $rate = (float) ($p->occupation_rate ?? 0);
                $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
                $isMaint   = $p->status === 'maintenance';
                $isAbj     = ($p->zone ?? '') === 'Abidjan';
                $rowClass  = $isMaint ? 'maint' : (($index % 2) ? 'even' : '');
            @endphp
            <tr @if($rowClass) class="{{ $rowClass }}" @endif>
                <td class="c num">{{ $index + 1 }}</td>
                <td>
                    <span class="ref">{{ $p->reference }}</span>@if($isMaint) <span class="maint-tag">🔧</span>@endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit($p->name ?? '—', 45) }}</td>
                <td>{{ $p->commune_name ?? '—' }}</td>
                <td class="c">
                    @if($isAbj)
                        <span class="zone-abj">Abidjan</span>
                    @else
                        <span class="zone-int">Intérieur</span>
                    @endif
                </td>
                <td class="c fmt">{{ $p->format_name ?? '—' }}</td>
                <td class="c">{{ (int) $p->campaigns_count }}</td>
                <td class="r">{{ (int) $p->days_occupied }} j</td>
                <td class="r {{ $rateClass }}">{{ $rate }} %</td>
            </tr>
        @empty
            <tr>
                <td colspan="9" style="text-align:center;color:#6b7280;font-style:italic;padding:24px">
                    Aucune donnée sur la période et les filtres choisis.
                </td>
            </tr>
        @endforelse

        @if($panels->isNotEmpty())
            @php
                $totalLabel = 'TOTAL (' . $panels->count() . ' panneaux'
                    . ($nbMaintenance > 0 ? ' · dont ' . $nbMaintenance . ' en maintenance' : '')
                    . ')';
            @endphp
            <tr class="totals">
                <td colspan="6" class="r">{{ $totalLabel }}</td>
                <td class="c">{{ $totalCampagnes }}</td>
                <td class="r">{{ number_format($totalJours, 0, ',', ' ') }} j</td>
                <td class="r">{{ $tauxMoyen }} %</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ═══ Footer : pas de placeholder "Page 1" HTML, la pagination
     est entièrement dessinée par le controller via getCanvas()->
     page_text() qui écrit sur TOUTES les pages. ═══ --}}
<div class="footer">
    <div class="l">CIBLE SARL · Régie OOH Côte d'Ivoire</div>
    <div class="c">Document généré automatiquement par Panora</div>
    <div class="r">&nbsp;</div>
</div>

</body>
</html>

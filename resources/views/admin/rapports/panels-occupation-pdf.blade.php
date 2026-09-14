<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 16mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.3; margin: 0; padding: 0; }

    /* ═══ HEADER — Layout FLOAT (pas de <table>, pas de display:table) ═══
       Fix bug DomPDF v3 : les <table> de layout génèrent des sauts de
       page fantômes derrière eux. Solution : float pur. */
    .header-band {
        background: #0a0c10;
        padding: 8px 12px;
        border-left: 4px solid #e8a020;
        margin-bottom: 6px;
        overflow: hidden;
    }
    .header-band .h-left  { float: left;  width: 65%; }
    .header-band .h-right { float: right; width: 33%; text-align: right; color: #cbd5e1; font-size: 8.5px; }
    .header-band h1 { margin: 0; color: #fff; font-size: 14px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; }
    .header-band .subtitle { margin-top: 2px; font-size: 9px; color: #e8a020; }
    .header-band .lbl { color: #94a3b8; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .header-band .val { color: #fff; font-size: 9px; font-weight: 600; }

    /* ═══ Meta stats — 1 ligne texte enrichi (fini les tuiles bugées) ═══ */
    .stats-line {
        background: #fefaf1;
        border: 1px solid #f3d999;
        border-left: 3px solid #e8a020;
        border-radius: 3px;
        padding: 6px 10px;
        margin: 4px 0 8px;
        font-size: 9px;
        color: #78350f;
        line-height: 1.6;
    }
    .stats-line .k { color: #92400e; font-weight: bold; }
    .stats-line .v { color: #78350f; font-weight: bold; font-size: 11px; }
    .stats-line .sep { color: #d97706; margin: 0 6px; }
    .stats-line .maint { color: #b45309; font-weight: bold; }

    /* ═══ Table data ═══ */
    table.data { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.data th { background: #0a0c10; color: #fff; padding: 5px 6px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
    table.data th.r, table.data td.r { text-align: right; }
    table.data th.c, table.data td.c { text-align: center; }
    table.data td { padding: 3px 6px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    table.data tr.even td { background: #fafafa; }
    table.data tr.maint td { background: #fffbeb; }
    .num  { color: #6b7280; font-size: 8px; font-family: 'Courier New', monospace; }
    .ref  { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 8.5px; }
    .pct-hi  { color: #16a34a; font-weight: bold; }
    .pct-mid { color: #f97316; font-weight: bold; }
    .pct-lo  { color: #dc2626; font-weight: bold; }
    .fmt { color: #4b5563; font-size: 8px; }
    .zone-abj { color: #1d4ed8; font-weight: bold; font-size: 8px; }
    .zone-int { color: #047857; font-weight: bold; font-size: 8px; }
    .maint-tag { color: #92400e; font-weight: bold; font-size: 7.5px; }

    tr.totals td {
        font-weight: bold;
        color: #92400e;
        border-top: 2px solid #f59e0b;
        background: #fef3c7;
        padding: 6px;
    }

    .footer {
        position: fixed;
        bottom: 3mm;
        left: 10mm;
        right: 10mm;
        height: 8mm;
        font-size: 7.5px;
        color: #6b7280;
        border-top: 1px solid #d1d5db;
        padding-top: 2mm;
    }
    .footer .l { float: left; }
    .footer .r { float: right; }
    .footer .c { text-align: center; }
</style>
</head>
<body>

{{-- HEADER : divs floatés, aucune <table> --}}
<div class="header-band">
    <div class="h-left">
        <h1>Occupation des panneaux</h1>
        <div class="subtitle">Rapport détaillé par panneau · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="h-right">
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

{{-- META : une ligne texte enrichi (plus de tuiles qui bugent) --}}
<div class="stats-line">
    <span class="k">Panneaux :</span> <span class="v">{{ $panels->count() }}</span>
    <span class="sep">·</span>
    <span class="k">Jours occupés :</span> <span class="v">{{ number_format($totalJours, 0, ',', ' ') }}</span>
    <span class="sep">·</span>
    <span class="k">Taux moyen :</span> <span class="v">{{ $tauxMoyen }} %</span>
    <span class="sep">·</span>
    <span class="k">Campagnes cumulées :</span> <span class="v">{{ $totalCampagnes }}</span>
    @if($nbMaintenance > 0)
        <span class="sep">·</span>
        <span class="maint">🔧 {{ $nbMaintenance }} en maintenance</span>
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

<div class="footer">
    <div class="l">CIBLE SARL · Régie OOH Côte d'Ivoire</div>
    <div class="c">Document généré automatiquement par Panora</div>
    <div class="r">&nbsp;</div>
</div>

</body>
</html>

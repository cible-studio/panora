<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation des panneaux — CIBLE CI</title>
<style>
    /* margin-bottom 26mm réservé au footer fixed (bug DomPDF connu). */
    @page { size: A4 landscape; margin: 14mm 10mm 22mm 10mm; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 9px;
        color: #1f2937;
        line-height: 1.4;
        padding-bottom: 4mm;
    }

    /* ═══ HEADER professionnel — bande foncée + accent orange ═══ */
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

    /* ═══ Bandeau meta stats (allégé — sans CA) ═══ */
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

    /* ═══ Table ═══ */
    table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr    { page-break-inside: avoid; }
    th {
        background: #0a0c10;
        padding: 6px 7px;
        text-align: left;
        font-size: 7.5px;
        font-weight: bold;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    th.r, td.r { text-align: right; }
    th.c, td.c { text-align: center; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:nth-child(even) td { background: #fafafa; }
    .num  { color: #6b7280; font-size: 8px; font-family: 'Courier New', monospace; text-align: right; }
    .ref  { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 8.5px; }
    .pct  { font-weight: bold; }
    .pct-hi  { color: #16a34a; }
    .pct-mid { color: #f97316; }
    .pct-lo  { color: #dc2626; }
    .fmt { color: #4b5563; font-size: 8px; }

    /* Badges */
    .badge-zone { display: inline-block; padding: 1px 6px; font-size: 7.5px; border-radius: 999px; }
    .badge-abj  { background: #dbeafe; color: #1d4ed8; }
    .badge-int  { background: #d1fae5; color: #047857; }
    .badge-maint {
        display: inline-block;
        padding: 1px 5px;
        font-size: 7px;
        border-radius: 3px;
        background: #fef3c7;
        color: #92400e;
        font-weight: bold;
        margin-left: 4px;
        letter-spacing: 0.3px;
    }
    tr.row-maintenance td { background: #fffbeb !important; }

    /* Ligne totaux */
    .totals td {
        font-weight: bold;
        color: #92400e;
        border-top: 2px solid #f59e0b;
        background: #fef3c7 !important;
    }

    /* ═══ Footer propre — visuellement séparé du corps ═══ */
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
    .footer .c { text-align: center; }
    .footer .r { float: right; font-weight: bold; color: #0a0c10; }
</style>
</head>
<body>

{{-- ═══ HEADER professionnel (fond foncé, sobre) ═══ --}}
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

{{-- Récap filtres actifs (source unique RapportFilterContextService) --}}
@include('admin.rapports.partials._filter_recap_pdf')

{{-- ═══ Meta stats — sans CA (retiré à la demande 2026-09-14) ═══ --}}
@php
    // Split panneaux : opérationnels vs maintenance
    $nbMaintenance = $panels->filter(fn ($p) => $p->status === 'maintenance')->count();
    $nbOperationnels = $panels->count() - $nbMaintenance;
    $totalJours = $panels->sum('days_occupied');
    $tauxMoyen = $panels->count() > 0 ? round($panels->avg('occupation_rate'), 1) : 0;
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
        <div class="val">{{ $panels->sum('campaigns_count') }}</div>
        <div class="lbl">Campagnes cumulées</div>
    </div>
    @if($nbMaintenance > 0)
        <div class="cell" style="background:#fef3c7;border-color:#fbbf24;border-left-color:#d97706">
            <div class="val" style="color:#92400e">{{ $nbMaintenance }}</div>
            <div class="lbl">En maintenance</div>
        </div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th style="width:22px" class="c">N°</th>
            <th style="width:70px">Référence</th>
            <th>Emplacement</th>
            <th style="width:95px">Commune</th>
            <th style="width:65px" class="c">Zone</th>
            <th style="width:60px" class="c">Format</th>
            <th style="width:55px" class="c">Camp.</th>
            <th style="width:70px" class="r">Jours occ.</th>
            <th style="width:65px" class="r">Taux occ.</th>
        </tr>
    </thead>
    <tbody>
        @forelse($panels as $index => $p)
            @php
                $rate = $p->occupation_rate ?? 0;
                $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
                $zoneClass = ($p->zone ?? '') === 'Abidjan' ? 'badge-abj' : 'badge-int';
                $isMaint   = $p->status === 'maintenance';
            @endphp
            <tr @if($isMaint) class="row-maintenance" @endif>
                <td class="num">{{ $index + 1 }}</td>
                <td class="ref">
                    {{ $p->reference }}
                    @if($isMaint)<span class="badge-maint">🔧 MAINT.</span>@endif
                </td>
                <td>{{ \Illuminate\Support\Str::limit($p->name ?? '—', 42) }}</td>
                <td>{{ $p->commune_name ?? '—' }}</td>
                <td class="c">
                    <span class="badge-zone {{ $zoneClass }}">{{ $p->zone ?? '—' }}</span>
                </td>
                <td class="c fmt">{{ $p->format_name ?? '—' }}</td>
                <td class="c">{{ (int) $p->campaigns_count }}</td>
                <td class="r">{{ (int) $p->days_occupied }} j</td>
                <td class="r pct {{ $rateClass }}">{{ $rate }} %</td>
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
                <td class="c">{{ $panels->sum('campaigns_count') }}</td>
                <td class="r">{{ number_format($totalJours, 0, ',', ' ') }} j</td>
                <td class="r">{{ $tauxMoyen }} %</td>
            </tr>
        @endif
    </tbody>
</table>

{{-- ═══ Footer — nettement séparé, avec vraie pagination X / Y ═══ --}}
<div class="footer">
    <div class="l">CIBLE SARL · Régie OOH Côte d'Ivoire</div>
    <div class="r">Page <span class="pagenum-fake">1</span></div>
    <div class="c">Document généré automatiquement par Panora</div>
</div>

{{-- DomPDF page_script : injecte la VRAIE numérotation "X / Y" en
     surimpression du texte fake — c'est la seule façon d'obtenir
     un total de pages correct avec DomPDF (le counter(pages) CSS
     renvoie 0 dans un position:fixed). --}}
<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        $font = $fontMetrics->get_font("DejaVu Sans", "bold");
        $size = 8;
        $text = "Page " . $PAGE_NUM . " / " . $PAGE_COUNT;
        $width  = $fontMetrics->get_text_width($text, $font, $size);
        $pageWidth  = $pdf->get_width();
        $pageHeight = $pdf->get_height();
        // Position : bas-droite, aligné sur le footer (bottom 4mm ≈ 11.3 pt)
        $x = $pageWidth - $width - 30;
        $y = $pageHeight - 26;
        // Masque blanc pour cacher le placeholder "Page 1"
        $pdf->filled_rectangle($x - 4, $y - 2, $width + 8, $size + 4, [1, 1, 1]);
        $pdf->text($x, $y, $text, $font, $size, [0.04, 0.05, 0.06]);
    ');
}
</script>

</body>
</html>

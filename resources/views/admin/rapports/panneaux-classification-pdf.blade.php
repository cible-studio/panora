<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classification panneaux — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 14mm 10mm 22mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; padding-bottom: 4mm; }

    /* Header professionnel identique au PDF Occupation */
    .doc-header { display: table; width: 100%; margin-bottom: 10px; background: #0a0c10; border-radius: 4px; overflow: hidden; }
    .doc-header .left { display: table-cell; vertical-align: middle; padding: 10px 14px; border-left: 4px solid #e8a020; }
    .doc-header .right { display: table-cell; vertical-align: middle; text-align: right; padding: 10px 14px; color: #cbd5e1; font-size: 8.5px; }
    .doc-header h1 { margin: 0; color: #fff; font-size: 15px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; }
    .doc-header .subtitle { margin-top: 3px; font-size: 9.5px; color: #e8a020; letter-spacing: 0.3px; }
    .doc-header .right .lbl { color: #94a3b8; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; }
    .doc-header .right .val { color: #fff; font-size: 9.5px; font-weight: 600; }

    /* Meta cellulée */
    .meta {
        margin: 8px 0 12px; padding: 8px 12px;
        background: #fefaf1; border: 1px solid #f3d999;
        border-left: 3px solid #e8a020; border-radius: 3px;
        font-size: 9px; color: #78350f;
    }
    .meta strong { color: #92400e; }

    /* Synthèse buckets (3 tuiles) */
    .synth { display: table; width: 100%; margin: 6px 0 14px; border-collapse: separate; border-spacing: 6px 0; }
    .synth .cell { display: table-cell; padding: 8px 10px; border-radius: 4px; text-align: center; vertical-align: middle; }
    .synth .value { font-size: 20px; font-weight: bold; color: #fff; line-height: 1; }
    .synth .label { font-size: 8px; color: rgba(255,255,255,.9); margin-top: 3px; text-transform: uppercase; letter-spacing: 0.3px; }

    /* Sections H2 par bucket */
    h2 { font-size: 12px; margin: 14px 0 6px; padding: 5px 8px; color: #fff; border-radius: 3px; letter-spacing: 0.3px; }

    /* Table */
    table { width: 100%; border-collapse: collapse; page-break-inside: auto; }
    thead { display: table-header-group; }
    tr    { page-break-inside: avoid; }
    th { padding: 6px 7px; text-align: left; font-size: 7.5px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    th.r, td.r { text-align: right; }
    th.c, td.c { text-align: center; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:nth-child(even) td { background: #fafafa; }
    .num  { color: #6b7280; font-size: 8px; font-family: 'Courier New', monospace; text-align: right; }
    .ref  { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 8.5px; }
    .fmt  { color: #4b5563; font-size: 8px; }

    /* Badges */
    .badge-zone { display: inline-block; padding: 1px 6px; font-size: 7.5px; border-radius: 999px; }
    .badge-abj  { background: #dbeafe; color: #1d4ed8; }
    .badge-int  { background: #d1fae5; color: #047857; }
    .badge-maint {
        display: inline-block; padding: 1px 5px; font-size: 7px;
        border-radius: 3px; background: #fef3c7; color: #92400e;
        font-weight: bold; margin-left: 4px; letter-spacing: 0.3px;
    }
    tr.row-maintenance td { background: #fffbeb !important; }

    .empty { padding: 12px; text-align: center; color: #9ca3af; font-style: italic; background: #fafafa; border-radius: 3px; }

    /* Footer bien séparé, pagination via DomPDF page_script */
    .footer {
        position: fixed; bottom: 4mm; left: 10mm; right: 10mm;
        height: 12mm;
        font-size: 7.5px; color: #6b7280; background: #fff;
        border-top: 1px solid #d1d5db; padding-top: 4mm;
    }
    .footer .l { float: left; }
    .footer .c { text-align: center; }
    .footer .r { float: right; font-weight: bold; color: #0a0c10; }
</style>
</head>
<body>

<div class="doc-header">
    <div class="left">
        <h1>Classification des panneaux par occupation</h1>
        <div class="subtitle">Analyse par durée d'occupation cumulée · CIBLE CI</div>
    </div>
    <div class="right">
        <div><span class="lbl">Période</span> <span class="val">{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</span></div>
        <div><span class="lbl">Édité le</span> <span class="val">{{ now()->format('d/m/Y H:i') }}</span></div>
        <div><span class="lbl">Par</span> <span class="val">{{ $user?->name ?? '—' }}</span></div>
    </div>
</div>

<div class="meta">
    <strong>Périmètre :</strong> {{ $classification['total'] }} panneaux analysés
    · <strong>Exclus :</strong> {{ $classification['excluded_count'] }} (chevalets + murales)
    · <strong>Règle :</strong> à l'année = ≥ 365 j occupés, intermédiaire = 1-364 j, jamais = 0 j
</div>

<div class="synth">
    <div class="cell" style="background:#22c55e">
        <div class="value">{{ $classification['a_lannee']->count() }}</div>
        <div class="label">À l'année (≥ 365 j)</div>
    </div>
    <div class="cell" style="background:#f97316">
        <div class="value">{{ $classification['intermediaire']->count() }}</div>
        <div class="label">Intermédiaire (1-364 j)</div>
    </div>
    <div class="cell" style="background:#ef4444">
        <div class="value">{{ $classification['jamais']->count() }}</div>
        <div class="label">Jamais occupés (0 j)</div>
    </div>
</div>

@php
    $sections = [
        ['key' => 'a_lannee',      'label' => "🟢 Panneaux occupés à l'année",           'color' => '#16a34a'],
        ['key' => 'intermediaire', 'label' => '🟡 Panneaux occupation intermédiaire',    'color' => '#ea580c'],
        ['key' => 'jamais',        'label' => '🔴 Panneaux jamais occupés sur la période','color' => '#dc2626'],
    ];
@endphp

@foreach($sections as $section)
    @php
        $list = $classification[$section['key']];
        $nbMaintList = $list->filter(fn ($p) => $p->status === 'maintenance')->count();
    @endphp
    <h2 style="background:{{ $section['color'] }}">
        {{ $section['label'] }} ({{ $list->count() }}@if($nbMaintList > 0) · dont {{ $nbMaintList }} en maintenance@endif)
    </h2>

    @if($list->isEmpty())
        <div class="empty">Aucun panneau dans cette catégorie.</div>
    @else
        <table>
            <thead>
                <tr style="background:#0a0c10">
                    <th style="width:22px" class="c">N°</th>
                    <th style="width:70px">Référence</th>
                    <th>Emplacement</th>
                    <th style="width:95px">Commune</th>
                    <th style="width:65px" class="c">Zone</th>
                    <th style="width:60px" class="c">Format</th>
                    <th style="width:55px" class="c">Camp.</th>
                    <th style="width:70px" class="r">Jours occ.</th>
                    <th style="width:65px" class="r">Taux</th>
                </tr>
            </thead>
            <tbody>
                @foreach($list as $index => $p)
                    @php $isMaint = $p->status === 'maintenance'; @endphp
                    <tr @if($isMaint) class="row-maintenance" @endif>
                        <td class="num">{{ $index + 1 }}</td>
                        <td class="ref">
                            {{ $p->reference }}
                            @if($isMaint)<span class="badge-maint">🔧 MAINT.</span>@endif
                        </td>
                        <td>{{ \Illuminate\Support\Str::limit($p->name ?? '—', 42) }}</td>
                        <td>{{ $p->commune_name ?? '—' }}</td>
                        <td class="c">
                            @if($p->zone === 'Abidjan')
                                <span class="badge-zone badge-abj">Abidjan</span>
                            @else
                                <span class="badge-zone badge-int">Intérieur</span>
                            @endif
                        </td>
                        <td class="c fmt">{{ $p->format_name ?? '—' }}</td>
                        <td class="c">{{ (int) $p->campaigns_count }}</td>
                        <td class="r"><strong>{{ (int) $p->days_occupied }}</strong></td>
                        <td class="r">{{ number_format((float) $p->occupation_rate, 1, ',', ' ') }} %</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach

<div class="footer">
    <div class="l">CIBLE SARL · Régie OOH Côte d'Ivoire</div>
    <div class="r">Page <span class="pagenum-fake">1</span></div>
    <div class="c">Document généré automatiquement par Panora</div>
</div>

<script type="text/php">
if (isset($pdf)) {
    $pdf->page_script('
        $font = $fontMetrics->get_font("DejaVu Sans", "bold");
        $size = 8;
        $text = "Page " . $PAGE_NUM . " / " . $PAGE_COUNT;
        $width  = $fontMetrics->get_text_width($text, $font, $size);
        $pageWidth  = $pdf->get_width();
        $pageHeight = $pdf->get_height();
        $x = $pageWidth - $width - 30;
        $y = $pageHeight - 26;
        $pdf->filled_rectangle($x - 4, $y - 2, $width + 8, $size + 4, [1, 1, 1]);
        $pdf->text($x, $y, $text, $font, $size, [0.04, 0.05, 0.06]);
    ');
}
</script>

</body>
</html>

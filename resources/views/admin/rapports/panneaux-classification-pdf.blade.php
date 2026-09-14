<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classification panneaux — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 18mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.3; margin: 0; padding: 0; }

    /* Header table native */
    table.doc-header { width: 100%; border-collapse: collapse; background: #0a0c10; margin-bottom: 8px; }
    table.doc-header td { padding: 8px 12px; vertical-align: middle; color: #cbd5e1; font-size: 8.5px; }
    table.doc-header td.left { border-left: 4px solid #e8a020; width: 60%; }
    table.doc-header td.right { text-align: right; }
    table.doc-header h1 { margin: 0; color: #fff; font-size: 14px; font-weight: bold; letter-spacing: 0.4px; text-transform: uppercase; }
    table.doc-header .subtitle { margin-top: 2px; font-size: 9px; color: #e8a020; }
    table.doc-header .lbl { color: #94a3b8; font-size: 7.5px; text-transform: uppercase; letter-spacing: 0.3px; }
    table.doc-header .val { color: #fff; font-size: 9px; font-weight: 600; }

    .meta {
        margin: 6px 0 8px; padding: 6px 10px;
        background: #fefaf1; border: 1px solid #f3d999;
        border-left: 3px solid #e8a020; border-radius: 3px;
        font-size: 9px; color: #78350f;
    }
    .meta strong { color: #92400e; }

    /* Synthèse buckets — table native */
    table.synth { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 4px 0 10px; }
    table.synth td { padding: 6px 8px; border-radius: 3px; text-align: center; vertical-align: middle; height: 40px; }
    table.synth .value { font-size: 18px; font-weight: bold; color: #fff; line-height: 1; }
    table.synth .label { font-size: 7.5px; color: #fff; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.3px; }

    h2.section { font-size: 11px; margin: 10px 0 4px; padding: 4px 8px; color: #fff; border-radius: 3px; letter-spacing: 0.3px; }

    table.data { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.data th { background: #0a0c10; color: #fff; padding: 5px 6px; text-align: left; font-size: 7.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.3px; }
    table.data th.r, table.data td.r { text-align: right; }
    table.data th.c, table.data td.c { text-align: center; }
    table.data td { padding: 3px 6px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    table.data tr.even td { background: #fafafa; }
    table.data tr.maint td { background: #fffbeb; }
    .num  { color: #6b7280; font-size: 8px; font-family: 'Courier New', monospace; }
    .ref  { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 8.5px; }
    .fmt  { color: #4b5563; font-size: 8px; }
    .zone-abj { color: #1d4ed8; font-weight: bold; font-size: 8px; }
    .zone-int { color: #047857; font-weight: bold; font-size: 8px; }
    .maint-tag { color: #92400e; font-weight: bold; font-size: 7.5px; }

    .empty { padding: 10px; text-align: center; color: #9ca3af; font-style: italic; background: #fafafa; border-radius: 3px; }

    .footer { position: fixed; bottom: 4mm; left: 10mm; right: 10mm; height: 10mm; font-size: 7.5px; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 3mm; }
    .footer .l { float: left; }
    .footer .r { float: right; }
    .footer .c { text-align: center; }
</style>
</head>
<body>

<table class="doc-header">
    <tr>
        <td class="left">
            <h1>Classification des panneaux par occupation</h1>
            <div class="subtitle">Analyse par durée d'occupation cumulée · CIBLE CI</div>
        </td>
        <td class="right">
            <div><span class="lbl">Période</span> <span class="val">{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</span></div>
            <div><span class="lbl">Édité le</span> <span class="val">{{ now()->format('d/m/Y H:i') }}</span></div>
            <div><span class="lbl">Par</span> <span class="val">{{ $user?->name ?? '—' }}</span></div>
        </td>
    </tr>
</table>

<div class="meta">
    <strong>Périmètre :</strong> {{ $classification['total'] }} panneaux analysés
    · <strong>Exclus :</strong> {{ $classification['excluded_count'] }} (chevalets + murales)
    · <strong>Règle :</strong> à l'année = ≥ 365 j occupés, intermédiaire = 1-364 j, jamais = 0 j
</div>

<table class="synth">
    <tr>
        <td style="background:#22c55e">
            <div class="value">{{ $classification['a_lannee']->count() }}</div>
            <div class="label">À l'année (≥ 365 j)</div>
        </td>
        <td style="background:#f97316">
            <div class="value">{{ $classification['intermediaire']->count() }}</div>
            <div class="label">Intermédiaire (1-364 j)</div>
        </td>
        <td style="background:#ef4444">
            <div class="value">{{ $classification['jamais']->count() }}</div>
            <div class="label">Jamais occupés (0 j)</div>
        </td>
    </tr>
</table>

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
        $sectionLabel = $section['label'] . ' (' . $list->count()
            . ($nbMaintList > 0 ? ' · dont ' . $nbMaintList . ' en maintenance' : '')
            . ')';
    @endphp
    <h2 class="section" style="background:{{ $section['color'] }}">{{ $sectionLabel }}</h2>

    @if($list->isEmpty())
        <div class="empty">Aucun panneau dans cette catégorie.</div>
    @else
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
                    <th style="width:55px" class="r">Taux</th>
                </tr>
            </thead>
            <tbody>
                @foreach($list as $index => $p)
                    @php
                        $isMaint = $p->status === 'maintenance';
                        $isAbj   = ($p->zone ?? '') === 'Abidjan';
                        $rowClass = $isMaint ? 'maint' : (($index % 2) ? 'even' : '');
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
    <div class="c">Document généré automatiquement par Panora</div>
    <div class="r">&nbsp;</div>
</div>

</body>
</html>

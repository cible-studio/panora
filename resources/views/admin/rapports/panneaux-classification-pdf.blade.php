<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Classification panneaux — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : styles communs, en-tête clair commun
     (charte-header) à la place du bandeau foncé flottant, synthèse en table
     (les float débordaient), pied commun charte-footer. --}}
@include('pdf.partials.charte-styles')
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 20mm 10mm !important; }
    body { font-size: 9px; color: {{ $charte['noir'] }}; line-height: 1.15; margin: 0; padding: 0; }

    .meta { margin: 0 0 6px; padding: 6px 10px; background: {{ $charte['gris_clair'] }}; border-left: 3px solid {{ $charte['rouge'] }}; font-size: 9px; color: {{ $charte['noir'] }}; }
    .meta strong { color: {{ $charte['noir'] }}; }

    /* Synth buckets — 1 ligne colorée compacte (table, pas de float). */
    table.synth-line { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin: 4px 0 8px; table-layout: fixed; }
    .synth-line td.b { padding: 6px 8px; text-align: center; color: {{ $charte['blanc'] }}; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px; font-weight: 700; }
    .synth-line td.b strong { display: block; font-family: {!! $charte['ff_titres'] !!}; font-size: 18px; margin-bottom: 2px; font-weight: 700; }
    .synth-line .b1 { background: {{ $charte['vert'] }}; }
    .synth-line .b2 { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .synth-line .b3 { background: {{ $charte['rouge'] }}; }

    h2.section {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 11px; font-weight: 700; margin: 8px 0 3px; padding: 4px 8px;
        color: {{ $charte['noir'] }}; background: {{ $charte['gris_clair'] }};
        border-left: 4px solid {{ $charte['rouge'] }}; letter-spacing: 0.3px;
    }

    table.data { width: 100%; border-collapse: collapse; margin-top: 2px; }
    table.data th { background: {{ $charte['noir'] }}; color: {{ $charte['blanc'] }}; font-family: {!! $charte['ff_titres'] !!}; padding: 5px 6px; text-align: left; font-size: 7.5px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
    table.data th.r, table.data td.r { text-align: right; }
    table.data th.c, table.data td.c { text-align: center; }
    table.data td { padding: 3px 6px; font-size: 8.5px; border-bottom: 1px solid {{ $charte['gris'] }}; vertical-align: middle; }
    table.data tr.even td { background: {{ $charte['gris_clair'] }}; }
    table.data tr.maint td { background: {{ $charte['jaune_clair'] }}; }
    .num  { color: {{ $charte['texte_doux'] }}; font-size: 8px; font-family: {!! $charte['ff_mono'] !!}; }
    .ref  { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; font-size: 8.5px; }
    .fmt  { color: {{ $charte['texte_doux'] }}; font-size: 8px; }
    .zone-abj { color: {{ $charte['bleu'] }}; font-weight: 700; font-size: 8px; }
    .zone-int { color: {{ $charte['vert'] }}; font-weight: 700; font-size: 8px; }
    .maint-tag { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; font-weight: 700; font-size: 7.5px; padding: 0 3px; border-radius: 2px; }

    .empty { padding: 10px; text-align: center; color: {{ $charte['texte_pale'] }}; font-style: italic; background: {{ $charte['gris_clair'] }}; }
</style>
</head>
<body>

@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE SARL · Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora",
])

@include('pdf.partials.charte-header', [
    'docKicker'   => 'Rapport',
    'docTitle'    => 'CLASSIFICATION DES PANNEAUX PAR OCCUPATION',
    'docSubtitle' => "Analyse par durée d'occupation cumulée · CIBLE CI",
    'docMeta'     => [
        'Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user?->name ?? '—'),
    ],
])

<div class="meta">
    <strong>Périmètre :</strong> {{ $classification['total'] }} panneaux analysés
    · <strong>Exclus :</strong> {{ $classification['excluded_count'] }} (chevalets + murales)
    · <strong>Règle :</strong> à l'année = ≥ 365 j, intermédiaire = 1-364 j, jamais = 0 j
</div>

<table class="synth-line">
    <tr>
        <td class="b b1"><strong>{{ $classification['a_lannee']->count() }}</strong>À l'année (≥ 365 j)</td>
        <td class="b b2"><strong>{{ $classification['intermediaire']->count() }}</strong>Intermédiaire (1-364 j)</td>
        <td class="b b3"><strong>{{ $classification['jamais']->count() }}</strong>Jamais occupés (0 j)</td>
    </tr>
</table>

@php
    // 2026-10-01 — charte graphique : couleurs de section issues de $charte
    // (filet gauche du titre) ; pastilles emoji retirées (carrés vides en PDF).
    $sections = [
        ['key' => 'a_lannee',      'label' => "Panneaux occupés à l'année",            'color' => $charte['vert']],
        ['key' => 'intermediaire', 'label' => 'Panneaux occupation intermédiaire',     'color' => $charte['jaune']],
        ['key' => 'jamais',        'label' => 'Panneaux jamais occupés sur la période', 'color' => $charte['rouge']],
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
    <h2 class="section" style="border-left-color:{{ $section['color'] }}">{{ $sectionLabel }}</h2>

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
                            {{-- Marqueur maintenance : ⚠ (l'emoji 🔧 rendait un carré vide). --}}
                            <span class="ref">{{ $p->reference }}</span>@if($isMaint) <span class="maint-tag">⚠</span>@endif
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

</body>
</html>

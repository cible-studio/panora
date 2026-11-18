<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des taxes ODP et TM — Exercice {{ $exercice }}</title>
    {{-- Suivi des mairies (2026-11-16) — la fiche de la direction, une
         section par zone (Abidjan et banlieue, Intérieur), mêmes colonnes.
         Chiffres : TaxeExerciceService. --}}
    <style>@page { size: A4 landscape; margin: 12mm 12mm 20mm 12mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    {{-- 2026-11-18 (présentation à la DG) — propre à ce document : les deux
         tableaux (Abidjan, Intérieur) ont les mêmes largeurs de colonnes,
         le TOTAL reste à la taille du tableau, les zones sont séparées et
         la note ne se coupe pas. Les autres PDF de taxes gardent le style
         partagé. --}}
    <style>
        .tdoc-suivi { font-size:8.5px; margin-bottom:22px; }
        .tdoc-suivi thead th { padding-left:5px; padding-right:5px; }
        .tdoc-suivi tbody td { padding:5px; }
        .tdoc-suivi tr { page-break-inside:avoid; }
        .tdoc-suivi tr.tdoc-total { page-break-before:avoid; } /* jamais seul en haut de page */
        .tdoc-suivi .mono { white-space:nowrap; }
        .tdoc-suivi .tdoc-total td { font-size:9px; padding-top:8px; padding-bottom:8px; }
        .tdoc-note.tdoc-suivi-fin { page-break-inside:avoid; margin-top:4px; padding:5px 0 5px 3mm; font-size:8.5px; line-height:1.45; }
    </style>
</head>
<body>
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $libelles = ['abidjan' => 'ABIDJAN ET BANLIEUE', 'interieur' => 'INTÉRIEUR'];
@endphp

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Gestion des taxes (ODP et TM) de la régie',
    'docPeriod' => 'Exercice ' . $exercice . ($reprise ? ' — année de reprise (montants réclamés par les mairies)' : ''),
])

@include('pdf.partials.tax-doc-meta', ['metaCols' => [
    ['label' => 'Exercice',  'value' => (string) $exercice, 'accent' => true],
    ['label' => 'Total dû',  'value' => $fmt($totaux['tout']['total_du']) . ' FCFA'],
    ['label' => 'Payé',      'value' => $fmt($totaux['tout']['total_paye']) . ' FCFA'],
    ['label' => 'Solde',     'value' => $fmt($totaux['tout']['solde']) . ' FCFA'],
    ['label' => 'Arrêté au', 'value' => now()->format('d/m/Y')],
]])

@foreach(['abidjan', 'interieur'] as $z)
    @php $t = $totaux[$z]; @endphp
    <table class="tdoc-table tdoc-suivi">
        <thead>
            <tr>
                {{-- Largeurs fixées : les deux zones s'alignent colonne par colonne. --}}
                <th style="width:15%">Mairie</th><th class="right" style="width:4.5%">Pan.</th><th class="right" style="width:4.5%">Faces</th>
                <th class="right" style="width:8.5%">Arriérés</th><th class="right" style="width:8%">ODP {{ $exercice }}</th>
                <th class="right" style="width:6.5%">TM T1</th><th class="right" style="width:6.5%">TM T2</th><th class="right" style="width:6.5%">TM T3</th><th class="right" style="width:6.5%">TM T4</th>
                <th class="right" style="width:7%">Total TM</th><th class="right" style="width:9.5%">Total général</th>
                <th class="right" style="width:8%">Payé</th><th class="right" style="width:9%">Solde</th>
            </tr>
        </thead>
        <tbody>
            <tr class="tdoc-group"><td colspan="13">{{ $libelles[$z] }}</td></tr>
            @forelse($zones[$z] as $l)
                <tr>
                    <td>{{ $l['commune']->name }}</td>
                    <td class="right mono">{{ $l['nb_panneaux'] ?: '' }}</td>
                    <td class="right mono">{{ $l['nb_faces'] ?: '' }}</td>
                    <td class="right mono">{{ $l['arrieres'] ? $fmt($l['arrieres']) : '' }}</td>
                    <td class="right mono">{{ $l['odp'] ? $fmt($l['odp']) : '' }}</td>
                    @foreach([1, 2, 3, 4] as $q)
                        <td class="right mono">{{ $l['tm_trim'][$q] ? $fmt($l['tm_trim'][$q]) : '' }}</td>
                    @endforeach
                    <td class="right mono">{{ $l['tm'] ? $fmt($l['tm']) : '' }}</td>
                    <td class="right mono"><b>{{ $fmt($l['total_du']) }}</b></td>
                    <td class="right mono">{{ $l['total_paye'] ? $fmt($l['total_paye']) : '' }}</td>
                    <td class="right mono"><b>{{ $fmt($l['solde']) }}</b></td>
                </tr>
            @empty
                <tr><td colspan="13" class="tdoc-muted" style="text-align:center">Aucune mairie.</td></tr>
            @endforelse
            <tr class="tdoc-total">
                <td>TOTAL</td>
                <td class="right mono">{{ $t['panneaux'] }}</td>
                <td class="right mono">{{ $t['faces'] }}</td>
                <td class="right mono">{{ $fmt($t['arrieres']) }}</td>
                <td class="right mono">{{ $fmt($t['odp']) }}</td>
                @foreach([1, 2, 3, 4] as $q)
                    <td class="right mono">{{ $fmt($t['tm_trim'][$q]) }}</td>
                @endforeach
                <td class="right mono">{{ $fmt($t['tm']) }}</td>
                <td class="right mono">{{ $fmt($t['total_du']) }}</td>
                <td class="right mono">{{ $fmt($t['total_paye']) }}</td>
                <td class="right mono">{{ $fmt($t['solde']) }}</td>
            </tr>
        </tbody>
    </table>
@endforeach

<div class="tdoc-note tdoc-suivi-fin">
    @if($reprise)
        ■ Année de reprise : le dû de chaque mairie est ce qu'elle a réclamé, arriérés compris. Le solde au
        31/12/{{ $exercice }} devient l'arriéré de {{ $exercice + 1 }}.
    @else
        ■ Arriérés = solde {{ $exercice - 1 }} · ODP = tarif × m² × mois d'occupation · TM = tarif × m² × mois de
        campagne, rangés dans le trimestre où ils commencent · tarifs en vigueur chaque mois.
    @endif
</div>

@include('pdf.partials.tax-doc-footer', ['footerHint' => 'Suivi des mairies · exercice ' . $exercice])
</body>
</html>

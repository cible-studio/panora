<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé des taxes — {{ $commune->name }} — {{ $exercice }}</title>
    {{-- Suivi des mairies (2026-11-16) — le relevé à remettre à la mairie
         quand elle se présente : sa base, ce qui était dû, chaque règlement
         avec sa date et sa référence, le solde. Chiffres : TaxeExerciceService. --}}
    <style>@page { size: A4 portrait; margin: 12mm 14mm 20mm 14mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    {{-- 2026-11-18 (présentation à la DG) — un relevé aux nombreux
         règlements passe sur deux pages : une ligne de total n'y arrive
         jamais seule en haut de page, et aucune ligne n'est coupée. --}}
    <style>
        .tdoc-table tr { page-break-inside:avoid; }
        .tdoc-table tr.tdoc-total { page-break-before:avoid; }
    </style>
</head>
<body>
@php
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
    $valides = $reglements->reject(fn ($r) => $r->estAnnule());
@endphp

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Relevé des taxes ODP et TM — ' . $commune->name,
    'docPeriod' => 'Exercice ' . $exercice . ($reprise ? ' (année de reprise)' : ''),
])

@include('pdf.partials.tax-doc-meta', ['metaCols' => [
    ['label' => 'Mairie',    'value' => $commune->name],
    ['label' => 'Zone',      'value' => $ligne['is_abidjan'] ? 'Abidjan et banlieue' : 'Intérieur'],
    ['label' => 'Exercice',  'value' => (string) $exercice, 'accent' => true],
    ['label' => 'Panneaux',  'value' => $ligne['nb_panneaux'] . ' panneau' . ($ligne['nb_panneaux'] > 1 ? 'x' : '') . ' · ' . $ligne['nb_faces'] . ' face' . ($ligne['nb_faces'] > 1 ? 's' : ''), 'small' => true],
    ['label' => 'Arrêté au', 'value' => now()->format('d/m/Y')],
]])

@if(!empty($ligne['base']))
<table class="tdoc-table" style="margin-bottom:10px">
    <thead><tr><th>Dimension d'une face</th><th class="right">Panneaux</th><th class="right">Faces</th></tr></thead>
    <tbody>
    @foreach($ligne['base'] as $dim => $b)
        <tr><td>{{ $dim }} m²</td><td class="right mono">{{ $b['panneaux'] }}</td><td class="right mono">{{ $b['faces'] }}</td></tr>
    @endforeach
    </tbody>
</table>
@endif

<table class="tdoc-table" style="margin-bottom:10px">
    <thead><tr><th>Montants dus</th><th class="right">ODP</th><th class="right">TM</th><th class="right">Total</th></tr></thead>
    <tbody>
        <tr><td>Arriérés {{ $reprise ? 'des exercices antérieurs' : '(solde ' . ($exercice - 1) . ')' }}</td><td></td><td></td><td class="right mono">{{ $fmt($ligne['arrieres']) }}</td></tr>
        @foreach([1, 2, 3, 4] as $q)
            <tr>
                <td>{{ $q === 1 ? '1er' : $q . 'e' }} trimestre {{ $exercice }}</td>
                <td class="right mono">{{ $fmt($ligne['odp_trim'][$q]) }}</td>
                <td class="right mono">{{ $fmt($ligne['tm_trim'][$q]) }}</td>
                <td class="right mono">{{ $fmt($ligne['odp_trim'][$q] + $ligne['tm_trim'][$q]) }}</td>
            </tr>
        @endforeach
        <tr class="tdoc-total">
            <td>TOTAL DÛ</td>
            <td class="right mono">{{ $fmt($ligne['odp']) }}</td>
            <td class="right mono">{{ $fmt($ligne['tm']) }}</td>
            <td class="tdoc-amount mono">{{ $fmt($ligne['total_du']) }}<span>FCFA</span></td>
        </tr>
    </tbody>
</table>

<table class="tdoc-table" style="margin-bottom:10px">
    <thead><tr><th style="width:70px">Date</th><th>Référence</th><th>Mode</th><th>Objet</th><th class="right" style="width:90px">Montant</th></tr></thead>
    <tbody>
    @forelse($valides as $r)
        <tr>
            <td>{{ $r->paid_at?->format('d/m/Y') }}</td>
            <td>{{ $r->reference ?: '—' }}</td>
            <td>{{ $r->mode ? strip_tags(preg_replace('/^\S+\s/u', '', $r->mode_label)) : '—' }}</td>
            <td>{{ $r->objet_label }}</td>
            <td class="right mono">{{ $fmt($r->montant) }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="tdoc-muted" style="text-align:center">Aucun règlement enregistré pour cet exercice.</td></tr>
    @endforelse
        <tr class="tdoc-total">
            <td colspan="4" class="right">TOTAL RÉGLÉ</td>
            <td class="tdoc-amount mono">{{ $fmt($ligne['total_paye']) }}<span>FCFA</span></td>
        </tr>
        <tr class="tdoc-total">
            <td colspan="4" class="right">{{ $ligne['solde'] >= 0 ? 'SOLDE RESTANT DÛ' : 'PAYÉ EN TROP — DÉDUIT DE L\'ANNÉE SUIVANTE' }}</td>
            <td class="tdoc-amount mono">{{ $fmt(abs($ligne['solde'])) }}<span>FCFA</span></td>
        </tr>
    </tbody>
</table>

<div class="tdoc-note">
    @if($reprise)
        ■ Exercice {{ $exercice }} : montants établis d'après la réclamation de la mairie, arriérés compris.
    @else
        ■ ODP = tarif × surface (m²) × nombre de mois d'occupation du domaine public (un mât compte pour un panneau) ·
        TM = tarif × surface (m²) × nombre de mois de campagne. Tarifs de la mairie en vigueur chaque mois.
        Arriérés = solde de l'exercice {{ $exercice - 1 }}.
    @endif
    @if($ligne['exigible'] > 0 && $ligne['exigible'] !== $ligne['solde'])
        <br>■ Dû au {{ now()->format('d/m/Y') }} (trimestres déjà commencés) : <b>{{ $fmt($ligne['exigible']) }} FCFA</b>.
    @endif
</div>

@include('pdf.partials.tax-doc-footer', ['footerHint' => 'Relevé ' . $commune->name . ' · exercice ' . $exercice])
</body>
</html>

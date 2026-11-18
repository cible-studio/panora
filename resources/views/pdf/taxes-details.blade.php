<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Taxes — {{ $periodLabel }}</title>

    {{-- Style partagé avec pdf/taxes-report.blade.php — cf. le partial pour
         la charte. Marges identiques dans les deux vues (les offsets du
         footer fixe en dépendent) ; seule l'orientation diffère. --}}
    <style>@page { size: A4 landscape; margin: 12mm 14mm 20mm 14mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    {{-- 2026-11-18 (présentation à la DG) — propre à ce document :
         lignes plus serrées, total sur une ligne et jamais séparé du
         tableau, note gardée d'un bloc. Les autres PDF de taxes gardent
         le style partagé. --}}
    <style>
        .tdoc-details tbody td { padding-top:5px; padding-bottom:5px; }
        .tdoc-details tr { page-break-inside:avoid; }
        .tdoc-details tr.tdoc-total { page-break-before:avoid; } /* jamais seul en haut de page */
        .tdoc-details .tdoc-total td { padding-top:9px; padding-bottom:9px; }
        .tdoc-details .tdoc-total .tdoc-amount span { display:inline; font-size:11px; margin-left:4px; }
        .tdoc-note.tdoc-details-fin { page-break-inside:avoid; margin-top:10px; padding:5px 0 5px 3mm; font-size:8.5px; line-height:1.45; }
        .tdoc-details .tdoc-periode { white-space:nowrap; }
    </style>
</head>
<body>

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Détail des taxes communales',
    'docPeriod' => $periodLabel,
])

{{-- Métadonnées du document : opérateur, période, filtres actifs
     (commune / client / campagne / nature) puis volumétrie. --}}
@php
    $metaCols = array_merge(
        [
            ['label' => 'Opérateur', 'value' => $operatorName ?? 'CIBLE CI'],
            ['label' => 'Période',   'value' => $periodLabel, 'accent' => true],
        ],
        collect($filterMeta ?? [])
            ->map(fn ($r) => ['label' => $r['label'], 'value' => $r['value'], 'small' => mb_strlen($r['value']) > 22])
            ->all(),
        [[
            'label' => 'Panneaux',
            'small' => true,
            'value' => $totals['panels_count'] . ' panneau' . ($totals['panels_count'] > 1 ? 'x' : '')
                     . ' · ' . $totals['lines_count'] . ' ligne' . ($totals['lines_count'] > 1 ? 's' : '')
                     . ' de facturation',
        ]]
    );
@endphp
@include('pdf.partials.tax-doc-meta', ['metaCols' => $metaCols])
@php
    // 2026-11-18 (demande de l'utilisateur) : PDF de l'ODP seule → sans les
    // colonnes Client, Campagne et Période : l'ODP taxe le mât planté, pas
    // une campagne. Vaut pour le filtre « ODP seul » comme pour une
    // sélection qui ne contient que de l'ODP. TM et PDF mixte : inchangés.
    $odpSeule = $lines->isNotEmpty() && $lines->every(fn ($l) => $l['type'] === 'odp');
    $nbColonnes = $odpSeule ? 5 : 8;
@endphp

<table class="tdoc-table tdoc-details">
    <thead>
        <tr>
            <th style="width:90px;">Panneau</th>
            <th style="width:62px;">Dim.</th>
            <th style="width:44px;">Type</th>
            @unless($odpSeule)
            <th>Client</th>
            <th>Campagne</th>
            <th style="width:150px;">Période</th>
            @endunless
            <th style="width:130px;" class="right">Tarif / Formule</th>
            <th style="width:86px;" class="right">Montant</th>
        </tr>
    </thead>
    <tbody>
    @php
        $currentCommune = null;
    @endphp
    @foreach($lines as $row)
        {{-- Séparateur de commune : n'apparaît qu'en multi-communes (un
             export filtré sur une seule commune n'en affiche aucun, la
             commune étant déjà portée par le bloc méta ci-dessus). --}}
        @if($currentCommune !== $row['commune'])
            @php $currentCommune = $row['commune']; @endphp
            <tr class="tdoc-group"><td colspan="{{ $nbColonnes }}">{{ mb_strtoupper($row['commune']) }}</td></tr>
        @endif
        <tr>
            <td class="mono tdoc-ref">{{ $row['reference'] }}</td>
            <td>
                @php $m2 = rtrim(rtrim(number_format($row['surface'], 2), '0'), '.'); @endphp
                {{ $row['dimensions'] }}
                @if(str_replace([' ', '²', 'm'], '', (string) $row['dimensions']) !== $m2)
                    <br><span class="tdoc-muted" style="font-size:9px;">{{ $m2 }} m²</span>
                @endif
            </td>
            <td><span class="tdoc-badge tdoc-badge-{{ $row['type'] }}">{{ strtoupper($row['type']) }}</span></td>
            @unless($odpSeule)
            <td>{{ $row['client_name'] ?? '—' }}</td>
            <td>{{ $row['campaign_name'] ?? '—' }}</td>
            <td style="font-size:9px;" class="tdoc-muted tdoc-periode">
                {{-- FIX 2026-06-26 — Vraies dates de la campagne (pas le filtre).
                     Lignes ODP sans campagne : on retombe sur la période du filtre. --}}
                @if(!empty($row['campaign_start']) && !empty($row['campaign_end']))
                    {{ $row['campaign_start']->format('d/m/Y') }} → {{ $row['campaign_end']->format('d/m/Y') }}
                @else
                    {{ $row['period_start']->format('d/m/Y') }} → {{ $row['period_end']->format('d/m/Y') }}
                @endif
            </td>
            @endunless
            <td class="right mono tdoc-muted" style="font-size:9px;">
                {{-- TX-14 (2026-09-24) — Les deux taxes se comptent en mois :
                     la TM en mois de campagne (date à date), l'ODP en mois
                     calendaires d'occupation. L'unité reste lue depuis la
                     ligne plutôt que codée en dur, pour que le document suive
                     si la règle rebouge. --}}
                @php $uniteLabel = ($row['unit'] ?? 'mois') === 'trimestre'
                    ? ($row['months'] > 1 ? 'trimestres' : 'trimestre')
                    : 'mois'; @endphp
                {{ number_format($row['rate_applied'] ?? $row['rate'], 0, ',', ' ') }} × {{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }}m² × {{ $row['months'] }} {{ $uniteLabel }}
            </td>
            <td class="right mono"><strong>{{ number_format($row['amount'], 0, ',', ' ') }}</strong></td>
        </tr>
    @endforeach
        <tr class="tdoc-total">
            <td colspan="{{ $nbColonnes - 1 }}" class="right">TOTAL GÉNÉRAL</td>
            <td class="tdoc-amount mono">
                {{ number_format($totals['total'], 0, ',', ' ') }}<span>FCFA</span>
            </td>
        </tr>
    </tbody>
</table>

<div class="tdoc-note tdoc-details-fin">
    @if($odpSeule)
        ■ Chaque montant est justifiable : <b>ODP = tarif × surface (m²) × nombre de mois</b> d'occupation du domaine public,
        comptée une seule fois par panneau physique (un mât double-face compte pour un).
    @else
        ■ Chaque montant est justifiable : <b>TM = tarif × surface (m²) × nombre de mois</b> de campagne ·
        <b>ODP = tarif × surface (m²) × nombre de mois</b> d'occupation du domaine public
        (comptée une seule fois par panneau physique — un mât double-face compte pour un).
    @endif
    {{-- 2026-11-18 — Corrigé : depuis le 13/11 les panneaux en maintenance
         sont INCLUS par défaut (l'ODP court tant que le mât est planté) ;
         l'ancienne phrase disait l'inverse. --}}
    Les panneaux en maintenance sont inclus, sauf mention contraire dans les filtres ci-dessus.<br>
    Les tarifs appliqués reflètent l'<b>historique tarifaire</b> de chaque commune à la date de la période —
    document fiable pour les contrôles administratifs.
</div>

@include('pdf.partials.tax-doc-footer', [
    'footerHint' => 'Tarif × Surface (m²) × Nombre de mois',
])

</body>
</html>

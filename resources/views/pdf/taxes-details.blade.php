<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Taxes — {{ $periodLabel }}</title>

    {{-- Style partagé avec pdf/taxes-report.blade.php — cf. le partial pour
         la charte. Marges identiques dans les deux vues (les offsets du
         footer fixe en dépendent) ; seule l'orientation diffère. --}}
    <style>@page { size: A4 landscape; margin: 14mm 14mm 18mm 14mm; }</style>
    @include('pdf.partials.tax-doc-styles')
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

<table class="tdoc-table">
    <thead>
        <tr>
            <th style="width:90px;">Panneau</th>
            <th style="width:62px;">Dim.</th>
            <th style="width:44px;">Type</th>
            <th>Client</th>
            <th>Campagne</th>
            <th style="width:120px;">Période</th>
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
            <tr class="tdoc-group"><td colspan="8">{{ mb_strtoupper($row['commune']) }}</td></tr>
        @endif
        <tr>
            <td class="mono tdoc-ref">{{ $row['reference'] }}</td>
            <td>
                {{ $row['dimensions'] }}
                <br><span class="tdoc-muted" style="font-size:7.5px;">{{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }} m²</span>
            </td>
            <td><span class="tdoc-badge tdoc-badge-{{ $row['type'] }}">{{ strtoupper($row['type']) }}</span></td>
            <td>{{ $row['client_name'] ?? '—' }}</td>
            <td>{{ $row['campaign_name'] ?? '—' }}</td>
            <td style="font-size:8px;" class="tdoc-muted">
                {{-- FIX 2026-06-26 — Vraies dates de la campagne (pas le filtre).
                     Lignes ODP sans campagne : on retombe sur la période du filtre. --}}
                @if(!empty($row['campaign_start']) && !empty($row['campaign_end']))
                    {{ $row['campaign_start']->format('d/m/Y') }} →<br>{{ $row['campaign_end']->format('d/m/Y') }}
                @else
                    {{ $row['period_start']->format('d/m/Y') }} →<br>{{ $row['period_end']->format('d/m/Y') }}
                @endif
            </td>
            <td class="right mono tdoc-muted" style="font-size:7.5px;">
                {{-- TX-12 (2026-09-23) — L'unité dépend de la taxe : la TM se
                     compte en mois de campagne, l'ODP en trimestres. Le PDF
                     écrivait « mois » partout, donc une ODP de 4 trimestres
                     était présentée à la mairie comme « 4 mois ». --}}
                @php $uniteLabel = ($row['unit'] ?? 'mois') === 'trimestre'
                    ? ($row['months'] > 1 ? 'trimestres' : 'trimestre')
                    : 'mois'; @endphp
                {{ number_format($row['rate_applied'] ?? $row['rate'], 0, ',', ' ') }} × {{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }}m² × {{ $row['months'] }} {{ $uniteLabel }}
            </td>
            <td class="right mono"><strong>{{ number_format($row['amount'], 0, ',', ' ') }}</strong></td>
        </tr>
    @endforeach
        <tr class="tdoc-total">
            <td colspan="7" class="right">TOTAL GÉNÉRAL</td>
            <td class="tdoc-amount mono">
                {{ number_format($totals['total'], 0, ',', ' ') }}<span>FCFA</span>
            </td>
        </tr>
    </tbody>
</table>

<div class="tdoc-note">
    ■ Chaque montant est justifiable : <b>TM = tarif × surface (m²) × nombre de mois</b> de campagne ·
    <b>ODP = tarif × surface (m²) × nombre de trimestres</b> entamés (l'ODP est due chaque trimestre,
    et une seule fois par panneau physique — un mât double-face compte pour un).
    Les panneaux en maintenance sont exclus sauf mention contraire dans les filtres ci-dessus.<br>
    Les tarifs appliqués reflètent l'<b>historique tarifaire</b> de chaque commune à la date de la période —
    document fiable pour les contrôles administratifs.
</div>

@include('pdf.partials.tax-doc-footer', [
    'footerHint' => 'Tarif × Surface (m²) × Nombre de mois',
])

</body>
</html>

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
        /* 2026-11-24 — période en gras, bien visible (trimestres pour la TM). */
        .tdoc-periode-bande { width:100%; border-collapse:collapse; margin:2px 0 12px; }
        .tdoc-periode-bande td { padding:7px 14px 8px 0; vertical-align:bottom; }
        .tdoc-periode-bande .lbl { display:block; font-size:7.5px; letter-spacing:.8px; text-transform:uppercase; color:#535353; margin-bottom:2px; }
        .tdoc-periode-bande .val { font-size:14px; font-weight:700; color:#111111; white-space:nowrap; }
    </style>
</head>
<body>

@include('pdf.partials.tax-doc-header', [
    'docTitle'  => 'Détail des taxes communales',
    'docPeriod' => $periodLabel,
])

@php
    // 2026-11-18 (demande de l'utilisateur) : PDF de l'ODP seule → sans les
    // colonnes Client, Campagne et Période : l'ODP taxe le mât planté, pas
    // une campagne. Vaut pour le filtre « ODP seul » comme pour une
    // sélection qui ne contient que de l'ODP. TM et PDF mixte : inchangés.
    $odpSeule = $lines->isNotEmpty() && $lines->every(fn ($l) => $l['type'] === 'odp');
    $nbColonnes = $odpSeule ? 5 : 8;

    // 2026-11-24 (demande de l'utilisateur) : période en gras, bien visible.
    // TM (et PDF mixte) : trimestres + dates de début et de fin ; ODP seule :
    // dates seulement. Bornes = celles du calcul (TaxController via
    // TaxCalculationService::resolvePeriod), sinon celles des lignes.
    $periodeDebut = $periodeDebut ?? $lines->min('period_start');
    $periodeFin   = $periodeFin ?? $lines->max('period_end');
    $trimestres   = ($periodeDebut && $periodeFin)
        ? \App\Services\TaxCalculationService::libelleTrimestres(\Carbon\Carbon::parse($periodeDebut), \Carbon\Carbon::parse($periodeFin))
        : null;

    // 2026-11-24 (demande de l'utilisateur) : la ligne Opérateur · Commune ·
    // Sélection · Panneaux est retirée ; la COMMUNE passe en tête du bandeau.
    // Une commune : son nom. Plusieurs : leurs noms (jusqu'à 3), sinon leur
    // nombre ; le tableau les sépare de toute façon par commune.
    $communes = $lines->pluck('commune')->filter()->unique()->values();
    $libelleCommunes = match (true) {
        $communes->count() === 1 => $communes->first(),
        $communes->count() <= 3  => $communes->implode(', '),
        default                  => $communes->count() . ' communes',
    };
    $maintenanceExclue = collect($filterMeta ?? [])->contains(fn ($r) => ($r['label'] ?? null) === 'Maintenance');
@endphp

@if($periodeDebut && $periodeFin)
<table class="tdoc-periode-bande">
    <tr>
        @if($communes->isNotEmpty())
            <td>
                <span class="lbl">{{ $communes->count() > 1 ? 'Communes' : 'Commune' }}</span>
                <span class="val">{{ mb_strtoupper($libelleCommunes) }}</span>
            </td>
        @endif
        @unless($odpSeule)
            <td>
                <span class="lbl">{{ (str_contains($trimestres, ' et ') || str_contains($trimestres, ' à ')) ? 'Trimestres' : 'Trimestre' }}</span>
                <span class="val">{{ $trimestres }}</span>
            </td>
        @endunless
        <td>
            <span class="lbl">Période</span>
            <span class="val">du {{ \Carbon\Carbon::parse($periodeDebut)->format('d/m/Y') }} au {{ \Carbon\Carbon::parse($periodeFin)->format('d/m/Y') }}</span>
        </td>
    </tr>
</table>
@endif

{{-- 2026-11-24 (demande de l'utilisateur) : plus de ligne Opérateur ·
     Commune · Sélection · Panneaux sous le bandeau. La commune est dans le
     bandeau, l'opérateur dans l'en-tête, le volume dans le total. --}}

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
    {{ $maintenanceExclue ? 'Les panneaux en maintenance ne sont pas comptés.' : 'Les panneaux en maintenance sont inclus.' }}<br>
    Les tarifs appliqués reflètent l'<b>historique tarifaire</b> de chaque commune à la date de la période —
    document fiable pour les contrôles administratifs.
</div>

@include('pdf.partials.tax-doc-footer', [
    'footerHint' => 'Tarif × Surface (m²) × Nombre de mois',
])

</body>
</html>

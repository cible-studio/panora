{{--
    Relevé des taxes communales — PDF du « Détail par panneau »
    (TaxController::detailsPdf).

    2026-11-24 (soir, demande de l'utilisateur) : retour à l'ANCIEN DESIGN
    (A4 paysage, en-tête CIBLE des documents internes, tableau à filets fins,
    pied de page de la charte), avec TOUTES les informations du relevé du
    jour, et en grand et en gras ce qui l'était : titre selon la taxe,
    commune, période, trimestres en toutes lettres, opérateur, nombre de
    panneaux, références, total à payer, somme en lettres.
    Toujours sans « À l'attention de Monsieur le Maire… » ni signature, et
    sans rien sur les filtres (catégories exclues, sélection…).

    Un relevé par commune, saut de page entre deux communes.
      · ODP seule : « Relevé des taxes d'occupation du domaine public ».
      · TM seule  : « Relevé de la taxe municipale » (annonceur, campagne,
                    dates d'affichage ; trimestres en toutes lettres).
      · Les deux  : deux tableaux (ODP puis TM), sous-totaux, un total.

    Variables : $lines, $totals, $periodLabel, $periodeDebut, $periodeFin
    (bornes de TaxCalculationService::resolvePeriod, sinon celles des lignes).
--}}
@php
    $operateur = $operatorName ?? config('app.operator_name', 'CIBLE CI');

    $periodeDebut = $periodeDebut ?? $lines->min('period_start');
    $periodeFin   = $periodeFin ?? $lines->max('period_end');
    $debutC = $periodeDebut ? \Carbon\Carbon::parse($periodeDebut) : null;
    $finC   = $periodeFin ? \Carbon\Carbon::parse($periodeFin) : null;
    // « novembre 2026 », « janvier → mars 2026 », « année 2026 » ; « T4 2026 » tel quel.
    $libellePeriode = preg_match('/^T\d/', (string) $periodLabel) ? $periodLabel : mb_strtolower((string) $periodLabel);
    $dateDoc = now()->locale('fr')->translatedFormat('j F Y');

    $fmt   = fn ($n) => number_format((int) round((float) $n), 0, ',', ' ');
    $fmtM2 = fn ($s) => rtrim(rtrim(number_format((float) $s, 2, ',', ''), '0'), ',');
    // « Commune de Yopougon », « Commune d'Adjamé ».
    $nomCommune = fn ($c) => mb_convert_case(mb_strtolower((string) $c), MB_CASE_TITLE);
    $deCommune  = fn ($c) => (preg_match('/^[aeiouhâàéèêîïôûAEIOUHÂÀÉÈÊÎÏÔÛ]/u', $nomCommune($c)) ? "d'" : 'de ') . $nomCommune($c);

    $parCommune = $lines->groupBy('commune');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé des taxes — {{ $periodLabel }}</title>

    {{-- Style partagé avec pdf/taxes-report.blade.php — cf. le partial pour
         la charte. Marges identiques dans les deux vues (les offsets du
         footer fixe en dépendent) ; seule l'orientation diffère. --}}
    <style>@page { size: A4 landscape; margin: 12mm 14mm 20mm 14mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    @php $chR = $charte ?? \App\Support\PdfCharte::data(); @endphp
    <style>
        .rv-saut { page-break-before: always; }

        /* Bandeau : commune, période, trimestres, opérateur, panneaux — en
           grand et en gras (ancien bandeau de la période, 2026-11-24). */
        .rv-bande { width:100%; border-collapse:collapse; margin:2px 0 14px; }
        .rv-bande td { padding:7px 22px 8px 0; vertical-align:top; }
        .rv-bande .lbl { display:block; font-size:7.5px; letter-spacing:.8px; text-transform:uppercase; color:{{ $chR['texte_doux'] }}; margin-bottom:3px; }
        .rv-bande .val { font-size:15px; font-weight:800; color:{{ $chR['noir'] }}; white-space:nowrap; }
        .rv-bande .val-s { font-size:11px; font-weight:700; color:{{ $chR['texte_doux'] }}; white-space:nowrap; }
        .rv-bande .rv-trim { font-size:12.5px; font-weight:800; color:{{ $chR['noir'] }}; white-space:nowrap; line-height:1.45; }

        /* Tableau (ancien design, lignes serrées, références en gras). */
        /* Largeurs fixes (en %) : les colonnes ne se répartissent plus au
           hasard (campagne coupée, montant trop large). */
        .tdoc-details { table-layout:fixed; }
        .tdoc-details tbody td { padding-top:6px; padding-bottom:6px; font-size:11.5px; }
        .tdoc-details tr { page-break-inside:avoid; }
        .tdoc-details .tdoc-ref { font-weight:800; white-space:nowrap; }
        .tdoc-details .c { text-align:center; }
        .tdoc-details .nowrap { white-space:nowrap; }
        .tdoc-details .tdoc-group td { font-size:10px; }
        .tdoc-details tr.st td { font-weight:800; }
        .tdoc-details tr.tdoc-total { page-break-before:avoid; } /* jamais seul en haut de page */
        .tdoc-details .tdoc-total td { padding-top:10px; padding-bottom:10px; font-size:15px; font-weight:800; }
        .tdoc-details .tdoc-total .tdoc-amount { font-size:18px; font-weight:800; }
        .tdoc-details .tdoc-total .tdoc-amount span { display:inline; font-size:12px; margin-left:4px; }

        .rv-arrete { font-size:12px; margin-top:14px; page-break-inside:avoid; color:{{ $chR['noir'] }}; }
        .rv-arrete b { font-weight:800; }
        .tdoc-note.tdoc-details-fin { page-break-inside:avoid; margin-top:10px; padding:5px 0 5px 3mm; font-size:9px; line-height:1.45; }
    </style>
</head>
<body>

@foreach($parCommune as $commune => $lignesCommune)
    @php
        $odp = $lignesCommune->where('type', 'odp')->values();
        $tm  = $lignesCommune->where('type', 'tm')->values();
        $total = (float) $lignesCommune->sum('amount');
        $nbPanneaux = $lignesCommune->pluck('panel_id')->filter()->unique()->count();
        [$titre, $sousTitre] = match (true) {
            $tm->isEmpty()  => ["Relevé des taxes d'occupation du domaine public", 'Panneaux publicitaires implantés sur le territoire de la Commune ' . $deCommune($commune)],
            $odp->isEmpty() => ['Relevé de la taxe municipale', 'Affichages publicitaires sur le territoire de la Commune ' . $deCommune($commune)],
            default         => ['Relevé des taxes communales', 'Occupation du domaine public et affichages publicitaires — Commune ' . $deCommune($commune)],
        };
        $mixte = $odp->isNotEmpty() && $tm->isNotEmpty();
    @endphp
    <div class="{{ $loop->first ? 'rv' : 'rv rv-saut' }}">

        @include('pdf.partials.doc-entete', [
            'docKicker'   => 'Taxes communales',
            'docTitle'    => $titre,
            'docSubtitle' => $sousTitre,
            'docMeta'     => ['Abidjan, le ' . $dateDoc],
        ])

        <table class="rv-bande">
            <tr>
                <td>
                    <span class="lbl">Commune</span>
                    <span class="val">{{ mb_strtoupper($commune) }}</span>
                </td>
                @if($debutC && $finC)
                    <td>
                        <span class="lbl">Période</span>
                        <span class="val">du {{ $debutC->format('d/m/Y') }} au {{ $finC->format('d/m/Y') }}</span>
                        @if($tm->isEmpty())<br><span class="val-s">({{ $libellePeriode }})</span>@endif
                    </td>
                    @if($tm->isNotEmpty())
                        {{-- TM (demande du 24/11) : chaque trimestre écrit en toutes
                             lettres, « Trimestre 1 : de janvier à mars 2026 ». --}}
                        @php $trimestres = \App\Services\TaxCalculationService::trimestresEnLettres($debutC, $finC); @endphp
                        <td>
                            <span class="lbl">{{ count($trimestres) > 1 ? 'Trimestres' : 'Trimestre' }}</span>
                            @foreach($trimestres as $trimestre)
                                <div class="rv-trim">{{ $trimestre }}</div>
                            @endforeach
                        </td>
                    @endif
                @endif
                <td>
                    <span class="lbl">Opérateur</span>
                    <span class="val">{{ $operateur }}</span>
                </td>
                <td>
                    <span class="lbl">Nombre de panneaux</span>
                    <span class="val">{{ $nbPanneaux }}</span>
                </td>
            </tr>
        </table>

        @if($odp->isNotEmpty())
            <table class="tdoc-table tdoc-details">
                <thead>
                    <tr>
                        <th class="c" style="width:5%;">N°</th>
                        <th style="width:33%;">Panneau</th>
                        <th class="c" style="width:14%;">Surface (m²)</th>
                        <th class="right" style="width:18%;">Tarif (FCFA/m²/mois)</th>
                        <th class="c" style="width:13%;">Durée (mois)</th>
                        <th class="right" style="width:17%;">Montant (FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    @if($mixte)<tr class="tdoc-group"><td colspan="6">Occupation du domaine public (ODP)</td></tr>@endif
                    @foreach($odp as $i => $row)
                        <tr>
                            <td class="c">{{ $i + 1 }}</td>
                            <td class="mono tdoc-ref">{{ $row['reference'] }}</td>
                            <td class="c">{{ $fmtM2($row['surface']) }}</td>
                            <td class="right mono">{{ $fmt($row['rate_applied'] ?? $row['rate']) }}</td>
                            <td class="c">{{ $row['months'] }}</td>
                            <td class="right mono"><strong>{{ $fmt($row['amount']) }}</strong></td>
                        </tr>
                    @endforeach
                    @if($mixte)
                        <tr class="st"><td colspan="5" class="right">Sous-total ODP</td><td class="right mono">{{ $fmt($odp->sum('amount')) }}</td></tr>
                    @else
                        <tr class="tdoc-total">
                            <td colspan="5" class="right">TOTAL À PAYER</td>
                            <td class="tdoc-amount mono">{{ $fmt($total) }}<span>FCFA</span></td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif

        @if($tm->isNotEmpty())
            <table class="tdoc-table tdoc-details" @if($mixte) style="margin-top:12px;" @endif>
                <thead>
                    <tr>
                        <th class="c" style="width:4%;">N°</th>
                        <th style="width:11%;">Panneau</th>
                        <th style="width:17%;">Annonceur</th>
                        <th style="width:18%;">Campagne</th>
                        <th style="width:16%;">Affichage</th>
                        <th class="c" style="width:7%;">Surface<br>(m²)</th>
                        <th class="right" style="width:10%;">Tarif<br>(FCFA/m²/mois)</th>
                        <th class="c" style="width:6%;">Durée<br>(mois)</th>
                        <th class="right" style="width:11%;">Montant<br>(FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    @if($mixte)<tr class="tdoc-group"><td colspan="9">Taxe municipale (TM)</td></tr>@endif
                    @foreach($tm as $i => $row)
                        <tr>
                            <td class="c">{{ $i + 1 }}</td>
                            <td class="mono tdoc-ref">{{ $row['reference'] }}</td>
                            <td>{{ $row['client_name'] ?? '—' }}</td>
                            {{-- Campagne (demande du 24/11 : « c'est important »). --}}
                            <td>{{ $row['campaign_name'] ?? '—' }}</td>
                            <td class="nowrap">
                                @if(!empty($row['campaign_start']) && !empty($row['campaign_end']))
                                    {{ \Carbon\Carbon::parse($row['campaign_start'])->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($row['campaign_end'])->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="c">{{ $fmtM2($row['surface']) }}</td>
                            <td class="right mono">{{ $fmt($row['rate_applied'] ?? $row['rate']) }}</td>
                            <td class="c">{{ $row['months'] }}</td>
                            <td class="right mono"><strong>{{ $fmt($row['amount']) }}</strong></td>
                        </tr>
                    @endforeach
                    @if($mixte)
                        <tr class="st"><td colspan="8" class="right">Sous-total TM</td><td class="right mono">{{ $fmt($tm->sum('amount')) }}</td></tr>
                    @endif
                    {{-- Montant sur deux colonnes : « 3 096 000 FCFA » tient sur une ligne. --}}
                    <tr class="tdoc-total">
                        <td colspan="7" class="right">TOTAL À PAYER</td>
                        <td colspan="2" class="tdoc-amount mono">{{ $fmt($total) }}<span>FCFA</span></td>
                    </tr>
                </tbody>
            </table>
        @endif

        <div class="rv-arrete">
            Arrêté le présent relevé à la somme de
            <b>{{ \App\Support\MontantEnLettres::en((int) round($total)) }} ({{ $fmt($total) }}) francs CFA</b>.
        </div>

        <div class="tdoc-note tdoc-details-fin">
            @if($odp->isNotEmpty())
                ■ Base de calcul{{ $mixte ? " de l'ODP" : '' }} : <b>tarif communal × surface (m²) × nombre de mois d'occupation.</b> Un panneau double face est compté une seule fois.
            @endif
            @if($tm->isNotEmpty())
                @if($mixte)<br>@endif
                ■ Base de calcul{{ $mixte ? ' de la TM' : '' }} : <b>tarif communal × surface (m²) × nombre de mois d'affichage de chaque campagne.</b>
            @endif
        </div>
    </div>
@endforeach

@include('pdf.partials.tax-doc-footer', [
    'footerHint' => 'Tarif × Surface (m²) × Nombre de mois',
])

</body>
</html>

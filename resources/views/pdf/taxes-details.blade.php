{{--
    Relevé des taxes communales pour la mairie (2026-11-24, maquette de
    l'utilisateur) — PDF du « Détail par panneau » (TaxController::detailsPdf).

    Un relevé par commune (saut de page entre deux communes), adressé au
    maire : en-tête de la régie, destinataire, titre selon la taxe, période
    en gras, tableau numéroté, total à payer, somme en toutes lettres, base de
    calcul.
      · ODP seule : « Relevé des taxes d'occupation du domaine public ».
      · TM seule  : « Relevé de la taxe municipale », avec l'annonceur et les
                    dates d'affichage de chaque campagne, et les trimestres.
      · Les deux  : deux tableaux (ODP puis TM), sous-totaux, un total.
    Rien sur les filtres (catégories exclues, sélection…) : demande du 24/11.

    Variables : $lines, $totals, $periodLabel, $periodeDebut, $periodeFin
    (bornes de TaxCalculationService::resolvePeriod, sinon celles des lignes).
--}}
@php
    $ch        = \App\Support\PdfCharte::data();
    $coord     = (array) ($ch['coordonnees'] ?? []);
    $operateur = $operatorName ?? config('app.operator_name', 'CIBLE CI');
    $adresse   = array_values(array_filter(array_map('trim', explode('·', (string) ($coord['adresse'] ?? '')))));

    $periodeDebut = $periodeDebut ?? $lines->min('period_start');
    $periodeFin   = $periodeFin ?? $lines->max('period_end');
    $debutC = $periodeDebut ? \Carbon\Carbon::parse($periodeDebut) : null;
    $finC   = $periodeFin ? \Carbon\Carbon::parse($periodeFin) : null;
    $trimestres = ($debutC && $finC) ? \App\Services\TaxCalculationService::libelleTrimestres($debutC, $finC) : null;
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
    <style>@page { size: A4 portrait; margin: 12mm 14mm 20mm 14mm !important; }</style>
    @include('pdf.partials.tax-doc-styles')
    <style>
        .rv-saut { page-break-before: always; }
        .rv-tete { width:100%; border-collapse:collapse; margin-top:8px; }
        .rv-tete td { vertical-align:top; padding:0; }
        .rv-tete img { height:34px; display:block; }
        .rv-regie { text-align:right; font-size:9.5px; line-height:1.35; color:{{ $ch['texte_doux'] }}; }
        .rv-regie strong { font-size:11px; color:{{ $ch['noir'] }}; }
        .rv-filet { height:0.8pt; background:{{ $ch['noir'] }}; margin:10px 0 14px; }
        .rv-dest { width:100%; border-collapse:collapse; margin-bottom:16px; }
        .rv-dest td { vertical-align:top; padding:0; font-size:11px; line-height:1.35; }
        .rv-dest .petit { font-size:9px; color:{{ $ch['texte_pale'] }}; }
        .rv-dest .date { text-align:right; font-size:11px; }
        .rv-titre { font-family:{!! $ch['ff_titres'] !!}; font-size:15px; font-weight:700; letter-spacing:.3px; color:{{ $ch['noir'] }}; text-transform:uppercase; }
        .rv-soustitre { font-size:10px; color:{{ $ch['texte_doux'] }}; margin:2px 0 12px; }
        .rv-periode { border-top:1.2pt solid {{ $ch['noir'] }}; border-bottom:0.6pt solid {{ $ch['noir'] }}; padding:8px 0 7px; margin-bottom:9px; }
        .rv-periode .v { font-family:{!! $ch['ff_titres'] !!}; font-size:14px; font-weight:700; color:{{ $ch['noir'] }}; }
        .rv-periode .s { font-size:9.5px; color:{{ $ch['texte_doux'] }}; margin-left:6px; }
        .rv-infos { border-collapse:collapse; margin-bottom:8px; }
        .rv-infos td { font-size:9.5px; color:{{ $ch['texte_doux'] }}; padding:0 26px 0 0; }
        .rv-infos b { color:{{ $ch['noir'] }}; }
        .rv-section { font-family:{!! $ch['ff_titres'] !!}; font-size:10.5px; font-weight:700; margin:12px 0 2px; color:{{ $ch['noir'] }}; }
        .rv-table { width:100%; border-collapse:collapse; margin-bottom:4px; }
        .rv-table th { font-size:7.5px; letter-spacing:.5px; text-transform:uppercase; color:{{ $ch['texte_doux'] }}; text-align:left; padding:7px 6px; border-bottom:0.6pt solid {{ $ch['noir'] }}; font-weight:700; }
        .rv-table td { font-size:9.5px; padding:6px 6px; border-bottom:0.4pt solid {{ $ch['gris'] }}; vertical-align:top; }
        .rv-table th.r, .rv-table td.r { text-align:right; }
        .rv-table th.c, .rv-table td.c { text-align:center; }
        .rv-table .ref { font-family:{!! $ch['ff_titres'] !!}; font-weight:700; }
        .rv-table .n { color:{{ $ch['texte_pale'] }}; width:18px; }
        .rv-table tr { page-break-inside:avoid; }
        .rv-table tr.st td { font-weight:700; border-bottom:none; }
        .rv-total { width:100%; border-collapse:collapse; border-top:1.2pt solid {{ $ch['noir'] }}; border-bottom:1.2pt solid {{ $ch['noir'] }}; margin-top:6px; page-break-inside:avoid; }
        .rv-total td { padding:8px 6px; font-family:{!! $ch['ff_titres'] !!}; font-weight:700; font-size:12px; text-align:right; }
        .rv-arrete { font-size:10.5px; margin-top:12px; page-break-inside:avoid; }
        .rv-base { font-size:8.5px; color:{{ $ch['texte_doux'] }}; margin-top:8px; line-height:1.5; }
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
    @endphp
    <div class="{{ $loop->first ? 'rv' : 'rv rv-saut' }}">
        @include('pdf.partials.charte-lisere')

        <table class="rv-tete">
            <tr>
                <td>@if(!empty($logoCibleLight))<img src="{{ $logoCibleLight }}" alt="{{ $ch['nom'] }}">@endif</td>
                <td class="rv-regie">
                    <strong>{{ $operateur }}</strong><br>
                    {{ $coord['metier'] ?? '' }}
                    @foreach($adresse as $ligneAdresse)<br>{{ $ligneAdresse }}{{ $loop->last ? ' – Côte d\'Ivoire' : '' }}@endforeach
                </td>
            </tr>
        </table>
        <div class="rv-filet"></div>

        <table class="rv-dest">
            <tr>
                <td>
                    <span class="petit">À l'attention de</span><br>
                    <strong>Monsieur le Maire</strong><br>
                    <strong>de la Commune {{ $deCommune($commune) }}</strong>
                </td>
                <td class="date">Abidjan, le {{ $dateDoc }}</td>
            </tr>
        </table>

        <div class="rv-titre">{{ $titre }}</div>
        <div class="rv-soustitre">{{ $sousTitre }}</div>

        @if($debutC && $finC)
            <div class="rv-periode">
                <span class="v">Période : du {{ $debutC->format('d/m/Y') }} au {{ $finC->format('d/m/Y') }}</span>
                {{-- TM : les trimestres (demande du 24/11) ; ODP : le libellé de la période. --}}
                <span class="s">({{ $tm->isNotEmpty() ? $trimestres : $libellePeriode }})</span>
            </div>
        @endif

        <table class="rv-infos">
            <tr>
                <td>Opérateur : <b>{{ $operateur }}</b></td>
                <td>Commune : <b>{{ $nomCommune($commune) }}</b></td>
                <td>Nombre de panneaux : <b>{{ $nbPanneaux }}</b></td>
            </tr>
        </table>

        @if($odp->isNotEmpty())
            @if($tm->isNotEmpty())<div class="rv-section">Occupation du domaine public (ODP)</div>@endif
            <table class="rv-table">
                <thead>
                    <tr>
                        <th class="n">N°</th>
                        <th>Panneau</th>
                        <th class="c">Surface (m²)</th>
                        <th class="r">Tarif (FCFA/m²/mois)</th>
                        <th class="c">Durée (mois)</th>
                        <th class="r">Montant (FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($odp as $i => $row)
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td class="ref">{{ $row['reference'] }}</td>
                            <td class="c">{{ $fmtM2($row['surface']) }}</td>
                            <td class="r">{{ $fmt($row['rate_applied'] ?? $row['rate']) }}</td>
                            <td class="c">{{ $row['months'] }}</td>
                            <td class="r">{{ $fmt($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    @if($tm->isNotEmpty())
                        <tr class="st"><td colspan="5" class="r">Sous-total ODP</td><td class="r">{{ $fmt($odp->sum('amount')) }}</td></tr>
                    @endif
                </tbody>
            </table>
        @endif

        @if($tm->isNotEmpty())
            @if($odp->isNotEmpty())<div class="rv-section">Taxe municipale (TM)</div>@endif
            <table class="rv-table">
                <thead>
                    <tr>
                        <th class="n">N°</th>
                        <th>Panneau</th>
                        <th>Annonceur</th>
                        <th>Affichage</th>
                        <th class="c">Surface (m²)</th>
                        <th class="r">Tarif (FCFA/m²/mois)</th>
                        <th class="c">Durée (mois)</th>
                        <th class="r">Montant (FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tm as $i => $row)
                        <tr>
                            <td class="n">{{ $i + 1 }}</td>
                            <td class="ref">{{ $row['reference'] }}</td>
                            <td>{{ $row['client_name'] ?? '—' }}</td>
                            <td style="white-space:nowrap">
                                @if(!empty($row['campaign_start']) && !empty($row['campaign_end']))
                                    {{ \Carbon\Carbon::parse($row['campaign_start'])->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($row['campaign_end'])->format('d/m/Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="c">{{ $fmtM2($row['surface']) }}</td>
                            <td class="r">{{ $fmt($row['rate_applied'] ?? $row['rate']) }}</td>
                            <td class="c">{{ $row['months'] }}</td>
                            <td class="r">{{ $fmt($row['amount']) }}</td>
                        </tr>
                    @endforeach
                    @if($odp->isNotEmpty())
                        <tr class="st"><td colspan="7" class="r">Sous-total TM</td><td class="r">{{ $fmt($tm->sum('amount')) }}</td></tr>
                    @endif
                </tbody>
            </table>
        @endif

        <table class="rv-total">
            <tr>
                <td>TOTAL À PAYER</td>
                <td style="width:110px">{{ $fmt($total) }}</td>
            </tr>
        </table>

        <div class="rv-arrete">
            Arrêté le présent relevé à la somme de
            <b>{{ \App\Support\MontantEnLettres::en((int) round($total)) }} ({{ $fmt($total) }}) francs CFA</b>.
        </div>

        <div class="rv-base">
            @if($odp->isNotEmpty())
                Base de calcul{{ $tm->isNotEmpty() ? " de l'ODP" : '' }} : tarif communal × surface (m²) × nombre de mois d'occupation. Un panneau double face est compté une seule fois.
            @endif
            @if($tm->isNotEmpty())
                @if($odp->isNotEmpty())<br>@endif
                Base de calcul{{ $odp->isNotEmpty() ? ' de la TM' : '' }} : tarif communal × surface (m²) × nombre de mois d'affichage de chaque campagne.
            @endif
        </div>
    </div>
@endforeach

@include('pdf.partials.charte-footer')

</body>
</html>

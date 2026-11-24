{{--
    Relevé des taxes communales pour la mairie — PDF du « Détail par panneau »
    (TaxController::detailsPdf).

    2026-11-24 : reproduit le PDF modèle fourni par l'utilisateur
    (Releve-ODP-Yopougon-novembre-2026.pdf) — tailles, graisses et
    espacements — SANS le bloc « À l'attention de Monsieur le Maire… » ni la
    signature (demande explicite). Un relevé par commune, saut de page entre
    deux communes.
      · ODP seule : « Relevé des taxes d'occupation du domaine public ».
      · TM seule  : « Relevé de la taxe municipale » (annonceur, dates
                    d'affichage, trimestres entre parenthèses).
      · Les deux  : deux tableaux (ODP puis TM), sous-totaux, un total.
    Rien sur les filtres (catégories exclues, sélection…).

    Variables : $lines, $totals, $periodLabel, $periodeDebut, $periodeFin
    (bornes de TaxCalculationService::resolvePeriod, sinon celles des lignes).
--}}
@php
    $ch        = \App\Support\PdfCharte::data();
    $coord     = (array) ($ch['coordonnees'] ?? []);
    $operateur = $operatorName ?? config('app.operator_name', 'CIBLE CI');
    $adresse   = array_values(array_filter(array_map('trim', explode('·', (string) ($coord['adresse'] ?? '')))));
    // Pied de page du modèle : le numéro fixe seulement (dernier de la liste).
    $telephones = array_values(array_filter(array_map('trim', explode('·', (string) ($coord['telephones'] ?? '')))));
    $pied = implode(' · ', array_filter([
        $operateur, ...$adresse, end($telephones) ?: null, $coord['email'] ?? null, $coord['site'] ?? null,
    ]));

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
    <style>@page { size: A4 portrait; margin: 16mm 18mm 18mm 18mm !important; }</style>
    @include('pdf.partials.charte-styles')
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:{!! $ch['ff_texte'] !!}; font-size:10pt; color:#111111; }
        .rv-saut { page-break-before: always; }
        .lisere { width:100%; border-collapse:collapse; margin-bottom:12pt; }
        .lisere td { height:3.5pt; padding:0; }
        .rv-tete { width:100%; border-collapse:collapse; }
        .rv-tete td { vertical-align:top; padding:0; }
        .rv-tete img { height:36pt; display:block; }
        .rv-regie { text-align:right; font-size:8pt; color:#3d3d3d; }
        .rv-regie div { line-height:9pt; margin:0; padding:0; }
        .rv-regie .nom { font-size:9pt; font-weight:800; color:#111111; }
        .rv-filet { height:1pt; background:#111111; margin:9pt 0 12pt; }
        .rv-date { text-align:right; font-size:10.5pt; margin-bottom:22pt; }
        .rv-titre { font-family:{!! $ch['ff_titres'] !!}; font-size:15pt; font-weight:700; color:#111111; text-transform:uppercase; }
        .rv-soustitre { font-size:10pt; color:#3d3d3d; margin:3pt 0 14pt; }
        .rv-periode { border-top:2pt solid #111111; border-bottom:1.2pt solid #111111; padding:7pt 0 7pt; margin-bottom:12pt; }
        .rv-periode .v { font-family:{!! $ch['ff_titres'] !!}; font-size:13pt; font-weight:700; color:#111111; }
        .rv-periode .s { font-size:8.5pt; font-weight:700; color:#3d3d3d; margin-left:6pt; }
        .rv-infos { border-collapse:collapse; margin-bottom:10pt; }
        .rv-infos td { font-size:9.5pt; color:#3d3d3d; padding:0 26pt 0 0; }
        .rv-infos b { color:#111111; font-weight:800; }
        .rv-section { font-family:{!! $ch['ff_titres'] !!}; font-size:10.5pt; font-weight:700; margin:12pt 0 2pt; color:#111111; }
        .rv-table { width:100%; border-collapse:collapse; }
        .rv-table th { font-size:8pt; font-weight:800; text-transform:uppercase; color:#2b2b2b; text-align:left; padding:6pt 6pt; border-bottom:0.8pt solid #111111; }
        .rv-table td { font-size:10pt; padding:7pt 6pt; border-bottom:0.5pt solid #d6d6d6; vertical-align:top; }
        .rv-table .r { text-align:right; }
        .rv-table .c { text-align:center; }
        .rv-table .ref { font-weight:800; letter-spacing:.3pt; }
        .rv-table tr { page-break-inside:avoid; }
        .rv-table tr.st td { font-weight:700; border-bottom:none; }
        .rv-total { width:100%; border-collapse:collapse; border-bottom:2pt solid #111111; page-break-inside:avoid; }
        .rv-total td { padding:8pt 6pt; font-weight:800; text-align:right; }
        .rv-total .l { font-size:10.5pt; }
        .rv-total .m { font-size:11.5pt; width:95pt; }
        .rv-arrete { font-size:10pt; margin-top:15pt; page-break-inside:avoid; }
        .rv-base { font-size:8.5pt; color:#3d3d3d; margin-top:9pt; line-height:1.45; }
        .rv-pied { position:fixed; left:0; right:0; bottom:-10mm; border-top:0.5pt solid #cfcfcf; padding-top:5pt;
                   font-size:7pt; color:#555555; white-space:nowrap; }
    </style>
</head>
<body>

<div class="rv-pied">{{ $pied }}</div>

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
        <table class="lisere"><tr>
            @foreach($ch['lisere'] as $couleur)<td style="background:{{ $couleur }}"></td>@endforeach
        </tr></table>

        <table class="rv-tete">
            <tr>
                <td>@if(!empty($logoCibleLight))<img src="{{ $logoCibleLight }}" alt="{{ $ch['nom'] }}">@endif</td>
                <td class="rv-regie">
                    <div class="nom">{{ $operateur }}</div>
                    {{-- Mention du modèle validé par l'utilisateur (24/11). --}}
                    <div>Régie publicitaire</div>
                    @foreach($adresse as $ligneAdresse)<div>{{ $ligneAdresse }}{{ $loop->last ? ' – Côte d\'Ivoire' : '' }}</div>@endforeach
                </td>
            </tr>
        </table>
        <div class="rv-filet"></div>

        <div class="rv-date">Abidjan, le {{ $dateDoc }}</div>

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
                        <th class="c" style="width:26pt">N°</th>
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
                            <td class="c">{{ $i + 1 }}</td>
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
                        <th class="c" style="width:26pt">N°</th>
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
                            <td class="c">{{ $i + 1 }}</td>
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
                <td class="l">TOTAL À PAYER</td>
                <td class="m">{{ $fmt($total) }}</td>
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

</body>
</html>

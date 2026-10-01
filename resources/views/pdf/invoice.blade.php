@php
    use chillerlan\QRCode\QRCode;
    use chillerlan\QRCode\QROptions;
    use chillerlan\QRCode\Common\EccLevel;
    use chillerlan\QRCode\Output\QRGdImagePNG;

    // ═══════════════════════════════════════════════════════════════════
    // PDF FACTURE FNE — Refonte 2026-07-21 selon modèle officiel DGI CI
    // fourni par la patronne (2 exemples : facture RIMCO-SETACI + SUCAF).
    //
    // Structure :
    //   1. Header 2 colonnes : émetteur (bloc encadré) + réf/QR/badge FNE
    //   2. Bandeau infos vendeur/PDV/période (gauche) + client (droite)
    //   3. Tableau lignes (Réf · Désignation · PU HT · Qté · Unité · Taxes · Rem · Mt HT)
    //   4. Tableau totaux aligné à droite
    //   5. Résumé de la facture (TVA + ODP+TM+TSP)
    //   6. Ventilation détaillée (ODP · TM · TSP)
    // ═══════════════════════════════════════════════════════════════════

    $isCreditNote = $invoice->isCreditNote();
    $company = config('billing.company');

    // ── Emplacement : concaténation des communes uniques des lignes ──
    $emplacement = $invoice->lines
        ->pluck('snapshot_commune_name')
        ->filter()
        ->unique()
        ->take(4)
        ->implode(' · ');
    if ($emplacement === '') $emplacement = '—';

    // ── Nombre de panneaux : somme des quantités des lignes panneaux ──
    $nbPanneaux = $invoice->lines->sum('quantite') ?: 0;

    // ── Période : depuis campagne liée si dispo, sinon issued_at + duree ──
    if ($invoice->campaign?->start_date && $invoice->campaign?->end_date) {
        $periode = strtoupper('DU ' . $invoice->campaign->start_date->format('d/m/Y')
                 . ' AU ' . $invoice->campaign->end_date->format('d/m/Y'));
    } else {
        $issuedAt = $invoice->issued_at;
        $maxDuree = (float) ($invoice->lines->max('duree_mois') ?: 1);
        $endEst   = $issuedAt?->copy()->addMonths((int) ceil($maxDuree));
        $periode  = $issuedAt && $endEst
            ? strtoupper('DU ' . $issuedAt->format('d/m/Y') . ' AU ' . $endEst->format('d/m/Y'))
            : '—';
    }

    // ── Mode de paiement : dérivé du 1er versement enregistré, sinon "À préciser" ──
    $modePaiementMap = [
        'especes'        => 'Espèces',
        'cheque'         => 'Chèque',
        'virement'       => 'Virement bancaire',
        'mobile_money'   => 'Mobile Money',
        'carte_bancaire' => 'Carte bancaire',
        'compensation'   => 'Compensation',
        'autre'          => 'Autre',
    ];
    $modePaiement = $invoice->payments->first()
        ? ($modePaiementMap[$invoice->payments->first()->mode] ?? ucfirst($invoice->payments->first()->mode))
        : 'À préciser';

    // ── Nom du vendeur : créateur de la facture (auth au moment du store) ──
    $nomVendeur = $invoice->creator?->name ?? '—';

    // ── QR CODE : contient les infos essentielles de la facture FNE ──
    // Format lisible + machine : NCC émetteur · référence · date · TTC.
    // Rendu SVG (léger + net à toute taille + DomPDF compatible via data URI).
    try {
        $qrPayload = sprintf(
            "FNE|EMET:%s|FAC:%s|DATE:%s|TTC:%s",
            $company['ncc'] ?? '',
            $invoice->reference,
            $invoice->issued_at?->format('Y-m-d') ?? '',
            (int) $invoice->total_a_payer
        );
        // PNG via GD : DomPDF supporte parfaitement le data URI PNG en
        // <img src="">, contrairement au SVG qui ne se rend pas dans un
        // <img>. Poids réduit (< 2 KB pour un QR classique) + rendu net.
        $qrOptions = new QROptions([
            'version'         => 6,
            'outputInterface' => QRGdImagePNG::class,
            'eccLevel'        => EccLevel::M,
            'outputBase64'    => true,
            'scale'           => 4,
        ]);
        // Rendu SVG base64 → data URI directement utilisable dans <img src>
        $qrSrc = (new QRCode($qrOptions))->render($qrPayload);
    } catch (\Throwable $e) {
        $qrSrc = null; // Fallback silencieux si la lib crashe
    }

    // ── Logo émetteur (image locale, base64 pour DomPDF sans réseau) ──
    $logoPath = public_path($company['logo_path'] ?? 'images/logol.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    // ── Ventilation détaillée (résumé bas de page) ──
    $netHt         = (int) $invoice->net_ht;
    $totalTtc      = (int) $invoice->amount_ttc;
    $tvaAmount     = (int) $invoice->tva_amount;
    $tspAmount     = (int) $invoice->tsp_amount;
    $tmTotal       = (int) $invoice->tm_total;
    $odpTotal      = (int) $invoice->odp_total;
    $tvaRate       = (float) $invoice->tva;

    // Services annexes : depuis 2026-08-03, chaque service porte un
    // flag `tva_applicable`. On sépare le HT en 2 sous-totaux pour ne
    // taxer que ceux qui l'exigent (les autres restent en HT strict).
    $servicesWithTva = $invoice->services->filter(fn($s) => (bool) $s->tva_applicable);
    $servicesNoTva   = $invoice->services->filter(fn($s) => !(bool) $s->tva_applicable);
    $servicesHtTot   = (int) $invoice->services->sum('prix_ht');
    $servicesHtWithTva = (int) $servicesWithTva->sum('prix_ht');
    $servicesHtNoTva   = (int) $servicesNoTva->sum('prix_ht');
    $servicesTvaAmount = (int) round($servicesHtWithTva * ($tvaRate / 100));
    $servicesTtcTot    = $servicesHtWithTva + $servicesTvaAmount + $servicesHtNoTva;

    $autresTaxes   = $tspAmount + $tmTotal + $odpTotal;
    $totalAPayer   = (int) $invoice->total_a_payer;

    // Base et taux du bloc "ODP+TM+TSP" du résumé — TTC panneaux + TTC
    // services (HT+TVA pour les taxés, HT seul pour les autres).
    $autresBase = $totalTtc + $servicesTtcTot;
    $autresRate = $autresBase > 0 ? round(($autresTaxes / $autresBase) * 100, 5) : 0;

    // Helper format entier (espaces + arrondi FCFA — pas de décimales)
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $isCreditNote ? 'Avoir' : 'Facture de vente' }} N° {{ $invoice->reference }}</title>
    {{-- 2026-10-01 — charte graphique (config/charte.php) : habillage seul,
         AUCUNE mention, donnée ni montant modifié (modèle officiel FNE). --}}
    @include('pdf.partials.charte-styles')
    <style>
        /* !important : sans lui, « * { margin:0 } » annule les marges de page sous DomPDF. */
        @page { margin: 8mm 10mm 8mm 10mm !important; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: {!! $charte['ff_texte'] !!};
            font-size: 10px;
            color: {{ $charte['noir'] }};
            line-height: 1.2;
        }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        strong { font-weight: 700; }

        /* ═════════ HEADER 2 colonnes ═════════ */
        .header { margin-bottom: 10px; }
        .header td { padding: 0; }
        .header .left  { width: 54%; padding-right: 12px; }
        .header .right { width: 46%; text-align: right; }

        .emit-box {
            border: 1px solid {{ $charte['gris'] }};
            border-left: 3px solid {{ $charte['rouge'] }};
            border-radius: 6px;
            padding: 10px 14px;
        }
        .emit-box .co-name {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: {{ $charte['noir'] }};
            margin-bottom: 4px;
        }
        .emit-box .co-line {
            font-size: 10px;
            color: {{ $charte['noir'] }};
            line-height: 1.25;
        }

        .header .right .brand-strip img {
            height: 44px;
            max-width: 200px;
            margin-bottom: 8px;
        }
        .header .right .fac-num {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 12.5px;
            font-weight: 700;
            color: {{ $charte['noir'] }};
            margin-bottom: 8px;
        }
        /* QR + badge FNE côte à côte via <table> — DomPDF gère mieux
           les tables inline que le display:inline-block pour aligner
           2 blocs de taille définie. */
        .qr-fne-tbl {
            margin-left: auto;
            margin-right: 0;
            border-collapse: separate;
            border-spacing: 6px 0;
        }
        .qr-fne-tbl td {
            vertical-align: middle;
            padding: 0;
        }
        .qr-fne-tbl img.qr {
            width: 74px; height: 74px;
            border: 1px solid {{ $charte['gris'] }};
            padding: 3px;
            background: {{ $charte['blanc'] }};
            display: block;
        }
        .fne-badge {
            display: inline-block;
            width: 96px;
            background: {{ $charte['blanc'] }};
            border: 1px solid {{ $charte['gris'] }};
            padding: 6px 6px 8px;
            text-align: center;
        }
        .fne-badge .fne-flag {
            display: inline-block;
            width: 26px; height: 16px;
            border: 1px solid {{ $charte['gris'] }};
        }
        /* Drapeau de Côte d'Ivoire du badge FNE : couleurs NATIONALES conservées
           volontairement (marque officielle de la facture normalisée, hors charte). */
        .fne-badge .fne-flag-orange { background: #f77f00; width: 8.5px; height: 16px; display: inline-block; vertical-align: top; }
        .fne-badge .fne-flag-white  { background: {{ $charte['blanc'] }}; width: 8.5px; height: 16px; display: inline-block; vertical-align: top; border-left: 1px solid {{ $charte['gris'] }}; border-right: 1px solid {{ $charte['gris'] }}; }
        .fne-badge .fne-flag-green  { background: #009e60; width: 8.5px; height: 16px; display: inline-block; vertical-align: top; }
        .fne-badge .fne-title {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 8.5px;
            font-weight: 700;
            color: {{ $charte['noir'] }};
            margin-top: 4px;
            line-height: 1.0;
            letter-spacing: 0.3px;
        }
        .fne-badge .fne-sub {
            font-size: 6.5px;
            color: {{ $charte['texte_doux'] }};
            margin-top: 2px;
            line-height: 1.0;
        }

        /* ═════════ Bandeau infos vendeur / client ═════════ */
        .infos { margin-bottom: 14px; }
        .infos td { padding: 0; }
        .infos .vendor { width: 58%; padding-right: 20px; }
        .infos .client { width: 42%; }
        .infos .row {
            font-size: 10px;
            padding: 0;
            line-height: 1.2;
        }
        .infos .row .lbl { font-weight: 700; color: {{ $charte['noir'] }}; }
        .infos .client-title {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 13px;
            font-weight: 700;
            color: {{ $charte['noir'] }};
            border-bottom: 2px solid {{ $charte['rouge'] }};
            padding-bottom: 2px;
            margin-bottom: 4px;
        }
        .infos .highlight {
            font-weight: 700;
            color: {{ $charte['noir'] }};
        }

        /* ═════════ Tableau des lignes ═════════ */
        .lines {
            margin-top: 4px;
            margin-bottom: 0;
            border: 1px solid {{ $charte['gris'] }};
        }
        .lines thead th {
            background: {{ $charte['noir'] }};
            color: {{ $charte['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 8.5px;
            font-weight: 600;
            text-align: left;
            padding: 5px 6px;
            white-space: nowrap;
            border-right: 1px solid {{ $charte['texte_doux'] }};
        }
        .lines thead th:last-child { border-right: none; }
        .lines thead th.num { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td {
            padding: 4px 8px;
            border-bottom: 1px solid {{ $charte['gris'] }};
            border-right: 1px solid {{ $charte['gris'] }};
            font-size: 9.5px;
        }
        .lines tbody tr:last-child td { border-bottom: none; }
        .lines tbody td:last-child { border-right: none; }
        .lines tbody td.num { text-align: right; }
        .lines tbody td.center { text-align: center; }
        .lines tbody td.ref { font-family: {!! $charte['ff_mono'] !!}; font-weight: 700; color: {{ $charte['rouge'] }}; }
        .lines tbody tr.commune-sep td {
            background: {{ $charte['gris_clair'] }}; padding: 4px 8px; font-size: 8.5px; font-weight: 700;
            color: {{ $charte['noir'] }}; border-top: 1px solid {{ $charte['gris'] }}; border-left: 3px solid {{ $charte['rouge'] }};
        }

        /* Largeurs colonnes */
        .col-ref { width: 8%; }
        .col-des { width: 32%; }
        .col-pu  { width: 12%; }
        .col-qte { width: 6%; }
        .col-un  { width: 6%; }
        .col-tva { width: 11%; }
        .col-rem { width: 8%; }
        .col-ht  { width: 17%; }

        /* ═════════ Tableau totaux (aligné à droite) ═════════ */
        .totals-wrap { margin-top: -1px; }
        .totals-wrap table {
            width: 55%;
            margin-left: 45%;
            border-collapse: collapse;
        }
        .totals-wrap td {
            padding: 4px 10px;
            font-size: 10px;
            border: 1px solid {{ $charte['gris'] }};
        }
        .totals-wrap .lbl-tot { text-align: left; font-weight: 700; background: {{ $charte['gris_clair'] }}; }
        .totals-wrap .val-tot { text-align: right; font-weight: 700; }
        .totals-wrap tr.grand td {
            background: {{ $charte['noir'] }};
            color: {{ $charte['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
            font-size: 12px;
            padding: 7px 10px;
            border-color: {{ $charte['noir'] }};
        }
        .totals-wrap tr.grand td.val-tot { color: {{ $charte['jaune'] }}; }

        /* ═════════ Bloc résumé de la facture ═════════ */
        .resume-title {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 10px;
            font-weight: 700;
            margin: 10px 0 4px;
            padding-left: 6px;
            border-left: 3px solid {{ $charte['rouge'] }};
            color: {{ $charte['noir'] }};
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .resume {
            border: 1px solid {{ $charte['gris'] }};
        }
        .resume thead th {
            background: {{ $charte['noir'] }};
            color: {{ $charte['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 9px;
            font-weight: 600;
            text-align: left;
            padding: 6px 8px;
            text-transform: uppercase;
        }
        .resume thead th.num { text-align: right; }
        .resume tbody td {
            padding: 4px 8px;
            font-size: 9.5px;
            border-bottom: 1px solid {{ $charte['gris'] }};
        }
        .resume tbody tr:last-child td { border-bottom: none; }
        .resume tbody td.num { text-align: right; }

        /* ═════════ Ventilation ODP/TM/TSP ═════════ */
        .ventil {
            margin-top: 5px;
            text-align: center;
            font-size: 9.5px;
            line-height: 1.15;
        }
        .ventil .v-line {
            display: block;
        }

        /* Footer en flow normal (le position:fixed de DomPDF ne respecte
           pas @page margin-bottom et provoque des chevauchements). */
        .footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid {{ $charte['gris'] }};
            font-size: 8px;
            color: {{ $charte['texte_doux'] }};
            text-align: center;
            line-height: 1.15;
        }
        .footer .payment-terms {
            margin-bottom: 2px;
            color: {{ $charte['noir'] }};
            font-weight: 700;
        }
    </style>
</head>
<body>

    {{-- Liseré 5 couleurs de la charte (signature commune à tous les PDF). --}}
    <div style="margin-bottom:8px;">@include('pdf.partials.charte-lisere')</div>

    {{-- ═════════ HEADER : émetteur (gauche) + réf + QR + FNE (droite) ═════════ --}}
    <table class="header">
        <tr>
            <td class="left">
                <div class="emit-box">
                    <div class="co-name">{{ $company['name'] }}</div>
                    @if(!empty($company['ncc']))
                        <div class="co-line"><strong>NCC :</strong> {{ $company['ncc'] }}</div>
                    @endif
                    @if(!empty($company['regime_imposition']))
                        <div class="co-line"><strong>Régime d'imposition :</strong> {{ $company['regime_imposition'] }}</div>
                    @endif
                    @if(!empty($company['centre_impots']))
                        <div class="co-line"><strong>Centre des impôts :</strong> {{ $company['centre_impots'] }}</div>
                    @endif
                </div>
            </td>
            <td class="right">
                <div class="brand-strip">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}">
                    @endif
                </div>
                <div class="fac-num">
                    {{ $isCreditNote ? 'Avoir N°' : 'Facture de vente N°' }}
                    {{ $invoice->reference }}
                </div>
                <table class="qr-fne-tbl">
                    <tr>
                        @if($qrSrc)
                            <td>
                                <img class="qr" src="{{ $qrSrc }}" alt="QR facture FNE">
                            </td>
                        @endif
                        <td>
                            <div class="fne-badge">
                                <div style="line-height:0">
                                    <span class="fne-flag-orange"></span><span class="fne-flag-white"></span><span class="fne-flag-green"></span>
                                </div>
                                <div class="fne-title">FNE</div>
                                <div class="fne-sub">FACTURE<br>NORMALISÉE<br>ÉLECTRONIQUE</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- ═════════ INFOS VENDEUR (gauche) + CLIENT (droite) ═════════ --}}
    <table class="infos">
        <tr>
            <td class="vendor">
                @if(!empty($company['rccm']))
                    <div class="row"><span class="lbl">RCCM :</span> {{ $company['rccm'] }}</div>
                @endif
                @if(!empty(config('billing.bank.name')) || !empty(config('billing.bank.rib')))
                    <div class="row"><span class="lbl">Références bancaires :</span></div>
                    <div class="row">
                        {{ config('billing.bank.name') }}
                        @if(config('billing.bank.rib')) N° {{ config('billing.bank.rib') }} @endif
                    </div>
                @endif
                <div class="row"><span class="lbl">Établissement :</span> {{ $company['name'] }}</div>
                @if(!empty($company['address']))
                    <div class="row"><span class="lbl">Adresse :</span> {{ $company['address'] }}</div>
                @endif
                @if(!empty($company['phone']))
                    <div class="row"><span class="lbl">N° Tel :</span> {{ $company['phone'] }}</div>
                @endif
                @if(!empty($company['email']))
                    <div class="row"><span class="lbl">Mail :</span> {{ $company['email'] }}</div>
                @endif
                <div class="row"><span class="lbl">Nom du vendeur :</span> {{ $nomVendeur }}</div>
                <div class="row"><span class="lbl">Nom de PDV :</span> {{ $company['pdv_name'] ?? 'COMPTA 1' }}</div>
                <div class="row"><span class="lbl">Date et heure :</span> {{ optional($invoice->issued_at)->format('d/m/Y H:i:s') }}</div>
                <div class="row"><span class="lbl">Mode de paiement :</span> {{ $modePaiement }}</div>
                <div class="row highlight" style="margin-top: 6px;">PERIODE : {{ $periode }}</div>
                <div class="row highlight">EMPLACEMENT : {{ $emplacement }}</div>
                @if($nbPanneaux > 0)
                    <div class="row highlight">Nbre de panneaux : {{ str_pad($nbPanneaux, 2, '0', STR_PAD_LEFT) }}</div>
                @endif
            </td>
            <td class="client">
                <div class="client-title">Client</div>
                <div class="row"><span class="lbl">Nom :</span> {{ $invoice->client?->name ?? '—' }}</div>
                @if(!empty($invoice->client?->email))
                    <div class="row"><span class="lbl">Adresse :</span> {{ $invoice->client->email }}</div>
                @endif
                @if(!empty($invoice->client?->address))
                    <div class="row"><span class="lbl">Adresse postale :</span> {{ $invoice->client->address }}</div>
                @endif
                @if(!empty($invoice->client?->phone))
                    <div class="row"><span class="lbl">Téléphone :</span> {{ $invoice->client->phone }}</div>
                @endif
                @if(!empty($invoice->client?->ncc))
                    <div class="row"><span class="lbl">NCC :</span> {{ $invoice->client->ncc }}</div>
                @endif
                @if(!empty($invoice->client?->rccm))
                    <div class="row"><span class="lbl">RCCM :</span> {{ $invoice->client->rccm }}</div>
                @endif
                @if(!empty($invoice->client?->regime_imposition))
                    <div class="row"><span class="lbl">Régime d'imposition :</span> {{ $invoice->client->regime_imposition }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- ═════════ TABLEAU DES LIGNES ═════════ --}}
    <table class="lines">
        <thead>
            <tr>
                <th class="col-ref">Réf</th>
                <th class="col-des">Désignation</th>
                <th class="col-pu num">P.U HT</th>
                <th class="col-qte center">Qté</th>
                <th class="col-un center">Unité</th>
                <th class="col-tva center">Taxes (%)</th>
                <th class="col-rem num">Rem. (%)</th>
                <th class="col-ht num">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            {{-- Regroupement par commune (demande 2026-08-03) : les lignes
                 de la même commune sont affichées ensemble. Séparateur
                 discret ligne fine avec pin + nom, pour ne pas alourdir
                 le PDF officiel FNE. Sous-total commune non exposé ici
                 (le total général reste la source d'autorité). --}}
            @php
                $groupedLinesPdf = $invoice->lines
                    ->sortBy([['snapshot_commune_name', 'asc'], ['designation', 'asc']])
                    ->groupBy(fn($l) => $l->snapshot_commune_name ?: '—');
            @endphp
            @foreach($groupedLinesPdf as $communeName => $groupLines)
                @if($groupedLinesPdf->count() > 1)
                    {{-- On n'affiche le séparateur que si multi-communes.
                         Facture mono-commune = liste simple, plus propre. --}}
                    <tr class="commune-sep">
                        <td colspan="8">
                            {{ $communeName }}
                        </td>
                    </tr>
                @endif
                @foreach($groupLines as $line)
                    <tr>
                        <td class="ref">LP</td>
                        <td>{{ $line->designation }}</td>
                        <td class="num">{{ $fmt($line->pu_ht_mensuel) }}</td>
                        <td class="center">{{ $line->quantite }}</td>
                        <td class="center">m²</td>
                        <td class="center">TVA ({{ (int) $tvaRate }})</td>
                        <td class="num">{{ $invoice->remise_pct > 0 ? number_format($invoice->remise_pct, 2, ',', '') : '0' }}</td>
                        <td class="num">{{ $fmt($line->montant_ht_ligne) }}</td>
                    </tr>
                @endforeach
            @endforeach

            @foreach($invoice->services as $svc)
                @php
                    $ref = str_contains(mb_strtolower($svc->label), 'impression') ? 'IMP'
                         : (str_contains(mb_strtolower($svc->label), 'pose') ? 'FP'
                         : (str_contains(mb_strtolower($svc->label), 'électricité') || str_contains(mb_strtolower($svc->label), 'electricite') ? 'ELEC' : 'SRV'));
                    $svcHasTva = (bool) $svc->tva_applicable;
                @endphp
                <tr>
                    <td class="ref">{{ $ref }}</td>
                    <td>{{ $svc->label }}{{ $svcHasTva ? '' : ' (HT strict)' }}</td>
                    <td class="num">{{ $fmt($svc->prix_ht) }}</td>
                    <td class="center">1</td>
                    <td class="center">—</td>
                    <td class="center">{{ $svcHasTva ? 'TVA (' . (int) $tvaRate . ')' : '—' }}</td>
                    <td class="num">0</td>
                    <td class="num">{{ $fmt($svc->prix_ht) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ═════════ TOTAUX (aligné à droite) ═════════ --}}
    <div class="totals-wrap">
        <table>
            @php $htBrut = (int) $invoice->amount; $remiseMontant = $htBrut - $netHt; @endphp
            <tr>
                <td class="lbl-tot">TOTAL HT</td>
                <td class="val-tot">{{ $fmt($htBrut) }}</td>
            </tr>
            @if($remiseMontant > 0)
                <tr>
                    <td class="lbl-tot">REMISE ({{ number_format($invoice->remise_pct, 2, ',', '') }} %)</td>
                    <td class="val-tot">- {{ $fmt($remiseMontant) }}</td>
                </tr>
                <tr>
                    <td class="lbl-tot">TOTAL HT APRÈS REMISE</td>
                    <td class="val-tot">{{ $fmt($netHt) }}</td>
                </tr>
            @endif
            @if($servicesHtTot > 0)
                <tr>
                    <td class="lbl-tot">Services annexes HT</td>
                    <td class="val-tot">{{ $fmt($servicesHtTot) }}</td>
                </tr>
            @endif
            <tr>
                <td class="lbl-tot">TVA</td>
                <td class="val-tot">{{ $fmt($tvaAmount + $servicesTvaAmount) }}</td>
            </tr>
            <tr>
                <td class="lbl-tot">TOTAL TTC</td>
                <td class="val-tot">{{ $fmt($totalTtc + $servicesTtcTot) }}</td>
            </tr>
            <tr>
                <td class="lbl-tot">AUTRES TAXES</td>
                <td class="val-tot">{{ $fmt($autresTaxes) }}</td>
            </tr>
            <tr class="grand">
                <td>{{ $isCreditNote ? 'TOTAL À AVOIRER' : 'TOTAL À PAYER' }}</td>
                <td class="val-tot">{{ $fmt($totalAPayer) }}</td>
            </tr>
        </table>
    </div>

    {{-- ═════════ RESUME DE LA FACTURE ═════════ --}}
    <div class="resume-title">RESUME DE LA FACTURE</div>
    <table class="resume">
        <thead>
            <tr>
                <th style="width: 45%;">CATEGORIE</th>
                <th class="num" style="width: 22%;">SOUS-TOTAL</th>
                <th class="num" style="width: 15%;">TAUX (%)</th>
                <th class="num" style="width: 18%;">TOTAL TAXES</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>TVA normal - TVA sur HT {{ number_format($tvaRate, 2, ',', '') }}% - A</td>
                <td class="num">{{ $fmt($netHt + $servicesHtWithTva) }}</td>
                <td class="num">{{ (int) $tvaRate }}%</td>
                <td class="num">{{ $fmt($tvaAmount + $servicesTvaAmount) }}</td>
            </tr>
            @if($servicesHtNoTva > 0)
            <tr>
                <td>Services HT strict (non soumis TVA)</td>
                <td class="num">{{ $fmt($servicesHtNoTva) }}</td>
                <td class="num">0%</td>
                <td class="num">0</td>
            </tr>
            @endif
            <tr>
                <td>ODP+TM+TSP</td>
                <td class="num">{{ $fmt($autresBase) }}</td>
                <td class="num">{{ number_format($autresRate, 5, '.', '') }}%</td>
                <td class="num">{{ $fmt($autresTaxes) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- ═════════ VENTILATION DÉTAILLÉE ODP · TM · TSP ═════════ --}}
    <div class="ventil">
        <span class="v-line">ODP = {{ number_format($odpTotal, 0, ',', '.') }}</span>
        <span class="v-line">TM = {{ number_format($tmTotal, 0, ',', '.') }}</span>
        <span class="v-line">TSP = {{ number_format($tspAmount, 0, ',', '.') }}</span>
    </div>

    {{-- ═════════ FOOTER LÉGAL ═════════ --}}
    <div class="footer">
        <div class="payment-terms">
            {{ $invoice->notes_client ?: config('billing.payment_terms_default') }}
        </div>
        <div>
            {{ config('billing.legal_mentions') }}
        </div>
    </div>

</body>
</html>

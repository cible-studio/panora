@php
    /**
     * PDF Devis — modèle CIBLE SARL officiel (BARRY PN-2025-09).
     *
     * Palette CIBLE :
     *   • Orange  #E8A020 (accent / call-out)
     *   • Bleu marine #1a3a5c (headers de section)
     *   • Fond neutre #ffffff / #f5f5f5
     *
     * Structure 3 pages :
     *   Page 1 — Header + Émetteur/Client + tableau lignes + taxes + services + net + modalités
     *   Page 2 — Signatures grand format (Directrice Commerciale + Annonceur)
     *   Page 3 — 8 articles de Conditions Générales
     *
     * Ce template ne fait AUCUN calcul. Tous les montants viennent du
     * modèle Quote (calculés par QuoteBuilder → InvoiceCalculator :
     * source unique). Modifier ici ne change JAMAIS le montant du devis.
     */
    $company = config('billing.company');
    $bank    = config('billing.bank');

    // Formatage entier FCFA (espaces normes locales)
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');

    // Logo émetteur en base64 (DomPDF ne charge PAS les URLs distantes,
    // isRemoteEnabled: false par défaut, base64 obligatoire).
    $logoPath = public_path($company['logo_path'] ?? 'images/logol.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    // Taxes publicitaires agrégées par commune (TM + ODP)
    // Reconstruites depuis odp_ligne / tm_ligne des lignes du devis.
    $taxesPub = [];
    foreach ($quote->lines as $line) {
        $commune = $line->snapshot_commune_name ?: '—';
        if ((int) $line->tm_ligne > 0) {
            $key = 'TM|' . $commune;
            $taxesPub[$key] = [
                'type'    => 'Taxe Municipale',
                'commune' => $commune,
                'rate'    => (float) $line->tm_rate_applique,
                'montant' => (int) (($taxesPub[$key]['montant'] ?? 0) + $line->tm_ligne),
            ];
        }
        if ((int) $line->odp_ligne > 0) {
            $key = 'ODP|' . $commune;
            $taxesPub[$key] = [
                'type'    => 'ODP',
                'commune' => $commune,
                'rate'    => (float) $line->odp_rate_applique,
                'montant' => (int) (($taxesPub[$key]['montant'] ?? 0) + $line->odp_ligne),
            ];
        }
    }
    uasort($taxesPub, fn($a, $b) => [$a['type'], $a['commune']] <=> [$b['type'], $b['commune']]);
    $totalTaxesPub = array_sum(array_column($taxesPub, 'montant'));

    // Totaux (sources : QuoteBuilder → InvoiceCalculator)
    $netHt         = (int) $quote->net_ht;
    $tvaAmount     = (int) $quote->tva_amount;
    $tspAmount     = (int) $quote->tsp_amount;
    $totalTtc      = (int) $quote->amount_ttc;      // panneaux HT + TVA + TSP
    $servicesHtTot = (int) $quote->services_ht_total;
    $totalAPayer   = (int) $quote->total_a_payer;   // TTC + Taxes pub + Services

    // Somme en toutes lettres (français, jusqu'aux millions)
    $montantEnLettres = function (int $n): string {
        if ($n === 0) return 'zéro';
        $units = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
                  'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
                  'dix-sept', 'dix-huit', 'dix-neuf'];
        $tens  = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante',
                  'soixante', 'quatre-vingt', 'quatre-vingt'];
        $sub999 = function (int $n) use (&$sub999, $units, $tens): string {
            if ($n < 20) return $units[$n];
            if ($n < 100) {
                $t = intdiv($n, 10); $u = $n % 10;
                if ($t === 7 || $t === 9) return $tens[$t] . '-' . $units[10 + $u];
                if ($t === 8 && $u === 0) return 'quatre-vingts';
                $sep = ($u === 1 && $t !== 8) ? ' et ' : '-';
                return $tens[$t] . ($u ? $sep . $units[$u] : '');
            }
            $c = intdiv($n, 100); $r = $n % 100;
            $prefix = $c === 1 ? 'cent' : $units[$c] . ' cent' . ($r === 0 ? 's' : '');
            return $prefix . ($r ? ' ' . $sub999($r) : '');
        };
        $out = '';
        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $out .= ($m === 1 ? 'un million' : $sub999($m) . ' millions') . ' ';
            $n %= 1_000_000;
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            $out .= ($k === 1 ? 'mille' : $sub999($k) . ' mille') . ' ';
            $n %= 1000;
        }
        if ($n > 0) $out .= $sub999($n);
        return trim($out);
    };

    $dateEmission = ($quote->sent_at ?: $quote->created_at)?->format('d/m/Y');
    $tvaRate = (int) $quote->tva;
    $tspRate = (int) config('billing.tsp_rate', 3);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis N° {{ $quote->reference }}</title>
    <style>
        @page { margin: 12mm 10mm 16mm 10mm; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Helvetica, sans-serif; font-size: 9.5px; color: #1a1a1a; line-height: 1.4; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: middle; }
        strong, b { font-weight: 700; }
        .page-break { page-break-after: always; }
        .no-break { page-break-inside: avoid; }

        /* ═══ HEADER : logo à gauche, bandeau ORANGE à droite ═══ */
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .header-left  {
            width: 28%;
            padding: 8px 10px;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-right: none;
            vertical-align: middle;
            text-align: center;
        }
        .header-left img { max-height: 60px; max-width: 160px; }
        .header-right {
            width: 72%;
            background: #E8A020;
            padding: 10px 16px;
            vertical-align: middle;
        }
        .header-right .title {
            font-size: 28px;
            font-weight: 900;
            color: #1a1a1a;
            letter-spacing: 3px;
            line-height: 1;
        }
        .header-right .meta {
            font-size: 11px;
            color: #1a1a1a;
            margin-top: 6px;
            line-height: 1.6;
        }
        .header-right .meta .lbl { font-weight: 700; display: inline-block; width: 42px; }
        .header-right .meta .val { color: #7f1d1d; font-weight: 700; }

        /* ═══ SECTION HEADER (bleu marine) — utilisé partout ═══ */
        .section-header {
            background: #1a3a5c;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            padding: 6px 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ═══ BLOC ÉMETTEUR / CLIENT ═══ */
        .party-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .party-table td { vertical-align: top; width: 50%; border: 1px solid #cbd5e1; padding: 0; }
        .party-body {
            padding: 8px 10px;
            font-size: 9.5px;
            line-height: 1.6;
        }
        .party-body .row { padding: 1px 0; }
        .party-body .lbl { font-weight: 700; color: #333; display: inline-block; min-width: 78px; }
        .party-body .link { color: #1a3a5c; text-decoration: underline; }

        /* ═══ TABLEAU DES LIGNES PANNEAUX ═══ */
        .panneaux-table { width: 100%; border-collapse: collapse; margin-bottom: 0; font-size: 9px; }
        .panneaux-table th {
            background: #1a3a5c;
            color: #ffffff;
            padding: 6px 4px;
            text-align: center;
            font-size: 9px;
            font-weight: 700;
            border-right: 1px solid #2c5282;
        }
        .panneaux-table th:last-child { border-right: none; }
        .panneaux-table th.left { text-align: left; padding-left: 10px; }
        .panneaux-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #e0e0e0;
            border-right: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 9px;
            text-align: center;
        }
        .panneaux-table td:last-child { border-right: none; }
        .panneaux-table td.designation-cell {
            text-align: left;
            padding-left: 10px;
            font-style: italic;
            line-height: 1.55;
        }
        .panneaux-table td.designation-cell .row-desc { display: block; }
        .panneaux-table td.num { text-align: right; padding-right: 8px; }
        .panneaux-table td.montant-ht-col { background: #f5f5f5; text-align: right; padding-right: 8px; font-weight: 600; }

        /* ═══ SOUS-TOTAUX (colonne 55% droite) ═══ */
        .subtotals { width: 55%; margin-left: 45%; border-collapse: collapse; margin-top: 0; }
        .subtotals td {
            padding: 6px 10px;
            font-size: 10px;
            border: 1px solid #cbd5e1;
        }
        .subtotals .lbl { text-align: right; background: #f8fafc; font-weight: 600; }
        .subtotals .val { text-align: right; font-weight: 700; width: 40%; }
        .subtotals tr.ttc td {
            background: #1a3a5c;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            padding: 9px 10px;
        }
        .subtotals tr.ttc td.val { font-size: 13px; }

        /* ═══ SECTIONS TAXES / SERVICES ═══ */
        .section-tbl { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1px solid #cbd5e1; }
        .section-tbl thead th {
            background: #E8A020;
            color: #ffffff;
            font-size: 9.5px;
            font-weight: 700;
            padding: 6px 8px;
            text-align: left;
            border-right: 1px solid #d18d18;
        }
        .section-tbl thead th:last-child { border-right: none; }
        .section-tbl thead th.num { text-align: right; }
        .section-tbl tbody td {
            padding: 6px 8px;
            border-top: 1px solid #e8e8e8;
            font-size: 9.5px;
        }
        .section-tbl tbody td.num { text-align: right; }
        .section-tbl tfoot td {
            padding: 7px 10px;
            font-weight: 700;
            font-size: 10px;
            background: #f8fafc;
            text-align: right;
            border-top: 2px solid #1a3a5c;
        }
        .section-tbl tfoot td.num { color: #1a3a5c; }

        /* Services : pas de sous-header orange (juste le titre bleu) */
        .section-tbl.no-thead-color thead th { background: transparent; color: #333; padding: 4px 8px; }

        /* ═══ MONTANT NET TOTAL À PAYER ═══ */
        .montant-net {
            margin-top: 14px;
            border: 2px solid #E8A020;
            border-left: 6px solid #E8A020;
            padding: 12px 16px;
            width: 100%;
            border-collapse: collapse;
        }
        .montant-net td { padding: 0; vertical-align: middle; }
        .montant-net .lbl-net {
            font-size: 14px;
            font-weight: 900;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .montant-net .val-net {
            text-align: right;
            font-size: 20px;
            font-weight: 900;
            color: #E8A020;
        }
        .montant-lettres {
            padding: 6px 14px;
            font-style: italic;
            font-size: 9px;
            color: #555;
            border: 1px solid #e0e0e0;
            border-top: none;
        }

        /* ═══ MODALITÉS DE RÈGLEMENT ═══ */
        .modalites { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1px solid #cbd5e1; }
        .modalites td { padding: 6px 10px; font-size: 9.5px; border-top: 1px solid #e8e8e8; }
        .modalites td.lbl { background: #f8fafc; font-weight: 700; width: 32%; color: #1a1a1a; }
        .modalites .cb {
            display: inline-block;
            width: 10px; height: 10px;
            border: 1.2px solid #1a1a1a;
            margin-right: 4px;
            vertical-align: middle;
        }
        .bon-accord {
            background: #1a3a5c;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .8px;
            text-align: center;
            text-transform: uppercase;
        }

        /* ═══ SIGNATURES (page 2) ═══ */
        .sig-title-block {
            text-align: center;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        .sig-title-block .st-title { font-size: 18px; font-weight: 800; color: #1a3a5c; letter-spacing: 2px; }
        .sig-title-block .st-sub   { font-size: 11px; color: #E8A020; margin-top: 4px; font-weight: 700; }
        .sig-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .sig-table td {
            width: 50%;
            border: 1px solid #cbd5e1;
            padding: 16px 18px;
            vertical-align: top;
            height: 180px;
            background: #f8fafc;
        }
        .sig-table .sig-label {
            font-size: 11px;
            font-weight: 800;
            color: #1a3a5c;
            text-align: center;
        }
        .sig-table .sig-role {
            font-size: 10px;
            color: #555;
            text-align: center;
            margin-top: 4px;
        }
        .sig-table .sig-line {
            border-bottom: 1px solid #333;
            width: 70%;
            margin: 100px auto 0 auto;
        }

        /* ═══ CGV (page 3) ═══ */
        .cgv-header {
            background: #1a3a5c;
            color: #ffffff;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .cgv-header .site {
            float: right;
            font-size: 10px;
            font-weight: 500;
            color: #E8A020;
            text-transform: none;
            letter-spacing: 0;
        }
        .cgv-art-title {
            background: #E8A020;
            color: #ffffff;
            padding: 5px 10px;
            font-size: 10px;
            font-weight: 800;
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .cgv-body {
            padding: 7px 12px;
            font-size: 9.5px;
            line-height: 1.55;
            color: #1a1a1a;
            border: 1px solid #e8e8e8;
            border-top: none;
        }
        .cgv-body ul { margin: 4px 0 4px 20px; }
        .cgv-body li { margin: 2px 0; }
        .cgv-body .hi { font-weight: 700; }

        /* ═══ FOOTER commun toutes pages ═══ */
        .footer-legal {
            position: fixed;
            bottom: 6mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5px;
            color: #555;
            padding-top: 4px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>

    {{-- ═══════════════════════════════════════════════════════════════
         PAGE 1
    ═══════════════════════════════════════════════════════════════ --}}

    {{-- HEADER logo + bandeau ORANGE --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}">
                @else
                    <div style="font-size:22px;font-weight:900;color:#1a3a5c">{{ $company['name'] }}</div>
                @endif
            </td>
            <td class="header-right">
                <div class="title">DEVIS</div>
                <div class="meta">
                    <span class="lbl">N° :</span> <span class="val">{{ $quote->reference }}</span><br>
                    <span class="lbl">Date :</span> <span class="val">{{ $dateEmission }}</span>
                </div>
            </td>
        </tr>
    </table>

    {{-- ÉMETTEUR / CLIENT --}}
    <table class="party-table">
        <tr>
            <td>
                <div class="section-header">ÉMETTEUR</div>
                <div class="party-body">
                    <div class="row"><strong>{{ $company['name'] ?? 'CIBLE SARL' }}</strong></div>
                    @if(!empty($company['address']))
                        <div class="row">{{ $company['address'] }}</div>
                    @endif
                    @if(!empty($company['phone']))
                        <div class="row">Tél. : {{ $company['phone'] }}</div>
                    @endif
                    @if(!empty($company['ncc']))
                        <div class="row">CC : N°{{ $company['ncc'] }}</div>
                    @endif
                    @if(!empty($company['email']))
                        <div class="row">{{ $company['email'] }}</div>
                    @endif
                </div>
            </td>
            <td>
                <div class="section-header">CLIENT / MANDATAIRE</div>
                <div class="party-body">
                    <div class="row"><span class="lbl">Nom :</span>{{ $quote->client?->contact_name ?? $quote->client?->name ?? '—' }}</div>
                    <div class="row"><span class="lbl">Entreprise :</span>{{ $quote->client?->company ?? $quote->client?->name ?? '—' }}</div>
                    <div class="row"><span class="lbl">N°CC :</span>{{ $quote->client?->ncc ?? '' }}</div>
                    <div class="row"><span class="lbl">Tél. :</span>{{ $quote->client?->phone ?? '' }}</div>
                    <div class="row"><span class="lbl">Email :</span>@if($quote->client?->email)<span class="link">{{ $quote->client->email }}</span>@endif</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- TABLEAU LIGNES PANNEAUX --}}
    <table class="panneaux-table">
        <thead>
            <tr>
                <th class="left" style="width:35%">DÉSIGNATION / EMPLACEMENT</th>
                <th style="width:8%">Format<br>m²</th>
                <th style="width:12%">Commune</th>
                <th style="width:5%">Qté</th>
                <th style="width:8%">Durée<br>(mois)</th>
                <th style="width:13%">PU HT<br>(FCFA)</th>
                <th style="width:13%">Montant HT<br>(FCFA)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->lines as $line)
                @php
                    $panel  = $line->panel_id ? \App\Models\Panel::find($line->panel_id) : null;
                    $refPan = $panel?->reference ?? '';
                    $addr   = $panel?->adresse ?: $panel?->quartier ?: $line->designation;
                    $periodeLine = ($quote->period_start && $quote->period_end)
                        ? 'Du ' . $quote->period_start->translatedFormat('j F Y') . ' au ' . $quote->period_end->translatedFormat('j F Y')
                        : null;
                @endphp
                <tr>
                    <td class="designation-cell">
                        <span class="row-desc"><strong>Emplacement :</strong> {{ $addr ?: $line->snapshot_commune_name ?? '—' }}</span>
                        @if($periodeLine)
                            <span class="row-desc"><strong>Période :</strong> {{ $periodeLine }}</span>
                        @endif
                        @if($refPan)
                            <span class="row-desc"><strong>Code :</strong> {{ $refPan }}</span>
                        @endif
                    </td>
                    <td>{{ (int) $line->dimension_m2 }}</td>
                    <td>{{ $line->snapshot_commune_name ?: '—' }}</td>
                    <td>{{ (int) $line->quantite }}</td>
                    <td>{{ rtrim(rtrim(number_format((float) $line->duree_mois, 1, ',', ''), '0'), ',') }}</td>
                    <td class="num">{{ $fmt($line->pu_ht_mensuel) }}</td>
                    <td class="montant-ht-col">{{ $fmt($line->montant_ht_ligne) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- SOUS-TOTAUX --}}
    <table class="subtotals">
        <tr>
            <td class="lbl">Montant Total HT</td>
            <td class="val">{{ $fmt($netHt) }} FCFA</td>
        </tr>
        <tr>
            <td class="lbl"><strong>Total HT</strong></td>
            <td class="val"><strong>{{ $fmt($netHt) }} FCFA</strong></td>
        </tr>
        <tr>
            <td class="lbl">TVA ({{ $tvaRate }}%)</td>
            <td class="val">{{ $fmt($tvaAmount) }} FCFA</td>
        </tr>
        <tr>
            <td class="lbl">TSP ({{ $tspRate }}%)</td>
            <td class="val">{{ $fmt($tspAmount) }} FCFA</td>
        </tr>
        <tr class="ttc">
            <td class="lbl">MONTANT TOTAL TTC</td>
            <td class="val">{{ $fmt($totalTtc) }} FCFA</td>
        </tr>
    </table>

    {{-- TAXES PUBLICITAIRES --}}
    <div class="section-header" style="margin-top:12px">Taxes publicitaires</div>
    <table class="section-tbl no-break" style="margin-top:0;border-top:none">
        <thead>
            <tr>
                <th style="width:30%">Type</th>
                <th style="width:20%">Commune</th>
                <th class="num" style="width:25%">PU/m²/mois</th>
                <th class="num" style="width:25%">Montant</th>
            </tr>
        </thead>
        <tbody>
            @forelse($taxesPub as $tax)
                <tr>
                    <td>{{ $tax['type'] }}</td>
                    <td>{{ $tax['commune'] }}</td>
                    <td class="num">{{ $fmt($tax['rate']) }}</td>
                    <td class="num">{{ $fmt($tax['montant']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#94a3b8;font-style:italic">Aucune taxe publicitaire applicable</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total Taxes Publicitaires</td>
                <td class="num">{{ $fmt($totalTaxesPub) }} FCFA</td>
            </tr>
        </tfoot>
    </table>

    {{-- SERVICES ADDITIONNELS --}}
    <div class="section-header" style="margin-top:12px">Services additionnels</div>
    <table class="section-tbl no-thead-color no-break" style="margin-top:0;border-top:none">
        <tbody>
            @if($quote->services->isEmpty())
                <tr>
                    <td style="width:35%">Frais d'impression</td>
                    <td style="width:30%"></td>
                    <td class="num" style="width:20%">0 FCFA</td>
                    <td class="num" style="width:15%">0 FCFA</td>
                </tr>
                <tr>
                    <td>Frais de pose et dépose</td>
                    <td></td>
                    <td class="num">OFFERT</td>
                    <td class="num">OFFERT</td>
                </tr>
            @else
                @foreach($quote->services as $svc)
                    <tr>
                        <td style="width:35%">{{ $svc->label }}</td>
                        <td style="width:30%"></td>
                        <td class="num" style="width:20%">{{ $svc->prix_ht > 0 ? $fmt($svc->prix_ht) . ' FCFA' : 'OFFERT' }}</td>
                        <td class="num" style="width:15%">{{ $svc->prix_ht > 0 ? $fmt($svc->prix_ht) . ' FCFA' : 'OFFERT' }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">Total Services Additionnels</td>
                <td class="num">{{ $fmt($servicesHtTot) }} FCFA</td>
            </tr>
        </tfoot>
    </table>

    {{-- MONTANT NET TOTAL À PAYER --}}
    <table class="montant-net no-break">
        <tr>
            <td class="lbl-net">Montant Net Total à Payer</td>
            <td class="val-net">{{ $fmt($totalAPayer) }} FCFA</td>
        </tr>
    </table>
    <div class="montant-lettres">
        Arrêté le présent devis à la somme de : <strong>{{ ucfirst($montantEnLettres($totalAPayer)) }} francs CFA</strong>
    </div>

    {{-- MODALITÉS DE RÈGLEMENT --}}
    <div class="section-header" style="margin-top:12px">Modalités de règlement</div>
    <table class="modalites no-break" style="margin-top:0;border-top:none">
        <tr>
            <td class="lbl">Moyens de paiement :</td>
            <td>Espèces · Chèque · Virement bancaire</td>
        </tr>
        <tr>
            <td class="lbl">Conditions de paiement :</td>
            <td>
                <span class="cb"></span> 100% à la commande
                &nbsp;&nbsp;&nbsp;
                <span class="cb"></span> 70% à la commande + 30% à J-15 avant fin de campagne
            </td>
        </tr>
        <tr>
            <td class="lbl">Libellé du chèque :</td>
            <td>{{ $company['name'] ?? 'CIBLE SARL' }}</td>
        </tr>
        <tr>
            <td class="lbl">Banque / BIC :</td>
            <td>{{ $bank['name'] ?? '' }}@if(!empty($bank['swift'])) &nbsp;·&nbsp; BIC : {{ $bank['swift'] }}@endif</td>
        </tr>
        <tr>
            <td class="lbl">IBAN :</td>
            <td>{{ $bank['iban'] ?? '' }}</td>
        </tr>
    </table>
    <div class="bon-accord">BON POUR ACCORD — Lu et approuvé</div>

    <div class="page-break"></div>

    {{-- ═══════════════════════════════════════════════════════════════
         PAGE 2 — SIGNATURES
    ═══════════════════════════════════════════════════════════════ --}}
    <table class="header-table">
        <tr>
            <td class="header-left">
                @if($logoSrc)<img src="{{ $logoSrc }}" alt="{{ $company['name'] }}">@endif
            </td>
            <td class="header-right">
                <div class="title">DEVIS</div>
                <div class="meta">
                    <span class="lbl">N° :</span> <span class="val">{{ $quote->reference }}</span><br>
                    <span class="lbl">Date :</span> <span class="val">{{ $dateEmission }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="sig-title-block">
        <div class="st-title">SIGNATURES</div>
        <div class="st-sub">Devis : {{ $quote->reference }}</div>
    </div>

    <table class="sig-table">
        <tr>
            <td>
                <div class="sig-label">Pour {{ $company['name'] ?? 'CIBLE SARL' }}</div>
                <div class="sig-role">Directrice Commerciale</div>
                <div class="sig-line"></div>
            </td>
            <td>
                <div class="sig-label">Pour l'Annonceur / Mandataire</div>
                <div class="sig-role">Nom &amp; Fonction :</div>
                <div class="sig-line"></div>
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    {{-- ═══════════════════════════════════════════════════════════════
         PAGE 3 — CONDITIONS GÉNÉRALES
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="cgv-header">
        {{ $company['name'] ?? 'CIBLE SARL' }} — Conditions générales
        <span class="site">www.cible-ci.com</span>
    </div>

    <div class="cgv-art-title">1. Validité du devis</div>
    <div class="cgv-body">
        Le présent devis est valable <strong>{{ $quote->valid_days ?? 30 }} jours calendaires</strong>
        à compter de sa date d'émission, sous réserve de la disponibilité des emplacements à la date
        de signature des deux parties. Passé ce délai, {{ $company['name'] ?? 'CIBLE SARL' }} se
        réserve le droit de modifier les tarifs et/ou les disponibilités sans préavis.
        <span class="hi">La commande n'est confirmée qu'après réception du présent devis signé,
        cacheté par l'annonceur/mandataire, et accompagné du règlement correspondant aux conditions
        définies ci-après.</span>
    </div>

    <div class="cgv-art-title">2. Conditions de paiement</div>
    <div class="cgv-body">
        Sauf conditions particulières expressément mentionnées sur ce devis, le règlement s'effectue
        selon l'une des modalités suivantes :
        <ul>
            <li><strong>Option A</strong> — 100 % du montant TTC à la signature du devis / bon de commande (paiement intégral à la commande) ;</li>
            <li><strong>Option B</strong> — 70 % du montant TTC à la signature, solde de 30 % au plus tard quinze (15) jours avant la date de fin de campagne.</li>
        </ul>
        La modalité applicable est cochée sur la page 1. {{ $company['name'] ?? 'CIBLE SARL' }} se
        réserve le droit de suspendre toute prestation en cas de non-respect des délais de paiement,
        sans que cela ne constitue un manquement contractuel de sa part.
    </div>

    <div class="cgv-art-title">3. Annulation, report et non-remboursement</div>
    <div class="cgv-body">
        Tout acompte ou paiement versé est <strong>NON REMBOURSABLE</strong> en cas d'annulation ou
        de report de campagne, quelle qu'en soit la cause, à l'exception des cas de force majeure
        dûment reconnus (voir article 4).<br>
        En cas d'annulation confirmée par écrit, les retenues suivantes s'appliquent sur le montant total TTC :
        <ul>
            <li>Annulation <strong>avant J-30</strong> du démarrage → retenue de <strong>30 %</strong></li>
            <li>Annulation <strong>entre J-30 et J-15</strong> du démarrage → retenue de <strong>50 %</strong></li>
            <li>Annulation <strong>à moins de J-15</strong> ou en cours → retenue de <strong>100 %</strong></li>
        </ul>
        Un <strong>AVOIR</strong> sera émis pour le montant versé diminué de la retenue applicable.
        Cet avoir est valable à compter de sa date d'émission sur l'exercice comptable en cours,
        utilisable exclusivement pour toute prestation {{ $company['name'] ?? 'CIBLE SARL' }},
        non remboursable et non cessible.
    </div>

    <div class="cgv-art-title">4. Cas de force majeure</div>
    <div class="cgv-body">
        Sont considérés comme cas de force majeure tout événement imprévisible, irrésistible et
        extérieur aux parties : catastrophe naturelle, acte de guerre, émeute, décision
        gouvernementale rendant impossible l'exécution des prestations. En cas de force majeure dûment
        prouvée et notifiée par écrit dans les 72 heures, {{ $company['name'] ?? 'CIBLE SARL' }}
        émettra un avoir sans retenue dans les conditions de l'article 3.
    </div>

    <div class="cgv-art-title">5. Production et livraison des visuels</div>
    <div class="cgv-body">
        Les frais d'impression, de pose et de dépose des visuels sont à la charge de
        l'annonceur/mandataire, sauf mention contraire expressément indiquée sur ce devis. Les visuels
        doivent être livrés en haute définition au moins cinq (5) jours ouvrables avant le démarrage
        de la campagne. Tout retard de livraison imputable à l'annonceur ne donnera droit à aucun
        report, prolongation, ni remboursement.
    </div>

    <div class="cgv-art-title">6. Taxes, impôts et redevances</div>
    <div class="cgv-body">
        Tous les montants sont exprimés en FCFA Hors Taxes. La <strong>TVA ({{ $tvaRate }} %)</strong>
        et la <strong>TSP ({{ $tspRate }} %)</strong> sont appliquées conformément à la législation
        fiscale de la République de Côte d'Ivoire. Les taxes publicitaires municipales
        (<strong>ODP</strong> et <strong>Taxe Municipale</strong>) sont facturées séparément selon la
        commune d'implantation du panneau. Toute modification législative entraînant une variation
        de ces taux sera répercutée de plein droit sur les montants facturés.
    </div>

    <div class="cgv-art-title">7. Droits de timbre</div>
    <div class="cgv-body">
        Pour tout règlement en espèces, des droits de timbre s'appliquent conformément à la
        réglementation fiscale ivoirienne :
        De 0 à 5 000 FCFA : 0 FCFA · De 5 001 à 100 000 FCFA : 100 FCFA ·
        De 100 001 à 500 000 FCFA : 500 FCFA · De 500 001 à 1 000 000 FCFA : 1 000 FCFA ·
        De 1 000 001 à 5 000 000 FCFA : 2 000 FCFA · Au-delà de 5 000 000 FCFA : 5 000 FCFA.
    </div>

    <div class="cgv-art-title">8. Droit applicable et règlement des litiges</div>
    <div class="cgv-body">
        Le présent devis/contrat est régi par le <strong>droit ivoirien</strong>. En cas de litige
        relatif à son interprétation, son exécution ou sa résiliation, les parties s'engagent à
        rechercher une solution amiable dans les trente (30) jours suivant la notification écrite du
        différend. À défaut d'accord amiable dans ce délai, tout litige sera soumis à la
        <strong>compétence exclusive du Tribunal de Commerce d'Abidjan</strong>.
    </div>

    <div class="bon-accord" style="margin-top:14px">BON POUR ACCORD — Lu et approuvé, bon pour accord</div>

    <table class="sig-table" style="margin-top:10px">
        <tr>
            <td style="height:110px">
                <div class="sig-label">Pour {{ $company['name'] ?? 'CIBLE SARL' }}</div>
                <div class="sig-role">Directrice Commerciale</div>
                <div class="sig-line" style="margin-top:60px"></div>
            </td>
            <td style="height:110px">
                <div class="sig-label">Pour l'Annonceur / Mandataire</div>
                <div class="sig-role">Nom &amp; Fonction :</div>
                <div class="sig-line" style="margin-top:60px"></div>
            </td>
        </tr>
    </table>

    {{-- ═══════════════════════════════════════════════════════════════
         FOOTER LÉGAL (fixed, apparaît sur toutes les pages)
    ═══════════════════════════════════════════════════════════════ --}}
    <div class="footer-legal">
        {{ $company['name'] ?? 'CIBLE SARL' }} — Capital : 10 000 000 FCFA
        · {{ $company['address'] ?? '' }}
        · Tél. {{ $company['phone'] ?? '' }}
        · {{ $company['email'] ?? '' }}
    </div>

    {{-- Numérotation "Page X sur Y" (DomPDF spécifique) --}}
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(
                520, 815,
                "Page {PAGE_NUM} sur {PAGE_COUNT}",
                null, 8, [100, 100, 100]
            );
        }
    </script>
</body>
</html>

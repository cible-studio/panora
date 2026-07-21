@php
    /**
     * PDF Devis — calqué sur le PDF facture FNE mais adapté au contexte
     * devis (non contractuel) : bandeau clair « DEVIS » en haut,
     * suppression du badge FNE, mention légale spécifique, encart
     * validité mis en évidence.
     */
    $company = config('billing.company');
    $legalMention = config('billing.quote_legal_mention');

    // Emplacement (concat communes uniques)
    $emplacement = $quote->lines->pluck('snapshot_commune_name')->filter()->unique()->take(4)->implode(' · ') ?: '—';
    $nbPanneaux  = $quote->lines->sum('quantite') ?: 0;

    // Période
    $periode = ($quote->period_start && $quote->period_end)
        ? strtoupper('DU ' . $quote->period_start->format('d/m/Y') . ' AU ' . $quote->period_end->format('d/m/Y'))
        : '—';

    // Logo émetteur (base64 pour DomPDF)
    $logoPath = public_path($company['logo_path'] ?? 'images/logol.png');
    $logoSrc  = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    // Formatage entier FCFA
    $fmt = fn($n) => number_format((int) $n, 0, ',', ' ');

    // Ventilations et taxes
    $netHt         = (int) $quote->net_ht;
    $totalTtc      = (int) $quote->amount_ttc;
    $tvaAmount     = (int) $quote->tva_amount;
    $servicesHtTot = (int) $quote->services_ht_total;
    $autresTaxes   = (int) $quote->tsp_amount + (int) $quote->tm_total + (int) $quote->odp_total;

    // Base résumé ODP+TM+TSP = TTC panneaux + TTC services
    $autresBase = $totalTtc + (int) round($servicesHtTot * (1 + ((float) $quote->tva) / 100));
    $autresRate = $autresBase > 0 ? round(($autresTaxes / $autresBase) * 100, 5) : 0;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Devis N° {{ $quote->reference }}</title>
    <style>
        @page { margin: 10mm 12mm 10mm 12mm; size: A4 portrait; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Helvetica, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.42; }
        table { border-collapse: collapse; width: 100%; }
        td, th { vertical-align: top; }
        strong { font-weight: 700; }

        /* Bandeau DEVIS haut de page — remplace le badge FNE de la facture */
        .devis-banner {
            background: #8b5cf6;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 2px;
            text-align: center;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .devis-banner .sub { font-weight: 500; letter-spacing: 0.5px; opacity: 0.9; font-size: 9.5px; margin-top: 2px; }

        .header td { padding: 0; }
        .header .left  { width: 54%; padding-right: 12px; }
        .header .right { width: 46%; text-align: right; }
        .header { margin-bottom: 14px; }

        .emit-box { border: 1.2px solid #94a3b8; border-radius: 10px; padding: 10px 14px; }
        .emit-box .co-name { font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .emit-box .co-line { font-size: 10px; color: #1e293b; line-height: 1.55; }

        .header .right .brand-strip img { height: 44px; max-width: 200px; margin-bottom: 8px; }
        .header .right .fac-num { font-size: 12.5px; font-weight: 800; color: #8b5cf6; margin-bottom: 8px; }
        .header .right .validity {
            display: inline-block;
            background: #fef3c7;
            border: 1px solid #f59e0b;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 10.5px;
            font-weight: 700;
            color: #78350f;
        }
        .header .right .validity small { font-weight: 500; font-size: 9px; color: #92400e; display: block; }

        /* Infos vendeur / client */
        .infos { margin-bottom: 14px; }
        .infos td { padding: 0; }
        .infos .vendor { width: 58%; padding-right: 20px; }
        .infos .client { width: 42%; }
        .infos .row { font-size: 10px; padding: 1px 0; line-height: 1.55; }
        .infos .row .lbl { font-weight: 600; color: #0f172a; }
        .infos .client-title { font-size: 13px; font-weight: 800; color: #0f172a; margin-bottom: 4px; }
        .infos .highlight { font-weight: 700; color: #0f172a; }

        /* Lignes */
        .lines { margin-top: 4px; border: 1px solid #cbd5e1; }
        .lines thead th { background: #8b5cf6; color: #fff; font-size: 9.5px; font-weight: 700; text-align: left; padding: 6px 8px; border-right: 1px solid #a78bfa; }
        .lines thead th:last-child { border-right: none; }
        .lines thead th.num { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #f1f5f9; font-size: 9.5px; }
        .lines tbody tr:last-child td { border-bottom: none; }
        .lines tbody td:last-child { border-right: none; }
        .lines tbody td.num { text-align: right; }
        .lines tbody td.center { text-align: center; }
        .lines tbody td.ref { font-family: 'DejaVu Sans Mono', monospace; font-weight: 700; color: #6d28d9; }

        /* Totaux */
        .totals-wrap { margin-top: -1px; }
        .totals-wrap table { width: 55%; margin-left: 45%; border-collapse: collapse; }
        .totals-wrap td { padding: 6px 10px; font-size: 10px; border: 1px solid #cbd5e1; }
        .totals-wrap .lbl-tot { text-align: left; font-weight: 600; background: #f8fafc; }
        .totals-wrap .val-tot { text-align: right; font-weight: 700; }
        .totals-wrap tr.grand td {
            background: #8b5cf6;
            color: #ffffff;
            font-size: 12px;
            padding: 9px 10px;
        }

        /* Résumé */
        .resume-title { font-size: 10px; font-weight: 800; margin: 12px 0 5px; color: #0f172a; letter-spacing: 0.5px; text-transform: uppercase; }
        .resume { border: 1px solid #cbd5e1; }
        .resume thead th { background: #8b5cf6; color: #fff; font-size: 9.5px; font-weight: 700; text-align: left; padding: 6px 8px; text-transform: uppercase; }
        .resume thead th.num { text-align: right; }
        .resume tbody td { padding: 6px 8px; font-size: 9.5px; border-bottom: 1px solid #e2e8f0; }
        .resume tbody tr:last-child td { border-bottom: none; }
        .resume tbody td.num { text-align: right; }

        .ventil { margin-top: 6px; text-align: center; font-size: 9.5px; line-height: 1.5; }

        .footer { margin-top: 10px; padding-top: 5px; border-top: 1px solid #e2e8f0; font-size: 8px; color: #64748b; text-align: center; line-height: 1.35; }
        .footer .mention { color: #78350f; font-weight: 700; margin-bottom: 3px; }
    </style>
</head>
<body>

    {{-- Bandeau DEVIS --}}
    <div class="devis-banner">
        DEVIS COMMERCIAL
        <div class="sub">Non contractuel avant signature — les panneaux ne sont pas bloqués</div>
    </div>

    {{-- Header --}}
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
                    @if($logoSrc) <img src="{{ $logoSrc }}" alt="{{ $company['name'] }}"> @endif
                </div>
                <div class="fac-num">
                    Devis N° {{ $quote->reference }}
                    @if($quote->version > 1) <span style="color:#94a3b8;font-size:10px">(v{{ $quote->version }})</span> @endif
                </div>
                @if($quote->expires_at)
                    <div class="validity">
                        Validité : {{ $quote->valid_days }} jours
                        <small>Jusqu'au {{ $quote->expires_at->format('d/m/Y') }}</small>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Bandeau vendeur / client --}}
    <table class="infos">
        <tr>
            <td class="vendor">
                @if(!empty($company['rccm'])) <div class="row"><span class="lbl">RCCM :</span> {{ $company['rccm'] }}</div> @endif
                <div class="row"><span class="lbl">Établissement :</span> {{ $company['name'] }}</div>
                @if(!empty($company['address'])) <div class="row"><span class="lbl">Adresse :</span> {{ $company['address'] }}</div> @endif
                @if(!empty($company['phone'])) <div class="row"><span class="lbl">N° Tel :</span> {{ $company['phone'] }}</div> @endif
                @if(!empty($company['email'])) <div class="row"><span class="lbl">Mail :</span> {{ $company['email'] }}</div> @endif
                <div class="row"><span class="lbl">Commercial :</span> {{ $quote->commercial?->name ?? '—' }}</div>
                <div class="row"><span class="lbl">Date d'émission :</span> {{ optional($quote->sent_at ?: $quote->created_at)->format('d/m/Y') }}</div>
                @if($periode !== '—')
                    <div class="row highlight" style="margin-top: 6px;">PÉRIODE ENVISAGÉE : {{ $periode }}</div>
                @endif
                <div class="row highlight">EMPLACEMENT : {{ $emplacement }}</div>
                @if($nbPanneaux > 0)
                    <div class="row highlight">Nbre de panneaux : {{ str_pad($nbPanneaux, 2, '0', STR_PAD_LEFT) }}</div>
                @endif
            </td>
            <td class="client">
                <div class="client-title">Destiné à</div>
                <div class="row"><span class="lbl">Nom :</span> {{ $quote->client?->name ?? '—' }}</div>
                @if(!empty($quote->client?->email))     <div class="row"><span class="lbl">Email :</span> {{ $quote->client->email }}</div> @endif
                @if(!empty($quote->client?->address))   <div class="row"><span class="lbl">Adresse :</span> {{ $quote->client->address }}</div> @endif
                @if(!empty($quote->client?->phone))     <div class="row"><span class="lbl">Téléphone :</span> {{ $quote->client->phone }}</div> @endif
                @if(!empty($quote->client?->ncc))       <div class="row"><span class="lbl">NCC :</span> {{ $quote->client->ncc }}</div> @endif
                @if(!empty($quote->client?->regime_imposition))
                    <div class="row"><span class="lbl">Régime d'imposition :</span> {{ $quote->client->regime_imposition }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Titre du devis --}}
    <div style="background:#f8fafc;border-left:4px solid #8b5cf6;padding:8px 12px;margin-bottom:12px;border-radius:0 4px 4px 0">
        <strong style="font-size:12px;color:#0f172a">Objet :</strong> {{ $quote->title }}
    </div>

    {{-- Lignes --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width:8%">Réf</th>
                <th style="width:32%">Désignation</th>
                <th style="width:12%" class="num">P.U HT</th>
                <th style="width:6%" class="center">Qté</th>
                <th style="width:6%" class="center">Unité</th>
                <th style="width:11%" class="center">Taxes (%)</th>
                <th style="width:8%" class="num">Rem. (%)</th>
                <th style="width:17%" class="num">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quote->lines as $line)
                <tr>
                    <td class="ref">LP</td>
                    <td>{{ $line->designation }}</td>
                    <td class="num">{{ $fmt($line->pu_ht_mensuel) }}</td>
                    <td class="center">{{ $line->quantite }}</td>
                    <td class="center">m²</td>
                    <td class="center">TVA ({{ (int) $quote->tva }})</td>
                    <td class="num">{{ $quote->remise_pct > 0 ? number_format($quote->remise_pct, 2, ',', '') : '0' }}</td>
                    <td class="num">{{ $fmt($line->montant_ht_ligne) }}</td>
                </tr>
            @endforeach
            @foreach($quote->services as $svc)
                @php
                    $ref = str_contains(mb_strtolower($svc->label), 'impression') ? 'IMP'
                         : (str_contains(mb_strtolower($svc->label), 'pose') ? 'FP' : 'SRV');
                @endphp
                <tr>
                    <td class="ref">{{ $ref }}</td>
                    <td>{{ $svc->label }}</td>
                    <td class="num">{{ $fmt($svc->prix_ht) }}</td>
                    <td class="center">1</td>
                    <td class="center">—</td>
                    <td class="center">TVA ({{ (int) $quote->tva }})</td>
                    <td class="num">0</td>
                    <td class="num">{{ $fmt($svc->prix_ht) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totaux --}}
    <div class="totals-wrap">
        <table>
            @php $htBrut = (int) $quote->amount; $remiseMontant = $htBrut - $netHt; @endphp
            <tr><td class="lbl-tot">TOTAL HT</td><td class="val-tot">{{ $fmt($htBrut) }}</td></tr>
            @if($remiseMontant > 0)
                <tr><td class="lbl-tot">REMISE ({{ number_format($quote->remise_pct, 2, ',', '') }} %)</td><td class="val-tot">- {{ $fmt($remiseMontant) }}</td></tr>
                <tr><td class="lbl-tot">TOTAL HT APRÈS REMISE</td><td class="val-tot">{{ $fmt($netHt) }}</td></tr>
            @endif
            @if($servicesHtTot > 0)
                <tr><td class="lbl-tot">Services annexes HT</td><td class="val-tot">{{ $fmt($servicesHtTot) }}</td></tr>
            @endif
            <tr><td class="lbl-tot">TVA</td><td class="val-tot">{{ $fmt($tvaAmount + (int) round($servicesHtTot * ((float) $quote->tva / 100))) }}</td></tr>
            <tr><td class="lbl-tot">TOTAL TTC</td><td class="val-tot">{{ $fmt($totalTtc + (int) round($servicesHtTot * (1 + (float) $quote->tva / 100))) }}</td></tr>
            <tr><td class="lbl-tot">AUTRES TAXES (ODP+TM+TSP)</td><td class="val-tot">{{ $fmt($autresTaxes) }}</td></tr>
            <tr class="grand"><td>TOTAL À PAYER</td><td class="val-tot">{{ $fmt($quote->total_a_payer) }}</td></tr>
        </table>
    </div>

    <div class="resume-title">RESUME DU DEVIS</div>
    <table class="resume">
        <thead>
            <tr>
                <th style="width:45%">CATEGORIE</th>
                <th class="num" style="width:22%">SOUS-TOTAL</th>
                <th class="num" style="width:15%">TAUX (%)</th>
                <th class="num" style="width:18%">TOTAL TAXES</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>TVA normal - TVA sur HT {{ number_format((float) $quote->tva, 2, ',', '') }}% - A</td>
                <td class="num">{{ $fmt($netHt + $servicesHtTot) }}</td>
                <td class="num">{{ (int) $quote->tva }}%</td>
                <td class="num">{{ $fmt($tvaAmount + (int) round($servicesHtTot * ((float) $quote->tva / 100))) }}</td>
            </tr>
            <tr>
                <td>ODP+TM+TSP</td>
                <td class="num">{{ $fmt($autresBase) }}</td>
                <td class="num">{{ number_format($autresRate, 5, '.', '') }}%</td>
                <td class="num">{{ $fmt($autresTaxes) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="ventil">
        <span>ODP = {{ number_format((int) $quote->odp_total, 0, ',', '.') }}</span> ·
        <span>TM = {{ number_format((int) $quote->tm_total, 0, ',', '.') }}</span> ·
        <span>TSP = {{ number_format((int) $quote->tsp_amount, 0, ',', '.') }}</span>
    </div>

    @if($quote->notes_client)
        <div style="margin-top:10px;padding:10px 12px;background:#f8fafc;border-left:3px solid #8b5cf6;border-radius:0 4px 4px 0;font-size:10px">
            <strong>Message : </strong>{{ $quote->notes_client }}
        </div>
    @endif

    {{-- Footer légal --}}
    <div class="footer">
        <div class="mention">⚠️ {{ $legalMention }}</div>
        <div>Devis émis par {{ $quote->commercial?->name ?? $company['name'] }} · Réf. {{ $quote->reference }} · {{ optional($quote->created_at)->format('d/m/Y') }}</div>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->isCreditNote() ? 'Avoir' : 'Facture' }} {{ $invoice->reference }}</title>
    <style>
        /* ════════════════════════════════════════════════════════════════
           PDF FACTURE FNE — Édition PREMIUM (Phase 8F final)

           Principes :
             1. Marges A4 généreuses (25mm latérales, 22mm haut/bas)
             2. Palette restreinte : doré CIBLE (#b45309), gris ardoise
                (#0f172a, #475569, #94a3b8) + blanc cassé (#fafbfc)
             3. Typographie hiérarchisée (4 tailles : 22/13/11/9)
             4. Compatibilité DomPDF : 100% tables, 0 flex, 0 gradient
             5. Page-break-inside avoid sur tous les blocs critiques
             6. Pagination N/M discrète en bas
        ════════════════════════════════════════════════════════════════ */

        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            margin: 22mm 22mm 26mm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            background: #fff;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── PAGE-BREAK CONTROL ─────────────── */
        .no-break    { page-break-inside: avoid; }
        .break-after { page-break-after:  always; }

        /* ════════════════════════════════════════
           HEADER : BANDE COULEUR + IDENTITÉ
        ════════════════════════════════════════ */
        .ribbon {
            height: 5px;
            background: #b45309;
            margin: -22mm -22mm 18px;
        }

        .head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }
        .head td { vertical-align: top; padding: 0; }

        .head .logo-cell { width: 52%; }
        .head .logo-cell img {
            height: 50px;
            margin-bottom: 6px;
        }
        .head .logo-cell .tagline {
            font-size: 8.5px;
            color: #94a3b8;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }

        .head .ref-cell {
            width: 48%;
            text-align: right;
        }
        .head .doc-type {
            font-size: 9px;
            font-weight: 700;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 4px;
        }
        .head .doc-num {
            font-family: 'DejaVu Sans Mono', monospace;
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .head .doc-date {
            font-size: 10px;
            color: #64748b;
            margin-bottom: 10px;
        }
        .head .doc-date strong { color: #334155; font-weight: 700; }
        .head .badge-status {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 99px;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ════════════════════════════════════════
           CARDS ÉMETTEUR / CLIENT
        ════════════════════════════════════════ */
        .parties {
            width: 100%;
            border-collapse: separate;
            border-spacing: 14px 0;
            margin-bottom: 18px;
        }
        .parties td {
            vertical-align: top;
            width: 50%;
            padding: 0;
        }
        .party {
            background: #fafbfc;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px 18px;
        }
        .party .lbl {
            font-size: 8.5px;
            font-weight: 800;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }
        .party .name {
            font-size: 14.5px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
            letter-spacing: -.2px;
        }
        .party .info {
            font-size: 10px;
            color: #475569;
            line-height: 1.8;
        }
        .party .info strong { color: #1e293b; font-weight: 700; }

        /* ════════════════════════════════════════
           BANNERS (Campagne / Avoir)
        ════════════════════════════════════════ */
        .strip-info {
            background: #fafbfc;
            border: 1px solid #e5e7eb;
            border-left: 4px solid #b45309;
            border-radius: 6px;
            padding: 11px 16px;
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 18px;
            line-height: 1.65;
        }
        .strip-info strong { color: #0f172a; font-weight: 700; }
        .strip-info .lbl {
            display: inline-block;
            font-weight: 800;
            color: #b45309;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 1px;
            margin-right: 6px;
        }

        .cn-banner {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-left: 4px solid #dc2626;
            border-radius: 6px;
            padding: 11px 16px;
            font-size: 10.5px;
            color: #991b1b;
            margin-bottom: 18px;
            line-height: 1.65;
        }
        .cn-banner strong { color: #7f1d1d; }

        /* ════════════════════════════════════════
           TABLE LIGNES — Premium
        ════════════════════════════════════════ */
        .lines-wrap {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 4px;
        }
        .lines {
            width: 100%;
            border-collapse: collapse;
        }
        .lines thead th {
            background: #0f172a;
            color: #fafafa;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            padding: 12px 12px;
            text-align: left;
            border-bottom: 3px solid #b45309;
        }
        .lines thead th.right  { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td {
            font-size: 10.5px;
            padding: 12px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
            color: #334155;
        }
        .lines tbody td.right {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            color: #0f172a;
        }
        .lines tbody td.center { text-align: center; color: #475569; }
        .lines tbody tr:nth-child(even) td { background: #fafbfc; }
        .lines tbody tr:last-child td { border-bottom: none; }
        .line-meta {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 4px;
            font-weight: 500;
        }
        .line-desig { font-weight: 600; color: #0f172a; }

        /* ════════════════════════════════════════
           VENTILATION FNE
        ════════════════════════════════════════ */
        .totals-block {
            width: 62%;
            margin-left: 38%;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        table.tt {
            width: 100%;
            border-collapse: collapse;
        }
        table.tt td {
            padding: 7px 14px;
            font-size: 10.5px;
            color: #475569;
        }
        table.tt td.lbl { text-align: left; }
        table.tt td.val {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }
        table.tt tr.sep td {
            border-top: 1px solid #e2e8f0;
            padding-top: 11px;
        }
        table.tt tr.strong td {
            font-weight: 800;
            font-size: 11.5px;
            color: #0f172a;
        }
        table.tt tr.neg td { color: #b45309; }

        table.tt tr.ttc td {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 12.5px;
            padding: 11px 14px;
            color: #0f172a;
            border-radius: 4px;
        }

        /* Encarts AUTRES TAXES / SERVICES */
        .box {
            width: 100%;
            border-collapse: collapse;
            background: #fafbfc;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin: 12px 0;
            page-break-inside: avoid;
        }
        .box td {
            padding: 7px 14px;
            font-size: 10px;
            color: #475569;
            vertical-align: top;
        }
        .box td.lbl { text-align: left; }
        .box td.val {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 700;
            white-space: nowrap;
            color: #0f172a;
        }
        .box .title td {
            font-size: 8.5px;
            font-weight: 800;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 10px 14px 8px;
            background: #fff;
            border-bottom: 1px solid #e5e7eb;
        }
        .box .subtotal td {
            font-size: 11px;
            font-weight: 800;
            color: #0f172a;
            border-top: 1px dashed #cbd5e1;
            padding: 10px 14px;
            background: #fff;
        }
        .box .subtotal td.val { color: #b45309; }

        /* TOTAL À PAYER — bandeau accent fort */
        .total-final {
            width: 100%;
            border-collapse: collapse;
            background: #0f172a;
            border-radius: 6px;
            margin-top: 14px;
            page-break-inside: avoid;
        }
        .total-final td {
            padding: 16px 18px;
            color: #fff;
            vertical-align: middle;
        }
        .total-final td.lbl {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1.5px;
            text-align: left;
            text-transform: uppercase;
            color: #fbbf24;
        }
        .total-final td.val {
            font-size: 22px;
            font-weight: 800;
            font-family: 'DejaVu Sans Mono', monospace;
            text-align: right;
            white-space: nowrap;
            color: #fff;
        }

        /* ════════════════════════════════════════
           SECTIONS COMPLÉMENTAIRES
        ════════════════════════════════════════ */
        .section-spacer { height: 24px; clear: both; }

        .section-card {
            border-radius: 8px;
            padding: 16px 18px;
            page-break-inside: avoid;
        }
        .section-card .sct-title {
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 12px;
        }
        .section-card table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .section-card th {
            text-align: left;
            font-weight: 800;
            padding: 8px 8px;
            border-bottom: 2px solid;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .section-card th.right { text-align: right; }
        .section-card td {
            padding: 9px 8px;
            border-bottom: 1px solid;
        }
        .section-card td.right {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            font-weight: 700;
        }

        /* Versements — verte */
        .pay-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
        }
        .pay-card .sct-title { color: #14532d; }
        .pay-card th { color: #166534; border-bottom-color: #86efac; }
        .pay-card td { color: #14532d; border-bottom-color: #d1fae5; }

        /* Balance */
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1.5px dashed #86efac;
        }
        .balance-table td {
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 800;
        }
        .balance-table td.lbl { text-align: left; color: #15803d; }
        .balance-table td.val {
            text-align: right;
            font-family: 'DejaVu Sans Mono', monospace;
            color: #15803d;
            white-space: nowrap;
        }
        .balance-table tr.due td { color: #b91c1c; }

        /* Échéancier — bleue */
        .sched-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
        }
        .sched-card .sct-title { color: #1e3a8a; }
        .sched-card th { color: #1e3a8a; border-bottom-color: #93c5fd; }
        .sched-card td { color: #1e40af; border-bottom-color: #dbeafe; }

        .pill {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .4px;
            text-transform: uppercase;
        }
        .pill-acompte { background: #fef3c7; color: #b45309; }
        .pill-paid    { background: #dcfce7; color: #15803d; }
        .pill-late    { background: #fee2e2; color: #b91c1c; }
        .pill-pending { background: #f1f5f9; color: #475569; }

        /* ════════════════════════════════════════
           FOOTER
        ════════════════════════════════════════ */
        .footer-wrap {
            clear: both;
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .footer-divider {
            height: 1px;
            background: #e2e8f0;
            margin-bottom: 14px;
        }
        .footer-info {
            font-size: 10px;
            color: #64748b;
            line-height: 1.75;
        }
        .footer-info strong { color: #334155; }
        .conditions {
            font-size: 10.5px;
            margin-bottom: 14px;
            line-height: 1.65;
        }
        .conditions .lbl {
            font-weight: 800;
            color: #b45309;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 1.2px;
            display: block;
            margin-bottom: 4px;
        }
        .bank-card {
            background: #fafbfc;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #b45309;
            border-radius: 6px;
            padding: 12px 16px;
            margin: 14px 0;
            font-size: 10px;
            color: #475569;
            line-height: 1.8;
            page-break-inside: avoid;
        }
        .bank-card .lbl {
            font-weight: 800;
            color: #b45309;
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 1.2px;
            display: block;
            margin-bottom: 6px;
        }
        .bank-card strong { color: #0f172a; font-weight: 700; }

        .footer-legal {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #94a3b8;
            font-style: italic;
            line-height: 1.65;
            text-align: justify;
        }

        .footer-meta {
            margin-top: 12px;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            letter-spacing: .5px;
        }
        .footer-meta strong { color: #475569; }

        /* Status badges */
        .st-brouillon     { background: #f1f5f9; color: #475569; }
        .st-generee       { background: #ede9fe; color: #6d28d9; }
        .st-validee       { background: #cffafe; color: #0e7490; }
        .st-envoyee       { background: #dbeafe; color: #1d4ed8; }
        .st-partielle     { background: #fef3c7; color: #b45309; }
        .st-payee         { background: #dcfce7; color: #15803d; }
        .st-retard        { background: #fee2e2; color: #b91c1c; }
        .st-litige        { background: #ffedd5; color: #9a3412; }
        .st-annulee       { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>

    {{-- Bande décorative en tête de page --}}
    <div class="ribbon"></div>

    @php
        $statusLabels = [
            'brouillon'           => ['cls' => 'st-brouillon', 'lbl' => 'Brouillon'],
            'generee'             => ['cls' => 'st-generee',   'lbl' => 'Générée'],
            'validee'             => ['cls' => 'st-validee',   'lbl' => 'Validée'],
            'envoyee'             => ['cls' => 'st-envoyee',   'lbl' => 'Envoyée'],
            'partiellement_payee' => ['cls' => 'st-partielle', 'lbl' => 'Partiellement payée'],
            'payee'               => ['cls' => 'st-payee',     'lbl' => 'Payée'],
            'en_retard'           => ['cls' => 'st-retard',    'lbl' => 'En retard'],
            'litige'              => ['cls' => 'st-litige',    'lbl' => 'Litige'],
            'annulee'             => ['cls' => 'st-annulee',   'lbl' => 'Annulée'],
        ];
        $st  = $statusLabels[$invoice->status] ?? null;
        $fmt = fn($v) => number_format((float) $v, 0, ',', ' ');
    @endphp

    {{-- ═══ HEADER ═══ --}}
    <table class="head no-break">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path(config('billing.company.logo_path', 'images/panora.png')) }}" alt="{{ config('billing.company.name') }}">
                <div class="tagline">Régie OOH · Plateforme Panora</div>
            </td>
            <td class="ref-cell">
                <div class="doc-type">{{ $invoice->isCreditNote() ? 'Avoir / Note de crédit' : 'Facture FNE' }}</div>
                <div class="doc-num">{{ $invoice->reference }}</div>
                <div class="doc-date">
                    Émise le <strong>{{ $invoice->issued_at->format('d F Y') }}</strong>
                </div>
                @if($st)
                    <span class="badge-status {{ $st['cls'] }}">{{ $st['lbl'] }}</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ═══ AVOIR BANNER ═══ --}}
    @if($invoice->isCreditNote() && $invoice->creditNoteFor)
        <div class="cn-banner no-break">
            <strong>Avoir / Note de crédit</strong> émis(e) sur la facture
            <strong>{{ $invoice->creditNoteFor->reference }}</strong>
            du {{ $invoice->creditNoteFor->issued_at->format('d/m/Y') }}.
        </div>
    @endif

    {{-- ═══ ÉMETTEUR + CLIENT ═══ --}}
    <table class="parties no-break">
        <tr>
            <td>
                <div class="party">
                    <div class="lbl">Émetteur</div>
                    <div class="name">{{ config('billing.company.name') }}</div>
                    <div class="info">
                        {{ config('billing.company.legal') }}<br>
                        {{ config('billing.company.address') }}<br>
                        @if(config('billing.company.phone')) <strong>Tél :</strong> {{ config('billing.company.phone') }}<br>@endif
                        @if(config('billing.company.email')) {{ config('billing.company.email') }}<br>@endif
                        @if(config('billing.company.rccm')) <strong>RCCM :</strong> {{ config('billing.company.rccm') }}<br>@endif
                        @if(config('billing.company.ifu')) <strong>IFU :</strong> {{ config('billing.company.ifu') }}<br>@endif
                        @if(config('billing.company.ncc')) <strong>NCC :</strong> {{ config('billing.company.ncc') }}@endif
                    </div>
                </div>
            </td>
            <td>
                <div class="party">
                    <div class="lbl">Facturé à</div>
                    <div class="name">{{ $invoice->client?->name ?? '—' }}</div>
                    <div class="info">
                        @if($invoice->client?->email) {{ $invoice->client->email }}<br>@endif
                        @if($invoice->client?->phone) <strong>Tél :</strong> {{ $invoice->client->phone }}<br>@endif
                        @if($invoice->client?->address) {{ $invoice->client->address }}<br>@endif
                        @if($invoice->client?->rccm) <strong>RCCM :</strong> {{ $invoice->client->rccm }}<br>@endif
                        @if($invoice->client?->ifu) <strong>IFU :</strong> {{ $invoice->client->ifu }}<br>@endif
                        @if($invoice->client?->ncc) <strong>NCC :</strong> {{ $invoice->client->ncc }}@endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ═══ CAMPAGNE ═══ --}}
    @if($invoice->campaign)
        <div class="strip-info no-break">
            <span class="lbl">Campagne</span>
            <strong>{{ $invoice->campaign->name }}</strong>
            @if($invoice->campaign->start_date && $invoice->campaign->end_date)
                <br><span class="lbl">Période</span>
                du <strong>{{ $invoice->campaign->start_date->format('d/m/Y') }}</strong>
                au <strong>{{ $invoice->campaign->end_date->format('d/m/Y') }}</strong>
            @endif
        </div>
    @endif

    {{-- ═══ LIGNES ═══ --}}
    @if($invoice->lines && $invoice->lines->isNotEmpty())
        <div class="lines-wrap">
            <table class="lines">
                <thead>
                    <tr>
                        <th style="width:40%">Désignation</th>
                        <th class="right" style="width:14%">PU HT</th>
                        <th class="center" style="width:8%">Qté</th>
                        <th class="center" style="width:9%">Mois</th>
                        <th class="center" style="width:8%">m²</th>
                        <th class="right" style="width:21%">Montant HT</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->lines as $l)
                        <tr>
                            <td>
                                <span class="line-desig">{{ $l->designation }}</span>
                                @if($l->snapshot_commune_name)
                                    <div class="line-meta">{{ $l->snapshot_commune_name }}</div>
                                @endif
                            </td>
                            <td class="right">{{ $fmt($l->pu_ht_mensuel) }}</td>
                            <td class="center">{{ $l->quantite }}</td>
                            <td class="center">{{ rtrim(rtrim(number_format($l->duree_mois, 2, ',', ''), '0'), ',') }}</td>
                            <td class="center">{{ rtrim(rtrim(number_format($l->dimension_m2, 2, ',', ''), '0'), ',') }}</td>
                            <td class="right"><strong>{{ $fmt($l->montant_ht_ligne) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══ VENTILATION FNE ═══ --}}
    <div class="totals-block">
        <table class="tt">
            @if($invoice->remise_pct > 0)
                <tr>
                    <td class="lbl">Total HT brut</td>
                    <td class="val">{{ $fmt($invoice->amount) }} FCFA</td>
                </tr>
                <tr class="neg">
                    <td class="lbl">Remise ({{ rtrim(rtrim(number_format($invoice->remise_pct, 2, ',', ''), '0'), ',') }} %)</td>
                    <td class="val">− {{ $fmt($invoice->amount * $invoice->remise_pct / 100) }} FCFA</td>
                </tr>
            @endif
            <tr class="strong {{ $invoice->remise_pct > 0 ? 'sep' : '' }}">
                <td class="lbl">TOTAL HT</td>
                <td class="val">{{ $fmt($invoice->net_ht ?: $invoice->amount) }} FCFA</td>
            </tr>
            <tr>
                <td class="lbl">TVA ({{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }} %)</td>
                <td class="val">{{ $fmt($invoice->tva_amount ?: ($invoice->amount_ttc - $invoice->amount)) }} FCFA</td>
            </tr>
            <tr class="ttc">
                <td class="lbl">TOTAL TTC</td>
                <td class="val">{{ $fmt($invoice->amount_ttc) }} FCFA</td>
            </tr>
        </table>

        @php
            $autres = (int) $invoice->tsp_amount + (int) $invoice->tm_total + (int) $invoice->odp_total;
            $pdfServices = $invoice->services;
            if ($pdfServices->isEmpty()) {
                $tmp = collect();
                if ((float) $invoice->services_impression > 0) {
                    $tmp->push((object) ['label' => "Frais d'impression", 'prix_ht' => (float) $invoice->services_impression]);
                }
                if ((float) $invoice->services_pose_depose > 0) {
                    $tmp->push((object) ['label' => 'Frais de pose et dépose', 'prix_ht' => (float) $invoice->services_pose_depose]);
                }
                $pdfServices = $tmp;
            }
            $servicesHt  = (int) $pdfServices->sum('prix_ht');
            $servicesTtc = (int) round($servicesHt * (1 + (float) $invoice->tva / 100));
        @endphp

        @if($autres > 0)
            <table class="box">
                <tr class="title"><td colspan="2">Autres taxes</td></tr>
                @if($invoice->tsp_amount > 0)
                    <tr>
                        <td class="lbl">TSP — Taxe de Soutien à la Production ({{ rtrim(rtrim(number_format(config('billing.tsp_rate', 3), 2, ',', ''), '0'), ',') }} %)</td>
                        <td class="val">{{ $fmt($invoice->tsp_amount) }} FCFA</td>
                    </tr>
                @endif
                @if($invoice->tm_total > 0)
                    <tr>
                        <td class="lbl">TM — Taxe Municipale</td>
                        <td class="val">{{ $fmt($invoice->tm_total) }} FCFA</td>
                    </tr>
                @endif
                @if($invoice->odp_total > 0)
                    <tr>
                        <td class="lbl">ODP — Occupation Domaine Public</td>
                        <td class="val">{{ $fmt($invoice->odp_total) }} FCFA</td>
                    </tr>
                @endif
                <tr class="subtotal">
                    <td class="lbl">Sous-total autres taxes</td>
                    <td class="val">{{ $fmt($autres) }} FCFA</td>
                </tr>
            </table>
        @endif

        @if($servicesHt > 0)
            <table class="box">
                <tr class="title"><td colspan="2">Services annexes ({{ $pdfServices->count() }})</td></tr>
                @foreach($pdfServices as $svc)
                    <tr>
                        <td class="lbl">{{ $svc->label }} <span style="color:#94a3b8;font-size:9px">(HT)</span></td>
                        <td class="val">{{ $fmt($svc->prix_ht) }} FCFA</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td class="lbl">Sous-total services TTC (TVA {{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }} %)</td>
                    <td class="val">{{ $fmt($servicesTtc) }} FCFA</td>
                </tr>
            </table>
        @endif

        <table class="total-final">
            <tr>
                <td class="lbl">Total à payer</td>
                <td class="val">{{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- ═══ VERSEMENTS ═══ --}}
    @php
        $payments = $invoice->payments ?? collect();
        $totalDue = (int) ($invoice->total_a_payer ?: $invoice->amount_ttc);
        $paid = (int) $payments->sum('montant');
        $remaining = max(0, $totalDue - $paid);
    @endphp
    @if($payments->isNotEmpty())
        <div class="section-spacer"></div>
        <div class="section-card pay-card">
            <div class="sct-title">Versements enregistrés ({{ $payments->count() }})</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:15%">Date</th>
                        <th style="width:26%">Mode</th>
                        <th style="width:22%">Référence</th>
                        <th style="width:17%">Banque</th>
                        <th class="right" style="width:20%">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments->sortBy('paid_at') as $p)
                        <tr>
                            <td>{{ $p->paid_at->format('d/m/Y') }}</td>
                            <td>
                                {{ $p->mode_label }}
                                @if($p->is_acompte)
                                    <span class="pill pill-acompte">Acompte</span>
                                @endif
                            </td>
                            <td>{{ $p->reference ?: '—' }}</td>
                            <td>{{ $p->bank ?: '—' }}</td>
                            <td class="right">{{ $fmt($p->montant) }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="balance-table">
                <tr>
                    <td class="lbl">Total encaissé</td>
                    <td class="val">{{ $fmt($paid) }} FCFA</td>
                </tr>
                @if($remaining > 0)
                    <tr class="due">
                        <td class="lbl">Reste à payer</td>
                        <td class="val">{{ $fmt($remaining) }} FCFA</td>
                    </tr>
                @else
                    <tr>
                        <td class="lbl">Facture intégralement soldée</td>
                        <td class="val">{{ $fmt(0) }} FCFA</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    {{-- ═══ ÉCHÉANCIER ═══ --}}
    @php $schedules = $invoice->schedules ?? collect(); @endphp
    @if($schedules->isNotEmpty())
        <div class="section-spacer"></div>
        <div class="section-card sched-card">
            <div class="sct-title">Échéancier prévisionnel ({{ $schedules->count() }})</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:7%">N°</th>
                        <th style="width:40%">Libellé</th>
                        <th style="width:20%">Échéance</th>
                        <th class="right" style="width:20%">Montant</th>
                        <th style="width:13%">État</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $i => $s)
                        <tr>
                            <td><strong>{{ $i + 1 }}</strong></td>
                            <td>{{ $s->label ?? 'Échéance' }}</td>
                            <td>{{ $s->due_date->format('d/m/Y') }}</td>
                            <td class="right">{{ $fmt($s->amount) }} FCFA</td>
                            <td>
                                @if($s->isPaid())
                                    <span class="pill pill-paid">Payée</span>
                                @elseif($s->isOverdue())
                                    <span class="pill pill-late">Retard</span>
                                @else
                                    <span class="pill pill-pending">À venir</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══ FOOTER ═══ --}}
    <div class="footer-wrap">
        <div class="footer-divider"></div>

        <div class="conditions">
            <span class="lbl">Conditions de règlement</span>
            {{ $invoice->notes_client ?: config('billing.payment_terms_default') }}
        </div>

        @php $bank = config('billing.bank'); @endphp
        @if(!empty($bank['name']) || !empty($bank['iban']) || !empty($bank['rib']))
            <div class="bank-card">
                <span class="lbl">Coordonnées bancaires</span>
                @if(!empty($bank['name'])) <strong>Banque :</strong> {{ $bank['name'] }}<br>@endif
                @if(!empty($bank['rib']))  <strong>RIB :</strong> {{ $bank['rib'] }}<br>@endif
                @if(!empty($bank['iban'])) <strong>IBAN :</strong> {{ $bank['iban'] }}<br>@endif
                @if(!empty($bank['swift'])) <strong>SWIFT/BIC :</strong> {{ $bank['swift'] }}@endif
            </div>
        @endif

        <div class="footer-legal">{{ config('billing.legal_mentions') }}</div>

        <div class="footer-meta">
            <strong>Panora</strong> · Données hébergées et sécurisées · Conformité RGPD<br>
            © {{ now()->year }} {{ config('billing.company.name') }}
        </div>
    </div>

    {{-- ═══ Pagination ═══ --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM} / {PAGE_COUNT}";
            $size = 8;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $pdf->page_text(
                $pdf->get_width() - 70,
                $pdf->get_height() - 26,
                $text,
                $font,
                $size,
                array(0.58, 0.64, 0.72)
            );
        }
    </script>

</body>
</html>

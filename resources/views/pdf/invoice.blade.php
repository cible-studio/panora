<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->isCreditNote() ? 'Avoir' : 'Facture' }} {{ $invoice->reference }}</title>
    <style>
        /* ════════════════════════════════════════════════════════════════
           PDF Facture FNE — Refonte PRO Phase 8F
           Compatible DomPDF (pas de flex, pas de gradient).
           Objectifs : aération, lisibilité, sauts de page propres.
        ════════════════════════════════════════════════════════════════ */
        * { margin: 0; padding: 0; box-sizing: border-box; }

        @page {
            margin: 18mm 16mm 22mm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', Helvetica, sans-serif;
            font-size: 11px;
            color: #1f2937;
            background: #fff;
            line-height: 1.55;
        }

        /* ── PAGE BREAK CONTROL ───────────────────────────────
           Empêche de couper au milieu des cards Émetteur/Client,
           Versements, Échéancier, Bandeau total. */
        .no-break { page-break-inside: avoid; }
        .break-before { page-break-before: always; }

        /* ── HEADER ───────────────────────────────────── */
        .head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .head td { vertical-align: top; padding: 0; }
        .head .logo-cell { width: 55%; }
        .head .logo-cell img { height: 52px; }
        .head .ref-cell {
            width: 45%;
            text-align: right;
        }
        .head .doc-type {
            font-size: 10px;
            font-weight: 700;
            color: #b45309;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 6px;
        }
        .head .doc-num {
            font-family: monospace;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .head .doc-date {
            font-size: 10.5px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .head .doc-date strong { color: #374151; }
        .head .badge-status {
            display: inline-block;
            padding: 4px 13px;
            border-radius: 99px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .6px;
            text-transform: uppercase;
        }

        .accent {
            height: 4px;
            background: #e8a020;
            margin: 0 0 22px;
        }

        /* ── ÉMETTEUR / CLIENT ───────────────────────── */
        .parties {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-bottom: 18px;
        }
        .parties td {
            vertical-align: top;
            width: 50%;
            padding: 0;
        }
        .party {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .party .lbl {
            font-size: 9px;
            font-weight: 800;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1.6px;
            margin-bottom: 8px;
        }
        .party .name {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .party .info {
            font-size: 10.5px;
            color: #475569;
            line-height: 1.75;
        }
        .party .info strong { color: #1e293b; }

        /* ── CAMPAGNE / AVOIR BANNERS ─────────────── */
        .campaign-strip {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-left: 4px solid #ea580c;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 10.5px;
            color: #9a3412;
            margin-bottom: 16px;
            line-height: 1.65;
        }
        .campaign-strip strong { color: #7c2d12; }

        .cn-banner {
            background: #fef2f2;
            border: 1.5px solid #fca5a5;
            border-left: 4px solid #ef4444;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 10.5px;
            color: #991b1b;
            margin-bottom: 16px;
            line-height: 1.65;
        }

        /* ── TABLE LIGNES ───────────────────────────── */
        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .lines thead th {
            background: #0f172a;
            color: #fff;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .7px;
            padding: 10px 10px;
            text-align: left;
        }
        .lines thead th.right { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td {
            font-size: 10.5px;
            padding: 11px 10px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }
        .lines tbody td.right { text-align: right; font-family: monospace; }
        .lines tbody td.center { text-align: center; }
        .lines tbody tr:nth-child(even) td { background: #fafafa; }
        .lines tbody tr:last-child td { border-bottom: 2px solid #0f172a; }
        .line-meta {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* ── VENTILATION FNE ───────────────────────── */
        .totals-block {
            width: 65%;
            margin-left: 35%;
            margin-top: 18px;
            page-break-inside: avoid;
        }
        table.tt {
            width: 100%;
            border-collapse: collapse;
        }
        table.tt td {
            padding: 7px 14px;
            font-size: 11px;
            color: #374151;
        }
        table.tt td.lbl { text-align: left; }
        table.tt td.val {
            text-align: right;
            font-family: monospace;
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
        }
        table.tt tr.sep td {
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }
        table.tt tr.strong td { font-weight: 800; font-size: 11.5px; }
        table.tt tr.neg td { color: #b45309; }
        table.tt tr.ttc td {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 13px;
            padding: 11px 14px;
        }

        /* Encarts AUTRES TAXES / SERVICES ANNEXES */
        .box {
            width: 100%;
            border-collapse: collapse;
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin: 12px 0;
            page-break-inside: avoid;
        }
        .box td {
            padding: 6px 12px;
            font-size: 10.5px;
            color: #4b5563;
            vertical-align: top;
        }
        .box td.lbl { text-align: left; }
        .box td.val {
            text-align: right;
            font-family: monospace;
            font-weight: 700;
            white-space: nowrap;
        }
        .box .title td {
            font-size: 9.5px;
            font-weight: 800;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding-top: 10px;
            padding-bottom: 7px;
            background: #f1f5f9;
        }
        .box .subtotal td {
            font-size: 11px;
            font-weight: 800;
            color: #1f2937;
            border-top: 1px dashed #cbd5e1;
            padding-top: 9px;
            padding-bottom: 10px;
        }
        .box .subtotal td.val { color: #b45309; }

        /* ── TOTAL À PAYER BANDEAU ─────────────────── */
        .total-final {
            width: 100%;
            border-collapse: collapse;
            background: #b45309;
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
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .8px;
            text-align: left;
            text-transform: uppercase;
        }
        .total-final td.val {
            font-size: 22px;
            font-weight: 800;
            font-family: monospace;
            text-align: right;
            white-space: nowrap;
        }

        /* ── VERSEMENTS (card verte) ───────────────── */
        .section-spacer { height: 24px; clear: both; }
        .payments-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            border-radius: 6px;
            padding: 14px 16px;
            page-break-inside: avoid;
        }
        .payments-card .pt {
            font-size: 11px;
            font-weight: 800;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 10px;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .payments-table th {
            text-align: left;
            color: #166534;
            font-weight: 800;
            padding: 7px 8px;
            border-bottom: 1.5px solid #86efac;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .payments-table th.right { text-align: right; }
        .payments-table td {
            padding: 8px 8px;
            color: #14532d;
            border-bottom: 1px solid #d1fae5;
        }
        .payments-table td.right { text-align: right; font-family: monospace; font-weight: 700; }

        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1.5px dashed #86efac;
        }
        .balance-table td {
            padding: 5px 8px;
            font-size: 11.5px;
            font-weight: 800;
        }
        .balance-table td.lbl { text-align: left; color: #15803d; }
        .balance-table td.val {
            text-align: right;
            font-family: monospace;
            color: #15803d;
            white-space: nowrap;
        }
        .balance-table tr.due td { color: #b91c1c; }
        .balance-table tr.soldee td { color: #16a34a; }

        /* ── ÉCHÉANCIER (card bleue) ───────────────── */
        .schedule-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 4px solid #2563eb;
            border-radius: 6px;
            padding: 14px 16px;
            page-break-inside: avoid;
        }
        .schedule-card .st {
            font-size: 11px;
            font-weight: 800;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: .8px;
            margin-bottom: 10px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
        }
        .schedule-table th {
            text-align: left;
            color: #1e3a8a;
            font-weight: 800;
            padding: 7px 8px;
            border-bottom: 1.5px solid #93c5fd;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .schedule-table th.right { text-align: right; }
        .schedule-table td {
            padding: 8px 8px;
            color: #1e40af;
            border-bottom: 1px solid #dbeafe;
        }
        .schedule-table td.right { text-align: right; font-family: monospace; font-weight: 700; }

        /* ── BAS DE PAGE ────────────────────────────── */
        .footer-wrap {
            clear: both;
            margin-top: 28px;
            page-break-inside: avoid;
        }
        .footer-info {
            padding-top: 14px;
            border-top: 2px solid #e2e8f0;
            font-size: 10px;
            color: #6b7280;
            line-height: 1.7;
        }
        .footer-info strong { color: #374151; }
        .conditions {
            font-size: 10.5px;
            margin-bottom: 10px;
        }
        .bank-card {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 14px;
            margin-top: 10px;
            margin-bottom: 12px;
            font-size: 10px;
            color: #475569;
            line-height: 1.7;
            page-break-inside: avoid;
        }
        .bank-card strong { color: #1e293b; }
        .footer-legal {
            margin-top: 14px;
            font-size: 8.5px;
            color: #94a3b8;
            font-style: italic;
            line-height: 1.6;
        }
        .footer-rgpd {
            margin-top: 12px;
            padding: 8px 12px;
            text-align: center;
            font-size: 8.5px;
            color: #6b7280;
            letter-spacing: .4px;
            background: #f8fafc;
            border-radius: 4px;
        }

        /* Status badges */
        .st-brouillon     { background: #f3f4f6; color: #4b5563; }
        .st-generee       { background: #ede9fe; color: #6d28d9; }
        .st-validee       { background: #cffafe; color: #0e7490; }
        .st-envoyee       { background: #dbeafe; color: #1d4ed8; }
        .st-partielle     { background: #fef3c7; color: #b45309; }
        .st-payee         { background: #dcfce7; color: #15803d; }
        .st-retard        { background: #fee2e2; color: #b91c1c; }
        .st-litige        { background: #ffedd5; color: #9a3412; }
        .st-annulee       { background: #fee2e2; color: #991b1b; }

        /* Page number footer (rendered by DomPDF script) */
        .page-num {
            position: fixed;
            bottom: 10mm;
            right: 16mm;
            font-size: 8.5px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    {{-- ════════════════════ HEADER ════════════════════ --}}
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
        $st = $statusLabels[$invoice->status] ?? null;
        $fmt = fn($v) => number_format((float) $v, 0, ',', ' ');
    @endphp
    <table class="head no-break">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path(config('billing.company.logo_path', 'images/panora.png')) }}" alt="{{ config('billing.company.name') }}">
            </td>
            <td class="ref-cell">
                <div class="doc-type">{{ $invoice->isCreditNote() ? 'Avoir / Note de crédit' : 'Facture FNE' }}</div>
                <div class="doc-num">{{ $invoice->reference }}</div>
                <div class="doc-date">
                    Émise le <strong>{{ $invoice->issued_at->format('d/m/Y') }}</strong>
                </div>
                @if($st)
                    <span class="badge-status {{ $st['cls'] }}">{{ $st['lbl'] }}</span>
                @endif
            </td>
        </tr>
    </table>
    <div class="accent"></div>

    {{-- ════════════════════ AVOIR BANNER ════════════════════ --}}
    @if($invoice->isCreditNote() && $invoice->creditNoteFor)
        <div class="cn-banner no-break">
            <strong>📋 Avoir / Note de crédit</strong> émis(e) sur la facture
            <strong>{{ $invoice->creditNoteFor->reference }}</strong>
            du {{ $invoice->creditNoteFor->issued_at->format('d/m/Y') }}.
        </div>
    @endif

    {{-- ════════════════════ ÉMETTEUR + CLIENT ════════════════════ --}}
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
                    <div class="lbl">Client</div>
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

    {{-- ════════════════════ CAMPAGNE LIÉE ════════════════════ --}}
    @if($invoice->campaign)
        <div class="campaign-strip no-break">
            <strong>📢 Campagne :</strong> {{ $invoice->campaign->name }}
            @if($invoice->campaign->start_date && $invoice->campaign->end_date)
                <br><strong>Période :</strong> du {{ $invoice->campaign->start_date->format('d/m/Y') }}
                au {{ $invoice->campaign->end_date->format('d/m/Y') }}
            @endif
        </div>
    @endif

    {{-- ════════════════════ LIGNES ════════════════════ --}}
    @if($invoice->lines && $invoice->lines->isNotEmpty())
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:38%">Désignation</th>
                    <th class="right" style="width:14%">PU HT</th>
                    <th class="center" style="width:8%">Qté</th>
                    <th class="center" style="width:10%">Mois</th>
                    <th class="center" style="width:8%">m²</th>
                    <th class="right" style="width:22%">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $l)
                    <tr>
                        <td>
                            {{ $l->designation }}
                            @if($l->snapshot_commune_name)
                                <div class="line-meta">📍 {{ $l->snapshot_commune_name }}</div>
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
    @endif

    {{-- ════════════════════ VENTILATION FNE ════════════════════ --}}
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
                <tr class="title"><td colspan="2">AUTRES TAXES</td></tr>
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
                <tr class="title"><td colspan="2">SERVICES ANNEXES ({{ $pdfServices->count() }})</td></tr>
                @foreach($pdfServices as $svc)
                    <tr>
                        <td class="lbl">{{ $svc->label }} <span style="color:#94a3b8">(HT)</span></td>
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
                <td class="lbl">TOTAL À PAYER</td>
                <td class="val">{{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA</td>
            </tr>
        </table>
    </div>

    {{-- ════════════════════ PAIEMENTS ENREGISTRÉS ════════════════════ --}}
    @php
        $payments = $invoice->payments ?? collect();
        $totalDue = (int) ($invoice->total_a_payer ?: $invoice->amount_ttc);
        $paid = (int) $payments->sum('montant');
        $remaining = max(0, $totalDue - $paid);
    @endphp
    @if($payments->isNotEmpty())
        <div class="section-spacer"></div>
        <div class="payments-card">
            <div class="pt">💸 Versements enregistrés ({{ $payments->count() }})</div>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th style="width:15%">Date</th>
                        <th style="width:25%">Mode</th>
                        <th style="width:22%">Référence</th>
                        <th style="width:18%">Banque</th>
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
                                    <span style="background:#fef3c7;color:#b45309;padding:2px 7px;border-radius:4px;font-size:8.5px;font-weight:700;margin-left:4px;letter-spacing:.3px">ACOMPTE</span>
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
                    <tr class="soldee">
                        <td class="lbl">✅ Facture intégralement soldée</td>
                        <td class="val">{{ $fmt(0) }} FCFA</td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    {{-- ════════════════════ ÉCHÉANCIER PRÉVISIONNEL ════════════════════ --}}
    @php $schedules = $invoice->schedules ?? collect(); @endphp
    @if($schedules->isNotEmpty())
        <div class="section-spacer"></div>
        <div class="schedule-card">
            <div class="st">📅 Échéancier prévisionnel ({{ $schedules->count() }})</div>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="width:8%">N°</th>
                        <th style="width:40%">Libellé</th>
                        <th style="width:20%">Date d'échéance</th>
                        <th class="right" style="width:20%">Montant</th>
                        <th style="width:12%">État</th>
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
                                    <span style="color:#15803d;font-weight:700">✓ Payée</span>
                                @elseif($s->isOverdue())
                                    <span style="color:#b91c1c;font-weight:700">⚠ Retard</span>
                                @else
                                    <span style="color:#475569">À venir</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ════════════════════ BAS DE PAGE ════════════════════ --}}
    <div class="footer-wrap">
        <div class="footer-info">
            <div class="conditions">
                <strong>💼 Conditions de règlement :</strong>
                {{ $invoice->notes_client ?: config('billing.payment_terms_default') }}
            </div>

            @php $bank = config('billing.bank'); @endphp
            @if(!empty($bank['name']) || !empty($bank['iban']) || !empty($bank['rib']))
                <div class="bank-card">
                    <strong>🏦 Coordonnées bancaires</strong><br>
                    @if(!empty($bank['name'])) <strong>Banque :</strong> {{ $bank['name'] }}<br>@endif
                    @if(!empty($bank['rib']))  <strong>RIB :</strong> {{ $bank['rib'] }}<br>@endif
                    @if(!empty($bank['iban'])) <strong>IBAN :</strong> {{ $bank['iban'] }}<br>@endif
                    @if(!empty($bank['swift'])) <strong>SWIFT/BIC :</strong> {{ $bank['swift'] }}@endif
                </div>
            @endif

            <div class="footer-legal">{{ config('billing.legal_mentions') }}</div>

            <div class="footer-rgpd">
                🔒 Données hébergées et sécurisées — Conformité RGPD · Panora © {{ now()->year }} {{ config('billing.company.name') }}
            </div>
        </div>
    </div>

    {{-- Pagination automatique DomPDF --}}
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Page {PAGE_NUM}/{PAGE_COUNT}";
            $size = 8.5;
            $font = $fontMetrics->getFont("DejaVu Sans");
            $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
            $pdf->page_text($pdf->get_width() - 60, $pdf->get_height() - 30, $text, $font, $size, array(0.58, 0.64, 0.72));
        }
    </script>

</body>
</html>

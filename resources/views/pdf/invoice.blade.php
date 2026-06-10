<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->isCreditNote() ? 'Avoir' : 'Facture' }} {{ $invoice->reference }}</title>
    <style>
        /* ════════════════════════════════════════════════════════════════
           PDF Facture FNE — DomPDF compatible
           Conventions clés :
             - PAS de display:flex (non supporté par DomPDF)
             - PAS de linear-gradient (idem)
             - Tous les "label / valeur" en <table> 2 colonnes
             - Couleurs solides (#b45309 doré CIBLE pour les bandeaux)
        ════════════════════════════════════════════════════════════════ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 0; size: A4 portrait; }
        body {
            font-family: 'DejaVu Sans', Helvetica, sans-serif;
            font-size: 10px;
            color: #1f2937;
            background: #fff;
            line-height: 1.45;
        }
        .wrap { padding: 16mm 14mm 22mm; }

        /* ── HEADER ─────────────────────────────────── */
        .head { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .head td { vertical-align: top; padding: 0; }
        .head .logo-cell { width: 50%; }
        .head .logo-cell img { height: 40px; }
        .head .ref-cell { width: 50%; text-align: right; }
        .head .doc-type {
            font-size: 9px; font-weight: 700; color: #b45309;
            text-transform: uppercase; letter-spacing: 3px;
            margin-bottom: 4px;
        }
        .head .doc-num {
            font-family: monospace; font-size: 18px; font-weight: 800; color: #0f172a;
        }
        .head .doc-date { font-size: 9.5px; color: #6b7280; margin-top: 4px; }
        .head .doc-date strong { color: #374151; }
        .head .badge-status {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 9px;
            border-radius: 99px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: .5px;
            text-transform: uppercase;
        }

        .accent { height: 3px; background: #e8a020; margin: 4px 0 14px; }

        /* ── PARTIES (Émetteur / Client) ─────────────── */
        .parties { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 12px; }
        .parties td { vertical-align: top; width: 50%; padding: 0; }
        .party {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .party .lbl {
            font-size: 7.5px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1.4px;
            margin-bottom: 5px;
        }
        .party .name {
            font-size: 12px; font-weight: 800; color: #0f172a;
            margin-bottom: 4px;
        }
        .party .info { font-size: 9px; color: #475569; line-height: 1.7; }
        .party .info strong { color: #1e293b; }

        /* ── CAMPAGNE STRIP ─────────────────────────── */
        .campaign-strip {
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-left: 3px solid #ea580c;
            border-radius: 4px;
            padding: 7px 11px;
            font-size: 9.5px;
            color: #9a3412;
            margin-bottom: 12px;
        }
        .campaign-strip strong { color: #7c2d12; }

        /* ── AVOIR BANNER ──────────────────────────── */
        .cn-banner {
            background: #fef2f2;
            border: 1.5px solid #fca5a5;
            border-radius: 6px;
            padding: 8px 11px;
            font-size: 9.5px;
            color: #991b1b;
            margin-bottom: 12px;
        }

        /* ── DRIFT BANNER ─────────────────────────── */
        .drift-banner {
            background: #fff7ed;
            border: 1.5px solid #fdba74;
            border-radius: 6px;
            padding: 8px 11px;
            font-size: 9px;
            color: #9a3412;
            margin-bottom: 10px;
        }

        /* ── TABLE LIGNES ──────────────────────────── */
        .lines {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .lines thead th {
            background: #0f172a;
            color: #fff;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            padding: 8px 8px;
            text-align: left;
        }
        .lines thead th.right { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td {
            font-size: 9.5px;
            padding: 7px 8px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .lines tbody td.right { text-align: right; font-family: monospace; }
        .lines tbody td.center { text-align: center; }
        .lines tbody tr:nth-child(even) td { background: #fafafa; }
        .line-meta { font-size: 8px; color: #94a3b8; margin-top: 1px; }

        /* ── BLOC TOTAUX (compatible DomPDF : tables uniquement) ── */
        .totals-block {
            width: 60%;
            margin-left: 40%;
            margin-top: 14px;
        }
        table.tt {
            width: 100%;
            border-collapse: collapse;
        }
        table.tt td {
            padding: 5px 12px;
            font-size: 10px;
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
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            margin-top: 4px;
        }
        table.tt tr.strong td { font-weight: 800; }
        table.tt tr.neg td { color: #b45309; }

        /* Bandeau TOTAL TTC clair */
        table.tt tr.ttc td {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 11px;
            padding: 9px 12px;
        }

        /* ── Encart Autres taxes / Services ─────────── */
        .box {
            width: 100%;
            border-collapse: collapse;
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            margin: 8px 0;
        }
        .box td {
            padding: 4px 10px;
            font-size: 9.5px;
            color: #4b5563;
            vertical-align: top;
        }
        .box td.lbl { text-align: left; }
        .box td.val { text-align: right; font-family: monospace; font-weight: 700; white-space: nowrap; }
        .box .title td {
            font-size: 8.5px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            padding-top: 7px;
            padding-bottom: 4px;
        }
        .box .subtotal td {
            font-size: 10px;
            font-weight: 800;
            color: #1f2937;
            border-top: 1px dashed #cbd5e1;
            padding-top: 6px;
            padding-bottom: 7px;
        }
        .box .subtotal td.val { color: #b45309; }

        /* ── TOTAL À PAYER bandeau accent (couleur solide) ── */
        .total-final {
            width: 100%;
            border-collapse: collapse;
            background: #b45309; /* doré CIBLE — solide (DomPDF n'aime pas le gradient) */
            border-radius: 4px;
            margin-top: 8px;
        }
        .total-final td {
            padding: 12px 14px;
            color: #fff;
            vertical-align: middle;
        }
        .total-final td.lbl {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .5px;
            text-align: left;
        }
        .total-final td.val {
            font-size: 18px;
            font-weight: 800;
            font-family: monospace;
            text-align: right;
            white-space: nowrap;
        }

        /* ── PAIEMENTS ────────────────────────────── */
        .payments-wrap { clear: both; margin-top: 22px; }
        .payments-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-left: 3px solid #16a34a;
            border-radius: 5px;
            padding: 10px 12px;
        }
        .payments-card .pt {
            font-size: 9px;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .payments-table th {
            text-align: left;
            color: #166534;
            font-weight: 700;
            padding: 4px 6px;
            border-bottom: 1px solid #bbf7d0;
        }
        .payments-table th.right { text-align: right; }
        .payments-table td {
            padding: 4px 6px;
            color: #14532d;
            border-bottom: 1px solid #dcfce7;
        }
        .payments-table td.right { text-align: right; font-family: monospace; font-weight: 700; }
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 7px;
            padding-top: 6px;
            border-top: 1px dashed #86efac;
        }
        .balance-table td {
            padding: 3px 6px;
            font-size: 10.5px;
            font-weight: 800;
        }
        .balance-table td.lbl { text-align: left; color: #15803d; }
        .balance-table td.val { text-align: right; font-family: monospace; color: #15803d; white-space: nowrap; }
        .balance-table tr.due td { color: #b91c1c; }

        /* ── ÉCHÉANCIER ────────────────────────────── */
        .schedule-card {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-left: 3px solid #2563eb;
            border-radius: 5px;
            padding: 9px 12px;
            margin-top: 10px;
        }
        .schedule-card .st {
            font-size: 9px;
            font-weight: 700;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        .schedule-table th {
            text-align: left;
            color: #1e3a8a;
            font-weight: 700;
            padding: 3px 6px;
            border-bottom: 1px solid #bfdbfe;
        }
        .schedule-table th.right { text-align: right; }
        .schedule-table td {
            padding: 3px 6px;
            color: #1e40af;
        }
        .schedule-table td.right { text-align: right; font-family: monospace; }

        /* ── BAS DE PAGE ──────────────────────────── */
        .footer-wrap { clear: both; margin-top: 22px; }
        .footer-info {
            padding-top: 11px;
            border-top: 1px solid #e2e8f0;
            font-size: 8.5px;
            color: #6b7280;
            line-height: 1.65;
        }
        .footer-info strong { color: #374151; }
        .bank-card {
            background: #fafafa;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-top: 7px;
            font-size: 8.5px;
            color: #475569;
        }
        .bank-card strong { color: #1e293b; }
        .footer-legal {
            margin-top: 10px;
            font-size: 7.5px;
            color: #94a3b8;
            font-style: italic;
            line-height: 1.6;
        }
        .footer-rgpd {
            margin-top: 6px;
            text-align: center;
            font-size: 7.5px;
            color: #9ca3af;
            letter-spacing: .3px;
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
    </style>
</head>
<body>
<div class="wrap">

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
    @endphp
    <table class="head">
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
        <div class="cn-banner">
            <strong>📋 Avoir / Note de crédit</strong> émis(e) sur la facture
            <strong>{{ $invoice->creditNoteFor->reference }}</strong>
            du {{ $invoice->creditNoteFor->issued_at->format('d/m/Y') }}.
        </div>
    @endif

    {{-- ════════════════════ ÉMETTEUR + CLIENT ════════════════════ --}}
    <table class="parties">
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
        <div class="campaign-strip">
            <strong>📢 Campagne :</strong> {{ $invoice->campaign->name }}
            @if($invoice->campaign->start_date && $invoice->campaign->end_date)
                · Période : {{ $invoice->campaign->start_date->format('d/m/Y') }}
                → {{ $invoice->campaign->end_date->format('d/m/Y') }}
            @endif
        </div>
    @endif

    {{-- ════════════════════ LIGNES ════════════════════ --}}
    @php
        $fmt     = fn($v) => number_format((float) $v, 0, ',', ' ');
        $hasLines = $invoice->lines && $invoice->lines->isNotEmpty();
    @endphp

    @if($hasLines)
        <table class="lines">
            <thead>
                <tr>
                    <th style="width:42%">Désignation</th>
                    <th class="right" style="width:13%">PU HT</th>
                    <th class="center" style="width:7%">Qté</th>
                    <th class="center" style="width:9%">Mois</th>
                    <th class="center" style="width:7%">m²</th>
                    <th class="right" style="width:18%">Montant HT</th>
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

        {{-- Total HT brut + remise --}}
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
            // Services annexes (Phase 5) : N lignes libres, fallback legacy.
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

        {{-- Autres taxes (TSP + TM + ODP) --}}
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

        {{-- Services annexes --}}
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

        {{-- TOTAL À PAYER (bandeau doré CIBLE) --}}
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
        <div class="payments-wrap">
            <div class="payments-card">
                <div class="pt">💸 Versements enregistrés ({{ $payments->count() }})</div>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th>Banque</th>
                            <th class="right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payments->sortBy('paid_at') as $p)
                            <tr>
                                <td>{{ $p->paid_at->format('d/m/Y') }}</td>
                                <td>
                                    {{ $p->mode_label }}
                                    @if($p->is_acompte)
                                        <span style="background:#fef3c7;color:#b45309;padding:1px 5px;border-radius:3px;font-size:7.5px;font-weight:700;margin-left:3px">ACOMPTE</span>
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
                            <td class="lbl">✅ Facture intégralement soldée</td>
                            <td class="val">{{ $fmt(0) }} FCFA</td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    @endif

    {{-- ════════════════════ ÉCHÉANCIER PRÉVISIONNEL ════════════════════ --}}
    @php $schedules = $invoice->schedules ?? collect(); @endphp
    @if($schedules->isNotEmpty())
        <div class="schedule-card">
            <div class="st">📅 Échéancier prévisionnel ({{ $schedules->count() }})</div>
            <table class="schedule-table">
                <thead>
                    <tr>
                        <th style="width:12%">N°</th>
                        <th>Libellé</th>
                        <th style="width:20%">Date d'échéance</th>
                        <th class="right" style="width:22%">Montant</th>
                        <th style="width:15%">État</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($schedules as $i => $s)
                        <tr>
                            <td>{{ $i + 1 }}</td>
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
            <strong>Conditions de règlement :</strong>
            {{ $invoice->notes_client ?: config('billing.payment_terms_default') }}

            @php $bank = config('billing.bank'); @endphp
            @if(!empty($bank['name']) || !empty($bank['iban']) || !empty($bank['rib']))
                <div class="bank-card">
                    <strong>🏦 Coordonnées bancaires :</strong>
                    @if(!empty($bank['name'])) {{ $bank['name'] }}@endif
                    @if(!empty($bank['rib']))  · <strong>RIB :</strong> {{ $bank['rib'] }}@endif
                    @if(!empty($bank['iban'])) · <strong>IBAN :</strong> {{ $bank['iban'] }}@endif
                    @if(!empty($bank['swift'])) · <strong>SWIFT :</strong> {{ $bank['swift'] }}@endif
                </div>
            @endif

            <div class="footer-legal">{{ config('billing.legal_mentions') }}</div>

            <div class="footer-rgpd">🔒 Données hébergées et sécurisées — Conformité RGPD · Panora © {{ now()->year }} {{ config('billing.company.name') }}</div>
        </div>
    </div>

</div>
</body>
</html>

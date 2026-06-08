<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice->isCreditNote() ? 'Avoir' : 'Facture' }} {{ $invoice->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { margin: 0; size: A4 portrait; }
        body {
            font-family: 'DejaVu Sans', Helvetica, sans-serif;
            font-size: 10px; color: #1f2937; background: #fff; line-height: 1.45;
        }
        .wrap { padding: 18mm 16mm 24mm; }

        /* ── HEADER : logo + titre + N° ─────────────── */
        .head { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .head td { vertical-align: top; }
        .head .logo-cell { width: 50%; }
        .head .logo-cell img { height: 38px; }
        .head .ref-cell { width: 50%; text-align: right; }
        .head .doc-type {
            font-size: 9px; font-weight: 700; color: #c2570d;
            text-transform: uppercase; letter-spacing: 3px;
            margin-bottom: 3px;
        }
        .head .doc-num {
            font-family: monospace; font-size: 17px; font-weight: 800; color: #0f172a;
        }
        .head .doc-date { font-size: 9.5px; color: #6b7280; margin-top: 2px; }
        .head .doc-date strong { color: #374151; }

        .accent { height: 2px; background: #e8a020; margin: 6px 0 14px; }

        /* ── ENTREPRISE + CLIENT ─────────────────────── */
        .parties { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px; }
        .parties td { vertical-align: top; width: 50%; }
        .party {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 10px 12px;
        }
        .party .lbl {
            font-size: 7.5px; font-weight: 700; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1.4px; margin-bottom: 5px;
        }
        .party .name { font-size: 11px; font-weight: 700; color: #0f172a; margin-bottom: 3px; }
        .party .info { font-size: 9px; color: #64748b; line-height: 1.65; }
        .party .info strong { color: #374151; }

        /* ── INFOS CAMPAGNE ─────────────────────────── */
        .campaign-strip {
            background: #fff7ed; border: 1px solid #fed7aa;
            border-radius: 6px; padding: 8px 11px;
            font-size: 9.5px; color: #9a3412; margin-bottom: 14px;
        }
        .campaign-strip strong { color: #7c2d12; }

        /* ── CREDIT NOTE BANNER ─────────────────────── */
        .cn-banner {
            background: #fef2f2; border: 1.5px solid #fca5a5;
            border-radius: 6px; padding: 8px 11px;
            font-size: 9.5px; color: #991b1b; margin-bottom: 14px;
        }

        /* ── LIGNES ──────────────────────────────────── */
        .lines { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .lines thead th {
            background: #0f172a; color: #fff;
            font-size: 8.5px; font-weight: 700;
            text-transform: uppercase; letter-spacing: .6px;
            padding: 7px 8px;
        }
        .lines thead th.right { text-align: right; }
        .lines thead th.center { text-align: center; }
        .lines tbody td {
            font-size: 9.5px; padding: 7px 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        .lines tbody td.right { text-align: right; font-family: monospace; }
        .lines tbody td.center { text-align: center; }
        .lines tbody tr:nth-child(even) td { background: #fafafa; }

        /* ── VENTILATION FNE ────────────────────────── */
        .totals-wrap { width: 100%; margin-top: 16px; }
        .totals { float: right; width: 62%; }
        .totals-row {
            display: flex; justify-content: space-between;
            padding: 5px 12px; font-size: 10px;
        }
        .totals-row.sep { border-top: 1px solid #e2e8f0; margin-top: 4px; padding-top: 8px; }
        .totals-row.ttc {
            background: #f1f5f9; font-weight: 800; font-size: 11px;
            padding: 8px 12px; border-radius: 4px; margin: 4px 0;
        }
        .totals-row .lbl { color: #374151; }
        .totals-row .val { font-family: monospace; font-weight: 700; color: #0f172a; }
        .totals-row .neg { color: #b45309; }

        .autres-taxes {
            background: #fafafa; border: 1px solid #e2e8f0;
            border-radius: 6px; padding: 8px 12px; margin: 6px 0;
        }
        .autres-taxes .at-title {
            font-size: 8.5px; font-weight: 700; color: #6b7280;
            text-transform: uppercase; letter-spacing: .5px; margin-bottom: 5px;
        }
        .autres-taxes .at-row { display: flex; justify-content: space-between; font-size: 9.5px; margin-bottom: 3px; color: #4b5563; }
        .autres-taxes .at-row .val { font-family: monospace; font-weight: 700; }
        .autres-taxes .at-total {
            border-top: 1px dashed #cbd5e1; margin-top: 5px; padding-top: 5px;
            display: flex; justify-content: space-between;
            font-size: 10px; font-weight: 800; color: #1f2937;
        }
        .autres-taxes .at-total .val { font-family: monospace; }

        .total-final {
            background: linear-gradient(135deg, #c2570d, #e8a020); color: #fff;
            padding: 10px 14px; border-radius: 6px; margin-top: 8px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .total-final .lbl { font-size: 11px; font-weight: 800; letter-spacing: .4px; }
        .total-final .val { font-size: 16px; font-weight: 800; font-family: monospace; }

        /* ── PAIEMENTS ──────────────────────────────── */
        .payments {
            clear: both; margin-top: 22px; padding: 10px 12px;
            background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px;
        }
        .payments .p-title {
            font-size: 9px; font-weight: 700; color: #166534;
            text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px;
        }
        .payments table { width: 100%; border-collapse: collapse; font-size: 9px; }
        .payments th { text-align: left; color: #166534; font-weight: 700; padding: 3px 6px; border-bottom: 1px solid #bbf7d0; }
        .payments td { padding: 4px 6px; color: #14532d; }
        .payments .right { text-align: right; font-family: monospace; font-weight: 700; }
        .payments .balance {
            margin-top: 6px; padding-top: 6px;
            border-top: 1px dashed #86efac;
            display: flex; justify-content: space-between;
            font-weight: 800; color: #15803d;
        }

        /* ── BAS DE PAGE ────────────────────────────── */
        .footer-info {
            clear: both; margin-top: 26px; padding-top: 12px;
            border-top: 1px solid #e2e8f0; font-size: 8.5px;
            color: #6b7280; line-height: 1.6;
        }
        .footer-info strong { color: #374151; }
        .footer-legal { margin-top: 10px; font-size: 7.5px; color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
<div class="wrap">

    {{-- ════ HEADER ════ --}}
    <table class="head">
        <tr>
            <td class="logo-cell">
                <img src="{{ public_path(config('billing.company.logo_path', 'images/panora.png')) }}" alt="{{ config('billing.company.name') }}">
            </td>
            <td class="ref-cell">
                <div class="doc-type">{{ $invoice->isCreditNote() ? 'Avoir / Note de crédit' : 'Facture' }}</div>
                <div class="doc-num">{{ $invoice->reference }}</div>
                <div class="doc-date">
                    Émise le <strong>{{ $invoice->issued_at->format('d/m/Y') }}</strong>
                    @if($invoice->status === 'payee')
                        · <strong style="color:#16a34a">Payée</strong>
                    @endif
                </div>
            </td>
        </tr>
    </table>
    <div class="accent"></div>

    {{-- ════ AVOIR ? ════ --}}
    @if($invoice->isCreditNote() && $invoice->creditNoteFor)
        <div class="cn-banner">
            <strong>📋 Avoir / Note de crédit</strong> émis(e) sur la facture
            <strong>{{ $invoice->creditNoteFor->reference }}</strong>
            du {{ $invoice->creditNoteFor->issued_at->format('d/m/Y') }}.
        </div>
    @endif

    {{-- ════ ENTREPRISE + CLIENT ════ --}}
    <table class="parties">
        <tr>
            <td>
                <div class="party">
                    <div class="lbl">Émetteur</div>
                    <div class="name">{{ config('billing.company.name') }}</div>
                    <div class="info">
                        {{ config('billing.company.legal') }}<br>
                        {{ config('billing.company.address') }}<br>
                        @if(config('billing.company.phone')) Tél : {{ config('billing.company.phone') }}<br>@endif
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
                        @if($invoice->client?->phone) Tél : {{ $invoice->client->phone }}<br>@endif
                        @if($invoice->client?->address) {{ $invoice->client->address }}<br>@endif
                        @if($invoice->client?->rccm) <strong>RCCM :</strong> {{ $invoice->client->rccm }}<br>@endif
                        @if($invoice->client?->ifu) <strong>IFU :</strong> {{ $invoice->client->ifu }}<br>@endif
                        @if($invoice->client?->ncc) <strong>NCC :</strong> {{ $invoice->client->ncc }}@endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ════ CAMPAGNE LIÉE ════ --}}
    @if($invoice->campaign)
        <div class="campaign-strip">
            <strong>📢 Campagne :</strong> {{ $invoice->campaign->name }}
            @if($invoice->campaign->start_date && $invoice->campaign->end_date)
                · Période : {{ $invoice->campaign->start_date->format('d/m/Y') }}
                → {{ $invoice->campaign->end_date->format('d/m/Y') }}
            @endif
        </div>
    @endif

    {{-- ════ LIGNES ════ --}}
    @php
        $fmt     = fn($v) => number_format((float) $v, 0, ',', ' ');
        $hasLines = $invoice->lines && $invoice->lines->isNotEmpty();
    @endphp
    @if($hasLines)
        <table class="lines">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="right">PU HT</th>
                    <th class="center">Qté</th>
                    <th class="center">Mois</th>
                    <th class="center">m²</th>
                    <th class="right">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->lines as $l)
                    <tr>
                        <td>
                            {{ $l->designation }}
                            @if($l->snapshot_commune_name)
                                <div style="font-size:8px;color:#94a3b8;margin-top:1px">{{ $l->snapshot_commune_name }}</div>
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

    {{-- ════ VENTILATION FNE ════ --}}
    <div class="totals-wrap">
        <div class="totals">
            @if($invoice->remise_pct > 0)
                <div class="totals-row">
                    <span class="lbl">Total HT brut</span>
                    <span class="val">{{ $fmt($invoice->amount) }} FCFA</span>
                </div>
                <div class="totals-row">
                    <span class="lbl neg">Remise ({{ rtrim(rtrim(number_format($invoice->remise_pct, 2, ',', ''), '0'), ',') }} %)</span>
                    <span class="val neg">− {{ $fmt($invoice->amount * $invoice->remise_pct / 100) }} FCFA</span>
                </div>
            @endif
            <div class="totals-row {{ $invoice->remise_pct > 0 ? 'sep' : '' }}">
                <span class="lbl"><strong>TOTAL HT</strong></span>
                <span class="val">{{ $fmt($invoice->net_ht ?: $invoice->amount) }} FCFA</span>
            </div>
            <div class="totals-row">
                <span class="lbl">TVA ({{ rtrim(rtrim(number_format($invoice->tva, 2, ',', ''), '0'), ',') }} %)</span>
                <span class="val">{{ $fmt($invoice->tva_amount ?: ($invoice->amount_ttc - $invoice->amount)) }} FCFA</span>
            </div>
            <div class="totals-row ttc">
                <span class="lbl">TOTAL TTC</span>
                <span class="val">{{ $fmt($invoice->amount_ttc) }} FCFA</span>
            </div>

            @php
                $autres = (float) $invoice->tsp_amount + (float) $invoice->tm_total + (float) $invoice->odp_total;
                $servicesHt = (float) $invoice->services_impression + (float) $invoice->services_pose_depose;
                $servicesTtc = $servicesHt * (1 + (float) $invoice->tva / 100);
            @endphp

            @if($autres > 0)
                <div class="autres-taxes">
                    <div class="at-title">Autres taxes</div>
                    @if($invoice->tsp_amount > 0)
                        <div class="at-row"><span>TSP (3 %)</span><span class="val">{{ $fmt($invoice->tsp_amount) }}</span></div>
                    @endif
                    @if($invoice->tm_total > 0)
                        <div class="at-row"><span>TM — Taxe Municipale</span><span class="val">{{ $fmt($invoice->tm_total) }}</span></div>
                    @endif
                    @if($invoice->odp_total > 0)
                        <div class="at-row"><span>ODP — Occupation Domaine Public</span><span class="val">{{ $fmt($invoice->odp_total) }}</span></div>
                    @endif
                    <div class="at-total"><span>Sous-total</span><span class="val">{{ $fmt($autres) }} FCFA</span></div>
                </div>
            @endif

            @if($servicesHt > 0)
                <div class="autres-taxes">
                    <div class="at-title">Services additionnels</div>
                    @if($invoice->services_impression > 0)
                        <div class="at-row"><span>Impression (HT)</span><span class="val">{{ $fmt($invoice->services_impression) }}</span></div>
                    @endif
                    @if($invoice->services_pose_depose > 0)
                        <div class="at-row"><span>Pose & dépose (HT)</span><span class="val">{{ $fmt($invoice->services_pose_depose) }}</span></div>
                    @endif
                    <div class="at-total"><span>Sous-total TTC (TVA 18%)</span><span class="val">{{ $fmt($servicesTtc) }} FCFA</span></div>
                </div>
            @endif

            <div class="total-final">
                <span class="lbl">TOTAL À PAYER</span>
                <span class="val">{{ $fmt($invoice->total_a_payer ?: $invoice->amount_ttc) }} FCFA</span>
            </div>
        </div>
    </div>

    {{-- ════ PAIEMENTS ENREGISTRÉS ════ --}}
    @php $payments = $invoice->payments ?? collect(); @endphp
    @if($payments->isNotEmpty())
        <div class="payments">
            <div class="p-title">💸 Versements enregistrés</div>
            <table>
                <thead>
                    <tr><th>Date</th><th>Mode</th><th>Réf.</th><th class="right">Montant</th></tr>
                </thead>
                <tbody>
                    @foreach($payments as $p)
                        <tr>
                            <td>{{ $p->paid_at->format('d/m/Y') }}</td>
                            <td>{{ $p->mode_label }}</td>
                            <td>{{ $p->reference ?: '—' }}</td>
                            <td class="right">{{ $fmt($p->montant) }} FCFA</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @php
                $paid = $payments->sum('montant');
                $total = (float) ($invoice->total_a_payer ?: $invoice->amount_ttc);
                $remaining = max(0, $total - $paid);
            @endphp
            <div class="balance">
                <span>Reste à payer</span>
                <span style="font-family:monospace">{{ $fmt($remaining) }} FCFA</span>
            </div>
        </div>
    @endif

    {{-- ════ BAS DE PAGE ════ --}}
    <div class="footer-info">
        @if($invoice->notes_client)
            <strong>Conditions :</strong> {{ $invoice->notes_client }}<br>
        @else
            <strong>Conditions :</strong> {{ config('billing.payment_terms_default') }}<br>
        @endif

        @php $bank = config('billing.bank'); @endphp
        @if($bank['name'] || $bank['iban'] || $bank['rib'])
            <br><strong>Coordonnées bancaires :</strong>
            @if($bank['name']) {{ $bank['name'] }}@endif
            @if($bank['iban']) · IBAN {{ $bank['iban'] }}@endif
            @if($bank['rib']) · RIB {{ $bank['rib'] }}@endif
            @if($bank['swift']) · SWIFT {{ $bank['swift'] }}@endif
            <br>
        @endif

        <div class="footer-legal">{{ config('billing.legal_mentions') }}</div>
    </div>

</div>
</body>
</html>

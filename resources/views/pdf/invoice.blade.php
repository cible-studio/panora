<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Facture {{ $invoice->reference }}</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    @page { margin: 0; size: A4 portrait; }

    body {
        font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
        font-size: 11px;
        color: #1f2937;
        background: #ffffff;
        line-height: 1.45;
    }

    .wrap { padding: 22mm 18mm 28mm; }

    /* HEADER ─────────────────────────────────────── */
    .head { width: 100%; margin-bottom: 24px; }
    .head td { vertical-align: middle; }
    .head .logo-cell { width: 50%; }
    .head .logo-cell img { height: 38px; }
    .head .ref-cell { width: 50%; text-align: right; }
    .head .doc-label {
        font-size: 10px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 2.5px;
        margin-bottom: 4px;
    }
    .head .doc-num {
        font-family: monospace, 'DejaVu Sans Mono', sans-serif;
        font-size: 18px; font-weight: 800; color: #0f172a;
    }
    .head .doc-date {
        font-size: 10px; color: #64748b; margin-top: 4px;
    }

    /* THIN ACCENT BAR ───────────────────────────── */
    .accent-bar {
        height: 2px; background: #e8a020;
        width: 100%; margin: 6px 0 22px;
    }

    /* PARTIES ───────────────────────────────────── */
    .parties { width: 100%; border-collapse: separate; border-spacing: 12px 0; margin-bottom: 22px; }
    .parties .col { width: 50%; vertical-align: top; }
    .party {
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 8px; padding: 14px 16px;
    }
    .party .lbl {
        font-size: 8.5px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 1.4px;
        margin-bottom: 8px;
    }
    .party .name { font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
    .party .info { font-size: 10px; color: #64748b; line-height: 1.6; }

    /* CAMPAGNE LIGNE ────────────────────────────── */
    .campaign-line {
        background: #fff; border: 1px solid #e2e8f0;
        border-left: 3px solid #e8a020;
        border-radius: 6px; padding: 12px 16px;
        margin-bottom: 18px;
        font-size: 11px;
    }
    .campaign-line strong { color: #0f172a; }
    .campaign-line .meta {
        display: inline-block; margin-left: 14px;
        color: #94a3b8; font-size: 10px;
    }

    /* TABLE MONTANTS ────────────────────────────── */
    .lines {
        width: 100%; border-collapse: collapse;
        margin-bottom: 18px; font-size: 11px;
    }
    .lines thead th {
        background: #f1f5f9; color: #475569;
        font-size: 9px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1.2px;
        padding: 10px 14px; text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    .lines thead th.r { text-align: right; }
    .lines tbody td {
        padding: 12px 14px; border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .lines tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
    .lines .desc { font-weight: 600; color: #0f172a; }
    .lines .desc-sub { color: #94a3b8; font-size: 9.5px; margin-top: 3px; }

    /* TOTAUX ─────────────────────────────────────── */
    .totals { width: 56%; margin-left: 44%; border-collapse: collapse; }
    .totals td { padding: 8px 14px; font-size: 11px; }
    .totals .lbl { color: #64748b; text-align: right; }
    .totals .val { font-weight: 700; text-align: right; min-width: 130px; font-variant-numeric: tabular-nums; }
    .totals .sep td { border-bottom: 1px solid #e2e8f0; }
    .totals .grand td {
        background: #0f172a; color: #e8a020;
        font-size: 13px; font-weight: 800;
        border-radius: 4px;
    }

    /* STATUS PILL ────────────────────────────────── */
    .status-zone {
        text-align: right; margin-top: 14px;
    }
    .pill {
        display: inline-block; padding: 5px 14px;
        border-radius: 14px; font-size: 10px; font-weight: 700;
        letter-spacing: 0.4px;
    }
    .pill-paid    { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .pill-sent    { background: #dbeafe; color: #1d4ed8; border: 1px solid #93c5fd; }
    .pill-draft   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .pill-cancel  { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

    /* SIGNATURES ─────────────────────────────────── */
    .signatures {
        width: 100%; margin-top: 36px; border-collapse: separate; border-spacing: 28px 0;
    }
    .signatures td {
        width: 50%; vertical-align: top;
        border-top: 1px solid #cbd5e1;
        padding-top: 8px;
        font-size: 9.5px; color: #64748b;
    }
    .signatures td strong { color: #0f172a; font-weight: 700; }

    /* FOOTER ─────────────────────────────────────── */
    .footer {
        margin-top: 26px; padding-top: 12px;
        border-top: 1px solid #e2e8f0;
        text-align: center; font-size: 9px; color: #94a3b8;
        line-height: 1.6;
    }
    .footer strong { color: #475569; }

    /* MENTIONS LÉGALES BAS DE PAGE ──────────────── */
    .legal {
        margin-top: 14px; font-size: 9px; color: #94a3b8;
        background: #f8fafc; padding: 10px 14px;
        border-radius: 6px; line-height: 1.6;
    }
</style>
</head>
<body>

<div class="wrap">

    {{-- HEADER : LOGO PANORA + RÉFÉRENCE ─────────────── --}}
    <table class="head">
        <tr>
            <td class="logo-cell">
                @if(!empty($logoCibleLight))
                    <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:40px;">
                @else
                    <img src="{{ public_path('images/logol.png') }}" alt="CIBLE CI">
                @endif
                <div style="font-size:9px;color:#6b7280;margin-top:2px;">
                    {{ $operatorName ?? 'CIBLE CI' }} — Régie OOH
                </div>
            </td>
            <td class="ref-cell">
                <div class="doc-label">Facture</div>
                <div class="doc-num">{{ $invoice->reference }}</div>
                <div class="doc-date">Émise le {{ $invoice->issued_at->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="accent-bar"></div>

    {{-- ÉMETTEUR / DESTINATAIRE ───────────────────── --}}
    <table class="parties">
        <tr>
            <td class="col">
                <div class="party">
                    <div class="lbl">Émetteur</div>
                    <div class="name">{{ $operatorName ?? 'CIBLE CI' }}</div>
                    <div class="info">
                        Régie publicitaire OOH<br>
                        Abidjan, Côte d'Ivoire<br>
                        Plateforme <strong>Panora</strong>
                    </div>
                </div>
            </td>
            <td class="col">
                <div class="party">
                    <div class="lbl">Facturé à</div>
                    <div class="name">{{ $invoice->client?->name ?? '—' }}</div>
                    <div class="info">
                        @if($invoice->client?->contact_name){{ $invoice->client->contact_name }}<br>@endif
                        @if($invoice->client?->email){{ $invoice->client->email }}<br>@endif
                        @if($invoice->client?->phone){{ $invoice->client->phone }}@endif
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- CAMPAGNE LIÉE ─────────────────────────────── --}}
    @if($invoice->campaign)
    <div class="campaign-line">
        <strong>Campagne :</strong> {{ $invoice->campaign->name }}
        <span class="meta">
            {{ $invoice->campaign->start_date->format('d/m/Y') }}
            →
            {{ $invoice->campaign->end_date->format('d/m/Y') }}
            @if($invoice->campaign->total_panels)
                · {{ $invoice->campaign->total_panels }} panneau{{ $invoice->campaign->total_panels > 1 ? 'x' : '' }}
            @endif
        </span>
    </div>
    @endif

    {{-- DÉSIGNATIONS ──────────────────────────────── --}}
    <table class="lines">
        <thead>
            <tr>
                <th style="width:70%;">Désignation</th>
                <th class="r" style="width:30%;">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="desc">Prestation publicitaire OOH</div>
                    @if($invoice->campaign)
                    <div class="desc-sub">
                        Campagne {{ $invoice->campaign->name }} —
                        du {{ $invoice->campaign->start_date->format('d/m/Y') }}
                        au {{ $invoice->campaign->end_date->format('d/m/Y') }}
                    </div>
                    @endif
                </td>
                <td class="r">{{ number_format((float) $invoice->amount, 0, ',', ' ') }} FCFA</td>
            </tr>
        </tbody>
    </table>

    {{-- TOTAUX ────────────────────────────────────── --}}
    @php
        $tva = (float) $invoice->tva;
        $tvaAmount = (float) $invoice->amount * $tva / 100;
    @endphp
    <table class="totals">
        <tr>
            <td class="lbl">Sous-total HT</td>
            <td class="val">{{ number_format((float) $invoice->amount, 0, ',', ' ') }} FCFA</td>
        </tr>
        @if($tva > 0)
        <tr class="sep">
            <td class="lbl">TVA ({{ rtrim(rtrim(number_format($tva, 2, ',', ''), '0'), ',') }} %)</td>
            <td class="val">{{ number_format($tvaAmount, 0, ',', ' ') }} FCFA</td>
        </tr>
        @endif
        <tr class="grand">
            <td class="lbl" style="color:#cbd5e1;">Total TTC</td>
            <td class="val">{{ number_format((float) $invoice->amount_ttc, 0, ',', ' ') }} FCFA</td>
        </tr>
    </table>

    {{-- STATUT ────────────────────────────────────── --}}
    <div class="status-zone">
        @switch($invoice->status)
            @case('payee')
                <span class="pill pill-paid">
                    ✓ Payée @if($invoice->paid_at) le {{ $invoice->paid_at->format('d/m/Y') }} @endif
                </span>
                @break
            @case('envoyee')
                <span class="pill pill-sent">↗ Envoyée — En attente de règlement</span>
                @break
            @case('annulee')
                <span class="pill pill-cancel">✕ Annulée</span>
                @break
            @default
                <span class="pill pill-draft">Brouillon</span>
        @endswitch
    </div>

    {{-- SIGNATURES ────────────────────────────────── --}}
    <table class="signatures">
        <tr>
            <td>
                <strong>{{ $operatorName ?? 'CIBLE CI' }}</strong><br>
                Signature & cachet
            </td>
            <td>
                <strong>{{ $invoice->client?->name ?? 'Client' }}</strong><br>
                Signature & cachet
            </td>
        </tr>
    </table>

    {{-- MENTIONS LÉGALES ──────────────────────────── --}}
    <div class="legal">
        Règlement à réception. Tout retard de paiement entraînera l'application
        des pénalités prévues par la législation en vigueur. En cas de litige,
        compétence exclusive aux juridictions d'Abidjan, Côte d'Ivoire.
    </div>

    <div class="footer">
        @if(!empty($logoPanoraDark))
            <img src="{{ $logoPanoraDark }}" alt="Panora" style="height:18px;display:inline-block;vertical-align:middle;margin-right:6px;opacity:.85;">
        @endif
        Plateforme <strong>Panora</strong> · opérée par <strong>{{ $operatorName ?? 'CIBLE CI' }}</strong>
        — Régie publicitaire OOH · Abidjan, Côte d'Ivoire ·
        Document généré le {{ now()->format('d/m/Y \à H:i') }}
    </div>

</div>

</body>
</html>

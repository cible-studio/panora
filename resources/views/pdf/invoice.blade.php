<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', sans-serif; font-size:12px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:25px 30px; margin-bottom:30px;
        }
        .logo { font-size:24px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:10px; color:#8a90a2; }

        .content { padding:0 30px 60px; }

        .invoice-meta {
            display:grid; grid-template-columns:1fr 1fr;
            gap:20px; margin-bottom:25px;
        }

        .section {
            background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:16px; margin-bottom:20px;
        }
        .section-title {
            font-size:10px; font-weight:700; color:#e8a020;
            text-transform:uppercase; letter-spacing:1px;
            margin-bottom:12px; padding-bottom:6px;
            border-bottom:1px solid #e2e8f0;
        }

        .field { margin-bottom:8px; }
        .field-label { font-size:9px; color:#64748b; text-transform:uppercase; margin-bottom:2px; }
        .field-value { font-size:12px; font-weight:600; }

        .amount-table { width:100%; border-collapse:collapse; margin-top:20px; }
        .amount-table td { padding:10px 15px; border-bottom:1px solid #e2e8f0; }
        .amount-table .total-row td {
            background:#0a0c10; color:#e8a020;
            font-weight:800; font-size:14px;
        }

        .badge {
            display:inline-block; padding:3px 10px;
            border-radius:20px; font-size:10px; font-weight:700;
        }
        .badge-green { background:#dcfce7; color:#16a34a; }
        .badge-blue  { background:#dbeafe; color:#2563eb; }
        .badge-gray  { background:#f1f5f9; color:#475569; }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:10px 30px; background:#f8fafc;
            border-top:1px solid #e2e8f0;
            font-size:9px; color:#94a3b8;
            display:flex; justify-content:space-between;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
            <div>
                <div class="logo">CIBLE CI</div>
                <div class="logo-sub">RÉGIE OOH — CÔTE D'IVOIRE</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:20px; font-weight:800; color:#e8a020;">FACTURE</div>
                <div style="color:white; font-size:14px; margin-top:4px;">
                    {{ $invoice->reference }}
                </div>
                <div style="color:#8a90a2; font-size:10px; margin-top:4px;">
                    {{ $invoice->issued_at->format('d/m/Y') }}
                </div>
            </div>
        </div>
    </div>

    <div class="content">

        {{-- ÉMETTEUR / DESTINATAIRE --}}
        <div class="invoice-meta">
            <div class="section">
                <div class="section-title">📤 Émetteur</div>
                <div class="field">
                    <div class="field-value" style="font-size:14px;">CIBLE CI</div>
                    <div style="color:#64748b; font-size:11px; margin-top:4px;">Régie OOH</div>
                    <div style="color:#64748b; font-size:11px;">www.cible-ci.com</div>
                    <div style="color:#64748b; font-size:11px;">Abidjan, Côte d'Ivoire</div>
                </div>
            </div>
            <div class="section">
                <div class="section-title">📥 Destinataire</div>
                <div class="field">
                    <div class="field-value" style="font-size:14px;">{{ $invoice->client->name }}</div>
                    @if($invoice->client->contact_name)
                    <div style="color:#64748b; font-size:11px; margin-top:4px;">
                        {{ $invoice->client->contact_name }}
                    </div>
                    @endif
                    @if($invoice->client->email)
                    <div style="color:#64748b; font-size:11px;">{{ $invoice->client->email }}</div>
                    @endif
                    @if($invoice->client->phone)
                    <div style="color:#64748b; font-size:11px;">{{ $invoice->client->phone }}</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- DÉTAILS --}}
        @if($invoice->campaign)
        <div class="section">
            <div class="section-title">📢 Campagne concernée</div>
            <div class="field">
                <div class="field-label">Nom</div>
                <div class="field-value">{{ $invoice->campaign->name }}</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-top:8px;">
                <div class="field">
                    <div class="field-label">Date début</div>
                    <div class="field-value">{{ $invoice->campaign->start_date->format('d/m/Y') }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Date fin</div>
                    <div class="field-value">{{ $invoice->campaign->end_date->format('d/m/Y') }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Panneaux</div>
                    <div class="field-value">{{ $invoice->campaign->total_panels }}</div>
                </div>
            </div>
        </div>
        @endif

        {{-- MONTANTS --}}
        <table class="amount-table">
            <tr>
                <td style="background:#f8fafc; font-weight:600;">Désignation</td>
                <td style="background:#f8fafc;">Prestation publicitaire OOH</td>
            </tr>
            <tr>
                <td style="font-weight:600;">Montant HT</td>
                <td>{{ number_format($invoice->amount, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr>
                <td style="font-weight:600;">TVA ({{ $invoice->tva }}%)</td>
                <td>{{ number_format($invoice->amount * $invoice->tva / 100, 0, ',', ' ') }} FCFA</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL TTC</td>
                <td>{{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA</td>
            </tr>
        </table>

        {{-- STATUT --}}
        <div style="margin-top:20px; text-align:right;">
            @if($invoice->status === 'payee')
                <span class="badge badge-green" style="font-size:12px; padding:6px 16px;">
                    ✅ PAYÉE le {{ $invoice->paid_at?->format('d/m/Y') }}
                </span>
            @elseif($invoice->status === 'envoyee')
                <span class="badge badge-blue" style="font-size:12px; padding:6px 16px;">
                    📤 ENVOYÉE
                </span>
            @else
                <span class="badge badge-gray" style="font-size:12px; padding:6px 16px;">
                    BROUILLON
                </span>
            @endif
        </div>

        {{-- SIGNATURE --}}
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:40px; margin-top:40px;">
            <div>
                <div style="border-top:1px solid #cbd5e1; padding-top:8px;
                            font-size:10px; color:#64748b;">
                    Signature & Cachet Cible CI
                </div>
            </div>
            <div>
                <div style="border-top:1px solid #cbd5e1; padding-top:8px;
                            font-size:10px; color:#64748b;">
                    Signature Client — {{ $invoice->client->name }}
                </div>
            </div>
        </div>

    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div>CIBLE CI — Régie OOH — www.cible-ci.com</div>
        <div>{{ $invoice->reference }} — {{ $invoice->issued_at->format('d/m/Y') }}</div>
        <div>Document officiel</div>
    </div>

</body>
</html>

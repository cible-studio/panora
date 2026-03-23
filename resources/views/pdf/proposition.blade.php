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
        .logo-sub { font-size:10px; color:#8a90a2; margin-top:3px; }
        .doc-title { font-size:16px; color:white; margin-top:12px; font-weight:600; }
        .doc-num { font-size:13px; color:#e8a020; margin-top:4px; }

        .content { padding:0 30px 40px; }

        .section {
            background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:18px; margin-bottom:20px;
        }
        .section-title {
            font-size:10px; font-weight:700; color:#e8a020;
            text-transform:uppercase; letter-spacing:1px;
            margin-bottom:14px; padding-bottom:8px;
            border-bottom:1px solid #e2e8f0;
        }

        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; }

        .field { margin-bottom:10px; }
        .field-label { font-size:9px; color:#64748b; text-transform:uppercase; margin-bottom:3px; }
        .field-value { font-size:12px; font-weight:600; }

        .amount-box {
            background:#0a0c10; color:white;
            border-radius:10px; padding:20px;
            text-align:center; margin:20px 0;
        }
        .amount-label { font-size:10px; color:#8a90a2; margin-bottom:6px; }
        .amount-value { font-size:28px; font-weight:800; color:#e8a020; }

        .badge {
            display:inline-block; padding:3px 12px;
            border-radius:20px; font-size:10px; font-weight:700;
        }
        .badge-orange { background:#fef3c7; color:#d97706; }
        .badge-green  { background:#dcfce7; color:#16a34a; }
        .badge-red    { background:#fee2e2; color:#dc2626; }
        .badge-gray   { background:#f1f5f9; color:#475569; }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:10px 30px; background:#f8fafc;
            border-top:1px solid #e2e8f0;
            font-size:9px; color:#94a3b8;
            display:flex; justify-content:space-between;
        }

        .signature-box {
            display:grid; grid-template-columns:1fr 1fr; gap:40px;
            margin-top:40px;
        }
        .sign-line {
            border-top:1px solid #cbd5e1;
            padding-top:8px; font-size:10px; color:#64748b;
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
                <div class="doc-title">PROPOSITION COMMERCIALE</div>
                <div class="doc-num">{{ $proposition->numero }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:9px; color:#8a90a2;">Date d'émission</div>
                <div style="color:white; font-weight:600;">{{ $proposition->created_at->format('d/m/Y') }}</div>
                <div style="margin-top:8px; font-size:9px; color:#8a90a2;">Statut</div>
                @if($proposition->statut === 'en_attente')
                    <span class="badge badge-orange">En attente</span>
                @elseif($proposition->statut === 'acceptee')
                    <span class="badge badge-green">Acceptée</span>
                @elseif($proposition->statut === 'refusee')
                    <span class="badge badge-red">Refusée</span>
                @else
                    <span class="badge badge-gray">Expirée</span>
                @endif
            </div>
        </div>
    </div>

    <div class="content">

        {{-- CLIENT --}}
        <div class="section">
            <div class="section-title">🏢 Informations client</div>
            <div class="grid-2">
                <div class="field">
                    <div class="field-label">Nom / Société</div>
                    <div class="field-value">{{ $proposition->client->name }}</div>
                </div>
                @if($proposition->client->contact_name)
                <div class="field">
                    <div class="field-label">Contact</div>
                    <div class="field-value">{{ $proposition->client->contact_name }}</div>
                </div>
                @endif
                @if($proposition->client->email)
                <div class="field">
                    <div class="field-label">Email</div>
                    <div class="field-value">{{ $proposition->client->email }}</div>
                </div>
                @endif
                @if($proposition->client->phone)
                <div class="field">
                    <div class="field-label">Téléphone</div>
                    <div class="field-value">{{ $proposition->client->phone }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- DÉTAILS --}}
        <div class="section">
            <div class="section-title">📋 Détails de la proposition</div>
            <div class="grid-3">
                <div class="field">
                    <div class="field-label">Nombre de panneaux</div>
                    <div class="field-value">{{ $proposition->nb_panneaux }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Date de début</div>
                    <div class="field-value">{{ $proposition->date_debut->format('d/m/Y') }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Date de fin</div>
                    <div class="field-value">{{ $proposition->date_fin->format('d/m/Y') }}</div>
                </div>
                <div class="field">
                    <div class="field-label">Durée</div>
                    <div class="field-value">
                        {{ $proposition->date_debut->diffInDays($proposition->date_fin) }} jours
                    </div>
                </div>
                <div class="field">
                    <div class="field-label">Commercial</div>
                    <div class="field-value">{{ $proposition->creator->name }}</div>
                </div>
            </div>
        </div>

        {{-- MONTANT --}}
        <div class="amount-box">
            <div class="amount-label">MONTANT TOTAL DE LA PROPOSITION</div>
            <div class="amount-value">{{ number_format($proposition->montant, 0, ',', ' ') }} FCFA</div>
            <div style="font-size:10px; color:#8a90a2; margin-top:6px;">
                TVA 18% incluse
            </div>
        </div>

        {{-- NOTES --}}
        @if($proposition->notes)
        <div class="section">
            <div class="section-title">📝 Notes et remarques</div>
            <div style="color:#475569; line-height:1.6;">{{ $proposition->notes }}</div>
        </div>
        @endif

        {{-- SIGNATURES --}}
        <div class="signature-box">
            <div>
                <div class="sign-line">Signature Cible CI</div>
                <div style="font-size:10px; color:#64748b; margin-top:4px;">
                    {{ $proposition->creator->name }}
                </div>
            </div>
            <div>
                <div class="sign-line">Signature Client</div>
                <div style="font-size:10px; color:#64748b; margin-top:4px;">
                    {{ $proposition->client->name }}
                </div>
            </div>
        </div>

    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div>CIBLE CI — Régie OOH — www.cible-ci.com</div>
        <div>{{ $proposition->numero }} — Généré le {{ now()->format('d/m/Y') }}</div>
        <div>Document confidentiel</div>
    </div>

</body>
</html>

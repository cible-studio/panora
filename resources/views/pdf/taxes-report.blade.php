<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', sans-serif; font-size:10px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:15px 20px; margin-bottom:20px;
        }
        .logo { font-size:18px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:9px; color:#8a90a2; }

        table { width:100%; border-collapse:collapse; }
        th {
            background:#0a0c10; color:#e8a020;
            padding:8px; text-align:left;
            font-size:9px; text-transform:uppercase;
        }
        td { padding:8px; border-bottom:1px solid #f1f5f9; }
        tr:nth-child(even) td { background:#f8fafc; }

        .badge {
            display:inline-block; padding:2px 8px;
            border-radius:10px; font-size:9px; font-weight:700;
        }
        .badge-green  { background:#dcfce7; color:#16a34a; }
        .badge-orange { background:#fef3c7; color:#d97706; }
        .badge-red    { background:#fee2e2; color:#dc2626; }
        .badge-blue   { background:#dbeafe; color:#2563eb; }
        .badge-purple { background:#f3e8ff; color:#7c3aed; }

        .total-row td {
            background:#0a0c10 !important;
            color:#e8a020; font-weight:700;
            border-top:2px solid #e8a020;
        }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:8px 20px; background:#f8fafc;
            border-top:1px solid #e2e8f0;
            font-size:8px; color:#94a3b8;
            display:flex; justify-content:space-between;
        }
    </style>
</head>
<body>

    <div class="header">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="logo">CIBLE CI</div>
                <div class="logo-sub">RAPPORT TAXES COMMUNES</div>
                @if($commune)
                <div style="color:white; margin-top:4px; font-size:10px;">
                    Commune : {{ $commune->name }}
                </div>
                @endif
            </div>
            <div style="text-align:right; color:#8a90a2; font-size:9px;">
                <div>{{ $taxes->count() }} taxes</div>
                <div>{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <div style="padding:0 20px 40px;">
        <table>
            <thead>
                <tr>
                    <th>Commune</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Montant</th>
                    <th>Échéance</th>
                    <th>Payée le</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($taxes as $tax)
                <tr>
                    <td><strong>{{ $tax->commune->name }}</strong></td>
                    <td>
                        @if($tax->type === 'odp')
                            <span class="badge badge-blue">ODP</span>
                        @else
                            <span class="badge badge-purple">TM</span>
                        @endif
                    </td>
                    <td>{{ $tax->year }}</td>
                    <td style="font-weight:600; color:#e8a020;">
                        {{ number_format($tax->amount, 0, ',', ' ') }} FCFA
                    </td>
                    <td>{{ $tax->due_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $tax->paid_at?->format('d/m/Y') ?? '—' }}</td>
                    <td>
                        @if($tax->status === 'payee')
                            <span class="badge badge-green">Payée</span>
                        @elseif($tax->status === 'en_retard')
                            <span class="badge badge-red">En retard</span>
                        @else
                            <span class="badge badge-orange">En attente</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" style="text-align:center; padding:20px; color:#94a3b8;">
                        Aucune taxe
                    </td>
                </tr>
                @endforelse

                {{-- TOTAL --}}
                @if($taxes->count() > 0)
                <tr class="total-row">
                    <td colspan="3">TOTAL</td>
                    <td>{{ number_format($taxes->sum('amount'), 0, ',', ' ') }} FCFA</td>
                    <td colspan="3"></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="footer">
        <div>CIBLE CI — Document confidentiel</div>
        <div>Généré le {{ now()->format('d/m/Y à H:i') }}</div>
        <div>{{ $taxes->count() }} taxes au total</div>
    </div>

</body>
</html>

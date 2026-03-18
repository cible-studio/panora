<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a2e; }

        .header {
            background: #0a0c10;
            color: white;
            padding: 15px 20px;
            margin-bottom: 20px;
        }
        .logo { font-size: 18px; font-weight: 800; color: #e8a020; }
        .logo-sub { font-size: 9px; color: #8a90a2; }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #0a0c10;
            color: #e8a020;
            padding: 8px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
        tr:nth-child(even) td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }
        .badge-green  { background: #dcfce7; color: #16a34a; }
        .badge-orange { background: #fef3c7; color: #d97706; }
        .badge-blue   { background: #dbeafe; color: #2563eb; }
        .badge-red    { background: #fee2e2; color: #dc2626; }
        .badge-gray   { background: #f1f5f9; color: #475569; }

        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 8px 20px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    <div class="header">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <div class="logo">CIBLE CI</div>
                <div class="logo-sub">LISTE DES PANNEAUX
                    @if($commune) — {{ strtoupper($commune->name) }} @endif
                </div>
            </div>
            <div style="text-align:right; color:#8a90a2; font-size:9px;">
                <div>{{ $panels->count() }} panneaux</div>
                <div>{{ now()->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <div style="padding: 0 20px 40px;">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Catégorie</th>
                    <th>Tarif/mois</th>
                    <th>Statut</th>
                    <th>Éclairé</th>
                </tr>
            </thead>
            <tbody>
                @forelse($panels as $panel)
                <tr>
                    <td style="font-family:monospace; color:#e8a020; font-weight:700;">
                        {{ $panel->reference }}
                    </td>
                    <td>{{ $panel->name }}</td>
                    <td>{{ $panel->commune->name }}</td>
                    <td>{{ $panel->format->name }}</td>
                    <td>{{ $panel->category?->name ?? '—' }}</td>
                    <td style="font-weight:600;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </td>
                    <td>
                        @if($panel->status->value === 'libre')
                            <span class="badge badge-green">Libre</span>
                        @elseif($panel->status->value === 'option')
                            <span class="badge badge-orange">Option</span>
                        @elseif($panel->status->value === 'confirme')
                            <span class="badge badge-blue">Confirmé</span>
                        @elseif($panel->status->value === 'occupe')
                            <span class="badge badge-blue">Occupé</span>
                        @else
                            <span class="badge badge-red">Maintenance</span>
                        @endif
                    </td>
                    <td>{{ $panel->is_lit ? '💡 Oui' : 'Non' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" style="text-align:center; padding:20px; color:#94a3b8;">
                        Aucun panneau trouvé
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer">
        <div>CIBLE CI — Document confidentiel</div>
        <div>Généré le {{ now()->format('d/m/Y à H:i') }}</div>
        <div>{{ $panels->count() }} panneaux au total</div>
    </div>

</body>
</html>

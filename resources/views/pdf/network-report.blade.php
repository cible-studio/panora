<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1a1a2e; }

        .header {
            background: #0a0c10;
            color: white;
            padding: 25px 30px;
            margin-bottom: 25px;
        }
        .logo { font-size: 24px; font-weight: 800; color: #e8a020; }
        .logo-sub { font-size: 10px; color: #8a90a2; margin-top: 3px; }
        .report-title { font-size: 14px; color: white; margin-top: 10px; }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 25px;
            padding: 0 30px;
        }
        .stat-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-label { font-size: 9px; color: #64748b; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { font-size: 22px; font-weight: 800; color: #e8a020; }

        .commune-section { padding: 0 30px; margin-bottom: 20px; }
        .commune-title {
            background: #0a0c10;
            color: #e8a020;
            padding: 8px 15px;
            font-weight: 700;
            font-size: 12px;
            border-radius: 6px 6px 0 0;
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            background: #1e2330;
            color: #8a90a2;
            padding: 7px 10px;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }
        td { padding: 7px 10px; border-bottom: 1px solid #f1f5f9; font-size: 10px; }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: 700;
        }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-red   { background: #fee2e2; color: #dc2626; }

        .footer {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            padding: 8px 30px;
            background: #0a0c10;
            color: #8a90a2;
            font-size: 8px;
            display: flex;
            justify-content: space-between;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="logo">CIBLE CI</div>
        <div class="logo-sub">RÉGIE OOH — CÔTE D'IVOIRE</div>
        <div class="report-title">📊 Rapport Réseau Panneaux — {{ now()->format('d/m/Y') }}</div>
    </div>

    {{-- STATS GLOBALES --}}
    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-label">Total panneaux</div>
            <div class="stat-value">{{ $totalPanneaux }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Disponibles</div>
            <div class="stat-value" style="color:#22c55e;">{{ $panneauxLibres }}</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Taux occupation</div>
            <div class="stat-value">{{ $tauxOccupation }}%</div>
        </div>
    </div>

    {{-- PAR COMMUNE --}}
    @foreach($communes as $commune)
    @if($commune->panels->count() > 0)
    <div class="commune-section">
        <div class="commune-title">
            📍 {{ strtoupper($commune->name) }}
            — {{ $commune->panels->count() }} panneau(x)
        </div>
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Format</th>
                    <th>Tarif/mois</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commune->panels as $panel)
                <tr>
                    <td style="font-family:monospace; color:#e8a020; font-weight:700;">
                        {{ $panel->reference }}
                    </td>
                    <td>{{ $panel->name }}</td>
                    <td>{{ $panel->format->name }}</td>
                    <td style="font-weight:600;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </td>
                    <td>
                        @if($panel->status->value === 'libre')
                            <span class="badge badge-green">Libre</span>
                        @else
                            <span class="badge badge-red">Occupé</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

    {{-- FOOTER --}}
    <div class="footer">
        <div>CIBLE CI — Document confidentiel — Ne pas diffuser</div>
        <div>Généré le {{ now()->format('d/m/Y à H:i') }}</div>
        <div>www.cible-ci.com</div>
    </div>

</body>
</html>

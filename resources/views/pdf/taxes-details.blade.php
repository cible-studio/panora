<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Taxes — {{ $periodLabel }}</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', sans-serif; font-size:9px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:14px 18px; margin-bottom:14px;
        }
        .logo { font-size:18px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:9px; color:#8a90a2; }
        .header-meta { margin-top:6px; font-size:10px; }

        .kpi-grid {
            display:flex; gap:8px; padding:0 18px; margin-bottom:14px;
        }
        .kpi {
            flex:1; padding:8px 10px; border-radius:6px;
            background:#f8fafc; border-left:3px solid #e8a020;
        }
        .kpi .lbl { font-size:8px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#6b7280; }
        .kpi .val { font-size:13px; font-weight:800; color:#0a0c10; margin-top:2px; }

        table { width:100%; border-collapse:collapse; font-size:8.5px; }
        thead th {
            background:#0a0c10; color:#e8a020;
            padding:7px 6px; text-align:left;
            font-size:8px; text-transform:uppercase; letter-spacing:.3px;
        }
        tbody td { padding:6px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        tbody tr:nth-child(even) td { background:#fafbfc; }
        .right { text-align:right; }
        .mono { font-family:'DejaVu Sans Mono', monospace; }

        .badge {
            display:inline-block; padding:1px 6px;
            border-radius:8px; font-size:8px; font-weight:700;
        }
        .badge-tm  { background:#dcfce7; color:#16a34a; }
        .badge-odp { background:#fed7aa; color:#ea580c; }
        .badge-db  { background:#dbeafe; color:#2563eb; }

        .total-row td {
            background:#0a0c10 !important;
            color:#e8a020; font-weight:800; padding:10px 6px;
            font-size:10px;
        }

        .commune-group {
            background:#fef3c7; font-weight:800; font-size:10px;
            padding:8px 6px !important; color:#92400e;
        }

        .footer {
            position:fixed; bottom:8px; left:0; right:0;
            font-size:8px; color:#9ca3af; text-align:center;
        }
        .footer-note {
            padding:10px 18px; margin-top:14px;
            background:#f8fafc; border-left:3px solid #e8a020;
            font-size:8.5px; color:#475569;
        }
    </style>
</head>
<body>

<div class="header">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            @if(!empty($logoCibleDark))
                <img src="{{ $logoCibleDark }}" alt="CIBLE CI" style="height:34px;margin-bottom:4px;">
            @else
                <div class="logo">CIBLE CI</div>
            @endif
            <div class="logo-sub">Détail des taxes communales · {{ $operatorName ?? 'CIBLE CI' }}</div>
        </div>
        <div style="text-align:right; font-size:9px;">
            <div>{{ $periodLabel }}</div>
            <div style="color:#8a90a2;">Édité le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    @if(!empty($filterSummary))
    <div class="header-meta">
        <strong>Filtres :</strong> {{ $filterSummary }}
    </div>
    @endif
</div>

<div class="kpi-grid">
    <div class="kpi"><div class="lbl">Total</div><div class="val">{{ number_format($totals['total'], 0, ',', ' ') }} FCFA</div></div>
    <div class="kpi"><div class="lbl">TM</div><div class="val">{{ number_format($totals['by_type']['tm']  ?? 0, 0, ',', ' ') }}</div></div>
    <div class="kpi"><div class="lbl">ODP</div><div class="val">{{ number_format($totals['by_type']['odp'] ?? 0, 0, ',', ' ') }}</div></div>
    <div class="kpi"><div class="lbl">DB</div><div class="val">{{ number_format($totals['by_type']['db']  ?? 0, 0, ',', ' ') }}</div></div>
    <div class="kpi"><div class="lbl">Panneaux</div><div class="val">{{ $totals['panels_count'] }}</div></div>
    <div class="kpi"><div class="lbl">Lignes</div><div class="val">{{ $totals['lines_count'] }}</div></div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:90px;">Panneau</th>
            <th style="width:65px;">Dim.</th>
            <th style="width:40px;">Type</th>
            <th>Client</th>
            <th>Campagne</th>
            <th style="width:130px;">Période</th>
            <th style="width:120px;" class="right">Tarif</th>
            <th style="width:80px;" class="right">Montant</th>
        </tr>
    </thead>
    <tbody>
    @php
        $currentCommune = null;
    @endphp
    @foreach($lines as $row)
        @if($currentCommune !== $row['commune'])
            @php $currentCommune = $row['commune']; @endphp
            <tr><td colspan="8" class="commune-group">📍 {{ $row['commune'] }}</td></tr>
        @endif
        <tr>
            <td class="mono"><strong>{{ $row['reference'] }}</strong></td>
            <td>{{ $row['dimensions'] }}<br><span style="color:#9ca3af; font-size:7.5px;">{{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }} m²</span></td>
            <td><span class="badge badge-{{ $row['type'] }}">{{ strtoupper($row['type']) }}</span></td>
            <td>{{ $row['client_name'] ?? '—' }}</td>
            <td>{{ $row['campaign_name'] ?? '—' }}</td>
            <td style="font-size:8px; color:#475569;">
                {{ $row['period_start']->format('d/m/Y') }}<br>
                → {{ $row['period_end']->format('d/m/Y') }}
            </td>
            <td class="right mono" style="font-size:7.5px; color:#6b7280;">
                {{ number_format($row['rate'], 0) }} × {{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }}m² × {{ $row['months'] }}m
            </td>
            <td class="right mono"><strong>{{ number_format($row['amount'], 0, ',', ' ') }}</strong></td>
        </tr>
    @endforeach
        <tr class="total-row">
            <td colspan="7" class="right">TOTAL GÉNÉRAL :</td>
            <td class="right mono">{{ number_format($totals['total'], 0, ',', ' ') }} FCFA</td>
        </tr>
    </tbody>
</table>

<div class="footer-note">
    💡 Chaque montant est justifiable : <strong>tarif × surface (m²) × nombre de mois</strong>.
    Les tarifs appliqués reflètent l'<strong>historique tarifaire</strong> de chaque commune à la date de la période —
    document fiable pour les contrôles administratifs.
</div>

<div class="footer">
    Plateforme <strong>Panora</strong> · opérée par <strong>{{ $operatorName ?? 'CIBLE CI' }}</strong>
    — Document généré automatiquement, taxes communales {{ $periodLabel }}
</div>

</body>
</html>

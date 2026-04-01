<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size:10px; color:#1e293b; background:#fff; }
        .header { background:#0f172a; color:#fff; padding:14px 20px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center; }
        .header h1 { font-size:16px; font-weight:bold; color:#e8a020; }
        .header p { font-size:9px; color:#94a3b8; margin-top:3px; }
        table { width:100%; border-collapse:collapse; }
        thead tr { background:#f1f5f9; }
        th { padding:7px 8px; text-align:left; font-size:9px; font-weight:700; text-transform:uppercase; color:#64748b; border-bottom:2px solid #e2e8f0; }
        td { padding:6px 8px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
        tr:nth-child(even) td { background:#f8fafc; }
        .ref { font-family:monospace; font-weight:700; font-size:11px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:10px; font-size:9px; font-weight:600; }
        .libre     { background:#dcfce7; color:#166534; }
        .occupe    { background:#fee2e2; color:#991b1b; }
        .option_periode { background:#fef3c7; color:#92400e; }
        .maintenance { background:#f1f5f9; color:#475569; }
        .footer { margin-top:16px; text-align:center; font-size:8px; color:#94a3b8; border-top:1px solid #e2e8f0; padding-top:8px; }
        .tarif { font-weight:700; color:#e8a020; }
    </style>
</head>
<body>
<div class="header">
    <div>
        <h1>CIBLE CI — Liste des Disponibilités</h1>
        <p>Généré le {{ $generated }} · {{ count($panels) }} panneau(x)</p>
    </div>
    <div style="text-align:right;font-size:9px;color:#94a3b8;">
        Régie Publicitaire OOH
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Réf.</th>
            <th>Emplacement</th>
            <th>Commune / Zone</th>
            <th>Format</th>
            <th>Dims</th>
            <th>💡</th>
            <th>Tarif/mois</th>
            <th>Trafic/j</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        @foreach($panels as $p)
        @php
            $statusLabel = match($p['display_status']) {
                'libre'          => ['label'=>'Disponible', 'class'=>'libre'],
                'occupe'         => ['label'=>'Occupé',     'class'=>'occupe'],
                'option_periode' => ['label'=>'En option',  'class'=>'option_periode'],
                'maintenance'    => ['label'=>'Maintenance','class'=>'maintenance'],
                default          => ['label'=>ucfirst($p['display_status']), 'class'=>'libre'],
            };
        @endphp
        <tr>
            <td><span class="ref">{{ $p['reference'] }}</span></td>
            <td>{{ $p['name'] }}</td>
            <td>{{ $p['commune'] }}{{ $p['zone'] !== '—' ? ' · '.$p['zone'] : '' }}</td>
            <td>{{ $p['format'] }}</td>
            <td>{{ $p['dimensions'] ?? '—' }}</td>
            <td>{{ $p['is_lit'] ? '💡' : '' }}</td>
            <td class="tarif">{{ $p['monthly_rate'] ? number_format($p['monthly_rate'], 0, ',', ' ').' FCFA' : '—' }}</td>
            <td>{{ $p['daily_traffic'] ? number_format($p['daily_traffic']).' contacts' : '—' }}</td>
            <td><span class="badge {{ $statusLabel['class'] }}">{{ $statusLabel['label'] }}</span></td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    CIBLE CI · Régie Publicitaire · Document confidentiel · {{ $generated }}
</div>
</body>
</html>
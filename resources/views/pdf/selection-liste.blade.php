<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',sans-serif; font-size:10px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:16px 20px;
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:20px;
        }
        .logo { font-size:20px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:9px; color:#8a90a2; margin-top:2px; }
        .header-right { text-align:right; font-size:10px; color:#8a90a2; }
        .header-right strong { color:white; font-size:13px; display:block; }

        .content { padding:0 20px 60px; }

        .summary {
            display:flex; gap:20px;
            background:#f8fafc; border:1px solid #e2e8f0;
            border-radius:8px; padding:12px 16px;
            margin-bottom:16px;
        }
        .summary-item { text-align:center; }
        .summary-label { font-size:9px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
        .summary-value { font-size:16px; font-weight:800; color:#e8a020; }

        table { width:100%; border-collapse:collapse; }
        thead tr { background:#0a0c10; }
        th {
            color:#e8a020; padding:9px 8px;
            text-align:left; font-size:9px;
            text-transform:uppercase; letter-spacing:.6px;
        }
        td { padding:8px; border-bottom:1px solid #f1f5f9; font-size:10px; vertical-align:middle; }
        tr:nth-child(even) td { background:#f8fafc; }

        .ref { font-family:monospace; font-weight:800; color:#e8a020; font-size:11px; }
        .commune { color:#64748b; font-size:9px; margin-top:2px; }

        .badge {
            display:inline-block; padding:2px 8px;
            border-radius:10px; font-size:9px; font-weight:700;
        }
        .badge-green  { background:#dcfce7; color:#16a34a; }
        .badge-orange { background:#fef3c7; color:#d97706; }
        .badge-purple { background:#f3e8ff; color:#7c3aed; }
        .badge-red    { background:#fee2e2; color:#dc2626; }
        .badge-gray   { background:#f1f5f9; color:#475569; }

        .price { font-weight:700; color:#e8a020; text-align:right; }
        .total-row td { background:#0a0c10 !important; color:white; font-weight:700; }
        .total-row .total-price { color:#e8a020; font-size:13px; font-weight:800; }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:8px 20px;
            background:#f8fafc; border-top:1px solid #e2e8f0;
            font-size:8px; color:#94a3b8;
            display:flex; justify-content:space-between;
        }
    </style>
</head>
<body>

<div class="header">
    <div>
        <div class="logo">CIBLE CI</div>
        <div class="logo-sub">GIE OOH — Régie Publicitaire</div>
    </div>
    <div class="header-right">
        <strong>Liste de sélection — {{ count($panels) }} panneau(x)</strong>
        Généré le {{ now()->format('d/m/Y à H:i') }}
        @if($startDate && $endDate)
        <br>Période : {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
        au {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        ({{ $dureeEnMois }} mois)
        @endif
    </div>
</div>

<div class="content">

    {{-- Résumé --}}
    <div class="summary">
        <div class="summary-item">
            <div class="summary-label">Panneaux</div>
            <div class="summary-value">{{ count($panels) }}</div>
        </div>
        <div class="summary-item">
            <div class="summary-label">Total / mois</div>
            <div class="summary-value">{{ number_format($totalMensuel, 0, ',', ' ') }} FCFA</div>
        </div>
        @if($startDate && $endDate)
        <div class="summary-item">
            <div class="summary-label">Total période ({{ $dureeEnMois }} mois)</div>
            <div class="summary-value">{{ number_format($totalPeriode, 0, ',', ' ') }} FCFA</div>
        </div>
        @endif
    </div>

    {{-- Tableau --}}
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Référence</th>
                <th>Désignation</th>
                <th>Commune</th>
                <th>Format</th>
                <th>Éclairé</th>
                <th>Statut</th>
                <th style="text-align:right;">Tarif/mois</th>
                @if($startDate && $endDate)
                <th style="text-align:right;">Total période</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($panels as $i => $panel)
            @php
                $statusBadge = match($panel->status->value) {
                    'libre'       => ['class'=>'badge-green',  'label'=>'Disponible'],
                    'option'      => ['class'=>'badge-orange', 'label'=>'Option'],
                    'confirme'    => ['class'=>'badge-purple', 'label'=>'Confirmé'],
                    'occupe'      => ['class'=>'badge-purple', 'label'=>'Occupé'],
                    'maintenance' => ['class'=>'badge-red',    'label'=>'Maintenance'],
                    default       => ['class'=>'badge-gray',   'label'=>ucfirst($panel->status->value)],
                };
            @endphp
            <tr>
                <td style="color:#94a3b8;">{{ $i + 1 }}</td>
                <td><span class="ref">{{ $panel->reference }}</span></td>
                <td>
                    {{ $panel->name }}
                    @if($panel->category)
                    <div style="font-size:9px;color:#94a3b8;">{{ $panel->category->name }}</div>
                    @endif
                </td>
                <td>
                    {{ $panel->commune?->name ?? '—' }}
                    @if($panel->quartier)
                    <div class="commune">{{ $panel->quartier }}</div>
                    @endif
                </td>
                <td>
                    {{ $panel->format?->name ?? '—' }}
                    @if($panel->format?->width && $panel->format?->height)
                    <div style="font-size:9px;color:#94a3b8;">
                        {{ $panel->format->width }}×{{ $panel->format->height }}m
                    </div>
                    @endif
                </td>
                <td style="text-align:center;">{{ $panel->is_lit ? '💡' : '—' }}</td>
                <td><span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span></td>
                <td class="price">{{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA</td>
                @if($startDate && $endDate)
                <td class="price">{{ number_format($panel->monthly_rate * $dureeEnMois, 0, ',', ' ') }} FCFA</td>
                @endif
            </tr>
            @endforeach

            {{-- Ligne total --}}
            <tr class="total-row">
                <td colspan="{{ $startDate && $endDate ? 7 : 7 }}" style="color:white;font-size:10px;">
                    TOTAL — {{ count($panels) }} panneau(x)
                </td>
                <td class="total-price" style="text-align:right;">
                    {{ number_format($totalMensuel, 0, ',', ' ') }} FCFA
                </td>
                @if($startDate && $endDate)
                <td class="total-price" style="text-align:right;">
                    {{ number_format($totalPeriode, 0, ',', ' ') }} FCFA
                </td>
                @endif
            </tr>
        </tbody>
    </table>
</div>

<div class="footer">
    <span>CIBLE CI — GIE OOH</span>
    <span>Document confidentiel — {{ now()->format('d/m/Y') }}</span>
</div>

</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',sans-serif; font-size:11px; color:#1a1a2e; background:#fff; }

        .header {
            background:#0a0c10; color:white;
            padding:16px 24px;
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:20px;
        }
        .logo { font-size:20px; font-weight:800; color:#e8a020; }
        .logo-sub { font-size:9px; color:#8a90a2; margin-top:2px; }
        .header-right { text-align:right; font-size:10px; color:#8a90a2; }
        .header-right strong { color:white; font-size:13px; display:block; }

        .grid {
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:16px;
            padding:0 24px 24px;
        }

        .card {
            border:1px solid #e2e8f0;
            border-radius:10px;
            overflow:hidden;
            page-break-inside:avoid;
        }

        .card-img {
            width:100%; height:160px;
            object-fit:cover;
            display:block;
        }
        .card-img-placeholder {
            width:100%; height:160px;
            background:#1a1a2e;
            display:flex; align-items:center; justify-content:center;
            font-size:40px;
        }

        .card-body { padding:12px; }

        .ref {
            font-family:monospace; font-size:13px;
            font-weight:800; color:#e8a020;
            margin-bottom:4px;
        }
        .name {
            font-size:12px; font-weight:600; color:#1a1a2e;
            margin-bottom:8px;
        }

        .tags { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
        .tag {
            font-size:9px; padding:2px 7px;
            border-radius:10px; font-weight:600;
            background:#f1f5f9; color:#475569;
        }

        .row { display:flex; justify-content:space-between; margin-bottom:3px; }
        .label { font-size:9px; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; }
        .value { font-size:10px; font-weight:600; color:#1a1a2e; }

        .price {
            margin-top:8px; padding-top:8px;
            border-top:1px solid #f1f5f9;
            font-size:15px; font-weight:800; color:#e8a020;
            text-align:right;
        }
        .price span { font-size:9px; font-weight:400; color:#94a3b8; }

        .badge {
            display:inline-block; padding:2px 8px;
            border-radius:10px; font-size:9px; font-weight:700;
        }
        .badge-green  { background:#dcfce7; color:#16a34a; }
        .badge-orange { background:#fef3c7; color:#d97706; }
        .badge-purple { background:#f3e8ff; color:#7c3aed; }
        .badge-red    { background:#fee2e2; color:#dc2626; }
        .badge-gray   { background:#f1f5f9; color:#475569; }

        .footer {
            position:fixed; bottom:0; left:0; right:0;
            padding:8px 24px;
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
        <strong>Sélection de panneaux</strong>
        {{ count($panels) }} panneau(x) · Généré le {{ now()->format('d/m/Y à H:i') }}
        @if($startDate && $endDate)
        <br>Période : {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
        @endif
    </div>
</div>

<div class="grid">
@foreach($panels as $panel)
@php
    $photo = $panel->photos->sortBy('ordre')->first();
    $statusBadge = match($panel->status->value) {
        'libre'       => ['class'=>'badge-green',  'label'=>'Disponible'],
        'option'      => ['class'=>'badge-orange', 'label'=>'Option'],
        'confirme'    => ['class'=>'badge-purple', 'label'=>'Confirmé'],
        'occupe'      => ['class'=>'badge-purple', 'label'=>'Occupé'],
        'maintenance' => ['class'=>'badge-red',    'label'=>'Maintenance'],
        default       => ['class'=>'badge-gray',   'label'=>ucfirst($panel->status->value)],
    };
@endphp
<div class="card">
    @if($photo)
        <img class="card-img" src="{{ storage_path('app/public/' . ltrim($photo->path, '/')) }}">
    @else
        <div class="card-img-placeholder">🪧</div>
    @endif

    <div class="card-body">
        <div class="ref">{{ $panel->reference }}</div>
        <div class="name">{{ $panel->name }}</div>

        <div class="tags">
            @if($panel->category)
                <span class="tag">{{ $panel->category->name }}</span>
            @endif
            @if($panel->format)
                <span class="tag">{{ $panel->format->name }}</span>
            @endif
            @if($panel->is_lit)
                <span class="tag">💡 Éclairé</span>
            @endif
        </div>

        <div class="row">
            <span class="label">Commune</span>
            <span class="value">{{ $panel->commune?->name ?? '—' }}</span>
        </div>
        @if($panel->quartier)
        <div class="row">
            <span class="label">Quartier</span>
            <span class="value">{{ $panel->quartier }}</span>
        </div>
        @endif
        @if($panel->format?->width && $panel->format?->height)
        <div class="row">
            <span class="label">Dimensions</span>
            <span class="value">{{ $panel->format->width }}m × {{ $panel->format->height }}m</span>
        </div>
        @endif
        <div class="row" style="margin-top:4px;">
            <span class="label">Statut</span>
            <span class="badge {{ $statusBadge['class'] }}">{{ $statusBadge['label'] }}</span>
        </div>

        <div class="price">
            {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
            <span>/ mois</span>
        </div>
    </div>
</div>
@endforeach
</div>

<div class="footer">
    <span>CIBLE CI — GIE OOH</span>
    <span>Document confidentiel — {{ now()->format('d/m/Y') }}</span>
</div>

</body>
</html>

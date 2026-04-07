<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIBLE CI - Fiche Panneau</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', 'Segoe UI', Arial, sans-serif;
            background: #0a0c15;
            padding: 20px;
        }

        /* Page container */
        .panel-page {
            max-width: 1100px;
            margin: 0 auto 30px auto;
            background: #ffffff;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            page-break-after: always;
            break-inside: avoid;
            transition: transform 0.2s;
        }

        /* Premium Header */
        .premium-header {
            background: linear-gradient(135deg, #0a0c15 0%, #1a1a2e 100%);
            padding: 28px 32px;
            position: relative;
            overflow: hidden;
        }

        .premium-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(232,160,32,0.08) 0%, transparent 70%);
            border-radius: 50%;
        }

        .premium-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #e8a020, #fbbf24, #e8a020);
        }

        .logo-section h1 {
            font-size: 28px;
            font-weight: 800;
            background: linear-gradient(135deg, #e8a020, #fbbf24);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        .logo-section p {
            font-size: 10px;
            color: #6b7280;
            margin-top: 4px;
            letter-spacing: 1px;
        }

        .badge-tech {
            background: rgba(232,160,32,0.12);
            border: 1px solid rgba(232,160,32,0.3);
            padding: 6px 16px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            color: #e8a020;
        }

        /* Hero Section avec image */
        .hero-section {
            display: flex;
            flex-wrap: wrap;
            gap: 32px;
            padding: 32px;
            background: #ffffff;
        }

        .image-container {
            flex: 1.2;
            min-width: 280px;
        }

        .image-frame {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 20px;
            padding: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .panel-image {
            width: 100%;
            height: auto;
            max-height: 320px;
            object-fit: contain;
            border-radius: 12px;
            display: block;
        }

        .no-image {
            width: 100%;
            height: 280px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            opacity: 0.6;
        }

        /* Info Container */
        .info-container {
            flex: 1.8;
            min-width: 320px;
        }

        /* Status Ribbon */
        .status-ribbon {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }

        .status-libre { background: #10b981; color: white; box-shadow: 0 2px 8px rgba(16,185,129,0.3); }
        .status-occupe { background: #ef4444; color: white; box-shadow: 0 2px 8px rgba(239,68,68,0.3); }
        .status-option { background: #f59e0b; color: white; box-shadow: 0 2px 8px rgba(245,158,11,0.3); }
        .status-maintenance { background: #6b7280; color: white; box-shadow: 0 2px 8px rgba(107,114,128,0.3); }
        .status-confirme { background: #8b5cf6; color: white; box-shadow: 0 2px 8px rgba(139,92,246,0.3); }

        .reference-badge {
            font-family: 'Courier New', monospace;
            font-size: 13px;
            font-weight: 700;
            color: #e8a020;
            background: rgba(232,160,32,0.08);
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid rgba(232,160,32,0.2);
            display: inline-block;
            margin-left: 12px;
        }

        .panel-title {
            font-size: 22px;
            font-weight: 800;
            color: #0a0c15;
            line-height: 1.3;
            margin: 16px 0 20px 0;
            padding-bottom: 12px;
            border-bottom: 2px solid #f1f5f9;
        }

        /* Info Grid Premium */
        .info-grid-premium {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            background: #f8fafc;
            border-radius: 20px;
            padding: 20px;
            margin: 20px 0;
        }

        .info-card {
            background: white;
            border-radius: 14px;
            padding: 12px 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .info-label {
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
        }

        .info-value.large {
            font-size: 18px;
            color: #e8a020;
        }

        /* Price Card */
        .price-card {
            background: linear-gradient(135deg, #0a0c15, #1a1a2e);
            border-radius: 20px;
            padding: 20px 24px;
            margin: 20px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            position: relative;
            overflow: hidden;
        }

        .price-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(232,160,32,0.15) 0%, transparent 70%);
            border-radius: 50%;
        }

        .price-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: #9ca3af;
        }

        .price-amount {
            font-size: 32px;
            font-weight: 800;
            color: #e8a020;
            line-height: 1;
        }

        .price-sub {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        .daily-rate {
            text-align: right;
        }

        .daily-rate .value {
            font-size: 18px;
            font-weight: 700;
            color: #fbbf24;
        }

        /* Features */
        .features {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin: 16px 0;
        }

        .feature-tag {
            background: #f1f5f9;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Location Description */
        .location-desc {
            background: linear-gradient(135deg, #fefce8, #fef9c3);
            border-left: 4px solid #e8a020;
            padding: 14px 18px;
            border-radius: 14px;
            margin-top: 16px;
        }

        .location-desc p {
            font-size: 12px;
            color: #854d0e;
            line-height: 1.5;
        }

        /* Footer */
        .premium-footer {
            background: #f8fafc;
            padding: 16px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 8px;
            color: #94a3b8;
        }

        .footer-left {
            display: flex;
            gap: 20px;
        }

        .footer-right {
            font-family: monospace;
        }

        /* Print Optimization */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .panel-page {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
                page-break-after: always;
            }
            .premium-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .status-ribbon, .price-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .panel-page {
            animation: fadeInUp 0.4s ease-out;
        }
    </style>
</head>
<body>

@foreach($panels as $index => $panel)
@php
    $photo = $panel->photos->sortBy('ordre')->first();
    
    // Status configuration
    $statusClass = match($panel->status->value) {
        'libre'       => 'status-libre',
        'option'      => 'status-option',
        'confirme'    => 'status-confirme',
        'occupe'      => 'status-occupe',
        'maintenance' => 'status-maintenance',
        default       => 'status-libre',
    };
    
    $statusLabel = match($panel->status->value) {
        'libre'       => '✓ DISPONIBLE',
        'option'      => '⏳ EN OPTION',
        'confirme'    => '🔒 CONFIRMÉ',
        'occupe'      => '🔴 OCCUPÉ',
        'maintenance' => '🔧 MAINTENANCE',
        default       => strtoupper($panel->status->value),
    };
    
    // Format dimensions
    $width = $panel->format?->width ? rtrim(rtrim(number_format($panel->format->width, 2, '.', ''), '0'), '.') : null;
    $height = $panel->format?->height ? rtrim(rtrim(number_format($panel->format->height, 2, '.', ''), '0'), '.') : null;
    $dimensions = ($width && $height) ? "{$width}m × {$height}m" : '—';
    $surface = ($width && $height) ? round($width * $height, 2) . ' m²' : '—';
    
    // Monthly rate
    $monthlyRate = number_format($panel->monthly_rate ?? 0, 0, ',', ' ');
    $dailyRate = $panel->monthly_rate ? number_format(($panel->monthly_rate / 30), 0, ',', ' ') : '0';
    
    // Traffic
    $traffic = ($panel->daily_traffic ?? 0) > 0 ? number_format($panel->daily_traffic, 0, ',', ' ') . ' véhicules/jour' : 'Non renseigné';
@endphp

<div class="panel-page">
    {{-- Premium Header --}}
    <div class="premium-header">
        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div class="logo-section">
                <h1>CIBLE CI</h1>
                <p>RÉGIE PUBLICITAIRE & OUT-OF-HOME</p>
            </div>
            <div class="badge-tech">
                FICHE TECHNIQUE
            </div>
        </div>
    </div>

    {{-- Hero Section --}}
    <div class="hero-section">
        {{-- Image Container --}}
        <div class="image-container">
            <div class="image-frame">
                @if($photo && file_exists(storage_path('app/public/' . ltrim($photo->path, '/'))))
                    <img src="data:image/{{ pathinfo($photo->path, PATHINFO_EXTENSION) }};base64,{{ base64_encode(file_get_contents(storage_path('app/public/' . ltrim($photo->path, '/')))) }}" 
                         alt="{{ $panel->reference }}"
                         class="panel-image">
                @else
                    <div class="no-image">
                        🪧
                    </div>
                @endif
            </div>
        </div>

        {{-- Info Container --}}
        <div class="info-container">
            {{-- Status & Reference --}}
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div class="status-ribbon {{ $statusClass }}">
                    {{ $statusLabel }}
                </div>
                <div class="reference-badge">
                    {{ $panel->reference }}
                </div>
            </div>

            {{-- Title --}}
            <h2 class="panel-title">{{ $panel->name }}</h2>

            {{-- Premium Info Grid --}}
            <div class="info-grid-premium">
                <div class="info-card">
                    <div class="info-label">📍 COMMUNE</div>
                    <div class="info-value">{{ $panel->commune?->name ?? '—' }}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">🗺️ ZONE</div>
                    <div class="info-value">{{ $panel->zone?->name ?? '—' }}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">📐 DIMENSIONS</div>
                    <div class="info-value">{{ $dimensions }}</div>
                    <div style="font-size: 10px; color: #94a3b8; margin-top: 2px;">{{ $surface }}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">📏 FORMAT</div>
                    <div class="info-value">{{ $panel->format?->name ?? '—' }}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">💡 ÉCLAIRAGE</div>
                    <div class="info-value">{{ $panel->is_lit ? '✅ LED Éclairé' : '🌙 Non éclairé' }}</div>
                </div>
                <div class="info-card">
                    <div class="info-label">🚗 TRAFIC</div>
                    <div class="info-value">{{ $traffic }}</div>
                </div>
            </div>

            {{-- Features Tags --}}
            <div class="features">
                @if($panel->category)
                <span class="feature-tag">🏷️ {{ $panel->category->name }}</span>
                @endif
                @if($panel->format?->name)
                <span class="feature-tag">📦 {{ $panel->format->name }}</span>
                @endif
                @if($panel->is_lit)
                <span class="feature-tag">⚡ Éclairé 24/7</span>
                @endif
                @if($panel->daily_traffic > 5000)
                <span class="feature-tag">🔥 Forte affluence</span>
                @endif
            </div>
        </div>
    </div>

    {{-- Price Section --}}
    <div style="padding: 0 32px 20px 32px;">
        <div class="price-card">
            <div>
                <div class="price-label">TARIF MENSUEL HT</div>
                <div class="price-amount">{{ $monthlyRate }} <span style="font-size: 14px;">FCFA</span></div>
                <div class="price-sub">Hors taxes et frais techniques</div>
            </div>
            <div class="daily-rate">
                <div class="price-label">SOIT</div>
                <div class="value">{{ $dailyRate }} FCFA</div>
                <div class="price-sub">par jour</div>
            </div>
        </div>
    </div>

    {{-- Location Description --}}
    @if($panel->zone_description || $panel->quartier || $panel->adresse)
    <div style="padding: 0 32px 24px 32px;">
        <div class="location-desc">
            <p>
                <strong style="display: inline-block; margin-right: 8px;">📍 EMPLACEMENT STRATÉGIQUE</strong><br>
                {{ $panel->zone_description ?? $panel->quartier ?? $panel->adresse ?? 'Emplacement premium en zone à forte visibilité' }}
            </p>
        </div>
    </div>
    @endif

    {{-- Premium Footer --}}
    <div class="premium-footer">
        <div class="footer-left">
            <span>🔒 Document confidentiel</span>
            <span>© CIBLE CI - Tous droits réservés</span>
        </div>
        <div class="footer-right">
            Généré le {{ now()->format('d/m/Y à H:i') }}
        </div>
    </div>
</div>
@endforeach

</body>
</html>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <style>
        /* ═══════════════════════════════════════════════════════════════
           CIBLE CI — Fiche Panneau (1 page = 1 panneau)
           Chartre graphique : Rouge (#e20613) + Doré (#e8a020)
           ═══════════════════════════════════════════════════════════════ */

        @page {
            margin: 15mm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            background: #fff;
            line-height: 1.4;
        }

        /* ── PAGE ── */
        .page {
            page-break-after: always;
            position: relative;
            min-height: 277mm;
        }

        .page:last-child {
            page-break-after: avoid;
        }

        /* ── HEADER AVEC LOGO ── */
        .header {
            border-bottom: 3px solid #e20613;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .header-table {
            width: 100%;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-img {
            width: 45px;
            height: auto;
        }

        .logo-text {
            font-size: 16px;
            font-weight: 800;
            color: #e20613;
            letter-spacing: 1px;
        }

        .logo-sub {
            font-size: 7px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-title {
            font-size: 11px;
            font-weight: 700;
            color: #e20613;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .doc-date {
            font-size: 7px;
            color: #64748b;
            margin-top: 3px;
        }

        /* ── RÉFÉRENCE ── */
        .ref-code {
            font-size: 14px;
            font-weight: 800;
            color: #e20613;
            font-family: monospace;
            margin: 6px 0 3px 0;
        }

        .ref-name {
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 10px;
        }

        /* ── IMAGE GRANDE ── */
        .photo-box {
            text-align: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 8px;
            margin: 8px 0;
        }

        .photo-img {
            width: 100%;
            max-height: 140mm;
            height: auto;
            object-fit: contain;
        }

        .photo-placeholder {
            padding: 55px;
            background: #f1f5f9;
            text-align: center;
        }

        .photo-placeholder-text {
            font-size: 10px;
            color: #94a3b8;
        }

        /* ── BADGE STATUT ── */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            font-size: 9px;
            font-weight: 700;
            border-radius: 4px;
            margin: 6px 0;
        }

        .status-libre { background: #22c55e; color: #fff; }
        .status-occupe { background: #ef4444; color: #fff; }
        .status-option { background: #e8a020; color: #1e293b; }
        .status-maintenance { background: #64748b; color: #fff; }
        .status-confirme { background: #3b82f6; color: #fff; }

        /* ── TABLE INFOS ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .info-table td {
            padding: 6px 5px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }

        .info-label {
            width: 35%;
            font-weight: 700;
            color: #475569;
            background: #f8fafc;
        }

        .info-value {
            width: 65%;
            font-weight: 500;
        }

        /* ── DESCRIPTION ── */
        .extra-box {
            background: #fefce8;
            border-left: 3px solid #e20613;
            padding: 8px 10px;
            margin: 10px 0;
            font-size: 9px;
        }

        .extra-title {
            font-weight: 700;
            color: #e20613;
            margin-bottom: 4px;
        }

        .extra-text {
            color: #475569;
            line-height: 1.4;
        }

        /* ── FOOTER FIXE ── */
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            font-size: 7px;
            text-align: center;
            color: #94a3b8;
        }
    </style>
</head>

<body>

@php
    use Carbon\Carbon;

    $fmtDate    = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
    $totalCount = count($panels);

    $statusMap = fn($s) => match ($s) {
        'libre'                    => ['label' => 'Disponible',  'class' => 'status-libre'],
        'occupe'                   => ['label' => 'Occupé',      'class' => 'status-occupe'],
        'option_periode', 'option' => ['label' => 'En option',   'class' => 'status-option'],
        'confirme'                 => ['label' => 'Confirmé',    'class' => 'status-confirme'],
        'maintenance'              => ['label' => 'Maintenance', 'class' => 'status-maintenance'],
        default                    => ['label' => 'Indisponible','class' => 'status-occupe'],
    };

    // Logo : provient de PdfAssets::getLogoPdf() via $logoSrc.
    // Fallback inline si la vue est rendue sans la variable (compat ascendante).
    if (!isset($logoSrc)) {
        $logoPath = public_path('images/logol.png');
        $logoSrc = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50">'
                .'<rect width="180" height="50" rx="6" fill="#0d1117"/>'
                .'<text x="90" y="34" font-family="Arial" font-weight="900" font-size="20" fill="#e8a020" text-anchor="middle">CIBLE CI</text>'
                .'</svg>'
              );
    }
@endphp

@foreach ($panels as $index => $p)
    @php
        $pageNum  = $index + 1;
        $status   = $statusMap($p['display_status'] ?? 'occupe');
        $traffic  = (int) ($p['daily_traffic'] ?? 0);
        $zoneDesc = $p['zone_description'] ?? '';

        // Photo : on utilise photo_src (base64 ou URL fallback) fournie par enrichPanel().
        // Compat ascendante avec ancienne clé photo_path (chemin local) :
        $imgSrc = $p['photo_src'] ?? null;
        if (!$imgSrc && !empty($p['photo_path']) && file_exists($p['photo_path'])) {
            $ext  = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
                default => 'image/jpeg',
            };
            $imgSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p['photo_path']));
        } elseif (!$imgSrc && !empty($p['photo_url'])) {
            $imgSrc = $p['photo_url'];
        }

        $commune   = $p['commune']    ?? '—';
        $zone      = $p['zone']       ?? '—';
        $format    = $p['format']     ?? '—';
        $dims      = $p['dimensions'] ?? null;
        $category  = $p['category']   ?? '—';
        $isLit     = (bool) ($p['is_lit'] ?? false);
        $latitude  = $p['latitude']   ?? null;
        $longitude = $p['longitude']  ?? null;
    @endphp

    <div class="page">

        <div class="header">
            <table class="header-table">
                <tr>
                    <td class="header-left">
                        <div class="logo-container">
                            <img src="{{ $logoSrc }}" class="logo-img" alt="CIBLE CI">
                            <div>
                                <div class="logo-text">CIBLE CI</div>
                                <div class="logo-sub">Régie Publicitaire OOH</div>
                            </div>
                        </div>
                    </td>
                    <td class="header-right">
                        <div class="doc-title">FICHE PANNEAU</div>
                        <div class="doc-date">{{ $generated ?? now()->format('d/m/Y H:i') }} | {{ $pageNum }}/{{ $totalCount }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="ref-code">{{ $p['reference'] ?? '—' }}</div>
        <div class="ref-name">{{ $p['name'] ?? '' }}</div>

        <div class="photo-box">
            @if ($imgSrc)
                <img src="{{ $imgSrc }}" class="photo-img" alt="{{ $p['reference'] }}">
            @else
                <div class="photo-placeholder">
                    <div class="photo-placeholder-text">📷 Aucune image disponible</div>
                </div>
            @endif
        </div>

        @if (empty($hideStatus ?? false))
            <div style="text-align: right;">
                <span class="status-badge {{ $status['class'] }}">{{ $status['label'] }}</span>
            </div>
        @endif

        <table class="info-table">
            <tr>
                <td class="info-label">📍 Localisation</td>
                <td class="info-value">{{ $commune }}</td>
            </tr>
            <tr>
                <td class="info-label">🗺️ Zone géographique</td>
                <td class="info-value">{{ $zone }}</td>
            </tr>
            <tr>
                <td class="info-label">📏 Format</td>
                <td class="info-value">{{ $format }}@if ($dims) ({{ $dims }}) @endif</td>
            </tr>
            <tr>
                <td class="info-label">📐 Dimensions</td>
                <td class="info-value">{{ $dims ?? '—' }}</td>
            </tr>
            <tr>
                <td class="info-label">🏷️ Catégorie</td>
                <td class="info-value">{{ $category }}</td>
            </tr>
            <tr>
                <td class="info-label">💡 Éclairage</td>
                <td class="info-value">{{ $isLit ? 'LED Éclairé' : 'Non éclairé' }}</td>
            </tr>
            @if ($traffic > 0)
                <tr>
                    <td class="info-label">🚗 Trafic estimé</td>
                    <td class="info-value">{{ number_format($traffic, 0, ',', ' ') }} véhicules/jour</td>
                </tr>
            @endif
            @if ($latitude && $longitude)
                <tr>
                    <td class="info-label">📍 Coordonnées GPS</td>
                    <td class="info-value">
                        <span style="font-family:monospace;font-size:9px;">{{ number_format($latitude, 6, '.', '') }}, {{ number_format($longitude, 6, '.', '') }}</span>
                        @if(!empty($p['gps_link']))
                            <br><a href="{{ $p['gps_link'] }}" style="color:#2563eb;text-decoration:none;font-size:9px;">📍 Voir sur Google Maps</a>
                        @endif
                    </td>
                </tr>
            @endif
        </table>

        @if ($zoneDesc)
            <div class="extra-box">
                <div class="extra-title">📝 Description de l'emplacement</div>
                <div class="extra-text">{{ \Illuminate\Support\Str::limit($zoneDesc, 250) }}</div>
            </div>
        @endif

        <div class="footer">
            CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire
        </div>

    </div>
@endforeach

</body>
</html>
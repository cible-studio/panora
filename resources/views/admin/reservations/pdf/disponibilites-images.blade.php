<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ═══════════════════════════════════════════════════════════════
   CIBLE CI — Fiche Disponibilité PREMIUM
   Version optimisée avec :
     - Images haute qualité (ratio préservé)
     - Structure d'informations enrichie
     - Design moderne et élégant
     - Compatible DomPDF
   ═══════════════════════════════════════════════════════════════ */

* { margin:0; padding:0; box-sizing:border-box; }

@page { 
    margin: 0; 
    size: A4 portrait; 
}

body {
    font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
    font-size: 11px;
    color: #1a1a1a;
    background: #e8e8e8;
    width: 210mm;
}

/* ── PAGE (1 panneau = 1 page) ── */
.page {
    width: 210mm;
    min-height: 297mm;
    page-break-after: always;
    position: relative;
    background: #ffffff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
    margin-bottom: 5mm;
}
.page:last-child { page-break-after: avoid; }

/* ═══════════════════════════════════════════════════════════════
   PAGE DE COUVERTURE PREMIUM
   ═══════════════════════════════════════════════════════════════ */
.cover-page {
    width: 210mm;
    min-height: 297mm;
    page-break-after: always;
    position: relative;
    background: linear-gradient(135deg, #0a0c15 0%, #1a1a2e 100%);
}

.cover-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" opacity="0.03"><path fill="none" stroke="%23e8a020" stroke-width="0.5" d="M10 10 L90 10 M10 20 L90 20 M10 30 L90 30 M10 40 L90 40 M10 50 L90 50 M10 60 L90 60 M10 70 L90 70 M10 80 L90 80 M10 90 L90 90 M10 10 L10 90 M20 10 L20 90 M30 10 L30 90 M40 10 L40 90 M50 10 L50 90 M60 10 L60 90 M70 10 L70 90 M80 10 L80 90 M90 10 L90 90"/></svg>') repeat;
    pointer-events: none;
}

.cover-date {
    font-size: 10px;
    color: rgba(255,255,255,0.5);
    text-align: right;
    padding: 10mm 12mm 0;
    position: relative;
    z-index: 2;
}

.cover-sep {
    height: 2px;
    background: linear-gradient(90deg, transparent, #e8a020, transparent);
    margin: 6mm 12mm 0;
    position: relative;
    z-index: 2;
}

.cover-center {
    position: relative;
    z-index: 2;
    padding-top: 70mm;
    text-align: center;
}

.cover-title-box {
    background: rgba(255,255,255,0.05);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(232,160,32,0.3);
    border-radius: 20px;
    padding: 12mm 15mm;
    display: inline-block;
    width: 160mm;
}

.cover-title {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #e8a020, #fbbf24);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.cover-subtitle {
    font-size: 11px;
    color: #e8a020;
    font-weight: 600;
    margin-top: 8px;
    letter-spacing: 2px;
}

.cover-period {
    margin-top: 10mm;
    padding: 4mm 8mm;
    background: rgba(232,160,32,0.1);
    border-radius: 30px;
    display: inline-block;
    font-size: 11px;
    color: #e8a020;
    font-weight: 600;
}

.cover-count {
    margin-top: 12mm;
    font-size: 14px;
    color: rgba(255,255,255,0.7);
    font-weight: 600;
}

.cover-count-number {
    font-size: 32px;
    font-weight: 800;
    color: #e8a020;
    display: inline-block;
    margin-right: 5px;
}

.cover-footer {
    position: absolute;
    bottom: 12mm;
    left: 0;
    right: 0;
    text-align: center;
    font-size: 9px;
    color: rgba(255,255,255,0.4);
    z-index: 2;
}

.cover-logo {
    font-size: 16px;
    font-weight: 800;
    color: #e8a020;
    margin-bottom: 3px;
}

/* ═══════════════════════════════════════════════════════════════
   EN-TÊTE COMMUNE PREMIUM
   ═══════════════════════════════════════════════════════════════ */
.commune-header {
    background: linear-gradient(135deg, #0a0c15, #1a1a2e);
    padding: 6mm 10mm;
    border-bottom: 3px solid #e8a020;
}

.commune-table { width: 100%; }

.commune-left {
    vertical-align: middle;
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: 0.5px;
}

.commune-left span {
    color: #e8a020;
    font-size: 16px;
    font-weight: 800;
}

.cible-logo {
    font-size: 13px;
    font-weight: 800;
    color: #e8a020;
    letter-spacing: 1px;
}

/* ═══════════════════════════════════════════════════════════════
   ZONE PHOTO PREMIUM — Ratio préservé
   ═══════════════════════════════════════════════════════════════ */
.photo-zone {
    height: 185mm;
    background: #f8fafc;
    position: relative;
    overflow: hidden;
    border-bottom: 1px solid #e8e8e8;
}

.photo-img {
    width: 100%;
    height: 185mm;
    object-fit: contain;
    background: #f5f5f5;
}

.photo-placeholder {
    height: 185mm;
    background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
}

.photo-placeholder-ref {
    font-size: 24px;
    font-weight: 800;
    color: #c0c0c0;
    font-family: monospace;
    margin-bottom: 8px;
}

.photo-placeholder-txt {
    font-size: 12px;
    color: #a0a0a0;
}

/* ═══════════════════════════════════════════════════════════════
   INFORMATIONS STRUCTURÉES — Design moderne
   ═══════════════════════════════════════════════════════════════ */
.info-section {
    padding: 6mm 10mm;
    background: #ffffff;
}

.info-grid {
    width: 100%;
    border-collapse: collapse;
}

.info-grid td {
    padding: 3mm 4mm;
    vertical-align: top;
}

.info-label {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #6b7280;
    display: block;
    margin-bottom: 2px;
}

.info-value {
    font-size: 12px;
    font-weight: 600;
    color: #1a1a2e;
    line-height: 1.4;
}

.info-value.code {
    font-family: monospace;
    font-size: 13px;
    color: #e8a020;
    background: rgba(232,160,32,0.08);
    padding: 2px 6px;
    border-radius: 4px;
    display: inline-block;
}

.info-value.dimensions {
    font-family: monospace;
    font-size: 12px;
}

/* Badge statut */
.status-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-libre { background: #10b981; color: white; }
.status-occupe { background: #ef4444; color: white; }
.status-option { background: #f59e0b; color: white; }
.status-maintenance { background: #6b7280; color: white; }
.status-confirme { background: #8b5cf6; color: white; }

/* Section tarif */
.price-section {
    background: linear-gradient(135deg, #fefce8, #fef9c3);
    border-left: 4px solid #e8a020;
    padding: 4mm 8mm;
    margin-top: 4mm;
    border-radius: 8px;
}

.price-label {
    font-size: 9px;
    font-weight: 700;
    text-transform: uppercase;
    color: #92400e;
    letter-spacing: 1px;
}

.price-amount {
    font-size: 20px;
    font-weight: 800;
    color: #e8a020;
}

.price-sub {
    font-size: 10px;
    color: #6b7280;
}

/* Description zone */
.zone-desc {
    background: #f8fafc;
    padding: 4mm 8mm;
    margin-top: 4mm;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.zone-desc p {
    font-size: 10px;
    color: #475569;
    line-height: 1.5;
}

/* ═══════════════════════════════════════════════════════════════
   PIED DE PAGE PREMIUM
   ═══════════════════════════════════════════════════════════════ */
.page-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 3mm 10mm;
    background: #f8fafc;
    border-top: 1px solid #e2e8f0;
}

.footer-table { width: 100%; }

.footer-left {
    font-size: 8px;
    color: #6b7280;
    vertical-align: middle;
}

.footer-center {
    text-align: center;
    font-size: 8px;
    color: #94a3b8;
    vertical-align: middle;
}

.footer-right {
    text-align: right;
    font-size: 10px;
    font-weight: 700;
    color: #e8a020;
    vertical-align: middle;
}

/* Utilitaires */
.text-right { text-align: right; }
.mt-2 { margin-top: 2mm; }
.mb-2 { margin-bottom: 2mm; }
</style>
</head>
<body>

@php
use Carbon\Carbon;

$totalPanels = $panels->count();
$generated   = now()->format('d/m/Y');
$grouped = $panels->groupBy(fn($p) => $p['commune'] ?? '—');
$pageNum = 1;
@endphp

{{-- ═══════════════════════════════════════════════════════════════
     PAGE DE COUVERTURE PREMIUM
     ═══════════════════════════════════════════════════════════════ --}}
<div class="cover-page">
    <div class="cover-overlay"></div>
    <div class="cover-date">{{ $generated }}</div>
    <div class="cover-sep"></div>

    <div class="cover-center">
        <div class="cover-title-box">
            <div class="cover-title">LISTE DES PANNEAUX</div>
            <div class="cover-subtitle">CIBLE CI — Régie Publicitaire</div>
        </div>

        @if(!empty($startDate) && !empty($endDate))
        <div class="cover-period">
            📅 {{ Carbon::parse($startDate)->format('d/m/Y') }} — {{ Carbon::parse($endDate)->format('d/m/Y') }}
        </div>
        @endif

        <div class="cover-count">
            <span class="cover-count-number">{{ $totalPanels }}</span> panneau(x)
        </div>
    </div>

    <div class="cover-footer">
        <div class="cover-logo">CIBLE CI</div>
        <div>Régie Publicitaire · Abidjan, Côte d'Ivoire</div>
        <div style="margin-top: 2px;">1/{{ $totalPanels + 1 }}</div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════
     PAGES PANNEAUX PREMIUM
     ═══════════════════════════════════════════════════════════════ --}}
@foreach($grouped as $communeName => $communePanels)
@foreach($communePanels as $p)
@php
    $pageNum++;
    $code    = $p['reference'] ?? '—';
    $name    = $p['name'] ?? '—';
    $commune = $p['commune'] ?? '—';
    $format  = $p['format'] ?? null;
    $dims    = $p['dimensions'] ?? null;
    
    // Calcul de la surface
    $width = $p['format_width'] ?? null;
    $height = $p['format_height'] ?? null;
    $formatM2 = ($width && $height) ? round((float)$width * (float)$height, 2) : null;
    
    // Dimensions formatées
    $dimensionsFormatted = $dims ? str_replace('×', 'x', $dims) : '—';
    if ($width && $height) {
        $dimensionsFormatted = $width . 'm × ' . $height . 'm';
    }
    
    // Surface formatée
    $surfaceFormatted = $formatM2 ? number_format($formatM2, 2, ',', ' ') . ' m²' : '—';
    
    // Désignation complète
    $designation = $name;
    if ($dimensionsFormatted && $dimensionsFormatted !== '—') {
        $designation .= ' - ' . $dimensionsFormatted;
    }
    if (!empty($p['zone_description'])) {
        $designation .= ' - ' . $p['zone_description'];
    }
    
    // Statut
    $statusValue = $p['display_status'] ?? $p['status_db'] ?? 'libre';
    $statusClass = match($statusValue) {
        'libre' => 'status-libre',
        'occupe', 'occupied' => 'status-occupe',
        'option', 'option_periode' => 'status-option',
        'maintenance' => 'status-maintenance',
        'confirme' => 'status-confirme',
        default => 'status-libre',
    };
    $statusLabel = match($statusValue) {
        'libre' => '✓ DISPONIBLE',
        'occupe', 'occupied' => '🔴 OCCUPÉ',
        'option', 'option_periode' => '⏳ EN OPTION',
        'maintenance' => '🔧 MAINTENANCE',
        'confirme' => '🔒 CONFIRMÉ',
        default => strtoupper($statusValue),
    };
    
    // Tarif
    $monthlyRate = isset($p['monthly_rate']) ? number_format((float)$p['monthly_rate'], 0, ',', ' ') : '0';
    $dailyRate = isset($p['monthly_rate']) ? number_format((float)$p['monthly_rate'] / 30, 0, ',', ' ') : '0';
    
    // Trafic
    $traffic = isset($p['daily_traffic']) && (int)$p['daily_traffic'] > 0 
        ? number_format((int)$p['daily_traffic'], 0, ',', ' ') . ' véhicules/jour' 
        : 'Non renseigné';
    
    // Éclairage
    $isLit = $p['is_lit'] ?? false;
    $lightingIcon = $isLit ? '💡' : '🌙';
    $lightingText = $isLit ? 'LED Éclairé' : 'Non éclairé';
    
    // Image
    $imgSrc = null;
    if (!empty($p['photo_path']) && file_exists($p['photo_path'])) {
        $ext = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
        $mime = match($ext) { 
            'png' => 'image/png', 
            'webp' => 'image/webp', 
            'gif' => 'image/gif', 
            default => 'image/jpeg' 
        };
        $b64 = base64_encode(file_get_contents($p['photo_path']));
        $imgSrc = "data:{$mime};base64,{$b64}";
    } elseif (!empty($p['photo_url'])) {
        $imgSrc = $p['photo_url'];
    }
@endphp

<div class="page">
    {{-- EN-TÊTE PREMIUM --}}
    <div class="commune-header">
        <table class="commune-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="commune-left">
                    📍 COMMUNE: <span>{{ strtoupper($communeName) }}</span>
                </td>
                <td class="text-right">
                    <span class="cible-logo">CIBLE CI</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- PHOTO PRINCIPALE --}}
    <div class="photo-zone">
        @if($imgSrc)
            <img src="{{ $imgSrc }}" class="photo-img" alt="{{ $code }}">
        @else
            <div class="photo-placeholder">
                <span class="photo-placeholder-ref">🪧 {{ $code }}</span>
                <span class="photo-placeholder-txt">Image non disponible</span>
            </div>
        @endif
    </div>

    {{-- INFORMATIONS STRUCTURÉES --}}
    <div class="info-section">
        <table class="info-grid" cellpadding="0" cellspacing="0">
            <tr>
                <td width="30%">
                    <div class="info-label">📌 CODE</div>
                    <div class="info-value code">{{ $code }}</div>
                </td>
                <td width="40%">
                    <div class="info-label">📊 STATUT</div>
                    <div class="info-value">
                        <span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                </td>
                <td width="30%">
                    <div class="info-label">🏷️ FORMAT</div>
                    <div class="info-value">{{ $format ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="3" class="mt-2">
                    <div class="info-label">📍 DÉSIGNATION</div>
                    <div class="info-value">{{ $designation }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">🏙️ COMMUNE</div>
                    <div class="info-value">{{ strtoupper($commune) }}</div>
                </td>
                <td>
                    <div class="info-label">📐 DIMENSIONS</div>
                    <div class="info-value dimensions">{{ $dimensionsFormatted }}</div>
                </td>
                <td>
                    <div class="info-label">📏 SURFACE</div>
                    <div class="info-value">{{ $surfaceFormatted }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">{{ $lightingIcon }} ÉCLAIRAGE</div>
                    <div class="info-value">{{ $lightingText }}</div>
                </td>
                <td>
                    <div class="info-label">🚗 TRAFIC</div>
                    <div class="info-value">{{ $traffic }}</div>
                </td>
                <td>
                    <div class="info-label">🗺️ ZONE</div>
                    <div class="info-value">{{ $p['zone'] ?? '—' }}</div>
                </td>
            </tr>
        </table>

        {{-- SECTION TARIF --}}
        <div class="price-section">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50%">
                        <div class="price-label">💰 TARIF MENSUEL HT</div>
                        <div class="price-amount">{{ $monthlyRate }} FCFA</div>
                        <div class="price-sub">Hors taxes et frais techniques</div>
                    </td>
                    <td width="50%" class="text-right">
                        <div class="price-label">📅 SOIT PAR JOUR</div>
                        <div class="price-amount" style="font-size: 18px;">{{ $dailyRate }} FCFA</div>
                        <div class="price-sub">Location minimale 1 mois</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- DESCRIPTION ZONE --}}
        @if(!empty($p['zone_description']))
        <div class="zone-desc">
            <p><strong>📍 EMPLACEMENT STRATÉGIQUE</strong><br>{{ $p['zone_description'] }}</p>
        </div>
        @endif
    </div>

    {{-- PIED DE PAGE --}}
    <div class="page-footer">
        <table class="footer-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="footer-left">CIBLE CI · Régie Publicitaire</td>
                <td class="footer-center">Document confidentiel · {{ $generated }}</td>
                <td class="footer-right">{{ $pageNum }}/{{ $totalPanels + 1 }}</td>
            </tr>
        </table>
    </div>
</div>

@endforeach
@endforeach

</body>
</html>
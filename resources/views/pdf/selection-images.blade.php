<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ═══════════════════════════════════════════════════════════════
   CIBLE CI — Fiche Disponibilités PDF (Ultra Optimisé)
   Compatible DomPDF — 1 panneau par page A4 portrait
   ═══════════════════════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }

@page { margin: 0; size: A4 portrait; }

body {
    font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
    font-size: 10px;
    color: #1e293b;
    background: #ffffff;
    width: 210mm;
}

/* ── PAGE ── */
.page {
    width: 210mm;
    height: 297mm;
    page-break-after: always;
    position: relative;
    background: #ffffff;
    overflow: hidden;
}
.page:last-child { page-break-after: avoid; }

/* ── HEADER CIBLE CI ── */
.page-header {
    background: #0b2b26;
    padding: 7mm 10mm 6mm;
    border-bottom: 3.5px solid #e3a51e;
}
.header-left { display: inline-block; width: 50%; vertical-align: middle; }
.header-right { display: inline-block; width: 48%; text-align: right; vertical-align: middle; }
.logo {
    font-size: 20px;
    font-weight: 800;
    color: #e3a51e;
    letter-spacing: -0.5px;
}
.logo-sub {
    font-size: 7px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: 2px;
}
.doc-title {
    font-size: 10px;
    font-weight: 700;
    color: #e2e8f0;
}
.doc-sub {
    font-size: 7px;
    color: #64748b;
    margin-top: 2px;
}

/* ── BARRE RÉFÉRENCE DORÉE ── */
.ref-bar {
    background: #e3a51e;
    padding: 4mm 10mm;
}
.ref-table { width: 100%; }
.ref-code {
    font-size: 16px;
    font-weight: 800;
    color: #0b2b26;
    letter-spacing: 0.5px;
    font-family: monospace, 'DejaVu Sans Mono', sans-serif;
}
.ref-name {
    font-size: 9px;
    color: rgba(11,43,38,0.7);
    margin-top: 2px;
}
.ref-right {
    text-align: right;
    font-size: 9px;
    color: rgba(11,43,38,0.8);
    font-weight: 600;
}

/* ── ZONE PHOTO ── */
.photo-zone {
    background: #0f1117;
    height: 92mm;
    position: relative;
    border-bottom: 2px solid #1e293b;
    overflow: hidden;
}
.photo-img {
    width: 100%;
    height: 92mm;
    object-fit: cover;
}
.photo-placeholder {
    height: 92mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1a1d2e, #0f1117);
}
.photo-placeholder-icon {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 8px;
}
.photo-placeholder-ref {
    font-family: monospace;
    font-size: 14px;
    font-weight: 700;
    color: #334155;
}
.photo-placeholder-txt {
    font-size: 10px;
    color: #475569;
    margin-top: 4px;
}

/* Badge statut overlay */
.status-tag {
    position: absolute;
    top: 8px;
    right: 8px;
    padding: 3px 10px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    backdrop-filter: blur(2px);
}
.status-libre       { background: #22c55e; color: #fff; }
.status-occupe      { background: #ef4444; color: #fff; }
.status-option      { background: #e3a51e; color: #0b2b26; }
.status-maintenance { background: #475569; color: #fff; }

/* Numéro de page overlay */
.page-num {
    position: absolute;
    bottom: 6px;
    left: 8px;
    font-size: 7px;
    color: rgba(255,255,255,0.4);
    font-family: monospace;
}

/* ── CORPS INFORMATIONS ── */
.body-zone {
    padding: 5mm 10mm 3mm;
}

/* 2 colonnes */
.cols-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 4mm;
}
.col-left {
    width: 50%;
    vertical-align: top;
    padding-right: 3mm;
}
.col-right {
    width: 50%;
    vertical-align: top;
    padding-left: 3mm;
    border-left: 1px solid #e2e8f0;
}

/* Bloc d'info */
.info-block {
    padding: 2.5mm 0;
    border-bottom: 1px solid #f1f5f9;
}
.info-block:last-child { border-bottom: none; }
.info-lbl {
    font-size: 7px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 1.5px;
}
.info-val {
    font-size: 10px;
    font-weight: 700;
    color: #1e293b;
}
.info-val-light {
    font-weight: 500;
    color: #64748b;
}

/* Badges éclairage */
.badge-lit {
    display: inline-block;
    background: rgba(251,191,36,0.12);
    border: 1px solid rgba(251,191,36,0.4);
    color: #b45309;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 8px;
    font-weight: 700;
}
.badge-non-lit {
    display: inline-block;
    background: rgba(100,116,139,0.08);
    border: 1px solid rgba(100,116,139,0.2);
    color: #64748b;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 8px;
}

/* ── BANDE PÉRIODE ── */
.period-band {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 2.5mm 4mm;
    margin-bottom: 4mm;
}
.period-table { width: 100%; }
.period-label {
    font-size: 7px;
    font-weight: 800;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.period-dates {
    font-size: 10px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 2px;
}
.period-right { text-align: right; }
.period-duration {
    font-size: 9px;
    color: #64748b;
    font-weight: 600;
}

/* ── DATE LIBÉRATION ── */
.release-band {
    background: rgba(239,68,68,0.05);
    border: 1px solid rgba(239,68,68,0.2);
    border-radius: 6px;
    padding: 2.5mm 4mm;
    margin-bottom: 4mm;
    font-size: 9px;
    font-weight: 600;
    color: #991b1b;
}

/* ── TRAFIC ── */
.traffic-band {
    background: rgba(59,130,246,0.05);
    border: 1px solid rgba(59,130,246,0.15);
    border-radius: 6px;
    padding: 2.5mm 4mm;
    margin-bottom: 3mm;
    display: inline-block;
}
.traffic-num {
    font-size: 13px;
    font-weight: 800;
    color: #1d4ed8;
}
.traffic-lbl {
    font-size: 7px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ── DESCRIPTION ZONE ── */
.zone-desc {
    background: #f8fafc;
    border-left: 3px solid #e3a51e;
    padding: 2.5mm 4mm;
    border-radius: 0 6px 6px 0;
    font-size: 8.5px;
    color: #475569;
    line-height: 1.4;
}

/* Séparateur */
.sep {
    height: 1px;
    background: #e2e8f0;
    margin: 3mm 0;
}

/* ── FOOTER ── */
.page-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: #0b2b26;
    padding: 3mm 10mm;
    border-top: 2px solid #1a3530;
}
.footer-table { width: 100%; }
.footer-left {
    font-size: 7px;
    color: #475569;
    vertical-align: middle;
}
.footer-left strong { color: #64748b; }
.footer-center {
    text-align: center;
    font-size: 7px;
    color: #334155;
    vertical-align: middle;
}
.footer-right {
    text-align: right;
    font-size: 8px;
    color: #475569;
    vertical-align: middle;
}
.footer-accent { color: #e3a51e; font-weight: 700; }
</style>
</head>
<body>

@php
use Carbon\Carbon;

$fmtDate = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
$totalCount = $panels->count();

$statusMap = fn($s) => match($s) {
    'libre'             => ['label' => 'Disponible', 'class' => 'status-libre'],
    'occupe'            => ['label' => 'Occupé',     'class' => 'status-occupe'],
    'option_periode',
    'option'            => ['label' => 'En option',  'class' => 'status-option'],
    'maintenance'       => ['label' => 'Maintenance','class' => 'status-maintenance'],
    default             => ['label' => 'Inconnu',    'class' => 'status-occupe'],
};

// Calcul période
$periodStr = null;
if (!empty($startDate) && !empty($endDate)) {
    $s = Carbon::parse($startDate);
    $e = Carbon::parse($endDate);
    $days = $s->diffInDays($e);
    $periodStr = $days >= 30 ? intdiv($days, 30) . ' mois' : $days . ' jours';
}
@endphp

@forelse($panels as $idx => $p)
@php
    $pageNum = $idx + 1;
    $status = $statusMap($p['display_status'] ?? 'occupe');
    $traffic = (int)($p['daily_traffic'] ?? 0);
    $releaseInfo = $p['release_info'] ?? null;
    $zoneDesc = $p['zone_description'] ?? '';

    // Image : chemin local ou base64
    $imgSrc = null;
    if (!empty($p['photo_path']) && file_exists($p['photo_path'])) {
        $ext = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
        $mime = match($ext) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            default => 'image/jpeg',
        };
        $imgSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p['photo_path']));
    } elseif (!empty($p['photo_url'])) {
        $imgSrc = $p['photo_url'];
    }

    $commune = $p['commune'] ?? '—';
    $zone = $p['zone'] ?? '—';
    $format = $p['format'] ?? '—';
    $dims = $p['dimensions'] ?? null;
    $category = $p['category'] ?? '—';
    $isLit = (bool)($p['is_lit'] ?? false);
@endphp

<div class="page">

    {{-- HEADER --}}
    <div class="page-header">
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="header-left">
                    <div class="logo">CIBLE CI</div>
                    <div class="logo-sub">Régie Publicitaire · Abidjan</div>
                </td>
                <td class="header-right">
                    <div class="doc-title">Fiche Disponibilité</div>
                    <div class="doc-sub">{{ $generated }} &nbsp;·&nbsp; {{ $pageNum }}/{{ $totalCount }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- BARRE RÉFÉRENCE --}}
    <div class="ref-bar">
        <table class="ref-table" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:60%">
                    <div class="ref-code">{{ $p['reference'] ?? '—' }}</div>
                    <div class="ref-name">{{ $p['name'] ?? '' }}</div>
                </td>
                <td style="width:40%; text-align:right">
                    <div class="ref-right">{{ $commune }}@if($zone !== '—') · {{ $zone }} @endif</div>
                    @if(($p['source'] ?? 'internal') === 'external' && !empty($p['agency_name']))
                        <div style="font-size:7px; color:rgba(11,43,38,0.6); margin-top:2px">🤝 {{ $p['agency_name'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- PHOTO --}}
    <div class="photo-zone">
        @if($imgSrc)
            <img src="{{ $imgSrc }}" class="photo-img" alt="{{ $p['reference'] }}">
        @else
            <div class="photo-placeholder">
                <div class="photo-placeholder-icon">🪧</div>
                <div class="photo-placeholder-ref">{{ $p['reference'] ?? '—' }}</div>
                <div class="photo-placeholder-txt">Aucune photo disponible</div>
            </div>
        @endif

        <div class="status-tag {{ $status['class'] }}">{{ $status['label'] }}</div>
        <div class="page-num">{{ $p['reference'] ?? '—' }} · {{ $pageNum }}/{{ $totalCount }}</div>
    </div>

    {{-- CORPS --}}
    <div class="body-zone">

        {{-- Période --}}
        @if(!empty($startDate) && !empty($endDate))
        <div class="period-band">
            <table class="period-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="width:60%">
                        <div class="period-label">📅 Période de campagne</div>
                        <div class="period-dates">{{ $fmtDate($startDate) }} → {{ $fmtDate($endDate) }}</div>
                    </td>
                    @if($periodStr)
                    <td style="width:40%; text-align:right">
                        <div class="period-duration">⏱ {{ $periodStr }}</div>
                    </td>
                    @endif
                </tr>
            </table>
        </div>
        @endif

        {{-- Libération --}}
        @if($releaseInfo && isset($releaseInfo['label']))
        <div class="release-band">
            ⏰ {{ $releaseInfo['label'] }}
            @if(isset($releaseInfo['date'])) — Libre à partir du {{ $releaseInfo['date'] }} @endif
        </div>
        @endif

        {{-- 2 colonnes informations --}}
        <table class="cols-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="col-left">
                    <div class="info-block">
                        <div class="info-lbl">📍 Commune</div>
                        <div class="info-val">{{ $commune }}</div>
                    </div>
                    <div class="info-block">
                        <div class="info-lbl">🗺️ Zone</div>
                        <div class="info-val">{{ $zone }}</div>
                    </div>
                    <div class="info-block">
                        <div class="info-lbl">📐 Format</div>
                        <div class="info-val">{{ $format }}@if($dims) <span class="info-val-light">({{ $dims }})</span>@endif</div>
                    </div>
                </td>
                <td class="col-right">
                    <div class="info-block">
                        <div class="info-lbl">🏷️ Catégorie</div>
                        <div class="info-val">{{ $category }}</div>
                    </div>
                    <div class="info-block">
                        <div class="info-lbl">💡 Éclairage</div>
                        <div class="info-val">
                            @if($isLit)
                                <span class="badge-lit">💡 Éclairé</span>
                            @else
                                <span class="badge-non-lit">Non éclairé</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-block">
                        <div class="info-lbl">📊 Statut</div>
                        <div class="info-val">{{ $status['label'] }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="sep"></div>

        {{-- TRAFIC + DESCRIPTION --}}
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:45%; vertical-align:top; padding-right:4mm">
                    @if($traffic > 0)
                    <div class="traffic-band">
                        <div style="font-size:7px; color:#1e40af; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px">👁️ Audience estimée</div>
                        <div class="traffic-num">{{ number_format($traffic, 0, ',', ' ') }}</div>
                        <div class="traffic-lbl">contacts / jour</div>
                    </div>
                    @endif
                </td>
                <td style="width:55%; vertical-align:top">
                    @if($zoneDesc)
                    <div class="zone-desc">📍 {{ Str::limit($zoneDesc, 85) }}</div>
                    @endif
                </td>
            </tr>
        </table>

    </div>{{-- /body-zone --}}

    {{-- FOOTER --}}
    <div class="page-footer">
        <table class="footer-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="footer-left">
                    <strong>CIBLE CI</strong> · Régie Publicitaire · Abidjan, Côte d'Ivoire
                </td>
                <td class="footer-center">
                    Document confidentiel — Usage commercial uniquement
                </td>
                <td class="footer-right">
                    <span class="footer-accent">{{ $pageNum }}</span> / {{ $totalCount }}
                </td>
            </tr>
        </table>
    </div>

</div>

@empty
<div style="padding:40mm; text-align:center; color:#94a3b8; font-size:14px">
    Aucun panneau à afficher.
</div>
@endforelse

</body>
</html>
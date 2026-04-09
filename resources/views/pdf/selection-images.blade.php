<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ══════════════════════════════════════════════
   CIBLE CI — Fiche Disponibilités PDF
   DomPDF : pas de flexbox ni grid → tables + float
   1 panneau par page A4 portrait
   Sans prix (document de prospection)
   ══════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }

@page {
    margin: 0;
    size: A4 portrait;
}

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #1e293b;
    background: #ffffff;
    width: 210mm;
}

/* ── UNE PAGE PAR PANNEAU ── */
.page {
    width: 210mm;
    height: 297mm;
    page-break-after: always;
    position: relative;
    overflow: hidden;
    background: #ffffff;
}
.page:last-child { page-break-after: avoid; }

/* ── HEADER VERT FONCÉ CIBLE CI ── */
.page-header {
    background: #0b2b26;
    padding: 7mm 10mm 6mm;
    border-bottom: 3.5px solid #e3a51e;
}
.header-left {
    display: inline-block;
    vertical-align: middle;
    width: 50%;
}
.header-right {
    display: inline-block;
    vertical-align: middle;
    width: 48%;
    text-align: right;
}
.logo {
    font-size: 20px;
    font-weight: bold;
    color: #e3a51e;
    letter-spacing: 1px;
}
.logo-sub {
    font-size: 8px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-top: 2px;
}
.doc-title {
    font-size: 11px;
    font-weight: bold;
    color: #e2e8f0;
}
.doc-sub {
    font-size: 8px;
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
    font-weight: bold;
    color: #0b2b26;
    letter-spacing: 1px;
    font-family: monospace, DejaVu Sans Mono, sans-serif;
}
.ref-name {
    font-size: 10px;
    color: rgba(11,43,38,0.75);
    margin-top: 1px;
}
.ref-right {
    text-align: right;
    font-size: 9px;
    color: rgba(11,43,38,0.8);
    font-weight: bold;
}

/* ── ZONE PHOTO ── */
.photo-zone {
    background: #0f1117;
    height: 95mm;
    overflow: hidden;
    position: relative;
    border-bottom: 2px solid #1e293b;
}
.photo-img {
    width: 100%;
    height: 95mm;
    object-fit: cover;
    display: block;
}
.photo-placeholder {
    height: 95mm;
    text-align: center;
    padding-top: 30mm;
    background: #111827;
}
.photo-placeholder-ref {
    font-family: monospace, sans-serif;
    font-size: 16px;
    font-weight: bold;
    color: #334155;
    margin-bottom: 6px;
}
.photo-placeholder-txt {
    font-size: 11px;
    color: #475569;
}

/* Badge statut en overlay haut-droite */
.status-tag {
    position: absolute;
    top: 5mm;
    right: 5mm;
    padding: 3px 10px;
    border-radius: 3px;
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.s-libre       { background: #22c55e; color: #fff; }
.s-occupe      { background: #ef4444; color: #fff; }
.s-option      { background: #e3a51e; color: #0b2b26; }
.s-maintenance { background: #475569; color: #fff; }

/* Numéro de page en overlay bas-gauche */
.page-num {
    position: absolute;
    bottom: 3mm;
    left: 5mm;
    font-size: 8px;
    color: rgba(255,255,255,0.45);
    font-family: monospace, sans-serif;
}

/* ── CORPS INFORMATIONS ── */
.body-zone {
    padding: 5mm 10mm 2mm;
    background: #ffffff;
}

/* 2 colonnes avec table */
.cols-table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
.col-left  { width: 50%; vertical-align: top; padding-right: 3mm; }
.col-right { width: 50%; vertical-align: top; padding-left: 3mm; border-left: 1px solid #e2e8f0; }

/* Ligne d'info */
.info-block {
    padding: 2.5mm 0;
    border-bottom: 1px solid #f1f5f9;
}
.info-block:last-child { border-bottom: none; }
.info-lbl {
    font-size: 7.5px;
    font-weight: bold;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 1.5px;
}
.info-val {
    font-size: 10px;
    font-weight: bold;
    color: #1e293b;
}
.info-val-muted { font-weight: normal; color: #64748b; }

/* Badge éclairé */
.badge-lit {
    display: inline-block;
    background: rgba(251,191,36,0.1);
    border: 1px solid rgba(251,191,36,0.4);
    color: #b45309;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 8px;
    font-weight: bold;
}
.badge-non-lit {
    display: inline-block;
    background: rgba(100,116,139,0.1);
    border: 1px solid rgba(100,116,139,0.2);
    color: #64748b;
    padding: 1px 6px;
    border-radius: 3px;
    font-size: 8px;
}

/* ── BANDE PÉRIODE (si fournie) ── */
.period-band {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 2.5mm 4mm;
    margin-bottom: 3mm;
}
.period-table { width: 100%; }
.period-label {
    font-size: 8px;
    font-weight: bold;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: block;
    margin-bottom: 2px;
}
.period-dates {
    font-size: 11px;
    font-weight: bold;
    color: #1e293b;
}
.period-right { text-align: right; }
.period-duration {
    font-size: 9px;
    color: #64748b;
}

/* ── DATE LIBÉRATION ── */
.release-band {
    background: rgba(239,68,68,0.04);
    border: 1px solid rgba(239,68,68,0.18);
    border-radius: 4px;
    padding: 2mm 4mm;
    margin-bottom: 3mm;
    font-size: 9px;
    color: #991b1b;
}

/* ── TRAFIC AUDIENCE ── */
.traffic-band {
    background: rgba(59,130,246,0.04);
    border: 1px solid rgba(59,130,246,0.15);
    border-radius: 4px;
    padding: 2mm 4mm;
    margin-bottom: 2mm;
    display: inline-block;
}
.traffic-num {
    font-size: 13px;
    font-weight: bold;
    color: #1d4ed8;
}
.traffic-lbl {
    font-size: 8px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ── NOTES ZONE ── */
.zone-desc-band {
    background: #f8fafc;
    border-left: 3px solid #e3a51e;
    padding: 2mm 4mm;
    margin-bottom: 2mm;
    border-radius: 0 4px 4px 0;
    font-size: 9px;
    color: #475569;
    font-style: italic;
}

/* ── SÉPARATEUR ── */
.sep { height: 1px; background: #e2e8f0; margin: 2mm 0; }

/* ── FOOTER ── */
.page-footer {
    background: #0b2b26;
    padding: 3mm 10mm;
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    border-top: 2px solid #1a3530;
}
.footer-table { width: 100%; }
.footer-left {
    font-size: 7.5px;
    color: #475569;
    vertical-align: middle;
}
.footer-left strong { color: #64748b; }
.footer-center {
    text-align: center;
    font-size: 7.5px;
    color: #334155;
    vertical-align: middle;
}
.footer-right {
    text-align: right;
    font-size: 8px;
    color: #475569;
    vertical-align: middle;
}
.footer-accent { color: #e3a51e; font-weight: bold; }
</style>
</head>
<body>

@php
use Carbon\Carbon;

$statusMap = fn($s) => match($s) {
    'libre'             => ['Disponible', 's-libre'],
    'occupe'            => ['Occupé',     's-occupe'],
    'option_periode',
    'option'            => ['En option',  's-option'],
    'maintenance'       => ['Maintenance','s-maintenance'],
    default             => ['Inconnu',    's-occupe'],
};

$fmtDate = fn($d) => $d ? Carbon::parse($d)->format('d/m/Y') : '—';
$totalCount = $panels->count();

// Calcul durée si période
$periodStr = null;
if (!empty($startDate) && !empty($endDate)) {
    $s = Carbon::parse($startDate);
    $e = Carbon::parse($endDate);
    $days = $s->diffInDays($e);
    $mois = intdiv($days, 30);
    $periodStr = $mois > 0 ? "{$mois} mois" : "{$days} jours";
}
@endphp

@forelse($panels as $idx => $p)
@php
    [$statusLabel, $statusClass] = $statusMap($p['display_status'] ?? 'occupe');
    $pageNum  = $idx + 1;
    $traffic  = (int)($p['daily_traffic'] ?? 0);
    $releaseInfo = $p['release_info'] ?? null;
    $zoneDesc = $p['zone_description'] ?? '';

    // Image : chemin local prioritaire (base64 pour DomPDF fiable)
    $imgSrc = null;
    if (!empty($p['photo_path']) && file_exists($p['photo_path'])) {
        $ext  = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
        $mime = match($ext) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            default => 'image/jpeg',
        };
        $b64    = base64_encode(file_get_contents($p['photo_path']));
        $imgSrc = "data:{$mime};base64,{$b64}";
    } elseif (!empty($p['photo_url'])) {
        $imgSrc = $p['photo_url'];
    }

    $commune  = $p['commune']    ?? '—';
    $zone     = $p['zone']       ?? '—';
    $format   = $p['format']     ?? '—';
    $dims     = $p['dimensions'] ?? null;
    $category = $p['category']   ?? '—';
    $isLit    = (bool)($p['is_lit'] ?? false);
    $source   = $p['source']     ?? 'internal';
    $agencyName = $p['agency_name'] ?? null;
@endphp

<div class="page">

    {{-- ── HEADER ── --}}
    <div class="page-header">
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="header-left">
                    <div class="logo">CIBLE CI</div>
                    <div class="logo-sub">Régie Publicitaire · Abidjan</div>
                </td>
                <td class="header-right">
                    <div class="doc-title">Fiche Disponibilité</div>
                    <div class="doc-sub">{{ $generated ?? now()->format('d/m/Y H:i') }} &nbsp;·&nbsp; {{ $pageNum }}/{{ $totalCount }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── BARRE RÉFÉRENCE ── --}}
    <div class="ref-bar">
        <table class="ref-table" cellpadding="0" cellspacing="0">
            <tr>
                <td>
                    <div class="ref-code">{{ $p['reference'] ?? '—' }}</div>
                    <div class="ref-name">{{ $p['name'] ?? '' }}</div>
                </td>
                <td style="text-align:right">
                    <div class="ref-right">{{ $commune }}@if($zone !== '—') &nbsp;·&nbsp; {{ $zone }} @endif</div>
                    @if($source === 'external' && $agencyName)
                        <div style="font-size:8px;color:rgba(11,43,38,0.65);margin-top:2px">🤝 {{ $agencyName }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── PHOTO ── --}}
    <div class="photo-zone">
        @if($imgSrc)
            <img src="{{ $imgSrc }}" class="photo-img" alt="{{ $p['reference'] }}">
        @else
            <div class="photo-placeholder">
                <div class="photo-placeholder-ref">{{ $p['reference'] ?? '—' }}</div>
                <div class="photo-placeholder-txt">📷 Aucune photo disponible</div>
            </div>
        @endif

        <div class="status-tag {{ $statusClass }}">{{ $statusLabel }}</div>
        <div class="page-num">{{ $p['reference'] }} · {{ $pageNum }}/{{ $totalCount }}</div>
    </div>

    {{-- ── CORPS ── --}}
    <div class="body-zone">

        {{-- Période si disponible --}}
        @if(!empty($startDate) && !empty($endDate))
        <div class="period-band">
            <table class="period-table" cellpadding="0" cellspacing="0">
                <tr>
                    <td>
                        <span class="period-label">📅 Période de campagne</span>
                        <div class="period-dates">{{ $fmtDate($startDate) }} → {{ $fmtDate($endDate) }}</div>
                    </td>
                    @if($periodStr)
                    <td class="period-right">
                        <div class="period-duration">⏱ {{ $periodStr }}</div>
                    </td>
                    @endif
                </tr>
            </table>
        </div>
        @endif

        {{-- Date libération si occupé --}}
        @if($releaseInfo && isset($releaseInfo['label']))
        <div class="release-band">
            ⏰ {{ $releaseInfo['label'] }}
            @if(isset($releaseInfo['date'])) &nbsp;—&nbsp; Libre à partir du {{ $releaseInfo['date'] }} @endif
        </div>
        @endif

        {{-- 2 colonnes d'infos --}}
        <table class="cols-table" cellpadding="0" cellspacing="0">
            <tr>
                <td class="col-left">

                    <div class="info-block">
                        <div class="info-lbl">📍 Commune</div>
                        <div class="info-val">{{ $commune }}</div>
                    </div>

                    <div class="info-block">
                        <div class="info-lbl">🗺 Zone</div>
                        <div class="info-val">{{ $zone }}</div>
                    </div>

                    <div class="info-block">
                        <div class="info-lbl">📐 Format / Dimensions</div>
                        <div class="info-val">{{ $format }}@if($dims) &nbsp;<span class="info-val-muted">({{ $dims }})</span>@endif</div>
                    </div>

                </td>
                <td class="col-right">

                    <div class="info-block">
                        <div class="info-lbl">🏷 Catégorie</div>
                        <div class="info-val">{{ $category }}</div>
                    </div>

                    <div class="info-block">
                        <div class="info-lbl">💡 Éclairage</div>
                        <div class="info-val">
                            @if($isLit)
                                <span class="badge-lit">💡 Panneau éclairé</span>
                            @else
                                <span class="badge-non-lit">Non éclairé</span>
                            @endif
                        </div>
                    </div>

                    <div class="info-block">
                        <div class="info-lbl">📊 Statut</div>
                        <div class="info-val">{{ $statusLabel }}</div>
                    </div>

                </td>
            </tr>
        </table>

        <div class="sep"></div>

        {{-- Trafic + note zone --}}
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="vertical-align:top;width:50%;padding-right:4mm">
                    @if($traffic > 0)
                    <div class="traffic-band">
                        <div style="font-size:8px;color:#1e40af;text-transform:uppercase;letter-spacing:.5px;margin-bottom:2px">👁 Audience estimée</div>
                        <div class="traffic-num">{{ number_format($traffic, 0, ',', ' ') }}</div>
                        <div class="traffic-lbl">contacts / jour</div>
                    </div>
                    @endif
                </td>
                <td style="vertical-align:top;width:50%">
                    @if($zoneDesc)
                    <div class="zone-desc-band">📍 {{ Str::limit($zoneDesc, 80) }}</div>
                    @endif
                </td>
            </tr>
        </table>

    </div>{{-- /body-zone --}}

    {{-- ── FOOTER ── --}}
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

</div>{{-- /page --}}

@empty
<div style="padding:40mm;text-align:center;color:#94a3b8;font-size:14px">Aucun panneau à afficher.</div>
@endforelse

</body>
</html>

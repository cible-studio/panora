<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
/* ═══════════════════════════════════════════════════════════════
   PROGICIA — Fiche Disponibilités PDF (1 panneau = 1 page)
   Chartes graphiques : Rouge #e20613, Jaune #fab80b, Vert #3aa835,
   Violet #81358a, Bleu #3f7fc0, Fond #0a0c15
   Compatible DomPDF — A4 portrait, sans prix
   ═══════════════════════════════════════════════════════════════ */
* { margin:0; padding:0; box-sizing:border-box; }

@page { margin: 0; size: A4 portrait; }

body {
    font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
    font-size: 10px;
    color: #e2e8f0;
    background: #0a0c15;
    width: 210mm;
}

/* ── PAGE ── */
.page {
    width: 210mm;
    height: 297mm;
    page-break-after: always;
    position: relative;
    background: #0a0c15;
    overflow: hidden;
}
.page:last-child { page-break-after: avoid; }

/* ── BANDE COULEURS PROGICIA ── */
.color-bar {
    height: 3px;
    background: linear-gradient(90deg, #e20613 0%, #fab80b 25%, #3aa835 50%, #81358a 75%, #3f7fc0 100%);
}

/* ── HEADER PROGICIA ── */
.page-header {
    background: #11131f;
    padding: 7mm 10mm 6mm;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.header-left { display: inline-block; width: 50%; vertical-align: middle; }
.header-right { display: inline-block; width: 48%; text-align: right; vertical-align: middle; }
.logo {
    font-size: 20px;
    font-weight: 800;
    background: linear-gradient(135deg, #e20613, #fab80b);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    letter-spacing: -0.5px;
}
.logo-sub {
    font-size: 7px;
    color: #64748b;
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

/* ── BARRE RÉFÉRENCE ── */
.ref-bar {
    background: rgba(227,165,30,0.12);
    padding: 4mm 10mm;
    border-bottom: 1px solid rgba(227,165,30,0.2);
}
.ref-table { width: 100%; }
.ref-code {
    font-size: 16px;
    font-weight: 800;
    color: #fab80b;
    letter-spacing: 0.5px;
    font-family: monospace, 'DejaVu Sans Mono', sans-serif;
}
.ref-name {
    font-size: 9px;
    color: #94a3b8;
    margin-top: 2px;
}
.ref-right {
    text-align: right;
    font-size: 9px;
    color: #94a3b8;
    font-weight: 600;
}

/* ── ZONE PHOTO ── */
.photo-zone {
    background: #1a1d2e;
    height: 100mm;
    position: relative;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    overflow: hidden;
}
.photo-img {
    width: 100%;
    height: 100mm;
    object-fit: cover;
}
.photo-placeholder {
    height: 100mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #1a1d2e, #11131f);
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
    color: #64748b;
}
.photo-placeholder-txt {
    font-size: 10px;
    color: #4a5568;
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
.status-libre       { background: #3aa835; color: #fff; }
.status-occupe      { background: #e20613; color: #fff; }
.status-option      { background: #fab80b; color: #0a0c15; }
.status-maintenance { background: #64748b; color: #fff; }

/* Numéro de page overlay */
.page-num {
    position: absolute;
    bottom: 6px;
    left: 8px;
    font-size: 7px;
    color: rgba(255,255,255,0.3);
    font-family: monospace;
}

/* ── CORPS INFORMATIONS ── */
.body-zone {
    padding: 5mm 10mm 5mm;
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
    border-left: 1px solid rgba(255,255,255,0.05);
}

/* Bloc d'info */
.info-block {
    padding: 2.5mm 0;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.info-block:last-child { border-bottom: none; }
.info-lbl {
    font-size: 7px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 1.5px;
}
.info-val {
    font-size: 10px;
    font-weight: 700;
    color: #e2e8f0;
}
.info-val-light {
    font-weight: 500;
    color: #94a3b8;
}

/* Badges éclairage */
.badge-lit {
    display: inline-block;
    background: rgba(250,184,11,0.12);
    border: 1px solid rgba(250,184,11,0.3);
    color: #fab80b;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 8px;
    font-weight: 700;
}
.badge-non-lit {
    display: inline-block;
    background: rgba(100,116,139,0.1);
    border: 1px solid rgba(100,116,139,0.2);
    color: #94a3b8;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 8px;
}

/* ── BANDE PÉRIODE ── */
.period-band {
    background: rgba(63,127,192,0.08);
    border: 1px solid rgba(63,127,192,0.2);
    border-radius: 6px;
    padding: 2.5mm 4mm;
    margin-bottom: 4mm;
}
.period-table { width: 100%; }
.period-label {
    font-size: 7px;
    font-weight: 800;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.period-dates {
    font-size: 10px;
    font-weight: 700;
    color: #e2e8f0;
    margin-top: 2px;
}
.period-right { text-align: right; }
.period-duration {
    font-size: 9px;
    color: #94a3b8;
    font-weight: 600;
}

/* ── DATE LIBÉRATION ── */
.release-band {
    background: rgba(226,6,19,0.08);
    border: 1px solid rgba(226,6,19,0.2);
    border-radius: 6px;
    padding: 2.5mm 4mm;
    margin-bottom: 4mm;
    font-size: 9px;
    font-weight: 600;
    color: #e20613;
}

/* ── TRAFIC ── */
.traffic-band {
    background: rgba(58,168,53,0.08);
    border: 1px solid rgba(58,168,53,0.2);
    border-radius: 6px;
    padding: 2.5mm 4mm;
    display: inline-block;
}
.traffic-num {
    font-size: 13px;
    font-weight: 800;
    color: #3aa835;
}
.traffic-lbl {
    font-size: 7px;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ── DESCRIPTION ZONE ── */
.zone-desc {
    background: rgba(129,53,138,0.08);
    border-left: 3px solid #81358a;
    padding: 2.5mm 4mm;
    border-radius: 0 6px 6px 0;
    font-size: 8.5px;
    color: #c084fc;
    line-height: 1.4;
}

/* Séparateur */
.sep {
    height: 1px;
    background: rgba(255,255,255,0.05);
    margin: 3mm 0;
}

/* ── FOOTER ── */
.page-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: #11131f;
    padding: 3mm 10mm;
    border-top: 1px solid rgba(255,255,255,0.05);
}
.footer-table { width: 100%; }
.footer-left {
    font-size: 7px;
    color: #64748b;
    vertical-align: middle;
}
.footer-left strong { color: #94a3b8; }
.footer-center {
    text-align: center;
    font-size: 7px;
    color: #4a5568;
    vertical-align: middle;
}
.footer-right {
    text-align: right;
    font-size: 8px;
    color: #64748b;
    vertical-align: middle;
}
.footer-accent { color: #fab80b; font-weight: 700; }
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
    {{-- Bande couleurs PROGICIA --}}
    <div class="color-bar"></div>

    {{-- HEADER --}}
    <div class="page-header">
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td class="header-left">
                    <div class="logo">PROGICIA</div>
                    <div class="logo-sub">GIE OOH — Régie Publicitaire</div>
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
                        <div style="font-size:7px; color:#64748b; margin-top:2px">🤝 {{ $p['agency_name'] }}</div>
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

        {{-- Période (si disponible) --}}
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

        {{-- Date de libération (si panneau occupé) --}}
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

        {{-- TRAFIC + DESCRIPTION ZONE --}}
        <table style="width:100%" cellpadding="0" cellspacing="0">
            <tr>
                <td style="width:45%; vertical-align:top; padding-right:4mm">
                    @if($traffic > 0)
                    <div class="traffic-band">
                        <div style="font-size:7px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:2px">👁️ Audience estimée</div>
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
                    <strong>PROGICIA</strong> · GIE OOH · Abidjan, Côte d'Ivoire
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
<div style="padding:40mm; text-align:center; color:#64748b; font-size:14px">
    Aucun panneau à afficher.
</div>
@endforelse

</body>
</html>
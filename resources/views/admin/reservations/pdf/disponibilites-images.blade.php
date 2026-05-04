<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Sélection panneaux — CIBLE CI</title>
<style>
    @page { margin: 0; size: A4 portrait; }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        color: #1f2937;
        font-size: 10px;
        line-height: 1.4;
    }

    .page {
        page-break-after: always;
        position: relative;
        min-height: 277mm;
    }
    .page:last-child { page-break-after: avoid; }

    /* ── HEADER UNIFORME (cohérent avec fiche panneau / disponibilites-list) ── */
    .pdf-header {
        background: #0d1117;
        color: #ffffff;
        padding: 14px 22px;
        display: table;
        width: 100%;
        border-bottom: 3px solid #e8a020;
        margin-bottom: 16px;
    }
    .pdf-header > div { display: table-cell; vertical-align: middle; }
    .pdf-header .logo-cell  { width: 30%; text-align: left; }
    .pdf-header .title-cell { width: 40%; text-align: center; }
    .pdf-header .meta-cell  { width: 30%; text-align: right; font-size: 9px; color: #9ca3af; }
    .pdf-header img {
        height: 38px;
        width: auto;
        background: #ffffff;
        padding: 4px 8px;
        border-radius: 4px;
    }
    .pdf-header h1 {
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: #fff;
    }
    .pdf-header .accent { color: #e8a020; }

    .container { padding: 0 22px 30px; }

    /* ── REF BANNER ── */
    .ref-banner {
        background: #fff7ed;
        border-left: 4px solid #e8a020;
        padding: 12px 18px;
        margin-bottom: 14px;
    }
    .ref-banner .ref-tag {
        font-family: 'Courier New', monospace;
        font-weight: 700;
        font-size: 18px;
        color: #c2570d;
        letter-spacing: 1.5px;
    }
    .ref-banner .ref-name {
        font-size: 13px;
        font-weight: 600;
        color: #1f2937;
        margin-top: 2px;
    }
    .ref-banner .ref-loc {
        font-size: 10.5px;
        color: #6b7280;
        margin-top: 2px;
    }

    /* ── PHOTO ── */
    .photo-wrap {
        text-align: center;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 6px;
        height: 200px;
        margin-bottom: 14px;
        line-height: 0;
    }
    .photo-wrap img {
        max-width: 100%;
        max-height: 188px;
        object-fit: contain;
    }
    .photo-empty {
        display: inline-block;
        line-height: 188px;
        color: #9ca3af;
        font-size: 13px;
    }

    /* ── SECTIONS ── */
    h2.section {
        font-size: 10px;
        font-weight: 700;
        color: #e8a020;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 4px;
        margin: 12px 0 8px;
    }

    /* ── INFO TABLE ── */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    .info-table td {
        padding: 6px 10px;
        border-bottom: 1px solid #f3f4f6;
        vertical-align: top;
    }
    .info-table td.lbl {
        color: #6b7280;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 8.5px;
        letter-spacing: 0.8px;
        width: 38%;
        background: #fafafa;
    }
    .info-table td.val { color: #1f2937; font-weight: 500; }
    .info-table a { color: #2563eb; text-decoration: none; }

    /* ── 2 COLONNES ── */
    .two-cols { display: table; width: 100%; }
    .two-cols .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 10px; }
    .two-cols .col:last-child { padding-right: 0; padding-left: 10px; }

    /* ── BADGES (uniquement si showPricing) ── */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-libre       { background: #d1fae5; color: #065f46; }
    .badge-occupe      { background: #fee2e2; color: #991b1b; }
    .badge-option      { background: #fed7aa; color: #9a3412; }
    .badge-confirme    { background: #dbeafe; color: #1e40af; }
    .badge-maintenance { background: #fde68a; color: #92400e; }

    .lit-yes { color: #c2570d; font-weight: 700; }
    .lit-no  { color: #9ca3af; }

    /* ── EXTRA DESCRIPTION ── */
    .extra {
        margin-top: 10px;
        background: #fafafa;
        border: 1px solid #e5e7eb;
        border-radius: 5px;
        padding: 10px 12px;
        font-size: 10px;
        color: #4b5563;
        line-height: 1.5;
    }

    /* ── FOOTER ── */
    .pdf-footer {
        position: absolute;
        bottom: 12mm;
        left: 22px;
        right: 22px;
        border-top: 1px solid #e5e7eb;
        padding-top: 6px;
        font-size: 8.5px;
        text-align: center;
        color: #9ca3af;
    }
</style>
</head>
<body>

@php
    use Carbon\Carbon;
    $totalCount = count($panels);

    $statusMap = fn($s) => match ($s) {
        'libre'                    => ['label' => 'Disponible',  'class' => 'badge-libre'],
        'occupe'                   => ['label' => 'Occupé',      'class' => 'badge-occupe'],
        'option_periode', 'option' => ['label' => 'En option',   'class' => 'badge-option'],
        'confirme'                 => ['label' => 'Confirmé',    'class' => 'badge-confirme'],
        'maintenance'              => ['label' => 'Maintenance', 'class' => 'badge-maintenance'],
        default                    => ['label' => 'Indisponible','class' => 'badge-occupe'],
    };

    // Logo CIBLE CI : passé par PdfAssets::getLogoPdf() — fallback inline
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

    // Règle : par défaut, pas de prix ni de statut
    $showPricing = $showPricing ?? !($hideStatus ?? true);
@endphp

@foreach ($panels as $index => $p)
    @php
        $pageNum  = $index + 1;
        $status   = $statusMap($p['display_status'] ?? 'occupe');
        $traffic  = (int) ($p['daily_traffic'] ?? 0);
        $zoneDesc = $p['zone_description'] ?? '';

        $imgSrc = $p['photo_src'] ?? null;
        if (!$imgSrc && !empty($p['photo_path']) && file_exists($p['photo_path'])) {
            $ext  = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
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
        $surface   = $p['surface_m2'] ?? null;
        $category  = $p['category']   ?? '—';
        $isLit     = (bool) ($p['is_lit'] ?? false);
        $latitude  = $p['latitude']   ?? null;
        $longitude = $p['longitude']  ?? null;
        $rate      = (float) ($p['monthly_rate'] ?? 0);
    @endphp

    <div class="page">

        {{-- ─── HEADER UNIFORME ─── --}}
        <div class="pdf-header">
            <div class="logo-cell">
                <img src="{{ $logoSrc }}" alt="CIBLE CI">
            </div>
            <div class="title-cell">
                <h1>Fiche <span class="accent">Panneau</span></h1>
            </div>
            <div class="meta-cell">
                Généré le {{ $generated ?? now()->format('d/m/Y à H:i') }}<br>
                Page {{ $pageNum }} / {{ $totalCount }}
            </div>
        </div>

        <div class="container">

            {{-- ─── BANNER RÉFÉRENCE ─── --}}
            <div class="ref-banner">
                <div class="ref-tag">{{ $p['reference'] ?? '—' }}</div>
                <div class="ref-name">{{ $p['name'] ?? '' }}</div>
                <div class="ref-loc">
                    {{ $commune }}{{ $zone !== '—' ? ' — '.$zone : '' }}
                </div>
            </div>

            {{-- ─── PHOTO ─── --}}
            <div class="photo-wrap">
                @if($imgSrc)
                    <img src="{{ $imgSrc }}" alt="{{ $p['reference'] ?? '' }}">
                @else
                    <span class="photo-empty">— Aucune photo disponible —</span>
                @endif
            </div>

            {{-- ─── CARACTÉRISTIQUES (2 colonnes) ─── --}}
            <h2 class="section">Caractéristiques techniques</h2>

            <div class="two-cols">
                <div class="col">
                    <table class="info-table">
                        <tr>
                            <td class="lbl">Référence</td>
                            <td class="val"><strong>{{ $p['reference'] ?? '—' }}</strong></td>
                        </tr>
                        <tr>
                            <td class="lbl">Type de support</td>
                            <td class="val">{{ $category }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Format</td>
                            <td class="val">{{ $format }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Dimensions impression</td>
                            <td class="val">
                                {{ $dims ?: '—' }}
                                @if($surface)
                                    <br><span style="color:#6b7280;font-size:9px;">Surface : {{ $surface }} m²</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Éclairage</td>
                            <td class="val">
                                @if($isLit)
                                    <span class="lit-yes">💡 Éclairé (LED)</span>
                                @else
                                    <span class="lit-no">Non éclairé</span>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="col">
                    <table class="info-table">
                        <tr>
                            <td class="lbl">Commune</td>
                            <td class="val">{{ $commune }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Zone</td>
                            <td class="val">{{ $zone }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">Coordonnées GPS</td>
                            <td class="val">
                                @if($latitude && $longitude)
                                    <span style="font-family:monospace;font-size:9px;">
                                        {{ number_format((float) $latitude, 6, '.', '') }}, {{ number_format((float) $longitude, 6, '.', '') }}
                                    </span>
                                    @if(!empty($p['gps_link']))
                                        <br><a href="{{ $p['gps_link'] }}" style="color:#2563eb;font-size:9px;">📍 Voir sur Google Maps</a>
                                    @endif
                                @else
                                    <span style="color:#9ca3af">Non renseignées</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="lbl">Trafic journalier</td>
                            <td class="val">
                                @if($traffic > 0)
                                    <strong>{{ number_format($traffic, 0, ',', ' ') }}</strong>
                                    <span style="color:#6b7280;font-size:9px;">contacts / jour</span>
                                @else
                                    <span style="color:#9ca3af">—</span>
                                @endif
                            </td>
                        </tr>

                        {{-- ─── Tarif & statut UNIQUEMENT si showPricing ─── --}}
                        @if($showPricing)
                            <tr>
                                <td class="lbl">Tarif mensuel HT</td>
                                <td class="val">
                                    @if($rate > 0)
                                        <strong style="color:#c2570d;">{{ number_format($rate, 0, ',', ' ') }} FCFA</strong>
                                    @else
                                        <span style="color:#9ca3af">Sur devis</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="lbl">Statut actuel</td>
                                <td class="val"><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                            </tr>
                        @endif
                    </table>
                </div>
            </div>

            @if($zoneDesc)
                <div class="extra">
                    <strong style="color:#e8a020;font-size:9px;text-transform:uppercase;letter-spacing:0.8px;">Description / Environnement</strong><br>
                    {{ \Illuminate\Support\Str::limit($zoneDesc, 320) }}
                </div>
            @endif

        </div>

        <div class="pdf-footer">
            CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
            @isset($reservation_ref) · Réf. {{ $reservation_ref }}@endisset
            @isset($client_name) · Client : {{ $client_name }}@endisset
        </div>
    </div>
@endforeach

</body>
</html>

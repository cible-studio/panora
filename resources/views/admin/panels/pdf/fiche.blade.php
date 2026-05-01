<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche panneau — {{ $panel['reference'] }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            line-height: 1.45;
        }

        .container { padding: 24px 28px; }

        /* ── HEADER UNIFORME (logo gauche / titre centre / méta droite) ── */
        .pdf-header {
            background: #0d1117;
            color: #ffffff;
            padding: 14px 22px;
            display: table;
            width: 100%;
            border-bottom: 3px solid #e8a020;
            margin-bottom: 18px;
        }
        .pdf-header > div { display: table-cell; vertical-align: middle; }
        .pdf-header .logo-cell  { width: 30%; text-align: left; }
        .pdf-header .title-cell { width: 40%; text-align: center; }
        .pdf-header .meta-cell  { width: 30%; text-align: right; font-size: 9px; color: #9ca3af; }
        .pdf-header img { height: 38px; width: auto; vertical-align: middle; }
        .pdf-header h1 {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #ffffff;
            text-transform: uppercase;
        }
        .pdf-header .accent { color: #e8a020; }

        /* ── REF EN GRAND ── */
        .ref-banner {
            background: #fff7ed;
            border-left: 4px solid #e8a020;
            padding: 12px 18px;
            margin-bottom: 18px;
        }
        .ref-banner .ref-tag {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 22px;
            color: #c2570d;
            letter-spacing: 2px;
        }
        .ref-banner .ref-name {
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
            margin-top: 2px;
        }
        .ref-banner .ref-loc {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }

        /* ── PHOTO PRINCIPALE ── */
        .photo-wrap {
            text-align: center;
            margin-bottom: 16px;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 6px;
            height: 220px;
            line-height: 0;
        }
        .photo-wrap img {
            max-width: 100%;
            max-height: 208px;
            object-fit: contain;
        }
        .photo-empty {
            display: inline-block;
            line-height: 200px;
            color: #9ca3af;
            font-size: 13px;
        }

        /* ── TABLEAU DES CARACTÉRISTIQUES ── */
        h2.section {
            font-size: 11px;
            font-weight: 700;
            color: #e8a020;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 4px;
            margin: 14px 0 8px;
        }

        table.specs {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.specs td {
            padding: 7px 10px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
        }
        table.specs td.lbl {
            color: #6b7280;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9.5px;
            letter-spacing: 1px;
            width: 40%;
            background: #fafafa;
        }
        table.specs td.val {
            color: #1f2937;
            font-weight: 500;
        }
        table.specs a { color: #2563eb; text-decoration: none; }
        table.specs .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-libre       { background: #d1fae5; color: #065f46; }
        .badge-occupe      { background: #fee2e2; color: #991b1b; }
        .badge-option      { background: #fed7aa; color: #9a3412; }
        .badge-confirme    { background: #dbeafe; color: #1e40af; }
        .badge-maintenance { background: #fde68a; color: #92400e; }
        .badge-default     { background: #e5e7eb; color: #4b5563; }

        /* ── 2 COLONNES POUR ÉCONOMISER L'ESPACE ── */
        .two-cols { display: table; width: 100%; }
        .two-cols .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        .two-cols .col:last-child { padding-right: 0; padding-left: 12px; }

        /* ── FOOTER ── */
        .pdf-footer {
            position: fixed;
            bottom: 18px;
            left: 28px;
            right: 28px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-size: 9px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $statusLabels = [
            'libre'       => ['Disponible',  'badge-libre'],
            'occupe'      => ['Occupé',      'badge-occupe'],
            'option'      => ['En option',   'badge-option'],
            'confirme'    => ['Confirmé',    'badge-confirme'],
            'maintenance' => ['Maintenance', 'badge-maintenance'],
        ];
        $st = $statusLabels[$panel['display_status'] ?? 'libre'] ?? [ucfirst($panel['display_status'] ?? '—'), 'badge-default'];
    @endphp

    {{-- ─── HEADER UNIFORME ─── --}}
    <div class="pdf-header">
        <div class="logo-cell">
            <img src="{{ $logoSrc }}" alt="CIBLE CI">
        </div>
        <div class="title-cell">
            <h1>Fiche <span class="accent">Panneau</span></h1>
        </div>
        <div class="meta-cell">
            Généré le {{ $generated }}<br>
            Réf. {{ $panel['reference'] }}
        </div>
    </div>

    <div class="container">

        {{-- ─── BANNER RÉFÉRENCE + LOCALISATION ─── --}}
        <div class="ref-banner">
            <div class="ref-tag">{{ $panel['reference'] }}</div>
            <div class="ref-name">{{ $panel['name'] }}</div>
            <div class="ref-loc">
                @if(!empty($panel['adresse'])){{ $panel['adresse'] }} —@endif
                @if(!empty($panel['quartier'])){{ $panel['quartier'] }}, @endif
                {{ $panel['commune'] }}{{ $panel['zone'] !== '—' ? ' / '.$panel['zone'] : '' }}
            </div>
        </div>

        {{-- ─── PHOTO ─── --}}
        <div class="photo-wrap">
            @if(!empty($panel['photo_src']))
                <img src="{{ $panel['photo_src'] }}" alt="Panneau {{ $panel['reference'] }}">
            @else
                <span class="photo-empty">— Aucune photo disponible —</span>
            @endif
        </div>

        {{-- ─── CARACTÉRISTIQUES (2 colonnes) ─── --}}
        <h2 class="section">Caractéristiques techniques</h2>

        <div class="two-cols">
            <div class="col">
                <table class="specs">
                    <tr><td class="lbl">Référence</td><td class="val"><strong>{{ $panel['reference'] }}</strong></td></tr>
                    <tr><td class="lbl">Désignation</td><td class="val">{{ $panel['name'] }}</td></tr>
                    <tr><td class="lbl">Type de support</td><td class="val">{{ $panel['category'] ?: '—' }}</td></tr>
                    <tr><td class="lbl">Format</td><td class="val">{{ $panel['format'] ?: '—' }}</td></tr>
                    <tr>
                        <td class="lbl">Dimensions impression</td>
                        <td class="val">
                            {{ $panel['dimensions'] ?: '—' }}
                            @if(!empty($panel['surface_m2']))
                                <br><span style="color:#6b7280;font-size:10px;">Surface : {{ $panel['surface_m2'] }} m²</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Éclairage</td>
                        <td class="val">
                            @if($panel['is_lit'])
                                <span style="color:#059669;font-weight:700;">💡 Éclairé (LED)</span>
                            @else
                                <span style="color:#9ca3af;">Non éclairé</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </div>

            <div class="col">
                <table class="specs">
                    <tr><td class="lbl">Commune</td><td class="val">{{ $panel['commune'] }}</td></tr>
                    <tr><td class="lbl">Zone</td><td class="val">{{ $panel['zone'] }}</td></tr>
                    <tr>
                        <td class="lbl">Coordonnées GPS</td>
                        <td class="val">
                            @if($panel['latitude'] !== null && $panel['longitude'] !== null)
                                <span style="font-family:monospace;">
                                    {{ number_format($panel['latitude'], 6, '.', '') }},
                                    {{ number_format($panel['longitude'], 6, '.', '') }}
                                </span>
                                @if(!empty($panel['gps_link']))
                                    <br><a href="{{ $panel['gps_link'] }}">📍 Voir sur Google Maps</a>
                                @endif
                            @else
                                <span style="color:#9ca3af;">Non renseignées</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Trafic journalier</td>
                        <td class="val">
                            @if($panel['daily_traffic'] > 0)
                                <strong>{{ number_format($panel['daily_traffic'], 0, ',', ' ') }}</strong>
                                <span style="color:#6b7280;font-size:10px;">contacts / jour</span>
                            @else
                                <span style="color:#9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Tarif mensuel</td>
                        <td class="val">
                            @if($panel['monthly_rate'] > 0)
                                <strong style="color:#e8a020;">{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</strong>
                            @else
                                <span style="color:#9ca3af;">Sur devis</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="lbl">Statut actuel</td><td class="val"><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td></tr>
                </table>
            </div>
        </div>

        @if(!empty($panel['zone_description']))
            <h2 class="section">Description / Environnement</h2>
            <div style="font-size:11px;color:#374151;line-height:1.5;border:1px solid #e5e7eb;border-radius:4px;padding:10px 12px;background:#fafafa;">
                {{ $panel['zone_description'] }}
            </div>
        @endif

    </div>

    <div class="pdf-footer">
        CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des panneaux — CIBLE CI</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 9.5px;
            line-height: 1.4;
        }

        .container { padding: 14px 18px 60px; }

        /* ── HEADER UNIFORME (cohérent avec fiche panneau) ── */
        .pdf-header {
            background: #0d1117;
            color: #ffffff;
            padding: 14px 22px;
            display: table;
            width: 100%;
            border-bottom: 3px solid #e8a020;
            margin-bottom: 14px;
        }
        .pdf-header > div { display: table-cell; vertical-align: middle; }
        .pdf-header .logo-cell  { width: 30%; text-align: left; }
        .pdf-header .title-cell { width: 40%; text-align: center; }
        .pdf-header .meta-cell  { width: 30%; text-align: right; font-size: 9px; color: #9ca3af; }
        .pdf-header img {
            height: 38px;
            width: auto;
            vertical-align: middle;
        }
        .pdf-header h1 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #ffffff;
            text-transform: uppercase;
        }
        .pdf-header .accent { color: #e8a020; }

        /* ── BANNER CONTEXTE (période / réservation / client) ── */
        .context-banner {
            background: #fff7ed;
            border-left: 4px solid #e8a020;
            padding: 10px 16px;
            margin-bottom: 14px;
            font-size: 10px;
            color: #1f2937;
            display: table;
            width: 100%;
        }
        .context-banner > div { display: table-cell; vertical-align: middle; }
        .context-banner .left  { text-align: left; }
        .context-banner .right { text-align: right; color: #c2570d; font-weight: 600; }
        .context-banner strong { color: #c2570d; }

        /* ── TABLE ── */
        h2.section-title {
            font-size: 10px;
            font-weight: 700;
            color: #e8a020;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }
        table.list thead th {
            background: #f3f4f6;
            color: #374151;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8px;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
            white-space: nowrap;
        }
        table.list tbody td {
            padding: 7px 6px;
            border-bottom: 1px solid #f3f4f6;
            vertical-align: top;
            color: #1f2937;
        }
        table.list tbody tr:nth-child(even) { background: #fafafa; }

        .ref {
            font-family: 'Courier New', monospace;
            color: #c2570d;
            font-weight: 700;
            font-size: 9.5px;
        }
        .lit-badge {
            display: inline-block;
            font-size: 8.5px;
            font-weight: 600;
            color: #c2570d;
        }
        .non-lit-badge {
            font-size: 8.5px;
            color: #9ca3af;
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }

        /* ── BADGES STATUT (uniquement si showPricing) ── */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-libre       { background: #d1fae5; color: #065f46; }
        .badge-occupe      { background: #fee2e2; color: #991b1b; }
        .badge-option      { background: #fed7aa; color: #9a3412; }
        .badge-confirme    { background: #dbeafe; color: #1e40af; }
        .badge-maintenance { background: #fde68a; color: #92400e; }

        /* ── TOTAUX (uniquement si showPricing) ── */
        .totals {
            margin-top: 14px;
            padding: 12px 16px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 5px;
            display: table;
            width: 100%;
            font-size: 11px;
        }
        .totals > div { display: table-cell; vertical-align: middle; }
        .totals .label { color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; }
        .totals .amount {
            color: #c2570d;
            font-weight: 700;
            font-size: 14px;
            text-align: right;
        }

        /* ── FOOTER FIXE ── */
        .pdf-footer {
            position: fixed;
            bottom: 14px;
            left: 22px;
            right: 22px;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
            font-size: 8.5px;
            color: #9ca3af;
            text-align: center;
        }
    </style>
</head>
<body>

@php
    // Logo : passé par PdfAssets::getLogoPdf() — fallback inline si la vue est rendue sans.
    // logob.png (et pas logol) car le header est foncé (#0d1117).
    if (!isset($logoSrc)) {
        $logoPath = public_path('images/logob.png');
        $logoSrc = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50">'
                .'<rect width="180" height="50" rx="6" fill="#0d1117"/>'
                .'<text x="90" y="34" font-family="Arial" font-weight="900" font-size="20" fill="#e8a020" text-anchor="middle">CIBLE CI</text>'
                .'</svg>'
              );
    }

    // Logique d'affichage : par défaut PAS de prix ni de statut.
    // Pour afficher : envoyer show_pricing=1 (ou hide_status=0).
    $showPricing = $showPricing ?? !($hideStatus ?? true);
    $count       = count($panels);
@endphp

{{-- ── HEADER UNIFORME ── --}}
<div class="pdf-header">
    <div class="logo-cell">
        <img src="{{ $logoSrc }}" alt="CIBLE CI">
    </div>
    <div class="title-cell">
        <h1>Sélection de <span class="accent">panneaux</span></h1>
    </div>
    <div class="meta-cell">
        Généré le {{ $generated ?? now()->format('d/m/Y à H:i') }}<br>
        {{ $count }} panneau{{ $count > 1 ? 'x' : '' }}
    </div>
</div>

<div class="container">

    {{-- ── BANNER PÉRIODE / CONTEXTE ── --}}
    @if(($startDate ?? null) || isset($reservation_ref) || isset($client_name))
        <div class="context-banner">
            <div class="left">
                @if(isset($reservation_ref))
                    Réf. réservation : <strong>{{ $reservation_ref }}</strong>
                    @if(isset($client_name)) — Client : <strong>{{ $client_name }}</strong>@endif
                    <br>
                @endif
                @if(($startDate ?? null) && ($endDate ?? null))
                    Période : <strong>{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} → {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</strong>
                @endif
            </div>
            <div class="right">
                {{ $count }} emplacement{{ $count > 1 ? 's' : '' }}
            </div>
        </div>
    @endif

    {{-- ── TABLEAU ── --}}
    <table class="list">
        <thead>
            <tr>
                <th style="width:9%">Réf.</th>
                <th style="width:20%">Emplacement</th>
                <th style="width:10%">Commune</th>
                <th style="width:8%">Zone</th>
                <th style="width:10%">Format</th>
                <th style="width:8%">Dimensions</th>
                <th style="width:10%">Catégorie</th>
                <th style="width:5%">Éclair.</th>
                <th class="num" style="width:7%">Trafic/j (estimatif)</th>
                {{-- Statut affiché sauf si hide_status=1 — permet de générer
                     un PDF "propre" sans information d'occupation interne. --}}
                @if(!$hideStatus)
                    <th style="width:8%">Statut</th>
                @endif
                @if($showPricing)
                    <th class="num" style="width:9%">Prix HT/mois</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @php $hasOptionInList = false; @endphp
            @foreach($panels as $p)
                @php
                    // Le contrôleur passe display_status (libre/option_periode/occupe/...)
                    // + release_date (dernière fin de résa bloquante). Règle métier
                    // PDF proposition : on n'affiche le badge QUE si le panneau est
                    // réellement occupé (confirme/occupe). Tout le reste reste vide
                    // pour ne pas polluer le doc commercial transmis au client.
                    $statusValue = $p->display_status
                        ?? (is_object($p->status ?? null) ? ($p->status->value ?? null) : ($p->status ?? null))
                        ?? 'libre';

                    $isOccupied = in_array($statusValue, ['occupe', 'occupé', 'confirme'], true);
                    $releaseDate = $p->release_date ?? null;
                    $releaseLabel = $releaseDate ? \Carbon\Carbon::parse($releaseDate)->format('d/m/Y') : null;

                    $statusMeta = $isOccupied
                        ? [
                            'label' => $releaseLabel ? 'Occupé jusqu\'au ' . $releaseLabel : 'Occupé',
                            'class' => 'badge-occupe',
                        ]
                        : null;

                    if (in_array($statusValue, ['option', 'option_periode'], true)) {
                        $hasOptionInList = true;
                    }
                    $traffic   = (int) ($p->daily_traffic ?? 0);
                    $isLit     = (bool) ($p->is_lit ?? false);
                    $reference = $p->reference ?? '—';
                    $name      = $p->name      ?? '—';
                    $communeVal = $p->commune ?? null;
                    $commune    = is_object($communeVal) ? ($communeVal->name ?? '—') : ($communeVal ?? '—');
                    $zoneVal    = $p->zone ?? null;
                    $zone       = is_object($zoneVal) ? ($zoneVal->name ?? '—') : ($zoneVal ?? '—');
                    $formatVal  = $p->format ?? null;
                    $format     = is_object($formatVal) ? ($formatVal->name ?? '—') : ($formatVal ?? '—');
                    $categoryVal = $p->category ?? null;
                    $category    = is_object($categoryVal) ? ($categoryVal->name ?? '—') : ($categoryVal ?? '—');
                    $rate      = (float) ($p->monthly_rate ?? 0);

                    $dims = null;
                    if (is_object($formatVal) && isset($formatVal->width) && isset($formatVal->height) && $formatVal->width && $formatVal->height) {
                        $w = rtrim(rtrim(number_format($formatVal->width, 2, '.', ''), '0'), '.');
                        $h = rtrim(rtrim(number_format($formatVal->height, 2, '.', ''), '0'), '.');
                        $dims = "{$w} × {$h} m";
                    } elseif (isset($p->dimensions) && $p->dimensions) {
                        $dims = $p->dimensions;
                    }
                @endphp
                <tr>
                    <td><span class="ref">{{ $reference }}</span></td>
                    <td style="font-weight:500">{{ $name }}</td>
                    <td>{{ $commune }}</td>
                    <td>{{ $zone }}</td>
                    <td>{{ $format }}</td>
                    <td>{{ $dims ?? '—' }}</td>
                    <td>{{ $category }}</td>
                    <td>
                        @if($isLit)
                            <span class="lit-badge">💡 LED</span>
                        @else
                            <span class="non-lit-badge">—</span>
                        @endif
                    </td>
                    <td class="num">{{ $traffic > 0 ? number_format($traffic, 0, ',', ' ') : '—' }}</td>
                    {{-- Cellule Statut — masquée si hide_status=1 --}}
                    @if(!$hideStatus)
                        <td>
                            @if($statusMeta)
                                <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                            @endif
                        </td>
                    @endif
                    @if($showPricing)
                        <td class="num" style="font-weight:600;color:#c2570d">
                            {{ $rate > 0 ? number_format($rate, 0, ',', ' ') : '—' }}
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── NOTE LÉGALE OPTIONS — affichée seulement si la liste contient
         au moins un panneau "En option". L'admin sait que ces lignes ne
         sont PAS des réservations fermes et qu'elles peuvent être proposées
         à un autre client en parallèle. --}}
    @if($hasOptionInList)
        <div style="margin-top:12px;padding:9px 14px;background:#fffbeb;border-left:3px solid #f97316;border-radius:4px;font-size:9px;color:#9a3412;line-height:1.5">
            <strong>⚠ Mention importante :</strong>
            Les panneaux marqués <span class="badge badge-option" style="margin:0 2px">En option</span>
            font l'objet d'une réservation provisoire non confirmée. Ils restent
            mobilisables pour une autre proposition tant que le client en option
            n'a pas validé son devis.
        </div>
    @endif

    {{-- ── TOTAUX (uniquement si showPricing activé) ── --}}
    @if($showPricing && isset($totalMensuel) && $totalMensuel > 0)
        <div class="totals">
            <div>
                <div class="label">Total mensuel HT</div>
                <strong style="color:#c2570d;font-size:14px">{{ number_format($totalMensuel, 0, ',', ' ') }} FCFA</strong>
                @if(isset($startDate) && isset($endDate) && $startDate && $endDate)
                    <div class="label" style="margin-top:6px">Total sur {{ $dureeEnMois ?? 1 }} mois</div>
                    <strong style="color:#c2570d;font-size:14px">{{ number_format($totalPeriode ?? 0, 0, ',', ' ') }} FCFA</strong>
                @endif
            </div>
            <div class="amount">
                {{ $count }} emplacement{{ $count > 1 ? 's' : '' }}
                @if(($dureeEnMois ?? 0) > 0)
                    <div style="font-size:9px;color:#9ca3af;font-weight:400;margin-top:3px">{{ $dureeEnMois }} mois de campagne</div>
                @endif
            </div>
        </div>
    @endif

</div>

<div class="pdf-footer">
    CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
</div>

</body>
</html>

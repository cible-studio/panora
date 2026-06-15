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

        /* ────────────────────────────────────────────────────────────
           PAGE DE GARDE PAR COMMUNE
           Avant chaque sous-liste (Cocody, Plateau…), on insère une
           page d'annonce. La page de garde tient sur une page A4 et
           force un page-break-after pour démarrer le tableau sur la
           page suivante. La 2e/3e commune force un page-break-before
           pour ne pas se coller au tableau précédent.
           ──────────────────────────────────────────────────────── */
        .commune-cover {
            page-break-after: always;
        }
        .commune-cover.not-first {
            page-break-before: always;
        }
        .cover-body {
            padding: 60px 50px 30px;
            text-align: center;
        }
        .cover-eyebrow {
            font-size: 10.5px;
            font-weight: 700;
            color: #9ca3af;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 26px;
        }
        .cover-commune {
            font-size: 42px;
            font-weight: 900;
            color: #0d1117;
            text-transform: uppercase;
            letter-spacing: 2px;
            line-height: 1.1;
            margin-bottom: 8px;
        }
        .cover-count {
            font-size: 14px;
            color: #c2570d;
            font-weight: 600;
            margin-bottom: 28px;
        }
        .cover-divider {
            width: 80px;
            height: 4px;
            background: #e8a020;
            margin: 0 auto 30px;
        }
        table.cover-stats {
            width: 88%;
            margin: 0 auto 32px;
            border-collapse: separate;
            border-spacing: 10px 0;
        }
        table.cover-stats td {
            width: 33.33%;
            text-align: center;
            padding: 16px 8px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #fafafa;
            vertical-align: middle;
        }
        .stat-num {
            font-size: 28px;
            font-weight: 800;
            color: #0d1117;
            line-height: 1;
            margin-bottom: 5px;
        }
        .stat-lbl {
            font-size: 8.5px;
            font-weight: 700;
            color: #6b7280;
            letter-spacing: 1.4px;
            text-transform: uppercase;
        }
        .cover-note {
            font-size: 10.5px;
            color: #4b5563;
            line-height: 1.6;
            margin: 0 auto;
            padding: 13px 16px;
            background: #fff7ed;
            border-radius: 6px;
            border-left: 3px solid #e8a020;
            max-width: 92%;
            text-align: left;
        }
        .cover-zones {
            margin-top: 20px;
            padding: 11px 14px;
            background: #f9fafb;
            border-radius: 6px;
            font-size: 9.5px;
            color: #4b5563;
            line-height: 1.55;
            text-align: left;
            max-width: 92%;
            margin-left: auto;
            margin-right: auto;
        }
        .cover-zones strong {
            display: block;
            font-size: 8.5px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin-bottom: 4px;
        }

        /* Section title au-dessus de chaque sous-tableau */
        .section-commune-title {
            margin: 6px 0 10px;
            padding: 9px 14px;
            background: #0d1117;
            color: #fff;
            border-left: 4px solid #e8a020;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .section-commune-title .accent { color: #e8a020; font-weight: 600; }
        .section-commune-title .count {
            font-size: 10px;
            font-weight: 500;
            color: #9ca3af;
            margin-left: 6px;
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

    // ── GROUPEMENT PAR COMMUNE ────────────────────────────────────
    // Le client doit recevoir une page d'intro avant chaque
    // sous-liste de panneaux concernant une même commune.
    // $p->commune peut être un objet (Commune) ou une string → on
    // résout vers une string pour pouvoir grouper proprement.
    $resolveCommune = function ($p) {
        $c = is_object($p) ? ($p->commune ?? null) : ($p['commune'] ?? null);
        if (is_object($c)) {
            return trim((string) ($c->name ?? '')) ?: '—';
        }
        return trim((string) $c) ?: '—';
    };
    $resolveZone = function ($p) {
        $z = is_object($p) ? ($p->zone ?? null) : ($p['zone'] ?? null);
        if (is_object($z)) {
            return trim((string) ($z->name ?? ''));
        }
        return trim((string) $z);
    };

    $grouped = collect($panels)
        ->sortBy(fn ($p) => $resolveCommune($p), SORT_NATURAL | SORT_FLAG_CASE)
        ->groupBy(fn ($p) => $resolveCommune($p));

    $totalGroups = $grouped->count();
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

    {{-- ── TABLEAUX PAR COMMUNE (avec page de garde) ── --}}
    @php $hasOptionInList = false; @endphp

    @foreach($grouped as $communeName => $groupPanels)
        @php
            $groupIndex = $loop->iteration;
            $groupSize  = count($groupPanels);
            $isFirst    = $loop->first;

            $nbLit = collect($groupPanels)->filter(fn ($p) => !empty(is_object($p) ? $p->is_lit : $p['is_lit'] ?? false))->count();
            $zones = collect($groupPanels)
                ->map(fn ($p) => $resolveZone($p))
                ->filter(fn ($z) => $z !== '' && $z !== '—')
                ->unique()
                ->values();
            $formats = collect($groupPanels)
                ->map(function ($p) {
                    $f = is_object($p) ? ($p->format ?? null) : ($p['format'] ?? null);
                    return is_object($f) ? ($f->name ?? '') : (string) $f;
                })
                ->filter(fn ($f) => $f !== '' && $f !== '—')
                ->unique()
                ->values();
        @endphp

        {{-- ═══════════════════ PAGE DE GARDE COMMUNE ═══════════════════ --}}
        <div class="commune-cover {{ $isFirst ? '' : 'not-first' }}">
            <div class="cover-body">
                <div class="cover-eyebrow">Commune {{ $groupIndex }} sur {{ $totalGroups }}</div>
                <div class="cover-commune">{{ $communeName }}</div>
                <div class="cover-count">
                    {{ $groupSize }} {{ $groupSize > 1 ? 'emplacements disponibles' : 'emplacement disponible' }}
                </div>
                <div class="cover-divider"></div>

                <table class="cover-stats">
                    <tr>
                        <td>
                            <div class="stat-num">{{ $groupSize }}</div>
                            <div class="stat-lbl">{{ $groupSize > 1 ? 'Panneaux' : 'Panneau' }}</div>
                        </td>
                        <td>
                            <div class="stat-num">{{ $nbLit }}</div>
                            <div class="stat-lbl">{{ $nbLit > 1 ? 'Éclairés LED' : 'Éclairé LED' }}</div>
                        </td>
                        <td>
                            <div class="stat-num">{{ $formats->count() }}</div>
                            <div class="stat-lbl">{{ $formats->count() > 1 ? 'Formats' : 'Format' }}</div>
                        </td>
                    </tr>
                </table>

                <div class="cover-note">
                    Le tableau suivant détaille les <strong>{{ $groupSize }}</strong>
                    emplacement{{ $groupSize > 1 ? 's' : '' }} disponible{{ $groupSize > 1 ? 's' : '' }}
                    à <strong>{{ $communeName }}</strong> : référence, caractéristiques techniques
                    et trafic estimatif.
                </div>

                @if($zones->isNotEmpty())
                    <div class="cover-zones">
                        <strong>Zones couvertes dans cette commune</strong>
                        {{ $zones->take(20)->implode(' · ') }}@if($zones->count() > 20) · …@endif
                    </div>
                @endif
            </div>
        </div>

        {{-- ═══════════════════ SOUS-TABLEAU DE LA COMMUNE ═══════════════════ --}}
        <div class="section-commune-title">
            {{ $communeName }} <span class="accent">·</span> Liste détaillée
            <span class="count">— {{ $groupSize }} {{ $groupSize > 1 ? 'emplacements' : 'emplacement' }}</span>
        </div>

        <table class="list">
            <thead>
                <tr>
                    <th style="width:10%">Réf.</th>
                    <th style="width:24%">Emplacement</th>
                    <th style="width:10%">Zone</th>
                    <th style="width:12%">Format</th>
                    <th style="width:10%">Dimensions</th>
                    <th style="width:12%">Catégorie</th>
                    <th style="width:6%">Éclair.</th>
                    <th class="num" style="width:8%">Trafic/j (estimatif)</th>
                    @if(!$hideStatus)
                        <th style="width:9%">Statut</th>
                    @endif
                    @if($showPricing)
                        <th class="num" style="width:10%">Prix HT/mois</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($groupPanels as $p)
                    @php
                        $statusValue = (is_object($p) ? ($p->display_status ?? null) : ($p['display_status'] ?? null))
                            ?? (is_object($p) && is_object($p->status ?? null) ? ($p->status->value ?? null) : (is_object($p) ? ($p->status ?? null) : ($p['status'] ?? null)))
                            ?? 'libre';

                        $isOccupied   = in_array($statusValue, ['occupe', 'occupé', 'confirme'], true);
                        $releaseDate  = is_object($p) ? ($p->release_date ?? null) : ($p['release_date'] ?? null);
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

                        $traffic   = (int) (is_object($p) ? ($p->daily_traffic ?? 0) : ($p['daily_traffic'] ?? 0));
                        $isLit     = (bool) (is_object($p) ? ($p->is_lit ?? false) : ($p['is_lit'] ?? false));
                        $reference = (is_object($p) ? ($p->reference ?? '—') : ($p['reference'] ?? '—'));
                        $name      = (is_object($p) ? ($p->name      ?? '—') : ($p['name']      ?? '—'));
                        $zone      = $resolveZone($p) ?: '—';

                        $formatVal = is_object($p) ? ($p->format ?? null) : ($p['format'] ?? null);
                        $format    = is_object($formatVal) ? ($formatVal->name ?? '—') : ($formatVal ?? '—');

                        $categoryVal = is_object($p) ? ($p->category ?? null) : ($p['category'] ?? null);
                        $category    = is_object($categoryVal) ? ($categoryVal->name ?? '—') : ($categoryVal ?? '—');

                        $rate = (float) (is_object($p) ? ($p->monthly_rate ?? 0) : ($p['monthly_rate'] ?? 0));

                        $dims = null;
                        if (is_object($formatVal) && isset($formatVal->width) && isset($formatVal->height) && $formatVal->width && $formatVal->height) {
                            $w = rtrim(rtrim(number_format($formatVal->width, 2, '.', ''), '0'), '.');
                            $h = rtrim(rtrim(number_format($formatVal->height, 2, '.', ''), '0'), '.');
                            $dims = "{$w} × {$h} m";
                        } else {
                            $rawDims = is_object($p) ? ($p->dimensions ?? null) : ($p['dimensions'] ?? null);
                            if ($rawDims) $dims = $rawDims;
                        }
                    @endphp
                    <tr>
                        <td><span class="ref">{{ $reference }}</span></td>
                        <td style="font-weight:500">{{ $name }}</td>
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
    @endforeach

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

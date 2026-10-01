<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des panneaux — CIBLE CI</title>
    @include('pdf.partials.charte-styles')
    {{-- 2026-10-01 — charte graphique : en-tête clair commun (liseré + logo
         CIBLE), pied commun fixe (charte-footer), palette $charte, polices
         Poppins / Nunito. Marges @page avec !important (DomPDF les annule
         sinon à cause de « * { margin:0 } ») : les suites de tableaux ne
         collent plus au bord de la page. Line-height resserrés (Nunito est
         plus haute que DejaVu Sans). Aucun poids 900 (cf. rapport). --}}
    <style>
        @page { margin: 10mm 10mm 18mm 10mm !important; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            color: {{ $charte['noir'] }};
            font-size: 9.5px;
            line-height: 1.2;
        }

        .container { padding: 0; }

        /* ── BANNER CONTEXTE (période / réservation / client) ── */
        .context-banner {
            background: {{ $charte['gris_clair'] }};
            border-left: 4px solid {{ $charte['rouge'] }};
            padding: 9px 16px;
            margin-bottom: 14px;
            font-size: 10px;
            color: {{ $charte['noir'] }};
            display: table;
            width: 100%;
        }
        .context-banner > div { display: table-cell; vertical-align: middle; }
        .context-banner .left  { text-align: left; }
        .context-banner .right { text-align: right; color: {{ $charte['rouge'] }}; font-weight: 700; }
        .context-banner strong { color: {{ $charte['rouge'] }}; }

        /* ── TABLE ── */
        h2.section-title {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 10px;
            font-weight: 700;
            color: {{ $charte['rouge'] }};
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
            background: {{ $charte['noir'] }};
            color: {{ $charte['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 600;
            text-transform: uppercase;
            font-size: 7.5px;
            letter-spacing: 0.4px;
            padding: 7px 6px;
            border: none;
            text-align: left;
            white-space: nowrap;
        }
        table.list tbody td {
            padding: 6px 6px;
            border-bottom: 1px solid {{ $charte['gris'] }};
            vertical-align: top;
            color: {{ $charte['noir'] }};
        }
        table.list tbody tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }

        .ref {
            font-family: {!! $charte['ff_mono'] !!};
            color: {{ $charte['rouge'] }};
            font-weight: 700;
            font-size: 9px;
        }
        .emplacement { font-weight: 600; }
        .lit-badge {
            display: inline-block;
            font-size: 8.5px;
            font-weight: 700;
            color: {{ $charte['rouge'] }};
        }
        .non-lit-badge {
            font-size: 8.5px;
            color: {{ $charte['texte_pale'] }};
        }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        table.list thead th.num { text-align: right; }
        .prix { font-weight: 700; color: {{ $charte['rouge'] }}; }

        /* ── BADGES STATUT (couleurs pleines de la charte) ── */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .2px;
            line-height: 1.3;
        }
        .badge-libre       { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
        .badge-occupe      { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }
        .badge-option      { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
        .badge-confirme    { background: {{ $charte['bleu'] }};  color: {{ $charte['blanc'] }}; }
        .badge-maintenance { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }

        /* ── NOTE LÉGALE OPTIONS ── */
        .note-option {
            margin-top: 12px;
            padding: 9px 14px;
            background: {{ $charte['jaune_clair'] }};
            border-left: 3px solid {{ $charte['jaune'] }};
            font-size: 9px;
            color: {{ $charte['noir'] }};
            line-height: 1.3;
        }
        .note-option .badge { margin: 0 2px; }

        /* ── TOTAUX (uniquement si showPricing) — bloc « total » sur fond
           noir, montants mis en avant en jaune (charte). ── */
        .totals {
            margin-top: 14px;
            padding: 12px 16px;
            background: {{ $charte['noir'] }};
            color: {{ $charte['blanc'] }};
            border-radius: 4px;
            display: table;
            width: 100%;
            font-size: 11px;
        }
        .totals > div { display: table-cell; vertical-align: middle; }
        .totals .label { color: {{ $charte['gris'] }}; font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.8px; }
        .totals .total-val {
            font-family: {!! $charte['ff_titres'] !!};
            color: {{ $charte['jaune'] }};
            font-size: 14px;
            font-weight: 700;
        }
        .totals .amount {
            color: {{ $charte['blanc'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 700;
            font-size: 14px;
            text-align: right;
        }
        .totals .amount-sub { font-size: 9px; color: {{ $charte['gris'] }}; font-weight: 400; margin-top: 3px; font-family: {!! $charte['ff_texte'] !!}; }

        /* ────────────────────────────────────────────────────────────
           PAGE DE GARDE PAR COMMUNE — design simple & moderne
           Aucun cadre, juste typographie + 2 fins filets encadrant un
           kicker "COMMUNE" et le nom en grand.
           Centrage vertical via padding-top calculé (DomPDF-safe).
           2026-10-01 — charte : liseré + logo en tête de page, filets
           aux 5 couleurs, nom en Poppins ; padding-top recalculé pour la
           zone utile (marges de page).
           ──────────────────────────────────────────────────────── */
        .commune-cover {
            page-break-before: always;
            page-break-after: always;
            text-align: center;
        }
        .cover-logo { padding-top: 10px; }
        .cover-logo img { height: 32px; width: auto; }
        .cover-body { padding-top: 34mm; }
        .cover-doc-type {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 11px;
            font-weight: 700;
            color: {{ $charte['rouge'] }};
            letter-spacing: 9px;
            text-transform: uppercase;
            margin-bottom: 22px;
        }
        .cover-rule {
            width: 60mm;
            margin: 0 auto 26px;
        }
        .cover-rule.bottom {
            margin: 30px auto 0;
        }
        .cover-kicker {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 10px;
            font-weight: 600;
            color: {{ $charte['texte_doux'] }};
            letter-spacing: 7px;
            text-transform: uppercase;
            margin-bottom: 18px;
        }
        .cover-name {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 52px;
            font-weight: 800;
            color: {{ $charte['noir'] }};
            text-transform: uppercase;
            letter-spacing: 5px;
            line-height: 1.1;
        }
        .cover-period {
            margin-top: 32px;
            display: inline-block;
            padding: 9px 22px;
            background: {{ $charte['gris_clair'] }};
            border: 1px solid {{ $charte['gris'] }};
            border-left: 3px solid {{ $charte['rouge'] }};
            border-radius: 4px;
            font-size: 12px;
            color: {{ $charte['texte_doux'] }};
            letter-spacing: .5px;
        }
        .cover-period-label {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 9px;
            font-weight: 700;
            color: {{ $charte['rouge'] }};
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-right: 8px;
        }
        .cover-period strong {
            color: {{ $charte['noir'] }};
            font-weight: 700;
            letter-spacing: .3px;
        }
        .cover-period-arrow {
            color: {{ $charte['rouge'] }};
            margin: 0 8px;
            font-weight: 700;
        }

        /* Section title au-dessus de chaque sous-tableau
           (2026-10-01 : bandeau clair à filet rouge, le noir est réservé
           à l'en-tête du tableau juste en dessous). */
        .section-commune-title {
            margin: 0 0 8px;
            padding: 8px 14px;
            background: {{ $charte['gris_clair'] }};
            color: {{ $charte['noir'] }};
            border-left: 4px solid {{ $charte['rouge'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .section-commune-title .accent { color: {{ $charte['rouge'] }}; font-weight: 700; }
        .section-commune-title .count {
            font-family: {!! $charte['ff_texte'] !!};
            font-size: 10px;
            font-weight: 400;
            color: {{ $charte['texte_doux'] }};
            margin-left: 6px;
        }
    </style>
</head>
<body>

@php
    // Logo : passé par PdfAssets::getLogoPdf() — fallback inline si la vue est rendue sans.
    // 2026-10-01 — charte : l'en-tête est désormais CLAIR → on affiche en
    // priorité $logoCibleLight (logol.png, injecté par le view composer) ;
    // $logoSrc (logob.png, texte blanc) reste le repli historique.
    $ch = $charte ?? \App\Support\PdfCharte::data();
    if (!isset($logoSrc)) {
        $logoPath = public_path('images/logob.png');
        $logoSrc = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50">'
                .'<rect width="180" height="50" rx="6" fill="' . $ch['blanc'] . '"/>'
                .'<text x="90" y="34" font-family="Arial" font-weight="700" font-size="20" fill="' . $ch['rouge'] . '" text-anchor="middle">CIBLE CI</text>'
                .'</svg>'
              );
    }
    $logoEntete = $logoCibleLight ?? $logoSrc;

    // Logique d'affichage : par défaut PAS de prix ni de statut.
    // Pour afficher : envoyer show_pricing=1 (ou hide_status=0).
    $showPricing = $showPricing ?? !($hideStatus ?? true);
    $count       = count($panels);

    // ── PÉRIODE EN FRANÇAIS ───────────────────────────────────────
    // Formatage manuel : Carbon::translatedFormat() dépend du locale
    // qui n'est pas toujours chargé côté DomPDF en prod.
    $moisFr = [1=>'janvier', 2=>'février', 3=>'mars', 4=>'avril', 5=>'mai',
               6=>'juin', 7=>'juillet', 8=>'août', 9=>'septembre',
               10=>'octobre', 11=>'novembre', 12=>'décembre'];
    $fmtPeriod = function ($d) use ($moisFr) {
        if (!$d) return null;
        try {
            $c = \Carbon\Carbon::parse($d);
            return $c->format('d') . ' ' . $moisFr[(int) $c->format('n')] . ' ' . $c->format('Y');
        } catch (\Throwable $e) {
            return null;
        }
    };
    $startFr = $fmtPeriod($startDate ?? null);
    $endFr   = $fmtPeriod($endDate   ?? null);

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

{{-- ── PIED FIXE COMMUN (texte du pied historique en 2e ligne) ── --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI · Régie Publicitaire · Abidjan, Côte d\'Ivoire · Document confidentiel',
])

{{-- ── EN-TÊTE (charte : liseré + logo clair + titre + méta) ── --}}
@include('pdf.partials.charte-header', [
    'docKicker' => 'Disponibilités',
    'docTitle'  => 'Sélection de panneaux',
    'docMeta'   => [
        'Généré le ' . ($generated ?? now()->format('d/m/Y à H:i')),
        $count . ' panneau' . ($count > 1 ? 'x' : ''),
    ],
    'docLogo'   => $logoEntete,
])

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
            $groupSize = count($groupPanels);
        @endphp

        {{-- ═══════════════════ PAGE DE GARDE COMMUNE (épuré) ═══════════════════ --}}
        <div class="commune-cover">
            @include('pdf.partials.charte-lisere')
            @if(!empty($logoEntete))
                <div class="cover-logo"><img src="{{ $logoEntete }}" alt="CIBLE CI"></div>
            @endif
            <div class="cover-body">
                <div class="cover-doc-type">Disponibilités</div>
                <div class="cover-rule">@include('pdf.partials.charte-lisere', ['lisereHeight' => 2])</div>
                <div class="cover-kicker">Commune</div>
                <div class="cover-name">{{ $communeName }}</div>
                <div class="cover-rule bottom">@include('pdf.partials.charte-lisere', ['lisereHeight' => 2])</div>
                @if($startFr && $endFr)
                    <div class="cover-period">
                        <span class="cover-period-label">Période</span>
                        <strong>{{ $startFr }}</strong>
                        <span class="cover-period-arrow">→</span>
                        <strong>{{ $endFr }}</strong>
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
                    {{-- Colonne TOUJOURS présente depuis 2026-09-22 : masquer
                         la disponibilité d'un panneau occupé induisait le
                         client en erreur (il le croyait libre toute la
                         période). Seul le PRIX reste conditionné. --}}
                    <th style="width:11%">Disponibilité</th>
                    @if($showPricing)
                        <th class="num" style="width:10%">Prix HT/mois</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @php
                    $dispoPeriodeLabel = ($startDate ?? null) && ($endDate ?? null)
                        ? 'Dispo. ' . \Carbon\Carbon::parse($startDate)->format('d/m/Y')
                          . ' → ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y')
                        : 'Disponible';
                @endphp
                @foreach($groupPanels as $p)
                    @php
                        $statusValue = (is_object($p) ? ($p->display_status ?? null) : ($p['display_status'] ?? null))
                            ?? (is_object($p) && is_object($p->status ?? null) ? ($p->status->value ?? null) : (is_object($p) ? ($p->status ?? null) : ($p['status'] ?? null)))
                            ?? 'libre';

                        $isOccupied   = in_array($statusValue, ['occupe', 'occupé', 'confirme'], true);
                        $releaseDate  = is_object($p) ? ($p->release_date ?? null) : ($p['release_date'] ?? null);
                        $releaseLabel = $releaseDate ? \Carbon\Carbon::parse($releaseDate)->format('d/m/Y') : null;

                        // 2026-09-22 : formulation positive + toujours
                        // affichée (cf. disponibilites-images.blade.php).
                        // La date stockée est le DERNIER jour d'occupation
                        // → disponible le lendemain.
                        $freeFromLabel = $releaseDate
                            ? \Carbon\Carbon::parse($releaseDate)->addDay()->format('d/m/Y')
                            : null;

                        // 2026-09-24 : le tableau répond à la PÉRIODE
                        // demandée. Un panneau libre sur la fenêtre mais
                        // occupé aujourd'hui affichait « Occupé ».
                        $statusMeta = $isOccupied
                            ? [
                                'label' => $freeFromLabel
                                    ? 'Dispo. dès le ' . $freeFromLabel
                                    : 'Occupé sur la période',
                                'class' => 'badge-occupe',
                            ]
                            : (in_array($statusValue, ['libre', 'disponible'], true)
                                ? ['label' => $dispoPeriodeLabel, 'class' => 'badge-libre']
                                : null);

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
                        <td class="emplacement">{{ $name }}</td>
                        <td>{{ $zone }}</td>
                        <td>{{ $format }}</td>
                        <td>{{ $dims ?? '—' }}</td>
                        <td>{{ $category }}</td>
                        <td>
                            @if($isLit)
                                <span class="lit-badge">LED</span>
                            @else
                                <span class="non-lit-badge">—</span>
                            @endif
                        </td>
                        <td class="num">{{ $traffic > 0 ? number_format($traffic, 0, ',', ' ') : '—' }}</td>
                        <td>
                            @if($statusMeta)
                                <span class="badge {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                            @else
                                <span class="badge badge-libre">Libre</span>
                            @endif
                        </td>
                        @if($showPricing)
                            <td class="num prix">
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
        <div class="note-option">
            <strong>⚠ Mention importante :</strong>
            Les panneaux marqués <span class="badge badge-option">En option</span>
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
                <strong class="total-val">{{ number_format($totalMensuel, 0, ',', ' ') }} FCFA</strong>
                @if(isset($startDate) && isset($endDate) && $startDate && $endDate)
                    <div class="label" style="margin-top:6px">Total sur {{ $dureeEnMois ?? 1 }} mois</div>
                    <strong class="total-val">{{ number_format($totalPeriode ?? 0, 0, ',', ' ') }} FCFA</strong>
                @endif
            </div>
            <div class="amount">
                {{ $count }} emplacement{{ $count > 1 ? 's' : '' }}
                @if(($dureeEnMois ?? 0) > 0)
                    <div class="amount-sub">{{ $dureeEnMois }} mois de campagne</div>
                @endif
            </div>
        </div>
    @endif

</div>

</body>
</html>

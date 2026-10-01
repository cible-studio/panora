<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Sélection panneaux — CIBLE CI</title>
@include('pdf.partials.charte-styles')
{{-- 2026-10-01 — charte graphique : en-tête clair (liseré + logo CIBLE),
     pied commun fixe, palette $charte, polices Poppins / Nunito.
     Marges @page avec !important (DomPDF les annule sinon à cause de
     « * { margin:0 } »). Plus de min-height sur .page : la zone utile fait
     269 mm, une hauteur minimale plus grande créerait des pages blanches.
     Line-height resserrés (Nunito est plus haute que DejaVu Sans) pour
     garder 1 panneau = 1 page. Aucun poids 900 (cf. rapport). --}}
<style>
    @page { size: A4 portrait; margin: 10mm 12mm 18mm 12mm !important; }
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        color: {{ $charte['noir'] }};
        font-size: 10px;
        line-height: 1.2;
    }

    .page { page-break-after: always; }
    .page:last-child { page-break-after: avoid; }

    /* ── EN-TÊTE DE FICHE (clair : liseré + logo + titre + méta) ── */
    .fiche-head { width: 100%; border-collapse: collapse; margin: 0; }
    .fiche-head td { vertical-align: middle; padding: 9px 0 8px; border: none; }
    .fiche-head .logo-cell { width: 1%; padding-right: 14px; white-space: nowrap; }
    .fiche-head .logo-cell img { height: 32px; width: auto; display: block; }
    .fiche-head .meta-cell { width: 1%; padding-left: 14px; text-align: right; white-space: nowrap; font-size: 8px; line-height: 1.4; color: {{ $charte['texte_doux'] }}; }
    .fiche-head h1 {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 15px;
        font-weight: 700;
        letter-spacing: .6px;
        text-transform: uppercase;
        color: {{ $charte['noir'] }};
        line-height: 1.15;
    }
    .fiche-head .accent { color: {{ $charte['rouge'] }}; }
    .fiche-rule { height: 2px; background: {{ $charte['noir'] }}; margin: 0 0 12px; font-size: 0; line-height: 0; }

    /* ── REF BANNER ── */
    .ref-banner {
        background: {{ $charte['gris_clair'] }};
        border-left: 4px solid {{ $charte['rouge'] }};
        padding: 10px 16px;
        margin-bottom: 12px;
    }
    .ref-banner .ref-tag {
        font-family: {!! $charte['ff_mono'] !!};
        font-weight: 700;
        font-size: 17px;
        color: {{ $charte['rouge'] }};
        letter-spacing: 1.2px;
    }
    .ref-banner .ref-name {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 12.5px;
        font-weight: 600;
        color: {{ $charte['noir'] }};
        margin-top: 2px;
    }
    .ref-banner .ref-loc {
        font-size: 10px;
        color: {{ $charte['texte_doux'] }};
        margin-top: 1px;
    }

    /* ── PHOTO ── */
    /* DomPDF gère mal object-fit ET max-height ensemble. On force la photo
       à respecter l'espace via width fixe + auto-height : DomPDF maintient
       alors le ratio et pas de bandeau noir résiduel.  */
    .photo-wrap {
        text-align: center;
        margin-bottom: 12px;
    }
    /* DomPDF préserve le ratio uniquement avec max-width + max-height
       (et SANS width/height/object-fit forcés, qui causent l'écrasement
       horizontal). L'image grandit autant que possible en respectant
       les deux bornes ET son ratio natif.
       2026-10-01 : 460 → 430 px (la zone utile perd les marges de page). */
    .photo-wrap img {
        max-width: 100%;
        max-height: 430px;
        border: 1px solid {{ $charte['gris'] }};
        border-radius: 4px;
    }
    /* Emplacement sans photo : cadre clair, filet rouge en tête (rappel
       du liseré), libellé discret. Une seule boîte CSS, aucune image. */
    .photo-empty {
        display: block;
        background: {{ $charte['gris_clair'] }};
        border: 1px solid {{ $charte['gris'] }};
        border-top: 3px solid {{ $charte['rouge'] }};
        padding: 170px 0;
        color: {{ $charte['texte_pale'] }};
        font-family: {!! $charte['ff_titres'] !!};
        font-weight: 600;
        font-size: 10.5px;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* ── SECTIONS ── */
    h2.section {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 10.5px;
        font-weight: 700;
        color: {{ $charte['noir'] }};
        text-transform: uppercase;
        letter-spacing: 1px;
        border-left: 3px solid {{ $charte['rouge'] }};
        border-bottom: 1px solid {{ $charte['gris'] }};
        padding: 0 0 4px 8px;
        margin: 10px 0 8px;
    }

    /* ── INFO TABLE ── */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    .info-table td {
        padding: 6px 9px;
        border-bottom: 1px solid {{ $charte['gris'] }};
        vertical-align: top;
    }
    .info-table td.lbl {
        color: {{ $charte['texte_doux'] }};
        font-weight: 700;
        text-transform: uppercase;
        font-size: 7.5px;
        letter-spacing: 0.6px;
        width: 38%;
        background: {{ $charte['gris_clair'] }};
    }
    .info-table td.val { color: {{ $charte['noir'] }}; }
    .info-table a { color: {{ $charte['bleu'] }}; text-decoration: none; }
    .val-sub  { color: {{ $charte['texte_doux'] }}; font-size: 9px; }
    .val-vide { color: {{ $charte['texte_pale'] }}; }
    .val-gps  { font-family: {!! $charte['ff_mono'] !!}; font-size: 9px; }
    .val-link { color: {{ $charte['bleu'] }}; font-size: 9px; }

    /* ── 2 COLONNES ── */
    /* Real <table> outer wrapper — DomPDF gère mal display:table-cell sur
       div, et finit par couper le rendu après la photo si une cellule
       contient une table imbriquée ratée. */
    table.two-cols { width: 100%; border-collapse: collapse; }
    table.two-cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 5px; }
    table.two-cols > tbody > tr > td:first-child { padding-left: 0; padding-right: 10px; }
    table.two-cols > tbody > tr > td:last-child { padding-left: 10px; padding-right: 0; }

    /* ── BADGES (couleurs pleines de la charte) ── */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 8.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .3px;
        line-height: 1.3;
    }
    .badge-libre       { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
    .badge-occupe      { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }
    .badge-option      { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .badge-confirme    { background: {{ $charte['bleu'] }};  color: {{ $charte['blanc'] }}; }
    .badge-maintenance { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }

    .lit-yes { color: {{ $charte['rouge'] }}; font-weight: 700; }
    .lit-no  { color: {{ $charte['texte_pale'] }}; }
    .tarif      { color: {{ $charte['rouge'] }}; }
    .tarif-zero { color: {{ $charte['vert'] }}; }

    /* ── EXTRA DESCRIPTION ── */
    .extra {
        margin-top: 10px;
        background: {{ $charte['gris_clair'] }};
        border: 1px solid {{ $charte['gris'] }};
        border-left: 3px solid {{ $charte['rouge'] }};
        padding: 9px 12px;
        font-size: 10px;
        color: {{ $charte['texte_doux'] }};
        line-height: 1.3;
    }
    .extra-title {
        font-family: {!! $charte['ff_titres'] !!};
        color: {{ $charte['rouge'] }};
        font-size: 8.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }

    /* ────────────────────────────────────────────────────────────────
       PAGE DE GARDE PAR COMMUNE — design simple & moderne
       Aucun cadre, aucune décoration lourde : juste de la respiration
       et de la typographie. Le bloc est centré verticalement via un
       padding-top calculé (DomPDF gère mal vertical-align sur table
       100% de page).
       2026-10-01 — charte : liseré + logo en tête de page, filets aux
       5 couleurs, nom de commune en Poppins. padding-top ajusté (marge
       haute de page + logo) pour garder le bloc au même endroit.
       ──────────────────────────────────────────────────────────── */
    .cover-page {
        page-break-after: always;
        text-align: center;
    }
    .cover-logo { padding-top: 12px; }
    .cover-logo img { height: 34px; width: auto; }
    .cover-body { padding-top: 62mm; }
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
        margin: 0 auto 28px;
    }
    .cover-rule.bottom {
        margin: 32px auto 0;
    }
    .cover-kicker {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 10px;
        font-weight: 600;
        color: {{ $charte['texte_doux'] }};
        letter-spacing: 7px;
        text-transform: uppercase;
        margin-bottom: 20px;
    }
    .cover-name {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 54px;
        font-weight: 800;
        color: {{ $charte['noir'] }};
        text-transform: uppercase;
        letter-spacing: 5px;
        line-height: 1.1;
    }
    /* Période d'affichage — sous le nom de la commune, dans un encart
       sobre. Reste optionnel : si pas de dates fournies par le
       contrôleur, l'encart n'est pas affiché. */
    .cover-period {
        margin-top: 34px;
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
</style>
</head>
<body>

@php
    use Carbon\Carbon;
    $totalCount = count($panels);

    // PDF proposition : on n'affiche le badge QUE si le panneau est réellement
    // occupé (confirme/occupe), avec la date de libération. Pour tout autre
    // statut (libre, option, maintenance…), renvoie null → pas de ligne statut.
    // 2026-09-22 : formulation POSITIVE de la disponibilité.
    // Avant : « Occupé jusqu'au 15/09 » — et surtout masqué quand le MP
    // générait une proposition sans prix, ce qui laissait croire que le
    // panneau était libre sur toute la période.
    // Maintenant : « Disponible à partir du 16/09 », toujours affiché.
    // C'est une info logistique essentielle, pas une donnée sensible :
    // seul le TARIF reste conditionné à $showPricing.
    // 2026-09-24 : la fiche répond à la PÉRIODE demandée, pas à l'instant
    // présent. Avant, un panneau libre en novembre mais occupé aujourd'hui
    // affichait « ACTUELLEMENT OCCUPÉ » sur une recherche de novembre —
    // le client croyait le panneau pris.
    $periodeLabel = ($startDate ?? null) && ($endDate ?? null)
        ? 'Disponible du ' . \Carbon\Carbon::parse($startDate)->format('d/m/Y')
          . ' au ' . \Carbon\Carbon::parse($endDate)->format('d/m/Y')
        : 'Disponible';

    $statusFor = function (array $p) use ($periodeLabel) {
        $s = $p['display_status'] ?? null;

        if ($s === 'maintenance') {
            return ['label' => 'En maintenance', 'class' => 'badge-occupe'];
        }

        // Libre sur la période demandée : on le dit, et on dit sur quoi.
        if (in_array($s, ['libre', 'disponible'], true)) {
            return ['label' => $periodeLabel, 'class' => 'badge-libre'];
        }

        if (!in_array($s, ['occupe', 'occupé', 'confirme', 'option', 'option_periode'], true)) {
            return null;
        }

        if (in_array($s, ['option', 'option_periode'], true)) {
            return ['label' => 'En option sur la période', 'class' => 'badge-option'];
        }

        $release = $p['release_date'] ?? null;
        if ($release) {
            // La date stockée est le DERNIER jour d'occupation → le
            // panneau est réellement disponible le lendemain.
            $freeFrom = \Carbon\Carbon::parse($release)->addDay();
            return [
                'label' => 'Disponible à partir du ' . $freeFrom->format('d/m/Y'),
                'class' => 'badge-occupe',
            ];
        }

        return ['label' => 'Occupé sur la période demandée', 'class' => 'badge-occupe'];
    };

    // Logo CIBLE CI : passé par PdfAssets::getLogoPdf() — fallback inline.
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

    // Règle : par défaut, pas de prix ni de statut
    $showPricing = $showPricing ?? !($hideStatus ?? true);

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
    // Le client doit recevoir une page d'intro avant chaque bloc de
    // panneaux concernant une même commune (Cocody, Plateau, …).
    // On résout la commune en string (les panneaux internes/externes
    // ont déjà une string via enrichPanel ; on garde un fallback).
    $resolveCommune = fn (array $p) => trim((string) ($p['commune'] ?? '')) ?: '—';

    $grouped     = collect($panels)
        ->sortBy(fn ($p) => $resolveCommune($p), SORT_NATURAL | SORT_FLAG_CASE)
        ->groupBy(fn ($p) => $resolveCommune($p));

    $totalGroups = $grouped->count();

    // Pied commun (charte-footer) : on y reporte le texte du pied
    // historique, avec la référence et le client s'ils sont fournis.
    $piedHistorique = 'CIBLE CI · Régie Publicitaire · Abidjan, Côte d\'Ivoire · Document confidentiel'
        . (isset($reservation_ref) ? ' · Réf. ' . $reservation_ref : '')
        . (isset($client_name) ? ' · Client : ' . $client_name : '');
@endphp

{{-- Pied fixe commun : répété sur chaque page. « Page N » désactivé :
     l'en-tête de chaque fiche porte déjà « Page x / y » (numéro de fiche,
     hors pages de garde) — deux numérotations différentes prêteraient à
     confusion. --}}
@include('pdf.partials.charte-footer', ['footerHint' => $piedHistorique, 'footerPage' => false])

@php $globalIndex = 0; @endphp
@foreach ($grouped as $communeName => $groupPanels)
    @php
        $groupSize = count($groupPanels);
    @endphp

    {{-- ═══════════════════ PAGE DE GARDE COMMUNE (épuré) ═══════════════════ --}}
    <div class="cover-page">
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

    {{-- ═══════════════════ FICHES PANNEAUX DE LA COMMUNE ═══════════════════ --}}
    @foreach ($groupPanels as $intraIndex => $p)
    @php
        $globalIndex++;
        $pageNum  = $globalIndex;
        $intraNum = $intraIndex + 1;
        $status   = $statusFor($p);
        $traffic  = (int) ($p['daily_traffic'] ?? 0);
        $zoneDesc = $p['zone_description'] ?? '';

        // photo_src est déjà un data-URI base64 (généré par PdfExportService).
        // Si absent, on tente une lecture directe du fichier local — DomPDF
        // n'a pas accès au réseau (isRemoteEnabled=false), donc on ignore
        // sciemment photo_url qui produirait une image cassée / fond noir.
        $imgSrc = $p['photo_src'] ?? null;
        if (!$imgSrc && !empty($p['photo_path']) && file_exists($p['photo_path'])) {
            $ext  = strtolower(pathinfo($p['photo_path'], PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
                default => 'image/jpeg',
            };
            $imgSrc = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p['photo_path']));
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

        // 2026-10-01 — mise en page uniquement : 1 panneau = 1 page.
        // Quand une description est saisie, on réduit la hauteur de la
        // photo (ou du cadre vide) d'autant que le texte l'exige, pour
        // que la fiche tienne sur sa page. Estimation : ~105 caractères
        // par ligne, ~20 px par ligne (Nunito) + 50 px de cadre ; ~145 px
        // sont libres sous les caractéristiques. Plancher 200 px : au-delà,
        // DomPDF poursuit la description sur la page suivante (comme avant).
        $descLignes = 0;
        if ($zoneDesc) {
            foreach (preg_split('/\R/', (string) $zoneDesc) as $paragraphe) {
                $descLignes += max(1, (int) ceil(mb_strlen($paragraphe) / 105));
            }
        }
        $photoReduc   = $descLignes ? max(0, 50 + 20 * $descLignes - 145) : 0;
        $photoMaxH    = max(200, 430 - $photoReduc);
        $videPadding  = max(60, 170 - (int) ceil($photoReduc / 2));
    @endphp

    <div class="page">

        {{-- ─── EN-TÊTE (charte : liseré + logo clair + titre + méta) ─── --}}
        @include('pdf.partials.charte-lisere')
        <table class="fiche-head">
            <tr>
                @if(!empty($logoEntete))
                    <td class="logo-cell"><img src="{{ $logoEntete }}" alt="CIBLE CI"></td>
                @endif
                <td>
                    <h1>{{ $communeName }} <span class="accent">· Panneau {{ $intraNum }}/{{ $groupSize }}</span></h1>
                </td>
                <td class="meta-cell">
                    Généré le {{ $generated ?? now()->format('d/m/Y à H:i') }}<br>
                    Page {{ $pageNum }} / {{ $totalCount }}
                </td>
            </tr>
        </table>
        <div class="fiche-rule"></div>

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
                <img src="{{ $imgSrc }}" alt="{{ $p['reference'] ?? '' }}"@if($photoReduc) style="max-height:{{ $photoMaxH }}px"@endif>
            @else
                <span class="photo-empty"@if($photoReduc) style="padding:{{ $videPadding }}px 0"@endif>— Aucune photo disponible —</span>
            @endif
        </div>

        {{-- ─── CARACTÉRISTIQUES (2 colonnes) ─── --}}
        <h2 class="section">Caractéristiques techniques</h2>

        <table class="two-cols">
            <tr>
                <td>
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
                                <br><span class="val-sub">Surface : {{ $surface }} m²</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Éclairage</td>
                        <td class="val">
                            @if($isLit)
                                <span class="lit-yes">Éclairé (LED)</span>
                            @else
                                <span class="lit-no">Non éclairé</span>
                            @endif
                        </td>
                    </tr>
                </table>
                </td>

                <td>
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
                                <span class="val-gps">
                                    {{ number_format((float) $latitude, 6, '.', '') }}, {{ number_format((float) $longitude, 6, '.', '') }}
                                </span>
                                @if(!empty($p['gps_link']))
                                    <br><a href="{{ $p['gps_link'] }}" class="val-link">Voir sur Google Maps</a>
                                @endif
                            @else
                                <span class="val-vide">Non renseignées</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Trafic journalier (estimatif)</td>
                        <td class="val">
                            @if($traffic > 0)
                                <strong>{{ number_format($traffic, 0, ',', ' ') }}</strong>
                                <span class="val-sub">contacts / jour</span>
                            @else
                                <span class="val-vide">—</span>
                            @endif
                        </td>
                    </tr>

                    {{-- ─── Tarif UNIQUEMENT si showPricing ─── --}}
                    @if($showPricing)
                        <tr>
                            <td class="lbl">Tarif mensuel HT</td>
                            <td class="val">
                                @if($rate > 0)
                                    <strong class="tarif">{{ number_format($rate, 0, ',', ' ') }} FCFA</strong>
                                @elseif($rate === 0 || $rate === 0.0)
                                    <strong class="tarif-zero">0 FCFA</strong>
                                @else
                                    <span class="val-vide">Sur devis</span>
                                @endif
                            </td>
                        </tr>
                    @endif

                    {{-- ─── Disponibilité : TOUJOURS affichée ───
                         2026-09-22 : ce bloc était imbriqué dans
                         @if($showPricing). Conséquence : sur une
                         proposition sans prix (cas par défaut du MP),
                         un panneau occupé jusqu'au 15 apparaissait
                         comme libre sur toute la période. Le tarif
                         est une donnée commerciale sensible, la date
                         de libération est une info logistique que le
                         client DOIT voir. --}}
                    @if($status)
                        <tr>
                            <td class="lbl">Disponibilité</td>
                            <td class="val"><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                        </tr>
                    @endif
                </table>
                </td>
            </tr>
        </table>

        @if($zoneDesc)
            {{--
              2026-07-17 (bug patronne) : Str::limit(320) tronquait la
              description avec « ... » — la patronne saisit des textes
              plus longs et voulait TOUT voir. Retrait du limit + nl2br
              pour respecter les sauts de ligne saisis + word-wrap pour
              éviter qu'un mot très long dépasse. La card autorise
              page-break-inside:auto pour laisser DomPDF couper
              proprement si le contenu déborde de la page.
            --}}
            <div class="extra" style="word-wrap:break-word;">
                <span class="extra-title">Description / Environnement</span><br>
                {!! nl2br(e($zoneDesc)) !!}
            </div>
        @endif

    </div>
    @endforeach {{-- fin boucle panneaux du groupe --}}
@endforeach {{-- fin boucle communes --}}

</body>
</html>

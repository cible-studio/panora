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
    /* DomPDF gère mal object-fit ET max-height ensemble. On force la photo
       à respecter l'espace via width fixe + auto-height : DomPDF maintient
       alors le ratio et pas de bandeau noir résiduel.  */
    .photo-wrap {
        text-align: center;
        margin-bottom: 14px;
    }
    /* DomPDF préserve le ratio uniquement avec max-width + max-height
       (et SANS width/height/object-fit forcés, qui causent l'écrasement
       horizontal). L'image grandit autant que possible en respectant
       les deux bornes ET son ratio natif. */
    .photo-wrap img {
        max-width: 100%;
        max-height: 460px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
    }
    .photo-empty {
        display: block;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 180px 0;
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
    /* Real <table> outer wrapper — DomPDF gère mal display:table-cell sur
       div, et finit par couper le rendu après la photo si une cellule
       contient une table imbriquée ratée. */
    table.two-cols { width: 100%; border-collapse: collapse; }
    table.two-cols > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 5px; }
    table.two-cols > tbody > tr > td:first-child { padding-left: 0; padding-right: 10px; }
    table.two-cols > tbody > tr > td:last-child { padding-left: 10px; padding-right: 0; }

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

    /* ────────────────────────────────────────────────────────────────
       PAGE DE GARDE PAR COMMUNE — design simple & moderne
       Aucun cadre, aucune décoration lourde : juste de la respiration
       et de la typographie. Le bloc est centré verticalement via un
       padding-top calculé (DomPDF gère mal vertical-align sur table
       100% de page).  A4 portrait = 297mm ; centre visuel ≈ 130mm.
       Le bloc fait ~40mm de hauteur → padding-top 110mm le pose
       presque pile au milieu optique.
       ──────────────────────────────────────────────────────────── */
    .cover-page {
        page-break-after: always;
        padding-top: 90mm;
        text-align: center;
        background: #ffffff;
    }
    .cover-doc-type {
        font-size: 11px;
        font-weight: 800;
        color: #c2570d;
        letter-spacing: 10px;
        text-transform: uppercase;
        margin-bottom: 22px;
    }
    .cover-rule {
        width: 60mm;
        height: 1px;
        background: #d4af37;
        margin: 0 auto 28px;
        font-size: 0;
        line-height: 0;
    }
    .cover-rule.bottom {
        margin: 32px auto 0;
    }
    .cover-kicker {
        font-size: 10px;
        font-weight: 700;
        color: #d4af37;
        letter-spacing: 7px;
        text-transform: uppercase;
        margin-bottom: 22px;
    }
    .cover-name {
        font-size: 60px;
        font-weight: 900;
        color: #0d1117;
        text-transform: uppercase;
        letter-spacing: 7px;
        line-height: 1;
    }
    /* Période d'affichage — sous le nom de la commune, dans un encart
       sobre fond ivoire / accent doré. Reste optionnel : si pas de
       dates fournies par le contrôleur, l'encart n'est pas affiché. */
    .cover-period {
        margin-top: 34px;
        display: inline-block;
        padding: 9px 22px;
        background: #fff7ed;
        border: 1px solid #fde6c9;
        border-radius: 6px;
        font-size: 12px;
        color: #4b5563;
        letter-spacing: .5px;
    }
    .cover-period-label {
        font-size: 9px;
        font-weight: 800;
        color: #c2570d;
        letter-spacing: 2.5px;
        text-transform: uppercase;
        margin-right: 8px;
    }
    .cover-period strong {
        color: #0d1117;
        font-weight: 700;
        letter-spacing: .3px;
    }
    .cover-period-arrow {
        color: #d4af37;
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
    $statusFor = function (array $p) {
        $s = $p['display_status'] ?? null;
        if (!in_array($s, ['occupe', 'occupé', 'confirme'], true)) {
            return null;
        }
        $release = $p['release_date'] ?? null;
        $label = $release
            ? 'Occupé jusqu\'au ' . \Carbon\Carbon::parse($release)->format('d/m/Y')
            : 'Occupé';
        return ['label' => $label, 'class' => 'badge-occupe'];
    };

    // Logo CIBLE CI : passé par PdfAssets::getLogoPdf() — fallback inline.
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
@endphp

@php $globalIndex = 0; @endphp
@foreach ($grouped as $communeName => $groupPanels)
    @php
        $groupSize = count($groupPanels);
    @endphp

    {{-- ═══════════════════ PAGE DE GARDE COMMUNE (épuré) ═══════════════════ --}}
    <div class="cover-page">
        <div class="cover-doc-type">Disponibilités</div>
        <div class="cover-rule"></div>
        <div class="cover-kicker">Commune</div>
        <div class="cover-name">{{ $communeName }}</div>
        <div class="cover-rule bottom"></div>
        @if($startFr && $endFr)
            <div class="cover-period">
                <span class="cover-period-label">Période</span>
                <strong>{{ $startFr }}</strong>
                <span class="cover-period-arrow">→</span>
                <strong>{{ $endFr }}</strong>
            </div>
        @endif
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
    @endphp

    <div class="page">

        {{-- ─── HEADER UNIFORME ─── --}}
        <div class="pdf-header">
            <div class="logo-cell">
                <img src="{{ $logoSrc }}" alt="CIBLE CI">
            </div>
            <div class="title-cell">
                <h1>{{ $communeName }} <span class="accent">· Panneau {{ $intraNum }}/{{ $groupSize }}</span></h1>
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
                            <td class="lbl">Trafic journalier (estimatif)</td>
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
                                    @elseif($rate === 0 || $rate === 0.0)
                                        <strong style="color:#16a34a;">0 FCFA</strong>
                                    @else
                                        <span style="color:#9ca3af">Sur devis</span>
                                    @endif
                                </td>
                            </tr>
                            @if($status)
                            <tr>
                                <td class="lbl">Statut actuel</td>
                                <td class="val"><span class="badge {{ $status['class'] }}">{{ $status['label'] }}</span></td>
                            </tr>
                            @endif
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
                    <strong style="color:#e8a020;font-size:9px;text-transform:uppercase;letter-spacing:0.8px;">Description / Environnement</strong><br>
                    {!! nl2br(e($zoneDesc)) !!}
                </div>
            @endif

        </div>

        <div class="pdf-footer">
            CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
            @isset($reservation_ref) · Réf. {{ $reservation_ref }}@endisset
            @isset($client_name) · Client : {{ $client_name }}@endisset
        </div>
    </div>
    @endforeach {{-- fin boucle panneaux du groupe --}}
@endforeach {{-- fin boucle communes --}}

</body>
</html>

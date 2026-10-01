<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Fiche panneau — {{ $panel['reference'] }}</title>
    @include('pdf.partials.charte-styles')
    {{-- 2026-10-01 — charte graphique : en-tête clair (liseré + logo CIBLE +
         titre + méta), pied commun fixe (texte historique reporté en 2e
         ligne), palette $charte, polices Poppins / Nunito. Marges @page avec
         !important (DomPDF les annule sinon à cause de « * { margin:0 } ») :
         la marge basse réserve la place du pied, qui ne peut plus masquer
         la fin d'une description longue. Aucun poids 900 (cf. rapport). --}}
    <style>
        @page { margin: 10mm 14mm 18mm 14mm !important; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            color: {{ $charte['noir'] }};
            font-size: 11px;
            line-height: 1.25;
        }

        /* Padding-bottom au container pour laisser la place au footer
           position:fixed. 2026-07-17 : bug rapporté par la patronne où
           la fin de la description était masquée par le footer sur
           page 2 (contenu long débordant).
           2026-10-01 : la place du pied est désormais réservée par la
           marge basse de @page (pied commun charte-footer). */
        .container { padding: 0; }

        /* ── EN-TÊTE (logo gauche / titre / méta droite) ── */
        .fiche-head { width: 100%; border-collapse: collapse; margin: 0; }
        .fiche-head td { vertical-align: middle; padding: 9px 0 8px; border: none; }
        .fiche-head .logo-cell { width: 1%; padding-right: 14px; white-space: nowrap; }
        .fiche-head .logo-cell img { height: 34px; width: auto; display: block; }
        .fiche-head .meta-cell { width: 1%; padding-left: 14px; text-align: right; white-space: nowrap; font-size: 8.5px; line-height: 1.45; color: {{ $charte['texte_doux'] }}; }
        .fiche-head h1 {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .8px;
            color: {{ $charte['noir'] }};
            text-transform: uppercase;
            line-height: 1.15;
        }
        .fiche-head .accent { color: {{ $charte['rouge'] }}; }
        .fiche-rule { height: 2px; background: {{ $charte['noir'] }}; margin: 0 0 14px; font-size: 0; line-height: 0; }

        /* ── REF EN GRAND ── */
        .ref-banner {
            background: {{ $charte['gris_clair'] }};
            border-left: 4px solid {{ $charte['rouge'] }};
            padding: 11px 18px;
            margin-bottom: 16px;
        }
        .ref-banner .ref-tag {
            font-family: {!! $charte['ff_mono'] !!};
            font-weight: 700;
            font-size: 20px;
            color: {{ $charte['rouge'] }};
            letter-spacing: 1.5px;
        }
        .ref-banner .ref-name {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 13.5px;
            font-weight: 600;
            color: {{ $charte['noir'] }};
            margin-top: 2px;
        }
        .ref-banner .ref-loc {
            font-size: 11px;
            color: {{ $charte['texte_doux'] }};
            margin-top: 2px;
        }

        /* ── PHOTO PRINCIPALE ── */
        .photo-wrap {
            text-align: center;
            margin-bottom: 16px;
            background: {{ $charte['gris_clair'] }};
            border: 1px solid {{ $charte['gris'] }};
            border-top: 3px solid {{ $charte['rouge'] }};
            padding: 6px;
            height: 220px;
            line-height: 0;
        }
        .photo-wrap img {
            max-width: 100%;
            max-height: 205px;
            object-fit: contain;
        }
        /* 2026-10-01 : bloc de hauteur fixe + padding (et non plus
           line-height 200px) — avec Poppins, l'ancien centrage par
           line-height faisait sortir le libellé du cadre. */
        .photo-empty {
            display: block;
            height: 206px;
            padding-top: 96px;
            line-height: 1.2;
            color: {{ $charte['texte_pale'] }};
            font-family: {!! $charte['ff_titres'] !!};
            font-weight: 600;
            font-size: 11px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ── TABLEAU DES CARACTÉRISTIQUES ── */
        h2.section {
            font-family: {!! $charte['ff_titres'] !!};
            font-size: 11px;
            font-weight: 700;
            color: {{ $charte['noir'] }};
            text-transform: uppercase;
            letter-spacing: 1px;
            border-left: 3px solid {{ $charte['rouge'] }};
            border-bottom: 1px solid {{ $charte['gris'] }};
            padding: 0 0 4px 8px;
            margin: 14px 0 8px;
        }

        table.specs {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table.specs td {
            padding: 7px 10px;
            border-bottom: 1px solid {{ $charte['gris'] }};
            vertical-align: top;
        }
        table.specs td.lbl {
            color: {{ $charte['texte_doux'] }};
            font-weight: 700;
            text-transform: uppercase;
            font-size: 8.5px;
            letter-spacing: .7px;
            width: 40%;
            background: {{ $charte['gris_clair'] }};
        }
        table.specs td.val {
            color: {{ $charte['noir'] }};
        }
        table.specs a { color: {{ $charte['bleu'] }}; text-decoration: none; }
        table.specs .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        .badge-libre       { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
        .badge-occupe      { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }
        .badge-option      { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
        .badge-confirme    { background: {{ $charte['bleu'] }};  color: {{ $charte['blanc'] }}; }
        .badge-maintenance { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }
        .badge-default     { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }

        .val-sub  { color: {{ $charte['texte_doux'] }}; font-size: 10px; }
        .val-vide { color: {{ $charte['texte_pale'] }}; }
        .val-gps  { font-family: {!! $charte['ff_mono'] !!}; font-size: 10px; }
        .lit-yes  { color: {{ $charte['vert'] }}; font-weight: 700; }
        .tarif      { color: {{ $charte['rouge'] }}; }
        .tarif-zero { color: {{ $charte['vert'] }}; }

        .description {
            font-size: 11px;
            color: {{ $charte['texte_doux'] }};
            line-height: 1.35;
            border: 1px solid {{ $charte['gris'] }};
            border-left: 3px solid {{ $charte['rouge'] }};
            padding: 10px 12px;
            background: {{ $charte['gris_clair'] }};
            word-wrap: break-word;
            page-break-inside: auto;
        }

        /* ── 2 COLONNES POUR ÉCONOMISER L'ESPACE ── */
        .two-cols { display: table; width: 100%; }
        .two-cols .col { display: table-cell; width: 50%; vertical-align: top; padding-right: 12px; }
        .two-cols .col:last-child { padding-right: 0; padding-left: 12px; }
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

        // 2026-10-01 — charte : en-tête clair → logo pour fond clair
        // ($logoCibleLight, view composer) ; $logoSrc (logob.png, texte
        // blanc) reste le repli historique.
        $logoEntete = $logoCibleLight ?? ($logoSrc ?? null);
    @endphp

    {{-- ─── PIED FIXE COMMUN (texte historique en 2e ligne) ─── --}}
    @include('pdf.partials.charte-footer', [
        'footerHint' => 'CIBLE CI · Régie Publicitaire · Abidjan, Côte d\'Ivoire · Document confidentiel',
    ])

    {{-- ─── EN-TÊTE (charte : liseré + logo clair + titre + méta) ─── --}}
    @include('pdf.partials.charte-lisere')
    <table class="fiche-head">
        <tr>
            @if(!empty($logoEntete))
                <td class="logo-cell"><img src="{{ $logoEntete }}" alt="CIBLE CI"></td>
            @endif
            <td>
                <h1>Fiche <span class="accent">Panneau</span></h1>
            </td>
            <td class="meta-cell">
                Généré le {{ $generated }}<br>
                Réf. {{ $panel['reference'] }}
            </td>
        </tr>
    </table>
    <div class="fiche-rule"></div>

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
                                <br><span class="val-sub">Surface : {{ $panel['surface_m2'] }} m²</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Éclairage</td>
                        <td class="val">
                            @if($panel['is_lit'])
                                <span class="lit-yes">Éclairé (LED)</span>
                            @else
                                <span class="val-vide">Non éclairé</span>
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
                                <span class="val-gps">
                                    {{ number_format($panel['latitude'], 6, '.', '') }},
                                    {{ number_format($panel['longitude'], 6, '.', '') }}
                                </span>
                                @if(!empty($panel['gps_link']))
                                    <br><a href="{{ $panel['gps_link'] }}">Voir sur Google Maps</a>
                                @endif
                            @else
                                <span class="val-vide">Non renseignées</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Trafic journalier</td>
                        <td class="val">
                            @if($panel['daily_traffic'] > 0)
                                <strong>{{ number_format($panel['daily_traffic'], 0, ',', ' ') }}</strong>
                                <span class="val-sub">contacts / jour</span>
                            @else
                                <span class="val-vide">—</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="lbl">Tarif mensuel</td>
                        <td class="val">
                            @if(($panel['monthly_rate'] ?? null) > 0)
                                <strong class="tarif">{{ number_format($panel['monthly_rate'], 0, ',', ' ') }} FCFA</strong>
                            @elseif(($panel['monthly_rate'] ?? null) === 0 || ($panel['monthly_rate'] ?? null) === 0.0)
                                <strong class="tarif-zero">0 FCFA</strong>
                            @else
                                <span class="val-vide">Sur devis</span>
                            @endif
                        </td>
                    </tr>
                    <tr><td class="lbl">Statut actuel</td><td class="val"><span class="badge {{ $st[1] }}">{{ $st[0] }}</span></td></tr>
                </table>
            </div>
        </div>

        @if(!empty($panel['zone_description']))
            <h2 class="section">Description / Environnement</h2>
            {{--
              2026-07-17 (bug patronne) : le texte long était tronqué visuellement
              en PDF pour deux raisons :
                1. le footer position:fixed masquait la fin sur page 2 → fixé
                   par padding-bottom:60px sur .container (voir CSS ci-dessus)
                   — 2026-10-01 : désormais par la marge basse de @page.
                2. les sauts de ligne du textarea n'étaient pas rendus → fixé
                   par nl2br() qui convertit \n en <br>
              word-wrap:break-word garantit qu'un mot très long ne dépasse pas
              la largeur de la boîte. page-break-inside:auto permet à DomPDF de
              couper le bloc entre 2 pages si besoin (plutôt qu'un débordement).
            --}}
            <div class="description">
                {!! nl2br(e($panel['zone_description'])) !!}
            </div>
        @endif

    </div>
</body>
</html>

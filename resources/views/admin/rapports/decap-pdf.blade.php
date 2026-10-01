{{-- 2026-06-22 — Feuille de décapage PDF pour les techs terrain.
     Refonte v2 : groupage par COMMUNE (tournée géographique), logo CIBLE,
     police 11px+ pour lisibilité terrain, footer fixe avec pagination,
     pas d'emoji Unicode (DomPDF + DejaVu ne supporte pas tout).
     2026-10-01 — charte graphique : palette $charte, polices de la charte,
     en-tête charte-header, pied charte-footer.

     Variables (injectées par AppServiceProvider pour les vues admin.*.pdf) :
       $logoCibleLight  : URI data: du logo CIBLE clair (header clair)
       $operatorName    : "CIBLE CI" par défaut
       $charte          : palette / polices (App\Support\PdfCharte)

     Variables (controller) :
       $byCommune   : Collection groupée [{name, city, panels[], overdue, total_panels}]
       $totals      : ['campaigns','panels','communes','overdue','generated_by','generated_at']
       $overdueOnly : true si filtre ?overdue=1
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Feuille de décapage — {{ $totals['generated_at']->format('d/m/Y') }}</title>
@include('pdf.partials.charte-styles')
<style>
    /* Marges : 12mm haut/bas, 10mm latéraux. 18mm bas réservés au footer. */
    @page { size: A4 portrait; margin: 12mm 10mm 18mm 10mm !important; }
    body { font-size: 11px; color: {{ $charte['noir'] }}; line-height: 1.3; }

    /* ── Bandeau mode d'emploi ──────────────────────────────────── */
    .intro {
        background: {{ $charte['jaune_clair'] }}; border-left: 4px solid {{ $charte['jaune'] }};
        padding: 10px 14px; margin-bottom: 14px;
        font-size: 11px; color: {{ $charte['noir'] }}; line-height: 1.35;
    }
    .intro strong { color: {{ $charte['noir'] }}; }

    /* ── Cards résumé ───────────────────────────────────────────── */
    .summary { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 6px 0; }
    .summary .cell {
        display: table-cell; padding: 10px 8px; width: 25%;
        background: {{ $charte['gris_clair'] }}; border-top: 3px solid {{ $charte['noir'] }};
        text-align: center;
    }
    .summary .cell .num { font-family: {!! $charte['ff_titres'] !!}; font-size: 22px; font-weight: 800; color: {{ $charte['noir'] }}; line-height: 1; }
    .summary .cell .lbl { font-size: 9.5px; color: {{ $charte['texte_doux'] }}; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; font-weight: 700; }
    .summary .cell.overdue { background: {{ $charte['rouge_clair'] }}; border-top-color: {{ $charte['rouge'] }}; }
    .summary .cell.overdue .num { color: {{ $charte['rouge'] }}; }

    /* ── Bloc commune (page-break-inside évité) ─────────────────── */
    .commune-block {
        margin-bottom: 14px; page-break-inside: avoid;
        border: 1px solid {{ $charte['gris'] }}; border-left: 3px solid {{ $charte['rouge'] }};
        padding: 12px 14px;
        background: {{ $charte['blanc'] }};
    }
    .commune-head {
        display: table; width: 100%; margin-bottom: 8px;
        border-bottom: 1.5px solid {{ $charte['gris'] }}; padding-bottom: 6px;
    }
    .commune-head .left { display: table-cell; vertical-align: middle; }
    .commune-head .right { display: table-cell; vertical-align: middle; text-align: right; font-size: 10px; }
    .commune-name { font-family: {!! $charte['ff_titres'] !!}; font-size: 16px; font-weight: 800; color: {{ $charte['noir'] }}; letter-spacing: -0.2px; }
    .commune-city { font-size: 10.5px; color: {{ $charte['texte_doux'] }}; font-style: italic; margin-top: 2px; }
    .commune-count {
        display: inline-block; padding: 4px 10px;
        background: {{ $charte['bleu'] }}; color: {{ $charte['blanc'] }};
        border-radius: 3px; font-size: 11px; font-weight: 700;
    }
    .commune-count.overdue { background: {{ $charte['rouge'] }}; }

    /* ── Tableau panneaux ──────────────────────────────────────── */
    table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.grid th {
        background: {{ $charte['noir'] }}; padding: 7px 6px; text-align: left;
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 9.5px; font-weight: 700; color: {{ $charte['blanc'] }};
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    table.grid th.c, table.grid td.c { text-align: center; }
    table.grid td { padding: 8px 6px; font-size: 10.5px; border-bottom: 1px solid {{ $charte['gris'] }}; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    table.grid tr.overdue-row td { background: {{ $charte['rouge_clair'] }}; }
    table.grid tr.overdue-row:nth-child(even) td { background: {{ $charte['rouge_clair'] }}; }

    .ref {
        font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }};
        font-weight: 800; font-size: 11.5px;
    }
    .addr-line { font-size: 10.5px; color: {{ $charte['noir'] }}; font-weight: 600; }
    .addr-detail { color: {{ $charte['texte_doux'] }}; font-size: 9.5px; line-height: 1.15; margin-top: 1px; }
    .quartier { color: {{ $charte['texte_doux'] }}; font-size: 9px; font-style: italic; margin-top: 1px; }
    .gps {
        font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['vert'] }};
        font-size: 9.5px; font-weight: 600; line-height: 1.15;
    }
    .gps-empty { color: {{ $charte['texte_pale'] }}; font-size: 10px; }
    .campaign-chip {
        display: inline-block; padding: 2px 8px;
        background: {{ $charte['gris'] }}; color: {{ $charte['noir'] }};
        border-radius: 3px; font-size: 9px; font-weight: 700;
    }
    .campaign-chip.overdue {
        background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }};
    }
    .late-badge {
        display: block; font-size: 9px; color: {{ $charte['rouge'] }};
        font-weight: 800; margin-top: 2px;
    }
    .check {
        display: inline-block; width: 16px; height: 16px;
        border: 1.5px solid {{ $charte['noir'] }}; border-radius: 3px;
        vertical-align: middle;
    }

    /* ── Empty state ───────────────────────────────────────────── */
    .empty {
        padding: 40px 20px; text-align: center;
        color: {{ $charte['texte_doux'] }}; font-size: 13px; font-style: italic;
        background: {{ $charte['gris_clair'] }};
    }
    .empty .big { font-size: 28px; margin-bottom: 8px; color: {{ $charte['vert'] }}; }

    /* ── Zone signature ────────────────────────────────────────── */
    .sign-zone {
        margin-top: 16px; padding: 12px 14px;
        border: 1.5px dashed {{ $charte['texte_doux'] }};
        background: {{ $charte['gris_clair'] }}; font-size: 11px; color: {{ $charte['texte_doux'] }};
        page-break-inside: avoid;
    }
    .sign-zone .row { margin-bottom: 8px; }
    .sign-zone .label { font-weight: 800; color: {{ $charte['noir'] }}; }
    .sign-zone .line {
        display: inline-block; border-bottom: 1.2px solid {{ $charte['texte_doux'] }};
        min-width: 180px; margin-left: 6px; height: 14px;
    }
    .sign-zone .line.wide { min-width: 360px; }
    .sign-zone .line.short { min-width: 110px; }
</style>
</head>
<body>

{{-- ──────────────────────────── FOOTER FIXE ────────────────────────────
     2026-10-01 — charte graphique : pied commun charte-footer (texte
     d'origine repris dans footerHint ; « Page N » fourni par le partiel). --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => ($operatorName ?? 'CIBLE CI') . ' — Feuille de décapage · Panora · généré le '
        . $totals['generated_at']->format('d/m/Y \à H\hi'),
])

{{-- ──────────────────────────── HEADER ────────────────────────────
     2026-10-01 — charte graphique : en-tête commun (liseré + logo clair). --}}
@include('pdf.partials.charte-header', [
    'docKicker'   => 'Terrain',
    'docTitle'    => 'FEUILLE DE DÉCAPAGE',
    'docSubtitle' => $overdueOnly
        ? 'Panneaux en retard (' . $totals['overdue'] . ' campagne' . ($totals['overdue'] > 1 ? 's' : '') . ' > 7 jours)'
        : 'Tous les panneaux à décaper · ' . $totals['campaigns'] . ' campagne' . ($totals['campaigns'] > 1 ? 's' : '')
            . ' terminée' . ($totals['campaigns'] > 1 ? 's' : ''),
    'docMeta'     => [
        'Édité le ' . $totals['generated_at']->format('d/m/Y \à H\hi'),
        'Par ' . $totals['generated_by'],
        $operatorName ?? 'CIBLE CI',
    ],
])

{{-- ──────────────────────────── MODE D'EMPLOI ──────────────────────────── --}}
<div class="intro">
    <strong>Mode d'emploi terrain :</strong>
    Va sur chaque panneau listé ci-dessous (groupés par <strong>commune</strong> pour
    optimiser ta tournée), retire l'affichage, et coche la case <strong>« Fait »</strong>.
    Note l'heure et tes initiales dans la dernière colonne. Rends la feuille signée
    au superviseur en fin de tournée. En cas de problème (panneau cassé, accès bloqué,
    etc.), écris le motif dans la marge.
</div>

{{-- ──────────────────────────── RÉSUMÉ CARDS ──────────────────────────── --}}
<div class="summary">
    <div class="cell">
        <div class="num">{{ $totals['communes'] }}</div>
        <div class="lbl">Communes</div>
    </div>
    <div class="cell">
        <div class="num">{{ $totals['campaigns'] }}</div>
        <div class="lbl">Campagnes</div>
    </div>
    <div class="cell">
        <div class="num">{{ $totals['panels'] }}</div>
        <div class="lbl">Panneaux à décaper</div>
    </div>
    <div class="cell overdue">
        <div class="num">{{ $totals['overdue'] }}</div>
        <div class="lbl">Campagnes en retard</div>
    </div>
</div>

{{-- ──────────────────────────── LISTE PAR COMMUNE ──────────────────────────── --}}
@if($byCommune->isEmpty())
    <div class="empty">
        <div class="big">✓</div>
        Aucun panneau à décaper.<br>
        Toutes les campagnes terminées sont à jour.
    </div>
@else
    @foreach($byCommune as $com)
        <div class="commune-block">
            <div class="commune-head">
                <div class="left">
                    <div class="commune-name">{{ mb_strtoupper($com['name']) }}</div>
                    @if(!empty($com['city']))
                        <div class="commune-city">{{ $com['city'] }}</div>
                    @endif
                </div>
                <div class="right">
                    <span class="commune-count {{ $com['overdue'] > 0 ? 'overdue' : '' }}">
                        {{ $com['total_panels'] }} panneau{{ $com['total_panels'] > 1 ? 'x' : '' }}
                        @if($com['overdue'] > 0)
                            &nbsp;·&nbsp; {{ $com['overdue'] }} en retard
                        @endif
                    </span>
                </div>
            </div>

            <table class="grid">
                <thead>
                    <tr>
                        <th style="width:13%">Référence</th>
                        <th style="width:36%">Nom &amp; Adresse</th>
                        <th style="width:18%">Campagne</th>
                        <th style="width:14%">GPS</th>
                        <th class="c" style="width:7%">Fait</th>
                        <th style="width:12%">Initiales / heure</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($com['panels'] as $p)
                        <tr class="{{ $p['is_overdue'] ? 'overdue-row' : '' }}">
                            <td class="ref">{{ $p['reference'] }}</td>
                            <td>
                                <div class="addr-line">{{ $p['name'] ?: '—' }}</div>
                                @if($p['adresse'])
                                    <div class="addr-detail">{{ $p['adresse'] }}</div>
                                @endif
                                @if($p['quartier'])
                                    <div class="quartier">Quartier : {{ $p['quartier'] }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="campaign-chip {{ $p['is_overdue'] ? 'overdue' : '' }}">
                                    {{ \Illuminate\Support\Str::limit($p['campaign_name'], 20) }}
                                </span>
                                @if($p['is_overdue'])
                                    <span class="late-badge">+{{ $p['days_overdue'] }}j retard</span>
                                @else
                                    <div class="quartier">Fin&nbsp;: {{ $p['campaign_end']->format('d/m/Y') }}</div>
                                @endif
                            </td>
                            <td>
                                @if($p['latitude'] && $p['longitude'])
                                    <span class="gps">
                                        {{ number_format($p['latitude'], 5) }}<br>
                                        {{ number_format($p['longitude'], 5) }}
                                    </span>
                                @else
                                    <span class="gps-empty">—</span>
                                @endif
                            </td>
                            <td class="c"><span class="check"></span></td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    {{-- ──────────────────────────── ZONE SIGNATURE ──────────────────────────── --}}
    <div class="sign-zone">
        <div class="row">
            <span class="label">Tournée du :</span><span class="line short"></span>
            &nbsp;&nbsp;&nbsp;
            <span class="label">Technicien :</span><span class="line"></span>
        </div>
        <div class="row">
            <span class="label">Observations terrain :</span><span class="line wide"></span>
        </div>
        <div class="row">
            <span class="label">Signature tech :</span><span class="line"></span>
            &nbsp;&nbsp;
            <span class="label">Validation superviseur :</span><span class="line"></span>
        </div>
    </div>
@endif

</body>
</html>

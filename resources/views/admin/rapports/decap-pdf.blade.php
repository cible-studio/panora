{{-- 2026-06-22 — Feuille de décappage PDF pour les techs terrain.
     Refonte v2 : groupage par COMMUNE (tournée géographique), logo CIBLE,
     police 11px+ pour lisibilité terrain, footer fixe avec pagination,
     pas d'emoji Unicode (DomPDF + DejaVu ne supporte pas tout).

     Variables (injectées par AppServiceProvider pour les vues admin.*.pdf) :
       $logoCibleLight  : URI data: du logo CIBLE clair (header foncé OK)
       $operatorName    : "CIBLE CI" par défaut

     Variables (controller) :
       $byCommune   : Collection groupée [{name, city, panels[], overdue, total_panels}]
       $totals      : ['campaigns','panels','communes','overdue','generated_by','generated_at']
       $overdueOnly : true si filtre ?overdue=1
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Feuille de décappage — {{ $totals['generated_at']->format('d/m/Y') }}</title>
<style>
    /* Marges : 12mm haut/bas, 10mm latéraux. 18mm bas réservés au footer. */
    @page { size: A4 portrait; margin: 12mm 10mm 18mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }

    /* ── Header avec logo CIBLE ─────────────────────────────────── */
    .header { display: table; width: 100%; margin-bottom: 14px; border-bottom: 2px solid #dc2626; padding-bottom: 10px; }
    .header .logo-cell { display: table-cell; vertical-align: middle; width: 90px; }
    .header .logo-cell img { height: 46px; }
    .header .title-cell { display: table-cell; vertical-align: middle; padding-left: 14px; }
    .header .meta-cell { display: table-cell; vertical-align: middle; text-align: right; font-size: 10px; color: #6b7280; width: 200px; }
    h1 { font-size: 20px; color: #dc2626; margin: 0 0 4px; letter-spacing: -0.3px; font-weight: 800; }
    .subtitle { font-size: 11.5px; color: #4b5563; font-weight: 600; }

    /* ── Bandeau mode d'emploi ──────────────────────────────────── */
    .intro {
        background: #fef3c7; border-left: 4px solid #f59e0b;
        padding: 10px 14px; margin-bottom: 14px;
        font-size: 11px; color: #92400e; line-height: 1.55;
    }
    .intro strong { color: #78350f; }

    /* ── Cards résumé ───────────────────────────────────────────── */
    .summary { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 6px 0; }
    .summary .cell {
        display: table-cell; padding: 10px 8px; width: 25%;
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
        text-align: center;
    }
    .summary .cell .num { font-size: 22px; font-weight: 800; color: #111827; line-height: 1; }
    .summary .cell .lbl { font-size: 9.5px; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; font-weight: 700; }
    .summary .cell.overdue { background: rgba(220,38,38,.06); border-color: rgba(220,38,38,.4); }
    .summary .cell.overdue .num { color: #dc2626; }

    /* ── Bloc commune (page-break-inside évité) ─────────────────── */
    .commune-block {
        margin-bottom: 14px; page-break-inside: avoid;
        border: 1px solid #d1d5db; border-radius: 8px; padding: 12px 14px;
        background: #fff;
    }
    .commune-head {
        display: table; width: 100%; margin-bottom: 8px;
        border-bottom: 1.5px solid #e5e7eb; padding-bottom: 6px;
    }
    .commune-head .left { display: table-cell; vertical-align: middle; }
    .commune-head .right { display: table-cell; vertical-align: middle; text-align: right; font-size: 10px; }
    .commune-name { font-size: 16px; font-weight: 800; color: #1d4ed8; letter-spacing: -0.2px; }
    .commune-city { font-size: 10.5px; color: #6b7280; font-style: italic; margin-top: 2px; }
    .commune-count {
        display: inline-block; padding: 4px 10px;
        background: #1d4ed8; color: #fff;
        border-radius: 12px; font-size: 11px; font-weight: 700;
    }
    .commune-count.overdue { background: #dc2626; }

    /* ── Tableau panneaux ──────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th {
        background: #1f2937; padding: 7px 6px; text-align: left;
        font-size: 9.5px; font-weight: 800; color: #fff;
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    th.c, td.c { text-align: center; }
    td { padding: 8px 6px; font-size: 10.5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    tr.overdue-row td { background: rgba(254, 226, 226, 0.4); }
    tr.overdue-row:nth-child(even) td { background: rgba(254, 226, 226, 0.6); }

    .ref {
        font-family: 'Courier New', monospace; color: #b45309;
        font-weight: 800; font-size: 11.5px;
    }
    .addr-line { font-size: 10.5px; color: #111827; font-weight: 600; }
    .addr-detail { color: #4b5563; font-size: 9.5px; line-height: 1.35; margin-top: 1px; }
    .quartier { color: #6b7280; font-size: 9px; font-style: italic; margin-top: 1px; }
    .gps {
        font-family: 'Courier New', monospace; color: #047857;
        font-size: 9.5px; font-weight: 600; line-height: 1.3;
    }
    .gps-empty { color: #9ca3af; font-size: 10px; }
    .campaign-chip {
        display: inline-block; padding: 2px 8px;
        background: #f3f4f6; color: #4b5563;
        border-radius: 10px; font-size: 9px; font-weight: 700;
    }
    .campaign-chip.overdue {
        background: #fee2e2; color: #b91c1c;
    }
    .late-badge {
        display: block; font-size: 9px; color: #dc2626;
        font-weight: 800; margin-top: 2px;
    }
    .check {
        display: inline-block; width: 16px; height: 16px;
        border: 1.5px solid #1f2937; border-radius: 3px;
        vertical-align: middle;
    }

    /* ── Empty state ───────────────────────────────────────────── */
    .empty {
        padding: 40px 20px; text-align: center;
        color: #6b7280; font-size: 13px; font-style: italic;
        background: #f9fafb; border-radius: 8px;
    }
    .empty .big { font-size: 28px; margin-bottom: 8px; color: #16a34a; }

    /* ── Zone signature ────────────────────────────────────────── */
    .sign-zone {
        margin-top: 16px; padding: 12px 14px;
        border: 1.5px dashed #6b7280; border-radius: 8px;
        background: #fafafa; font-size: 11px; color: #374151;
        page-break-inside: avoid;
    }
    .sign-zone .row { margin-bottom: 8px; }
    .sign-zone .label { font-weight: 800; color: #111827; }
    .sign-zone .line {
        display: inline-block; border-bottom: 1.2px solid #6b7280;
        min-width: 180px; margin-left: 6px; height: 14px;
    }
    .sign-zone .line.wide { min-width: 360px; }
    .sign-zone .line.short { min-width: 110px; }

    /* ── Footer fixe avec pagination ───────────────────────────── */
    .footer {
        position: fixed; bottom: -8mm; left: 0; right: 0;
        font-size: 9px; color: #6b7280; text-align: center;
        border-top: 1px solid #e5e7eb; padding-top: 4px; padding-bottom: 2px;
        background: #fff;
    }
    .footer .left { float: left; text-align: left; padding-left: 10mm; }
    .footer .right { float: right; text-align: right; padding-right: 10mm; }
    .footer .center { display: inline-block; }
    .footer .pagenum:before { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>

{{-- ──────────────────────────── HEADER ──────────────────────────── --}}
<div class="header">
    @if(!empty($logoCibleLight))
        <div class="logo-cell">
            <img src="{{ $logoCibleLight }}" alt="{{ $operatorName ?? 'CIBLE CI' }}">
        </div>
    @endif
    <div class="title-cell">
        <h1>FEUILLE DE DÉCAPPAGE</h1>
        <div class="subtitle">
            @if($overdueOnly)
                Panneaux en retard ({{ $totals['overdue'] }} campagne{{ $totals['overdue'] > 1 ? 's' : '' }} &gt; 7 jours)
            @else
                Tous les panneaux à décaper · {{ $totals['campaigns'] }} campagne{{ $totals['campaigns'] > 1 ? 's' : '' }} terminée{{ $totals['campaigns'] > 1 ? 's' : '' }}
            @endif
        </div>
    </div>
    <div class="meta-cell">
        <strong>Édité le {{ $totals['generated_at']->format('d/m/Y \à H\hi') }}</strong><br>
        Par <strong>{{ $totals['generated_by'] }}</strong><br>
        {{ $operatorName ?? 'CIBLE CI' }}
    </div>
</div>

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

            <table>
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

{{-- ──────────────────────────── FOOTER FIXE ──────────────────────────── --}}
<div class="footer">
    <span class="left">{{ $operatorName ?? 'CIBLE CI' }} — Feuille de décappage</span>
    <span class="right">Page <span class="pagenum"></span></span>
    <span class="center">Panora · généré le {{ $totals['generated_at']->format('d/m/Y \à H\hi') }}</span>
</div>

</body>
</html>

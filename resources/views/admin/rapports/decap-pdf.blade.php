{{-- 2026-06-19 — Feuille de décappage PDF pour les techs terrain.
     Liste des panneaux NON ENCORE décappés, groupés par campagne, avec
     réf, nom, commune, adresse, GPS + case à cocher pour pointer sur place.

     Variables :
       $campaigns   : Collection<Campaign> avec ->panels (DashboardKpiService::decapList)
       $panelsFull  : Collection<Panel> indexée par id (avec adresse, quartier,
                      lat, lng, commune complète) — re-fetch côté controller
       $totals      : ['campaigns','panels','overdue','generated_by','generated_at']
       $overdueOnly : true si filtre ?overdue=1
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Feuille de décappage — {{ $totals['generated_at']->format('d/m/Y') }}</title>
<style>
    @page { size: A4 portrait; margin: 12mm 10mm 16mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 18px; color: #dc2626; margin: 0 0 4px; letter-spacing: -0.3px; }
    h2 { font-size: 12.5px; color: #111827; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #e5e7eb; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left  { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .subtitle { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .intro {
        background: #fef3c7; border-left: 4px solid #f59e0b;
        padding: 8px 12px; margin-bottom: 12px;
        font-size: 9.5px; color: #92400e; line-height: 1.5;
    }
    .summary { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 6px 0; }
    .summary .cell {
        display: table-cell; padding: 8px 10px;
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px;
        text-align: center; width: 33%;
    }
    .summary .cell .num { font-size: 18px; font-weight: 800; color: #111827; line-height: 1; }
    .summary .cell .lbl { font-size: 8.5px; color: #6b7280; text-transform: uppercase; letter-spacing: .4px; margin-top: 2px; }
    .summary .cell.overdue { background: rgba(220,38,38,.06); border-color: rgba(220,38,38,.4); }
    .summary .cell.overdue .num { color: #dc2626; }
    .campaign-block {
        margin-bottom: 14px; page-break-inside: avoid;
        border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 12px;
    }
    .campaign-block.overdue { border-color: rgba(220,38,38,.5); background: rgba(220,38,38,.02); }
    .campaign-title {
        display: table; width: 100%; margin-bottom: 8px;
    }
    .campaign-title .name {
        display: table-cell; font-size: 12px; font-weight: 800; color: #111827;
        padding-bottom: 2px;
    }
    .campaign-title .meta {
        display: table-cell; text-align: right; font-size: 9px; color: #6b7280;
        vertical-align: top;
    }
    .campaign-title .meta .late { color: #dc2626; font-weight: 800; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    th {
        background: #1f2937; padding: 5px 6px; text-align: left;
        font-size: 8px; font-weight: bold; color: #fff;
        text-transform: uppercase; letter-spacing: 0.3px;
    }
    th.c, td.c { text-align: center; }
    th.r, td.r { text-align: right; }
    td { padding: 6px; font-size: 9px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    .ref { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; font-size: 9.5px; }
    .commune { font-weight: 700; color: #1d4ed8; font-size: 9px; }
    .addr { color: #4b5563; font-size: 8.5px; line-height: 1.35; }
    .quartier { color: #6b7280; font-size: 8px; font-style: italic; }
    .gps { font-family: 'Courier New', monospace; color: #047857; font-size: 8px; }
    .check { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #6b7280; border-radius: 2px; vertical-align: middle; }
    .signed { color: #16a34a; font-weight: bold; font-size: 8.5px; }
    .empty { padding: 30px; text-align: center; color: #9ca3af; font-style: italic; }
    .footer {
        position: fixed; bottom: 4mm; left: 10mm; right: 10mm;
        font-size: 8px; color: #9ca3af; text-align: center;
        border-top: 1px solid #e5e7eb; padding-top: 3px;
    }
    .footer .pagenum:before { content: counter(page) " / " counter(pages); }
    .sign-zone {
        margin-top: 12px; padding: 8px 12px;
        border: 1px dashed #d1d5db; border-radius: 6px;
        background: #fafafa; font-size: 9px; color: #4b5563;
        page-break-inside: avoid;
    }
    .sign-zone .label { font-weight: 700; color: #111827; }
    .sign-zone .line { display: inline-block; border-bottom: 1px solid #6b7280; min-width: 140px; margin-left: 4px; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        <h1>📋 FEUILLE DE DÉCAPPAGE</h1>
        <div class="subtitle">
            @if($overdueOnly)
                Panneaux EN RETARD ({{ $totals['overdue'] }} campagne(s) &gt; 7j)
            @else
                Tous les panneaux à décaper · {{ $totals['campaigns'] }} campagne(s) terminée(s)
            @endif
        </div>
    </div>
    <div class="right">
        Édité le {{ $totals['generated_at']->format('d/m/Y H:i') }}<br>
        Par {{ $totals['generated_by'] }}<br>
        CIBLE CI
    </div>
</div>

<div class="intro">
    <strong>📌 Mode d'emploi terrain :</strong>
    Va sur chaque panneau listé ci-dessous, retire l'affichage, et coche la case
    « ✓ Fait ». Note l'heure et tes initiales dans la dernière colonne. Rends
    la feuille signée au superviseur en fin de tournée. En cas de problème
    (panneau cassé, accès bloqué, etc.), écris le motif dans la marge.
</div>

<div class="summary">
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
        <div class="lbl">En retard (&gt; 7j)</div>
    </div>
</div>

@if($campaigns->isEmpty())
    <div class="empty">
        🎉 Aucun panneau à décaper.<br>
        Toutes les campagnes terminées sont à jour.
    </div>
@else
    @foreach($campaigns as $c)
        @php
            $daysOverdue = (int) $c->end_date->diffInDays(now(), false);
            $pendingPanels = $c->panels->filter(fn($p) => $p->decapped_at === null);
        @endphp
        <div class="campaign-block {{ $c->is_overdue ? 'overdue' : '' }}">
            <div class="campaign-title">
                <div class="name">
                    🏷️ {{ $c->name }}
                    <span style="font-size:9px;font-weight:500;color:#6b7280">· {{ $c->client?->name ?? '—' }}</span>
                </div>
                <div class="meta">
                    Fin : {{ $c->end_date->format('d/m/Y') }}<br>
                    @if($c->is_overdue)
                        <span class="late">+{{ $daysOverdue }}j de retard</span>
                    @else
                        {{ $daysOverdue }}j depuis fin
                    @endif
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th style="width:13%">Référence</th>
                        <th style="width:18%">Commune</th>
                        <th style="width:36%">Nom &amp; Adresse</th>
                        <th style="width:18%">GPS</th>
                        <th class="c" style="width:5%">✓ Fait</th>
                        <th style="width:10%">Initiales / heure</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pendingPanels as $p)
                        @php $full = $panelsFull->get($p->id); @endphp
                        <tr>
                            <td class="ref">{{ $p->reference }}</td>
                            <td>
                                <span class="commune">{{ $full?->commune?->name ?? $p->commune?->name ?? '—' }}</span>
                                @if($full?->commune?->city)
                                    <div class="quartier">{{ $full->commune->city }}</div>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $p->name ?: '—' }}</strong>
                                @if($full?->adresse)
                                    <div class="addr">📍 {{ $full->adresse }}</div>
                                @endif
                                @if($full?->quartier)
                                    <div class="quartier">Quartier : {{ $full->quartier }}</div>
                                @endif
                            </td>
                            <td>
                                @if($full?->latitude && $full?->longitude)
                                    <span class="gps">{{ number_format($full->latitude, 5) }},<br>{{ number_format($full->longitude, 5) }}</span>
                                @else
                                    <span style="color:#9ca3af;font-size:8px">—</span>
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

    <div class="sign-zone">
        <span class="label">Tournée du :</span>
        <span class="line"></span>
        &nbsp;&nbsp;
        <span class="label">Technicien :</span>
        <span class="line"></span>
        <br><br>
        <span class="label">Observations :</span>
        <span class="line" style="min-width:380px"></span>
        <br><br>
        <span class="label">Signature tech :</span>
        <span class="line" style="min-width:160px"></span>
        &nbsp;&nbsp;
        <span class="label">Validation superviseur :</span>
        <span class="line" style="min-width:160px"></span>
    </div>
@endif

<div class="footer">
    Panora · CIBLE CI · Feuille de décappage générée le {{ $totals['generated_at']->format('d/m/Y à H:i') }}
    &nbsp;·&nbsp; Page <span class="pagenum"></span>
</div>

</body>
</html>

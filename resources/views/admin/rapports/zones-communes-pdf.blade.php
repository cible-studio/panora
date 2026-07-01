<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Zones & Communes — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm 20mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 16px; color: #e8a020; margin: 0 0 4px; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left  { display: table-cell; vertical-align: middle; }
    .header .mid   { display: table-cell; vertical-align: middle; }
    .header .right { display: table-cell; vertical-align: middle; text-align: right; font-size: 8.5px; color: #6b7280; }
    .header .logo { height: 38px; margin-right: 14px; }
    .period { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .meta { font-size: 9px; color: #374151; margin-top: 8px; padding: 6px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 3px; }
    .meta strong { color: #111827; }
    .kpis { display: table; width: 100%; margin: 8px 0 12px; border-collapse: separate; border-spacing: 5px 0; }
    .kpis .cell { display: table-cell; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 4px; padding: 6px 10px; text-align: center; }
    .kpis .cell .n { font-size: 15px; font-weight: bold; color: #b45309; display: block; }
    .kpis .cell .l { font-size: 7.5px; text-transform: uppercase; color: #78716c; letter-spacing: .4px; }

    /* ── Section commune ── */
    .commune-block { margin-top: 16px; page-break-inside: avoid; }
    .commune-head {
        background: #0a0c10; color: #fff; padding: 8px 12px; border-radius: 6px 6px 0 0;
        display: table; width: 100%;
    }
    .commune-head .name { display: table-cell; font-size: 12px; font-weight: bold; letter-spacing: .5px; }
    .commune-head .stats {
        display: table-cell; text-align: right; font-size: 9px; color: #cbd5e1;
    }
    .commune-head .stats strong { color: #fff; }
    .commune-head .badge-zone {
        display: inline-block; padding: 1px 8px; font-size: 8px; border-radius: 999px;
        margin-left: 8px; font-weight: bold;
    }
    .commune-head .badge-abj { background: rgba(59,130,246,.35); color: #dbeafe; }
    .commune-head .badge-int { background: rgba(16,185,129,.35); color: #d1fae5; }

    .commune-empty {
        background: #f9fafb; border: 1px solid #e5e7eb; border-top: 0;
        padding: 10px 14px; font-size: 8.5px; color: #6b7280; font-style: italic;
        border-radius: 0 0 6px 6px;
    }

    table.details {
        width: 100%; border-collapse: collapse; border: 1px solid #e5e7eb;
        border-top: 0; border-radius: 0 0 6px 6px; overflow: hidden;
    }
    table.details th {
        background: #f3f4f6; padding: 5px 8px; text-align: left; font-size: 7.5px;
        font-weight: bold; color: #374151; text-transform: uppercase; letter-spacing: 0.3px;
        border-bottom: 1px solid #e5e7eb;
    }
    table.details th.r, table.details td.r { text-align: right; }
    table.details th.c, table.details td.c { text-align: center; }
    table.details td { padding: 4px 8px; font-size: 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    table.details tr:nth-child(even) td { background: #fafafa; }
    .ref { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; }
    .muted { color: #6b7280; font-size: 7.5px; }
    .badge { display: inline-block; padding: 1px 5px; font-size: 7px; border-radius: 999px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
    .badge-actif    { background: rgba(34,197,94,.15);  color: #15803d; }
    .badge-planifie { background: rgba(59,130,246,.15); color: #1d4ed8; }
    .badge-termine  { background: rgba(107,114,128,.18);color: #374151; }
    .badge-pause    { background: rgba(249,115,22,.15); color: #c2410c; }
    .badge-ext      { background: rgba(107,114,128,.15);color: #4b5563; margin-top: 2px; display: inline-block; }
    .badge-decap    { background: rgba(220,38,38,.15);  color: #b91c1c; margin-top: 2px; display: inline-block; }
    .pct { font-weight: bold; }
    .pct-hi { color: #16a34a; }
    .pct-mid { color: #f97316; }
    .pct-lo { color: #dc2626; }

    .footer { position: fixed; bottom: 6mm; left: 10mm; right: 10mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
    .footer .pagenum:before { content: counter(page) " / " counter(pages); }
</style>
</head>
<body>

<div class="header">
    @if(!empty($logoCibleLight))
        <div class="mid">
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" class="logo">
        </div>
    @endif
    <div class="left">
        <h1>ZONES & COMMUNES — DÉTAIL PAR PANNEAU</h1>
        <div class="period">Panneaux occupés × campagnes, groupés par commune · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

@include('admin.rapports.partials._filter_recap_pdf')

<div class="kpis">
    <div class="cell"><span class="n">{{ number_format($summary['nb_communes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Communes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_panels'] ?? 0, 0, ',', ' ') }}</span><span class="l">Panneaux total</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_occupes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Occupés</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_libres'] ?? 0, 0, ',', ' ') }}</span><span class="l">Libres</span></div>
    <div class="cell"><span class="n">{{ ($summary['taux_moyen'] ?? 0) }} %</span><span class="l">Taux moyen</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['ca_total'] ?? 0, 0, ',', ' ') }}</span><span class="l">CA FCFA</span></div>
</div>

<div class="meta">
    Période : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
    · Pour chaque commune : détail des panneaux occupés avec la (les) campagne(s) qui les ont utilisés sur la période.
    · Statuts campagne inclus : planifié, actif, en pause, terminé (annulés exclus).
    · Communes triées par taux d'occupation décroissant.
</div>

{{-- Boucle par commune ─────────────────────────────────────────── --}}
@forelse($rows as $c)
    @php
        $rate = $c['taux'] ?? 0;
        $rateClass = $rate >= 60 ? 'pct-hi' : ($rate >= 25 ? 'pct-mid' : 'pct-lo');
        $zoneBadge = $c['zone'] === 'Abidjan' ? 'badge-abj' : 'badge-int';
        $panelsList = $detailsByCommune[$c['commune']] ?? collect();
    @endphp

    <div class="commune-block">
        <div class="commune-head">
            <div class="name">
                {{ $c['commune'] }}
                <span class="badge-zone {{ $zoneBadge }}">{{ $c['zone'] }}</span>
                <span class="muted" style="color:#9ca3af;font-weight:normal;margin-left:6px">— {{ $c['city'] }}</span>
            </div>
            <div class="stats">
                <strong>{{ (int) $c['total'] }}</strong> pann. ·
                <strong>{{ (int) $c['occupes'] }}</strong> occ. ·
                <strong>{{ (int) $c['libres'] }}</strong> libres ·
                Taux <strong class="pct {{ $rateClass }}" style="color:{{ $rate >= 60 ? '#86efac' : ($rate >= 25 ? '#fdba74' : '#fca5a5') }}">{{ $rate }} %</strong> ·
                CA <strong>{{ number_format((float) $c['ca_annee'], 0, ',', ' ') }}</strong> FCFA
            </div>
        </div>

        @if($panelsList->isEmpty())
            <div class="commune-empty">
                Aucun panneau de cette commune n'a été occupé sur la période.
                @if(($c['occupes'] ?? 0) > 0)
                    <br><em>Note : {{ $c['occupes'] }} panneau(x) affiché(s) en "occupé" mais liés à des campagnes annulées ou hors périmètre RBAC.</em>
                @endif
            </div>
        @else
            <table class="details">
                <thead>
                    <tr>
                        <th style="width:14%">Réf. Panneau</th>
                        <th style="width:20%">Nom / Type</th>
                        <th style="width:22%">Campagne</th>
                        <th style="width:18%">Client</th>
                        <th class="c" style="width:9%">Début</th>
                        <th class="c" style="width:9%">Fin</th>
                        <th class="r" style="width:8%">Durée</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($panelsList as $p)
                        @php
                            $st = (string) $p['campaign_status'];
                            $badgeClass = match($st) {
                                'actif'    => 'badge-actif',
                                'planifie' => 'badge-planifie',
                                'termine'  => 'badge-termine',
                                'pause'    => 'badge-pause',
                                default    => 'badge-termine',
                            };
                            $statusLabel = match($st) {
                                'actif'    => 'Actif',
                                'planifie' => 'Planifié',
                                'termine'  => 'Terminé',
                                'pause'    => 'En pause',
                                default    => ucfirst($st),
                            };
                        @endphp
                        <tr>
                            <td>
                                <span class="ref">{{ $p['panel_ref'] }}</span>
                                @if($p['is_external'])
                                    <div><span class="badge badge-ext">Externe</span></div>
                                @endif
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['panel_name'] ?? '', 30) }}
                                <div class="muted">{{ $p['panel_dims'] }}{{ $p['panel_type'] && $p['panel_type'] !== '—' ? ' · '.$p['panel_type'] : '' }}</div>
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['campaign_name'] ?? '', 26) }}
                                <div><span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span></div>
                            </td>
                            <td>
                                {{ \Illuminate\Support\Str::limit($p['client_name'] ?? '', 22) }}
                                @if($p['client_sector'] && $p['client_sector'] !== '—')
                                    <div class="muted">{{ \Illuminate\Support\Str::limit($p['client_sector'], 22) }}</div>
                                @endif
                            </td>
                            <td class="c">{{ $p['campaign_start'] ? \Carbon\Carbon::parse($p['campaign_start'])->format('d/m/y') : '—' }}</td>
                            <td class="c">{{ $p['campaign_end']   ? \Carbon\Carbon::parse($p['campaign_end'])->format('d/m/y')   : '—' }}</td>
                            <td class="r">
                                <strong>{{ $p['duration_label'] }}</strong>
                                @if($p['decapped_at'])
                                    <div><span class="badge badge-decap">Décapé {{ \Carbon\Carbon::parse($p['decapped_at'])->format('d/m/y') }}</span></div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@empty
    <div style="text-align:center;color:#6b7280;font-style:italic;padding:40px">
        Aucune commune ne correspond aux filtres sélectionnés.
    </div>
@endforelse

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora · Page <span class="pagenum"></span>
</div>

</body>
</html>

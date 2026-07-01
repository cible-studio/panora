<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Occupation détaillée — CIBLE CI</title>
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
    table { width: 100%; border-collapse: collapse; }
    th { background: #0a0c10; padding: 6px 6px; text-align: left; font-size: 8px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    th.r, td.r { text-align: right; }
    th.c, td.c { text-align: center; }
    td { padding: 4px 6px; font-size: 8px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    tr:nth-child(even) td { background: #fafafa; }
    .ref { font-family: 'Courier New', monospace; color: #b45309; font-weight: bold; }
    .muted { color: #6b7280; font-size: 7.5px; }
    .badge { display: inline-block; padding: 1px 6px; font-size: 7.5px; border-radius: 999px; font-weight: bold; text-transform: uppercase; letter-spacing: .3px; }
    .badge-actif    { background: rgba(34,197,94,.15);  color: #15803d; }
    .badge-planifie { background: rgba(59,130,246,.15); color: #1d4ed8; }
    .badge-termine  { background: rgba(107,114,128,.18);color: #374151; }
    .badge-pause    { background: rgba(249,115,22,.15); color: #c2410c; }
    .badge-ext      { background: rgba(107,114,128,.15);color: #4b5563; margin-top: 2px; display: inline-block; }
    .badge-decap    { background: rgba(220,38,38,.15);  color: #b91c1c; margin-top: 2px; display: inline-block; }
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
        <h1>OCCUPATION DÉTAILLÉE</h1>
        <div class="period">Panneaux occupés × campagnes · {{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

@include('admin.rapports.partials._filter_recap_pdf')

<div class="kpis">
    <div class="cell"><span class="n">{{ number_format($summary['total_rows'] ?? 0, 0, ',', ' ') }}</span><span class="l">Lignes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_panels'] ?? 0, 0, ',', ' ') }}</span><span class="l">Panneaux</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_campaigns'] ?? 0, 0, ',', ' ') }}</span><span class="l">Campagnes</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_clients'] ?? 0, 0, ',', ' ') }}</span><span class="l">Clients</span></div>
    <div class="cell"><span class="n">{{ number_format($summary['nb_communes'] ?? 0, 0, ',', ' ') }}</span><span class="l">Communes</span></div>
    @if(($summary['nb_externals'] ?? 0) > 0)
    <div class="cell"><span class="n">{{ number_format($summary['nb_externals'], 0, ',', ' ') }}</span><span class="l">Externes</span></div>
    @endif
</div>

<div class="meta">
    Période analysée : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
    · Une ligne = un panneau occupé par une campagne dont la période chevauche l'intervalle demandé
    · Statuts inclus : planifié, actif, en pause, terminé (annulés exclus).
</div>

<table>
    <thead>
        <tr>
            <th>Commune</th>
            <th>Panneau</th>
            <th>Type / Dim.</th>
            <th>Campagne</th>
            <th>Client</th>
            <th class="c">Début</th>
            <th class="c">Fin</th>
            <th class="r">Durée</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $r)
            @php
                $st = (string) $r['campaign_status'];
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
                    <strong>{{ $r['commune'] }}</strong>
                    @if($r['city'] !== $r['commune'])
                        <div class="muted">{{ $r['city'] }}</div>
                    @endif
                </td>
                <td>
                    <span class="ref">{{ $r['panel_ref'] }}</span>
                    <div class="muted">{{ \Illuminate\Support\Str::limit($r['panel_name'] ?? '', 40) }}</div>
                    @if($r['is_external'])
                        <span class="badge badge-ext">Externe</span>
                    @endif
                </td>
                <td>
                    <div>{{ $r['panel_dims'] }}</div>
                    @if($r['panel_type'] && $r['panel_type'] !== '—')
                        <div class="muted">{{ $r['panel_type'] }}</div>
                    @endif
                </td>
                <td>
                    <div>{{ \Illuminate\Support\Str::limit($r['campaign_name'] ?? '', 34) }}</div>
                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </td>
                <td>
                    <div>{{ \Illuminate\Support\Str::limit($r['client_name'] ?? '', 24) }}</div>
                    @if($r['client_sector'] && $r['client_sector'] !== '—')
                        <div class="muted">{{ $r['client_sector'] }}</div>
                    @endif
                </td>
                <td class="c">{{ $r['campaign_start'] ? \Carbon\Carbon::parse($r['campaign_start'])->format('d/m/Y') : '—' }}</td>
                <td class="c">{{ $r['campaign_end']   ? \Carbon\Carbon::parse($r['campaign_end'])->format('d/m/Y')   : '—' }}</td>
                <td class="r">
                    <strong>{{ $r['duration_label'] }}</strong>
                    @if($r['decapped_at'])
                        <div><span class="badge badge-decap">Décapé {{ \Carbon\Carbon::parse($r['decapped_at'])->format('d/m/y') }}</span></div>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center;color:#6b7280;font-style:italic;padding:24px">Aucune occupation sur la période et les filtres choisis.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Document généré automatiquement par Panora · Page <span class="pagenum"></span>
</div>

</body>
</html>

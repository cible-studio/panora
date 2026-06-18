<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Analyse des signalements — CIBLE CI</title>
<style>
    @page { size: A4 landscape; margin: 14mm 12mm 22mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 17px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 11.5px; color: #111827; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1.5px solid #e8a020; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .period { font-size: 11px; color: #6b7280; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #f3f4f6; padding: 5px 7px; text-align: left; font-size: 8.5px; font-weight: bold; color: #374151; border-bottom: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 5px 7px; font-size: 9.5px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: #6b7280; }
    .kpi-grid { display: table; width: 100%; margin-bottom: 10px; border-collapse: separate; border-spacing: 4px; }
    .kpi-row { display: table-row; }
    .kpi { display: table-cell; padding: 8px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 4px; width: 20%; }
    .kpi-label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.6px; }
    .kpi-value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }
    .kpi-sub { font-size: 8px; color: #6b7280; margin-top: 1px; }
    .color-total   { border-left-color: #6b7280; }
    .color-pending { border-left-color: #b45309; }
    .color-done    { border-left-color: #15803d; }
    .color-motif   { border-left-color: #e8a020; }
    .color-rec     { border-left-color: #b91c1c; }
    .filter-chip { display: inline-block; padding: 1px 7px; background: #fef3c7; border-radius: 5px; font-size: 9px; color: #92400e; margin-right: 4px; font-weight: 600; }
    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; background: #fff; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:30px;margin-bottom:5px;">
        @endif
        <h1>ANALYSE DES SIGNALEMENTS</h1>
        <div class="period">Analyse des motifs de retard signalés par les techniciens · CIBLE CI</div>
        <div class="period">Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)</div>
        <div style="margin-top:6px">
            @if($motifFilter)         <span class="filter-chip">Motif : {{ $motifFilter->label() }}</span>@endif
            @if(!empty($filters['zone']))      <span class="filter-chip">Zone : {{ ucfirst($filters['zone']) }}</span>@endif
            @if(!empty($filters['commune_id']))<span class="filter-chip">Commune filtrée</span>@endif
            @if(!empty($filters['client_id'])) <span class="filter-chip">Client filtré</span>@endif
            @if($status !== 'all')             <span class="filter-chip">Statut : {{ $status }}</span>@endif
        </div>
    </div>
    <div class="right">
        Édité le {{ $generatedAt->format('d/m/Y à H:i') }}<br>
        Par {{ $user->name ?? '—' }}
    </div>
</div>

{{-- KPIs --}}
<div class="kpi-grid">
    <div class="kpi-row">
        <div class="kpi color-total">
            <div class="kpi-label">Total signalements</div>
            <div class="kpi-value">{{ $stats['kpi']['total_all'] }}</div>
            <div class="kpi-sub">sur la période</div>
        </div>
        <div class="kpi color-pending">
            <div class="kpi-label">En attente</div>
            <div class="kpi-value">{{ $stats['kpi']['total_open'] }}</div>
            <div class="kpi-sub">non résolus</div>
        </div>
        <div class="kpi color-done">
            <div class="kpi-label">Résolus</div>
            <div class="kpi-value">{{ $stats['kpi']['total_resolved'] }}</div>
            <div class="kpi-sub">maintenance ou dismissed</div>
        </div>
        <div class="kpi color-motif">
            <div class="kpi-label">Motif dominant</div>
            <div class="kpi-value" style="font-size:11px">
                {{ $stats['kpi']['dominant_motif']?->icon() ?? '—' }}
                {{ $stats['kpi']['dominant_motif']?->label() ?? 'Aucun' }}
            </div>
            <div class="kpi-sub">{{ $stats['kpi']['dominant_count'] }} ouverts</div>
        </div>
        <div class="kpi color-rec">
            <div class="kpi-label">Panneaux récurrents</div>
            <div class="kpi-value">{{ $stats['kpi']['recurring_count'] }}</div>
            <div class="kpi-sub">≥ 2 signalements même motif</div>
        </div>
    </div>
</div>

{{-- Répartition par motif --}}
@if($stats['by_motif_open']->isNotEmpty())
<h2>📊 Répartition des signalements ouverts par motif</h2>
<table>
    <thead>
        <tr>
            <th style="width:40%">Motif</th>
            <th class="r">Nb ouverts</th>
            <th class="r">Part</th>
            <th>Indicateur</th>
        </tr>
    </thead>
    <tbody>
        @php $totalOpen = max(1, $stats['kpi']['total_open']); @endphp
        @foreach($stats['by_motif_open'] as $row)
            @php $pct = round(($row['count'] / $totalOpen) * 100, 1); @endphp
            <tr>
                <td class="b">{{ $row['motif']->icon() }} {{ $row['motif']->label() }}</td>
                <td class="r b">{{ $row['count'] }}</td>
                <td class="r">{{ $pct }} %</td>
                <td><span style="display:inline-block;height:8px;width:{{ min(100, $pct * 2) }}px;background:{{ $row['motif']->color() }};border-radius:3px"></span></td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Cross-commune (Top 10) --}}
@if($stats['cross_commune']->isNotEmpty())
<h2>🌍 Cross-commune — signalements par commune (Top 10)</h2>
<table>
    <thead>
        <tr>
            <th>Commune</th>
            <th class="r">Total</th>
            <th class="r">Ouverts</th>
            <th class="r">Résolus</th>
        </tr>
    </thead>
    <tbody>
        @foreach($stats['cross_commune']->take(10) as $row)
            <tr>
                <td class="b">{{ $row['commune'] }}</td>
                <td class="r">{{ $row['total'] }}</td>
                <td class="r" style="color:#b45309">{{ $row['open'] }}</td>
                <td class="r" style="color:#15803d">{{ $row['resolved'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Panneaux récurrents --}}
@if(!empty($stats['recurring']) && $stats['recurring']->isNotEmpty())
<h2>🔁 Panneaux récurrents (≥ 2 signalements même motif)</h2>
<table>
    <thead>
        <tr>
            <th>Panneau</th>
            <th>Commune</th>
            <th>Motif récurrent</th>
            <th class="r">Nb signalements</th>
        </tr>
    </thead>
    <tbody>
        @foreach($stats['recurring']->take(20) as $row)
            <tr>
                <td class="b">{{ $row['panel_reference'] ?? '—' }}</td>
                <td>{{ $row['commune_name'] ?? '—' }}</td>
                <td>{{ $row['motif']?->label() ?? '—' }}</td>
                <td class="r b" style="color:#b91c1c">{{ $row['count'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Détail des signalements (Top 100 chronologique) --}}
@if($signalements->isNotEmpty())
<h2>📋 Détail des signalements ({{ $signalements->count() }} affichés{{ $signalements->count() >= 100 ? ' — 100 max' : '' }})</h2>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Panneau</th>
            <th>Commune</th>
            <th>Campagne / client</th>
            <th>Technicien</th>
            <th>Motif</th>
            <th>Statut</th>
        </tr>
    </thead>
    <tbody>
        @foreach($signalements as $a)
            @php
                $motif = $a->effectiveMotif();
                $isResolved = $a->resolved_at !== null || $a->maintenance_id !== null;
            @endphp
            <tr>
                <td class="muted">{{ $a->created_at?->format('d/m/Y') ?? '—' }}</td>
                <td class="b">{{ $a->task?->panel?->reference ?? '—' }}</td>
                <td>{{ $a->task?->panel?->commune?->name ?? '—' }}</td>
                <td>
                    {{ $a->task?->campaign?->name ?? '—' }}
                    @if($a->task?->campaign?->client)
                        <div class="muted" style="font-size:8.5px">{{ $a->task->campaign->client->name }}</div>
                    @endif
                </td>
                <td>{{ $a->task?->technicien?->name ?? '—' }}</td>
                <td>{{ $motif?->icon() }} {{ $motif?->label() ?? '—' }}</td>
                <td>
                    @if($isResolved)
                        <span style="color:#15803d;font-weight:bold">✓ Résolu</span>
                    @else
                        <span style="color:#b45309;font-weight:bold">⏳ En attente</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">
    CIBLE CI — Analyse des signalements · Édité par Panora le {{ $generatedAt->format('d/m/Y à H:i') }}
    · Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
</div>

</body>
</html>

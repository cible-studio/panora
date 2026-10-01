<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Analyse des signalements — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
@include('pdf.partials.charte-styles')
<style>
    @page { size: A4 landscape; margin: 14mm 12mm 22mm 12mm !important; }
    body { font-size: 10px; color: {{ $charte['noir'] }}; line-height: 1.25; }
    h2 {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 11.5px; font-weight: 700; color: {{ $charte['noir'] }};
        margin: 14px 0 6px; padding: 0 0 4px 8px;
        border-left: 3px solid {{ $charte['rouge'] }};
        border-bottom: 1px solid {{ $charte['gris'] }};
    }
    table.ch-table { margin-bottom: 8px; font-size: 9.5px; }
    .ch-table tbody td { vertical-align: top; }
    .ch-table th.r, .ch-table td.r { text-align: right; }
    .ch-table th.c, .ch-table td.c { text-align: center; }
    .r { text-align: right; }
    .c { text-align: center; }
    .b { font-weight: bold; }
    .muted { color: {{ $charte['texte_doux'] }}; }
    .ch-kpi { width: 20%; }
    .filter-chip { display: inline-block; padding: 1px 7px; background: {{ $charte['jaune_clair'] }}; border-radius: 3px; font-size: 9px; color: {{ $charte['noir'] }}; margin-right: 4px; font-weight: 600; }
    .filters { margin: -4px 0 10px; }
</style>
</head>
<body>

@php
    // 2026-10-01 — charte graphique : couleur de la barre « Indicateur » par
    // motif, mappée côté vue sur la palette (DelayReason::color() renvoie des
    // hex hors charte, utilisés aussi par l'interface web — non modifié).
    $motifTone = fn ($m) => match ($m?->value) {
        'panneau_casse'                => $charte['rouge'],
        'acces_bloque', 'mauvaise_adresse', 'retard_client' => $charte['jaune'],
        'technicien_absent', 'retard_impression'            => $charte['violet'],
        'materiel_indisponible', 'meteo'                    => $charte['bleu'],
        default                        => $charte['texte_pale'],
    };
@endphp

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre + méta). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'ANALYSE DES SIGNALEMENTS',
    'docSubtitle' => 'Analyse des motifs de retard signalés par les techniciens · CIBLE CI',
    'docMeta'     => [
        'Édité le ' . $generatedAt->format('d/m/Y à H:i'),
        'Par ' . ($user->name ?? '—'),
    ],
])

<div class="ch-info">
    <strong>Période :</strong> {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)
</div>
<div class="filters">
    @if($motifFilter)         <span class="filter-chip">Motif : {{ $motifFilter->label() }}</span>@endif
    @if(!empty($filters['zone']))      <span class="filter-chip">Zone : {{ ucfirst($filters['zone']) }}</span>@endif
    @if(!empty($filters['commune_id']))<span class="filter-chip">Commune filtrée</span>@endif
    @if(!empty($filters['client_id'])) <span class="filter-chip">Client filtré</span>@endif
    @if($status !== 'all')             <span class="filter-chip">Statut : {{ $status }}</span>@endif
</div>

{{-- KPIs --}}
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-noir">
            <div class="ch-kpi-label">Total signalements</div>
            <div class="ch-kpi-value">{{ $stats['kpi']['total_all'] }}</div>
            <div class="ch-kpi-sub">sur la période</div>
        </td>
        <td class="ch-kpi k-jaune">
            <div class="ch-kpi-label">En attente</div>
            <div class="ch-kpi-value">{{ $stats['kpi']['total_open'] }}</div>
            <div class="ch-kpi-sub">non résolus</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="ch-kpi-label">Résolus</div>
            <div class="ch-kpi-value">{{ $stats['kpi']['total_resolved'] }}</div>
            <div class="ch-kpi-sub">maintenance ou dismissed</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Motif dominant</div>
            {{-- 2026-10-01 — icône emoji du motif retirée (rendue en carré vide dans le PDF). --}}
            <div class="ch-kpi-value" style="font-size:11px">
                {{ $stats['kpi']['dominant_motif']?->label() ?? 'Aucun' }}
            </div>
            <div class="ch-kpi-sub">{{ $stats['kpi']['dominant_count'] }} ouverts</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Panneaux récurrents</div>
            <div class="ch-kpi-value">{{ $stats['kpi']['recurring_count'] }}</div>
            <div class="ch-kpi-sub">≥ 2 signalements même motif</div>
        </td>
    </tr>
</table>

{{-- Répartition par motif --}}
@if($stats['by_motif_open']->isNotEmpty())
<h2>Répartition des signalements ouverts par motif</h2>
<table class="ch-table">
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
                <td class="b">{{ $row['motif']->label() }}</td>
                <td class="r b">{{ $row['count'] }}</td>
                <td class="r">{{ $pct }} %</td>
                <td><span style="display:inline-block;height:8px;width:{{ min(100, $pct * 2) }}px;background:{{ $motifTone($row['motif']) }};border-radius:3px"></span></td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Cross-commune (Top 10) --}}
@if($stats['cross_commune']->isNotEmpty())
<h2>Cross-commune — signalements par commune (Top 10)</h2>
<table class="ch-table">
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
                <td class="r"><span class="ch-badge ch-badge-jaune">{{ $row['open'] }}</span></td>
                <td class="r c-vert">{{ $row['resolved'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Panneaux récurrents --}}
@if(!empty($stats['recurring']) && $stats['recurring']->isNotEmpty())
<h2>Panneaux récurrents (≥ 2 signalements même motif)</h2>
<table class="ch-table">
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
                <td class="r b c-rouge">{{ $row['count'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Détail des signalements (Top 100 chronologique) --}}
@if($signalements->isNotEmpty())
<h2>Détail des signalements ({{ $signalements->count() }} affichés{{ $signalements->count() >= 100 ? ' — 100 max' : '' }})</h2>
<table class="ch-table">
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
                <td>{{ $motif?->label() ?? '—' }}</td>
                <td>
                    @if($isResolved)
                        <span class="ch-badge ch-badge-vert">✓ Résolu</span>
                    @else
                        <span class="ch-badge ch-badge-jaune">En attente</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Analyse des signalements · Édité par Panora le ' . $generatedAt->format('d/m/Y à H:i')
                  . ' · Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
])

</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des campagnes — CIBLE CI</title>
    {{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres.
         2026-11-05 — refait pour être envoyé tel quel à l'administration :
         la sélection en clair dans l'en-tête (plus « Filtres appliqués
         depuis l'interface admin »), chiffres clés, colonne Commercial,
         ligne de total. Critères : CampagneFiltres::description(). --}}
    @include('pdf.partials.charte-styles')
    <style>
        /* margin-bottom 22mm : le pied commun charte-footer se place dans la
           marge basse (DomPDF : !important obligatoire). */
        @page { margin: 12mm 12mm 22mm 12mm !important; }
        body  { color: {{ $charte['noir'] }}; font-size: 9px; line-height: 1.3; padding-bottom: 4mm; }

        .kpi-montant .ch-kpi-value { color: {{ $charte['rouge'] }}; }

        table.ch-table th, table.ch-table td { vertical-align: middle; }
        table.ch-table thead th { font-size: 7.5px; letter-spacing: 0.5px; padding: 7px 8px; }
        table.ch-table tbody td { padding: 7px 8px; }
        table.ch-table tfoot td { padding: 8px; }

        .ref  { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['texte_doux'] }}; font-weight: 700; }
        .nom  { font-weight: 700; color: {{ $charte['noir'] }}; }
        .num  { text-align: right; white-space: nowrap; }
        .date { white-space: nowrap; }
        .empty-row { text-align: center; padding: 24px; color: {{ $charte['texte_pale'] }}; }
    </style>
</head>
<body>

@php
    $enCours = $campaigns->filter(fn ($c) => $c->status?->value === 'actif')->count();
@endphp

@include('pdf.partials.charte-header', [
    'docKicker'   => 'Liste des campagnes',
    'docTitle'    => $criteres ? implode(' · ', $criteres) : 'Toutes les campagnes',
    'docMeta'     => ['Édité le ' . $generated],
])

<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-noir">
            <div class="ch-kpi-label">Campagnes</div>
            <div class="ch-kpi-value">{{ $campaigns->count() }}</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="ch-kpi-label">Dont en cours</div>
            <div class="ch-kpi-value">{{ $enCours }}</div>
        </td>
        <td class="ch-kpi k-bleu">
            <div class="ch-kpi-label">Panneaux</div>
            <div class="ch-kpi-value">{{ number_format($totalPanneaux, 0, ',', ' ') }}</div>
        </td>
        <td class="ch-kpi k-rouge kpi-montant">
            <div class="ch-kpi-label">Montant total</div>
            <div class="ch-kpi-value">{{ number_format($totalAmount, 0, ',', ' ') }} FCFA</div>
        </td>
    </tr>
</table>

<table class="ch-table">
    <thead>
        <tr>
            <th style="width:6%">N°</th>
            <th style="width:21%">Campagne</th>
            <th style="width:16%">Client</th>
            <th style="width:14%">Commercial</th>
            <th style="width:8%">Début</th>
            <th style="width:8%">Fin</th>
            <th style="width:9%">Statut</th>
            <th style="width:6%" class="num">Panneaux</th>
            <th style="width:12%" class="num">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($campaigns as $c)
            @php
                // 2026-10-01 — charte graphique : badges pleins de la palette.
                $statusClass = match($c->status?->value) {
                    'actif'    => 'ch-badge-vert',
                    'pose'     => 'ch-badge-bleu',
                    'planifie' => 'ch-badge-jaune',
                    'termine'  => 'ch-badge-gris',
                    'annule'   => 'ch-badge-rouge',
                    default    => 'ch-badge-gris',
                };
            @endphp
            <tr>
                <td><span class="ref">#{{ $c->id }}</span></td>
                <td class="nom">{{ $c->name }}</td>
                <td>{{ $c->client?->name ?? '—' }}</td>
                {{-- Commercial responsable ; à défaut, le créateur (même règle
                     que la Performance commerciale). --}}
                <td>{{ $c->commercial?->name ?? $c->user?->name ?? '—' }}</td>
                <td class="date">{{ $c->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="date">{{ $c->end_date?->format('d/m/Y') ?? '—' }}</td>
                <td><span class="ch-badge {{ $statusClass }}">{{ $c->status?->label() ?? '—' }}</span></td>
                <td class="num">{{ $c->panels_count ?? 0 }}</td>
                <td class="num">{{ number_format((float) $c->total_amount, 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-row">Aucune campagne ne correspond à cette sélection.</td></tr>
        @endforelse
    </tbody>
    @if($campaigns->isNotEmpty())
    {{-- En pied de tableau : dans le tbody, une ligne paire prendrait le fond
         gris des lignes alternées et le texte blanc du total serait illisible. --}}
    <tfoot>
        <tr class="ch-total">
            <td colspan="7">Total · {{ $campaigns->count() }} campagne(s)</td>
            <td class="num">{{ number_format($totalPanneaux, 0, ',', ' ') }}</td>
            <td class="num">{{ number_format($totalAmount, 0, ',', ' ') }}</td>
        </tr>
    </tfoot>
    @endif
</table>

{{-- 2026-10-01 — charte graphique : pied commun (régie · slogan · coordonnées · page). --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'Document confidentiel',
])

</body>
</html>

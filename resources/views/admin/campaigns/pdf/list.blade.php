<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des campagnes — CIBLE CI</title>
    {{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
    @include('pdf.partials.charte-styles')
    <style>
        /* margin-bottom 22mm + body padding-bottom = double garde-fou
           contre le débordement du tableau sur le footer (bug DomPDF).
           Ici on est en portrait A4 par défaut → 22mm suffit (au lieu
           de 26mm sur les rapports paysage).
           2026-10-01 — charte graphique : !important (marges respectées
           par DomPDF) ; le pied commun charte-footer se place dans la marge basse. */
        @page { margin: 12mm 12mm 22mm 12mm !important; }
        body  { color: {{ $charte['noir'] }}; font-size: 9px; line-height: 1.2; padding-bottom: 4mm; }

        .summary {
            background: {{ $charte['gris_clair'] }}; border-left: 3px solid {{ $charte['rouge'] }};
            padding: 10px 14px; margin-bottom: 12px;
            font-size: 10px; display: table; width: 100%;
        }
        .summary > div { display: table-cell; vertical-align: middle; }
        .summary .total { text-align: right; font-weight: 700; color: {{ $charte['rouge'] }}; font-size: 14px; font-family: {!! $charte['ff_titres'] !!}; }

        table.ch-table th, table.ch-table td { vertical-align: top; }
        table.ch-table thead th { font-size: 8px; letter-spacing: 0.5px; padding: 6px 7px; }
        table.ch-table tbody td { padding: 6px 7px; }

        .ref { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .empty-row { text-align: center; padding: 24px; color: {{ $charte['texte_pale'] }}; }
    </style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun clair (liseré + logo
     CIBLE pour fond clair + titre + méta), remplace le bandeau sombre. --}}
@include('pdf.partials.charte-header', [
    'docTitle' => 'Liste des campagnes',
    'docMeta'  => [
        'Généré le ' . $generated,
        $campaigns->count() . ' campagne(s)',
    ],
])

<div class="summary">
    <div>
        <strong>{{ $campaigns->count() }}</strong> campagne(s) listée(s) ·
        Filtres appliqués depuis l'interface admin.
    </div>
    <div class="total">
        Montant total : {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
    </div>
</div>

<table class="ch-table">
    <thead>
        <tr>
            <th style="width:10%">Référence</th>
            <th style="width:22%">Campagne</th>
            <th style="width:18%">Client</th>
            <th style="width:9%">Statut</th>
            <th style="width:9%">Début</th>
            <th style="width:9%">Fin</th>
            <th style="width:6%" class="num">Pann.</th>
            <th style="width:13%" class="num">Montant</th>
            <th style="width:10%">Créée par</th>
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
                <td>{{ $c->name }}</td>
                <td>{{ $c->client?->name ?? '—' }}</td>
                <td><span class="ch-badge {{ $statusClass }}">{{ $c->status?->label() ?? '—' }}</span></td>
                <td>{{ $c->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $c->end_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="num">{{ $c->panels_count ?? 0 }}</td>
                <td class="num">{{ number_format((float) $c->total_amount, 0, ',', ' ') }}</td>
                <td>{{ $c->user?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty-row">Aucune campagne ne correspond aux filtres.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel",
])

</body>
</html>

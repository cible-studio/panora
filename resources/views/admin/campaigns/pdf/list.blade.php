<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des campagnes — {{ $charte['nom'] }}</title>
    {{-- Liste des campagnes, prête à envoyer à l'administration.

         2026-11-05 — mise en page éditoriale (« ces cases font trop IA »,
         logo mal aligné, date d'édition au pied) : en-tête commun
         pdf.partials.doc-entete, styles pdf.partials.doc-styles, pied commun
         charte-footer. Titre = la sélection en clair
         (CampagneFiltres::description), une ligne de synthèse, tableau à
         filets avec statut en pastille. --}}
    @include('pdf.partials.charte-styles')
    @include('pdf.partials.doc-styles')
    @php
        $ch      = $charte;
        $nb      = $campaigns->count();
        $enCours = $campaigns->filter(fn ($c) => $c->status?->value === 'actif')->count();
        $fcfa    = fn ($n) => number_format((float) $n, 0, ',', "\u{202F}");

        // Pastille de statut (doc-styles : .ch-badge-* → pastille + texte).
        $badge = fn ($c) => match ($c->status?->value) {
            'actif'             => 'ch-badge-vert',
            'planifie', 'pause' => 'ch-badge-jaune',
            'annule'            => 'ch-badge-rouge',
            'pose'              => 'ch-badge-bleu',
            default             => 'ch-badge-gris',
        };

        // Synthèse sous le titre : valeurs échappées, mise en gras.
        $synthese = '<strong>' . $nb . '</strong> campagne' . ($nb > 1 ? 's' : '')
            . ($enCours ? ', dont <strong>' . $enCours . '</strong> en cours' : '')
            . ' · <strong>' . e($fcfa($totalPanneaux)) . '</strong> panneau' . ($totalPanneaux > 1 ? 'x' : '')
            . ' · montant total <strong>' . e($fcfa($totalAmount)) . ' FCFA</strong>';
    @endphp
    <style>
        @page { margin: 14mm 15mm 20mm 15mm !important; }
        body { font-family: {!! $ch['ff_texte'] !!}; font-size: 8.5pt; line-height: 1.35; color: {{ $ch['noir'] }}; }
        .ch-table { font-size: 8.5pt; }
        .ch-table td { vertical-align: top; }
        .ch-table tbody td { padding: 6px 7px; }
        .num  { text-align: right; white-space: nowrap; }
        .date, .statut { white-space: nowrap; }
        .nom  { font-weight: 700; }
        .ref  { font-size: 7pt; line-height: 1.2; margin-top: 1px; color: {{ $ch['texte_pale'] }}; }
        .vide { text-align: center; padding: 18mm 0; color: {{ $ch['texte_pale'] }}; }
    </style>
</head>
<body>

@include('pdf.partials.charte-footer', ['footerHint' => 'Document confidentiel'])

@include('pdf.partials.doc-entete', [
    'docKicker'       => 'Liste des campagnes',
    'docTitle'        => $criteres ? implode(' · ', $criteres) : 'Toutes les campagnes',
    'docSubtitleHtml' => $synthese,
])

<table class="ch-table">
    <thead>
        <tr>
            <th style="width:24%">Campagne</th>
            <th style="width:16%">Client</th>
            <th style="width:15%">Commercial</th>
            <th style="width:9%">Début</th>
            <th style="width:9%">Fin</th>
            <th style="width:12%">Statut</th>
            <th style="width:6%" class="num">Panneaux</th>
            <th style="width:9%" class="num">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($campaigns as $c)
            <tr>
                <td>
                    <span class="nom">{{ $c->name }}</span>
                    <div class="ref">N° {{ $c->id }}</div>
                </td>
                <td>{{ $c->client?->name ?? '—' }}</td>
                {{-- Commercial responsable ; à défaut, le créateur (règle de
                     la Performance commerciale). --}}
                <td>{{ $c->commercial?->name ?? $c->user?->name ?? '—' }}</td>
                <td class="date">{{ $c->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="date">{{ $c->end_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="statut"><span class="ch-badge {{ $badge($c) }}">{{ $c->status?->label() ?? '—' }}</span></td>
                <td class="num">{{ $c->panels_count ?? 0 }}</td>
                <td class="num">{{ $fcfa($c->total_amount) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="vide">Aucune campagne ne correspond à cette sélection.</td></tr>
        @endforelse
        @if($campaigns->isNotEmpty())
        <tr class="ch-total">
            <td colspan="6">Total · {{ $nb }} campagne{{ $nb > 1 ? 's' : '' }}</td>
            <td class="num">{{ $fcfa($totalPanneaux) }}</td>
            <td class="num">{{ $fcfa($totalAmount) }}</td>
        </tr>
        @endif
    </tbody>
</table>

</body>
</html>

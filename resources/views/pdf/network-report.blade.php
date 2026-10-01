<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    {{-- 2026-10-01 — charte graphique : styles communs + en-tête clair commun
         (charte-header) à la place du bandeau foncé, pied commun charte-footer. --}}
    @include('pdf.partials.charte-styles')
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        @page { size: A4; margin: 12mm 12mm 20mm 12mm !important; }
        body { font-size: 11px; color: {{ $charte['noir'] }}; line-height: 1.2; }

        /* Stats globales : 3 cartes KPI côte à côte (table, pas de grid). */
        .stats-row { margin-bottom: 18px; }
        .stats-row .ch-kpi { text-align: center; width: 33.33%; }
        .stat-label { font-size: 9px; color: {{ $charte['texte_doux'] }}; text-transform: uppercase; margin-bottom: 5px; }
        .stat-value { font-family: {!! $charte['ff_titres'] !!}; font-size: 22px; font-weight: 800; color: {{ $charte['noir'] }}; }

        .commune-section { margin-bottom: 16px; }
        .commune-title {
            font-family: {!! $charte['ff_titres'] !!};
            background: {{ $charte['gris_clair'] }};
            color: {{ $charte['noir'] }};
            border-left: 3px solid {{ $charte['rouge'] }};
            padding: 7px 12px;
            font-weight: 700;
            font-size: 12px;
        }

        /* Nunito plus haute que DejaVu : interligne resserré pour garder la densité. */
        .ch-table tbody td { line-height: 1.15; }

        .ref-cell { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; font-weight: 700; }
    </style>
</head>
<body>

    @include('pdf.partials.charte-footer', [
        'footerHint' => 'CIBLE CI — Document confidentiel — Ne pas diffuser · Généré le ' . now()->format('d/m/Y à H:i') . ' · www.cible-ci.com',
    ])

    {{-- HEADER — reprend le contenu de pdf.partials.branding-header
         (régie · opéré par Panora) dans l'en-tête clair commun. --}}
    @include('pdf.partials.charte-header', [
        'docKicker'   => 'Rapport',
        'docTitle'    => 'Rapport Réseau Panneaux — ' . now()->format('d/m/Y'),
        'docSubtitle' => ($operatorName ?? 'CIBLE CI') . ' · opéré par Panora',
    ])

    {{-- STATS GLOBALES --}}
    <table class="ch-kpis stats-row">
        <tr>
            <td class="ch-kpi k-rouge">
                <div class="stat-label">Total panneaux</div>
                <div class="stat-value">{{ $totalPanneaux }}</div>
            </td>
            <td class="ch-kpi k-vert">
                <div class="stat-label">Disponibles</div>
                <div class="stat-value c-vert">{{ $panneauxLibres }}</div>
            </td>
            <td class="ch-kpi k-jaune">
                <div class="stat-label">Taux occupation</div>
                <div class="stat-value">{{ $tauxOccupation }}%</div>
            </td>
        </tr>
    </table>

    {{-- PAR COMMUNE --}}
    @foreach($communes as $commune)
    @if($commune->panels->count() > 0)
    <div class="commune-section">
        <div class="commune-title">
            {{ strtoupper($commune->name) }}
            — {{ $commune->panels->count() }} panneau(x)
        </div>
        <table class="ch-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Format</th>
                    <th>Tarif/mois</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($commune->panels as $panel)
                <tr>
                    <td class="ref-cell">
                        {{ $panel->reference }}
                    </td>
                    <td>{{ $panel->name }}</td>
                    <td>{{ $panel->format->name }}</td>
                    <td style="font-weight:700; white-space:nowrap;">
                        {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                    </td>
                    <td>
                        @if($panel->status->value === 'libre')
                            <span class="ch-badge ch-badge-vert">Libre</span>
                        @else
                            <span class="ch-badge ch-badge-rouge">Occupé</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
    @endforeach

</body>
</html>

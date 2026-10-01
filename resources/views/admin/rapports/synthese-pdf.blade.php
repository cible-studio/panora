<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Synthèse exécutive — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : styles communs, en-tête charte-header,
     pied charte-footer. Le pied était auparavant positionné DANS la zone de
     contenu (bottom:4mm) et chevauchait le tableau en bas de la page 1 ; il
     vit désormais dans la marge basse réservée (20mm). --}}
@include('pdf.partials.charte-styles')
<style>
    @page { size: A4; margin: 12mm 14mm 20mm 14mm !important; }
    body { font-size: 10px; color: {{ $charte['noir'] }}; line-height: 1.25; }
    h2 {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 12px; font-weight: 700; color: {{ $charte['noir'] }};
        margin: 18px 0 8px; padding: 0 0 4px 8px;
        border-left: 3px solid {{ $charte['rouge'] }};
        border-bottom: 1px solid {{ $charte['gris'] }};
    }
    table.ch-table { margin-bottom: 8px; }
    table.ch-table tbody td { font-size: 9.5px; padding: 5px 8px; }
    .r { text-align: right; }
    .b { font-weight: 700; }
    .ch-table thead th.r { text-align: right; }
    .badge-up   { color: {{ $charte['vert'] }}; font-weight: 700; }
    .badge-down { color: {{ $charte['rouge'] }}; font-weight: 700; }
    .badge-flat { color: {{ $charte['texte_doux'] }}; font-weight: 700; }
    .insight { margin: 0 0 5px; font-size: 9.5px; }
</style>
</head>
<body>

@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Dashboard analytique OOH · Document généré automatiquement · Confidentiel',
])

@include('pdf.partials.charte-header', [
    'docKicker'   => 'Rapport',
    'docTitle'    => 'SYNTHÈSE EXÉCUTIVE',
    'docSubtitle' => 'Dashboard analytique OOH — ' . ($operatorName ?? 'CIBLE CI'),
    'docMeta'     => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5(now()), 0, 8)),
    ],
])
{{-- Période retirée du header : elle est désormais portée par le
     bandeau "Type d'export" plus bas (plus complet, avec le preset). --}}

{{-- ════ Récap filtres actifs ════
     Source unique : RapportFilterContextService — cohérent avec
     l'Excel et le PDF Occupation panneaux. --}}
@include('admin.rapports.partials._filter_recap_pdf')

{{-- ════ KPIs principaux ════ --}}
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-bleu" style="width:25%">
            <div class="ch-kpi-label">Taux d'occupation</div>
            <div class="ch-kpi-value">{{ $parc['occupation_rate'] }}%</div>
            <div class="ch-kpi-sub">{{ $parc['occupied'] }} / {{ $parc['total'] }} panneaux</div>
        </td>
        <td class="ch-kpi k-vert" style="width:25%">
            <div class="ch-kpi-label">CA contractuel période</div>
            <div class="ch-kpi-value">{{ number_format($revenue / 1000000, 1, ',', ' ') }} M</div>
            <div class="ch-kpi-sub">FCFA — {{ $stats['total'] }} campagnes</div>
        </td>
        <td class="ch-kpi k-violet" style="width:25%">
            <div class="ch-kpi-label">Clients à risque</div>
            <div class="ch-kpi-value">{{ $inactivity['6_to_12'] + $inactivity['12_plus'] }}</div>
            <div class="ch-kpi-sub">Inactifs > 6 mois</div>
        </td>
        <td class="ch-kpi k-jaune" style="width:25%">
            <div class="ch-kpi-label">Décapages en retard</div>
            <div class="ch-kpi-value">{{ $decapStats['overdue'] }}</div>
            <div class="ch-kpi-sub">Sur {{ $decapStats['total'] }} concernés</div>
        </td>
    </tr>
</table>

{{-- ════ Bloc CA RÉEL (Bloc 4 Commit 14 — 2026-06-18) ════
     Source : CaRealService → cohérent au franc près avec FinancialDashboardService.
     Filtres commune/zone/category ignorés par construction. ──────────────── --}}
@if(!empty($caReel))
<h2>CA réel sur la période</h2>
<div class="ch-note ch-note-jaune" style="font-size:9px;line-height:1.4;margin:0 0 8px">
    <strong>ℹ Note méthodologique :</strong> les chiffres ci-dessous proviennent de la comptabilité (factures émises HT + paiements reçus TTC),
    et non du contractuel campagne (CA contractuel période ci-dessus).
    Pour la même période, ils ne tiennent pas compte des filtres « commune / zone / catégorie panneau » :
    la facturation suit le client, pas le panneau. Pour un CA réel filtré géographiquement, consulter le tableau de bord Finance.
</div>
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-jaune" style="width:33%">
            <div class="ch-kpi-label">CA HT facturé</div>
            <div class="ch-kpi-value">{{ number_format(($caReel['ht_facture'] ?? 0) / 1000000, 1, ',', ' ') }} M</div>
            <div class="ch-kpi-sub">FCFA · factures émises hors annulées</div>
        </td>
        <td class="ch-kpi k-vert" style="width:33%">
            <div class="ch-kpi-label">Encaissé TTC</div>
            <div class="ch-kpi-value">{{ number_format(($caReel['ttc_encaisse'] ?? 0) / 1000000, 1, ',', ' ') }} M</div>
            <div class="ch-kpi-sub">FCFA · paiements reçus</div>
        </td>
        <td class="ch-kpi k-bleu" style="width:33%">
            <div class="ch-kpi-label">Taux de recouvrement</div>
            <div class="ch-kpi-value">{{ number_format($caReel['taux_recouvrement'] ?? 0, 1, ',', ' ') }} %</div>
            <div class="ch-kpi-sub">encaissé / facturé TTC</div>
        </td>
    </tr>
</table>
@endif

{{-- ════ État du parc ════ --}}
<h2>Vue d'ensemble du parc</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th>Indicateur</th>
            <th class="r">Valeur</th>
            <th>Détail</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Total panneaux installés</td><td class="r b">{{ number_format($parc['total']) }}</td><td>Parc actif</td></tr>
        <tr><td>Panneaux occupés</td><td class="r b">{{ number_format($parc['occupied']) }}</td><td>{{ $parc['occupation_rate'] }}% du parc</td></tr>
        <tr><td>Panneaux disponibles</td><td class="r b">{{ number_format($parc['available']) }}</td><td>À commercialiser</td></tr>
        <tr><td>Panneaux en maintenance</td><td class="r b">{{ number_format($parc['maintenance']) }}</td><td>Indisponibles</td></tr>
    </tbody>
</table>

{{-- ════ Campagnes ════ --}}
<h2>Activité commerciale</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th>Statut</th>
            <th class="r">Nombre</th>
            <th>%</th>
        </tr>
    </thead>
    <tbody>
        <tr><td>Total campagnes</td><td class="r b">{{ $stats['total'] }}</td><td>—</td></tr>
        <tr><td>Actives</td><td class="r">{{ $stats['active'] }}</td><td>{{ $stats['total'] > 0 ? round(($stats['active'] / $stats['total']) * 100, 1) : 0 }}%</td></tr>
        <tr><td>Planifiées</td><td class="r">{{ $stats['planned'] }}</td><td>{{ $stats['total'] > 0 ? round(($stats['planned'] / $stats['total']) * 100, 1) : 0 }}%</td></tr>
        <tr><td>Terminées</td><td class="r">{{ $stats['done'] }}</td><td>{{ $stats['total'] > 0 ? round(($stats['done'] / $stats['total']) * 100, 1) : 0 }}%</td></tr>
        <tr><td>Annulées</td><td class="r c-rouge">{{ $stats['cancelled'] }}</td><td class="c-rouge">{{ $stats['cancel_rate'] }}%</td></tr>
    </tbody>
</table>

{{-- ════ Prévisions ════ --}}
<h2>Prévisions sur 3 mois (régression linéaire)</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th>Mois</th>
            <th class="r">CA prévu (FCFA)</th>
            <th class="r">Occupation prévue</th>
            <th>Tendance CA</th>
        </tr>
    </thead>
    <tbody>
        @foreach($forecast['revenue']['forecast'] as $i => $rf)
            @php $occf = $forecast['occupation']['forecast'][$i] ?? null; @endphp
            <tr>
                <td class="b">{{ $rf['label'] }}</td>
                <td class="r b">{{ number_format($rf['value'], 0, ',', ' ') }}</td>
                <td class="r">{{ $occf ? round($occf['value'], 1) . '%' : '—' }}</td>
                <td>
                    @if($forecast['revenue']['trend_direction'] === 'up')
                        <span class="badge-up">{{ $rf['trend'] }} (+{{ abs($forecast['revenue']['trend_pct_per_month']) }}%/mois)</span>
                    @elseif($forecast['revenue']['trend_direction'] === 'down')
                        <span class="badge-down">{{ $rf['trend'] }} ({{ $forecast['revenue']['trend_pct_per_month'] }}%/mois)</span>
                    @else
                        <span class="badge-flat">{{ $rf['trend'] }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
<div class="ch-muted" style="font-size:8.5px;margin-top:4px">
    Confiance du modèle CA : <strong>{{ $forecast['revenue']['confidence'] }}%</strong> (R² = {{ $forecast['revenue']['r_squared'] }}) ·
    Confiance occupation : <strong>{{ $forecast['occupation']['confidence'] }}%</strong> (R² = {{ $forecast['occupation']['r_squared'] }})
    <br>Méthode : régression linéaire des moindres carrés sur l'historique 12 mois. Ne capture pas la saisonnalité — à utiliser comme tendance globale, pas comme valeur exacte.
</div>

{{-- ════ Top 5 clients ════ --}}
<h2>Top 5 clients (CA période)</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Client</th>
            <th class="r">Campagnes</th>
            <th class="r">CA total (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($topClients->take(5) as $i => $c)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="b">{{ $c->name }}</td>
                <td class="r">{{ $c->campaigns_count }}</td>
                <td class="r b c-vert">{{ number_format($c->total_revenue, 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" class="ch-pale" style="text-align:center">Aucun client sur la période</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Insights & alertes ════ --}}
@if($insights->isNotEmpty())
<h2>⚠ Insights & recommandations</h2>
@foreach($insights->take(6) as $insight)
    @php
        // 2026-10-01 — charte graphique : encadrés ch-note-* (sévérité inchangée).
        $cls = match($insight['severity'] ?? 'info') {
            'danger'  => 'insight ch-note ch-note-rouge',
            'warning' => 'insight ch-note ch-note-jaune',
            'success' => 'insight ch-note ch-note-vert',
            default   => 'insight ch-note',
        };
    @endphp
    <div class="{{ $cls }}">
        {{-- $insight['icon'] (emoji décoratif fourni par DashboardKpiService)
             n'est plus affiché : il rendait un carré vide dans le PDF. La
             sévérité reste portée par la couleur de l'encadré. --}}
        <strong>{{ $insight['title'] }}</strong><br>
        <span class="ch-muted">{{ $insight['message'] }}</span>
    </div>
@endforeach
@endif

</body>
</html>

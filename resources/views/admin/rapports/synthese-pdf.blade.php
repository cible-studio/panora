<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Synthèse exécutive — CIBLE CI</title>
<style>
    @page { size: A4; margin: 18mm 14mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 18px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 12px; color: #111827; margin: 18px 0 8px; padding-bottom: 4px; border-bottom: 1.5px solid #e8a020; }
    .header { display: table; width: 100%; margin-bottom: 14px; }
    .header .left { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .period { font-size: 11px; color: #6b7280; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th { background: #f3f4f6; padding: 5px 8px; text-align: left; font-size: 9px; font-weight: bold; color: #374151; border-bottom: 1px solid #d1d5db; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 5px 8px; font-size: 9.5px; border-bottom: 1px solid #f3f4f6; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .kpi-grid { display: table; width: 100%; margin-bottom: 8px; border-collapse: separate; border-spacing: 4px; }
    .kpi-row { display: table-row; }
    .kpi { display: table-cell; padding: 8px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 4px; width: 25%; }
    .kpi-label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.6px; }
    .kpi-value { font-size: 14px; font-weight: bold; color: #111827; margin-top: 2px; }
    .kpi-sub { font-size: 8px; color: #6b7280; margin-top: 1px; }
    .color-occ { border-left-color: #3b82f6; }
    .color-ca  { border-left-color: #16a34a; }
    .color-clt { border-left-color: #a855f7; }
    .color-dec { border-left-color: #f59e0b; }
    .badge-up   { color: #16a34a; font-weight: bold; }
    .badge-down { color: #dc2626; font-weight: bold; }
    .badge-flat { color: #6b7280; font-weight: bold; }
    .footer { position: fixed; bottom: 4mm; left: 14mm; right: 14mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
    .insight { background: #fef3c7; border-left: 3px solid #f59e0b; padding: 6px 10px; margin-bottom: 5px; font-size: 9.5px; }
    .insight-danger { background: #fee2e2; border-left-color: #dc2626; }
    .insight-success { background: #dcfce7; border-left-color: #16a34a; }
    .insight-info { background: #dbeafe; border-left-color: #3b82f6; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        @if(!empty($logoCibleLight))
            <img src="{{ $logoCibleLight }}" alt="CIBLE CI" style="height:34px;margin-bottom:6px;">
        @endif
        <h1>SYNTHÈSE EXÉCUTIVE</h1>
        <div class="period">Dashboard analytique OOH — {{ $operatorName ?? 'CIBLE CI' }}</div>
        <div class="period">Période : {{ $period['from']->format('d/m/Y') }} → {{ $period['to']->format('d/m/Y') }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

{{-- ════ KPIs principaux ════ --}}
<div class="kpi-grid">
    <div class="kpi-row">
        <div class="kpi color-occ">
            <div class="kpi-label">Taux d'occupation</div>
            <div class="kpi-value">{{ $parc['occupation_rate'] }}%</div>
            <div class="kpi-sub">{{ $parc['occupied'] }} / {{ $parc['total'] }} panneaux</div>
        </div>
        <div class="kpi color-ca">
            <div class="kpi-label">CA total période</div>
            <div class="kpi-value">{{ number_format($revenue / 1000000, 1, ',', ' ') }} M</div>
            <div class="kpi-sub">FCFA — {{ $stats['total'] }} campagnes</div>
        </div>
        <div class="kpi color-clt">
            <div class="kpi-label">Clients à risque</div>
            <div class="kpi-value">{{ $inactivity['6_to_12'] + $inactivity['12_plus'] }}</div>
            <div class="kpi-sub">Inactifs > 6 mois</div>
        </div>
        <div class="kpi color-dec">
            <div class="kpi-label">Décappages en retard</div>
            <div class="kpi-value">{{ $decapStats['overdue'] }}</div>
            <div class="kpi-sub">Sur {{ $decapStats['total'] }} concernés</div>
        </div>
    </div>
</div>

{{-- ════ État du parc ════ --}}
<h2>📊 Vue d'ensemble du parc</h2>
<table>
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
<h2>🎯 Activité commerciale</h2>
<table>
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
        <tr><td>Annulées</td><td class="r" style="color:#dc2626">{{ $stats['cancelled'] }}</td><td style="color:#dc2626">{{ $stats['cancel_rate'] }}%</td></tr>
    </tbody>
</table>

{{-- ════ Prévisions ════ --}}
<h2>🔮 Prévisions sur 3 mois (régression linéaire)</h2>
<table>
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
<div style="font-size:8.5px;color:#6b7280;margin-top:4px">
    Confiance du modèle CA : <strong>{{ $forecast['revenue']['confidence'] }}%</strong> (R² = {{ $forecast['revenue']['r_squared'] }}) ·
    Confiance occupation : <strong>{{ $forecast['occupation']['confidence'] }}%</strong> (R² = {{ $forecast['occupation']['r_squared'] }})
    <br>Méthode : régression linéaire des moindres carrés sur l'historique 12 mois. Ne capture pas la saisonnalité — à utiliser comme tendance globale, pas comme valeur exacte.
</div>

{{-- ════ Top 5 clients ════ --}}
<h2>🏆 Top 5 clients (CA période)</h2>
<table>
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
                <td class="r b" style="color:#16a34a">{{ number_format($c->total_revenue, 0, ',', ' ') }}</td>
            </tr>
        @empty
            <tr><td colspan="4" style="text-align:center;color:#9ca3af">Aucun client sur la période</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Insights & alertes ════ --}}
@if($insights->isNotEmpty())
<h2>⚠️ Insights & recommandations</h2>
@foreach($insights->take(6) as $insight)
    @php
        $cls = match($insight['severity'] ?? 'info') {
            'danger'  => 'insight insight-danger',
            'warning' => 'insight',
            'success' => 'insight insight-success',
            default   => 'insight insight-info',
        };
    @endphp
    <div class="{{ $cls }}">
        <strong>{{ $insight['icon'] ?? '' }} {{ $insight['title'] }}</strong><br>
        <span style="color:#374151">{{ $insight['message'] }}</span>
    </div>
@endforeach
@endif

<div class="footer">
    CIBLE CI — Dashboard analytique OOH · Document généré automatiquement · Confidentiel
</div>

</body>
</html>

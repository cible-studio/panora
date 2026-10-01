<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance techniciens — CIBLE CI</title>
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
    /* Médailles du podium : or → jaune, argent → gris, bronze → jaune clair. */
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .b-1 { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .b-2 { background: {{ $charte['gris'] }}; color: {{ $charte['noir'] }}; }
    .b-3 { background: {{ $charte['jaune_clair'] }}; color: {{ $charte['noir'] }}; }
    .ch-kpi { width: 16%; }
    .ch-kpi-value { font-size: 13px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre + méta). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'PERFORMANCE TECHNICIENS',
    'docSubtitle' => 'Classement des techniciens · CIBLE CI',
    'docMeta'     => [
        'Édité le ' . $generatedAt->format('d/m/Y à H:i'),
        'Par ' . ($user->name ?? '—'),
    ],
])

<div class="ch-info">
    <strong>Période :</strong> {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)
</div>

{{-- KPIs globaux équipe --}}
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-violet">
            <div class="ch-kpi-label">Techniciens actifs</div>
            <div class="ch-kpi-value">{{ $globalKpis['nb_techs_actifs'] }}</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="ch-kpi-label">Poses réalisées</div>
            <div class="ch-kpi-value">{{ $globalKpis['nb_poses_realisees'] }}</div>
        </td>
        <td class="ch-kpi k-bleu">
            <div class="ch-kpi-label">Réactivité moyenne</div>
            <div class="ch-kpi-value">{{ \App\Support\HumanDuration::fromMinutes($globalKpis['reactivite_avg_min']) }}</div>
            <div class="ch-kpi-sub">attribution → début</div>
        </td>
        <td class="ch-kpi k-violet">
            <div class="ch-kpi-label">Durée pose moyenne</div>
            <div class="ch-kpi-value">{{ \App\Support\HumanDuration::fromMinutes($globalKpis['duree_pose_avg_min']) }}</div>
        </td>
        <td class="ch-kpi k-jaune">
            <div class="ch-kpi-label">% en retard</div>
            <div class="ch-kpi-value">{{ $globalKpis['taux_poses_en_retard'] }} %</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">% piges rejetées</div>
            <div class="ch-kpi-value">{{ $globalKpis['taux_piges_rejetees'] }} %</div>
        </td>
    </tr>
</table>

{{-- Leaderboard détaillé --}}
<h2>Classement des techniciens</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th class="c" style="width:30px">#</th>
            <th>Technicien</th>
            <th>Équipe</th>
            <th class="r">Poses</th>
            <th class="r">Réalisées</th>
            <th class="r">Planifiées</th>
            <th class="r">En retard</th>
            <th class="r">Réactivité moy.</th>
            <th class="r">Durée pose moy.</th>
            <th class="r">% piges rejetées</th>
            <th class="r">Signalements</th>
        </tr>
    </thead>
    <tbody>
        @forelse($leaderboard as $i => $row)
            @php
                $rank = $i + 1;
                $rankBadge = $rank <= 3 ? 'b-' . $rank : '';
                $k = $row['kpis'];
                // Charte : ≤ 5 % vert · ≤ 15 % jaune (badge, jamais de texte jaune) · sinon rouge.
                $rejetTone = $k['taux_piges_rejetees'] <= 5 ? 'vert' : ($k['taux_piges_rejetees'] <= 15 ? 'jaune' : 'rouge');
            @endphp
            <tr>
                <td class="c">
                    @if($rank <= 3)
                        <span class="badge {{ $rankBadge }}">{{ $rank }}</span>
                    @else
                        {{ $rank }}
                    @endif
                </td>
                <td class="b">{{ $row['user']->name ?? '—' }}</td>
                {{-- 2026-06-19 — Multi-équipe : liste les noms séparés par virgules. --}}
                <td class="muted">{{ $row['user']->poseTeams->pluck('name')->join(', ') ?: '—' }}</td>
                <td class="r">{{ $k['nb_poses_total'] }}</td>
                <td class="r b c-vert">{{ $k['nb_poses_realisees'] }}</td>
                <td class="r muted">{{ $k['nb_poses_planifiees'] }}</td>
                <td class="r {{ $k['nb_poses_en_retard'] > 0 ? 'c-rouge' : 'c-doux' }}">{{ $k['nb_poses_en_retard'] }}</td>
                <td class="r">{{ \App\Support\HumanDuration::fromMinutes($k['reactivite_avg_min']) }}</td>
                <td class="r">{{ \App\Support\HumanDuration::fromMinutes($k['duree_pose_avg_min']) }}</td>
                <td class="r b"><span class="ch-badge ch-badge-{{ $rejetTone }}">{{ $k['taux_piges_rejetees'] }} %</span></td>
                <td class="r muted">{{ $k['nb_signalements'] }}</td>
            </tr>
        @empty
            <tr><td colspan="11" class="c muted" style="padding:20px;font-style:italic">Aucun technicien actif sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- Top par commune --}}
@if($topByCommune->isNotEmpty())
    <h2>Top techniciens par commune</h2>
    <table class="ch-table">
        <thead>
            <tr>
                <th>Commune</th>
                <th>Technicien top</th>
                <th class="r">Poses réalisées</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topByCommune as $row)
                <tr>
                    <td class="b">{{ $row['commune'] ?? '—' }}</td>
                    <td>{{ $row['user_name'] ?? '—' }}</td>
                    <td class="r b">{{ $row['nb_poses'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- Top par campagne --}}
@if($topByCampaign->isNotEmpty())
    <h2>Top techniciens par campagne</h2>
    <table class="ch-table">
        <thead>
            <tr>
                <th>Campagne</th>
                <th>Technicien top</th>
                <th class="r">Poses réalisées</th>
            </tr>
        </thead>
        <tbody>
            @foreach($topByCampaign as $row)
                <tr>
                    <td class="b">{{ $row['campaign'] ?? '—' }}</td>
                    <td>{{ $row['user_name'] ?? '—' }}</td>
                    <td class="r b">{{ $row['nb_poses'] ?? 0 }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Performance techniciens · Édité par Panora le ' . $generatedAt->format('d/m/Y à H:i')
                  . ' · Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
])

</body>
</html>

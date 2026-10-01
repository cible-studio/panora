<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Performance équipes — CIBLE CI</title>
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
    .r { text-align: right; } .c { text-align: center; } .b { font-weight: bold; } .muted { color: {{ $charte['texte_doux'] }}; }
    /* Médailles du podium : or → jaune, argent → gris, bronze → jaune clair. */
    .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .b-1 { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .b-2 { background: {{ $charte['gris'] }}; color: {{ $charte['noir'] }}; }
    .b-3 { background: {{ $charte['jaune_clair'] }}; color: {{ $charte['noir'] }}; }
    .ch-info .scope-note { font-style: italic; color: {{ $charte['texte_doux'] }}; font-size: 8.5px; margin-top: 3px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre + méta). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'PERFORMANCE ÉQUIPES',
    'docSubtitle' => 'Classement des équipes de pose · CIBLE CI',
    'docMeta'     => [
        'Édité le ' . $generatedAt->format('d/m/Y à H:i'),
        'Par ' . ($user->name ?? '—'),
    ],
])

<div class="ch-info">
    <strong>Période :</strong> {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }} ({{ $from->diffInDays($to) + 1 }} jours)
    <div class="scope-note">
        Compte uniquement les poses attribuées à chaque équipe (pose_team_id).
        Les poses solo des membres sont dans le rapport individuel technicien.
    </div>
</div>

<h2>Classement des équipes</h2>
<table class="ch-table">
    <thead>
        <tr>
            <th class="c" style="width:30px">#</th>
            <th>Équipe</th>
            <th>Leader</th>
            <th class="r">Membres</th>
            <th class="r">Poses d'équipe</th>
            <th class="r">Réactivité moy.</th>
            <th class="r">% en retard</th>
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
                $team = $row['team'] ?? null;
                // Charte : ≤ 5 % vert · ≤ 15 % jaune (badge, jamais de texte jaune) · sinon rouge.
                $rejetTone = ($k['taux_piges_rejetees'] ?? 0) <= 5 ? 'vert' : (($k['taux_piges_rejetees'] ?? 0) <= 15 ? 'jaune' : 'rouge');
            @endphp
            <tr>
                <td class="c">
                    @if($rank <= 3)
                        <span class="badge {{ $rankBadge }}">{{ $rank }}</span>
                    @else
                        {{ $rank }}
                    @endif
                </td>
                <td class="b">{{ $team?->name ?? '—' }}</td>
                <td class="muted">{{ $team?->leader?->name ?? '—' }}</td>
                <td class="r">{{ $row['members_count'] ?? 0 }}</td>
                <td class="r b c-vert">{{ $k['nb_poses_realisees'] ?? 0 }}</td>
                <td class="r">{{ \App\Support\HumanDuration::fromMinutes($k['reactivite_avg_min'] ?? null) }}</td>
                <td class="r">{{ ($k['taux_poses_en_retard'] ?? 0) }} %</td>
                <td class="r b"><span class="ch-badge ch-badge-{{ $rejetTone }}">{{ ($k['taux_piges_rejetees'] ?? 0) }} %</span></td>
                <td class="r muted">{{ $k['nb_signalements'] ?? 0 }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="c muted" style="padding:20px;font-style:italic">Aucune équipe active sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Performance équipes · Édité par Panora le ' . $generatedAt->format('d/m/Y à H:i')
                  . ' · Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
])

</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Fiche pose (liste) — {{ $campaign->name }}</title>
{{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
@include('pdf.partials.charte-styles')
<style>
    /* 2026-10-01 — charte graphique : marge basse portée à 20mm pour le pied commun. */
    @page { size: A4 landscape; margin: 12mm 10mm 20mm 10mm !important; }
    body { font-size: 9.5px; color: {{ $charte['noir'] }}; line-height: 1.2; }
    .meta-row { font-size: 11px; color: {{ $charte['texte_doux'] }}; line-height: 1.3; }
    .meta-row strong { color: {{ $charte['noir'] }}; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 8.5px; font-weight: bold; }
    .badge-actif    { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
    .badge-planifie { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .badge-pause    { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .badge-termine  { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }
    .badge-annule   { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }

    .ch-kpis { margin-bottom: 10px; }
    .ch-kpi { width: 33%; padding: 7px 10px; }
    .ch-kpi-value { font-size: 13px; }

    table.ch-table { margin-top: 6px; font-size: 9.5px; }
    table.ch-table thead th { font-size: 8px; padding: 6px 8px; }
    table.ch-table tbody td { padding: 6px 8px; }
    /* 2026-10-01 — pastille en padding (le line-height fixe décalait le chiffre sous DomPDF). */
    .num { display: inline-block; min-width: 9px; padding: 2px 6px; line-height: 1.2; text-align: center;
        background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; border-radius: 9px; font-size: 9px; font-weight: bold; }
    .ref { font-family: {!! $charte['ff_mono'] !!}; font-weight: bold; color: {{ $charte['rouge'] }}; }
    .empty { color: {{ $charte['texte_pale'] }}; font-style: italic; }
    .empty-row { text-align: center; color: {{ $charte['texte_doux'] }}; font-style: italic; padding: 20px; }
</style>
</head>
<body>

{{-- ════ EN-TÊTE ════
     2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre +
     méta) ; client / statut / période restent juste en dessous. --}}
@include('pdf.partials.charte-header', [
    'docTitle'  => $campaign->name . ' — Liste de pose',
    'docKicker' => 'Fiche de pose',
    'docMeta'   => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5($campaign->id . now()), 0, 8)),
    ],
])

<div class="ch-info">
    <div class="meta-row">
        <strong>Client :</strong> {{ $campaign->client?->name ?? '—' }}
        @if($campaign->status)
            @php $st = $campaign->status->value ?? (string) $campaign->status; @endphp
            · <span class="badge badge-{{ $st }}">{{ strtoupper($st) }}</span>
        @endif
        · <strong>Période :</strong>
        {{ $campaign->start_date?->format('d/m/Y') ?? '—' }} → {{ $campaign->end_date?->format('d/m/Y') ?? '—' }}
        @if($campaign->start_date && $campaign->end_date)
            ({{ (int) $campaign->start_date->diffInDays($campaign->end_date) + 1 }} jours)
        @endif
    </div>
</div>

{{-- ════ RÉCAPITULATIF ════ --}}
<table class="ch-kpis">
    <tr>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Total panneaux</div>
            <div class="ch-kpi-value">{{ $panels->count() }}</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Communes</div>
            <div class="ch-kpi-value">{{ $panels->pluck('commune.name')->filter()->unique()->count() }}</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Poses planifiées</div>
            <div class="ch-kpi-value">{{ $panels->filter(fn($p) => isset($poseByPanel[$p->id]))->count() }} / {{ $panels->count() }}</div>
        </td>
    </tr>
</table>

{{-- ════ TABLEAU COMPACT (sans photos, sans dates) ════ --}}
<table class="ch-table">
    <thead>
        <tr>
            <th style="width:32px">#</th>
            <th style="width:14%">Référence</th>
            <th>Emplacement</th>
            <th style="width:14%">Commune</th>
            <th style="width:10%">Format</th>
            <th style="width:18%">Technicien</th>
            <th style="width:14%">Équipe</th>
        </tr>
    </thead>
    <tbody>
        @forelse($panels as $i => $panel)
            @php $pose = $poseByPanel[$panel->id] ?? null; @endphp
            <tr>
                <td><span class="num">{{ $i + 1 }}</span></td>
                <td class="ref">{{ $panel->reference }}</td>
                <td>{{ $panel->name ?? '—' }}</td>
                <td>{{ $panel->commune?->name ?? '—' }}</td>
                <td>{{ $panel->format?->name ?? '—' }}</td>
                {{-- 2026-08-10 option A : si pose_team_id renseigné,
                     la col "Technicien" affiche "Équipe X" (mérite collectif).
                     Cf. refonte KPI équipe/solo. --}}
                <td>
                    @if($pose?->pose_team_id)
                        Équipe {{ $pose->poseTeam?->name ?? $pose->team_name }}
                    @else
                        {{ $pose?->technicien?->name ?? '—' }}
                    @endif
                </td>
                <td>{{ $pose?->poseTeam?->name ?? $pose?->team_name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty-row">
                Aucun panneau associé à cette campagne.
            </td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE SARL — Régie OOH Côte d'Ivoire · Liste de pose campagne · Document interne.",
])

</body>
</html>

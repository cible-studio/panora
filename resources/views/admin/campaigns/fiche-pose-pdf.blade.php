<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Fiche de pose — {{ $campaign->name }}</title>
{{-- 2026-10-01 — charte graphique : styles communs (polices, ch-*) puis styles propres. --}}
@include('pdf.partials.charte-styles')
<style>
    /* 2026-10-01 — charte graphique : marge basse portée à 18mm pour le pied commun. */
    @page { size: A4; margin: 12mm 12mm 18mm 12mm !important; }
    body { font-size: 10px; color: {{ $charte['noir'] }}; line-height: 1.25; }
    .meta-row { font-size: 11px; color: {{ $charte['texte_doux'] }}; line-height: 1.35; }
    .meta-row strong { color: {{ $charte['noir'] }}; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
    .badge-actif    { background: {{ $charte['vert'] }};  color: {{ $charte['blanc'] }}; }
    .badge-planifie { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .badge-pause    { background: {{ $charte['jaune'] }}; color: {{ $charte['noir'] }}; }
    .badge-termine  { background: {{ $charte['gris'] }};  color: {{ $charte['noir'] }}; }
    .badge-annule   { background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; }

    /* Densité : la fiche de 6 panneaux doit tenir sur une page comme avant. */
    .ch-head { margin-bottom: 6px; }
    .ch-head-rule { margin-bottom: 10px; }
    .ch-kpis { margin-bottom: 8px; }
    .ch-kpi { width: 25%; padding: 6px 10px; }
    .ch-kpi-value { font-size: 13px; }

    h2 {
        font-family: {!! $charte['ff_titres'] !!};
        font-size: 12px; font-weight: 700; color: {{ $charte['noir'] }};
        margin: 12px 0 7px; padding: 0 0 4px 8px;
        border-left: 3px solid {{ $charte['rouge'] }};
        border-bottom: 1px solid {{ $charte['gris'] }};
    }

    .panel-card {
        display: table; width: 100%; margin-bottom: 7px;
        border: 1px solid {{ $charte['gris'] }}; border-radius: 6px; padding: 6px 8px;
        page-break-inside: avoid;
    }
    .panel-photo {
        display: table-cell; vertical-align: top; width: 110px; padding-right: 10px;
    }
    .panel-photo img { width: 100px; height: 75px; object-fit: cover; border-radius: 4px; border: 1px solid {{ $charte['gris'] }}; }
    .panel-placeholder {
        width: 100px; height: 75px; background: {{ $charte['gris_clair'] }};
        border-radius: 4px; border: 1px dashed {{ $charte['gris'] }};
        text-align: center; line-height: 75px; color: {{ $charte['texte_pale'] }}; font-size: 8.5px;
    }
    .panel-info { display: table-cell; vertical-align: top; }
    .panel-ref { font-family: {!! $charte['ff_mono'] !!}; font-weight: bold; color: {{ $charte['rouge'] }}; font-size: 11px; }
    .panel-name { font-family: {!! $charte['ff_titres'] !!}; font-size: 11.5px; font-weight: bold; color: {{ $charte['noir'] }}; margin-top: 1px; }
    .panel-meta { font-size: 9.5px; color: {{ $charte['texte_doux'] }}; margin-top: 3px; line-height: 1.3; }
    .panel-meta strong { color: {{ $charte['noir'] }}; }
    .pose-info {
        margin-top: 4px; padding: 4px 8px; background: {{ $charte['vert_clair'] }};
        border-left: 2px solid {{ $charte['vert'] }}; border-radius: 3px; font-size: 9.5px; color: {{ $charte['noir'] }};
    }
    .pose-info.late { background: {{ $charte['rouge_clair'] }}; border-left-color: {{ $charte['rouge'] }}; color: {{ $charte['noir'] }}; }
    .pose-info.none { background: {{ $charte['gris_clair'] }}; border-left-color: {{ $charte['texte_pale'] }}; color: {{ $charte['texte_doux'] }}; }
    .pose-info strong { color: inherit; }

    /* 2026-10-01 — pastille en padding (le line-height fixe décalait le chiffre sous DomPDF). */
    .num { display: inline-block; min-width: 9px; padding: 2px 6px; line-height: 1.2; text-align: center;
        background: {{ $charte['rouge'] }}; color: {{ $charte['blanc'] }}; border-radius: 9px; font-size: 10px; font-weight: bold; margin-right: 6px; }

    .empty-msg { padding: 20px; text-align: center; color: {{ $charte['texte_doux'] }}; font-style: italic; }
</style>
</head>
<body>

{{-- ════ EN-TÊTE ════
     2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre +
     méta) ; client / statut / période restent juste en dessous. --}}
@include('pdf.partials.charte-header', [
    'docTitle'  => $campaign->name,
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
            @php
                $st = $campaign->status->value ?? (string) $campaign->status;
            @endphp
            · <span class="badge badge-{{ $st }}">{{ strtoupper($st) }}</span>
        @endif
    </div>
    <div class="meta-row">
        <strong>Période campagne :</strong>
        {{ $campaign->start_date?->format('d/m/Y') ?? '—' }} → {{ $campaign->end_date?->format('d/m/Y') ?? '—' }}
        @if($campaign->start_date && $campaign->end_date)
            ({{ (int) $campaign->start_date->diffInDays($campaign->end_date) + 1 }} jours)
        @endif
    </div>
</div>

{{-- ════ RÉCAPITULATIF CAMPAGNE ════ --}}
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
            <div class="ch-kpi-value">{{ $panels->filter(fn($p) => isset($poseByPanel[$p->id]))->count() }}</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="ch-kpi-label">Sans pose</div>
            <div class="ch-kpi-value">{{ $panels->filter(fn($p) => !isset($poseByPanel[$p->id]))->count() }}</div>
        </td>
    </tr>
</table>

{{-- ════ LISTE PANNEAUX AVEC PHOTOS ════ --}}
<h2>Panneaux à poser ({{ $panels->count() }})</h2>

@forelse($panels as $i => $panel)
    @php
        $photo = $panel->photos->first();
        $photoPath = $photo ? public_path('storage/' . $photo->path) : null;
        $hasPhoto  = $photo && file_exists($photoPath);
        $pose      = $poseByPanel[$panel->id] ?? null;
    @endphp
    <div class="panel-card">
        <div class="panel-photo">
            @if($hasPhoto)
                <img src="{{ $photoPath }}" alt="Panneau {{ $panel->reference }}">
            @else
                <div class="panel-placeholder">Pas de photo</div>
            @endif
        </div>
        <div class="panel-info">
            <div>
                <span class="num">{{ $i + 1 }}</span>
                <span class="panel-ref">{{ $panel->reference }}</span>
            </div>
            <div class="panel-name">{{ $panel->name ?? '—' }}</div>
            <div class="panel-meta">
                <strong>Commune :</strong> {{ $panel->commune?->name ?? '—' }}
                @if($panel->format)
                    · <strong>Format :</strong> {{ $panel->format->name }}
                @endif
                @if($panel->city)
                    · <strong>Ville :</strong> {{ $panel->city }}
                @endif
            </div>

            {{-- Bloc pose : technicien / équipe assigné(s)
                 2026-06-25 — Date de pose retirée (demande user). On garde
                 uniquement l'assignation pour identifier qui fait quoi.
                 2026-08-10 (option A validée user) — si pose_team_id renseigné,
                 c'est une pose CRÉDITÉE À L'ÉQUIPE (mérite collectif) :
                 on affiche "Équipe X" comme porteur principal, sans le nom
                 du tech (même s'il est renseigné en interne). --}}
            @if($pose && $pose->pose_team_id)
                <div class="pose-info">
                    <strong>Prise par : Équipe {{ $pose->poseTeam?->name ?? $pose->team_name ?? '—' }}</strong>
                </div>
            @elseif($pose && ($pose->technicien || $pose->team_name))
                <div class="pose-info">
                    @if($pose->technicien)
                        <strong>Technicien :</strong> {{ $pose->technicien->name }}
                    @endif
                    @if($pose->team_name)
                        @if($pose->technicien) · @endif
                        <strong>Équipe :</strong> {{ $pose->team_name }}
                    @endif
                </div>
            @elseif(!$pose)
                <div class="pose-info none">
                    <strong>⚠ Aucune pose planifiée</strong> pour ce panneau.
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="empty-msg">
        Aucun panneau associé à cette campagne.
    </div>
@endforelse

{{-- 2026-10-01 — charte graphique : pied commun ; l'ancien texte de pied est conservé en footerHint. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => "CIBLE SARL — Régie OOH Côte d'Ivoire · Fiche de pose campagne · Document interne.",
])

</body>
</html>

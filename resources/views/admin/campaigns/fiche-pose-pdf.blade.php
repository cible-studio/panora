<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Fiche de pose — {{ $campaign->name }}</title>
<style>
    @page { size: A4; margin: 14mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 20px; color: #e8a020; margin: 0 0 4px; letter-spacing: -.3px; }
    .header { display: table; width: 100%; margin-bottom: 14px; padding-bottom: 10px; border-bottom: 2px solid #e8a020; }
    .header .left  { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .meta-row { font-size: 11.5px; color: #374151; margin-top: 4px; line-height: 1.55; }
    .meta-row strong { color: #111827; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; }
    .badge-actif    { background: rgba(34,197,94,.18);  color: #15803d; }
    .badge-planifie { background: rgba(59,130,246,.18); color: #1d4ed8; }
    .badge-termine  { background: rgba(107,114,128,.18); color: #374151; }
    .badge-annule   { background: rgba(239,68,68,.18);  color: #b91c1c; }

    .summary {
        display: table; width: 100%; margin-bottom: 14px;
        border-collapse: separate; border-spacing: 4px;
    }
    .summary .cell {
        display: table-cell; padding: 8px 10px; background: #fafafa;
        border-left: 3px solid #e8a020; border-radius: 4px; width: 25%;
    }
    .summary .label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .summary .value { font-size: 13px; font-weight: bold; color: #111827; margin-top: 2px; }

    h2 { font-size: 12px; color: #111827; margin: 16px 0 8px; padding-bottom: 4px; border-bottom: 1.5px solid #e8a020; }

    .panel-card {
        display: table; width: 100%; margin-bottom: 10px;
        border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;
        page-break-inside: avoid;
    }
    .panel-photo {
        display: table-cell; vertical-align: top; width: 110px; padding-right: 10px;
    }
    .panel-photo img { width: 100px; height: 75px; object-fit: cover; border-radius: 4px; border: 1px solid #e5e7eb; }
    .panel-placeholder {
        width: 100px; height: 75px; background: #f3f4f6;
        border-radius: 4px; border: 1px dashed #d1d5db;
        text-align: center; line-height: 75px; color: #9ca3af; font-size: 8.5px;
    }
    .panel-info { display: table-cell; vertical-align: top; }
    .panel-ref { font-family: 'Courier New', monospace; font-weight: bold; color: #b45309; font-size: 11px; }
    .panel-name { font-size: 11.5px; font-weight: bold; color: #111827; margin-top: 1px; }
    .panel-meta { font-size: 9.5px; color: #6b7280; margin-top: 3px; line-height: 1.5; }
    .panel-meta strong { color: #374151; }
    .pose-info {
        margin-top: 4px; padding: 4px 8px; background: #ecfdf5;
        border-left: 2px solid #16a34a; border-radius: 3px; font-size: 9.5px; color: #15803d;
    }
    .pose-info.late { background: #fef2f2; border-left-color: #dc2626; color: #b91c1c; }
    .pose-info.none { background: #f3f4f6; border-left-color: #9ca3af; color: #4b5563; }
    .pose-info strong { color: inherit; }

    .num { display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center;
        background: #e8a020; color: #fff; border-radius: 50%; font-size: 10px; font-weight: bold; margin-right: 6px; }

    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
</head>
<body>

{{-- ════ EN-TÊTE ════ --}}
<div class="header">
    <div class="left">
        <h1>{{ $campaign->name }}</h1>
        <div class="meta-row">
            <strong>👤 Client :</strong> {{ $campaign->client?->name ?? '—' }}
            @if($campaign->status)
                @php
                    $st = $campaign->status->value ?? (string) $campaign->status;
                @endphp
                · <span class="badge badge-{{ $st }}">{{ strtoupper($st) }}</span>
            @endif
        </div>
        <div class="meta-row">
            <strong>📅 Période campagne :</strong>
            {{ $campaign->start_date?->format('d/m/Y') ?? '—' }} → {{ $campaign->end_date?->format('d/m/Y') ?? '—' }}
            @if($campaign->start_date && $campaign->end_date)
                ({{ (int) $campaign->start_date->diffInDays($campaign->end_date) + 1 }} jours)
            @endif
        </div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5($campaign->id . now()), 0, 8)) }}
    </div>
</div>

{{-- ════ RÉCAPITULATIF CAMPAGNE ════ --}}
<div class="summary">
    <div class="cell">
        <div class="label">Total panneaux</div>
        <div class="value">{{ $panels->count() }}</div>
    </div>
    <div class="cell">
        <div class="label">Communes</div>
        <div class="value">{{ $panels->pluck('commune.name')->filter()->unique()->count() }}</div>
    </div>
    <div class="cell">
        <div class="label">Poses planifiées</div>
        <div class="value">{{ $panels->filter(fn($p) => isset($poseByPanel[$p->id]))->count() }}</div>
    </div>
    <div class="cell">
        <div class="label">Sans pose</div>
        <div class="value">{{ $panels->filter(fn($p) => !isset($poseByPanel[$p->id]))->count() }}</div>
    </div>
</div>

{{-- ════ LISTE PANNEAUX AVEC PHOTOS ════ --}}
<h2>📋 Panneaux à poser ({{ $panels->count() }})</h2>

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
                <strong>📍 Commune :</strong> {{ $panel->commune?->name ?? '—' }}
                @if($panel->format)
                    · <strong>Format :</strong> {{ $panel->format->name }}
                @endif
                @if($panel->city)
                    · <strong>Ville :</strong> {{ $panel->city }}
                @endif
            </div>

            {{-- Bloc pose : date planifiée + équipe/technicien --}}
            @if($pose)
                @php
                    $isLate = $pose->scheduled_at && $pose->scheduled_at->isPast() && in_array($pose->status, ['planifiee', 'en_cours']);
                @endphp
                <div class="pose-info {{ $isLate ? 'late' : '' }}">
                    <strong>🗓 Pose prévue :</strong>
                    @if($pose->scheduled_at)
                        {{ $pose->scheduled_at->translatedFormat('l d F Y à H\hi') }}
                        @if($isLate) <strong>· EN RETARD</strong> @endif
                    @else
                        — non datée
                    @endif
                    @if($pose->technicien)
                        <br><strong>👷 Technicien :</strong> {{ $pose->technicien->name }}
                    @endif
                    @if($pose->team_name)
                        @if(!$pose->technicien) <br> @else · @endif
                        <strong>Équipe :</strong> {{ $pose->team_name }}
                    @endif
                </div>
            @else
                <div class="pose-info none">
                    <strong>⚠ Aucune pose planifiée</strong> pour ce panneau.
                </div>
            @endif
        </div>
    </div>
@empty
    <div style="padding:20px;text-align:center;color:#6b7280;font-style:italic">
        Aucun panneau associé à cette campagne.
    </div>
@endforelse

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Fiche de pose campagne · Document interne.
</div>

</body>
</html>

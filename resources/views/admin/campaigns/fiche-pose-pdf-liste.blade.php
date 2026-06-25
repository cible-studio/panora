<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Fiche pose (liste) — {{ $campaign->name }}</title>
<style>
    @page { size: A4 landscape; margin: 12mm 10mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2937; line-height: 1.4; }
    h1 { font-size: 18px; color: #e8a020; margin: 0 0 4px; letter-spacing: -.3px; }
    .header { display: table; width: 100%; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid #e8a020; }
    .header .left  { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 8.5px; color: #6b7280; }
    .meta-row { font-size: 11px; color: #374151; margin-top: 3px; line-height: 1.5; }
    .meta-row strong { color: #111827; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 8.5px; font-weight: bold; }
    .badge-actif    { background: rgba(34,197,94,.18);  color: #15803d; }
    .badge-planifie { background: rgba(59,130,246,.18); color: #1d4ed8; }
    .badge-termine  { background: rgba(107,114,128,.18); color: #374151; }
    .badge-annule   { background: rgba(239,68,68,.18);  color: #b91c1c; }

    .summary {
        display: table; width: 100%; margin-bottom: 10px;
        border-collapse: separate; border-spacing: 4px;
    }
    .summary .cell {
        display: table-cell; padding: 7px 10px; background: #fafafa;
        border-left: 3px solid #e8a020; border-radius: 4px; width: 33%;
    }
    .summary .label { font-size: 8px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .summary .value { font-size: 13px; font-weight: bold; color: #111827; margin-top: 2px; }

    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th { background: #0a0c10; padding: 6px 8px; text-align: left; font-size: 8px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 6px 8px; font-size: 9.5px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
    tr:nth-child(even) td { background: #fafafa; }
    .num { display: inline-block; width: 22px; height: 22px; line-height: 22px; text-align: center;
        background: #e8a020; color: #fff; border-radius: 50%; font-size: 9px; font-weight: bold; }
    .ref { font-family: 'Courier New', monospace; font-weight: bold; color: #b45309; }
    .empty { color: #9ca3af; font-style: italic; }
    .footer { position: fixed; bottom: 4mm; left: 10mm; right: 10mm; font-size: 8px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
</head>
<body>

{{-- ════ EN-TÊTE ════ --}}
<div class="header">
    <div class="left">
        <h1>{{ $campaign->name }} — Liste de pose</h1>
        <div class="meta-row">
            <strong>👤 Client :</strong> {{ $campaign->client?->name ?? '—' }}
            @if($campaign->status)
                @php $st = $campaign->status->value ?? (string) $campaign->status; @endphp
                · <span class="badge badge-{{ $st }}">{{ strtoupper($st) }}</span>
            @endif
            · <strong>📅 Période :</strong>
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

{{-- ════ RÉCAPITULATIF ════ --}}
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
        <div class="value">{{ $panels->filter(fn($p) => isset($poseByPanel[$p->id]))->count() }} / {{ $panels->count() }}</div>
    </div>
</div>

{{-- ════ TABLEAU COMPACT (sans photos, sans dates) ════ --}}
<table>
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
                <td>{{ $pose?->technicien?->name ?? '—' }}</td>
                <td>{{ $pose?->team_name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="text-align:center;color:#6b7280;font-style:italic;padding:20px">
                Aucun panneau associé à cette campagne.
            </td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Liste de pose campagne · Document interne.
</div>

</body>
</html>

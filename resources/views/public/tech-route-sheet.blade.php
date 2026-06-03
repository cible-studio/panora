<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feuille de route — {{ $tech->name }} · Panora</title>

    {{-- Favicon Panora (aligné sur le layout admin pour cohérence onglet) --}}
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body {
            margin: 0; padding: 16mm 12mm;
            font-family: 'Segoe UI', -apple-system, sans-serif;
            color: #111827; font-size: 11pt; line-height: 1.4;
            background: #fff;
        }
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            .commune-block { break-inside: avoid; }
            .pose-row { break-inside: avoid; }
        }
        .header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 12mm; padding-bottom: 6mm;
            border-bottom: 2px solid #e8a020;
            gap: 14mm;
        }
        .header-l { display: flex; align-items: center; gap: 8mm; flex: 1; min-width: 0; }
        .header-l .brand-mark {
            flex: 0 0 auto;
            height: 16mm; width: auto; display: block;
            object-fit: contain;
        }
        .header-l h1 {
            margin: 0; font-size: 18pt; color: #c2570d;
            letter-spacing: -.3px;
        }
        .header-l .sub {
            margin-top: 3px; font-size: 10pt; color: #6b7280;
        }
        .header-r {
            text-align: right; font-size: 9.5pt; color: #6b7280;
            flex: 0 0 auto;
        }
        .header-r strong { color: #111827; font-size: 11pt; }

        .summary {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 8mm;
            margin-bottom: 8mm; padding: 4mm 5mm;
            background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px;
        }
        .summary-item .lbl {
            font-size: 8pt; color: #9a3412; text-transform: uppercase;
            letter-spacing: .5px; font-weight: 700;
        }
        .summary-item .val {
            font-size: 16pt; font-weight: 800; color: #111827;
            line-height: 1;
        }
        .summary-item .sub {
            font-size: 8pt; color: #6b7280;
        }

        .commune-block { margin-bottom: 9mm; }
        .commune-head {
            display: flex; justify-content: space-between; align-items: baseline;
            margin-bottom: 3mm; padding-bottom: 2mm;
            border-bottom: 1.5px solid #c2570d;
        }
        .commune-head h2 {
            margin: 0; font-size: 13pt; color: #c2570d; font-weight: 800;
        }
        .commune-head .count {
            font-size: 9.5pt; color: #6b7280; font-weight: 600;
        }

        table.poses {
            width: 100%; border-collapse: collapse; font-size: 9.5pt;
        }
        table.poses th {
            background: #f9fafb; text-align: left;
            padding: 3mm 2mm; font-size: 8.5pt; font-weight: 700;
            color: #374151; text-transform: uppercase; letter-spacing: .3px;
            border-bottom: 1.5px solid #e5e7eb;
        }
        table.poses td {
            padding: 3mm 2mm; vertical-align: top;
            border-bottom: 1px solid #f1f5f9;
        }
        table.poses tr.late td { background: rgba(239,68,68,.04); }
        table.poses tr.late td:first-child { border-left: 2px solid #ef4444; }
        .ref {
            font-family: ui-monospace, 'SF Mono', monospace;
            font-weight: 700; color: #c2570d;
        }
        .badge {
            display: inline-block; padding: 1px 6px; border-radius: 8px;
            font-size: 8pt; font-weight: 700;
        }
        .badge-late { background: rgba(239,68,68,.10); color: #b91c1c; }
        .badge-today { background: rgba(59,130,246,.10); color: #1d4ed8; }
        .badge-status { background: #e5e7eb; color: #374151; }
        .badge-status[data-st="planifiee"] { background: rgba(232,160,32,.15); color: #b45309; }
        .badge-status[data-st="en_route"]  { background: rgba(139,92,246,.15); color: #6d28d9; }
        .badge-status[data-st="en_cours"]  { background: rgba(59,130,246,.15); color: #1d4ed8; }

        .gps-mono {
            font-family: ui-monospace, 'SF Mono', monospace;
            font-size: 8.5pt; color: #6b7280;
        }

        .checkbox {
            display: inline-block; width: 4mm; height: 4mm;
            border: 1.5px solid #6b7280; border-radius: 2px;
            vertical-align: middle; margin-right: 3px;
        }

        .footer {
            margin-top: 8mm; padding-top: 4mm;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt; color: #9ca3af; text-align: center;
        }

        .print-bar {
            position: sticky; top: 0; z-index: 10;
            display: flex; gap: 8px; align-items: center; justify-content: space-between;
            padding: 12px 16px; margin: -16mm -12mm 16mm; background: #fff7ed;
            border-bottom: 1px solid #fed7aa;
        }
        .print-bar a, .print-bar button {
            padding: 8px 14px; border-radius: 8px; border: 1px solid #c2570d;
            background: #fff; color: #c2570d; font-weight: 700;
            text-decoration: none; font-size: 13px; cursor: pointer;
            font-family: inherit;
        }
        .print-bar .btn-print { background: #c2570d; color: #fff; }
        .print-bar .meta { font-size: 12px; color: #92400e; }
    </style>
</head>
<body>

<div class="print-bar no-print">
    <div class="meta">
        <strong>{{ $tech->name }}</strong> · feuille de route du {{ now()->format('d/m/Y') }} · {{ $total }} pose{{ $total > 1 ? 's' : '' }}
    </div>
    <div style="display:flex;gap:8px">
        <a href="{{ route('tech.space', $token) }}">← Retour à l'espace tech</a>
        <button type="button" class="btn-print" onclick="window.print()">🖨 Imprimer / PDF</button>
    </div>
</div>

<div class="header">
    <div class="header-l">
        <img src="{{ asset('images/panora.png') }}" alt="Panora by CIBLE" class="brand-mark">
        <div>
            <h1>Feuille de route — {{ $tech->name }}</h1>
            <div class="sub">Toutes les poses actives, groupées par commune · à jour le {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="header-r">
        <div><strong>Panora</strong> · CIBLE CI</div>
        <div>Document à conserver pendant la tournée</div>
    </div>
</div>

@if($total === 0)
    <div style="padding:30mm 0;text-align:center;color:#9ca3af;font-size:13pt">
        🎉 Aucune pose en attente — tu es à jour.
    </div>
@else
    <div class="summary">
        <div class="summary-item">
            <div class="lbl">Poses à faire</div>
            <div class="val">{{ $total }}</div>
            <div class="sub">non terminées</div>
        </div>
        <div class="summary-item">
            <div class="lbl">Zones à couvrir</div>
            <div class="val">{{ $groupedByCommune->count() }}</div>
            <div class="sub">communes distinctes</div>
        </div>
        <div class="summary-item">
            <div class="lbl">En retard</div>
            <div class="val" style="color:#b91c1c">
                {{ $groupedByCommune->flatten(1)->filter(fn($t) => $isLate($t))->count() }}
            </div>
            <div class="sub">échéance dépassée</div>
        </div>
        <div class="summary-item">
            <div class="lbl">Date du jour</div>
            <div class="val">{{ now()->format('d/m') }}</div>
            <div class="sub">{{ now()->locale('fr')->isoFormat('dddd') }}</div>
        </div>
    </div>

    @foreach($groupedByCommune as $communeName => $tasks)
        @php
            $countLate = $tasks->filter(fn($t) => $isLate($t))->count();
        @endphp
        <div class="commune-block">
            <div class="commune-head">
                <h2>📍 {{ $communeName }}</h2>
                <div class="count">
                    {{ $tasks->count() }} pose{{ $tasks->count() > 1 ? 's' : '' }}
                    @if($countLate > 0)
                        · <span style="color:#b91c1c;font-weight:700">{{ $countLate }} en retard</span>
                    @endif
                </div>
            </div>
            <table class="poses">
                <thead>
                    <tr>
                        <th style="width:5%">✓</th>
                        <th style="width:13%">Référence</th>
                        <th style="width:24%">Panneau</th>
                        <th style="width:24%">Adresse / Quartier</th>
                        <th style="width:14%">GPS</th>
                        <th style="width:10%">Format</th>
                        <th style="width:10%">Statut</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($tasks as $task)
                    @php
                        $late = $isLate($task);
                        $statusValue = $task->status instanceof \App\Enums\PoseTaskStatus
                            ? $task->status->value
                            : (string) $task->status;
                        $statusLabel = $task->status instanceof \App\Enums\PoseTaskStatus
                            ? $task->status->label()
                            : ucfirst($statusValue);
                        $lat = $task->panel?->latitude;
                        $lng = $task->panel?->longitude;
                    @endphp
                    <tr class="pose-row {{ $late ? 'late' : '' }}">
                        <td><span class="checkbox"></span></td>
                        <td>
                            <div class="ref">{{ $task->panel?->reference ?? '—' }}</div>
                            @if($task->scheduled_at)
                                <div style="font-size:8pt;color:#6b7280;margin-top:1px">
                                    {{ \Carbon\Carbon::parse($task->scheduled_at)->format('d/m H:i') }}
                                </div>
                            @endif
                            @if($late)
                                <div style="margin-top:2px"><span class="badge badge-late">⏰ Retard</span></div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight:600">{{ $task->panel?->name ?? '—' }}</div>
                            @if($task->campaign)
                                <div style="font-size:8.5pt;color:#6b7280;margin-top:1px">
                                    📢 {{ \Illuminate\Support\Str::limit($task->campaign->name, 40) }}
                                    @if($task->campaign->client)
                                        · {{ \Illuminate\Support\Str::limit($task->campaign->client->name, 25) }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $task->panel?->adresse ?: '—' }}</div>
                            @if($task->panel?->quartier)
                                <div style="font-size:8.5pt;color:#6b7280;margin-top:1px">{{ $task->panel->quartier }}</div>
                            @endif
                        </td>
                        <td class="gps-mono">
                            @if($lat && $lng)
                                {{ number_format((float) $lat, 5, '.', '') }}<br>
                                {{ number_format((float) $lng, 5, '.', '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $task->panel?->format?->name ?? '—' }}</td>
                        <td>
                            <span class="badge badge-status" data-st="{{ $statusValue }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
@endif

<div class="footer">
    Panora · CIBLE CI · Feuille générée le {{ now()->format('d/m/Y à H:i') }} pour {{ $tech->name }}
</div>

<script>
    // Si on arrive depuis le bouton "Imprimer la feuille de route", ouvre
    // le dialog d'impression immédiatement (UX bonus).
    if (location.hash === '#print') {
        setTimeout(() => window.print(), 400);
    }
</script>

</body>
</html>

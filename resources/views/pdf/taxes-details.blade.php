<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail Taxes — {{ $periodLabel }}</title>
    <style>
        /* FIX 2026-07-30 — Marges de page A4 paysage revues pour aération.
           Avant : 10mm + paddings internes non uniformes (header 18px,
           kpi-grid 0 18px, table 0) → aspect "collé aux bords". Maintenant :
           marges 14/16mm + tous les blocs héritent de la même largeur imprimable,
           plus aucun padding latéral différencié. */
        @page { size: A4 landscape; margin: 14mm 14mm 18mm 14mm; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', sans-serif; font-size:9px; color:#1a1a2e; }

        .header {
            background:#0a0c10; color:white;
            padding:16px 18px;
            margin-bottom:14px;
            border-bottom:3px solid #e8a020;
            border-radius:4px;
        }
        .logo { font-size:19px; font-weight:800; color:#e8a020; letter-spacing:.2px; }
        .logo-sub { font-size:9.5px; color:#c9cdd6; margin-top:3px; letter-spacing:.3px; }
        .header-issued { font-size:8.5px; color:#8a90a2; margin-top:2px; }
        .header-period {
            font-family:'DejaVu Serif', serif;
            font-size:11.5px; font-weight:700; color:#f5f5f7;
            letter-spacing:.3px;
        }

        /* Fiche synthétique — style « en-tête administratif ».
           Deux colonnes label / valeur alignées, feuillet blanc bordé
           d'un liseré doré pour lier au header. */
        .synth {
            display:table; width:100%;
            margin-bottom:16px; padding:12px 18px;
            background:#fbfbfd; border:1px solid #e5e7eb;
            border-left:3px solid #e8a020; border-radius:4px;
        }
        .synth-row { display:table-row; }
        .synth-lbl, .synth-val {
            display:table-cell; padding:3px 8px 3px 0;
            font-size:9.5px; vertical-align:top;
        }
        .synth-lbl {
            width:110px;
            font-weight:700; color:#6b7280;
            text-transform:uppercase; letter-spacing:.3px;
            font-size:8.5px;
        }
        .synth-val {
            font-weight:600; color:#0a0c10;
        }

        table { width:100%; border-collapse:collapse; font-size:8.5px; }
        thead th {
            background:#0a0c10; color:#e8a020;
            padding:8px 8px; text-align:left;
            font-size:8px; text-transform:uppercase; letter-spacing:.3px;
        }
        thead th:first-child { border-top-left-radius:4px; }
        thead th:last-child  { border-top-right-radius:4px; }
        tbody td { padding:7px 8px; border-bottom:1px solid #e5e7eb; vertical-align:top; }
        tbody tr:nth-child(even) td { background:#fafbfc; }
        .right { text-align:right; }
        .mono { font-family:'DejaVu Sans Mono', monospace; }

        .badge {
            display:inline-block; padding:2px 7px;
            border-radius:8px; font-size:8px; font-weight:700;
        }
        .badge-tm  { background:#dcfce7; color:#16a34a; }
        .badge-odp { background:#fed7aa; color:#ea580c; }
        .badge-db  { background:#dbeafe; color:#2563eb; }

        .total-row td {
            background:#0a0c10 !important;
            color:#e8a020; font-weight:800; padding:11px 8px;
            font-size:10.5px;
            border-bottom:none;
        }
        .total-row td:first-child { border-bottom-left-radius:4px; }
        .total-row td:last-child  { border-bottom-right-radius:4px; }

        .commune-group {
            background:#fef3c7; font-weight:800; font-size:10px;
            padding:9px 8px !important; color:#92400e;
            border-left:3px solid #e8a020;
        }

        .footer {
            position:fixed; bottom:6mm; left:14mm; right:14mm;
            font-size:8px; color:#9ca3af; text-align:center;
            padding-top:6px; border-top:1px solid #e5e7eb;
        }
        .footer-note {
            padding:11px 14px; margin-top:18px;
            background:#f8fafc; border-left:3px solid #e8a020;
            border-radius:4px;
            font-size:8.5px; color:#475569; line-height:1.5;
        }
    </style>
</head>
<body>

<div class="header">
    <table style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="vertical-align:middle;">
                @if(!empty($logoCibleDark))
                    <img src="{{ $logoCibleDark }}" alt="CIBLE CI" style="height:34px;margin-bottom:4px;">
                @else
                    <div class="logo">CIBLE CI</div>
                @endif
                <div class="logo-sub">Détail des taxes communales</div>
            </td>
            <td style="vertical-align:middle; text-align:right;">
                <div class="header-period">{{ $periodLabel }}</div>
                <div class="header-issued">Édité le {{ now()->format('d/m/Y') }} à {{ now()->format('H\hi') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- Fiche synthétique — remplace la barre "Filtres :" et les cards KPI.
     Présente les métadonnées du document (opérateur, période, éventuels
     commune / nature / client / campagne) en style administratif. --}}
<div class="synth">
    <div class="synth-row">
        <div class="synth-lbl">Opérateur</div>
        <div class="synth-val">{{ $operatorName ?? 'CIBLE CI' }}</div>
    </div>
    <div class="synth-row">
        <div class="synth-lbl">Période</div>
        <div class="synth-val">{{ $periodLabel }}</div>
    </div>
    @foreach($filterMeta ?? [] as $row)
        <div class="synth-row">
            <div class="synth-lbl">{{ $row['label'] }}</div>
            <div class="synth-val">{{ $row['value'] }}</div>
        </div>
    @endforeach
    <div class="synth-row">
        <div class="synth-lbl">Panneaux</div>
        <div class="synth-val">{{ $totals['panels_count'] }} panneau{{ $totals['panels_count'] > 1 ? 'x' : '' }} · {{ $totals['lines_count'] }} ligne{{ $totals['lines_count'] > 1 ? 's' : '' }} de facturation</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:90px;">Panneau</th>
            <th style="width:65px;">Dim.</th>
            <th style="width:40px;">Type</th>
            <th>Client</th>
            <th>Campagne</th>
            <th style="width:130px;">Période</th>
            <th style="width:120px;" class="right">Tarif</th>
            <th style="width:80px;" class="right">Montant</th>
        </tr>
    </thead>
    <tbody>
    @php
        $currentCommune = null;
    @endphp
    @foreach($lines as $row)
        @if($currentCommune !== $row['commune'])
            @php $currentCommune = $row['commune']; @endphp
            <tr><td colspan="8" class="commune-group">📍 {{ $row['commune'] }}</td></tr>
        @endif
        <tr>
            <td class="mono"><strong>{{ $row['reference'] }}</strong></td>
            <td>{{ $row['dimensions'] }}<br><span style="color:#9ca3af; font-size:7.5px;">{{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }} m²</span></td>
            <td><span class="badge badge-{{ $row['type'] }}">{{ strtoupper($row['type']) }}</span></td>
            <td>{{ $row['client_name'] ?? '—' }}</td>
            <td>{{ $row['campaign_name'] ?? '—' }}</td>
            <td style="font-size:8px; color:#475569;">
                {{-- FIX 2026-06-26 — Vraies dates de la campagne (pas le filtre).
                     Lignes ODP sans campagne : on retombe sur la période du filtre. --}}
                @if(!empty($row['campaign_start']) && !empty($row['campaign_end']))
                    {{ $row['campaign_start']->format('d/m/Y') }}<br>
                    → {{ $row['campaign_end']->format('d/m/Y') }}
                @else
                    {{ $row['period_start']->format('d/m/Y') }}<br>
                    → {{ $row['period_end']->format('d/m/Y') }}
                @endif
            </td>
            <td class="right mono" style="font-size:7.5px; color:#6b7280;">
                {{ number_format($row['rate'], 0) }} × {{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }}m² × {{ $row['months'] }}m
            </td>
            <td class="right mono"><strong>{{ number_format($row['amount'], 0, ',', ' ') }}</strong></td>
        </tr>
    @endforeach
        <tr class="total-row">
            <td colspan="7" class="right">TOTAL GÉNÉRAL :</td>
            <td class="right mono">{{ number_format($totals['total'], 0, ',', ' ') }} FCFA</td>
        </tr>
    </tbody>
</table>

<div class="footer-note">
    💡 Chaque montant est justifiable : <strong>tarif × surface (m²) × nombre de mois</strong>.
    Les tarifs appliqués reflètent l'<strong>historique tarifaire</strong> de chaque commune à la date de la période —
    document fiable pour les contrôles administratifs.
</div>

<div class="footer">
    Plateforme <strong>Panora</strong> · opérée par <strong>{{ $operatorName ?? 'CIBLE CI' }}</strong>
    — Document généré automatiquement, taxes communales {{ $periodLabel }}
</div>

</body>
</html>

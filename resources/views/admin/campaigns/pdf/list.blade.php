<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Liste des campagnes — CIBLE CI</title>
    <style>
        @page { margin: 12mm; }
        body  { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 9px; line-height: 1.4; }

        /* Header uniforme cohérent avec les autres PDFs */
        .pdf-header {
            background: #0d1117; color: #fff;
            padding: 12px 18px; display: table; width: 100%;
            border-bottom: 3px solid #e8a020; margin-bottom: 14px;
        }
        .pdf-header > div { display: table-cell; vertical-align: middle; }
        .pdf-header .l { width: 30%; text-align: left; }
        .pdf-header .c { width: 40%; text-align: center; }
        .pdf-header .r { width: 30%; text-align: right; font-size: 9px; color: #9ca3af; }
        .pdf-header img { height: 32px; width: auto; }
        .pdf-header h1  { font-size: 14px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #fff; }
        .pdf-header .accent { color: #e8a020; }

        .summary {
            background: #fff7ed; border: 1px solid #fed7aa;
            border-radius: 4px; padding: 10px 14px; margin-bottom: 12px;
            font-size: 10px; display: table; width: 100%;
        }
        .summary > div { display: table-cell; vertical-align: middle; }
        .summary .total { text-align: right; font-weight: 700; color: #c2570d; font-size: 14px; }

        table { width: 100%; border-collapse: collapse; font-size: 8.5px; }
        thead tr { background: #f3f4f6; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 7px; vertical-align: top; text-align: left; }
        th { font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; font-size: 8px; }
        tbody tr:nth-child(even) { background: #fafafa; }

        .ref { font-family: 'Courier New', monospace; color: #c2570d; font-weight: 700; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 8px; font-weight: 700; }
        .b-actif    { background: #dcfce7; color: #166534; }
        .b-pose     { background: #dbeafe; color: #1e40af; }
        .b-planifie { background: #fef3c7; color: #92400e; }
        .b-termine  { background: #f3f4f6; color: #374151; }
        .b-annule   { background: #fee2e2; color: #991b1b; }

        .footer {
            position: fixed; bottom: 6mm; left: 12mm; right: 12mm;
            border-top: 1px solid #e5e7eb; padding-top: 5px;
            font-size: 8px; color: #9ca3af; text-align: center;
        }
    </style>
</head>
<body>

<div class="pdf-header">
    <div class="l">
        @if(!empty($logoSrc))
            <img src="{{ $logoSrc }}" alt="CIBLE CI">
        @else
            <strong style="color:#e8a020;font-size:18px">CIBLE CI</strong>
        @endif
    </div>
    <div class="c"><h1>Liste des <span class="accent">campagnes</span></h1></div>
    <div class="r">Généré le {{ $generated }}<br>{{ $campaigns->count() }} campagne(s)</div>
</div>

<div class="summary">
    <div>
        <strong>{{ $campaigns->count() }}</strong> campagne(s) listée(s) ·
        Filtres appliqués depuis l'interface admin.
    </div>
    <div class="total">
        Montant total : {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:10%">Référence</th>
            <th style="width:22%">Campagne</th>
            <th style="width:18%">Client</th>
            <th style="width:9%">Statut</th>
            <th style="width:9%">Début</th>
            <th style="width:9%">Fin</th>
            <th style="width:6%" class="num">Pann.</th>
            <th style="width:13%" class="num">Montant</th>
            <th style="width:10%">Créée par</th>
        </tr>
    </thead>
    <tbody>
        @forelse($campaigns as $c)
            @php
                $statusClass = match($c->status?->value) {
                    'actif'    => 'b-actif',
                    'pose'     => 'b-pose',
                    'planifie' => 'b-planifie',
                    'termine'  => 'b-termine',
                    'annule'   => 'b-annule',
                    default    => 'b-termine',
                };
            @endphp
            <tr>
                <td><span class="ref">#{{ $c->id }}</span></td>
                <td>{{ $c->name }}</td>
                <td>{{ $c->client?->name ?? '—' }}</td>
                <td><span class="badge {{ $statusClass }}">{{ $c->status?->label() ?? '—' }}</span></td>
                <td>{{ $c->start_date?->format('d/m/Y') ?? '—' }}</td>
                <td>{{ $c->end_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="num">{{ $c->panels_count ?? 0 }}</td>
                <td class="num">{{ number_format((float) $c->total_amount, 0, ',', ' ') }}</td>
                <td>{{ $c->user?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" style="text-align:center;padding:24px;color:#9ca3af">Aucune campagne ne correspond aux filtres.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    CIBLE CI · Régie Publicitaire · Abidjan, Côte d'Ivoire · Document confidentiel
</div>

</body>
</html>

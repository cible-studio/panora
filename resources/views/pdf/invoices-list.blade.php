<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des factures</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    @page { margin: 12mm; size: A4 landscape; }

    body {
        font-family: 'DejaVu Sans', 'Helvetica', sans-serif;
        font-size: 10px; color: #1f2937; background: #fff;
    }

    .head { width: 100%; margin-bottom: 14px; }
    .head td { vertical-align: middle; }
    .head .logo-cell img { height: 32px; }
    .head .ref-cell { text-align: right; }
    .doc-label {
        font-size: 9px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 2px;
        margin-bottom: 3px;
    }
    .doc-title { font-size: 16px; font-weight: 800; color: #0f172a; }
    .doc-date { font-size: 10px; color: #64748b; margin-top: 3px; }

    .accent-bar { height: 2px; background: #e8a020; width: 100%; margin: 4px 0 14px; }

    .filters-bar {
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 6px; padding: 8px 12px; margin-bottom: 12px;
        font-size: 9.5px; color: #475569;
    }
    .filters-bar strong { color: #0f172a; }
    .filters-bar .chip {
        display: inline-block; padding: 2px 8px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 10px; margin-right: 6px;
        font-size: 9px;
    }

    .summary {
        display: table; width: 100%;
        border-collapse: separate; border-spacing: 8px 0;
        margin-bottom: 14px;
    }
    .summary .cell {
        display: table-cell; width: 25%;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-left: 3px solid #e8a020;
        border-radius: 6px; padding: 9px 12px;
    }
    .summary .lbl { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    .summary .val { font-size: 14px; font-weight: 800; color: #0f172a; margin-top: 3px; font-variant-numeric: tabular-nums; }

    table.list { width: 100%; border-collapse: collapse; font-size: 9.5px; }
    table.list thead th {
        background: #0f172a; color: #e8a020;
        padding: 7px 9px; text-align: left;
        font-size: 8.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 1px;
    }
    table.list thead th.r { text-align: right; }
    table.list tbody td {
        padding: 6px 9px; border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    table.list tbody td.r { text-align: right; font-variant-numeric: tabular-nums; }
    table.list tbody tr:nth-child(even) td { background: #fafbfc; }
    table.list .ref { font-family: monospace, 'DejaVu Sans Mono', sans-serif; font-weight: 700; color: #c2570d; }

    .pill {
        display: inline-block; padding: 1px 7px;
        border-radius: 10px; font-size: 8.5px; font-weight: 700;
    }
    .pill-paid    { background: #dcfce7; color: #15803d; }
    .pill-sent    { background: #dbeafe; color: #1d4ed8; }
    .pill-draft   { background: #f1f5f9; color: #475569; }
    .pill-cancel  { background: #fee2e2; color: #b91c1c; }

    .footer {
        margin-top: 16px; padding-top: 8px;
        border-top: 1px solid #e2e8f0;
        text-align: center; font-size: 8.5px; color: #94a3b8;
    }
</style>
</head>
<body>

<table class="head">
    <tr>
        <td class="logo-cell" style="width:50%;">
            <img src="{{ public_path('images/logol.png') }}" alt="CIBLE CI">
        </td>
        <td class="ref-cell" style="width:50%;">
            <div class="doc-label">Rapport</div>
            <div class="doc-title">Liste des factures</div>
            <div class="doc-date">Généré le {{ now()->format('d/m/Y \à H:i') }}</div>
        </td>
    </tr>
</table>

<div class="accent-bar"></div>

@php
    $statusLabels = [
        'brouillon' => 'Brouillon', 'envoyee' => 'Envoyée',
        'payee' => 'Payée', 'annulee' => 'Annulée',
    ];
    $hasFilter = !empty($filters['client_id']) || !empty($filters['status'])
        || !empty($filters['date_from']) || !empty($filters['date_to']);
@endphp

@if($hasFilter)
<div class="filters-bar">
    <strong>Filtres appliqués :</strong>
    @if(!empty($filters['client_id']) && !empty($filters['client_name']))
        <span class="chip">Client : {{ $filters['client_name'] }}</span>
    @endif
    @if(!empty($filters['status']))
        <span class="chip">Statut : {{ $statusLabels[$filters['status']] ?? $filters['status'] }}</span>
    @endif
    @if(!empty($filters['date_from']))
        <span class="chip">Émise après {{ \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') }}</span>
    @endif
    @if(!empty($filters['date_to']))
        <span class="chip">Émise avant {{ \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') }}</span>
    @endif
</div>
@endif

<div class="summary">
    <div class="cell">
        <div class="lbl">Nombre</div>
        <div class="val">{{ count($invoices) }}</div>
    </div>
    <div class="cell">
        <div class="lbl">Total HT</div>
        <div class="val">{{ number_format((float) $invoices->sum('amount'), 0, ',', ' ') }} FCFA</div>
    </div>
    <div class="cell">
        <div class="lbl">Total TTC</div>
        <div class="val">{{ number_format((float) $invoices->sum('amount_ttc'), 0, ',', ' ') }} FCFA</div>
    </div>
    <div class="cell">
        <div class="lbl">Encaissé (payées)</div>
        <div class="val">{{ number_format((float) $invoices->where('status','payee')->sum('amount_ttc'), 0, ',', ' ') }} FCFA</div>
    </div>
</div>

<table class="list">
    <thead>
        <tr>
            <th style="width:11%;">Référence</th>
            <th style="width:18%;">Client</th>
            <th style="width:18%;">Campagne</th>
            <th style="width:8%;">Émise le</th>
            <th style="width:8%;">Payée le</th>
            <th style="width:9%;">Statut</th>
            <th class="r" style="width:9%;">HT</th>
            <th class="r" style="width:5%;">TVA</th>
            <th class="r" style="width:11%;">TTC</th>
            <th style="width:10%;">Créée par</th>
        </tr>
    </thead>
    <tbody>
        @forelse($invoices as $inv)
        <tr>
            <td class="ref">{{ $inv->reference }}</td>
            <td>{{ $inv->client?->name ?? '—' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($inv->campaign?->name ?? '—', 35) }}</td>
            <td>{{ $inv->issued_at?->format('d/m/Y') ?? '' }}</td>
            <td>{{ $inv->paid_at?->format('d/m/Y') ?? '—' }}</td>
            <td>
                @switch($inv->status)
                    @case('payee')   <span class="pill pill-paid">✓ Payée</span> @break
                    @case('envoyee') <span class="pill pill-sent">↗ Envoyée</span> @break
                    @case('annulee') <span class="pill pill-cancel">✕ Annulée</span> @break
                    @default         <span class="pill pill-draft">Brouillon</span>
                @endswitch
            </td>
            <td class="r">{{ number_format((float) $inv->amount, 0, ',', ' ') }}</td>
            <td class="r">{{ rtrim(rtrim(number_format((float) $inv->tva, 2, ',', ''), '0'), ',') }} %</td>
            <td class="r"><strong>{{ number_format((float) $inv->amount_ttc, 0, ',', ' ') }}</strong></td>
            <td>{{ $inv->creator?->name ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" style="text-align:center;color:#94a3b8;padding:18px;">Aucune facture.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    <strong>CIBLE CI</strong> — Régie publicitaire OOH · Abidjan, Côte d'Ivoire
</div>

</body>
</html>

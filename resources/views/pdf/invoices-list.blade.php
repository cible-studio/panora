<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Liste des factures</title>
{{-- 2026-10-01 — charte graphique : socle commun (polices, palette, classes ch-*). --}}
@include('pdf.partials.charte-styles')
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    /* !important : « * { margin:0 } » annule sinon la marge @page sous DomPDF.
       Marge basse ≥ 18 mm pour le pied fixe charte-footer. */
    @page { margin: 12mm 12mm 18mm 12mm !important; size: A4 landscape; }

    body { font-size: 10px; color: {{ $charte['noir'] }}; background: {{ $charte['blanc'] }}; }

    .filters-bar { font-size: 9.5px; color: {{ $charte['texte_doux'] }}; }
    .filters-bar strong { color: {{ $charte['noir'] }}; }
    .filters-bar .chip {
        display: inline-block; padding: 2px 8px;
        background: {{ $charte['blanc'] }}; border: 1px solid {{ $charte['gris'] }};
        border-radius: 10px; margin-right: 6px;
        font-size: 9px;
    }

    /* Cartes de synthèse — table (pas de flex/grid sous DomPDF). */
    .summary { margin-bottom: 14px; border-spacing: 8px 0; }
    .summary .ch-kpi { width: 25%; padding: 9px 12px; }
    .summary .lbl { font-size: 9px; color: {{ $charte['texte_doux'] }}; text-transform: uppercase; letter-spacing: 1px; }
    .summary .val { font-family: {!! $charte['ff_titres'] !!}; font-size: 14px; font-weight: 800; color: {{ $charte['noir'] }}; margin-top: 3px; }

    table.list { font-size: 9.5px; }
    table.list thead th { padding: 7px 9px; font-size: 8.5px; letter-spacing: 1px; }
    table.list thead th.r { text-align: right; }
    table.list tbody td { padding: 6px 9px; vertical-align: top; }
    table.list tbody td.r { text-align: right; }
    table.list .ref { font-family: {!! $charte['ff_mono'] !!}; font-weight: 700; color: {{ $charte['rouge'] }}; }
    table.list td.empty { text-align: center; color: {{ $charte['texte_pale'] }}; padding: 18px; }

    .pill { font-size: 8.5px; border-radius: 10px; padding: 1px 7px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre),
     reprend « Rapport » / « Liste des factures » / date de génération. --}}
@include('pdf.partials.charte-header', [
    'docKicker' => 'Rapport',
    'docTitle'  => 'Liste des factures',
    'docMeta'   => ['Généré le ' . now()->format('d/m/Y \à H:i')],
    'docLogo'   => $logoCibleLight ?? public_path('images/logol.png'),
])

@php
    // Libellés alignés sur Invoice::STATUS_LABELS (M1 — "Soldée" remplace "Payée").
    $statusLabels = \App\Models\Invoice::STATUS_LABELS;
    $hasFilter = !empty($filters['client_id']) || !empty($filters['status'])
        || !empty($filters['date_from']) || !empty($filters['date_to']);
@endphp

@if($hasFilter)
<div class="ch-info filters-bar">
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

<table class="ch-kpis summary">
    <tr>
        <td class="ch-kpi k-noir">
            <div class="lbl">Nombre</div>
            <div class="val">{{ count($invoices) }}</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="lbl">Total HT</div>
            <div class="val">{{ number_format((float) $invoices->sum('amount'), 0, ',', ' ') }} FCFA</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="lbl">Total TTC</div>
            <div class="val">{{ number_format((float) $invoices->sum('amount_ttc'), 0, ',', ' ') }} FCFA</div>
        </td>
        <td class="ch-kpi k-vert">
            <div class="lbl">Encaissé (soldées)</div>
            <div class="val">{{ number_format((float) $invoices->where('status','payee')->sum('amount_ttc'), 0, ',', ' ') }} FCFA</div>
        </td>
    </tr>
</table>

<table class="ch-table list">
    <thead>
        <tr>
            <th style="width:11%;">Référence</th>
            <th style="width:18%;">Client</th>
            <th style="width:18%;">Campagne</th>
            <th style="width:8%;">Émise le</th>
            <th style="width:8%;">Soldée le</th>
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
                    @case('payee')   <span class="ch-badge ch-badge-vert pill">✓ Soldée</span> @break
                    @case('envoyee') <span class="ch-badge ch-badge-bleu pill">↗ Envoyée</span> @break
                    @case('annulee') <span class="ch-badge ch-badge-rouge pill">✕ Annulée</span> @break
                    @default         <span class="ch-badge ch-badge-gris pill">{{ \App\Models\Invoice::statusLabel($inv->status) }}</span>
                @endswitch
            </td>
            <td class="r">{{ number_format((float) $inv->amount, 0, ',', ' ') }}</td>
            <td class="r">{{ rtrim(rtrim(number_format((float) $inv->tva, 2, ',', ''), '0'), ',') }} %</td>
            <td class="r"><strong>{{ number_format((float) $inv->amount_ttc, 0, ',', ' ') }}</strong></td>
            <td>{{ $inv->creator?->name ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="10" class="empty">Aucune facture.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2026-10-01 — charte graphique : pied fixe commun ; l'ancienne mention
     de pied est conservée en 2e ligne. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE CI — Régie publicitaire OOH · Abidjan, Côte d\'Ivoire',
])

</body>
</html>

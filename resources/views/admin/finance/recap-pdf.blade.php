<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Récap Finance — CIBLE CI</title>
{{-- 2026-10-01 — charte graphique : socle commun (polices, palette, classes ch-*). --}}
@include('pdf.partials.charte-styles')
<style>
    /* Marge basse ≥ 18 mm : réserve la place du pied fixe charte-footer. */
    @page { size: A4; margin: 14mm 12mm 18mm 12mm !important; }
    body { font-size: 9.5px; color: {{ $charte['noir'] }}; line-height: 1.25; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    th { background: {{ $charte['noir'] }}; padding: 5px 7px; text-align: left; font-family: {!! $charte['ff_titres'] !!}; font-size: 7.5px; font-weight: 700; color: {{ $charte['blanc'] }}; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid {{ $charte['gris'] }}; }
    tr:nth-child(even) td { background: {{ $charte['gris_clair'] }}; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .ref { font-family: {!! $charte['ff_mono'] !!}; color: {{ $charte['rouge'] }}; }
    .empty { text-align: center; color: {{ $charte['texte_doux'] }}; font-style: italic; padding: 20px; }
    /* Cartes KPI : table + classes ch-kpi (bordure haute colorée). */
    .kpi-grid { margin: 6px 0 14px; border-spacing: 4px; }
    .kpi-grid td.ch-kpi { width: 25%; padding: 7px 10px; border-bottom: none; background: {{ $charte['gris_clair'] }}; }
    .kpi-label { font-size: 7.5px; text-transform: uppercase; color: {{ $charte['texte_doux'] }}; letter-spacing: 0.5px; }
    .kpi-value { font-family: {!! $charte['ff_titres'] !!}; font-size: 13.5px; font-weight: 700; color: {{ $charte['noir'] }}; margin-top: 2px; }
    .kpi-sub   { font-size: 7.5px; color: {{ $charte['texte_doux'] }}; margin-top: 1px; }
</style>
</head>
<body>

{{-- 2026-10-01 — charte graphique : en-tête commun (liseré + logo + titre). --}}
@include('pdf.partials.charte-header', [
    'docTitle'    => 'RÉCAP FINANCIER',
    'docSubtitle' => 'Tableau de bord financier · ' . ($operatorName ?? 'CIBLE CI') . ' · Période : ' . $from->format('d/m/Y') . ' → ' . $to->format('d/m/Y'),
    'docMeta'     => [
        'Édité le ' . now()->format('d/m/Y H:i'),
        'Par ' . ($user->name ?? '—'),
        'Réf. ' . strtoupper(substr(md5(now()), 0, 8)),
    ],
])

{{-- ════ KPI principaux ════ --}}
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
@endphp
<table class="ch-kpis kpi-grid">
    <tr>
        <td class="ch-kpi k-vert">
            <div class="kpi-label">Encaissé période</div>
            <div class="kpi-value">{{ $fmt($kpis['encaisse_total'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · versements TTC</div>
        </td>
        <td class="ch-kpi k-jaune">
            <div class="kpi-label">Facturé période</div>
            <div class="kpi-value">{{ $fmt($kpis['facture_total'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · HT hors annulées</div>
        </td>
        <td class="ch-kpi k-rouge">
            <div class="kpi-label">Total dû</div>
            <div class="kpi-value">{{ $fmt($kpis['total_du'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · cumul créances</div>
        </td>
        <td class="ch-kpi k-bleu">
            <div class="kpi-label">Recouvrement</div>
            <div class="kpi-value">{{ number_format($kpis['taux_recouvrement'] ?? 0, 1, ',', ' ') }} %</div>
            <div class="kpi-sub">encaissé / facturé</div>
        </td>
    </tr>
</table>

{{-- ════ Top 10 versements ════ --}}
<div class="ch-section">Top 10 versements de la période</div>
<table>
    <thead>
        <tr>
            <th style="width:11%">Date</th>
            <th style="width:13%">Facture</th>
            <th>Client</th>
            <th style="width:13%">Mode</th>
            <th class="r" style="width:15%">Montant (FCFA)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($recentPayments->sortByDesc('montant')->take(10) as $p)
            <tr>
                <td>{{ $p['paid_at']?->format('d/m/Y') ?? '—' }}</td>
                <td class="ref">{{ $p['invoice_ref'] ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($p['client_name'] ?? '—', 40) }}</td>
                <td>{{ $p['mode_label'] ?? $p['mode'] ?? '—' }}</td>
                <td class="r b">{{ $fmt($p['montant'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Aucun versement sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Top 10 créances ouvertes ════ --}}
<div class="ch-section">Top 10 créances ouvertes (reste à payer)</div>
<table>
    <thead>
        <tr>
            <th style="width:13%">Facture</th>
            <th>Client</th>
            <th style="width:11%">Échéance</th>
            <th class="r" style="width:14%">Total (FCFA)</th>
            <th class="r" style="width:14%">Reste (FCFA)</th>
            <th style="width:12%">Statut</th>
        </tr>
    </thead>
    <tbody>
        @forelse($creances->sortByDesc(fn($i) => $i->remainingAmount())->take(10) as $inv)
            @php
                $statut = $inv->paymentStatus();
                $isOverdue = $statut === 'en_retard';
            @endphp
            <tr>
                <td class="ref">{{ $inv->reference ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($inv->client?->name ?? '—', 38) }}</td>
                <td>{{ $inv->due_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="r">{{ $fmt($inv->total_a_payer ?? 0) }}</td>
                <td class="r b" style="color:{{ $isOverdue ? $charte['rouge'] : $charte['noir'] }}">{{ $fmt($inv->remainingAmount()) }}</td>
                <td>
                    @if($isOverdue)
                        <span class="ch-badge ch-badge-rouge">EN RETARD</span>
                    @else
                        {{ ucfirst(str_replace('_', ' ', $statut)) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Aucune créance ouverte.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Top 10 clients à relancer ════ --}}
<div class="ch-section">Top 10 clients à relancer</div>
<table>
    <thead>
        <tr>
            <th>Client</th>
            <th style="width:11%">Téléphone</th>
            <th class="r" style="width:15%">Dette (FCFA)</th>
            <th class="r" style="width:10%">Factures</th>
            <th style="width:14%">Dernière relance</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clientsToFollow->sortByDesc('total_du')->take(10) as $row)
            <tr>
                <td class="b">{{ \Illuminate\Support\Str::limit($row['client_name'] ?? '—', 40) }}</td>
                <td>{{ $row['client_phone'] ?? '—' }}</td>
                <td class="r b c-rouge">{{ $fmt($row['total_du'] ?? 0) }}</td>
                <td class="r">{{ (int) ($row['factures_open'] ?? 0) }}</td>
                <td>
                    @php $dr = $row['derniere_relance'] ?? null; @endphp
                    @if($dr)
                        {{ $dr instanceof \DateTimeInterface ? $dr->format('d/m/Y') : \Carbon\Carbon::parse($dr)->format('d/m/Y') }}
                    @else
                        <em class="ch-muted">Jamais</em>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Aucun client à relancer</td></tr>
        @endforelse
    </tbody>
</table>

{{-- 2026-10-01 — charte graphique : pied fixe commun ; ancienne mention conservée en 2e ligne. --}}
@include('pdf.partials.charte-footer', [
    'footerHint' => 'CIBLE SARL — Régie OOH Côte d\'Ivoire · Récap financier · Détail complet disponible en Excel.',
])

</body>
</html>

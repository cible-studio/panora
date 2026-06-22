<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Récap Finance — CIBLE CI</title>
<style>
    @page { size: A4; margin: 14mm 12mm; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1f2937; line-height: 1.45; }
    h1 { font-size: 17px; color: #e8a020; margin: 0 0 4px; }
    h2 { font-size: 12px; color: #111827; margin: 16px 0 7px; padding-bottom: 4px; border-bottom: 1.5px solid #e8a020; }
    .header { display: table; width: 100%; margin-bottom: 12px; }
    .header .left  { display: table-cell; vertical-align: top; }
    .header .right { display: table-cell; vertical-align: top; text-align: right; font-size: 9px; color: #6b7280; }
    .period { font-size: 10.5px; color: #6b7280; margin-top: 2px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
    th { background: #0a0c10; padding: 5px 7px; text-align: left; font-size: 7.5px; font-weight: bold; color: #fff; text-transform: uppercase; letter-spacing: 0.4px; }
    td { padding: 4px 7px; font-size: 8.5px; border-bottom: 1px solid #f3f4f6; }
    tr:nth-child(even) td { background: #fafafa; }
    .r { text-align: right; }
    .b { font-weight: bold; }
    .kpi-grid { display: table; width: 100%; margin: 6px 0 14px; border-collapse: separate; border-spacing: 4px; }
    .kpi-row  { display: table-row; }
    .kpi      { display: table-cell; padding: 7px 10px; background: #fafafa; border-left: 3px solid #e8a020; border-radius: 4px; width: 25%; }
    .kpi-label { font-size: 7.5px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.5px; }
    .kpi-value { font-size: 13.5px; font-weight: bold; color: #111827; margin-top: 2px; }
    .kpi-sub   { font-size: 7.5px; color: #6b7280; margin-top: 1px; }
    .color-enc { border-left-color: #16a34a; }
    .color-fac { border-left-color: #f59e0b; }
    .color-du  { border-left-color: #dc2626; }
    .color-rec { border-left-color: #3b82f6; }
    .badge-overdue { background: rgba(220,38,38,.10); color: #b91c1c; padding: 1px 6px; border-radius: 999px; font-size: 8px; font-weight: bold; }
    .footer { position: fixed; bottom: 4mm; left: 12mm; right: 12mm; font-size: 7.5px; color: #9ca3af; text-align: center; border-top: 1px solid #e5e7eb; padding-top: 4px; }
</style>
</head>
<body>

<div class="header">
    <div class="left">
        <h1>RÉCAP FINANCIER</h1>
        <div class="period">Tableau de bord financier · {{ $operatorName ?? 'CIBLE CI' }}</div>
        <div class="period">Période : {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</div>
    </div>
    <div class="right">
        Édité le {{ now()->format('d/m/Y H:i') }}<br>
        Par {{ $user->name ?? '—' }}<br>
        Réf. {{ strtoupper(substr(md5(now()), 0, 8)) }}
    </div>
</div>

{{-- ════ KPI principaux ════ --}}
@php
    $fmt = fn($n) => number_format((float) $n, 0, ',', ' ');
@endphp
<div class="kpi-grid">
    <div class="kpi-row">
        <div class="kpi color-enc">
            <div class="kpi-label">💰 Encaissé période</div>
            <div class="kpi-value">{{ $fmt($kpis['encaisse_total'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · versements TTC</div>
        </div>
        <div class="kpi color-fac">
            <div class="kpi-label">📤 Facturé période</div>
            <div class="kpi-value">{{ $fmt($kpis['facture_total'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · HT hors annulées</div>
        </div>
        <div class="kpi color-du">
            <div class="kpi-label">📉 Total dû</div>
            <div class="kpi-value">{{ $fmt($kpis['total_du'] ?? 0) }}</div>
            <div class="kpi-sub">FCFA · cumul créances</div>
        </div>
        <div class="kpi color-rec">
            <div class="kpi-label">🔁 Recouvrement</div>
            <div class="kpi-value">{{ number_format($kpis['taux_recouvrement'] ?? 0, 1, ',', ' ') }} %</div>
            <div class="kpi-sub">encaissé / facturé</div>
        </div>
    </div>
</div>

{{-- ════ Top 10 versements ════ --}}
<h2>💵 Top 10 versements de la période</h2>
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
                <td style="font-family:'Courier New',monospace;color:#b45309">{{ $p['invoice_ref'] ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($p['client_name'] ?? '—', 40) }}</td>
                <td>{{ $p['mode_label'] ?? $p['mode'] ?? '—' }}</td>
                <td class="r b">{{ $fmt($p['montant'] ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#6b7280;font-style:italic;padding:20px">Aucun versement sur la période.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Top 10 créances ouvertes ════ --}}
<h2>📉 Top 10 créances ouvertes (reste à payer)</h2>
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
                <td style="font-family:'Courier New',monospace;color:#b45309">{{ $inv->reference ?? '—' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($inv->client?->name ?? '—', 38) }}</td>
                <td>{{ $inv->due_date?->format('d/m/Y') ?? '—' }}</td>
                <td class="r">{{ $fmt($inv->total_a_payer ?? 0) }}</td>
                <td class="r b" style="color:{{ $isOverdue ? '#b91c1c' : '#111827' }}">{{ $fmt($inv->remainingAmount()) }}</td>
                <td>
                    @if($isOverdue)
                        <span class="badge-overdue">EN RETARD</span>
                    @else
                        {{ ucfirst(str_replace('_', ' ', $statut)) }}
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;color:#6b7280;font-style:italic;padding:20px">Aucune créance ouverte.</td></tr>
        @endforelse
    </tbody>
</table>

{{-- ════ Top 10 clients à relancer ════ --}}
<h2>🔁 Top 10 clients à relancer</h2>
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
                <td class="r b" style="color:#b45309">{{ $fmt($row['total_du'] ?? 0) }}</td>
                <td class="r">{{ (int) ($row['factures_open'] ?? 0) }}</td>
                <td>
                    @php $dr = $row['derniere_relance'] ?? null; @endphp
                    @if($dr)
                        {{ $dr instanceof \DateTimeInterface ? $dr->format('d/m/Y') : \Carbon\Carbon::parse($dr)->format('d/m/Y') }}
                    @else
                        <em style="color:#6b7280">Jamais</em>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;color:#6b7280;font-style:italic;padding:20px">Aucun client à relancer 🎉</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    CIBLE SARL — Régie OOH Côte d'Ivoire · Récap financier · Détail complet disponible en Excel.
</div>

</body>
</html>

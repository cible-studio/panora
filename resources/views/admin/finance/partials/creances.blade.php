{{-- Onglet CRÉANCES : balance âgée + détail factures impayées --}}
@php
    $bucketColors = [
        '0-30'   => '#22c55e',
        '31-60'  => '#eab308',
        '61-90'  => '#f97316',
        '91-120' => '#ef4444',
        '120+'   => '#991b1b',
    ];
    $bucketLabels = [
        '0-30'   => '0-30 jours',
        '31-60'  => '31-60 jours',
        '61-90'  => '61-90 jours',
        '91-120' => '91-120 jours',
        '120+'   => '120+ jours',
    ];
@endphp

{{-- ════ AGING BUCKETS ════ --}}
<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">⏳ Balance âgée — répartition du dû</div>
            <div class="fin-card-sub">Ancienneté basée sur l'échéance la plus ancienne non payée (fallback date d'émission)</div>
        </div>
    </div>
    <div class="fin-card-body">
        <div class="fin-aging-grid">
            @foreach($aging['buckets'] as $bucket => $total)
                @php $pct = $aging['total'] > 0 ? round(($total / $aging['total']) * 100, 1) : 0; @endphp
                <div class="fin-aging-card" style="--c:{{ $bucketColors[$bucket] }}">
                    <div class="fin-aging-label">{{ $bucketLabels[$bucket] }}</div>
                    <div class="fin-aging-value">{{ $fmt($total) }} <span>FCFA</span></div>
                    <div class="fin-aging-bar"><div class="fin-aging-bar-fill" style="width:{{ $pct }}%;background:{{ $bucketColors[$bucket] }}"></div></div>
                    <div class="fin-aging-pct">{{ $pct }}% du total dû</div>
                </div>
            @endforeach
        </div>
        <div style="margin-top:14px;text-align:right;font-size:13px;color:var(--text2)">
            Total dû : <strong style="color:var(--accent);font-size:16px">{{ $fmt($aging['total']) }} FCFA</strong>
        </div>
    </div>
</div>

{{-- ════ BALANCE PAR CLIENT ════ --}}
<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">👥 Balance âgée par client</div>
            <div class="fin-card-sub">{{ $aging['by_client']->count() }} client(s) avec créance ouverte</div>
        </div>
    </div>
    <div class="fin-card-body fin-card-body--flush">
        @if($aging['by_client']->isEmpty())
            <div class="fin-empty" style="margin:18px">Aucune créance ouverte. 🎉</div>
        @else
            <div style="overflow-x:auto">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            @foreach($bucketLabels as $bucket => $label)
                                <th class="num" style="white-space:nowrap">{{ $label }}</th>
                            @endforeach
                            <th class="num">Total dû</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($aging['by_client'] as $row)
                            <tr>
                                <td><a href="{{ route('admin.clients.show', $row['client_id']) }}" style="color:var(--accent);text-decoration:none">{{ $row['client_name'] }}</a></td>
                                @foreach($bucketLabels as $bucket => $label)
                                    @php $v = $row['buckets'][$bucket] ?? 0; @endphp
                                    <td class="num" style="color:{{ $v > 0 ? $bucketColors[$bucket] : 'var(--text3)' }}">
                                        {{ $v > 0 ? $fmt($v) : '—' }}
                                    </td>
                                @endforeach
                                <td class="num strong">{{ $fmt($row['total']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

{{-- ════ FACTURES IMPAYÉES — DÉTAIL ════ --}}
<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">📄 Factures avec reste à payer</div>
            <div class="fin-card-sub">{{ $creances->count() }} facture(s) — 200 max affichées</div>
        </div>
    </div>
    <div class="fin-card-body fin-card-body--flush">
        @if($creances->isEmpty())
            <div class="fin-empty" style="margin:18px">Aucune facture impayée. 🎉</div>
        @else
            <div style="overflow-x:auto">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Client</th>
                            <th>Émise</th>
                            <th>Prochaine échéance</th>
                            <th class="num">Total</th>
                            <th class="num">Payé</th>
                            <th class="num">Reste</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($creances as $inv)
                            @php
                                $status = $inv->paymentStatus();
                                $statusCfg = match($status) {
                                    'non_payee' => ['bg' => 'rgba(107,114,128,.15)', 'c' => '#6b7280', 'l' => 'Non payée'],
                                    'partielle' => ['bg' => 'rgba(245,158,11,.15)',  'c' => '#f59e0b', 'l' => 'Partielle'],
                                    'en_retard' => ['bg' => 'rgba(239,68,68,.15)',   'c' => '#ef4444', 'l' => 'En retard'],
                                    default     => ['bg' => 'var(--surface2)', 'c' => 'var(--text3)', 'l' => $status],
                                };
                                $next = $inv->schedules->whereNull('paid_at')->sortBy('due_date')->first();
                            @endphp
                            <tr>
                                <td><a href="{{ route('admin.invoices.show', $inv) }}" style="color:var(--accent);text-decoration:none;font-family:monospace;font-weight:700">{{ $inv->reference }}</a></td>
                                <td>{{ $inv->client?->name ?? '—' }}</td>
                                <td style="color:var(--text2)">{{ $inv->issued_at?->format('d/m/Y') }}</td>
                                <td style="color:{{ $next && $next->due_date->isPast() ? '#ef4444' : 'var(--text2)' }}">
                                    {{ $next ? $next->due_date->format('d/m/Y') : '—' }}
                                </td>
                                <td class="num">{{ $fmt($inv->total_a_payer) }}</td>
                                <td class="num" style="color:#22c55e">{{ $fmt($inv->paidAmount()) }}</td>
                                <td class="num strong">{{ $fmt($inv->remainingAmount()) }}</td>
                                <td>
                                    <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['c'] }}">
                                        {{ $statusCfg['l'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
.fin-aging-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
}
@media (max-width: 900px) { .fin-aging-grid { grid-template-columns: repeat(2, 1fr); } }
.fin-aging-card {
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 11px;
    padding: 12px 14px;
    border-left: 4px solid var(--c);
}
.fin-aging-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; color: var(--text3); }
.fin-aging-value { font-size: 17px; font-weight: 800; color: var(--c); margin-top: 4px; font-variant-numeric: tabular-nums; }
.fin-aging-value span { font-size: 10px; color: var(--text3); font-weight: 600; }
.fin-aging-bar { height: 4px; background: var(--surface3, #f3f4f6); border-radius: 4px; overflow: hidden; margin-top: 8px; }
.fin-aging-bar-fill { height: 100%; transition: width .25s; }
.fin-aging-pct { font-size: 10.5px; color: var(--text3); margin-top: 4px; }
</style>

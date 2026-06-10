{{-- Onglet ENCAISSEMENTS : graphique d'évolution + 3 tableaux (client / commune / commercial) --}}

<div class="fin-card">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">📈 Évolution des encaissements</div>
            <div class="fin-card-sub">Montants reçus dans le temps sur la période</div>
        </div>
    </div>
    <div class="fin-card-body">
        <div style="position:relative;height:280px">
            <canvas id="finChartEncaissements"></canvas>
        </div>
    </div>
</div>

<div class="fin-encaissements-grid">
    {{-- TOP CLIENTS --}}
    <div class="fin-card">
        <div class="fin-card-head">
            <div>
                <div class="fin-card-title">🏆 Top clients</div>
                <div class="fin-card-sub">Les plus gros encaissements</div>
            </div>
        </div>
        <div class="fin-card-body fin-card-body--flush">
            @if($topClients->isEmpty())
                <div class="fin-empty">Aucun encaissement sur la période.</div>
            @else
                <table class="fin-table">
                    <thead><tr><th>Client</th><th class="num">Factures</th><th class="num">Encaissé</th></tr></thead>
                    <tbody>
                        @foreach($topClients as $c)
                            <tr>
                                <td><a href="{{ route('admin.clients.show', $c['client_id']) }}" style="color:var(--accent);text-decoration:none">{{ $c['client_name'] }}</a></td>
                                <td class="num">{{ $c['factures_count'] }}</td>
                                <td class="num strong">{{ $fmt($c['total']) }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- PAR COMMUNE --}}
    <div class="fin-card">
        <div class="fin-card-head">
            <div>
                <div class="fin-card-title">📍 Par commune</div>
                <div class="fin-card-sub">Ventilation au prorata des lignes HT</div>
            </div>
        </div>
        <div class="fin-card-body fin-card-body--flush">
            @if($byCommune->isEmpty())
                <div class="fin-empty">Aucune ventilation possible (pas de lignes).</div>
            @else
                <table class="fin-table">
                    <thead><tr><th>Commune</th><th class="num">Encaissé</th><th class="num">Part</th></tr></thead>
                    <tbody>
                        @php $totalCom = $byCommune->sum('total'); @endphp
                        @foreach($byCommune as $c)
                            @php $pct = $totalCom > 0 ? round(($c['total'] / $totalCom) * 100, 1) : 0; @endphp
                            <tr>
                                <td>{{ $c['commune_name'] ?? '—' }}</td>
                                <td class="num strong">{{ $fmt($c['total']) }} FCFA</td>
                                <td class="num" style="color:var(--text3)">{{ $pct }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- PAR COMMERCIAL --}}
    <div class="fin-card">
        <div class="fin-card-head">
            <div>
                <div class="fin-card-title">👤 Par commercial</div>
                <div class="fin-card-sub">Performance d'encaissement par vendeur</div>
            </div>
        </div>
        <div class="fin-card-body fin-card-body--flush">
            @if($byCommercial->isEmpty())
                <div class="fin-empty">Aucun encaissement attribué.</div>
            @else
                <table class="fin-table">
                    <thead><tr><th>Commercial</th><th class="num">Factures</th><th class="num">Encaissé</th></tr></thead>
                    <tbody>
                        @foreach($byCommercial as $c)
                            <tr>
                                <td>{{ $c['user_name'] }}</td>
                                <td class="num">{{ $c['factures_count'] }}</td>
                                <td class="num strong">{{ $fmt($c['total']) }} FCFA</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</div>

{{-- ════════ DÉTAIL DES VERSEMENTS — date + montant + mode + référence ════════
     Demandé : "et les dates et montants de differents versements c'est où ?"
     On affiche chaque ligne de versement sur la période, du plus récent au
     plus ancien. Capé à 200 entrées (cf. recentPayments(…, 200, …) dans le
     service). Cliquer sur la référence ouvre la facture. --}}
<div class="fin-card" style="margin-top:14px">
    <div class="fin-card-head">
        <div>
            <div class="fin-card-title">💵 Détail des versements</div>
            <div class="fin-card-sub">{{ $recentPayments->count() }} versement(s) sur la période — du plus récent au plus ancien</div>
        </div>
    </div>
    <div class="fin-card-body fin-card-body--flush">
        @if($recentPayments->isEmpty())
            <div class="fin-empty" style="margin:18px">Aucun versement enregistré sur la période.</div>
        @else
            <div style="overflow-x:auto">
                <table class="fin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Facture</th>
                            <th>Client</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th class="num">Montant</th>
                            <th>Enregistré par</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentPayments as $p)
                            <tr>
                                <td style="white-space:nowrap;color:var(--text2)">{{ $p['paid_at']?->format('d/m/Y') ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('admin.invoices.show', $p['invoice_id']) }}"
                                       style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p['invoice_ref'] }}</a>
                                    @if($p['is_acompte'])
                                        <span style="display:inline-block;margin-left:4px;padding:1px 6px;background:rgba(59,130,246,.15);color:#3b82f6;border-radius:999px;font-size:9.5px;font-weight:700">ACOMPTE</span>
                                    @endif
                                </td>
                                <td>
                                    @if($p['client_id'])
                                        <a href="{{ route('admin.clients.show', $p['client_id']) }}" style="color:var(--text);text-decoration:none">{{ $p['client_name'] }}</a>
                                    @else
                                        <span style="color:var(--text3)">{{ $p['client_name'] }}</span>
                                    @endif
                                </td>
                                <td style="font-size:12px">{{ $p['mode_label'] ?? $p['mode'] }}</td>
                                <td style="font-size:12px;color:var(--text2)">
                                    {{ $p['reference'] ?: '—' }}
                                    @if($p['bank'])
                                        <div style="font-size:10.5px;color:var(--text3)">{{ $p['bank'] }}</div>
                                    @endif
                                </td>
                                <td class="num strong">{{ $fmt($p['montant']) }} <span style="font-size:10px;color:var(--text3);font-weight:500">FCFA</span></td>
                                <td style="color:var(--text2);font-size:12px;white-space:nowrap">{{ $p['creator_name'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr style="border-top:2px solid var(--border);background:var(--surface2)">
                            <td colspan="5" style="text-align:right;padding:12px 14px;font-weight:700;color:var(--text2)">Total des versements affichés</td>
                            <td class="num" style="padding:12px 14px;font-weight:800;color:var(--accent);font-size:14px">{{ $fmt($recentPayments->sum('montant')) }} FCFA</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>

<style>
.fin-encaissements-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
}
@media (max-width: 1100px) { .fin-encaissements-grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 720px)  { .fin-encaissements-grid { grid-template-columns: 1fr; } }
</style>

@push('scripts')
<script>
(function () {
    const ctx = document.getElementById('finChartEncaissements');
    if (!ctx) return;
    const data = window.financeBootstrap.series;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                label: 'Encaissé (FCFA)',
                data: data.map(d => d.montant),
                backgroundColor: 'rgba(34, 197, 94, 0.55)',
                borderColor: '#22c55e',
                borderWidth: 1.5,
                borderRadius: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => Math.round(ctx.parsed.y).toLocaleString('fr-FR') + ' FCFA',
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000) + 'k' : v),
                    },
                },
            },
        },
    });
})();
</script>
@endpush

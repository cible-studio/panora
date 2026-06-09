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

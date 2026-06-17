<x-admin-layout>
<x-slot name="title">Performance — {{ $user->name }}</x-slot>

<x-slot:topbarLeft>
    @if(in_array(auth()->user()->role->value, ['admin','mediaplanner'], true))
        <a href="{{ route('admin.performance.commercial.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Classement
        </a>
    @endif
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    @if(in_array(auth()->user()->role->value, ['admin','mediaplanner'], true))
        <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">⚙ Profil</a>
    @endif
</x-slot>

@php
    $fmt   = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtM  = fn ($n) => $n >= 1_000_000 ? number_format($n / 1_000_000, 1, ',', ' ') . ' M' : $fmt($n);
    $tauxCol = $kpis['taux_recouvrement'] >= 75 ? '#15803d' : ($kpis['taux_recouvrement'] >= 50 ? '#b45309' : '#b91c1c');
@endphp

<div class="perf-page">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,rgba(168,85,247,.10),rgba(99,102,241,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:60px;height:60px;border-radius:14px;background:rgba(168,85,247,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:28px;font-weight:800;color:#7c3aed">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:20px;font-weight:800;color:var(--text)">{{ $user->name }}</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:3px">
                <span style="font-family:monospace">{{ $user->agent_code }}</span> · Commercial
                · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
            </div>
        </div>
    </div>

    {{-- Filtres période — partial partagé (compact pour /show) Bloc 2 Famille A. --}}
    @include('admin.performance.partials._period_filters', [
        'action_route' => route('admin.performance.commercial.show', $user),
        'reset_route'  => route('admin.performance.commercial.show', $user),
        'from'         => $from,
        'to'           => $to,
        'preset'       => request('preset'),
        'compact'      => true,
    ])

    {{-- 6 KPI cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:16px">
        <div class="perf-kpi" style="border-left-color:var(--accent)">
            <div class="perf-kpi-label">CA TTC</div>
            <div class="perf-kpi-val" style="color:var(--accent)">{{ $fmtM($kpis['ca_ttc']) }}</div>
            <div class="perf-kpi-sub">HT : {{ $fmtM($kpis['ca_ht']) }} FCFA</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#16a34a">
            <div class="perf-kpi-label">Recouvrement</div>
            <div class="perf-kpi-val" style="color:{{ $tauxCol }}">{{ $kpis['taux_recouvrement'] }} %</div>
            <div class="perf-kpi-sub">{{ $fmtM($kpis['encaisse']) }} / {{ $fmtM($kpis['facture_periode']) }}</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#3b82f6">
            <div class="perf-kpi-label">Panier moyen</div>
            <div class="perf-kpi-val" style="color:#1d4ed8">{{ $fmtM($kpis['panier_moyen_ttc']) }}</div>
            <div class="perf-kpi-sub">{{ $kpis['nb_campagnes'] }} campagne(s)</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#f59e0b">
            <div class="perf-kpi-label">Délai paiement</div>
            <div class="perf-kpi-val" style="color:#b45309">{{ $kpis['delai_moyen_paiement_jours'] !== null ? $kpis['delai_moyen_paiement_jours'].' j' : '—' }}</div>
            <div class="perf-kpi-sub">moyenne factures soldées</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#ef4444">
            <div class="perf-kpi-label">Factures impayées</div>
            <div class="perf-kpi-val" style="color:#b91c1c">{{ $kpis['factures_impayees_count'] }}</div>
            <div class="perf-kpi-sub">reste dû : {{ $fmtM($kpis['reste_du']) }}</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#a855f7">
            <div class="perf-kpi-label">Comparaison N vs N-1</div>
            <div class="perf-kpi-val" style="color:{{ ($yearComp['delta_pct'] ?? 0) >= 0 ? '#15803d' : '#b91c1c' }}">
                {{ $yearComp['delta_pct'] === null ? '—' : ($yearComp['delta_pct'] >= 0 ? '+' : '') . $yearComp['delta_pct'] . ' %' }}
            </div>
            <div class="perf-kpi-sub">{{ $fmtM($yearComp['current']) }} vs {{ $fmtM($yearComp['previous']) }}</div>
        </div>
    </div>

    {{-- Graphique évolution CA mensuelle --}}
    <div class="perf-card" style="margin-bottom:16px">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">📈 Évolution mensuelle CA — 12 mois glissants</div>
                <div class="perf-card-sub">Source : campaigns.total_amount (TTC) regroupé par mois de start_date</div>
            </div>
        </div>
        <div style="padding:18px">
            <div style="position:relative;width:100%;height:280px">
                <canvas id="chart-perf-monthly" role="img" aria-label="Évolution CA mensuel"></canvas>
            </div>
        </div>
    </div>

    {{-- CA par secteur + Diversification --}}
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px;margin-bottom:16px">
        <div class="perf-card">
            <div class="perf-card-head">
                <div>
                    <div class="perf-card-title">🏷 CA par secteur d'activité</div>
                    <div class="perf-card-sub">Source : clients.sector · {{ $bySector->count() }} secteur(s) actif(s)</div>
                </div>
            </div>
            <div style="padding:18px">
                @if($bySector->isEmpty())
                    <div style="text-align:center;padding:30px;color:var(--text3);font-style:italic">Pas de CA sur la période.</div>
                @else
                    <div style="display:grid;gap:8px">
                        @foreach($bySector->take(8) as $s)
                            <div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px;font-size:12.5px">
                                    <span style="color:var(--text2);font-weight:500">{{ $s['sector'] }}</span>
                                    <span style="color:var(--text3);font-weight:700">{{ $fmtM($s['ca']) }} FCFA ({{ $s['pct'] }} %)</span>
                                </div>
                                <div style="height:6px;background:var(--surface2);border-radius:4px;overflow:hidden">
                                    <div style="height:100%;width:{{ $s['pct'] }}%;background:var(--accent);border-radius:4px"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="perf-card">
            <div class="perf-card-head">
                <div>
                    <div class="perf-card-title">🎯 Diversification portefeuille</div>
                    <div class="perf-card-sub">2 indicateurs complémentaires</div>
                </div>
            </div>
            <div style="padding:18px">
                <div style="text-align:center;margin-bottom:18px">
                    <div style="font-size:11px;color:var(--text3);text-transform:uppercase;font-weight:700;letter-spacing:.5px">Indice Herfindahl inversé</div>
                    <div style="font-size:42px;font-weight:800;color:{{ $diversification >= 0.7 ? '#15803d' : ($diversification >= 0.4 ? '#b45309' : '#b91c1c') }};line-height:1;margin-top:4px">{{ $diversification }}</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:4px">
                        {{ $diversification >= 0.7 ? 'Portefeuille équilibré' : ($diversification >= 0.4 ? 'Concentration modérée' : 'Forte concentration') }}
                    </div>
                </div>
                <div style="padding:10px 12px;background:var(--surface2);border-radius:8px;font-size:12px">
                    <div style="font-weight:700;color:var(--text2);margin-bottom:4px">Top client</div>
                    @if($topClient['client_id'])
                        <div style="font-size:13px;color:var(--text);font-weight:700">{{ $topClient['client_name'] }}</div>
                        <div style="color:var(--text3);margin-top:2px">{{ $fmtM($topClient['ca']) }} FCFA · {{ $topClient['pct'] }} % du CA</div>
                    @else
                        <div style="color:var(--text3);font-style:italic">Aucun client sur la période.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tableau campagnes --}}
    <div class="perf-card">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">📋 Campagnes de ce commercial</div>
                <div class="perf-card-sub">{{ $campaigns->total() }} total · page {{ $campaigns->currentPage() }}/{{ $campaigns->lastPage() }}</div>
            </div>
        </div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Secteur</th>
                        <th>Statut</th>
                        <th>Du</th>
                        <th>Au</th>
                        <th style="text-align:right">Montant TTC</th>
                        <th style="text-align:right">Encaissé</th>
                        <th style="text-align:right">Reste</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $c)
                        @php
                            $totalFact   = $c->invoices->sum('total_a_payer');
                            $encaisseCmp = $c->invoices->reduce(fn ($s, $i) => $s + ($i->total_a_payer - $i->remainingAmount()), 0);
                            $reste       = $totalFact - $encaisseCmp;
                        @endphp
                        <tr>
                            <td><a href="{{ route('admin.campaigns.show', $c) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $c->name }}</a></td>
                            <td style="color:var(--text2)">{{ $c->client?->name ?? '—' }}</td>
                            <td style="font-size:11.5px;color:var(--text3)">{{ $c->client?->sector ?? '—' }}</td>
                            <td><span style="padding:2px 8px;border-radius:999px;font-size:10.5px;font-weight:700;background:var(--surface2);color:var(--text2)">{{ $c->status->label() ?? $c->status }}</span></td>
                            <td style="color:var(--text3);font-size:12px">{{ $c->start_date?->format('d/m/Y') }}</td>
                            <td style="color:var(--text3);font-size:12px">{{ $c->end_date?->format('d/m/Y') }}</td>
                            <td style="text-align:right;font-family:monospace;font-weight:700">{{ $fmtM($c->total_amount) }}</td>
                            <td style="text-align:right;font-family:monospace;color:#15803d">{{ $fmtM($encaisseCmp) }}</td>
                            <td style="text-align:right;font-family:monospace;color:{{ $reste > 0 ? '#b91c1c' : 'var(--text3)' }}">{{ $fmtM($reste) }}</td>
                            <td style="text-align:right"><a href="{{ route('admin.campaigns.show', $c) }}" style="color:var(--accent);text-decoration:none;font-size:11.5px">Ouvrir →</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3);font-style:italic">Aucune campagne sur la période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())
            <div style="padding:14px 18px;border-top:1px solid var(--border);display:flex;justify-content:flex-end">
                {{ $campaigns->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>

<style>
.perf-page input[type="date"] { height: 38px; padding: 0 10px; background: var(--surface2); border: 1px solid var(--border); border-radius: 8px; color: var(--text); font-size: 13px; font-family: inherit; outline: none; box-sizing: border-box; }
.perf-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.perf-kpi { background: var(--surface); border: 1px solid var(--border); border-left: 4px solid; border-radius: 14px; padding: 14px 18px; }
.perf-kpi-label { font-size: 10.5px; font-weight: 800; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; }
.perf-kpi-val { font-size: 22px; font-weight: 800; color: var(--text); margin-top: 2px; }
.perf-kpi-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.perf-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.perf-card-head { padding: 14px 18px; border-bottom: 1px solid var(--border); background: var(--surface2); }
.perf-card-title { font-size: 14px; font-weight: 800; color: var(--text); }
.perf-card-sub { font-size: 11.5px; color: var(--text3); margin-top: 3px; }
.perf-card-body--flush { padding: 0; overflow-x: auto; }
.perf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.perf-table th { text-align: left; padding: 10px 14px; background: var(--surface2); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); border-bottom: 1px solid var(--border); }
.perf-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
.perf-table tr:hover td { background: rgba(232,160,32,.04); }
</style>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function () {
    const data = @json($monthlyTrend->values());
    const canvas = document.getElementById('chart-perf-monthly');
    if (!canvas || !data?.length || typeof Chart === 'undefined') return;
    const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
    const gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
    const tickC = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map(d => d.label),
            datasets: [{
                label: 'CA TTC',
                data:  data.map(d => d.ca),
                borderColor: '#e8a020',
                backgroundColor: 'rgba(232,160,32,.18)',
                borderWidth: 2.5, tension: .35, fill: true,
                pointBackgroundColor: '#e8a020', pointRadius: 4, pointHoverRadius: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => ' ' + new Intl.NumberFormat('fr-FR').format(Math.round(ctx.parsed.y)) + ' FCFA' } },
            },
            scales: {
                x: { ticks: { color: tickC, font: { size: 11 } }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: tickC, font: { size: 11 }, callback: v => v >= 1_000_000 ? (v/1_000_000).toFixed(1) + 'M' : v }, grid: { color: gridC } },
            }
        }
    });
})();
</script>
@endpush

</x-admin-layout>

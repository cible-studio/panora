<x-admin-layout>
<x-slot name="title">Performance commerciale</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.migration.commercial-attribution') }}" class="btn btn-ghost btn-sm">⚙ Attribuer campagnes</a>
    <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm">📊 Rapports</a>
</x-slot>

@php
    // Helper format FCFA — réutilisé partout dans la vue
    $fmt = fn ($n) => number_format((float) $n, 0, ',', ' ');
    $fmtM = fn ($n) => $n >= 1_000_000 ? number_format($n / 1_000_000, 1, ',', ' ') . ' M' : $fmt($n);
@endphp

<div class="perf-page">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,rgba(168,85,247,.10),rgba(99,102,241,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(168,85,247,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px">📈</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">Performance commerciale</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                Classement des commerciaux · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
                ({{ (int)$from->diffInDays($to) + 1 }} jours)
            </div>
        </div>
    </div>

    {{-- Filtres période — preset OU range custom (mutuellement exclusifs).
         Quand on choisit un preset, on vide from/to ; quand on saisit
         une date, on vide le preset. Sinon le serveur a 2 sources de
         vérité conflictuelles et le filtre date avait l'air de "ne pas
         marcher" sans qu'on comprenne pourquoi. --}}
    @php $usingCustomRange = request()->filled('from') || request()->filled('to'); @endphp
    <form method="GET" class="perf-filter-card" style="margin-bottom:16px">
        <div style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
            <div class="fne-field" style="min-width:160px">
                <label>Période rapide</label>
                <select name="preset"
                        onchange="this.form.querySelector('[name=from]').value='';this.form.querySelector('[name=to]').value='';this.form.submit()">
                    <option value=""        {{ $usingCustomRange ? 'selected' : '' }} disabled hidden>— Personnalisé —</option>
                    <option value="year"    {{ !$usingCustomRange && ($preset ?? 'year') === 'year'    ? 'selected' : '' }}>Cette année</option>
                    <option value="quarter" {{ !$usingCustomRange && ($preset ?? '') === 'quarter' ? 'selected' : '' }}>Ce trimestre</option>
                    <option value="month"   {{ !$usingCustomRange && ($preset ?? '') === 'month'   ? 'selected' : '' }}>Ce mois</option>
                    <option value="all"     {{ !$usingCustomRange && ($preset ?? '') === 'all'     ? 'selected' : '' }}>Tout</option>
                </select>
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Du</label>
                <input type="date" name="from" value="{{ request('from', $usingCustomRange ? $from->format('Y-m-d') : '') }}"
                       onchange="this.form.querySelector('[name=preset]').value='';this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Au</label>
                <input type="date" name="to" value="{{ request('to', $usingCustomRange ? $to->format('Y-m-d') : '') }}"
                       onchange="this.form.querySelector('[name=preset]').value='';this.form.submit()">
            </div>
            @if($usingCustomRange)
                <a href="{{ route('admin.performance.commercial.index') }}" class="btn btn-ghost btn-sm" style="height:38px;display:inline-flex;align-items:center" title="Revenir aux périodes rapides">↺ Réinitialiser</a>
            @endif
        </div>
    </form>

    {{-- KPI globaux du leaderboard --}}
    @php
        $totalCaHt   = $leaderboard->sum('ca_ht');
        $totalCa     = $leaderboard->sum('ca_ttc');
        $totalEnc    = $leaderboard->sum('encaisse');
        $tauxMoyen   = $totalCa > 0 ? round($totalEnc / $totalCa * 100, 1) : 0;
        $panierMoyen = $leaderboard->avg('panier_moyen');
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:18px">
        <div class="perf-kpi" style="border-left-color:#e8a020">
            <div class="perf-kpi-label">CA HT équipe</div>
            <div class="perf-kpi-val" style="color:#c97d10">{{ $fmtM($totalCaHt) }} <span style="font-size:12px;color:var(--text3)">FCFA</span></div>
            <div class="perf-kpi-sub">net_ht des factures émises</div>
        </div>
        <div class="perf-kpi" style="border-left-color:var(--accent)">
            <div class="perf-kpi-label">CA TTC équipe</div>
            <div class="perf-kpi-val">{{ $fmtM($totalCa) }} <span style="font-size:12px;color:var(--text3)">FCFA</span></div>
            <div class="perf-kpi-sub">total_amount des campagnes</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#16a34a">
            <div class="perf-kpi-label">Taux recouvrement moyen</div>
            <div class="perf-kpi-val" style="color:#15803d">{{ $tauxMoyen }} %</div>
            <div class="perf-kpi-sub">encaissé / facturé période</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#3b82f6">
            <div class="perf-kpi-label">Panier moyen équipe</div>
            <div class="perf-kpi-val" style="color:#1d4ed8">{{ $fmtM((int) $panierMoyen) }}</div>
            <div class="perf-kpi-sub">FCFA / campagne</div>
        </div>
        <div class="perf-kpi" style="border-left-color:#a855f7">
            <div class="perf-kpi-label">Commerciaux actifs</div>
            <div class="perf-kpi-val" style="color:#7c3aed">{{ $leaderboard->count() }}</div>
            <div class="perf-kpi-sub">role=commercial · is_active</div>
        </div>
    </div>

    {{-- ════════ COURBE GLOBALE — évolution CA équipe sur 12 mois ════════
         Donne le tempo global de la régie avant le drill-down par
         commercial. La sub-curve "Campagnes" permet de distinguer un
         mois "gros tickets" (peu de campagnes, gros CA) d'un mois
         "volume" (beaucoup de petites campagnes). --}}
    <div class="perf-card" style="margin-bottom:18px">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">📈 Performance globale équipe (12 derniers mois)</div>
                <div class="perf-card-sub">Évolution mensuelle du CA équipe et du nombre de campagnes</div>
            </div>
            @php
                $trendTotalCa   = $globalTrend->sum('ca');
                $trendTotalCaHt = $globalTrend->sum('ca_ht');
                $trendTotalCnt  = $globalTrend->sum('count');
            @endphp
            <div style="font-size:11.5px;color:var(--text3);text-align:right;line-height:1.5">
                <div>CA TTC 12 mois : <strong style="color:var(--accent);font-size:13px">{{ number_format($trendTotalCa, 0, ',', ' ') }}</strong></div>
                <div>CA HT 12 mois : <strong style="color:#c97d10;font-size:13px">{{ number_format($trendTotalCaHt, 0, ',', ' ') }}</strong></div>
                <div style="font-size:10px;color:var(--text3)">{{ $trendTotalCnt }} campagnes</div>
            </div>
        </div>
        <div style="padding:18px 20px">
            <div style="position:relative;height:280px">
                <canvas id="perfGlobalTrendChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Tableau leaderboard --}}
    <div class="perf-card">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">🏆 Classement</div>
                <div class="perf-card-sub">{{ $leaderboard->count() }} commercial(ux) — ordonné par CA TTC</div>
            </div>
        </div>
        <div class="perf-card-body--flush">
            <table class="perf-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Commercial</th>
                        <th style="text-align:right">CA HT</th>
                        <th style="text-align:right">CA TTC</th>
                        <th style="text-align:right">Encaissé</th>
                        <th style="text-align:right">Reste dû</th>
                        <th style="text-align:right">Taux</th>
                        <th style="text-align:right">Panier moyen</th>
                        <th style="text-align:right">Campagnes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leaderboard as $idx => $row)
                        @php
                            $tauxCol = $row['taux_recouvrement'] >= 75 ? '#15803d'
                                       : ($row['taux_recouvrement'] >= 50 ? '#b45309' : '#b91c1c');
                            $medals = ['🥇','🥈','🥉'];
                            $rank   = $medals[$idx] ?? ($idx + 1);
                        @endphp
                        <tr>
                            <td style="font-weight:800;color:var(--text)">{{ $rank }}</td>
                            <td>
                                <div style="font-weight:700;color:var(--text)">{{ $row['user']->name }}</div>
                                <div style="font-size:10.5px;color:var(--text3);font-family:monospace">{{ $row['user']->agent_code }}</div>
                            </td>
                            <td style="text-align:right;font-family:monospace">{{ $fmtM($row['ca_ht']) }}</td>
                            <td style="text-align:right;font-family:monospace;font-weight:800;color:var(--accent)">{{ $fmtM($row['ca_ttc']) }}</td>
                            <td style="text-align:right;font-family:monospace;color:#15803d">{{ $fmtM($row['encaisse']) }}</td>
                            <td style="text-align:right;font-family:monospace;color:{{ $row['reste_du'] > 0 ? '#b91c1c' : 'var(--text3)' }}">{{ $fmtM($row['reste_du']) }}</td>
                            <td style="text-align:right;font-weight:700;color:{{ $tauxCol }}">{{ $row['taux_recouvrement'] }} %</td>
                            <td style="text-align:right;font-family:monospace;color:var(--text2)">{{ $fmtM($row['panier_moyen']) }}</td>
                            <td style="text-align:right;font-weight:700">{{ $row['nb_campagnes'] }}</td>
                            <td style="text-align:right;white-space:nowrap">
                                <a href="{{ route('admin.performance.commercial.show', $row['user']) }}"
                                   class="btn btn-ghost btn-sm" style="font-size:11px">Détail →</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="text-align:center;padding:40px;color:var(--text3);font-style:italic">Aucun commercial actif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ════════ TOP COMMERCIAL PAR SECTEUR D'ACTIVITÉ ════════
         Vue "qui domine quoi" — pour chaque secteur client présent
         dans la période, le commercial avec le plus de CA. Trié par
         CA total du secteur (les plus porteurs en haut). --}}
    <div class="perf-card" style="margin-top:18px">
        <div class="perf-card-head">
            <div>
                <div class="perf-card-title">🏆 Top commercial par secteur d'activité</div>
                <div class="perf-card-sub">{{ $topBySector->count() }} secteur(s) actif(s) sur la période — qui domine quoi</div>
            </div>
        </div>
        @if($topBySector->isEmpty())
            <div style="padding:40px;text-align:center;color:var(--text3);font-style:italic">
                Aucune campagne attribuée à un commercial sur la période.
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="perf-table">
                    <thead>
                        <tr>
                            <th>Secteur d'activité</th>
                            <th>Top commercial</th>
                            <th class="num">CA du top</th>
                            <th class="num">Campagnes</th>
                            <th class="num">Part du secteur</th>
                            <th class="num">CA total secteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topBySector as $row)
                            @php
                                $sharePct = $row['share_pct'];
                                $shareColor = $sharePct >= 80 ? '#ef4444' : ($sharePct >= 50 ? '#f97316' : '#22c55e');
                                $shareLabel = $sharePct >= 80 ? 'Monopole' : ($sharePct >= 50 ? 'Majoritaire' : 'Partagé');
                            @endphp
                            <tr>
                                <td style="font-weight:700;color:var(--text)">{{ $row['sector'] }}</td>
                                <td>
                                    <a href="{{ route('admin.performance.commercial.show', $row['commercial_id']) }}"
                                       style="color:var(--accent);text-decoration:none;font-weight:600">
                                        {{ $row['commercial_name'] }}
                                    </a>
                                </td>
                                <td class="num" style="font-weight:800;color:var(--accent);font-variant-numeric:tabular-nums">
                                    {{ number_format($row['ca'], 0, ',', ' ') }}
                                    <span style="font-size:10px;color:var(--text3);font-weight:500">FCFA</span>
                                </td>
                                <td class="num">{{ $row['count'] }}</td>
                                <td class="num">
                                    <span style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $shareColor }}1f;color:{{ $shareColor }}"
                                          title="{{ $shareLabel }} sur ce secteur">
                                        {{ rtrim(rtrim(number_format($sharePct, 1, ',', ''), '0'), ',') }}%
                                    </span>
                                </td>
                                <td class="num" style="color:var(--text2);font-variant-numeric:tabular-nums">
                                    {{ number_format($row['sector_total_ca'], 0, ',', ' ') }}
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
.perf-page select, .perf-page input[type="date"] {
    height: 38px; width: 100%; padding: 0 10px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-size: 13px;
    font-family: inherit; outline: none; box-sizing: border-box;
}
.perf-page select { padding-right: 28px; cursor: pointer; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 8px center;
}
.perf-filter-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 14px 18px; }
.perf-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.perf-kpi { background: var(--surface); border: 1px solid var(--border); border-left: 4px solid; border-radius: 14px; padding: 14px 18px; }
.perf-kpi-label { font-size: 10.5px; font-weight: 800; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; }
.perf-kpi-val { font-size: 24px; font-weight: 800; color: var(--text); margin-top: 2px; }
.perf-kpi-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.perf-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.perf-card-head { padding: 14px 18px; border-bottom: 1px solid var(--border); background: var(--surface2); display:flex; align-items:center; justify-content:space-between; gap:12px; }
.perf-card-title { font-size: 14px; font-weight: 800; color: var(--text); }
.perf-card-sub { font-size: 11.5px; color: var(--text3); margin-top: 3px; }
.perf-card-body--flush { padding: 0; overflow-x: auto; }
.perf-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.perf-table th { text-align: left; padding: 10px 14px; background: var(--surface2); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); border-bottom: 1px solid var(--border); }
.perf-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; }
.perf-table tr:hover td { background: rgba(232,160,32,.04); }
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('perfGlobalTrendChart');
    if (!ctx) return;
    const data = @json($globalTrend);
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => d.label),
            datasets: [
                {
                    label: 'CA TTC (FCFA)',
                    data: data.map(d => d.ca),
                    borderColor: '#e8a020',
                    backgroundColor: 'rgba(232, 160, 32, 0.12)',
                    borderWidth: 2.5,
                    tension: 0.35,
                    fill: true,
                    yAxisID: 'y',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#e8a020',
                },
                {
                    label: 'CA HT (FCFA)',
                    data: data.map(d => d.ca_ht),
                    borderColor: '#c97d10',
                    backgroundColor: 'rgba(201, 125, 16, 0.08)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#c97d10',
                },
                {
                    label: 'Campagnes',
                    data: data.map(d => d.count),
                    borderColor: '#a855f7',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    tension: 0.35,
                    fill: false,
                    yAxisID: 'y2',
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#a855f7',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 14, font: { size: 12 } },
                },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            const isCount = ctx.dataset.yAxisID === 'y2';
                            const val = ctx.parsed.y;
                            return ctx.dataset.label + ' : '
                                + (isCount
                                    ? val + ' campagne' + (val > 1 ? 's' : '')
                                    : Math.round(val).toLocaleString('fr-FR') + ' FCFA');
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: { display: true, text: 'CA HT / TTC (FCFA)', color: '#e8a020', font: { weight: 700 } },
                    ticks: {
                        callback: v => v >= 1000000 ? (v / 1000000).toFixed(1) + 'M' : (v >= 1000 ? (v / 1000) + 'k' : v),
                    },
                },
                y2: {
                    beginAtZero: true,
                    position: 'right',
                    title: { display: true, text: 'Nb campagnes', color: '#a855f7', font: { weight: 700 } },
                    grid: { drawOnChartArea: false },
                    ticks: { stepSize: 1, precision: 0 },
                },
            },
        },
    });
})();
</script>
@endpush

</x-admin-layout>

<x-admin-layout>
<x-slot name="title">Tableau de bord financier</x-slot>

<x-slot:topbarActions>
    <a href="{{ route('admin.finance.relances') }}" class="btn btn-ghost btn-sm">
        📋 Historique des relances
    </a>
</x-slot>

@php
    $tab = request('tab', 'encaissements');
    $period = request('period', '');
    $isCustom = request()->filled('from') && request()->filled('to');
    $fmt = fn($v) => number_format($v, 0, ',', ' ');
@endphp

{{-- ════ BARRE DE PÉRIODE / FILTRES ════ --}}
<form method="GET" class="fin-filter-card">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="fin-filter-bar">
        <div class="fin-period-pills">
            @php
                $periods = [
                    'today'        => "Aujourd'hui",
                    'this_week'    => 'Cette semaine',
                    'this_month'   => 'Ce mois',
                    'this_quarter' => 'Ce trimestre',
                    'this_year'    => 'Cette année',
                    'last_90'      => '90 jours',
                ];
                $activePeriod = !$isCustom && $period !== '' ? $period : (!$isCustom ? 'last_30' : '');
            @endphp
            @foreach($periods as $key => $label)
                <a href="{{ route('admin.finance.index', ['tab' => $tab, 'period' => $key]) }}"
                   class="fin-period-pill {{ $activePeriod === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('admin.finance.index', ['tab' => $tab]) }}"
               class="fin-period-pill {{ !$period && !$isCustom ? 'is-active' : '' }}">30 jours</a>
        </div>

        <div class="fin-custom-range">
            <input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}">
            <span>→</span>
            <input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}">
            <button type="submit" class="btn btn-primary btn-sm">Appliquer</button>
        </div>
    </div>
    <div class="fin-period-info">
        Période : <strong>{{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}</strong>
        @if($isCommercialView)
            · <span style="color:#3b82f6">🔒 Vue scopée à vos dossiers</span>
        @endif
    </div>
</form>

{{-- ════ KPI CARDS ════ --}}
<div class="fin-kpi-grid">
    <div class="kpi-card" style="--kpi-color:#22c55e">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e">💵</div>
        <div class="kpi-card__value" style="color:#22c55e;font-size:20px">{{ $fmt($kpis['encaisse']) }}</div>
        <div class="kpi-card__label">Encaissé</div>
        <div class="kpi-card__sub">sur la période · FCFA</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#f97316">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316">⏳</div>
        <div class="kpi-card__value" style="color:#f97316;font-size:20px">{{ $fmt($kpis['du']) }}</div>
        <div class="kpi-card__label">Total dû</div>
        <div class="kpi-card__sub">toutes factures actives · FCFA</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#ef4444">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444">🔴</div>
        <div class="kpi-card__value" style="color:#ef4444;font-size:20px">{{ $fmt($kpis['en_retard']) }}</div>
        <div class="kpi-card__label">En retard</div>
        <div class="kpi-card__sub">échéance dépassée · FCFA</div>
    </div>
    <div class="kpi-card" style="--kpi-color:#3b82f6">
        <div class="kpi-card__top-bar" style="background:#3b82f6"></div>
        <div class="kpi-card__icon" style="color:#3b82f6">📊</div>
        <div class="kpi-card__value" style="color:#3b82f6;font-size:20px">{{ $kpis['taux_recouvrement'] }}%</div>
        <div class="kpi-card__label">Taux de recouvrement</div>
        <div class="kpi-card__sub">encaissé ÷ facturé période</div>
    </div>
</div>

{{-- ════ ONGLETS ════ --}}
<div class="fin-tabs">
    @php
        $tabs = [
            'encaissements' => ['💵', 'Encaissements'],
            'creances'      => ['📉', 'Créances'],
            'recouvrement'  => ['🔁', 'Recouvrement'],
        ];
    @endphp
    @foreach($tabs as $key => [$ico, $label])
        <a href="{{ route('admin.finance.index', array_merge(request()->query(), ['tab' => $key])) }}"
           class="fin-tab {{ $tab === $key ? 'is-active' : '' }}">{{ $ico }} {{ $label }}</a>
    @endforeach
</div>

<div class="fin-tab-content">
    @switch($tab)
        @case('creances')
            @include('admin.finance.partials.creances', compact('aging', 'creances', 'fmt'))
            @break
        @case('recouvrement')
            @include('admin.finance.partials.recouvrement', compact('clientsToFollow', 'clientsList', 'fmt'))
            @break
        @default
            @include('admin.finance.partials.encaissements', compact('series', 'topClients', 'byCommune', 'byCommercial', 'fmt'))
    @endswitch
</div>

<style>
/* ═════════════ FINANCE DASHBOARD ═════════════ */
.fin-filter-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 14px 18px;
    margin-bottom: 16px;
}
.fin-filter-bar { display: flex; flex-wrap: wrap; gap: 14px; align-items: center; justify-content: space-between; }
.fin-period-pills { display: flex; flex-wrap: wrap; gap: 6px; }
.fin-period-pill {
    display: inline-flex; align-items: center;
    padding: 6px 12px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 999px;
    color: var(--text2);
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    transition: all .15s;
}
.fin-period-pill:hover { border-color: var(--text3); color: var(--text); }
.fin-period-pill.is-active { background: var(--accent); border-color: var(--accent); color: #fff; }
.fin-custom-range { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.fin-custom-range input[type="date"] {
    height: 34px; padding: 0 10px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 12.5px;
    color: var(--text);
}
.fin-period-info { font-size: 12px; color: var(--text3); margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); }

.fin-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
@media (max-width: 900px) { .fin-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

.fin-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid var(--border);
    margin-bottom: 18px;
    overflow-x: auto;
}
.fin-tab {
    padding: 10px 18px;
    color: var(--text2);
    font-size: 13.5px;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all .15s;
    white-space: nowrap;
}
.fin-tab:hover { color: var(--text); border-bottom-color: var(--text3); }
.fin-tab.is-active { color: var(--accent); border-bottom-color: var(--accent); font-weight: 800; }

.fin-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 16px;
    overflow: hidden;
}
.fin-card-head {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    background: var(--surface2);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.fin-card-title { font-size: 14px; font-weight: 800; color: var(--text); }
.fin-card-sub   { font-size: 11px; color: var(--text3); margin-top: 2px; }
.fin-card-body  { padding: 16px 18px; }
.fin-card-body--flush { padding: 0; }

.fin-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.fin-table th {
    text-align: left;
    padding: 10px 14px;
    background: var(--surface2);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--text3);
    border-bottom: 1px solid var(--border);
}
.fin-table th.num { text-align: right; }
.fin-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); }
.fin-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
.fin-table tr:last-child td { border-bottom: none; }
.fin-table tr:hover td { background: rgba(232, 160, 32, .04); }
.fin-table .strong { font-weight: 800; color: var(--accent); }

.fin-empty {
    padding: 30px 18px;
    text-align: center;
    color: var(--text3);
    font-size: 13px;
    background: var(--surface2);
    border-radius: 10px;
}
</style>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
window.financeBootstrap = {
    series: @json($series),
    seriesUrl: "{{ route('admin.finance.series') }}",
    period: "{{ request('period', '') }}",
    from: "{{ $from->format('Y-m-d') }}",
    to: "{{ $to->format('Y-m-d') }}",
};
</script>
@endpush

</x-admin-layout>

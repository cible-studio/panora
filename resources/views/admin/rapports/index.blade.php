<x-admin-layout title="Rapports & Analyses">

{{-- ════ DONNÉES SERVEUR ════ --}}
<script>
window.__RPT__ = {
    ajaxUrl:       '{{ route("admin.rapports.ajax") }}',
    csrf:          '{{ csrf_token() }}',
    occParCommune: {!! json_encode($occParCommune->values()) !!},
    evolMensuelle: {!! json_encode($evolMensuelle->values()) !!},
    caMensuel:     {!! json_encode($caMensuel->values()) !!},
    tableauMensuel:{!! json_encode($tableauMensuel->values()) !!},
    topClients:    {!! json_encode($topClients->values()) !!},
    statsCommunes: {!! json_encode($statsCommunes->values()) !!},
    annee:         {{ $annee }},
    moisDu:        {{ $moisDu }},
    moisAu:        {{ $moisAu }},
    // Données pour les nouveaux graphiques Chart.js
    occupationTrend: {!! json_encode($occupationTrend->values()) !!},
    topPanels:       {!! json_encode($topPanels->values()) !!},
    cancelReasons:   {!! json_encode($cancelReasons->values()) !!},
    revenueByMonth:  {!! json_encode($revenueByMonth->values()) !!},
    inactivityBucket:{!! json_encode($inactivityBucket) !!},
    parcByCommune:   {!! json_encode($parcByCommune->values()) !!},
    // Données COMMIT B : corrélation, CA par ville
    occVsRevenue:    {!! json_encode($occVsRevenue->values()) !!},
    revenueByCity:   {!! json_encode($revenueByCity->values()) !!},
    revenueByCommune:{!! json_encode($revenueByCommune->values()) !!},
};
</script>

{{-- ════ FILTRES AVANCÉS (presets + dates custom + filtres) ════ --}}
<form id="form-periode" method="GET" action="{{ route('admin.rapports.index') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-bottom:20px">

    {{-- Ligne 1 : Presets période ───────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:12px;">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Période rapide
        </span>
        @foreach([
            'today'   => "Aujourd'hui",
            'week'    => 'Cette semaine',
            'month'   => 'Ce mois',
            'quarter' => 'Ce trimestre',
            'year'    => 'Cette année',
            'all'     => 'Tout',
        ] as $key => $label)
            <a href="{{ route('admin.rapports.index', array_merge(request()->except(['preset','from','to','annee','mois_du','mois_au']), ['preset' => $key])) }}"
               style="padding:6px 12px;font-size:11px;font-weight:600;border-radius:8px;text-decoration:none;border:1px solid {{ $currentPreset === $key ? 'var(--accent)' : 'var(--border)' }};background:{{ $currentPreset === $key ? 'var(--accent)' : 'var(--surface2)' }};color:{{ $currentPreset === $key ? '#fff' : 'var(--text2)' }};">
                {{ $label }}
            </a>
        @endforeach
        <span style="color:var(--border);">|</span>
        <input type="date" name="from" value="{{ $dateFrom->format('Y-m-d') }}" onchange="this.form.submit()"
               style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text)">
        <span style="color:var(--text3);font-size:12px">→</span>
        <input type="date" name="to" value="{{ $dateTo->format('Y-m-d') }}" onchange="this.form.submit()"
               style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text)">
        <span style="font-size:11px;color:var(--text3);margin-left:auto;">
            {{ $dateFrom->format('d/m/Y') }} → {{ $dateTo->format('d/m/Y') }}
            ({{ (int) $dateFrom->diffInDays($dateTo) + 1 }} jours)
        </span>
    </div>

    {{-- Ligne 2 : Filtres dimensionnels ────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filtres
        </span>
        <select name="filter_commune_id" onchange="this.form.submit()"
                style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);min-width:140px;">
            <option value="">Toutes communes</option>
            @foreach($allCommunes as $c)
                <option value="{{ $c->id }}" {{ $filterCommune == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="filter_city" onchange="this.form.submit()"
                style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);min-width:120px;">
            <option value="">Toutes villes</option>
            @foreach($allCities as $city)
                <option value="{{ $city }}" {{ $filterCity == $city ? 'selected' : '' }}>{{ $city }}</option>
            @endforeach
        </select>
        <select name="filter_client_id" onchange="this.form.submit()"
                style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);min-width:140px;">
            <option value="">Tous clients</option>
            @foreach($allClients as $c)
                <option value="{{ $c->id }}" {{ $filterClient == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="filter_category_id" onchange="this.form.submit()"
                style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);min-width:140px;">
            <option value="">Tous types de panneau</option>
            @foreach($allCategories as $cat)
                <option value="{{ $cat->id }}" {{ $filterCategory == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
            @endforeach
        </select>
        @if($filterCommune || $filterCity || $filterClient || $filterCategory || $currentPreset)
            <a href="{{ route('admin.rapports.index') }}"
               style="font-size:11px;color:var(--text3);text-decoration:underline;margin-left:8px;">
                ✕ Réinitialiser
            </a>
        @endif
        <div style="margin-left:auto;font-size:11px;color:var(--text3)">
            {{ number_format($totalPanneaux) }} panneaux ·
            {{ number_format($totalClients) }} clients ·
            {{ number_format($totalCampagnes) }} campagnes
        </div>
    </div>
</form>

{{-- ════ RAPPORTS DÉTAILLÉS — RACCOURCIS ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:20px">
    <a href="{{ route('admin.rapports.campagnes') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #fab80b;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(250,184,11,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fab80b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport campagnes</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Performance · motifs d'annulation · top</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    <a href="{{ route('admin.rapports.annulations') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #ef4444;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(239,68,68,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport annulations</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Détail des campagnes annulées</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>

    <a href="{{ route('admin.rapports.taxes') }}"
       style="display:flex;align-items:center;gap:12px;padding:14px 16px;background:var(--surface);border:1px solid var(--border);border-left:4px solid #8b5cf6;border-radius:12px;text-decoration:none;transition:transform .15s,border-color .15s,box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.08)'"
       onmouseleave="this.style.transform='';this.style.boxShadow=''">
        <div style="width:38px;height:38px;border-radius:10px;background:rgba(139,92,246,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:700;color:var(--text)">Rapport taxes</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">Suivi des taxes communales</div>
        </div>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
</div>

{{-- ════ CARDS KPI CLIQUABLES ════ --}}
@php
$kpiCards = [
    [
        'id'    => 'occupation',
        'label' => "Taux d'occupation",
        'val'   => $occupation['taux'] . '%',
        'sub'   => $occupation['occupes'] . ' panneaux occupés',
        'color' => '#e8a020',
        'tab'   => 'occupation',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>',
    ],
    [
        'id'    => 'libres',
        'label' => 'Panneaux disponibles',
        'val'   => number_format($occupation['libres']),
        'sub'   => 'sur ' . number_format($occupation['total']) . ' au total',
        'color' => '#22c55e',
        'tab'   => 'occupation',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
    ],
    [
        'id'    => 'ca',
        'label' => 'CA période',
        'val'   => number_format($caTotal / 1000000, 1) . 'M',
        'sub'   => 'FCFA · ' . number_format($totalCampagnes) . ' campagnes',
        'color' => '#3b82f6',
        'tab'   => 'ca',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
    ],
    [
        'id'    => 'clients',
        'label' => 'Clients actifs',
        'val'   => number_format($totalClients),
        'sub'   => 'dans le portefeuille',
        'color' => '#a855f7',
        'tab'   => 'clients',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
    ],
    [
        'id'    => 'maintenance',
        'label' => 'En maintenance',
        'val'   => number_format($occupation['maintenance']),
        'sub'   => 'panneaux indisponibles',
        'color' => '#6b7280',
        'tab'   => 'zones',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    ],
    [
        'id'    => 'decaper',
        'label' => 'À décaper (30j)',
        'val'   => number_format($aDecaper->count()),
        'sub'   => 'fins de campagne proches',
        'color' => $aDecaper->count() > 0 ? '#ef4444' : '#22c55e',
        'tab'   => 'zones',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ],
];
@endphp

<div id="kpi-cards" style="display:grid;grid-template-columns:repeat(6,1fr);gap:10px;margin-bottom:20px">
    @foreach($kpiCards as $card)
    <button type="button"
            id="kpi-{{ $card['id'] }}"
            onclick="RPT.clickCard('{{ $card['id'] }}', '{{ $card['tab'] }}')"
            style="background:var(--surface);border:2px solid var(--border);border-radius:14px;padding:16px 14px;
                   cursor:pointer;text-align:left;transition:all .2s;position:relative;overflow:hidden"
            onmouseenter="if(!this.classList.contains('kpi-active')){this.style.borderColor='{{ $card['color'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.2)'}"
            onmouseleave="if(!this.classList.contains('kpi-active')){this.style.borderColor='var(--border)';this.style.transform='';this.style.boxShadow=''}">

        <div style="position:absolute;top:0;left:0;right:0;height:3px;background:{{ $card['color'] }};border-radius:14px 14px 0 0"></div>
        <div style="color:{{ $card['color'] }};margin-bottom:10px;opacity:.9">{!! $card['icon'] !!}</div>
        <div style="font-size:22px;font-weight:800;color:{{ $card['color'] }};line-height:1;margin-bottom:4px">{{ $card['val'] }}</div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:3px">{{ $card['label'] }}</div>
        <div style="font-size:10px;color:var(--text3);line-height:1.3">{{ $card['sub'] }}</div>
        <div style="position:absolute;bottom:10px;right:12px;color:{{ $card['color'] }};opacity:.35;font-size:14px">→</div>
    </button>
    @endforeach
</div>

{{-- ════ ONGLETS ════ --}}
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:6px">
    @php
    $onglets = [
        ['id'=>'occupation','icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>','label'=>"Occupation"],
        ['id'=>'panneaux',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>','label'=>'Performance panneaux'],
        ['id'=>'periodes',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>','label'=>'Périodes'],
        ['id'=>'ca',        'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>','label'=>'CA & Revenus'],
        ['id'=>'zones',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>','label'=>'Zones & Communes'],
        ['id'=>'clients',   'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>','label'=>'Clients'],
        ['id'=>'decap',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>','label'=>'Décappages'],
        ['id'=>'insights',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h.01M15 11h.01M18 21l-3-3H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-3l-3 3z"/></svg>','label'=>'Insights & Alertes'],
    ];
    @endphp
    @foreach($onglets as $o)
    <button id="tab-{{ $o['id'] }}" onclick="RPT.switchTab('{{ $o['id'] }}')"
            class="rpt-tab {{ $loop->first ? 'active' : '' }}">
        <span style="display:flex;align-items:center;gap:6px">{!! $o['icon'] !!} {{ $o['label'] }}</span>
    </button>
    @endforeach
</div>

{{-- ══════════════════════════════════
     ONGLET 1 — OCCUPATION
══════════════════════════════════ --}}
<div id="panel-occupation" class="rpt-panel">

    {{-- Jauge globale --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Taux global du réseau</span>
            </div>
            <span style="font-size:24px;font-weight:800;color:var(--accent)">{{ $occupation['taux'] }}%</span>
        </div>
        <div style="height:14px;background:var(--surface2);border-radius:20px;overflow:hidden">
            <div style="height:100%;width:{{ $occupation['taux'] }}%;background:linear-gradient(90deg,#e8a020,#f97316);border-radius:20px;transition:width .8s cubic-bezier(.4,0,.2,1)"></div>
        </div>
        <div style="display:flex;gap:20px;margin-top:10px;font-size:11px;color:var(--text3)">
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#ef4444;border-radius:50%;display:inline-block"></span>Occupés {{ $occupation['occupes'] }}</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#22c55e;border-radius:50%;display:inline-block"></span>Libres {{ $occupation['libres'] }}</span>
            <span style="display:flex;align-items:center;gap:5px"><span style="width:8px;height:8px;background:#6b7280;border-radius:50%;display:inline-block"></span>Maintenance {{ $occupation['maintenance'] }}</span>
        </div>
    </div>

    {{-- Barres par commune --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Taux d'occupation par commune</span>
        </div>
        @forelse($occParCommune as $row)
        <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--text)">{{ $row['commune'] }}</span>
                <div style="display:flex;gap:12px;font-size:11px;color:var(--text3)">
                    <span>{{ $row['total'] }} pann.</span>
                    <span style="font-weight:700;color:{{ $row['color'] }}">{{ $row['taux'] }}%</span>
                </div>
            </div>
            <div style="height:8px;background:var(--surface2);border-radius:10px;overflow:hidden">
                <div style="height:100%;width:{{ $row['taux'] }}%;background:{{ $row['color'] }};border-radius:10px;transition:width .6s {{ $loop->index * 60 }}ms ease-out"></div>
            </div>
        </div>
        @empty
        <div style="text-align:center;padding:30px;color:var(--text3)">Aucune donnée disponible</div>
        @endforelse
    </div>

    {{-- Évolution mensuelle (barres custom) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Évolution mensuelle — 12 derniers mois</span>
        </div>
        <div id="chart-evol" style="display:flex;align-items:flex-end;gap:4px;height:120px"></div>
        <div id="chart-evol-labels" style="display:flex;gap:4px;margin-top:6px"></div>
    </div>

    {{-- Courbe Chart.js : tendance occupation 12 mois (analyse parc) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Tendance d'occupation du parc — 12 derniers mois</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Pourcentage moyen mensuel</span>
        </div>
        <div style="position:relative;width:100%;height:260px">
            <canvas id="chart-occupation-trend" role="img" aria-label="Tendance d'occupation 12 mois"></canvas>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════
     ONGLET 2 — PÉRIODES
══════════════════════════════════ --}}
<div id="panel-periodes" class="rpt-panel" style="display:none">

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Répartition des durées de campagnes</span>
        </div>
        @php $colors = ['#3b82f6','#e8a020','#a855f7','#14b8a6','#22c55e']; @endphp
        @forelse($repartitionDurees as $i => $row)
        <div style="margin-bottom:12px">
            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                <span style="font-size:12px;color:var(--text)">{{ $row['label'] }}</span>
                <div style="display:flex;gap:10px;font-size:11px;color:var(--text3)">
                    <span>{{ $row['count'] }} campagne(s)</span>
                    <span style="font-weight:700;color:{{ $colors[$i % count($colors)] }}">{{ $row['pct'] }}%</span>
                </div>
            </div>
            <div style="height:8px;background:var(--surface2);border-radius:10px;overflow:hidden">
                <div style="height:100%;width:{{ $row['pct'] }}%;background:{{ $colors[$i % count($colors)] }};border-radius:10px"></div>
            </div>
        </div>
        @empty
        <div style="color:var(--text3);font-size:13px;text-align:center;padding:24px">Aucune donnée sur cette période</div>
        @endforelse
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Activité mensuelle {{ $annee }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:600px">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Mois','Campagnes','Panneaux mobilisés','CA (FCFA)','Taux'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($tableauMensuel as $row)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:12px;color:var(--text);font-weight:600">{{ $row['mois'] }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($row['nb_campagnes']) }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($row['panneaux_mobilises']) }}</td>
                        <td style="padding:10px 16px;font-size:12px;font-weight:600;color:var(--accent)">{{ $row['ca'] > 0 ? number_format($row['ca'], 0, ',', ' ') : '—' }}</td>
                        <td style="padding:10px 16px">
                            @php $tc = $row['taux'] >= 75 ? '#ef4444' : ($row['taux'] >= 50 ? '#f97316' : ($row['taux'] >= 25 ? '#e8a020' : '#22c55e')); @endphp
                            @if($row['taux'] > 0)
                            <span style="padding:2px 10px;border-radius:20px;background:{{ $tc }}22;color:{{ $tc }};font-size:11px;font-weight:700">{{ $row['taux'] }}%</span>
                            @else<span style="color:var(--text3);font-size:11px">—</span>@endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════
     ONGLET 3 — CA & REVENUS
══════════════════════════════════ --}}
<div id="panel-ca" class="rpt-panel" style="display:none">

    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
        @php
        $caKpis = [
            ['CA Période', number_format($caTotal, 0, ',', ' ') . ' FCFA', '#e8a020',
             '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'],
            ['Ticket moyen / campagne', number_format($caTicketMoy, 0, ',', ' ') . ' FCFA', '#3b82f6',
             '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'],
            ['Top client', ($topClients->first()?->name ?? '—'), '#a855f7',
             '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>'],
        ];
        @endphp
        @foreach($caKpis as [$lbl, $val, $col, $ico])
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;border-top:3px solid {{ $col }}">
            <div style="color:{{ $col }};margin-bottom:10px">{!! $ico !!}</div>
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">{{ $lbl }}</div>
            <div style="font-size:16px;font-weight:800;color:{{ $col }}">{{ $val }}</div>
            @if($lbl === 'Top client' && $topClients->first())
            <div style="font-size:10px;color:var(--text3);margin-top:4px">{{ number_format($topClients->first()->ca_total, 0, ',', ' ') }} FCFA</div>
            @endif
        </div>
        @endforeach
    </div>

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">CA mensuel {{ $annee }}</span>
        </div>
        <div id="chart-ca" style="display:flex;align-items:flex-end;gap:6px;height:140px"></div>
        <div id="chart-ca-labels" style="display:flex;gap:6px;margin-top:6px"></div>
    </div>

    {{-- Courbe Chart.js : CA mensuel sur 12 mois glissants (réservations) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Évolution du CA — 12 derniers mois</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Réservations confirmées + terminées</span>
        </div>
        <div style="position:relative;width:100%;height:260px">
            <canvas id="chart-revenue-trend" role="img" aria-label="CA mensuel 12 mois"></canvas>
        </div>
    </div>

    {{-- Corrélation occupation × revenus (scatter) — COMMIT B --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="9" cy="14" r="1"/><circle cx="14" cy="9" r="1"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Corrélation occupation × revenus par commune</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Identifier les zones sous-monétisées</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px;line-height:1.5">
            Chaque point = une commune. <strong style="color:#22c55e">Haut-droite</strong> : occupation forte + CA élevé (zones moteurs).
            <strong style="color:#ef4444">Bas-droite</strong> : occupation forte mais CA faible (tarif sous-évalué).
            <strong style="color:#f59e0b">Haut-gauche</strong> : CA élevé sur peu de panneaux occupés (rareté précieuse).
        </div>
        <div style="position:relative;width:100%;height:340px">
            <canvas id="chart-occ-revenue" role="img" aria-label="Corrélation occupation revenus"></canvas>
        </div>
    </div>

    {{-- CA par ville (vue agrégée) — COMMIT B --}}
    @if($revenueByCity->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">CA par ville (vue agrégée)</span>
            <span style="margin-left:auto;font-size:11px;color:var(--text3)">{{ $revenueByCity->count() }} villes</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Ville','CA','Campagnes','Panneaux loués','Communes'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueByCity as $i => $r)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:13px;color:var(--text3);font-weight:700">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i + 1)) }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $r->city }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:var(--accent)">{{ number_format((float) $r->revenue, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->campaigns_count) }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->panels_engaged) }}</td>
                        <td style="padding:10px 16px;font-size:11px;color:var(--text3)">{{ $r->communes_count }} commune(s)</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Top clients — CA sur la période</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Client','CA Total','Campagnes','Panneaux'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($topClients as $i => $client)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:14px">{{ $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $client->name }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:var(--accent)">{{ number_format($client->ca_total, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client->nb_campagnes) }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client->total_panneaux ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">Aucun client sur cette période</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════
     ONGLET 4 — ZONES & COMMUNES (HEATMAP)
══════════════════════════════════ --}}
<div id="panel-zones" class="rpt-panel" style="display:none">

    {{-- Boutons mode --}}
    <div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;align-items:center">
        <button onclick="HM.setMode('taux')"  id="hm-btn-taux"
                style="font-size:12px;padding:6px 14px;border-radius:8px;border:1px solid var(--accent);background:var(--accent);color:#000;cursor:pointer;font-weight:700;transition:all .15s">
            Taux d'occupation
        </button>
        <button onclick="HM.setMode('total')" id="hm-btn-total"
                style="font-size:12px;padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);cursor:pointer;transition:all .15s">
            Nbre panneaux
        </button>
        <button onclick="HM.setMode('ca')"    id="hm-btn-ca"
                style="font-size:12px;padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);cursor:pointer;transition:all .15s">
            CA annuel
        </button>
        <span style="margin-left:auto;font-size:11px;color:var(--text3)">
            {{ $statsCommunes->count() }} communes · survolez une tuile pour le détail
        </span>
    </div>

    {{-- Grille heatmap --}}
    <div id="hm-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;margin-bottom:14px">
        {{-- Rendu JS --}}
    </div>

    {{-- Légende dégradé --}}
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:11px;color:var(--text3)">
        <span>Faible</span>
        <div style="height:8px;flex:1;border-radius:4px;background:linear-gradient(90deg,#E6F1FB,#185FA5)"></div>
        <span>Élevé</span>
    </div>

    {{-- Graphique barres --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:14px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)" id="hm-chart-title">Taux d'occupation par commune</span>
        </div>
        <div style="position:relative;width:100%;height:280px">
            <canvas id="hm-bar-chart" role="img" aria-label="Graphique par commune"></canvas>
        </div>
    </div>

    {{-- Tableau détaillé --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Détail par commune</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;min-width:700px">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Commune','Total','Occupés','Libres','Maint.','Taux','Tarif moy.','CA ' . $annee] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($statsCommunes as $row)
                    @php $tc = $row['taux'] >= 75 ? '#ef4444' : ($row['taux'] >= 50 ? '#f97316' : ($row['taux'] >= 25 ? '#e8a020' : '#22c55e')); @endphp
                    <tr data-commune-id="{{ $row['id'] }}"
                        onclick="CommuneDrilldown.open({{ $row['id'] }})"
                        title="Cliquer pour voir le détail de la commune"
                        style="border-bottom:1px solid var(--border);transition:background .1s;cursor:pointer"
                        onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">
                            {{ $row['commune'] }}
                            <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                        </td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ $row['total'] }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:#ef4444;font-weight:600">{{ $row['occupes'] }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:#22c55e;font-weight:600">{{ $row['libres'] }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text3)">{{ $row['maintenance'] }}</td>
                        <td style="padding:10px 16px">
                            @if($row['taux'] > 0)
                            <span style="padding:2px 10px;border-radius:20px;background:{{ $tc }}22;color:{{ $tc }};font-size:11px;font-weight:700">{{ $row['taux'] }}%</span>
                            @else<span style="color:var(--text3);font-size:11px">—</span>@endif
                        </td>
                        <td style="padding:10px 16px;font-size:11px;color:var(--text3)">{{ $row['tarif_moyen'] > 0 ? number_format($row['tarif_moyen'], 0, ',', ' ') . ' FCFA' : '—' }}</td>
                        <td style="padding:10px 16px;font-size:12px;font-weight:600;color:var(--accent)">{{ $row['ca_annee'] > 0 ? number_format($row['ca_annee'], 0, ',', ' ') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text3)">Aucune commune avec des panneaux</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panneaux à décaper --}}
    @if($aDecaper->isNotEmpty())
    <div style="background:var(--surface);border:1px solid rgba(239,68,68,.3);border-radius:14px;overflow:hidden">
        <div style="padding:14px 20px;border-bottom:1px solid rgba(239,68,68,.2);background:rgba(239,68,68,.04);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:13px;font-weight:700;color:#ef4444">Panneaux à décaper — 30 prochains jours</span>
            </div>
            <span style="font-size:11px;background:rgba(239,68,68,.12);color:#ef4444;padding:2px 10px;border-radius:20px;font-weight:700">{{ $aDecaper->count() }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Panneau','Commune','Client','Fin campagne','Jours restants'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($aDecaper as $p)
                    @php $urgent = $p->jours_restants <= 7; @endphp
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">{{ $p->reference }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ $p->commune ?? '—' }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ $p->client_name }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ \Carbon\Carbon::parse($p->end_date)->format('d/m/Y') }}</td>
                        <td style="padding:10px 16px">
                            <span style="padding:2px 10px;border-radius:20px;font-size:11px;font-weight:700;background:{{ $urgent ? 'rgba(239,68,68,.15)' : 'rgba(249,115,22,.12)' }};color:{{ $urgent ? '#ef4444' : '#f97316' }}">
                                {{ $p->jours_restants }}j
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- ══════════════════════════════════
     ONGLET 5 — CLIENTS
══════════════════════════════════ --}}
<div id="panel-clients" class="rpt-panel" style="display:none">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Portefeuille clients — Activité</span>
            </div>
            <span style="font-size:11px;color:var(--text3)">{{ $statsClients->count() }} clients</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['Client','NCC','Campagnes','Actives','CA Total','Panneaux','Dernière activité'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($statsClients as $client)
                    <tr data-client-id="{{ $client['id'] }}"
                        onclick="ClientDrilldown.open({{ $client['id'] }})"
                        title="Cliquer pour voir l'historique complet du client"
                        style="border-bottom:1px solid var(--border);transition:background .1s;cursor:pointer"
                        onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px">
                            <a href="{{ route('admin.clients.show', $client['id']) }}"
                               onclick="event.stopPropagation()"
                               style="font-size:13px;font-weight:600;color:var(--accent);text-decoration:none">{{ $client['name'] }}</a>
                            <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                        </td>
                        <td style="padding:10px 16px;font-family:monospace;font-size:11px;color:var(--text3)">{{ $client['ncc'] ?? '—' }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client['total_campagnes']) }}</td>
                        <td style="padding:10px 16px">
                            @if($client['campagnes_actives'] > 0)
                            <span style="padding:2px 10px;border-radius:20px;background:rgba(34,197,94,.12);color:#22c55e;font-size:11px;font-weight:700">{{ $client['campagnes_actives'] }} actives</span>
                            @else<span style="color:var(--text3);font-size:11px">—</span>@endif
                        </td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:var(--accent)">
                            {{ $client['ca_total'] > 0 ? number_format($client['ca_total'], 0, ',', ' ') : '—' }}
                            @if($client['ca_total'] > 0)<span style="font-size:10px;font-weight:400;color:var(--text3)"> FCFA</span>@endif
                        </td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client['total_panneaux'] ?? 0) }}</td>
                        <td style="padding:10px 16px;font-size:11px;color:var(--text3)">{{ $client['derniere_campagne'] ? \Carbon\Carbon::parse($client['derniere_campagne'])->format('d/m/Y') : '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;padding:40px;color:var(--text3)">Aucun client</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ════ MODAL DRILLDOWN COMMUNE ════ --}}
<div id="commune-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)CommuneDrilldown.close()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:1080px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;">Commune</div>
                <h2 id="cm-name" style="font-size:18px;font-weight:800;color:var(--text);margin-top:3px;">…</h2>
            </div>
            <button type="button" onclick="CommuneDrilldown.close()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:6px 10px;border-radius:8px;" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background='none'">✕</button>
        </div>

        <div id="cm-loading" style="padding:60px;text-align:center;color:var(--text3);font-size:13px;">
            <div class="rpt-spinner" style="display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:rpt-spin .7s linear infinite;vertical-align:middle;margin-right:8px;"></div>
            Chargement…
        </div>

        <div id="cm-body" style="display:none;padding:18px 22px;overflow-y:auto;flex:1;">
            {{-- Stats résumé --}}
            <div id="cm-stats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px;"></div>

            {{-- Top clients --}}
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Top clients (année en cours)</h3>
            <div id="cm-top-clients" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:18px;"></div>

            {{-- Campagnes --}}
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Campagnes touchant cette commune</h3>
            <div id="cm-campagnes" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:18px;"></div>

            {{-- Panneaux --}}
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Panneaux installés (<span id="cm-panels-count">0</span>)</h3>
            <div id="cm-panels" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;"></div>
        </div>
    </div>
</div>

{{-- ════ MODAL DRILLDOWN CLIENT (COMMIT B) ════ --}}
<div id="client-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)ClientDrilldown.close()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:1080px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;">Client</div>
                <h2 id="cl-name" style="font-size:18px;font-weight:800;color:var(--text);margin-top:3px;">…</h2>
                <div id="cl-meta" style="font-size:11px;color:var(--text3);margin-top:2px;"></div>
            </div>
            <div style="display:flex;gap:8px;">
                <a id="cl-link" href="#" style="font-size:11px;font-weight:700;padding:6px 12px;background:var(--accent);color:#fff;border-radius:8px;text-decoration:none;">Fiche client →</a>
                <button type="button" onclick="ClientDrilldown.close()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:6px 10px;border-radius:8px;" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background='none'">✕</button>
            </div>
        </div>

        <div id="cl-loading" style="padding:60px;text-align:center;color:var(--text3);font-size:13px;">
            <div class="rpt-spinner" style="display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:rpt-spin .7s linear infinite;vertical-align:middle;margin-right:8px;"></div>
            Chargement…
        </div>

        <div id="cl-body" style="display:none;padding:18px 22px;overflow-y:auto;flex:1;">
            {{-- Synthèse cards --}}
            <div id="cl-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px;"></div>

            {{-- CA mensuel 12 mois (mini chart) --}}
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">CA mensuel — 12 derniers mois</h3>
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:18px;position:relative;height:200px;">
                <canvas id="cl-revenue-chart" role="img" aria-label="CA mensuel client"></canvas>
            </div>

            {{-- Top communes + top panneaux --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;">
                <div>
                    <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Top communes exploitées</h3>
                    <div id="cl-communes" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;"></div>
                </div>
                <div>
                    <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Panneaux favoris (top 10)</h3>
                    <div id="cl-panels" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;"></div>
                </div>
            </div>

            {{-- Historique campagnes --}}
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Historique campagnes (<span id="cl-camp-count">0</span>)</h3>
            <div id="cl-campaigns" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;"></div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — PERFORMANCE PANNEAUX (top + low)
══════════════════════════════════════════════════════════════ --}}
<div id="panel-panneaux" class="rpt-panel" style="display:none">

    {{-- Classement visuel Chart.js (top 15 panneaux les plus loués) --}}
    @if($topPanels->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Classement visuel — top 15 panneaux les plus loués</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Jours occupés sur la période</span>
        </div>
        <div style="position:relative;width:100%;height:380px">
            <canvas id="chart-top-panels" role="img" aria-label="Top panneaux"></canvas>
        </div>
    </div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        @media (max-width:900px) { .rpt-grid-2 { grid-template-columns:1fr; } }

        {{-- Top 20 plus loués ──────────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
                Top 20 panneaux les plus loués
            </div>
            @if($topPanels->isEmpty())
                <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune donnée sur la période.</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">#</th>
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Jours occupé</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Camp.</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">CA estimé</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topPanels as $i => $p)
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px;color:var(--text3);font-weight:700">{{ $i+1 }}</td>
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }} · {{ $p->commune_name ?? '—' }}</div>
                                </td>
                                <td style="padding:8px;text-align:right;font-weight:700;color:#16a34a">{{ $p->days_occupied }}j</td>
                                <td style="padding:8px;text-align:right;color:var(--text2)">{{ $p->campaigns_count }}</td>
                                <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ number_format((float) $p->estimated_revenue, 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Top 20 sous-performants ────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                Panneaux sous-performants
            </div>
            @if($lowPanels->isEmpty())
                <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Tous les panneaux ont au moins une campagne.</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Camp.</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Tarif/mois</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowPanels as $p)
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }} · {{ $p->commune_name ?? '—' }}</div>
                                </td>
                                <td style="padding:8px;text-align:right">
                                    @if($p->campaigns_count == 0)
                                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(239,68,68,.1);color:#ef4444">0 — jamais loué</span>
                                    @else
                                        <span style="color:#f97316;font-weight:700">{{ $p->campaigns_count }}</span>
                                    @endif
                                </td>
                                <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ $p->monthly_rate > 0 ? number_format($p->monthly_rate, 0, ',', ' ') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — DÉCAPPAGES (campagnes terminées + à venir)
══════════════════════════════════════════════════════════════ --}}
<div id="panel-decap" class="rpt-panel" style="display:none">
    {{-- Campagnes terminées récemment (à décaper) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg>
            Campagnes terminées — à décaper ({{ $decapList->count() }})
        </div>
        @if($decapList->isEmpty())
            <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune campagne récemment terminée.</div>
        @else
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:var(--surface2)">
                            <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Campagne</th>
                            <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Client</th>
                            <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneaux</th>
                            <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Fin</th>
                            <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Retard</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($decapList as $c)
                            @php
                                $daysOverdue = (int) $c->end_date->diffInDays(now(), false);
                                $isOverdue = $daysOverdue > 7;
                            @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px">
                                    <a href="{{ route('admin.campaigns.show', $c->id) }}" style="color:var(--accent);text-decoration:none;font-weight:600">{{ $c->name }}</a>
                                </td>
                                <td style="padding:8px;color:var(--text2)">{{ $c->client?->name ?? '—' }}</td>
                                <td style="padding:8px;text-align:right;font-weight:700">{{ $c->panels->count() }}</td>
                                <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ $c->end_date->format('d/m/Y') }}</td>
                                <td style="padding:8px;text-align:right">
                                    @if($isOverdue)
                                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(220,38,38,.1);color:#dc2626">+{{ $daysOverdue }}j</span>
                                    @else
                                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,.1);color:#f59e0b">{{ $daysOverdue }}j</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Campagnes à venir (J+14) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Campagnes finissant dans les 14 jours ({{ $upcomingEndings->count() }})
        </div>
        @if($upcomingEndings->isEmpty())
            <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune campagne active ne se termine dans les 14 jours.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach($upcomingEndings as $c)
                    @php $daysLeft = (int) now()->startOfDay()->diffInDays($c->end_date->startOfDay(), false); @endphp
                    <a href="{{ route('admin.campaigns.show', $c->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;text-decoration:none">
                        <div>
                            <div style="font-size:12px;font-weight:600;color:var(--text)">{{ $c->name }}</div>
                            <div style="font-size:10px;color:var(--text3)">{{ $c->client?->name ?? '—' }} · Fin : {{ $c->end_date->format('d/m/Y') }}</div>
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px;background:rgba(59,130,246,.1);color:#3b82f6">Dans {{ $daysLeft }}j</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — INSIGHTS & ALERTES
══════════════════════════════════════════════════════════════ --}}
<div id="panel-insights" class="rpt-panel" style="display:none">

    {{-- Suggestions reconquête : templates prêts à l'emploi (COMMIT B) --}}
    @if(($inactivityBucket['6_to_12'] ?? 0) + ($inactivityBucket['12_plus'] ?? 0) > 0)
    <div style="background:linear-gradient(135deg,rgba(239,68,68,.04),rgba(168,85,247,.04));border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">🎯 Reconquête clients — templates prêts à l'emploi</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:14px">
            {{ ($inactivityBucket['6_to_12'] ?? 0) + ($inactivityBucket['12_plus'] ?? 0) }} client(s) en zone de churn — utilisez ces messages directement.
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            {{-- Template MAIL --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;position:relative">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">📧 Modèle e-mail (J0)</span>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('tpl-mail').innerText);this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',1500)"
                            style="margin-left:auto;font-size:10px;font-weight:600;background:var(--surface2);border:1px solid var(--border);color:var(--text2);padding:3px 10px;border-radius:6px;cursor:pointer">Copier</button>
                </div>
                <div id="tpl-mail" style="font-size:11px;line-height:1.55;color:var(--text2);font-family:Georgia,serif;background:var(--surface2);padding:10px 12px;border-radius:8px;white-space:pre-wrap">Objet : Une opportunité en or vous attend chez CIBLE CI

Bonjour [PRENOM],

Cela fait plusieurs mois que nous n'avons pas eu le plaisir de collaborer avec [SOCIETE]. Vos précédentes campagnes ont eu un excellent impact sur le terrain, et nous tenons à vous proposer une offre privilégiée pour votre retour :

• 15 % de remise sur votre prochaine campagne (>1 mois)
• Choix prioritaire sur nos panneaux stratégiques
• Suivi dédié par votre commercial habituel

Souhaitez-vous que nous échangions cette semaine pour évoquer vos prochains objectifs de communication ?

Cordialement,
[VOTRE NOM]
CIBLE CI — Affichage urbain</div>
            </div>

            {{-- Template APPEL --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;position:relative">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">📞 Script appel commercial</span>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('tpl-call').innerText);this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',1500)"
                            style="margin-left:auto;font-size:10px;font-weight:600;background:var(--surface2);border:1px solid var(--border);color:var(--text2);padding:3px 10px;border-radius:6px;cursor:pointer">Copier</button>
                </div>
                <div id="tpl-call" style="font-size:11px;line-height:1.55;color:var(--text2);font-family:Georgia,serif;background:var(--surface2);padding:10px 12px;border-radius:8px;white-space:pre-wrap">🎯 OUVERTURE (15 sec)
"Bonjour [PRENOM], c'est [VOTRE NOM] de CIBLE CI. Je vous appelle car cela fait [X mois] que nous n'avons pas eu l'occasion de travailler ensemble. Avez-vous 2 minutes ?"

🔍 DÉCOUVERTE
• Comment se portent vos actions de communication actuellement ?
• Quels sont vos objectifs prioritaires pour les prochains mois ?
• Avez-vous testé d'autres canaux (digital, presse) entre-temps ?

💡 PROPOSITION
"J'ai justement repéré [N] emplacements stratégiques disponibles dans la zone [VILLE/COMMUNE] — exactement le profil de vos précédentes campagnes qui ont bien performé. Je vous propose une offre de retour : 15 % de remise + suivi VIP."

✅ CLÔTURE
"Quand serait le meilleur moment cette semaine pour vous envoyer un dossier sur mesure ?"</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Liste des insights générés automatiquement --}}
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px">
        @foreach($insights as $insight)
            @php
                $colors = match($insight['severity']) {
                    'danger'  => ['bg' => 'rgba(220,38,38,.06)',  'border' => 'rgba(220,38,38,.3)',  'color' => '#dc2626'],
                    'warning' => ['bg' => 'rgba(245,158,11,.06)', 'border' => 'rgba(245,158,11,.3)', 'color' => '#d97706'],
                    'success' => ['bg' => 'rgba(34,197,94,.06)',  'border' => 'rgba(34,197,94,.3)',  'color' => '#16a34a'],
                    default   => ['bg' => 'rgba(59,130,246,.06)', 'border' => 'rgba(59,130,246,.3)', 'color' => '#3b82f6'],
                };
            @endphp
            <div style="background:{{ $colors['bg'] }};border:1px solid {{ $colors['border'] }};border-radius:12px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px">
                <span style="font-size:22px;line-height:1;flex-shrink:0">{{ $insight['icon'] }}</span>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:700;color:{{ $colors['color'] }};margin-bottom:4px">{{ $insight['title'] }}</div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.5">{{ $insight['message'] }}</div>
                    @if(!empty($insight['details']))
                        <div style="font-size:11px;color:var(--text3);margin-top:4px;font-style:italic">{{ $insight['details'] }}</div>
                    @endif
                </div>
                @if(!empty($insight['cta_label']) && !empty($insight['cta_url']))
                    <a href="{{ $insight['cta_url'] }}" style="font-size:11px;font-weight:700;padding:5px 12px;background:{{ $colors['color'] }};color:#fff;border-radius:8px;text-decoration:none;flex-shrink:0;white-space:nowrap">
                        {{ $insight['cta_label'] }} →
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Tranches d'inactivité clients (cards + Chart.js bar) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">📉 Clients inactifs — par tranche</div>
        <div style="display:grid;grid-template-columns:2fr 3fr;gap:16px;align-items:start">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div style="text-align:center;padding:14px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#d97706">{{ $inactivityBucket['3_to_6'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs 3-6 mois</div>
            </div>
            <div style="text-align:center;padding:14px;background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#ea580c">{{ $inactivityBucket['6_to_12'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs 6-12 mois</div>
            </div>
            <div style="text-align:center;padding:14px;background:rgba(220,38,38,.06);border:1px solid rgba(220,38,38,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#dc2626">{{ $inactivityBucket['12_plus'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs > 12 mois</div>
            </div>
        </div>
        <div style="position:relative;width:100%;height:180px">
            <canvas id="chart-inactivity" role="img" aria-label="Tranches d'inactivité"></canvas>
        </div>
        </div>
    </div>

    {{-- Motifs d'annulation campagnes (doughnut Chart.js + liste détaillée) --}}
    @if($cancelReasons->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">📋 Motifs d'annulation campagnes ({{ $campaignStats['cancel_rate'] }}% sur {{ $campaignStats['total'] }} campagnes)</div>
        @php
            $reasonLabels = [
                'budget' => '💸 Budget client', 'zone' => '📍 Choix zone', 'strategie' => '🎯 Changement stratégie',
                'report' => '⏰ Report client', 'concurrent' => '🤝 Choix concurrent', 'autre' => '❓ Autre',
            ];
            $totalCancel = $cancelReasons->sum('count');
        @endphp
        <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:center">
            <div style="position:relative;width:280px;height:240px">
                <canvas id="chart-cancel-reasons" role="img" aria-label="Motifs d'annulation"></canvas>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach($cancelReasons as $r)
                    @php $pct = $totalCancel > 0 ? round(($r->count / $totalCancel) * 100, 1) : 0; @endphp
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface2);border-radius:8px">
                        <span style="font-size:12px;color:var(--text);min-width:160px">{{ $reasonLabels[$r->cancellation_reason] ?? ucfirst($r->cancellation_reason) }}</span>
                        <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                            <div style="height:100%;background:linear-gradient(90deg,#ef4444,#f97316);width:{{ $pct }}%"></div>
                        </div>
                        <span style="font-size:11px;font-weight:700;color:var(--text);min-width:40px;text-align:right">{{ $r->count }}</span>
                        <span style="font-size:10px;color:var(--text3);min-width:40px;text-align:right">{{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>

{{-- ════ STYLES ════ --}}
<style>
.rpt-tab { flex:1;padding:9px 10px;border-radius:10px;border:none;background:transparent;color:var(--text3);font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap; }
.rpt-tab:hover { background:var(--surface2);color:var(--text); }
.rpt-tab.active { background:var(--accent);color:#000; }
.kpi-active { border-color:var(--kpi-c,var(--accent)) !important;background:var(--surface2) !important;transform:translateY(-3px) !important; }
@keyframes rpt-spin { to { transform: rotate(360deg); } }
.cm-stat { background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:12px 14px;border-left:3px solid var(--accent); }
.cm-stat .lbl { font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.2px;margin-bottom:4px; }
.cm-stat .val { font-size:18px;font-weight:800;color:var(--text);font-variant-numeric:tabular-nums; }
.cm-table { width:100%;border-collapse:collapse;font-size:12px; }
.cm-table th { background:var(--surface);padding:8px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;border-bottom:1px solid var(--border); }
.cm-table th.r { text-align:right; }
.cm-table td { padding:8px 12px;border-bottom:1px solid var(--border); }
.cm-table td.r { text-align:right;font-variant-numeric:tabular-nums; }
.cm-table tbody tr:last-child td { border-bottom:none; }
.cm-table tbody tr:hover td { background:var(--surface); }
.cm-table a { color:var(--accent);text-decoration:none; }
.cm-table a:hover { text-decoration:underline; }
.cm-status-pill { display:inline-block;padding:1px 7px;border-radius:9px;font-size:9.5px;font-weight:700; }
.cm-status-libre       { background:rgba(34,197,94,.12);color:#16a34a; }
.cm-status-occupe      { background:rgba(239,68,68,.12);color:#b91c1c; }
.cm-status-option      { background:rgba(232,160,32,.12);color:#c2570d; }
.cm-status-confirme    { background:rgba(59,130,246,.12);color:#1d4ed8; }
.cm-status-maintenance { background:rgba(107,114,128,.12);color:#374151; }
</style>

{{-- ════ JAVASCRIPT ════ --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
(function(){
'use strict';

const D  = window.__RPT__;
const HM_DATA = D.statsCommunes || [];

// ══════════════════════════════
// HEATMAP — module
// ══════════════════════════════
let hmMode  = 'taux';
let hmChart = null;

function hmColor(norm) {
    const stops = [[0,[230,241,251]],[0.3,[133,183,235]],[0.6,[55,138,221]],[0.85,[24,95,165]],[1,[4,44,83]]];
    let lo=stops[0], hi=stops[stops.length-1];
    for (let i=0;i<stops.length-1;i++) { if(norm>=stops[i][0]&&norm<=stops[i+1][0]){lo=stops[i];hi=stops[i+1];break;} }
    const t=lo[0]===hi[0]?0:(norm-lo[0])/(hi[0]-lo[0]);
    return `rgb(${Math.round(lo[1][0]+(hi[1][0]-lo[1][0])*t)},${Math.round(lo[1][1]+(hi[1][1]-lo[1][1])*t)},${Math.round(lo[1][2]+(hi[1][2]-lo[1][2])*t)})`;
}
function hmTC(norm) { return norm>0.55?'#fff':'#042C53'; }
function hmTC2(norm) { return norm>0.55?'rgba(255,255,255,.65)':'rgba(4,44,83,.55)'; }
function hmVal(d,m) { return m==='taux'?d.taux:(m==='ca'?d.ca_annee:d.total); }
function hmFmt(v,m) { return m==='taux'?v+'%':(m==='ca'?(v/1000000).toFixed(1)+'M':String(v)); }
function hmNorm(arr) {
    const mn=Math.min(...arr), mx=Math.max(...arr);
    return mn===mx ? arr.map(()=>0.5) : arr.map(v=>(v-mn)/(mx-mn));
}

function hmRenderGrid() {
    const grid = document.getElementById('hm-grid');
    if (!grid||!HM_DATA.length) return;
    const vals  = HM_DATA.map(d=>hmVal(d,hmMode));
    const norms = hmNorm(vals);
    const sorted = HM_DATA.map((d,i)=>({d,n:norms[i]})).sort((a,b)=>hmVal(b.d,hmMode)-hmVal(a.d,hmMode));
    grid.innerHTML = sorted.map(({d,n})=>{
        const bg=hmColor(n), tc=hmTC(n), tc2=hmTC2(n);
        const sub = hmMode!=='taux' ? d.taux+'% occ.' : d.total+' pann.';
        // Tuile cliquable → drilldown commune. cursor:pointer + onclick.
        return `<div style="background:${bg};border-radius:10px;padding:14px 12px;cursor:pointer;
            transition:transform .15s,box-shadow .15s;position:relative;overflow:hidden"
            onclick="CommuneDrilldown.open(${d.id})"
            onmouseenter="this.style.transform='scale(1.05)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.25)'"
            onmouseleave="this.style.transform='';this.style.boxShadow=''"
            title="${d.commune} — cliquer pour le détail">
            <div style="font-size:11px;font-weight:500;color:${tc};margin-bottom:8px;line-height:1.2">${d.commune}</div>
            <div style="font-size:20px;font-weight:500;color:${tc};line-height:1">${hmFmt(hmVal(d,hmMode),hmMode)}</div>
            <div style="font-size:10px;color:${tc2};margin-top:6px">${sub}</div>
            <div style="position:absolute;bottom:0;left:0;height:3px;width:${d.taux}%;background:rgba(255,255,255,.3)"></div>
        </div>`;
    }).join('');
}

function hmRenderChart() {
    const canvas = document.getElementById('hm-bar-chart');
    if (!canvas||!HM_DATA.length||typeof Chart==='undefined') return;
    const sorted = [...HM_DATA].sort((a,b)=>hmVal(b,hmMode)-hmVal(a,hmMode));
    const vals   = sorted.map(d=>hmVal(d,hmMode));
    const colors = hmNorm(vals).map(n=>hmColor(n));
    const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
    const gridC  = isDark?'rgba(255,255,255,.08)':'rgba(0,0,0,.07)';
    const tickC  = isDark?'rgba(255,255,255,.5)':'rgba(0,0,0,.4)';
    const titles = { taux:"Taux d'occupation par commune", total:"Nombre de panneaux par commune", ca:"CA annuel par commune" };
    const titleEl = document.getElementById('hm-chart-title');
    if (titleEl) titleEl.textContent = titles[hmMode];
    if (hmChart) { hmChart.destroy(); hmChart=null; }
    hmChart = new Chart(canvas, {
        type:'bar',
        data:{ labels:sorted.map(d=>d.commune), datasets:[{data:vals,backgroundColor:colors,borderRadius:6,borderSkipped:false}] },
        options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                title: ctx=>ctx[0].label,
                label: ctx=>{ const d=sorted[ctx.dataIndex]; return [` ${hmFmt(ctx.raw,hmMode)}`,` Taux: ${d.taux}%`,` Panneaux: ${d.total} (${d.occupes} occupés)`,` CA: ${(d.ca_annee/1000000).toFixed(1)}M FCFA`]; }
            }}},
            scales:{
                x:{ ticks:{color:tickC,font:{size:11},maxRotation:35,autoSkip:false}, grid:{display:false} },
                y:{ ticks:{color:tickC,font:{size:11},callback:v=>hmMode==='ca'?(v/1000000).toFixed(0)+'M':v+(hmMode==='taux'?'%':'')}, grid:{color:gridC} }
            }
        }
    });
}

window.HM = {
    setMode(m) {
        hmMode = m;
        ['taux','total','ca'].forEach(k=>{
            const btn=document.getElementById('hm-btn-'+k);
            if(!btn) return;
            if(k===m){ btn.style.background='var(--accent)';btn.style.color='#000';btn.style.borderColor='var(--accent)'; }
            else{ btn.style.background='var(--surface2)';btn.style.color='var(--text3)';btn.style.borderColor='var(--border)'; }
        });
        hmRenderGrid(); hmRenderChart();
    },
    init() { hmRenderGrid(); hmRenderChart(); }
};

// ══════════════════════════════
// RPT — module principal
// ══════════════════════════════
const CARD_COLORS = { occupation:'#e8a020', libres:'#22c55e', ca:'#3b82f6', clients:'#a855f7', maintenance:'#6b7280', decaper:'#ef4444' };

window.RPT = {

    clickCard(cardId, tabId) {
        document.querySelectorAll('#kpi-cards button').forEach(btn=>{
            btn.classList.remove('kpi-active');
            btn.style.borderColor='var(--border)';
            btn.style.transform='';
            btn.style.boxShadow='';
        });
        const card = document.getElementById('kpi-'+cardId);
        if (card) {
            const color = CARD_COLORS[cardId]||'var(--accent)';
            card.classList.add('kpi-active');
            card.style.setProperty('--kpi-c', color);
            card.style.borderColor = color;
            card.style.transform   = 'translateY(-3px)';
            card.style.boxShadow   = `0 8px 24px rgba(0,0,0,.2),0 0 0 3px ${color}33`;
        }
        this.switchTab(tabId);
    },

    switchTab(id) {
        document.querySelectorAll('.rpt-tab').forEach(t=>t.classList.remove('active'));
        document.querySelectorAll('.rpt-panel').forEach(p=>p.style.display='none');
        document.getElementById('tab-'+id).classList.add('active');
        document.getElementById('panel-'+id).style.display='block';
        if (id==='occupation'&&!this._evolDone)     { this.renderEvol(); this.renderOccupationTrend(); this._evolDone=true; }
        if (id==='ca'        &&!this._caDone)       { this.renderCa();   this.renderRevenueTrend();    this.renderOccVsRevenue(); this._caDone=true; }
        if (id==='zones'     &&!this._hmDone)       { HM.init();         this._hmDone=true; }
        if (id==='panneaux'  &&!this._panneauxDone) { this.renderTopPanels();    this._panneauxDone=true; }
        if (id==='insights'  &&!this._insightsDone) { this.renderInsightsCharts();this._insightsDone=true; }
    },

    renderEvol() {
        const data=D.evolMensuelle; if(!data?.length) return;
        const max=Math.max(...data.map(d=>d.taux),1);
        const chart=document.getElementById('chart-evol');
        const labels=document.getElementById('chart-evol-labels');
        if(!chart) return;
        chart.innerHTML=data.map(d=>{
            const h=Math.max((d.taux/max)*100,2);
            const col=d.taux>=75?'#ef4444':d.taux>=50?'#f97316':d.taux>=25?'#e8a020':'#22c55e';
            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px" title="${d.label}: ${d.taux}%">
                <div style="font-size:9px;color:var(--text3)">${d.taux}%</div>
                <div style="width:100%;height:${h}%;background:${col};border-radius:5px 5px 0 0;min-height:4px;transition:height .7s cubic-bezier(.4,0,.2,1)"
                     onmouseenter="this.style.opacity='.75'" onmouseleave="this.style.opacity='1'"></div>
            </div>`;
        }).join('');
        labels.style.cssText='display:flex;gap:4px;margin-top:4px';
        labels.innerHTML=data.map(d=>`<div style="flex:1;text-align:center;font-size:9px;color:var(--text3)">${d.label}</div>`).join('');
    },

    renderCa() {
        const data=D.caMensuel; if(!data?.length) return;
        const max=Math.max(...data.map(d=>d.ca),1);
        const chart=document.getElementById('chart-ca');
        const labels=document.getElementById('chart-ca-labels');
        if(!chart) return;
        chart.innerHTML=data.map(d=>{
            const h=Math.max((d.ca/max)*120,d.ca>0?4:0);
            const mK=d.ca>0?(d.ca/1000000).toFixed(1)+'M':'';
            return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px" title="${d.label}: ${mK} FCFA">
                <div style="font-size:9px;color:var(--accent)">${mK}</div>
                <div style="width:100%;height:${h}px;background:linear-gradient(180deg,var(--accent),#c05000);border-radius:5px 5px 0 0;min-height:${d.ca>0?'4':'0'}px;transition:height .7s cubic-bezier(.4,0,.2,1)"
                     onmouseenter="this.style.opacity='.75'" onmouseleave="this.style.opacity='1'"></div>
            </div>`;
        }).join('');
        labels.style.cssText='display:flex;gap:6px;margin-top:4px';
        labels.innerHTML=data.map(d=>`<div style="flex:1;text-align:center;font-size:9px;color:var(--text3)">${d.label}</div>`).join('');
    },

    // ── Chart.js — tendance d'occupation 12 mois ─────────────────────
    renderOccupationTrend() {
        const canvas = document.getElementById('chart-occupation-trend');
        const data = D.occupationTrend;
        if (!canvas || !data?.length || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        new Chart(canvas, {
            type:'line',
            data:{
                labels: data.map(d=>d.label),
                datasets:[{
                    label: "Taux d'occupation",
                    data: data.map(d=>d.rate),
                    borderColor:'#3b82f6',
                    backgroundColor:'rgba(59,130,246,.18)',
                    borderWidth:2.5, tension:.35, fill:true,
                    pointBackgroundColor:'#3b82f6', pointRadius:4, pointHoverRadius:6,
                }],
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                    label: ctx => ` ${ctx.parsed.y}% d'occupation`,
                }}},
                scales:{
                    x:{ ticks:{color:tickC,font:{size:11}}, grid:{display:false} },
                    y:{ beginAtZero:true, max:100, ticks:{color:tickC,font:{size:11},callback:v=>v+'%'}, grid:{color:gridC} },
                }
            }
        });
    },

    // ── Chart.js — évolution CA 12 mois glissants ─────────────────────
    renderRevenueTrend() {
        const canvas = document.getElementById('chart-revenue-trend');
        const data = D.revenueByMonth;
        if (!canvas || !data?.length || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        new Chart(canvas, {
            type:'line',
            data:{
                labels: data.map(d=>d.label),
                datasets:[{
                    label: 'CA mensuel',
                    data: data.map(d=>d.total),
                    borderColor:'#16a34a',
                    backgroundColor:'rgba(22,163,74,.18)',
                    borderWidth:2.5, tension:.35, fill:true,
                    pointBackgroundColor:'#16a34a', pointRadius:4, pointHoverRadius:6,
                }],
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                    label: ctx => ' ' + new Intl.NumberFormat('fr-FR').format(Math.round(ctx.parsed.y)) + ' FCFA',
                }}},
                scales:{
                    x:{ ticks:{color:tickC,font:{size:11}}, grid:{display:false} },
                    y:{ beginAtZero:true, ticks:{color:tickC,font:{size:11},callback:v=>v>=1e6?(v/1e6).toFixed(1)+'M':(v>=1e3?(v/1e3).toFixed(0)+'K':v)}, grid:{color:gridC} },
                }
            }
        });
    },

    // ── Chart.js — classement top panneaux (barres horizontales) ──────
    renderTopPanels() {
        const canvas = document.getElementById('chart-top-panels');
        const data = D.topPanels;
        if (!canvas || !data?.length || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        const top = data.slice(0, 15);
        // Dégradé vert (le plus loué = vert vif → vert pâle)
        const colors = top.map((_, i) => {
            const t = i / Math.max(top.length - 1, 1);
            const r = Math.round(22 + (134 - 22) * t);
            const g = Math.round(163 + (239 - 163) * t);
            const b = Math.round(74 + (172 - 74) * t);
            return `rgb(${r},${g},${b})`;
        });
        new Chart(canvas, {
            type:'bar',
            data:{
                labels: top.map(p => p.reference + (p.commune_name ? ' — ' + p.commune_name : '')),
                datasets:[{
                    label:'Jours occupés',
                    data: top.map(p => Number(p.days_occupied) || 0),
                    backgroundColor: colors,
                    borderRadius:6, borderSkipped:false,
                }],
            },
            options:{
                indexAxis:'y',
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                    label: ctx => {
                        const p = top[ctx.dataIndex];
                        return [` ${ctx.parsed.x} jours occupés`, ` ${p.campaigns_count} campagne(s)`, ` CA estimé : ${new Intl.NumberFormat('fr-FR').format(Math.round(p.estimated_revenue || 0))} FCFA`];
                    },
                }}},
                scales:{
                    x:{ beginAtZero:true, ticks:{color:tickC,font:{size:11}}, grid:{color:gridC} },
                    y:{ ticks:{color:tickC,font:{size:10},autoSkip:false}, grid:{display:false} },
                }
            }
        });
    },

    // ── Chart.js — scatter occupation × revenus par commune (COMMIT B) ──
    renderOccVsRevenue() {
        const canvas = document.getElementById('chart-occ-revenue');
        const data = D.occVsRevenue || [];
        if (!canvas || !data.length || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        // Catégoriser chaque commune par quadrant
        const maxRev = Math.max(...data.map(d => d.revenue), 1);
        const points = data.map(d => {
            const occHigh = d.rate >= 50;
            const revHigh = d.revenue >= maxRev * 0.4;
            const color = occHigh && revHigh ? '#22c55e' :
                          occHigh && !revHigh ? '#ef4444' :
                          !occHigh && revHigh ? '#f59e0b' : '#94a3b8';
            return { x: d.rate, y: d.revenue, r: Math.min(Math.max(Math.sqrt(d.total) * 2.5, 5), 22), color, data: d };
        });
        new Chart(canvas, {
            type:'bubble',
            data:{
                datasets: points.map(p => ({
                    data:[{ x:p.x, y:p.y, r:p.r }],
                    backgroundColor: p.color + '99',
                    borderColor: p.color,
                    borderWidth: 1.5,
                    label: p.data.commune,
                })),
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{
                    legend:{display:false},
                    tooltip:{ callbacks:{
                        title: ctx => points[ctx[0].datasetIndex].data.commune,
                        label: ctx => {
                            const d = points[ctx.datasetIndex].data;
                            return [
                                ` Taux occupation : ${d.rate}%`,
                                ` CA : ${new Intl.NumberFormat('fr-FR').format(Math.round(d.revenue))} FCFA`,
                                ` Parc : ${d.total} pann. (${d.occupied} occupés)`,
                                ` Campagnes : ${d.campaigns}`,
                            ];
                        },
                    }},
                },
                scales:{
                    x:{
                        title:{display:true,text:"Taux d'occupation (%)",color:tickC,font:{size:11}},
                        beginAtZero:true, max:100,
                        ticks:{color:tickC,font:{size:11},callback:v=>v+'%'},
                        grid:{color:gridC},
                    },
                    y:{
                        title:{display:true,text:'CA (FCFA)',color:tickC,font:{size:11}},
                        beginAtZero:true,
                        ticks:{color:tickC,font:{size:11},callback:v=>v>=1e6?(v/1e6).toFixed(1)+'M':(v>=1e3?(v/1e3).toFixed(0)+'K':v)},
                        grid:{color:gridC},
                    },
                }
            }
        });
    },

    // ── Chart.js — doughnut motifs annulation + bar tranches inactivité ──
    renderInsightsCharts() {
        if (typeof Chart === 'undefined') return;
        const reasonLabels = {
            budget:'💸 Budget', zone:'📍 Zone', strategie:'🎯 Stratégie',
            report:'⏰ Report', concurrent:'🤝 Concurrent', autre:'❓ Autre',
        };
        const palette = ['#ef4444','#f97316','#e8a020','#3b82f6','#a855f7','#6b7280'];

        // Doughnut motifs d'annulation
        const cd = D.cancelReasons || [];
        const cCanvas = document.getElementById('chart-cancel-reasons');
        if (cCanvas && cd.length) {
            const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
            new Chart(cCanvas, {
                type:'doughnut',
                data:{
                    labels: cd.map(r => reasonLabels[r.cancellation_reason] || r.cancellation_reason),
                    datasets:[{
                        data: cd.map(r => Number(r.count) || 0),
                        backgroundColor: palette,
                        borderWidth: 2,
                        borderColor: isDark ? '#1e293b' : '#ffffff',
                    }],
                },
                options:{
                    responsive:true, maintainAspectRatio:false, cutout:'62%',
                    plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                        label: ctx => ` ${ctx.label} : ${ctx.parsed} campagne(s)`,
                    }}},
                }
            });
        }

        // Bar tranches inactivité
        const ib = D.inactivityBucket || {};
        const iCanvas = document.getElementById('chart-inactivity');
        if (iCanvas) {
            const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
            const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
            new Chart(iCanvas, {
                type:'bar',
                data:{
                    labels:['3-6 mois','6-12 mois','> 12 mois'],
                    datasets:[{
                        data:[Number(ib['3_to_6']||0), Number(ib['6_to_12']||0), Number(ib['12_plus']||0)],
                        backgroundColor:['#d97706','#ea580c','#dc2626'],
                        borderRadius:6, borderSkipped:false,
                    }],
                },
                options:{
                    responsive:true, maintainAspectRatio:false,
                    plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                        label: ctx => ` ${ctx.parsed.y} client(s)`,
                    }}},
                    scales:{
                        x:{ ticks:{color:tickC,font:{size:11}}, grid:{display:false} },
                        y:{ beginAtZero:true, ticks:{color:tickC,font:{size:11},precision:0}, grid:{display:false} },
                    }
                }
            });
        }
    },
};

// Init graphiques de l'onglet par défaut (occupation) au chargement
document.addEventListener('DOMContentLoaded', () => {
    RPT.renderEvol();
    RPT.renderOccupationTrend();
    RPT._evolDone = true;
});

// ══════════════════════════════
// DRILLDOWN COMMUNE — module
// ══════════════════════════════
window.CommuneDrilldown = (function () {
    const overlay = document.getElementById('commune-modal');
    const body    = document.getElementById('cm-body');
    const loading = document.getElementById('cm-loading');
    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));

    const statusLabels = {
        libre: 'Libre', occupe: 'Occupé', option: 'Option',
        confirme: 'Confirmé', maintenance: 'Maintenance',
    };

    function renderStats(stats, commune) {
        const cards = [
            ['Panneaux',     fmt(stats.total),       'var(--accent)'],
            ["Taux d'occ.",  stats.taux + '%',       stats.taux >= 75 ? '#ef4444' : (stats.taux >= 50 ? '#f97316' : (stats.taux >= 25 ? '#e8a020' : '#22c55e'))],
            ['Occupés',      fmt(stats.occupes),     '#ef4444'],
            ['Libres',       fmt(stats.libres),      '#22c55e'],
            ['Maintenance',  fmt(stats.maintenance), '#6b7280'],
            ['Tarif moyen',  stats.tarif_moyen ? fmt(stats.tarif_moyen) + ' FCFA' : '—', '#3b82f6'],
            ["CA " + (D.annee || ''), stats.ca_annee ? fmt(stats.ca_annee) + ' FCFA' : '—', '#a855f7'],
        ];
        return cards.map(([lbl, val, color]) => `
            <div class="cm-stat" style="border-left-color:${color}">
                <div class="lbl">${lbl}</div>
                <div class="val" style="color:${color}">${val}</div>
            </div>
        `).join('');
    }

    function renderTopClients(rows) {
        if (!rows || !rows.length) {
            return '<div style="padding:14px 16px;color:var(--text3);font-size:12px;text-align:center;">Aucune campagne client cette année.</div>';
        }
        return `
            <table class="cm-table">
                <thead><tr><th>#</th><th>Client</th><th class="r">Campagnes</th><th class="r">CA cumulé</th></tr></thead>
                <tbody>
                    ${rows.map((r, i) => `
                        <tr>
                            <td style="width:40px;">${i === 0 ? '🥇' : (i === 1 ? '🥈' : (i === 2 ? '🥉' : i + 1))}</td>
                            <td><strong>${r.url ? `<a href="${r.url}">${r.name}</a>` : r.name}</strong></td>
                            <td class="r">${fmt(r.nb)}</td>
                            <td class="r"><strong style="color:var(--accent)">${fmt(r.ca)} FCFA</strong></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderCampagnes(rows) {
        if (!rows || !rows.length) {
            return '<div style="padding:14px 16px;color:var(--text3);font-size:12px;text-align:center;">Aucune campagne touchant cette commune dans la période.</div>';
        }
        const statusColors = {
            actif: '#22c55e', pose: '#22c55e', planifie: '#3b82f6',
            pause: '#f97316', termine: '#6b7280', annule: '#ef4444',
        };
        return `
            <table class="cm-table">
                <thead><tr><th>Campagne</th><th>Client</th><th>Période</th><th>Statut</th><th class="r">Montant</th></tr></thead>
                <tbody>
                    ${rows.map(c => `
                        <tr>
                            <td><a href="${c.url}"><strong>${c.name}</strong></a></td>
                            <td>${c.client}</td>
                            <td style="font-size:11px;color:var(--text3)">${c.start_date} → ${c.end_date}</td>
                            <td><span class="cm-status-pill" style="background:${(statusColors[c.status]||'#6b7280')}22;color:${statusColors[c.status]||'#6b7280'}">${c.status}</span></td>
                            <td class="r"><strong>${c.amount > 0 ? fmt(c.amount) + ' FCFA' : (c.amount === 0 ? '0 FCFA · Offert' : '—')}</strong></td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    function renderPanels(rows) {
        if (!rows || !rows.length) {
            return '<div style="padding:14px 16px;color:var(--text3);font-size:12px;text-align:center;">Aucun panneau installé.</div>';
        }
        const tauxColor = (t) =>
            t >= 75 ? '#ef4444' : (t >= 50 ? '#f97316' : (t >= 25 ? '#e8a020' : '#22c55e'));
        return `
            <table class="cm-table">
                <thead>
                    <tr>
                        <th>Réf.</th><th>Nom</th><th>Format</th><th>Zone</th><th>Statut</th>
                        <th class="r">Tarif/mois</th>
                        <th class="r" title="Taux d'occupation sur la période sélectionnée (jours occupés / jours période)">Occ. période</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(p => {
                        const t = p.taux || 0;
                        const c = tauxColor(t);
                        return `
                        <tr>
                            <td><a href="${p.url}" style="font-family:monospace;font-weight:700;">${p.reference}</a></td>
                            <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${p.name || '—'}</td>
                            <td>${p.format}${p.is_lit ? ' · 💡' : ''}</td>
                            <td style="color:var(--text3);font-size:11px;">${p.zone}</td>
                            <td><span class="cm-status-pill cm-status-${p.status}">${statusLabels[p.status] || p.status}</span></td>
                            <td class="r">${p.rate > 0 ? fmt(p.rate) + ' FCFA' : (p.rate === 0 ? '0 FCFA' : '—')}</td>
                            <td class="r" title="${p.busy_days || 0}j occupés${p.campaigns ? ' · ' + p.campaigns + ' campagne(s)' : ''}">
                                <div style="display:flex;align-items:center;gap:6px;justify-content:flex-end;">
                                    <div style="width:46px;height:5px;background:var(--surface2);border-radius:3px;overflow:hidden;">
                                        <div style="width:${t}%;height:100%;background:${c};"></div>
                                    </div>
                                    <strong style="color:${c};min-width:34px;text-align:right;">${t}%</strong>
                                </div>
                            </td>
                        </tr>
                    `;}).join('')}
                </tbody>
            </table>
        `;
    }

    return {
        async open(communeId) {
            overlay.style.display = 'flex';
            body.style.display = 'none';
            loading.style.display = 'block';
            document.body.style.overflow = 'hidden';

            try {
                // Lot 8.2 — Passe la période active au drilldown pour que le
                // taux d'occupation panneau soit calculé sur la même fenêtre
                // que le rapport principal.
                const params = new URLSearchParams({
                    annee:   D.annee   || new Date().getFullYear(),
                    mois_du: D.moisDu  || 1,
                    mois_au: D.moisAu  || 12,
                });
                const url = `/admin/rapports/communes/${communeId}/detail?${params}`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                document.getElementById('cm-name').textContent = data.commune.name;
                document.getElementById('cm-stats').innerHTML = renderStats(data.stats, data.commune);
                document.getElementById('cm-top-clients').innerHTML = renderTopClients(data.top_clients);
                document.getElementById('cm-campagnes').innerHTML = renderCampagnes(data.campagnes);
                document.getElementById('cm-panels').innerHTML = renderPanels(data.panels);
                document.getElementById('cm-panels-count').textContent = (data.panels || []).length;

                loading.style.display = 'none';
                body.style.display = 'block';
            } catch (e) {
                console.error(e);
                loading.innerHTML = '<div style="color:#ef4444;">Erreur de chargement. Réessayez.</div>';
            }
        },
        close() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
        },
    };
})();

// ══════════════════════════════
// DRILLDOWN CLIENT — module (COMMIT B)
// ══════════════════════════════
window.ClientDrilldown = (function () {
    const overlay = document.getElementById('client-modal');
    const body    = document.getElementById('cl-body');
    const loading = document.getElementById('cl-loading');
    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));
    let revChart = null;

    const statusColors = {
        actif:'#22c55e', termine:'#6b7280', planifie:'#3b82f6',
        confirme:'#3b82f6', pause:'#f97316', annule:'#ef4444',
        option:'#e8a020',
    };

    function renderSummary(s) {
        const cards = [
            ['Campagnes',      fmt(s.total_campaigns),                                'var(--accent)'],
            ['CA total',       s.total_revenue > 0 ? fmt(s.total_revenue) + ' FCFA' : '—', '#16a34a'],
            ['Ticket moyen',   s.avg_ticket > 0    ? fmt(s.avg_ticket)    + ' FCFA' : '—', '#3b82f6'],
            ['Annulées',       fmt(s.cancelled) + ' (' + s.cancel_rate + '%)',       s.cancel_rate > 20 ? '#ef4444' : '#a855f7'],
            ['Dernière camp.', s.last_campaign || '—',                               '#0ea5e9'],
            ['Inactivité',     s.months_inactive !== null ? s.months_inactive + ' mois' : '—',
                               (s.months_inactive ?? 0) > 6 ? '#dc2626' : ((s.months_inactive ?? 0) > 3 ? '#f59e0b' : '#22c55e')],
        ];
        return cards.map(([lbl, val, color]) => `
            <div class="cm-stat" style="border-left-color:${color}">
                <div class="lbl">${lbl}</div>
                <div class="val" style="color:${color};font-size:16px">${val}</div>
            </div>
        `).join('');
    }

    function renderCommunes(rows) {
        if (!rows?.length) return '<div style="padding:14px;color:var(--text3);font-size:12px;text-align:center">Aucune commune exploitée.</div>';
        return `<table class="cm-table">
            <thead><tr><th>Commune</th><th class="r">Pann.</th><th class="r">Camp.</th><th class="r">CA</th></tr></thead>
            <tbody>${rows.map(r => `
                <tr>
                    <td><strong>${r.commune}</strong></td>
                    <td class="r">${fmt(r.panels_count)}</td>
                    <td class="r">${fmt(r.campaigns_count)}</td>
                    <td class="r"><strong style="color:var(--accent)">${fmt(r.revenue)}</strong></td>
                </tr>
            `).join('')}</tbody></table>`;
    }

    function renderPanels(rows) {
        if (!rows?.length) return '<div style="padding:14px;color:var(--text3);font-size:12px;text-align:center">Aucun panneau loué.</div>';
        return `<table class="cm-table">
            <thead><tr><th>Réf.</th><th>Commune</th><th class="r">Loc.</th><th class="r">CA</th></tr></thead>
            <tbody>${rows.map(r => `
                <tr>
                    <td><strong style="font-family:monospace">${r.reference}</strong></td>
                    <td style="color:var(--text3);font-size:11px">${r.commune || '—'}</td>
                    <td class="r">${fmt(r.campaigns_count)}</td>
                    <td class="r"><strong style="color:var(--accent)">${fmt(r.revenue)}</strong></td>
                </tr>
            `).join('')}</tbody></table>`;
    }

    function renderCampaigns(rows) {
        if (!rows?.length) return '<div style="padding:14px;color:var(--text3);font-size:12px;text-align:center">Aucune campagne.</div>';
        return `<table class="cm-table">
            <thead><tr><th>Campagne</th><th>Période</th><th>Statut</th><th class="r">Pann.</th><th class="r">Montant</th></tr></thead>
            <tbody>${rows.map(c => `
                <tr>
                    <td><a href="${c.url}"><strong>${c.name || '—'}</strong></a>${c.cancellation_reason ? `<div style="font-size:10px;color:#ef4444;margin-top:2px">Motif : ${c.cancellation_reason}</div>` : ''}</td>
                    <td style="font-size:11px;color:var(--text3)">${c.start_date} → ${c.end_date}</td>
                    <td><span class="cm-status-pill" style="background:${(statusColors[c.status]||'#6b7280')}22;color:${statusColors[c.status]||'#6b7280'}">${c.status}</span></td>
                    <td class="r">${fmt(c.panels_count)}</td>
                    <td class="r"><strong>${c.total_amount > 0 ? fmt(c.total_amount) + ' FCFA' : '—'}</strong></td>
                </tr>
            `).join('')}</tbody></table>`;
    }

    function renderRevenueChart(rows) {
        const canvas = document.getElementById('cl-revenue-chart');
        if (!canvas || !rows?.length || typeof Chart === 'undefined') return;
        if (revChart) { revChart.destroy(); revChart = null; }
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        revChart = new Chart(canvas, {
            type:'bar',
            data:{
                labels: rows.map(r => r.label),
                datasets:[{
                    label:'CA',
                    data: rows.map(r => Number(r.total) || 0),
                    backgroundColor:'rgba(232,160,32,.8)',
                    borderRadius:5, borderSkipped:false,
                }],
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                    label: ctx => ' ' + new Intl.NumberFormat('fr-FR').format(Math.round(ctx.parsed.y)) + ' FCFA',
                }}},
                scales:{
                    x:{ ticks:{color:tickC,font:{size:10}}, grid:{display:false} },
                    y:{ beginAtZero:true, ticks:{color:tickC,font:{size:10},callback:v=>v>=1e6?(v/1e6).toFixed(1)+'M':(v>=1e3?(v/1e3).toFixed(0)+'K':v)}, grid:{color:gridC} },
                }
            }
        });
    }

    return {
        async open(clientId) {
            overlay.style.display = 'flex';
            body.style.display = 'none';
            loading.style.display = 'block';
            document.body.style.overflow = 'hidden';

            try {
                const url = `/admin/rapports/clients/${clientId}/detail`;
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                document.getElementById('cl-name').textContent = data.client.name;
                document.getElementById('cl-meta').textContent =
                    (data.client.ncc ? 'NCC : ' + data.client.ncc : '') +
                    (data.client.email ? (data.client.ncc ? ' · ' : '') + data.client.email : '') +
                    (data.client.phone ? ' · ' + data.client.phone : '');
                document.getElementById('cl-link').href = data.client.url;
                document.getElementById('cl-summary').innerHTML = renderSummary(data.summary);
                document.getElementById('cl-communes').innerHTML = renderCommunes(data.top_communes);
                document.getElementById('cl-panels').innerHTML = renderPanels(data.top_panels);
                document.getElementById('cl-campaigns').innerHTML = renderCampaigns(data.campaigns);
                document.getElementById('cl-camp-count').textContent = (data.campaigns || []).length;

                loading.style.display = 'none';
                body.style.display = 'block';
                // Le canvas a besoin d'être visible avant que Chart.js mesure ses dimensions
                requestAnimationFrame(() => renderRevenueChart(data.revenue_month));
            } catch (e) {
                console.error(e);
                loading.innerHTML = '<div style="color:#ef4444;">Erreur de chargement. Réessayez.</div>';
            }
        },
        close() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
            if (revChart) { revChart.destroy(); revChart = null; }
        },
    };
})();

// Échap = fermer le drilldown
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (document.getElementById('commune-modal')?.style.display === 'flex') {
            window.CommuneDrilldown.close();
        }
        if (document.getElementById('client-modal')?.style.display === 'flex') {
            window.ClientDrilldown.close();
        }
    }
});

})();
</script>
@endpush

</x-admin-layout>

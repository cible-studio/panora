<x-admin-layout title="Rapports & Analyses">

{{-- ════ RBAC — variables disponibles GLOBALEMENT dans la vue ════
     Définies au tout début pour qu'AUCUN bloc @php ne puisse hit un
     $isCommercial undefined (cas reproduit en prod après déploiement
     d'un commit qui référençait $isCommercial avant sa définition
     locale). Source unique = la chaîne role->value. --}}
@php
    $roleValue    = auth()->user()?->role?->value;
    $isAdmin      = $roleValue === 'admin';
    $isMP         = $roleValue === 'mediaplanner';
    $isCommercial = $roleValue === 'commercial';
@endphp

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
    // Données onglet Clients
    clientRevenueDist: {!! json_encode($clientRevenueDist) !!},
    // Données onglet Campagnes
    cancellationTrend: {!! json_encode($cancellationTrend->values()) !!},
    campaignStats:     {!! json_encode($campaignStats) !!},
};
</script>

{{-- ════ ACTIONS RAPIDES — exports (COMMIT D) ════ --}}
@php
    // Filtres communs propagés à tous les exports (= ceux affichés
    // dans la barre de filtres). Inclut filter_zone (Abidjan/Intérieur).
    $exportFilters = request()->only([
        'preset','from','to','annee','mois_du','mois_au',
        'filter_commune_id','filter_city','filter_client_id','filter_category_id','filter_zone',
    ]);
@endphp
<div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="{{ route('admin.rapports.export.excel', $exportFilters) }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#16a34a;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Télécharger le dashboard complet en Excel (8 feuilles)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exporter Excel
    </a>
    <a href="{{ route('admin.rapports.export.pdf', $exportFilters) }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Télécharger une synthèse exécutive PDF (1 page)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8"/></svg>
        Synthèse PDF
    </a>
    {{-- Séparateur visuel --}}
    <span style="width:1px;height:24px;background:var(--border);margin:0 4px"></span>
    {{-- Exports dédiés : panneaux + taux d'occupation (filtrés par zone si sélectionnée) --}}
    <a href="{{ route('admin.rapports.export.panels-occupation-excel', $exportFilters) }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#0f766e;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Exporter la liste complète des panneaux avec leur taux d'occupation sur la période (Excel)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        Panneaux + occupation (Excel)
    </a>
    <a href="{{ route('admin.rapports.export.panels-occupation-pdf', $exportFilters) }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#9333ea;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Exporter la liste complète des panneaux avec leur taux d'occupation (PDF A4 paysage)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8"/></svg>
        Panneaux + occupation (PDF)
    </a>
</div>

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
        <select name="filter_zone" onchange="this.form.submit()"
                style="height:32px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);min-width:130px;font-weight:600"
                title="Zone : Abidjan ou Intérieur (toutes les villes hors Abidjan)">
            <option value="">🌍 Toutes zones</option>
            <option value="abidjan"   {{ ($filterZone ?? null) === 'abidjan'   ? 'selected' : '' }}>🏙️ Abidjan</option>
            <option value="interieur" {{ ($filterZone ?? null) === 'interieur' ? 'selected' : '' }}>🌾 Intérieur</option>
        </select>
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
        @if($filterCommune || $filterCity || $filterClient || $filterCategory || ($filterZone ?? null) || $currentPreset)
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

    {{-- Annulations entreprise + Taxes communales : admin/MP only.
         Pour le commercial, on cache les liens (les routes sont déjà
         bloquées côté backend, mais évite un 403 si l'admin a partagé
         le lien ou si l'admin a cliqué par erreur côté UI commercial). --}}
    @if(auth()->user()?->role?->value !== 'commercial')
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
    @endif
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
        // Avant : 'zones' → l'onglet "Zones & Communes" est masqué pour
        // commercial. On redirige vers 'decap' qui est conservé.
        'tab'   => 'decap',
        'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    ],
];

// RBAC : on n'affiche que les KPI cards alignées avec les onglets
// autorisés au rôle courant. Détail :
//   Admin       : 6 cards (Taux occupation, Libres, CA, Clients,
//                 Maintenance, À décaper).
//   MP          : 5 cards — exclut 'ca' (CA stratégique entreprise).
//                 Garde Occupation/Libres/Clients/Maintenance/Décaper.
//   Commercial  : 2 cards — CA filtré + À décaper.
// NB : on re-resout le role ici localement (au lieu de reutiliser le
//      scope de la vue) car la vue est appelée de mani
// de composant Blade, le partage de scope n'est pas garanti selon la
// version de Laravel — d'ou l'undefined variable signale en prod.
$kpiRole = auth()->user()?->role?->value;
$kpiCardsByRole = [
    'admin'        => null, // tous (6 cards)
    'mediaplanner' => ['occupation', 'clients', 'zones', 'decap'], // 5 (sans 'ca')
    'commercial'   => ['ca', 'decap'],                              // 2
];
$allowedKpiTabs = $kpiCardsByRole[$kpiRole] ?? null;
if ($allowedKpiTabs !== null) {
    $kpiCards = array_values(array_filter(
        $kpiCards,
        fn($c) => in_array($c['tab'], $allowedKpiTabs, true)
    ));
}
@endphp

<div id="kpi-cards" style="display:grid;grid-template-columns:repeat({{ max(1, count($kpiCards)) }},1fr);gap:10px;margin-bottom:20px">
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
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:6px;flex-wrap:wrap">
    @php
    // ── RBAC module Rapport — politique par rôle ──────────────
    // Admin       : vue globale entreprise complète (CA stratégique,
    //               EBITDA, synthèse exécutive direction, exports).
    // MP          : vue PRODUCTION / opérationnelle. Parc, performance
    //               panneaux, géo, clients, taxes, motifs annulation,
    //               décappages. PAS de CA global ni d'insights stratégiques
    //               (réservés à la direction).
    // Commercial  : vue PERSONNELLE filtrée à ses campagnes. Périodes,
    //               ses campagnes, SON CA, ses décappages.
    // NB : re-resolution locale du role (cf. note sur le scope @php).
    $tabRole = auth()->user()?->role?->value;

    $onglets = [
        ['id'=>'occupation','icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>','label'=>"Occupation"],
        ['id'=>'panneaux',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>','label'=>'Performance panneaux'],
        ['id'=>'periodes',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>','label'=>'Périodes'],
        ['id'=>'campagnes', 'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>','label'=>'Campagnes'],
        ['id'=>'ca',        'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>','label'=>'CA & Revenus'],
        ['id'=>'zones',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>','label'=>'Zones & Communes'],
        ['id'=>'clients',   'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>','label'=>'Clients'],
        ['id'=>'decap',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>','label'=>'Décappages'],
        ['id'=>'insights',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h.01M15 11h.01M18 21l-3-3H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-3l-3 3z"/></svg>','label'=>'Insights & Alertes'],
    ];

    // Mapping rôle → onglets autorisés (null = tous = admin).
    $tabsByRole = [
        'admin'        => null, // tous les 9
        'mediaplanner' => [     // 7 : production / opérationnel
            'occupation', 'panneaux', 'periodes', 'campagnes',
            'zones', 'clients', 'decap',
            // EXCLUS pour MP : 'ca' (CA stratégique entreprise),
            //                  'insights' (synthèse exécutive direction).
        ],
        'commercial'   => [     // 4 : strictement personnel filtré
            'periodes', 'campagnes', 'ca', 'decap',
        ],
    ];
    $allowedTabs = $tabsByRole[$tabRole] ?? null;
    if ($allowedTabs !== null) {
        $onglets = array_values(array_filter(
            $onglets,
            fn($o) => in_array($o['id'], $allowedTabs, true)
        ));
    }
    // Ids autorisés (sert plus bas à exclure les <div id="panel-X"> du DOM)
    $allowedTabIds = array_column($onglets, 'id');
    @endphp
    @foreach($onglets as $o)
    <button id="tab-{{ $o['id'] }}" onclick="RPT.switchTab('{{ $o['id'] }}')"
            class="rpt-tab {{ $loop->first ? 'active' : '' }}">
        <span style="display:flex;align-items:center;gap:6px">
            {!! $o['icon'] !!} {{ $o['label'] }}
            @if($o['id'] === 'decap' && ($decapStats['overdue'] ?? 0) > 0)
                <span title="Décappages en retard"
                      style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;line-height:1;animation:rpt-pulse 1.6s ease-in-out infinite">{{ $decapStats['overdue'] }}</span>
            @endif
        </span>
    </button>
    @endforeach
</div>

{{-- ══════════════════════════════════
     ONGLET 1 — OCCUPATION (admin/MP only)
══════════════════════════════════ --}}
@if(in_array('occupation', $allowedTabIds, true))
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

@endif {{-- panel-occupation --}}

{{-- ══════════════════════════════════
     ONGLET 2 — PÉRIODES (commercial OK : scopé via applyCampaignFilters)
══════════════════════════════════ --}}
@if(in_array('periodes', $allowedTabIds, true))
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

@endif {{-- panel-periodes --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — CAMPAGNES (commercial OK : scopé)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('campagnes', $allowedTabIds, true))
<div id="panel-campagnes" class="rpt-panel" style="display:none">

    {{-- Statuts campagnes : 5 cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px" class="rpt-grid-5">
        @php
            $statusCards = [
                ['Total', $campaignStats['total'],     '#6b7280', 'sur la période'],
                ['Actives', $campaignStats['active'],  '#22c55e', 'en cours'],
                ['Planifiées', $campaignStats['planned'], '#3b82f6', 'à venir'],
                ['Terminées', $campaignStats['done'],  '#94a3b8', 'historique'],
                ['Annulées', $campaignStats['cancelled'], '#dc2626', $campaignStats['cancel_rate'] . '% du total'],
            ];
        @endphp
        @foreach($statusCards as [$lbl, $val, $col, $sub])
            <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid {{ $col }};border-radius:12px;padding:14px 16px">
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">{{ $lbl }}</div>
                <div style="font-size:24px;font-weight:800;color:{{ $col }};margin-top:4px;font-variant-numeric:tabular-nums">{{ number_format($val) }}</div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    {{-- Évolution annulations + doughnut motifs côte à côte --}}
    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">

        {{-- Évolution mensuelle annulations (line chart) --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Tendance des annulations — 12 mois</span>
                @php
                    $td  = $cancellationPatterns['trend_direction'];
                    $tp  = $cancellationPatterns['trend_pct'];
                    $tdLabel = $td === 'up' ? '↗ Hausse' : ($td === 'down' ? '↘ Baisse' : '→ Stable');
                    $tdCol   = $td === 'up' ? '#dc2626' : ($td === 'down' ? '#16a34a' : '#6b7280');
                @endphp
                <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $tdCol }}22;color:{{ $tdCol }};font-size:10px;font-weight:700">{{ $tdLabel }} {{ abs($tp) }}%</span>
            </div>
            <div style="position:relative;width:100%;height:240px">
                <canvas id="chart-cancel-trend" role="img" aria-label="Tendance annulations"></canvas>
            </div>
        </div>

        {{-- Doughnut motifs annulation --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l8 4"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Motifs d'annulation</span>
                <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $cancelReasons->sum('count') }} annulation(s)</span>
            </div>
            @if($cancelReasons->isEmpty())
                <div style="padding:60px 14px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune annulation sur la période.</div>
            @else
                <div style="position:relative;width:100%;height:240px">
                    <canvas id="chart-cancel-reasons-camp" role="img" aria-label="Motifs annulation"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Causes récurrentes : tableau détaillé + clients récidivistes --}}
    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">

        {{-- Détail motifs avec % et CA perdu estimé --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span style="font-size:12px;font-weight:700;color:var(--text)">Causes récurrentes (motifs prédéfinis)</span>
            </div>
            @if($cancelReasons->isEmpty())
                <div style="padding:30px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun motif enregistré.</div>
            @else
                @php
                    $reasonLabelsFull = [
                        'budget' => '💸 Budget client',
                        'zone' => '📍 Choix de zone',
                        'strategie' => '🎯 Changement stratégique',
                        'report' => '⏰ Report client',
                        'concurrent' => '🤝 Choix concurrent',
                        'autre' => '❓ Autre motif',
                    ];
                    $totalCancFull = $cancelReasons->sum('count');
                @endphp
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:var(--surface2)">
                            <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Motif</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Nb</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">% du total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cancelReasons as $r)
                            @php $pct = $totalCancFull > 0 ? round(($r->count / $totalCancFull) * 100, 1) : 0; @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 12px;color:var(--text)">{{ $reasonLabelsFull[$r->cancellation_reason] ?? ucfirst($r->cancellation_reason) }}</td>
                                <td style="padding:8px 12px;text-align:right;font-weight:700;color:#dc2626">{{ $r->count }}</td>
                                <td style="padding:8px 12px;text-align:right">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px">
                                        <div style="width:50px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
                                            <div style="height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#ef4444,#f97316)"></div>
                                        </div>
                                        <span style="font-size:11px;font-weight:700;color:var(--text);min-width:36px;text-align:right">{{ $pct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Clients récidivistes --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:12px;font-weight:700;color:var(--text)">Clients récidivistes (>1 annulation)</span>
                <span style="margin-left:auto;font-size:10px;color:var(--text3)">Signal faible</span>
            </div>
            @if($cancellationPatterns['repeat_offenders']->isEmpty())
                <div style="padding:30px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun client récidiviste détecté.</div>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:var(--surface2)">
                            <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Client</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Annulations</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">CA perdu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cancellationPatterns['repeat_offenders'] as $client)
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="ClientDrilldown.open({{ $client->id }})" title="Voir historique client">
                                <td style="padding:8px 12px;font-weight:600;color:var(--text)">{{ $client->name }}</td>
                                <td style="padding:8px 12px;text-align:right">
                                    <span style="padding:2px 8px;border-radius:10px;background:rgba(220,38,38,.12);color:#dc2626;font-size:11px;font-weight:700">{{ $client->cancellations }}</span>
                                </td>
                                <td style="padding:8px 12px;text-align:right;font-size:11px;font-weight:600;color:var(--text2);font-variant-numeric:tabular-nums">{{ $client->lost_revenue > 0 ? number_format($client->lost_revenue, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Recommandations actionnables --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">💡 Recommandations pour réduire les annulations</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Généré automatiquement</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($cancellationRecos as $reco)
                @php
                    $rc = match($reco['severity']) {
                        'danger'  => ['#dc2626', 'rgba(220,38,38,.06)',  'rgba(220,38,38,.3)'],
                        'warning' => ['#d97706', 'rgba(245,158,11,.06)', 'rgba(245,158,11,.3)'],
                        'success' => ['#16a34a', 'rgba(34,197,94,.06)',  'rgba(34,197,94,.3)'],
                        default   => ['#3b82f6', 'rgba(59,130,246,.06)', 'rgba(59,130,246,.3)'],
                    };
                @endphp
                <div style="background:{{ $rc[1] }};border:1px solid {{ $rc[2] }};border-radius:10px;padding:12px 14px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <span style="font-size:16px">{{ $reco['icon'] }}</span>
                        <span style="font-size:12.5px;font-weight:700;color:{{ $rc[0] }}">{{ $reco['title'] }}</span>
                    </div>
                    <div style="font-size:11.5px;color:var(--text3);font-style:italic;margin-bottom:6px;line-height:1.5">{{ $reco['pattern'] }}</div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.55"><strong style="color:var(--text)">Action :</strong> {{ $reco['action'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@endif {{-- panel-campagnes --}}

{{-- ══════════════════════════════════
     ONGLET 3 — CA & REVENUS (commercial OK : scopé sur ses campagnes)
══════════════════════════════════ --}}
@if(in_array('ca', $allowedTabIds, true))
<div id="panel-ca" class="rpt-panel" style="display:none">

    {{-- 5 KPIs financiers : CA, ticket moyen, CA/panneau, CA/client, top client --}}
    @php
        $caParPanneau = $occupation['occupes'] > 0 ? round($caTotal / $occupation['occupes']) : 0;
        $caParClient  = $totalClients > 0 ? round($caTotal / $totalClients) : 0;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:18px" class="rpt-grid-5">
        @php
        $caKpis = [
            ['CA Période',          number_format($caTotal, 0, ',', ' ') . ' FCFA', '#e8a020', 'FCFA · ' . $totalCampagnes . ' campagnes'],
            ['Ticket moyen',        number_format($caTicketMoy, 0, ',', ' ') . ' FCFA', '#3b82f6', 'par campagne'],
            ['CA / panneau loué',   number_format($caParPanneau, 0, ',', ' ') . ' FCFA', '#16a34a', 'sur ' . number_format($occupation['occupes']) . ' panneaux occupés'],
            ['CA moyen / client',   number_format($caParClient, 0, ',', ' ') . ' FCFA', '#06b6d4', 'sur ' . number_format($totalClients) . ' clients actifs'],
            ['Top client',          $topClients->first()?->name ?? '—', '#a855f7', $topClients->first() ? number_format($topClients->first()->ca_total, 0, ',', ' ') . ' FCFA' : '—'],
        ];
        @endphp
        @foreach($caKpis as [$lbl, $val, $col, $sub])
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;border-top:3px solid {{ $col }}">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:5px">{{ $lbl }}</div>
            <div style="font-size:14px;font-weight:800;color:{{ $col }};line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $val }}">{{ $val }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:3px;line-height:1.3">{{ $sub }}</div>
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

    {{-- 🏆 Classement communes les plus rentables (top 15) --}}
    @if($revenueByCommune->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Classement communes les plus rentables</span>
            <span style="margin-left:auto;font-size:11px;color:var(--text3)">Top {{ $revenueByCommune->count() }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Commune','CA généré','Campagnes','Panneaux loués','CA / panneau'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueByCommune as $i => $r)
                        @php $caPerPanel = $r->panels_engaged > 0 ? round((float)$r->revenue / $r->panels_engaged) : 0; @endphp
                        <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                            onclick="CommuneDrilldown.open({{ $r->id }})"
                            title="Cliquer pour voir le détail commune"
                            onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                            <td style="padding:10px 16px;font-size:13px;color:var(--text3);font-weight:700">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i + 1)) }}</td>
                            <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $r->commune }}</td>
                            <td style="padding:10px 16px;font-size:13px;font-weight:700;color:#16a34a;font-variant-numeric:tabular-nums">{{ number_format((float) $r->revenue, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                            <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->campaigns_count) }}</td>
                            <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->panels_engaged) }}</td>
                            <td style="padding:10px 16px;font-size:11px;color:var(--text3);font-variant-numeric:tabular-nums">{{ $caPerPanel > 0 ? number_format($caPerPanel, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

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

@endif {{-- panel-ca --}}

{{-- ══════════════════════════════════
     ONGLET 4 — ZONES & COMMUNES (admin/MP only : parc géographique)
══════════════════════════════════ --}}
@if(in_array('zones', $allowedTabIds, true))
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

@endif {{-- panel-zones --}}

{{-- ══════════════════════════════════
     ONGLET 5 — CLIENTS (admin/MP only : top clients entreprise)
══════════════════════════════════ --}}
@if(in_array('clients', $allowedTabIds, true))
<div id="panel-clients" class="rpt-panel" style="display:none">

    {{-- 3 podiums : Top CA / Top Volume / Top Fréquence --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px" class="rpt-grid-clients">
        @php
            $podiums = [
                ['title' => 'Top CA', 'icon' => '💰', 'color' => '#16a34a', 'rows' => $topClientsByRev,  'metric' => 'revenue', 'unit' => 'FCFA'],
                ['title' => 'Top volume', 'icon' => '📊', 'color' => '#3b82f6', 'rows' => $topClientsByVol,  'metric' => 'volume',  'unit' => 'camp.'],
                ['title' => 'Top fréquence', 'icon' => '⏱️', 'color' => '#a855f7', 'rows' => $topClientsByFreq, 'metric' => 'frequency', 'unit' => '/mois'],
            ];
        @endphp
        @foreach($podiums as $podium)
            <div style="background:var(--surface);border:1px solid var(--border);border-top:3px solid {{ $podium['color'] }};border-radius:14px;padding:14px 16px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <span style="font-size:14px">{{ $podium['icon'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">{{ $podium['title'] }}</span>
                </div>
                @if($podium['rows']->isEmpty())
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">Aucun client sur la période.</div>
                @else
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($podium['rows'] as $i => $r)
                            @php
                                $value = match($podium['metric']) {
                                    'revenue'   => number_format((float) $r->total_revenue, 0, ',', ' '),
                                    'volume'    => (int) $r->campaigns_count,
                                    'frequency' => number_format($r->frequency, 2, ',', ' '),
                                };
                            @endphp
                            <div onclick="ClientDrilldown.open({{ $r->id }})"
                                 style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--surface2);border-radius:8px;cursor:pointer;transition:transform .15s"
                                 onmouseenter="this.style.transform='translateX(2px)'"
                                 onmouseleave="this.style.transform=''"
                                 title="Voir détail client">
                                <span style="font-size:12px;width:18px;flex-shrink:0">{{ $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) }}</span>
                                <span style="flex:1;min-width:0;font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->name }}</span>
                                <span style="font-size:11px;font-weight:700;color:{{ $podium['color'] }};font-variant-numeric:tabular-nums;text-align:right">{{ $value }} <span style="font-size:9px;font-weight:400;color:var(--text3)">{{ $podium['unit'] }}</span></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Répartition des revenus par client (doughnut) + Bandeau inactivité --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px" class="rpt-grid-2">

        {{-- Doughnut répartition CA par client --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l8 4"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Répartition du CA par client</span>
            </div>
            @if($clientRevenueDist['total'] > 0)
                <div style="position:relative;width:100%;height:240px">
                    <canvas id="chart-client-dist" role="img" aria-label="Répartition CA clients"></canvas>
                </div>
            @else
                <div style="padding:60px 14px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun revenu enregistré sur la période.</div>
            @endif
        </div>

        {{-- Alertes inactivité : 3/6/12 mois directement dans l'onglet Clients --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Clients inactifs — par tranche</span>
                <a href="#tab-insights" onclick="event.preventDefault();RPT.switchTab('insights');" style="margin-left:auto;font-size:10px;color:var(--accent);text-decoration:none;font-weight:600">Templates reconquête →</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
                <div style="text-align:center;padding:12px 8px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#d97706">{{ $inactivityBucket['3_to_6'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">3-6 mois</div>
                </div>
                <div style="text-align:center;padding:12px 8px;background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#ea580c">{{ $inactivityBucket['6_to_12'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">6-12 mois</div>
                </div>
                <div style="text-align:center;padding:12px 8px;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#dc2626">{{ $inactivityBucket['12_plus'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">> 12 mois</div>
                </div>
            </div>
            {{-- Top 5 inactifs > 12 mois pour action rapide --}}
            @if($inactiveClients12->isNotEmpty())
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Top 5 inactifs > 12 mois — priorité reconquête</div>
                <div style="display:flex;flex-direction:column;gap:5px">
                    @foreach($inactiveClients12->take(5) as $c)
                        <div onclick="ClientDrilldown.open({{ $c->id }})"
                             style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--surface2);border-radius:8px;cursor:pointer;transition:transform .15s"
                             onmouseenter="this.style.transform='translateX(2px)'"
                             onmouseleave="this.style.transform=''">
                            <span style="font-size:11px;font-weight:600;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c->name }}</span>
                            <span style="font-size:10px;color:var(--text3);font-family:ui-monospace,monospace">{{ $c->last_campaign_at ? \Carbon\Carbon::parse($c->last_campaign_at)->format('d/m/Y') : '—' }}</span>
                            <span style="font-size:10px;color:#dc2626;font-weight:700">↗</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Tableau portefeuille (existant) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Portefeuille clients — Activité complète</span>
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

@endif {{-- panel-clients --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — PERFORMANCE PANNEAUX (admin/MP only : parc global)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('panneaux', $allowedTabIds, true))
<div id="panel-panneaux" class="rpt-panel" style="display:none">

    {{-- Alertes performance panneaux (COMMIT E) --}}
    @if($panelAlerts->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Alertes performance panneaux</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Détection automatique</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:8px">
            @foreach($panelAlerts as $a)
                @php
                    $col = match($a['severity']) {
                        'danger'  => ['#dc2626', 'rgba(220,38,38,.06)',  'rgba(220,38,38,.3)'],
                        'warning' => ['#d97706', 'rgba(245,158,11,.06)', 'rgba(245,158,11,.3)'],
                        'success' => ['#16a34a', 'rgba(34,197,94,.06)',  'rgba(34,197,94,.3)'],
                        default   => ['#3b82f6', 'rgba(59,130,246,.06)', 'rgba(59,130,246,.3)'],
                    };
                @endphp
                <div style="background:{{ $col[1] }};border:1px solid {{ $col[2] }};border-radius:10px;padding:10px 12px">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                        <span style="font-size:14px">{{ $a['icon'] }}</span>
                        <span style="font-size:11.5px;font-weight:700;color:{{ $col[0] }}">{{ $a['title'] }}</span>
                    </div>
                    <div style="font-size:10.5px;color:var(--text2);line-height:1.5">{{ $a['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

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

    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

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
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="PanelDrilldown.open({{ $p->id }})" title="Cliquer pour l'historique d'occupation">
                                <td style="padding:8px;color:var(--text3);font-weight:700">{{ $i+1 }}</td>
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
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
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="PanelDrilldown.open({{ $p->id }})" title="Cliquer pour l'historique d'occupation">
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
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

    {{-- Périodes creuses détectées (panneaux > 60j sans campagne) — COMMIT E --}}
    @if($inactivePanels->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Périodes creuses — panneaux > 60 jours sans campagne</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $inactivePanels->count() }} panneau(x)</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:var(--surface2)">
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Commune</th>
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Dernière campagne</th>
                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Inactivité</th>
                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Tarif/mois</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inactivePanels as $p)
                        @php
                            $days = (int) ($p->days_inactive ?? 0);
                            $color = $p->last_end === null ? '#dc2626' : ($days > 180 ? '#dc2626' : '#d97706');
                            $bg    = $p->last_end === null ? 'rgba(220,38,38,.12)' : ($days > 180 ? 'rgba(220,38,38,.12)' : 'rgba(245,158,11,.12)');
                        @endphp
                        <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                            onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                            onclick="PanelDrilldown.open({{ $p->id }})" title="Voir l'historique">
                            <td style="padding:8px">
                                <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                                <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }}</div>
                            </td>
                            <td style="padding:8px;color:var(--text2)">{{ $p->commune_name ?? '—' }}</td>
                            <td style="padding:8px;color:var(--text3);font-family:ui-monospace,monospace;font-size:11px">
                                {{ $p->last_end ? \Carbon\Carbon::parse($p->last_end)->format('d/m/Y') : 'Jamais loué' }}
                            </td>
                            <td style="padding:8px;text-align:right">
                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:{{ $bg }};color:{{ $color }}">
                                    {{ $p->last_end === null ? 'Jamais loué' : $days . 'j' }}
                                </span>
                            </td>
                            <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ $p->monthly_rate > 0 ? number_format($p->monthly_rate, 0, ',', ' ') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

{{-- ════ MODAL DRILLDOWN PANNEAU (historique occupations) — COMMIT E ════ --}}
<div id="panel-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:20px;"
     onclick="if(event.target===this)PanelDrilldown.close()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:1080px;max-height:92vh;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.5px;">Panneau</div>
                <h2 id="pl-name" style="font-size:18px;font-weight:800;color:var(--text);margin-top:3px;">…</h2>
                <div id="pl-meta" style="font-size:11px;color:var(--text3);margin-top:2px;"></div>
            </div>
            <div style="display:flex;gap:8px;">
                <a id="pl-link" href="#" style="font-size:11px;font-weight:700;padding:6px 12px;background:var(--accent);color:#fff;border-radius:8px;text-decoration:none;">Fiche panneau →</a>
                <button type="button" onclick="PanelDrilldown.close()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);padding:6px 10px;border-radius:8px;" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background='none'">✕</button>
            </div>
        </div>
        <div id="pl-loading" style="padding:60px;text-align:center;color:var(--text3);font-size:13px;">
            <div class="rpt-spinner" style="display:inline-block;width:24px;height:24px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:rpt-spin .7s linear infinite;vertical-align:middle;margin-right:8px;"></div>
            Chargement…
        </div>
        <div id="pl-body" style="display:none;padding:18px 22px;overflow-y:auto;flex:1;">
            <div id="pl-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:18px;"></div>
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Jours occupés mensuel — 12 derniers mois</h3>
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:18px;position:relative;height:200px;">
                <canvas id="pl-monthly-chart" role="img" aria-label="Occupation mensuelle panneau"></canvas>
            </div>
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Top clients sur ce panneau</h3>
            <div id="pl-top-clients" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-bottom:18px;"></div>
            <h3 style="font-size:12px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px;">Historique campagnes (<span id="pl-camp-count">0</span>)</h3>
            <div id="pl-campaigns" style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;overflow:hidden;"></div>
        </div>
    </div>
</div>

@endif {{-- panel-panneaux --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — DÉCAPPAGES (commercial OK : scopé via decapStats + decapList)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('decap', $allowedTabIds, true))
<div id="panel-decap" class="rpt-panel" style="display:none">

    {{-- ⚠ BANDEAU CRITIQUE : campagnes expirées non décappées.
         Toujours rendu mais hidden via data-decap-overdue-banner pour que
         le JS puisse le réafficher en cas d'unmark sans reload de page. --}}
    <div data-decap-overdue-banner
         style="background:linear-gradient(135deg,rgba(220,38,38,.12),rgba(220,38,38,.06));border:1.5px solid rgba(220,38,38,.4);border-radius:14px;padding:16px 20px;margin-bottom:16px;display:{{ ($decapStats['overdue'] ?? 0) > 0 ? 'flex' : 'none' }};align-items:center;gap:14px"
         data-init-display="flex">
        <div style="font-size:32px;line-height:1;animation:rpt-pulse 1.6s ease-in-out infinite;width:44px;height:44px;border-radius:50%;background:rgba(220,38,38,.15);display:flex;align-items:center;justify-content:center">⚠️</div>
        <div style="flex:1">
            <div style="font-size:14px;font-weight:800;color:#dc2626;margin-bottom:3px">
                <span data-decap-overdue-banner-count>{{ $decapStats['overdue'] }}</span> panneau(x) en retard de décappage
            </div>
            <div style="font-size:12px;color:var(--text2);line-height:1.5">Campagne(s) terminée(s) depuis plus de <strong>7 jours</strong> avec affichage non retiré sur le terrain. Risque d'amende municipale et de plainte client. Planifiez les tournées de décappage en priorité.</div>
        </div>
        <a href="#" onclick="event.preventDefault();document.getElementById('decap-overdue-list')?.scrollIntoView({behavior:'smooth',block:'start'});"
           style="padding:8px 14px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap">
            Voir les retards →
        </a>
    </div>

    {{-- Bandeau stats décappage (COMMIT C) — data-kpi=* permet la MAJ live
         après mark/unmark sans recharger toute la page. --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #6366f1;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Panneaux concernés</div>
            <div data-decap-kpi="total" style="font-size:24px;font-weight:800;color:var(--text);margin-top:4px">{{ number_format($decapStats['total']) }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px">90 derniers jours</div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #22c55e;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Décappés</div>
            <div data-decap-kpi="decapped" style="font-size:24px;font-weight:800;color:#16a34a;margin-top:4px">{{ number_format($decapStats['decapped']) }}</div>
            <div style="font-size:10px;color:#16a34a;margin-top:2px;font-weight:600"><span data-decap-kpi="rate">{{ $decapStats['rate'] }}</span>% complétés</div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #f59e0b;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">En attente</div>
            <div data-decap-kpi="pending" style="font-size:24px;font-weight:800;color:#d97706;margin-top:4px">{{ number_format($decapStats['pending']) }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px">À planifier</div>
        </div>
        <div data-decap-kpi-overdue-card style="background:var(--surface);border:1px solid {{ $decapStats['overdue'] > 0 ? 'rgba(220,38,38,.4)' : 'var(--border)' }};border-left:3px solid #dc2626;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">En retard</div>
            <div data-decap-kpi="overdue" style="font-size:24px;font-weight:800;color:#dc2626;margin-top:4px">{{ number_format($decapStats['overdue']) }}</div>
            <div data-decap-kpi-overdue-sub style="font-size:10px;color:{{ $decapStats['overdue'] > 0 ? '#dc2626' : 'var(--text3)' }};margin-top:2px;font-weight:600">> 7j sans décappage</div>
        </div>
    </div>

    {{-- Campagnes terminées récemment (à décaper) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg>
            Campagnes terminées — à décaper ({{ $decapList->count() }})
        </div>
        @if($decapList->isEmpty())
            <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune campagne récemment terminée.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach($decapList as $c)
                    @php
                        $daysOverdue   = (int) $c->end_date->diffInDays(now(), false);
                        $isOverdue     = $c->is_overdue;
                        $isComplete    = $c->decapped_count === $c->total_panels;
                    @endphp
                    <details
                        @if($isOverdue && !isset($firstOverdueShown)) id="decap-overdue-list" @php $firstOverdueShown = true; @endphp @endif
                        data-decap-campaign="{{ $c->id }}"
                        data-decap-total="{{ $c->total_panels }}"
                        data-decap-overdue="{{ $isOverdue ? '1' : '0' }}"
                        style="background:var(--surface2);border:1px solid {{ $isOverdue ? 'rgba(220,38,38,.3)' : 'var(--border)' }};border-radius:10px;overflow:hidden">
                        <summary style="padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;list-style:none">
                            <span data-decap-dot style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:{{ $isComplete ? '#22c55e' : ($isOverdue ? '#dc2626' : '#f59e0b') }};box-shadow:0 0 0 3px {{ $isComplete ? 'rgba(34,197,94,.2)' : ($isOverdue ? 'rgba(220,38,38,.2)' : 'rgba(245,158,11,.2)') }}"></span>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <a href="{{ route('admin.campaigns.show', $c->id) }}" style="color:var(--accent);text-decoration:none" onclick="event.stopPropagation()">{{ $c->name }}</a>
                                    <span style="font-size:11px;color:var(--text3);font-weight:400;margin-left:6px">· {{ $c->client?->name ?? '—' }}</span>
                                </div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">
                                    Fin : {{ $c->end_date->format('d/m/Y') }}
                                    @if($isOverdue)
                                        <span style="color:#dc2626;font-weight:700;margin-left:6px">+ {{ $daysOverdue }}j de retard</span>
                                    @else
                                        <span style="margin-left:6px">{{ $daysOverdue }}j depuis fin</span>
                                    @endif
                                </div>
                            </div>
                            <div style="flex-shrink:0;text-align:right">
                                <div data-decap-ratio style="font-size:12px;font-weight:700;color:{{ $isComplete ? '#16a34a' : 'var(--text)' }}">{{ $c->decapped_count }}/{{ $c->total_panels }}</div>
                                <div style="height:4px;width:80px;background:var(--border);border-radius:2px;overflow:hidden;margin-top:4px">
                                    <div data-decap-bar style="height:100%;width:{{ $c->decap_progress }}%;background:{{ $isComplete ? '#22c55e' : ($isOverdue ? '#dc2626' : '#f59e0b') }}"></div>
                                </div>
                                <div style="font-size:10px;color:var(--text3);margin-top:2px"><span data-decap-pct>{{ $c->decap_progress }}</span>% décappés</div>
                            </div>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" style="flex-shrink:0"><polyline points="6 9 12 15 18 9"/></svg>
                        </summary>
                        <div style="padding:0 14px 12px;border-top:1px solid var(--border);background:var(--surface)">
                            @if($c->pending_count > 1)
                                <div style="display:flex;justify-content:flex-end;padding:10px 0 2px">
                                    <button type="button" onclick="Decap.markAll({{ $c->id }})"
                                            style="font-size:10.5px;font-weight:700;padding:6px 14px;border:1px solid #22c55e;background:rgba(34,197,94,.1);color:#16a34a;border-radius:6px;cursor:pointer">
                                        ✓✓ Marquer tous décappés ({{ $c->pending_count }})
                                    </button>
                                </div>
                            @endif
                            <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:10px">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border)">
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Commune</th>
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Statut</th>
                                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($c->panels as $p)
                                        @php $isDone = $p->decapped_at !== null; @endphp
                                        <tr id="decap-row-{{ $c->id }}-{{ $p->id }}"
                                            data-decap-row="1"
                                            data-decap-panel="{{ $p->id }}"
                                            data-decap-state="{{ $isDone ? 'done' : 'pending' }}"
                                            style="border-bottom:1px solid var(--border)">
                                            <td style="padding:8px">
                                                <a href="{{ route('admin.panels.show', $p->id) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                            </td>
                                            <td style="padding:8px;color:var(--text2)">{{ $p->commune?->name ?? '—' }}</td>
                                            <td style="padding:8px" data-decap-status-cell>
                                                @if($isDone)
                                                    <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(34,197,94,.12);color:#16a34a">✓ Décappé le {{ \Carbon\Carbon::parse($p->decapped_at)->format('d/m H:i') }}</span>
                                                @else
                                                    <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,.12);color:#d97706">En attente</span>
                                                @endif
                                            </td>
                                            <td style="padding:8px;text-align:right" data-decap-action-cell>
                                                @if($isDone)
                                                    <button type="button" onclick="Decap.unmark({{ $c->id }}, {{ $p->id }})"
                                                            style="font-size:10px;font-weight:600;padding:4px 10px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);border-radius:6px;cursor:pointer">
                                                        Annuler
                                                    </button>
                                                @else
                                                    <button type="button" onclick="Decap.mark({{ $c->id }}, {{ $p->id }})"
                                                            style="font-size:10px;font-weight:700;padding:4px 10px;border:none;background:#22c55e;color:#fff;border-radius:6px;cursor:pointer">
                                                        ✓ Marquer décappé
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
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

@endif {{-- panel-decap --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — INSIGHTS & ALERTES (admin/MP only : tendances entreprise)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('insights', $allowedTabIds, true))
<div id="panel-insights" class="rpt-panel" style="display:none">

    {{-- 🎯 SYNTHÈSE EXÉCUTIVE — direction (vue stratégique en haut) --}}
    <div style="background:linear-gradient(135deg,var(--surface),var(--surface2));border:1.5px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="{{ $execSummary['score_color'] }}" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span style="font-size:14px;font-weight:800;color:var(--text)">Synthèse exécutive — direction</span>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:var(--text3)">Score performance</span>
                <span style="font-size:18px;font-weight:800;color:{{ $execSummary['score_color'] }};padding:4px 12px;border-radius:10px;background:{{ $execSummary['score_color'] }}22">{{ $execSummary['score'] }}/10 — {{ $execSummary['score_label'] }}</span>
            </div>
        </div>

        {{-- 4 KPIs synthèse direction --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px" class="rpt-grid-5">
            @php
                $execKpis = [
                    ['CA réalisé', number_format($execSummary['kpis']['revenue']/1000000, 1, ',', ' ') . 'M', 'FCFA sur période', '#e8a020'],
                    ['Occupation', $execSummary['kpis']['occupation_rate'] . '%', 'du parc', '#3b82f6'],
                    ["Taux annul.", $execSummary['kpis']['cancel_rate'] . '%', $execSummary['kpis']['campaigns_total'] . ' campagnes', $execSummary['kpis']['cancel_rate'] > 18 ? '#dc2626' : ($execSummary['kpis']['cancel_rate'] > 12 ? '#f59e0b' : '#16a34a')],
                    ['CA prévu 3m', number_format($execSummary['forecast_3m_revenue']/1000000, 1, ',', ' ') . 'M', 'FCFA · conf. ' . $execSummary['forecast_confidence'] . '%', '#a855f7'],
                ];
            @endphp
            @foreach($execKpis as [$lbl, $val, $sub, $col])
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:11px 14px;border-left:3px solid {{ $col }}">
                    <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">{{ $lbl }}</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $col }};line-height:1.1;margin-top:4px">{{ $val }}</div>
                    <div style="font-size:9.5px;color:var(--text3);margin-top:2px">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- 3 blocs risques / opportunités / actions --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px" class="rpt-grid-2">
            {{-- Risques --}}
            <div style="background:rgba(220,38,38,.04);border:1px solid rgba(220,38,38,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.5px">Risques majeurs</span>
                </div>
                <ul style="margin:0;padding-left:0;list-style:none;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['risks'] as $r)
                        <li>{{ $r }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Opportunités --}}
            <div style="background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.5px">Opportunités</span>
                </div>
                <ul style="margin:0;padding-left:0;list-style:none;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['opportunities'] as $o)
                        <li>{{ $o }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Actions prioritaires --}}
            <div style="background:rgba(59,130,246,.04);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.5px">Actions prioritaires</span>
                </div>
                <ol style="margin:0;padding-left:18px;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['actions'] as $a)
                        @php $aCol = $a['priority'] === 'high' ? '#dc2626' : ($a['priority'] === 'medium' ? '#f59e0b' : '#3b82f6'); @endphp
                        <li><span style="color:{{ $aCol }};font-weight:700">[{{ strtoupper($a['priority']) }}]</span> {{ $a['action'] }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>

    {{-- 🌍 BENCHMARKS SECTORIELS — données marché OOH Côte d'Ivoire / Afrique --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Benchmarks sectoriels OOH — Côte d'Ivoire / Afrique</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic" title="{{ $marketBenchmarks['meta']['notes'] }}">MAJ {{ $marketBenchmarks['meta']['last_updated'] }}</span>
        </div>

        {{-- Notre position vs marché : occupation + annulation --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            {{-- Position occupation --}}
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px">📊 Occupation parc</span>
                    @php
                        $posLabels = ['leader' => ['🏆 Leader', '#16a34a'], 'above_average' => ['✅ Au-dessus marché', '#3b82f6'], 'below_average' => ['⚠️ Sous le marché', '#f59e0b']];
                        $pos = $posLabels[$marketBenchmarks['occupation']['position']];
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $pos[1] }}22;color:{{ $pos[1] }};font-size:10px;font-weight:700">{{ $pos[0] }}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:6px">
                    <span style="font-size:22px;font-weight:800;color:{{ $pos[1] }}">{{ $marketBenchmarks['occupation']['our_value'] }}%</span>
                    <span style="font-size:11px;color:var(--text3)">vs marché CI {{ $marketBenchmarks['occupation']['market_ci'] }}% · top {{ $marketBenchmarks['occupation']['market_top'] }}% · Afrique {{ $marketBenchmarks['occupation']['market_africa'] }}%</span>
                </div>
                <div style="position:relative;height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:8px">
                    <div style="position:absolute;left:{{ $marketBenchmarks['occupation']['market_ci'] }}%;width:2px;height:100%;background:#94a3b8" title="Moyenne marché CI"></div>
                    <div style="position:absolute;left:{{ $marketBenchmarks['occupation']['market_top'] }}%;width:2px;height:100%;background:#16a34a" title="Top performers"></div>
                    <div style="height:100%;width:{{ min($marketBenchmarks['occupation']['our_value'], 100) }}%;background:linear-gradient(90deg,#3b82f6,{{ $pos[1] }});border-radius:4px"></div>
                </div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.4">{{ $marketBenchmarks['occupation']['note'] }}</div>
            </div>

            {{-- Position taux annulation --}}
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px">❌ Taux d'annulation</span>
                    @php
                        $cancelLabels = ['healthy' => ['✅ Sain', '#16a34a'], 'average' => ['⚠️ Moyen', '#f59e0b'], 'critical' => ['🔴 Critique', '#dc2626']];
                        $cancelPos = $cancelLabels[$marketBenchmarks['cancel_rate']['position']];
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $cancelPos[1] }}22;color:{{ $cancelPos[1] }};font-size:10px;font-weight:700">{{ $cancelPos[0] }}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:6px">
                    <span style="font-size:22px;font-weight:800;color:{{ $cancelPos[1] }}">{{ $marketBenchmarks['cancel_rate']['our_value'] }}%</span>
                    <span style="font-size:11px;color:var(--text3)">vs sain ≤{{ $marketBenchmarks['cancel_rate']['industry_healthy'] }}% · moy. {{ $marketBenchmarks['cancel_rate']['industry_average'] }}% · alerte ≥{{ $marketBenchmarks['cancel_rate']['industry_warning'] }}%</span>
                </div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.4">
                    @if($marketBenchmarks['cancel_rate']['delta_vs_market'] < 0)
                        Vous êtes <strong style="color:#16a34a">{{ abs($marketBenchmarks['cancel_rate']['delta_vs_market']) }} pts sous</strong> la moyenne marché.
                    @else
                        Vous êtes <strong style="color:#dc2626">+{{ $marketBenchmarks['cancel_rate']['delta_vs_market'] }} pts au-dessus</strong> de la moyenne marché.
                    @endif
                </div>
            </div>
        </div>

        {{-- Croissance + Tarification de référence --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">📈 Croissance secteur OOH</div>
                <div style="font-size:20px;font-weight:800;color:#16a34a;margin-bottom:4px">+{{ $marketBenchmarks['growth']['ci_yoy_2025_2026'] ?? '—' }}% <span style="font-size:11px;color:var(--text3);font-weight:400">YoY 2025→2026 CI</span></div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.5">CI 2024-2025 : <strong>+{{ $marketBenchmarks['growth']['ci_yoy_2024_2025'] ?? '—' }}%</strong> · Afrique 2025 : <strong>+{{ $marketBenchmarks['growth']['africa_yoy_2025'] ?? '—' }}%</strong></div>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:6px;line-height:1.4">{{ $marketBenchmarks['growth']['note'] ?? '' }}</div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">💰 Tarification de référence</div>
                <div style="display:flex;flex-direction:column;gap:4px;font-size:11px">
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Abidjan 4×3 lumineux</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['abidjan_4x3_lit'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Abidjan 4×3 classique</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['abidjan_4x3_classique'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Intérieur pays 4×3</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['intérieur_pays_4x3'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Panneau géant 8×3</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['panneau_geant_8x3'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                </div>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:8px;line-height:1.4">{{ $marketBenchmarks['pricing']['note'] ?? '' }}</div>
            </div>
        </div>

        {{-- Mix sectoriel annonceurs + Concurrents --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🎯 Mix annonceurs CI</div>
                <div style="display:flex;flex-direction:column;gap:5px">
                    @foreach($marketBenchmarks['industry_mix'] as $sec)
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-size:11px;color:var(--text);min-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $sec['sector'] }}</span>
                            <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden">
                                <div style="height:100%;width:{{ $sec['share_pct'] * 3.5 }}%;background:linear-gradient(90deg,#3b82f6,#a855f7);border-radius:3px"></div>
                            </div>
                            <span style="font-size:11px;font-weight:700;color:var(--text2);min-width:30px;text-align:right">{{ $sec['share_pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🏢 Acteurs concurrents (estimés)</div>
                <table style="width:100%;border-collapse:collapse;font-size:11px">
                    <tbody>
                        @foreach($marketBenchmarks['competitors'] as $comp)
                            @php $tierCol = $comp['tier'] === 'leader' ? '#16a34a' : ($comp['tier'] === 'challenger' ? '#3b82f6' : '#94a3b8'); @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:5px 0;color:var(--text)">{{ $comp['name'] }}</td>
                                <td style="padding:5px 0;text-align:right;color:var(--text3);font-variant-numeric:tabular-nums">{{ number_format($comp['estimated_parc']) }} pann.</td>
                                <td style="padding:5px 0;text-align:right"><span style="font-size:9px;padding:2px 7px;border-radius:8px;background:{{ $tierCol }}22;color:{{ $tierCol }};font-weight:700;text-transform:uppercase">{{ $comp['tier'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:6px">⚠️ Estimations indicatives — sources publiques non officielles.</div>
            </div>
        </div>

        {{-- Tendances structurelles à surveiller --}}
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🔮 Tendances structurelles du marché</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px" class="rpt-grid-2">
                @foreach($marketBenchmarks['trends'] as $tr)
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 13px">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
                            <span style="font-size:16px">{{ $tr['icon'] }}</span>
                            <strong style="font-size:11.5px;color:var(--text)">{{ $tr['title'] }}</strong>
                        </div>
                        <div style="font-size:11px;color:var(--text3);line-height:1.5">{{ $tr['desc'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:14px;padding:8px 12px;background:var(--surface2);border-radius:8px;line-height:1.5">
            ⓘ <strong>Sources :</strong> {{ $marketBenchmarks['meta']['notes'] }}. Pour mettre à jour ces données, éditez <code>config/market_benchmarks.php</code>. Ces valeurs sont indicatives — à compléter avec études OAAA, UDECI, INS Côte d'Ivoire dès qu'elles sont disponibles.
        </div>
    </div>

    {{-- Prévisions régression linéaire 3 mois (COMMIT D) --}}
    <div style="background:linear-gradient(135deg,rgba(59,130,246,.05),rgba(168,85,247,.05));border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">🔮 Prévisions 3 mois — régression linéaire</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Statistique simple, pas d'IA · basé sur les 12 derniers mois</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
            {{-- Prévision CA --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <span style="font-size:11px;font-weight:700;color:var(--text)">💰 CA projeté</span>
                    @php
                        $rev = $forecastRevenue;
                        $revBadge = $rev['trend_direction'] === 'up'   ? ['#16a34a','rgba(34,197,94,.12)','↗ Hausse']
                                  : ($rev['trend_direction'] === 'down' ? ['#dc2626','rgba(220,38,38,.12)','↘ Baisse']
                                                                       : ['#6b7280','rgba(107,114,128,.12)','→ Stable']);
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $revBadge[1] }};color:{{ $revBadge[0] }};font-size:10px;font-weight:700">{{ $revBadge[2] }} {{ abs($rev['trend_pct_per_month']) }}%/mois</span>
                </div>
                @if(empty($rev['forecast']))
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">{{ $rev['message'] ?? 'Pas assez de données.' }}</div>
                @else
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <tbody>
                            @foreach($rev['forecast'] as $f)
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:7px 0;color:var(--text2);font-weight:600">{{ $f['label'] }}</td>
                                    <td style="padding:7px 0;text-align:right;font-weight:700;color:#16a34a;font-variant-numeric:tabular-nums">{{ number_format($f['value'], 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="font-size:10px;color:var(--text3);margin-top:8px;line-height:1.5">
                        Confiance modèle : <strong style="color:{{ $rev['confidence'] >= 60 ? '#16a34a' : ($rev['confidence'] >= 30 ? '#f59e0b' : '#dc2626') }}">{{ $rev['confidence'] }}%</strong>
                        (R² = {{ $rev['r_squared'] }})
                    </div>
                @endif
            </div>

            {{-- Prévision Occupation --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <span style="font-size:11px;font-weight:700;color:var(--text)">📊 Taux d'occupation projeté</span>
                    @php
                        $occ = $forecastOccupation;
                        $occBadge = $occ['trend_direction'] === 'up'   ? ['#16a34a','rgba(34,197,94,.12)','↗ Hausse']
                                  : ($occ['trend_direction'] === 'down' ? ['#dc2626','rgba(220,38,38,.12)','↘ Baisse']
                                                                       : ['#6b7280','rgba(107,114,128,.12)','→ Stable']);
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $occBadge[1] }};color:{{ $occBadge[0] }};font-size:10px;font-weight:700">{{ $occBadge[2] }} {{ abs($occ['trend_pct_per_month']) }}%/mois</span>
                </div>
                @if(empty($occ['forecast']))
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">{{ $occ['message'] ?? 'Pas assez de données.' }}</div>
                @else
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <tbody>
                            @foreach($occ['forecast'] as $f)
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:7px 0;color:var(--text2);font-weight:600">{{ $f['label'] }}</td>
                                    <td style="padding:7px 0;text-align:right;font-weight:700;color:#3b82f6;font-variant-numeric:tabular-nums">{{ round($f['value'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="font-size:10px;color:var(--text3);margin-top:8px;line-height:1.5">
                        Confiance modèle : <strong style="color:{{ $occ['confidence'] >= 60 ? '#16a34a' : ($occ['confidence'] >= 30 ? '#f59e0b' : '#dc2626') }}">{{ $occ['confidence'] }}%</strong>
                        (R² = {{ $occ['r_squared'] }})
                    </div>
                @endif
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);margin-top:12px;padding:8px 12px;background:var(--surface2);border-radius:8px;line-height:1.5">
            ⓘ <strong>Méthode :</strong> régression linéaire des moindres carrés sur l'historique 12 mois. Le modèle projette une tendance linéaire — il ne capture pas la saisonnalité (Ramadan, fêtes de fin d'année, etc.). À interpréter comme une <em>orientation</em>, pas comme une valeur exacte. Un R² élevé indique que la tendance est nette dans les données passées.
        </div>
    </div>

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
@endif {{-- panel-insights --}}

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
@media (max-width: 900px) { .rpt-grid-2 { grid-template-columns: 1fr !important; } .rpt-grid-clients { grid-template-columns: 1fr !important; } .rpt-grid-5 { grid-template-columns: repeat(2, 1fr) !important; } }
@keyframes rpt-pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,.6); } 50% { box-shadow: 0 0 0 4px rgba(220,38,38,0); } }
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
        // Defensive : pour le commercial certains onglets sont absents du
        // DOM (Occupation/Panneaux/Zones/Clients/Insights = vue globale
        // entreprise). On no-op si l'élément cible n'existe pas, plutôt
        // que de planter avec "Cannot read property 'classList' of null".
        const tabEl   = document.getElementById('tab-'+id);
        const panelEl = document.getElementById('panel-'+id);
        if (!tabEl || !panelEl) return;
        document.querySelectorAll('.rpt-tab').forEach(t=>t.classList.remove('active'));
        document.querySelectorAll('.rpt-panel').forEach(p=>p.style.display='none');
        tabEl.classList.add('active');
        panelEl.style.display='block';
        if (id==='occupation'&&!this._evolDone)     { this.renderEvol(); this.renderOccupationTrend(); this._evolDone=true; }
        if (id==='ca'        &&!this._caDone)       { this.renderCa();   this.renderRevenueTrend();    this.renderOccVsRevenue(); this._caDone=true; }
        if (id==='zones'     &&!this._hmDone)       { HM.init();         this._hmDone=true; }
        if (id==='panneaux'  &&!this._panneauxDone) { this.renderTopPanels();    this._panneauxDone=true; }
        if (id==='clients'   &&!this._clientsDone)  { this.renderClientDistribution(); this._clientsDone=true; }
        if (id==='campagnes' &&!this._campagnesDone){ this.renderCancellationTrend(); this.renderCancelReasonsCamp(); this._campagnesDone=true; }
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

    // ── Chart.js — tendance annulations 12 mois (line + bar mix) ──────
    renderCancellationTrend() {
        const canvas = document.getElementById('chart-cancel-trend');
        const data = D.cancellationTrend;
        if (!canvas || !data?.length || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        new Chart(canvas, {
            data:{
                labels: data.map(d => d.label),
                datasets:[
                    {
                        type:'bar',
                        label:'Total campagnes',
                        data: data.map(d => d.total),
                        backgroundColor:'rgba(148,163,184,.4)',
                        borderRadius:4,
                        yAxisID:'y',
                    },
                    {
                        type:'bar',
                        label:'Annulées',
                        data: data.map(d => d.cancelled),
                        backgroundColor:'#dc2626',
                        borderRadius:4,
                        yAxisID:'y',
                    },
                    {
                        type:'line',
                        label:"Taux d'annulation (%)",
                        data: data.map(d => d.rate),
                        borderColor:'#f59e0b',
                        backgroundColor:'rgba(245,158,11,.15)',
                        borderWidth:2.5, tension:.3, fill:false,
                        pointBackgroundColor:'#f59e0b', pointRadius:3,
                        yAxisID:'y1',
                    },
                ],
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                interaction:{ mode:'index', intersect:false },
                plugins:{
                    legend:{ position:'bottom', labels:{ color:tickC, font:{size:10}, boxWidth:10, padding:8 } },
                    tooltip:{ callbacks:{
                        label: ctx => {
                            const ds = ctx.dataset.label;
                            const v  = ctx.parsed.y;
                            return ds.includes('Taux') ? ` ${ds} : ${v}%` : ` ${ds} : ${v}`;
                        },
                    }},
                },
                scales:{
                    x:{ ticks:{color:tickC,font:{size:10}}, grid:{display:false} },
                    y:{ beginAtZero:true, ticks:{color:tickC,font:{size:10},precision:0}, grid:{color:gridC}, title:{display:true,text:'Nombre',color:tickC,font:{size:10}} },
                    y1:{ beginAtZero:true, max:100, position:'right', ticks:{color:tickC,font:{size:10},callback:v=>v+'%'}, grid:{display:false}, title:{display:true,text:'Taux (%)',color:tickC,font:{size:10}} },
                },
            }
        });
    },

    // ── Chart.js — doughnut motifs dans onglet Campagnes ──────────────
    renderCancelReasonsCamp() {
        const canvas = document.getElementById('chart-cancel-reasons-camp');
        const data = D.cancelReasons || [];
        if (!canvas || !data.length || typeof Chart === 'undefined') return;
        const reasonLabels = {
            budget:'💸 Budget', zone:'📍 Zone', strategie:'🎯 Stratégie',
            report:'⏰ Report', concurrent:'🤝 Concurrent', autre:'❓ Autre',
        };
        const palette = ['#ef4444','#f97316','#e8a020','#3b82f6','#a855f7','#6b7280'];
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        new Chart(canvas, {
            type:'doughnut',
            data:{
                labels: data.map(r => reasonLabels[r.cancellation_reason] || r.cancellation_reason),
                datasets:[{
                    data: data.map(r => Number(r.count) || 0),
                    backgroundColor: palette,
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                }],
            },
            options:{
                responsive:true, maintainAspectRatio:false, cutout:'55%',
                plugins:{
                    legend:{ position:'bottom', labels:{ color: isDark ? 'rgba(255,255,255,.7)' : 'rgba(0,0,0,.7)', font:{size:10}, boxWidth:10, padding:6 } },
                    tooltip:{ callbacks:{ label: ctx => ` ${ctx.label} : ${ctx.parsed} campagne(s)` } },
                },
            }
        });
    },

    // ── Chart.js — doughnut répartition CA par client ─────────────────
    renderClientDistribution() {
        const canvas = document.getElementById('chart-client-dist');
        const data = D.clientRevenueDist;
        if (!canvas || !data || data.total <= 0 || typeof Chart === 'undefined') return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const palette = ['#16a34a','#3b82f6','#a855f7','#e8a020','#0ea5e9','#ec4899','#14b8a6','#f97316'];
        const labels = data.top.map(r => r.name);
        const values = data.top.map(r => r.revenue);
        const colors = data.top.map((_, i) => palette[i % palette.length]);
        if (data.others > 0) {
            labels.push('Autres');
            values.push(data.others);
            colors.push('#94a3b8');
        }
        new Chart(canvas, {
            type:'doughnut',
            data:{
                labels: labels,
                datasets:[{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: isDark ? '#1e293b' : '#ffffff',
                }],
            },
            options:{
                responsive:true, maintainAspectRatio:false, cutout:'60%',
                plugins:{
                    legend:{
                        position:'right',
                        labels:{ color: isDark ? 'rgba(255,255,255,.7)' : 'rgba(0,0,0,.7)', font:{size:10}, boxWidth:10, padding:8 },
                    },
                    tooltip:{ callbacks:{
                        label: ctx => {
                            const v = ctx.parsed;
                            const pct = data.total > 0 ? ((v / data.total) * 100).toFixed(1) : 0;
                            return ` ${ctx.label} : ${new Intl.NumberFormat('fr-FR').format(Math.round(v))} FCFA (${pct}%)`;
                        },
                    }},
                },
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

// ══════════════════════════════
// DRILLDOWN PANNEAU — module (historique occupations)
// ══════════════════════════════
window.PanelDrilldown = (function () {
    const overlay = document.getElementById('panel-modal');
    const body    = document.getElementById('pl-body');
    const loading = document.getElementById('pl-loading');
    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n || 0));
    let monthlyChart = null;

    const statusColors = {
        actif:'#22c55e', termine:'#6b7280', planifie:'#3b82f6',
        confirme:'#3b82f6', pause:'#f97316', annule:'#ef4444', option:'#e8a020',
    };

    function renderSummary(s, p) {
        const cards = [
            ['Statut',         (p.status || '—').toUpperCase(),                          'var(--accent)'],
            ['Tarif / mois',   p.rate > 0 ? fmt(p.rate) + ' FCFA' : '—',                 '#3b82f6'],
            ['Campagnes',      fmt(s.campaigns_total),                                   'var(--accent)'],
            ['Jours occupés (période)', fmt(s.busy_days) + ' / ' + fmt(s.period_days),   '#16a34a'],
            ["Taux période",   s.rate + '%',                                              s.rate >= 75 ? '#ef4444' : (s.rate >= 50 ? '#f97316' : (s.rate >= 25 ? '#e8a020' : '#22c55e'))],
            ['CA généré (période)', s.revenue_period > 0 ? fmt(s.revenue_period) + ' FCFA' : '—', '#a855f7'],
            ['Inactivité actuelle', s.days_since_last !== null ? s.days_since_last + ' jours' : 'Jamais loué', (s.days_since_last ?? 0) > 60 ? '#dc2626' : ((s.days_since_last ?? 0) > 30 ? '#f59e0b' : '#22c55e')],
            ['Plus longue plage creuse', s.longest_gap_days > 0 ? s.longest_gap_days + ' j' + (s.longest_gap_start ? ' (' + s.longest_gap_start + '→' + s.longest_gap_end + ')' : '') : '—', '#6b7280'],
        ];
        return cards.map(([lbl, val, color]) => `
            <div class="cm-stat" style="border-left-color:${color}">
                <div class="lbl">${lbl}</div>
                <div class="val" style="color:${color};font-size:14px">${val}</div>
            </div>
        `).join('');
    }

    function renderTopClients(rows) {
        if (!rows?.length) return '<div style="padding:14px;color:var(--text3);font-size:12px;text-align:center">Aucun client identifié.</div>';
        return `<table class="cm-table">
            <thead><tr><th>#</th><th>Client</th><th class="r">Campagnes</th><th class="r">CA cumulé</th></tr></thead>
            <tbody>${rows.map((r,i) => `
                <tr>
                    <td style="width:36px;">${i===0?'🥇':(i===1?'🥈':(i===2?'🥉':i+1))}</td>
                    <td><strong>${r.name}</strong></td>
                    <td class="r">${fmt(r.count)}</td>
                    <td class="r"><strong style="color:var(--accent)">${fmt(r.revenue)} FCFA</strong></td>
                </tr>
            `).join('')}</tbody></table>`;
    }

    function renderCampaigns(rows) {
        if (!rows?.length) return '<div style="padding:14px;color:var(--text3);font-size:12px;text-align:center">Aucune campagne sur ce panneau.</div>';
        return `<table class="cm-table">
            <thead><tr><th>Campagne</th><th>Client</th><th>Période</th><th>Statut</th><th>Décap.</th><th class="r">Montant</th></tr></thead>
            <tbody>${rows.map(c => `
                <tr>
                    <td><a href="${c.url}"><strong>${c.name || '—'}</strong></a></td>
                    <td>${c.client}</td>
                    <td style="font-size:11px;color:var(--text3)">${c.start_date} → ${c.end_date}</td>
                    <td><span class="cm-status-pill" style="background:${(statusColors[c.status]||'#6b7280')}22;color:${statusColors[c.status]||'#6b7280'}">${c.status}</span></td>
                    <td>${c.decapped_at ? '<span style="font-size:10px;color:#16a34a;font-weight:700">✓ ' + new Date(c.decapped_at).toLocaleDateString('fr-FR') + '</span>' : '<span style="font-size:10px;color:var(--text3)">—</span>'}</td>
                    <td class="r"><strong>${c.amount > 0 ? fmt(c.amount) + ' FCFA' : '—'}</strong></td>
                </tr>
            `).join('')}</tbody></table>`;
    }

    function renderMonthlyChart(rows) {
        const canvas = document.getElementById('pl-monthly-chart');
        if (!canvas || !rows?.length || typeof Chart === 'undefined') return;
        if (monthlyChart) { monthlyChart.destroy(); monthlyChart = null; }
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const tickC = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        const gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const colors = rows.map(r => r.rate >= 75 ? '#ef4444' : (r.rate >= 50 ? '#f97316' : (r.rate >= 25 ? '#e8a020' : (r.days_occupied > 0 ? '#22c55e' : '#cbd5e1'))));
        monthlyChart = new Chart(canvas, {
            type:'bar',
            data:{
                labels: rows.map(r => r.label),
                datasets:[{ label:'Jours occupés', data: rows.map(r => r.days_occupied), backgroundColor: colors, borderRadius:5, borderSkipped:false }],
            },
            options:{
                responsive:true, maintainAspectRatio:false,
                plugins:{ legend:{display:false}, tooltip:{ callbacks:{
                    label: ctx => {
                        const r = rows[ctx.dataIndex];
                        return [` ${r.days_occupied} / ${r.total_days} jours`, ` Taux : ${r.rate}%`];
                    },
                }}},
                scales:{
                    x:{ ticks:{color:tickC,font:{size:10}}, grid:{display:false} },
                    y:{ beginAtZero:true, ticks:{color:tickC,font:{size:10},precision:0}, grid:{color:gridC} },
                }
            }
        });
    }

    return {
        async open(panelId) {
            overlay.style.display = 'flex';
            body.style.display = 'none';
            loading.style.display = 'block';
            document.body.style.overflow = 'hidden';

            try {
                // Conserve la période active (ex. preset year) pour cohérence
                const params = new URLSearchParams(window.location.search);
                const r = await fetch(`/admin/rapports/panels/${panelId}/detail?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                });
                if (!r.ok) throw new Error('HTTP ' + r.status);
                const data = await r.json();

                document.getElementById('pl-name').textContent = data.panel.reference + (data.panel.name ? ' — ' + data.panel.name : '');
                document.getElementById('pl-meta').textContent = [data.panel.commune, data.panel.city, data.panel.category].filter(Boolean).join(' · ');
                document.getElementById('pl-link').href = data.panel.url;
                document.getElementById('pl-summary').innerHTML = renderSummary(data.summary, data.panel);
                document.getElementById('pl-top-clients').innerHTML = renderTopClients(data.top_clients);
                document.getElementById('pl-campaigns').innerHTML = renderCampaigns(data.campaigns);
                document.getElementById('pl-camp-count').textContent = (data.campaigns || []).length;

                loading.style.display = 'none';
                body.style.display = 'block';
                requestAnimationFrame(() => renderMonthlyChart(data.monthly));
            } catch (e) {
                console.error(e);
                loading.innerHTML = '<div style="color:#ef4444;">Erreur de chargement. Réessayez.</div>';
            }
        },
        close() {
            overlay.style.display = 'none';
            document.body.style.overflow = '';
            if (monthlyChart) { monthlyChart.destroy(); monthlyChart = null; }
        },
    };
})();

// ══════════════════════════════
// DECAP — module marquage décappage (COMMIT C)
//
// Refactor : avant on faisait `location.reload()` après chaque action
// → la page se rechargeait depuis le haut, le <details> ouvert se
// refermait, le scroll position était perdu, et un cache oublié (cf.
// DashboardKpiService::markDecapped fix) laissait la bannière sur les
// vieux compteurs. L'admin avait l'impression "rien ne se passe et la
// page redirige ailleurs".
//
// Maintenant : MAJ DOM en place + fetch JSON summary pour rafraîchir
// les 4 KPI cards et la bannière "X en retard" SANS reload. État
// <details> et scroll préservés.
// ══════════════════════════════
window.Decap = (function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || (typeof D !== 'undefined' ? D.csrf : '');
    const SUMMARY_URL = '{{ route("admin.rapports.decap.summary") }}';
    const MARK_URL    = '{{ route("admin.rapports.decap.mark") }}';
    const MARKALL_URL = '{{ route("admin.rapports.decap.markAll") }}';

    async function postUpdate(campaignId, panelId, action, notes = null) {
        const body = new URLSearchParams({
            campaign_id: campaignId, panel_id: panelId, action,
        });
        if (notes) body.append('notes', notes);
        const r = await fetch(MARK_URL, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    async function postBulk(campaignId) {
        const body = new URLSearchParams({ campaign_id: campaignId });
        const r = await fetch(MARKALL_URL, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body,
        });
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
    }

    function toast(msg, type = 'success') {
        const t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = `position:fixed;bottom:30px;right:30px;z-index:10000;padding:12px 20px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;box-shadow:0 8px 24px rgba(0,0,0,.25);background:${type === 'success' ? '#16a34a' : '#dc2626'};opacity:0;transform:translateY(20px);transition:all .25s`;
        document.body.appendChild(t);
        requestAnimationFrame(() => { t.style.opacity = '1'; t.style.transform = 'translateY(0)'; });
        setTimeout(() => {
            t.style.opacity = '0'; t.style.transform = 'translateY(20px)';
            setTimeout(() => t.remove(), 250);
        }, 2200);
    }

    function fmt(n) {
        return Number(n).toLocaleString('fr-FR').replace(/ /g, ' ');
    }

    // ─── MAJ DOM ligne panneau ──────────────────────────────────
    function applyRowState(campaignId, panelId, done, at, by) {
        const row = document.getElementById(`decap-row-${campaignId}-${panelId}`);
        if (!row) return;
        row.dataset.decapState = done ? 'done' : 'pending';
        const statusCell = row.querySelector('[data-decap-status-cell]');
        const actionCell = row.querySelector('[data-decap-action-cell]');
        if (statusCell) {
            if (done) {
                const when = at || 'à l\'instant';
                statusCell.innerHTML = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(34,197,94,.12);color:#16a34a">✓ Décappé le ${when}</span>`;
            } else {
                statusCell.innerHTML = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,.12);color:#d97706">En attente</span>`;
            }
        }
        if (actionCell) {
            if (done) {
                actionCell.innerHTML = `<button type="button" onclick="Decap.unmark(${campaignId}, ${panelId})" style="font-size:10px;font-weight:600;padding:4px 10px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);border-radius:6px;cursor:pointer">Annuler</button>`;
            } else {
                actionCell.innerHTML = `<button type="button" onclick="Decap.mark(${campaignId}, ${panelId})" style="font-size:10px;font-weight:700;padding:4px 10px;border:none;background:#22c55e;color:#fff;border-radius:6px;cursor:pointer">✓ Marquer décappé</button>`;
            }
        }
    }

    // ─── Recompte la barre/ratio de la campagne en lisant le tbody ──
    function refreshCampaignSummary(campaignId) {
        const block = document.querySelector(`[data-decap-campaign="${campaignId}"]`);
        if (!block) return;
        const total    = parseInt(block.dataset.decapTotal || '0', 10);
        const isOverdue = block.dataset.decapOverdue === '1';
        const doneCount = block.querySelectorAll('[data-decap-row][data-decap-state="done"]').length;
        const pct = total > 0 ? Math.round(doneCount / total * 100) : 0;
        const complete = total > 0 && doneCount === total;
        const ratioEl = block.querySelector('[data-decap-ratio]');
        const barEl   = block.querySelector('[data-decap-bar]');
        const pctEl   = block.querySelector('[data-decap-pct]');
        const dotEl   = block.querySelector('[data-decap-dot]');
        const bulkBtn = block.querySelector('button[onclick*="Decap.markAll"]');
        if (ratioEl) {
            ratioEl.textContent = `${doneCount}/${total}`;
            ratioEl.style.color = complete ? '#16a34a' : 'var(--text)';
        }
        if (barEl) {
            barEl.style.width = `${pct}%`;
            barEl.style.background = complete ? '#22c55e' : (isOverdue ? '#dc2626' : '#f59e0b');
        }
        if (pctEl) pctEl.textContent = pct;
        if (dotEl) {
            const color  = complete ? '#22c55e' : (isOverdue ? '#dc2626' : '#f59e0b');
            const shadow = complete ? 'rgba(34,197,94,.2)' : (isOverdue ? 'rgba(220,38,38,.2)' : 'rgba(245,158,11,.2)');
            dotEl.style.background = color;
            dotEl.style.boxShadow  = `0 0 0 3px ${shadow}`;
        }
        // Cache le bouton bulk si plus rien à faire, l'affiche si reste
        const pending = total - doneCount;
        if (bulkBtn) {
            const bulkWrap = bulkBtn.closest('div');
            if (pending > 1) {
                if (bulkWrap) bulkWrap.style.display = '';
                bulkBtn.textContent = `✓✓ Marquer tous décappés (${pending})`;
            } else {
                if (bulkWrap) bulkWrap.style.display = 'none';
            }
        }
    }

    // ─── Fetch summary KPI + MAJ bannière + cards ──────────────
    async function refreshGlobalKpis() {
        try {
            const r = await fetch(SUMMARY_URL, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;
            const setKpi = (key, val) => {
                const el = document.querySelector(`[data-decap-kpi="${key}"]`);
                if (el) el.textContent = key === 'rate' ? Number(val).toFixed(1).replace(/\.0$/, '') : fmt(val);
            };
            setKpi('total',    d.total);
            setKpi('decapped', d.decapped);
            setKpi('pending',  d.pending);
            setKpi('overdue',  d.overdue);
            setKpi('rate',     d.rate);
            // Bannière critique : afficher/masquer selon overdue
            const banner = document.querySelector('[data-decap-overdue-banner]');
            if (banner) {
                banner.style.display = d.overdue > 0
                    ? (banner.dataset.initDisplay || 'flex')
                    : 'none';
                const cnt = banner.querySelector('[data-decap-overdue-banner-count]');
                if (cnt) cnt.textContent = fmt(d.overdue);
            }
            // Card "En retard" : bordure rouge si > 0
            const overdueCard = document.querySelector('[data-decap-kpi-overdue-card]');
            if (overdueCard) {
                overdueCard.style.borderColor = d.overdue > 0 ? 'rgba(220,38,38,.4)' : 'var(--border)';
            }
        } catch (_) { /* silencieux : pas critique */ }
    }

    return {
        async mark(campaignId, panelId) {
            try {
                const res = await postUpdate(campaignId, panelId, 'mark');
                if (!res.ok) { toast(res.message || 'Erreur.', 'error'); return; }
                applyRowState(campaignId, panelId, true, res.at, res.by);
                refreshCampaignSummary(campaignId);
                refreshGlobalKpis();
                toast('✓ Panneau marqué décappé');
            } catch (e) {
                console.error(e);
                toast('Erreur réseau.', 'error');
            }
        },
        async unmark(campaignId, panelId) {
            if (!confirm('Annuler le décappage de ce panneau ?')) return;
            try {
                const res = await postUpdate(campaignId, panelId, 'unmark');
                if (!res.ok) { toast(res.message || 'Erreur.', 'error'); return; }
                applyRowState(campaignId, panelId, false);
                refreshCampaignSummary(campaignId);
                refreshGlobalKpis();
                toast('Décappage annulé');
            } catch (e) {
                console.error(e);
                toast('Erreur réseau.', 'error');
            }
        },
        async markAll(campaignId) {
            if (!confirm('Marquer TOUS les panneaux de cette campagne comme décappés ?')) return;
            try {
                const res = await postBulk(campaignId);
                if (!res.ok) { toast(res.message || 'Erreur.', 'error'); return; }
                // Toutes les lignes pending de cette campagne passent à done
                const now = new Date();
                const at = String(now.getDate()).padStart(2, '0') + '/' + String(now.getMonth() + 1).padStart(2, '0')
                         + ' ' + String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
                document.querySelectorAll(`[data-decap-campaign="${campaignId}"] [data-decap-row][data-decap-state="pending"]`).forEach(row => {
                    const pid = parseInt(row.dataset.decapPanel, 10);
                    if (pid) applyRowState(campaignId, pid, true, at);
                });
                refreshCampaignSummary(campaignId);
                refreshGlobalKpis();
                toast('✓ ' + res.count + ' panneaux décappés');
            } catch (e) {
                console.error(e);
                toast('Erreur réseau.', 'error');
            }
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
        if (document.getElementById('panel-modal')?.style.display === 'flex') {
            window.PanelDrilldown.close();
        }
    }
});

})();
</script>
@endpush

</x-admin-layout>

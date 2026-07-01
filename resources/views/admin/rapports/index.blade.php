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
    // Bloc 4 — Commit 13 : séries CA réel (HT facturé + TTC encaissé) pour le graphique 2 lignes
    caMensuelHt:   {!! json_encode($caMensuelHt->values()) !!},
    caMensuelTtc:  {!! json_encode($caMensuelTtc->values()) !!},
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
    // dans la barre de filtres). Inclut filter_zone (Abidjan/Intérieur)
    // + ca_year/tableau_year (sélecteurs annuels internes).
    $exportFilters = request()->only([
        'preset','from','to','annee','mois_du','mois_au',
        'filter_commune_id','filter_city','filter_client_id','filter_category_id','filter_zone',
        'ca_year','tableau_year',
    ]);
@endphp
{{-- data-export-route : la base statique de l'URL. rapports-live.js suffixe
     la query string courante au moment de chaque mise à jour AJAX. --}}
<div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:14px;flex-wrap:wrap">
    <a href="{{ route('admin.rapports.export.excel', $exportFilters) }}"
       data-export-route="{{ route('admin.rapports.export.excel') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#16a34a;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Télécharger le dashboard complet en Excel (8 feuilles) — respecte les filtres actifs">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Exporter Excel
    </a>
    <a href="{{ route('admin.rapports.export.pdf', $exportFilters) }}"
       data-export-route="{{ route('admin.rapports.export.pdf') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Télécharger une synthèse exécutive PDF (1 page) — respecte les filtres actifs">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8"/></svg>
        Synthèse PDF
    </a>
    {{-- Séparateur visuel --}}
    <span style="width:1px;height:24px;background:var(--border);margin:0 4px"></span>
    {{-- Exports dédiés : panneaux + taux d'occupation (filtrés par zone si sélectionnée) --}}
    <a href="{{ route('admin.rapports.export.panels-occupation-excel', $exportFilters) }}"
       data-export-route="{{ route('admin.rapports.export.panels-occupation-excel') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#0f766e;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Exporter la liste complète des panneaux avec leur taux d'occupation sur la période (Excel)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        Panneaux + occupation (Excel)
    </a>
    <a href="{{ route('admin.rapports.export.panels-occupation-pdf', $exportFilters) }}"
       data-export-route="{{ route('admin.rapports.export.panels-occupation-pdf') }}"
       style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#9333ea;color:#fff;border-radius:8px;text-decoration:none;font-size:12px;font-weight:700"
       title="Exporter la liste complète des panneaux avec leur taux d'occupation (PDF A4 paysage)">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M16 13H8M16 17H8"/></svg>
        Panneaux + occupation (PDF)
    </a>
</div>

{{-- 2026-06-18 (feedback patronne) : la barre de filtres a été déplacée
     SOUS la barre d'onglets pour libérer la vue d'entrée (KPI/cards plus
     proches du titre). Voir bloc <form id="form-periode"> plus bas, juste
     après la div des onglets. --}}

@include('admin.rapports.partials._topcards')

@include('admin.rapports.partials._kpis')

{{-- ════ ONGLETS ════ --}}
<div style="display:flex;gap:4px;margin-bottom:20px;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:6px;flex-wrap:wrap">
    @php
    // ── RBAC module Rapport — politique par rôle ──────────────
    // Admin       : vue globale entreprise complète (CA stratégique,
    //               EBITDA, synthèse exécutive direction, exports).
    // MP          : vue PRODUCTION / opérationnelle. Parc, performance
    //               panneaux, géo, clients, taxes, motifs annulation,
    //               décapages. PAS de CA global ni d'insights stratégiques
    //               (réservés à la direction).
    // Commercial  : vue PERSONNELLE filtrée à ses campagnes. Périodes,
    //               ses campagnes, SON CA, ses décapages.
    // NB : re-resolution locale du role (cf. note sur le scope @php).
    $tabRole = auth()->user()?->role?->value;

    $onglets = [
        ['id'=>'occupation','icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>','label'=>"Occupation"],
        ['id'=>'occupation-details','icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>','label'=>'Occupation détaillée'],
        ['id'=>'panneaux',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>','label'=>'Performance panneaux'],
        ['id'=>'periodes',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>','label'=>'Périodes'],
        // 'Campagnes' retiré (2026-06-17) — doublon avec la carte 'Rapport campagnes'
        // en haut de la page qui redirige vers /admin/rapports/campagnes.
        ['id'=>'ca',        'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>','label'=>'CA & Revenus'],
        ['id'=>'zones',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>','label'=>'Zones & Communes'],
        ['id'=>'clients',   'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>','label'=>'Clients'],
        ['id'=>'decap',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>','label'=>'Décapages'],
        ['id'=>'insights',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11h.01M15 11h.01M18 21l-3-3H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2h-3l-3 3z"/></svg>','label'=>'Insights & Alertes'],
        // 'SLA & Retards' retiré (2026-06-17) — page dédiée /admin/sla/retards
        // accessible via sidebar 'SLA & Retards'.
    ];

    // Mapping rôle → onglets autorisés (null = tous = admin).
    $tabsByRole = [
        'admin'        => null, // tous (après retraits SLA + Campagnes)
        'mediaplanner' => [     // production / opérationnel
            'occupation', 'occupation-details', 'panneaux', 'periodes',
            'zones', 'clients', 'decap',
            // EXCLUS pour MP : 'ca' (CA stratégique entreprise),
            //                  'insights' (synthèse exécutive direction).
        ],
        'commercial'   => [     // strictement personnel filtré
            'periodes', 'ca', 'decap',
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
                <span title="Décapages en retard"
                      style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#dc2626;color:#fff;font-size:10px;font-weight:800;line-height:1;animation:rpt-pulse 1.6s ease-in-out infinite">{{ $decapStats['overdue'] }}</span>
            @endif
        </span>
    </button>
    @endforeach
</div>

{{-- ════ FILTRES AVANCÉS (presets + dates custom + filtres) ════
     2026-06-18 (feedback patronne) : déplacé du dessus de la barre
     d'onglets vers ICI (sous les onglets). Le user voit d'abord le
     titre + topcards + KPI + onglets, puis ajuste ses filtres en
     dessous quand il veut affiner. --}}
<form id="form-periode" method="GET" action="{{ route('admin.rapports.index') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-bottom:20px">

    {{-- ⚠ 2026-06-18 (feedback patronne) : les selects (Commune, Ville,
         Client, Type panneau) étaient sur la 2e ligne et leur dropdown
         natif s'ouvrait VERS LE HAUT par manque de place en dessous.
         Solution : on inverse — Filtres dimensionnels EN HAUT (ligne 1),
         Période rapide EN BAS (ligne 2). Les dropdowns natifs ont
         maintenant tout l'espace de la page sous eux et s'ouvrent
         naturellement vers le bas. --}}

    {{-- Ligne 1 : Filtres dimensionnels ────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:12px;border-bottom:1px solid var(--border);padding-bottom:12px;">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
            Filtres
        </span>
        <select name="filter_zone" onchange="this.form.submit()"
                class="rpt-filter-select" style="min-width:130px;font-weight:600"
                title="Zone : Abidjan ou Intérieur (toutes les villes hors Abidjan)">
            <option value="">Toutes zones</option>
            <option value="abidjan"   {{ ($filterZone ?? null) === 'abidjan'   ? 'selected' : '' }}>Abidjan</option>
            <option value="interieur" {{ ($filterZone ?? null) === 'interieur' ? 'selected' : '' }}>Intérieur</option>
        </select>
        <select name="filter_commune_id" onchange="this.form.submit()"
                class="rpt-filter-select" style="min-width:160px;">
            <option value="">Toutes communes</option>
            @foreach($allCommunes as $c)
                <option value="{{ $c->id }}" {{ $filterCommune == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="filter_city" onchange="this.form.submit()"
                class="rpt-filter-select" style="min-width:140px;">
            <option value="">Toutes villes</option>
            @foreach($allCities as $city)
                <option value="{{ $city }}" {{ $filterCity == $city ? 'selected' : '' }}>{{ $city }}</option>
            @endforeach
        </select>
        <select name="filter_client_id" onchange="this.form.submit()"
                class="rpt-filter-select" style="min-width:170px;">
            <option value="">Tous clients</option>
            @foreach($allClients as $c)
                <option value="{{ $c->id }}" {{ $filterClient == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
        </select>
        <select name="filter_category_id" onchange="this.form.submit()"
                class="rpt-filter-select" style="min-width:160px;">
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
    </div>

    {{-- Ligne 2 : Presets période ───────────────────────────────── --}}
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
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
            @php $isActive = $currentPreset === $key; @endphp
            <a href="{{ route('admin.rapports.index', array_merge(request()->except(['preset','from','to','annee','mois_du','mois_au']), ['preset' => $key])) }}"
               class="rapport-preset-pill {{ $isActive ? 'is-active' : '' }}">
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

    {{-- Ligne 3 : récap textuel des filtres actifs (partial _summary) --}}
    @include('admin.rapports.partials._summary')
</form>

{{-- ══════════════════════════════════
     ONGLET 1 — OCCUPATION (admin/MP only)
══════════════════════════════════ --}}
@if(in_array('occupation', $allowedTabIds, true))
@include('admin.rapports.partials._tab_occupation')

@endif {{-- panel-occupation --}}

{{-- ══════════════════════════════════
     ONGLET 1-bis — OCCUPATION DÉTAILLÉE (admin/MP)
     Liste panneau × campagne active sur la période demandée.
     Ajouté 2026-07-01 sur demande patronne pour voir concrètement qui
     a occupé quoi durant un trimestre (retiré du module Taxes qui était
     mal placé sémantiquement).
══════════════════════════════════ --}}
@if(in_array('occupation-details', $allowedTabIds, true))
@include('admin.rapports.partials._tab_occupation_details')

@endif {{-- panel-occupation-details --}}

{{-- ══════════════════════════════════
     ONGLET 2 — PÉRIODES (commercial OK : scopé via applyCampaignFilters)
══════════════════════════════════ --}}
@if(in_array('periodes', $allowedTabIds, true))
@include('admin.rapports.partials._tab_periodes')

@endif {{-- panel-periodes --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — CAMPAGNES (commercial OK : scopé)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('campagnes', $allowedTabIds, true))
@include('admin.rapports.partials._tab_campagnes')

@endif {{-- panel-campagnes --}}

{{-- ══════════════════════════════════
     ONGLET 3 — CA & REVENUS (commercial OK : scopé sur ses campagnes)
══════════════════════════════════ --}}
@if(in_array('ca', $allowedTabIds, true))
@include('admin.rapports.partials._tab_ca')

@endif {{-- panel-ca --}}

{{-- ══════════════════════════════════
     ONGLET 4 — ZONES & COMMUNES (admin/MP only : parc géographique)
══════════════════════════════════ --}}
@if(in_array('zones', $allowedTabIds, true))
@include('admin.rapports.partials._tab_zones')

@endif {{-- panel-zones --}}

{{-- ══════════════════════════════════
     ONGLET 5 — CLIENTS (admin/MP only : top clients entreprise)
══════════════════════════════════ --}}
@if(in_array('clients', $allowedTabIds, true))
@include('admin.rapports.partials._tab_clients')

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
@include('admin.rapports.partials._tab_panneaux')

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
     ONGLET — DÉCAPAGES (commercial OK : scopé via decapStats + decapList)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('decap', $allowedTabIds, true))
@include('admin.rapports.partials._tab_decappages')

@endif {{-- panel-decap --}}

{{-- ══════════════════════════════════════════════════════════════
     ONGLET — INSIGHTS & ALERTES (admin/MP only : tendances entreprise)
══════════════════════════════════════════════════════════════ --}}
@if(in_array('insights', $allowedTabIds, true))
@include('admin.rapports.partials._tab_insights')
@endif {{-- panel-insights --}}

{{-- ONGLET SLA & RETARDS retiré (2026-06-17) — accessible via la page
     dédiée /admin/sla/retards (sidebar 'SLA & Retards'). Le partial
     _tab_sla.blade.php est conservé sur disque mais plus inclus. --}}

{{-- ════ STYLES ════ --}}
<style>
/* ─── Filtres select Rapports (2026-06-18) ────────────────────────
   Avant : selects sans chevron → ressemblaient à de simples chips
   pas évidemment cliquables. Fix : chevron SVG, focus ring, hover. */
.rpt-filter-select {
    height: 34px;
    padding: 0 32px 0 12px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 12.5px;
    color: var(--text);
    font-family: inherit;
    cursor: pointer;
    outline: none;
    transition: border-color .12s, box-shadow .12s, background .12s;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 10px center;
    background-size: 12px;
}
.rpt-filter-select:hover { border-color: var(--accent, #e8a020); background-color: var(--surface2); }
.rpt-filter-select:focus { border-color: var(--accent, #e8a020); box-shadow: 0 0 0 3px rgba(232,160,32,.18); }
.rpt-filter-select option { font-size: 13px; padding: 4px; }

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
/* Breakpoint intermédiaire pour la grille Clients (4 podiums) — évite le tassement
   sur écran moyen (1200px → 2 colonnes au lieu d'écraser 4 podiums sur 4 colonnes). */
@media (max-width: 1200px) and (min-width: 901px) { .rpt-grid-clients { grid-template-columns: repeat(2, 1fr) !important; } }
@media (max-width: 900px) { .rpt-grid-2 { grid-template-columns: 1fr !important; } .rpt-grid-clients { grid-template-columns: 1fr !important; } .rpt-grid-5 { grid-template-columns: repeat(2, 1fr) !important; } }
@keyframes rpt-pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,.6); } 50% { box-shadow: 0 0 0 4px rgba(220,38,38,0); } }

/* ── Pilules "Période rapide" ───────────────────────────────────
   Pilule au repos : fond surface2, texte gris, bordure discrète.
   Pilule active : fond ACCENT plein avec ombre douce + scale léger
   pour qu'on voie immédiatement laquelle est sélectionnée.
*/
.rapport-preset-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    font-size: 11.5px;
    font-weight: 600;
    border-radius: 999px;
    text-decoration: none;
    border: 1px solid var(--border);
    background: var(--surface2);
    color: var(--text2);
    transition: background .15s, color .15s, border-color .15s, box-shadow .2s, transform .12s;
    white-space: nowrap;
}
.rapport-preset-pill:hover {
    background: var(--accent-dim);
    border-color: var(--accent);
    color: var(--accent-dark, var(--accent));
}
.rapport-preset-pill.is-active {
    background: var(--accent);
    border-color: var(--accent);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 4px 12px -2px rgba(232, 160, 32, .45);
    transform: translateY(-1px);
}
.rapport-preset-pill.is-active:hover {
    background: var(--accent-dark, var(--accent));
    color: #fff;
    border-color: var(--accent-dark, var(--accent));
}
</style>

{{-- ════ JAVASCRIPT ════ --}}
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
{{-- Progressive enhancement : si Vite ou ce fichier ne charge pas,
     le form garde son submit GET classique (action="rapports.index"),
     la page se recharge entièrement comme avant. Aucun blocage. --}}
@vite('resources/js/rapports-live.js')
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
        if (id==='ca'        &&!this._caDone)       { this.renderCaReal(); this.renderCa();   this.renderRevenueTrend();    this.renderOccVsRevenue(); this._caDone=true; }
        if (id==='zones'     &&!this._hmDone)       { HM.init();         this._hmDone=true; }
        if (id==='panneaux'  &&!this._panneauxDone) { this.renderTopPanels();    this._panneauxDone=true; }
        if (id==='clients'   &&!this._clientsDone)  { this.renderClientDistribution(); this._clientsDone=true; }
        if (id==='insights'  &&!this._insightsDone) { this.renderInsightsCharts();this._insightsDone=true; }
        // 'campagnes' et 'sla' switchs retirés le 2026-06-17 (onglets eux-mêmes retirés).
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

    // ── Chart.js — CA RÉEL mensuel (2 lignes : HT facturé + TTC encaissé)
    //   Bloc 4 Commit 13 (2026-06-18). Data injectée via D.caMensuelHt et
    //   D.caMensuelTtc (CaRealService::mensuelHtFacture / mensuelTtcEncaisse).
    //   Année calendaire complète scopée sur le sélecteur ca_year.
    renderCaReal() {
        const canvas = document.getElementById('chart-ca-real');
        const ht     = D.caMensuelHt;
        const ttc    = D.caMensuelTtc;
        if (!canvas || typeof Chart === 'undefined') return;
        if (!ht?.length && !ttc?.length) return;
        const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
        const gridC  = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
        const tickC  = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
        const labels = (ht?.length ? ht : ttc).map(d => d.label);
        new Chart(canvas, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: '📤 HT facturé',
                        data: ht.map(d => d.ht),
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,.12)',
                        borderWidth: 2.5,
                        tension: .35,
                        fill: true,
                        pointBackgroundColor: '#f59e0b',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                    {
                        label: '💰 TTC encaissé',
                        data: ttc.map(d => d.ttc),
                        borderColor: '#16a34a',
                        backgroundColor: 'rgba(22,163,74,.08)',
                        borderWidth: 2.5,
                        tension: .35,
                        fill: false,
                        pointBackgroundColor: '#16a34a',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: tickC, font: { size: 11, weight: '700' }, usePointStyle: true, padding: 14 },
                    },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.dataset.label + ' : ' + new Intl.NumberFormat('fr-FR').format(Math.round(ctx.parsed.y)) + ' FCFA',
                        },
                    },
                },
                scales: {
                    x: { ticks: { color: tickC, font: { size: 11 } }, grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: { color: tickC, font: { size: 11 }, callback: v => v >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : (v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v) },
                        grid: { color: gridC },
                    },
                },
            },
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

    // renderSlaMotifs retiré le 2026-06-17 — l'onglet 'SLA & Retards' a été
    // retiré de la nav Rapports (cf. _tab_sla.blade.php supprimé). Le doughnut
    // motifs vit désormais uniquement sur /admin/sla/retards (page dédiée).
};

// Init graphiques de l'onglet par défaut (occupation) au chargement
document.addEventListener('DOMContentLoaded', () => {
    RPT.renderEvol();
    RPT.renderOccupationTrend();
    RPT._evolDone = true;
});

// ══════════════════════════════════════════════════════════════════
// RPT.refreshAllCharts(newData) — rafraîchit TOUS les graphiques
// Chart.js de la page sans rechargement, appelée par rapports-live.js
// après chaque réponse AJAX.
//
// Étapes :
//   1. Object.assign(D, newData) → met à jour le data store partagé.
//      (D pointe sur window.__RPT__ ; on garde la même référence pour
//       que tous les renders existants voient les nouvelles données.)
//   2. Détruit toutes les instances Chart.js attachées aux <canvas>
//      connus, via l'API officielle Chart.getChart() (pas de fuite mém).
//   3. Reset les flags _xxxDone pour autoriser un re-render.
//   4. Re-call les renders custom (renderEvol/renderCa) + Chart.js.
//      Les renders no-op si data absente ou canvas absent → safe.
// ══════════════════════════════════════════════════════════════════
RPT.refreshAllCharts = function (newData) {
    if (!newData) return;
    Object.assign(D, newData);

    var chartIds = [
        'chart-occupation-trend', 'chart-top-panels',
        'chart-cancel-trend', 'chart-cancel-reasons-camp',
        'chart-revenue-trend', 'chart-occ-revenue',
        'chart-client-dist', 'hm-bar-chart',
        'chart-inactivity', 'chart-cancel-reasons',
        // 'chart-sla-motifs' retiré le 2026-06-17 (onglet SLA retiré de Rapports)
        // Bloc 4 Commit 13 (2026-06-18) : CA réel mensuel 2 lignes
        'chart-ca-real',
    ];
    chartIds.forEach(function (id) {
        var c = document.getElementById(id);
        if (c && typeof Chart !== 'undefined' && Chart.getChart) {
            var inst = Chart.getChart(c);
            if (inst) { try { inst.destroy(); } catch (_) {} }
        }
    });
    if (typeof hmChart !== 'undefined' && hmChart) {
        try { hmChart.destroy(); } catch (_) {} hmChart = null;
    }

    RPT._evolDone = RPT._caDone = RPT._panneauxDone = RPT._clientsDone =
        RPT._campagnesDone = RPT._insightsDone = RPT._hmDone = false;

    var safeCall = function (fn) { try { fn(); } catch (_) {} };
    safeCall(function () { RPT.renderEvol(); });
    safeCall(function () { RPT.renderCa(); });
    safeCall(function () { RPT.renderCaReal(); });
    safeCall(function () { RPT.renderOccupationTrend(); });
    safeCall(function () { RPT.renderRevenueTrend(); });
    safeCall(function () { RPT.renderOccVsRevenue(); });
    safeCall(function () { RPT.renderTopPanels(); });
    safeCall(function () { RPT.renderCancellationTrend(); });
    safeCall(function () { RPT.renderCancelReasonsCamp(); });
    safeCall(function () { RPT.renderClientDistribution(); });
    safeCall(function () { RPT.renderInsightsCharts(); });
    // RPT.renderSlaMotifs() retiré (méthode + canvas n'existent plus)
    if (typeof HM !== 'undefined' && HM.init) safeCall(function () { HM.init(); });
};

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
// DECAP — module marquage décapage (COMMIT C)
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
                statusCell.innerHTML = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(34,197,94,.12);color:#16a34a">✓ Décapé le ${when}</span>`;
            } else {
                statusCell.innerHTML = `<span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,.12);color:#d97706">En attente</span>`;
            }
        }
        if (actionCell) {
            if (done) {
                actionCell.innerHTML = `<button type="button" onclick="Decap.unmark(${campaignId}, ${panelId})" style="font-size:10px;font-weight:600;padding:4px 10px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);border-radius:6px;cursor:pointer">Annuler</button>`;
            } else {
                actionCell.innerHTML = `<button type="button" onclick="Decap.mark(${campaignId}, ${panelId})" style="font-size:10px;font-weight:700;padding:4px 10px;border:none;background:#22c55e;color:#fff;border-radius:6px;cursor:pointer">✓ Marquer décapé</button>`;
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
                bulkBtn.textContent = `✓✓ Marquer tous décapés (${pending})`;
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
                toast('✓ Panneau marqué décapé');
            } catch (e) {
                console.error(e);
                toast('Erreur réseau.', 'error');
            }
        },
        async unmark(campaignId, panelId) {
            if (!confirm('Annuler le décapage de ce panneau ?')) return;
            try {
                const res = await postUpdate(campaignId, panelId, 'unmark');
                if (!res.ok) { toast(res.message || 'Erreur.', 'error'); return; }
                applyRowState(campaignId, panelId, false);
                refreshCampaignSummary(campaignId);
                refreshGlobalKpis();
                toast('Décapage annulé');
            } catch (e) {
                console.error(e);
                toast('Erreur réseau.', 'error');
            }
        },
        async markAll(campaignId) {
            if (!confirm('Marquer TOUS les panneaux de cette campagne comme décapés ?')) return;
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
                toast('✓ ' + res.count + ' panneaux décapés');
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

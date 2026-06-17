<div id="rpt-kpis">
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
</div>
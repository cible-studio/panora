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
};
</script>

{{-- ════ FILTRES PÉRIODE ════ --}}
<form id="form-periode" method="GET" action="{{ route('admin.rapports.index') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">

        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            Période
        </span>

        <select name="annee" onchange="this.form.submit()"
                style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text)">
            @foreach($anneesDisponibles as $a)
                <option value="{{ $a }}" {{ $a == $annee ? 'selected' : '' }}>{{ $a }}</option>
            @endforeach
        </select>

        <select name="mois_du" onchange="this.form.submit()"
                style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text)">
            @foreach(['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'] as $i => $m)
                <option value="{{ $i+1 }}" {{ ($i+1) == $moisDu ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>

        <span style="color:var(--text3);font-size:12px">→</span>

        <select name="mois_au" onchange="this.form.submit()"
                style="height:36px;padding:0 10px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text)">
            @foreach(['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'] as $i => $m)
                <option value="{{ $i+1 }}" {{ ($i+1) == $moisAu ? 'selected' : '' }}>{{ $m }}</option>
            @endforeach
        </select>

        <span style="font-size:11px;color:var(--text3)">
            {{ $dateFrom->format('d/m/Y') }} → {{ $dateTo->format('d/m/Y') }}
        </span>

        <div style="margin-left:auto;font-size:11px;color:var(--text3)">
            {{ number_format($totalPanneaux) }} panneaux ·
            {{ number_format($totalClients) }} clients ·
            {{ number_format($totalCampagnes) }} campagnes
        </div>
    </div>
</form>

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
        ['id'=>'occupation','icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>','label'=>"Taux d'occupation"],
        ['id'=>'periodes',  'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>','label'=>'Périodes'],
        ['id'=>'ca',        'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>','label'=>'CA & Revenus'],
        ['id'=>'zones',     'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>','label'=>'Zones & Communes'],
        ['id'=>'clients',   'icon'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>','label'=>'Clients'],
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

    {{-- Évolution mensuelle --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Évolution mensuelle — 12 derniers mois</span>
        </div>
        <div id="chart-evol" style="display:flex;align-items:flex-end;gap:4px;height:120px"></div>
        <div id="chart-evol-labels" style="display:flex;gap:4px;margin-top:6px"></div>
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
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $row['commune'] }}</td>
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
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px">
                            <a href="{{ route('admin.clients.show', $client['id']) }}" style="font-size:13px;font-weight:600;color:var(--accent);text-decoration:none">{{ $client['name'] }}</a>
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

{{-- ════ STYLES ════ --}}
<style>
.rpt-tab { flex:1;padding:9px 10px;border-radius:10px;border:none;background:transparent;color:var(--text3);font-size:12px;font-weight:600;cursor:pointer;transition:all .15s;white-space:nowrap; }
.rpt-tab:hover { background:var(--surface2);color:var(--text); }
.rpt-tab.active { background:var(--accent);color:#000; }
.kpi-active { border-color:var(--kpi-c,var(--accent)) !important;background:var(--surface2) !important;transform:translateY(-3px) !important; }
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
        return `<div style="background:${bg};border-radius:10px;padding:14px 12px;cursor:default;
            transition:transform .15s,box-shadow .15s;position:relative;overflow:hidden"
            onmouseenter="this.style.transform='scale(1.05)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.25)'"
            onmouseleave="this.style.transform='';this.style.boxShadow=''"
            title="${d.commune} — Taux: ${d.taux}% · ${d.total} panneaux · CA: ${(d.ca_annee/1000000).toFixed(1)}M FCFA">
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
        if (id==='occupation'&&!this._evolDone) { this.renderEvol(); this._evolDone=true; }
        if (id==='ca'        &&!this._caDone)   { this.renderCa();   this._caDone=true; }
        if (id==='zones'     &&!this._hmDone)   { HM.init();         this._hmDone=true; }
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
};

// Init graphique évolution au chargement (onglet 1 actif par défaut)
document.addEventListener('DOMContentLoaded', ()=>{ RPT.renderEvol(); RPT._evolDone=true; });

})();
</script>
@endpush

</x-admin-layout>

<x-admin-layout title="Top Commercial">

<x-slot:topbarLeft>
    <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm">
        ← Retour aux rapports
    </a>
</x-slot:topbarLeft>

{{-- ════ Filtre période ═══════════════════════════════════════════ --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:18px;flex-wrap:wrap">
    <span style="font-size:11px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-right:4px">Période</span>
    @foreach([['month','Mois en cours'], ['30d','30 derniers jours'], ['year','Année en cours']] as [$key, $label])
        <a href="{{ route('admin.rapports.top-commercial', ['period' => $key]) }}"
           class="btn btn-sm {{ $period === $key ? 'btn-primary' : 'btn-ghost' }}"
           style="font-size:12px">
            {{ $label }}
        </a>
    @endforeach
    <span style="margin-left:auto;font-size:12px;color:var(--text3)">
        {{ $dateFrom->format('d/m/Y') }} → {{ $dateTo->format('d/m/Y') }}
    </span>
</div>

{{-- ════ KPI Header ═══════════════════════════════════════════════ --}}
@php
    $kpis = [
        [
            'label' => 'CA généré',
            'sub'   => 'campagnes hors annulées',
            'value' => number_format($caTotalPeriode, 0, ',', ' ') . ' FCFA',
            'color' => 'var(--accent)',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        ],
        [
            'label' => 'Campagnes',
            'sub'   => 'sur la période',
            'value' => number_format($nbCampagnesPeriode),
            'color' => '#3b82f6',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l19-9-9 19-2-8-8-2z"/></svg>',
        ],
        [
            'label' => 'Commerciaux actifs',
            'sub'   => 'au moins 1 campagne',
            'value' => number_format($nbCommerciauxActifs),
            'color' => '#22c55e',
            'icon'  => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        ],
    ];
@endphp
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-bottom:24px">
    @foreach($kpis as $k)
    <div class="kpi-card" style="--kpi-color:{{ $k['color'] }}">
        <div class="kpi-card__top-bar" style="background:{{ $k['color'] }}"></div>
        <div class="kpi-card__icon" style="color:{{ $k['color'] }}">{!! $k['icon'] !!}</div>
        <div class="kpi-card__value" style="color:{{ $k['color'] }};font-size:22px">{{ $k['value'] }}</div>
        <div class="kpi-card__label">{{ $k['label'] }}</div>
        <div class="kpi-card__sub">{{ $k['sub'] }}</div>
    </div>
    @endforeach
</div>

{{-- ════ 2 colonnes : Top par CA · Top par nombre ══════════════════ --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

    {{-- ── Top par CA ────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">💰 Top par CA</div>
            <span style="font-size:12px;color:var(--text3)">{{ $periodLabel }}</span>
        </div>
        @if($topByCA->isEmpty())
            <div style="padding:48px 20px;text-align:center;color:var(--text3);font-size:13px">
                Aucun commercial actif sur cette période.
            </div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:48px">#</th>
                        <th>Commercial</th>
                        <th style="text-align:right">CA</th>
                        <th style="text-align:right">Camp.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topByCA as $i => $row)
                    @php
                        $rank = $i + 1;
                        $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : null));
                    @endphp
                    <tr>
                        <td style="font-weight:700;color:var(--text3);font-size:13px">
                            {{ $medal ?? '#'.$rank }}
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar-circle">
                                    {{ strtoupper(substr($row->user?->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--text)">{{ $row->user?->name ?? '— Inconnu —' }}</div>
                                    @if($row->user?->role)
                                        <div style="font-size:10px;color:var(--text3)">{{ $row->user->role->value }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;font-weight:700;color:var(--accent)">
                            {{ number_format($row->ca_total, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3);font-weight:500">FCFA</span>
                        </td>
                        <td style="text-align:right;color:var(--text2);font-size:12px">{{ $row->nb_campagnes }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Top par nombre de campagnes ───────────────────────────── --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📊 Top par nombre de campagnes</div>
            <span style="font-size:12px;color:var(--text3)">{{ $periodLabel }}</span>
        </div>
        @if($topByCount->isEmpty())
            <div style="padding:48px 20px;text-align:center;color:var(--text3);font-size:13px">
                Aucun commercial actif sur cette période.
            </div>
        @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:48px">#</th>
                        <th>Commercial</th>
                        <th style="text-align:right">Camp.</th>
                        <th style="text-align:right">CA</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topByCount as $i => $row)
                    @php
                        $rank = $i + 1;
                        $medal = $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : null));
                    @endphp
                    <tr>
                        <td style="font-weight:700;color:var(--text3);font-size:13px">
                            {{ $medal ?? '#'.$rank }}
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="avatar-circle" style="background:linear-gradient(135deg,#3b82f6,#1d4ed8)">
                                    {{ strtoupper(substr($row->user?->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <div style="font-weight:600;color:var(--text)">{{ $row->user?->name ?? '— Inconnu —' }}</div>
                                    @if($row->user?->role)
                                        <div style="font-size:10px;color:var(--text3)">{{ $row->user->role->value }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td style="text-align:right;font-weight:700;color:#3b82f6">{{ $row->nb_campagnes }}</td>
                        <td style="text-align:right;color:var(--text2);font-size:12px">
                            {{ number_format($row->ca_total, 0, ',', ' ') }}
                            <span style="font-size:10px;color:var(--text3)">FCFA</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

<div style="margin-top:14px;font-size:11px;color:var(--text3);text-align:center">
    Le commercial assigné est crédité en priorité. Si aucun n'est défini, le créateur de la campagne est utilisé.
    Les campagnes annulées sont exclues.
</div>

</x-admin-layout>

<x-admin-layout>
    <x-slot name="title">Taxes — Détail par panneau</x-slot>

    <x-slot:topbarLeft>
        <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm">← Retour aux taxes</a>
    </x-slot:topbarLeft>

    <x-slot name="topbarActions">
        <span class="badge badge-blue">{{ $lines->count() }} ligne(s)</span>
        @if($lines->isNotEmpty())
            @php
                $exportParams = array_merge(['year' => $year, 'period_type' => $periodType, 'period_value' => $periodValue], $filters);
            @endphp
            <a href="{{ route('admin.taxes.details.excel', $exportParams) }}"
               class="btn btn-ghost btn-sm">📊 Excel</a>
            <a href="{{ route('admin.taxes.details.pdf', $exportParams) }}"
               class="btn btn-primary btn-sm" target="_blank" rel="noopener">📄 PDF</a>
        @endif
    </x-slot>

    @php
        $typeLabels = ['tm' => 'TM', 'odp' => 'ODP', 'db' => 'DB'];
        $typeColors = [
            'tm'  => ['bg' => 'rgba(34,197,94,.1)',  'c' => '#16a34a'],
            'odp' => ['bg' => 'rgba(249,115,22,.1)', 'c' => '#ea580c'],
            'db'  => ['bg' => 'rgba(59,130,246,.1)', 'c' => '#2563eb'],
        ];
        $statutLabels = [
            'libre'       => '🟢 Libre',
            'occupe'      => '🔴 Occupé',
            'option'      => '🟡 Option',
            'confirme'    => '🔵 Confirmé',
            'maintenance' => '🔧 Maintenance',
        ];
    @endphp

    {{-- ═══ FILTRES ═══ --}}
    <div class="card" style="margin-bottom:16px;">
        <form method="GET" action="{{ route('admin.taxes.details') }}">
            <div class="filter-bar" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
                <div class="filter-group">
                    <label class="filter-label">Périodicité</label>
                    <select name="period_type" class="filter-select" onchange="this.form.submit()">
                        <option value="mensuel"     {{ $periodType === 'mensuel'     ? 'selected' : '' }}>Mensuel</option>
                        <option value="trimestriel" {{ $periodType === 'trimestriel' ? 'selected' : '' }}>Trimestriel</option>
                        <option value="annuel"      {{ $periodType === 'annuel'      ? 'selected' : '' }}>Annuel</option>
                    </select>
                </div>
                @if($periodType !== 'annuel')
                <div class="filter-group">
                    <label class="filter-label">{{ $periodType === 'mensuel' ? 'Mois' : 'Trimestre' }}</label>
                    <select name="period_value" class="filter-select" onchange="this.form.submit()">
                        @if($periodType === 'mensuel')
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" {{ $periodValue === $m ? 'selected' : '' }}>
                                    {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                                </option>
                            @endforeach
                        @else
                            @foreach(range(1,4) as $q)
                                <option value="{{ $q }}" {{ $periodValue === $q ? 'selected' : '' }}>T{{ $q }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>
                @endif
                <div class="filter-group">
                    <label class="filter-label">Année</label>
                    <select name="year" class="filter-select" onchange="this.form.submit()">
                        @foreach($anneesDispos as $y)
                            <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Commune</label>
                    <select name="commune_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        @foreach($communes as $c)
                            <option value="{{ $c->id }}" {{ ($filters['commune_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Client</label>
                    <select name="client_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tous</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}" {{ ($filters['client_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Campagne</label>
                    <select name="campaign_id" class="filter-select" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}" {{ ($filters['campaign_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Type taxe</label>
                    <select name="type" class="filter-select" onchange="this.form.submit()">
                        <option value="">Toutes</option>
                        <option value="tm"  {{ ($filters['type'] ?? null) === 'tm'  ? 'selected' : '' }}>TM</option>
                        <option value="odp" {{ ($filters['type'] ?? null) === 'odp' ? 'selected' : '' }}>ODP</option>
                        <option value="db"  {{ ($filters['type'] ?? null) === 'db'  ? 'selected' : '' }}>DB</option>
                    </select>
                </div>
                @if(!empty($filters))
                <div class="filter-group">
                    <a href="{{ route('admin.taxes.details') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
                @endif
            </div>
        </form>
    </div>

    {{-- ═══ KPI ═══ --}}
    @php
        $kpis = [
            [
                'label' => 'Total taxes', 'val' => $totals['total'], 'sub' => 'cumul période',
                'color' => 'var(--accent)', 'unit' => ' FCFA',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
            ],
            [
                'label' => 'TM', 'val' => $totals['by_type']['tm']  ?? 0, 'sub' => 'taxe municipale',
                'color' => '#22c55e', 'unit' => ' FCFA',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M10 21V11h4v10"/></svg>',
            ],
            [
                'label' => 'ODP', 'val' => $totals['by_type']['odp'] ?? 0, 'sub' => 'domaine public',
                'color' => '#f97316', 'unit' => ' FCFA',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            ],
            [
                'label' => 'DB', 'val' => $totals['by_type']['db']  ?? 0, 'sub' => 'droits de bord',
                'color' => '#3b82f6', 'unit' => ' FCFA',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            ],
            [
                'label' => 'Panneaux', 'val' => $totals['panels_count'], 'sub' => 'concernés période',
                'color' => '#6b7280', 'unit' => '',
                'svg' => '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>',
            ],
        ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
        @foreach($kpis as $k)
        <div class="kpi-card" style="--kpi-color:{{ $k['color'] }}">
            <div class="kpi-card__top-bar" style="background:{{ $k['color'] }}"></div>
            <div class="kpi-card__icon" style="color:{{ $k['color'] }}">{!! $k['svg'] !!}</div>
            <div class="kpi-card__value" style="color:{{ $k['color'] }}">{{ number_format($k['val'], 0, ',', ' ') }}{{ $k['unit'] }}</div>
            <div class="kpi-card__label">{{ $k['label'] }}</div>
            <div class="kpi-card__sub">{{ $k['sub'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ═══ TABLEAU DÉTAILLÉ ═══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Détail par panneau</div>
            @if($lines->isNotEmpty())
                <div style="font-size:12px;color:var(--text3);">
                    Période :
                    <strong>{{ $lines->first()['period_start']?->format('d/m/Y') }}
                    → {{ $lines->first()['period_end']?->format('d/m/Y') }}</strong>
                </div>
            @endif
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Commune</th>
                        <th>Panneau</th>
                        <th>Dimensions</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Client</th>
                        <th>Campagne</th>
                        <th>Période campagne</th>
                        <th style="text-align:right;">Tarif</th>
                        <th style="text-align:right;">Montant</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($lines as $row)
                    @php
                        $tc = $typeColors[$row['type']] ?? ['bg' => 'var(--surface2)', 'c' => 'var(--text2)'];
                    @endphp
                    <tr>
                        <td style="font-weight:600;">{{ $row['commune'] }}</td>
                        <td>
                            <div style="font-family:monospace;font-weight:700;color:var(--accent);font-size:12px;">{{ $row['reference'] }}</div>
                            <div style="font-size:11px;color:var(--text3);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $row['name'] }}">{{ $row['name'] }}</div>
                        </td>
                        <td>
                            {{ $row['dimensions'] }}
                            <div style="font-size:10px;color:var(--text3);">{{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }} m²</div>
                        </td>
                        <td>
                            <span class="badge" style="background:{{ $tc['bg'] }};color:{{ $tc['c'] }};font-weight:700;">
                                {{ $typeLabels[$row['type']] ?? $row['type'] }}
                            </span>
                        </td>
                        <td style="font-size:12px;">{{ $statutLabels[$row['statut']] ?? $row['statut'] }}</td>
                        <td>{{ $row['client_name'] ?? '—' }}</td>
                        <td>
                            @if($row['campaign_id'])
                                <a href="{{ route('admin.campaigns.show', $row['campaign_id']) }}"
                                   style="color:var(--accent);text-decoration:none;font-size:12px;">{{ $row['campaign_name'] }}</a>
                            @else
                                <span style="color:var(--text3);">—</span>
                            @endif
                        </td>
                        <td style="font-size:11px;color:var(--text2);">
                            @if($row['campaign_id'])
                                {{ $row['period_start']->format('d/m/Y') }} → {{ $row['period_end']->format('d/m/Y') }}
                            @endif
                        </td>
                        <td style="text-align:right;font-size:12px;color:var(--text2);">
                            {{ number_format($row['rate'], 0, ',', ' ') }} × {{ rtrim(rtrim(number_format($row['surface'], 2), '0'), '.') }}m² × {{ $row['months'] }}m
                        </td>
                        <td style="text-align:right;font-weight:700;color:var(--accent);">
                            {{ number_format($row['amount'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" style="text-align:center;padding:40px;color:var(--text3);">
                            Aucune ligne pour cette période et ces filtres.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                @if($lines->isNotEmpty())
                <tfoot>
                    <tr style="background:var(--surface2);font-weight:800;">
                        <td colspan="9" style="text-align:right;padding:14px 16px;">TOTAL :</td>
                        <td style="text-align:right;color:var(--accent);font-size:15px;padding:14px 16px;">
                            {{ number_format($totals['total'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div style="font-size:11px;color:var(--text3);margin-top:10px;text-align:center;">
        💡 Chaque montant est justifiable : tarif appliqué × surface du panneau × nombre de mois.
        Les tarifs utilisent l'<strong>historique tarifaire</strong> de la commune (cohérence rétroactive).
    </div>

</x-admin-layout>

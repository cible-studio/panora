<x-admin-layout>
    <x-slot name="title">Taxes — Détail par panneau</x-slot>

    <x-slot:topbarLeft>
        <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm">← Retour aux taxes</a>
    </x-slot:topbarLeft>

    <x-slot name="topbarActions">
        <span class="badge badge-blue">{{ $lines->count() }} ligne(s)</span>
        @if($lines->isNotEmpty())
            @php
                // FIX 2026-06-26 — Propage period_end_value pour le mode personnalisé,
                // sinon les exports PDF/Excel retombent sur 1 mois au lieu de la plage saisie.
                $exportParams = array_merge(
                    ['year' => $year, 'period_type' => $periodType, 'period_value' => $periodValue],
                    $periodType === 'personnalise' ? ['period_end_value' => $periodEndValue ?? $periodValue] : [],
                    $filters
                );
            @endphp
            <a href="{{ route('admin.taxes.details.excel', $exportParams) }}"
               class="btn btn-ghost btn-sm">📊 Excel</a>
            <a href="{{ route('admin.taxes.details.pdf', $exportParams) }}"
               class="btn btn-primary btn-sm" target="_blank" rel="noopener">📄 PDF</a>
        @endif
    </x-slot>

    @php
        $typeLabels = ['tm' => 'TM', 'odp' => 'ODP'];
        $typeColors = [
            'tm'  => ['bg' => 'rgba(34,197,94,.1)',  'c' => '#16a34a'],
            'odp' => ['bg' => 'rgba(249,115,22,.1)', 'c' => '#ea580c'],
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
                        <option value="mensuel"      {{ $periodType === 'mensuel'      ? 'selected' : '' }}>Mensuel</option>
                        <option value="trimestriel"  {{ $periodType === 'trimestriel'  ? 'selected' : '' }}>Trimestriel</option>
                        <option value="annuel"       {{ $periodType === 'annuel'       ? 'selected' : '' }}>Annuel</option>
                        {{-- FIX 2026-06-26 — Période personnalisée (mois début → mois fin) --}}
                        <option value="personnalise" {{ $periodType === 'personnalise' ? 'selected' : '' }}>Personnalisée</option>
                    </select>
                </div>
                @if($periodType !== 'annuel')
                <div class="filter-group">
                    <label class="filter-label">
                        @if($periodType === 'mensuel') Mois
                        @elseif($periodType === 'trimestriel') Trimestre
                        @else Mois début @endif
                    </label>
                    <select name="period_value" class="filter-select" onchange="this.form.submit()">
                        @if($periodType === 'mensuel' || $periodType === 'personnalise')
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
                {{-- FIX 2026-06-26 — 2e sélecteur "Mois fin" visible uniquement en mode personnalisé --}}
                @if($periodType === 'personnalise')
                <div class="filter-group">
                    <label class="filter-label">Mois fin</label>
                    <select name="period_end_value" class="filter-select" onchange="this.form.submit()">
                        @foreach(range(1,12) as $m)
                            <option value="{{ $m }}"
                                    {{ ($periodEndValue ?? $periodValue) === $m ? 'selected' : '' }}
                                    {{ $m < $periodValue ? 'disabled' : '' }}>
                                {{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}
                            </option>
                        @endforeach
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
                {{-- FIX 2026-06-25 — Pills cliquables au lieu d'un select dropdown.
                     L'état actif est immédiatement visible (couleur de la taxe),
                     et le filtrage se fait en 1 clic. Cohérent avec le code couleur
                     du reste de l'app : ODP = bleu, TM = violet. --}}
                <div class="filter-group" style="flex:1;min-width:240px">
                    <label class="filter-label">Type taxe</label>
                    @php $activeType = $filters['type'] ?? ''; @endphp
                    <input type="hidden" name="type" id="tax-type-input" value="{{ $activeType }}">
                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <button type="button" onclick="document.getElementById('tax-type-input').value='';this.form.submit()"
                                style="padding:8px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;border:1.5px solid {{ $activeType === '' ? 'var(--accent)' : 'var(--border)' }};background:{{ $activeType === '' ? 'var(--accent)' : 'var(--surface)' }};color:{{ $activeType === '' ? '#fff' : 'var(--text2)' }};transition:transform .1s,box-shadow .15s;{{ $activeType === '' ? 'box-shadow:0 2px 8px rgba(232,160,32,.30)' : '' }}"
                                title="Voir toutes les taxes (ODP + TM)">
                            🔍 Toutes
                        </button>
                        <button type="button" onclick="document.getElementById('tax-type-input').value='odp';this.form.submit()"
                                style="padding:8px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;border:1.5px solid {{ $activeType === 'odp' ? '#3b82f6' : 'var(--border)' }};background:{{ $activeType === 'odp' ? '#3b82f6' : 'var(--surface)' }};color:{{ $activeType === 'odp' ? '#fff' : '#1d4ed8' }};transition:transform .1s,box-shadow .15s;{{ $activeType === 'odp' ? 'box-shadow:0 2px 8px rgba(59,130,246,.40)' : '' }}"
                                title="Filtrer sur la taxe ODP (Occupation Domaine Public) uniquement">
                            🏛️ ODP seul
                        </button>
                        <button type="button" onclick="document.getElementById('tax-type-input').value='tm';this.form.submit()"
                                style="padding:8px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;border:1.5px solid {{ $activeType === 'tm' ? '#a855f7' : 'var(--border)' }};background:{{ $activeType === 'tm' ? '#a855f7' : 'var(--surface)' }};color:{{ $activeType === 'tm' ? '#fff' : '#7c3aed' }};transition:transform .1s,box-shadow .15s;{{ $activeType === 'tm' ? 'box-shadow:0 2px 8px rgba(168,85,247,.40)' : '' }}"
                                title="Filtrer sur la taxe TM (Taxe Municipale publicité) uniquement">
                            🏢 TM seul
                        </button>
                    </div>
                </div>
                @if(!empty($filters))
                <div class="filter-group" style="display:flex;align-items:flex-end">
                    <a href="{{ route('admin.taxes.details') }}"
                       class="btn btn-ghost"
                       style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--surface);border:1px solid var(--border);border-radius:10px;font-weight:600;font-size:13px;text-decoration:none;color:var(--text2);height:38px;line-height:1"
                       title="Effacer tous les filtres et revenir à la vue par défaut">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        Réinitialiser les filtres
                    </a>
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
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <div class="card-title">📋 Détail par panneau</div>
            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                @if($lines->isNotEmpty())
                    <div style="font-size:12px;color:var(--text3);">
                        Période :
                        <strong>{{ $lines->first()['period_start']?->format('d/m/Y') }}
                        → {{ $lines->first()['period_end']?->format('d/m/Y') }}</strong>
                    </div>
                @endif
                @if($paginator->total() > 0)
                    {{-- Sélecteur "par page" qui poste sur GET avec les filtres préservés. --}}
                    <form method="GET" action="{{ route('admin.taxes.details') }}"
                          style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3)">
                        @foreach(request()->except(['per_page','page']) as $k => $v)
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endforeach
                        <label for="per-page-select" style="white-space:nowrap">Par page :</label>
                        <select name="per_page" id="per-page-select"
                                onchange="this.form.submit()"
                                style="height:30px;padding:0 10px;background:var(--surface);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text)">
                            @foreach([25, 50, 100, 200] as $opt)
                                <option value="{{ $opt }}" {{ $perPage === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
            </div>
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
                @forelse($paginator as $row)
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
                            {{-- FIX 2026-06-26 — Affiche les VRAIES dates de la
                                 campagne (campaign_start/end), pas la période du
                                 filtre. Pour les lignes ODP sans campagne, vide. --}}
                            @if($row['campaign_id'] && !empty($row['campaign_start']) && !empty($row['campaign_end']))
                                {{ $row['campaign_start']->format('d/m/Y') }} → {{ $row['campaign_end']->format('d/m/Y') }}
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
                        <td colspan="9" style="text-align:right;padding:14px 16px;">TOTAL <span style="font-weight:500;color:var(--text3);font-size:11px">({{ number_format($paginator->total(), 0, ',', ' ') }} ligne(s), toutes pages) :</span></td>
                        <td style="text-align:right;color:var(--accent);font-size:15px;padding:14px 16px;">
                            {{ number_format($totals['total'], 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>

        {{-- Pagination + récap pages — affichée seulement s'il y a + d'1 page --}}
        @if($paginator->hasPages())
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-top:1px solid var(--border);flex-wrap:wrap;gap:10px">
                <div style="font-size:12px;color:var(--text3)">
                    Affichage des lignes
                    <strong style="color:var(--text)">{{ $paginator->firstItem() }}</strong>
                    à
                    <strong style="color:var(--text)">{{ $paginator->lastItem() }}</strong>
                    sur
                    <strong style="color:var(--text)">{{ number_format($paginator->total(), 0, ',', ' ') }}</strong>
                </div>
                <div>
                    {{ $paginator->onEachSide(1)->links() }}
                </div>
            </div>
        @endif
    </div>

    <div style="font-size:11px;color:var(--text3);margin-top:10px;text-align:center;">
        💡 Chaque montant est justifiable : tarif appliqué × surface du panneau × nombre de mois.
        Les tarifs utilisent l'<strong>historique tarifaire</strong> de la commune (cohérence rétroactive).
    </div>

</x-admin-layout>

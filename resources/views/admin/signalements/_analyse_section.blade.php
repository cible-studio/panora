{{-- _analyse_section.blade.php — Section "Analyse" extraite de sla/index.blade.php
     pour la fusion admin/signalements/index ?view=analyse (2026-06-19).

     Variables consommées :
       $analyse = [
         'stats'       => DelayReasonsService::stats(),
         'from'        => Carbon,
         'to'          => Carbon,
         'filters'     => ['commune_id','client_id','zone'],
         'motifFilter' => DelayReason|null,
         'allCommunes' => Collection,
         'allClients'  => Collection,
       ]
       $formAction = route à utiliser pour le form GET des filtres (par défaut current)
--}}
@php
    $stats       = $analyse['stats'];
    $from        = $analyse['from'];
    $to          = $analyse['to'];
    $filters     = $analyse['filters'] ?? [];
    $motifFilter = $analyse['motifFilter'] ?? null;
    $allCommunes = $analyse['allCommunes'];
    $allClients  = $analyse['allClients'];
    $formAction  = $formAction ?? url()->current();
@endphp
<div class="sla-page">
    {{-- Hero --}}
    <div style="background:linear-gradient(135deg,rgba(239,68,68,.10),rgba(245,158,11,.06));border:1px solid var(--border);border-radius:16px;padding:22px 26px;margin-bottom:18px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="width:54px;height:54px;border-radius:14px;background:rgba(239,68,68,.20);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:26px;box-shadow:0 4px 12px rgba(239,68,68,.18)">⚠</div>
        <div style="flex:1;min-width:240px">
            <div style="font-size:18px;font-weight:800;color:var(--text);letter-spacing:-.2px">Analyse des signalements</div>
            <div style="font-size:12.5px;color:var(--text3);margin-top:4px;line-height:1.5">
                Analyse des motifs de retard signalés par les techniciens · {{ $from->format('d/m/Y') }} → {{ $to->format('d/m/Y') }}
                ({{ (int)$from->diffInDays($to) + 1 }} jours).
            </div>
        </div>
        <a href="{{ route('admin.sla.retards.export.pdf', array_filter(request()->only(['from', 'to', 'commune_id', 'client_id', 'zone', 'motif']))) }}"
           class="btn btn-ghost btn-sm" style="font-size:12.5px"
           title="Télécharger le PDF de l'analyse avec les filtres courants">📄 Exporter PDF</a>
    </div>

    {{-- Filtres --}}
    <form method="GET" action="{{ $formAction }}" class="sla-filter-card" style="margin-bottom:16px">
        <input type="hidden" name="view" value="analyse">
        <div class="sla-filter-bar">
            <div class="fne-field" style="min-width:140px">
                <label>Du</label>
                <input type="date" name="from" value="{{ $from->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:140px">
                <label>Au</label>
                <input type="date" name="to" value="{{ $to->format('Y-m-d') }}" onchange="this.form.submit()">
            </div>
            <div class="fne-field" style="min-width:180px">
                <label>Motif</label>
                <select name="motif" onchange="this.form.submit()">
                    <option value="">— Tous —</option>
                    @foreach(\App\Enums\DelayReason::cases() as $m)
                        <option value="{{ $m->value }}" {{ ($motifFilter?->value ?? '') === $m->value ? 'selected' : '' }}>{{ $m->icon() }} {{ $m->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="fne-field" style="min-width:120px">
                <label>Zone</label>
                <select name="zone" onchange="this.form.submit()">
                    <option value="">— Toutes —</option>
                    <option value="abidjan"   {{ ($filters['zone'] ?? '') === 'abidjan' ? 'selected' : '' }}>🏙 Abidjan</option>
                    <option value="interieur" {{ ($filters['zone'] ?? '') === 'interieur' ? 'selected' : '' }}>🌾 Intérieur</option>
                </select>
            </div>
            <div class="fne-field" style="min-width:180px">
                <label>Commune</label>
                <select name="commune_id" onchange="this.form.submit()">
                    <option value="">— Toutes —</option>
                    @foreach($allCommunes as $c)
                        <option value="{{ $c->id }}" {{ (int)($filters['commune_id'] ?? 0) === $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            @if($motifFilter || !empty($filters) || request()->has('from') || request()->has('to'))
                <a href="{{ $formAction }}?view=analyse" class="btn btn-ghost btn-sm" style="height:38px;display:inline-flex;align-items:center">↺ Réinitialiser</a>
            @endif
        </div>
    </form>

    {{-- KPI cards analytiques --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:18px">
        <div class="sla-kpi" style="border-left-color:var(--text3)">
            <div class="sla-kpi-label">Total signalements</div>
            <div class="sla-kpi-val">{{ $stats['kpi']['total_all'] }}</div>
            <div class="sla-kpi-sub">sur la période</div>
        </div>
        <div class="sla-kpi" style="border-left-color:#f59e0b">
            <div class="sla-kpi-label">⏳ En attente</div>
            <div class="sla-kpi-val" style="color:#b45309">{{ $stats['kpi']['total_open'] }}</div>
            <div class="sla-kpi-sub">non résolus</div>
        </div>
        <div class="sla-kpi" style="border-left-color:#16a34a">
            <div class="sla-kpi-label">✓ Résolus</div>
            <div class="sla-kpi-val" style="color:#15803d">{{ $stats['kpi']['total_resolved'] }}</div>
            <div class="sla-kpi-sub">maintenance ou dismissed</div>
        </div>
        <div class="sla-kpi" style="border-left-color:{{ $stats['kpi']['dominant_motif']?->color() ?? '#6b7280' }}">
            <div class="sla-kpi-label">🎯 Motif dominant</div>
            <div class="sla-kpi-val" style="font-size:18px;color:{{ $stats['kpi']['dominant_motif']?->color() ?? 'var(--text)' }}">
                {{ $stats['kpi']['dominant_motif']?->icon() ?? '—' }} {{ $stats['kpi']['dominant_motif']?->label() ?? 'Aucun' }}
            </div>
            <div class="sla-kpi-sub">{{ $stats['kpi']['dominant_count'] }} signalement(s) ouverts</div>
        </div>
        <div class="sla-kpi" style="border-left-color:#dc2626">
            <div class="sla-kpi-label">🔁 Panneaux récurrents</div>
            <div class="sla-kpi-val" style="color:#b91c1c">{{ $stats['kpi']['recurring_count'] }}</div>
            <div class="sla-kpi-sub">≥ 2 signalements même motif</div>
        </div>
    </div>

    {{-- Répartition par motif + Croisement Motif × Commune --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px">
        <div class="sla-card">
            <div class="sla-card-head">
                <div class="sla-card-title">🎯 Répartition par motif</div>
                <div class="sla-card-sub">Signalements en attente</div>
            </div>
            <div class="sla-card-body" style="padding:18px">
                @if($stats['by_motif_open']->isEmpty())
                    <div class="sla-empty">Aucun signalement en attente sur la période.</div>
                @else
                    @php $totalOpen = $stats['kpi']['total_open']; @endphp
                    <div style="display:grid;gap:8px">
                        @foreach($stats['by_motif_open'] as $row)
                            @php $pct = $totalOpen > 0 ? round(($row['count']/$totalOpen)*100, 1) : 0; @endphp
                            <div>
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                                    <span style="font-size:12.5px;color:var(--text2);font-weight:500">{{ $row['icon'] }} {{ $row['label'] }}</span>
                                    <span style="font-size:11.5px;color:var(--text3);font-weight:700">{{ $row['count'] }} ({{ $pct }} %)</span>
                                </div>
                                <div style="height:6px;background:var(--surface2);border-radius:4px;overflow:hidden">
                                    <div style="height:100%;width:{{ $pct }}%;background:{{ $row['motif']->color() }};border-radius:4px"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <div class="sla-card">
            <div class="sla-card-head">
                <div class="sla-card-title">🗺 Motif × Commune</div>
                <div class="sla-card-sub">Croisement géo · top 10</div>
            </div>
            <div class="sla-card-body sla-card-body--flush">
                @if($stats['cross_commune']->isEmpty())
                    <div class="sla-empty" style="padding:24px">Aucun croisement à afficher.</div>
                @else
                    <table class="sla-table">
                        <thead>
                            <tr>
                                <th>Motif</th>
                                <th>Commune</th>
                                <th style="text-align:right">Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($stats['cross_commune']->take(10) as $row)
                                <tr>
                                    <td>
                                        <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $row['motif']->bg() }};color:{{ $row['motif']->color() }}">
                                            {{ $row['motif_icon'] }} {{ $row['motif_label'] }}
                                        </span>
                                    </td>
                                    <td style="color:var(--text2)">{{ $row['commune_name'] }} <span style="font-size:10px;color:var(--text3)">({{ $row['commune_city'] }})</span></td>
                                    <td style="text-align:right;font-weight:700;color:var(--text)">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    {{-- Panneaux récurrents --}}
    @if($stats['recurring']->isNotEmpty())
        <div class="sla-card" style="margin-bottom:18px">
            <div class="sla-card-head">
                <div class="sla-card-title">🔁 Panneaux récurrents</div>
                <div class="sla-card-sub">{{ $stats['recurring']->count() }} panneau(x) avec ≥ 2 signalements du MÊME motif sur la période</div>
            </div>
            <div class="sla-card-body sla-card-body--flush">
                <table class="sla-table">
                    <thead>
                        <tr>
                            <th>Panneau</th>
                            <th>Commune</th>
                            <th>Motif récurrent</th>
                            <th style="text-align:right">Occurrences</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stats['recurring'] as $row)
                            <tr>
                                <td style="font-family:monospace;font-weight:700;color:var(--accent)">{{ $row['panel_reference'] }}</td>
                                <td style="color:var(--text2)">{{ $row['commune_name'] ?? '—' }}</td>
                                <td>
                                    <span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:{{ $row['motif']->bg() }};color:{{ $row['motif']->color() }}">
                                        {{ $row['motif_icon'] }} {{ $row['motif_label'] }}
                                    </span>
                                </td>
                                <td style="text-align:right;font-weight:800;color:#b91c1c">×{{ $row['count'] }}</td>
                                <td style="text-align:right">
                                    <a href="{{ route('admin.panels.show', $row['panel_id']) }}" style="color:var(--accent);text-decoration:none;font-size:12px">Ouvrir →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

<style>
.sla-page select, .sla-page input[type="date"] {
    height: 38px; width: 100%; padding: 0 10px;
    background: var(--surface2); border: 1px solid var(--border);
    border-radius: 8px; color: var(--text); font-size: 13px;
    font-family: inherit; outline: none; box-sizing: border-box;
}
.sla-page select { padding-right: 28px; cursor: pointer; -webkit-appearance:none; appearance:none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat; background-position: right 8px center;
}
.sla-filter-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 14px 18px; }
.sla-filter-bar { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; }
.sla-page label { display:block; font-size:10px; text-transform:uppercase; font-weight:700; color:var(--text3); margin-bottom:4px; }
.sla-kpi { background: var(--surface); border: 1px solid var(--border); border-left: 4px solid; border-radius: 14px; padding: 14px 18px; }
.sla-kpi-label { font-size: 10.5px; font-weight: 800; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; }
.sla-kpi-val { font-size: 26px; font-weight: 800; color: var(--text); margin-top: 2px; }
.sla-kpi-sub { font-size: 11px; color: var(--text3); margin-top: 2px; }
.sla-card { background: var(--surface); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; }
.sla-card-head { padding: 14px 18px; border-bottom: 1px solid var(--border); background: var(--surface2); display:flex; align-items:center; justify-content:space-between; gap:12px; }
.sla-card-title { font-size: 14px; font-weight: 800; color: var(--text); }
.sla-card-sub { font-size: 11.5px; color: var(--text3); margin-top: 3px; }
.sla-card-body { padding: 16px 18px; }
.sla-card-body--flush { padding: 0; }
.sla-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sla-table th { text-align: left; padding: 10px 14px; background: var(--surface2); font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: var(--text3); border-bottom: 1px solid var(--border); }
.sla-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: top; }
.sla-table tr:hover td { background: rgba(232, 160, 32, .04); }
.sla-empty { text-align: center; color: var(--text3); font-size: 13px; font-style: italic; padding: 20px; }
</style>

<x-admin-layout title="Rapport Taxes Communales">

{{-- 2026-06-25 — Bouton Retour vers le dashboard Taxes (convention Panora :
     topbarLeft pour la navigation arrière, topbarActions pour les actions). --}}
<x-slot:topbarLeft>
    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm"
       style="display:inline-flex;align-items:center;gap:6px"
       title="Retour au dashboard Taxes communales">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux taxes
    </a>
</x-slot:topbarLeft>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm">📋 Liste taxes</a>
    <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm">📊 Rapports</a>
</x-slot>

@php
    $months = $months ?? ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    $fmt = fn($n) => number_format((float) ($n ?? 0), 0, ',', ' ');
@endphp

{{-- ════ FILTRES (année + commune + client + campagne) ════ --}}
<form method="GET" action="{{ route('admin.rapports.taxes') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);">
        🏛️ Rapport taxes
    </span>
    @once
    <style>
    /* 2026-06-18 — selects rapports/taxes : chevron + focus ring + hover. */
    .rpt-tax-select {
        height: 38px;
        padding: 0 34px 0 12px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 9px;
        font-size: 13px;
        color: var(--text);
        font-family: inherit;
        cursor: pointer;
        outline: none;
        transition: border-color .12s, box-shadow .12s, background .12s;
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
        background-repeat: no-repeat;
        background-position: right 12px center;
        background-size: 12px;
    }
    .rpt-tax-select:hover { border-color: var(--accent, #e8a020); background-color: var(--surface2); }
    .rpt-tax-select:focus { border-color: var(--accent, #e8a020); box-shadow: 0 0 0 3px rgba(232,160,32,.18); }
    </style>
    @endonce
    {{-- 2026-06-18 : selects harmonisés avec rapports/index — chevron, focus
         ring, hover. --}}
    <select name="annee" onchange="this.form.submit()" class="rpt-tax-select">
        @foreach($anneesDisponibles as $a)
            <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
        @endforeach
    </select>
    <select name="commune_id" onchange="this.form.submit()" class="rpt-tax-select" style="min-width:170px">
        <option value="">Toutes communes</option>
        @foreach($communes as $c)
            <option value="{{ $c->id }}" {{ ($filters['commune_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="client_id" onchange="this.form.submit()" class="rpt-tax-select" style="min-width:180px">
        <option value="">Tous clients</option>
        @foreach($clients as $c)
            <option value="{{ $c->id }}" {{ ($filters['client_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    <select name="campaign_id" onchange="this.form.submit()" class="rpt-tax-select" style="min-width:180px">
        <option value="">Toutes campagnes</option>
        @foreach($campaigns as $c)
            <option value="{{ $c->id }}" {{ ($filters['campaign_id'] ?? null) == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
    </select>
    @if(!empty($filters))
        <a href="{{ route('admin.rapports.taxes', ['annee' => $year]) }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:9px 16px;background:var(--surface);border:1px solid var(--border);border-radius:10px;font-weight:600;font-size:13px;text-decoration:none;color:var(--text2);height:38px;line-height:1"
           title="Effacer tous les filtres et revenir à la vue par défaut">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            Réinitialiser les filtres
        </a>
    @endif
    <span style="margin-left:auto;font-size:11px;color:var(--text3);">
        {{ $totals['communes'] ?? 0 }} commune(s) · {{ $totals['panel_max'] ?? 0 }} panneaux max simultanés
    </span>
</form>

{{-- ════ KPI ANNUEL ════ --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:18px;">
    @php
        $kpis = [
            ['lbl'=>'Total annuel',   'val'=>$totals['total'], 'col'=>'#e8a020', 'sub'=>'ODP + TM cumulés'],
            ['lbl'=>'ODP annuel',     'val'=>$totals['odp'],   'col'=>'#3b82f6', 'sub'=>'Occupation domaine public'],
            ['lbl'=>'TM annuelle',    'val'=>$totals['tm'],    'col'=>'#a855f7', 'sub'=>'Taxe municipale'],
            ['lbl'=>'Q1 (Jan-Mars)',  'val'=>$totals['q1'],    'col'=>'#22c55e', 'sub'=>'1er trimestre'],
            ['lbl'=>'Q2 (Avr-Juin)',  'val'=>$totals['q2'],    'col'=>'#22c55e', 'sub'=>'2e trimestre'],
            ['lbl'=>'Q3 (Jul-Sep)',   'val'=>$totals['q3'],    'col'=>'#22c55e', 'sub'=>'3e trimestre'],
            ['lbl'=>'Q4 (Oct-Déc)',   'val'=>$totals['q4'],    'col'=>'#22c55e', 'sub'=>'4e trimestre'],
        ];
    @endphp
    @foreach($kpis as $k)
    <div style="background:var(--surface);border:1px solid var(--border);border-left:4px solid {{ $k['col'] }};border-radius:12px;padding:14px 16px;">
        <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1.4px;margin-bottom:5px;">{{ $k['lbl'] }}</div>
        <div style="font-size:18px;font-weight:800;color:{{ $k['col'] }};font-variant-numeric:tabular-nums;">{{ $fmt($k['val']) }} <span style="font-size:11px;color:var(--text3);font-weight:400;">FCFA</span></div>
        <div style="font-size:10px;color:var(--text3);margin-top:3px;">{{ $k['sub'] }}</div>
    </div>
    @endforeach
</div>

{{-- ════ TRIMESTRES (graph barres) ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;">
    <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:14px;">📊 Répartition trimestrielle</div>
    @php $maxQ = max(1, $totals['q1'], $totals['q2'], $totals['q3'], $totals['q4']); @endphp
    <div style="display:flex;align-items:flex-end;gap:14px;height:160px;">
        @foreach(['q1'=>'Q1','q2'=>'Q2','q3'=>'Q3','q4'=>'Q4'] as $key => $label)
        @php $pct = $totals[$key] > 0 ? round(($totals[$key] / $maxQ) * 100) : 0; @endphp
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:6px;height:100%;">
            <div style="font-size:11px;font-weight:700;color:var(--accent);font-variant-numeric:tabular-nums;">{{ $fmt($totals[$key]) }}</div>
            <div style="width:80%;height:{{ $pct }}%;min-height:2px;background:linear-gradient(180deg,#e8a020,#f97316);border-radius:6px 6px 0 0;transition:height .6s ease;"></div>
            <div style="font-size:11px;color:var(--text3);font-weight:600;">{{ $label }}</div>
        </div>
        @endforeach
    </div>
</div>

{{-- ════ MATRICE MENSUELLE × COMMUNE ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:18px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:13px;font-weight:700;color:var(--text);">🗓️ Matrice mensuelle par commune</span>
        <span style="font-size:11px;color:var(--text3);">Total annuel = somme des 12 mois</span>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:11.5px;min-width:900px;">
            <thead>
                <tr>
                    <th style="position:sticky;left:0;background:var(--surface2);padding:8px 12px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);min-width:130px;">Commune</th>
                    @foreach($months as $i => $m)
                    <th style="padding:8px 6px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.7px;border-bottom:1px solid var(--border);">{{ $m }}</th>
                    @endforeach
                    <th style="padding:8px 12px;text-align:right;font-size:9px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border);background:rgba(232,160,32,.06);">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matrix as $row)
                <tr style="transition:background .1s;" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                    <td style="position:sticky;left:0;background:inherit;padding:8px 12px;font-weight:600;color:var(--text);">
                        <a href="{{ route('admin.rapports.communes.detail', $row['commune_id']) }}?annee={{ $year }}"
                           style="color:var(--text);text-decoration:none;"
                           title="Détail commune">
                            {{ $row['commune'] }}
                        </a>
                        <span style="font-size:10px;color:var(--text3);font-weight:400;display:block;">
                            max {{ $row['panel_max'] }} panneau{{ $row['panel_max'] > 1 ? 'x' : '' }}
                        </span>
                    </td>
                    @for($m = 1; $m <= 12; $m++)
                    @php $val = $row['cells'][$m] ?? 0; @endphp
                    <td style="padding:8px 6px;text-align:right;font-variant-numeric:tabular-nums;color:{{ $val > 0 ? 'var(--text2)' : 'var(--text3)' }};{{ $val > 0 ? 'background:rgba(232,160,32,.04);' : '' }}">
                        {{ $val > 0 ? $fmt($val) : '—' }}
                    </td>
                    @endfor
                    <td style="padding:8px 12px;text-align:right;font-weight:700;color:var(--accent);background:rgba(232,160,32,.06);font-variant-numeric:tabular-nums;">
                        {{ $fmt($row['total']) }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="14" style="text-align:center;padding:40px;color:var(--text3);">
                    Aucune campagne active sur {{ $year }} — rien à taxer.
                </td></tr>
                @endforelse
            </tbody>
            @if($matrix->isNotEmpty())
            <tfoot>
                <tr style="background:#0f172a;">
                    <td style="position:sticky;left:0;background:#0f172a;padding:10px 12px;font-weight:800;color:#e8a020;text-transform:uppercase;letter-spacing:1px;font-size:10px;">Total mois</td>
                    @for($m = 1; $m <= 12; $m++)
                    @php $monthTotal = $matrix->sum(fn($r) => $r['cells'][$m] ?? 0); @endphp
                    <td style="padding:10px 6px;text-align:right;font-weight:700;color:#e8a020;font-variant-numeric:tabular-nums;font-size:11px;">{{ $monthTotal > 0 ? $fmt($monthTotal) : '—' }}</td>
                    @endfor
                    <td style="padding:10px 12px;text-align:right;font-weight:800;color:#e8a020;background:rgba(232,160,32,.15);font-variant-numeric:tabular-nums;font-size:13px;">{{ $fmt($totals['total']) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ════ SYNTHÈSE ANNUELLE PAR COMMUNE (ODP / TM / Q1-Q4) ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
        <span style="font-size:13px;font-weight:700;color:var(--text);">📑 Synthèse annuelle par commune</span>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--surface2);">
                    <th style="padding:10px 14px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Commune</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">ODP</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">TM</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Q1</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Q2</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Q3</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Q4</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--accent);text-transform:uppercase;letter-spacing:1px;">Total annuel</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matrix as $row)
                <tr style="border-bottom:1px solid var(--border);" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                    <td style="padding:10px 14px;font-weight:600;color:var(--text);">
                        <a href="{{ route('admin.rapports.communes.detail', $row['commune_id']) }}?annee={{ $year }}" style="color:var(--text);text-decoration:none;">
                            {{ $row['commune'] }}
                        </a>
                    </td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#3b82f6;">{{ $fmt($row['odp']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;">{{ $fmt($row['tm']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['q1']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['q2']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['q3']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['q4']) }}</td>
                    <td style="padding:10px 14px;text-align:right;font-weight:800;color:var(--accent);font-variant-numeric:tabular-nums;">{{ $fmt($row['total']) }}</td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;padding:36px;color:var(--text3);">Aucune donnée.</td></tr>
                @endforelse
            </tbody>
            @if($matrix->isNotEmpty())
            <tfoot>
                <tr style="background:#0f172a;">
                    <td style="padding:12px 14px;font-weight:800;color:#e8a020;text-transform:uppercase;letter-spacing:1px;font-size:11px;">Total</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['odp']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['tm']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['q1']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['q2']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['q3']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($totals['q4']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:800;font-variant-numeric:tabular-nums;background:rgba(232,160,32,.15);font-size:13px;">{{ $fmt($totals['total']) }}</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- ════ ÉVOLUTION MULTI-ANNÉES (Chart.js) ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-top:18px;">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        <span style="font-size:13px;font-weight:700;color:var(--text)">📈 Évolution annuelle — 5 dernières années</span>
        <span style="margin-left:auto;font-size:11px;color:var(--text3);font-style:italic">ODP + TM facturable aux clients vs effectivement reversé aux communes</span>
    </div>
    <div style="position:relative;width:100%;height:280px">
        <canvas id="chart-yearly-tax-evolution" role="img" aria-label="Évolution annuelle taxes"></canvas>
    </div>
</div>

{{-- ════ COMPARAISON DÛ vs REVERSÉ (COMMIT C) ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-top:18px;">
    <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:13px;font-weight:700;color:var(--text);">⚖️ Taxes facturables clients vs reversées aux communes — {{ $year }}</span>
        <a href="{{ route('admin.taxes.index') }}" style="font-size:11px;color:var(--accent);text-decoration:none;font-weight:600;">Gérer les paiements →</a>
    </div>

    {{-- KPIs comparaison --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:0;border-bottom:1px solid var(--border);">
        <div style="padding:16px 20px;border-right:1px solid var(--border);" title="Montant théorique facturable aux clients pour couvrir les taxes communales — base : campagnes actives × tarifs mairie">
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Taxes facturables clients</div>
            <div style="font-size:18px;font-weight:800;color:#a855f7;font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['due']) }} <span style="font-size:11px;color:var(--text3);font-weight:400;">FCFA</span></div>
            <div style="font-size:10px;color:var(--text3);margin-top:3px;">ODP + TM dus pour {{ $year }}</div>
        </div>
        <div style="padding:16px 20px;border-right:1px solid var(--border);" title="Montant effectivement payé aux mairies — table taxes.paid_at">
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Reversé aux communes</div>
            <div style="font-size:18px;font-weight:800;color:#16a34a;font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['paid']) }} <span style="font-size:11px;color:var(--text3);font-weight:400;">FCFA</span></div>
            <div style="font-size:10px;color:#16a34a;margin-top:3px;font-weight:600;">{{ $comparisonTotals['rate'] }}% complétés</div>
        </div>
        <div style="padding:16px 20px;border-right:1px solid var(--border);" title="Différence = ce qu'il reste à payer aux mairies (en plus = à régler, en moins = sur-versement)">
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Solde restant</div>
            <div style="font-size:18px;font-weight:800;color:{{ $comparisonTotals['balance'] > 0 ? '#dc2626' : '#16a34a' }};font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['balance']) }} <span style="font-size:11px;color:var(--text3);font-weight:400;">FCFA</span></div>
            <div style="font-size:10px;color:var(--text3);margin-top:3px;">{{ $comparisonTotals['balance'] > 0 ? 'À régler aux mairies' : 'Tout est à jour' }}</div>
        </div>
        <div style="padding:16px 20px;">
            <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">État communes</div>
            <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap;">
                <span style="font-size:14px;font-weight:700;color:#16a34a;">{{ $comparisonTotals['paid_communes'] }}</span><span style="font-size:10px;color:var(--text3);">à jour</span>
                <span style="font-size:14px;font-weight:700;color:#f59e0b;">{{ $comparisonTotals['partial_communes'] }}</span><span style="font-size:10px;color:var(--text3);">partielles</span>
                <span style="font-size:14px;font-weight:700;color:#dc2626;">{{ $comparisonTotals['pending_communes'] }}</span><span style="font-size:10px;color:var(--text3);">en attente</span>
            </div>
        </div>
    </div>

    {{-- Tableau détaillé --}}
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:var(--surface2);">
                    <th style="padding:10px 14px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Commune</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;" title="Montant facturable aux clients pour couvrir la taxe">Facturable client</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">ODP reversé</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">TM reversé</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Total reversé</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">% versé</th>
                    <th style="padding:10px 14px;text-align:right;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Solde</th>
                    <th style="padding:10px 14px;text-align:left;font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:1px;">Statut</th>
                </tr>
            </thead>
            <tbody>
                @forelse($comparison as $row)
                    @php
                        $statusColors = [
                            'paid'    => ['#16a34a', 'rgba(34,197,94,.12)',  '✓ À jour'],
                            'partial' => ['#f59e0b', 'rgba(245,158,11,.12)', '◐ Partiel'],
                            'pending' => ['#dc2626', 'rgba(220,38,38,.12)',  '⚠ En attente'],
                        ];
                        [$color, $bg, $label] = $statusColors[$row['status']] ?? $statusColors['pending'];
                    @endphp
                    <tr style="border-bottom:1px solid var(--border);" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 14px;font-weight:600;color:var(--text);">{{ $row['commune'] }}</td>
                        <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:#a855f7;">{{ $fmt($row['due_total']) }}</td>
                        <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['paid_odp']) }}</td>
                        <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;color:var(--text3);">{{ $fmt($row['paid_tm']) }}</td>
                        <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:600;color:#16a34a;">{{ $fmt($row['paid_total']) }}</td>
                        <td style="padding:10px 14px;text-align:right;">
                            <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;">
                                <div style="width:50px;height:5px;background:var(--border);border-radius:3px;overflow:hidden;">
                                    <div style="height:100%;width:{{ min($row['rate'], 100) }}%;background:{{ $color }};"></div>
                                </div>
                                <span style="font-size:11px;font-weight:700;color:{{ $color }};min-width:36px;text-align:right;">{{ $row['rate'] }}%</span>
                            </div>
                        </td>
                        <td style="padding:10px 14px;text-align:right;font-variant-numeric:tabular-nums;font-weight:700;color:{{ $row['balance'] > 0 ? '#dc2626' : '#16a34a' }};">{{ $fmt($row['balance']) }}</td>
                        <td style="padding:10px 14px;">
                            <span style="padding:3px 10px;border-radius:12px;background:{{ $bg }};color:{{ $color }};font-size:10px;font-weight:700;white-space:nowrap;">{{ $label }}</span>
                            @if($row['last_paid_at'])
                                <div style="font-size:9px;color:var(--text3);margin-top:2px;">Dernier paiement : {{ \Carbon\Carbon::parse($row['last_paid_at'])->format('d/m/Y') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" style="text-align:center;padding:36px;color:var(--text3);">Aucune commune avec des taxes à reverser.</td></tr>
                @endforelse
            </tbody>
            @if($comparison->isNotEmpty())
            <tfoot>
                <tr style="background:#0f172a;">
                    <td style="padding:12px 14px;font-weight:800;color:#e8a020;text-transform:uppercase;letter-spacing:1px;font-size:11px;">TOTAL</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['due']) }}</td>
                    <td colspan="2"></td>
                    <td style="padding:12px 14px;text-align:right;color:#16a34a;font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['paid']) }}</td>
                    <td style="padding:12px 14px;text-align:right;color:#e8a020;font-weight:700;">{{ $comparisonTotals['rate'] }}%</td>
                    <td style="padding:12px 14px;text-align:right;color:{{ $comparisonTotals['balance'] > 0 ? '#dc2626' : '#16a34a' }};font-weight:700;font-variant-numeric:tabular-nums;">{{ $fmt($comparisonTotals['balance']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
</div>

<div style="font-size:11px;color:var(--text3);margin-top:14px;line-height:1.6;padding:10px 14px;background:var(--surface2);border-radius:8px">
    <strong>📐 Méthode de calcul :</strong> tarif annuel mairie ÷ 12 × nombre de panneaux distincts occupés au moins 1 jour
    dans le mois (campagnes statut planifié / actif / pose / terminé). Les campagnes annulées sont exclues.
    <br><strong>💰 Facturable client :</strong> montant que vous devez théoriquement facturer aux clients pour couvrir la taxe communale due. Inclut ODP (occupation domaine public) + TM (taxe municipale).
    <br><strong>🏛️ Reversé aux mairies :</strong> montants effectivement payés (table <code>taxes</code> où <code>paid_at</code> est renseigné). Gérez les paiements depuis la liste taxes.
    <br><strong>⚖️ Solde :</strong> positif = à régler aux mairies, négatif = sur-versement.
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const yearlyData = {!! json_encode($yearlyTrend ?? collect()) !!};
    const canvas = document.getElementById('chart-yearly-tax-evolution');
    if (!canvas || !yearlyData.length || typeof Chart === 'undefined') return;
    const isDark = matchMedia('(prefers-color-scheme:dark)').matches;
    const gridC = isDark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
    const tickC = isDark ? 'rgba(255,255,255,.55)' : 'rgba(0,0,0,.5)';
    new Chart(canvas, {
        data: {
            labels: yearlyData.map(y => y.year),
            datasets: [
                {
                    type: 'bar',
                    label: 'ODP facturable',
                    data: yearlyData.map(y => y.odp),
                    backgroundColor: 'rgba(59,130,246,.7)',
                    borderRadius: 5,
                    stack: 'due',
                },
                {
                    type: 'bar',
                    label: 'TM facturable',
                    data: yearlyData.map(y => y.tm),
                    backgroundColor: 'rgba(168,85,247,.7)',
                    borderRadius: 5,
                    stack: 'due',
                },
                {
                    type: 'bar',
                    label: 'Reversé aux communes',
                    data: yearlyData.map(y => y.paid_total),
                    backgroundColor: 'rgba(22,163,74,.85)',
                    borderRadius: 5,
                    stack: 'paid',
                },
                {
                    type: 'line',
                    label: '% reversé',
                    data: yearlyData.map(y => y.total > 0 ? (y.paid_total / y.total) * 100 : 0),
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,.15)',
                    borderWidth: 2.5,
                    tension: 0.3,
                    pointBackgroundColor: '#f59e0b',
                    pointRadius: 4,
                    yAxisID: 'y1',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { color: tickC, font: { size: 11 }, boxWidth: 10, padding: 10 } },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const v = ctx.parsed.y;
                            if (ctx.dataset.label.includes('%')) {
                                return ` ${ctx.dataset.label} : ${v.toFixed(1)}%`;
                            }
                            return ` ${ctx.dataset.label} : ${new Intl.NumberFormat('fr-FR').format(Math.round(v))} FCFA`;
                        },
                    },
                },
            },
            scales: {
                x: { stacked: true, ticks: { color: tickC, font: { size: 12, weight: 'bold' } }, grid: { display: false } },
                y: {
                    stacked: true, beginAtZero: true,
                    ticks: { color: tickC, font: { size: 11 }, callback: v => v >= 1e6 ? (v / 1e6).toFixed(1) + 'M' : (v >= 1e3 ? (v / 1e3).toFixed(0) + 'K' : v) },
                    grid: { color: gridC },
                    title: { display: true, text: 'FCFA', color: tickC, font: { size: 10 } },
                },
                y1: {
                    beginAtZero: true, max: 100, position: 'right',
                    ticks: { color: tickC, font: { size: 11 }, callback: v => v + '%' },
                    grid: { display: false },
                    title: { display: true, text: '% reversé', color: tickC, font: { size: 10 } },
                },
            },
        },
    });
});
</script>
@endpush

</x-admin-layout>

<x-admin-layout title="Rapport Taxes Communales">

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost btn-sm">📋 Liste taxes</a>
    <a href="{{ route('admin.rapports.index') }}" class="btn btn-ghost btn-sm">📊 Rapports</a>
</x-slot>

@php
    $months = $months ?? ['Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
    $fmt = fn($n) => number_format((float) ($n ?? 0), 0, ',', ' ');
@endphp

{{-- ════ FILTRES ANNÉE ════ --}}
<form method="GET" action="{{ route('admin.rapports.taxes') }}"
      style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);">
        🏛️ Taxes communales
    </span>
    <select name="annee" onchange="this.form.submit()"
            style="height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:9px;font-size:13px;color:var(--text);">
        @foreach($anneesDisponibles as $a)
            <option value="{{ $a }}" {{ $a == $year ? 'selected' : '' }}>{{ $a }}</option>
        @endforeach
    </select>
    <span style="margin-left:auto;font-size:11px;color:var(--text3);">
        Calculé depuis les campagnes actives — {{ $totals['communes'] }} commune(s) · {{ $totals['panel_max'] }} panneaux max simultanés
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

<div style="font-size:11px;color:var(--text3);margin-top:14px;line-height:1.6;">
    <strong>Méthode de calcul :</strong> tarif annuel mairie ÷ 12 × nombre de panneaux distincts occupés au moins 1 jour
    dans le mois (campagnes statut planifié / actif / pose / terminé). Les campagnes annulées sont exclues.
    Cliquez une commune pour voir le détail des panneaux et campagnes contributifs.
</div>

</x-admin-layout>

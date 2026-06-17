<div id="panel-sla" class="rpt-panel" style="display:none">
    @php
        // delayStats vient de buildReportData() — null si user non admin/MP.
        $sla = $delayStats ?? null;
    @endphp

    @if(!$sla)
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:60px;text-align:center;color:var(--text3);font-style:italic">
            Onglet réservé aux administrateurs et media planners.
        </div>
    @else
        {{-- 4 KPI cards --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px;margin-bottom:16px">
            <div style="background:var(--surface);border:1px solid var(--border);border-left:4px solid var(--text3);border-radius:14px;padding:14px 18px">
                <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Total signalements</div>
                <div style="font-size:26px;font-weight:800;color:var(--text)">{{ $sla['kpi']['total_all'] }}</div>
                <div style="font-size:11px;color:var(--text3)">sur la période filtrée</div>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-left:4px solid #f59e0b;border-radius:14px;padding:14px 18px">
                <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">⏳ En attente</div>
                <div style="font-size:26px;font-weight:800;color:#b45309">{{ $sla['kpi']['total_open'] }}</div>
                <div style="font-size:11px;color:var(--text3)">non résolus</div>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-left:4px solid {{ $sla['kpi']['dominant_motif']?->color() ?? '#6b7280' }};border-radius:14px;padding:14px 18px">
                <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">🎯 Motif dominant</div>
                <div style="font-size:16px;font-weight:800;color:{{ $sla['kpi']['dominant_motif']?->color() ?? 'var(--text)' }};margin-top:4px">{{ $sla['kpi']['dominant_motif']?->icon() ?? '—' }} {{ $sla['kpi']['dominant_motif']?->label() ?? 'Aucun' }}</div>
                <div style="font-size:11px;color:var(--text3)">{{ $sla['kpi']['dominant_count'] }} signalement(s)</div>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-left:4px solid #dc2626;border-radius:14px;padding:14px 18px">
                <div style="font-size:10.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">🔁 Panneaux récurrents</div>
                <div style="font-size:26px;font-weight:800;color:#b91c1c">{{ $sla['kpi']['recurring_count'] }}</div>
                <div style="font-size:11px;color:var(--text3)">≥ 2 signalements même motif</div>
            </div>
        </div>

        {{-- Doughnut motifs + Heatmap commune --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 18px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l8 4"/></svg>
                    <span style="font-size:13px;font-weight:700;color:var(--text)">Répartition par motif</span>
                </div>
                <div style="font-size:11px;color:var(--text3);font-style:italic;margin-bottom:10px">ℹ️ Signalements en attente · suit les filtres dimensionnels</div>
                <div style="position:relative;width:100%;height:240px">
                    <canvas id="chart-sla-motifs" role="img" aria-label="Répartition motifs SLA"></canvas>
                </div>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
                <div style="padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface2)">
                    <div style="font-size:13px;font-weight:700;color:var(--text)">🗺 Motif × Commune</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px">Top 10 croisements</div>
                </div>
                <div style="max-height:330px;overflow-y:auto">
                    @if($sla['cross_commune']->isEmpty())
                        <div style="padding:30px;text-align:center;color:var(--text3);font-style:italic;font-size:13px">Aucun croisement à afficher.</div>
                    @else
                        <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                            <thead>
                                <tr style="background:var(--surface2)">
                                    <th style="text-align:left;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">Motif</th>
                                    <th style="text-align:left;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">Commune</th>
                                    <th style="text-align:right;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">×</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($sla['cross_commune']->take(10) as $row)
                                    <tr>
                                        <td style="padding:9px 14px;border-bottom:1px solid var(--border)">
                                            <span style="padding:2px 7px;border-radius:999px;font-size:10.5px;font-weight:700;background:{{ $row['motif']->bg() }};color:{{ $row['motif']->color() }}">{{ $row['motif_icon'] }} {{ \Illuminate\Support\Str::limit($row['motif_label'], 25) }}</span>
                                        </td>
                                        <td style="padding:9px 14px;border-bottom:1px solid var(--border);color:var(--text2)">{{ $row['commune_name'] }}</td>
                                        <td style="padding:9px 14px;border-bottom:1px solid var(--border);text-align:right;font-weight:700">{{ $row['count'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>

        {{-- Panneaux récurrents --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:14px 18px;border-bottom:1px solid var(--border);background:var(--surface2);display:flex;justify-content:space-between;align-items:center;gap:12px">
                <div>
                    <div style="font-size:13px;font-weight:700;color:var(--text)">🔁 Panneaux récurrents — drill-down</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px">{{ $sla['recurring']->count() }} panneau(x) avec ≥ 2 signalements du MÊME motif sur la période</div>
                </div>
                <a href="{{ route('admin.sla.retards.index') }}" class="btn btn-ghost btn-sm" style="font-size:11px">📋 Page complète →</a>
            </div>
            @if($sla['recurring']->isEmpty())
                <div style="padding:40px;text-align:center;color:var(--text3);font-style:italic;font-size:13px">Aucun panneau récurrent sur la période 🎉</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12.5px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="text-align:left;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">Panneau</th>
                                <th style="text-align:left;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">Commune</th>
                                <th style="text-align:left;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">Motif</th>
                                <th style="text-align:right;padding:9px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);border-bottom:1px solid var(--border)">×</th>
                                <th style="padding:9px 14px;border-bottom:1px solid var(--border)"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sla['recurring']->take(15) as $row)
                                <tr>
                                    <td style="padding:9px 14px;border-bottom:1px solid var(--border);font-family:monospace;font-weight:700;color:var(--accent)">{{ $row['panel_reference'] }}</td>
                                    <td style="padding:9px 14px;border-bottom:1px solid var(--border);color:var(--text2)">{{ $row['commune_name'] ?? '—' }}</td>
                                    <td style="padding:9px 14px;border-bottom:1px solid var(--border)">
                                        <span style="padding:2px 7px;border-radius:999px;font-size:10.5px;font-weight:700;background:{{ $row['motif']->bg() }};color:{{ $row['motif']->color() }}">{{ $row['motif_icon'] }} {{ $row['motif_label'] }}</span>
                                    </td>
                                    <td style="padding:9px 14px;border-bottom:1px solid var(--border);text-align:right;font-weight:800;color:#b91c1c">×{{ $row['count'] }}</td>
                                    <td style="padding:9px 14px;border-bottom:1px solid var(--border);text-align:right">
                                        <a href="{{ route('admin.panels.show', $row['panel_id']) }}" style="color:var(--accent);text-decoration:none;font-size:11.5px">Ouvrir →</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</div>

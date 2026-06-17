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
                    <tr data-commune-id="{{ $row['id'] }}"
                        onclick="CommuneDrilldown.open({{ $row['id'] }})"
                        title="Cliquer pour voir le détail de la commune"
                        style="border-bottom:1px solid var(--border);transition:background .1s;cursor:pointer"
                        onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">
                            {{ $row['commune'] }}
                            <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                        </td>
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
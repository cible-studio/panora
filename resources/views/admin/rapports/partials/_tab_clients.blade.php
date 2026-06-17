<div id="panel-clients" class="rpt-panel" style="display:none">

    {{-- 3 podiums : Top CA / Top Volume / Top Fréquence --}}
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px" class="rpt-grid-clients">
        @php
            $podiums = [
                ['title' => 'Top CA', 'icon' => '💰', 'color' => '#16a34a', 'rows' => $topClientsByRev,  'metric' => 'revenue', 'unit' => 'FCFA'],
                ['title' => 'Top volume', 'icon' => '📊', 'color' => '#3b82f6', 'rows' => $topClientsByVol,  'metric' => 'volume',  'unit' => 'camp.'],
                ['title' => 'Top fréquence', 'icon' => '⏱️', 'color' => '#a855f7', 'rows' => $topClientsByFreq, 'metric' => 'frequency', 'unit' => '/mois'],
            ];
        @endphp
        @foreach($podiums as $podium)
            <div style="background:var(--surface);border:1px solid var(--border);border-top:3px solid {{ $podium['color'] }};border-radius:14px;padding:14px 16px">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                    <span style="font-size:14px">{{ $podium['icon'] }}</span>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">{{ $podium['title'] }}</span>
                </div>
                @if($podium['rows']->isEmpty())
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">Aucun client sur la période.</div>
                @else
                    <div style="display:flex;flex-direction:column;gap:6px">
                        @foreach($podium['rows'] as $i => $r)
                            @php
                                $value = match($podium['metric']) {
                                    'revenue'   => number_format((float) $r->total_revenue, 0, ',', ' '),
                                    'volume'    => (int) $r->campaigns_count,
                                    'frequency' => number_format($r->frequency, 2, ',', ' '),
                                };
                            @endphp
                            <div onclick="ClientDrilldown.open({{ $r->id }})"
                                 style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:var(--surface2);border-radius:8px;cursor:pointer;transition:transform .15s"
                                 onmouseenter="this.style.transform='translateX(2px)'"
                                 onmouseleave="this.style.transform=''"
                                 title="Voir détail client">
                                <span style="font-size:12px;width:18px;flex-shrink:0">{{ $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) }}</span>
                                <span style="flex:1;min-width:0;font-size:12px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $r->name }}</span>
                                <span style="font-size:11px;font-weight:700;color:{{ $podium['color'] }};font-variant-numeric:tabular-nums;text-align:right">{{ $value }} <span style="font-size:9px;font-weight:400;color:var(--text3)">{{ $podium['unit'] }}</span></span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Répartition des revenus par client (doughnut) + Bandeau inactivité --}}
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px" class="rpt-grid-2">

        {{-- Doughnut répartition CA par client --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l8 4"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Répartition du CA par client</span>
            </div>
            @if($clientRevenueDist['total'] > 0)
                <div style="position:relative;width:100%;height:240px">
                    <canvas id="chart-client-dist" role="img" aria-label="Répartition CA clients"></canvas>
                </div>
            @else
                <div style="padding:60px 14px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun revenu enregistré sur la période.</div>
            @endif
        </div>

        {{-- Alertes inactivité : 3/6/12 mois directement dans l'onglet Clients --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Clients inactifs — par tranche</span>
                <a href="#tab-insights" onclick="event.preventDefault();RPT.switchTab('insights');" style="margin-left:auto;font-size:10px;color:var(--accent);text-decoration:none;font-weight:600">Templates reconquête →</a>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
                <div style="text-align:center;padding:12px 8px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#d97706">{{ $inactivityBucket['3_to_6'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">3-6 mois</div>
                </div>
                <div style="text-align:center;padding:12px 8px;background:rgba(249,115,22,.08);border:1px solid rgba(249,115,22,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#ea580c">{{ $inactivityBucket['6_to_12'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">6-12 mois</div>
                </div>
                <div style="text-align:center;padding:12px 8px;background:rgba(220,38,38,.08);border:1px solid rgba(220,38,38,.3);border-radius:10px">
                    <div style="font-size:22px;font-weight:800;color:#dc2626">{{ $inactivityBucket['12_plus'] }}</div>
                    <div style="font-size:9px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">> 12 mois</div>
                </div>
            </div>
            {{-- Top 5 inactifs > 12 mois pour action rapide --}}
            @if($inactiveClients12->isNotEmpty())
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">Top 5 inactifs > 12 mois — priorité reconquête</div>
                <div style="display:flex;flex-direction:column;gap:5px">
                    @foreach($inactiveClients12->take(5) as $c)
                        <div onclick="ClientDrilldown.open({{ $c->id }})"
                             style="display:flex;align-items:center;gap:8px;padding:6px 10px;background:var(--surface2);border-radius:8px;cursor:pointer;transition:transform .15s"
                             onmouseenter="this.style.transform='translateX(2px)'"
                             onmouseleave="this.style.transform=''">
                            <span style="font-size:11px;font-weight:600;color:var(--text);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $c->name }}</span>
                            <span style="font-size:10px;color:var(--text3);font-family:ui-monospace,monospace">{{ $c->last_campaign_at ? \Carbon\Carbon::parse($c->last_campaign_at)->format('d/m/Y') : '—' }}</span>
                            <span style="font-size:10px;color:#dc2626;font-weight:700">↗</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Tableau portefeuille (existant) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="display:flex;align-items:center;gap:8px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Portefeuille clients — Activité complète</span>
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
                    <tr data-client-id="{{ $client['id'] }}"
                        onclick="ClientDrilldown.open({{ $client['id'] }})"
                        title="Cliquer pour voir l'historique complet du client"
                        style="border-bottom:1px solid var(--border);transition:background .1s;cursor:pointer"
                        onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px">
                            <a href="{{ route('admin.clients.show', $client['id']) }}"
                               onclick="event.stopPropagation()"
                               style="font-size:13px;font-weight:600;color:var(--accent);text-decoration:none">{{ $client['name'] }}</a>
                            <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
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
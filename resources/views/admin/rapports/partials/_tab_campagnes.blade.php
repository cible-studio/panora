<div id="panel-campagnes" class="rpt-panel" style="display:none">

    {{-- Statuts campagnes : 5 cards --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:16px" class="rpt-grid-5">
        @php
            $statusCards = [
                ['Total', $campaignStats['total'],     '#6b7280', 'sur la période'],
                ['Actives', $campaignStats['active'],  '#22c55e', 'en cours'],
                ['Planifiées', $campaignStats['planned'], '#3b82f6', 'à venir'],
                ['Terminées', $campaignStats['done'],  '#94a3b8', 'historique'],
                ['Annulées', $campaignStats['cancelled'], '#dc2626', $campaignStats['cancel_rate'] . '% du total'],
            ];
        @endphp
        @foreach($statusCards as [$lbl, $val, $col, $sub])
            <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid {{ $col }};border-radius:12px;padding:14px 16px">
                <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">{{ $lbl }}</div>
                <div style="font-size:24px;font-weight:800;color:{{ $col }};margin-top:4px;font-variant-numeric:tabular-nums">{{ number_format($val) }}</div>
                <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $sub }}</div>
            </div>
        @endforeach
    </div>

    {{-- Évolution annulations + doughnut motifs côte à côte --}}
    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">

        {{-- Évolution mensuelle annulations (line chart) --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Tendance des annulations — 12 mois</span>
                @php
                    $td  = $cancellationPatterns['trend_direction'];
                    $tp  = $cancellationPatterns['trend_pct'];
                    $tdLabel = $td === 'up' ? '↗ Hausse' : ($td === 'down' ? '↘ Baisse' : '→ Stable');
                    $tdCol   = $td === 'up' ? '#dc2626' : ($td === 'down' ? '#16a34a' : '#6b7280');
                @endphp
                <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $tdCol }}22;color:{{ $tdCol }};font-size:10px;font-weight:700">{{ $tdLabel }} {{ abs($tp) }}%</span>
            </div>
            <div style="position:relative;width:100%;height:240px">
                <canvas id="chart-cancel-trend" role="img" aria-label="Tendance annulations"></canvas>
            </div>
        </div>

        {{-- Doughnut motifs annulation --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2v10l8 4"/></svg>
                <span style="font-size:13px;font-weight:700;color:var(--text)">Motifs d'annulation</span>
                <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $cancelReasons->sum('count') }} annulation(s)</span>
            </div>
            @if($cancelReasons->isEmpty())
                <div style="padding:60px 14px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune annulation sur la période.</div>
            @else
                <div style="position:relative;width:100%;height:240px">
                    <canvas id="chart-cancel-reasons-camp" role="img" aria-label="Motifs annulation"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Causes récurrentes : tableau détaillé + clients récidivistes --}}
    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">

        {{-- Détail motifs avec % et CA perdu estimé --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                <span style="font-size:12px;font-weight:700;color:var(--text)">Causes récurrentes (motifs prédéfinis)</span>
            </div>
            @if($cancelReasons->isEmpty())
                <div style="padding:30px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun motif enregistré.</div>
            @else
                @php
                    $reasonLabelsFull = [
                        'budget' => '💸 Budget client',
                        'zone' => '📍 Choix de zone',
                        'strategie' => '🎯 Changement stratégique',
                        'report' => '⏰ Report client',
                        'concurrent' => '🤝 Choix concurrent',
                        'autre' => '❓ Autre motif',
                    ];
                    $totalCancFull = $cancelReasons->sum('count');
                @endphp
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:var(--surface2)">
                            <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Motif</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Nb</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">% du total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cancelReasons as $r)
                            @php $pct = $totalCancFull > 0 ? round(($r->count / $totalCancFull) * 100, 1) : 0; @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:8px 12px;color:var(--text)">{{ $reasonLabelsFull[$r->cancellation_reason] ?? ucfirst($r->cancellation_reason) }}</td>
                                <td style="padding:8px 12px;text-align:right;font-weight:700;color:#dc2626">{{ $r->count }}</td>
                                <td style="padding:8px 12px;text-align:right">
                                    <div style="display:flex;align-items:center;justify-content:flex-end;gap:6px">
                                        <div style="width:50px;height:4px;background:var(--border);border-radius:2px;overflow:hidden">
                                            <div style="height:100%;width:{{ $pct }}%;background:linear-gradient(90deg,#ef4444,#f97316)"></div>
                                        </div>
                                        <span style="font-size:11px;font-weight:700;color:var(--text);min-width:36px;text-align:right">{{ $pct }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- Clients récidivistes --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
            <div style="padding:12px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span style="font-size:12px;font-weight:700;color:var(--text)">Clients récidivistes (>1 annulation)</span>
                <span style="margin-left:auto;font-size:10px;color:var(--text3)">Signal faible</span>
            </div>
            @if($cancellationPatterns['repeat_offenders']->isEmpty())
                <div style="padding:30px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucun client récidiviste détecté.</div>
            @else
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr style="background:var(--surface2)">
                            <th style="padding:8px 12px;text-align:left;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Client</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">Annulations</th>
                            <th style="padding:8px 12px;text-align:right;font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.4px">CA perdu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cancellationPatterns['repeat_offenders'] as $client)
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="ClientDrilldown.open({{ $client->id }})" title="Voir historique client">
                                <td style="padding:8px 12px;font-weight:600;color:var(--text)">{{ $client->name }}</td>
                                <td style="padding:8px 12px;text-align:right">
                                    <span style="padding:2px 8px;border-radius:10px;background:rgba(220,38,38,.12);color:#dc2626;font-size:11px;font-weight:700">{{ $client->cancellations }}</span>
                                </td>
                                <td style="padding:8px 12px;text-align:right;font-size:11px;font-weight:600;color:var(--text2);font-variant-numeric:tabular-nums">{{ $client->lost_revenue > 0 ? number_format($client->lost_revenue, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Recommandations actionnables --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">💡 Recommandations pour réduire les annulations</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Généré automatiquement</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:10px">
            @foreach($cancellationRecos as $reco)
                @php
                    $rc = match($reco['severity']) {
                        'danger'  => ['#dc2626', 'rgba(220,38,38,.06)',  'rgba(220,38,38,.3)'],
                        'warning' => ['#d97706', 'rgba(245,158,11,.06)', 'rgba(245,158,11,.3)'],
                        'success' => ['#16a34a', 'rgba(34,197,94,.06)',  'rgba(34,197,94,.3)'],
                        default   => ['#3b82f6', 'rgba(59,130,246,.06)', 'rgba(59,130,246,.3)'],
                    };
                @endphp
                <div style="background:{{ $rc[1] }};border:1px solid {{ $rc[2] }};border-radius:10px;padding:12px 14px">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                        <span style="font-size:16px">{{ $reco['icon'] }}</span>
                        <span style="font-size:12.5px;font-weight:700;color:{{ $rc[0] }}">{{ $reco['title'] }}</span>
                    </div>
                    <div style="font-size:11.5px;color:var(--text3);font-style:italic;margin-bottom:6px;line-height:1.5">{{ $reco['pattern'] }}</div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.55"><strong style="color:var(--text)">Action :</strong> {{ $reco['action'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
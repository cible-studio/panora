<div id="panel-panneaux" class="rpt-panel" style="display:none">

    {{-- ── Bandeau exports dédiés (2026-07-01) ──────────────────
         Réutilise les routes existantes panels-occupation-{excel,pdf}
         qui retournent la liste complète des panneaux triés par jours
         occupés desc, en respectant tous les filtres actifs (période,
         zone, commune, ville, catégorie). --}}
    <div style="background:linear-gradient(90deg,#fff7ed,#fef3c7);border:1px solid #fed7aa;border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
            <div>
                <div style="font-size:12px;font-weight:800;color:#78350f">Exporter les panneaux les plus occupés</div>
                <div style="font-size:10px;color:#92400e;margin-top:2px">Liste complète triée par jours occupés · respecte les filtres actifs</div>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('admin.rapports.export.panels-occupation-excel', request()->query()) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg>
                Excel
            </a>
            <a href="{{ route('admin.rapports.export.panels-occupation-pdf', request()->query()) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF
            </a>
        </div>
    </div>

    {{-- Alertes performance panneaux (COMMIT E) --}}
    @if($panelAlerts->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Alertes performance panneaux</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Détection automatique</span>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:8px">
            @foreach($panelAlerts as $a)
                @php
                    $col = match($a['severity']) {
                        'danger'  => ['#dc2626', 'rgba(220,38,38,.06)',  'rgba(220,38,38,.3)'],
                        'warning' => ['#d97706', 'rgba(245,158,11,.06)', 'rgba(245,158,11,.3)'],
                        'success' => ['#16a34a', 'rgba(34,197,94,.06)',  'rgba(34,197,94,.3)'],
                        default   => ['#3b82f6', 'rgba(59,130,246,.06)', 'rgba(59,130,246,.3)'],
                    };
                @endphp
                <div style="background:{{ $col[1] }};border:1px solid {{ $col[2] }};border-radius:10px;padding:10px 12px">
                    <div style="display:flex;align-items:center;gap:6px;margin-bottom:4px">
                        <span style="font-size:14px">{{ $a['icon'] }}</span>
                        <span style="font-size:11.5px;font-weight:700;color:{{ $col[0] }}">{{ $a['title'] }}</span>
                    </div>
                    <div style="font-size:10.5px;color:var(--text2);line-height:1.5">{{ $a['detail'] }}</div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Classement visuel Chart.js (top 15 panneaux les plus loués) --}}
    @if($topPanels->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Classement visuel — top 15 panneaux les plus loués</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Jours occupés sur la période</span>
        </div>
        <div style="position:relative;width:100%;height:380px">
            <canvas id="chart-top-panels" role="img" aria-label="Top panneaux"></canvas>
        </div>
    </div>
    @endif

    <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">

        {{-- Top 20 plus loués ──────────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/></svg>
                Top 20 panneaux les plus loués
            </div>
            @if($topPanels->isEmpty())
                <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune donnée sur la période.</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">#</th>
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Jours occupé</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Camp.</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">CA estimé</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topPanels as $i => $p)
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="PanelDrilldown.open({{ $p->id }})" title="Cliquer pour l'historique d'occupation">
                                <td style="padding:8px;color:var(--text3);font-weight:700">{{ $i+1 }}</td>
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                                    <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }} · {{ $p->commune_name ?? '—' }}</div>
                                </td>
                                <td style="padding:8px;text-align:right;font-weight:700;color:#16a34a">{{ $p->days_occupied }}j</td>
                                <td style="padding:8px;text-align:right;color:var(--text2)">{{ $p->campaigns_count }}</td>
                                <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ number_format((float) $p->estimated_revenue, 0, ',', ' ') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Top 20 sous-performants ────────────────────────────────── --}}
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
            <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                Panneaux sous-performants
            </div>
            @if($lowPanels->isEmpty())
                <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Tous les panneaux ont au moins une campagne.</div>
            @else
                <div style="overflow-x:auto">
                    <table style="width:100%;border-collapse:collapse;font-size:12px">
                        <thead>
                            <tr style="background:var(--surface2)">
                                <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Camp.</th>
                                <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Tarif/mois</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lowPanels as $p)
                            <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                                onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                                onclick="PanelDrilldown.open({{ $p->id }})" title="Cliquer pour l'historique d'occupation">
                                <td style="padding:8px">
                                    <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                    <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                                    <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }} · {{ $p->commune_name ?? '—' }}</div>
                                </td>
                                <td style="padding:8px;text-align:right">
                                    @if($p->campaigns_count == 0)
                                        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(239,68,68,.1);color:#ef4444">0 — jamais loué</span>
                                    @else
                                        <span style="color:#f97316;font-weight:700">{{ $p->campaigns_count }}</span>
                                    @endif
                                </td>
                                <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ $p->monthly_rate > 0 ? number_format($p->monthly_rate, 0, ',', ' ') : '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- Périodes creuses détectées (panneaux > 60j sans campagne) — COMMIT E --}}
    @if($inactivePanels->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Périodes creuses — panneaux > 60 jours sans campagne</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3)">{{ $inactivePanels->count() }} panneau(x)</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:var(--surface2)">
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Commune</th>
                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Dernière campagne</th>
                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Inactivité</th>
                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Tarif/mois</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($inactivePanels as $p)
                        @php
                            $days = (int) ($p->days_inactive ?? 0);
                            $color = $p->last_end === null ? '#dc2626' : ($days > 180 ? '#dc2626' : '#d97706');
                            $bg    = $p->last_end === null ? 'rgba(220,38,38,.12)' : ($days > 180 ? 'rgba(220,38,38,.12)' : 'rgba(245,158,11,.12)');
                        @endphp
                        <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                            onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''"
                            onclick="PanelDrilldown.open({{ $p->id }})" title="Voir l'historique">
                            <td style="padding:8px">
                                <a href="{{ route('admin.panels.show', $p->id) }}" onclick="event.stopPropagation()" style="font-family:ui-monospace,monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                <span style="font-size:11px;color:var(--text3);margin-left:6px;opacity:.6">↗</span>
                                <div style="font-size:10px;color:var(--text3)">{{ \Illuminate\Support\Str::limit($p->name ?? '', 40) }}</div>
                            </td>
                            <td style="padding:8px;color:var(--text2)">{{ $p->commune_name ?? '—' }}</td>
                            <td style="padding:8px;color:var(--text3);font-family:ui-monospace,monospace;font-size:11px">
                                {{ $p->last_end ? \Carbon\Carbon::parse($p->last_end)->format('d/m/Y') : 'Jamais loué' }}
                            </td>
                            <td style="padding:8px;text-align:right">
                                <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:{{ $bg }};color:{{ $color }}">
                                    {{ $p->last_end === null ? 'Jamais loué' : $days . 'j' }}
                                </span>
                            </td>
                            <td style="padding:8px;text-align:right;color:var(--text2);font-family:ui-monospace,monospace;font-size:11px">{{ $p->monthly_rate > 0 ? number_format($p->monthly_rate, 0, ',', ' ') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
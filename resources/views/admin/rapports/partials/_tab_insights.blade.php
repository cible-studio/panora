<div id="panel-insights" class="rpt-panel" style="display:none">

    {{-- 🎯 SYNTHÈSE EXÉCUTIVE — direction (vue stratégique en haut) --}}
    <div style="background:linear-gradient(135deg,var(--surface),var(--surface2));border:1.5px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="{{ $execSummary['score_color'] }}" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span style="font-size:14px;font-weight:800;color:var(--text)">Synthèse exécutive — direction</span>
            <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
                <span style="font-size:11px;color:var(--text3)">Score performance</span>
                <span style="font-size:18px;font-weight:800;color:{{ $execSummary['score_color'] }};padding:4px 12px;border-radius:10px;background:{{ $execSummary['score_color'] }}22">{{ $execSummary['score'] }}/10 — {{ $execSummary['score_label'] }}</span>
            </div>
        </div>

        {{-- 4 KPIs synthèse direction --}}
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:14px" class="rpt-grid-5">
            @php
                $execKpis = [
                    ['CA réalisé', number_format($execSummary['kpis']['revenue']/1000000, 1, ',', ' ') . 'M', 'FCFA sur période', '#e8a020'],
                    ['Occupation', $execSummary['kpis']['occupation_rate'] . '%', 'du parc', '#3b82f6'],
                    ["Taux annul.", $execSummary['kpis']['cancel_rate'] . '%', $execSummary['kpis']['campaigns_total'] . ' campagnes', $execSummary['kpis']['cancel_rate'] > 18 ? '#dc2626' : ($execSummary['kpis']['cancel_rate'] > 12 ? '#f59e0b' : '#16a34a')],
                    ['CA prévu 3m', number_format($execSummary['forecast_3m_revenue']/1000000, 1, ',', ' ') . 'M', 'FCFA · conf. ' . $execSummary['forecast_confidence'] . '%', '#a855f7'],
                ];
            @endphp
            @foreach($execKpis as [$lbl, $val, $sub, $col])
                <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:11px 14px;border-left:3px solid {{ $col }}">
                    <div style="font-size:9px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">{{ $lbl }}</div>
                    <div style="font-size:18px;font-weight:800;color:{{ $col }};line-height:1.1;margin-top:4px">{{ $val }}</div>
                    <div style="font-size:9.5px;color:var(--text3);margin-top:2px">{{ $sub }}</div>
                </div>
            @endforeach
        </div>

        {{-- 3 blocs risques / opportunités / actions --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px" class="rpt-grid-2">
            {{-- Risques --}}
            <div style="background:rgba(220,38,38,.04);border:1px solid rgba(220,38,38,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#dc2626;text-transform:uppercase;letter-spacing:.5px">Risques majeurs</span>
                </div>
                <ul style="margin:0;padding-left:0;list-style:none;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['risks'] as $r)
                        <li>{{ $r }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Opportunités --}}
            <div style="background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#16a34a;text-transform:uppercase;letter-spacing:.5px">Opportunités</span>
                </div>
                <ul style="margin:0;padding-left:0;list-style:none;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['opportunities'] as $o)
                        <li>{{ $o }}</li>
                    @endforeach
                </ul>
            </div>

            {{-- Actions prioritaires --}}
            <div style="background:rgba(59,130,246,.04);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:12px 14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    <span style="font-size:11px;font-weight:700;color:#3b82f6;text-transform:uppercase;letter-spacing:.5px">Actions prioritaires</span>
                </div>
                <ol style="margin:0;padding-left:18px;font-size:11px;color:var(--text2);line-height:1.5;display:flex;flex-direction:column;gap:6px">
                    @foreach($execSummary['actions'] as $a)
                        @php $aCol = $a['priority'] === 'high' ? '#dc2626' : ($a['priority'] === 'medium' ? '#f59e0b' : '#3b82f6'); @endphp
                        <li><span style="color:{{ $aCol }};font-weight:700">[{{ strtoupper($a['priority']) }}]</span> {{ $a['action'] }}</li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>

    {{-- 🌍 BENCHMARKS SECTORIELS — données marché OOH Côte d'Ivoire / Afrique --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Benchmarks sectoriels OOH — Côte d'Ivoire / Afrique</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic" title="{{ $marketBenchmarks['meta']['notes'] }}">MAJ {{ $marketBenchmarks['meta']['last_updated'] }}</span>
        </div>

        {{-- Notre position vs marché : occupation + annulation --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            {{-- Position occupation --}}
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px">📊 Occupation parc</span>
                    @php
                        $posLabels = ['leader' => ['🏆 Leader', '#16a34a'], 'above_average' => ['✅ Au-dessus marché', '#3b82f6'], 'below_average' => ['⚠️ Sous le marché', '#f59e0b']];
                        $pos = $posLabels[$marketBenchmarks['occupation']['position']];
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $pos[1] }}22;color:{{ $pos[1] }};font-size:10px;font-weight:700">{{ $pos[0] }}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:6px">
                    <span style="font-size:22px;font-weight:800;color:{{ $pos[1] }}">{{ $marketBenchmarks['occupation']['our_value'] }}%</span>
                    <span style="font-size:11px;color:var(--text3)">vs marché CI {{ $marketBenchmarks['occupation']['market_ci'] }}% · top {{ $marketBenchmarks['occupation']['market_top'] }}% · Afrique {{ $marketBenchmarks['occupation']['market_africa'] }}%</span>
                </div>
                <div style="position:relative;height:8px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:8px">
                    <div style="position:absolute;left:{{ $marketBenchmarks['occupation']['market_ci'] }}%;width:2px;height:100%;background:#94a3b8" title="Moyenne marché CI"></div>
                    <div style="position:absolute;left:{{ $marketBenchmarks['occupation']['market_top'] }}%;width:2px;height:100%;background:#16a34a" title="Top performers"></div>
                    <div style="height:100%;width:{{ min($marketBenchmarks['occupation']['our_value'], 100) }}%;background:linear-gradient(90deg,#3b82f6,{{ $pos[1] }});border-radius:4px"></div>
                </div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.4">{{ $marketBenchmarks['occupation']['note'] }}</div>
            </div>

            {{-- Position taux annulation --}}
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px">
                    <span style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px">❌ Taux d'annulation</span>
                    @php
                        $cancelLabels = ['healthy' => ['✅ Sain', '#16a34a'], 'average' => ['⚠️ Moyen', '#f59e0b'], 'critical' => ['🔴 Critique', '#dc2626']];
                        $cancelPos = $cancelLabels[$marketBenchmarks['cancel_rate']['position']];
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $cancelPos[1] }}22;color:{{ $cancelPos[1] }};font-size:10px;font-weight:700">{{ $cancelPos[0] }}</span>
                </div>
                <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:6px">
                    <span style="font-size:22px;font-weight:800;color:{{ $cancelPos[1] }}">{{ $marketBenchmarks['cancel_rate']['our_value'] }}%</span>
                    <span style="font-size:11px;color:var(--text3)">vs sain ≤{{ $marketBenchmarks['cancel_rate']['industry_healthy'] }}% · moy. {{ $marketBenchmarks['cancel_rate']['industry_average'] }}% · alerte ≥{{ $marketBenchmarks['cancel_rate']['industry_warning'] }}%</span>
                </div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.4">
                    @if($marketBenchmarks['cancel_rate']['delta_vs_market'] < 0)
                        Vous êtes <strong style="color:#16a34a">{{ abs($marketBenchmarks['cancel_rate']['delta_vs_market']) }} pts sous</strong> la moyenne marché.
                    @else
                        Vous êtes <strong style="color:#dc2626">+{{ $marketBenchmarks['cancel_rate']['delta_vs_market'] }} pts au-dessus</strong> de la moyenne marché.
                    @endif
                </div>
            </div>
        </div>

        {{-- Croissance + Tarification de référence --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">📈 Croissance secteur OOH</div>
                <div style="font-size:20px;font-weight:800;color:#16a34a;margin-bottom:4px">+{{ $marketBenchmarks['growth']['ci_yoy_2025_2026'] ?? '—' }}% <span style="font-size:11px;color:var(--text3);font-weight:400">YoY 2025→2026 CI</span></div>
                <div style="font-size:10.5px;color:var(--text3);line-height:1.5">CI 2024-2025 : <strong>+{{ $marketBenchmarks['growth']['ci_yoy_2024_2025'] ?? '—' }}%</strong> · Afrique 2025 : <strong>+{{ $marketBenchmarks['growth']['africa_yoy_2025'] ?? '—' }}%</strong></div>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:6px;line-height:1.4">{{ $marketBenchmarks['growth']['note'] ?? '' }}</div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">💰 Tarification de référence</div>
                <div style="display:flex;flex-direction:column;gap:4px;font-size:11px">
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Abidjan 4×3 lumineux</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['abidjan_4x3_lit'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Abidjan 4×3 classique</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['abidjan_4x3_classique'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Intérieur pays 4×3</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['intérieur_pays_4x3'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                    <div style="display:flex;justify-content:space-between;padding:3px 0"><span style="color:var(--text3)">Panneau géant 8×3</span><strong style="color:var(--accent)">{{ number_format($marketBenchmarks['pricing']['panneau_geant_8x3'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                </div>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:8px;line-height:1.4">{{ $marketBenchmarks['pricing']['note'] ?? '' }}</div>
            </div>
        </div>

        {{-- Mix sectoriel annonceurs + Concurrents --}}
        <div class="rpt-grid-2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🎯 Mix annonceurs CI</div>
                <div style="display:flex;flex-direction:column;gap:5px">
                    @foreach($marketBenchmarks['industry_mix'] as $sec)
                        <div style="display:flex;align-items:center;gap:8px">
                            <span style="font-size:11px;color:var(--text);min-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $sec['sector'] }}</span>
                            <div style="flex:1;height:5px;background:var(--border);border-radius:3px;overflow:hidden">
                                <div style="height:100%;width:{{ $sec['share_pct'] * 3.5 }}%;background:linear-gradient(90deg,#3b82f6,#a855f7);border-radius:3px"></div>
                            </div>
                            <span style="font-size:11px;font-weight:700;color:var(--text2);min-width:30px;text-align:right">{{ $sec['share_pct'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🏢 Acteurs concurrents (estimés)</div>
                <table style="width:100%;border-collapse:collapse;font-size:11px">
                    <tbody>
                        @foreach($marketBenchmarks['competitors'] as $comp)
                            @php $tierCol = $comp['tier'] === 'leader' ? '#16a34a' : ($comp['tier'] === 'challenger' ? '#3b82f6' : '#94a3b8'); @endphp
                            <tr style="border-bottom:1px solid var(--border)">
                                <td style="padding:5px 0;color:var(--text)">{{ $comp['name'] }}</td>
                                <td style="padding:5px 0;text-align:right;color:var(--text3);font-variant-numeric:tabular-nums">{{ number_format($comp['estimated_parc']) }} pann.</td>
                                <td style="padding:5px 0;text-align:right"><span style="font-size:9px;padding:2px 7px;border-radius:8px;background:{{ $tierCol }}22;color:{{ $tierCol }};font-weight:700;text-transform:uppercase">{{ $comp['tier'] }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:6px">⚠️ Estimations indicatives — sources publiques non officielles.</div>
            </div>
        </div>

        {{-- Tendances structurelles à surveiller --}}
        <div>
            <div style="font-size:11px;font-weight:700;color:var(--text);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px">🔮 Tendances structurelles du marché</div>
            <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px" class="rpt-grid-2">
                @foreach($marketBenchmarks['trends'] as $tr)
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 13px">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px">
                            <span style="font-size:16px">{{ $tr['icon'] }}</span>
                            <strong style="font-size:11.5px;color:var(--text)">{{ $tr['title'] }}</strong>
                        </div>
                        <div style="font-size:11px;color:var(--text3);line-height:1.5">{{ $tr['desc'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);font-style:italic;margin-top:14px;padding:8px 12px;background:var(--surface2);border-radius:8px;line-height:1.5">
            ⓘ <strong>Sources :</strong> {{ $marketBenchmarks['meta']['notes'] }}. Pour mettre à jour ces données, éditez <code>config/market_benchmarks.php</code>. Ces valeurs sont indicatives — à compléter avec études OAAA, UDECI, INS Côte d'Ivoire dès qu'elles sont disponibles.
        </div>
    </div>

    {{-- Prévisions régression linéaire 3 mois (COMMIT D) --}}
    <div style="background:linear-gradient(135deg,rgba(59,130,246,.05),rgba(168,85,247,.05));border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">🔮 Prévisions 3 mois — régression linéaire</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Statistique simple, pas d'IA · basé sur les 12 derniers mois</span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
            {{-- Prévision CA --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <span style="font-size:11px;font-weight:700;color:var(--text)">💰 CA projeté</span>
                    @php
                        $rev = $forecastRevenue;
                        $revBadge = $rev['trend_direction'] === 'up'   ? ['#16a34a','rgba(34,197,94,.12)','↗ Hausse']
                                  : ($rev['trend_direction'] === 'down' ? ['#dc2626','rgba(220,38,38,.12)','↘ Baisse']
                                                                       : ['#6b7280','rgba(107,114,128,.12)','→ Stable']);
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $revBadge[1] }};color:{{ $revBadge[0] }};font-size:10px;font-weight:700">{{ $revBadge[2] }} {{ abs($rev['trend_pct_per_month']) }}%/mois</span>
                </div>
                @if(empty($rev['forecast']))
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">{{ $rev['message'] ?? 'Pas assez de données.' }}</div>
                @else
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <tbody>
                            @foreach($rev['forecast'] as $f)
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:7px 0;color:var(--text2);font-weight:600">{{ $f['label'] }}</td>
                                    <td style="padding:7px 0;text-align:right;font-weight:700;color:#16a34a;font-variant-numeric:tabular-nums">{{ number_format($f['value'], 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="font-size:10px;color:var(--text3);margin-top:8px;line-height:1.5">
                        Confiance modèle : <strong style="color:{{ $rev['confidence'] >= 60 ? '#16a34a' : ($rev['confidence'] >= 30 ? '#f59e0b' : '#dc2626') }}">{{ $rev['confidence'] }}%</strong>
                        (R² = {{ $rev['r_squared'] }})
                    </div>
                @endif
            </div>

            {{-- Prévision Occupation --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <span style="font-size:11px;font-weight:700;color:var(--text)">📊 Taux d'occupation projeté</span>
                    @php
                        $occ = $forecastOccupation;
                        $occBadge = $occ['trend_direction'] === 'up'   ? ['#16a34a','rgba(34,197,94,.12)','↗ Hausse']
                                  : ($occ['trend_direction'] === 'down' ? ['#dc2626','rgba(220,38,38,.12)','↘ Baisse']
                                                                       : ['#6b7280','rgba(107,114,128,.12)','→ Stable']);
                    @endphp
                    <span style="margin-left:auto;padding:2px 8px;border-radius:10px;background:{{ $occBadge[1] }};color:{{ $occBadge[0] }};font-size:10px;font-weight:700">{{ $occBadge[2] }} {{ abs($occ['trend_pct_per_month']) }}%/mois</span>
                </div>
                @if(empty($occ['forecast']))
                    <div style="padding:14px;text-align:center;color:var(--text3);font-size:11px;font-style:italic">{{ $occ['message'] ?? 'Pas assez de données.' }}</div>
                @else
                    <table style="width:100%;font-size:12px;border-collapse:collapse">
                        <tbody>
                            @foreach($occ['forecast'] as $f)
                                <tr style="border-bottom:1px solid var(--border)">
                                    <td style="padding:7px 0;color:var(--text2);font-weight:600">{{ $f['label'] }}</td>
                                    <td style="padding:7px 0;text-align:right;font-weight:700;color:#3b82f6;font-variant-numeric:tabular-nums">{{ round($f['value'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div style="font-size:10px;color:var(--text3);margin-top:8px;line-height:1.5">
                        Confiance modèle : <strong style="color:{{ $occ['confidence'] >= 60 ? '#16a34a' : ($occ['confidence'] >= 30 ? '#f59e0b' : '#dc2626') }}">{{ $occ['confidence'] }}%</strong>
                        (R² = {{ $occ['r_squared'] }})
                    </div>
                @endif
            </div>
        </div>

        <div style="font-size:10px;color:var(--text3);margin-top:12px;padding:8px 12px;background:var(--surface2);border-radius:8px;line-height:1.5">
            ⓘ <strong>Méthode :</strong> régression linéaire des moindres carrés sur l'historique 12 mois. Le modèle projette une tendance linéaire — il ne capture pas la saisonnalité (Ramadan, fêtes de fin d'année, etc.). À interpréter comme une <em>orientation</em>, pas comme une valeur exacte. Un R² élevé indique que la tendance est nette dans les données passées.
        </div>
    </div>

    {{-- Suggestions reconquête : templates prêts à l'emploi (COMMIT B) --}}
    @if(($inactivityBucket['6_to_12'] ?? 0) + ($inactivityBucket['12_plus'] ?? 0) > 0)
    <div style="background:linear-gradient(135deg,rgba(239,68,68,.04),rgba(168,85,247,.04));border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:18px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">🎯 Reconquête clients — templates prêts à l'emploi</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:14px">
            {{ ($inactivityBucket['6_to_12'] ?? 0) + ($inactivityBucket['12_plus'] ?? 0) }} client(s) en zone de churn — utilisez ces messages directement.
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            {{-- Template MAIL --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;position:relative">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">📧 Modèle e-mail (J0)</span>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('tpl-mail').innerText);this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',1500)"
                            style="margin-left:auto;font-size:10px;font-weight:600;background:var(--surface2);border:1px solid var(--border);color:var(--text2);padding:3px 10px;border-radius:6px;cursor:pointer">Copier</button>
                </div>
                <div id="tpl-mail" style="font-size:11px;line-height:1.55;color:var(--text2);font-family:Georgia,serif;background:var(--surface2);padding:10px 12px;border-radius:8px;white-space:pre-wrap">Objet : Une opportunité en or vous attend chez CIBLE CI

Bonjour [PRENOM],

Cela fait plusieurs mois que nous n'avons pas eu le plaisir de collaborer avec [SOCIETE]. Vos précédentes campagnes ont eu un excellent impact sur le terrain, et nous tenons à vous proposer une offre privilégiée pour votre retour :

• 15 % de remise sur votre prochaine campagne (>1 mois)
• Choix prioritaire sur nos panneaux stratégiques
• Suivi dédié par votre commercial habituel

Souhaitez-vous que nous échangions cette semaine pour évoquer vos prochains objectifs de communication ?

Cordialement,
[VOTRE NOM]
CIBLE CI — Affichage urbain</div>
            </div>

            {{-- Template APPEL --}}
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:14px;position:relative">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:10px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <span style="font-size:12px;font-weight:700;color:var(--text)">📞 Script appel commercial</span>
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('tpl-call').innerText);this.textContent='✓ Copié';setTimeout(()=>this.textContent='Copier',1500)"
                            style="margin-left:auto;font-size:10px;font-weight:600;background:var(--surface2);border:1px solid var(--border);color:var(--text2);padding:3px 10px;border-radius:6px;cursor:pointer">Copier</button>
                </div>
                <div id="tpl-call" style="font-size:11px;line-height:1.55;color:var(--text2);font-family:Georgia,serif;background:var(--surface2);padding:10px 12px;border-radius:8px;white-space:pre-wrap">🎯 OUVERTURE (15 sec)
"Bonjour [PRENOM], c'est [VOTRE NOM] de CIBLE CI. Je vous appelle car cela fait [X mois] que nous n'avons pas eu l'occasion de travailler ensemble. Avez-vous 2 minutes ?"

🔍 DÉCOUVERTE
• Comment se portent vos actions de communication actuellement ?
• Quels sont vos objectifs prioritaires pour les prochains mois ?
• Avez-vous testé d'autres canaux (digital, presse) entre-temps ?

💡 PROPOSITION
"J'ai justement repéré [N] emplacements stratégiques disponibles dans la zone [VILLE/COMMUNE] — exactement le profil de vos précédentes campagnes qui ont bien performé. Je vous propose une offre de retour : 15 % de remise + suivi VIP."

✅ CLÔTURE
"Quand serait le meilleur moment cette semaine pour vous envoyer un dossier sur mesure ?"</div>
            </div>
        </div>
    </div>
    @endif

    {{-- Liste des insights générés automatiquement --}}
    <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:18px">
        @foreach($insights as $insight)
            @php
                $colors = match($insight['severity']) {
                    'danger'  => ['bg' => 'rgba(220,38,38,.06)',  'border' => 'rgba(220,38,38,.3)',  'color' => '#dc2626'],
                    'warning' => ['bg' => 'rgba(245,158,11,.06)', 'border' => 'rgba(245,158,11,.3)', 'color' => '#d97706'],
                    'success' => ['bg' => 'rgba(34,197,94,.06)',  'border' => 'rgba(34,197,94,.3)',  'color' => '#16a34a'],
                    default   => ['bg' => 'rgba(59,130,246,.06)', 'border' => 'rgba(59,130,246,.3)', 'color' => '#3b82f6'],
                };
            @endphp
            <div style="background:{{ $colors['bg'] }};border:1px solid {{ $colors['border'] }};border-radius:12px;padding:14px 16px;display:flex;align-items:flex-start;gap:12px">
                <span style="font-size:22px;line-height:1;flex-shrink:0">{{ $insight['icon'] }}</span>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:700;color:{{ $colors['color'] }};margin-bottom:4px">{{ $insight['title'] }}</div>
                    <div style="font-size:12px;color:var(--text2);line-height:1.5">{{ $insight['message'] }}</div>
                    @if(!empty($insight['details']))
                        <div style="font-size:11px;color:var(--text3);margin-top:4px;font-style:italic">{{ $insight['details'] }}</div>
                    @endif
                </div>
                @if(!empty($insight['cta_label']) && !empty($insight['cta_url']))
                    <a href="{{ $insight['cta_url'] }}" style="font-size:11px;font-weight:700;padding:5px 12px;background:{{ $colors['color'] }};color:#fff;border-radius:8px;text-decoration:none;flex-shrink:0;white-space:nowrap">
                        {{ $insight['cta_label'] }} →
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Tranches d'inactivité clients (cards + Chart.js bar) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">📉 Clients inactifs — par tranche</div>
        <div style="display:grid;grid-template-columns:2fr 3fr;gap:16px;align-items:start">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
            <div style="text-align:center;padding:14px;background:rgba(245,158,11,.06);border:1px solid rgba(245,158,11,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#d97706">{{ $inactivityBucket['3_to_6'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs 3-6 mois</div>
            </div>
            <div style="text-align:center;padding:14px;background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#ea580c">{{ $inactivityBucket['6_to_12'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs 6-12 mois</div>
            </div>
            <div style="text-align:center;padding:14px;background:rgba(220,38,38,.06);border:1px solid rgba(220,38,38,.25);border-radius:10px">
                <div style="font-size:24px;font-weight:800;color:#dc2626">{{ $inactivityBucket['12_plus'] }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:4px">Inactifs > 12 mois</div>
            </div>
        </div>
        <div style="position:relative;width:100%;height:180px">
            <canvas id="chart-inactivity" role="img" aria-label="Tranches d'inactivité"></canvas>
        </div>
        </div>
    </div>

    {{-- Motifs d'annulation campagnes (doughnut Chart.js + liste détaillée) --}}
    @if($cancelReasons->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px">📋 Motifs d'annulation campagnes ({{ $campaignStats['cancel_rate'] }}% sur {{ $campaignStats['total'] }} campagnes)</div>
        @php
            $reasonLabels = [
                'budget' => '💸 Budget client', 'zone' => '📍 Choix zone', 'strategie' => '🎯 Changement stratégie',
                'report' => '⏰ Report client', 'concurrent' => '🤝 Choix concurrent', 'autre' => '❓ Autre',
            ];
            $totalCancel = $cancelReasons->sum('count');
        @endphp
        <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;align-items:center">
            <div style="position:relative;width:280px;height:240px">
                <canvas id="chart-cancel-reasons" role="img" aria-label="Motifs d'annulation"></canvas>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach($cancelReasons as $r)
                    @php $pct = $totalCancel > 0 ? round(($r->count / $totalCancel) * 100, 1) : 0; @endphp
                    <div style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--surface2);border-radius:8px">
                        <span style="font-size:12px;color:var(--text);min-width:160px">{{ $reasonLabels[$r->cancellation_reason] ?? ucfirst($r->cancellation_reason) }}</span>
                        <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                            <div style="height:100%;background:linear-gradient(90deg,#ef4444,#f97316);width:{{ $pct }}%"></div>
                        </div>
                        <span style="font-size:11px;font-weight:700;color:var(--text);min-width:40px;text-align:right">{{ $r->count }}</span>
                        <span style="font-size:10px;color:var(--text3);min-width:40px;text-align:right">{{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif
</div>
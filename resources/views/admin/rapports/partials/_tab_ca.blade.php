<div id="panel-ca" class="rpt-panel" style="display:none">

    {{-- 5 KPIs financiers : CA, ticket moyen, CA/panneau, CA/client, top client --}}
    @php
        $caParPanneau = $occupation['occupes'] > 0 ? round($caTotal / $occupation['occupes']) : 0;
        $caParClient  = $totalClients > 0 ? round($caTotal / $totalClients) : 0;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;margin-bottom:18px" class="rpt-grid-5">
        @php
        // Bloc 4 Garde-fou 2 : ces 5 KPIs sont basés sur le CA CONTRACTUEL
        // (Campaign.total_amount) — libellé explicite pour lever toute
        // ambiguïté avec les 2 KPIs CA RÉEL en tête de la page Rapports.
        $caKpis = [
            ['CA contractuel période',  number_format($caTotal, 0, ',', ' ') . ' FCFA', '#e8a020', 'FCFA · ' . $totalCampagnes . ' campagnes'],
            ['Ticket moyen',            number_format($caTicketMoy, 0, ',', ' ') . ' FCFA', '#3b82f6', 'par campagne (contractuel)'],
            ['CA contr. / panneau loué',number_format($caParPanneau, 0, ',', ' ') . ' FCFA', '#16a34a', 'sur ' . number_format($occupation['occupes']) . ' panneaux occupés'],
            ['CA contr. moyen / client',number_format($caParClient, 0, ',', ' ') . ' FCFA', '#06b6d4', 'sur ' . number_format($totalClients) . ' clients actifs'],
            ['Top client (contractuel)',$topClients->first()?->name ?? '—', '#a855f7', $topClients->first() ? number_format($topClients->first()->ca_total, 0, ',', ' ') . ' FCFA' : '—'],
        ];
        @endphp
        @foreach($caKpis as [$lbl, $val, $col, $sub])
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px;border-top:3px solid {{ $col }}">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:5px">{{ $lbl }}</div>
            <div style="font-size:14px;font-weight:800;color:{{ $col }};line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $val }}">{{ $val }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:3px;line-height:1.3">{{ $sub }}</div>
        </div>
        @endforeach
    </div>

    {{-- ════ Bloc 4 Commit 13 (2026-06-18) — CA RÉEL mensuel (2 lignes) ════
         Graphique Chart.js à 2 séries (HT facturé + TTC encaissé) alimenté
         par CaRealService. Indépendant des filtres commune/zone/category
         (cf. Q2 patronne — bandeau d'info géré dans _kpis.blade.php).
         Sélecteur d'année partagé avec le graphique CA contractuel ci-dessous
         (même `ca_year` → 1 submit, 2 graphiques rafraîchis). ─────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">📊 CA réel mensuel {{ $caMensuelYear ?? $annee }} — HT facturé / TTC encaissé</span>
            <select name="ca_year" form="form-periode" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()"
                    style="margin-left:auto;height:28px;padding:0 8px;background:var(--surface2);border:1px solid var(--border);border-radius:6px;font-size:12px;color:var(--text);cursor:pointer"
                    title="Choisir l'année à explorer (indépendant du filtre période global)">
                @foreach($anneesDisponibles ?? [date('Y'), date('Y')-1, date('Y')-2] as $y)
                    <option value="{{ $y }}" {{ (int)($caMensuelYear ?? $annee) === (int)$y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div style="font-size:11px;color:var(--text3);font-style:italic;margin-bottom:14px">
            ℹ️ Année calendaire complète · indépendant des filtres commune/zone/catégorie · basé sur les factures émises (HT) et les paiements reçus (TTC)
        </div>
        <div style="position:relative;width:100%;height:260px">
            <canvas id="chart-ca-real" role="img" aria-label="CA réel mensuel — HT facturé et TTC encaissé"></canvas>
        </div>
    </div>

    {{-- Graphique CA CONTRACTUEL mensuel (bars) — Q4 patronne : on garde
         l'existant car il intègre les filtres dimensionnels et reste utile
         pour les analyses d'activité commerciale par zone. Libellé clarifié. --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;flex-wrap:wrap">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)" title="CA contractuel basé sur Campaign.total_amount — utile pour le pilotage commercial avec filtres zone/commune">CA contractuel mensuel {{ $caMensuelYear ?? $annee }}</span>
        </div>
        <div style="font-size:11px;color:var(--text3);font-style:italic;margin-bottom:14px">
            ℹ️ Année calendaire complète · suit tous les filtres dimensionnels (zone, commune, client, type)
        </div>
        <div id="chart-ca" style="display:flex;align-items:flex-end;gap:6px;height:140px"></div>
        <div id="chart-ca-labels" style="display:flex;gap:6px;margin-top:6px"></div>
    </div>

    {{-- Courbe Chart.js : CA contractuel mensuel sur 12 mois glissants (réservations) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Évolution du CA contractuel — 12 derniers mois</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Réservations confirmées + terminées</span>
        </div>
        <div style="position:relative;width:100%;height:260px">
            <canvas id="chart-revenue-trend" role="img" aria-label="CA contractuel mensuel 12 mois"></canvas>
        </div>
    </div>

    {{-- Corrélation occupation × revenus (scatter) — COMMIT B --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="9" cy="14" r="1"/><circle cx="14" cy="9" r="1"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)">Corrélation occupation × revenus par commune</span>
            <span style="margin-left:auto;font-size:10px;color:var(--text3);font-style:italic">Identifier les zones sous-monétisées</span>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-bottom:12px;line-height:1.5">
            Chaque point = une commune. <strong style="color:#22c55e">Haut-droite</strong> : occupation forte + CA élevé (zones moteurs).
            <strong style="color:#ef4444">Bas-droite</strong> : occupation forte mais CA faible (tarif sous-évalué).
            <strong style="color:#f59e0b">Haut-gauche</strong> : CA élevé sur peu de panneaux occupés (rareté précieuse).
        </div>
        <div style="position:relative;width:100%;height:340px">
            <canvas id="chart-occ-revenue" role="img" aria-label="Corrélation occupation revenus"></canvas>
        </div>
    </div>

    {{-- 🏆 Classement communes les plus rentables (top 15) --}}
    @if($revenueByCommune->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)" title="Classement basé sur le CA contractuel généré par les campagnes (suit les filtres dimensionnels)">Classement communes les plus rentables (CA contractuel)</span>
            <span style="margin-left:auto;font-size:11px;color:var(--text3)">Top {{ $revenueByCommune->count() }}</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Commune','CA généré','Campagnes','Panneaux loués','CA / panneau'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueByCommune as $i => $r)
                        @php $caPerPanel = $r->panels_engaged > 0 ? round((float)$r->revenue / $r->panels_engaged) : 0; @endphp
                        <tr style="border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s"
                            onclick="CommuneDrilldown.open({{ $r->id }})"
                            title="Cliquer pour voir le détail commune"
                            onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                            <td style="padding:10px 16px;font-size:13px;color:var(--text3);font-weight:700">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i + 1)) }}</td>
                            <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $r->commune }}</td>
                            <td style="padding:10px 16px;font-size:13px;font-weight:700;color:#16a34a;font-variant-numeric:tabular-nums">{{ number_format((float) $r->revenue, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                            <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->campaigns_count) }}</td>
                            <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->panels_engaged) }}</td>
                            <td style="padding:10px 16px;font-size:11px;color:var(--text3);font-variant-numeric:tabular-nums">{{ $caPerPanel > 0 ? number_format($caPerPanel, 0, ',', ' ') . ' FCFA' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- CA par ville (vue agrégée) — COMMIT B --}}
    @if($revenueByCity->isNotEmpty())
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden;margin-bottom:16px">
        <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0ea5e9" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)" title="CA contractuel agrégé par ville — basé sur Campaign.total_amount">CA contractuel par ville (vue agrégée)</span>
            <span style="margin-left:auto;font-size:11px;color:var(--text3)">{{ $revenueByCity->count() }} villes</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Ville','CA','Campagnes','Panneaux loués','Communes'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($revenueByCity as $i => $r)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:13px;color:var(--text3);font-weight:700">{{ $i === 0 ? '🥇' : ($i === 1 ? '🥈' : ($i === 2 ? '🥉' : $i + 1)) }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $r->city }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:var(--accent)">{{ number_format((float) $r->revenue, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->campaigns_count) }}</td>
                        <td style="padding:10px 16px;font-size:12px;color:var(--text)">{{ number_format($r->panels_engaged) }}</td>
                        <td style="padding:10px 16px;font-size:11px;color:var(--text3)">{{ $r->communes_count }} commune(s)</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:8px">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span style="font-size:13px;font-weight:700;color:var(--text)" title="Basé sur le total contractuel des campagnes (Campaign.total_amount) — pas sur les factures émises">Top clients — CA contractuel sur la période</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr style="border-bottom:1px solid var(--border)">
                        @foreach(['#','Client','CA contractuel','Campagnes','Panneaux'] as $h)
                        <th style="padding:10px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($topClients as $i => $client)
                    <tr style="border-bottom:1px solid var(--border);transition:background .1s" onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background=''">
                        <td style="padding:10px 16px;font-size:14px">{{ $i===0?'🥇':($i===1?'🥈':($i===2?'🥉':$i+1)) }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:600;color:var(--text)">{{ $client->name }}</td>
                        <td style="padding:10px 16px;font-size:13px;font-weight:700;color:var(--accent)">{{ number_format($client->ca_total, 0, ',', ' ') }} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client->nb_campagnes) }}</td>
                        <td style="padding:10px 16px;font-size:13px;color:var(--text)">{{ number_format($client->total_panneaux ?? 0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text3)">Aucun client sur cette période</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
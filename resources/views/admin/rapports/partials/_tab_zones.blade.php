<div id="panel-zones" class="rpt-panel" style="display:none">

    {{-- ── Bandeau exports dédiés (2026-07-01) ──────────────────
         Export du tableau par commune : total, occupés, libres, maint.,
         taux, tarif moyen, CA. Respecte tous les filtres actifs (période,
         zone, commune, ville, catégorie). --}}
    <div style="background:linear-gradient(90deg,#eff6ff,#e0f2fe);border:1px solid #93c5fd;border-radius:12px;padding:12px 16px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <div style="display:flex;align-items:center;gap:10px">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1d4ed8" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
            <div>
                <div style="font-size:12px;font-weight:800;color:#1e3a8a">Exporter l'occupation par commune / ville</div>
                <div style="font-size:10px;color:#1e40af;margin-top:2px">Total panneaux, occupés, libres, taux et CA par commune · respecte les filtres actifs</div>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('admin.rapports.export.zones-communes-excel', request()->query()) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg>
                Excel
            </a>
            <a href="{{ route('admin.rapports.export.zones-communes-pdf', request()->query()) }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF
            </a>
        </div>
    </div>

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
                style="font-size:12px;padding:6px 14px;border-radius:8px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);cursor:pointer;transition:all .15s"
                title="CA contractuel basé sur les campagnes (Campaign.total_amount)">
            CA contractuel annuel
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
                        @foreach(['Commune','Total','Occupés','Libres','Maint.','Taux','Tarif moy.','CA contractuel ' . $annee] as $h)
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

    {{-- Bloc 'Panneaux à décaper — 30 prochains jours' retiré du tab Zones
         le 2026-06-17 (user : doit rester dans l'onglet Décapages uniquement
         pour éviter la duplication). KPI card 'À décaper (30j)' en haut +
         tab Décapages ($decapList, $upcomingEndings) couvrent le besoin. --}}
</div>
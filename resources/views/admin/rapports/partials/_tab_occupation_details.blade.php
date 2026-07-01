{{--
    Onglet "Occupation détaillée" — /admin/rapports
    Ajouté 2026-07-01 (demande patronne)

    Contexte : la patronne cherchait "concrètement" qui occupait quels
    panneaux sur un trimestre (avril-juin). L'info existait dans
    /admin/taxes/details mais mal placée sémantiquement (module Taxes).
    Migrée ici sous forme d'un onglet dédié avec exports.

    Colonnes validées (AskUserQuestion 2026-07-01) :
    Panneau (réf + nom + commune + dimensions/m²) — Campagne (nom + statut)
    — Client (nom + secteur) — Période (dates + durée mois+jours).

    Filtres respectés : période, commune, ville, client, type panneau, zone.
    Les filtres du haut de page s'appliquent (partagés avec les autres onglets).
--}}
<div id="panel-occupation-details" class="rpt-panel">

    {{-- ── Bandeau récap ──────────────────────────────────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                    <span style="font-size:14px;font-weight:800;color:var(--text)">Panneaux occupés × Campagnes</span>
                </div>
                <div style="font-size:11px;color:var(--text3);line-height:1.5">
                    Une ligne par couple <b>Panneau × Campagne</b> dont la campagne chevauche la période sélectionnée
                    (<b>{{ \Carbon\Carbon::parse($dateFrom)->format('d/m/Y') }}</b> → <b>{{ \Carbon\Carbon::parse($dateTo)->format('d/m/Y') }}</b>).<br>
                    Statuts campagne inclus : planifié, actif, en pause, terminé (les annulées sont exclues).
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.rapports.export.occupation-details-excel', request()->query()) }}"
                   style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#22c55e;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M9 13l2 2 4-4"/></svg>
                    Excel
                </a>
                <a href="{{ route('admin.rapports.export.occupation-details-pdf', request()->query()) }}"
                   style="display:inline-flex;align-items:center;gap:6px;padding:8px 14px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-size:11px;font-weight:700;text-decoration:none;text-transform:uppercase;letter-spacing:.5px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    PDF
                </a>
            </div>
        </div>

        {{-- Petits KPI en ligne --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px">
            @php $S = $occupationDetailsSummary; @endphp
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#e8a020">{{ number_format($S['total_rows'] ?? 0, 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Lignes</div>
            </div>
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#3b82f6">{{ number_format($S['nb_panels'] ?? 0, 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Panneaux</div>
            </div>
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#a855f7">{{ number_format($S['nb_campaigns'] ?? 0, 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Campagnes</div>
            </div>
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#f97316">{{ number_format($S['nb_clients'] ?? 0, 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Clients</div>
            </div>
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#22c55e">{{ number_format($S['nb_communes'] ?? 0, 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Communes</div>
            </div>
            @if(($S['nb_externals'] ?? 0) > 0)
            <div style="background:var(--surface2);border-radius:10px;padding:10px 12px;text-align:center">
                <div style="font-size:22px;font-weight:800;color:#6b7280">{{ number_format($S['nb_externals'], 0, ',', ' ') }}</div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-top:2px">Externes (pige)</div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Recherche libre en JS ──────────────────────────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 16px;margin-bottom:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="occ-details-search"
               placeholder="Filtre libre : panneau, client, campagne, commune…"
               style="flex:1;min-width:200px;padding:8px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:12px"
               oninput="RPT.filterOccDetails(this.value)">
        <span id="occ-details-count" style="font-size:11px;color:var(--text3);font-weight:600"></span>
    </div>

    {{-- ── Tableau ─────────────────────────────────────────────── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;overflow:hidden">
        @if($occupationDetails->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:var(--text3)">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="opacity:.5;margin-bottom:12px">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                <div style="font-size:13px;font-weight:600;margin-bottom:4px">Aucune occupation sur cette période</div>
                <div style="font-size:11px">Ajustez la période ou les filtres pour voir des résultats.</div>
            </div>
        @else
        <div style="overflow-x:auto">
            <table id="occ-details-table" style="width:100%;border-collapse:collapse;font-size:12px">
                <thead>
                    <tr style="background:var(--surface2);border-bottom:2px solid var(--border)">
                        <th style="padding:12px 10px;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);white-space:nowrap">Commune</th>
                        <th style="padding:12px 10px;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">Panneau</th>
                        <th style="padding:12px 10px;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);white-space:nowrap">Dimensions</th>
                        <th style="padding:12px 10px;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">Campagne</th>
                        <th style="padding:12px 10px;text-align:left;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3)">Client</th>
                        <th style="padding:12px 10px;text-align:center;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);white-space:nowrap">Période campagne</th>
                        <th style="padding:12px 10px;text-align:right;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);white-space:nowrap">Durée</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($occupationDetails as $r)
                    @php
                        $st = (string) $r['campaign_status'];
                        $statusColor = match($st) {
                            'actif'    => '#22c55e',
                            'planifie' => '#3b82f6',
                            'termine'  => '#6b7280',
                            'pause'    => '#f97316',
                            'annule'   => '#dc2626',
                            default    => '#94a3b8',
                        };
                        $statusLabel = match($st) {
                            'actif'    => 'Actif',
                            'planifie' => 'Planifié',
                            'termine'  => 'Terminé',
                            'pause'    => 'En pause',
                            'annule'   => 'Annulé',
                            default    => $st,
                        };
                    @endphp
                    <tr class="occ-details-row"
                        data-search="{{ strtolower($r['commune'].' '.$r['panel_ref'].' '.$r['panel_name'].' '.$r['campaign_name'].' '.$r['client_name'].' '.$r['client_sector']) }}"
                        style="border-bottom:1px solid var(--border);transition:background .15s"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background='transparent'">
                        <td style="padding:10px;color:var(--text);white-space:nowrap">
                            <div style="font-weight:600">{{ $r['commune'] }}</div>
                            @if($r['city'] !== $r['commune'])
                                <div style="font-size:10px;color:var(--text3)">{{ $r['city'] }}</div>
                            @endif
                        </td>
                        <td style="padding:10px;color:var(--text)">
                            <div style="font-weight:700;font-family:monospace;font-size:11px;color:#e8a020">{{ $r['panel_ref'] }}</div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px">{{ $r['panel_name'] }}</div>
                            @if($r['is_external'])
                                <span style="display:inline-block;margin-top:3px;padding:1px 6px;background:#6b7280;color:#fff;font-size:9px;font-weight:700;border-radius:4px;text-transform:uppercase;letter-spacing:.4px">Externe</span>
                            @endif
                        </td>
                        <td style="padding:10px;color:var(--text2);white-space:nowrap;font-size:11px">
                            {{ $r['panel_dims'] }}
                            @if($r['panel_type'] && $r['panel_type'] !== '—')
                                <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $r['panel_type'] }}</div>
                            @endif
                        </td>
                        <td style="padding:10px;color:var(--text)">
                            <a href="{{ route('admin.campaigns.show', $r['campaign_id']) }}"
                               style="color:var(--text);text-decoration:none;font-weight:600" target="_blank">
                                {{ $r['campaign_name'] }}
                            </a>
                            <div style="margin-top:3px">
                                <span style="display:inline-block;padding:2px 8px;background:{{ $statusColor }}22;color:{{ $statusColor }};font-size:10px;font-weight:700;border-radius:6px;text-transform:uppercase;letter-spacing:.4px">{{ $statusLabel }}</span>
                            </div>
                        </td>
                        <td style="padding:10px;color:var(--text)">
                            <div style="font-weight:600">{{ $r['client_name'] }}</div>
                            @if($r['client_sector'] && $r['client_sector'] !== '—')
                                <div style="font-size:10px;color:var(--text3);margin-top:2px">{{ $r['client_sector'] }}</div>
                            @endif
                        </td>
                        <td style="padding:10px;color:var(--text2);text-align:center;white-space:nowrap;font-size:11px">
                            <div>{{ \Carbon\Carbon::parse($r['campaign_start'])->format('d/m/Y') }}</div>
                            <div style="color:var(--text3);font-size:10px">→</div>
                            <div>{{ \Carbon\Carbon::parse($r['campaign_end'])->format('d/m/Y') }}</div>
                        </td>
                        <td style="padding:10px;text-align:right;font-weight:700;color:var(--accent);white-space:nowrap">
                            {{ $r['duration_label'] }}
                            @if($r['decapped_at'])
                                <div style="font-size:9px;color:#dc2626;margin-top:3px;text-transform:uppercase;letter-spacing:.4px" title="Décapé le {{ \Carbon\Carbon::parse($r['decapped_at'])->format('d/m/Y') }}">
                                    Décapé
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    @if($occupationDetails->count() >= 3000)
        <div style="margin-top:12px;padding:10px 14px;background:#f59e0b22;border:1px solid #f59e0b;border-radius:8px;font-size:11px;color:#f59e0b">
            <b>{{ number_format($occupationDetails->count(), 0, ',', ' ') }} lignes</b> affichées. Utilisez les filtres (commune, client, zone) ou réduisez la période pour affiner.
        </div>
    @endif
</div>

<script>
// Filtre libre côté client — ne recharge pas la page.
(function() {
    if (!window.RPT) return;
    window.RPT.filterOccDetails = function(q) {
        const s = (q || '').toLowerCase().trim();
        const rows = document.querySelectorAll('#occ-details-table .occ-details-row');
        let visible = 0;
        rows.forEach(tr => {
            const match = !s || (tr.dataset.search || '').indexOf(s) !== -1;
            tr.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const c = document.getElementById('occ-details-count');
        if (c) c.textContent = s ? `${visible} / ${rows.length} lignes` : '';
    };
})();
</script>

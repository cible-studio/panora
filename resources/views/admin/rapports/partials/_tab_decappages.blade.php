<div id="panel-decap" class="rpt-panel" style="display:none">

    {{-- ⚠ BANDEAU CRITIQUE : campagnes expirées non décapées.
         Toujours rendu mais hidden via data-decap-overdue-banner pour que
         le JS puisse le réafficher en cas d'unmark sans reload de page. --}}
    <div data-decap-overdue-banner
         style="background:linear-gradient(135deg,rgba(220,38,38,.12),rgba(220,38,38,.06));border:1.5px solid rgba(220,38,38,.4);border-radius:14px;padding:16px 20px;margin-bottom:16px;display:{{ ($decapStats['overdue'] ?? 0) > 0 ? 'flex' : 'none' }};align-items:center;gap:14px"
         data-init-display="flex">
        <div style="font-size:32px;line-height:1;animation:rpt-pulse 1.6s ease-in-out infinite;width:44px;height:44px;border-radius:50%;background:rgba(220,38,38,.15);display:flex;align-items:center;justify-content:center">⚠️</div>
        <div style="flex:1">
            <div style="font-size:14px;font-weight:800;color:#dc2626;margin-bottom:3px">
                <span data-decap-overdue-banner-count>{{ $decapStats['overdue'] }}</span> panneau(x) en retard de décapage
            </div>
            <div style="font-size:12px;color:var(--text2);line-height:1.5">Campagne(s) terminée(s) depuis plus de <strong>7 jours</strong> avec affichage non retiré sur le terrain. Risque d'amende municipale et de plainte client. Planifiez les tournées de décapage en priorité.</div>
        </div>
        <a href="#" onclick="event.preventDefault();document.getElementById('decap-overdue-list')?.scrollIntoView({behavior:'smooth',block:'start'});"
           style="padding:8px 14px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-size:11px;font-weight:700;white-space:nowrap">
            Voir les retards →
        </a>
    </div>

    {{-- Bandeau stats décapage (COMMIT C) — data-kpi=* permet la MAJ live
         après mark/unmark sans recharger toute la page. --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px">
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #6366f1;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Panneaux concernés</div>
            <div data-decap-kpi="total" style="font-size:24px;font-weight:800;color:var(--text);margin-top:4px">{{ number_format($decapStats['total']) }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px">90 derniers jours</div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #22c55e;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Décapés</div>
            <div data-decap-kpi="decapped" style="font-size:24px;font-weight:800;color:#16a34a;margin-top:4px">{{ number_format($decapStats['decapped']) }}</div>
            <div style="font-size:10px;color:#16a34a;margin-top:2px;font-weight:600"><span data-decap-kpi="rate">{{ $decapStats['rate'] }}</span>% complétés</div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid #f59e0b;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">En attente</div>
            <div data-decap-kpi="pending" style="font-size:24px;font-weight:800;color:#d97706;margin-top:4px">{{ number_format($decapStats['pending']) }}</div>
            <div style="font-size:10px;color:var(--text3);margin-top:2px">À planifier</div>
        </div>
        <div data-decap-kpi-overdue-card style="background:var(--surface);border:1px solid {{ $decapStats['overdue'] > 0 ? 'rgba(220,38,38,.4)' : 'var(--border)' }};border-left:3px solid #dc2626;border-radius:12px;padding:14px">
            <div style="font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">En retard</div>
            <div data-decap-kpi="overdue" style="font-size:24px;font-weight:800;color:#dc2626;margin-top:4px">{{ number_format($decapStats['overdue']) }}</div>
            <div data-decap-kpi-overdue-sub style="font-size:10px;color:{{ $decapStats['overdue'] > 0 ? '#dc2626' : 'var(--text3)' }};margin-top:2px;font-weight:600">> 7j sans décapage</div>
        </div>
    </div>

    {{-- Campagnes terminées récemment (à décaper) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;gap:12px;flex-wrap:wrap">
            <div style="font-size:13px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2"><polyline points="9 11 12 14 22 4"/></svg>
                Campagnes terminées — à décaper ({{ $decapList->count() }})
            </div>
            {{-- 2026-06-19 — Export PDF "Feuille de décapage" pour les techs.
                 Bouton split : "Toutes" ouvre toutes les campagnes terminées,
                 "Retards uniquement" filtre sur > 7j (gain de papier sur le
                 terrain quand seules les urgentes comptent). --}}
            @if($decapList->isNotEmpty())
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a href="{{ route('admin.rapports.decap.pdf') }}"
                       target="_blank"
                       style="display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#1f2937;color:#fff;border-radius:8px;text-decoration:none;font-size:11.5px;font-weight:700"
                       title="Imprimable A4 — liste des panneaux à décaper avec adresse + GPS + case à cocher">
                        📄 Exporter PDF (tech)
                    </a>
                    @if(($decapStats['overdue'] ?? 0) > 0)
                        <a href="{{ route('admin.rapports.decap.pdf', ['overdue' => 1]) }}"
                           target="_blank"
                           style="display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:#dc2626;color:#fff;border-radius:8px;text-decoration:none;font-size:11.5px;font-weight:700"
                           title="PDF des panneaux en retard de décapage uniquement (> 7j)">
                            ⚠ Retards uniquement
                        </a>
                    @endif
                </div>
            @endif
        </div>
        @if($decapList->isEmpty())
            <div style="padding:32px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune campagne récemment terminée.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach($decapList as $c)
                    @php
                        $daysOverdue   = (int) $c->end_date->diffInDays(now(), false);
                        $isOverdue     = $c->is_overdue;
                        $isComplete    = $c->decapped_count === $c->total_panels;
                    @endphp
                    <details
                        @if($isOverdue && !isset($firstOverdueShown)) id="decap-overdue-list" @php $firstOverdueShown = true; @endphp @endif
                        data-decap-campaign="{{ $c->id }}"
                        data-decap-total="{{ $c->total_panels }}"
                        data-decap-overdue="{{ $isOverdue ? '1' : '0' }}"
                        style="background:var(--surface2);border:1px solid {{ $isOverdue ? 'rgba(220,38,38,.3)' : 'var(--border)' }};border-radius:10px;overflow:hidden">
                        <summary style="padding:12px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;list-style:none">
                            <span data-decap-dot style="flex-shrink:0;width:8px;height:8px;border-radius:50%;background:{{ $isComplete ? '#22c55e' : ($isOverdue ? '#dc2626' : '#f59e0b') }};box-shadow:0 0 0 3px {{ $isComplete ? 'rgba(34,197,94,.2)' : ($isOverdue ? 'rgba(220,38,38,.2)' : 'rgba(245,158,11,.2)') }}"></span>
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    <a href="{{ route('admin.campaigns.show', $c->id) }}" style="color:var(--accent);text-decoration:none" onclick="event.stopPropagation()">{{ $c->name }}</a>
                                    <span style="font-size:11px;color:var(--text3);font-weight:400;margin-left:6px">· {{ $c->client?->name ?? '—' }}</span>
                                </div>
                                <div style="font-size:11px;color:var(--text3);margin-top:2px">
                                    Fin : {{ $c->end_date->format('d/m/Y') }}
                                    @if($isOverdue)
                                        <span style="color:#dc2626;font-weight:700;margin-left:6px">+ {{ $daysOverdue }}j de retard</span>
                                    @else
                                        <span style="margin-left:6px">{{ $daysOverdue }}j depuis fin</span>
                                    @endif
                                </div>
                            </div>
                            <div style="flex-shrink:0;text-align:right">
                                <div data-decap-ratio style="font-size:12px;font-weight:700;color:{{ $isComplete ? '#16a34a' : 'var(--text)' }}">{{ $c->decapped_count }}/{{ $c->total_panels }}</div>
                                <div style="height:4px;width:80px;background:var(--border);border-radius:2px;overflow:hidden;margin-top:4px">
                                    <div data-decap-bar style="height:100%;width:{{ $c->decap_progress }}%;background:{{ $isComplete ? '#22c55e' : ($isOverdue ? '#dc2626' : '#f59e0b') }}"></div>
                                </div>
                                <div style="font-size:10px;color:var(--text3);margin-top:2px"><span data-decap-pct>{{ $c->decap_progress }}</span>% décapés</div>
                            </div>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="2" style="flex-shrink:0"><polyline points="6 9 12 15 18 9"/></svg>
                        </summary>
                        <div style="padding:0 14px 12px;border-top:1px solid var(--border);background:var(--surface)">
                            @if($c->pending_count > 1)
                                <div style="display:flex;justify-content:flex-end;padding:10px 0 2px">
                                    <button type="button" onclick="Decap.markAll({{ $c->id }})"
                                            style="font-size:10.5px;font-weight:700;padding:6px 14px;border:1px solid #22c55e;background:rgba(34,197,94,.1);color:#16a34a;border-radius:6px;cursor:pointer">
                                        ✓✓ Marquer tous décapés ({{ $c->pending_count }})
                                    </button>
                                </div>
                            @endif
                            <table style="width:100%;border-collapse:collapse;font-size:12px;margin-top:10px">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border)">
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Panneau</th>
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Commune</th>
                                        <th style="padding:8px;text-align:left;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Statut</th>
                                        <th style="padding:8px;text-align:right;color:var(--text3);font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.4px">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($c->panels as $p)
                                        @php $isDone = $p->decapped_at !== null; @endphp
                                        <tr id="decap-row-{{ $c->id }}-{{ $p->id }}"
                                            data-decap-row="1"
                                            data-decap-panel="{{ $p->id }}"
                                            data-decap-state="{{ $isDone ? 'done' : 'pending' }}"
                                            style="border-bottom:1px solid var(--border)">
                                            <td style="padding:8px">
                                                <a href="{{ route('admin.panels.show', $p->id) }}" style="font-family:monospace;color:var(--accent);text-decoration:none;font-weight:700">{{ $p->reference }}</a>
                                            </td>
                                            <td style="padding:8px;color:var(--text2)">{{ $p->commune?->name ?? '—' }}</td>
                                            <td style="padding:8px" data-decap-status-cell>
                                                @if($isDone)
                                                    <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(34,197,94,.12);color:#16a34a">✓ Décapé le {{ \Carbon\Carbon::parse($p->decapped_at)->format('d/m H:i') }}</span>
                                                @else
                                                    <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;background:rgba(245,158,11,.12);color:#d97706">En attente</span>
                                                @endif
                                            </td>
                                            <td style="padding:8px;text-align:right" data-decap-action-cell>
                                                @if($isDone)
                                                    <button type="button" onclick="Decap.unmark({{ $c->id }}, {{ $p->id }})"
                                                            style="font-size:10px;font-weight:600;padding:4px 10px;border:1px solid var(--border);background:var(--surface2);color:var(--text3);border-radius:6px;cursor:pointer">
                                                        Annuler
                                                    </button>
                                                @else
                                                    <button type="button" onclick="Decap.mark({{ $c->id }}, {{ $p->id }})"
                                                            style="font-size:10px;font-weight:700;padding:4px 10px;border:none;background:#22c55e;color:#fff;border-radius:6px;cursor:pointer">
                                                        ✓ Marquer décapé
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Campagnes à venir (J+14) --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px">
        <div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Campagnes finissant dans les 14 jours ({{ $upcomingEndings->count() }})
        </div>
        @if($upcomingEndings->isEmpty())
            <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px;font-style:italic">Aucune campagne active ne se termine dans les 14 jours.</div>
        @else
            <div style="display:flex;flex-direction:column;gap:6px">
                @foreach($upcomingEndings as $c)
                    @php $daysLeft = (int) now()->startOfDay()->diffInDays($c->end_date->startOfDay(), false); @endphp
                    <a href="{{ route('admin.campaigns.show', $c->id) }}" style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;text-decoration:none">
                        <div>
                            <div style="font-size:12px;font-weight:600;color:var(--text)">{{ $c->name }}</div>
                            <div style="font-size:10px;color:var(--text3)">{{ $c->client?->name ?? '—' }} · Fin : {{ $c->end_date->format('d/m/Y') }}</div>
                        </div>
                        <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:10px;background:rgba(59,130,246,.1);color:#3b82f6">Dans {{ $daysLeft }}j</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
<x-admin-layout title="{{ $campaign->name }}">

<x-slot:topbarActions>
    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
    @if($can['update'])
    <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
    @endif
    @if($can['delete'])
    <button type="button"
            onclick="openDeleteCampaignShow({{ $campaign->id }}, '{{ addslashes($campaign->name) }}')"
            class="btn btn-ghost btn-sm"
            style="color:var(--red);border-color:rgba(239,68,68,.3);">
        🗑 Supprimer
    </button>
    @endif
</x-slot:topbarActions>

@php
    $statusCfg = $campaign->status->uiConfig();
    $daysLeft  = $campaign->daysRemaining();
    $pct       = $campaign->progressPercent();
    $humanTime = $campaign->humanTimeRemaining();

    $barColor = $pct >= 90 ? '#ef4444' : ($pct >= 70 ? '#e8a020' : '#22c55e');
    $endingSoon = $campaign->isEndingSoon();
@endphp

{{-- ── En-tête ── --}}
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div style="font-weight:800;font-size:22px;color:var(--text);">
        {{ $campaign->name }}
    </div>
    <span style="padding:5px 14px;border-radius:20px;font-size:12px;font-weight:700;
                 background:{{ $statusCfg['bg'] }};color:{{ $statusCfg['color'] }};
                 border:1px solid {{ $statusCfg['border'] }};">
        {{ $statusCfg['icon'] }} {{ $campaign->status->label() }}
    </span>
    @if(isset($campaign->type) && $campaign->type)
    <span style="padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;
                 background:var(--surface2);color:var(--text2);border:1px solid var(--border2);">
        {{ $campaign->type === 'ferme' ? '🔒 Ferme' : '⏳ Option' }}
    </span>
    @endif
</div>

{{-- ── Alerte fin proche ── --}}
@if($endingSoon)
<div style="background:rgba(232,160,32,0.08);border:1px solid rgba(232,160,32,0.3);
            border-radius:10px;padding:12px 16px;margin-bottom:16px;
            display:flex;align-items:center;gap:12px;">
    <span style="font-size:22px;">⚠️</span>
    <div style="flex:1;">
        <div style="font-size:13px;font-weight:700;color:var(--accent);">
            Campagne se terminant bientôt — {{ $daysLeft }} jour(s) restant(s)
        </div>
        <div style="font-size:12px;color:var(--text2);margin-top:2px;">
            Pensez à relancer <strong>{{ $campaign->client?->name }}</strong>
            pour prolongation ou nouvelle campagne.
        </div>
    </div>
    @if($can['update'])
    <a href="#section-prolonger"
       style="font-size:11px;padding:6px 14px;background:var(--accent);
              color:#000;border-radius:7px;font-weight:700;text-decoration:none;
              white-space:nowrap;flex-shrink:0;">
        📅 Prolonger
    </a>
    @endif
</div>
@endif

{{-- ── Layout 2 colonnes ── --}}
<div style="display:grid;grid-template-columns:1fr 300px;gap:16px;margin-bottom:16px;
            align-items:start;">

    {{-- ── Carte Informations ── --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">📋 Informations</span>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">

                {{-- Client --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Client</div>
                    @if($campaign->client?->trashed())
                        <div style="font-weight:600;color:var(--text2);">
                            {{ $campaign->client->name }}
                            <span style="font-size:10px;padding:1px 5px;
                                         background:rgba(239,68,68,.1);color:var(--red);
                                         border-radius:4px;margin-left:4px;">Supprimé</span>
                        </div>
                    @else
                        <a href="{{ route('admin.clients.show', $campaign->client) }}"
                           style="font-weight:700;color:var(--text);text-decoration:none;">
                            {{ $campaign->client?->name ?? '—' }}
                        </a>
                    @endif
                </div>

                {{-- Période --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Période</div>
                    <div style="font-weight:600;">
                        {{ $campaign->start_date->format('d/m/Y') }}
                        → {{ $campaign->end_date->format('d/m/Y') }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:2px;">
                        {{ $campaign->durationHuman() }}
                    </div>
                </div>

                {{-- Montant --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Montant total</div>
                    <div style="font-size:20px;font-weight:800;color:var(--accent);">
                        {{ number_format($campaign->total_amount, 0, ',', ' ') }}
                        <span style="font-size:11px;font-weight:400;color:var(--text3);">FCFA</span>
                    </div>
                </div>

                {{-- Réservation liée --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Réservation liée</div>
                    @if($campaign->reservation)
                        <a href="{{ route('admin.reservations.show', $campaign->reservation) }}"
                           style="color:var(--accent);font-weight:600;text-decoration:none;
                                  font-family:monospace;">
                            {{ $campaign->reservation->reference }} →
                        </a>
                    @else
                        <span style="color:var(--text3);font-size:12px;">Aucune</span>
                    @endif
                </div>

                {{-- Créée par --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Créée par</div>
                    <div style="font-size:13px;font-weight:500;">
                        {{ $campaign->user?->name ?? '—' }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);">
                        {{ $campaign->created_at->format('d/m/Y à H:i') }}
                    </div>
                </div>

                {{-- Dernière modif --}}
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">Dernière modif.</div>
                    <div style="font-size:11px;color:var(--text3);">
                        {{ $campaign->updated_at->format('d/m/Y à H:i') }}
                    </div>
                    @if($campaign->updatedBy)
                    <div style="font-size:11px;color:var(--text3);">
                        par {{ $campaign->updatedBy->name }}
                    </div>
                    @endif
                </div>
            </div>

            @if($campaign->notes)
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                            letter-spacing:.6px;margin-bottom:6px;">Notes</div>
                <p style="font-size:13px;color:var(--text2);line-height:1.6;">
                    {{ $campaign->notes }}
                </p>
            </div>
            @endif

            {{-- Barre progression ── --}}
            @if($campaign->status->value === 'actif')
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--border);">
                <div style="display:flex;justify-content:space-between;align-items:center;
                            margin-bottom:6px;">
                    <span style="font-size:11px;color:var(--text3);font-weight:700;
                                 letter-spacing:.5px;">PROGRESSION</span>
                    <span style="font-size:12px;font-weight:600;
                                 color:{{ $daysLeft === 0 ? 'var(--red)' : ($daysLeft <= 7 ? 'var(--accent)' : 'var(--text2)') }};">
                        @if($daysLeft === 0)
                            ⚡ {{ $humanTime }}
                        @else
                            {{ $humanTime }}
                        @endif
                    </span>
                </div>
                <div style="background:var(--surface3);border-radius:6px;
                            height:8px;overflow:hidden;">
                    <div style="background:{{ $barColor }};
                                width:{{ $pct }}%;height:100%;
                                border-radius:6px;transition:width .5s;"></div>
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:4px;
                            display:flex;justify-content:space-between;">
                    <span>{{ $pct }}% de la période écoulée</span>
                    @if($daysLeft > 0)
                    <span style="color:var(--text2);">
                        {{ $daysLeft }} jour(s) restant(s)
                    </span>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ── Colonne droite : Actions + Facturation ── --}}
    <div style="display:flex;flex-direction:column;gap:12px;">

        {{-- Actions ── --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">⚡ Actions</span>
            </div>
            <div class="card-body">

                {{-- Badge statut --}}
                <div style="padding:14px;background:{{ $statusCfg['bg'] }};
                            border:1px solid {{ $statusCfg['border'] }};
                            border-radius:10px;text-align:center;margin-bottom:14px;">
                    <div style="font-size:22px;margin-bottom:4px;">{{ $statusCfg['icon'] }}</div>
                    <div style="font-size:14px;font-weight:800;color:{{ $statusCfg['color'] }};">
                        {{ $campaign->status->label() }}
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px;">
                        {{ $statusCfg['description'] }}
                    </div>
                </div>

                {{-- Boutons de transition — chacun dans son form ── --}}
                @if($can['updateStatus'] && !empty($allowed))
                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;">
                    @foreach($allowed as $val => $label)
                    @php
                        $btnStyle = match($val) {
                            'termine' => 'background:rgba(107,114,128,0.12);color:#9ca3af;border:1px solid rgba(107,114,128,0.35);',
                            'annule'  => 'background:rgba(239,68,68,0.08);color:#ef4444;border:1px solid rgba(239,68,68,0.3);',
                            'actif'   => 'background:rgba(34,197,94,0.08);color:#22c55e;border:1px solid rgba(34,197,94,0.3);',
                            default   => 'background:var(--surface2);color:var(--text);border:1px solid var(--border2);',
                        };
                        $btnIcon = match($val) {
                            'termine' => '✅',
                            'annule'  => '🚫',
                            'actif'   => '▶️',
                            default   => '→',
                        };
                    @endphp
                    <form method="POST"
                          action="{{ route('admin.campaigns.update-status', $campaign) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="{{ $val }}">
                        <button type="submit"
                                style="width:100%;padding:10px 14px;border-radius:8px;
                                       font-size:13px;font-weight:600;cursor:pointer;
                                       text-align:left;display:flex;align-items:center;
                                       gap:8px;transition:opacity .15s;{{ $btnStyle }}"
                                onmouseover="this.style.opacity='.8'"
                                onmouseout="this.style.opacity='1'"
                                @if($val === 'annule')
                                onclick="return confirm('Confirmer l\'annulation ?\nLes panneaux seront libérés immédiatement.')"
                                @endif>
                            <span style="font-size:15px;">{{ $btnIcon }}</span>
                            <span>{{ $label }}</span>
                        </button>
                    </form>
                    @endforeach
                </div>
                @else
                <p style="font-size:12px;color:var(--text3);text-align:center;padding:8px 0;">
                    Aucune transition disponible.
                </p>
                @endif

                {{-- Prolonger ── --}}
                @if($can['update'] && in_array($campaign->status->value, ['actif','termine']))
                <div id="section-prolonger"
                     style="padding-top:12px;border-top:1px solid var(--border);"
                     x-data="{ show: false }">
                    <button type="button"
                            class="btn btn-ghost btn-sm"
                            style="width:100%;"
                            @click="show = !show">
                        📅 Prolonger la campagne
                    </button>
                    <div x-show="show" x-transition style="margin-top:10px;">
                        <form method="POST"
                              action="{{ route('admin.campaigns.prolonger', $campaign) }}">
                            @csrf @method('PATCH')
                            <label style="font-size:10px;color:var(--text3);font-weight:700;
                                          letter-spacing:.5px;display:block;margin-bottom:5px;">
                                NOUVELLE DATE DE FIN
                                <span style="font-weight:400;color:var(--text3);">
                                    (actuelle : {{ $campaign->end_date->format('d/m/Y') }})
                                </span>
                            </label>
                            <input type="date" name="new_end_date"
                                   min="{{ $campaign->end_date->addDay()->format('Y-m-d') }}"
                                   class="filter-input"
                                   style="width:100%;margin-bottom:8px;box-sizing:border-box;">
                            <button type="submit" class="btn btn-primary"
                                    style="width:100%;">
                                ✅ Confirmer la prolongation
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Facturation ── --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">💰 Facturation</span>
            </div>
            <div class="card-body">
                @if($campaign->invoices->isNotEmpty())
                @foreach($campaign->invoices as $inv)
                <div style="display:flex;justify-content:space-between;align-items:center;
                            padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;">
                    <span style="font-family:monospace;font-size:11px;color:var(--accent);">
                        {{ $inv->reference ?? '#'.$inv->id }}
                    </span>
                    <span style="font-weight:600;">
                        {{ number_format($inv->amount_ttc, 0, ',', ' ') }} FCFA
                    </span>
                </div>
                @endforeach
                @else
                <div style="text-align:center;padding:16px;color:var(--text3);">
                    <div style="font-size:22px;margin-bottom:6px;">💰</div>
                    <div style="font-size:12px;font-weight:600;color:var(--accent);">
                        À facturer
                    </div>
                    <div style="font-size:11px;color:var(--text3);margin-top:3px;">
                        Aucune facture émise.
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- ══ PANNEAUX ══ --}}
<div class="card" style="margin-bottom:16px;" x-data="panneauxManager()">
    <div class="card-header">
        <span class="card-title">🪧 Panneaux ({{ $campaign->panels->count() }})</span>
        @if($can['managePanel'])
        <button type="button" class="btn btn-ghost btn-sm"
                @click="showAdd = !showAdd"
                x-text="showAdd ? '✕ Annuler' : '+ Ajouter un panneau'">
        </button>
        @endif
    </div>

    @if($can['managePanel'])
    <div x-show="showAdd" x-transition
         style="border-bottom:1px solid var(--border);padding:16px;
                background:var(--surface2);">
        <form method="POST" action="{{ route('admin.campaigns.panels.add', $campaign) }}">
            @csrf

            <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;">
                <div style="flex:1;min-width:200px;">
                    <label style="display:block;font-size:10px;color:var(--text3);
                                   font-weight:700;letter-spacing:.5px;margin-bottom:5px;">
                        RECHERCHER UN PANNEAU LIBRE
                    </label>
                    <input type="text"
                           placeholder="Référence, nom, commune…"
                           x-model="search"
                           @input.debounce.250ms="filterPanels()"
                           class="filter-input" style="width:100%;"/>
                </div>
                <div>
                    <label style="display:block;font-size:10px;color:var(--text3);
                                   font-weight:700;letter-spacing:.5px;margin-bottom:5px;">
                        COMMUNE
                    </label>
                    <select x-model="filterCommune" @change="filterPanels()"
                            class="filter-select" style="font-size:12px;">
                        <option value="">Toutes</option>
                        @foreach($communes as $c)
                        <option value="{{ $c->name }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:10px;color:var(--text3);
                                   font-weight:700;letter-spacing:.5px;margin-bottom:5px;">
                        FORMAT
                    </label>
                    <select x-model="filterFormat" @change="filterPanels()"
                            class="filter-select" style="font-size:12px;">
                        <option value="">Tous</option>
                        @foreach($formats as $f)
                        <option value="{{ $f->name }}">{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Liste résultats --}}
            <div style="max-height:220px;overflow-y:auto;border:1px solid var(--border2);
                        border-radius:8px;background:var(--surface);">
                <template x-if="filteredPanels.length === 0">
                    <div style="text-align:center;padding:24px;
                                color:var(--text3);font-size:13px;">
                        Aucun panneau libre trouvé.
                    </div>
                </template>
                <template x-for="p in filteredPanels" :key="p.id">
                    <label style="display:flex;align-items:center;gap:12px;
                                  padding:9px 14px;border-bottom:1px solid var(--border);
                                  cursor:pointer;transition:background .1s;"
                           :style="selectedPanels.includes(p.id)
                               ? 'background:rgba(232,160,32,0.08);'
                               : ''"
                           onmouseover="this.style.background='var(--surface2)'"
                           onmouseout="this.style.background=selectedPanels.includes(p.id)
                               ? 'rgba(232,160,32,0.08)' : ''">
                        <input type="checkbox"
                               :value="p.id"
                               x-model="selectedPanels"
                               name="panel_ids[]"
                               style="accent-color:var(--accent);width:15px;height:15px;">
                        <div style="flex:1;min-width:0;">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <span style="font-family:monospace;font-size:12px;
                                             font-weight:700;color:var(--accent);"
                                      x-text="p.reference"></span>
                                <span style="font-size:13px;font-weight:500;color:var(--text);
                                             overflow:hidden;text-overflow:ellipsis;
                                             white-space:nowrap;"
                                      x-text="p.name"></span>
                            </div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px;
                                        display:flex;gap:8px;flex-wrap:wrap;">
                                <span x-text="p.commune || '—'"></span>
                                <span x-text="p.format  || '—'"></span>
                                <span x-show="p.monthly_rate"
                                      style="color:var(--accent);font-weight:600;"
                                      x-text="Number(p.monthly_rate).toLocaleString('fr-FR') + ' FCFA/mois'">
                                </span>
                            </div>
                        </div>
                    </label>
                </template>
            </div>

            <div x-show="selectedPanels.length > 0"
                 style="margin-top:10px;display:flex;align-items:center;
                        justify-content:space-between;gap:10px;">
                <span style="font-size:13px;color:var(--text2);">
                    <strong style="color:var(--text);"
                            x-text="selectedPanels.length"></strong>
                    panneau(x) sélectionné(s)
                    — Montant estimé :
                    <strong style="color:var(--accent);"
                            x-text="formatEstimate()"></strong> FCFA
                </span>
                <button type="submit" class="btn btn-primary btn-sm">
                    ✅ Ajouter à la campagne
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Tableau panneaux --}}
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Désignation</th>
                    <th>Commune</th>
                    <th>Format</th>
                    <th>Éclairé</th>
                    <th style="text-align:right;">Prix/mois</th>
                    <th style="text-align:right;">Total période</th>
                    <th>Statut panneau</th>
                    @if($can['managePanel'])<th></th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse($campaign->panels as $panel)
                @php
                    $ps      = $panel->status->value;
                    $psColor = match($ps) {
                        'confirme'    => ['#22c55e','rgba(34,197,94,0.08)','rgba(34,197,94,0.3)'],
                        'option'      => ['#e8a020','rgba(232,160,32,0.08)','rgba(232,160,32,0.3)'],
                        'libre'       => ['#6b7280','rgba(107,114,128,0.08)','rgba(107,114,128,0.3)'],
                        'maintenance' => ['#ef4444','rgba(239,68,68,0.08)','rgba(239,68,68,0.3)'],
                        default       => ['#6b7280','rgba(107,114,128,0.08)','rgba(107,114,128,0.3)'],
                    };
                @endphp
                <tr style="transition:background .12s;"
                    onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background=''">
                    <td>
                        <span style="font-family:monospace;font-size:12px;font-weight:700;
                                     color:var(--accent);">
                            {{ $panel->reference }}
                        </span>
                    </td>
                    <td style="font-weight:500;">{{ $panel->name }}</td>
                    <td style="color:var(--text2);font-size:12px;">
                        {{ $panel->commune?->name ?? '—' }}
                    </td>
                    <td style="color:var(--text2);font-size:12px;">
                        {{ $panel->format?->name ?? '—' }}
                    </td>
                    <td>
                        @if($panel->is_lit)
                        <span style="color:var(--accent);font-size:12px;">💡 Oui</span>
                        @else
                        <span style="color:var(--text3);font-size:12px;">Non</span>
                        @endif
                    </td>
                    <td style="text-align:right;color:var(--text2);font-size:12px;">
                        {{ $panel->monthly_rate
                            ? number_format($panel->monthly_rate, 0, ',', ' ') . ' FCFA'
                            : '—' }}
                    </td>
                    <td style="text-align:right;font-weight:600;color:var(--accent);
                               font-size:12px;">
                        @if($panel->monthly_rate)
                        {{ number_format(
                            $panel->monthly_rate * $campaign->durationInMonths(),
                            0, ',', ' '
                        ) }} FCFA
                        @else
                        —
                        @endif
                    </td>
                    <td>
                        <span style="padding:3px 9px;border-radius:20px;font-size:11px;
                                     font-weight:600;
                                     background:{{ $psColor[1] }};
                                     color:{{ $psColor[0] }};
                                     border:1px solid {{ $psColor[2] }};">
                            {{ $panel->status->label() }}
                        </span>
                    </td>
                    @if($can['managePanel'])
                    <td>
                        <button type="button"
                                onclick="openRetirePanel({{ $panel->id }},
                                    '{{ addslashes($panel->reference) }}')"
                                class="btn btn-ghost btn-sm"
                                style="color:var(--red);font-size:11px;">
                            ✕ Retirer
                        </button>
                    </td>
                    @endif
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $can['managePanel'] ? 9 : 8 }}"
                        style="text-align:center;padding:40px;color:var(--text3);">
                        Aucun panneau lié à cette campagne.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Modals --}}
<div id="modal-delete-show" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeDeleteShow()">
    <div class="modal" style="max-width:420px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--red);">🗑 Supprimer</div>
            <button class="modal-close" onclick="closeDeleteShow()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:28px 22px;">
            <div style="font-size:44px;margin-bottom:12px;">🗑</div>
            <div style="font-weight:700;font-size:15px;margin-bottom:8px;">
                Supprimer <span id="del-show-name"
                                style="color:var(--accent);"></span> ?
            </div>
            <div style="font-size:13px;color:var(--text2);margin-bottom:14px;">
                Tous les panneaux liés seront détachés et libérés.
            </div>
            <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);
                        border-radius:8px;padding:10px;font-size:12px;color:var(--red);">
                ⚠️ Uniquement possible si la campagne est annulée.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeDeleteShow()">Annuler</button>
            <form id="del-show-form" method="POST" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">🗑 Supprimer définitivement</button>
            </form>
        </div>
    </div>
</div>

<div id="modal-retire-panel" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeRetirePanel()">
    <div class="modal" style="max-width:400px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">✕ Retirer le panneau</div>
            <button class="modal-close" onclick="closeRetirePanel()">✕</button>
        </div>
        <div class="modal-body" style="text-align:center;padding:24px;">
            <div style="font-size:36px;margin-bottom:10px;">🪧</div>
            <div style="font-weight:700;margin-bottom:8px;">
                Retirer <span id="retire-ref"
                              style="color:var(--accent);font-family:monospace;"></span> ?
            </div>
            <div style="font-size:13px;color:var(--text2);">
                Le panneau sera détaché et son statut recalculé.
                Le montant total sera mis à jour.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeRetirePanel()">Annuler</button>
            <form id="retire-panel-form" method="POST" style="display:inline;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger">✕ Retirer</button>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const AVAILABLE_PANELS = {!! json_encode(
    $availablePanels->map(fn($p) => [
        'id'           => $p->id,
        'reference'    => $p->reference,
        'name'         => $p->name,
        'commune'      => $p->commune?->name ?? '',
        'format'       => $p->format?->name ?? '',
        'monthly_rate' => (float)($p->monthly_rate ?? 0),
        'is_lit'       => (bool)$p->is_lit,
    ])->values()->toArray()
) !!};

const CAMPAIGN_MONTHS = {{ $campaign->durationInMonths() }};

function panneauxManager() {
    return {
        showAdd:       false,
        search:        '',
        filterCommune: '',
        filterFormat:  '',
        selectedPanels:[],
        allPanels:     AVAILABLE_PANELS,
        filteredPanels:[],

        init() {
            this.filteredPanels = this.allPanels;
        },

        filterPanels() {
            const s  = this.search.toLowerCase().trim();
            const fc = this.filterCommune.toLowerCase();
            const ff = this.filterFormat.toLowerCase();

            this.filteredPanels = this.allPanels.filter(p => {
                const ms = !s  || p.reference.toLowerCase().includes(s)
                               || p.name.toLowerCase().includes(s)
                               || p.commune.toLowerCase().includes(s);
                const mc = !fc || p.commune.toLowerCase() === fc;
                const mf = !ff || p.format.toLowerCase()  === ff;
                return ms && mc && mf;
            });
        },

        formatEstimate() {
            const total = this.selectedPanels.reduce((sum, id) => {
                const p = this.allPanels.find(x => x.id === id);
                return sum + (p ? p.monthly_rate * CAMPAIGN_MONTHS : 0);
            }, 0);
            return Math.round(total).toLocaleString('fr-FR');
        },
    };
}

function openDeleteCampaignShow(id, name) {
    document.getElementById('del-show-name').textContent = name;
    document.getElementById('del-show-form').action = `/admin/campaigns/${id}`;
    document.getElementById('modal-delete-show').style.display = 'flex';
}
function closeDeleteShow() {
    document.getElementById('modal-delete-show').style.display = 'none';
}
function openRetirePanel(panelId, ref) {
    document.getElementById('retire-ref').textContent = ref;
    document.getElementById('retire-panel-form').action =
        `/admin/campaigns/{{ $campaign->id }}/panels/${panelId}`;
    document.getElementById('modal-retire-panel').style.display = 'flex';
}
function closeRetirePanel() {
    document.getElementById('modal-retire-panel').style.display = 'none';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeDeleteShow(); closeRetirePanel(); }
});
</script>
@endpush

</x-admin-layout>
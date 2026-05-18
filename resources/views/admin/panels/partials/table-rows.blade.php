{{-- resources/views/admin/panels/partials/table-rows.blade.php --}}
@if($source !== 'externe')
@forelse($panels as $panel)
<tr>
    <td style="vertical-align:middle;">
        @php
            $photo = $panel->photos->first();
            $photoPath = $photo ? storage_path('app/public/'.$photo->path) : null;
            $hasFile = $photo && file_exists($photoPath);
        @endphp
        @if($hasFile)
            <img src="{{ asset('storage/'.$photo->path) }}"
                 alt="{{ $panel->reference }}"
                 loading="lazy"
                 onclick="openLightbox(this.src)"
                 onerror="this.onerror=null;this.src='/images/panel-placeholder.svg';"
                 style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:zoom-in;">
        @else
            <img src="/images/panel-placeholder.svg" alt="placeholder"
                 style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid var(--border);background:var(--surface2);">
        @endif
    </td>
    <td style="vertical-align:middle;"><span style="font-family:monospace;color:var(--accent);font-weight:700;">{{ $panel->reference }}</span></td>
    <td style="vertical-align:middle;"><div style="font-weight:500;">{{ $panel->name }}</div><div style="font-size:11px;color:var(--text3);">{{ $panel->category?->name ?? '—' }} @if($panel->is_lit) · 💡 @endif</div></td>
    <td style="vertical-align:middle;">{{ $panel->commune->name }}</td>
    <td style="vertical-align:middle;">
        @if($panel->format->dimensions_label)<div style="font-weight:500;">{{ $panel->format->dimensions_label }}</div>@endif
        @if($panel->format->surface_label)<div style="font-size:11px;color:var(--text3);">{{ $panel->format->surface_label }}</div>@elseif(!$panel->format->dimensions_label)<div>{{ $panel->format->name }}</div>@endif
    </td>
    <td style="text-align:center;vertical-align:middle;"><span style="font-weight:700;color:var(--text2);">{{ $panel->nombre_faces ?? 1 }}</span></td>
    <td style="vertical-align:middle;">@if($panel->quartier)<div style="font-weight:500;font-size:12px;">{{ $panel->quartier }}</div>@endif @if($panel->adresse)<div style="font-size:11px;color:var(--text3);">{{ $panel->adresse }}</div>@endif @if(!$panel->quartier && !$panel->adresse)<span style="color:var(--text3);">—</span>@endif</td>
    <td style="color:var(--accent);font-weight:600;vertical-align:middle;">{{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA</td>
    <td style="vertical-align:middle;">
        @php
            $statusVal = $panel->status->value;
            $statusBadgeClass = match($statusVal) {
                'libre' => 'badge-green', 'option' => 'badge-orange',
                'confirme' => 'badge-blue', 'occupe' => 'badge-purple',
                default => 'badge-red',
            };
            // Labels courts pour tenir dans la cellule (cf. PanelStatus::shortLabel)
            $statusLabel = $panel->status->shortLabel();
            // Tooltip explicatif = description longue de l'enum
            $statusDesc  = $panel->status->uiConfig()['description'] ?? '';
            // Seuls libre et maintenance sont basculables manuellement —
            // les autres statuts sont dérivés des réservations/campagnes
            // (voir AvailabilityService::syncPanelStatuses).
            $canToggleMaintenance = in_array($statusVal, ['libre', 'maintenance']);
            // URL "Voir disponibilité" pour comprendre ce qui bloque
            $availabilityUrl = route('admin.panels.availability', $panel);
        @endphp
        @if($canToggleMaintenance)
            <div class="panel-status-wrap" data-panel-id="{{ $panel->id }}" style="position:relative;display:inline-block;">
                <button type="button" class="badge {{ $statusBadgeClass }} panel-status-trigger"
                        onclick="PanelStatus.toggleMenu({{ $panel->id }})"
                        title="{{ $statusDesc }}{{ $statusVal === 'libre' ? ' · Cliquer pour gérer la maintenance' : ' · Cliquer pour sortir de maintenance' }}"
                        style="border:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-family:inherit;">
                    <span class="panel-status-label">{{ $statusLabel }}</span>
                    <span style="font-size:9px;opacity:.7;">▾</span>
                </button>
                <div class="panel-status-menu" id="panel-menu-{{ $panel->id }}" style="display:none;position:absolute;top:calc(100% + 4px);left:0;background:var(--surface);border:1px solid var(--border);border-radius:8px;padding:4px;min-width:200px;z-index:100;box-shadow:0 6px 20px rgba(0,0,0,.15);">
                    @if($statusVal === 'libre')
                        <button type="button" class="panel-status-action" data-status="maintenance"
                                onclick="PanelStatus.set({{ $panel->id }}, 'maintenance')"
                                style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;background:none;border:none;border-radius:6px;cursor:pointer;font-size:12px;color:var(--text);text-align:left;"
                                onmouseenter="this.style.background='rgba(239,68,68,.1)'" onmouseleave="this.style.background='none'">
                            🛠 Mettre en maintenance
                        </button>
                    @else
                        <button type="button" class="panel-status-action" data-status="libre"
                                onclick="PanelStatus.set({{ $panel->id }}, 'libre')"
                                style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;background:none;border:none;border-radius:6px;cursor:pointer;font-size:12px;color:var(--text);text-align:left;"
                                onmouseenter="this.style.background='rgba(34,197,94,.1)'" onmouseleave="this.style.background='none'">
                            ✅ Sortir de maintenance
                        </button>
                    @endif
                    <a href="{{ $availabilityUrl }}"
                       style="display:flex;align-items:center;gap:8px;width:100%;padding:8px 10px;text-decoration:none;border-radius:6px;font-size:12px;color:var(--text2);border-top:1px solid var(--border);margin-top:4px"
                       onmouseenter="this.style.background='var(--surface2)'" onmouseleave="this.style.background='none'">
                        📅 Voir disponibilité
                    </a>
                </div>
            </div>
        @else
            {{-- Statut auto (option/confirme/occupe) : badge cliquable qui pointe
                 vers la fiche disponibilité pour comprendre qui bloque. --}}
            <a href="{{ $availabilityUrl }}" class="badge {{ $statusBadgeClass }}"
               title="{{ $statusDesc }} · Cliquer pour voir quelle réservation/campagne bloque ce panneau"
               style="text-decoration:none;cursor:pointer">
                {{ $statusLabel }}
            </a>
        @endif
        @if(($showOccupants ?? false) && $panel->relationLoaded('campaigns') && $panel->campaigns->isNotEmpty())
            @php $occ = $panel->campaigns->first(); @endphp
            <div style="margin-top:5px;padding-top:5px;border-top:1px solid var(--border);font-size:11px;line-height:1.5;">
                <div style="font-weight:600;color:var(--text2);">{{ $occ->client?->name ?? '—' }}</div>
                <a href="{{ route('admin.campaigns.show', $occ->id) }}"
                   style="color:var(--accent);text-decoration:none;display:block;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                   title="{{ $occ->name }}">{{ $occ->name }}</a>
                <div style="color:var(--text3);">→ {{ $occ->end_date->format('d/m/Y') }}</div>
            </div>
        @endif
    </td>
    <td style="vertical-align:middle;"><div style="display:flex;gap:6px;"><a href="{{ route('admin.panels.show', $panel) }}" class="btn btn-ghost btn-sm" title="Voir">👁️</a><a href="{{ route('admin.panels.edit', $panel) }}" class="btn btn-ghost btn-sm" title="Modifier">✏️</a><a href="{{ route('admin.panels.pdf', $panel) }}" class="btn btn-ghost btn-sm" title="PDF">📄</a><form method="POST" action="{{ route('admin.panels.destroy', $panel) }}" onsubmit="return confirm('Supprimer ce panneau ?')">@csrf @method('DELETE')<button class="btn btn-danger btn-sm" title="Supprimer">🗑️</button></form></div></td>
</tr>
@empty
@if($source === 'cible' || ($externalPanels ?? collect())->isEmpty())
<tr><td colspan="10" style="text-align:center;color:var(--text3);padding:32px;">Aucun panneau trouvé</td></tr>
@endif
@endforelse
@endif

{{-- PANNEAUX EXTERNES --}}
@if($source !== 'cible' && isset($externalPanels) && $externalPanels->isNotEmpty())
@if($source === 'all' && ($panels ?? collect())->isNotEmpty())
<tr><td colspan="10" style="padding:8px 12px;background:rgba(168,85,247,0.06);border-top:2px solid rgba(168,85,247,0.3);border-bottom:1px solid rgba(168,85,247,0.2);"><span style="font-size:11px;font-weight:700;color:var(--purple);text-transform:uppercase;letter-spacing:1px;">🏢 Panneaux — Régies externes ({{ $externalPanels->count() }})</span></td></tr>
@endif

@foreach($externalPanels as $ext)
<tr style="background:rgba(168,85,247,0.02);">
    <td style="vertical-align:middle;">
        @if($ext->photo_path && file_exists(storage_path('app/public/'.$ext->photo_path)))
            <img src="{{ asset('storage/'.$ext->photo_path) }}"
                 alt="{{ $ext->code_panneau }}"
                 loading="lazy"
                 onclick="openLightbox(this.src)"
                 style="width:60px;height:45px;object-fit:cover;border-radius:6px;border:1px solid rgba(168,85,247,0.2);cursor:zoom-in;">
        @else
            <div style="width:60px;height:45px;border-radius:6px;border:1px solid rgba(168,85,247,0.2);background:rgba(168,85,247,0.08);display:flex;align-items:center;justify-content:center;color:var(--purple);font-size:16px;">🏢</div>
        @endif
    </td>
    <td style="vertical-align:middle;">
        <span style="font-family:monospace;color:var(--purple);font-weight:700;">{{ $ext->code_panneau }}</span>
        @if($ext->agency)
        <div style="margin-top:2px;"><span style="font-size:10px;padding:1px 6px;border-radius:4px;background:rgba(168,85,247,0.12);color:var(--purple);font-weight:600;">{{ $ext->agency->name }}</span></div>
        @endif
    </td>
    <td style="vertical-align:middle;"><div style="font-weight:500;">{{ $ext->designation }}</div><div style="font-size:11px;color:var(--text3);">{{ $ext->category?->name ?? '—' }} @if($ext->is_lit) · 💡 @endif</div></td>
    <td style="vertical-align:middle;">{{ $ext->commune?->name ?? '—' }}</td>
    <td style="vertical-align:middle;">
        @if($ext->format?->dimensions_label)<div style="font-weight:500;">{{ $ext->format->dimensions_label }}</div>@endif
        @if($ext->format?->surface_label)<div style="font-size:11px;color:var(--text3);">{{ $ext->format->surface_label }}</div>@elseif(!$ext->format?->dimensions_label)<div>{{ $ext->format?->name ?? '—' }}</div>@endif
    </td>
    <td style="text-align:center;vertical-align:middle;"><span style="font-weight:700;color:var(--text2);">{{ $ext->nombre_faces ?? 1 }}</span></td>
    <td style="vertical-align:middle;">@if($ext->quartier)<div style="font-weight:500;font-size:12px;">{{ $ext->quartier }}</div>@endif @if($ext->adresse)<div style="font-size:11px;color:var(--text3);">{{ $ext->adresse }}</div>@endif @if(!$ext->quartier && !$ext->adresse)<span style="color:var(--text3);">—</span>@endif @if($ext->orientation)<span class="badge badge-gray mt-1">{{ ucfirst($ext->orientation) }}</span>@endif</td>
    <td style="color:var(--purple);font-weight:600;vertical-align:middle;">@if($ext->monthly_rate > 0){{ number_format($ext->monthly_rate, 0, ',', ' ') }} FCFA @else<span style="color:var(--text3);">—</span>@endif</td>
    <td style="vertical-align:middle;"><span style="font-size:11px;padding:2px 8px;border-radius:20px;background:rgba(168,85,247,0.12);color:var(--purple);border:1px solid rgba(168,85,247,0.3);font-weight:600;">Externe</span></td>
    <td style="vertical-align:middle;"><a href="{{ route('admin.external-agencies.show', $ext->agency_id) }}" class="btn btn-ghost btn-sm" title="Voir la régie">👁️</a></td>
</tr>
@endforeach
@endif

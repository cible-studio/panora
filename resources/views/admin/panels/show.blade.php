<x-admin-layout>
<x-slot name="title">{{ $panel->reference }}</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.panels.pdf', $panel) }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    <a href="{{ route('admin.panels.edit', $panel) }}" class="btn btn-ghost btn-sm">
        ✏️ Modifier
    </a>
    <a href="{{ route('admin.panels.availability', $panel) }}" class="btn btn-blue btn-sm">
        📅 Disponibilités
    </a>
</x-slot>

<div style="display:grid; grid-template-columns:1fr 320px; gap:20px;">

    {{-- COLONNE GAUCHE --}}
    <div>

        {{-- INFOS PRINCIPALES --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">{{ $panel->name }}</div>
                    <div style="font-size:12px; color:var(--text3); margin-top:3px;">
                        Réf : <span style="color:var(--accent); font-family:monospace;">
                            {{ $panel->reference }}
                        </span>
                    </div>
                </div>
                @if($panel->status->value === 'libre')
                    <span class="badge badge-green" style="font-size:13px; padding:5px 14px;">Libre</span>
                @elseif($panel->status->value === 'option')
                    <span class="badge badge-orange" style="font-size:13px; padding:5px 14px;">Option</span>
                @elseif($panel->status->value === 'confirme')
                    <span class="badge badge-blue" style="font-size:13px; padding:5px 14px;">Confirmé</span>
                @elseif($panel->status->value === 'occupe')
                    <span class="badge badge-purple" style="font-size:13px; padding:5px 14px;">Occupé</span>
                @else
                    <span class="badge badge-red" style="font-size:13px; padding:5px 14px;">Maintenance</span>
                @endif
            </div>
            <div class="card-body">

                {{-- INFOS DE BASE --}}
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">COMMUNE</div>
                        <div style="font-weight:600;">{{ $panel->commune->name }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">ZONE</div>
                        <div style="font-weight:600;">{{ $panel->zone?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">FORMAT</div>
                        <div style="font-weight:600;">{{ $panel->format->name }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">CATÉGORIE</div>
                        <div style="font-weight:600;">{{ $panel->category?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TARIF MENSUEL</div>
                        <div style="font-weight:600; color:var(--accent);">
                            {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                        </div>
                    </div>
                    <div>
                        <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">ÉCLAIRÉ</div>
                        <div style="font-weight:600;">{{ $panel->is_lit ? '💡 Oui' : 'Non' }}</div>
                    </div>
                </div>

                {{-- CARACTÉRISTIQUES TECHNIQUES --}}
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--accent); margin-bottom:10px;
                                font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                        Caractéristiques techniques
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">NOMBRE DE FACES</div>
                            <div style="font-weight:600;">{{ $panel->nombre_faces ?? 1 }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TYPE SUPPORT</div>
                            <div style="font-weight:600;">{{ $panel->type_support ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">ORIENTATION</div>
                            <div style="font-weight:600;">
                                {{ $panel->orientation ? ucfirst($panel->orientation) : '—' }}
                            </div>
                        </div>
                        @if($panel->format->width && $panel->format->height)
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DIMENSIONS</div>
                            <div style="font-weight:600;">
                                {{ $panel->format->width }}m × {{ $panel->format->height }}m
                            </div>
                        </div>
                        @endif
                        @if($panel->format->surface)
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">SURFACE</div>
                            <div style="font-weight:600;">{{ $panel->format->surface }} m²</div>
                        </div>
                        @endif
                        @if($panel->daily_traffic)
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TRAFIC/JOUR</div>
                            <div style="font-weight:600;">
                                {{ number_format($panel->daily_traffic, 0, ',', ' ') }} véhicules
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- LOCALISATION --}}
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--accent); margin-bottom:10px;
                                font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                        Localisation
                    </div>
                    <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">ADRESSE</div>
                            <div style="font-weight:600;">{{ $panel->adresse ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">QUARTIER</div>
                            <div style="font-weight:600;">{{ $panel->quartier ?? '—' }}</div>
                        </div>
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">AXE ROUTIER</div>
                            <div style="font-weight:600;">{{ $panel->axe_routier ?? '—' }}</div>
                        </div>
                    </div>
                </div>

                {{-- DESCRIPTION --}}
                @if($panel->zone_description)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">
                        DESCRIPTION EMPLACEMENT
                    </div>
                    <div style="color:var(--text2);">{{ $panel->zone_description }}</div>
                </div>
                @endif

                {{-- GPS --}}
                @if($panel->latitude && $panel->longitude)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">
                        COORDONNÉES GPS
                    </div>
                    <div style="font-family:monospace; color:var(--text2);">
                        {{ $panel->latitude }}, {{ $panel->longitude }}
                    </div>
                </div>
                @endif

            </div>
        </div>

        {{-- PHOTOS --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📸 Photos ({{ $panel->photos->count() }})</div>
            </div>
            <div class="card-body">
                @if($panel->photos->count() > 0)
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px;">
                    @foreach($panel->photos as $photo)
                    <div style="position:relative;border-radius:8px;overflow:hidden;border:1px solid var(--border);"
                         onmouseover="this.querySelector('.photo-actions').style.opacity='1';this.querySelector('.photo-actions').style.background='rgba(0,0,0,0.55)'"
                         onmouseout="this.querySelector('.photo-actions').style.opacity='0';this.querySelector('.photo-actions').style.background='rgba(0,0,0,0)'">
                        <img src="{{ asset('storage/'.$photo->path) }}"
                             style="width:100%;height:120px;object-fit:cover;display:block;">
                        <div class="photo-actions"
                             style="position:absolute;inset:0;opacity:0;transition:all .2s;
                                    display:flex;align-items:center;justify-content:center;gap:6px;">
                            {{-- Remplacer --}}
                            <form method="POST" action="{{ route('admin.panels.photos', $panel) }}"
                                  enctype="multipart/form-data" id="rep-{{ $photo->id }}">
                                @csrf
                                <input type="file" name="photo" accept="image/*"
                                       id="rep-inp-{{ $photo->id }}" style="display:none;"
                                       onchange="document.getElementById('rep-{{ $photo->id }}').submit()">
                                <label for="rep-inp-{{ $photo->id }}"
                                       style="cursor:pointer;padding:5px 10px;border-radius:6px;
                                              font-size:11px;font-weight:600;background:var(--surface);
                                              color:var(--text);border:1px solid var(--border2);">
                                    ✏️ Changer
                                </label>
                            </form>
                            {{-- Supprimer --}}
                            <form method="POST"
                                  action="{{ route('admin.panels.photos.delete', [$panel, $photo]) }}"
                                  onsubmit="return confirm('Supprimer cette photo ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        style="padding:5px 10px;border-radius:6px;font-size:11px;
                                               font-weight:600;background:rgba(239,68,68,0.9);
                                               color:white;border:none;cursor:pointer;">
                                    🗑️
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif

                <form method="POST" action="{{ route('admin.panels.photos', $panel) }}"
                      enctype="multipart/form-data"
                      style="display:flex;gap:10px;align-items:center;">
                    @csrf
                    <input type="file" name="photo" accept="image/*"
                           style="color:var(--text2);flex:1;">
                    <button type="submit" class="btn btn-ghost btn-sm">➕ Ajouter</button>
                </form>
            </div>
        </div>

        {{-- OCCUPANTS ACTUELS --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 Occupants actuels</div>
            </div>
            @if($occupants->isEmpty())
            <div class="card-body" style="text-align:center;padding:28px;color:var(--text3);font-size:13px;">
                @if($panel->status->value === 'libre')
                    ✅ Ce panneau est libre — aucun client associé.
                @elseif($panel->status->value === 'maintenance')
                    🔧 Panneau en maintenance.
                @else
                    Aucun occupant actif trouvé.
                @endif
            </div>
            @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Source</th>
                            <th>Période</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($occupants as $occ)
                        @php
                            $sc = match($occ['status']) {
                                'actif','confirme' => ['bg'=>'rgba(34,197,94,0.1)',  'color'=>'#22c55e', 'border'=>'rgba(34,197,94,0.3)'],
                                'en_attente','option' => ['bg'=>'rgba(232,160,32,0.1)','color'=>'#e8a020','border'=>'rgba(232,160,32,0.3)'],
                                'pose'             => ['bg'=>'rgba(59,130,246,0.1)', 'color'=>'#3b82f6', 'border'=>'rgba(59,130,246,0.3)'],
                                default            => ['bg'=>'rgba(107,114,128,0.1)','color'=>'#6b7280','border'=>'rgba(107,114,128,0.3)'],
                            };
                        @endphp
                        <tr onmouseover="this.style.background='var(--surface2)'"
                            onmouseout="this.style.background=''">
                            <td>
                                @if($occ['client'])
                                <a href="{{ route('admin.clients.show', $occ['client']) }}"
                                   style="font-weight:700;color:var(--text);text-decoration:none;
                                          display:flex;align-items:center;gap:6px;">
                                    <span style="font-size:18px;">👤</span>
                                    {{ $occ['client']->name }}
                                </a>
                                @else
                                <span style="color:var(--text3);">—</span>
                                @endif
                            </td>
                            <td>
                                @if($occ['type'] === 'campaign')
                                <a href="{{ route('admin.campaigns.show', $occ['source_id']) }}"
                                   style="font-size:12px;color:#3b82f6;text-decoration:none;font-weight:600;">
                                    📢 {{ $occ['reference'] }}
                                </a>
                                @else
                                <a href="{{ route('admin.reservations.show', $occ['source_id']) }}"
                                   style="font-size:12px;color:var(--accent);text-decoration:none;font-weight:600;">
                                    📋 {{ $occ['reference'] }}
                                </a>
                                @endif
                            </td>
                            <td style="font-size:11px;color:var(--text3);white-space:nowrap;">
                                {{ \Carbon\Carbon::parse($occ['start_date'])->format('d/m/Y') }}
                                → {{ \Carbon\Carbon::parse($occ['end_date'])->format('d/m/Y') }}
                            </td>
                            <td>
                                <span style="padding:3px 10px;border-radius:20px;font-size:11px;
                                             font-weight:700;text-transform:uppercase;letter-spacing:.5px;
                                             background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                                             border:1px solid {{ $sc['border'] }};">
                                    {{ $occ['status_label'] }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

    </div>
    <div>

        {{-- CHANGER STATUT --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Changer statut</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.panels.status', $panel) }}">
                    @csrf
                    <div class="mfg">
                        <select name="status">
                            <option value="libre"       {{ $panel->status->value === 'libre'       ? 'selected' : '' }}>🟢 Libre</option>
                            <option value="option"      {{ $panel->status->value === 'option'      ? 'selected' : '' }}>🟡 Option</option>
                            <option value="confirme"    {{ $panel->status->value === 'confirme'    ? 'selected' : '' }}>🔵 Confirmé</option>
                            <option value="occupe"      {{ $panel->status->value === 'occupe'      ? 'selected' : '' }}>🟣 Occupé</option>
                            <option value="maintenance" {{ $panel->status->value === 'maintenance' ? 'selected' : '' }}>🔴 Maintenance</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">
                        Mettre à jour
                    </button>
                </form>
            </div>
        </div>

        {{-- TARIFS --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Tarifs</div>
            </div>
            <div class="card-body">
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12px; color:var(--text3);">Mensuel</span>
                        <span style="font-weight:700; color:var(--accent);">
                            {{ number_format($panel->monthly_rate, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12px; color:var(--text3);">Trimestriel</span>
                        <span style="font-weight:600;">
                            {{ number_format($panel->monthly_rate * 3, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:12px; color:var(--text3);">Annuel</span>
                        <span style="font-weight:600;">
                            {{ number_format($panel->monthly_rate * 12, 0, ',', ' ') }} FCFA
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- MAINTENANCES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔧 Maintenances</div>
                <a href="{{ route('admin.maintenances.create') }}?panel_id={{ $panel->id }}"
                   class="btn btn-ghost btn-sm">＋</a>
            </div>
            <div class="card-body">
                @forelse($panel->maintenances->take(3) as $maintenance)
                <div style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--border);">
                    <div style="font-size:12px; font-weight:600;">{{ $maintenance->type_panne }}</div>
                    <div style="font-size:11px; color:var(--text3);">
                        {{ $maintenance->date_signalement->format('d/m/Y') }}
                    </div>
                    @if($maintenance->statut === 'resolu')
                        <span class="badge badge-green" style="margin-top:4px;">Résolu</span>
                    @else
                        <span class="badge badge-red" style="margin-top:4px;">En cours</span>
                    @endif
                </div>
                @empty
                <div style="color:var(--text3); font-size:13px; text-align:center;">
                    Aucune maintenance
                </div>
                @endforelse
            </div>
        </div>

        {{-- PIGES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📸 Dernières piges</div>
            </div>
            <div class="card-body">
                @forelse($panel->piges->take(3) as $pige)
                <div style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--border);">
                    <div style="font-size:12px; color:var(--text2);">
                        {{ $pige->taken_at->format('d/m/Y') }}
                    </div>
                    @if($pige->is_verified)
                        <span class="badge badge-green" style="margin-top:4px;">✓ Vérifiée</span>
                    @else
                        <span class="badge badge-gray" style="margin-top:4px;">En attente</span>
                    @endif
                </div>
                @empty
                <div style="color:var(--text3); font-size:13px; text-align:center;">
                    Aucune pige
                </div>
                @endforelse
            </div>
        </div>

    </div>

</div>

</x-admin-layout>

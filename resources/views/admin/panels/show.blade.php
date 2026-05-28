<x-admin-layout>
    <x-slot name="title">{{ $panel->reference }}</x-slot>

    <x-slot:topbarLeft>
        <a href="{{ route('admin.panels.index') }}" class="btn btn-ghost btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
            Retour
        </a>
    </x-slot:topbarLeft>

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

    {{-- ALERTE MAINTENANCE ACTIVE (placée au-dessus de tout) --}}
    @isset($activeMaintenance)
    @if($activeMaintenance)
    <div style="background:linear-gradient(90deg,rgba(239,68,68,.10),rgba(239,68,68,.04));border:1px solid rgba(239,68,68,.4);border-left:4px solid #ef4444;border-radius:12px;padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
        <div style="display:flex;align-items:center;gap:14px;flex:1;min-width:240px;">
            <div style="font-size:24px;">🔧</div>
            <div>
                <div style="font-weight:700;color:#dc2626;font-size:14px;">
                    Maintenance en cours · {{ $activeMaintenance->type_panne }}
                </div>
                <div style="font-size:12px;color:var(--text2);margin-top:3px;">
                    @if($activeMaintenance->technicien)
                        Technicien : <strong>{{ $activeMaintenance->technicien->name }}</strong>
                    @else
                        <span style="color:#dc2626;">⚠️ Aucun technicien assigné</span>
                    @endif
                    · Priorité : <strong>{{ ucfirst($activeMaintenance->priorite) }}</strong>
                    · Signalée le {{ $activeMaintenance->date_signalement?->format('d/m/Y') }}
                </div>
            </div>
        </div>
        <a href="{{ route('admin.maintenances.show', $activeMaintenance) }}" class="btn btn-primary btn-sm">
            Voir la fiche maintenance →
        </a>
    </div>
    @endif
    @endisset

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
                    @if ($panel->status->value === 'libre')
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
                            <div style="font-weight:600;">{{ $panel->format->surface_label ?? $panel->format->name }}</div>
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
                        <div>
                            <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">VIP</div>
                            <div style="font-weight:600;">
                                @if($panel->is_vip)
                                    <span style="display:inline-flex;align-items:center;gap:5px;padding:2px 9px;border-radius:20px;background:rgba(168,85,247,.12);color:#a855f7;border:1px solid rgba(168,85,247,.3);font-size:12px;font-weight:700;">
                                        ⭐ Oui
                                    </span>
                                @else
                                    <span style="color:var(--text3);">Non</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- CARACTÉRISTIQUES TECHNIQUES --}}
                    <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                        <div
                            style="font-size:11px; color:var(--accent); margin-bottom:10px;
                                font-weight:700; text-transform:uppercase; letter-spacing:0.5px;">
                            Caractéristiques techniques
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
                            <div>
                                <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">NOMBRE DE FACES
                                </div>
                                <div style="font-weight:600;">{{ $panel->nombre_faces ?? 1 }}</div>
                            </div>
                            <div>
                                <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TYPE SUPPORT</div>
                                <div style="font-weight:600;">{{ $panel->type_support ?? '—' }}</div>
                            </div>
                            @if ($panel->format->dimensions_label)
                                <div>
                                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">DIMENSIONS</div>
                                    <div style="font-weight:600;">{{ $panel->format->dimensions_label }}</div>
                                </div>
                            @endif
                            @if ($panel->format->surface_label)
                                <div>
                                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">SURFACE</div>
                                    <div style="font-weight:600;">{{ $panel->format->surface_label }}</div>
                                </div>
                            @endif
                            @if ($panel->daily_traffic)
                                <div>
                                    <div style="font-size:11px; color:var(--text3); margin-bottom:4px;">TRAFIC/JOUR
                                    </div>
                                    <div style="font-weight:600;">
                                        {{ number_format($panel->daily_traffic, 0, ',', ' ') }} véhicules
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- LOCALISATION --}}
                    <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                        <div
                            style="font-size:11px; color:var(--accent); margin-bottom:10px;
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
                    @if ($panel->zone_description)
                        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                            <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">
                                DESCRIPTION EMPLACEMENT
                            </div>
                            <div style="color:var(--text2);">{{ $panel->zone_description }}</div>
                        </div>
                    @endif

                    {{-- GPS --}}
                    @if ($panel->latitude && $panel->longitude)
                        @php
                            $gpsBadge = match($panel->gps_source) {
                                'manual'           => ['label' => '📍 Saisie manuelle',      'color' => '#6b7280', 'bg' => 'rgba(107,114,128,.12)'],
                                'pige_confirmed'   => ['label' => '✅ Confirmé par piges',   'color' => '#16a34a', 'bg' => 'rgba(34,197,94,.12)'],
                                'pige_provisional' => ['label' => '⏳ Provisoire (1 pige)',  'color' => '#d97706', 'bg' => 'rgba(245,158,11,.12)'],
                                default            => null,
                            };
                        @endphp
                        <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                            <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">
                                COORDONNÉES GPS
                            </div>
                            <div style="font-family:monospace; color:var(--text2);">
                                {{ $panel->latitude }}, {{ $panel->longitude }}
                            </div>
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                                @if ($gpsBadge)
                                    <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;color:{{ $gpsBadge['color'] }};background:{{ $gpsBadge['bg'] }};">
                                        {{ $gpsBadge['label'] }}
                                    </span>
                                @endif
                                @if ($panel->gps_dispersion_flag)
                                    <span style="display:inline-flex;align-items:center;font-size:11px;font-weight:600;padding:2px 8px;border-radius:999px;color:#ef4444;background:rgba(239,68,68,.12);" title="Les piges de ce panneau divergent de plus de 100 m">
                                        ⚠ Positions divergentes
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                </div>
            </div>

            {{-- PHOTOS --}}
            {{-- PHOTOS --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📸 Photos ({{ $panel->photos->count() }})</div>
                </div>

                <div class="card-body">
                    @if ($panel->photos->count() > 0)

                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">

                            @foreach ($panel->photos as $photo)
                                <div style="border-radius:8px;overflow:hidden;border:1px solid var(--border);">
                                    <img src="{{ asset('storage/' . $photo->path) }}"
                                        style="width:100%;height:140px;object-fit:cover;display:block;cursor:pointer;"
                                        onclick="window.open(this.src,'_blank')">
                                </div>
                            @endforeach

                        </div>
                    @else
                        <div style="text-align:center;padding:20px;color:var(--text3);font-size:13px;">
                            Aucune photo disponible
                        </div>

                    @endif
                </div>
            </div>

            {{-- OCCUPANTS ACTUELS --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">👤 Occupants actuels</div>
                </div>
                @if ($occupants->isEmpty())
                    <div class="card-body" style="text-align:center;padding:28px;color:var(--text3);font-size:13px;">
                        @if ($panel->status->value === 'libre')
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
                                @foreach ($occupants as $occ)
                                    @php
                                        $sc = match ($occ['status']) {
                                            'actif', 'confirme' => [
                                                'bg' => 'rgba(34,197,94,0.1)',
                                                'color' => '#22c55e',
                                                'border' => 'rgba(34,197,94,0.3)',
                                            ],
                                            'en_attente', 'option' => [
                                                'bg' => 'rgba(232,160,32,0.1)',
                                                'color' => '#e8a020',
                                                'border' => 'rgba(232,160,32,0.3)',
                                            ],
                                            'pose' => [
                                                'bg' => 'rgba(59,130,246,0.1)',
                                                'color' => '#3b82f6',
                                                'border' => 'rgba(59,130,246,0.3)',
                                            ],
                                            default => [
                                                'bg' => 'rgba(107,114,128,0.1)',
                                                'color' => '#6b7280',
                                                'border' => 'rgba(107,114,128,0.3)',
                                            ],
                                        };
                                    @endphp
                                    <tr onmouseover="this.style.background='var(--surface2)'"
                                        onmouseout="this.style.background=''">
                                        <td>
                                            @if ($occ['client'])
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
                                            @if ($occ['type'] === 'campaign')
                                                {{-- Badge type + lien : on identifie immédiatement
                                                     que c'est une campagne (affichage en cours sur
                                                     le terrain) et non une réservation commerciale. --}}
                                                <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;background:rgba(59,130,246,.12);color:#3b82f6;border:1px solid rgba(59,130,246,.3);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;">
                                                        📢 Campagne
                                                    </span>
                                                    <a href="{{ route('admin.campaigns.show', $occ['source_id']) }}"
                                                        style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#3b82f6;text-decoration:none;font-weight:700;">
                                                        {{ $occ['reference'] }}
                                                    </a>
                                                </div>
                                            @else
                                                {{-- Réservation = engagement commercial signé (sans
                                                     affichage en cours forcément). À distinguer
                                                     visuellement de la campagne active. --}}
                                                <div style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">
                                                    <span style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:4px;background:rgba(194,87,13,.12);color:#c2570d;border:1px solid rgba(194,87,13,.3);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;">
                                                        📋 Réservation
                                                    </span>
                                                    <a href="{{ route('admin.reservations.show', $occ['source_id']) }}"
                                                        style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12px;color:#c2570d;text-decoration:none;font-weight:700;">
                                                        {{ $occ['reference'] }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td style="font-size:11px;color:var(--text3);white-space:nowrap;">
                                            {{ \Carbon\Carbon::parse($occ['start_date'])->format('d/m/Y') }}
                                            → {{ \Carbon\Carbon::parse($occ['end_date'])->format('d/m/Y') }}
                                        </td>
                                        <td>
                                            <span
                                                style="padding:3px 10px;border-radius:20px;font-size:11px;
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

            {{-- CHANGER STATUT (manuels uniquement) --}}
            {{-- Les statuts option / confirmé / occupé sont calculés par
                 AvailabilityService à partir des réservations actives —
                 ils ne sont pas modifiables manuellement pour éviter
                 toute incohérence avec le planning commercial. --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⚡ Changer statut</div>
                </div>
                <div class="card-body">
                    @php
                        $currentStatus = $panel->status->value;
                        $isCalculated = in_array($currentStatus, ['option', 'confirme', 'occupe']);
                    @endphp

                    @if($isCalculated)
                        <div style="background:var(--surface2); border:1px solid var(--border); border-radius:8px; padding:12px; font-size:12px; color:var(--text2); margin-bottom:10px;">
                            🔒 Statut <strong>{{ $currentStatus }}</strong> dérivé automatiquement des réservations actives — non modifiable ici.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.panels.status', $panel) }}">
                        @csrf
                        <div class="mfg">
                            <select name="status" {{ $isCalculated ? 'disabled' : '' }}>
                                <option value="libre"
                                    {{ $currentStatus === 'libre' ? 'selected' : '' }}>🟢 Libre</option>
                                <option value="maintenance"
                                    {{ $currentStatus === 'maintenance' ? 'selected' : '' }}>🔴 Maintenance</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;" {{ $isCalculated ? 'disabled' : '' }}>
                            Mettre à jour
                        </button>
                    </form>

                    @if($currentStatus === 'maintenance')
                        <div style="margin-top:10px; font-size:12px; color:var(--text3);">
                            💡 Pour gérer la panne en cours, utilisez le module
                            <a href="{{ route('admin.maintenances.index') }}" style="color:var(--accent);">Maintenances</a>.
                        </div>
                    @endif
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
                            @if ($maintenance->statut === 'resolu')
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
                            @if ($pige->isVerifiee())
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

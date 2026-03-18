<x-admin-layout>
<x-slot name="title">{{ $panel->reference }}</x-slot>

<x-slot name="topbarActions">
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
                        Réf : <span style="color:var(--accent); font-family:monospace;">{{ $panel->reference }}</span>
                    </div>
                </div>
                {{-- Statut --}}
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

                @if($panel->zone_description)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">DESCRIPTION EMPLACEMENT</div>
                    <div style="color:var(--text2);">{{ $panel->zone_description }}</div>
                </div>
                @endif

                @if($panel->latitude && $panel->longitude)
                <div style="margin-top:16px; padding-top:16px; border-top:1px solid var(--border);">
                    <div style="font-size:11px; color:var(--text3); margin-bottom:6px;">COORDONNÉES GPS</div>
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
                <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin-bottom:16px;">
                    @foreach($panel->photos as $photo)
                    <img src="{{ asset('storage/'.$photo->path) }}"
                         style="width:100%; height:120px; object-fit:cover; border-radius:8px; border:1px solid var(--border);">
                    @endforeach
                </div>
                @endif

                {{-- Ajouter photo --}}
                <form method="POST" action="{{ route('admin.panels.photos', $panel) }}"
                      enctype="multipart/form-data"
                      style="display:flex; gap:10px; align-items:center;">
                    @csrf
                    <input type="file" name="photo" accept="image/*"
                           style="color:var(--text2); flex:1;">
                    <button type="submit" class="btn btn-ghost btn-sm">
                        ➕ Ajouter
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- COLONNE DROITE --}}
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

        {{-- MAINTENANCES --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🔧 Maintenances</div>
            </div>
            <div class="card-body">
                @forelse($panel->maintenances->take(3) as $maintenance)
                <div style="padding-bottom:10px; margin-bottom:10px; border-bottom:1px solid var(--border);">
                    <div style="font-size:12px; font-weight:600;">{{ $maintenance->type_panne }}</div>
                    <div style="font-size:11px; color:var(--text3);">{{ $maintenance->date_signalement->format('d/m/Y') }}</div>
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
                    <div style="font-size:12px; color:var(--text2);">{{ $pige->taken_at->format('d/m/Y') }}</div>
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

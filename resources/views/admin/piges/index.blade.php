<x-admin-layout>
<x-slot name="title">Piges Photos</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.piges.export.pdf') }}" class="btn btn-ghost btn-sm">
        📄 Export PDF
    </a>
    <button onclick="document.getElementById('modal-upload').style.display='flex'"
            class="btn btn-primary btn-sm">
        📷 Uploader une pige
    </button>
</x-slot>

{{-- STATS --}}
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="stat-card">
        <div class="stat-label">Total Piges</div>
        <div class="stat-value">{{ $totalPiges }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Vérifiées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalVerifiees }}</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">En attente</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalEnAttente }}</div>
    </div>
</div>

{{-- FILTRES --}}
<div class="card" style="margin-bottom:16px;">
    <form method="GET" action="{{ route('admin.piges.index') }}">
        <div class="filter-bar">
            <div class="filter-group">
                <label class="filter-label">Campagne</label>
                <select name="campaign_id" class="filter-select">
                    <option value="">Toutes</option>
                    @foreach($campaigns as $campaign)
                    <option value="{{ $campaign->id }}"
                        {{ request('campaign_id') == $campaign->id ? 'selected' : '' }}>
                        {{ $campaign->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Panneau</label>
                <select name="panel_id" class="filter-select">
                    <option value="">Tous</option>
                    @foreach($panels as $panel)
                    <option value="{{ $panel->id }}"
                        {{ request('panel_id') == $panel->id ? 'selected' : '' }}>
                        {{ $panel->reference }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="filter-group">
                <label class="filter-label">Statut</label>
                <select name="is_verified" class="filter-select">
                    <option value="">Tous</option>
                    <option value="1" {{ request('is_verified') === '1' ? 'selected' : '' }}>Vérifiées</option>
                    <option value="0" {{ request('is_verified') === '0' ? 'selected' : '' }}>En attente</option>
                </select>
            </div>
            <div class="filter-group" style="justify-content:flex-end;">
                <label class="filter-label">&nbsp;</label>
                <div style="display:flex; gap:6px;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost btn-sm">✕ Reset</a>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- GRILLE PHOTOS --}}
<div style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
    @forelse($piges as $pige)
    <div class="card" style="margin-bottom:0; overflow:hidden;">

        {{-- PHOTO --}}
        <div style="position:relative;">
            <img src="{{ asset('storage/'.$pige->photo_path) }}"
                 style="width:100%; height:180px; object-fit:cover; display:block;">

            {{-- Badge vérifié --}}
            <div style="position:absolute; top:8px; right:8px;">
                @if($pige->is_verified)
                    <span class="badge badge-green">✓ Vérifiée</span>
                @else
                    <span class="badge badge-orange">En attente</span>
                @endif
            </div>
        </div>

        {{-- INFOS --}}
        <div style="padding:12px;">
            <div style="font-weight:600; font-size:12px; color:var(--accent); font-family:monospace;">
                {{ $pige->panel->reference }}
            </div>
            <div style="font-size:11px; color:var(--text3); margin-top:2px;">
                {{ $pige->panel->commune->name }}
            </div>
            @if($pige->campaign)
            <div style="font-size:11px; color:var(--text2); margin-top:4px;">
                📢 {{ $pige->campaign->name }}
            </div>
            @endif
            <div style="font-size:11px; color:var(--text3); margin-top:4px;">
                📅 {{ $pige->taken_at->format('d/m/Y') }}
            </div>
            <div style="font-size:11px; color:var(--text3);">
                👤 {{ $pige->takenBy->name }}
            </div>

            {{-- ACTIONS --}}
            <div style="display:flex; gap:6px; margin-top:10px;">
                <a href="{{ route('admin.piges.show', $pige) }}"
                   class="btn btn-ghost btn-sm" style="flex:1; text-align:center;">
                    👁️ Voir
                </a>
                @if(!$pige->is_verified)
                <form method="POST"
                      action="{{ route('admin.piges.verify', $pige) }}">
                    @csrf
                    <button class="btn btn-success btn-sm">✓</button>
                </form>
                @endif
                <form method="POST"
                      action="{{ route('admin.piges.destroy', $pige) }}"
                      onsubmit="return confirm('Supprimer ?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm">🗑️</button>
                </form>
            </div>
        </div>

    </div>
    @empty
    <div style="grid-column:span 4; text-align:center; color:var(--text3); padding:40px;">
        <div style="font-size:40px; margin-bottom:12px;">📷</div>
        <div style="font-size:16px; font-weight:600;">Aucune pige photo</div>
        <div style="font-size:13px; margin-top:4px;">Uploadez votre première pige !</div>
    </div>
    @endforelse
</div>

{{-- PAGINATION --}}
<div style="margin-top:20px;">
    {{ $piges->links() }}
</div>

{{-- MODAL UPLOAD --}}
<div id="modal-upload" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📷 Uploader une pige</div>
            <button class="modal-close"
                    onclick="document.getElementById('modal-upload').style.display='none'">
                ✕
            </button>
        </div>
        <form method="POST" action="{{ route('admin.piges.upload') }}"
              enctype="multipart/form-data">
            @csrf
            <div class="modal-body">

                <div class="mfg">
                    <label>Photo *</label>
                    <input type="file" name="photo" accept="image/*"
                           style="color:var(--text2);" required>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Panneau *</label>
                        <select name="panel_id" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($panels as $panel)
                            <option value="{{ $panel->id }}">
                                {{ $panel->reference }} — {{ $panel->commune->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Campagne</label>
                        <select name="campaign_id">
                            <option value="">— Aucune —</option>
                            @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">
                                {{ $campaign->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mfg">
                    <label>Date de prise *</label>
                    <input type="date" name="date_prise"
                           value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>GPS Latitude</label>
                        <input type="number" name="gps_lat"
                               step="0.0000001"
                               placeholder="Ex: 5.3600">
                    </div>
                    <div class="mfg">
                        <label>GPS Longitude</label>
                        <input type="number" name="gps_lng"
                               step="0.0000001"
                               placeholder="Ex: -4.0083">
                    </div>
                </div>

                <div class="mfg">
                    <label>Notes</label>
                    <textarea name="notes" placeholder="Observations..."></textarea>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button"
                        onclick="document.getElementById('modal-upload').style.display='none'"
                        class="btn btn-ghost">
                    Annuler
                </button>
                <button type="submit" class="btn btn-primary">
                    📷 Uploader
                </button>
            </div>
        </form>
    </div>
</div>

</x-admin-layout>

<x-admin-layout title="Modifier pige — {{ $pige->panel?->reference }}">

<x-slot:topbarActions>
    {{-- Retour --}}
    <a href="{{ route('admin.piges.show', $pige) }}" 
       class="btn btn-ghost btn-sm" 
       style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        Retour
    </a>

    {{-- Voir panneau --}}
    @if($pige->panel)
    <a href="{{ route('admin.panels.show', $pige->panel) }}" 
       class="btn btn-ghost btn-sm"
       style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="2" y="3" width="20" height="14" rx="2"/>
            <path d="M8 21h8M12 17v4"/>
        </svg>
        Panneau
    </a>
    @endif

    {{-- Voir campagne --}}
    @if($pige->campaign)
    <a href="{{ route('admin.campaigns.show', $pige->campaign) }}" 
       class="btn btn-ghost btn-sm"
       style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 11l19-9-9 19-2-8-8-2z"/>
        </svg>
        Campagne
    </a>
    @endif

    {{-- Toutes les piges du panneau --}}
    <a href="{{ route('admin.piges.index', array_filter(['campaign_id'=>$pige->campaign_id,'panel_id'=>$pige->panel_id])) }}" 
       class="btn btn-ghost btn-sm"
       style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
        </svg>
        Piges
    </a>

    {{-- Navigation pige précédente / suivante --}}
    @php
        $prevPige = \App\Models\Pige::where('id', '<', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->latest('id')->first();
        $nextPige = \App\Models\Pige::where('id', '>', $pige->id)
            ->when($pige->campaign_id, fn($q) => $q->where('campaign_id', $pige->campaign_id))
            ->oldest('id')->first();
    @endphp

    @if($prevPige)
    <a href="{{ route('admin.piges.edit', $prevPige) }}" 
       class="btn btn-ghost btn-sm" title="Pige précédente"
       style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Préc.
    </a>
    @endif

    @if($nextPige)
    <a href="{{ route('admin.piges.edit', $nextPige) }}" 
       class="btn btn-ghost btn-sm" title="Pige suivante"
       style="display:flex;align-items:center;gap:5px">
        Suiv.
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="9 18 15 12 9 6"/>
        </svg>
    </a>
    @endif
</x-slot:topbarActions>

<div style="max-width:760px;margin:0 auto 40px;padding:0 14px">

    {{-- Bannière info pige --}}
    <div style="background:rgba(232,160,32,.06);border:1px solid rgba(232,160,32,.2);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--text)">Modification de la pige #{{ $pige->id }}</div>
            <div style="font-size:11px;color:var(--text3);margin-top:2px">
                Panneau : <span style="font-family:monospace;color:var(--accent);font-weight:700">{{ $pige->panel?->reference }}</span>
                · Statut actuel : <span style="font-weight:600;color:{{ $pige->getStatusConfig()['color'] }}">{{ $pige->getStatusConfig()['label'] }}</span>
            </div>
        </div>
        @if($pige->isVerifiee())
        <div style="margin-left:auto;font-size:11px;font-weight:700;padding:4px 10px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:8px;white-space:nowrap;flex-shrink:0">
            Pige vérifiée — modifications limitées
        </div>
        @endif
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:16px">
        <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:8px">⚠️ Erreurs</div>
        <ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444;display:flex;flex-direction:column;gap:3px">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.piges.update', $pige) }}" enctype="multipart/form-data" id="edit-form">
        @csrf
        @method('PUT')

        {{-- ══ S1 : INFOS PANNEAU & CAMPAGNE (lecture seule) ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">1</div>
                <div>
                    <div class="pg-section-title">Panneau & Campagne</div>
                    <div class="pg-section-sub">Ces informations ne peuvent pas être modifiées</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label class="pg-label">Panneau</label>
                    <div style="padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;gap:8px">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                        <div>
                            <div style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">{{ $pige->panel?->reference }}</div>
                            <div style="font-size:11px;color:var(--text2)">{{ $pige->panel?->name }}</div>
                            <div style="font-size:10px;color:var(--text3)">📍 {{ $pige->panel?->commune?->name ?? '—' }}</div>
                        </div>
                        <a href="{{ route('admin.panels.show', $pige->panel) }}" style="margin-left:auto;font-size:10px;color:var(--accent);text-decoration:none;padding:2px 8px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.2);border-radius:6px">
                            Voir →
                        </a>
                    </div>
                </div>
                <div>
                    <label class="pg-label">Campagne</label>
                    @if($pige->campaign)
                    <div style="padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;gap:8px">
                        <span style="font-size:16px">{{ $pige->campaign->status->uiConfig()['icon'] }}</span>
                        <div>
                            <div style="font-size:12px;font-weight:600;color:var(--text)">{{ Str::limit($pige->campaign->name, 28) }}</div>
                            <div style="font-size:10px;color:{{ $pige->campaign->status->uiConfig()['color'] }}">{{ $pige->campaign->status->label() }}</div>
                        </div>
                        <a href="{{ route('admin.campaigns.show', $pige->campaign) }}" style="margin-left:auto;font-size:10px;color:var(--accent);text-decoration:none;padding:2px 8px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.2);border-radius:6px">
                            Voir →
                        </a>
                    </div>
                    @else
                    <div style="padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text3);font-style:italic">
                        Sans campagne (contrôle spot)
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ══ S2 : PHOTO ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">2</div>
                <div>
                    <div class="pg-section-title">
                        Photo
                        @if($pige->isVerifiee())
                        <span style="font-size:11px;font-weight:400;color:var(--text3)"> (non modifiable — pige vérifiée)</span>
                        @else
                        <span style="font-size:11px;font-weight:400;color:var(--text3)"> (optionnel — laisser vide pour conserver)</span>
                        @endif
                    </div>
                    <div class="pg-section-sub">Formats acceptés : JPG, PNG, WebP · 30 Mo max</div>
                </div>
            </div>

            {{-- Photo actuelle --}}
            <div style="display:grid;grid-template-columns:180px 1fr;gap:16px;align-items:start">
                <div>
                    <label class="pg-label">Photo actuelle</label>
                    <a href="{{ $pige->getPhotoUrl() }}" target="_blank"
                       style="display:block;border-radius:10px;overflow:hidden;border:1px solid var(--border);aspect-ratio:1;position:relative">
                        <img src="{{ $pige->getThumbUrl() }}" alt="Pige actuelle"
                             style="width:100%;height:100%;object-fit:cover"
                             onerror="this.closest('a').innerHTML='<div style=\'display:flex;align-items:center;justify-content:center;height:100%;color:var(--text3);font-size:11px;flex-direction:column;gap:6px\'><svg width=24 height=24 viewBox=\'0 0 24 24\' fill=none stroke=currentColor stroke-width=1.5><path d=\'M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z\'/><circle cx=12 cy=13 r=4/></svg>Photo indisponible</div>'">
                        <div style="position:absolute;bottom:0;left:0;right:0;padding:4px 6px;background:rgba(0,0,0,.6);font-size:9px;color:#fff;text-align:center">
                            Cliquer pour agrandir
                        </div>
                    </a>
                </div>

                @if(!$pige->isVerifiee())
                <div>
                    <label class="pg-label">Remplacer la photo</label>
                    <div id="new-photo-zone" class="drop-zone-sm"
                         onclick="document.getElementById('photo-input').click()"
                         ondragover="EditPige.onDragOver(event)"
                         ondragleave="EditPige.onDragLeave(event)"
                         ondrop="EditPige.onDrop(event)">
                        <div id="new-photo-idle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;color:var(--text3);opacity:.4">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <div style="font-size:12px;color:var(--text3)">Glissez ou <span style="color:var(--accent);font-weight:600;cursor:pointer">cliquez</span></div>
                            <div style="font-size:10px;color:var(--text3);margin-top:4px;opacity:.7">JPG · PNG · WebP · 30 Mo max</div>
                        </div>
                        <div id="new-photo-preview" style="display:none;width:100%;text-align:center">
                            <img id="new-photo-img" alt="" style="max-height:120px;max-width:100%;border-radius:8px;object-fit:contain">
                            <div id="new-photo-name" style="font-size:10px;color:var(--text3);margin-top:5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></div>
                        </div>
                    </div>
                    <input type="file" id="photo-input" name="photo" accept="image/jpeg,image/png,image/webp"
                           style="display:none" onchange="EditPige.onFileSelect(this.files)">
                    @if($pige->photo_path)
                    <div style="margin-top:8px;display:flex;align-items:center;gap:8px">
                        <input type="checkbox" name="keep_photo" id="keep-photo" value="1" checked
                               style="accent-color:var(--accent);width:14px;height:14px">
                        <label for="keep-photo" style="font-size:11px;color:var(--text3);cursor:pointer">
                            Conserver la photo actuelle si aucune nouvelle n'est choisie
                        </label>
                    </div>
                    @endif
                </div>
                @else
                <div style="padding:14px;background:rgba(34,197,94,.04);border:1px solid rgba(34,197,94,.2);border-radius:10px;font-size:12px;color:#22c55e;display:flex;align-items:center;gap:8px">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    Pige vérifiée — la photo ne peut plus être modifiée pour conserver l'intégrité des preuves.
                </div>
                @endif
            </div>
        </div>

        {{-- ══ S3 : MÉTADONNÉES ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">3</div>
                <div><div class="pg-section-title">Métadonnées</div><div class="pg-section-sub">Informations complémentaires sur la prise de vue</div></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label class="pg-label">Date & heure de prise de vue</label>
                    <input type="datetime-local" name="taken_at" class="pg-input"
                           value="{{ old('taken_at', $pige->taken_at?->format('Y-m-d\TH:i') ?? $pige->created_at->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label class="pg-label">GPS Latitude</label>
                    <input type="number" name="gps_lat" step="0.0000001" class="pg-input"
                           placeholder="5.3464" value="{{ old('gps_lat', $pige->gps_lat) }}">
                </div>
                <div>
                    <label class="pg-label">GPS Longitude</label>
                    <input type="number" name="gps_lng" step="0.0000001" class="pg-input"
                           placeholder="-4.0267" value="{{ old('gps_lng', $pige->gps_lng) }}">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label class="pg-label">Technicien</label>
                    <select name="user_id" class="pg-select">
                        <option value="">— Non renseigné —</option>
                        @foreach($techniciens as $t)
                        <option value="{{ $t->id }}" {{ old('user_id', $pige->user_id)==$t->id?'selected':'' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end">
                    <button type="button" onclick="EditPige.getGeo()" id="btn-geo"
                            style="height:40px;padding:0 14px;font-size:11px;color:var(--accent);background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:10px;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Obtenir ma position GPS
                    </button>
                </div>
            </div>

            <div>
                <label class="pg-label">Notes <span style="font-weight:400;color:var(--text3)">(remarques terrain, conditions d'affichage…)</span></label>
                <textarea name="notes" class="pg-input" style="height:auto;resize:none;padding:10px 12px" rows="3"
                          placeholder="Ex: Visuel bien positionné, éclairage fonctionnel…">{{ old('notes', $pige->notes) }}</textarea>
            </div>
        </div>

        {{-- ══ S4 : STATUT (si pas vérifiée) ══ --}}
        @if(!$pige->isVerifiee())
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">4</div>
                <div><div class="pg-section-title">Statut</div><div class="pg-section-sub">Modifier manuellement le statut de la pige</div></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label class="pg-label">Statut de vérification</label>
                    <select name="status" class="pg-select" id="status-select" onchange="EditPige.onStatusChange(this.value)">
                        <option value="en_attente" {{ old('status',$pige->status)==='en_attente'?'selected':'' }}>⏳ En attente de vérification</option>
                        <option value="verifie"    {{ old('status',$pige->status)==='verifie'?'selected':'' }}>✓ Vérifiée</option>
                        <option value="rejete"     {{ old('status',$pige->status)==='rejete'?'selected':'' }}>✗ Rejetée</option>
                    </select>
                </div>
                <div id="rejection-reason-zone" style="display:{{ $pige->status==='rejete'?'block':'none' }}">
                    <label class="pg-label">Motif de rejet *</label>
                    <input type="text" name="rejection_reason" class="pg-input"
                           value="{{ old('rejection_reason', $pige->rejection_reason) }}"
                           placeholder="Ex: Photo floue, mauvais panneau…">
                </div>
            </div>

            {{-- Motifs rapides --}}
            <div id="quick-reasons" style="display:{{ $pige->status==='rejete'?'flex':'none' }};flex-wrap:wrap;gap:5px;margin-top:8px">
                @foreach(['Photo floue','Mauvais panneau','Visuel non conforme','Date incorrecte','Photo trop sombre','Angle incorrect','Panneau absent'] as $m)
                <button type="button"
                        onclick="document.querySelector('[name=rejection_reason]').value='{{ $m }}'"
                        style="font-size:10px;padding:3px 9px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;cursor:pointer;color:var(--text3)">
                    {{ $m }}
                </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ══ ACTIONS ══ --}}
        <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" class="btn btn-primary" style="min-width:180px;display:flex;align-items:center;justify-content:center;gap:7px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/>
                    <polyline points="17 21 17 13 7 13 7 21"/>
                </svg>
                Enregistrer
            </button>
            <a href="{{ route('admin.piges.show', $pige) }}" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>

<style>
.pg-section        { background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:14px }
.pg-section-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:16px }
.pg-section-title  { font-size:14px;font-weight:700;color:var(--text) }
.pg-section-sub    { font-size:11px;color:var(--text3);margin-top:2px }
.pg-step           { width:26px;height:26px;background:var(--accent);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;margin-top:1px }
.pg-label          { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:5px }
.pg-input          { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);transition:border-color .2s;box-sizing:border-box;outline:none }
.pg-input:focus    { border-color:var(--accent) }
.pg-select         { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);cursor:pointer;outline:none }

.drop-zone-sm { border:2px dashed var(--border);border-radius:10px;padding:20px 14px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface2);min-height:110px;display:flex;align-items:center;justify-content:center }
.drop-zone-sm.dragging { border-color:var(--accent);background:rgba(232,160,32,.04) }
.drop-zone-sm:hover { border-color:rgba(232,160,32,.4) }
</style>

@push('scripts')
<script>
window.EditPige = {
    _file: null,

    onDragOver(e) { e.preventDefault(); document.getElementById('new-photo-zone').classList.add('dragging'); },
    onDragLeave() { document.getElementById('new-photo-zone').classList.remove('dragging'); },
    onDrop(e)     { e.preventDefault(); this.onDragLeave(); this.onFileSelect(e.dataTransfer.files); },

    onFileSelect(files) {
        const f = files[0];
        if (!f) return;
        const MAX_SZ = 30 * 1024 * 1024;
        const allowed = ['image/jpeg','image/png','image/webp'];
        if (!allowed.includes(f.type)) { alert('Format non supporté. Utilisez JPG, PNG ou WebP.'); return; }
        if (f.size > MAX_SZ) { alert(`Fichier trop volumineux : ${(f.size/1024/1024).toFixed(1)} Mo (max 30 Mo).`); return; }

        this._file = f;
        const url = URL.createObjectURL(f);
        document.getElementById('new-photo-img').src = url;
        document.getElementById('new-photo-name').textContent = `${f.name} · ${(f.size/1024/1024).toFixed(1)} Mo`;
        document.getElementById('new-photo-idle').style.display   = 'none';
        document.getElementById('new-photo-preview').style.display = 'block';

        // Injecter dans l'input
        try {
            const dt = new DataTransfer(); dt.items.add(f);
            document.getElementById('photo-input').files = dt.files;
        } catch {}
    },

    onStatusChange(val) {
        const zone   = document.getElementById('rejection-reason-zone');
        const quick  = document.getElementById('quick-reasons');
        const show   = val === 'rejete';
        zone.style.display  = show ? 'block' : 'none';
        quick.style.display = show ? 'flex'  : 'none';
    },

    getGeo() {
        if (!navigator.geolocation) return;
        const btn = document.getElementById('btn-geo');
        btn.innerHTML = '⏳ Localisation…'; btn.disabled = true;
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.querySelector('[name=gps_lat]').value = pos.coords.latitude.toFixed(7);
                document.querySelector('[name=gps_lng]').value = pos.coords.longitude.toFixed(7);
                btn.innerHTML = '✓ GPS obtenu';
                btn.style.color = '#22c55e'; btn.style.borderColor = 'rgba(34,197,94,.3)';
                btn.disabled = false;
            },
            () => { btn.innerHTML = 'Indisponible'; btn.disabled = false; }
        );
    },
};

// Validation avant soumission
document.getElementById('edit-form').addEventListener('submit', function(e) {
    const status = document.getElementById('status-select')?.value;
    if (status === 'rejete') {
        const reason = document.querySelector('[name=rejection_reason]')?.value?.trim();
        if (!reason) {
            e.preventDefault();
            alert('Le motif de rejet est obligatoire.');
            document.querySelector('[name=rejection_reason]').focus();
        }
    }
});
</script>
@endpush
</x-admin-layout>
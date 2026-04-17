<x-admin-layout>
<x-slot name="title">Modifier — {{ $panel->reference }}</x-slot>

<div style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $panel->name }}</div>
        </div>
        <div class="card-body">

            @if($errors->any())
            <div style="background:rgba(239,68,68,.1);border:1px solid var(--red);border-radius:8px;padding:12px;margin-bottom:16px;">
                <div style="color:var(--red);font-weight:600;margin-bottom:8px;">Erreurs :</div>
                <ul style="color:var(--red);padding-left:16px;">
                    @foreach($errors->all() as $error)
                        <li style="font-size:13px;">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <form method="POST" action="{{ route('admin.panels.update', $panel) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="section-label">Informations générales</div>

                <div class="mfg">
                    <label>Nom / Désignation *</label>
                    <input type="text" name="name" value="{{ old('name', $panel->name) }}" class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')<div class="field-error">{{ $message }}</div>@enderror
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Commune *</label>
                        <select name="commune_id">
                            @foreach($communes as $commune)
                            <option value="{{ $commune->id }}" {{ old('commune_id', $panel->commune_id) == $commune->id ? 'selected' : '' }}>{{ $commune->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Zone</label>
                        <select name="zone_id">
                            <option value="">— Aucune —</option>
                            @foreach($zones as $zone)
                            <option value="{{ $zone->id }}" {{ old('zone_id', $panel->zone_id) == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Format *</label>
                        <select name="format_id">
                            @foreach($formats as $format)
                            <option value="{{ $format->id }}" {{ old('format_id', $panel->format_id) == $format->id ? 'selected' : '' }}>
                                {{ $format->name }}@if($format->surface) ({{ $format->surface }}m²)@endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Catégorie</label>
                        <select name="category_id">
                            <option value="">— Aucune —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id', $panel->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="section-label">Caractéristiques techniques</div>

                <div class="form-3col">
                    <div class="mfg">
                        <label>Nombre de faces</label>
                        <input type="number" name="nombre_faces" value="{{ old('nombre_faces', $panel->nombre_faces ?? 1) }}" min="1" max="6">
                    </div>
                    <div class="mfg">
                        <label>Type de support</label>
                        <input type="text" name="type_support" value="{{ old('type_support', $panel->type_support ?? '') }}" placeholder="Ex: Bâche, Papier, LED...">
                    </div>
                    <div class="mfg">
                        <label>Orientation</label>
                        <select name="orientation">
                            <option value="">— Aucune —</option>
                            @foreach(['nord','sud','est','ouest','nord-est','nord-ouest','sud-est','sud-ouest'] as $o)
                            <option value="{{ $o }}" {{ old('orientation', $panel->orientation ?? '') === $o ? 'selected' : '' }}>{{ ucfirst($o) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="section-label">Tarification</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Tarif mensuel (FCFA)</label>
                        <input type="number" name="monthly_rate" value="{{ old('monthly_rate', $panel->monthly_rate) }}" step="1000" min="0">
                    </div>
                    <div class="mfg">
                        <label>Trafic journalier</label>
                        <input type="number" name="daily_traffic" value="{{ old('daily_traffic', $panel->daily_traffic) }}" min="0">
                    </div>
                </div>

                <div class="mfg">
                    <input type="checkbox" name="is_lit" value="1" id="is_lit_toggle" {{ old('is_lit', $panel->is_lit) ? 'checked' : '' }} style="display:none;">
                    <div style="display:flex;align-items:center;gap:12px;cursor:pointer;" onclick="toggleLit()">
                        <div id="toggle-track" style="position:relative;width:52px;height:28px;border-radius:14px;background:{{ old('is_lit', $panel->is_lit) ? '#e20613' : '#d1d5db' }};transition:background .3s;flex-shrink:0;">
                            <div id="toggle-thumb" style="position:absolute;top:3px;left:{{ old('is_lit', $panel->is_lit) ? '25px' : '3px' }};width:22px;height:22px;border-radius:50%;background:white;box-shadow:0 1px 4px rgba(0,0,0,.25);transition:left .3s;"></div>
                        </div>
                        <div>
                            <div id="toggle-label" style="font-size:14px;font-weight:600;color:{{ old('is_lit', $panel->is_lit) ? '#e20613' : 'var(--text2)' }};">
                                {{ old('is_lit', $panel->is_lit) ? '💡 Éclairé (rétroéclairé)' : '🌑 Non éclairé' }}
                            </div>
                            <div style="font-size:11px;color:var(--text3);margin-top:2px;">Cliquez pour basculer</div>
                        </div>
                    </div>
                </div>

                <script>
                function toggleLit() {
                    const cb=document.getElementById('is_lit_toggle'),track=document.getElementById('toggle-track'),thumb=document.getElementById('toggle-thumb'),label=document.getElementById('toggle-label');
                    cb.checked=!cb.checked;
                    if(cb.checked){track.style.background='#e20613';thumb.style.left='25px';label.textContent='💡 Éclairé (rétroéclairé)';label.style.color='#e20613';}
                    else{track.style.background='#d1d5db';thumb.style.left='3px';label.textContent='🌑 Non éclairé';label.style.color='var(--text2)';}
                }
                </script>

                <div class="section-label">Localisation</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Adresse</label>
                        <input type="text" name="adresse" value="{{ old('adresse', $panel->adresse ?? '') }}" placeholder="Ex: Rue des Jardins, N°12">
                    </div>
                    <div class="mfg">
                        <label>Quartier</label>
                        <input type="text" name="quartier" value="{{ old('quartier', $panel->quartier ?? '') }}" placeholder="Ex: Deux Plateaux">
                    </div>
                </div>

                <div class="mfg">
                    <label>Axe routier</label>
                    <input type="text" name="axe_routier" value="{{ old('axe_routier', $panel->axe_routier ?? '') }}" placeholder="Ex: Boulevard Latrille...">
                </div>

                <div class="section-label">Coordonnées GPS</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Latitude</label>
                        <input type="number" name="latitude" value="{{ old('latitude', $panel->latitude) }}" step="0.0000001">
                    </div>
                    <div class="mfg">
                        <label>Longitude</label>
                        <input type="number" name="longitude" value="{{ old('longitude', $panel->longitude) }}" step="0.0000001">
                    </div>
                </div>

                <div class="mfg">
                    <label>Description emplacement</label>
                    <textarea name="zone_description">{{ old('zone_description', $panel->zone_description) }}</textarea>
                </div>

                {{-- ══ IMAGES ══ --}}
                <div class="section-label">Images du panneau</div>

                {{-- Upload --}}
                <div class="mfg">
                    <label>Ajouter des images</label>
                    <input type="file" name="new_images[]" accept="image/*" multiple>
                    <div style="font-size:12px;color:var(--text3);margin-top:4px;">Formats acceptés : JPG, PNG, GIF (max 5MB par image)</div>
                </div>

                {{-- Images existantes --}}
                @if($panel->photos->count() > 0)
                <div style="margin-top:16px;">
                    <label style="font-weight:600;margin-bottom:12px;display:block;font-size:13px;color:var(--text2);">
                        Images existantes ({{ $panel->photos->count() }})
                    </label>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;">
                        @foreach($panel->photos->sortBy('ordre') as $photo)
                        <div id="photo-card-{{ $photo->id }}"
                             style="position:relative;border:2px solid var(--border);border-radius:10px;overflow:hidden;background:var(--surface2);transition:border-color .2s;">

                            {{-- Image --}}
                            <img src="{{ asset('storage/' . $photo->path) }}"
                                 alt="Photo panneau"
                                 style="width:100%;height:120px;object-fit:cover;display:block;">

                            {{-- Overlay suppression en cours --}}
                            <div id="overlay-{{ $photo->id }}"
                                 style="display:none;position:absolute;inset:0;background:rgba(239,68,68,.85);align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                <span style="color:white;font-size:11px;font-weight:600;">À supprimer</span>
                            </div>

                            {{-- Footer --}}
                            <div style="padding:8px;background:var(--surface);border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:6px;">
                                {{-- Ordre --}}
                                <div style="display:flex;align-items:center;gap:4px;">
                                    <span style="font-size:10px;color:var(--text3);">#</span>
                                    <select name="ordre[{{ $photo->id }}]"
                                            style="font-size:11px;padding:2px 4px;border-radius:5px;border:1px solid var(--border2);background:var(--surface2);color:var(--text);width:48px;">
                                        @for($i = 0; $i < $panel->photos->count(); $i++)
                                        <option value="{{ $i }}" {{ $photo->ordre == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                {{-- Bouton supprimer --}}
                                <input type="checkbox"
                                       name="delete_photos[]"
                                       value="{{ $photo->id }}"
                                       id="del-{{ $photo->id }}"
                                       style="display:none;">
                                <button type="button"
                                        id="del-btn-{{ $photo->id }}"
                                        onclick="toggleDeletePhoto({{ $photo->id }})"
                                        style="padding:5px 8px;border-radius:6px;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#ef4444;cursor:pointer;font-size:11px;font-weight:600;transition:all .15s;display:flex;align-items:center;gap:4px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                    Suppr.
                                </button>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div style="font-size:12px;color:var(--text3);margin-top:10px;display:flex;align-items:center;gap:6px;">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        Cliquez sur "Suppr." pour marquer une image à supprimer — elle sera supprimée à l'enregistrement.
                    </div>
                </div>
                @else
                <div style="padding:24px;text-align:center;background:var(--surface2);border-radius:10px;margin-top:8px;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--text3)" stroke-width="1.5" style="margin:0 auto 10px;display:block;opacity:.5;"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <div style="font-size:13px;color:var(--text2);">Aucune image pour ce panneau</div>
                    <div style="font-size:12px;color:var(--text3);margin-top:4px;">Ajoutez des photos en utilisant le champ ci-dessus</div>
                </div>
                @endif

                <div style="display:flex;gap:10px;margin-top:24px;">
                    <button type="submit" class="btn btn-primary">💾 Enregistrer</button>
                    <a href="{{ route('admin.panels.show', $panel) }}" class="btn btn-ghost">Annuler</a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
function toggleDeletePhoto(id) {
    const cb      = document.getElementById('del-' + id);
    const card    = document.getElementById('photo-card-' + id);
    const overlay = document.getElementById('overlay-' + id);
    const btn     = document.getElementById('del-btn-' + id);
    const svgIcon = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>`;

    cb.checked = !cb.checked;

    if (cb.checked) {
        card.style.borderColor = '#ef4444';
        overlay.style.display  = 'flex';
        btn.style.background   = 'rgba(239,68,68,.2)';
        btn.innerHTML = svgIcon + ' Annuler';
    } else {
        card.style.borderColor = 'var(--border)';
        overlay.style.display  = 'none';
        btn.style.background   = 'rgba(239,68,68,.08)';
        btn.innerHTML = svgIcon + ' Suppr.';
    }
}
</script>

</x-admin-layout>

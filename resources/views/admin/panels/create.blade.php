<x-admin-layout>
    <x-slot name="title">Nouveau Panneau</x-slot>

    <div style="max-width:800px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">➕ Nouveau Panneau</div>
            </div>
            <div class="card-body">

                {{-- Afficher erreurs de validation --}}
                @if($errors->any())
                <div style="background:rgba(239,68,68,.1); border:1px solid var(--red);
                            border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                    <ul style="color:var(--red); padding-left:16px;">
                        @foreach($errors->all() as $error)
                            <li style="font-size:13px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('admin.panels.store') }}"
                      enctype="multipart/form-data">
                    @csrf

                    {{-- INFORMATIONS GÉNÉRALES --}}
                    <div class="section-label">Informations générales</div>

                    <div class="mfg">
                        <label>Nom / Désignation *</label>
                        <input type="text" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ex: Face A — Carrefour Anono"
                               class="{{ $errors->has('name') ? 'error' : '' }}">
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Commune *</label>
                            <select name="commune_id"
                                    class="{{ $errors->has('commune_id') ? 'error' : '' }}">
                                <option value="">— Sélectionner —</option>
                                @foreach($communes as $commune)
                                <option value="{{ $commune->id }}"
                                    {{ old('commune_id') == $commune->id ? 'selected' : '' }}>
                                    {{ $commune->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('commune_id')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mfg">
                            <label>Zone</label>
                            <select name="zone_id">
                                <option value="">— Aucune —</option>
                                @foreach($zones as $zone)
                                <option value="{{ $zone->id }}"
                                    {{ old('zone_id') == $zone->id ? 'selected' : '' }}>
                                    {{ $zone->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Format *</label>
                            <select name="format_id"
                                    class="{{ $errors->has('format_id') ? 'error' : '' }}">
                                <option value="">— Sélectionner —</option>
                                @foreach($formats as $format)
                                <option value="{{ $format->id }}"
                                    {{ old('format_id') == $format->id ? 'selected' : '' }}>
                                    {{ $format->name }}
                                    @if($format->surface) ({{ $format->surface }}m²) @endif
                                </option>
                                @endforeach
                            </select>
                            @error('format_id')
                                <div class="field-error">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="mfg">
                            <label>Catégorie</label>
                            <select name="category_id">
                                <option value="">— Aucune —</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}"
                                    {{ old('category_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- CARACTÉRISTIQUES TECHNIQUES --}}
                    <div class="section-label">Caractéristiques techniques</div>

                    <div class="form-3col">
                        <div class="mfg">
                            <label>Nombre de faces</label>
                            <input type="number" name="nombre_faces"
                                   value="{{ old('nombre_faces', 1) }}"
                                   min="1" max="6">
                        </div>
                        <div class="mfg">
                            <label>Type de support</label>
                            <input type="text" name="type_support"
                                   value="{{ old('type_support') }}"
                                   placeholder="Ex: Bâche, Papier, LED...">
                        </div>
                        <div class="mfg">
                            <label>Orientation</label>
                            <select name="orientation">
                                <option value="">— Aucune —</option>
                                <option value="nord"       {{ old('orientation') === 'nord'       ? 'selected' : '' }}>Nord</option>
                                <option value="sud"        {{ old('orientation') === 'sud'        ? 'selected' : '' }}>Sud</option>
                                <option value="est"        {{ old('orientation') === 'est'        ? 'selected' : '' }}>Est</option>
                                <option value="ouest"      {{ old('orientation') === 'ouest'      ? 'selected' : '' }}>Ouest</option>
                                <option value="nord-est"   {{ old('orientation') === 'nord-est'   ? 'selected' : '' }}>Nord-Est</option>
                                <option value="nord-ouest" {{ old('orientation') === 'nord-ouest' ? 'selected' : '' }}>Nord-Ouest</option>
                                <option value="sud-est"    {{ old('orientation') === 'sud-est'    ? 'selected' : '' }}>Sud-Est</option>
                                <option value="sud-ouest"  {{ old('orientation') === 'sud-ouest'  ? 'selected' : '' }}>Sud-Ouest</option>
                            </select>
                        </div>
                    </div>

                    {{-- TARIFICATION --}}
                    <div class="section-label">Tarification</div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Tarif mensuel (FCFA)</label>
                            <input type="number" name="monthly_rate"
                                   value="{{ old('monthly_rate', 0) }}"
                                   step="1000" min="0">
                        </div>
                        <div class="mfg">
                            <label>Trafic journalier</label>
                            <input type="number" name="daily_traffic"
                                   value="{{ old('daily_traffic') }}"
                                   min="0" placeholder="Nb véhicules/jour">
                        </div>
                    </div>

                    <div class="mfg">
                        <input type="checkbox" name="is_lit" value="1"
                               id="is_lit_toggle"
                               {{ old('is_lit') ? 'checked' : '' }}
                               style="display:none;">

                        <div style="display:flex; align-items:center; gap:12px; cursor:pointer;" onclick="toggleLit()">
                            <div id="toggle-track"
                                 style="
                                    position:relative; width:52px; height:28px;
                                    border-radius:14px;
                                    background: {{ old('is_lit') ? '#e8a020' : '#d1d5db' }};
                                    transition: background .3s ease;
                                    flex-shrink:0;
                                 ">
                                <div id="toggle-thumb"
                                     style="
                                        position:absolute; top:3px;
                                        left: {{ old('is_lit') ? '25px' : '3px' }};
                                        width:22px; height:22px;
                                        border-radius:50%; background:white;
                                        box-shadow:0 1px 4px rgba(0,0,0,.25);
                                        transition: left .3s ease;
                                     ">
                                </div>
                            </div>
                            <div>
                                <div id="toggle-label"
                                     style="font-size:14px; font-weight:600; color:{{ old('is_lit') ? '#e8a020' : 'var(--text2)' }};">
                                    {{ old('is_lit') ? '💡 Éclairé (rétroéclairé)' : '🌑 Non éclairé' }}
                                </div>
                                <div style="font-size:11px; color:var(--text3); margin-top:2px;">Cliquez pour basculer</div>
                            </div>
                        </div>

                        <script>
                        function toggleLit() {
                            const cb    = document.getElementById('is_lit_toggle');
                            const track = document.getElementById('toggle-track');
                            const thumb = document.getElementById('toggle-thumb');
                            const label = document.getElementById('toggle-label');
                            cb.checked = !cb.checked;
                            if (cb.checked) {
                                track.style.background = '#e8a020';
                                thumb.style.left = '25px';
                                label.textContent = '💡 Éclairé (rétroéclairé)';
                                label.style.color = '#e8a020';
                            } else {
                                track.style.background = '#d1d5db';
                                thumb.style.left = '3px';
                                label.textContent = '🌑 Non éclairé';
                                label.style.color = 'var(--text2)';
                            }
                        }
                        </script>
                    </div>

                    {{-- LOCALISATION --}}
                    <div class="section-label">Localisation</div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Adresse</label>
                            <input type="text" name="adresse"
                                   value="{{ old('adresse') }}"
                                   placeholder="Ex: Rue des Jardins, N°12">
                        </div>
                        <div class="mfg">
                            <label>Quartier</label>
                            <input type="text" name="quartier"
                                   value="{{ old('quartier') }}"
                                   placeholder="Ex: Deux Plateaux">
                        </div>
                    </div>

                    <div class="mfg">
                        <label>Axe routier</label>
                        <input type="text" name="axe_routier"
                               value="{{ old('axe_routier') }}"
                               placeholder="Ex: Boulevard Latrille, Autoroute du Nord...">
                    </div>

                    {{-- GPS --}}
                    <div class="section-label">Coordonnées GPS</div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Latitude</label>
                            <input type="number" name="latitude"
                                   value="{{ old('latitude') }}"
                                   step="0.0000001"
                                   placeholder="Ex: 5.3600">
                        </div>
                        <div class="mfg">
                            <label>Longitude</label>
                            <input type="number" name="longitude"
                                   value="{{ old('longitude') }}"
                                   step="0.0000001"
                                   placeholder="Ex: -4.0083">
                        </div>
                    </div>

                    <div class="mfg">
                        <label>Description emplacement</label>
                        <textarea name="zone_description"
                                  placeholder="Ex: Face au carrefour, côté droit en venant du Plateau...">{{ old('zone_description') }}</textarea>
                    </div>

                    {{-- PHOTOS --}}
                    <div class="section-label">Photos</div>

                    <div class="mfg">
                        <label>Ajouter des photos</label>
                        <input type="file" name="photos[]"
                               multiple accept="image/*"
                               style="color:var(--text2);">
                    </div>

                    <div style="display:flex; gap:10px; margin-top:16px;">
                        <button type="submit" class="btn btn-primary">
                            ✅ Créer le panneau
                        </button>
                        <a href="{{ route('admin.panels.index') }}" class="btn btn-ghost">
                            Annuler
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>

</x-admin-layout>

<x-admin-layout>
<x-slot name="title">Modifier — {{ $panel->reference }}</x-slot>

<div style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $panel->name }}</div>
        </div>
        <div class="card-body">

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

            <form method="POST" action="{{ route('admin.panels.update', $panel) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- INFORMATIONS GÉNÉRALES --}}
                <div class="section-label">Informations générales</div>

                <div class="mfg">
                    <label>Nom / Désignation *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $panel->name) }}"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Commune *</label>
                        <select name="commune_id">
                            @foreach($communes as $commune)
                            <option value="{{ $commune->id }}"
                                {{ old('commune_id', $panel->commune_id) == $commune->id ? 'selected' : '' }}>
                                {{ $commune->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Zone</label>
                        <select name="zone_id">
                            <option value="">— Aucune —</option>
                            @foreach($zones as $zone)
                            <option value="{{ $zone->id }}"
                                {{ old('zone_id', $panel->zone_id) == $zone->id ? 'selected' : '' }}>
                                {{ $zone->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Format *</label>
                        <select name="format_id">
                            @foreach($formats as $format)
                            <option value="{{ $format->id }}"
                                {{ old('format_id', $panel->format_id) == $format->id ? 'selected' : '' }}>
                                {{ $format->name }}
                                @if($format->surface) ({{ $format->surface }}m²) @endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Catégorie</label>
                        <select name="category_id">
                            <option value="">— Aucune —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}"
                                {{ old('category_id', $panel->category_id) == $cat->id ? 'selected' : '' }}>
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
                               value="{{ old('nombre_faces', $panel->nombre_faces ?? 1) }}"
                               min="1" max="6">
                    </div>
                    <div class="mfg">
                        <label>Type de support</label>
                        <input type="text" name="type_support"
                               value="{{ old('type_support', $panel->type_support ?? '') }}"
                               placeholder="Ex: Bâche, Papier, LED...">
                    </div>
                    <div class="mfg">
                        <label>Orientation</label>
                        <select name="orientation">
                            <option value="">— Aucune —</option>
                            <option value="nord"       {{ old('orientation', $panel->orientation ?? '') === 'nord'       ? 'selected' : '' }}>Nord</option>
                            <option value="sud"        {{ old('orientation', $panel->orientation ?? '') === 'sud'        ? 'selected' : '' }}>Sud</option>
                            <option value="est"        {{ old('orientation', $panel->orientation ?? '') === 'est'        ? 'selected' : '' }}>Est</option>
                            <option value="ouest"      {{ old('orientation', $panel->orientation ?? '') === 'ouest'      ? 'selected' : '' }}>Ouest</option>
                            <option value="nord-est"   {{ old('orientation', $panel->orientation ?? '') === 'nord-est'   ? 'selected' : '' }}>Nord-Est</option>
                            <option value="nord-ouest" {{ old('orientation', $panel->orientation ?? '') === 'nord-ouest' ? 'selected' : '' }}>Nord-Ouest</option>
                            <option value="sud-est"    {{ old('orientation', $panel->orientation ?? '') === 'sud-est'    ? 'selected' : '' }}>Sud-Est</option>
                            <option value="sud-ouest"  {{ old('orientation', $panel->orientation ?? '') === 'sud-ouest'  ? 'selected' : '' }}>Sud-Ouest</option>
                        </select>
                    </div>
                </div>

                {{-- TARIFICATION --}}
                <div class="section-label">Tarification</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Tarif mensuel (FCFA)</label>
                        <input type="number" name="monthly_rate"
                               value="{{ old('monthly_rate', $panel->monthly_rate) }}"
                               step="1000" min="0">
                    </div>
                    <div class="mfg">
                        <label>Trafic journalier</label>
                        <input type="number" name="daily_traffic"
                               value="{{ old('daily_traffic', $panel->daily_traffic) }}"
                               min="0">
                    </div>
                </div>

                <div class="mfg">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_lit" value="1"
                               {{ old('is_lit', $panel->is_lit) ? 'checked' : '' }}
                               style="width:16px; height:16px;">
                        💡 Panneau éclairé
                    </label>
                </div>

                {{-- LOCALISATION --}}
                <div class="section-label">Localisation</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Adresse</label>
                        <input type="text" name="adresse"
                               value="{{ old('adresse', $panel->adresse ?? '') }}"
                               placeholder="Ex: Rue des Jardins, N°12">
                    </div>
                    <div class="mfg">
                        <label>Quartier</label>
                        <input type="text" name="quartier"
                               value="{{ old('quartier', $panel->quartier ?? '') }}"
                               placeholder="Ex: Deux Plateaux">
                    </div>
                </div>

                <div class="mfg">
                    <label>Axe routier</label>
                    <input type="text" name="axe_routier"
                           value="{{ old('axe_routier', $panel->axe_routier ?? '') }}"
                           placeholder="Ex: Boulevard Latrille...">
                </div>

                {{-- GPS --}}
                <div class="section-label">Coordonnées GPS</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Latitude</label>
                        <input type="number" name="latitude"
                               value="{{ old('latitude', $panel->latitude) }}"
                               step="0.0000001">
                    </div>
                    <div class="mfg">
                        <label>Longitude</label>
                        <input type="number" name="longitude"
                               value="{{ old('longitude', $panel->longitude) }}"
                               step="0.0000001">
                    </div>
                </div>

                <div class="mfg">
                    <label>Description emplacement</label>
                    <textarea name="zone_description">{{ old('zone_description', $panel->zone_description) }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.panels.show', $panel) }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

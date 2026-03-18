<x-admin-layout>
<x-slot name="title">Modifier — {{ $panel->reference }}</x-slot>

<div style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $panel->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.panels.update', $panel) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="section-label">Informations générales</div>

                <div class="mfg">
                    <label>Nom / Désignation *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $panel->name) }}"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name') <div class="field-error">{{ $message }}</div> @enderror
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

                <div class="section-label">Localisation GPS</div>

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

<x-admin-layout>
    <x-slot name="title">Nouveau Panneau</x-slot>

    <div style="max-width:800px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">➕ Nouveau Panneau</div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.panels.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="section-label">Informations générales</div>

                    <div class="mfg">
                        <label>Nom / Désignation *</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            placeholder="Ex: Face A — Carrefour Anono"
                            class="{{ $errors->has('name') ? 'error' : '' }}">
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Commune *</label>
                            <select name="commune_id" class="{{ $errors->has('commune_id') ? 'error' : '' }}">
                                <option value="">— Sélectionner —</option>
                                @foreach ($communes as $commune)
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
                                @foreach ($zones as $zone)
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
                            <select name="format_id" class="{{ $errors->has('format_id') ? 'error' : '' }}">
                                <option value="">— Sélectionner —</option>
                                @foreach ($formats as $format)
                                    <option value="{{ $format->id }}"
                                        {{ old('format_id') == $format->id ? 'selected' : '' }}>
                                        {{ $format->name }}
                                        @if ($format->surface)
                                            ({{ $format->surface }}m²)
                                        @endif
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
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ old('category_id') == $cat->id ? 'selected' : '' }}>
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
                            <input type="number" name="monthly_rate" value="{{ old('monthly_rate', 0) }}"
                                step="1000" min="0">
                        </div>

                        <div class="mfg">
                            <label>Trafic journalier</label>
                            <input type="number" name="daily_traffic" value="{{ old('daily_traffic') }}"
                                min="0" placeholder="Nb véhicules/jour">
                        </div>
                    </div>

                    <div class="mfg">
                        <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                            <input type="checkbox" name="is_lit" value="1" {{ old('is_lit') ? 'checked' : '' }}
                                style="width:16px; height:16px;">
                            💡 Panneau éclairé (rétroéclairé)
                        </label>
                    </div>

                    <div class="section-label">Localisation GPS</div>

                    <div class="form-2col">
                        <div class="mfg">
                            <label>Latitude</label>
                            <input type="number" name="latitude" value="{{ old('latitude') }}" step="0.0000001"
                                placeholder="Ex: 5.3600">
                        </div>

                        <div class="mfg">
                            <label>Longitude</label>
                            <input type="number" name="longitude" value="{{ old('longitude') }}" step="0.0000001"
                                placeholder="Ex: -4.0083">
                        </div>
                    </div>

                    <div class="mfg">
                        <label>Description emplacement</label>
                        <textarea name="zone_description" placeholder="Ex: Face au carrefour, côté droit en venant du Plateau...">{{ old('zone_description') }}</textarea>
                    </div>

                    <div class="section-label">Photos</div>

                    <div class="mfg">
                        <label>Ajouter des photos</label>
                        <input type="file" name="photos[]" multiple accept="image/*" style="color:var(--text2);">
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
                {{-- Afficher erreurs de validation --}}
                @if ($errors->any())
                    <div
                        style="background:rgba(239,68,68,.1); border:1px solid var(--red); border-radius:8px; padding:12px; margin-bottom:16px;">
                        <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                        <ul style="color:var(--red); padding-left:16px;">
                            @foreach ($errors->all() as $error)
                                <li style="font-size:13px;">{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>

</x-admin-layout>

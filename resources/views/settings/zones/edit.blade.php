<x-admin-layout>
<x-slot name="title">Modifier Zone</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $zone->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.zones.update', $zone) }}">
                @csrf
                @method('PUT')

                <div class="mfg">
                    <label>Nom de la zone *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $zone->name) }}"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mfg">
                    <label>Commune</label>
                    <select name="commune_id">
                        <option value="">— Aucune —</option>
                        @foreach($communes as $commune)
                        <option value="{{ $commune->id }}"
                            {{ old('commune_id', $zone->commune_id) == $commune->id ? 'selected' : '' }}>
                            {{ $commune->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mfg">
                    <label>Niveau de demande *</label>
                    <select name="demand_level">
                        <option value="faible"     {{ old('demand_level', $zone->demand_level) === 'faible'     ? 'selected' : '' }}>Faible</option>
                        <option value="normale"    {{ old('demand_level', $zone->demand_level) === 'normale'    ? 'selected' : '' }}>Normale</option>
                        <option value="haute"      {{ old('demand_level', $zone->demand_level) === 'haute'      ? 'selected' : '' }}>Haute</option>
                        <option value="tres_haute" {{ old('demand_level', $zone->demand_level) === 'tres_haute' ? 'selected' : '' }}>Très haute</option>
                    </select>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description">{{ old('description', $zone->description) }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.settings.zones.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>


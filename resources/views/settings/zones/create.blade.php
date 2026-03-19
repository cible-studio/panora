<x-admin-layout>
<x-slot name="title">Nouvelle Zone</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Zone</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.zones.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom de la zone *</label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex: Zone Nord"
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
                            {{ old('commune_id') == $commune->id ? 'selected' : '' }}>
                            {{ $commune->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="mfg">
                    <label>Niveau de demande *</label>
                    <select name="demand_level">
                        <option value="faible"     {{ old('demand_level') === 'faible'     ? 'selected' : '' }}>Faible</option>
                        <option value="normale" selected {{ old('demand_level') === 'normale' ? 'selected' : '' }}>Normale</option>
                        <option value="haute"      {{ old('demand_level') === 'haute'      ? 'selected' : '' }}>Haute</option>
                        <option value="tres_haute" {{ old('demand_level') === 'tres_haute' ? 'selected' : '' }}>Très haute</option>
                    </select>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Description optionnelle...">{{ old('description') }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la zone
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

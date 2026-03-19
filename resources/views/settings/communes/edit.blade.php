<x-admin-layout>
<x-slot name="title">Modifier Commune</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $commune->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.communes.update', $commune) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom de la commune *</label>
                        <input type="text" name="name"
                               value="{{ old('name', $commune->name) }}"
                               class="{{ $errors->has('name') ? 'error' : '' }}">
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Ville</label>
                        <input type="text" name="city"
                               value="{{ old('city', $commune->city) }}"
                               placeholder="Ex: Abidjan">
                    </div>
                </div>

                <div class="mfg">
                    <label>Région</label>
                    <input type="text" name="region"
                           value="{{ old('region', $commune->region) }}"
                           placeholder="Ex: Lagunes">
                </div>

                <div class="section-label">Taxes</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Taux ODP (FCFA)</label>
                        <input type="number" name="odp_rate"
                               value="{{ old('odp_rate', $commune->odp_rate) }}"
                               step="0.01" min="0">
                    </div>

                    <div class="mfg">
                        <label>Taux TM (FCFA)</label>
                        <input type="number" name="tm_rate"
                               value="{{ old('tm_rate', $commune->tm_rate) }}"
                               step="0.01" min="0">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.settings.communes.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>


<x-admin-layout>
<x-slot name="title">Nouveau Format</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouveau Format</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.formats.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom du format *</label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex: 4x3m"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="section-label">Dimensions</div>

                <div class="form-3col">
                    <div class="mfg">
                        <label>Largeur (m)</label>
                        <input type="number" name="width"
                               value="{{ old('width') }}"
                               step="0.01" min="0"
                               placeholder="Ex: 4">
                    </div>

                    <div class="mfg">
                        <label>Hauteur (m)</label>
                        <input type="number" name="height"
                               value="{{ old('height') }}"
                               step="0.01" min="0"
                               placeholder="Ex: 3">
                    </div>

                    <div class="mfg">
                        <label>Surface (m²)</label>
                        <input type="number" name="surface"
                               value="{{ old('surface') }}"
                               step="0.01" min="0"
                               placeholder="Ex: 12">
                    </div>
                </div>

                <div class="mfg">
                    <label>Type d'impression</label>
                    <input type="text" name="print_type"
                           value="{{ old('print_type') }}"
                           placeholder="Ex: Bâche imprimée, Papier affiché...">
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer le format
                    </button>
                    <a href="{{ route('admin.settings.formats.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

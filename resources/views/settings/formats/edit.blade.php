<x-admin-layout>
<x-slot name="title">Modifier Format</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $format->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.formats.update', $format) }}">
                @csrf
                @method('PUT')

                <div class="mfg">
                    <label>Nom du format *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $format->name) }}"
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
                               value="{{ old('width', $format->width) }}"
                               step="0.01" min="0">
                    </div>

                    <div class="mfg">
                        <label>Hauteur (m)</label>
                        <input type="number" name="height"
                               value="{{ old('height', $format->height) }}"
                               step="0.01" min="0">
                    </div>

                    <div class="mfg">
                        <label>Surface (m²)</label>
                        <input type="number" name="surface"
                               value="{{ old('surface', $format->surface) }}"
                               step="0.01" min="0">
                    </div>
                </div>

                <div class="mfg">
                    <label>Type d'impression</label>
                    <input type="text" name="print_type"
                           value="{{ old('print_type', $format->print_type) }}"
                           placeholder="Ex: Bâche imprimée...">
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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


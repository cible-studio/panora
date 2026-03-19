<x-admin-layout>
<x-slot name="title">Nouvelle Catégorie</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Catégorie</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.categories.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom de la catégorie *</label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex: Panneau 4x3, Abribus, Totem..."
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Description optionnelle...">{{ old('description') }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la catégorie
                    </button>
                    <a href="{{ route('admin.settings.categories.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

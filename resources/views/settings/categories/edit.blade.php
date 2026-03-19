<x-admin-layout>
<x-slot name="title">Modifier Catégorie</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $category->name }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.categories.update', $category) }}">
                @csrf
                @method('PUT')

                <div class="mfg">
                    <label>Nom de la catégorie *</label>
                    <input type="text" name="name"
                           value="{{ old('name', $category->name) }}"
                           class="{{ $errors->has('name') ? 'error' : '' }}">
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description">{{ old('description', $category->description) }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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

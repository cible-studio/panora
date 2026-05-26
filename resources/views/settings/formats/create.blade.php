<x-admin-layout>
<x-slot name="title">Nouveau Format</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux paramètres
    </a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.settings.index') }}" style="color:var(--text3);text-decoration:none">Paramètres</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Nouveau format</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(58,168,53,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><path d="M3 3h18v18H3zM3 9h18M9 21V9"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Ajouter un format de panneau</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Dimensions standardisées d'un panneau (4×3 m, 6×3 m, etc.).
                La surface peut être calculée automatiquement, ou saisie pour les formats irréguliers.
            </div>
        </div>
    </div>

    @if($errors->any())
    <div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
        @foreach($errors->all() as $error)
            <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
                <span>⚠️</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
    @endif

    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouveau format</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.formats.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom du format <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex: 4x3m"
                           class="{{ $errors->has('name') ? 'error' : '' }}"
                           required>
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

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer le format
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

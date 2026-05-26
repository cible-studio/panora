<x-admin-layout>
<x-slot name="title">Nouvelle Catégorie</x-slot>

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
        <span style="color:var(--text)">Nouvelle catégorie</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(129,53,138,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#81358a" stroke-width="2"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Ajouter une catégorie</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Type de support publicitaire (Abribus, Totem, Bâche, Panneau 4×3…).
                Sert à filtrer les panneaux dans le catalogue et les rapports.
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
            <div class="card-title">➕ Nouvelle catégorie</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.categories.store') }}">
                @csrf

                <div class="mfg">
                    <label>Nom de la catégorie <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name"
                           value="{{ old('name') }}"
                           placeholder="Ex: Panneau 4x3, Abribus, Totem..."
                           class="{{ $errors->has('name') ? 'error' : '' }}"
                           required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Description optionnelle...">{{ old('description') }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la catégorie
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

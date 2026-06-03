<x-admin-layout>
<x-slot name="title">Nouvelle Commune</x-slot>

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
        <span style="color:var(--text)">Nouvelle commune</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(232,160,32,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Ajouter une commune</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                La commune sert à localiser les panneaux et à calculer les taxes mensuelles (ODP/TM/DB).
                Tu pourras éditer les tarifs plus tard depuis la page Paramètres.
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

    {{-- Formulaire --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle commune</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.communes.store') }}">
                @csrf

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom de la commune <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ex: Cocody"
                               class="{{ $errors->has('name') ? 'error' : '' }}"
                               required>
                        @error('name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Ville</label>
                        <input type="text" name="city"
                               value="{{ old('city') }}"
                               placeholder="Ex: Abidjan">
                    </div>
                </div>

                <div class="mfg">
                    <label>Région</label>
                    <input type="text" name="region"
                           value="{{ old('region') }}"
                           placeholder="Ex: Lagunes">
                </div>

                <div class="section-label">Taxes (FCFA / m² / mois)</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Tarif ODP</label>
                        <input type="number" name="odp_rate"
                               value="{{ old('odp_rate', 0) }}"
                               step="0.01" min="0" placeholder="0">
                    </div>

                    <div class="mfg">
                        <label>Tarif TM</label>
                        <input type="number" name="tm_rate"
                               value="{{ old('tm_rate', 0) }}"
                               step="0.01" min="0" placeholder="0">
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la commune
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

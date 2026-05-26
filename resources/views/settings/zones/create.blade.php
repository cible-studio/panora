<x-admin-layout>
<x-slot name="title">Nouvelle Zone</x-slot>

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
        <span style="color:var(--text)">Nouvelle zone</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(63,127,192,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3f7fc0" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Ajouter une zone</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Une zone regroupe des panneaux d'une même commune (ex: Riviera, Marcory Anoumabo).
                Le niveau de demande sert à hiérarchiser les zones premium dans les rapports.
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
            <div class="card-title">➕ Nouvelle zone</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.zones.store') }}">
                @csrf

                <div class="form-2col">
                    <div class="mfg">
                        <label>Nom de la zone <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name"
                               value="{{ old('name') }}"
                               placeholder="Ex: Zone Nord"
                               class="{{ $errors->has('name') ? 'error' : '' }}"
                               required>
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
                </div>

                <div class="mfg">
                    <label>Niveau de demande <span style="color:var(--red)">*</span></label>
                    <select name="demand_level" required>
                        <option value="faible"     {{ old('demand_level') === 'faible'     ? 'selected' : '' }}>Faible</option>
                        <option value="normale"    {{ old('demand_level', 'normale') === 'normale'    ? 'selected' : '' }}>Normale</option>
                        <option value="haute"      {{ old('demand_level') === 'haute'      ? 'selected' : '' }}>Haute</option>
                        <option value="tres_haute" {{ old('demand_level') === 'tres_haute' ? 'selected' : '' }}>Très haute</option>
                    </select>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Description optionnelle...">{{ old('description') }}</textarea>
                </div>

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.settings.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la zone
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

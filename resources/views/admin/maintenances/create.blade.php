<x-admin-layout>
<x-slot name="title">Signaler une panne</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔧 Signaler une panne</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.maintenances.store') }}">
                @csrf

                @if($errors->any())
                <div style="background:rgba(239,68,68,.1); border:1px solid var(--red);
                            border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                    <ul style="color:var(--red); padding-left:16px;">
                        @foreach($errors->all() as $error)
                            <li style="font-size:13px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="section-label">Panneau concerné</div>

                <div class="mfg">
                    <label>Panneau *</label>
                    <select name="panel_id"
                            class="{{ $errors->has('panel_id') ? 'error' : '' }}">
                        <option value="">— Sélectionner un panneau —</option>
                        @foreach($panels as $panel)
                        <option value="{{ $panel->id }}"
                            {{ old('panel_id') == $panel->id ? 'selected' : '' }}>
                            {{ $panel->reference }} — {{ $panel->name }} ({{ $panel->commune->name }})
                        </option>
                        @endforeach
                    </select>
                    @error('panel_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="section-label">Détails de la panne</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Type de panne *</label>
                        <input type="text" name="type_panne"
                               value="{{ old('type_panne') }}"
                               placeholder="Ex: Éclairage défaillant, Bâche déchirée..."
                               class="{{ $errors->has('type_panne') ? 'error' : '' }}">
                        @error('type_panne')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Priorité *</label>
                        <select name="priorite">
                            <option value="faible"  {{ old('priorite') === 'faible'  ? 'selected' : '' }}>⚪ Faible</option>
                            <option value="normale" {{ old('priorite') === 'normale' ? 'selected' : '' }} selected>🔵 Normale</option>
                            <option value="haute"   {{ old('priorite') === 'haute'   ? 'selected' : '' }}>🟠 Haute</option>
                            <option value="urgente" {{ old('priorite') === 'urgente' ? 'selected' : '' }}>🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Décrivez la panne en détail...">{{ old('description') }}</textarea>
                </div>

                <div class="section-label">Assignation</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Technicien assigné</label>
                        <select name="technicien_id">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('technicien_id') == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfg">
                        <label>Date de signalement *</label>
                        <input type="date" name="date_signalement"
                               value="{{ old('date_signalement', date('Y-m-d')) }}"
                               class="{{ $errors->has('date_signalement') ? 'error' : '' }}">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        🔧 Signaler la panne
                    </button>
                    <a href="{{ route('admin.maintenances.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

<x-admin-layout>
<x-slot name="title">Modifier Maintenance</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $maintenance->panel->reference }}</div>
        </div>
        <div class="card-body">
            <form method="POST"
                  action="{{ route('admin.maintenances.update', $maintenance) }}">
                @csrf
                @method('PUT')

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

                <div class="form-2col">
                    <div class="mfg">
                        <label>Type de panne *</label>
                        <input type="text" name="type_panne"
                               value="{{ old('type_panne', $maintenance->type_panne ?? '') }}"
                               class="{{ $errors->has('type_panne') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Priorité *</label>
                        <select name="priorite">
                            <option value="faible"
                                {{ old('priorite', $maintenance->priorite ?? '') === 'faible' ? 'selected' : '' }}>
                                ⚪ Faible
                            </option>
                            <option value="normale"
                                {{ old('priorite', $maintenance->priorite ?? '') === 'normale' ? 'selected' : '' }}>
                                🔵 Normale
                            </option>
                            <option value="haute"
                                {{ old('priorite', $maintenance->priorite ?? '') === 'haute' ? 'selected' : '' }}>
                                🟠 Haute
                            </option>
                            <option value="urgente"
                                {{ old('priorite', $maintenance->priorite ?? '') === 'urgente' ? 'selected' : '' }}>
                                🔴 Urgente
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Statut *</label>
                        <select name="statut">
                            <option value="signale"
                                {{ old('statut', $maintenance->statut ?? '') === 'signale' ? 'selected' : '' }}>
                                Signalé
                            </option>
                            <option value="en_cours"
                                {{ old('statut', $maintenance->statut ?? '') === 'en_cours' ? 'selected' : '' }}>
                                En cours
                            </option>
                            <option value="resolu"
                                {{ old('statut', $maintenance->statut ?? '') === 'resolu' ? 'selected' : '' }}>
                                Résolu
                            </option>
                            <option value="annule"
                                {{ old('statut', $maintenance->statut ?? '') === 'annule' ? 'selected' : '' }}>
                                Annulé
                            </option>
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Technicien</label>
                        <select name="technicien_id">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('technicien_id', $maintenance->technicien_id ?? '') == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Description de la panne...">{{ old('description', $maintenance->description ?? '') }}</textarea>
                </div>

                <div class="mfg">
                    <label>Solution</label>
                    <textarea name="solution"
                              placeholder="Solution apportée...">{{ old('solution', $maintenance->solution ?? '') }}</textarea>
                </div>

                <div class="mfg">
                    <label>Date résolution</label>
                    <input type="date" name="date_resolution"
                           value="{{ old('date_resolution', $maintenance->date_resolution?->format('Y-m-d') ?? '') }}">
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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

<x-admin-layout>
<x-slot name="title">Nouvelle Proposition</x-slot>

<div style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Proposition</div>
        </div>
        <div class="card-body">

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

            <form method="POST" action="{{ route('admin.propositions.store') }}">
                @csrf

                <div class="section-label">Informations générales</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Numéro *</label>
                        <input type="text" name="numero"
                               value="{{ old('numero', $numero) }}"
                               class="{{ $errors->has('numero') ? 'error' : '' }}">
                        @error('numero')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id"
                                class="{{ $errors->has('client_id') ? 'error' : '' }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ old('client_id') == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                        @error('client_id')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="section-label">Période & Panneaux</div>

                <div class="form-3col">
                    <div class="mfg">
                        <label>Date début *</label>
                        <input type="date" name="date_debut"
                               value="{{ old('date_debut') }}"
                               class="{{ $errors->has('date_debut') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Date fin *</label>
                        <input type="date" name="date_fin"
                               value="{{ old('date_fin') }}"
                               class="{{ $errors->has('date_fin') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Nombre de panneaux *</label>
                        <input type="number" name="nb_panneaux"
                               value="{{ old('nb_panneaux', 1) }}"
                               min="1"
                               class="{{ $errors->has('nb_panneaux') ? 'error' : '' }}">
                    </div>
                </div>

                <div class="section-label">Montant</div>

                <div class="mfg">
                    <label>Montant total (FCFA) *</label>
                    <input type="number" name="montant"
                           value="{{ old('montant', 0) }}"
                           step="1000" min="0"
                           class="{{ $errors->has('montant') ? 'error' : '' }}">
                    @error('montant')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mfg">
                    <label>Notes / Remarques</label>
                    <textarea name="notes"
                              placeholder="Détails de la proposition...">{{ old('notes') }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la proposition
                    </button>
                    <a href="{{ route('admin.propositions.index') }}" class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

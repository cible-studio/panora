<x-admin-layout>
<x-slot name="title">Modifier — {{ $proposition->numero }}</x-slot>

<div style="max-width:800px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $proposition->numero }}</div>
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

            <form method="POST"
                  action="{{ route('admin.propositions.update', $proposition) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Numéro</label>
                        <input type="text"
                               value="{{ $proposition->numero }}"
                               disabled
                               style="opacity:0.5;">
                    </div>
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ old('client_id', $proposition->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-3col">
                    <div class="mfg">
                        <label>Date début *</label>
                        <input type="date" name="date_debut"
                               value="{{ old('date_debut', $proposition->date_debut->format('Y-m-d')) }}">
                    </div>
                    <div class="mfg">
                        <label>Date fin *</label>
                        <input type="date" name="date_fin"
                               value="{{ old('date_fin', $proposition->date_fin->format('Y-m-d')) }}">
                    </div>
                    <div class="mfg">
                        <label>Nombre de panneaux *</label>
                        <input type="number" name="nb_panneaux"
                               value="{{ old('nb_panneaux', $proposition->nb_panneaux) }}"
                               min="1">
                    </div>
                </div>

                <div class="mfg">
                    <label>Montant total (FCFA) *</label>
                    <input type="number" name="montant"
                           value="{{ old('montant', $proposition->montant) }}"
                           step="1000" min="0">
                </div>

                <div class="mfg">
                    <label>Notes</label>
                    <textarea name="notes">{{ old('notes', $proposition->notes ?? '') }}</textarea>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.propositions.show', $proposition) }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

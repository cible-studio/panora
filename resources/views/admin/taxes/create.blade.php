<x-admin-layout>
<x-slot name="title">Nouvelle Taxe</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Taxe</div>
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

            <form method="POST" action="{{ route('admin.taxes.store') }}">
                @csrf

                <div class="form-2col">
                    <div class="mfg">
                        <label>Commune *</label>
                        <select name="commune_id"
                                class="{{ $errors->has('commune_id') ? 'error' : '' }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($communes as $commune)
                            <option value="{{ $commune->id }}"
                                {{ old('commune_id') == $commune->id ? 'selected' : '' }}>
                                {{ $commune->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Type *</label>
                        <select name="type">
                            <option value="odp" {{ old('type') === 'odp' ? 'selected' : '' }}>
                                ODP — Occupation Domaine Public
                            </option>
                            <option value="tm" {{ old('type') === 'tm' ? 'selected' : '' }}>
                                TM — Taxe Municipale
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Année *</label>
                        <input type="number" name="year"
                               value="{{ old('year', date('Y')) }}"
                               min="2000" max="2099">
                    </div>
                    <div class="mfg">
                        <label>Montant (FCFA) *</label>
                        <input type="number" name="amount"
                               value="{{ old('amount', 0) }}"
                               step="1000" min="0">
                    </div>
                </div>

                <div class="mfg">
                    <label>Date d'échéance</label>
                    <input type="date" name="due_date"
                           value="{{ old('due_date') }}">
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la taxe
                    </button>
                    <a href="{{ route('admin.taxes.index') }}" class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

</x-admin-layout>

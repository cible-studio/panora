<x-admin-layout>
<x-slot name="title">Modifier Taxe</x-slot>

<div style="max-width:600px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier Taxe</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.taxes.update', $tax) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Commune *</label>
                        <select name="commune_id">
                            @foreach($communes as $commune)
                            <option value="{{ $commune->id }}"
                                {{ old('commune_id', $tax->commune_id) == $commune->id ? 'selected' : '' }}>
                                {{ $commune->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Type *</label>
                        <select name="type">
                            <option value="odp" {{ old('type', $tax->type) === 'odp' ? 'selected' : '' }}>
                                ODP
                            </option>
                            <option value="tm" {{ old('type', $tax->type) === 'tm' ? 'selected' : '' }}>
                                TM
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Année *</label>
                        <input type="number" name="year"
                               value="{{ old('year', $tax->year) }}"
                               min="2000" max="2099">
                    </div>
                    <div class="mfg">
                        <label>Montant (FCFA) *</label>
                        <input type="number" name="amount"
                               value="{{ old('amount', $tax->amount) }}"
                               step="1000" min="0">
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date d'échéance</label>
                        <input type="date" name="due_date"
                               value="{{ old('due_date', $tax->due_date?->format('Y-m-d') ?? '') }}">
                    </div>
                    <div class="mfg">
                        <label>Statut *</label>
                        <select name="status">
                            <option value="en_attente" {{ old('status', $tax->status) === 'en_attente' ? 'selected' : '' }}>En attente</option>
                            <option value="payee"      {{ old('status', $tax->status) === 'payee'      ? 'selected' : '' }}>Payée</option>
                            <option value="en_retard"  {{ old('status', $tax->status) === 'en_retard'  ? 'selected' : '' }}>En retard</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
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

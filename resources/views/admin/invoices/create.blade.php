<x-admin-layout>
<x-slot name="title">Nouvelle Facture</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">➕ Nouvelle Facture</div>
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

            <form method="POST" action="{{ route('admin.invoices.store') }}">
                @csrf

                <div class="section-label">Informations</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Référence *</label>
                        <input type="text" name="reference"
                               value="{{ old('reference', $reference) }}"
                               class="{{ $errors->has('reference') ? 'error' : '' }}">
                    </div>
                    <div class="mfg">
                        <label>Date d'émission *</label>
                        <input type="date" name="issued_at"
                               value="{{ old('issued_at', date('Y-m-d')) }}">
                    </div>
                </div>

                <div class="form-2col">
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
                    </div>
                    <div class="mfg">
                        <label>Campagne</label>
                        <select name="campaign_id">
                            <option value="">— Aucune —</option>
                            @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}"
                                {{ old('campaign_id') == $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="section-label">Montants</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Montant HT (FCFA) *</label>
                        <input type="number" name="amount"
                               value="{{ old('amount', 0) }}"
                               step="1000" min="0"
                               id="amount"
                               oninput="calculateTTC()">
                    </div>
                    <div class="mfg">
                        <label>TVA (%) *</label>
                        <input type="number" name="tva"
                               value="{{ old('tva', 18) }}"
                               step="0.01" min="0" max="100"
                               id="tva"
                               oninput="calculateTTC()">
                    </div>
                </div>

                {{-- Montant TTC calculé automatiquement --}}
                <div class="mfg">
                    <label>Montant TTC (calculé automatiquement)</label>
                    <div id="amount-ttc"
                         style="background:var(--surface2); border:1px solid var(--border2);
                                border-radius:8px; padding:10px 12px; font-size:16px;
                                font-weight:700; color:var(--accent);">
                        0 FCFA
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la facture
                    </button>
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function calculateTTC() {
    const amount = parseFloat(document.getElementById('amount').value) || 0;
    const tva    = parseFloat(document.getElementById('tva').value) || 0;
    const ttc    = amount * (1 + tva / 100);
    document.getElementById('amount-ttc').textContent =
        ttc.toLocaleString('fr-FR') + ' FCFA';
}
calculateTTC();
</script>
@endpush

</x-admin-layout>

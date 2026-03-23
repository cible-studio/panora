<x-admin-layout>
<x-slot name="title">Modifier — {{ $invoice->reference }}</x-slot>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">✏️ Modifier — {{ $invoice->reference }}</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}">
                @csrf
                @method('PUT')

                <div class="form-2col">
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ old('client_id', $invoice->client_id) == $client->id ? 'selected' : '' }}>
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
                                {{ old('campaign_id', $invoice->campaign_id) == $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Montant HT (FCFA) *</label>
                        <input type="number" name="amount"
                               value="{{ old('amount', $invoice->amount) }}"
                               step="1000" min="0"
                               id="amount"
                               oninput="calculateTTC()">
                    </div>
                    <div class="mfg">
                        <label>TVA (%) *</label>
                        <input type="number" name="tva"
                               value="{{ old('tva', $invoice->tva) }}"
                               step="0.01" min="0" max="100"
                               id="tva"
                               oninput="calculateTTC()">
                    </div>
                </div>

                <div class="mfg">
                    <label>Montant TTC</label>
                    <div id="amount-ttc"
                         style="background:var(--surface2); border:1px solid var(--border2);
                                border-radius:8px; padding:10px 12px; font-size:16px;
                                font-weight:700; color:var(--accent);">
                        {{ number_format($invoice->amount_ttc, 0, ',', ' ') }} FCFA
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date d'émission *</label>
                        <input type="date" name="issued_at"
                               value="{{ old('issued_at', $invoice->issued_at->format('Y-m-d')) }}">
                    </div>
                    <div class="mfg">
                        <label>Statut *</label>
                        <select name="status">
                            <option value="brouillon" {{ old('status', $invoice->status) === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                            <option value="envoyee"   {{ old('status', $invoice->status) === 'envoyee'   ? 'selected' : '' }}>Envoyée</option>
                            <option value="payee"     {{ old('status', $invoice->status) === 'payee'     ? 'selected' : '' }}>Payée</option>
                            <option value="annulee"   {{ old('status', $invoice->status) === 'annulee'   ? 'selected' : '' }}>Annulée</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:16px;">
                    <button type="submit" class="btn btn-primary">
                        💾 Enregistrer
                    </button>
                    <a href="{{ route('admin.invoices.show', $invoice) }}"
                       class="btn btn-ghost">
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
</script>
@endpush

</x-admin-layout>

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
                        <select name="client_id" id="inv-client">
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ old('client_id', $invoice->client_id) == $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>
                            Campagne
                            <span id="inv-campaign-hint" style="font-size:11px;color:var(--text3);font-weight:400"></span>
                        </label>
                        <select name="campaign_id" id="inv-campaign">
                            <option value="">— Aucune —</option>
                            @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}"
                                    data-client-id="{{ $campaign->client_id }}"
                                {{ old('campaign_id', $invoice->campaign_id) == $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}@if($campaign->client) — {{ $campaign->client->name }}@endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Bandeau info campagne (montant connu + déjà facturé) --}}
                <div id="inv-campaign-info"
                     style="display:none;background:linear-gradient(180deg,#ecfdf5,#f0fdf4);border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;margin:-4px 0 14px;font-size:12.5px;color:#065f46;line-height:1.5">
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>
                            Montant HT (FCFA) *
                            <button type="button" id="inv-apply-suggested"
                                    style="display:none;margin-left:8px;padding:2px 8px;background:#3aa835;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">
                                Reprendre le montant suggéré
                            </button>
                        </label>
                        <input type="number" name="amount"
                               value="{{ old('amount', $invoice->amount) }}"
                               step="1000" min="0"
                               id="amount">
                    </div>
                    <div class="mfg">
                        <label>TVA (%) *</label>
                        <input type="number" name="tva"
                               value="{{ old('tva', $invoice->tva) }}"
                               step="0.01" min="0" max="100"
                               id="tva">
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
(function () {
    const clientSel    = document.getElementById('inv-client');
    const campaignSel  = document.getElementById('inv-campaign');
    const campaignHint = document.getElementById('inv-campaign-hint');
    const infoBox      = document.getElementById('inv-campaign-info');
    const amountInput  = document.getElementById('amount');
    const tvaInput     = document.getElementById('tva');
    const ttcBox       = document.getElementById('amount-ttc');
    const applyBtn     = document.getElementById('inv-apply-suggested');

    const URL_CAMP_INFO = "{{ url('/admin/invoices/lookup/campaign') }}";

    const fmt = n => Math.round(n).toLocaleString('fr-FR') + ' FCFA';
    function calculateTTC() {
        const a = parseFloat(amountInput.value) || 0;
        const t = parseFloat(tvaInput.value) || 0;
        ttcBox.textContent = fmt(a * (1 + t / 100));
    }

    function refreshCampaignOptions() {
        const clientId = clientSel.value;
        let visible = 0;
        Array.from(campaignSel.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }
            const ok = !clientId || opt.dataset.clientId === clientId;
            opt.hidden = !ok;
            if (ok) visible++;
        });
        const cur = campaignSel.selectedOptions[0];
        if (cur && cur.hidden) {
            campaignSel.value = '';
            hideCampaignInfo();
        }
        campaignHint.textContent = clientId
            ? ` — ${visible} campagne${visible > 1 ? 's' : ''}`
            : ' — toutes campagnes';
    }

    let suggestedHt = null;
    async function fetchCampaignInfo() {
        const id = campaignSel.value;
        if (!id) { hideCampaignInfo(); return; }
        try {
            const r = await fetch(`${URL_CAMP_INFO}/${id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (d.client && clientSel.value !== String(d.client.id)) {
                clientSel.value = String(d.client.id);
                refreshCampaignOptions();
                campaignSel.value = String(d.id);
            }
            suggestedHt = d.suggested_amount_ht;
            const lines = [];
            lines.push(`<strong>${d.name}</strong> — période ${d.period}`);
            lines.push(`Montant total HT connu : <strong>${fmt(d.amount_ht)}</strong>`);
            if (d.billed_ht > 0) {
                lines.push(`Déjà facturé : ${fmt(d.billed_ht)} · Reste à facturer : <strong>${fmt(d.remaining_ht)}</strong>`);
            }
            if (d.fully_billed) {
                lines.unshift(`<span style="color:#92400e;font-weight:800">⚠️ Campagne déjà entièrement facturée</span>`);
                infoBox.style.background  = 'linear-gradient(180deg,#fff7ed,#fffbeb)';
                infoBox.style.borderColor = '#fed7aa';
                infoBox.style.color       = '#9a3412';
            } else {
                infoBox.style.background  = 'linear-gradient(180deg,#ecfdf5,#f0fdf4)';
                infoBox.style.borderColor = '#a7f3d0';
                infoBox.style.color       = '#065f46';
            }
            infoBox.innerHTML  = lines.join('<br>');
            infoBox.style.display = 'block';
            applyBtn.style.display = suggestedHt > 0 ? 'inline-block' : 'none';
        } catch (e) {
            console.warn('Lookup campagne échoué', e);
            hideCampaignInfo();
        }
    }

    function hideCampaignInfo() {
        infoBox.style.display = 'none';
        infoBox.innerHTML = '';
        suggestedHt = null;
        applyBtn.style.display = 'none';
    }

    clientSel.addEventListener('change', refreshCampaignOptions);
    campaignSel.addEventListener('change', fetchCampaignInfo);
    amountInput.addEventListener('input', calculateTTC);
    tvaInput.addEventListener('input', calculateTTC);
    applyBtn.addEventListener('click', () => {
        if (suggestedHt != null) {
            amountInput.value = Math.round(suggestedHt);
            calculateTTC();
        }
    });

    refreshCampaignOptions();
    if (campaignSel.value) fetchCampaignInfo();
    calculateTTC();
})();
</script>
@endpush

</x-admin-layout>

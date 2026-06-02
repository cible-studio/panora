<x-admin-layout>
<x-slot name="title">Nouvelle facture</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour aux factures
    </a>
</x-slot:topbarLeft>

<div style="max-width:720px;margin:0 auto">

    {{-- Breadcrumb --}}
    <div style="font-size:12px;color:var(--text3);margin-bottom:16px">
        <a href="{{ route('admin.invoices.index') }}" style="color:var(--text3);text-decoration:none">Facturation</a>
        <span style="margin:0 6px">›</span>
        <span style="color:var(--text)">Nouvelle facture</span>
    </div>

    {{-- Intro card --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:18px 22px;margin-bottom:18px;display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:rgba(58,168,53,.12);display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#3aa835" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div>
            <div style="font-size:16px;font-weight:800;color:var(--text);margin-bottom:3px">Créer une facture</div>
            <div style="font-size:12px;color:var(--text3);line-height:1.5">
                Facture liée à un client (et optionnellement une campagne). Le montant TTC est calculé automatiquement à partir du HT et de la TVA saisis.
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
            <div class="card-title">➕ Nouvelle facture</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.invoices.store') }}">
                @csrf

                <div class="section-label">Informations</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Référence <span style="color:var(--red)">*</span></label>
                        <input type="text" name="reference"
                               value="{{ old('reference', $reference) }}"
                               class="{{ $errors->has('reference') ? 'error' : '' }}"
                               required>
                    </div>
                    <div class="mfg">
                        <label>Date d'émission <span style="color:var(--red)">*</span></label>
                        <input type="date" name="issued_at"
                               value="{{ old('issued_at', date('Y-m-d')) }}"
                               required>
                    </div>
                </div>

                @php
                    $selClient   = old('client_id', $preselect['client_id'] ?? null);
                    $selCampaign = old('campaign_id', $preselect['campaign_id'] ?? null);
                @endphp

                <div class="form-2col">
                    <div class="mfg">
                        <label>Client <span style="color:var(--red)">*</span></label>
                        <select name="client_id" id="inv-client"
                                class="{{ $errors->has('client_id') ? 'error' : '' }}"
                                required>
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $client)
                            <option value="{{ $client->id }}"
                                {{ (string) $selClient === (string) $client->id ? 'selected' : '' }}>
                                {{ $client->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>
                            Campagne
                            <span id="inv-campaign-hint" style="font-size:11px;color:var(--text3);font-weight:400">
                                — choisis un client d'abord pour filtrer
                            </span>
                        </label>
                        {{-- Les campagnes sont rendues TOUTES avec data-client-id ; le JS
                             les masque selon le client sélectionné (pas de round-trip). --}}
                        <select name="campaign_id" id="inv-campaign">
                            <option value="">— Aucune —</option>
                            @foreach($campaigns as $campaign)
                            <option value="{{ $campaign->id }}"
                                    data-client-id="{{ $campaign->client_id }}"
                                    data-client-name="{{ $campaign->client?->name }}"
                                {{ (string) $selCampaign === (string) $campaign->id ? 'selected' : '' }}>
                                {{ $campaign->name }}@if($campaign->client) — {{ $campaign->client->name }}@endif
                            </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Bandeau live d'info sur la campagne choisie : montant HT
                     connu, déjà facturé, reste à facturer. Permet à l'admin
                     de voir d'un coup d'œil s'il sur-facture. --}}
                <div id="inv-campaign-info"
                     style="display:none;background:linear-gradient(180deg,#ecfdf5,#f0fdf4);border:1px solid #a7f3d0;border-radius:10px;padding:12px 14px;margin:-4px 0 14px;font-size:12.5px;color:#065f46;line-height:1.5">
                </div>

                <div class="section-label">Montants</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>
                            Montant HT (FCFA) <span style="color:var(--red)">*</span>
                            <button type="button" id="inv-apply-suggested"
                                    style="display:none;margin-left:8px;padding:2px 8px;background:#3aa835;color:#fff;border:none;border-radius:6px;font-size:11px;font-weight:700;cursor:pointer">
                                Reprendre le montant suggéré
                            </button>
                        </label>
                        <input type="number" name="amount"
                               value="{{ old('amount', $preselectAmount !== null ? (int) round($preselectAmount) : 0) }}"
                               step="1000" min="0"
                               id="amount"
                               oninput="calculateTTC()"
                               required>
                    </div>
                    <div class="mfg">
                        <label>TVA (%) <span style="color:var(--red)">*</span></label>
                        <input type="number" name="tva"
                               value="{{ old('tva', 18) }}"
                               step="0.01" min="0" max="100"
                               id="tva"
                               oninput="calculateTTC()"
                               required>
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

                <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-top:18px;border-top:1px solid var(--border)">
                    <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost">Annuler</a>
                    <button type="submit" class="btn btn-primary">
                        ✅ Créer la facture
                    </button>
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

    function fmt(n) {
        return Math.round(n).toLocaleString('fr-FR') + ' FCFA';
    }

    function calculateTTC() {
        const a = parseFloat(amountInput.value) || 0;
        const t = parseFloat(tvaInput.value) || 0;
        ttcBox.textContent = fmt(a * (1 + t / 100));
    }

    // ── Filtrage des campagnes selon le client ───────────────────────
    // Les <option> portent data-client-id ; on les masque/affiche sans
    // recharger la page. Hint label mis à jour en parallèle.
    function refreshCampaignOptions() {
        const clientId = clientSel.value;
        let visible = 0;
        Array.from(campaignSel.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }
            const ok = !clientId || opt.dataset.clientId === clientId;
            opt.hidden = !ok;
            if (ok) visible++;
        });

        // Si la campagne sélectionnée appartient à un autre client → reset
        const cur = campaignSel.selectedOptions[0];
        if (cur && cur.hidden) {
            campaignSel.value = '';
            hideCampaignInfo();
        }

        campaignHint.textContent = clientId
            ? `— ${visible} campagne${visible > 1 ? 's' : ''} de ce client`
            : "— choisis un client d'abord pour filtrer";
    }

    // ── Lookup de la campagne sélectionnée (montant + client + déjà facturé)
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

            // Si le client n'est pas encore sélectionné OU ne correspond pas
            // au client de la campagne, on le force (la campagne fait foi).
            if (d.client && clientSel.value !== String(d.client.id)) {
                clientSel.value = String(d.client.id);
                refreshCampaignOptions();
                campaignSel.value = String(d.id); // re-set après refresh
            }

            suggestedHt = d.suggested_amount_ht;

            // Affichage du bandeau info
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

            // Auto-fill montant si vide / à zéro / non manuellement modifié.
            const currentVal = parseFloat(amountInput.value) || 0;
            if (currentVal === 0 || amountInput.dataset.autoFilled === '1') {
                amountInput.value = Math.round(suggestedHt);
                amountInput.dataset.autoFilled = '1';
                calculateTTC();
            }
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

    // ── Wiring ────────────────────────────────────────────────────────
    clientSel.addEventListener('change', () => {
        refreshCampaignOptions();
        if (!campaignSel.value) hideCampaignInfo();
    });
    campaignSel.addEventListener('change', fetchCampaignInfo);
    amountInput.addEventListener('input', () => {
        // L'utilisateur a tapé manuellement → on ne réécrasera plus.
        amountInput.dataset.autoFilled = '';
        calculateTTC();
    });
    tvaInput.addEventListener('input', calculateTTC);
    applyBtn.addEventListener('click', () => {
        if (suggestedHt != null) {
            amountInput.value = Math.round(suggestedHt);
            amountInput.dataset.autoFilled = '1';
            calculateTTC();
        }
    });

    // Initial : si pré-rempli (?campaign_id=…) on lance le lookup, sinon on
    // se contente de filtrer la liste selon le client courant.
    refreshCampaignOptions();
    if (campaignSel.value) {
        amountInput.dataset.autoFilled = '1';
        fetchCampaignInfo();
    }
    calculateTTC();
})();
</script>
@endpush

</x-admin-layout>

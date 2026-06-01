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

                <div class="form-2col">
                    <div class="mfg">
                        <label>Client <span style="color:var(--red)">*</span></label>
                        <select name="client_id"
                                class="{{ $errors->has('client_id') ? 'error' : '' }}"
                                required>
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
                        <label>Montant HT (FCFA) <span style="color:var(--red)">*</span></label>
                        <input type="number" name="amount"
                               value="{{ old('amount', 0) }}"
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

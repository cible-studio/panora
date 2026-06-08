{{--
    Formulaire FNE partagé create + edit.
    Variables attendues :
      $invoice    : Invoice|null (null = create)
      $clients    : Collection<Client>
      $campaigns  : Collection<Campaign>
      $reference  : string (auto-générée pour create, sinon = invoice->reference)
      $preselect  : ['client_id'=>?, 'campaign_id'=>?] (utilisé en create)
      $isEdit     : bool
      $action     : string (URL submit)
      $method     : 'POST' (create) ou 'PUT' (edit)

    L'éditeur de lignes utilise une table dynamique avec
    add/remove + recalcul live total via le même algorithme que
    InvoiceCalculator côté serveur (cf. config/billing.php).
--}}
@php
    $isEdit    = isset($invoice) && $invoice !== null;
    $oldLines  = old('lines');
    $lines     = $oldLines ?: ($isEdit && $invoice->lines->count() > 0
                    ? $invoice->lines->map(fn($l) => [
                        'designation'       => $l->designation,
                        'commune_id'        => $l->commune_id,
                        'dimension_m2'      => $l->dimension_m2,
                        'pu_ht_mensuel'     => $l->pu_ht_mensuel,
                        'quantite'          => $l->quantite,
                        'duree_mois'        => $l->duree_mois,
                        'odp_rate_applique' => $l->odp_rate_applique,
                        'tm_rate_applique'  => $l->tm_rate_applique,
                    ])->all()
                    : [
                        // ligne vide par défaut en create
                        ['designation' => '', 'dimension_m2' => 0, 'pu_ht_mensuel' => 0,
                         'quantite' => 1, 'duree_mois' => 1, 'odp_rate_applique' => 0, 'tm_rate_applique' => 1000],
                    ]);
    $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate']);
    $tvaRate   = (float) config('billing.tva_rate', 18);
    $tspRate   = (float) config('billing.tsp_rate', 3);
    $tmDefault = (float) config('billing.tm_default', 1000);
@endphp

@if($errors->any())
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:10px;padding:14px 16px;margin-bottom:16px">
    @foreach($errors->all() as $error)
        <div style="color:#ef4444;font-size:13px;display:flex;gap:6px;align-items:flex-start;margin-bottom:3px">
            <span>⚠️</span><span>{{ $error }}</span>
        </div>
    @endforeach
</div>
@endif

@if($isEdit && $invoice->isLocked())
<div style="background:rgba(107,114,128,.10);border:1.5px solid rgba(107,114,128,.35);border-radius:10px;padding:12px 14px;margin-bottom:16px;color:#374151">
    🔒 <strong>Facture verrouillée</strong> le {{ $invoice->locked_at->format('d/m/Y à H:i') }}
    @if($invoice->lockedBy) par {{ $invoice->lockedBy->name }}@endif.
    Pour la modifier, déverrouille-la d'abord depuis la fiche détail (action tracée).
</div>
@endif

<form method="POST" action="{{ $action }}" id="form-fne">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- ════ INFOS GÉNÉRALES ════ --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">📄 Informations</div></div>
        <div class="card-body">
            <div class="form-2col">
                <div class="mfg">
                    <label>Référence <span style="color:var(--red)">*</span></label>
                    <input type="text" name="reference"
                           value="{{ old('reference', $reference ?? ($invoice->reference ?? '')) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           class="{{ $errors->has('reference') ? 'error' : '' }}" required>
                </div>
                <div class="mfg">
                    <label>Date d'émission <span style="color:var(--red)">*</span></label>
                    <input type="date" name="issued_at"
                           value="{{ old('issued_at', $isEdit ? $invoice->issued_at?->format('Y-m-d') : date('Y-m-d')) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }} required>
                </div>
            </div>

            @php
                $selClient   = old('client_id', $isEdit ? $invoice->client_id : ($preselect['client_id'] ?? null));
                $selCampaign = old('campaign_id', $isEdit ? $invoice->campaign_id : ($preselect['campaign_id'] ?? null));
            @endphp

            <div class="form-2col">
                <div class="mfg">
                    <label>Client <span style="color:var(--red)">*</span></label>
                    <select name="client_id" id="inv-client" required {{ $isEdit && $invoice->isLocked() ? 'disabled' : '' }}>
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ (string) $selClient === (string) $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                        @endforeach
                    </select>
                    @if($isEdit && $invoice->isLocked())
                        <input type="hidden" name="client_id" value="{{ $invoice->client_id }}">
                    @endif
                </div>
                <div class="mfg">
                    <label>Campagne <span style="font-size:11px;color:var(--text3);font-weight:400">— optionnel</span></label>
                    <select name="campaign_id" id="inv-campaign" {{ $isEdit && $invoice->isLocked() ? 'disabled' : '' }}>
                        <option value="">— Aucune —</option>
                        @foreach($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" data-client-id="{{ $campaign->client_id }}"
                                {{ (string) $selCampaign === (string) $campaign->id ? 'selected' : '' }}>
                            {{ $campaign->name }}@if($campaign->client) — {{ $campaign->client->name }}@endif
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mfg">
                <label>Notes / Conditions client <span style="font-size:11px;color:var(--text3);font-weight:400">— affiché en bas de facture</span></label>
                <textarea name="notes_client" rows="2" maxlength="2000"
                          {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                          placeholder="{{ config('billing.payment_terms_default') }}">{{ old('notes_client', $isEdit ? $invoice->notes_client : '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ════ LIGNES ÉDITABLES ════ --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
            <div class="card-title">📋 Lignes de facturation</div>
            @if(!($isEdit && $invoice->isLocked()))
            <button type="button" id="add-line" class="btn btn-ghost btn-sm">+ Ajouter une ligne</button>
            @endif
        </div>
        <div class="card-body" style="padding:0">
            <div style="overflow-x:auto">
                <table id="lines-table" style="width:100%;border-collapse:collapse;font-size:12px;min-width:880px">
                    <thead>
                        <tr style="background:var(--surface2);color:var(--text3);text-align:left">
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700">Désignation</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700">Commune</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700;text-align:center">m²</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700;text-align:right">PU HT/mois</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700;text-align:center">Qté</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700;text-align:center">Mois</th>
                            <th style="padding:8px 10px;font-size:10px;text-transform:uppercase;font-weight:700;text-align:right">Total HT</th>
                            <th style="padding:8px 10px"></th>
                        </tr>
                    </thead>
                    <tbody id="lines-tbody">
                        @foreach($lines as $i => $l)
                            <tr class="line-row" data-index="{{ $i }}">
                                <td style="padding:6px 8px;border-top:1px solid var(--border)">
                                    <input type="text" name="lines[{{ $i }}][designation]" required
                                           value="{{ $l['designation'] ?? '' }}"
                                           placeholder="Ex: ABG-001 — Treichville…"
                                           style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px">
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border)">
                                    <select name="lines[{{ $i }}][commune_id]" class="line-commune" required
                                            style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;background:var(--surface)">
                                        <option value="">—</option>
                                        @foreach($communes as $c)
                                            <option value="{{ $c->id }}"
                                                    data-odp="{{ $c->ratesAt(($isEdit ? $invoice->issued_at?->format('Y-m-d') : date('Y-m-d')))['odp'] }}"
                                                    data-tm="{{ $c->ratesAt(($isEdit ? $invoice->issued_at?->format('Y-m-d') : date('Y-m-d')))['tm'] }}"
                                                    {{ (string) ($l['commune_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>
                                                {{ $c->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:center">
                                    <input type="number" name="lines[{{ $i }}][dimension_m2]" class="line-m2" required
                                           value="{{ $l['dimension_m2'] ?? 0 }}" min="0" step="0.01"
                                           style="width:70px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;text-align:right">
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:right">
                                    <input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" class="line-pu" required
                                           value="{{ $l['pu_ht_mensuel'] ?? 0 }}" min="0" step="1000"
                                           style="width:110px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;text-align:right;font-family:ui-monospace,monospace">
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:center">
                                    <input type="number" name="lines[{{ $i }}][quantite]" class="line-qte" required
                                           value="{{ $l['quantite'] ?? 1 }}" min="1" step="1"
                                           style="width:55px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;text-align:right">
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:center">
                                    <input type="number" name="lines[{{ $i }}][duree_mois]" class="line-mois" required
                                           value="{{ $l['duree_mois'] ?? 1 }}" min="0.5" step="0.5"
                                           style="width:65px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:12px;text-align:right">
                                </td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:right;font-family:ui-monospace,monospace;font-weight:700;color:var(--accent)" class="line-total">0</td>
                                <td style="padding:6px 8px;border-top:1px solid var(--border);text-align:center">
                                    <button type="button" class="btn btn-ghost btn-sm line-remove" style="color:var(--red);padding:4px 8px">🗑</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ════ REMISE + SERVICES ════ --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">⚙ Remise & Services additionnels</div></div>
        <div class="card-body">
            <div class="form-3col">
                <div class="mfg">
                    <label>Remise globale (%)</label>
                    <input type="number" name="remise_pct" id="remise_pct" min="0" max="100" step="0.5"
                           value="{{ old('remise_pct', $isEdit ? $invoice->remise_pct : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="font-family:ui-monospace,monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label>Impression (HT)</label>
                    <input type="number" name="services_impression" id="services_impression" min="0" step="1000"
                           value="{{ old('services_impression', $isEdit ? $invoice->services_impression : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="font-family:ui-monospace,monospace;text-align:right">
                </div>
                <div class="mfg">
                    <label>Pose & dépose (HT)</label>
                    <input type="number" name="services_pose_depose" id="services_pose_depose" min="0" step="1000"
                           value="{{ old('services_pose_depose', $isEdit ? $invoice->services_pose_depose : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="font-family:ui-monospace,monospace;text-align:right">
                </div>
            </div>
        </div>
    </div>

    {{-- ════ RÉCAP LIVE ════ --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">💰 Récapitulatif FNE (calculé en direct)</div></div>
        <div class="card-body" style="background:var(--surface2)">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12.5px">
                <div style="color:var(--text3)">Total HT brut</div><div style="text-align:right;font-family:ui-monospace,monospace" id="rec-brut">0 FCFA</div>
                <div style="color:var(--text3)">Remise</div><div style="text-align:right;font-family:ui-monospace,monospace;color:#b45309" id="rec-remise">0 FCFA</div>
                <div style="color:var(--text2);font-weight:700;padding-top:6px;border-top:1px solid var(--border)">TOTAL HT</div><div style="text-align:right;font-family:ui-monospace,monospace;font-weight:700;padding-top:6px;border-top:1px solid var(--border)" id="rec-netht">0 FCFA</div>
                <div style="color:var(--text3)">TVA ({{ rtrim(rtrim(number_format($tvaRate, 2, ',', ''), '0'), ',') }} %)</div><div style="text-align:right;font-family:ui-monospace,monospace" id="rec-tva">0 FCFA</div>
                <div style="color:var(--text2);font-weight:800;padding-top:6px;border-top:1px solid var(--border)">TOTAL TTC</div><div style="text-align:right;font-family:ui-monospace,monospace;font-weight:800;padding-top:6px;border-top:1px solid var(--border)" id="rec-ttc">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px;margin-top:8px">TSP ({{ rtrim(rtrim(number_format($tspRate, 2, ',', ''), '0'), ',') }} %)</div><div style="text-align:right;font-family:ui-monospace,monospace;font-size:11.5px;margin-top:8px" id="rec-tsp">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">TM total</div><div style="text-align:right;font-family:ui-monospace,monospace;font-size:11.5px" id="rec-tm">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">ODP total</div><div style="text-align:right;font-family:ui-monospace,monospace;font-size:11.5px" id="rec-odp">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">Services TTC</div><div style="text-align:right;font-family:ui-monospace,monospace;font-size:11.5px" id="rec-svc">0 FCFA</div>
            </div>
            <div style="margin-top:14px;padding:12px 14px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-weight:800;font-size:14px;letter-spacing:.3px">💰 TOTAL À PAYER</span>
                <span style="font-weight:800;font-size:18px;font-family:ui-monospace,monospace" id="rec-total">0 FCFA</span>
            </div>
        </div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost">Annuler</a>
        @if(!($isEdit && $invoice->isLocked()))
        <button type="submit" class="btn btn-primary">✅ {{ $isEdit ? 'Enregistrer les modifications' : 'Créer la facture' }}</button>
        @endif
    </div>
</form>

@push('scripts')
<script>
(function () {
    const TVA = {{ $tvaRate }};
    const TSP = {{ $tspRate }};
    const TM_DEFAULT = {{ $tmDefault }};

    const tbody = document.getElementById('lines-tbody');
    const addBtn = document.getElementById('add-line');
    let nextIdx = {{ count($lines) }};

    function fmt(n) { return Math.round(n).toLocaleString('fr-FR') + ' FCFA'; }

    function recompute() {
        let htBrut = 0, totalTm = 0, totalOdp = 0;
        tbody.querySelectorAll('.line-row').forEach(row => {
            const pu  = parseFloat(row.querySelector('.line-pu')?.value)   || 0;
            const qte = parseInt(  row.querySelector('.line-qte')?.value)  || 0;
            const mois= parseFloat(row.querySelector('.line-mois')?.value) || 0;
            const m2  = parseFloat(row.querySelector('.line-m2')?.value)   || 0;
            const sel = row.querySelector('.line-commune');
            const opt = sel?.selectedOptions?.[0];
            const odp = parseFloat(opt?.dataset.odp) || 0;
            const tm  = parseFloat(opt?.dataset.tm)  || TM_DEFAULT;
            const lineHt = pu * qte * mois;
            htBrut    += lineHt;
            totalOdp  += odp * m2 * qte * mois;
            totalTm   += tm  * m2 * qte * mois;
            const cell = row.querySelector('.line-total');
            if (cell) cell.textContent = fmt(lineHt);
        });

        const remise   = parseFloat(document.getElementById('remise_pct').value) || 0;
        const svcImp   = parseFloat(document.getElementById('services_impression').value)   || 0;
        const svcPose  = parseFloat(document.getElementById('services_pose_depose').value) || 0;

        const netHt    = htBrut * (1 - remise/100);
        const tvaAmt   = netHt * TVA / 100;
        const tspAmt   = netHt * TSP / 100;
        const ttc      = netHt + tvaAmt;
        const svcHt    = svcImp + svcPose;
        const svcTtc   = svcHt * (1 + TVA / 100);
        const total    = ttc + tspAmt + totalTm + totalOdp + svcTtc;

        document.getElementById('rec-brut').textContent   = fmt(htBrut);
        document.getElementById('rec-remise').textContent = '− ' + fmt(htBrut * remise / 100);
        document.getElementById('rec-netht').textContent  = fmt(netHt);
        document.getElementById('rec-tva').textContent    = fmt(tvaAmt);
        document.getElementById('rec-ttc').textContent    = fmt(ttc);
        document.getElementById('rec-tsp').textContent    = fmt(tspAmt);
        document.getElementById('rec-tm').textContent     = fmt(totalTm);
        document.getElementById('rec-odp').textContent    = fmt(totalOdp);
        document.getElementById('rec-svc').textContent    = fmt(svcTtc);
        document.getElementById('rec-total').textContent  = fmt(total);
    }

    function bindRow(row) {
        row.querySelectorAll('input, select').forEach(el => {
            el.addEventListener('input', recompute);
            el.addEventListener('change', recompute);
        });
        const rm = row.querySelector('.line-remove');
        rm?.addEventListener('click', () => {
            if (tbody.querySelectorAll('.line-row').length <= 1) {
                alert('Au moins une ligne est requise.');
                return;
            }
            row.remove();
            recompute();
        });
    }

    function addLine() {
        const i = nextIdx++;
        const tr = document.createElement('tr');
        tr.className = 'line-row';
        tr.dataset.index = i;
        // Clone d'une ligne vide depuis la première (template)
        const firstRow = tbody.querySelector('.line-row');
        if (!firstRow) return;
        tr.innerHTML = firstRow.innerHTML.replace(/lines\[\d+\]/g, `lines[${i}]`);
        // Reset valeurs
        tr.querySelectorAll('input').forEach(inp => {
            if (inp.classList.contains('line-qte') || inp.classList.contains('line-mois')) {
                inp.value = inp.classList.contains('line-mois') ? '1' : '1';
            } else if (inp.classList.contains('line-m2') || inp.classList.contains('line-pu')) {
                inp.value = '0';
            } else { inp.value = ''; }
        });
        const sel = tr.querySelector('.line-commune');
        if (sel) sel.selectedIndex = 0;
        tbody.appendChild(tr);
        bindRow(tr);
        recompute();
    }

    addBtn?.addEventListener('click', addLine);
    tbody.querySelectorAll('.line-row').forEach(bindRow);

    ['remise_pct','services_impression','services_pose_depose'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', recompute);
    });

    recompute();
})();
</script>
@endpush

{{--
    Formulaire FNE partagé create + edit (version retravaillée).
    Contrat inchangé (drop-in) : mêmes name="…", mêmes routes admin.invoices.*,
    même config('billing.*'), même algorithme de calcul que InvoiceCalculator.

    Variables attendues :
      $invoice, $clients, $campaigns, $reference, $preselect, $isEdit, $action, $method

    Changements de cette version :
      • Design : système de cartes unifié (.ifc) + récapitulatif FNE en pièce maîtresse.
      • Selects : init Select2 idempotente, dropdowns lisibles, largeurs stables.
      • Fix bug : l'ajout de ligne clone désormais un <template> propre au lieu de
        recopier le HTML d'une ligne déjà initialisée par Select2 (évitait les
        widgets dupliqués / cassés).
--}}
@php
    $isEdit    = isset($invoice) && $invoice !== null;
    $rateDate  = $isEdit ? ($invoice->issued_at?->format('Y-m-d') ?? date('Y-m-d')) : date('Y-m-d');
    $oldLines  = old('lines');
    $lines     = $oldLines ?: ($isEdit && $invoice->lines->count() > 0
                    ? $invoice->lines->map(fn($l) => [
                        'designation'   => $l->designation,
                        'commune_id'    => $l->commune_id,
                        'dimension_m2'  => $l->dimension_m2,
                        'pu_ht_mensuel' => $l->pu_ht_mensuel,
                        'quantite'      => $l->quantite,
                        'duree_mois'    => $l->duree_mois,
                    ])->all()
                    : [
                        ['designation' => '', 'commune_id' => '', 'dimension_m2' => 0,
                         'pu_ht_mensuel' => 0, 'quantite' => 1, 'duree_mois' => 1],
                    ]);
    $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate']);
    $tvaRate   = (float) config('billing.tva_rate', 18);
    $tspRate   = (float) config('billing.tsp_rate', 3);
    $tmDefault = (float) config('billing.tm_default', 1000);
    $locked    = $isEdit && $invoice->isLocked();
    $fmtPct    = fn($v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
@endphp

@if($errors->any())
<div class="ifc-alert ifc-alert--error" role="alert">
    <div class="ifc-alert-title">Vérifie les champs suivants</div>
    @foreach($errors->all() as $error)
        <div class="ifc-alert-line">{{ $error }}</div>
    @endforeach
</div>
@endif

@if($locked)
<div class="ifc-alert ifc-alert--locked">
    🔒 <strong>Facture verrouillée</strong> le {{ $invoice->locked_at->format('d/m/Y à H:i') }}@if($invoice->lockedBy) par {{ $invoice->lockedBy->name }}@endif.
    Déverrouille-la depuis la fiche détail pour la modifier (action tracée).
</div>
@endif

<form method="POST" action="{{ $action }}" id="form-fne" class="invoice-form" autocomplete="off">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- ════════════ INFORMATIONS ════════════ --}}
    <section class="ifc">
        <header class="ifc-head">
            <span class="ifc-chip">📄</span>
            <div>
                <div class="ifc-title">Informations</div>
                <div class="ifc-sub">Client, campagne et entête de la facture</div>
            </div>
        </header>
        <div class="ifc-body">
            <div class="form-2col">
                <div class="mfg">
                    <label>Référence <span class="req">*</span></label>
                    <input type="text" name="reference"
                           value="{{ old('reference', $reference ?? ($invoice->reference ?? '')) }}"
                           {{ $locked ? 'readonly' : '' }}
                           class="{{ $errors->has('reference') ? 'error' : '' }}" required>
                </div>
                <div class="mfg">
                    <label>Date d'émission <span class="req">*</span></label>
                    <input type="date" name="issued_at"
                           value="{{ old('issued_at', $isEdit ? $invoice->issued_at?->format('Y-m-d') : date('Y-m-d')) }}"
                           {{ $locked ? 'readonly' : '' }} required>
                </div>
            </div>

            @php
                $selClient   = old('client_id', $isEdit ? $invoice->client_id : ($preselect['client_id'] ?? null));
                $selCampaign = old('campaign_id', $isEdit ? $invoice->campaign_id : ($preselect['campaign_id'] ?? null));
            @endphp

            <div class="form-2col">
                <div class="mfg">
                    <label>Client <span class="req">*</span></label>
                    <select name="client_id" id="inv-client" required {{ $locked ? 'disabled' : '' }}>
                        <option value="">— Sélectionner —</option>
                        @foreach($clients as $client)
                        <option value="{{ $client->id }}" {{ (string) $selClient === (string) $client->id ? 'selected' : '' }}>
                            {{ $client->name }}
                        </option>
                        @endforeach
                    </select>
                    @if($locked)
                        <input type="hidden" name="client_id" value="{{ $invoice->client_id }}">
                    @endif
                </div>
                <div class="mfg">
                    <label>Campagne <span class="opt">— optionnel</span></label>
                    <select name="campaign_id" id="inv-campaign" {{ $locked ? 'disabled' : '' }}>
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

            <div class="mfg" style="margin-bottom:0">
                <label>Notes / Conditions client <span class="opt">— affiché en bas de facture</span></label>
                <textarea name="notes_client" rows="2" maxlength="2000" {{ $locked ? 'readonly' : '' }}
                          placeholder="{{ config('billing.payment_terms_default') }}">{{ old('notes_client', $isEdit ? $invoice->notes_client : '') }}</textarea>
            </div>
        </div>
    </section>

    {{-- ════════════ LIGNES DE FACTURATION ════════════ --}}
    <section class="ifc">
        <header class="ifc-head">
            <span class="ifc-chip">📋</span>
            <div>
                <div class="ifc-title">Lignes de facturation</div>
                <div class="ifc-sub" id="lines-count-label">{{ count($lines) }} ligne{{ count($lines) > 1 ? 's' : '' }} · ajoute les panneaux à facturer</div>
            </div>
        </header>

        <div class="lines-scroll">
            <table id="lines-table" class="lines-table">
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th>Désignation</th>
                        <th>Commune</th>
                        <th class="num">m²</th>
                        <th class="num">PU HT/mois</th>
                        <th class="num">Qté</th>
                        <th class="num">Mois</th>
                        <th class="num">Total HT</th>
                        <th class="act"></th>
                    </tr>
                </thead>
                <tbody id="lines-tbody">
                    @foreach($lines as $i => $l)
                        <tr class="line-row" data-index="{{ $i }}">
                            <td class="col-num"><span class="row-number">{{ $i + 1 }}</span></td>
                            <td class="col-designation">
                                <select name="lines[{{ $i }}][designation_picker]" class="line-designation" style="width:100%"></select>
                                <input type="hidden" name="lines[{{ $i }}][designation]" class="line-designation-value" value="{{ $l['designation'] ?? '' }}">
                            </td>
                            <td class="col-commune">
                                <select name="lines[{{ $i }}][commune_id]" class="line-commune" required style="width:100%">
                                    <option value=""></option>
                                    @foreach($communes as $c)
                                        <option value="{{ $c->id }}"
                                                data-odp="{{ $c->ratesAt($rateDate)['odp'] }}"
                                                data-tm="{{ $c->ratesAt($rateDate)['tm'] }}"
                                                {{ (string) ($l['commune_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>
                                            {{ $c->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="num col-m2"><input type="number" name="lines[{{ $i }}][dimension_m2]" class="line-m2" required value="{{ $l['dimension_m2'] ?? 0 }}" min="0" step="0.01"></td>
                            <td class="num col-pu"><input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" class="line-pu" required value="{{ $l['pu_ht_mensuel'] ?? 0 }}" min="0" step="1000"></td>
                            <td class="num col-qte"><input type="number" name="lines[{{ $i }}][quantite]" class="line-qte" required value="{{ $l['quantite'] ?? 1 }}" min="1" step="1"></td>
                            <td class="num col-mois"><input type="number" name="lines[{{ $i }}][duree_mois]" class="line-mois" required value="{{ $l['duree_mois'] ?? 1 }}" min="0.5" step="0.5"></td>
                            <td class="num col-total line-total">0 FCFA</td>
                            <td class="act">
                                <button type="button" class="btn-line-remove line-remove" title="Supprimer la ligne" aria-label="Supprimer cette ligne">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @unless($locked)
        <footer class="ifc-foot">
            <button type="button" id="add-line" class="btn-add-line">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Ajouter une ligne
            </button>
        </footer>
        @endunless
    </section>

    {{-- ════════════ REMISE & SERVICES ════════════ --}}
    <section class="ifc">
        <header class="ifc-head">
            <span class="ifc-chip">⚙️</span>
            <div>
                <div class="ifc-title">Remise & services additionnels</div>
                <div class="ifc-sub">La remise s'applique sur le HT · les services sont soumis à la TVA</div>
            </div>
        </header>
        <div class="ifc-body">
            <div class="form-3col">
                <div class="mfg">
                    <label>Remise globale (%)</label>
                    <input type="number" name="remise_pct" id="remise_pct" min="0" max="100" step="0.5"
                           value="{{ old('remise_pct', $isEdit ? $invoice->remise_pct : 0) }}" {{ $locked ? 'readonly' : '' }}>
                </div>
                <div class="mfg">
                    <label>Impression (HT)</label>
                    <input type="number" name="services_impression" id="services_impression" min="0" step="1000"
                           value="{{ old('services_impression', $isEdit ? $invoice->services_impression : 0) }}" {{ $locked ? 'readonly' : '' }}>
                </div>
                <div class="mfg" style="margin-bottom:0">
                    <label>Pose & dépose (HT)</label>
                    <input type="number" name="services_pose_depose" id="services_pose_depose" min="0" step="1000"
                           value="{{ old('services_pose_depose', $isEdit ? $invoice->services_pose_depose : 0) }}" {{ $locked ? 'readonly' : '' }}>
                </div>
            </div>
        </div>
    </section>

    {{-- ════════════ RÉCAPITULATIF FNE (pièce maîtresse) ════════════ --}}
    <section class="ifc recap">
        <header class="ifc-head">
            <span class="ifc-chip">💰</span>
            <div>
                <div class="ifc-title">Récapitulatif FNE</div>
                <div class="ifc-sub">Calculé en direct au fil de la saisie</div>
            </div>
        </header>
        <div class="ifc-body">
            <div class="recap-cols">
                {{-- Colonne principale : HT → TVA → TTC --}}
                <div class="recap-block">
                    <div class="recap-line"><span class="lbl">Total HT brut</span><span class="val" id="rec-brut">0 FCFA</span></div>
                    <div class="recap-line"><span class="lbl">Remise</span><span class="val val-minus" id="rec-remise">0 FCFA</span></div>
                    <div class="recap-sep"></div>
                    <div class="recap-line strong"><span class="lbl">Total HT</span><span class="val" id="rec-netht">0 FCFA</span></div>
                    <div class="recap-line"><span class="lbl">TVA ({{ $fmtPct($tvaRate) }} %)</span><span class="val" id="rec-tva">0 FCFA</span></div>
                    <div class="recap-sep"></div>
                    <div class="recap-line strong"><span class="lbl">Total TTC</span><span class="val" id="rec-ttc">0 FCFA</span></div>
                </div>
                {{-- Colonne : autres taxes + services --}}
                <div class="recap-block recap-block--alt">
                    <div class="recap-cap">Autres taxes &amp; services</div>
                    <div class="recap-line sub"><span class="lbl">TSP ({{ $fmtPct($tspRate) }} %)</span><span class="val" id="rec-tsp">0 FCFA</span></div>
                    <div class="recap-line sub"><span class="lbl">Taxe municipale (TM)</span><span class="val" id="rec-tm">0 FCFA</span></div>
                    <div class="recap-line sub"><span class="lbl">Occupation domaine public (ODP)</span><span class="val" id="rec-odp">0 FCFA</span></div>
                    <div class="recap-line sub"><span class="lbl">Services TTC</span><span class="val" id="rec-svc">0 FCFA</span></div>
                </div>
            </div>
            <div class="recap-total">
                <span class="recap-total-lbl">Total à payer</span>
                <span class="recap-total-val" id="rec-total">0 FCFA</span>
            </div>
        </div>
    </section>

    <div class="ifc-actions">
        <a href="{{ route('admin.invoices.index') }}" class="btn btn-ghost">Annuler</a>
        @unless($locked)
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Enregistrer les modifications' : 'Créer la facture' }}</button>
        @endunless
    </div>
</form>

{{-- ════════════ TEMPLATE LIGNE VIERGE (cloné par le JS, jamais initialisé tant que non inséré) ════════════ --}}
<template id="line-tpl">
    <tr class="line-row" data-index="__IDX__">
        <td class="col-num"><span class="row-number">0</span></td>
        <td class="col-designation">
            <select name="lines[__IDX__][designation_picker]" class="line-designation" style="width:100%"></select>
            <input type="hidden" name="lines[__IDX__][designation]" class="line-designation-value" value="">
        </td>
        <td class="col-commune">
            <select name="lines[__IDX__][commune_id]" class="line-commune" required style="width:100%">
                <option value=""></option>
                @foreach($communes as $c)
                    <option value="{{ $c->id }}" data-odp="{{ $c->ratesAt($rateDate)['odp'] }}" data-tm="{{ $c->ratesAt($rateDate)['tm'] }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </td>
        <td class="num col-m2"><input type="number" name="lines[__IDX__][dimension_m2]" class="line-m2" required value="0" min="0" step="0.01"></td>
        <td class="num col-pu"><input type="number" name="lines[__IDX__][pu_ht_mensuel]" class="line-pu" required value="0" min="0" step="1000"></td>
        <td class="num col-qte"><input type="number" name="lines[__IDX__][quantite]" class="line-qte" required value="1" min="1" step="1"></td>
        <td class="num col-mois"><input type="number" name="lines[__IDX__][duree_mois]" class="line-mois" required value="1" min="0.5" step="0.5"></td>
        <td class="num col-total line-total">0 FCFA</td>
        <td class="act">
            <button type="button" class="btn-line-remove line-remove" title="Supprimer la ligne" aria-label="Supprimer cette ligne">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
        </td>
    </tr>
</template>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    /* ════════════ SYSTÈME DE CARTES UNIFIÉ (.ifc) ════════════ */
    .invoice-form .ifc {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,.03), 0 12px 32px -22px rgba(0,0,0,.22);
    }
    .invoice-form .ifc-head {
        display: flex; align-items: center; gap: 14px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(135deg, rgba(232,160,32,.06), rgba(58,168,53,.035));
    }
    .invoice-form .ifc-chip {
        width: 42px; height: 42px; flex-shrink: 0;
        border-radius: 12px;
        background: rgba(232,160,32,.12);
        display: flex; align-items: center; justify-content: center;
        font-size: 20px;
    }
    .invoice-form .ifc-title { font-size: 14.5px; font-weight: 800; color: var(--text); letter-spacing: -.01em; }
    .invoice-form .ifc-sub   { font-size: 12px; color: var(--text3); margin-top: 1px; line-height: 1.35; }
    .invoice-form .ifc-body  { padding: 18px 20px; }
    .invoice-form .ifc-foot  {
        padding: 14px 18px; border-top: 1px solid var(--border);
        background: var(--surface2); display: flex; justify-content: center;
    }

    /* ════════════ BANDEAUX (erreurs / verrouillage) ════════════ */
    .invoice-form ~ .ifc-alert, .ifc-alert { border-radius: 12px; padding: 14px 16px; margin-bottom: 16px; font-size: 13px; }
    .ifc-alert--error  { background: rgba(239,68,68,.07); border: 1px solid rgba(239,68,68,.28); color: #b91c1c; }
    .ifc-alert--locked { background: rgba(107,114,128,.09); border: 1px solid rgba(107,114,128,.32); color: #374151; }
    .ifc-alert-title { font-weight: 800; margin-bottom: 6px; }
    .ifc-alert-line  { display: flex; gap: 6px; line-height: 1.5; }
    .ifc-alert-line::before { content: "•"; opacity: .6; }

    /* ════════════ CHAMPS NATIFS ════════════ */
    .invoice-form .mfg { margin-bottom: 14px; }
    .invoice-form .mfg label {
        display: block; font-size: 11px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .5px;
        color: var(--text2); margin-bottom: 6px;
    }
    .invoice-form .mfg .req { color: var(--red, #ef4444); }
    .invoice-form .mfg .opt { font-size: 11px; color: var(--text3); font-weight: 400; text-transform: none; letter-spacing: 0; }
    .invoice-form .mfg input[type="text"],
    .invoice-form .mfg input[type="number"],
    .invoice-form .mfg input[type="date"],
    .invoice-form .mfg textarea {
        height: 42px; width: 100%; padding: 0 13px;
        background: var(--surface); border: 1px solid var(--border);
        border-radius: 9px; font-size: 13.5px; color: var(--text);
        font-family: inherit; outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .invoice-form .mfg input[type="number"] { text-align: right; font-variant-numeric: tabular-nums; }
    .invoice-form .mfg textarea { height: auto; min-height: 62px; padding: 10px 13px; line-height: 1.5; resize: vertical; text-align: left; }
    .invoice-form .mfg input:hover, .invoice-form .mfg textarea:hover { border-color: var(--text3); }
    .invoice-form .mfg input:focus, .invoice-form .mfg textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(232,160,32,.16);
    }
    .invoice-form .mfg input.error { border-color: rgba(239,68,68,.55); }
    .invoice-form .mfg input[readonly], .invoice-form .mfg input:disabled {
        background: var(--surface2); color: var(--text2); cursor: not-allowed;
    }
    .invoice-form .form-2col, .invoice-form .form-3col { display: grid; gap: 14px; }
    .invoice-form .form-2col { grid-template-columns: 1fr 1fr; }
    .invoice-form .form-3col { grid-template-columns: repeat(3, 1fr); }

    /* ════════════ TABLEAU DES LIGNES ════════════ */
    .lines-scroll {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .lines-table { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 940px; }
    .lines-table thead tr { background: var(--surface2); border-bottom: 1px solid var(--border); }
    .lines-table th {
        padding: 11px 12px; font-size: 10.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .6px; text-align: left;
        color: var(--text3); white-space: nowrap;
    }
    .lines-table th.num { text-align: right; }
    .lines-table th.act { width: 56px; }
    .lines-table th.col-num { width: 46px; text-align: center; padding-left: 16px; }

    .lines-table tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
    .lines-table tbody tr:last-child { border-bottom: none; }
    .lines-table tbody tr:hover { background: rgba(232,160,32,.035); }
    .lines-table tbody tr:hover .row-number { background: var(--accent); color: #fff; border-color: var(--accent); }
    .lines-table td { padding: 11px 10px; vertical-align: middle; }
    .lines-table td.num { text-align: right; }
    .lines-table td.act { text-align: center; width: 56px; }
    .lines-table td.col-num { width: 46px; text-align: center; padding-left: 16px; }

    .lines-table .col-designation { min-width: 290px; }
    .lines-table .col-commune     { min-width: 175px; }
    .lines-table .col-m2   { width: 92px; }
    .lines-table .col-pu   { width: 138px; }
    .lines-table .col-qte  { width: 78px; }
    .lines-table .col-mois { width: 88px; }
    .lines-table .col-total {
        width: 138px; font-weight: 800; color: var(--accent);
        font-size: 13.5px; white-space: nowrap; font-variant-numeric: tabular-nums;
    }

    .row-number {
        display: inline-flex; align-items: center; justify-content: center;
        width: 26px; height: 26px; border-radius: 999px;
        background: var(--surface); border: 1px solid var(--border);
        color: var(--text3); font-size: 11px; font-weight: 800;
        transition: background .12s, color .12s, border-color .12s;
    }

    .lines-table input[type="number"], .lines-table input[type="text"] {
        height: 40px !important; width: 100% !important; padding: 0 11px !important;
        border: 1px solid var(--border) !important; border-radius: 8px !important;
        background: var(--surface) !important; font-size: 13px !important;
        text-align: right !important; color: var(--text) !important;
        font-variant-numeric: tabular-nums; transition: border-color .15s, box-shadow .15s;
    }
    .lines-table input:hover { border-color: var(--text3) !important; }
    .lines-table input:focus { border-color: var(--accent) !important; outline: none !important; box-shadow: 0 0 0 3px rgba(232,160,32,.15) !important; }
    .lines-table input[type="number"]::-webkit-outer-spin-button,
    .lines-table input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .lines-table input[type="number"] { -moz-appearance: textfield; }

    .btn-add-line {
        display: inline-flex; align-items: center; gap: 8px;
        height: 40px; padding: 0 18px;
        background: var(--accent); color: #fff; border: none; border-radius: 10px;
        font-size: 13px; font-weight: 700; cursor: pointer;
        box-shadow: 0 4px 12px -4px rgba(232,160,32,.5);
        transition: transform .12s, box-shadow .15s, background .15s;
    }
    .btn-add-line:hover  { background: var(--accent-dark, #d18d12); transform: translateY(-1px); box-shadow: 0 6px 16px -4px rgba(232,160,32,.6); }
    .btn-add-line:active { transform: translateY(0); }

    .btn-line-remove {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 8px;
        background: transparent; border: 1px solid var(--border); color: var(--text3);
        cursor: pointer; transition: background .15s, border-color .15s, color .15s, transform .1s;
    }
    .btn-line-remove:hover { background: rgba(239,68,68,.1); border-color: rgba(239,68,68,.4); color: #ef4444; }
    .btn-line-remove:active { transform: scale(.92); }
    .btn-line-remove svg { display: block; }

    /* ════════════ RÉCAPITULATIF ════════════ */
    .invoice-form .recap .ifc-body { background: var(--surface2); }
    .recap-cols { display: grid; grid-template-columns: 1.05fr .95fr; gap: 22px; }
    .recap-block--alt {
        padding-left: 22px; border-left: 1px dashed var(--border);
    }
    .recap-cap {
        font-size: 10.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .6px; color: var(--text3); margin-bottom: 8px;
    }
    .recap-line { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 6px 0; font-size: 13px; }
    .recap-line .lbl { color: var(--text3); }
    .recap-line .val { color: var(--text2); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .recap-line.sub  { font-size: 12px; padding: 5px 0; }
    .recap-line.strong .lbl, .recap-line.strong .val { font-weight: 800; color: var(--text); font-size: 13.5px; }
    .recap-line .val-minus { color: #b45309; }
    .recap-sep { border-top: 1px solid var(--border); margin: 5px 0; }

    .recap-total {
        margin-top: 18px; padding: 15px 18px; border-radius: 12px;
        background: linear-gradient(135deg, var(--accent), var(--accent-dark, #c47f12));
        color: #fff; display: flex; justify-content: space-between; align-items: center; gap: 12px;
        box-shadow: 0 8px 22px -10px rgba(232,160,32,.55);
    }
    .recap-total-lbl { font-weight: 800; font-size: 13px; letter-spacing: .4px; text-transform: uppercase; }
    .recap-total-val { font-weight: 800; font-size: 22px; font-variant-numeric: tabular-nums; letter-spacing: -.01em; }

    /* ════════════ ACTIONS ════════════ */
    .ifc-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }

    /* ════════════ RESPONSIVE ════════════ */
    @media (max-width: 720px) {
        .invoice-form .form-2col, .invoice-form .form-3col { grid-template-columns: 1fr; }
        .recap-cols { grid-template-columns: 1fr; gap: 16px; }
        .recap-block--alt { padding-left: 0; border-left: none; padding-top: 14px; border-top: 1px dashed var(--border); }
        .recap-total-val { font-size: 19px; }
        .ifc-actions { flex-direction: column-reverse; }
        .ifc-actions .btn { width: 100%; text-align: center; }
    }
    @media (prefers-reduced-motion: reduce) {
        .invoice-form *, .invoice-form *::before, .invoice-form *::after { transition: none !important; }
    }

    /* ════════════ SELECT2 — habillage unifié CIBLE ════════════ */
    .invoice-form .select2-container { width: 100% !important; display: block; }
    .invoice-form .select2-container--default .select2-selection--single,
    .lines-table .select2-container--default .select2-selection--single {
        height: 42px !important; border: 1px solid var(--border) !important;
        border-radius: 9px !important; background: var(--surface) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .lines-table .select2-container--default .select2-selection--single { height: 40px !important; border-radius: 8px !important; }
    .invoice-form .select2-container--default .select2-selection--single .select2-selection__rendered,
    .lines-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px !important; font-size: 13px !important; color: var(--text) !important;
        padding-left: 13px !important; padding-right: 30px !important;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lines-table .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 38px !important; }
    .invoice-form .select2-container--default .select2-selection__placeholder { color: var(--text3) !important; }
    .invoice-form .select2-container--default .select2-selection--single .select2-selection__arrow,
    .lines-table .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px !important; right: 7px !important; }
    .invoice-form .select2-container--default.select2-container--focus .select2-selection--single,
    .invoice-form .select2-container--default.select2-container--open .select2-selection--single,
    .lines-table .select2-container--default.select2-container--focus .select2-selection--single,
    .lines-table .select2-container--default.select2-container--open .select2-selection--single {
        border-color: var(--accent) !important; box-shadow: 0 0 0 3px rgba(232,160,32,.15) !important;
    }

    /* Dropdown : fond OPAQUE + au-dessus de tout + ombre cohérente */
    .select2-dropdown {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important; border-radius: 11px !important;
        box-shadow: 0 18px 48px -12px rgba(0,0,0,.28), 0 2px 6px rgba(0,0,0,.08) !important;
        overflow: hidden; z-index: 9999 !important;
    }
    .select2-results__options { background: var(--surface) !important; max-height: 280px !important; }
    .select2-search--dropdown { padding: 8px !important; background: var(--surface) !important; border-bottom: 1px solid var(--border); }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--border) !important; border-radius: 8px !important;
        padding: 8px 10px !important; font-size: 13px !important; outline: none !important;
        background: var(--surface2) !important; color: var(--text) !important;
    }
    .select2-search--dropdown .select2-search__field:focus { border-color: var(--accent) !important; box-shadow: 0 0 0 2px rgba(232,160,32,.15) !important; }
    .select2-results__option {
        padding: 9px 12px !important; font-size: 12.5px !important;
        background: var(--surface) !important; color: var(--text) !important;
        border-bottom: 1px solid var(--border);
    }
    .select2-results__option:last-child { border-bottom: none; }
    .select2-results__option--highlighted,
    .select2-results__option--highlighted[aria-selected] { background: rgba(232,160,32,.14) !important; color: var(--text) !important; }
    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background: rgba(232,160,32,.06) !important; color: var(--accent-dark, var(--accent)) !important; font-weight: 600;
    }
    .select2-results__message { background: var(--surface) !important; color: var(--text3) !important; font-style: italic; }

    /* Templates d'options (panneau / campagne) */
    .s2-pan-row { display: flex; gap: 10px; align-items: flex-start; padding: 2px 0; }
    .s2-pan-info { flex: 1; min-width: 0; }
    .s2-pan-ref { font-weight: 800; color: var(--accent-dark, var(--accent)); font-size: 12.5px; display: flex; align-items: center; gap: 6px; }
    .s2-pan-name { font-size: 12px; color: var(--text); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .s2-pan-meta { font-size: 10.5px; color: var(--text3); margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
    .s2-pan-pill { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 700; }
    .s2-pan-pill.ext { background: rgba(59,130,246,.12); color: #1d4ed8; }
    .s2-pan-pill.int { background: rgba(232,160,32,.12); color: var(--accent-dark, var(--accent)); }
    .s2-camp-row { padding: 2px 0; }
    .s2-camp-name { font-weight: 700; color: var(--text); font-size: 12.5px; }
    .s2-camp-meta { font-size: 10.5px; color: var(--text3); margin-top: 2px; }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
(function () {
    const TVA = {{ $tvaRate }};
    const TSP = {{ $tspRate }};
    const TM_DEFAULT = {{ $tmDefault }};
    const LOOKUP_PANELS_URL = "{{ route('admin.invoices.lookup.panels') }}";

    const tbody      = document.getElementById('lines-tbody');
    const addBtn     = document.getElementById('add-line');
    const tpl        = document.getElementById('line-tpl');
    const clientSel  = document.getElementById('inv-client');
    const campaignSel= document.getElementById('inv-campaign');
    const $client    = $(clientSel);
    const $campaign  = $(campaignSel);
    let nextIdx      = {{ count($lines) }};

    const fmt = (n) => Math.round(n || 0).toLocaleString('fr-FR') + ' FCFA';

    // ═══════════ CLIENT + CAMPAGNE (filtre en cascade) ═══════════
    $client.select2({ placeholder: '— Choisir un client —', allowClear: true, minimumResultsForSearch: 0 });

    function campaignTemplate(item) {
        if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
        const parts = (item.text || '').split(' — ');
        return $('<div class="s2-camp-row"><div class="s2-camp-name">' + parts[0] + '</div>' +
            (parts.length > 1 ? '<div class="s2-camp-meta">👤 ' + parts.slice(1).join(' — ') + '</div>' : '') + '</div>');
    }

    function initCampaignSelect2() {
        const cid = clientSel.value;
        const visible = Array.from(campaignSel.options).filter(o => o.value && !o.hidden).length;
        $campaign.select2({
            placeholder: cid
                ? (visible > 0 ? '— Choisir une campagne (optionnel) —' : '— Aucune campagne pour ce client —')
                : '— Choisis d\'abord un client —',
            allowClear: true, minimumResultsForSearch: 0,
            templateResult: campaignTemplate,
            templateSelection: (item) => item.text || '— Aucune —',
        });
    }
    initCampaignSelect2();

    function refreshCampaignOptions() {
        const cid = clientSel.value;
        Array.from(campaignSel.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; opt.disabled = false; return; }
            const ok = !cid || opt.dataset.clientId === cid;
            opt.hidden = !ok; opt.disabled = !ok;
        });
        const cur = campaignSel.selectedOptions[0];
        if (cur && cur.hidden) $campaign.val(null).trigger('change');
        if ($campaign.data('select2')) $campaign.select2('destroy');
        initCampaignSelect2();
    }

    $client.on('select2:select select2:clear', refreshCampaignOptions);

    $campaign.on('select2:select select2:clear', function () {
        const opt = campaignSel.selectedOptions[0];
        if (opt && opt.value) {
            const camCid = opt.dataset.clientId;
            if (camCid && clientSel.value !== camCid) {
                $client.val(camCid).trigger('change');
                refreshCampaignOptions();
                $campaign.val(opt.value).trigger('change');
            }
        }
        resetAllDesignations(); // les panneaux disponibles dépendent de la campagne
    });

    refreshCampaignOptions();
    if (campaignSel.value) $campaign.trigger('change');

    function resetAllDesignations() {
        tbody.querySelectorAll('.line-row').forEach(row => {
            const $sel = $(row.querySelector('.line-designation'));
            if ($sel.data('select2')) $sel.select2('destroy');
            $sel.find('option').remove();
            row.querySelector('.line-designation-value').value = '';
            $sel.removeData('select2-init');
            initLineSelect2(row);
        });
        recompute();
    }

    // ═══════════ SELECT2 PAR LIGNE — désignation (AJAX) + commune ═══════════
    function s2Pan(item) {
        if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
        if (item.ref) {
            const badge = item.is_external
                ? '<span class="s2-pan-pill ext">🤝 Externe</span>'
                : '<span class="s2-pan-pill int">🏢 CIBLE</span>';
            return $('<div class="s2-pan-row"><div class="s2-pan-info">' +
                '<div class="s2-pan-ref">' + item.ref + ' ' + badge + '</div>' +
                '<div class="s2-pan-name">' + (item.name || '') + '</div>' +
                '<div class="s2-pan-meta"><span>📍 ' + (item.commune_name || '—') + '</span>' +
                (item.dimension_m2 ? '<span>📐 ' + item.dimension_m2 + ' m²</span>' : '') +
                (item.pu_suggested ? '<span style="color:var(--accent-dark);font-weight:700">' + Math.round(item.pu_suggested).toLocaleString('fr-FR') + ' F/mois</span>' : '') +
                '</div></div></div>');
        }
        return $('<span>📝 ' + (item.text || '') + '</span>');
    }
    const s2PanSelection = (item) => (!item.id && !item.text) ? '— Choisir un panneau ou taper —' : (item.designation || item.text);

    function initLineSelect2(row) {
        const $design  = $(row.querySelector('.line-designation'));
        const $commune = $(row.querySelector('.line-commune'));

        if (!$design.data('select2-init')) {
            const initial = row.querySelector('.line-designation-value').value;
            if (initial) $design[0].appendChild(new Option(initial, 'manual:' + initial, true, true));
            $design.select2({
                placeholder: campaignSel?.value ? 'Choisir un panneau de la campagne…' : 'Choisir une campagne d\'abord…',
                allowClear: true, tags: true, minimumInputLength: 0,
                language: {
                    noResults: () => campaignSel?.value ? 'Aucun panneau — tape pour une saisie libre.' : 'Choisis d\'abord une campagne en haut.',
                    inputTooShort: () => 'Tape pour rechercher…',
                    searching: () => 'Recherche…',
                },
                ajax: {
                    url: LOOKUP_PANELS_URL, delay: 220, dataType: 'json',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    data: (params) => ({ q: params.term || '', page: params.page || 1, campaign_id: campaignSel?.value || '' }),
                    processResults: (data, params) => {
                        params.page = params.page || 1;
                        return { results: data.results || [], pagination: { more: data.pagination?.more === true } };
                    },
                    cache: true,
                },
                createTag: (params) => {
                    const term = (params.term || '').trim();
                    return term === '' ? null : { id: 'manual:' + term, text: term, designation: term };
                },
                templateResult: s2Pan,
                templateSelection: s2PanSelection,
                escapeMarkup: (m) => m,
            });
            $design.data('select2-init', true);

            $design.on('select2:select', function (e) {
                const item   = e.params.data;
                const dField = row.querySelector('.line-designation-value');
                const m2     = row.querySelector('.line-m2');
                const pu     = row.querySelector('.line-pu');
                if (String(item.id).startsWith('manual:')) { dField.value = item.designation || item.text; recompute(); return; }
                dField.value = item.designation || item.text;
                if (item.dimension_m2 && m2) m2.value = item.dimension_m2;
                if (item.pu_suggested && pu) pu.value = Math.round(item.pu_suggested);
                if (item.commune_id) $commune.val(String(item.commune_id)).trigger('change');
                recompute();
            });
            $design.on('select2:clear', function () { row.querySelector('.line-designation-value').value = ''; recompute(); });
        }

        if (!$commune.data('select2-init')) {
            $commune.select2({ placeholder: 'Commune', minimumResultsForSearch: 0 });
            $commune.data('select2-init', true);
            $commune.on('change', recompute);
        }
    }

    // ═══════════ CALCUL EN DIRECT (même algo que InvoiceCalculator) ═══════════
    function recompute() {
        let htBrut = 0, totalTm = 0, totalOdp = 0;
        tbody.querySelectorAll('.line-row').forEach(row => {
            const pu   = parseFloat(row.querySelector('.line-pu')?.value)   || 0;
            const qte  = parseInt(  row.querySelector('.line-qte')?.value)  || 0;
            const mois = parseFloat(row.querySelector('.line-mois')?.value) || 0;
            const m2   = parseFloat(row.querySelector('.line-m2')?.value)   || 0;
            const opt  = row.querySelector('.line-commune')?.selectedOptions?.[0];
            const odp  = parseFloat(opt?.dataset.odp) || 0;
            const tm   = parseFloat(opt?.dataset.tm)  || TM_DEFAULT;
            const lineHt = pu * qte * mois;
            htBrut   += lineHt;
            totalOdp += odp * m2 * qte * mois;
            totalTm  += tm  * m2 * qte * mois;
            const cell = row.querySelector('.line-total');
            if (cell) cell.textContent = fmt(lineHt);
        });

        const remise  = parseFloat(document.getElementById('remise_pct').value) || 0;
        const svcImp  = parseFloat(document.getElementById('services_impression').value)   || 0;
        const svcPose = parseFloat(document.getElementById('services_pose_depose').value)  || 0;

        const netHt  = htBrut * (1 - remise / 100);
        const tvaAmt = netHt * TVA / 100;
        const tspAmt = netHt * TSP / 100;
        const ttc    = netHt + tvaAmt;
        const svcTtc = (svcImp + svcPose) * (1 + TVA / 100);
        const total  = ttc + tspAmt + totalTm + totalOdp + svcTtc;

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

    // ═══════════ AJOUT / SUPPRESSION DE LIGNES ═══════════
    function renumberLines() {
        const rows = tbody.querySelectorAll('.line-row');
        rows.forEach((r, idx) => { const b = r.querySelector('.row-number'); if (b) b.textContent = idx + 1; });
        const label = document.getElementById('lines-count-label');
        if (label) label.textContent = rows.length + ' ligne' + (rows.length > 1 ? 's' : '') + ' · ajoute les panneaux à facturer';
    }

    function bindRow(row) {
        row.querySelectorAll('input.line-m2, input.line-pu, input.line-qte, input.line-mois')
           .forEach(el => el.addEventListener('input', recompute));
        initLineSelect2(row);
        row.querySelector('.line-remove')?.addEventListener('click', () => {
            if (tbody.querySelectorAll('.line-row').length <= 1) {
                alert('Une facture doit comporter au moins une ligne.');
                return;
            }
            const $d = $(row.querySelector('.line-designation'));
            const $c = $(row.querySelector('.line-commune'));
            if ($d.data('select2')) $d.select2('destroy');
            if ($c.data('select2')) $c.select2('destroy');
            row.remove();
            renumberLines();
            recompute();
        });
    }

    // Clone d'un <template> PROPRE (pas d'une ligne déjà initialisée par Select2)
    function addLine() {
        const i = nextIdx++;
        const tr = tpl.content.firstElementChild.cloneNode(true);
        tr.dataset.index = i;
        tr.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace(/__IDX__/g, i); });
        tbody.appendChild(tr);
        bindRow(tr);
        renumberLines();
        recompute();
    }

    addBtn?.addEventListener('click', addLine);
    tbody.querySelectorAll('.line-row').forEach(bindRow);
    ['remise_pct', 'services_impression', 'services_pose_depose'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', recompute);
    });

    recompute();
})();
</script>
@endpush

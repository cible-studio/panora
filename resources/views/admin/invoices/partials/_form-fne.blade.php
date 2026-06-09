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

<form method="POST" action="{{ $action }}" id="form-fne" class="invoice-form">
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
                <table id="lines-table" class="lines-table">
                    <thead>
                        <tr>
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
                                <td class="col-designation">
                                    {{-- Select2 AJAX panneau (filtré par la campagne sélectionnée
                                         en haut). À la sélection, auto-remplit commune + m² + PU. --}}
                                    <select name="lines[{{ $i }}][designation_picker]" class="line-designation" style="width:100%"></select>
                                    <input type="hidden" name="lines[{{ $i }}][designation]" class="line-designation-value" value="{{ $l['designation'] ?? '' }}">
                                </td>
                                <td class="col-commune">
                                    <select name="lines[{{ $i }}][commune_id]" class="line-commune" required style="width:100%">
                                        <option value=""></option>
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
                                <td class="num col-m2">
                                    <input type="number" name="lines[{{ $i }}][dimension_m2]" class="line-m2" required
                                           value="{{ $l['dimension_m2'] ?? 0 }}" min="0" step="0.01">
                                </td>
                                <td class="num col-pu">
                                    <input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" class="line-pu" required
                                           value="{{ $l['pu_ht_mensuel'] ?? 0 }}" min="0" step="1000">
                                </td>
                                <td class="num col-qte">
                                    <input type="number" name="lines[{{ $i }}][quantite]" class="line-qte" required
                                           value="{{ $l['quantite'] ?? 1 }}" min="1" step="1">
                                </td>
                                <td class="num col-mois">
                                    <input type="number" name="lines[{{ $i }}][duree_mois]" class="line-mois" required
                                           value="{{ $l['duree_mois'] ?? 1 }}" min="0.5" step="0.5">
                                </td>
                                <td class="num col-total line-total">0 FCFA</td>
                                <td class="act">
                                    <button type="button" class="btn-line-remove line-remove" title="Supprimer la ligne">🗑</button>
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
                           style="text-align:right">
                </div>
                <div class="mfg">
                    <label>Impression (HT)</label>
                    <input type="number" name="services_impression" id="services_impression" min="0" step="1000"
                           value="{{ old('services_impression', $isEdit ? $invoice->services_impression : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="text-align:right">
                </div>
                <div class="mfg">
                    <label>Pose & dépose (HT)</label>
                    <input type="number" name="services_pose_depose" id="services_pose_depose" min="0" step="1000"
                           value="{{ old('services_pose_depose', $isEdit ? $invoice->services_pose_depose : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="text-align:right">
                </div>
            </div>
        </div>
    </div>

    {{-- ════ RÉCAP LIVE ════ --}}
    <div class="card" style="margin-bottom:16px">
        <div class="card-header"><div class="card-title">💰 Récapitulatif FNE (calculé en direct)</div></div>
        <div class="card-body" style="background:var(--surface2)">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12.5px">
                <div style="color:var(--text3)">Total HT brut</div><div style="text-align:right;" id="rec-brut">0 FCFA</div>
                <div style="color:var(--text3)">Remise</div><div style="text-align:right;color:#b45309" id="rec-remise">0 FCFA</div>
                <div style="color:var(--text2);font-weight:700;padding-top:6px;border-top:1px solid var(--border)">TOTAL HT</div><div style="text-align:right;font-weight:700;padding-top:6px;border-top:1px solid var(--border)" id="rec-netht">0 FCFA</div>
                <div style="color:var(--text3)">TVA ({{ rtrim(rtrim(number_format($tvaRate, 2, ',', ''), '0'), ',') }} %)</div><div style="text-align:right;" id="rec-tva">0 FCFA</div>
                <div style="color:var(--text2);font-weight:800;padding-top:6px;border-top:1px solid var(--border)">TOTAL TTC</div><div style="text-align:right;font-weight:800;padding-top:6px;border-top:1px solid var(--border)" id="rec-ttc">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px;margin-top:8px">TSP ({{ rtrim(rtrim(number_format($tspRate, 2, ',', ''), '0'), ',') }} %)</div><div style="text-align:right;font-size:11.5px;margin-top:8px" id="rec-tsp">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">TM total</div><div style="text-align:right;font-size:11.5px" id="rec-tm">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">ODP total</div><div style="text-align:right;font-size:11.5px" id="rec-odp">0 FCFA</div>
                <div style="color:var(--text3);font-size:11.5px">Services TTC</div><div style="text-align:right;font-size:11.5px" id="rec-svc">0 FCFA</div>
            </div>
            <div style="margin-top:14px;padding:12px 14px;background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#fff;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                <span style="font-weight:800;font-size:14px;letter-spacing:.3px">💰 TOTAL À PAYER</span>
                <span style="font-weight:800;font-size:18px;" id="rec-total">0 FCFA</span>
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

@push('styles')
{{-- Select2 v4 + style CIBLE unifié pour TOUS les Select2 du formulaire
     facture (client, campagne, désignation, commune). Hauteur 40px
     calée sur les autres <input> natifs, padding cohérent, focus
     accent doré. Une seule règle CSS partagée pour cohérence visuelle. --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    /* ── Container Select2 : hauteur + look natif uniforme ────────── */
    .invoice-form .select2-container--default .select2-selection--single {
        height: 40px !important;
        border: 1px solid var(--border) !important;
        border-radius: 8px !important;
        background: var(--surface) !important;
        font-family: inherit !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .invoice-form .select2-container--default.select2-container--focus .select2-selection--single,
    .invoice-form .select2-container--default.select2-container--open  .select2-selection--single {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(232,160,32,.12) !important;
    }
    .invoice-form .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        font-size: 13px !important;
        color: var(--text) !important;
        padding-left: 12px !important;
        padding-right: 28px !important;
    }
    .invoice-form .select2-container--default .select2-selection__placeholder {
        color: var(--text3) !important;
    }
    .invoice-form .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
        right: 6px !important;
    }
    .invoice-form .select2-container--default .select2-selection--single .select2-selection__clear {
        margin-right: 22px !important;
        color: var(--text3) !important;
    }

    /* ── Dropdown : background OPAQUE (sinon options bleed sur la
         page derrière) + z-index au-dessus de tout le formulaire +
         ombre / radius cohérents avec le design system. ─────────── */
    .select2-container--open .select2-dropdown,
    .select2-dropdown {
        background: var(--surface) !important;
        border: 1px solid var(--border2, var(--border)) !important;
        border-radius: 10px !important;
        box-shadow: 0 18px 48px -12px rgba(0, 0, 0, .28), 0 2px 6px rgba(0, 0, 0, .08) !important;
        overflow: hidden;
        z-index: 9999 !important;
    }
    /* Container des résultats + zone scrollable */
    .select2-results,
    .select2-results__options {
        background: var(--surface) !important;
        color: var(--text) !important;
        max-height: 280px !important;
    }
    .select2-search--dropdown {
        padding: 8px !important;
        background: var(--surface) !important;
        border-bottom: 1px solid var(--border);
    }
    .select2-search--dropdown .select2-search__field {
        border: 1px solid var(--border) !important;
        border-radius: 8px !important;
        padding: 8px 10px !important;
        font-size: 13px !important;
        outline: none !important;
        background: var(--surface2) !important;
        color: var(--text) !important;
    }
    .select2-search--dropdown .select2-search__field:focus {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 2px rgba(232, 160, 32, .15) !important;
    }
    /* Chaque option : background plein + séparateur fin */
    .select2-results__option {
        padding: 9px 12px !important;
        font-size: 12.5px !important;
        background: var(--surface) !important;
        color: var(--text) !important;
        border-bottom: 1px solid var(--border);
    }
    .select2-results__option:last-child { border-bottom: none; }
    /* Hover / sélection clavier (highlighted) */
    .select2-results__option--highlighted,
    .select2-results__option--highlighted[aria-selected] {
        background: rgba(232, 160, 32, .14) !important;
        color: var(--text) !important;
    }
    /* Option déjà sélectionnée */
    .select2-results__option[aria-selected="true"]:not(.select2-results__option--highlighted) {
        background: rgba(232, 160, 32, .06) !important;
        color: var(--accent-dark, var(--accent)) !important;
        font-weight: 600;
    }
    /* Messages "Aucun résultat" / "Recherche…" */
    .select2-results__message,
    .select2-results__option.loading-results {
        background: var(--surface) !important;
        color: var(--text3) !important;
        font-style: italic;
    }

    /* ── Tableau des lignes : layout centralisé (ex-inline styles) ── */
    .lines-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        min-width: 920px;
    }
    .lines-table thead tr {
        background: var(--surface2);
        color: var(--text3);
    }
    .lines-table th {
        padding: 10px 10px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        text-align: left;
    }
    .lines-table th.num { text-align: right; }
    .lines-table th.act { width: 44px; }
    .lines-table td {
        padding: 8px 10px;
        border-top: 1px solid var(--border);
        vertical-align: middle;
    }
    .lines-table td.num { text-align: right; }
    .lines-table td.act { text-align: center; width: 44px; }
    .lines-table .col-designation { min-width: 280px; }
    .lines-table .col-commune     { min-width: 160px; }
    .lines-table .col-m2          { width: 90px; }
    .lines-table .col-pu          { width: 130px; }
    .lines-table .col-qte         { width: 75px; }
    .lines-table .col-mois        { width: 85px; }
    .lines-table .col-total       { width: 120px; font-weight: 800; color: var(--accent); white-space: nowrap; }
    .lines-table tbody tr:hover td { background: rgba(232, 160, 32, .03); }

    /* Inputs natifs dans le tableau — compacts 34px, focus accent */
    .lines-table input[type="number"],
    .lines-table input[type="text"] {
        height: 34px !important;
        width: 100% !important;
        padding: 0 10px !important;
        border: 1px solid var(--border) !important;
        border-radius: 6px !important;
        background: var(--surface) !important;
        font-size: 12px !important;
        text-align: right !important;
    }
    .lines-table input[type="number"]:focus,
    .lines-table input[type="text"]:focus {
        border-color: var(--accent) !important;
        outline: none !important;
        box-shadow: 0 0 0 2px rgba(232, 160, 32, .15) !important;
    }

    /* Select2 dans le tableau — compact 34px + LARGEUR PLEINE.
       Sans `width: 100% !important` sur .select2-container, Select2 4.x
       fixe une largeur en pixels au moment de l'init qui collapse le
       container à 60-80px (vu : un carré minuscule avec juste le chevron),
       ignorant le `style="width:100%"` qu'on a mis sur le <select>. */
    .lines-table .select2-container {
        width: 100% !important;
        display: block;
    }
    .lines-table .select2-container--default .select2-selection--single {
        height: 34px !important;
        border-radius: 6px !important;
        border: 1px solid var(--border) !important;
        background: var(--surface) !important;
    }
    .lines-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 32px !important;
        font-size: 12px !important;
        padding-left: 10px !important;
        padding-right: 28px !important;
        color: var(--text) !important;
        /* Empêche le placeholder ("Choisir une campagne d'abord…") de
           wrapper sur 2-3 lignes dans la cellule étroite — on tronque
           avec ellipsis et on garde la hauteur 34px stable. */
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .lines-table .select2-container--default .select2-selection__placeholder {
        color: var(--text3) !important;
    }
    .lines-table .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 32px !important;
        right: 4px !important;
    }
    .lines-table .select2-container--default.select2-container--focus .select2-selection--single,
    .lines-table .select2-container--default.select2-container--open  .select2-selection--single {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 2px rgba(232, 160, 32, .15) !important;
    }

    /* Bouton supprimer ligne — discret par défaut, rouge au hover */
    .btn-line-remove {
        background: transparent;
        border: 1px solid transparent;
        color: var(--text3);
        font-size: 14px;
        line-height: 1;
        cursor: pointer;
        padding: 6px 8px;
        border-radius: 6px;
        transition: background .15s, border-color .15s, color .15s;
    }
    .btn-line-remove:hover {
        background: rgba(239, 68, 68, .1);
        border-color: rgba(239, 68, 68, .25);
        color: #ef4444;
    }

    /* ── Polish global des inputs natifs du formulaire facture ──
       (en dehors du tableau, qui a ses propres règles plus compactes) */
    .invoice-form .mfg input[type="text"],
    .invoice-form .mfg input[type="number"],
    .invoice-form .mfg input[type="date"],
    .invoice-form .mfg textarea {
        height: 40px;
        width: 100%;
        padding: 0 12px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        color: var(--text);
        font-family: inherit;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
    }
    .invoice-form .mfg textarea {
        height: auto;
        min-height: 60px;
        padding: 10px 12px;
        line-height: 1.5;
        resize: vertical;
    }
    .invoice-form .mfg input:hover,
    .invoice-form .mfg textarea:hover { border-color: var(--text3); }
    .invoice-form .mfg input:focus,
    .invoice-form .mfg textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
    }
    .invoice-form .mfg input.error,
    .invoice-form .mfg input:invalid:not(:placeholder-shown) { border-color: rgba(239, 68, 68, .5); }
    .invoice-form .mfg input[readonly] { background: var(--surface2); cursor: not-allowed; color: var(--text2); }
    .invoice-form .mfg label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .5px;
        color: var(--text2);
        margin-bottom: 6px;
    }
    .invoice-form .mfg { margin-bottom: 14px; }
    .invoice-form .form-2col,
    .invoice-form .form-3col {
        display: grid;
        gap: 14px;
    }
    .invoice-form .form-2col { grid-template-columns: 1fr 1fr; }
    .invoice-form .form-3col { grid-template-columns: 1fr 1fr 1fr; }
    @media (max-width: 720px) {
        .invoice-form .form-2col,
        .invoice-form .form-3col { grid-template-columns: 1fr; }
    }

    /* ── Template option PANNEAU (rich) ───────────────────────────── */
    .s2-pan-row { display: flex; gap: 10px; align-items: flex-start; padding: 2px 0; }
    .s2-pan-info { flex: 1; min-width: 0; }
    .s2-pan-ref {  font-weight: 800; color: var(--accent-dark); font-size: 12.5px; display: flex; align-items: center; gap: 6px; }
    .s2-pan-name { font-size: 12px; color: var(--text); margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .s2-pan-meta { font-size: 10.5px; color: var(--text3); margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
    .s2-pan-pill { display: inline-block; padding: 1px 6px; border-radius: 6px; font-size: 9.5px; font-weight: 700; }
    .s2-pan-pill.ext { background: rgba(59,130,246,.12); color: #1d4ed8; }
    .s2-pan-pill.int { background: rgba(232,160,32,.12); color: var(--accent-dark); }

    /* ── Template option CAMPAGNE ─────────────────────────────────── */
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

    const tbody = document.getElementById('lines-tbody');
    const addBtn = document.getElementById('add-line');
    const clientSel = document.getElementById('inv-client');
    const campaignSel = document.getElementById('inv-campaign');
    const $client = $(clientSel);
    const $campaign = $(campaignSel);
    let nextIdx = {{ count($lines) }};

    function fmt(n) { return Math.round(n).toLocaleString('fr-FR') + ' FCFA'; }

    // ═══════════════════════════════════════════════════════════════
    // CLIENT + CAMPAGNE en Select2 avec cascading filter
    // ═══════════════════════════════════════════════════════════════
    $client.select2({
        placeholder: '— Choisir un client —',
        allowClear: true,
        minimumResultsForSearch: 0,
    });

    function campaignTemplate(item) {
        if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
        const $opt = $(item.element);
        const cid = $opt.data('client-id');
        return $(
            '<div class="s2-camp-row">' +
                '<div class="s2-camp-name">' + (item.text || '').split(' — ')[0] + '</div>' +
                (item.text && item.text.includes(' — ')
                    ? '<div class="s2-camp-meta">👤 ' + item.text.split(' — ').slice(1).join(' — ') + '</div>'
                    : '') +
            '</div>'
        );
    }

    $campaign.select2({
        placeholder: clientSel?.value
            ? '— Choisir une campagne (optionnel) —'
            : '— Choisis d\'abord un client —',
        allowClear: true,
        minimumResultsForSearch: 0,
        templateResult: campaignTemplate,
        templateSelection: (item) => item.text || '— Aucune —',
    });

    // ── Filtre les options campagne selon le client choisi ──
    // Les <option> portent data-client-id ; on masque celles qui ne
    // correspondent pas. Quand le client change, on reset la campagne
    // sélectionnée si elle n'est plus visible.
    function refreshCampaignOptions() {
        const cid = clientSel.value;
        let visible = 0;
        Array.from(campaignSel.options).forEach(opt => {
            if (!opt.value) { opt.hidden = false; return; }
            const ok = !cid || opt.dataset.clientId === cid;
            opt.hidden = !ok;
            opt.disabled = !ok; // pour le HTML form aussi
            if (ok) visible++;
        });
        // Reset si la sélection courante ne matche plus le nouveau client
        const cur = campaignSel.selectedOptions[0];
        if (cur && cur.hidden) {
            $campaign.val(null).trigger('change');
        }
        // Réinit le placeholder selon l'état
        $campaign.select2('destroy');
        $campaign.select2({
            placeholder: cid
                ? (visible > 0 ? '— Choisir une campagne (optionnel) —' : '— Aucune campagne pour ce client —')
                : '— Choisis d\'abord un client —',
            allowClear: true,
            minimumResultsForSearch: 0,
            templateResult: campaignTemplate,
            templateSelection: (item) => item.text || '— Aucune —',
        });
    }

    $client.on('select2:select select2:clear', () => {
        refreshCampaignOptions();
    });

    $campaign.on('select2:select select2:clear', () => {
        // Quand la campagne change, on doit aussi forcer le client
        // (cohérence : si l'admin a choisi une campagne sans client,
        // on remonte le client de la campagne automatiquement).
        const opt = campaignSel.selectedOptions[0];
        if (opt && opt.value) {
            const camCid = opt.dataset.clientId;
            if (camCid && clientSel.value !== camCid) {
                $client.val(camCid).trigger('change');
                refreshCampaignOptions();
                // re-set la campagne après refresh
                $campaign.val(opt.value).trigger('change');
            }
        }
        // Toutes les lignes de désignation doivent être reset car les
        // panneaux disponibles changent avec la campagne.
        resetAllDesignations();
    });

    // Init initial : filtre selon le client pré-sélectionné
    refreshCampaignOptions();
    if (campaignSel.value) $campaign.trigger('change');

    function resetAllDesignations() {
        tbody.querySelectorAll('.line-row').forEach(row => {
            const $sel = $(row.querySelector('.line-designation'));
            $sel.val(null).trigger('change');
            $sel.find('option').remove();
            row.querySelector('.line-designation-value').value = '';
            $sel.data('select2-init', false);
            if ($sel.data('select2')) $sel.select2('destroy');
            initLineSelect2(row);
        });
        recompute();
    }

    // ═══════════════════════════════════════════════════════════════
    // INIT SELECT2 — désignation (AJAX panneau) + commune (statique)
    // ═══════════════════════════════════════════════════════════════
    function s2Pan(item) {
        if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
        // Item issu de l'AJAX → on a tous les champs panneau
        if (item.ref) {
            const sourceBadge = item.is_external
                ? '<span class="s2-pan-pill ext">🤝 Externe</span>'
                : '<span class="s2-pan-pill int">🏢 CIBLE</span>';
            return $(`
                <div class="s2-pan-row">
                    <div class="s2-pan-info">
                        <div class="s2-pan-ref">${item.ref} ${sourceBadge}</div>
                        <div class="s2-pan-name">${item.name || ''}</div>
                        <div class="s2-pan-meta">
                            <span>📍 ${item.commune_name || '—'}</span>
                            ${item.dimension_m2 ? '<span>📐 ' + item.dimension_m2 + ' m²</span>' : ''}
                            ${item.pu_suggested ? '<span style="color:var(--accent-dark);font-weight:700">' + Math.round(item.pu_suggested).toLocaleString('fr-FR') + ' F/mois</span>' : ''}
                        </div>
                    </div>
                </div>
            `);
        }
        // Item "tag" libre (l'admin a tapé du texte)
        return $('<span>📝 ' + (item.text || '') + '</span>');
    }

    function s2PanSelection(item) {
        if (!item.id && !item.text) return '— Choisir un panneau ou taper —';
        return item.designation || item.text;
    }

    function initLineSelect2(row) {
        const $design = $(row.querySelector('.line-designation'));
        const $commune = $(row.querySelector('.line-commune'));

        // ── Désignation : Select2 AJAX + tags (texte libre OK) ──
        if (!$design.data('select2-init')) {
            // Pré-remplissage : si la ligne a déjà une désignation
            // (cas edit), on l'injecte comme option pré-sélectionnée.
            const initialDesign = row.querySelector('.line-designation-value').value;
            if (initialDesign) {
                const opt = new Option(initialDesign, 'manual:' + initialDesign, true, true);
                $design[0].appendChild(opt);
            }
            $design.select2({
                placeholder: campaignSel?.value
                    ? 'Choisir un panneau de la campagne…'
                    : 'Choisir une campagne d\'abord…',
                allowClear: true,
                tags: true,
                minimumInputLength: 0,
                language: {
                    noResults: () => campaignSel?.value
                        ? 'Aucun panneau correspondant — tape pour saisie libre.'
                        : 'Choisis d\'abord une campagne en haut.',
                    inputTooShort: () => 'Tape pour rechercher…',
                    searching: () => 'Recherche…',
                },
                ajax: {
                    url: LOOKUP_PANELS_URL,
                    delay: 220,
                    dataType: 'json',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    data: (params) => ({
                        q: params.term || '',
                        page: params.page || 1,
                        campaign_id: campaignSel?.value || '',
                    }),
                    processResults: (data, params) => {
                        params.page = params.page || 1;
                        return {
                            results: data.results || [],
                            pagination: { more: data.pagination?.more === true },
                        };
                    },
                    cache: true,
                },
                createTag: (params) => {
                    const term = (params.term || '').trim();
                    if (term === '') return null;
                    // Tag = ligne libre. id préfixé "manual:" pour distinguer du panneau.
                    return { id: 'manual:' + term, text: term, designation: term };
                },
                templateResult: s2Pan,
                templateSelection: s2PanSelection,
                escapeMarkup: m => m,
            });
            $design.data('select2-init', true);

            $design.on('select2:select', function (e) {
                const item = e.params.data;
                const designationField = row.querySelector('.line-designation-value');
                const m2Field          = row.querySelector('.line-m2');
                const puField          = row.querySelector('.line-pu');

                // Cas tag libre : on ne touche qu'à la désignation
                if (String(item.id).startsWith('manual:')) {
                    designationField.value = item.designation || item.text;
                    recompute();
                    return;
                }

                // Cas panneau : on remplit tout (designation, commune, m², PU)
                designationField.value = item.designation || item.text;
                if (item.dimension_m2 && m2Field) m2Field.value = item.dimension_m2;
                if (item.pu_suggested && puField) puField.value = Math.round(item.pu_suggested);

                // Sélectionner la commune dans le select Commune
                if (item.commune_id) {
                    $commune.val(String(item.commune_id)).trigger('change');
                }
                recompute();
            });
            $design.on('select2:clear', function () {
                row.querySelector('.line-designation-value').value = '';
                recompute();
            });
        }

        // ── Commune : Select2 statique (consistance UX) ──
        if (!$commune.data('select2-init')) {
            $commune.select2({
                placeholder: 'Commune',
                minimumResultsForSearch: 0, // affiche le champ search dès 1+ communes
            });
            $commune.data('select2-init', true);
            $commune.on('change', recompute);
        }
    }

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
        // Inputs natifs (m², PU, qté, mois) → recompute live
        row.querySelectorAll('input.line-m2, input.line-pu, input.line-qte, input.line-mois').forEach(el => {
            el.addEventListener('input', recompute);
        });
        // Select2 (désignation, commune) sont initialisés via initLineSelect2
        initLineSelect2(row);

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

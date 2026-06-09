{{--
    Formulaire facture FNE — refonte complète, design simple et robuste.

    Contrat (drop-in inchangé) :
      $invoice, $clients, $campaigns, $reference, $preselect, $isEdit, $action, $method

    Choix techniques :
      • Tout le CSS est scopé sous `.fne` → zéro conflit avec le reste du site.
      • Selects NATIFS pour client / campagne / commune (cascading en vanilla JS) :
        pas de Select2 inutile = pas de bug de fond transparent / z-index / largeur.
      • Select2 UNIQUEMENT sur la désignation de ligne (qui a besoin d'AJAX pour
        rechercher les panneaux de la campagne sélectionnée).
      • Services annexes : N lignes libres (label + prix HT TVA-able).
      • Récap calculé en direct.
--}}

@php
    $isEdit    = isset($invoice) && $invoice !== null;
    $locked    = $isEdit && $invoice->isLocked();
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

    $existingServices = $isEdit && $invoice->services
        ? $invoice->services->map(fn($s) => ['label' => $s->label, 'prix_ht' => (float) $s->prix_ht])->all()
        : [];
    if ($isEdit && empty($existingServices)) {
        if ((float) $invoice->services_impression > 0) {
            $existingServices[] = ['label' => "Frais d'impression", 'prix_ht' => (float) $invoice->services_impression];
        }
        if ((float) $invoice->services_pose_depose > 0) {
            $existingServices[] = ['label' => 'Frais de pose et dépose', 'prix_ht' => (float) $invoice->services_pose_depose];
        }
    }
    $oldServices = old('services');
    $services    = is_array($oldServices) && !empty($oldServices) ? $oldServices : $existingServices;

    $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate']);
    $tvaRate   = (float) config('billing.tva_rate', 18);
    $tspRate   = (float) config('billing.tsp_rate', 3);
    $tmDefault = (float) config('billing.tm_default', 1000);
    $fmtPct    = fn($v) => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');

    $selClient   = old('client_id',   $isEdit ? $invoice->client_id   : ($preselect['client_id']   ?? null));
    $selCampaign = old('campaign_id', $isEdit ? $invoice->campaign_id : ($preselect['campaign_id'] ?? null));
@endphp

<div class="fne">

    @if($errors->any())
    <div class="fne-alert fne-alert--error">
        <div class="fne-alert-title">⚠️ Vérifie les champs suivants</div>
        @foreach($errors->all() as $error)
            <div class="fne-alert-item">• {{ $error }}</div>
        @endforeach
    </div>
    @endif

    @if($locked)
    <div class="fne-alert fne-alert--locked">
        🔒 <strong>Facture verrouillée</strong> le {{ $invoice->locked_at->format('d/m/Y à H:i') }}@if($invoice->lockedBy) par {{ $invoice->lockedBy->name }}@endif.
        Déverrouille-la depuis la fiche détail pour la modifier.
    </div>
    @endif

    <form method="POST" action="{{ $action }}" id="fne-form" autocomplete="off">
        @csrf
        @if($method === 'PUT') @method('PUT') @endif

        {{-- ════════════ 1. INFOS ════════════ --}}
        <section class="fne-card">
            <div class="fne-card-head">
                <span class="fne-chip" style="--c:#3b82f6">📄</span>
                <div>
                    <div class="fne-card-title">Informations</div>
                    <div class="fne-card-sub">Référence, dates, client et campagne</div>
                </div>
            </div>
            <div class="fne-card-body">
                <div class="fne-grid-2">
                    <div class="fne-field">
                        <label>Référence <span class="req">*</span></label>
                        <input type="text" name="reference" required {{ $locked ? 'readonly' : '' }}
                               value="{{ old('reference', $reference ?? ($invoice->reference ?? '')) }}"
                               class="{{ $errors->has('reference') ? 'has-error' : '' }}">
                    </div>
                    <div class="fne-field">
                        <label>Date d'émission <span class="req">*</span></label>
                        <input type="date" name="issued_at" required {{ $locked ? 'readonly' : '' }}
                               value="{{ old('issued_at', $isEdit ? $invoice->issued_at?->format('Y-m-d') : date('Y-m-d')) }}">
                    </div>
                </div>

                <div class="fne-grid-2">
                    <div class="fne-field">
                        <label>Client <span class="req">*</span></label>
                        <select name="client_id" id="fne-client" required {{ $locked ? 'disabled' : '' }}>
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ (string) $selClient === (string) $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($locked)<input type="hidden" name="client_id" value="{{ $invoice->client_id }}">@endif
                    </div>
                    <div class="fne-field">
                        <label>Campagne <span class="opt">— optionnel</span></label>
                        <select name="campaign_id" id="fne-campaign" {{ $locked ? 'disabled' : '' }}>
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

                <div class="fne-field">
                    <label>Notes / Conditions client <span class="opt">— affiché en bas de facture</span></label>
                    <textarea name="notes_client" rows="2" maxlength="2000" {{ $locked ? 'readonly' : '' }}
                              placeholder="{{ config('billing.payment_terms_default') }}">{{ old('notes_client', $isEdit ? $invoice->notes_client : '') }}</textarea>
                </div>
            </div>
        </section>

        {{-- ════════════ 2. LIGNES DE FACTURATION ════════════ --}}
        <section class="fne-card">
            <div class="fne-card-head">
                <span class="fne-chip" style="--c:#e8a020">📋</span>
                <div>
                    <div class="fne-card-title">Lignes de facturation</div>
                    <div class="fne-card-sub" id="fne-lines-sub">{{ count($lines) }} ligne{{ count($lines) > 1 ? 's' : '' }} · panneaux à facturer</div>
                </div>
            </div>

            <div class="fne-lines">
                <div class="fne-lines-header">
                    <div class="lh-num">#</div>
                    <div class="lh-designation">Désignation</div>
                    <div class="lh-commune">Commune</div>
                    <div class="lh-num-cell">m²</div>
                    <div class="lh-num-cell">PU HT/mois</div>
                    <div class="lh-num-cell">Qté</div>
                    <div class="lh-num-cell">Mois</div>
                    <div class="lh-num-cell">Total HT</div>
                    <div class="lh-act"></div>
                </div>
                <div id="fne-lines-body">
                    @foreach($lines as $i => $l)
                        @include('admin.invoices.partials._line-row', [
                            'i'        => $i,
                            'l'        => $l,
                            'communes' => $communes,
                            'rateDate' => $rateDate,
                        ])
                    @endforeach
                </div>
            </div>

            @unless($locked)
            <div class="fne-card-foot">
                <button type="button" id="fne-add-line" class="fne-btn fne-btn-add">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajouter une ligne
                </button>
            </div>
            @endunless
        </section>

        {{-- ════════════ 3. REMISE ════════════ --}}
        <section class="fne-card">
            <div class="fne-card-head">
                <span class="fne-chip" style="--c:#a855f7">⚙️</span>
                <div>
                    <div class="fne-card-title">Remise globale</div>
                    <div class="fne-card-sub">S'applique au total HT des lignes panneaux (pas sur les services)</div>
                </div>
            </div>
            <div class="fne-card-body">
                <div class="fne-field" style="max-width:240px;margin:0">
                    <label>Remise (%)</label>
                    <input type="number" name="remise_pct" id="fne-remise" min="0" max="100" step="0.5"
                           value="{{ old('remise_pct', $isEdit ? $invoice->remise_pct : 0) }}" {{ $locked ? 'readonly' : '' }}>
                </div>
            </div>
        </section>

        {{-- ════════════ 4. SERVICES ANNEXES ════════════ --}}
        <section class="fne-card">
            <div class="fne-card-head">
                <span class="fne-chip" style="--c:#22c55e">🧾</span>
                <div style="flex:1;min-width:0">
                    <div class="fne-card-title">Services annexes</div>
                    <div class="fne-card-sub">Impression, pose, création… libellé libre + prix HT (TVA {{ $fmtPct($tvaRate) }}%)</div>
                </div>
                @unless($locked)
                    <button type="button" class="fne-btn fne-btn-ghost" onclick="fneAddService()">+ Ajouter</button>
                @endunless
            </div>
            <div class="fne-card-body" id="fne-svc-wrap">
                <div id="fne-svc-empty" class="fne-empty" style="display:{{ empty($services) ? 'block' : 'none' }}">
                    Aucun service annexe pour le moment.
                </div>
                <div id="fne-svc-rows" style="display:{{ empty($services) ? 'none' : 'block' }}">
                    @foreach($services as $i => $s)
                    <div class="fne-svc-row" data-idx="{{ $i }}">
                        <div class="fne-field" style="flex:1;margin:0">
                            <label>Libellé</label>
                            <input type="text" name="services[{{ $i }}][label]" value="{{ $s['label'] ?? '' }}"
                                   maxlength="200" placeholder="Ex: Frais d'impression" class="svc-label"
                                   {{ $locked ? 'readonly' : '' }} required>
                        </div>
                        <div class="fne-field" style="width:140px;margin:0">
                            <label>Prix HT (FCFA)</label>
                            <input type="number" name="services[{{ $i }}][prix_ht]" value="{{ $s['prix_ht'] ?? 0 }}"
                                   min="0" step="1000" class="svc-prix svc-num"
                                   {{ $locked ? 'readonly' : '' }} required>
                        </div>
                        <div class="fne-field" style="width:140px;margin:0">
                            <label>TTC</label>
                            <div class="svc-ttc">—</div>
                        </div>
                        @unless($locked)
                        <button type="button" class="fne-btn-remove" onclick="fneRemoveService(this)" title="Supprimer">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                        @endunless
                    </div>
                    @endforeach
                </div>
                <div class="fne-svc-subtotal" id="fne-svc-subtotal-wrap" style="display:{{ empty($services) ? 'none' : 'flex' }}">
                    <span>Sous-total services TTC</span>
                    <strong id="fne-svc-subtotal">0 FCFA</strong>
                </div>
            </div>
        </section>

        {{-- ════════════ 5. RÉCAP FNE ════════════ --}}
        <section class="fne-card fne-recap">
            <div class="fne-card-head">
                <span class="fne-chip" style="--c:#e8a020">💰</span>
                <div>
                    <div class="fne-card-title">Récapitulatif FNE</div>
                    <div class="fne-card-sub">Calculé en direct au fil de la saisie</div>
                </div>
            </div>
            <div class="fne-recap-body">
                <div class="fne-recap-grid">
                    <div class="fne-recap-col">
                        <div class="rl"><span>Total HT brut</span><span id="rec-brut">0 FCFA</span></div>
                        <div class="rl"><span>Remise</span><span id="rec-remise" class="neg">0 FCFA</span></div>
                        <div class="rl-sep"></div>
                        <div class="rl strong"><span>Total HT</span><span id="rec-netht">0 FCFA</span></div>
                        <div class="rl"><span>TVA ({{ $fmtPct($tvaRate) }}%)</span><span id="rec-tva">0 FCFA</span></div>
                        <div class="rl-sep"></div>
                        <div class="rl strong"><span>Total TTC</span><span id="rec-ttc">0 FCFA</span></div>
                    </div>
                    <div class="fne-recap-col alt">
                        <div class="rl-cap">Autres taxes & services</div>
                        <div class="rl sub"><span>TSP ({{ $fmtPct($tspRate) }}%)</span><span id="rec-tsp">0 FCFA</span></div>
                        <div class="rl sub"><span>Taxe municipale (TM)</span><span id="rec-tm">0 FCFA</span></div>
                        <div class="rl sub"><span>Domaine public (ODP)</span><span id="rec-odp">0 FCFA</span></div>
                        <div class="rl sub"><span>Services TTC</span><span id="rec-svc">0 FCFA</span></div>
                    </div>
                </div>
                <div class="fne-recap-total">
                    <span class="lbl">Total à payer</span>
                    <span class="val" id="rec-total">0 FCFA</span>
                </div>
            </div>
        </section>

        <div class="fne-actions">
            <a href="{{ route('admin.invoices.index') }}" class="fne-btn fne-btn-ghost">Annuler</a>
            @unless($locked)
            <button type="submit" class="fne-btn fne-btn-primary">
                {{ $isEdit ? '✅ Enregistrer les modifications' : '✅ Créer la facture' }}
            </button>
            @endunless
        </div>
    </form>
</div>

{{-- ════════ Template ligne vierge (cloné par JS pour ajouter une nouvelle ligne) ════════ --}}
<template id="fne-line-tpl">
    @include('admin.invoices.partials._line-row', [
        'i'        => '__IDX__',
        'l'        => ['designation' => '', 'commune_id' => '', 'dimension_m2' => 0, 'pu_ht_mensuel' => 0, 'quantite' => 1, 'duree_mois' => 1],
        'communes' => $communes,
        'rateDate' => $rateDate,
    ])
</template>

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
/* ═══════════════════════════════════════════════════════════════
   FNE — styles 100% scopés sous .fne pour éviter tout conflit.
═══════════════════════════════════════════════════════════════ */

.fne { max-width: 1040px; margin: 0 auto; }

/* Variables locales pour rester cohérent même si le thème global change */
.fne {
    --fne-bg:      var(--surface, #fff);
    --fne-bg2:    var(--surface2, #f9fafb);
    --fne-border: var(--border, #e5e7eb);
    --fne-text:   var(--text, #111827);
    --fne-text2:  var(--text2, #4b5563);
    --fne-text3:  var(--text3, #9ca3af);
    --fne-accent: var(--accent, #e8a020);
    --fne-accent-dark: var(--accent-dark, #c97d10);
    --fne-red:    #ef4444;
    --fne-green:  #22c55e;
}

/* ── Alertes ──────────────────────────────────────────────── */
.fne-alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: 13px;
    line-height: 1.5;
}
.fne-alert--error  { background: rgba(239, 68, 68, .08); border: 1px solid rgba(239, 68, 68, .3); color: #b91c1c; }
.fne-alert--locked { background: rgba(107, 114, 128, .1); border: 1px solid rgba(107, 114, 128, .3); color: #374151; }
.fne-alert-title { font-weight: 700; margin-bottom: 6px; }
.fne-alert-item  { font-size: 12.5px; }

/* ── Cartes ───────────────────────────────────────────────── */
.fne-card {
    background: var(--fne-bg);
    border: 1px solid var(--fne-border);
    border-radius: 14px;
    margin-bottom: 16px;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .03);
}
.fne-card-head {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--fne-border);
    background: var(--fne-bg2);
}
.fne-card-body { padding: 18px 20px; }
.fne-card-foot {
    padding: 14px 20px;
    border-top: 1px solid var(--fne-border);
    background: var(--fne-bg2);
    display: flex;
    justify-content: center;
}
.fne-chip {
    width: 40px; height: 40px;
    border-radius: 11px;
    background: color-mix(in srgb, var(--c, var(--fne-accent)) 14%, transparent);
    color: var(--c, var(--fne-accent));
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.fne-card-title { font-size: 15px; font-weight: 800; color: var(--fne-text); margin-bottom: 2px; }
.fne-card-sub   { font-size: 12px; color: var(--fne-text3); line-height: 1.4; }

/* ── Grids ────────────────────────────────────────────────── */
.fne-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
.fne-grid-2:last-child { margin-bottom: 0; }
@media (max-width: 720px) { .fne-grid-2 { grid-template-columns: 1fr; } }

/* ── Champs (label + input/select/textarea) ───────────────── */
.fne-field { display: flex; flex-direction: column; }
.fne-field label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: var(--fne-text2);
    margin-bottom: 6px;
}
.fne-field .req { color: var(--fne-red); }
.fne-field .opt { font-size: 10.5px; color: var(--fne-text3); font-weight: 500; text-transform: none; letter-spacing: 0; margin-left: 4px; }

.fne input[type="text"],
.fne input[type="number"],
.fne input[type="date"],
.fne textarea,
.fne select {
    width: 100%;
    height: 40px;
    padding: 0 12px;
    background: var(--fne-bg);
    border: 1px solid var(--fne-border);
    border-radius: 9px;
    font-size: 13px;
    color: var(--fne-text);
    font-family: inherit;
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.fne textarea {
    height: auto;
    min-height: 60px;
    padding: 10px 12px;
    line-height: 1.5;
    resize: vertical;
}
/* Selects natifs avec chevron custom (zéro Select2 ici) */
.fne select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 12px center;
    padding-right: 36px;
    cursor: pointer;
}
.fne select:disabled,
.fne input[readonly] { background: var(--fne-bg2); cursor: not-allowed; color: var(--fne-text2); }
.fne input:hover,
.fne select:hover,
.fne textarea:hover { border-color: var(--fne-text3); }
.fne input:focus,
.fne select:focus,
.fne textarea:focus {
    border-color: var(--fne-accent);
    box-shadow: 0 0 0 3px rgba(232, 160, 32, .15);
}
.fne input.has-error { border-color: rgba(239, 68, 68, .5); }
.fne input::-webkit-outer-spin-button,
.fne input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.fne input[type="number"] { -moz-appearance: textfield; }

/* ── Boutons ──────────────────────────────────────────────── */
.fne-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 40px;
    padding: 0 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    border: 1px solid transparent;
    transition: all .15s;
    font-family: inherit;
}
.fne-btn-primary {
    background: var(--fne-accent);
    color: #fff;
    box-shadow: 0 4px 12px -4px rgba(232, 160, 32, .5);
}
.fne-btn-primary:hover { background: var(--fne-accent-dark); transform: translateY(-1px); }
.fne-btn-ghost {
    background: var(--fne-bg2);
    color: var(--fne-text2);
    border-color: var(--fne-border);
}
.fne-btn-ghost:hover { background: var(--fne-bg); border-color: var(--fne-text3); color: var(--fne-text); }
.fne-btn-add {
    background: var(--fne-accent);
    color: #fff;
    box-shadow: 0 4px 12px -4px rgba(232, 160, 32, .5);
}
.fne-btn-add:hover { background: var(--fne-accent-dark); transform: translateY(-1px); }
.fne-btn-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px; height: 34px;
    background: transparent;
    border: 1px solid var(--fne-border);
    border-radius: 8px;
    color: var(--fne-text3);
    cursor: pointer;
    transition: all .15s;
    align-self: flex-end;
    margin-bottom: 0;
    flex-shrink: 0;
}
.fne-btn-remove:hover {
    background: rgba(239, 68, 68, .1);
    border-color: rgba(239, 68, 68, .4);
    color: var(--fne-red);
}

/* ═══════════════════════════════════════════════════════════
   LIGNES DE FACTURATION — grid layout (pas table) :
   robuste, alignable, responsive sans bidouillage. Chaque
   ligne est une row à 9 colonnes ; le header utilise le même
   layout pour aligner parfaitement.
═══════════════════════════════════════════════════════════ */
.fne-lines {
    overflow-x: auto;
}
.fne-lines-header,
.fne-line {
    display: grid;
    grid-template-columns:
        44px           /* # */
        minmax(260px, 1.6fr)  /* Désignation */
        minmax(160px, 1fr)    /* Commune */
        90px           /* m² */
        130px          /* PU */
        80px           /* Qté */
        85px           /* Mois */
        130px          /* Total HT */
        44px;          /* Action */
    gap: 8px;
    align-items: center;
    min-width: 1020px;
    padding: 10px 16px;
}
.fne-lines-header {
    background: var(--fne-bg2);
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .6px;
    color: var(--fne-text3);
    border-bottom: 1px solid var(--fne-border);
}
.fne-lines-header .lh-num-cell { text-align: right; }
.fne-lines-header .lh-num { text-align: center; }

.fne-line {
    border-bottom: 1px solid var(--fne-border);
    transition: background .12s;
}
.fne-line:hover { background: rgba(232, 160, 32, .04); }
.fne-line:hover .fne-line-num { background: var(--fne-accent); color: #fff; border-color: var(--fne-accent); }
.fne-line:last-child { border-bottom: none; }

.fne-line-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px; height: 28px;
    background: var(--fne-bg);
    border: 1px solid var(--fne-border);
    color: var(--fne-text3);
    font-size: 11px;
    font-weight: 800;
    border-radius: 999px;
    transition: all .15s;
    justify-self: center;
}

.fne-line .line-num-input { text-align: right !important; font-variant-numeric: tabular-nums; }
.fne-line-total {
    text-align: right;
    font-weight: 800;
    color: var(--fne-accent);
    font-variant-numeric: tabular-nums;
    font-size: 13.5px;
    white-space: nowrap;
}

/* ── Select2 UNIQUEMENT pour la désignation (recherche AJAX) ── */
.fne .select2-container { width: 100% !important; display: block; }
.fne .select2-container--default .select2-selection--single {
    height: 40px !important;
    background: var(--fne-bg) !important;
    border: 1px solid var(--fne-border) !important;
    border-radius: 9px !important;
    transition: border-color .15s, box-shadow .15s;
}
.fne .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px !important;
    color: var(--fne-text) !important;
    font-size: 13px !important;
    padding: 0 32px 0 12px !important;
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
}
.fne .select2-container--default .select2-selection__placeholder { color: var(--fne-text3) !important; }
.fne .select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 38px !important;
    right: 8px !important;
}
.fne .select2-container--default.select2-container--focus .select2-selection--single,
.fne .select2-container--default.select2-container--open  .select2-selection--single {
    border-color: var(--fne-accent) !important;
    box-shadow: 0 0 0 3px rgba(232, 160, 32, .15) !important;
}
/* Dropdown Select2 : fond opaque + z-index élevé + ombre marquée
   pour ne PAS bleed sur la page comme dans le bug précédent. */
.select2-container--open .select2-dropdown,
.select2-dropdown {
    background: var(--surface, #fff) !important;
    border: 1px solid var(--border, #e5e7eb) !important;
    border-radius: 10px !important;
    box-shadow: 0 16px 40px -10px rgba(0, 0, 0, .25), 0 2px 4px rgba(0, 0, 0, .08) !important;
    z-index: 99999 !important;
    overflow: hidden;
}
.select2-results,
.select2-results__options { background: var(--surface, #fff) !important; max-height: 280px !important; }
.select2-search--dropdown { padding: 8px !important; background: var(--surface, #fff) !important; border-bottom: 1px solid var(--border, #e5e7eb); }
.select2-search--dropdown .select2-search__field {
    border: 1px solid var(--border, #e5e7eb) !important;
    border-radius: 8px !important;
    padding: 8px 10px !important;
    font-size: 13px !important;
    outline: none !important;
    background: var(--surface2, #f9fafb) !important;
    color: var(--text, #111827) !important;
}
.select2-results__option {
    padding: 9px 12px !important;
    font-size: 12.5px !important;
    background: var(--surface, #fff) !important;
    color: var(--text, #111827) !important;
    border-bottom: 1px solid var(--border, #e5e7eb);
}
.select2-results__option:last-child { border-bottom: none; }
.select2-results__option--highlighted,
.select2-results__option--highlighted[aria-selected] {
    background: rgba(232, 160, 32, .14) !important;
    color: var(--text, #111827) !important;
}

/* ── Services annexes ─────────────────────────────────────── */
.fne-empty {
    padding: 20px;
    text-align: center;
    color: var(--fne-text3);
    background: var(--fne-bg2);
    border-radius: 10px;
    font-size: 13px;
}
.fne-svc-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid var(--fne-border);
}
.fne-svc-row:last-child { border-bottom: none; padding-bottom: 0; }
.fne-svc-row:first-child { padding-top: 0; }
.svc-num { text-align: right; }
.svc-ttc {
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 0 12px;
    background: var(--fne-bg2);
    border: 1px solid var(--fne-border);
    border-radius: 9px;
    font-weight: 700;
    color: var(--fne-accent);
    font-variant-numeric: tabular-nums;
    font-size: 13px;
}
.fne-svc-subtotal {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 16px;
    padding: 14px 0 0;
    margin-top: 14px;
    border-top: 2px solid var(--fne-border);
    font-size: 13px;
    color: var(--fne-text2);
}
.fne-svc-subtotal strong {
    color: var(--fne-accent);
    font-weight: 800;
    font-size: 14px;
    font-variant-numeric: tabular-nums;
}

/* ── Récapitulatif ────────────────────────────────────────── */
.fne-recap-body { padding: 0; }
.fne-recap-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 0;
}
@media (max-width: 720px) { .fne-recap-grid { grid-template-columns: 1fr; } }
.fne-recap-col {
    padding: 18px 22px;
    background: var(--fne-bg);
}
.fne-recap-col.alt {
    background: var(--fne-bg2);
    border-left: 1px solid var(--fne-border);
}
@media (max-width: 720px) {
    .fne-recap-col.alt { border-left: none; border-top: 1px solid var(--fne-border); }
}
.rl-cap {
    font-size: 10.5px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: .6px;
    color: var(--fne-text3);
    margin-bottom: 8px;
}
.rl {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    font-size: 13px;
    color: var(--fne-text2);
}
.rl span:last-child { color: var(--fne-text); font-variant-numeric: tabular-nums; }
.rl.strong { font-weight: 800; font-size: 14px; color: var(--fne-text); }
.rl.strong span:last-child { color: var(--fne-text); }
.rl.sub { font-size: 12.5px; color: var(--fne-text3); }
.rl.sub span:last-child { color: var(--fne-text2); }
.rl .neg { color: #b45309; }
.rl-sep {
    height: 1px;
    background: var(--fne-border);
    margin: 6px 0;
}
.fne-recap-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 22px;
    background: linear-gradient(135deg, var(--fne-accent), var(--fne-accent-dark));
    color: #fff;
}
.fne-recap-total .lbl { font-weight: 800; font-size: 14px; letter-spacing: .3px; }
.fne-recap-total .val { font-weight: 800; font-size: 20px; font-variant-numeric: tabular-nums; }

/* ── Footer actions ───────────────────────────────────────── */
.fne-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 6px;
}

/* ── Template option panneau (Select2) ────────────────────── */
.fne-pan { display: flex; flex-direction: column; gap: 2px; }
.fne-pan-top { display: flex; align-items: center; gap: 8px; font-weight: 700; color: var(--fne-accent-dark); font-size: 12.5px; }
.fne-pan-meta { font-size: 11px; color: var(--fne-text3); display: flex; gap: 8px; flex-wrap: wrap; }
.fne-pan-pill { font-size: 9.5px; font-weight: 700; padding: 1px 6px; border-radius: 5px; }
.fne-pan-pill.ext { background: rgba(59, 130, 246, .15); color: #1d4ed8; }
.fne-pan-pill.int { background: rgba(232, 160, 32, .15); color: var(--fne-accent-dark); }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<script>
(function () {
    const TVA        = {{ $tvaRate }};
    const TSP        = {{ $tspRate }};
    const TM_DEFAULT = {{ $tmDefault }};
    const LOOKUP_URL = "{{ route('admin.invoices.lookup.panels') }}";

    const $client   = document.getElementById('fne-client');
    const $campaign = document.getElementById('fne-campaign');
    const $linesBody = document.getElementById('fne-lines-body');
    const $linesTpl  = document.getElementById('fne-line-tpl');
    const $addLine   = document.getElementById('fne-add-line');
    const $linesSub  = document.getElementById('fne-lines-sub');

    let nextLineIdx = {{ count($lines) }};
    let nextSvcIdx  = {{ count($services) }};

    function fmt(n) { return Math.round(n).toLocaleString('fr-FR') + ' FCFA'; }

    // ── Cascading client ↔ campagne (vanilla, sans Select2) ──
    function refreshCampaignOptions() {
        const cid = $client.value;
        let visible = 0;
        Array.from($campaign.options).forEach(opt => {
            if (!opt.value) return;
            const ok = !cid || opt.dataset.clientId === cid;
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (ok) visible++;
        });
        // Reset campagne si l'option courante n'est plus valide
        const cur = $campaign.selectedOptions[0];
        if (cur && cur.hidden) {
            $campaign.value = '';
            // Reset toutes les désignations puisque les panneaux dépendent de la campagne
            resetAllDesignations();
        }
    }

    $client?.addEventListener('change', () => {
        refreshCampaignOptions();
        // Si on a changé de client, on remet à zéro les désignations (panneaux liés à la campagne)
        resetAllDesignations();
    });

    $campaign?.addEventListener('change', () => {
        // Aligner le client sur celui de la campagne sélectionnée
        const opt = $campaign.selectedOptions[0];
        if (opt && opt.value && opt.dataset.clientId && $client.value !== opt.dataset.clientId) {
            $client.value = opt.dataset.clientId;
            refreshCampaignOptions();
        }
        resetAllDesignations();
    });

    refreshCampaignOptions();

    // ── Désignation (Select2 AJAX par ligne) ──────────────────
    function panSelTemplate(item) {
        if (!item.id && !item.text) return $('<span style="color:#9ca3af">Recherche d\'un panneau…</span>');
        if (!item.id) return $('<span>📝 ' + (item.text || '') + '</span>');
        return $(
            '<div class="fne-pan">' +
                '<div class="fne-pan-top">' +
                    '<span class="fne-pan-pill ' + (item.source === 'ext' ? 'ext' : 'int') + '">' + (item.source === 'ext' ? 'EXTERNE' : 'INTERNE') + '</span>' +
                    '<span>' + (item.reference || '') + '</span>' +
                '</div>' +
                '<div>' + (item.name || '') + '</div>' +
                '<div class="fne-pan-meta">' +
                    '<span>📍 ' + (item.commune_name || '—') + '</span>' +
                    (item.dimension_m2 ? '<span>📐 ' + item.dimension_m2 + ' m²</span>' : '') +
                    (item.pu_suggested ? '<span style="color:#c97d10;font-weight:700">' + Math.round(item.pu_suggested).toLocaleString('fr-FR') + ' F/mois</span>' : '') +
                '</div>' +
            '</div>'
        );
    }
    function panSelSelection(item) {
        if (!item.id && !item.text) return 'Choisir un panneau ou taper…';
        return item.designation || item.text;
    }

    function initLineSelect2(line) {
        const $design = $(line.querySelector('.line-designation'));
        if ($design.data('s2-ready')) return;

        // Pré-remplissage si edit
        const initialDesign = line.querySelector('.line-designation-value').value;
        if (initialDesign) {
            const opt = new Option(initialDesign, 'manual:' + initialDesign, true, true);
            $design[0].appendChild(opt);
        }

        $design.select2({
            placeholder: $campaign?.value ? 'Choisir un panneau ou taper…' : 'Choisis d\'abord une campagne',
            allowClear: true,
            tags: true,
            minimumInputLength: 0,
            language: {
                noResults: () => $campaign?.value ? 'Aucun panneau correspondant — tape pour saisie libre.' : 'Choisis d\'abord une campagne en haut.',
                inputTooShort: () => 'Tape pour rechercher…',
                searching: () => 'Recherche…',
            },
            ajax: {
                url: LOOKUP_URL,
                delay: 220,
                dataType: 'json',
                data: params => ({ q: params.term || '', page: params.page || 1, campaign_id: $campaign?.value || '' }),
                processResults: (data, params) => {
                    params.page = params.page || 1;
                    return { results: data.results || [], pagination: data.pagination || { more: false } };
                },
            },
            templateResult: panSelTemplate,
            templateSelection: panSelSelection,
        });
        $design.data('s2-ready', true);

        $design.on('select2:select', (e) => {
            const d = e.params.data || {};
            const hidden = line.querySelector('.line-designation-value');
            const isManual = String(d.id || '').startsWith('manual:') || (!d.id && d.text);
            if (isManual) {
                hidden.value = d.text || '';
            } else {
                hidden.value = d.designation || d.text || '';
                // Auto-fill commune + m² + PU
                if (d.commune_id) {
                    const sel = line.querySelector('.line-commune');
                    if (sel) sel.value = d.commune_id;
                }
                if (d.dimension_m2) line.querySelector('.line-m2').value = d.dimension_m2;
                if (d.pu_suggested) line.querySelector('.line-pu').value = Math.round(d.pu_suggested);
                recompute();
            }
        });
        $design.on('select2:clear', () => {
            line.querySelector('.line-designation-value').value = '';
        });
    }

    function resetAllDesignations() {
        document.querySelectorAll('#fne-lines-body .fne-line').forEach(line => {
            const $d = $(line.querySelector('.line-designation'));
            if ($d.data('s2-ready')) { $d.val(null).trigger('change'); }
            const hid = line.querySelector('.line-designation-value');
            if (hid) hid.value = '';
        });
    }

    // ── Lignes : add / remove / renumber ──────────────────────
    function renumberLines() {
        const lines = $linesBody.querySelectorAll('.fne-line');
        lines.forEach((l, idx) => {
            const num = l.querySelector('.fne-line-num');
            if (num) num.textContent = idx + 1;
        });
        if ($linesSub) {
            $linesSub.textContent = lines.length + ' ligne' + (lines.length > 1 ? 's' : '') + ' · panneaux à facturer';
        }
    }

    function bindLine(line) {
        line.querySelectorAll('input.line-m2, input.line-pu, input.line-qte, input.line-mois').forEach(el => {
            el.addEventListener('input', recompute);
        });
        line.querySelector('.line-commune')?.addEventListener('change', recompute);
        initLineSelect2(line);

        const rm = line.querySelector('.fne-line-remove');
        rm?.addEventListener('click', () => {
            if ($linesBody.querySelectorAll('.fne-line').length <= 1) {
                alert('Au moins une ligne est requise.');
                return;
            }
            line.remove();
            renumberLines();
            recompute();
        });
    }

    $addLine?.addEventListener('click', () => {
        const i = nextLineIdx++;
        const html = $linesTpl.innerHTML.replaceAll('__IDX__', i);
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const newLine = wrap.firstElementChild;
        $linesBody.appendChild(newLine);
        bindLine(newLine);
        renumberLines();
        recompute();
    });

    // Bind existing lines
    $linesBody.querySelectorAll('.fne-line').forEach(bindLine);

    // ── Services annexes : add / remove ──────────────────────
    window.fneAddService = function () {
        const i = nextSvcIdx++;
        const wrap = document.getElementById('fne-svc-rows');
        const empty = document.getElementById('fne-svc-empty');
        const subWrap = document.getElementById('fne-svc-subtotal-wrap');
        const row = document.createElement('div');
        row.className = 'fne-svc-row';
        row.dataset.idx = i;
        row.innerHTML = `
            <div class="fne-field" style="flex:1;margin:0">
                <label>Libellé</label>
                <input type="text" name="services[${i}][label]" maxlength="200" placeholder="Ex: Frais d'impression" class="svc-label" required>
            </div>
            <div class="fne-field" style="width:140px;margin:0">
                <label>Prix HT (FCFA)</label>
                <input type="number" name="services[${i}][prix_ht]" value="0" min="0" step="1000" class="svc-prix svc-num" required>
            </div>
            <div class="fne-field" style="width:140px;margin:0">
                <label>TTC</label>
                <div class="svc-ttc">—</div>
            </div>
            <button type="button" class="fne-btn-remove" onclick="fneRemoveService(this)" title="Supprimer">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
            </button>
        `;
        wrap.appendChild(row);
        wrap.style.display = 'block';
        empty.style.display = 'none';
        subWrap.style.display = 'flex';
        row.querySelector('.svc-prix').addEventListener('input', recompute);
        row.querySelector('.svc-label').focus();
        recompute();
    };

    window.fneRemoveService = function (btn) {
        const row = btn.closest('.fne-svc-row');
        if (!row) return;
        row.remove();
        // Reindex
        document.querySelectorAll('#fne-svc-rows .fne-svc-row').forEach((r, idx) => {
            r.dataset.idx = idx;
            r.querySelector('.svc-label').name = `services[${idx}][label]`;
            r.querySelector('.svc-prix').name  = `services[${idx}][prix_ht]`;
        });
        const remaining = document.querySelectorAll('#fne-svc-rows .fne-svc-row').length;
        const wrap = document.getElementById('fne-svc-rows');
        const empty = document.getElementById('fne-svc-empty');
        const subWrap = document.getElementById('fne-svc-subtotal-wrap');
        if (remaining === 0) {
            wrap.style.display = 'none';
            empty.style.display = 'block';
            subWrap.style.display = 'none';
        }
        recompute();
    };

    // Bind existing services
    document.querySelectorAll('#fne-svc-rows .svc-prix').forEach(el => el.addEventListener('input', recompute));

    // ── Recompute ─────────────────────────────────────────────
    function recompute() {
        let htBrut = 0, totalTm = 0, totalOdp = 0;

        document.querySelectorAll('#fne-lines-body .fne-line').forEach(line => {
            const pu   = parseFloat(line.querySelector('.line-pu')?.value)   || 0;
            const qte  = parseInt(  line.querySelector('.line-qte')?.value)  || 0;
            const mois = parseFloat(line.querySelector('.line-mois')?.value) || 0;
            const m2   = parseFloat(line.querySelector('.line-m2')?.value)   || 0;
            const sel  = line.querySelector('.line-commune');
            const opt  = sel?.selectedOptions?.[0];
            const odp  = parseFloat(opt?.dataset.odp) || 0;
            const tm   = parseFloat(opt?.dataset.tm)  || TM_DEFAULT;
            const lineHt = pu * qte * mois;
            htBrut   += lineHt;
            totalOdp += odp * m2 * qte * mois;
            totalTm  += tm  * m2 * qte * mois;
            const cell = line.querySelector('.fne-line-total');
            if (cell) cell.textContent = fmt(lineHt);
        });

        const remise = parseFloat(document.getElementById('fne-remise').value) || 0;

        let svcHt = 0;
        document.querySelectorAll('#fne-svc-rows .fne-svc-row').forEach(r => {
            const prix = parseFloat(r.querySelector('.svc-prix')?.value) || 0;
            svcHt += prix;
            const ttcCell = r.querySelector('.svc-ttc');
            if (ttcCell) ttcCell.textContent = prix > 0 ? fmt(prix * (1 + TVA / 100)) : '—';
        });

        const netHt  = htBrut * (1 - remise / 100);
        const tvaAmt = netHt * TVA / 100;
        const tspAmt = netHt * TSP / 100;
        const ttc    = netHt + tvaAmt;
        const svcTtc = svcHt * (1 + TVA / 100);
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

        const svcSub = document.getElementById('fne-svc-subtotal');
        if (svcSub) svcSub.textContent = fmt(svcTtc);
    }

    document.getElementById('fne-remise')?.addEventListener('input', recompute);

    recompute();
})();
</script>
@endpush

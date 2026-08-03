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
    // Tri des lignes par commune puis par désignation en mode édition —
    // demandé 2026-08-03 pour regrouper visuellement les panneaux d'une
    // même commune (facilite la lecture + édition ciblée). old() est
    // respecté tel quel (n'est présent qu'après un échec de validation,
    // là on doit garder l'ordre saisi par l'utilisateur).
    // Cast explicite (float)/(int) : le cast Eloquent `decimal:2` renvoie
    // parfois un objet Decimal dont le __toString peut casser value=""
    // sur type="number" (bug 2026-08-03 : M²/QTÉ vides visuellement).
    $lines     = $oldLines ?: ($isEdit && $invoice->lines->count() > 0
                    ? $invoice->lines
                        ->sortBy([['snapshot_commune_name', 'asc'], ['designation', 'asc']])
                        ->values()
                        ->map(fn($l) => [
                            'designation'       => $l->designation,
                            'commune_id'        => $l->commune_id,
                            'dimension_m2'      => (float) $l->dimension_m2,
                            'pu_ht_mensuel'     => (int) $l->pu_ht_mensuel,
                            'quantite'          => (int) $l->quantite,
                            'duree_mois'        => (float) $l->duree_mois,
                            'odp_rate_applique' => (int) $l->odp_rate_applique,
                            'tm_rate_applique'  => (int) $l->tm_rate_applique,
                            // Bug 3a (2026-07-16) : conserver le lien panneau pour
                            // validation d'unicité en édition (sinon un update
                            // enlèverait le panel_id sur les lignes existantes).
                            'panel_id'          => $l->panel_id ?? null,
                            'external_panel_id' => $l->external_panel_id ?? null,
                        ])->all()
                    : [
                        // ligne vide par défaut en create
                        ['designation' => '', 'dimension_m2' => 0, 'pu_ht_mensuel' => 0,
                         'quantite' => 1, 'duree_mois' => 1, 'odp_rate_applique' => 0, 'tm_rate_applique' => 1000,
                         'panel_id' => null, 'external_panel_id' => null],
                    ]);
    $communes  = \App\Models\Commune::orderBy('name')->get(['id', 'name', 'odp_rate', 'tm_rate']);
    $tvaRate   = (float) config('billing.tva_rate', 18);
    $tspRate   = (float) config('billing.tsp_rate', 3);
    $tmDefault = (float) config('billing.tm_default', 1000);
@endphp

@if($errors->any())
<div class="fne-error-banner">
    <div style="width:40px;height:40px;border-radius:10px;background:#ef4444;color:#fff;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">⚠</div>
    <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:800;color:#b91c1c;margin-bottom:4px">{{ $errors->count() }} erreur{{ $errors->count() > 1 ? 's' : '' }} à corriger</div>
        @foreach($errors->all() as $error)
            <div style="color:#991b1b;font-size:12.5px;display:flex;gap:6px;align-items:flex-start;margin-bottom:2px">
                <span style="opacity:.6">•</span><span>{{ $error }}</span>
            </div>
        @endforeach
    </div>
</div>
@endif

<form method="POST" action="{{ $action }}" id="form-fne" class="invoice-form">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    {{-- ═══ LAYOUT 2 COLONNES : formulaire (gauche) + récap sticky (droite) ═══
         L'aside récap reste visible quand l'utilisateur scrolle dans les
         lignes — feedback immédiat sur le total à payer.
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="fne-grid">

        <div class="fne-main">

            {{-- ════ INFOS GÉNÉRALES ════ --}}
            <div class="fne-section">
                <div class="fne-section-head">
                    <div class="fne-section-icon" style="background:rgba(58,168,53,.10)">📄</div>
                    <div>
                        <h3 class="fne-section-title">Informations générales</h3>
                        <p class="fne-section-sub">Référence, date d'émission, client et campagne liée.</p>
                    </div>
                </div>
                <div class="fne-section-body">
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

    {{-- ════ LIGNES ÉDITABLES (refonte design) ════ --}}
    <div class="invoice-lines-card">
        <div class="invoice-lines-header">
            <div class="invoice-lines-title">
                <span class="invoice-lines-icon">📋</span>
                <div>
                    <div class="invoice-lines-title-main">Lignes de facturation</div>
                    <div class="invoice-lines-title-sub" id="lines-count-label">{{ count($lines) }} ligne{{ count($lines) > 1 ? 's' : '' }} · ajoutez les panneaux à facturer</div>
                </div>
            </div>
        </div>

        <div class="invoice-lines-body">
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
                            <td class="col-designation" data-label="Panneau">
                                {{-- Select2 AJAX panneau (filtré par la campagne sélectionnée
                                     en haut). À la sélection, auto-remplit commune + m² + PU
                                     + panel_id (hidden) pour permettre validation d'unicité. --}}
                                <select name="lines[{{ $i }}][designation_picker]" class="line-designation" style="width:100%"></select>
                                <input type="hidden" name="lines[{{ $i }}][designation]" class="line-designation-value" value="{{ $l['designation'] ?? '' }}">
                                {{-- Bug 3a (2026-07-16) : hidden panel_id / external_panel_id
                                     transmis au controller pour validation d'unicité
                                     (aucun panneau facturé 2× dans la même facture). --}}
                                <input type="hidden" name="lines[{{ $i }}][panel_id]" class="line-panel-id" value="{{ $l['panel_id'] ?? '' }}">
                                <input type="hidden" name="lines[{{ $i }}][external_panel_id]" class="line-external-panel-id" value="{{ $l['external_panel_id'] ?? '' }}">
                            </td>
                            <td class="col-commune" data-label="Commune">
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
                            <td class="num col-m2" data-label="m²">
                                {{-- ?: (pas ??) — bouclier contre old() qui renvoie
                                     une string vide après un échec de validation :
                                     "" n'est pas null, donc ?? ne fallback pas.
                                     placeholder + inputmode : filet si la value
                                     rendue est vide (bug 2026-08-03 sur objets
                                     Decimal), l'utilisateur voit au moins "0" en gris. --}}
                                <input type="number" name="lines[{{ $i }}][dimension_m2]" class="line-m2" required
                                       inputmode="decimal" placeholder="0"
                                       value="{{ $l['dimension_m2'] ?: 0 }}" min="0" step="0.01">
                            </td>
                            <td class="num col-pu" data-label="PU HT/mois">
                                {{-- step="1" (FCFA entier, pas de centimes) — 2026-06-18 :
                                     l'ancien step="1000" rejetait les prix négociés non
                                     arrondis (ex. 435 600). --}}
                                <input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" class="line-pu" required
                                       inputmode="numeric" placeholder="0"
                                       value="{{ $l['pu_ht_mensuel'] ?: 0 }}" min="0" step="1">
                            </td>
                            <td class="num col-qte" data-label="Qté">
                                <input type="number" name="lines[{{ $i }}][quantite]" class="line-qte" required
                                       inputmode="numeric" placeholder="1"
                                       value="{{ $l['quantite'] ?: 1 }}" min="1" step="1">
                            </td>
                            <td class="num col-mois" data-label="Mois">
                                <input type="number" name="lines[{{ $i }}][duree_mois]" class="line-mois" required
                                       inputmode="decimal" placeholder="1"
                                       value="{{ $l['duree_mois'] ?: 1 }}" min="0.5" step="0.5">
                            </td>
                            <td class="num col-total line-total" data-label="Total HT">0 FCFA</td>
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

        @if(!($isEdit && $invoice->isLocked()))
        <div class="invoice-lines-footer">
            <button type="button" id="add-line" class="btn-add-line">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Ajouter une ligne
            </button>
        </div>
        @endif
    </div>

    {{-- ════ REMISE GLOBALE ════ --}}
    <div class="fne-section">
        <div class="fne-section-head">
            <div class="fne-section-icon" style="background:rgba(245,158,11,.10)">⚙</div>
            <div>
                <h3 class="fne-section-title">Remise globale</h3>
                <p class="fne-section-sub">S'applique au TOTAL HT des lignes panneaux uniquement (pas sur les services annexes).</p>
            </div>
        </div>
        <div class="fne-section-body">
            <div class="mfg" style="max-width:280px">
                <label>Pourcentage de remise (%)</label>
                <div style="position:relative">
                    <input type="number" name="remise_pct" id="remise_pct" min="0" max="100" step="0.5"
                           value="{{ old('remise_pct', $isEdit ? $invoice->remise_pct : 0) }}"
                           {{ $isEdit && $invoice->isLocked() ? 'readonly' : '' }}
                           style="text-align:right;padding-right:32px">
                    <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text3);font-weight:700;font-size:13px;pointer-events:none">%</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ════ SERVICES ANNEXES (N lignes libres — prompt v2) ════
         Chaque service : libellé libre + prix HT. Soumis à TVA 18 %.
         Pré-rempli avec les services existants en édition + migration
         transparente depuis les 2 anciens champs invoices.services_*
    --}}
    @php
        $existingServices = $isEdit && $invoice->services
            ? $invoice->services->map(fn($s) => ['label' => $s->label, 'prix_ht' => (float) $s->prix_ht])->all()
            : [];
        // Si edit + pas de services modernes + valeurs legacy > 0, on
        // prépopule à partir des 2 champs legacy (sera saved en modernes
        // dès la 1ère modif via syncServices).
        if ($isEdit && empty($existingServices)) {
            if ((float) $invoice->services_impression > 0) {
                $existingServices[] = ['label' => "Frais d'impression", 'prix_ht' => (float) $invoice->services_impression];
            }
            if ((float) $invoice->services_pose_depose > 0) {
                $existingServices[] = ['label' => 'Frais de pose et dépose', 'prix_ht' => (float) $invoice->services_pose_depose];
            }
        }
        // Si old() après erreur, on prend ça en priorité
        $oldServices = old('services');
        $renderedServices = is_array($oldServices) && !empty($oldServices) ? $oldServices : $existingServices;
        $locked = $isEdit && $invoice->isLocked();
    @endphp
    <div class="fne-section">
        <div class="fne-section-head" style="display:flex;justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
            <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:240px">
                <div class="fne-section-icon" style="background:rgba(180,83,9,.10)">🧾</div>
                <div>
                    <h3 class="fne-section-title">Services annexes</h3>
                    <p class="fne-section-sub">Impression, pose, créa, photographe… N lignes libres, chacune avec TVA 18 %.</p>
                </div>
            </div>
            @unless($locked)
                <button type="button" class="fne-btn-add-svc" onclick="addService()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Ajouter un service
                </button>
            @endunless
        </div>
        <div class="fne-section-body">
            {{-- ═══ ÉTAT VIDE — design soigné ═══ --}}
            <div id="services-empty" style="padding:26px 18px;text-align:center;background:linear-gradient(180deg,var(--surface2) 0%,var(--surface) 100%);border:1.5px dashed var(--border2);border-radius:12px;display:{{ empty($renderedServices) ? 'block' : 'none' }}">
                <div style="font-size:32px;margin-bottom:8px;opacity:.4">🧾</div>
                <div style="font-size:13px;color:var(--text2);font-weight:700;margin-bottom:3px">Aucun service annexe</div>
                <div style="font-size:11.5px;color:var(--text3);line-height:1.5">Clique <strong>+ Ajouter un service</strong> ci-dessus pour facturer<br>un libellé libre (avec TVA 18 % automatique).</div>
            </div>

            {{-- ═══ LISTE DES SERVICES — design en cards flex ═══
                 Chaque service = une card avec libellé à gauche, montant
                 saisissable + TTC calculé live + bouton suppression à droite.
                 Le hidden #services-table garde les classes attendues par le
                 JS recompute (.service-row, .svc-label, .svc-prix, .svc-ttc).
            ═══════════════════════════════════════════════════════════ --}}
            <div id="services-table" style="display:{{ empty($renderedServices) ? 'none' : 'block' }}">
                <div id="services-tbody" style="display:flex;flex-direction:column;gap:8px">
                    @foreach($renderedServices as $i => $s)
                    <div class="service-row svc-card" data-idx="{{ $i }}">
                        <div class="svc-card-num">{{ $i + 1 }}</div>
                        <div class="svc-card-fields">
                            <div class="svc-card-field svc-card-field-label">
                                <label>Libellé du service</label>
                                <input type="text" name="services[{{ $i }}][label]" value="{{ $s['label'] ?? '' }}"
                                       placeholder="Ex: Frais d'impression, Reportage photo…"
                                       maxlength="200" required {{ $locked ? 'readonly' : '' }}
                                       class="svc-label">
                            </div>
                            <div class="svc-card-field svc-card-field-prix">
                                <label>Prix HT (FCFA)</label>
                                <div class="svc-prix-wrap">
                                    {{-- step="1" — cf. ligne 192 (PU HT) pour la justification. --}}
                                    <input type="number" name="services[{{ $i }}][prix_ht]" value="{{ $s['prix_ht'] ?? 0 }}"
                                           min="0" step="1" required {{ $locked ? 'readonly' : '' }}
                                           class="svc-prix">
                                    <span class="svc-prix-suffix">F</span>
                                </div>
                            </div>
                            <div class="svc-card-field svc-card-field-ttc">
                                <label>TTC (TVA 18 %)</label>
                                <div class="svc-ttc-display"><span class="svc-ttc">—</span></div>
                            </div>
                        </div>
                        @unless($locked)
                        <button type="button" class="svc-card-remove" onclick="removeService(this)" title="Supprimer ce service" aria-label="Supprimer ce service">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                        @endunless
                    </div>
                    @endforeach
                </div>

                {{-- Sous-total footer --}}
                <div class="svc-subtotal-bar">
                    <span class="svc-subtotal-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h12"/></svg>
                        Sous-total services TTC
                    </span>
                    <span class="svc-subtotal-val" id="services-subtotal">0 FCFA</span>
                </div>
            </div>
        </div>
    </div>

        </div>{{-- /.fne-main --}}

        {{-- ═══ SIDEBAR : RÉCAPITULATIF FNE STICKY ═══
             Calcul live (JS recompute) — l'utilisateur voit les totaux
             se mettre à jour pendant qu'il saisit. Reste visible quand
             il scrolle dans les lignes ou les services.
        ═══════════════════════════════════════════════════════════ --}}
        <aside class="fne-aside">
            <div class="fne-recap">
                <div class="fne-recap-head">
                    <div style="font-size:10px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px">Récapitulatif FNE</div>
                    <div style="font-size:11.5px;color:var(--text3);margin-top:2px;display:flex;align-items:center;gap:4px">
                        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:#22c55e;animation:fne-pulse 1.5s ease-in-out infinite"></span>
                        Calculé en direct
                    </div>
                </div>

                <div class="fne-recap-body">
                    {{-- Bloc HT + remise + TTC --}}
                    <div class="fne-recap-row">
                        <span class="lbl">Total HT brut</span>
                        <span class="val" id="rec-brut">0 FCFA</span>
                    </div>
                    <div class="fne-recap-row" style="color:#b45309">
                        <span class="lbl">Remise</span>
                        <span class="val" id="rec-remise">0 FCFA</span>
                    </div>
                    <div class="fne-recap-row fne-recap-strong" style="border-top:1px solid var(--border);padding-top:8px">
                        <span class="lbl">TOTAL HT</span>
                        <span class="val" id="rec-netht">0 FCFA</span>
                    </div>
                    <div class="fne-recap-row">
                        <span class="lbl">TVA ({{ rtrim(rtrim(number_format($tvaRate, 2, ',', ''), '0'), ',') }} %)</span>
                        <span class="val" id="rec-tva">0 FCFA</span>
                    </div>
                    <div class="fne-recap-row fne-recap-strong" style="border-top:1px solid var(--border);padding-top:8px">
                        <span class="lbl">TOTAL TTC</span>
                        <span class="val" id="rec-ttc">0 FCFA</span>
                    </div>

                    {{-- Autres taxes --}}
                    <div style="margin-top:10px;padding:10px 12px;background:var(--surface2);border-radius:8px">
                        <div style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Autres taxes</div>
                        <div class="fne-recap-row fne-recap-small">
                            <span class="lbl">TSP ({{ rtrim(rtrim(number_format($tspRate, 2, ',', ''), '0'), ',') }} %)</span>
                            <span class="val" id="rec-tsp">0 FCFA</span>
                        </div>
                        <div class="fne-recap-row fne-recap-small">
                            <span class="lbl">TM total</span>
                            <span class="val" id="rec-tm">0 FCFA</span>
                        </div>
                        <div class="fne-recap-row fne-recap-small">
                            <span class="lbl">ODP total</span>
                            <span class="val" id="rec-odp">0 FCFA</span>
                        </div>
                    </div>

                    {{-- Services — 2026-06-22 : détail par ligne au lieu d'un total agrégé.
                         Le user veut voir d'où vient le montant "Services TTC" (impression,
                         pose, etc.) directement dans le récap. JS remplit #rec-svc-detail
                         avec une mini-liste, et #rec-svc affiche le grand total en gras. --}}
                    <div style="margin-top:10px;padding:10px 12px;background:var(--surface2);border-radius:8px">
                        <div style="font-size:9.5px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px">Services & frais</div>
                        <div id="rec-svc-detail" style="display:flex;flex-direction:column;gap:3px">
                            <div style="font-size:11px;color:var(--text3);font-style:italic">Aucun service ajouté</div>
                        </div>
                        <div class="fne-recap-row fne-recap-small" style="margin-top:6px;padding-top:6px;border-top:1px dashed var(--border);font-weight:700">
                            <span class="lbl">Total services TTC</span>
                            <span class="val" id="rec-svc">0 FCFA</span>
                        </div>
                    </div>
                </div>

                {{-- Bandeau total à payer --}}
                <div class="fne-recap-total">
                    <span class="lbl">💰 TOTAL À PAYER</span>
                    <span class="val" id="rec-total">0 FCFA</span>
                </div>
            </div>

            {{-- Actions sticky en bas de sidebar --}}
            <div class="fne-actions">
                <a href="{{ $isEdit ? route('admin.invoices.show', $invoice) : route('admin.invoices.index') }}" class="fne-btn-cancel">
                    Annuler
                </a>
                @if(!($isEdit && $invoice->isLocked()))
                <button type="submit" class="fne-btn-submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $isEdit ? 'Enregistrer' : 'Créer la facture' }}
                </button>
                @endif
            </div>
        </aside>

    </div>{{-- /.fne-grid --}}
</form>

@push('styles')
{{-- Select2 v4 + style CIBLE unifié pour TOUS les Select2 du formulaire
     facture (client, campagne, désignation, commune). Hauteur 40px
     calée sur les autres <input> natifs, padding cohérent, focus
     accent doré. Une seule règle CSS partagée pour cohérence visuelle. --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<style>
    /* ══════════════════════════════════════════════════════════════
       REFONTE PRO — Page Create / Edit Facture FNE
       Layout 2 colonnes (formulaire à gauche, récap sticky à droite),
       sections cards harmonisées, footer actions visible en permanence.
       ══════════════════════════════════════════════════════════════ */
    /* ── PRIORITÉ STACKING : la topbar doit toujours rester devant ──
       Sans ça, sur cette page le contenu (Select2 lignes + sidebar
       récap) passait au-dessus de la topbar pendant le scroll
       (l'admin voyait des champs "Choisir une campagne d'abord…"
       flotter au-dessus du bouton Retour). Cause : --z-topbar = 40
       par défaut, alors que certains stacking contexts internes au
       formulaire (will-change sur .main-area, sticky aside, etc.)
       remontaient au-dessus. On force la topbar à z-index 100 le
       temps qu'on est sur cette page. */
    body .topbar-fixed,
    body .topbar { z-index: 100 !important; }

    .fne-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 300px;
        gap: 16px;
        align-items: flex-start;
    }
    @media (max-width: 1100px) {
        .fne-grid { grid-template-columns: 1fr; }
        .fne-aside { position: static !important; max-height: none !important; }
    }
    /* Empêche la table lignes de déborder hors de sa carte si l'écran est étroit.
       overflow-x sur le wrapper interne (.invoice-lines-body), pas sur la table. */
    .fne-main .invoice-lines-body { overflow-x: auto; }
    .fne-main { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
    .fne-aside {
        position: sticky;
        /* top doit dépasser la topbar fixe (--topbar-height = 52px),
           sinon le récap passe DERRIÈRE quand on scrolle. +12px de
           respiration pour ne pas coller au bord. */
        top: calc(var(--topbar-height, 52px) + 12px);
        display: flex;
        flex-direction: column;
        gap: 12px;
        /* Borne la hauteur au viewport restant pour éviter le débordement
           en bas. overflow-y:auto activé pour que le récap scrolle de
           manière interne sur petits écrans (préserve la visibilité du
           bandeau TOTAL À PAYER + boutons d'action). */
        max-height: calc(100vh - var(--topbar-height, 52px) - 24px);
        overflow-y: auto;
        /* Cosmétique : scrollbar discrète sur Webkit */
        scrollbar-width: thin;
    }
    .fne-aside::-webkit-scrollbar { width: 6px; }
    .fne-aside::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
    .fne-aside::-webkit-scrollbar-thumb:hover { background: var(--text3); }

    /* ── SECTION CARDS HARMONISÉES ───────────────────────────── */
    .fne-section {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
    }
    .fne-section-head {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        background: linear-gradient(180deg, var(--surface) 0%, var(--surface2) 100%);
    }
    .fne-section-icon {
        width: 38px; height: 38px;
        border-radius: 11px;
        background: rgba(232, 160, 32, .10);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .fne-section-title {
        font-size: 14px;
        font-weight: 800;
        color: var(--text);
        margin: 0;
        letter-spacing: -.1px;
    }
    .fne-section-sub {
        font-size: 11.5px;
        color: var(--text3);
        margin: 1px 0 0;
        line-height: 1.45;
    }
    .fne-section-body { padding: 18px 20px; }

    /* ── BOUTON "AJOUTER UN SERVICE" ─────────────────────────── */
    .fne-btn-add-svc {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: linear-gradient(135deg, rgba(232,160,32,.10), rgba(180,83,9,.08));
        border: 1px solid rgba(232,160,32,.30);
        color: var(--accent-dark);
        padding: 7px 14px;
        border-radius: 9px;
        font-size: 12px;
        font-weight: 700;
        cursor: pointer;
        transition: background .15s, transform .12s, box-shadow .15s;
    }
    .fne-btn-add-svc:hover {
        background: linear-gradient(135deg, rgba(232,160,32,.18), rgba(180,83,9,.14));
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(232,160,32,.18);
    }

    /* ── RÉCAPITULATIF FNE STICKY ────────────────────────────── */
    .fne-recap {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        overflow: hidden;
        flex-shrink: 0;
    }
    .fne-recap-head {
        padding: 14px 18px;
        background: linear-gradient(180deg, rgba(232,160,32,.06) 0%, rgba(232,160,32,.01) 100%);
        border-bottom: 1px solid var(--border);
    }
    .fne-recap-body { padding: 14px 18px; font-size: 12.5px; color: var(--text2); }
    .fne-recap-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
    }
    .fne-recap-row .lbl { color: var(--text3); }
    .fne-recap-row .val { color: var(--text); font-weight: 700; }
    .fne-recap-row.fne-recap-strong .lbl { color: var(--text); font-weight: 800; }
    .fne-recap-row.fne-recap-strong .val { color: var(--text); font-weight: 800; }
    .fne-recap-row.fne-recap-small { font-size: 11.5px; padding: 3px 0; }
    .fne-recap-row.fne-recap-small .val { font-weight: 600; }
    .fne-recap-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 14px 18px;
        background: linear-gradient(135deg, #1f2937 0%, #0f172a 100%);
        color: #fbbf24;
        border-top: 1px solid var(--border);
    }
    .fne-recap-total .lbl {
        font-weight: 800;
        font-size: 13px;
        letter-spacing: .3px;
        color: #fbbf24;
    }
    .fne-recap-total .val {
        font-weight: 800;
        font-size: 17px;
        color: #fff;
        text-shadow: 0 1px 2px rgba(0,0,0,.4);
    }

    @keyframes fne-pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%      { opacity: .5; transform: scale(.85); }
    }

    /* ── FOOTER ACTIONS (annuler + submit) ───────────────────── */
    .fne-actions {
        display: flex;
        gap: 8px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        flex-shrink: 0;
    }
    .fne-btn-cancel {
        flex: 1;
        background: var(--surface2);
        border: 1px solid var(--border2);
        color: var(--text2);
        padding: 10px 16px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .15s;
    }
    .fne-btn-cancel:hover { background: var(--border); }
    .fne-btn-submit {
        flex: 2;
        background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        color: #fff;
        border: 0;
        padding: 10px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        box-shadow: 0 6px 16px rgba(232,160,32,.25);
        transition: transform .15s, box-shadow .15s, filter .15s;
    }
    .fne-btn-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(232,160,32,.32);
        filter: brightness(1.04);
    }
    .fne-btn-submit:active { transform: translateY(0); }

    /* ── BANDEAU D'ERREURS ÉLÉGANT ───────────────────────────── */
    .fne-error-banner {
        background: linear-gradient(180deg, rgba(239,68,68,.08), rgba(239,68,68,.04));
        border: 1px solid rgba(239,68,68,.3);
        border-radius: 14px;
        padding: 14px 18px;
        margin-bottom: 14px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }

    /* ══════════════════════════════════════════════════════════════
       SERVICES ANNEXES — cards flex (refonte design propre)
       Chaque service est une card horizontale avec :
         - pastille numérique à gauche
         - 3 champs côte à côte (libellé / prix HT / TTC calculé)
         - bouton suppression à droite (icône X minimaliste)
       Sous-total dans une barre bandeau en dessous.
       ══════════════════════════════════════════════════════════════ */
    .svc-card {
        display: flex;
        align-items: stretch;
        gap: 12px;
        padding: 12px 14px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 11px;
        transition: border-color .15s, box-shadow .15s, transform .12s;
    }
    .svc-card:hover { border-color: var(--border2); box-shadow: 0 4px 12px -6px rgba(0,0,0,.08); }
    .svc-card:focus-within {
        border-color: rgba(232,160,32,.45);
        box-shadow: 0 0 0 3px rgba(232,160,32,.10);
    }
    .svc-card-num {
        width: 26px; height: 26px;
        border-radius: 8px;
        background: linear-gradient(135deg, rgba(232,160,32,.14), rgba(180,83,9,.10));
        color: var(--accent-dark);
        font-weight: 800;
        font-size: 11.5px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        align-self: center;
    }
    .svc-card-fields {
        flex: 1;
        display: grid;
        grid-template-columns: minmax(0, 2.2fr) minmax(0, 1fr) minmax(0, 1fr);
        gap: 12px;
        min-width: 0;
    }
    @media (max-width: 720px) {
        .svc-card-fields { grid-template-columns: 1fr; }
    }
    .svc-card-field { display: flex; flex-direction: column; min-width: 0; }
    .svc-card-field label {
        font-size: 9.5px;
        font-weight: 800;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: .4px;
        margin-bottom: 3px;
        display: block;
    }
    .svc-card-field input {
        width: 100%;
        height: 36px;
        padding: 0 12px;
        background: var(--surface2);
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 13px;
        color: var(--text);
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .svc-card-field input:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(232,160,32,.14);
        background: var(--surface);
    }
    .svc-card-field-prix .svc-prix-wrap { position: relative; }
    .svc-card-field-prix .svc-prix {
        text-align: right;
        padding-right: 24px;
        font-variant-numeric: tabular-nums;
    }
    .svc-card-field-prix .svc-prix-suffix {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text3);
        font-weight: 700;
        font-size: 12px;
        pointer-events: none;
    }
    .svc-card-field-ttc .svc-ttc-display {
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding: 0 12px;
        background: linear-gradient(180deg, rgba(232,160,32,.06), rgba(232,160,32,.02));
        border: 1px dashed rgba(232,160,32,.30);
        border-radius: 8px;
        font-size: 13px;
        font-weight: 800;
        color: var(--accent-dark);
        font-variant-numeric: tabular-nums;
    }
    .svc-card-remove {
        align-self: center;
        width: 32px; height: 32px;
        border-radius: 8px;
        border: 1px solid var(--border);
        background: var(--surface);
        color: #ef4444;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        transition: background .15s, border-color .15s, transform .12s;
    }
    .svc-card-remove:hover {
        background: rgba(239,68,68,.08);
        border-color: rgba(239,68,68,.30);
        transform: scale(1.04);
    }

    /* Sous-total services en bandeau --------------------------------- */
    .svc-subtotal-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 11px 16px;
        margin-top: 10px;
        background: linear-gradient(135deg, rgba(232,160,32,.08), rgba(180,83,9,.05));
        border: 1px solid rgba(232,160,32,.20);
        border-radius: 11px;
    }
    .svc-subtotal-label {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        font-weight: 700;
        color: var(--text2);
    }
    .svc-subtotal-val {
        font-size: 14px;
        font-weight: 800;
        color: var(--accent-dark);
        font-variant-numeric: tabular-nums;
    }

    /* ══════════════════════════════════════════════════════════════ */
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

    /* ══════ CARTE LIGNES DE FACTURATION — refonte design ══════ */
    .invoice-lines-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        margin-bottom: 16px;
        overflow: hidden;
        box-shadow: 0 2px 8px -2px rgba(0, 0, 0, .04);
    }
    .invoice-lines-header {
        padding: 18px 22px;
        background: linear-gradient(135deg, rgba(232, 160, 32, .06), rgba(58, 168, 53, .04));
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .invoice-lines-title { display: flex; align-items: center; gap: 14px; min-width: 0; }
    .invoice-lines-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: rgba(232, 160, 32, .12);
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .invoice-lines-title-main { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 2px; }
    .invoice-lines-title-sub  { font-size: 12px; color: var(--text3); line-height: 1.4; }

    .invoice-lines-body { overflow-x: auto; }

    .invoice-lines-footer {
        padding: 14px 18px;
        border-top: 1px solid var(--border);
        background: var(--surface2);
        display: flex;
        justify-content: center;
    }
    .btn-add-line {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        height: 40px;
        padding: 0 18px;
        background: var(--accent);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 4px 12px -4px rgba(232, 160, 32, .5);
        transition: transform .12s, box-shadow .15s, background .15s;
    }
    .btn-add-line:hover { background: var(--accent-dark, #d18d12); transform: translateY(-1px); box-shadow: 0 6px 16px -4px rgba(232, 160, 32, .6); }
    .btn-add-line:active { transform: translateY(0); }

    /* ── Tableau lignes : padding généreux + inputs 40px + visuel modern ── */
    .lines-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
        min-width: 960px;
    }
    .lines-table thead tr {
        background: var(--surface2);
        border-bottom: 1px solid var(--border);
    }
    .lines-table th {
        padding: 12px 12px;
        font-size: 10.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .6px;
        text-align: left;
        color: var(--text3);
        white-space: nowrap;
    }
    .lines-table th.num { text-align: right; }
    .lines-table th.act { width: 56px; }
    .lines-table th.col-num { width: 44px; text-align: center; padding-left: 16px; }

    .lines-table tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background .12s;
    }
    .lines-table tbody tr:last-child { border-bottom: none; }
    .lines-table tbody tr:hover { background: rgba(232, 160, 32, .04); }
    .lines-table tbody tr:hover .row-number { background: var(--accent); color: #fff; border-color: var(--accent); }

    .lines-table td {
        padding: 12px 10px;
        vertical-align: middle;
    }
    .lines-table td.num { text-align: right; }
    .lines-table td.act { text-align: center; width: 56px; }
    .lines-table td.col-num { width: 44px; text-align: center; padding-left: 16px; }

    /* Largeurs colonnes — généreuses */
    .lines-table .col-designation { min-width: 300px; }
    .lines-table .col-commune     { min-width: 180px; }
    .lines-table .col-m2          { width: 95px; }
    .lines-table .col-pu          { width: 140px; }
    .lines-table .col-qte         { width: 80px; }
    .lines-table .col-mois        { width: 90px; }
    .lines-table .col-total {
        width: 140px;
        font-weight: 800;
        color: var(--accent);
        font-size: 13.5px;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    /* Numéro de ligne — pastille ronde */
    .row-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px; height: 26px;
        background: var(--surface);
        border: 1px solid var(--border);
        color: var(--text3);
        font-size: 11px;
        font-weight: 800;
        border-radius: 999px;
        transition: background .12s, color .12s, border-color .12s;
    }

    /* Inputs natifs dans le tableau — 40px (cohérent avec hors-tableau) */
    .lines-table input[type="number"],
    .lines-table input[type="text"] {
        height: 40px !important;
        width: 100% !important;
        padding: 0 12px !important;
        border: 1px solid var(--border) !important;
        border-radius: 8px !important;
        background: var(--surface) !important;
        font-size: 13px !important;
        text-align: right !important;
        color: var(--text) !important;
        font-variant-numeric: tabular-nums;
        transition: border-color .15s, box-shadow .15s;
    }
    .lines-table input[type="number"]:hover,
    .lines-table input[type="text"]:hover { border-color: var(--text3) !important; }
    .lines-table input[type="number"]:focus,
    .lines-table input[type="text"]:focus {
        border-color: var(--accent) !important;
        outline: none !important;
        box-shadow: 0 0 0 3px rgba(232, 160, 32, .15) !important;
    }
    /* Retire les spin buttons sur number (plus propre) */
    .lines-table input[type="number"]::-webkit-outer-spin-button,
    .lines-table input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .lines-table input[type="number"] { -moz-appearance: textfield; }

    /* Select2 dans le tableau — 40px + largeur pleine. Sans `width: 100% !important`
       sur .select2-container, Select2 4.x fixe une largeur en px à l'init qui
       collapse le container (~80px), ignorant le `style="width:100%"` sur le <select>. */
    .lines-table .select2-container {
        width: 100% !important;
        display: block;
    }
    .lines-table .select2-container--default .select2-selection--single {
        height: 40px !important;
        border-radius: 8px !important;
        border: 1px solid var(--border) !important;
        background: var(--surface) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .lines-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 38px !important;
        font-size: 13px !important;
        padding-left: 12px !important;
        padding-right: 32px !important;
        color: var(--text) !important;
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
    }
    .lines-table .select2-container--default .select2-selection__placeholder {
        color: var(--text3) !important;
    }
    .lines-table .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px !important;
        right: 8px !important;
    }
    .lines-table .select2-container--default.select2-container--focus .select2-selection--single,
    .lines-table .select2-container--default.select2-container--open  .select2-selection--single {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(232, 160, 32, .15) !important;
    }

    /* Bouton supprimer ligne — icône poubelle, rouge au hover */
    .btn-line-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px; height: 34px;
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text3);
        cursor: pointer;
        border-radius: 8px;
        transition: background .15s, border-color .15s, color .15s, transform .1s;
    }
    .btn-line-remove:hover {
        background: rgba(239, 68, 68, .1);
        border-color: rgba(239, 68, 68, .4);
        color: #ef4444;
    }
    .btn-line-remove:active { transform: scale(.92); }
    .btn-line-remove svg { display: block; }

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

    /* ══════════════════════════════════════════════════════════════
       RESPONSIVE — Vue card sous 900px (mobile + tablette portrait)
       2026-07-16d : le tableau des lignes de facture avait 8 colonnes
       et débordait sur mobile (scroll horizontal pénible). En dessous
       de 900px, chaque ligne devient une CARD empilée avec labels
       inline (data-label) pour rester lisible et utilisable.
       ══════════════════════════════════════════════════════════════ */
    @media (max-width: 900px) {
        /* Le container ne scrolle plus horizontal — plus besoin */
        .fne-main .invoice-lines-body,
        .invoice-lines-body { overflow-x: visible; }

        /* Repli des éléments table en block */
        .lines-table,
        .lines-table thead,
        .lines-table tbody,
        .lines-table tr,
        .lines-table td,
        .lines-table th { display: block; width: 100%; }

        /* Header table caché (labels dans data-label maintenant) */
        .lines-table thead { display: none; }

        /* Chaque ligne devient une card verticale */
        .lines-table tbody tr {
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 14px 8px;
            margin-bottom: 14px;
            background: var(--surface);
            box-shadow: 0 2px 6px -4px rgba(0,0,0,0.06);
        }
        .lines-table tbody tr:hover { background: var(--surface); }

        /* Cellules empilées avec label au-dessus */
        .lines-table td {
            padding: 8px 0 !important;
            border: none !important;
            text-align: left !important;
        }
        .lines-table td::before {
            content: attr(data-label);
            display: block;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--text3);
            margin-bottom: 6px;
        }
        .lines-table td:not([data-label])::before { display: none; }

        /* Cellule # : le numéro en pastille en tête de card,
           sans label (le contexte "ligne N" est évident). */
        .lines-table td.col-num {
            padding: 0 0 8px !important;
            text-align: left !important;
            width: auto !important;
            border-bottom: 1px dashed var(--border) !important;
            margin-bottom: 6px;
        }
        .lines-table td.col-num::before { display: none; }

        /* Cellule action (suppression) : bouton visible plein à droite */
        .lines-table td.act {
            padding: 10px 0 4px !important;
            text-align: right !important;
            width: auto !important;
        }
        .lines-table td.act::before { display: none; }

        /* Colonne Total HT : en fin de card, style résumé */
        .lines-table td.col-total {
            padding: 12px 0 6px !important;
            text-align: right !important;
            font-size: 16px !important;
            border-top: 1px dashed var(--border) !important;
            margin-top: 4px;
        }

        /* Layout de la card ligne — grid avec zones nommées.
           M², QTÉ, MOIS côte à côte (chiffres courts → gain d'espace). */
        .lines-table tbody tr {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr);
            grid-template-areas:
                "num  num  num"
                "designation designation designation"
                "commune commune commune"
                "m2   qte  mois"
                "pu   pu   pu"
                "total total total"
                "act  act  act";
            gap: 4px 10px;
        }
        .lines-table td.col-num         { grid-area: num; }
        .lines-table td.col-designation { grid-area: designation; }
        .lines-table td.col-commune     { grid-area: commune; }
        .lines-table td.col-m2          { grid-area: m2; }
        .lines-table td.col-qte         { grid-area: qte; }
        .lines-table td.col-mois        { grid-area: mois; }
        .lines-table td.col-pu          { grid-area: pu; }
        .lines-table td.col-total       { grid-area: total; }
        .lines-table td.act             { grid-area: act; }
    }

    /* Encore plus dense sous 560px (téléphones) : hero mode empilé */
    @media (max-width: 560px) {
        .invoice-lines-card { border-radius: 10px; padding: 10px; }
        .lines-table tbody tr { padding: 10px 12px 6px; }
        .lines-table input[type="number"] { font-size: 14px !important; height: 42px !important; }
    }
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
                        // Filtre panneaux déjà pris (2026-07-16) : on retire de
                        // la liste tout panneau dont l'id (int_X ou ext_X) est
                        // déjà présent dans une AUTRE ligne de la facture.
                        // On garde l'item si c'est la ligne courante (le user
                        // peut vouloir juste rouvrir le dropdown sans changer).
                        const takenInt = window.takenPanelsCache?.int || new Set();
                        const takenExt = window.takenPanelsCache?.ext || new Set();
                        const ownPanelId = row.querySelector('.line-panel-id')?.value || '';
                        const ownExtId   = row.querySelector('.line-external-panel-id')?.value || '';
                        const filtered = (data.results || []).filter(r => {
                            const id = String(r.id || '');
                            if (id.startsWith('int_')) {
                                const pid = id.slice(4);
                                return pid === ownPanelId || !takenInt.has(pid);
                            }
                            if (id.startsWith('ext_')) {
                                const eid = id.slice(4);
                                return eid === ownExtId || !takenExt.has(eid);
                            }
                            return true;
                        });
                        return {
                            results: filtered,
                            pagination: { more: data.pagination?.more === true },
                        };
                    },
                    // cache désactivé (2026-07-16) — sinon le filtre "panneaux
                    // pris" ne se recalcule pas quand l'utilisateur ajoute une
                    // ligne : Select2 servirait la liste initiale (avant sélection)
                    // et permettrait de re-sélectionner un panneau déjà pris.
                    cache: false,
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

                // Cas panneau : on remplit tout (designation, commune, m², PU, qté, mois)
                designationField.value = item.designation || item.text;

                // Panel_id (Bug 3a — 2026-07-16) : capté depuis l'item.id
                // Select2 format "int_289" ou "ext_42". Alimente le hidden
                // panel_id / external_panel_id transmis au backend pour
                // validation d'unicité (aucun doublon panneau par facture).
                const panelIdField = row.querySelector('.line-panel-id');
                const extPanelIdField = row.querySelector('.line-external-panel-id');
                if (panelIdField) panelIdField.value = '';
                if (extPanelIdField) extPanelIdField.value = '';
                const idStr = String(item.id || '');
                if (idStr.startsWith('int_') && panelIdField) {
                    panelIdField.value = idStr.slice(4);
                } else if (idStr.startsWith('ext_') && extPanelIdField) {
                    extPanelIdField.value = idStr.slice(4);
                }

                // Helper : setter ULTRA-DÉFENSIF (triple-write + events).
                // Contournement des cas edge observés (2026-07-16) où
                // input.value = X ne s'affichait pas visuellement pour
                // certains inputs alors qu'il fonctionnait pour d'autres
                // dans la même ligne. La cause exacte reste inconnue, mais
                // combiner value + defaultValue + attribute + input event
                // couvre tous les mécanismes DOM/React-like/browser cache.
                function forceInputValue(field, value, tag) {
                    if (!field) { console.warn('[FORM-FNE]', tag, 'field null'); return; }
                    const before = field.value;
                    field.value = String(value);
                    field.defaultValue = String(value);
                    field.setAttribute('value', String(value));
                    field.dispatchEvent(new Event('input',  { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    console.log('[FORM-FNE]', tag, 'was:', JSON.stringify(before), '→ now:', JSON.stringify(field.value));
                }

                // M² : TOUJOURS visible (0 avec bordure orange si inconnu).
                if (m2Field) {
                    const m2Val = Number(item.dimension_m2 || 0);
                    forceInputValue(m2Field, m2Val > 0 ? m2Val : 0, 'M²');
                    if (m2Val <= 0) {
                        m2Field.setAttribute('title', "Surface inconnue côté panneau — corrige à la main.");
                        m2Field.style.borderColor = '#f59e0b';
                        m2Field.style.background = '#fffbeb';
                    } else {
                        m2Field.removeAttribute('title');
                        m2Field.style.borderColor = '';
                        m2Field.style.background = '';
                    }
                }
                if (puField) {
                    const puVal = Math.round(Number(item.pu_suggested || 0));
                    forceInputValue(puField, puVal > 0 ? puVal : 0, 'PU');
                    if (puVal <= 0) {
                        puField.setAttribute('title', "Prix mensuel non configuré — saisis-le à la main.");
                        puField.style.borderColor = '#f59e0b';
                        puField.style.background = '#fffbeb';
                    } else {
                        puField.removeAttribute('title');
                        puField.style.borderColor = '';
                        puField.style.background = '';
                    }
                }

                // QTÉ et MOIS : forcer valeur par défaut si vide ou aberrante.
                const qteField  = row.querySelector('.line-qte');
                const moisField = row.querySelector('.line-mois');
                if (qteField  && (!qteField.value  || Number(qteField.value)  < 1))   forceInputValue(qteField,  1, 'QTÉ');
                if (moisField && (!moisField.value || Number(moisField.value) < 0.5)) forceInputValue(moisField, 1, 'MOIS');

                // Vérification différée (100ms après le handler) : si quelque
                // chose blanchit ces champs APRÈS notre write (race JS d'un
                // autre listener), on re-force. C'est du filet de sécurité
                // pur — si on voit ce log en console, c'est qu'un tiers
                // écrit. Debug utile.
                setTimeout(() => {
                    [['m2', m2Field, Number(item.dimension_m2 || 0) > 0 ? Number(item.dimension_m2) : 0],
                     ['qté', qteField, 1],
                     ['mois', moisField, 1],
                     ['pu', puField, Math.round(Number(item.pu_suggested || 0)) > 0 ? Math.round(Number(item.pu_suggested)) : 0]
                    ].forEach(([tag, field, expected]) => {
                        if (field && field.value === '' && expected != null) {
                            console.warn('[FORM-FNE] ⚠ ' + tag + ' a été blanchi APRÈS handler (race JS) — re-force à', expected);
                            forceInputValue(field, expected, tag + ' (re-force)');
                        }
                    });
                }, 100);

                // Sélectionner la commune dans le select Commune
                if (item.commune_id) {
                    $commune.val(String(item.commune_id)).trigger('change');
                }
                // Rafraîchir le cache "panneaux pris" pour que la prochaine
                // ligne ajoutée ne propose plus celui qu'on vient de choisir.
                if (typeof refreshTakenPanelsCache === 'function') refreshTakenPanelsCache();
                recompute();
            });
            $design.on('select2:clear', function () {
                row.querySelector('.line-designation-value').value = '';
                // Vider les hidden panel_id / external_panel_id (Bug 3a) — le
                // user a désélectionné le panneau, la ligne ne pointe plus vers
                // un panneau connu (elle redevient tag libre).
                const panelIdField = row.querySelector('.line-panel-id');
                const extPanelIdField = row.querySelector('.line-external-panel-id');
                if (panelIdField) panelIdField.value = '';
                if (extPanelIdField) extPanelIdField.value = '';
                if (typeof refreshTakenPanelsCache === 'function') refreshTakenPanelsCache();
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

        // ── Services annexes libres (N lignes) ──
        // 2026-06-22 — En + du calcul du total, on construit aussi le détail
        // pour le récap (1 ligne par service avec son label et son TTC).
        let svcHt = 0;
        const svcDetailRows = [];
        document.querySelectorAll('#services-tbody .service-row').forEach(row => {
            const prix  = parseFloat(row.querySelector('.svc-prix')?.value) || 0;
            const label = (row.querySelector('.svc-label')?.value || '').trim();
            svcHt += prix;
            const ttcCell = row.querySelector('.svc-ttc');
            if (ttcCell) ttcCell.textContent = prix > 0 ? fmt(prix * (1 + TVA/100)) : '—';
            // On n'affiche dans le détail que les services > 0 (ignore les rows vides).
            if (prix > 0) {
                svcDetailRows.push({
                    label: label || '(sans libellé)',
                    ttc:   prix * (1 + TVA/100),
                });
            }
        });

        const netHt    = htBrut * (1 - remise/100);
        const tvaAmt   = netHt * TVA / 100;
        const tspAmt   = netHt * TSP / 100;
        const ttc      = netHt + tvaAmt;
        const svcTtc   = svcHt * (1 + TVA / 100);
        const total    = ttc + tspAmt + totalTm + totalOdp + svcTtc;

        const svcSub = document.getElementById('services-subtotal');
        if (svcSub) svcSub.textContent = svcHt > 0 ? fmt(svcTtc) + ' FCFA' : '0 FCFA';

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

        // 2026-06-22 — Détail des services dans le récap (1 ligne par service).
        // Si aucun service : message en italique. Sinon : liste avec label + TTC.
        const svcDetailEl = document.getElementById('rec-svc-detail');
        if (svcDetailEl) {
            if (svcDetailRows.length === 0) {
                svcDetailEl.innerHTML = '<div style="font-size:11px;color:var(--text3);font-style:italic">Aucun service ajouté</div>';
            } else {
                svcDetailEl.innerHTML = svcDetailRows.map(s => `
                    <div class="fne-recap-row fne-recap-small" style="padding:1px 0">
                        <span class="lbl" style="font-size:11px;color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%" title="${s.label.replace(/"/g, '&quot;')}">${s.label}</span>
                        <span class="val" style="font-size:11px;font-weight:600">${fmt(s.ttc)}</span>
                    </div>
                `).join('');
            }
        }
    }

    // Réindexe les pastilles "1, 2, 3…" et le label "X lignes" après
    // ajout/suppression — sinon les numéros sont incohérents.
    function renumberLines() {
        const rows = tbody.querySelectorAll('.line-row');
        rows.forEach((r, idx) => {
            const badge = r.querySelector('.row-number');
            if (badge) badge.textContent = idx + 1;
        });
        const label = document.getElementById('lines-count-label');
        if (label) {
            label.textContent = rows.length + ' ligne' + (rows.length > 1 ? 's' : '')
                              + ' · ajoutez les panneaux à facturer';
        }
    }

    // ── Services annexes : ajout / suppression dynamiques ─────────
    function nextServiceIdx() {
        const rows = document.querySelectorAll('#services-tbody .service-row');
        return rows.length;
    }
    window.addService = function() {
        const tbody = document.getElementById('services-tbody');
        const empty = document.getElementById('services-empty');
        const table = document.getElementById('services-table');
        const idx = nextServiceIdx();
        const card = document.createElement('div');
        card.className = 'service-row svc-card';
        card.dataset.idx = idx;
        card.innerHTML = `
            <div class="svc-card-num">${idx + 1}</div>
            <div class="svc-card-fields">
                <div class="svc-card-field svc-card-field-label">
                    <label>Libellé du service</label>
                    <input type="text" name="services[${idx}][label]" placeholder="Ex: Frais d'impression, Reportage photo…"
                           maxlength="200" required class="svc-label">
                </div>
                <div class="svc-card-field svc-card-field-prix">
                    <label>Prix HT (FCFA)</label>
                    <div class="svc-prix-wrap">
                        <input type="number" name="services[${idx}][prix_ht]" value="0"
                               min="0" step="1" required class="svc-prix">
                        <span class="svc-prix-suffix">F</span>
                    </div>
                </div>
                <div class="svc-card-field svc-card-field-ttc">
                    <label>TTC (TVA 18 %)</label>
                    <div class="svc-ttc-display"><span class="svc-ttc">—</span></div>
                </div>
            </div>
            <button type="button" class="svc-card-remove" onclick="removeService(this)" title="Supprimer ce service" aria-label="Supprimer ce service">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        `;
        tbody.appendChild(card);
        empty.style.display = 'none';
        table.style.display = 'block';
        card.querySelector('.svc-label')?.focus();
        card.querySelector('.svc-prix')?.addEventListener('input', recompute);
        reindexServices();
        recompute();
    };
    window.removeService = function(btn) {
        const row = btn.closest('.service-row');
        if (!row) return;
        row.remove();
        reindexServices();
        const remaining = document.querySelectorAll('#services-tbody .service-row').length;
        if (remaining === 0) {
            document.getElementById('services-empty').style.display = 'block';
            document.getElementById('services-table').style.display = 'none';
        }
        recompute();
    };
    function reindexServices() {
        document.querySelectorAll('#services-tbody .service-row').forEach((row, i) => {
            row.dataset.idx = i;
            row.querySelector('.svc-label').name = `services[${i}][label]`;
            row.querySelector('.svc-prix').name  = `services[${i}][prix_ht]`;
            // Re-numérote la pastille (1, 2, 3…)
            const num = row.querySelector('.svc-card-num');
            if (num) num.textContent = i + 1;
        });
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
            renumberLines();
            // Le panneau qui était dans cette ligne redevient disponible
            // dans le dropdown des autres lignes (2026-07-16).
            if (typeof refreshTakenPanelsCache === 'function') refreshTakenPanelsCache();
            recompute();
        });
    }

    function addLine() {
        const i = nextIdx++;
        // ─── Clone propre du template (1re ligne) ───
        // Bug avant : on utilisait firstRow.innerHTML, qui capturait ALSO les
        // wrappers .select2-container que Select2 avait injectés à côté des
        // <select> natifs. Résultat : nouvelle ligne avec 2 selects visibles
        // (l'ancien rendu Select2 figé + le nouveau init). Maintenant on
        // cloneNode puis on nettoie tout artefact Select2 avant init.
        const firstRow = tbody.querySelector('.line-row');
        if (!firstRow) return;

        const tr = firstRow.cloneNode(true);
        tr.dataset.index = i;
        if (!tr.classList.contains('line-row')) tr.classList.add('line-row');

        // 1) Retire les wrappers Select2 (présents si la 1re ligne a déjà été init)
        tr.querySelectorAll('.select2-container').forEach(el => el.remove());
        // 2) Réaffiche les <select> natifs (Select2 les cachait via class+style)
        tr.querySelectorAll('select').forEach(sel => {
            sel.classList.remove('select2-hidden-accessible');
            sel.removeAttribute('aria-hidden');
            sel.removeAttribute('tabindex');
            sel.style.display = '';
            // jQuery.data n'est pas copié par cloneNode, donc le marqueur
            // 'select2-init' n'existe pas sur le clone — pas besoin de reset.
        });
        // 3) CRITIQUE — retire TOUS les data-select2-id du clone.
        //    cloneNode copie cet attribut, ce qui crée un conflit d'ID quand
        //    Select2 init la nouvelle ligne. Symptôme observé : Select2 voit
        //    deux éléments avec le même data-select2-id et CASSE le wrapper
        //    de la PREMIÈRE ligne (qui devient un <select> natif visible
        //    avec carret natif — visible sur le screenshot user "ligne 1
        //    perd son design après ajout de la ligne 2").
        tr.querySelectorAll('[data-select2-id]').forEach(el => el.removeAttribute('data-select2-id'));

        // 4) Renomme name="lines[X][...]" → lines[i][...]
        tr.querySelectorAll('[name]').forEach(el => {
            if (el.name) el.name = el.name.replace(/lines\[\d+\]/, `lines[${i}]`);
        });

        // 5) Reset valeurs (qté=1, mois=1, m²/PU=0, autres vides)
        tr.querySelectorAll('input').forEach(inp => {
            if (inp.classList.contains('line-qte') || inp.classList.contains('line-mois')) {
                inp.value = '1';
            } else if (inp.classList.contains('line-m2') || inp.classList.contains('line-pu')) {
                inp.value = '0';
            } else {
                inp.value = '';
            }
        });
        const sel = tr.querySelector('.line-commune');
        if (sel) sel.selectedIndex = 0;
        const totalCell = tr.querySelector('.line-total');
        if (totalCell) totalCell.textContent = '0 FCFA';

        tbody.appendChild(tr);
        bindRow(tr);
        renumberLines();
        recompute();
    }

    addBtn?.addEventListener('click', addLine);
    tbody.querySelectorAll('.line-row').forEach(bindRow);

    // Sentinelle de version (2026-07-16c) — permet de vérifier depuis
    // la console du navigateur que le NOUVEAU script tourne bien.
    // Si le log n'apparaît pas → c'est du cache navigateur agressif,
    // faire Ctrl+F5 (ou Ctrl+Shift+R sur Firefox).
    console.log('[FORM-FNE] v2026-07-16c chargé — guard M²/QTÉ/MOIS + filtre panneaux actif');

    // Guard boot (2026-07-16) — filet indépendant du handler select2.
    // Si un input critique arrive VIDE (cache navigateur ancien HTML,
    // old() Laravel avec chaîne vide, race JS…), on force un défaut
    // visible avant même que l'utilisateur ne clique quelque part.
    // Garantit que les colonnes M², QTÉ, MOIS ne restent JAMAIS vides.
    let guardFixCount = 0;
    tbody.querySelectorAll('.line-row').forEach(row => {
        const q = row.querySelector('.line-qte');
        const m = row.querySelector('.line-mois');
        const s = row.querySelector('.line-m2');
        const p = row.querySelector('.line-pu');
        if (q && (q.value === '' || Number(q.value) < 1))   { q.value = '1'; guardFixCount++; }
        if (m && (m.value === '' || Number(m.value) < 0.5)) { m.value = '1'; guardFixCount++; }
        if (s && s.value === '') { s.value = '0'; guardFixCount++; }
        if (p && p.value === '') { p.value = '0'; guardFixCount++; }
    });
    if (guardFixCount > 0) {
        console.log('[FORM-FNE] guard boot a corrigé ' + guardFixCount + ' champ(s) vide(s)');
    }

    ['remise_pct'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', recompute);
    });
    // Services annexes existants : binding initial sur les .svc-prix
    document.querySelectorAll('#services-tbody .svc-prix').forEach(el => {
        el.addEventListener('input', recompute);
    });

    // Filtre panneaux déjà sélectionnés — mise à jour après chaque changement
    // de désignation dans une ligne. Rebindé sur toutes les lignes existantes.
    tbody.addEventListener('change', function(e) {
        if (e.target && (e.target.classList.contains('line-designation') ||
                         e.target.classList.contains('line-panel-id') ||
                         e.target.classList.contains('line-external-panel-id'))) {
            refreshTakenPanelsCache();
        }
    });
    // Init cache à l'ouverture
    refreshTakenPanelsCache();

    recompute();
})();

/**
 * Cache des panel_id / external_panel_id déjà sélectionnés dans la facture.
 * Consommé par le processResults() du Select2 panneau (bug 3 — 2026-07-16)
 * pour retirer visuellement les panneaux déjà pris — l'utilisateur ne peut
 * plus les re-sélectionner, ce qui prévient les doublons AVANT le POST.
 * (La validation serveur reste en place comme filet de sécurité.)
 *
 * Fix 2026-07-16d : function refreshTakenPanelsCache() est hoisted mais
 * window.takenPanelsCache = {...} est une assignment NON hoisted. Résultat :
 * quand l'IIFE ci-dessus appelle refreshTakenPanelsCache() à l'init,
 * window.takenPanelsCache est encore undefined → « Cannot read 'int' ».
 * Solution : initialiser DANS la fonction si absent (lazy init).
 */
function refreshTakenPanelsCache() {
    if (!window.takenPanelsCache) {
        window.takenPanelsCache = { int: new Set(), ext: new Set() };
    }
    const cache = window.takenPanelsCache;
    cache.int.clear();
    cache.ext.clear();
    document.querySelectorAll('#lines-tbody .line-row').forEach(row => {
        const p = row.querySelector('.line-panel-id')?.value;
        const e = row.querySelector('.line-external-panel-id')?.value;
        if (p) cache.int.add(String(p));
        if (e) cache.ext.add(String(e));
    });
}
</script>
@endpush

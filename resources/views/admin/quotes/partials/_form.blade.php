{{--
    Formulaire réutilisé par create + edit.
    Variables attendues :
      $quote      : Quote|null
      $clients    : Collection
      $campaigns  : Collection
      $communes   : Collection
      $preselect  : ['client_id' => ?, 'campaign_id' => ?]
      $isEdit     : bool (implicite)
      $validDays  : int (par défaut 30)
      $action     : string (URL submit)
      $method     : 'POST' (create) ou 'PUT' (edit)
--}}
@php
    $isEdit = isset($quote) && $quote !== null;
    $oldLines = old('lines');
    // Priorité : anciennes valeurs (repost validation) > lignes du devis en édition
    //          > lignes pré-remplies depuis Disponibilités > une ligne vide.
    $prefilled = $prefilledLines ?? [];

    // Pré-charge des noms panneaux pour l'affichage initial des Select2 AJAX
    // (edit mode ou POST failed). Sans ça les lignes existantes montreraient
    // juste "panel_id=42" sans texte au chargement du form.
    $panelNames = [];
    if ($isEdit) {
        $ids = $quote->lines->pluck('panel_id')->filter()->unique();
        if ($ids->isNotEmpty()) {
            foreach (\App\Models\Panel::whereIn('id', $ids)->get(['id', 'reference', 'adresse', 'quartier', 'name']) as $p) {
                $panelNames[$p->id] = trim(($p->reference ?? '') . ' — ' . ($p->adresse ?: $p->quartier ?: $p->name ?: ''));
            }
        }
    }

    $lines = $oldLines ?: ($isEdit && $quote->lines->count() > 0
        ? $quote->lines->map(fn($l) => [
            'designation'   => $l->designation,
            'commune_id'    => $l->commune_id,
            'dimension_m2'  => $l->dimension_m2,
            'pu_ht_mensuel' => $l->pu_ht_mensuel,
            'quantite'      => $l->quantite,
            'duree_mois'    => $l->duree_mois,
            'panel_id'      => $l->panel_id,
            'panel_label'   => $panelNames[$l->panel_id] ?? null,
            'external_panel_id' => $l->external_panel_id,
        ])->all()
        : (!empty($prefilled)
            ? $prefilled
            : [['designation'=>'', 'dimension_m2'=>0, 'pu_ht_mensuel'=>0, 'quantite'=>1, 'duree_mois'=>1, 'panel_id'=>null, 'external_panel_id'=>null]]));

    $oldServices = old('services');
    $services = $oldServices ?: ($isEdit && $quote->services->count() > 0
        ? $quote->services->map(fn($s) => [
            'label'          => $s->label,
            'prix_ht'        => $s->prix_ht,
            'tva_applicable' => (bool) $s->tva_applicable,
          ])->all()
        : []);
    // Taux de TVA du devis (colonne `tva`), pour afficher le TTC de chaque
    // service dans le formulaire (2026-10-30).
    $quoteTva = (float) old('tva', $isEdit ? ($quote->tva ?? 18) : 18);
@endphp

{{-- Deux colonnes (2026-10-30) : la saisie à gauche, le récapitulatif et
     l'aperçu du devis à droite, qui suit le défilement. Sur petit écran,
     la colonne passe dessous (cf. la règle responsive plus bas). --}}
<div class="qf-colonnes">
<form method="POST" action="{{ $action }}" id="quote-form" style="display:flex;flex-direction:column;gap:20px">
    @csrf
    @if($method === 'PUT') @method('PUT') @endif

    @if($errors->any())
        <div style="padding:14px;background:#fef2f2;border:1px solid #fca5a5;border-radius:8px">
            <div style="font-weight:700;color:#b91c1c;margin-bottom:6px">{{ $errors->count() }} erreur(s) à corriger</div>
            @foreach($errors->all() as $err)
                <div style="color:#991b1b;font-size:13px">• {{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- ── Bloc 1 : identité du devis ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <h3 style="font-size:14px;font-weight:800;margin-bottom:14px">📋 Identité du devis</h3>
        {{-- Numéro du devis (2026-10-30) : attribué par Panora, affiché pour
             qu'on sache lequel sera pris, mais non modifiable — un numéro
             saisi à la main créerait des doublons. Quote::generateReference()
             le produit déjà à l'enregistrement, il n'était simplement nulle
             part à l'écran. --}}
        <div style="margin-bottom:14px;display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:8px">
            <span style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;font-weight:700">Numéro du devis</span>
            <strong style="font-family:monospace;font-size:15px;color:var(--accent)">{{ $isEdit ? $quote->reference : ($referenceProposee ?? '—') }}</strong>
            <span style="font-size:11px;color:var(--text3)">
                {{ $isEdit ? 'attribué à la création' : 'attribué automatiquement à l\'enregistrement' }}
            </span>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
            <div>
                <label class="qf-label">Client *</label>
                @php $sel = old('client_id', $isEdit ? $quote->client_id : ($preselect['client_id'] ?? null)); @endphp
                <select name="client_id" id="quote-client" required class="qf-select2" data-placeholder="🔍 Rechercher un client…">
                    <option value=""></option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" @selected($sel==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="qf-label">Campagne liée (optionnel)</label>
                @php $sel = old('campaign_id', $isEdit ? $quote->campaign_id : ($preselect['campaign_id'] ?? null)); @endphp
                <select name="campaign_id" id="quote-campaign" class="qf-select2" data-placeholder="— Aucune campagne —">
                    <option value=""></option>
                    @foreach($campaigns as $c)
                        <option value="{{ $c->id }}" data-client="{{ $c->client_id }}" @selected($sel==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        {{-- Interlocuteur du client (2026-10-29) --}}
        <div style="margin-top:12px">
            @include('admin.partials.interlocuteur-select', [
                'clientId' => old('client_id', $isEdit ? $quote->client_id : ($preselect['client_id'] ?? null)),
                'selection' => $isEdit ? $quote->client_contact_id : null,
                'classeLabel' => 'qf-label', 'classeSelect' => 'qf-input',
            ])
        </div>
        {{-- Titre proposé à partir du client et de la campagne (2026-10-30),
             et laissé modifiable. Dès qu'on le modifie, Panora n'y touche
             plus — data-auto passe à 0. --}}
        <div style="margin-top:12px">
            <label class="qf-label">Titre du devis *</label>
            @php $titreSaisi = old('title', $isEdit ? $quote->title : ''); @endphp
            <input type="text" name="title" id="quote-title" required maxlength="200"
                   value="{{ $titreSaisi }}"
                   data-auto="{{ $titreSaisi === '' ? '1' : '0' }}"
                   placeholder="proposé d'après le client et la campagne — modifiable"
                   class="qf-input">
            <div style="font-size:11px;color:var(--text3);margin-top:4px">
                Proposé d'après le client et la campagne. Écrivez par-dessus pour le fixer.
            </div>
        </div>
        <div style="margin-top:16px;padding:10px 14px;background:#f5f3ff;border-left:3px solid #8b5cf6;border-radius:0 6px 6px 0">
            <div style="font-size:12px;font-weight:700;color:#6d28d9;margin-bottom:4px">📅 Période de la campagne</div>
            <div style="font-size:11.5px;color:#475569;line-height:1.5">
                Ces dates correspondent à la <strong>période d'affichage</strong> souhaitée par le client
                (quand les affiches seront visibles sur les panneaux). Elles servent au calcul du prix
                (PU × durée) et au contrôle de disponibilité à l'acceptation du devis.
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:10px">
            <div>
                <label class="qf-label">Début de campagne</label>
                <input type="date" name="period_start" value="{{ old('period_start', $isEdit ? $quote->period_start?->format('Y-m-d') : ($preselect['period_start'] ?? '')) }}" class="qf-input">
                <div class="qf-hint">Premier jour d'affichage prévu</div>
            </div>
            <div>
                <label class="qf-label">Fin de campagne</label>
                <input type="date" name="period_end" value="{{ old('period_end', $isEdit ? $quote->period_end?->format('Y-m-d') : ($preselect['period_end'] ?? '')) }}" class="qf-input">
                <div class="qf-hint">Dernier jour d'affichage prévu</div>
            </div>
        </div>

        <div style="margin-top:16px;padding:10px 14px;background:#fef3c7;border-left:3px solid #f59e0b;border-radius:0 6px 6px 0">
            <div style="font-size:12px;font-weight:700;color:#78350f;margin-bottom:4px">⏱ Validité du devis</div>
            <div style="font-size:11.5px;color:#475569;line-height:1.5">
                Nombre de jours pendant lesquels ce devis <strong>engage la régie</strong> après l'envoi
                au client. Le client peut accepter/refuser dans ce délai. Au-delà, le devis passe
                automatiquement en <strong>EXPIRÉ</strong> et une alerte t'est envoyée 3 jours avant.
                (Par défaut : {{ (int) config('billing.quote_valid_days_default', 30) }} jours.)
            </div>
        </div>
        <div style="display:grid;grid-template-columns:200px 1fr;gap:14px;margin-top:10px;align-items:end">
            <div>
                <label class="qf-label">Validité (jours) *</label>
                <input type="number" name="valid_days" min="1" max="365" value="{{ old('valid_days', $validDays) }}" class="qf-input">
                <div class="qf-hint">Ex. 7 jours = client a 1 semaine</div>
            </div>
            <div style="padding:10px;background:var(--surface2);border-radius:8px;font-size:11.5px;color:var(--text3)">
                💡 <strong>À ne pas confondre :</strong>
                <br>« Période de campagne » = quand les affiches sont visibles.
                <br>« Validité » = combien de temps le client a pour dire oui.
            </div>
        </div>
    </div>

    {{-- ── Bloc 2 : lignes panneaux ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <h3 style="font-size:14px;font-weight:800">🪧 Panneaux proposés</h3>
            <button type="button" onclick="QuoteForm.addLine()" style="background:var(--accent);color:#fff;padding:8px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer">+ Ajouter une ligne</button>
        </div>
        <p style="font-size:12px;color:var(--text3);margin-bottom:12px;line-height:1.55">
            💡 Utilise <strong>« Rechercher un panneau »</strong> pour choisir un panneau du parc (surface, tarif et commune se pré-remplissent).
            Ou tape une <strong>désignation libre</strong> pour proposer un emplacement hors parc.
            <br>
            ⚠️ Un panneau ne peut être ajouté qu'<strong>une seule fois</strong>. Pour proposer plusieurs exemplaires du même,
            augmente la colonne <strong>Qté</strong> plutôt que de dupliquer la ligne.
        </p>
        {{-- Défilement horizontal (2026-10-30) : sans lui, les colonnes
             de droite (m², PU, Qté, Mois) étaient écrasées puis coupées
             dès que la fenêtre rétrécissait. Les menus déroulants ne sont
             pas rognés : Select2 les attache au <body> (dropdownParent). --}}
        <div class="ql-defilement">
            <table id="quote-lines-table" class="ql-table" style="border-collapse:separate;border-spacing:0 8px">
                <thead>
                    <tr>
                        <th class="ql-th ql-col-desi" style="text-align:left">Panneau / Désignation</th>
                        <th class="ql-th ql-col-commune" style="text-align:left">Commune</th>
                        <th class="ql-th ql-col-m2" style="text-align:right">m²</th>
                        <th class="ql-th ql-col-pu" style="text-align:right">PU HT / mois</th>
                        <th class="ql-th ql-col-qte" style="text-align:center">Qté</th>
                        <th class="ql-th ql-col-mois" style="text-align:center">Mois</th>
                        <th class="ql-col-action" style="background:var(--surface2);border-radius:0 8px 8px 0"></th>
                    </tr>
                </thead>
                <tbody id="quote-lines-tbody">
                    @foreach($lines as $i => $l)
                        <tr class="ql-row" data-index="{{ $i }}" style="background:var(--surface2)">
                            <td class="ql-td">
                                <div class="ql-cell-stack">
                                    <select name="lines[{{ $i }}][panel_id]" class="ql-panel-search" data-line-index="{{ $i }}" data-placeholder="🔍 Rechercher un panneau du parc…">
                                        <option value=""></option>
                                        @if(!empty($l['panel_id']))
                                            <option value="{{ $l['panel_id'] }}" selected>{{ $l['panel_label'] ?? ('Panneau #' . $l['panel_id']) }}</option>
                                        @endif
                                    </select>
                                    <input type="text" name="lines[{{ $i }}][designation]" required maxlength="200" value="{{ $l['designation'] ?? '' }}" placeholder="ex. SP-001 — San-Pedro Triangle (ou saisie libre)" class="ql-input ql-designation">
                                </div>
                            </td>
                            <td class="ql-td" data-label="Commune">
                                <select name="lines[{{ $i }}][commune_id]" class="ql-input ql-commune-select" data-placeholder="—">
                                    <option value=""></option>
                                    @foreach($communes as $c)
                                        <option value="{{ $c->id }}" @selected(($l['commune_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="ql-td" data-label="m²"><input type="number" name="lines[{{ $i }}][dimension_m2]" required min="0" step="0.01" value="{{ $l['dimension_m2'] ?: 0 }}" class="ql-input ql-num"></td>
                            <td class="ql-td" data-label="PU HT / mois"><input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" required min="0" step="1" value="{{ $l['pu_ht_mensuel'] ?: 0 }}" class="ql-input ql-num"></td>
                            <td class="ql-td" data-label="Qté"><input type="number" name="lines[{{ $i }}][quantite]" required min="1" step="1" value="{{ $l['quantite'] ?: 1 }}" class="ql-input ql-num ql-center"></td>
                            <td class="ql-td" data-label="Mois"><input type="number" name="lines[{{ $i }}][duree_mois]" required min="0.5" step="0.5" value="{{ $l['duree_mois'] ?: 1 }}" class="ql-input ql-num ql-center"></td>
                            <td class="ql-td" style="text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Bloc 3 : services annexes (optionnel) ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h3 style="font-size:14px;font-weight:800">🔧 Services annexes (optionnel)</h3>
            <button type="button" onclick="QuoteForm.addService()" style="background:var(--accent);color:#fff;padding:8px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer">+ Ajouter un service</button>
        </div>
        <div class="ql-defilement">
            <table id="quote-services-table" class="qs-table" style="border-collapse:collapse">
                <thead>
                    <tr>
                        <th class="ql-th" style="text-align:left">Libellé</th>
                        <th class="ql-th" style="text-align:right;width:150px">Prix HT (FCFA)</th>
                        {{-- TVA au choix, service par service (2026-10-30) :
                             même règle que la facture FNE. Un frais déjà taxé
                             par le fournisseur se refacture sans TVA. --}}
                        <th class="ql-th" style="text-align:center;width:110px">TVA {{ rtrim(rtrim(number_format($quoteTva, 2, ',', ''), '0'), ',') }}%</th>
                        <th class="ql-th" style="text-align:right;width:140px">TTC</th>
                        <th style="width:40px;background:var(--surface2)"></th>
                    </tr>
                </thead>
                <tbody id="quote-services-tbody">
                    @foreach($services as $i => $s)
                        @php $svcTva = !array_key_exists('tva_applicable', $s) || (bool) $s['tva_applicable']; @endphp
                        <tr class="qs-row">
                            <td style="padding:6px" data-label="Libellé"><input type="text" name="services[{{ $i }}][label]" maxlength="200" value="{{ $s['label'] ?? '' }}" placeholder="ex. Impression affiches dos bleu" class="ql-input"></td>
                            <td style="padding:6px" data-label="Prix HT"><input type="number" name="services[{{ $i }}][prix_ht]" min="0" step="1" value="{{ $s['prix_ht'] ?? 0 }}" class="ql-input ql-num qs-prix"></td>
                            <td style="padding:6px;text-align:center" data-label="TVA">
                                {{-- Le champ caché garantit qu'un interrupteur éteint
                                     envoie « 0 » : sans lui, rien n'est transmis et
                                     PHP comprend « absent » = soumis à TVA. --}}
                                <label class="qs-tva-toggle {{ $svcTva ? 'is-on' : 'is-off' }}">
                                    <input type="hidden" name="services[{{ $i }}][tva_applicable]" value="0">
                                    <input type="checkbox" name="services[{{ $i }}][tva_applicable]" value="1" class="qs-tva" {{ $svcTva ? 'checked' : '' }}>
                                    <span class="qs-tva-switch"></span>
                                    <span class="qs-tva-text">{{ $svcTva ? 'TVA' : 'HT' }}</span>
                                </label>
                            </td>
                            <td style="padding:6px;text-align:right" data-label="TTC"><span class="qs-ttc" style="font-weight:700">—</span></td>
                            <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove();QuoteForm.recalcServices()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{-- Sous-total TTC des services, comme sur la facture FNE. --}}
            <div style="display:flex;justify-content:flex-end;align-items:center;gap:10px;margin-top:10px;padding:8px 10px;background:rgba(232,160,32,.06);border-radius:8px">
                <span style="font-size:12px;color:var(--text2);font-weight:700">Sous-total services TTC</span>
                <span id="qs-sous-total" style="font-size:14px;font-weight:800;color:var(--accent)">0 FCFA</span>
            </div>
        </div>
    </div>

    {{-- ── Bloc 4 : remise + notes ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <h3 style="font-size:14px;font-weight:800;margin-bottom:14px">💬 Notes & remise</h3>
        <div style="display:grid;grid-template-columns:150px 1fr 1fr;gap:14px">
            <div>
                <label class="qf-label">Remise globale (%)</label>
                <input type="number" name="remise_pct" min="0" max="100" step="0.5" value="{{ old('remise_pct', $isEdit ? $quote->remise_pct : 0) }}" class="qf-input">
            </div>
            <div>
                <label class="qf-label">Message client (apparaît dans le PDF/email)</label>
                <textarea name="notes_client" rows="3" maxlength="2000" class="qf-input" style="height:auto;padding:10px">{{ old('notes_client', $isEdit ? $quote->notes_client : '') }}</textarea>
            </div>
            <div>
                <label class="qf-label">Notes internes (jamais envoyées au client)</label>
                <textarea name="notes_internes" rows="3" maxlength="2000" class="qf-input" style="height:auto;padding:10px">{{ old('notes_internes', $isEdit ? $quote->notes_internes : '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- ── Boutons de soumission ── --}}
    <div style="display:flex;justify-content:flex-end;gap:10px;padding:16px;background:var(--surface);border:1px solid var(--border);border-radius:10px">
        <a href="{{ route('admin.quotes.index') }}" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-primary" style="padding:10px 24px">
            {{ $isEdit ? '💾 Enregistrer les modifications' : '📝 Créer le devis (brouillon)' }}
        </button>
    </div>
</form>

{{-- ═══ Colonne de droite : le récapitulatif ═══
     Calculé par le SERVEUR (QuoteController::totaux) avec les mêmes
     règles qu'à l'enregistrement : ce qu'on lit ici est ce qui sera
     enregistré, au franc près.

     Le total à payer est hors de la zone qui défile : avec plusieurs
     services, il sortait de l'écran et devenait illisible. --}}
<aside class="qf-cote">
    <div class="qf-recap">
        <div class="qf-recap-tete">
            <span>Récapitulatif</span>
            <span class="qf-recap-etat" id="qf-etat">en direct</span>
        </div>

        <div class="qf-recap-corps">
            <div class="qf-recap-ligne"><span>Total HT brut</span><strong id="qf-brut">0</strong></div>
            <div class="qf-recap-ligne qf-recap-remise" id="qf-ligne-remise" hidden>
                <span id="qf-remise-libelle">Remise</span><strong id="qf-remise">0</strong>
            </div>
            <div class="qf-recap-ligne qf-recap-fort"><span>Total HT</span><strong id="qf-net">0</strong></div>
            <div class="qf-recap-ligne"><span id="qf-tva-libelle">TVA</span><strong id="qf-tva">0</strong></div>

            {{-- Taxes détaillées, une ligne chacune (2026-10-30) : elles
                 étaient regroupées sous « Taxes (TSP · TM · ODP) », on ne
                 savait pas ce que couvrait le montant. --}}
            <div class="qf-recap-groupe" id="qf-groupe-taxes" hidden>
                <div class="qf-recap-sous-titre">Taxes</div>
                <div class="qf-recap-ligne" id="qf-ligne-tsp" hidden>
                    <span id="qf-tsp-libelle">TSP</span><strong id="qf-tsp">0</strong>
                </div>
                <div class="qf-recap-ligne" id="qf-ligne-tm" hidden>
                    <span>Taxe municipale <small>TM</small></span><strong id="qf-tm">0</strong>
                </div>
                <div class="qf-recap-ligne" id="qf-ligne-odp" hidden>
                    <span>Occupation du domaine public <small>ODP</small></span><strong id="qf-odp">0</strong>
                </div>
            </div>

            {{-- Chaque service est nommé (2026-10-30) : un « Total TTC »
                 seul ne disait pas d'où venait le montant. Le montant
                 affiché est le TTC du service, badge « HT » s'il échappe
                 à la TVA. Même présentation que le récapitulatif de la
                 facture FNE. --}}
            <div class="qf-recap-groupe" id="qf-groupe-services" hidden>
                <div class="qf-recap-sous-titre">Services annexes</div>
                <div id="qf-services-detail" class="qf-recap-detail"></div>
                <div class="qf-recap-ligne qf-recap-sous-total"><span>Total HT</span><strong id="qf-services-ht">0</strong></div>
                <div class="qf-recap-ligne qf-recap-sous-total"><span>Total TTC</span><strong id="qf-services">0</strong></div>
            </div>
        </div>

        <div class="qf-recap-total">
            <span>Total à payer</span>
            <strong id="qf-total">0 FCFA</strong>
        </div>
    </div>
</aside>
</div>

@push('scripts')
{{-- Select2 (partagé avec admin/invoices/index et admin/quotes/index) --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<style>
    .qf-label { font-size:12px; font-weight:600; color:var(--text2); display:block; margin-bottom:4px; }
    .qf-hint  { font-size:11px; color:var(--text3, #64748b); margin-top:4px; font-style:italic; }
    .qf-input, .qf-select2 {
        width:100%; height:40px; padding:0 12px;
        background:var(--surface2); border:1px solid var(--border2, var(--border));
        border-radius:8px; color:var(--text); font-size:13px; font-family:inherit;
    }
    .qf-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(232,160,32,.15); }

    /* ── Lignes panneaux : chaque ligne dans un "tile" pour lisibilité ── */
    /* ═══ Deux colonnes : saisie à gauche, récapitulatif à droite ═══
       (2026-10-30). minmax(0,1fr) est indispensable : sans lui, une
       colonne de grille prend la largeur de son contenu le plus large et
       le tableau des lignes pousse toute la page au lieu de défiler. */
    .qf-colonnes { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:20px; align-items:start; }
    .qf-cote { position:sticky; top:72px; }
    @media (max-width: 1280px) { .qf-colonnes { grid-template-columns:1fr; } .qf-cote { position:static; } }

    /* Le récap se tient en hauteur d'écran : seul son CORPS défile, la
       barre du total reste visible. Avec plusieurs services, elle sortait
       de l'écran et on ne voyait plus le montant. */
    .qf-recap {
        background:var(--surface); border:1px solid var(--border); border-radius:10px;
        overflow:hidden; display:flex; flex-direction:column; max-height:calc(100vh - 96px);
    }
    @media (max-width: 1280px) { .qf-recap { max-height:none; } }
    .qf-recap-tete {
        display:flex; align-items:center; justify-content:space-between;
        padding:10px 14px; background:var(--surface2); border-bottom:1px solid var(--border);
        font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:var(--text2);
    }
    .qf-recap-etat { font-weight:600; text-transform:none; letter-spacing:0; color:var(--text3); }
    .qf-recap-etat.calcul { color:var(--accent); }

    /* Seul le corps défile — la barre du total reste toujours à l'écran. */
    .qf-recap-corps { padding:12px 14px; display:flex; flex-direction:column; gap:7px; overflow-y:auto; min-height:0; }
    .qf-recap-ligne { display:flex; justify-content:space-between; align-items:baseline; gap:10px; font-size:12.5px; color:var(--text2); }
    .qf-recap-ligne span { min-width:0; }
    .qf-recap-ligne small { font-size:10px; color:var(--text3); font-weight:700; }
    .qf-recap-ligne strong { color:var(--text); white-space:nowrap; flex-shrink:0; }
    .qf-recap-fort { padding-top:7px; border-top:1px solid var(--border); font-weight:700; }
    .qf-recap-fort span, .qf-recap-fort strong { color:var(--text); }
    .qf-recap-remise span, .qf-recap-remise strong { color:#b45309; }

    /* Groupes repliés visuellement : taxes et services annexes. */
    .qf-recap-groupe { display:flex; flex-direction:column; gap:6px; padding:8px 0 0; border-top:1px solid var(--border); }
    .qf-recap-sous-titre {
        font-size:10px; font-weight:800; text-transform:uppercase; letter-spacing:.5px;
        color:var(--text3);
    }

    /* Détail nommé des services. Un libellé trop long est coupé par des
       points de suspension plutôt que de pousser le montant hors du cadre. */
    .qf-recap-detail { display:flex; flex-direction:column; gap:3px; }
    .qf-recap-detail .qf-recap-ligne span {
        overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:62%;
    }
    .qf-recap-detail .qf-recap-ligne { font-size:12px; }
    .qf-recap-detail .qf-recap-ligne strong { font-weight:600; }
    .qf-recap-vide { font-size:11.5px; color:var(--text3); font-style:italic; }
    .qf-badge-ht {
        display:inline-block; margin-left:4px; padding:1px 5px; border-radius:4px;
        background:rgba(59,130,246,.12); color:#1d4ed8; font-size:9px; font-weight:800;
    }
    .qf-recap-sous-total { padding-top:5px; border-top:1px dashed var(--border); font-weight:700; }
    .qf-recap-sous-total span { color:var(--text2); }

    .qf-recap-total {
        display:flex; justify-content:space-between; align-items:center; gap:10px;
        padding:14px; background:var(--accent); color:#fff; font-weight:800;
        flex-shrink:0;
    }
    .qf-recap-total strong { font-size:15px; white-space:nowrap; }

    /* Interrupteur TVA d'un service annexe (2026-10-30) — même apparence
       que celui de la facture FNE, pour que les deux écrans se ressemblent. */
    .qs-tva-toggle {
        display:inline-flex; align-items:center; gap:8px; cursor:pointer;
        padding:0 10px; height:36px; border:1px solid var(--border);
        background:var(--surface2); border-radius:8px; user-select:none;
    }
    .qs-tva-toggle:hover { border-color:var(--text3); }
    .qs-tva-toggle input[type="checkbox"] { position:absolute; opacity:0; width:0; height:0; }
    .qs-tva-toggle .qs-tva-switch {
        position:relative; width:34px; height:20px; background:#94a3b8;
        border-radius:999px; transition:background .15s; flex-shrink:0;
    }
    .qs-tva-toggle .qs-tva-switch::after {
        content:''; position:absolute; top:2px; left:2px; width:16px; height:16px;
        background:#fff; border-radius:50%; transition:transform .15s;
    }
    .qs-tva-toggle.is-on  .qs-tva-switch { background:#16a34a; }
    .qs-tva-toggle.is-on  .qs-tva-switch::after { transform:translateX(14px); }
    .qs-tva-toggle .qs-tva-text { font-size:11px; font-weight:800; line-height:1; min-width:30px; }
    .qs-tva-toggle.is-on  .qs-tva-text { color:#16a34a; }
    .qs-tva-toggle.is-off .qs-tva-text { color:var(--text3); }

    .ql-th { padding:6px 10px; font-size:11px; color:var(--text3); background:var(--surface2); text-transform:uppercase; letter-spacing:.5px; }
    .ql-th:first-child { border-radius:8px 0 0 8px; }
    .ql-td { padding:8px 10px; vertical-align:middle; background:var(--surface2); }
    .ql-td:first-child { border-radius:8px 0 0 8px; }
    .ql-td:last-child, .ql-td:nth-last-child(2) { padding-right:10px; }
    .ql-cell-stack { display:flex; flex-direction:column; gap:6px; }
    .ql-input {
        width:100%; height:40px; padding:0 10px;
        background:var(--surface); border:1px solid var(--border);
        border-radius:6px; color:var(--text); font-size:13.5px; font-family:inherit;
    }
    .ql-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(232,160,32,.12); }
    .ql-num { text-align:right; }
    .ql-center { text-align:center; }

    /* ═══ Tableau des lignes : défilement horizontal et colonnes assez
       larges pour qu'on LISE ce qu'on tape (2026-10-30) ═══
       Les champs chiffrés étaient si étroits qu'un tarif à six chiffres
       y était tronqué. Largeurs reprises de la facture FNE. */
    .ql-defilement { overflow-x:auto; overflow-y:visible; padding-bottom:4px; }
    .ql-table { width:100%; min-width:1020px; }
    .ql-col-desi    { min-width:300px; }
    .ql-col-commune { width:170px; min-width:150px; }
    .ql-col-m2      { width:110px; min-width:100px; }
    .ql-col-pu      { width:150px; min-width:140px; }
    .ql-col-qte     { width:90px;  min-width:80px; }
    .ql-col-mois    { width:100px; min-width:90px; }
    .ql-col-action  { width:46px; }

    /* Les flèches natives des champs nombre mangent la place utile :
       on les retire, la valeur occupe toute la largeur. */
    .ql-num::-webkit-outer-spin-button,
    .ql-num::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    .ql-num { -moz-appearance:textfield; appearance:textfield; }

    /* Une barre de défilement discrète mais visible : sans elle, on ne
       devine pas qu'il reste des colonnes à droite. */
    .ql-defilement::-webkit-scrollbar { height:8px; }
    .ql-defilement::-webkit-scrollbar-track { background:var(--surface2); border-radius:999px; }
    .ql-defilement::-webkit-scrollbar-thumb { background:var(--border2, var(--border)); border-radius:999px; }
    .ql-defilement::-webkit-scrollbar-thumb:hover { background:var(--text3); }

    /* Services annexes : mêmes règles, en plus étroit. */
    .qs-table { width:100%; min-width:720px; }
    .qs-table .ql-th:first-child { min-width:260px; }
    .ql-designation { background:var(--surface); border-color:var(--border); }
    textarea.qf-input { line-height:1.5; }

    /* ── Harmonisation Select2 ── */
    .select2-container--default .select2-selection--single {
        height: 40px !important;
        background: var(--surface2) !important;
        border: 1px solid var(--border2, var(--border)) !important;
        border-radius: 8px !important;
        padding: 0 4px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 40px !important;
        color: var(--text) !important;
        font-size: 13px !important;
        padding-left: 10px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 38px !important; }
    .select2-container--default .select2-selection--single .select2-selection__clear {
        margin-right: 22px !important; color: #94a3b8 !important;
    }
    /* Versions "compactes" pour les cellules du tableau lignes */
    /* Variante compacte pour les Select2 des lignes du tableau — ciblée
       via un scope parent sur le <td> plutôt que dropdownCssClass qui
       requiert le module compat manquant du build min de Select2 4.0.13. */
    .ql-td .select2-container .select2-selection--single {
        height: 38px !important;
        background: var(--surface) !important;
        border-color: var(--border) !important;
        border-radius: 6px !important;
    }
    .ql-td .select2-container .select2-selection--single .select2-selection__rendered { line-height: 38px !important; padding-left: 8px !important; }
    .ql-td .select2-container .select2-selection--single .select2-selection__arrow { height: 36px !important; }

    /* ── Dropdown : fond + padding + z-index élevé pour ne pas être caché ── */
    .select2-container--open { z-index: 100000 !important; }
    .select2-dropdown {
        background: var(--surface, #fff) !important;
        color: var(--text, #0f172a) !important;
        border: 1px solid var(--border2, var(--border, #cbd5e1)) !important;
        border-radius: 10px !important;
        box-shadow: 0 12px 32px rgba(0,0,0,.15) !important;
        overflow: hidden !important;
    }
    .select2-results { background: var(--surface, #fff) !important; }
    .select2-results__options { max-height: 320px !important; }
    .select2-results__option {
        padding: 10px 14px !important;
        color: var(--text, #0f172a) !important;
        font-size: 13px !important;
        line-height: 1.4 !important;
        border-bottom: 1px solid var(--border, #eef2f7) !important;
        white-space: normal !important;
        word-break: break-word !important;
    }
    /* Le dropdown des panneaux (et autres Select2 de cette page) doit
       pouvoir dépasser la largeur du champ trigger — sinon les longues
       adresses "Rond-point hôtel de ville…" sont tronquées.
       min-width: min(480px, 92vw) → 480 px sur desktop, mais reste
       lisible sur mobile en respectant le viewport. */
    .select2-dropdown { min-width: min(480px, 92vw) !important; }
    /* Template panneau : référence en gras + adresse en gris en dessous */
    .panel-result { display:flex; flex-direction:column; gap:2px; }
    .panel-result .pr-head { font-weight:700; color:var(--text, #0f172a); font-size:13px; }
    .panel-result .pr-head .pr-badge {
        display:inline-block; margin-left:6px; padding:1px 6px; border-radius:4px;
        font-size:10px; font-weight:700; letter-spacing:.3px; text-transform:uppercase;
    }
    .panel-result .pr-badge-libre    { background:#dcfce7; color:#166534; }
    .panel-result .pr-badge-option   { background:#fef3c7; color:#78350f; }
    .panel-result .pr-badge-confirme,
    .panel-result .pr-badge-occupe   { background:#fee2e2; color:#991b1b; }
    .panel-result .pr-badge-maintenance { background:#e0e7ff; color:#3730a3; }
    .panel-result .pr-sub { font-size:11.5px; color:var(--text3, #64748b); }
    .panel-result .pr-meta { font-size:11px; color:var(--text3, #64748b); margin-top:2px; }
    .panel-result .pr-meta strong { color:var(--accent, #e8a020); }
    .select2-results__option:last-child { border-bottom: none !important; }
    .select2-results__option[aria-selected="true"] {
        background: var(--surface2, #f8fafc) !important;
        font-weight: 700 !important;
    }
    .select2-search--dropdown {
        padding: 8px !important;
        background: var(--surface2, #f8fafc) !important;
        border-bottom: 1px solid var(--border, #e2e8f0) !important;
    }
    .select2-search--dropdown .select2-search__field {
        background: var(--surface, #fff) !important;
        color: var(--text, #0f172a) !important;
        border: 1px solid var(--border, #e2e8f0) !important;
        border-radius: 6px !important;
        padding: 8px 12px !important;
        font-size: 13px !important;
    }
    .select2-results__option--highlighted[aria-selected] {
        background: var(--accent, #e8a020) !important;
        color: #fff !important;
    }
    .select2-results__message {
        padding: 12px 14px !important;
        color: var(--text3, #64748b) !important;
        font-style: italic !important;
    }

    /* ══════════════════════════════════════════════════════════════
       RESPONSIVE — formulaire devis
       ══════════════════════════════════════════════════════════════ */
    /* Grilles identité (client/campagne, dates/validité, notes/remise) */
    @media (max-width: 900px) {
        #quote-form div[style*="grid-template-columns:1fr 1fr 1fr"],
        #quote-form div[style*="grid-template-columns:150px 1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }
    @media (max-width: 700px) {
        #quote-form div[style*="grid-template-columns:1fr 1fr"] {
            grid-template-columns: 1fr !important;
        }
    }

    /* Tableau des lignes — sur mobile chaque ligne devient une carte
       empilée pour rester lisible sans scroll horizontal. */
    @media (max-width: 800px) {
        /* La table passe en cartes : le min-width et le défilement
           horizontal n'ont plus lieu d'être, et provoqueraient un
           débordement de la page (2026-10-30). */
        .ql-table { min-width: 0; }
        .ql-defilement { overflow-x: visible; }

        #quote-lines-table thead { display: none; }
        #quote-lines-table, #quote-lines-table tbody { display: block; width: 100%; }
        #quote-lines-table tr.ql-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 12px;
            background: var(--surface2);
        }
        #quote-lines-table tr.ql-row td.ql-td {
            display: block;
            padding: 0;
            background: transparent;
            border-radius: 0;
        }
        /* Le "Panneau/Désignation" occupe toute la largeur */
        #quote-lines-table tr.ql-row td.ql-td:first-child { grid-column: 1 / -1; }
        /* Le bouton supprimer va tout en bas à droite */
        #quote-lines-table tr.ql-row td.ql-td:last-child {
            grid-column: 1 / -1;
            text-align: right;
            border-top: 1px dashed var(--border);
            padding-top: 8px;
        }
        /* Labels visibles au-dessus de chaque champ (via ::before) */
        #quote-lines-table tr.ql-row td.ql-td::before {
            content: attr(data-label);
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 4px;
        }
        #quote-lines-table tr.ql-row td.ql-td:first-child::before { content: "Panneau / Désignation"; }
        #quote-lines-table tr.ql-row td.ql-td:last-child::before { content: ""; }
    }

    /* Tableau services — même approche mobile-first */
    @media (max-width: 600px) {
        .qs-table { min-width: 0; }
        #quote-services-table, #quote-services-table tbody { display: block; }
        #quote-services-table thead { display: none; }
        #quote-services-table tr.qs-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 8px;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 8px;
        }
        #quote-services-table tr.qs-row td {
            padding: 0 !important;
            display: block;
        }
        #quote-services-table tr.qs-row td:first-child { grid-column: 1 / -1; }

        /* Étiquettes au-dessus de chaque champ, comme pour les lignes :
           sans en-tête de tableau, « 59 004 » tout seul ne veut rien dire
           (2026-10-30). Le libellé et le bouton supprimer s'en passent. */
        #quote-services-table tr.qs-row td::before {
            content: attr(data-label);
            display: block;
            font-size: 10px; font-weight: 700; color: var(--text3);
            text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px;
        }
        #quote-services-table tr.qs-row td:first-child::before,
        #quote-services-table tr.qs-row td:last-child::before { content: ""; }
        #quote-services-table tr.qs-row td:last-child { align-self: end; text-align: right; }
    }
    .qf-panel-hint { font-size:11px; color:var(--text3); margin-top:2px; }
</style>

<script>
window.QuoteForm = (function() {
    const communes  = @json($communes->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])->values());
    const searchUrl = @json(route('admin.quotes.search-panels'));
    let lineIdx     = {{ count($lines) }};
    let serviceIdx  = {{ count($services) }};

    const $ = window.jQuery;

    // ── Init des Select2 "identité" du devis (client + campagne) ──
    function initHeaderSelects() {
        $('#quote-client, #quote-campaign').each(function() {
            $(this).select2({
                placeholder: $(this).data('placeholder'),
                allowClear: true,
                width: '100%',
                dropdownParent: $('body'),
                language: {
                    noResults: () => 'Aucun résultat',
                    searching: () => 'Recherche…',
                },
            });
        });

        // Bonus UX : quand on choisit une campagne, on aligne le client
        // automatiquement (une campagne appartient à un seul client).
        $('#quote-campaign').on('change', function() {
            const opt = this.options[this.selectedIndex];
            const cid = opt?.getAttribute('data-client');
            if (cid && $('#quote-client').val() !== cid) {
                $('#quote-client').val(cid).trigger('change');
            }
        });
    }

    // ── Init d'une ligne (Select2 panneau + Select2 commune) ──
    function initLineSelects(row) {
        const $row = $(row);

        // Select2 AJAX sur le panneau — dropdown attaché au <body> pour
        // ne pas être tronqué par le tableau parent.
        const $panel = $row.find('.ql-panel-search');

        // Template résultat riche : réf en gras + adresse en dessous +
        // ligne meta (commune · m² · tarif catalogue).
        const escapeHtml = s => String(s || '').replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        })[c]);
        const templatePanel = (p) => {
            if (!p.id) return escapeHtml(p.text || ''); // placeholder / "searching..."
            const addr    = p.address || p.name || '';
            const commune = p.commune_name || '—';
            const m2      = p.dimension_m2 ? Number(p.dimension_m2).toLocaleString('fr-FR') + ' m²' : '';
            const tarif   = p.monthly_rate ? Number(p.monthly_rate).toLocaleString('fr-FR') + ' FCFA/mois' : '';
            const status  = (p.status || '').toLowerCase();
            const badge   = status ? `<span class="pr-badge pr-badge-${escapeHtml(status)}">${escapeHtml(status)}</span>` : '';
            const meta    = [commune, m2, tarif].filter(Boolean).map(escapeHtml).join(' · ');
            return $(`
                <div class="panel-result">
                    <div class="pr-head">${escapeHtml(p.reference || '—')} ${badge}</div>
                    <div class="pr-sub">${escapeHtml(addr)}</div>
                    <div class="pr-meta">${meta ? '<strong>' + meta + '</strong>' : ''}</div>
                </div>
            `);
        };
        const templatePanelSelected = (p) => {
            if (!p.id) return p.text || '';
            return $(`<span>${escapeHtml(p.reference || '')} — ${escapeHtml(p.address || p.name || '')}</span>`);
        };

        $panel.select2({
            placeholder: $panel.data('placeholder'),
            allowClear: true,
            width: '100%',
            dropdownParent: $('body'),
            minimumInputLength: 0,
            escapeMarkup: m => m,
            templateResult: templatePanel,
            templateSelection: templatePanelSelected,
            language: {
                inputTooShort: () => 'Tape pour rechercher…',
                noResults: () => 'Aucun panneau trouvé',
                searching: () => 'Recherche…',
                loadingMore: () => 'Chargement…',
            },
            ajax: {
                url: searchUrl,
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term || '' }),
                processResults: data => ({ results: data.results || [] }),
                cache: true,
            },
        }).on('select2:select', function(e) {
            const p   = e.params?.data || {};
            const idx = $row.data('index');
            const pid = String(p.id || '');

            // ── Anti-doublon : le même panneau ne peut pas apparaître 2× ──
            // Un panneau physique ne peut être proposé qu'une seule fois par
            // devis — le nombre d'exemplaires se gère avec la colonne Qté.
            if (pid) {
                const existing = document.querySelectorAll('#quote-lines-tbody .ql-panel-search');
                for (const sel of existing) {
                    if (sel === $panel[0]) continue;
                    if (String(sel.value || '') === pid) {
                        alert(
                            "⚠️ " + (p.reference || 'Ce panneau') + " est déjà dans le devis.\n\n" +
                            "Pour proposer plusieurs exemplaires du même panneau, " +
                            "augmente la colonne « Qté » de la ligne existante."
                        );
                        // Reset propre du Select2 sur cette ligne
                        $panel.val(null).trigger('change');
                        // Scroll vers la ligne existante pour aider le commercial
                        const $existingRow = $(sel).closest('tr.ql-row');
                        if ($existingRow.length) {
                            $existingRow[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                            $existingRow.css('outline', '2px solid #f59e0b');
                            setTimeout(() => $existingRow.css('outline', ''), 1800);
                        }
                        return;
                    }
                }
            }

            // Pré-remplit désignation, commune, dimension, PU HT
            if (p.text) {
                $row.find('.ql-designation').val(p.text);
            }
            if (p.commune_id) {
                const $c = $row.find('.ql-commune-select');
                $c.val(String(p.commune_id)).trigger('change');
            }
            if (Number(p.dimension_m2) > 0) {
                $row.find('input[name="lines[' + idx + '][dimension_m2]"]').val(p.dimension_m2);
            }
            if (Number(p.monthly_rate) > 0) {
                $row.find('input[name="lines[' + idx + '][pu_ht_mensuel]"]').val(p.monthly_rate);
            }
        }).on('select2:clear', function() {
            // Réinit hidden panel_id — le champ désignation reste (le user peut vouloir le garder)
        });

        // Select2 sur la commune de la ligne (recherche parmi la liste locale)
        const $commune = $row.find('.ql-commune-select');
        $commune.select2({
            placeholder: $commune.data('placeholder') || '—',
            allowClear: true,
            width: '100%',
            dropdownParent: $('body'),
            language: {
                noResults: () => 'Aucune commune',
                searching: () => 'Recherche…',
            },
        });
    }

    // ── Ajout d'une nouvelle ligne panneau ──
    function addLine() {
        const tbody = document.getElementById('quote-lines-tbody');
        const i = lineIdx++;
        const communesHtml = communes.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        const row = document.createElement('tr');
        row.className = 'ql-row';
        row.setAttribute('data-index', i);
        row.style.background = 'var(--surface2)';
        row.innerHTML = `
            <td class="ql-td">
                <div class="ql-cell-stack">
                    <select name="lines[${i}][panel_id]" class="ql-panel-search" data-line-index="${i}" data-placeholder="🔍 Rechercher un panneau du parc…"><option value=""></option></select>
                    <input type="text" name="lines[${i}][designation]" required maxlength="200" placeholder="ex. SP-001 — San-Pedro Triangle (ou saisie libre)" class="ql-input ql-designation">
                </div>
            </td>
            <td class="ql-td" data-label="Commune">
                <select name="lines[${i}][commune_id]" class="ql-input ql-commune-select" data-placeholder="—"><option value=""></option>${communesHtml}</select>
            </td>
            <td class="ql-td" data-label="m²"><input type="number" name="lines[${i}][dimension_m2]" required min="0" step="0.01" value="0" class="ql-input ql-num"></td>
            <td class="ql-td" data-label="PU HT / mois"><input type="number" name="lines[${i}][pu_ht_mensuel]" required min="0" step="1" value="0" class="ql-input ql-num"></td>
            <td class="ql-td" data-label="Qté"><input type="number" name="lines[${i}][quantite]" required min="1" step="1" value="1" class="ql-input ql-num ql-center"></td>
            <td class="ql-td" data-label="Mois"><input type="number" name="lines[${i}][duree_mois]" required min="0.5" step="0.5" value="1" class="ql-input ql-num ql-center"></td>
            <td class="ql-td" style="text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
        `;
        tbody.appendChild(row);
        initLineSelects(row);
    }

    // ── Ajout service annexe ──
    function addService() {
        const tbody = document.getElementById('quote-services-tbody');
        const i = serviceIdx++;
        const row = document.createElement('tr');
        row.className = 'qs-row';
        row.innerHTML = `
            <td style="padding:6px" data-label="Libellé"><input type="text" name="services[${i}][label]" maxlength="200" placeholder="ex. Impression affiches dos bleu" class="ql-input"></td>
            <td style="padding:6px" data-label="Prix HT"><input type="number" name="services[${i}][prix_ht]" min="0" step="1" value="0" class="ql-input ql-num qs-prix"></td>
            <td style="padding:6px;text-align:center" data-label="TVA">
                <label class="qs-tva-toggle is-on">
                    <input type="hidden" name="services[${i}][tva_applicable]" value="0">
                    <input type="checkbox" name="services[${i}][tva_applicable]" value="1" class="qs-tva" checked>
                    <span class="qs-tva-switch"></span>
                    <span class="qs-tva-text">TVA</span>
                </label>
            </td>
            <td style="padding:6px;text-align:right" data-label="TTC"><span class="qs-ttc" style="font-weight:700">—</span></td>
            <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove();recalcServices()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
        `;
        tbody.appendChild(row);
        recalcServices();
    }

    /**
     * TTC de chaque service et sous-total (2026-10-30).
     * Le taux vient du champ TVA du devis : un seul endroit à changer.
     */
    function recalcServices() {
        const taux = parseFloat(document.querySelector('[name="tva"]')?.value ?? {{ $quoteTva }}) || 0;
        let totalTtc = 0;

        document.querySelectorAll('#quote-services-tbody .qs-row').forEach(row => {
            const prix   = parseFloat(row.querySelector('.qs-prix')?.value) || 0;
            const coche  = row.querySelector('.qs-tva')?.checked;
            const ttc    = coche ? Math.round(prix * (1 + taux / 100)) : Math.round(prix);
            totalTtc    += ttc;

            const cible = row.querySelector('.qs-ttc');
            if (cible) cible.textContent = prix > 0 ? ttc.toLocaleString('fr-FR') + ' F' : '—';

            // L'étiquette de l'interrupteur suit son état.
            const bascule = row.querySelector('.qs-tva-toggle');
            const texte   = row.querySelector('.qs-tva-text');
            if (bascule) bascule.classList.toggle('is-on', !!coche);
            if (bascule) bascule.classList.toggle('is-off', !coche);
            if (texte)   texte.textContent = coche ? 'TVA' : 'HT';
        });

        const sousTotal = document.getElementById('qs-sous-total');
        if (sousTotal) sousTotal.textContent = totalTtc.toLocaleString('fr-FR') + ' FCFA';
    }

    // ── Boot ──
    $(function() {
        initHeaderSelects();
        document.querySelectorAll('#quote-lines-tbody .ql-row').forEach(initLineSelects);

        // Titre proposé d'après le client et la campagne, tant qu'on ne
        // l'a pas écrit soi-même.
        const titre = document.getElementById('quote-title');
        const proposerTitre = () => {
            if (!titre || titre.dataset.auto !== '1') return;
            const client   = document.querySelector('#quote-client option:checked')?.textContent?.trim();
            const campagne = document.querySelector('#quote-campaign option:checked')?.textContent?.trim();
            if (!client) return;
            titre.value = campagne ? `${client} — ${campagne}` : client;
        };
        titre?.addEventListener('input', () => { titre.dataset.auto = '0'; });
        $('#quote-client, #quote-campaign').on('change', proposerTitre);
        proposerTitre();

        // Services : TTC recalculé à chaque saisie de prix, à chaque
        // bascule de TVA, et quand le taux du devis change.
        document.addEventListener('input', e => {
            if (e.target.matches('.qs-prix, [name="tva"]')) recalcServices();
        });
        document.addEventListener('change', e => {
            if (e.target.matches('.qs-tva, [name="tva"]')) recalcServices();
        });
        recalcServices();

        // Récapitulatif : toute modification du formulaire le relance.
        ['input', 'change'].forEach(ev =>
            document.getElementById('quote-form')?.addEventListener(ev, demanderTotaux));
        $('#quote-client, #quote-campaign').on('change', demanderTotaux);
        demanderTotaux();
    });

    // ── Récapitulatif en direct (2026-10-30) ───────────────────────
    // Le serveur calcule, le navigateur se contente d'afficher : aucune
    // formule n'est recopiée ici (CLAUDE.md).
    const TOTAUX_URL = @json(route('admin.quotes.totaux'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('#quote-form input[name="_token"]')?.value;

    let minuteur = null;
    let demandeEnCours = 0;
    const fmt = n => Number(n || 0).toLocaleString('fr-FR');

    function demanderTotaux() {
        // Anti-rafale : on ne part qu'une fois la saisie calmée.
        clearTimeout(minuteur);
        document.getElementById('qf-etat')?.classList.add('calcul');
        minuteur = setTimeout(chargerTotaux, 400);
    }

    // Un libellé de service est saisi librement : il part dans du HTML,
    // donc il est échappé avant affichage.
    const echapper = s => String(s ?? '').replace(/[&<>"']/g, c =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

    const txt  = (id, v) => { const e = document.getElementById(id); if (e) e.textContent = v; };
    const bloc = (id, afficher) => { const e = document.getElementById(id); if (e) e.hidden = !afficher; };
    const taux = v => rtrimZeros(Number(v || 0).toFixed(2));
    const rtrimZeros = s => s.replace(/,?0+$/, '').replace(/\.?0+$/, '').replace('.', ',');

    async function chargerTotaux() {
        const form = document.getElementById('quote-form');
        if (!form) return;

        const mien = ++demandeEnCours;

        try {
            const r = await fetch(TOTAUX_URL, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();

            // Une réponse dépassée par une plus récente est ignorée :
            // sinon le récap peut revenir en arrière sur une frappe rapide.
            if (mien !== demandeEnCours) return;

            const t = d.totaux || {};

            txt('qf-brut',  fmt(t.amount));
            txt('qf-net',   fmt(t.net_ht));
            txt('qf-tva',   fmt(t.tva_amount));
            txt('qf-tva-libelle', 'TVA (' + taux(t.tva) + ' %)');
            txt('qf-total', fmt(t.total_a_payer) + ' FCFA');

            const remise = (t.amount || 0) - (t.net_ht || 0);
            bloc('qf-ligne-remise', remise > 0);
            txt('qf-remise-libelle', 'Remise (' + taux(t.remise_pct) + ' %)');
            txt('qf-remise', '− ' + fmt(remise));

            // Taxes, une ligne chacune : on doit savoir ce qu'on paie.
            const tsp = t.tsp_amount || 0, tm = t.tm_total || 0, odp = t.odp_total || 0;
            bloc('qf-groupe-taxes', tsp + tm + odp > 0);
            bloc('qf-ligne-tsp', tsp > 0); txt('qf-tsp', fmt(tsp));
            bloc('qf-ligne-tm',  tm  > 0); txt('qf-tm',  fmt(tm));
            bloc('qf-ligne-odp', odp > 0); txt('qf-odp', fmt(odp));

            // Services : chacun par son nom, puis les deux totaux.
            const services = d.services || [];
            bloc('qf-groupe-services', services.length > 0);
            txt('qf-services-ht', fmt(t.services_ht_total));
            txt('qf-services',    fmt(t.services_ttc_total));

            const detail = document.getElementById('qf-services-detail');
            if (detail) {
                detail.innerHTML = services.length === 0
                    ? '<div class="qf-recap-vide">Aucun service ajouté</div>'
                    : services.map(s =>
                        '<div class="qf-recap-ligne">'
                        + '<span title="' + echapper(s.label) + '">' + echapper(s.label)
                        + (s.tva_applicable ? '' : '<span class="qf-badge-ht">HT</span>')
                        + '</span><strong>' + fmt(s.ttc) + '</strong></div>'
                      ).join('');
            }
        } catch (e) {
            // Un récapitulatif en panne ne doit jamais gêner la saisie.
            console.warn('Récapitulatif indisponible :', e.message);
        } finally {
            if (mien === demandeEnCours) {
                document.getElementById('qf-etat')?.classList.remove('calcul');
            }
        }
    }

    return { addLine, addService, recalcServices, demanderTotaux };
})();
</script>
@endpush

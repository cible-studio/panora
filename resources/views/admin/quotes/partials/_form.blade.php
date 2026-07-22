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
        ? $quote->services->map(fn($s) => ['label'=>$s->label, 'prix_ht'=>$s->prix_ht])->all()
        : []);
@endphp

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
        <div style="margin-top:12px">
            <label class="qf-label">Titre du devis *</label>
            <input type="text" name="title" required maxlength="200" value="{{ old('title', $isEdit ? $quote->title : '') }}" placeholder="ex. Campagne lancement Duster - Q2 2026" class="qf-input">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:12px">
            <div>
                <label class="qf-label">Période début</label>
                <input type="date" name="period_start" value="{{ old('period_start', $isEdit ? $quote->period_start?->format('Y-m-d') : ($preselect['period_start'] ?? '')) }}" class="qf-input">
            </div>
            <div>
                <label class="qf-label">Période fin</label>
                <input type="date" name="period_end" value="{{ old('period_end', $isEdit ? $quote->period_end?->format('Y-m-d') : ($preselect['period_end'] ?? '')) }}" class="qf-input">
            </div>
            <div>
                <label class="qf-label">Validité (jours)</label>
                <input type="number" name="valid_days" min="1" max="365" value="{{ old('valid_days', $validDays) }}" class="qf-input">
            </div>
        </div>
    </div>

    {{-- ── Bloc 2 : lignes panneaux ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
            <h3 style="font-size:14px;font-weight:800">🪧 Panneaux proposés</h3>
            <button type="button" onclick="QuoteForm.addLine()" style="background:var(--accent);color:#fff;padding:8px 14px;border:none;border-radius:8px;font-weight:700;cursor:pointer">+ Ajouter une ligne</button>
        </div>
        <p style="font-size:12px;color:var(--text3);margin-bottom:12px">
            💡 Utilise <strong>« Rechercher un panneau »</strong> pour choisir un panneau du parc (surface, tarif et commune se pré-remplissent).
            Ou tape une <strong>désignation libre</strong> pour proposer un emplacement hors parc.
        </p>
        <div style="overflow:visible">
            <table id="quote-lines-table" style="width:100%;border-collapse:separate;border-spacing:0 8px">
                <thead>
                    <tr>
                        <th class="ql-th" style="text-align:left;min-width:280px">Panneau / Désignation</th>
                        <th class="ql-th" style="text-align:left;width:160px">Commune</th>
                        <th class="ql-th" style="text-align:right;width:100px">m²</th>
                        <th class="ql-th" style="text-align:right;width:120px">PU HT / mois</th>
                        <th class="ql-th" style="text-align:center;width:70px">Qté</th>
                        <th class="ql-th" style="text-align:center;width:80px">Mois</th>
                        <th style="width:40px;background:var(--surface2);border-radius:0 8px 8px 0"></th>
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
        <div style="overflow-x:auto">
            <table id="quote-services-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th class="ql-th" style="text-align:left">Libellé</th>
                        <th class="ql-th" style="text-align:right;width:160px">Prix HT (FCFA)</th>
                        <th style="width:40px;background:var(--surface2)"></th>
                    </tr>
                </thead>
                <tbody id="quote-services-tbody">
                    @foreach($services as $i => $s)
                        <tr class="qs-row">
                            <td style="padding:6px"><input type="text" name="services[{{ $i }}][label]" maxlength="200" value="{{ $s['label'] ?? '' }}" placeholder="ex. Impression affiches dos bleu" class="ql-input"></td>
                            <td style="padding:6px"><input type="number" name="services[{{ $i }}][prix_ht]" min="0" step="1" value="{{ $s['prix_ht'] ?? 0 }}" class="ql-input ql-num"></td>
                            <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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

@push('scripts')
{{-- Select2 (partagé avec admin/invoices/index et admin/quotes/index) --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<style>
    .qf-label { font-size:12px; font-weight:600; color:var(--text2); display:block; margin-bottom:4px; }
    .qf-input, .qf-select2 {
        width:100%; height:40px; padding:0 12px;
        background:var(--surface2); border:1px solid var(--border2, var(--border));
        border-radius:8px; color:var(--text); font-size:13px; font-family:inherit;
    }
    .qf-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(232,160,32,.15); }

    /* ── Lignes panneaux : chaque ligne dans un "tile" pour lisibilité ── */
    .ql-th { padding:6px 10px; font-size:11px; color:var(--text3); background:var(--surface2); text-transform:uppercase; letter-spacing:.5px; }
    .ql-th:first-child { border-radius:8px 0 0 8px; }
    .ql-td { padding:8px 10px; vertical-align:middle; background:var(--surface2); }
    .ql-td:first-child { border-radius:8px 0 0 8px; }
    .ql-td:last-child, .ql-td:nth-last-child(2) { padding-right:10px; }
    .ql-cell-stack { display:flex; flex-direction:column; gap:6px; }
    .ql-input {
        width:100%; height:38px; padding:0 10px;
        background:var(--surface); border:1px solid var(--border);
        border-radius:6px; color:var(--text); font-size:13px; font-family:inherit;
    }
    .ql-input:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(232,160,32,.12); }
    .ql-num { text-align:right; }
    .ql-center { text-align:center; }
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
            <td style="padding:6px"><input type="text" name="services[${i}][label]" maxlength="200" placeholder="ex. Impression affiches dos bleu" class="ql-input"></td>
            <td style="padding:6px"><input type="number" name="services[${i}][prix_ht]" min="0" step="1" value="0" class="ql-input ql-num"></td>
            <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
        `;
        tbody.appendChild(row);
    }

    // ── Boot ──
    $(function() {
        initHeaderSelects();
        document.querySelectorAll('#quote-lines-tbody .ql-row').forEach(initLineSelects);
    });

    return { addLine, addService };
})();
</script>
@endpush

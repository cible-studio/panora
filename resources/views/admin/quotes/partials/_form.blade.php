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
    $lines = $oldLines ?: ($isEdit && $quote->lines->count() > 0
        ? $quote->lines->map(fn($l) => [
            'designation'   => $l->designation,
            'commune_id'    => $l->commune_id,
            'dimension_m2'  => $l->dimension_m2,
            'pu_ht_mensuel' => $l->pu_ht_mensuel,
            'quantite'      => $l->quantite,
            'duree_mois'    => $l->duree_mois,
            'panel_id'      => $l->panel_id,
            'external_panel_id' => $l->external_panel_id,
        ])->all()
        : [['designation'=>'', 'dimension_m2'=>0, 'pu_ht_mensuel'=>0, 'quantite'=>1, 'duree_mois'=>1, 'panel_id'=>null, 'external_panel_id'=>null]]);

    $oldServices = old('services');
    $services = $oldServices ?: ($isEdit && $quote->services->count() > 0
        ? $quote->services->map(fn($s) => ['label'=>$s->label, 'prix_ht'=>$s->prix_ht])->all()
        : []);
@endphp

<form method="POST" action="{{ $action }}" style="display:flex;flex-direction:column;gap:20px">
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
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Client *</label>
                <select name="client_id" required style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
                    <option value="">— Choisir un client —</option>
                    @foreach($clients as $c)
                        @php $sel = old('client_id', $isEdit ? $quote->client_id : ($preselect['client_id'] ?? null)); @endphp
                        <option value="{{ $c->id }}" @selected($sel==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Campagne liée (optionnel)</label>
                <select name="campaign_id" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
                    <option value="">— Aucune —</option>
                    @foreach($campaigns as $c)
                        @php $sel = old('campaign_id', $isEdit ? $quote->campaign_id : ($preselect['campaign_id'] ?? null)); @endphp
                        <option value="{{ $c->id }}" @selected($sel==$c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div style="margin-top:12px">
            <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Titre du devis *</label>
            <input type="text" name="title" required maxlength="200" value="{{ old('title', $isEdit ? $quote->title : '') }}" placeholder="ex. Campagne lancement Duster - Q2 2026" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:12px">
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Période début</label>
                <input type="date" name="period_start" value="{{ old('period_start', $isEdit ? $quote->period_start?->format('Y-m-d') : '') }}" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Période fin</label>
                <input type="date" name="period_end" value="{{ old('period_end', $isEdit ? $quote->period_end?->format('Y-m-d') : '') }}" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Validité (jours)</label>
                <input type="number" name="valid_days" min="1" max="365" value="{{ old('valid_days', $validDays) }}" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
            </div>
        </div>
    </div>

    {{-- ── Bloc 2 : lignes panneaux ── --}}
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h3 style="font-size:14px;font-weight:800">🪧 Panneaux proposés</h3>
            <button type="button" onclick="addQuoteLine()" style="background:var(--accent);color:#fff;padding:6px 12px;border:none;border-radius:6px;font-weight:700;cursor:pointer">+ Ajouter une ligne</button>
        </div>
        <div style="overflow-x:auto">
            <table id="quote-lines-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2)">Désignation</th>
                        <th style="text-align:left;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:140px">Commune</th>
                        <th style="text-align:right;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:90px">m²</th>
                        <th style="text-align:right;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:120px">PU HT/mois</th>
                        <th style="text-align:center;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:70px">Qté</th>
                        <th style="text-align:center;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:80px">Mois</th>
                        <th style="width:40px;background:var(--surface2)"></th>
                    </tr>
                </thead>
                <tbody id="quote-lines-tbody">
                    @foreach($lines as $i => $l)
                        <tr class="ql-row">
                            <td style="padding:6px"><input type="text" name="lines[{{ $i }}][designation]" required maxlength="200" value="{{ $l['designation'] ?? '' }}" placeholder="ex. SP-001 — San-Pedro Triangle" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px"></td>
                            <td style="padding:6px">
                                <select name="lines[{{ $i }}][commune_id]" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px">
                                    <option value="">—</option>
                                    @foreach($communes as $c)
                                        <option value="{{ $c->id }}" @selected(($l['commune_id'] ?? '') == $c->id)>{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="padding:6px"><input type="number" name="lines[{{ $i }}][dimension_m2]" required min="0" step="0.01" value="{{ $l['dimension_m2'] ?: 0 }}" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
                            <td style="padding:6px"><input type="number" name="lines[{{ $i }}][pu_ht_mensuel]" required min="0" step="1" value="{{ $l['pu_ht_mensuel'] ?: 0 }}" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
                            <td style="padding:6px"><input type="number" name="lines[{{ $i }}][quantite]" required min="1" step="1" value="{{ $l['quantite'] ?: 1 }}" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:center"></td>
                            <td style="padding:6px"><input type="number" name="lines[{{ $i }}][duree_mois]" required min="0.5" step="0.5" value="{{ $l['duree_mois'] ?: 1 }}" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:center"></td>
                            <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
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
            <button type="button" onclick="addQuoteService()" style="background:var(--accent);color:#fff;padding:6px 12px;border:none;border-radius:6px;font-weight:700;cursor:pointer">+ Ajouter un service</button>
        </div>
        <div style="overflow-x:auto">
            <table id="quote-services-table" style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2)">Libellé</th>
                        <th style="text-align:right;padding:8px;font-size:11px;color:var(--text3);background:var(--surface2);width:160px">Prix HT (FCFA)</th>
                        <th style="width:40px;background:var(--surface2)"></th>
                    </tr>
                </thead>
                <tbody id="quote-services-tbody">
                    @foreach($services as $i => $s)
                        <tr class="qs-row">
                            <td style="padding:6px"><input type="text" name="services[{{ $i }}][label]" maxlength="200" value="{{ $s['label'] ?? '' }}" placeholder="ex. Impression affiches dos bleu" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px"></td>
                            <td style="padding:6px"><input type="number" name="services[{{ $i }}][prix_ht]" min="0" step="1" value="{{ $s['prix_ht'] ?? 0 }}" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
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
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Remise globale (%)</label>
                <input type="number" name="remise_pct" min="0" max="100" step="0.5" value="{{ old('remise_pct', $isEdit ? $quote->remise_pct : 0) }}" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Message client (apparaît dans le PDF/email)</label>
                <textarea name="notes_client" rows="3" maxlength="2000" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">{{ old('notes_client', $isEdit ? $quote->notes_client : '') }}</textarea>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;color:var(--text2);display:block;margin-bottom:4px">Notes internes (jamais envoyées au client)</label>
                <textarea name="notes_internes" rows="3" maxlength="2000" style="width:100%;padding:10px;border:1px solid var(--border);border-radius:6px">{{ old('notes_internes', $isEdit ? $quote->notes_internes : '') }}</textarea>
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

<script>
let qlIdx = {{ count($lines) }};
function addQuoteLine() {
    const tbody = document.getElementById('quote-lines-tbody');
    const i = qlIdx++;
    const communesOpts = @json($communes->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])->values());
    const communesHtml = communesOpts.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    const row = document.createElement('tr');
    row.className = 'ql-row';
    row.innerHTML = `
        <td style="padding:6px"><input type="text" name="lines[${i}][designation]" required maxlength="200" placeholder="ex. SP-001 — San-Pedro Triangle" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px"></td>
        <td style="padding:6px"><select name="lines[${i}][commune_id]" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px"><option value="">—</option>${communesHtml}</select></td>
        <td style="padding:6px"><input type="number" name="lines[${i}][dimension_m2]" required min="0" step="0.01" value="0" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
        <td style="padding:6px"><input type="number" name="lines[${i}][pu_ht_mensuel]" required min="0" step="1" value="0" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
        <td style="padding:6px"><input type="number" name="lines[${i}][quantite]" required min="1" step="1" value="1" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:center"></td>
        <td style="padding:6px"><input type="number" name="lines[${i}][duree_mois]" required min="0.5" step="0.5" value="1" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:center"></td>
        <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
    `;
    tbody.appendChild(row);
}

let qsIdx = {{ count($services) }};
function addQuoteService() {
    const tbody = document.getElementById('quote-services-tbody');
    const i = qsIdx++;
    const row = document.createElement('tr');
    row.className = 'qs-row';
    row.innerHTML = `
        <td style="padding:6px"><input type="text" name="services[${i}][label]" maxlength="200" placeholder="ex. Impression affiches dos bleu" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px"></td>
        <td style="padding:6px"><input type="number" name="services[${i}][prix_ht]" min="0" step="1" value="0" style="width:100%;padding:8px;border:1px solid var(--border);border-radius:5px;text-align:right"></td>
        <td style="padding:6px;text-align:center"><button type="button" onclick="this.closest('tr').remove()" style="background:none;border:none;color:#ef4444;font-size:18px;cursor:pointer" title="Supprimer">🗑</button></td>
    `;
    tbody.appendChild(row);
}
</script>

<x-admin-layout title="Paramètres">

<x-slot:topbarActions>
</x-slot:topbarActions>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

    {{-- ══ COMMUNES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🏙️ Communes ({{ $communes->total() }})</div>
            <a href="{{ route('admin.settings.communes.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Ville</th>
                        <th>Taux ODP</th>
                        <th>Taux TM</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($communes as $commune)
                    <tr>
                        <td><strong>{{ $commune->name }}</strong></td>
                        <td style="color:var(--text2);font-size:12px;">{{ $commune->city ?? '—' }}</td>
                        <td style="font-size:12px;">{{ number_format($commune->odp_rate, 0, ',', ' ') }} FCFA</td>
                        <td style="font-size:12px;">{{ number_format($commune->tm_rate, 0, ',', ' ') }} FCFA</td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-quick-edit="commune"
                                        data-id="{{ $commune->id }}"
                                        data-name="{{ $commune->name }}"
                                        data-city="{{ $commune->city }}"
                                        data-region="{{ $commune->region }}"
                                        data-odp_rate="{{ $commune->odp_rate }}"
                                        data-tm_rate="{{ $commune->tm_rate }}"
                                        title="Modifier">✏️</button>
                                <form method="POST"
                                      action="{{ route('admin.settings.communes.destroy', $commune) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune commune
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($communes->hasPages())
        <div style="padding:10px 16px;border-top:1px solid var(--border);">
            {{ $communes->appends(request()->except('communes_page'))->links('pagination::simple-bootstrap-4') }}
        </div>
        @endif
    </div>

    {{-- ══ ZONES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📍 Zones ({{ $zones->total() }})</div>
            <a href="{{ route('admin.settings.zones.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Commune</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($zones as $zone)
                    <tr>
                        <td><strong>{{ $zone->name }}</strong></td>
                        <td style="color:var(--text2);font-size:12px;">{{ $zone->commune?->name ?? '—' }}</td>
                        <td style="font-size:11px;color:var(--text3);">
                            {{ \Illuminate\Support\Str::limit($zone->description ?? '', 30) ?: '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-quick-edit="zone"
                                        data-id="{{ $zone->id }}"
                                        data-name="{{ $zone->name }}"
                                        data-commune_id="{{ $zone->commune_id }}"
                                        data-description="{{ $zone->description }}"
                                        data-demand_level="{{ $zone->demand_level }}"
                                        title="Modifier">✏️</button>
                                <form method="POST"
                                      action="{{ route('admin.settings.zones.destroy', $zone) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune zone
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($zones->hasPages())
        <div style="padding:10px 16px;border-top:1px solid var(--border);">
            {{ $zones->appends(request()->except('zones_page'))->links('pagination::simple-bootstrap-4') }}
        </div>
        @endif
    </div>

    {{-- ══ FORMATS ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📐 Formats ({{ $formats->total() }})</div>
            <a href="{{ route('admin.settings.formats.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Dimensions</th>
                        <th>Surface</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($formats as $format)
                    <tr>
                        <td><strong>{{ $format->name }}</strong></td>
                        <td style="font-size:12px;color:var(--text2);">
                            {{ $format->dimensions_label ?? '—' }}
                        </td>
                        <td style="font-size:12px;">
                            {{ $format->surface_label ?? '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-quick-edit="format"
                                        data-id="{{ $format->id }}"
                                        data-name="{{ $format->name }}"
                                        data-width="{{ $format->width }}"
                                        data-height="{{ $format->height }}"
                                        data-surface="{{ $format->surface }}"
                                        data-print_type="{{ $format->print_type }}"
                                        title="Modifier">✏️</button>
                                <form method="POST"
                                      action="{{ route('admin.settings.formats.destroy', $format) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucun format
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($formats->hasPages())
        <div style="padding:10px 16px;border-top:1px solid var(--border);">
            {{ $formats->appends(request()->except('formats_page'))->links('pagination::simple-bootstrap-4') }}
        </div>
        @endif
    </div>

    {{-- ══ CATÉGORIES ══ --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🏷️ Catégories ({{ $categories->total() }})</div>
            <a href="{{ route('admin.settings.categories.create') }}" class="btn btn-primary btn-sm">＋ Ajouter</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Description</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($categories as $category)
                    <tr>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td style="font-size:11px;color:var(--text3);">
                            {{ \Illuminate\Support\Str::limit($category->description ?? '', 40) ?: '—' }}
                        </td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <button type="button" class="btn btn-ghost btn-sm"
                                        data-quick-edit="category"
                                        data-id="{{ $category->id }}"
                                        data-name="{{ $category->name }}"
                                        data-description="{{ $category->description }}"
                                        title="Modifier">✏️</button>
                                <form method="POST"
                                      action="{{ route('admin.settings.categories.destroy', $category) }}"
                                      onsubmit="return confirm('Supprimer ?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-danger btn-sm">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" style="text-align:center;color:var(--text3);padding:20px;">
                            Aucune catégorie
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div style="padding:10px 16px;border-top:1px solid var(--border);">
            {{ $categories->appends(request()->except('categories_page'))->links('pagination::simple-bootstrap-4') }}
        </div>
        @endif
    </div>

</div>

{{-- ══════ MODAL : Édition rapide commune ══════ --}}
<div id="modal-commune" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🏙️ Modifier la commune</div>
            <button type="button" class="modal-close" data-quick-close>✕</button>
        </div>
        <form id="form-commune" data-action-tpl="{{ url('admin/settings/communes') }}/__ID__">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="mfg">
                    <label>Nom <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="100">
                    <div class="field-error" data-error="name"></div>
                </div>
                <div class="form-2col">
                    <div class="mfg">
                        <label>Ville</label>
                        <input type="text" name="city" maxlength="100">
                        <div class="field-error" data-error="city"></div>
                    </div>
                    <div class="mfg">
                        <label>Région</label>
                        <input type="text" name="region" maxlength="100">
                        <div class="field-error" data-error="region"></div>
                    </div>
                </div>
                <div class="form-3col">
                    <div class="mfg">
                        <label>Taux ODP (FCFA)</label>
                        <input type="number" name="odp_rate" min="0" step="0.01">
                        <div class="field-error" data-error="odp_rate"></div>
                    </div>
                    <div class="mfg">
                        <label>Taux TM (FCFA)</label>
                        <input type="number" name="tm_rate" min="0" step="0.01">
                        <div class="field-error" data-error="tm_rate"></div>
                    </div>
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:6px;">
                    Les changements de tarif sont historisés (calculs rétroactifs conservés).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost btn-sm" data-quick-close>Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- ══════ MODAL : Édition rapide zone ══════ --}}
<div id="modal-zone" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📍 Modifier la zone</div>
            <button type="button" class="modal-close" data-quick-close>✕</button>
        </div>
        <form id="form-zone" data-action-tpl="{{ url('admin/settings/zones') }}/__ID__">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="mfg">
                    <label>Nom <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="100">
                    <div class="field-error" data-error="name"></div>
                </div>
                <div class="form-2col">
                    <div class="mfg">
                        <label>Commune</label>
                        <select name="commune_id">
                            <option value="">— Aucune —</option>
                            @foreach($allCommunes as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div class="field-error" data-error="commune_id"></div>
                    </div>
                    <div class="mfg">
                        <label>Niveau de demande <span style="color:var(--red)">*</span></label>
                        <select name="demand_level" required>
                            <option value="faible">Faible</option>
                            <option value="normale">Normale</option>
                            <option value="haute">Haute</option>
                            <option value="tres_haute">Très haute</option>
                        </select>
                        <div class="field-error" data-error="demand_level"></div>
                    </div>
                </div>
                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                    <div class="field-error" data-error="description"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost btn-sm" data-quick-close>Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- ══════ MODAL : Édition rapide format ══════ --}}
<div id="modal-format" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">📐 Modifier le format</div>
            <button type="button" class="modal-close" data-quick-close>✕</button>
        </div>
        <form id="form-format" data-action-tpl="{{ url('admin/settings/formats') }}/__ID__">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="mfg">
                    <label>Nom <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="50">
                    <div class="field-error" data-error="name"></div>
                </div>
                <div class="form-3col">
                    <div class="mfg">
                        <label>Largeur (m)</label>
                        <input type="number" name="width" min="0" step="0.01">
                        <div class="field-error" data-error="width"></div>
                    </div>
                    <div class="mfg">
                        <label>Hauteur (m)</label>
                        <input type="number" name="height" min="0" step="0.01">
                        <div class="field-error" data-error="height"></div>
                    </div>
                    <div class="mfg">
                        <label>Surface (m²)</label>
                        <input type="number" name="surface" min="0" step="0.01">
                        <div class="field-error" data-error="surface"></div>
                    </div>
                </div>
                <div class="mfg">
                    <label>Type d'impression</label>
                    <input type="text" name="print_type" maxlength="80" placeholder="Ex: bâche PVC, papier..">
                    <div class="field-error" data-error="print_type"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost btn-sm" data-quick-close>Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

{{-- ══════ MODAL : Édition rapide catégorie ══════ --}}
<div id="modal-category" class="modal-overlay" style="display:none;">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">🏷️ Modifier la catégorie</div>
            <button type="button" class="modal-close" data-quick-close>✕</button>
        </div>
        <form id="form-category" data-action-tpl="{{ url('admin/settings/categories') }}/__ID__">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="mfg">
                    <label>Nom <span style="color:var(--red)">*</span></label>
                    <input type="text" name="name" required maxlength="100">
                    <div class="field-error" data-error="name"></div>
                </div>
                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"></textarea>
                    <div class="field-error" data-error="description"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost btn-sm" data-quick-close>Annuler</button>
                <button type="submit" class="btn btn-primary btn-sm">💾 Enregistrer</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════════
// PARAMÈTRES — Édition rapide via modal (pas de navigation)
// ════════════════════════════════════════════════════════════════
(function () {
    const csrf = '{{ csrf_token() }}';
    const MODAL_BY_TYPE = {
        commune:  'modal-commune',
        zone:     'modal-zone',
        format:   'modal-format',
        category: 'modal-category',
    };

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    }
    function closeModal(el) {
        const m = el.closest('.modal-overlay');
        if (m) m.style.display = 'none';
        // efface les erreurs et libère le focus
        if (m) m.querySelectorAll('.field-error').forEach(e => e.textContent = '');
        if (m) m.querySelectorAll('.error').forEach(e => e.classList.remove('error'));
    }

    // Ouvre le bon modal depuis n'importe quel bouton data-quick-edit
    document.addEventListener('click', e => {
        const btn = e.target.closest('[data-quick-edit]');
        if (!btn) return;
        const type = btn.dataset.quickEdit;
        const modalId = MODAL_BY_TYPE[type];
        if (!modalId) return;
        const modal = document.getElementById(modalId);
        const form  = modal.querySelector('form');
        // injecte l'ID dans l'URL
        const id = btn.dataset.id;
        form.action = form.dataset.actionTpl.replace('__ID__', id);
        // peuple les champs depuis les data-*
        form.querySelectorAll('[name]').forEach(field => {
            if (['_token', '_method'].includes(field.name)) return;
            const v = btn.dataset[field.name];
            if (field.tagName === 'SELECT') {
                field.value = v ?? '';
            } else if (field.tagName === 'TEXTAREA') {
                field.value = v ?? '';
            } else if (field.type === 'checkbox') {
                field.checked = !!v && v !== '0';
            } else {
                field.value = v ?? '';
            }
        });
        // efface erreurs résiduelles
        modal.querySelectorAll('.field-error').forEach(e => e.textContent = '');
        modal.querySelectorAll('.error').forEach(e => e.classList.remove('error'));
        openModal(modalId);
    });

    // Fermeture (X / Annuler / clic sur backdrop)
    document.addEventListener('click', e => {
        if (e.target.matches('[data-quick-close]')) {
            closeModal(e.target);
        } else if (e.target.classList?.contains('modal-overlay')) {
            // clic en dehors du modal
            e.target.style.display = 'none';
        }
    });

    // Soumission AJAX des 4 formulaires
    ['form-commune', 'form-zone', 'form-format', 'form-category'].forEach(formId => {
        const form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalLabel = submitBtn?.textContent;
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '⏳ Enregistrement…'; }
            // reset des erreurs précédentes
            form.querySelectorAll('.field-error').forEach(e => e.textContent = '');
            form.querySelectorAll('.error').forEach(e => e.classList.remove('error'));

            try {
                const fd = new FormData(form);
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                });
                if (res.status === 422) {
                    const data = await res.json();
                    const errors = data.errors || {};
                    Object.entries(errors).forEach(([field, msgs]) => {
                        const err = form.querySelector(`[data-error="${field}"]`);
                        if (err) err.textContent = msgs[0];
                        const input = form.querySelector(`[name="${field}"]`);
                        if (input) input.classList.add('error');
                    });
                    return;
                }
                if (!res.ok) {
                    showToast('error', 'Erreur lors de la modification');
                    return;
                }
                // succès → recharge la page pour voir les changements
                showToast('success', 'Modification enregistrée !');
                setTimeout(() => location.reload(), 350);
            } catch (err) {
                console.error('settings.quick-edit.error', err);
                showToast('error', 'Erreur réseau');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalLabel; }
            }
        });
    });

    // Échap = ferme tout modal ouvert
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.modal-overlay').forEach(m => {
            if (m.style.display !== 'none') m.style.display = 'none';
        });
    });

    function showToast(type, message) {
        const colors = { success: '#22c55e', error: '#ef4444', info: '#3b82f6' };
        const t = document.createElement('div');
        t.style.cssText = `position:fixed;bottom:24px;right:24px;z-index:99999;padding:12px 18px;background:var(--surface);border-left:3px solid ${colors[type] || '#22c55e'};border-radius:10px;font-size:13px;color:var(--text);box-shadow:0 8px 24px rgba(0,0,0,.25);max-width:340px`;
        t.textContent = message;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, 2800);
    }
})();
</script>
@endpush

</x-admin-layout>

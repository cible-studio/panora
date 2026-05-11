<x-admin-layout>
<x-slot name="title">Signaler une panne</x-slot>

<x-slot:topbarLeft>
    <a href="{{ route('admin.maintenances.index') }}" class="btn btn-ghost btn-sm" title="Retour à la liste">
        ← Retour
    </a>
</x-slot:topbarLeft>

<div style="max-width:700px;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">🔧 Signaler une panne</div>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.maintenances.store') }}">
                @csrf

                @if($errors->any())
                <div style="background:rgba(239,68,68,.1); border:1px solid var(--red);
                            border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="color:var(--red); font-weight:600; margin-bottom:8px;">❌ Erreurs :</div>
                    <ul style="color:var(--red); padding-left:16px;">
                        @foreach($errors->all() as $error)
                            <li style="font-size:13px;">{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="section-label">Panneau concerné</div>

                <input type="hidden" name="panel_id" id="panel_id_hidden" value="{{ old('panel_id') }}">

                <div class="mfg" style="position:relative;">
                    <label>
                        Panneau *
                        <span style="font-size:11px;color:var(--text3);">Référence · nom · zone · commune (min. 2 caractères)</span>
                    </label>

                    <input type="text"
                           id="panel-search"
                           placeholder="Ex: P-ABC, Angré, Cocody, Plateau…"
                           autocomplete="off"
                           class="{{ $errors->has('panel_id') ? 'error' : '' }}"
                           style="width:100%;">

                    <div id="panel-dropdown"
                         style="display:none;position:absolute;top:100%;left:0;right:0;
                                background:var(--surface);border:1px solid var(--border2);
                                border-radius:10px;z-index:100;max-height:320px;overflow-y:auto;
                                box-shadow:0 8px 24px rgba(0,0,0,.3);margin-top:4px;">
                    </div>

                    <div id="panel-selected"
                         style="display:none;margin-top:8px;padding:10px 12px;
                                background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.3);
                                border-radius:8px;align-items:center;gap:10px;">
                        <span style="font-size:16px;">🪧</span>
                        <div style="flex:1;">
                            <div id="panel-selected-ref" style="font-weight:700;color:var(--accent);font-family:monospace;"></div>
                            <div id="panel-selected-name" style="font-size:12px;color:var(--text2);margin-top:2px;"></div>
                        </div>
                        <button type="button" onclick="clearPanel()"
                                style="background:none;border:none;color:var(--text3);cursor:pointer;font-size:16px;">✕</button>
                    </div>

                    @error('panel_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="section-label">Détails de la panne</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Type de panne *</label>
                        <input type="text" name="type_panne"
                               value="{{ old('type_panne') }}"
                               placeholder="Ex: Éclairage défaillant, Bâche déchirée..."
                               class="{{ $errors->has('type_panne') ? 'error' : '' }}">
                        @error('type_panne')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mfg">
                        <label>Priorité *</label>
                        <select name="priorite">
                            <option value="faible"  {{ old('priorite') === 'faible'  ? 'selected' : '' }}>⚪ Faible</option>
                            <option value="normale" {{ old('priorite', 'normale') === 'normale' ? 'selected' : '' }}>🔵 Normale</option>
                            <option value="haute"   {{ old('priorite') === 'haute'   ? 'selected' : '' }}>🟠 Haute</option>
                            <option value="urgente" {{ old('priorite') === 'urgente' ? 'selected' : '' }}>🔴 Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="mfg">
                    <label>Description</label>
                    <textarea name="description"
                              placeholder="Décrivez la panne en détail...">{{ old('description') }}</textarea>
                </div>

                <div class="section-label">Assignation</div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>
                            Technicien assigné
                            <button type="button" onclick="openQuickTechModal()"
                                    style="margin-left:8px;background:none;border:1px dashed var(--accent);color:var(--accent);font-size:11px;font-weight:700;padding:1px 9px;border-radius:8px;cursor:pointer;"
                                    title="Créer rapidement un technicien">
                                + Nouveau
                            </button>
                        </label>
                        <select name="technicien_id" id="technicien_select">
                            <option value="">— Non assigné —</option>
                            @foreach($techniciens as $tech)
                            <option value="{{ $tech->id }}"
                                {{ old('technicien_id') == $tech->id ? 'selected' : '' }}>
                                {{ $tech->name }}@if($tech->whatsapp_number) · {{ $tech->whatsapp_number }}@endif
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mfg">
                        <label>Date de signalement *</label>
                        <input type="date" name="date_signalement"
                               value="{{ old('date_signalement', date('Y-m-d')) }}"
                               class="{{ $errors->has('date_signalement') ? 'error' : '' }}">
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:8px;">
                    <button type="submit" class="btn btn-primary">
                        🔧 Signaler la panne
                    </button>
                    <a href="{{ route('admin.maintenances.index') }}"
                       class="btn btn-ghost">
                        Annuler
                    </a>
                </div>

            </form>
        </div>
    </div>
</div>

{{-- ════ MODAL "CRÉATION RAPIDE TECHNICIEN" ════ --}}
<div id="quick-tech-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:16px;"
     onclick="if(event.target===this)closeQuickTechModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:480px;display:flex;flex-direction:column;overflow:hidden;"
         onclick="event.stopPropagation()">
        <div style="padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;">
            <h3 style="font-size:15px;font-weight:700;">🔧 Nouveau technicien</h3>
            <button type="button" onclick="closeQuickTechModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);">✕</button>
        </div>
        <div style="padding:18px 22px;">
            <div style="background:rgba(232,160,32,.06);border:1px solid rgba(232,160,32,.2);border-radius:8px;padding:10px 12px;margin-bottom:14px;font-size:11.5px;color:var(--text2);">
                💡 Création rapide : un code agent <strong>TT-XXX</strong> est généré automatiquement.
                Le mot de passe temporaire sera affiché pour transmission au technicien.
            </div>
            <div class="mfg">
                <label>Nom complet *</label>
                <input type="text" id="qt-name" placeholder="Prénom Nom" maxlength="100">
            </div>
            <div class="form-2col">
                <div class="mfg">
                    <label>Téléphone</label>
                    <input type="text" id="qt-phone" placeholder="07 07 07 07 07" maxlength="30">
                </div>
                <div class="mfg">
                    <label>WhatsApp</label>
                    <input type="text" id="qt-whatsapp" placeholder="0707070707" maxlength="30">
                </div>
            </div>
            <div class="mfg">
                <label>Email (optionnel)</label>
                <input type="email" id="qt-email" placeholder="prenom.nom@cibleci.ci" maxlength="150">
                <small style="display:block;color:var(--text3);font-size:11px;margin-top:4px;">
                    Si vide, un email temporaire <em>@tech.local</em> est généré.
                </small>
            </div>
            <div id="qt-error" style="display:none;background:rgba(239,68,68,.1);border:1px solid var(--red);border-radius:8px;padding:9px 12px;margin-top:8px;color:var(--red);font-size:12px;"></div>
            <div id="qt-success" style="display:none;background:rgba(34,197,94,.1);border:1px solid #22c55e;border-radius:8px;padding:10px 12px;margin-top:8px;font-size:12px;color:#16a34a;"></div>
        </div>
        <div style="padding:14px 22px;border-top:1px solid var(--border);background:var(--surface2);display:flex;gap:8px;justify-content:flex-end;">
            <button type="button" onclick="closeQuickTechModal()" class="btn btn-ghost btn-sm">Annuler</button>
            <button type="button" id="qt-submit" onclick="submitQuickTech()" class="btn btn-primary btn-sm">
                ✓ Créer le technicien
            </button>
        </div>
    </div>
</div>

<script>
// ══════════════════════════════
// RECHERCHE AJAX PANNEAUX (debounce 300ms)
// ══════════════════════════════
const searchInput  = document.getElementById('panel-search');
const dropdown     = document.getElementById('panel-dropdown');
const hiddenInput  = document.getElementById('panel_id_hidden');
const selectedBox  = document.getElementById('panel-selected');
const selectedRef  = document.getElementById('panel-selected-ref');
const selectedName = document.getElementById('panel-selected-name');

let searchTimer = null;
let currentReq  = null;

const statusColors = {
    libre: '#22c55e', occupe: '#e8a020', maintenance: '#ef4444',
    option: '#3b82f6', confirme: '#8b5cf6'
};

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    })[m]);
}

async function searchPanels(q) {
    if (currentReq) currentReq.abort();
    if (q.length < 2) {
        dropdown.style.display = 'none';
        return;
    }

    dropdown.innerHTML = '<div style="padding:12px 14px;color:var(--text3);font-size:13px;">⏳ Recherche…</div>';
    dropdown.style.display = 'block';

    currentReq = new AbortController();
    try {
        const r = await fetch(`{{ route('admin.maintenances.search-panels') }}?q=${encodeURIComponent(q)}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            signal: currentReq.signal,
        });
        const results = await r.json();

        if (!Array.isArray(results) || results.length === 0) {
            dropdown.innerHTML = '<div style="padding:12px 14px;color:var(--text3);font-size:13px;">Aucun panneau trouvé pour « ' + escapeHtml(q) + ' »</div>';
            return;
        }

        dropdown.innerHTML = results.map(p => {
            const color = statusColors[p.status] || '#6b7280';
            const safeP = encodeURIComponent(JSON.stringify(p));
            return `<div onclick='selectPanelFromDataset(this)' data-p="${safeP}"
                        style="padding:10px 14px;cursor:pointer;border-bottom:1px solid var(--border);transition:background .1s;"
                        onmouseover="this.style.background='var(--surface2)'"
                        onmouseout="this.style.background=''">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span style="font-family:monospace;font-weight:700;color:var(--accent);font-size:13px;">${escapeHtml(p.ref)}</span>
                            <span style="font-size:10px;padding:2px 7px;border-radius:10px;
                                  background:${color}20;color:${color};border:1px solid ${color}40;font-weight:600;">
                                ${escapeHtml(p.status)}
                            </span>
                        </div>
                        <div style="font-size:12px;color:var(--text2);margin-top:3px;">${escapeHtml(p.name)}</div>
                        <div style="font-size:11px;color:var(--text3);">📍 ${escapeHtml(p.commune)}</div>
                    </div>`;
        }).join('');
    } catch (e) {
        if (e.name === 'AbortError') return;
        console.error(e);
        dropdown.innerHTML = '<div style="padding:12px 14px;color:var(--red);font-size:13px;">Erreur de recherche.</div>';
    }
}

searchInput.addEventListener('input', function() {
    const q = this.value.trim();
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => searchPanels(q), 300);
});

function selectPanelFromDataset(el) {
    selectPanel(JSON.parse(decodeURIComponent(el.dataset.p)));
}

function selectPanel(panel) {
    hiddenInput.value = panel.id;
    searchInput.value = '';
    dropdown.style.display = 'none';
    selectedRef.textContent  = panel.ref;
    selectedName.textContent = panel.name + ' — ' + panel.commune;
    selectedBox.style.display = 'flex';
    searchInput.style.display = 'none';
}

function clearPanel() {
    hiddenInput.value = '';
    selectedBox.style.display = 'none';
    searchInput.style.display = 'block';
    searchInput.value = '';
    searchInput.focus();
}

document.addEventListener('click', e => {
    if (!e.target.closest('#panel-search') && !e.target.closest('#panel-dropdown')) {
        dropdown.style.display = 'none';
    }
});

// ══════════════════════════════
// MODAL CRÉATION RAPIDE TECHNICIEN
// ══════════════════════════════
function openQuickTechModal() {
    ['qt-name', 'qt-phone', 'qt-whatsapp', 'qt-email'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('qt-error').style.display = 'none';
    document.getElementById('qt-success').style.display = 'none';
    const btn = document.getElementById('qt-submit');
    btn.disabled = false;
    btn.textContent = '✓ Créer le technicien';
    btn.onclick = submitQuickTech;
    document.getElementById('quick-tech-modal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => document.getElementById('qt-name').focus(), 50);
}

function closeQuickTechModal() {
    document.getElementById('quick-tech-modal').style.display = 'none';
    document.body.style.overflow = '';
}

async function submitQuickTech() {
    const name = document.getElementById('qt-name').value.trim();
    if (!name) {
        showQtError('Le nom est obligatoire.');
        return;
    }
    const btn = document.getElementById('qt-submit');
    btn.disabled = true;
    btn.textContent = 'Création…';
    document.getElementById('qt-error').style.display = 'none';

    const fd = new URLSearchParams({
        _token:          '{{ csrf_token() }}',
        name:            name,
        phone:           document.getElementById('qt-phone').value,
        whatsapp_number: document.getElementById('qt-whatsapp').value,
        email:           document.getElementById('qt-email').value,
    });

    try {
        const r = await fetch(`{{ route('admin.maintenances.quick-tech') }}`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: fd,
        });

        if (r.status === 422) {
            const data = await r.json().catch(() => ({}));
            const first = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Données invalides.');
            showQtError(first);
            return;
        }

        const data = await r.json();
        if (!r.ok || !data.ok) {
            showQtError(data.message || 'Erreur lors de la création.');
            return;
        }

        // Ajoute l'option dans le select + la sélectionne automatiquement
        const sel = document.getElementById('technicien_select');
        const opt = new Option(
            data.technician.name + (data.technician.whatsapp ? ' · ' + data.technician.whatsapp : ''),
            data.technician.id,
            true, true
        );
        sel.appendChild(opt);
        sel.value = data.technician.id;

        // Affiche le password temporaire (1 fois) pour transmission
        const okBox = document.getElementById('qt-success');
        okBox.innerHTML = `
            ✅ ${escapeHtml(data.message)}
            <div style="margin-top:8px;padding:8px 10px;background:#fff;border:1px dashed #22c55e;border-radius:6px;">
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;">Mot de passe temporaire</div>
                <div style="font-family:monospace;font-weight:700;font-size:14px;color:#16a34a;margin-top:2px;letter-spacing:.5px;user-select:all;">${escapeHtml(data.plain_password)}</div>
                <div style="font-size:10px;color:var(--text3);margin-top:4px;">⚠️ Notez-le maintenant — il ne sera plus affiché.</div>
            </div>`;
        okBox.style.display = 'block';

        btn.textContent = 'Fermer';
        btn.disabled = false;
        btn.onclick = closeQuickTechModal;
    } catch (e) {
        console.error(e);
        showQtError('Erreur réseau. Réessayez.');
    }
}

function showQtError(msg) {
    const box = document.getElementById('qt-error');
    box.textContent = msg;
    box.style.display = 'block';
    const btn = document.getElementById('qt-submit');
    btn.disabled = false;
    btn.textContent = '✓ Créer le technicien';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('quick-tech-modal').style.display === 'flex') {
        closeQuickTechModal();
    }
});
</script>

</x-admin-layout>

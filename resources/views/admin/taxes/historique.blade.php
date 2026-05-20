<x-admin-layout>
<x-slot name="title">Taxes Communes</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.rapports.taxes') }}" class="btn btn-ghost btn-sm" title="Calcul automatique des taxes dues mois/commune/trimestre/année">
        📊 Rapport auto
    </a>
    <button type="button" class="btn btn-ghost btn-sm" onclick="openAutoTaxModal()" title="Générer les écritures de taxes pour suivi des paiements">
        🪄 Auto-générer
    </button>
    <a href="{{ route('admin.taxes.export.pdf') }}" class="btn btn-ghost btn-sm">📄 Export PDF</a>
    <a href="{{ route('admin.taxes.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle taxe</a>
</x-slot>

{{-- STATS CLIQUABLES (filtres AJAX) --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
    <a href="#" data-status="en_attente"
       class="kpi-card filter-stat {{ request('status') === 'en_attente' ? 'is-active' : '' }}"
       style="--kpi-color:#f97316"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#f97316';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#f97316"></div>
        <div class="kpi-card__icon" style="color:#f97316"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
        <div class="kpi-card__value" style="color:#f97316">{{ $totalEnAttente }}</div>
        <div class="kpi-card__label">En attente</div>
        <div class="kpi-card__sub">à régler</div>
        <div class="kpi-card__arrow" style="color:#f97316">→</div>
    </a>
    <a href="#" data-status="payee"
       class="kpi-card filter-stat {{ request('status') === 'payee' ? 'is-active' : '' }}"
       style="--kpi-color:#22c55e"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#22c55e';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="kpi-card__value" style="color:#22c55e">{{ $totalPayees }}</div>
        <div class="kpi-card__label">Payées</div>
        <div class="kpi-card__sub">taxes réglées</div>
        <div class="kpi-card__arrow" style="color:#22c55e">→</div>
    </a>
    <a href="#" data-status="en_retard"
       class="kpi-card filter-stat {{ request('status') === 'en_retard' ? 'is-active' : '' }}"
       style="--kpi-color:#ef4444"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#ef4444';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#ef4444"></div>
        <div class="kpi-card__icon" style="color:#ef4444"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></div>
        <div class="kpi-card__value" style="color:#ef4444">{{ $totalEnRetard }}</div>
        <div class="kpi-card__label">En retard</div>
        <div class="kpi-card__sub">échéance dépassée</div>
        <div class="kpi-card__arrow" style="color:#ef4444">→</div>
    </a>
    <a href="#" data-status=""
       class="kpi-card filter-stat"
       style="--kpi-color:var(--accent)"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="kpi-card__value" style="color:var(--accent);font-size:18px">{{ number_format($montantTotal, 0, ',', ' ') }}</div>
        <div class="kpi-card__label">Montant dû</div>
        <div class="kpi-card__sub">FCFA cumul</div>
        <div class="kpi-card__arrow" style="color:var(--accent)">→</div>
    </a>
</div>

{{-- FILTRES AJAX DYNAMIQUES --}}
<div class="card" style="margin-bottom:16px;">
    <div class="filter-bar" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;padding:16px;">
        <div class="filter-group">
            <label class="filter-label">Commune</label>
            <select id="filter-commune" class="filter-select" style="width:180px;">
                <option value="">Toutes</option>
                @foreach($communes as $commune)
                <option value="{{ $commune->id }}" {{ request('commune_id') == $commune->id ? 'selected' : '' }}>{{ $commune->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Type</label>
            <select id="filter-type" class="filter-select" style="width:120px;">
                <option value="">Tous</option>
                <option value="odp" {{ request('type') === 'odp' ? 'selected' : '' }}>ODP</option>
                <option value="tm"  {{ request('type') === 'tm'  ? 'selected' : '' }}>TM</option>
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Année</label>
            <select id="filter-year" class="filter-select" style="width:100px;">
                <option value="">Toutes</option>
                @for($y = date('Y'); $y >= 2020; $y--)
                <option value="{{ $y }}" {{ request('year') == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Statut</label>
            <select id="filter-status" class="filter-select" style="width:130px;">
                <option value="">Tous</option>
                <option value="en_attente" {{ request('status') === 'en_attente' ? 'selected' : '' }}>En attente</option>
                <option value="payee"      {{ request('status') === 'payee'      ? 'selected' : '' }}>Payée</option>
                <option value="en_retard"  {{ request('status') === 'en_retard'  ? 'selected' : '' }}>En retard</option>
            </select>
        </div>
        
        <div class="filter-group" id="reset-wrapper" style="display:none;">
            <label class="filter-label" style="visibility:hidden;">Actions</label>
            <button id="btn-reset" class="reset-btn" style="display:flex;align-items:center;gap:4px;">
                ↺ Réinitialiser
            </button>
        </div>

        <div class="filter-group" style="margin-left:auto;">
            <label class="filter-label" style="visibility:hidden;">&nbsp;</label>
            <div class="result-badge">
                <strong id="result-count">{{ number_format($taxes->total()) }}</strong> taxe(s)
            </div>
        </div>
    </div>
</div>

{{-- TABLEAU --}}
<div id="table-container" class="card">
    <div class="card-header">
        <div class="card-title">🏛️ Taxes <span id="title-count">({{ $taxes->total() }})</span></div>
    </div>
    <div class="table-wrap">
        <table id="taxes-table">
            <thead>
                <tr>
                    <th>Commune</th>
                    <th>Type</th>
                    <th>Année</th>
                    <th>Montant</th>
                    <th>Échéance</th>
                    <th>Payée le</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="table-body">
                @include('admin.taxes.partials.table-rows', ['taxes' => $taxes])
            </tbody>
        </table>
    </div>
    <div id="pagination-container" style="padding:16px;">
        {{ $taxes->links() }}
    </div>
</div>

<style>

.reset-btn {
    height: 40px;
    padding: 0 20px;
    background: var(--surface2);
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text-muted);
    font-size: 12px;
    cursor: pointer;
}
.reset-btn:hover { background: var(--surface3); border-color: var(--danger); color: var(--danger); }
.filter-select, .filter-input { height:38px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:12px;color:var(--text);outline:none; }
.filter-select:focus, .filter-input:focus { border-color:var(--accent); }
.filter-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px; }
.filter-group { display:flex;flex-direction:column; }
.result-badge { height:38px;display:flex;align-items:center;font-size:12px;color:var(--text3);white-space:nowrap; }
.spinner { display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Modal auto-génération */
.tax-auto-overlay { display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9000;align-items:center;justify-content:center;padding:16px; }
.tax-auto-overlay.open { display:flex; }
.tax-auto-modal { background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:760px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden; }
.tax-auto-head { padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between; }
.tax-auto-head h3 { font-size:15px;font-weight:700; }
.tax-auto-body { padding:18px 22px;overflow-y:auto;flex:1; }
.tax-auto-foot { padding:14px 22px;border-top:1px solid var(--border);display:flex;gap:10px;justify-content:flex-end;background:var(--surface2); }
.tax-auto-table { width:100%;border-collapse:collapse;font-size:12px;margin-top:10px; }
.tax-auto-table th { text-align:left;padding:7px 10px;background:var(--surface2);font-size:10px;font-weight:700;color:var(--text3);text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid var(--border); }
.tax-auto-table td { padding:7px 10px;border-bottom:1px solid var(--border); }
.tax-auto-table tr.exists td { color:var(--text3);background:var(--surface2); }
.tax-auto-pill { display:inline-block;padding:1px 7px;border-radius:10px;font-size:10px;font-weight:600; }
.tax-auto-summary { display:flex;gap:14px;flex-wrap:wrap;margin-top:8px;font-size:13px; }
.tax-auto-summary > div { padding:8px 12px;background:var(--surface2);border-radius:8px;border:1px solid var(--border); }
.tax-auto-summary strong { color:var(--accent); }
</style>

{{-- ─── MODAL AUTO-GÉNÉRATION ─── --}}
<div class="tax-auto-overlay" id="tax-auto-overlay" onclick="if(event.target===this)closeAutoTaxModal()">
    <div class="tax-auto-modal" onclick="event.stopPropagation()">
        <div class="tax-auto-head">
            <h3>🪄 Auto-générer les taxes annuelles</h3>
            <button type="button" onclick="closeAutoTaxModal()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text3);">✕</button>
        </div>
        <div class="tax-auto-body">
            <p style="font-size:12.5px;color:var(--text2);line-height:1.6;margin-bottom:12px;">
                Calcule les taxes ODP et TM pour l'année choisie, à partir du parc interne installé
                sur chaque commune × les tarifs saisis sur la commune (<code>odp_rate</code> et
                <code>tm_rate</code>). Les lignes déjà existantes sont conservées telles quelles.
            </p>
            <div style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
                <div>
                    <label class="filter-label">Année</label>
                    <select id="auto-year" class="filter-select" style="width:130px;">
                        @for($y = date('Y') + 1; $y >= 2020; $y--)
                            <option value="{{ $y }}" @if($y === (int) date('Y')) selected @endif>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <button type="button" id="btn-preview-auto" class="btn btn-ghost btn-sm">Aperçu</button>
            </div>

            <div id="auto-preview-zone" style="margin-top:14px;display:none;"></div>
        </div>
        <div class="tax-auto-foot">
            <button type="button" onclick="closeAutoTaxModal()" class="btn btn-ghost btn-sm">Fermer</button>
            <button type="button" id="btn-confirm-auto" class="btn btn-primary btn-sm" disabled>
                Générer les taxes manquantes
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// FILTRAGE AJAX DYNAMIQUE
// ════════════════════════════════════════════════════════════
(function() {
    let currentFilters = {
        commune_id: '',
        type: '',
        year: '',
        status: ''
    };
    let debounceTimer = null;
    let isUpdating = false;

    const elements = {
        commune: document.getElementById('filter-commune'),
        type: document.getElementById('filter-type'),
        year: document.getElementById('filter-year'),
        status: document.getElementById('filter-status'),
        resetBtn: document.getElementById('btn-reset'),
        resetWrapper: document.getElementById('reset-wrapper'),
        resultCount: document.getElementById('result-count'),
        titleCount: document.getElementById('title-count'),
        tableBody: document.getElementById('table-body'),
        paginationContainer: document.getElementById('pagination-container')
    };

    function updateResetButton() {
        const hasFilters = currentFilters.commune_id ||
                          currentFilters.type ||
                          currentFilters.year ||
                          currentFilters.status;
        if (elements.resetWrapper) {
            elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function applyFilters() {
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
        if (currentFilters.type) params.set('type', currentFilters.type);
        if (currentFilters.year) params.set('year', currentFilters.year);
        if (currentFilters.status) params.set('status', currentFilters.status);
        params.set('ajax', '1');

        if (elements.tableBody) {
            elements.tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:60px;"><div class="spinner"></div> Chargement...</td></tr>';
        }

        try {
            const response = await fetch(`{{ route("admin.taxes.index") }}?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await response.json();

            if (data.html && elements.tableBody) {
                elements.tableBody.innerHTML = data.html;
            }
            
            if (elements.resultCount && data.total) {
                elements.resultCount.textContent = data.total;
            }
            if (elements.titleCount && data.total) {
                elements.titleCount.textContent = `(${data.total})`;
            }
            
            if (elements.paginationContainer && data.pagination) {
                elements.paginationContainer.innerHTML = data.pagination;
            }

            const url = new URL(window.location.href);
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key]) url.searchParams.set(key, currentFilters[key]);
                else url.searchParams.delete(key);
            });
            window.history.pushState({}, '', url);

        } catch (error) {
            console.error('Erreur:', error);
            if (elements.tableBody) {
                elements.tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:60px;color:#ef4444;">Erreur de chargement</td></tr>';
            }
        } finally {
            isUpdating = false;
        }
    }

    // Écouteurs d'événements
    const selectElements = [elements.commune, elements.type, elements.year, elements.status];
    selectElements.forEach(el => {
        if (el) {
            el.addEventListener('change', () => {
                currentFilters.commune_id = elements.commune?.value || '';
                currentFilters.type = elements.type?.value || '';
                currentFilters.year = elements.year?.value || '';
                currentFilters.status = elements.status?.value || '';
                updateResetButton();
                applyFilters();
                
                // Mettre à jour l'apparence des cartes stats
                document.querySelectorAll('.kpi-card.filter-stat').forEach(card => {
                    const status = card.dataset.status;
                    if (status === currentFilters.status) {
                        card.classList.add('is-active');
                    } else {
                        card.classList.remove('is-active');
                    }
                });
            });
        }
    });

    // Cartes stats
    document.querySelectorAll('.kpi-card.filter-stat').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const status = card.dataset.status;
            if (elements.status) {
                elements.status.value = status;
                currentFilters.status = status;
                updateResetButton();
                applyFilters();

                document.querySelectorAll('.kpi-card.filter-stat').forEach(c => {
                    if (c.dataset.status === status) {
                        c.classList.add('is-active');
                    } else {
                        c.classList.remove('is-active');
                    }
                });
            }
        });
    });

    // Reset button
    if (elements.resetBtn) {
        elements.resetBtn.addEventListener('click', () => {
            currentFilters = { commune_id: '', type: '', year: '', status: '' };
            if (elements.commune) elements.commune.value = '';
            if (elements.type) elements.type.value = '';
            if (elements.year) elements.year.value = '';
            if (elements.status) elements.status.value = '';

            document.querySelectorAll('.kpi-card.filter-stat').forEach(card => card.classList.remove('is-active'));
            
            updateResetButton();
            applyFilters();
        });
    }

    // Initialiser les valeurs depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('commune_id')) currentFilters.commune_id = urlParams.get('commune_id');
    if (urlParams.has('type')) currentFilters.type = urlParams.get('type');
    if (urlParams.has('year')) currentFilters.year = urlParams.get('year');
    if (urlParams.has('status')) currentFilters.status = urlParams.get('status');
    
    if (elements.commune && currentFilters.commune_id) elements.commune.value = currentFilters.commune_id;
    if (elements.type && currentFilters.type) elements.type.value = currentFilters.type;
    if (elements.year && currentFilters.year) elements.year.value = currentFilters.year;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;

    updateResetButton();
})();

// ════════════════════════════════════════════════════════════
// AUTO-GÉNÉRATION DES TAXES ANNUELLES
// ════════════════════════════════════════════════════════════
function openAutoTaxModal() {
    document.getElementById('tax-auto-overlay').classList.add('open');
    document.getElementById('auto-preview-zone').style.display = 'none';
    document.getElementById('auto-preview-zone').innerHTML = '';
    document.getElementById('btn-confirm-auto').disabled = true;
}
function closeAutoTaxModal() {
    document.getElementById('tax-auto-overlay').classList.remove('open');
}

(function () {
    const previewBtn = document.getElementById('btn-preview-auto');
    const confirmBtn = document.getElementById('btn-confirm-auto');
    const yearSel    = document.getElementById('auto-year');
    const zone       = document.getElementById('auto-preview-zone');

    if (!previewBtn || !confirmBtn) return;

    const fmt = n => new Intl.NumberFormat('fr-FR').format(Math.round(n));

    function pillStatus(row) {
        if (!row.exists) return '<span class="tax-auto-pill" style="background:rgba(34,197,94,.12);color:#16a34a;">➕ à créer</span>';
        const s = row.existing_status || '—';
        const colors = {
            payee:      'background:rgba(34,197,94,.12);color:#16a34a;',
            en_attente: 'background:rgba(232,160,32,.12);color:#c2570d;',
            en_retard:  'background:rgba(239,68,68,.12);color:#b91c1c;',
        };
        return `<span class="tax-auto-pill" style="${colors[s] || ''}">✓ ${s}</span>`;
    }

    async function loadPreview() {
        const year = yearSel.value;
        zone.style.display = 'block';
        zone.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text3);"><div class="spinner"></div> Chargement…</div>';
        confirmBtn.disabled = true;

        try {
            const r = await fetch(`{{ route('admin.taxes.auto.preview') }}?year=${encodeURIComponent(year)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (!r.ok) {
                zone.innerHTML = `<div style="color:#ef4444;font-size:13px;">${data.error || 'Erreur.'}</div>`;
                return;
            }

            if (!data.rows.length) {
                zone.innerHTML = `<div style="text-align:center;padding:24px;color:var(--text3);font-size:13px;">
                    Aucune commune éligible pour ${data.year} (parc vide ou tarifs non saisis).
                </div>`;
                return;
            }

            const rowsHtml = data.rows.map(row => `
                <tr class="${row.exists ? 'exists' : ''}">
                    <td><strong>${row.commune}</strong></td>
                    <td><span class="tax-auto-pill" style="background:rgba(59,130,246,.1);color:#2563eb;">${row.type.toUpperCase()}</span></td>
                    <td style="text-align:right;">${row.panels}</td>
                    <td style="text-align:right;">${fmt(row.rate)}</td>
                    <td style="text-align:right;font-weight:600;">${fmt(row.amount)} FCFA</td>
                    <td>${pillStatus(row)}</td>
                </tr>
            `).join('');

            zone.innerHTML = `
                <div class="tax-auto-summary">
                    <div>À créer : <strong>${data.summary.to_create}</strong></div>
                    <div>Déjà existantes : <strong>${data.summary.exists}</strong></div>
                    <div>Montant total à créer : <strong>${fmt(data.summary.total_amount_to_create)} FCFA</strong></div>
                </div>
                <table class="tax-auto-table">
                    <thead>
                        <tr>
                            <th>Commune</th>
                            <th>Type</th>
                            <th style="text-align:right;">Panneaux</th>
                            <th style="text-align:right;">Tarif/an</th>
                            <th style="text-align:right;">Montant</th>
                            <th>État</th>
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            `;

            confirmBtn.disabled = data.summary.to_create === 0;
            confirmBtn.dataset.year = data.year;
            confirmBtn.textContent = data.summary.to_create > 0
                ? `Créer ${data.summary.to_create} taxe(s)`
                : 'Aucune taxe à créer';
        } catch (e) {
            console.error(e);
            zone.innerHTML = `<div style="color:#ef4444;">Erreur réseau.</div>`;
        }
    }

    async function applyGeneration() {
        const year = confirmBtn.dataset.year || yearSel.value;
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Création…';

        try {
            const r = await fetch(`{{ route('admin.taxes.auto.generate') }}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: new URLSearchParams({
                    year,
                    _token: document.querySelector('meta[name="csrf-token"]')?.content || '',
                }),
            });
            const data = await r.json();
            if (!r.ok || !data.success) {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Générer les taxes manquantes';
                alert(data.message || 'Erreur lors de la génération.');
                return;
            }
            // Recharge la page pour voir les nouvelles lignes dans le tableau
            window.location.href = `{{ route('admin.taxes.index') }}?year=${encodeURIComponent(year)}`;
        } catch (e) {
            console.error(e);
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Générer les taxes manquantes';
            alert('Erreur réseau.');
        }
    }

    previewBtn.addEventListener('click', loadPreview);
    confirmBtn.addEventListener('click', applyGeneration);
    yearSel.addEventListener('change', () => {
        // Reset preview quand l'année change pour forcer un nouveau "Aperçu"
        zone.style.display = 'none';
        zone.innerHTML = '';
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Générer les taxes manquantes';
    });
})();
</script>
@endpush
</x-admin-layout>
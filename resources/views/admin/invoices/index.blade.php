<x-admin-layout>
<x-slot name="title">Facturation</x-slot>

<x-slot name="topbarActions">
    {{-- Les exports reprennent les filtres en cours pour ne pas avoir à
         refiltrer côté Excel/PDF — utile pour transmettre un sous-ensemble
         (1 client, 1 mois, 1 statut) au comptable. --}}
    <a href="{{ route('admin.invoices.export.pdf', request()->only(['client_id','status','date_from','date_to'])) }}"
       class="btn btn-ghost btn-sm" title="Export PDF du listing filtré">
        📄 PDF
    </a>
    <a href="{{ route('admin.invoices.export.excel', request()->only(['client_id','status','date_from','date_to'])) }}"
       class="btn btn-ghost btn-sm" title="Export Excel (.xlsx) du listing filtré">
        📊 Excel
    </a>
    <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle facture</a>
</x-slot>

{{-- STATS CLIQUABLES (filtres AJAX) --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px">
    <a href="#" data-status="brouillon"
       class="kpi-card filter-stat {{ request('status') === 'brouillon' ? 'is-active' : '' }}"
       style="--kpi-color:#6b7280"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#6b7280';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#6b7280"></div>
        <div class="kpi-card__icon" style="color:#6b7280"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/></svg></div>
        <div class="kpi-card__value" data-kpi="brouillon" data-kpi-value="brouillon" style="color:#6b7280">{{ $totalBrouillons }}</div>
        <div class="kpi-card__label">Brouillons</div>
        <div class="kpi-card__sub">à finaliser</div>
        <div class="kpi-card__arrow" style="color:#6b7280">→</div>
    </a>
    <a href="#" data-status="envoyee"
       class="kpi-card filter-stat {{ request('status') === 'envoyee' ? 'is-active' : '' }}"
       style="--kpi-color:#3b82f6"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#3b82f6';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#3b82f6"></div>
        <div class="kpi-card__icon" style="color:#3b82f6"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div>
        <div class="kpi-card__value" data-kpi="envoyee" data-kpi-value="envoyee" style="color:#3b82f6">{{ $totalEnvoyees }}</div>
        <div class="kpi-card__label">Envoyées</div>
        <div class="kpi-card__sub">en attente paiement</div>
        <div class="kpi-card__arrow" style="color:#3b82f6">→</div>
    </a>
    <a href="#" data-status="payee"
       class="kpi-card filter-stat {{ request('status') === 'payee' ? 'is-active' : '' }}"
       style="--kpi-color:#22c55e"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='#22c55e';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:#22c55e"></div>
        <div class="kpi-card__icon" style="color:#22c55e"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
        <div class="kpi-card__value" data-kpi="payee" data-kpi-value="payee" style="color:#22c55e">{{ $totalPayees }}</div>
        <div class="kpi-card__label">Payées</div>
        <div class="kpi-card__sub">factures encaissées</div>
        <div class="kpi-card__arrow" style="color:#22c55e">→</div>
    </a>
    <a href="#" data-status=""
       class="kpi-card filter-stat {{ !request('status') ? 'is-active' : '' }}"
       style="--kpi-color:var(--accent)"
       onclick="event.preventDefault()"
       onmouseenter="this.style.borderColor='var(--accent)';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 16px rgba(0,0,0,.12)'"
       onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
        <div class="kpi-card__top-bar" style="background:var(--accent)"></div>
        <div class="kpi-card__icon" style="color:var(--accent)"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
        <div class="kpi-card__value" data-kpi="ca" data-kpi-value="ca" style="color:var(--accent);font-size:18px">{{ number_format($montantTotal, 0, ',', ' ') }}</div>
        <div class="kpi-card__label">CA Encaissé</div>
        <div class="kpi-card__sub">FCFA total</div>
        <div class="kpi-card__arrow" style="color:var(--accent)">→</div>
    </a>
</div>

{{-- FILTRES AJAX DYNAMIQUES --}}
<div class="card" style="margin-bottom:16px;">
    <div class="filter-bar" style="display:flex;gap:16px;flex-wrap:wrap;align-items:flex-end;padding:16px;">
        <div class="filter-group">
            <label class="filter-label">Client</label>
            <select id="filter-client" class="filter-select" style="width:200px;">
                <option value="">Tous</option>
                @foreach($clients as $client)
                <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <label class="filter-label">Statut</label>
            <select id="filter-status" class="filter-select" style="width:130px;">
                <option value="">Tous</option>
                <option value="brouillon" {{ request('status') === 'brouillon' ? 'selected' : '' }}>Brouillon</option>
                <option value="envoyee"   {{ request('status') === 'envoyee'   ? 'selected' : '' }}>Envoyée</option>
                <option value="payee"     {{ request('status') === 'payee'     ? 'selected' : '' }}>Payée</option>
                <option value="annulee"   {{ request('status') === 'annulee'   ? 'selected' : '' }}>Annulée</option>
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
                <strong id="result-count">{{ number_format($invoices->total()) }}</strong> facture(s)
            </div>
        </div>
    </div>
</div>

{{-- TABLEAU --}}
<div id="table-container" class="card">
    <div class="card-header">
        <div class="card-title">💰 Factures <span id="title-count">({{ $invoices->total() }})</span></div>
    </div>
    <div class="table-wrap">
        <table id="invoices-table">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Client</th>
                    <th>Campagne</th>
                    <th>Montant HT</th>
                    <th>TVA</th>
                    <th>Montant TTC</th>
                    <th>Date</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="table-body">
                @include('admin.invoices.partials.table-rows', ['invoices' => $invoices])
            </tbody>
        </table>
    </div>
    <div id="pagination-container" style="padding:16px;">
        {{ $invoices->links() }}
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
.btn-blue { background:rgba(59,130,246,.1);border:1px solid rgba(59,130,246,.3);color:#3b82f6; }
.btn-blue:hover { background:rgba(59,130,246,.2); }
</style>

@push('scripts')
<script>
// ════════════════════════════════════════════════════════════
// FILTRAGE AJAX DYNAMIQUE
// ════════════════════════════════════════════════════════════
(function() {
    let currentFilters = {
        client_id: '',
        status: ''
    };
    let debounceTimer = null;
    let isUpdating = false;

    const elements = {
        client: document.getElementById('filter-client'),
        status: document.getElementById('filter-status'),
        resetBtn: document.getElementById('btn-reset'),
        resetWrapper: document.getElementById('reset-wrapper'),
        resultCount: document.getElementById('result-count'),
        titleCount: document.getElementById('title-count'),
        tableBody: document.getElementById('table-body'),
        paginationContainer: document.getElementById('pagination-container')
    };

    function updateResetButton() {
        const hasFilters = currentFilters.client_id || currentFilters.status;
        if (elements.resetWrapper) {
            elements.resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function applyFilters() {
        if (isUpdating) return;
        isUpdating = true;

        const params = new URLSearchParams();
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        params.set('ajax', '1');

        if (elements.tableBody) {
            elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:60px;"><div class="spinner"></div> Chargement...</td></tr>';
        }

        try {
            const response = await fetch(`{{ route("admin.invoices.index") }}?${params}`, {
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
                elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:60px;color:#ef4444;">Erreur de chargement</td></tr>';
            }
        } finally {
            isUpdating = false;
        }
    }

    // Écouteurs d'événements
    if (elements.client) {
        elements.client.addEventListener('change', () => {
            currentFilters.client_id = elements.client.value;
            updateResetButton();
            applyFilters();
        });
    }

    if (elements.status) {
        elements.status.addEventListener('change', () => {
            currentFilters.status = elements.status.value;
            updateResetButton();
            applyFilters();
            
            // Mettre à jour l'apparence des cartes stats
            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
                const status = card.dataset.status;
                if (status === currentFilters.status) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
                }
            });
        });
    }

    // Cartes stats
    document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const status = card.dataset.status;
            if (elements.status) {
                elements.status.value = status;
                currentFilters.status = status;
                updateResetButton();
                applyFilters();

                document.querySelectorAll('.kpi-card[data-kpi]').forEach(c => {
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
            currentFilters = { client_id: '', status: '' };
            if (elements.client) elements.client.value = '';
            if (elements.status) elements.status.value = '';

            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => card.classList.remove('is-active'));
            
            updateResetButton();
            applyFilters();
        });
    }

    // Initialiser les valeurs depuis l'URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('client_id')) currentFilters.client_id = urlParams.get('client_id');
    if (urlParams.has('status')) currentFilters.status = urlParams.get('status');

    if (elements.client && currentFilters.client_id) elements.client.value = currentFilters.client_id;
    if (elements.status && currentFilters.status) elements.status.value = currentFilters.status;

    updateResetButton();
})();

// ════════════════════════════════════════════════════════════
// ACTIONS INLINE (send / pay / cancel / revert) — AJAX
// ════════════════════════════════════════════════════════════
(function () {
    const tableBody = document.getElementById('table-body');
    if (!tableBody) return;

    function showToast(message, type = 'success') {
        let host = document.getElementById('invoice-toast-host');
        if (!host) {
            host = document.createElement('div');
            host.id = 'invoice-toast-host';
            host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(host);
        }
        const colors = type === 'error'
            ? { bg: '#fee2e2', fg: '#991b1b', bd: '#fca5a5' }
            : { bg: '#dcfce7', fg: '#166534', bd: '#86efac' };
        const t = document.createElement('div');
        t.textContent = message;
        t.style.cssText = `padding:10px 14px;background:${colors.bg};color:${colors.fg};border:1px solid ${colors.bd};border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 4px 12px rgba(0,0,0,.08);min-width:240px;max-width:380px;`;
        host.appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 2700);
        setTimeout(() => t.remove(), 3100);
    }

    function updateKPIs(counts) {
        if (!counts) return;
        const map = { brouillon: counts.brouillon, envoyee: counts.envoyee, payee: counts.payee };
        for (const [k, v] of Object.entries(map)) {
            const el = document.querySelector(`[data-kpi="${k}"]`);
            if (el && v !== undefined) el.textContent = v;
        }
        const ca = document.querySelector('[data-kpi="ca"]');
        if (ca && counts.ca !== undefined) {
            ca.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(counts.ca));
        }
    }

    tableBody.addEventListener('submit', async (event) => {
        const form = event.target.closest('form.invoice-action');
        if (!form) return;
        event.preventDefault();

        const confirmText = form.dataset.confirm;
        if (confirmText && !window.confirm(confirmText)) return;

        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.dataset.prev = button.innerHTML; button.innerHTML = '⏳'; }

        try {
            const fd = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: fd,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                showToast(data.message || 'Action impossible.', 'error');
                if (button) { button.disabled = false; button.innerHTML = button.dataset.prev || '↺'; }
                return;
            }

            // Remplacer la ligne par la version fraîche renvoyée par le serveur
            const row = form.closest('tr[data-invoice-row]');
            if (row && data.row_html) {
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = data.row_html.trim();
                const newRow = wrapper.firstElementChild;
                if (newRow) row.replaceWith(newRow);
            }

            updateKPIs(data.counts);
            showToast(data.message || 'Action effectuée.');
        } catch (err) {
            console.error(err);
            showToast('Erreur réseau. Réessayez.', 'error');
            if (button) { button.disabled = false; button.innerHTML = button.dataset.prev || '↺'; }
        }
    });
})();
</script>
@endpush
</x-admin-layout>
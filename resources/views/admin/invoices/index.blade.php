<x-admin-layout>
<x-slot name="title">Facturation</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.invoices.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle facture</a>
</x-slot>

{{-- STATS CLIQUABLES (filtres AJAX) --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div data-status="brouillon" class="stat-card filter-stat {{ request('status') === 'brouillon' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">Brouillons</div>
        <div class="stat-value" style="color:var(--text3);">{{ $totalBrouillons }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="envoyee" class="stat-card filter-stat {{ request('status') === 'envoyee' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">Envoyées</div>
        <div class="stat-value" style="color:var(--blue);">{{ $totalEnvoyees }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="payee" class="stat-card filter-stat {{ request('status') === 'payee' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">Payées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalPayees }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="" class="stat-card filter-stat {{ !request('status') ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">CA Encaissé</div>
        <div class="stat-value" style="font-size:16px; color:var(--accent);">{{ number_format($montantTotal, 0, ',', ' ') }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Voir tout →</div>
    </div>
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
.stat-card { cursor:pointer; transition:all .15s; border:2px solid transparent; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card.active { border-color:var(--accent) !important; }
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
            document.querySelectorAll('.stat-card').forEach(card => {
                const status = card.dataset.status;
                if (status === currentFilters.status) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
        });
    }

    // Cartes stats
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            const status = card.dataset.status;
            if (elements.status) {
                elements.status.value = status;
                currentFilters.status = status;
                updateResetButton();
                applyFilters();
                
                document.querySelectorAll('.stat-card').forEach(c => {
                    if (c.dataset.status === status) {
                        c.classList.add('active');
                    } else {
                        c.classList.remove('active');
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
            
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
            
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
</script>
@endpush
</x-admin-layout>
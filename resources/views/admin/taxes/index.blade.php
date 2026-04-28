<x-admin-layout>
<x-slot name="title">Taxes Communes</x-slot>

<x-slot name="topbarActions">
    <a href="{{ route('admin.taxes.export.pdf') }}" class="btn btn-ghost btn-sm">📄 Export PDF</a>
    <a href="{{ route('admin.taxes.create') }}" class="btn btn-primary btn-sm">＋ Nouvelle taxe</a>
</x-slot>

{{-- STATS CLIQUABLES (filtres AJAX) --}}
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div data-status="en_attente" class="stat-card filter-stat {{ request('status') === 'en_attente' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">En attente</div>
        <div class="stat-value" style="color:var(--accent);">{{ $totalEnAttente }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="payee" class="stat-card filter-stat {{ request('status') === 'payee' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">Payées</div>
        <div class="stat-value" style="color:var(--green);">{{ $totalPayees }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="en_retard" class="stat-card filter-stat {{ request('status') === 'en_retard' ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">En retard</div>
        <div class="stat-value" style="color:var(--red);">{{ $totalEnRetard }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Filtrer →</div>
    </div>
    <div data-status="" class="stat-card filter-stat {{ !request('status') ? 'active' : '' }}"
         style="text-decoration:none;cursor:pointer;transition:all .15s;">
        <div class="stat-label">Montant dû</div>
        <div class="stat-value" style="font-size:18px; color:var(--accent);">{{ number_format($montantTotal, 0, ',', ' ') }}</div>
        <div style="font-size:11px;color:var(--text3);margin-top:4px;">Voir tout →</div>
    </div>
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
.stat-card { cursor:pointer; transition:all .15s; border:2px solid transparent; }
.stat-card:hover { transform:translateY(-2px); }
.stat-card.active { border-color:var(--accent) !important; }
.spinner { display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>

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
    });

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
            currentFilters = { commune_id: '', type: '', year: '', status: '' };
            if (elements.commune) elements.commune.value = '';
            if (elements.type) elements.type.value = '';
            if (elements.year) elements.year.value = '';
            if (elements.status) elements.status.value = '';
            
            document.querySelectorAll('.stat-card').forEach(card => card.classList.remove('active'));
            
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
</script>
@endpush
</x-admin-layout>
<x-admin-layout title="Clients">
    <x-slot:topbarActions>
        <a href="{{ route('admin.clients.create') }}" class="btn btn-primary transition-all duration-200 hover:scale-105">
            + Nouveau client
        </a>
    </x-slot:topbarActions>

    {{-- ══ STATS OPTIMISÉES ══ --}}
    <div class="stats-grid">
        <div class="stat-card stat-total">
            <div class="stat-icon">👥</div>
            <div class="stat-number">{{ $stats['total'] }}</div>
            <div class="stat-label">TOTAL CLIENTS</div>
        </div>
        <div class="stat-card stat-active">
            <div class="stat-icon">📡</div>
            <div class="stat-number">{{ $stats['actifs'] }}</div>
            <div class="stat-label">AVEC CAMPAGNE ACTIVE</div>
        </div>
        <div class="stat-card stat-ca">
            <div class="stat-icon">💰</div>
            <div class="stat-number">{{ number_format($stats['ca_total'] ?? 0, 0, ',', ' ') }}</div>
            <div class="stat-label">CHIFFRE D'AFFAIRES</div>
        </div>
        <div class="stat-card stat-export">
            <div class="stat-actions">
                <button class="btn-export" onclick="window.Toast?.info('Export en développement')">
                    📊 Excel
                </button>
                <button class="btn-export" onclick="window.Toast?.info('Export en développement')">
                    📄 PDF
                </button>
            </div>
        </div>
    </div>

    {{-- ══ FILTRES DYNAMIQUES (sans bouton) ══ --}}
    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">🔍 Recherche</label>
                <input type="text" id="filter-search" class="filter-input" 
                       placeholder="Nom, NCC, email, contact, téléphone…"
                       value="{{ request('search') }}"
                       data-filter="search">
            </div>

            <div class="filter-group">
                <label class="filter-label">🏢 Secteur</label>
                <select id="filter-sector" class="filter-select" data-filter="sector">
                    <option value="">Tous les secteurs</option>
                    @foreach($sectors as $sector)
                    <option value="{{ $sector }}" {{ request('sector') === $sector ? 'selected' : '' }}>
                        {{ $sector }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📊 Trier par</label>
                <select id="filter-sort" class="filter-select" data-filter="sort">
                    <option value="name" {{ request('sort', 'name') === 'name' ? 'selected' : '' }}>Nom A-Z</option>
                    <option value="created_at" {{ request('sort') === 'created_at' ? 'selected' : '' }}>Plus récents</option>
                    <option value="campaigns_count" {{ request('sort') === 'campaigns_count' ? 'selected' : '' }}>Nb campagnes</option>
                </select>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">↺ Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- ══ TABLEAU CLIENTS ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Portefeuille clients</span>
            <div class="stats-info">
                <span id="total-count" class="total-count">{{ $clients->total() }} client(s)</span>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="clients-table">
                <thead>
                    <tr>
                        <th>Client / NCC</th>
                        <th>Secteur</th>
                        <th>Campagnes</th>
                        <th>Réservations</th>
                        <th>Contact</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.clients.partials.table-rows', ['clients' => $clients])
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div id="pagination-container" class="pagination-container">
            {{ $clients->links() }}
        </div>
    </div>

    {{-- ══ MODAL SUPPRESSION ══ --}}
    <div id="modal-delete-client" class="modal-overlay hidden" onclick="if(event.target===this) closeDeleteClient()">
        <div class="modal" onclick="event.stopPropagation()">
            <div class="modal-header">
                <div class="modal-title text-red-500">🗑 Supprimer le client</div>
                <button class="modal-close" onclick="closeDeleteClient()">✕</button>
            </div>
            <div class="modal-body text-center">
                <div class="text-5xl mb-3">👥</div>
                <div class="font-bold text-lg mb-2">
                    Supprimer <span id="del-client-name" class="text-accent"></span> ?
                </div>
                <div id="del-client-warning" class="hidden bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-sm text-red-500 mb-3">
                    ⚠️ Ce client a des campagnes actives. La suppression sera bloquée.
                </div>
                <div class="text-sm text-text3 mb-4">
                    Le client sera archivé (soft delete). Ses données historiques seront conservées.
                </div>
                <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-xs text-red-500">
                    ⚠️ Ses réservations passeront en lecture seule uniquement.
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeDeleteClient()">Annuler</button>
                <form id="del-client-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 16px 20px;
            border: 1px solid var(--border);
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .stat-icon { font-size: 28px; margin-bottom: 8px; }
        .stat-number { font-size: 28px; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 11px; color: var(--text3); font-weight: 600; letter-spacing: 0.4px; margin-top: 6px; }
        .stat-total .stat-number { color: var(--text); }
        .stat-active .stat-number { color: #22c55e; }
        .stat-ca .stat-number { color: var(--accent); font-size: 22px; }
        .stat-export { display: flex; align-items: center; justify-content: flex-end; }
        .stat-actions { display: flex; gap: 8px; }
        .btn-export {
            padding: 8px 16px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 12px;
            color: var(--text2);
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-export:hover { background: var(--surface3); border-color: var(--accent); color: var(--accent); }

        /* Filters */
        .filters-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); }
        .filter-input, .filter-select {
            height: 42px;
            padding: 0 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
            transition: all 0.2s;
        }
        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px var(--accent-dim);
        }
        .reset-btn {
            height: 42px;
            padding: 0 20px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: var(--text-muted);
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .reset-btn:hover { background: var(--surface3); border-color: var(--danger); color: var(--danger); }

        /* Card */
        .card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .card-header {
            padding: 14px 20px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .card-title { font-size: 16px; font-weight: 600; }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px 16px;
            background: var(--surface2);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
        }
        .data-table tr:hover td { background: var(--surface2); }

        /* Badges */
        .client-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--accent);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 16px;
            flex-shrink: 0;
        }
        .client-name { font-weight: 700; color: var(--text); text-decoration: none; font-size: 14px; }
        .client-ncc { font-family: monospace; background: var(--surface3); padding: 2px 6px; border-radius: 4px; font-size: 10px; }
        .sector-badge { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; background: var(--surface3); color: var(--text2); }
        .active-badge { font-size: 10px; color: #22c55e; font-weight: 600; margin-top: 2px; }
        .campaign-count { font-weight: 600; color: var(--text); font-size: 14px; }
        .reservation-count { color: var(--text2); font-size: 13px; }
        .contact-name { font-size: 13px; color: var(--text); }
        .contact-detail { font-size: 11px; color: var(--text3); }

        /* Actions */
        .actions { display: flex; gap: 6px; }
        .btn-icon {
            background: transparent;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-icon:hover { background: var(--surface3); transform: scale(1.05); }
        .btn-delete { color: var(--danger); }

        /* Pagination */
        .pagination-container {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
        }
        .pagination { display: flex; gap: 4px; list-style: none; margin: 0; padding: 0; }
        .pagination li a, .pagination li span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            height: 34px;
            padding: 0 10px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination li.active span { background: var(--accent); border-color: var(--accent); color: #000; }
        .pagination li a:hover { background: var(--surface3); border-color: var(--accent); }

        /* Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            max-width: 90%;
            width: 460px;
            overflow: hidden;
            animation: modalFade 0.2s ease;
        }
        @keyframes modalFade {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title { font-size: 18px; font-weight: 600; }
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text-muted);
        }
        .modal-body { padding: 24px; }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Buttons */
        .btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        .btn-primary { background: var(--accent); color: #000; }
        .btn-primary:hover { background: #f0b040; transform: translateY(-1px); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

        /* Utilitaires */
        .hidden { display: none; }
        .text-center { text-align: center; }
        .text-5xl { font-size: 48px; }
        .text-lg { font-size: 18px; }
        .text-sm { font-size: 13px; }
        .text-xs { font-size: 11px; }
        .font-bold { font-weight: 700; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .text-accent { color: var(--accent); }
        .text-red-500 { color: #ef4444; }

        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .filters-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 10px 12px; }
        }
    </style>

    @push('scripts')
    <script>
    // ══ MODALS ══
    function openDeleteClient(id, name, activeCampaigns) {
        document.getElementById('del-client-name').textContent = name;
        document.getElementById('del-client-form').action = `/admin/clients/${id}`;
        const warning = document.getElementById('del-client-warning');
        if (warning) warning.style.display = activeCampaigns > 0 ? 'block' : 'none';
        document.getElementById('modal-delete-client').classList.remove('hidden');
    }
    function closeDeleteClient() {
        document.getElementById('modal-delete-client').classList.add('hidden');
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteClient(); });

    // ══ FILTRES DYNAMIQUES ══
    (function() {
        let currentFilters = { search: '', sector: '', sort: 'name', page: 1 };
        let isLoading = false;
        let currentUrl = '{{ route("admin.clients.index") }}';
        let debounceTimer = null;

        function init() {
            const urlParams = new URLSearchParams(window.location.search);
            currentFilters.search = urlParams.get('search') || '';
            currentFilters.sector = urlParams.get('sector') || '';
            currentFilters.sort = urlParams.get('sort') || 'name';

            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-sector').value = currentFilters.sector;
            document.getElementById('filter-sort').value = currentFilters.sort;

            updateResetButton();

            document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
            document.getElementById('filter-sector').addEventListener('change', applyFilters);
            document.getElementById('filter-sort').addEventListener('change', applyFilters);
            document.getElementById('btn-reset').addEventListener('click', resetFilters);
        }

        function applyFilters() {
            currentFilters.search = document.getElementById('filter-search').value;
            currentFilters.sector = document.getElementById('filter-sector').value;
            currentFilters.sort = document.getElementById('filter-sort').value;
            currentFilters.page = 1;

            updateResetButton();
            fetchData();
        }

        function resetFilters() {
            currentFilters = { search: '', sector: '', sort: 'name', page: 1 };
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-sector').value = '';
            document.getElementById('filter-sort').value = 'name';
            updateResetButton();
            fetchData();
        }

        function updateResetButton() {
            const hasFilters = currentFilters.search || currentFilters.sector || currentFilters.sort !== 'name';
            document.getElementById('reset-wrapper').style.display = hasFilters ? 'flex' : 'none';
        }

        async function fetchData() {
            if (isLoading) return;
            isLoading = true;

            const params = new URLSearchParams();
            if (currentFilters.search) params.set('search', currentFilters.search);
            if (currentFilters.sector) params.set('sector', currentFilters.sector);
            if (currentFilters.sort !== 'name') params.set('sort', currentFilters.sort);
            params.set('ajax', '1');

            try {
                const response = await fetch(`${currentUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Erreur réseau');

                const data = await response.json();
                document.getElementById('table-body').innerHTML = data.html;
                document.getElementById('pagination-container').innerHTML = data.pagination;
                document.getElementById('total-count').textContent = data.total + ' client(s)';

                const newUrl = buildUrl();
                window.history.pushState({}, '', newUrl);
            } catch (error) {
                console.error('Erreur:', error);
            } finally {
                isLoading = false;
            }
        }

        function buildUrl() {
            const params = new URLSearchParams();
            if (currentFilters.search) params.set('search', currentFilters.search);
            if (currentFilters.sector) params.set('sector', currentFilters.sector);
            if (currentFilters.sort !== 'name') params.set('sort', currentFilters.sort);
            const query = params.toString();
            return query ? `${currentUrl}?${query}` : currentUrl;
        }

        function debounce(func, wait) {
            return function(...args) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => func(...args), wait);
            };
        }

        init();
    })();
    </script>
    @endpush
</x-admin-layout>
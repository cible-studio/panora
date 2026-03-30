<x-admin-layout title="Campagnes">
    <x-slot:topbarActions>
        @can('create', App\Models\Campaign::class)
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary">
            + Nouvelle campagne
        </a>
        @endcan
    </x-slot:topbarActions>

    {{-- ══ ALERTE FIN PROCHE ══ --}}
    @if(($endingSoonCount ?? 0) > 0)
    <div class="alert-warning-bar">
        <span class="alert-icon">⚠️</span>
        <span class="alert-text">{{ $endingSoonCount }} campagne(s) se terminent dans moins de 14 jours</span>
        <a href="{{ route('admin.campaigns.index', ['status' => 'actif', 'date_to' => now()->addDays(14)->format('Y-m-d')]) }}" class="alert-link">
            Voir →
        </a>
    </div>
    @endif

    {{-- ══ STATS AVEC FILTRES DYNAMIQUES ══ --}}
    <div class="stats-grid">
        @php
        $statCards = [
            ['key'=>'all', 'label'=>'Total', 'icon'=>'📋', 'color'=>'var(--text)', 'bg'=>'var(--surface)'],
            ['key'=>'actif', 'label'=>'En cours', 'icon'=>'📡', 'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,0.08)'],
            ['key'=>'pose', 'label'=>'En pose', 'icon'=>'🔧', 'color'=>'#3b82f6', 'bg'=>'rgba(59,130,246,0.08)'],
            ['key'=>'termine', 'label'=>'Terminées', 'icon'=>'✅', 'color'=>'#6b7280', 'bg'=>'rgba(107,114,128,0.08)'],
            ['key'=>'annule', 'label'=>'Annulées', 'icon'=>'🚫', 'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,0.08)'],
        ];
        @endphp

        @foreach($statCards as $sc)
        <a href="#" 
           class="stat-card" 
           data-filter="status" 
           data-value="{{ $sc['key'] !== 'all' ? $sc['key'] : '' }}"
           style="background:{{ $sc['bg'] }};border:1px solid var(--border);">
            <div class="stat-icon">{{ $sc['icon'] }}</div>
            <div class="stat-number" style="color:{{ $sc['color'] }}">
                {{ $sc['key'] === 'all' ? $campaigns->total() : ($counts[$sc['key']] ?? 0) }}
            </div>
            <div class="stat-label">{{ strtoupper($sc['label']) }}</div>
        </a>
        @endforeach
    </div>

    {{-- ══ FILTRES DYNAMIQUES (sans bouton) ══ --}}
    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">🔍 Recherche</label>
                <input type="text" id="filter-search" class="filter-input" 
                       placeholder="Nom de campagne…"
                       value="{{ request('search') }}"
                       data-filter="search">
            </div>

            <div class="filter-group">
                <label class="filter-label">👤 Client</label>
                <select id="filter-client" class="filter-select" data-filter="client_id">
                    <option value="">Tous</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📊 Statut</label>
                <select id="filter-status" class="filter-select" data-filter="status">
                    <option value="">Tous</option>
                    @foreach(\App\Enums\CampaignStatus::cases() as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📅 Du</label>
                <input type="date" id="filter-date-from" class="filter-input" 
                       value="{{ request('date_from') }}" data-filter="date_from">
            </div>

            <div class="filter-group">
                <label class="filter-label">📅 Au</label>
                <input type="date" id="filter-date-to" class="filter-input" 
                       value="{{ request('date_to') }}" data-filter="date_to">
            </div>

            <div class="filter-group">
                <label class="filter-label">💰 Facturation</label>
                <select id="filter-facture" class="filter-select" data-filter="non_facturee">
                    <option value="">Toutes</option>
                    <option value="1" {{ request('non_facturee') ? 'selected' : '' }}>Non facturées</option>
                </select>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">↺ Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- ══ TABLEAU DES CAMPAGNES ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Campagnes</span>
            <div class="stats-info">
                <span id="total-count" class="total-count">{{ $campaigns->total() }} résultat(s)</span>
                @if(($nonFactureesCount ?? 0) > 0)
                <span class="new-badge">💰 {{ $nonFactureesCount }} non facturée(s)</span>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="campaigns-table">
                <thead>
                    <tr>
                        <th>Campagne</th>
                        <th>Client</th>
                        <th>Période</th>
                        <th>Durée</th>
                        <th>Panneaux</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        <th>Facturation</th>
                        <th>Créée par</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.campaigns.partials.table-rows', ['campaigns' => $campaigns])
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div id="pagination-container" class="pagination-container">
            {{ $campaigns->links() }}
        </div>
    </div>

    {{-- ══ MODAL SUPPRESSION ══ --}}
    <div id="modal-delete-campaign" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:420px;">
            <div class="modal-header">
                <div class="modal-title" style="color:var(--red);">🗑 Supprimer la campagne</div>
                <button class="modal-close" onclick="closeDeleteCampaign()">✕</button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <div class="text-5xl mb-3">🗑</div>
                    <div class="font-bold text-lg mb-2">
                        Supprimer <span id="del-campaign-name" class="text-accent"></span> ?
                    </div>
                    <div class="text-sm text-gray-400 mb-4">
                        Suppression définitive. Les panneaux liés seront libérés si aucune reservation en cours, sinon annulez la reservation associée.
                    </div>
                    <div class="warning-box">
                        ⚠️ Uniquement possible si la campagne est annulée.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeDeleteCampaign()">Annuler</button>
                <form id="del-campaign-form" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 12px 14px;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .stat-card.active { box-shadow: 0 0 0 2px var(--accent); }
        .stat-icon { font-size: 20px; margin-bottom: 4px; }
        .stat-number { font-size: 22px; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 10px; color: var(--text3); font-weight: 600; letter-spacing: 0.4px; margin-top: 4px; }

        .alert-warning-bar {
            background: rgba(232,160,32,0.08);
            border: 1px solid rgba(232,160,32,0.3);
            border-radius: 10px;
            padding: 10px 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-icon { font-size: 18px; }
        .alert-text { font-size: 13px; color: var(--accent); font-weight: 600; flex: 1; }
        .alert-link {
            font-size: 11px;
            color: var(--accent);
            text-decoration: none;
            padding: 4px 10px;
            border: 1px solid rgba(232,160,32,0.4);
            border-radius: 6px;
        }

        .filters-card {
            background: var(--surface);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 16px 20px;
            margin-bottom: 20px;
        }
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--text-muted); }
        .filter-input, .filter-select {
            height: 40px;
            padding: 0 12px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
        }
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
        .stats-info { display: flex; align-items: center; gap: 12px; }
        .total-count { font-size: 12px; color: var(--text2); }
        .new-badge { font-size: 11px; font-weight: 600; color: var(--warning); background: rgba(232,160,32,0.1); padding: 3px 10px; border-radius: 20px; }

        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            text-align: left;
            padding: 10px 12px;
            background: var(--surface2);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        .data-table td {
            padding: 12px 12px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
        }
        .data-table tr:hover td { background: var(--surface2); }

        .campaign-name { font-weight: 700; color: var(--text); text-decoration: none; }
        .client-link { color: var(--text); text-decoration: none; font-weight: 500; }
        .badge-panels { background: rgba(59,130,246,0.1); color: #60a5fa; padding: 2px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; }
        .amount { font-weight: 700; color: var(--accent); white-space: nowrap; }
        .status-badge { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid; }
        .progress-bar { margin-top: 5px; background: var(--surface3); border-radius: 3px; height: 3px; overflow: hidden; }
        .progress-fill { height: 100%; border-radius: 3px; }
        .days-left { font-size: 9px; color: var(--text3); margin-top: 2px; }

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
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text);
            text-decoration: none;
        }
        .pagination li.active span { background: var(--accent); border-color: var(--accent); color: #000; }
        .pagination li a:hover { background: var(--surface3); border-color: var(--accent); }

        .actions { display: flex; gap: 6px; }
        .btn-icon {
            background: transparent;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-icon:hover { background: var(--surface3); transform: scale(1.05); }
        .btn-delete { color: var(--danger); }

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
            overflow: hidden;
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
        .modal-body { padding: 20px; }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .warning-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.2);
            border-radius: 8px;
            padding: 10px;
            font-size: 12px;
            color: var(--red);
            display: flex;
            gap: 8px;
        }
        .btn {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: var(--accent); color: #000; }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
        .text-center { text-align: center; }
        .text-5xl { font-size: 48px; }
        .font-bold { font-weight: 700; }
        .text-lg { font-size: 18px; }
        .text-sm { font-size: 13px; }
        .text-gray-400 { color: #9ca3af; }
        .text-accent { color: var(--accent); }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px 10px; }
        }
    </style>

    @push('scripts')
    <script>
    // ══ MODAL SUPPRESSION ══
    function openDeleteCampaign(id, name) {
        document.getElementById('del-campaign-name').textContent = name;
        document.getElementById('del-campaign-form').action = `/admin/campaigns/${id}`;
        document.getElementById('modal-delete-campaign').style.display = 'flex';
    }
    function closeDeleteCampaign() {
        document.getElementById('modal-delete-campaign').style.display = 'none';
    }
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeDeleteCampaign();
    });

    // ══ FILTRES DYNAMIQUES ══
    (function() {
        let currentFilters = {
            search: '',
            client_id: '',
            status: '',
            date_from: '',
            date_to: '',
            non_facturee: '',
            page: 1
        };
        let isLoading = false;
        let currentUrl = '{{ route("admin.campaigns.index") }}';
        let debounceTimer = null;

        function init() {
            const urlParams = new URLSearchParams(window.location.search);
            currentFilters.search = urlParams.get('search') || '';
            currentFilters.client_id = urlParams.get('client_id') || '';
            currentFilters.status = urlParams.get('status') || '';
            currentFilters.date_from = urlParams.get('date_from') || '';
            currentFilters.date_to = urlParams.get('date_to') || '';
            currentFilters.non_facturee = urlParams.get('non_facturee') || '';

            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-client').value = currentFilters.client_id;
            document.getElementById('filter-status').value = currentFilters.status;
            document.getElementById('filter-date-from').value = currentFilters.date_from;
            document.getElementById('filter-date-to').value = currentFilters.date_to;
            document.getElementById('filter-facture').value = currentFilters.non_facturee;

            updateActiveStat();
            updateResetButton();

            document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
            document.getElementById('filter-client').addEventListener('change', applyFilters);
            document.getElementById('filter-status').addEventListener('change', applyFilters);
            document.getElementById('filter-date-from').addEventListener('change', applyFilters);
            document.getElementById('filter-date-to').addEventListener('change', applyFilters);
            document.getElementById('filter-facture').addEventListener('change', applyFilters);
            
            document.querySelectorAll('.stat-card').forEach(card => {
                card.addEventListener('click', (e) => {
                    e.preventDefault();
                    const filterValue = card.dataset.value;
                    document.getElementById('filter-status').value = filterValue;
                    applyFilters();
                });
            });

            document.getElementById('btn-reset').addEventListener('click', resetFilters);
        }

        function applyFilters() {
            currentFilters.search = document.getElementById('filter-search').value;
            currentFilters.client_id = document.getElementById('filter-client').value;
            currentFilters.status = document.getElementById('filter-status').value;
            currentFilters.date_from = document.getElementById('filter-date-from').value;
            currentFilters.date_to = document.getElementById('filter-date-to').value;
            currentFilters.non_facturee = document.getElementById('filter-facture').value;

            updateResetButton();
            updateActiveStat();
            fetchData();
        }

        function resetFilters() {
            currentFilters = { search: '', client_id: '', status: '', date_from: '', date_to: '', non_facturee: '', page: 1 };
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-client').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            document.getElementById('filter-facture').value = '';

            updateResetButton();
            updateActiveStat();
            fetchData();
        }

        function updateActiveStat() {
            const activeStatus = currentFilters.status;
            document.querySelectorAll('.stat-card').forEach(card => {
                const cardValue = card.dataset.value;
                if ((activeStatus === '' && cardValue === '') || (cardValue === activeStatus)) {
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                }
            });
        }

        function updateResetButton() {
            const hasFilters = currentFilters.search || currentFilters.client_id || 
                               currentFilters.status || currentFilters.date_from || 
                               currentFilters.date_to || currentFilters.non_facturee;
            document.getElementById('reset-wrapper').style.display = hasFilters ? 'flex' : 'none';
        }

        async function fetchData() {
            if (isLoading) return;
            isLoading = true;
            
            const params = new URLSearchParams();
            if (currentFilters.search) params.set('search', currentFilters.search);
            if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
            if (currentFilters.status) params.set('status', currentFilters.status);
            if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
            if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
            if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
            params.set('ajax', '1');

            try {
                const response = await fetch(`${currentUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Erreur réseau');
                
                const data = await response.json();
                document.getElementById('table-body').innerHTML = data.html;
                document.getElementById('pagination-container').innerHTML = data.pagination;
                
                if (data.stats) updateStats(data.stats);
                
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
            if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
            if (currentFilters.status) params.set('status', currentFilters.status);
            if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
            if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
            if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
            const query = params.toString();
            return query ? `${currentUrl}?${query}` : currentUrl;
        }

        function updateStats(stats) {
            document.getElementById('total-count').textContent = stats.total + ' résultat(s)';
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
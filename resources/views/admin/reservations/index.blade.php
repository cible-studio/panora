<x-admin-layout title="Réservations">
    <x-slot:topbarActions>
        <a href="{{ route('admin.reservations.disponibilites') }}" class="btn btn-primary">
            + Nouvelle réservation
        </a>
    </x-slot:topbarActions>

    {{-- ══ STATS AVEC FILTRES DYNAMIQUES ══ --}}
    <div class="stats-grid">
        @php
        $statCards = [
            ['key'=>'total', 'label'=>'Total', 'icon'=>'📋', 'color'=>'var(--text)', 'bg'=>'var(--surface)'],
            ['key'=>'en_attente', 'label'=>'En attente', 'icon'=>'⏳', 'color'=>'#e8a020', 'bg'=>'rgba(232,160,32,0.08)'],
            ['key'=>'confirme', 'label'=>'Confirmées', 'icon'=>'✅', 'color'=>'#22c55e', 'bg'=>'rgba(34,197,94,0.08)'],
            ['key'=>'refuse', 'label'=>'Refusées', 'icon'=>'❌', 'color'=>'#ef4444', 'bg'=>'rgba(239,68,68,0.08)'],
            ['key'=>'annule', 'label'=>'Annulées', 'icon'=>'🚫', 'color'=>'#6b7280', 'bg'=>'rgba(107,114,128,0.08)'],
        ];
        @endphp
        @foreach($statCards as $sc)
        <a href="#" 
           class="stat-card" 
           data-filter="status" 
           data-value="{{ $sc['key'] !== 'total' ? $sc['key'] : '' }}"
           style="background:{{ $sc['bg'] }};border:1px solid rgba(0,0,0,0.1);">
            <div class="stat-icon">{{ $sc['icon'] }}</div>
            <div class="stat-number" style="color:{{ $sc['color'] }}">{{ $counts[$sc['key']] ?? 0 }}</div>
            <div class="stat-label">{{ strtoupper($sc['label']) }}</div>
            @if($sc['key'] === 'en_attente' && ($newCount ?? 0) > 0)
            <div class="stat-badge">✦ {{ $newCount }} nouvelle(s)</div>
            @endif
        </a>
        @endforeach
    </div>

    {{-- ══ FILTRES DYNAMIQUES ══ --}}
    <div class="filters-card">
        <div class="filters-grid">
            <div class="filter-group">
                <label class="filter-label">🔍 Recherche</label>
                <input type="text" id="filter-search" class="filter-input" 
                       placeholder="Référence, client…"
                       value="{{ request('search') }}"
                       data-filter="search">
            </div>

            <div class="filter-group">
                <label class="filter-label">📊 Statut</label>
                <select id="filter-status" class="filter-select" data-filter="status">
                    <option value="">Tous</option>
                    @foreach($statuses as $s)
                    <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>
                        {{ $s->label() }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">🏷️ Type</label>
                <select id="filter-type" class="filter-select" data-filter="type">
                    <option value="">Tous</option>
                    <option value="option" {{ request('type') === 'option' ? 'selected' : '' }}>⏳ Option</option>
                    <option value="ferme" {{ request('type') === 'ferme' ? 'selected' : '' }}>🔒 Ferme</option>
                </select>
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
                <label class="filter-label">📅 Période</label>
                <select id="filter-periode" class="filter-select" data-filter="periode">
                    <option value="">Toutes</option>
                    <option value="this_month" {{ request('periode') === 'this_month' ? 'selected' : '' }}>Ce mois</option>
                    <option value="last_month" {{ request('periode') === 'last_month' ? 'selected' : '' }}>Mois dernier</option>
                    <option value="this_quarter" {{ request('periode') === 'this_quarter' ? 'selected' : '' }}>Ce trimestre</option>
                    <option value="this_year" {{ request('periode') === 'this_year' ? 'selected' : '' }}>Cette année</option>
                </select>
            </div>

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">↺ Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- ══ TABLEAU DES RÉSERVATIONS ══ --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Réservations</span>
            <div class="stats-info">
                <span id="total-count" class="total-count">{{ $reservations->total() }} résultat(s)</span>
                @if(($newCount ?? 0) > 0)
                <span class="new-badge">✦ {{ $newCount }} nouvelle(s)</span>
                @endif
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="reservations-table">
                <thead>
                        <th style="width:8px"></th>
                        <th>Référence</th>
                        <th>Client</th>
                        <th>Période</th>
                        <th>Panneaux</th>
                        <th>Montant</th>
                        <th>Type</th>
                        <th>Statut</th>
                        <th>Campagne</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.reservations.partials.table-rows', ['reservations' => $reservations])
                </tbody>
            </table>
        </div>

       {{-- Pagination --}}
        <!-- <div id="pagination-container" class="pagination-container">
            {{ $reservations->links() }}
        </div> -->
    </div>

    {{-- ══ MODAL SUPPRESSION ══ --}}
    <div id="modal-delete" class="modal-overlay" style="display:none;" onclick="closeDeleteModal(event)">
        <div class="modal" style="max-width:480px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <span class="modal-title" style="color:var(--red);">🗑️ Supprimer la réservation</span>
                <button class="modal-close" onclick="closeDeleteModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="text-5xl mb-3">🗑️</div>
                    <div class="font-bold text-lg mb-2">
                        Supprimer <span id="delete-ref" class="text-accent"></span> ?
                    </div>
                    <div class="text-sm text-gray-400" id="delete-client"></div>
                </div>

                <div class="info-box mb-4">
                    <div class="font-semibold mb-2 text-gray-300">⚠️ Conséquences :</div>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li class="flex gap-2">
                            <span class="text-red-500">🗑️</span>
                            <span>La réservation sera <strong>définitivement supprimée</strong></span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-yellow-500">📁</span>
                            <span>La campagne liée sera <strong>automatiquement annulée</strong></span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-green-500">🔓</span>
                            <span>Les <strong id="delete-panels-count"></strong> panneau(x) seront libérés</span>
                        </li>
                    </ul>
                </div>

                <div class="warning-box">
                    <span>⚠️</span>
                    <span>Cette action est <strong>irréversible</strong> et ne peut pas être annulée.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeDeleteModal()" class="btn btn-ghost">Annuler</button>
                <form id="delete-form" method="POST" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger">🗑️ Supprimer définitivement</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ MODAL ANNULATION ══ --}}
    <div id="modal-annuler" class="modal-overlay" style="display:none;" onclick="closeAnnulerModal(event)">
        <div class="modal" style="max-width:480px;" onclick="event.stopPropagation()">
            <div class="modal-header">
                <span class="modal-title" style="color:var(--orange);">🚫 Annuler la réservation</span>
                <button class="modal-close" onclick="closeAnnulerModal()">✕</button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <div class="text-5xl mb-3">🚫</div>
                    <div class="font-bold text-lg mb-2">
                        Annuler <span id="annuler-ref" class="text-accent"></span> ?
                    </div>
                    <div class="text-sm text-gray-400">
                        Réservation de <strong id="annuler-client"></strong>
                    </div>
                </div>

                <div class="info-box mb-4">
                    <div class="font-semibold mb-2 text-gray-300">Ce qui va se passer :</div>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li class="flex gap-2">
                            <span class="text-green-500">✓</span>
                            <span>Les <strong id="annuler-panels"></strong> panneau(x) seront <strong>immédiatement libérés</strong></span>
                        </li>
                        <li class="flex gap-2">
                            <span class="text-green-500">✓</span>
                            <span>L'historique sera conservé avec le statut "Annulé"</span>
                        </li>
                    </ul>
                </div>

                <div class="warning-box">
                    <span>⚠️</span>
                    <span>Cette action est <strong>irréversible</strong>. Une réservation annulée ne peut pas être réactivée.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button onclick="closeAnnulerModal()" class="btn btn-ghost">Conserver</button>
                <form id="annuler-form" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="btn-warning">🚫 Confirmer l'annulation</button>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 14px 16px;
            text-decoration: none;
            transition: transform 0.15s, box-shadow 0.15s;
            cursor: pointer;
            position: relative;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .stat-card.active { box-shadow: 0 0 0 2px var(--accent); }
        .stat-icon { font-size: 20px; margin-bottom: 6px; }
        .stat-number { font-size: 24px; font-weight: 800; line-height: 1; }
        .stat-label { font-size: 11px; color: var(--text3); font-weight: 600; letter-spacing: 0.4px; margin-top: 4px; }
        .stat-badge { font-size: 10px; color: var(--accent); font-weight: 700; margin-top: 3px; }

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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .filter-input, .filter-select {
            height: 40px;
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
            height: 40px;
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
            padding: 12px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            transition: background 0.12s;
        }
        .data-table tr:hover td { background: var(--surface2); }
        .data-table tr.new-row td { background: rgba(232, 160, 32, 0.04); }

        /* Badges et styles */
        .reference-link { font-family: monospace; font-size: 12px; font-weight: 700; color: var(--accent); text-decoration: none; }
        .date-humans { font-size: 10px; color: var(--text3); margin-top: 1px; }
        .client-link { font-weight: 600; color: var(--text); text-decoration: none; }
        .client-deleted { color: var(--text2); }
        .deleted-badge { font-size: 10px; margin-left: 4px; padding: 1px 5px; background: rgba(239,68,68,0.1); color: var(--red); border-radius: 4px; }
        .date-range { font-size: 12px; white-space: nowrap; color: var(--text2); }
        .date-range span { color: var(--text3); margin: 0 2px; }
        .badge { background: var(--surface3); padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .amount { font-weight: 600; color: var(--accent); white-space: nowrap; }
        .amount span { font-size: 10px; font-weight: 400; color: var(--text3); }
        .type-badge { font-size: 11px; padding: 2px 7px; border-radius: 5px; background: var(--surface3); color: var(--text2); }
        .type-ferme { background: rgba(34,197,94,0.1); color: var(--success); }
        .type-option { background: rgba(232,160,32,0.1); color: var(--warning); }
        .status-badge { padding: 3px 9px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; border: 1px solid; }
        .campaign-link { font-size: 12px; color: var(--accent); text-decoration: none; font-weight: 600; }
        .create-campaign { font-size: 11px; color: var(--success); text-decoration: none; padding: 2px 7px; border-radius: 5px; border: 1px solid rgba(34,197,94,0.3); }
        .no-campaign { color: var(--text3); font-size: 12px; }

        /* Actions */
        .actions { display: flex; gap: 6px; align-items: center; }
        .btn-icon {
            background: transparent;
            border: none;
            font-size: 16px;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 6px;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-icon:hover { background: var(--surface3); transform: scale(1.05); }
        .btn-cancel { color: var(--warning); }
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
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text);
            text-decoration: none;
            transition: all 0.2s;
        }
        .pagination li.active span { background: var(--accent); border-color: var(--accent); color: #000; }
        .pagination li a:hover { background: var(--surface3); border-color: var(--accent); }

        /* Stats info */
        .stats-info { display: flex; align-items: center; gap: 12px; }
        .total-count { font-size: 12px; color: var(--text2); }
        .new-badge { font-size: 12px; font-weight: 600; color: var(--accent); background: rgba(232,160,32,0.1); padding: 3px 10px; border-radius: 20px; border: 1px solid rgba(232,160,32,0.3); }

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
            max-height: 90vh;
            overflow: hidden;
            animation: modalFadeIn 0.2s ease;
        }
        @keyframes modalFadeIn {
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
            transition: all 0.2s;
        }
        .modal-close:hover { color: var(--danger); }
        .modal-body { padding: 20px; overflow-y: auto; max-height: calc(90vh - 120px); }
        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .info-box { background: var(--surface2); border-radius: 12px; padding: 14px; }
        .warning-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 8px; padding: 11px 13px; font-size: 12px; color: var(--red); display: flex; gap: 8px; }
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
        .btn-danger:hover { background: #dc2626; transform: translateY(-1px); }
        .btn-ghost { background: transparent; border: 1px solid var(--border); color: var(--text-dim); }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }
        .btn-warning { background: var(--warning); color: #000; border: none; padding: 8px 18px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .text-accent { color: var(--accent); }
        .text-center { text-align: center; }
        .text-5xl { font-size: 48px; }
        .font-bold { font-weight: 700; }
        .text-lg { font-size: 18px; }
        .text-sm { font-size: 13px; }
        .text-gray-300 { color: #d1d5db; }
        .text-gray-400 { color: #9ca3af; }
        .text-red-500 { color: #ef4444; }
        .text-yellow-500 { color: #eab308; }
        .text-green-500 { color: #22c55e; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .mb-4 { margin-bottom: 16px; }
        .space-y-2 > * + * { margin-top: 8px; }
        .gap-2 { gap: 8px; }
        .flex { display: flex; }
        .inline { display: inline; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-end { justify-content: flex-end; }

        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px 12px; }
        }
    </style>

    @push('scripts')
    <script>
    // ══ MODALS ══
    function openDeleteModal(id, ref, client, panelsCount) {
        document.getElementById('delete-ref').textContent = ref;
        document.getElementById('delete-client').textContent = 'Réservation de ' + client;
        document.getElementById('delete-panels-count').textContent = panelsCount;
        document.getElementById('delete-form').action = '/admin/reservations/' + id;
        document.getElementById('modal-delete').style.display = 'flex';
    }
    
    function closeDeleteModal(e) {
        if (!e || e.target === document.getElementById('modal-delete') || e.target.closest('.modal-close')) {
            document.getElementById('modal-delete').style.display = 'none';
        }
    }
    
    function openAnnulerModal(id, ref, client, panelsCount) {
        document.getElementById('annuler-ref').textContent = ref;
        document.getElementById('annuler-client').textContent = client;
        document.getElementById('annuler-panels').textContent = panelsCount;
        document.getElementById('annuler-form').action = '/admin/reservations/' + id + '/annuler';
        document.getElementById('modal-annuler').style.display = 'flex';
    }
    
    function closeAnnulerModal(e) {
        if (!e || e.target === document.getElementById('modal-annuler') || e.target.closest('.modal-close')) {
            document.getElementById('modal-annuler').style.display = 'none';
        }
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDeleteModal();
            closeAnnulerModal();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Marquer comme vu après 2 secondes
        setTimeout(() => {
            fetch('{{ route("admin.reservations.mark-seen") }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'Content-Type': 'application/json',
                }
            });
        }, 2000);
    });
        
    // ══ FILTRES DYNAMIQUES ══
    (function() {
        let currentFilters = {
            search: '',
            status: '',
            type: '',
            client_id: '',
            periode: '',
            page: 1
        };
        let isLoading = false;
        let currentUrl = '{{ route("admin.reservations.index") }}';
        let debounceTimer = null;

        function init() {
            const urlParams = new URLSearchParams(window.location.search);
            currentFilters.search = urlParams.get('search') || '';
            currentFilters.status = urlParams.get('status') || '';
            currentFilters.type = urlParams.get('type') || '';
            currentFilters.client_id = urlParams.get('client_id') || '';
            currentFilters.periode = urlParams.get('periode') || '';

            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-status').value = currentFilters.status;
            document.getElementById('filter-type').value = currentFilters.type;
            document.getElementById('filter-client').value = currentFilters.client_id;
            document.getElementById('filter-periode').value = currentFilters.periode;

            updateActiveStat();
            updateResetButton();

            document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
            document.getElementById('filter-status').addEventListener('change', applyFilters);
            document.getElementById('filter-type').addEventListener('change', applyFilters);
            document.getElementById('filter-client').addEventListener('change', applyFilters);
            document.getElementById('filter-periode').addEventListener('change', applyFilters);
            
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
            currentFilters.status = document.getElementById('filter-status').value;
            currentFilters.type = document.getElementById('filter-type').value;
            currentFilters.client_id = document.getElementById('filter-client').value;
            currentFilters.periode = document.getElementById('filter-periode').value;
            currentFilters.page = 1;

            updateResetButton();
            updateActiveStat();
            fetchData();
        }

        function resetFilters() {
            currentFilters = { search: '', status: '', type: '', client_id: '', periode: '', page: 1 };
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-type').value = '';
            document.getElementById('filter-client').value = '';
            document.getElementById('filter-periode').value = '';

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
            const hasFilters = currentFilters.search || currentFilters.status || 
                               currentFilters.type || currentFilters.client_id || 
                               currentFilters.periode;
            document.getElementById('reset-wrapper').style.display = hasFilters ? 'flex' : 'none';
        }

        async function fetchData() {
                if (isLoading) return;
                isLoading = true;
                
                // Afficher un loader
                const tbody = document.getElementById('table-body');
                tbody.innerHTML = '<tr><td colspan="10" class="text-center py-10">⏳ Chargement...</td></tr>';
                
                const params = new URLSearchParams();
                if (currentFilters.search) params.set('search', currentFilters.search);
                if (currentFilters.status) params.set('status', currentFilters.status);
                if (currentFilters.type) params.set('type', currentFilters.type);
                if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
                if (currentFilters.periode) params.set('periode', currentFilters.periode);
                params.set('ajax', '1');

                try {
                    const response = await fetch(`${currentUrl}?${params}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!response.ok) throw new Error('Erreur réseau');
                    
                    const data = await response.json();
                    
                    // Mettre à jour le tableau
                    document.getElementById('table-body').innerHTML = data.html;
                    
                    // Mettre à jour la pagination
                    const paginationContainer = document.getElementById('pagination-container');
                    if (paginationContainer && data.pagination) {
                        paginationContainer.innerHTML = data.pagination;
                    }
                    
                    // Mettre à jour les stats
                    if (data.stats) updateStats(data.stats);
                    
                    // Mettre à jour l'URL
                    const newUrl = buildUrl();
                    window.history.pushState({}, '', newUrl);
                    
                } catch (error) {
                    console.error('Erreur:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="10" class="text-center py-10 text-red-500">❌ Erreur de chargement</td></tr>';
                } finally {
                    isLoading = false;
                }
            }

        function buildUrl() {
            const params = new URLSearchParams();
            if (currentFilters.search) params.set('search', currentFilters.search);
            if (currentFilters.status) params.set('status', currentFilters.status);
            if (currentFilters.type) params.set('type', currentFilters.type);
            if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
            if (currentFilters.periode) params.set('periode', currentFilters.periode);
            const query = params.toString();
            return query ? `${currentUrl}?${query}` : currentUrl;
        }

        function updateStats(stats) {
            document.getElementById('total-count').textContent = stats.total + ' résultat(s)';
            const statElements = { total: stats.total, en_attente: stats.en_attente, confirme: stats.confirme, refuse: stats.refuse, annule: stats.annule };
            document.querySelectorAll('.stat-card').forEach(card => {
                const key = card.dataset.value === '' ? 'total' : card.dataset.value;
                const numberSpan = card.querySelector('.stat-number');
                if (numberSpan && statElements[key] !== undefined) numberSpan.textContent = statElements[key];
            });
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
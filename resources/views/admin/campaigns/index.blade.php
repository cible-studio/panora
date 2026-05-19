<x-admin-layout title="Campagnes">
    <x-slot:topbarActions>
        {{-- Exports (préservent les filtres URL courants) --}}
        <a href="{{ route('admin.campaigns.export.excel', request()->query()) }}"
           class="btn btn-ghost btn-sm" title="Exporter la liste filtrée en Excel">
            📊 Excel
        </a>
        <a href="{{ route('admin.campaigns.export.pdf', request()->query()) }}"
           class="btn btn-ghost btn-sm" title="Exporter la liste filtrée en PDF">
            📄 PDF
        </a>
        @can('create', App\Models\Campaign::class)
        <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary">
            + Nouvelle campagne
        </a>
        @endcan
    </x-slot:topbarActions>

    {{-- ══ ALERTE FIN PROCHE — dismissible (localStorage 24h) ══ --}}
    @if(($endingSoonCount ?? 0) > 0)
    <div class="alert-warning-bar" id="ending-soon-banner" data-banner-key="ending-soon-{{ $endingSoonCount }}">
        <span class="alert-icon">⚠️</span>
        <span class="alert-text">{{ $endingSoonCount }} campagne(s) se terminent dans moins de 14 jours</span>
        <a href="{{ route('admin.campaigns.index', ['status' => 'actif', 'date_to' => now()->addDays(14)->format('Y-m-d')]) }}" class="alert-link">
            Voir →
        </a>
        <button type="button" class="alert-close"
                onclick="(function(b){
                    try { localStorage.setItem('dismissed-' + b.dataset.bannerKey, Date.now()); } catch(e) {}
                    b.style.display='none';
                })(this.parentElement)"
                title="Masquer cette alerte (24h)"
                aria-label="Fermer">✕</button>
    </div>
    <script>
        // Vérifie le localStorage au load : si l'alerte a été fermée il y a
        // moins de 24h, on la cache. Le compteur change le data-banner-key
        // donc une nouvelle alerte réapparaît même si l'ancienne était dismiss.
        (function() {
            const b = document.getElementById('ending-soon-banner');
            if (!b) return;
            const key = 'dismissed-' + b.dataset.bannerKey;
            try {
                const ts = parseInt(localStorage.getItem(key) || '0', 10);
                if (ts && (Date.now() - ts) < 24 * 3600 * 1000) {
                    b.style.display = 'none';
                }
            } catch(e) { /* localStorage indisponible : on laisse visible */ }
        })();
    </script>
    @endif

    {{-- ══ KPI cards (pattern unifié projet : bordure latérale colorée,
         toggle, état actif, counts qui suivent les filtres) ══ --}}
    @php
    $statCards = [
        ['key'=>'all',      'label'=>'Total',      'icon'=>'📋', 'color'=>'var(--accent)'],
        ['key'=>'planifie', 'label'=>'Planifiées', 'icon'=>'📅', 'color'=>'#f97316'],
        ['key'=>'actif',    'label'=>'En cours',   'icon'=>'📡', 'color'=>'#22c55e'],
        ['key'=>'pause',    'label'=>'En pause',   'icon'=>'⏸',  'color'=>'#f59e0b'],
        ['key'=>'termine',  'label'=>'Terminées',  'icon'=>'✅', 'color'=>'#3b82f6'],
        ['key'=>'annule',   'label'=>'Annulées',   'icon'=>'🚫', 'color'=>'#ef4444'],
    ];
    $activeStatus = request('status');
    $hasAnyFilter = request('search') || request('status') || request('client_id')
                  || request('date_from') || request('date_to') || request('date_debut')
                  || request('date_fin') || request('non_facturee')
                  || request('commune_id') || request('zone_id');
    @endphp
    {{-- Compact : icone à gauche, chiffre + label à droite, sur 1 seule ligne.
         6 cartes alignées sans étirement excessif. --}}
    <div class="stats-grid" style="display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin-bottom:18px">
        @foreach($statCards as $sc)
        @php
            $isAll    = $sc['key'] === 'all';
            $isActive = $isAll ? !$hasAnyFilter : ($activeStatus === $sc['key']);
            $val      = $isAll ? $campaigns->total() : ($counts[$sc['key']] ?? 0);
        @endphp
        <a href="#"
           class="stat-card {{ $isActive ? 'active' : '' }}"
           data-filter="status"
           data-kpi="{{ $sc['key'] }}"
           data-value="{{ $isAll ? '' : $sc['key'] }}"
           title="{{ $sc['label'] }}"
           style="background:var(--surface);border:1px solid var(--border);border-left:3px solid {{ $sc['color'] }};border-radius:10px;padding:10px 12px;text-decoration:none;display:flex;align-items:center;gap:10px;transition:all .15s;min-width:0;{{ $isActive ? 'box-shadow:0 0 0 2px '.$sc['color'].'33;' : '' }}">
            <div class="stat-icon" style="font-size:18px;color:{{ $sc['color'] }};flex-shrink:0;line-height:1">{{ $sc['icon'] }}</div>
            <div style="min-width:0;line-height:1.1">
                <div class="stat-number" data-kpi-value="{{ $sc['key'] }}" style="font-size:20px;font-weight:800;color:{{ $sc['color'] }}">{{ number_format($val) }}</div>
                <div class="stat-label" style="font-size:10px;font-weight:600;color:var(--text3);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $sc['label'] }}</div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Responsive : sur petits écrans, 3 cartes par ligne au lieu de 6 --}}
    <style>
        @media (max-width: 900px) {
            .stats-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
            }
        }
        @media (max-width: 540px) {
            .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
        }
    </style>

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
                <label class="filter-label">📍 Commune</label>
                <select id="filter-commune" class="filter-select" data-filter="commune_id">
                    <option value="">Toutes les communes</option>
                    @foreach($communes as $commune)
                    <option value="{{ $commune->id }}" {{ request('commune_id') == $commune->id ? 'selected' : '' }}>
                        {{ $commune->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="filter-group">
                <label class="filter-label">📍 Zone</label>
                <select id="filter-zone" class="filter-select" data-filter="zone_id">
                    <option value="">Toutes les zones</option>
                    @foreach($zones as $zone)
                    <option value="{{ $zone->id }}" {{ request('zone_id') == $zone->id ? 'selected' : '' }}>
                        {{ $zone->name }}
                    </option>
                    @endforeach
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
            @php
                // Facturation : visible pour admin + commercial (le commercial
                // suit le règlement de son portefeuille). Cachée pour MP
                // (matrice FACTURES — MP ne facture pas).
                $authRole = auth()->user()?->role?->value;
                $canSeeBilling = in_array($authRole, ['admin', 'commercial'], true);
            @endphp
            <table class="data-table" id="campaigns-table">
                <thead>
                    <tr>
                        @if(in_array($authRole, ['admin', 'mediaplanner'], true))
                        <th style="width:36px;text-align:center;">
                            <input type="checkbox" data-bulk-select-all aria-label="Tout sélectionner">
                        </th>
                        @endif
                        <th>Campagne</th>
                        <th>Client</th>
                        <th>Période</th>
                        <th>Durée</th>
                        <th>Panneaux</th>
                        <th>Montant</th>
                        <th>Statut</th>
                        @if($canSeeBilling)
                        <th>Facturation</th>
                        @endif
                        <th>Commercial</th>
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

    {{-- ── Barre d'action groupée campagnes (admin + MP) ── --}}
    @if(in_array($authRole, ['admin', 'mediaplanner'], true))
    <div class="bulk-bar" id="camp-bulk-bar">
        <div class="bulk-count">
            <strong class="bulk-count-num">0</strong> campagne(s) sélectionnée(s)
        </div>
        <div class="bulk-actions">
            <button type="button" data-bulk-clear class="secondary">Désélectionner</button>
            <button type="button" onclick="bulkCamp('pause')">⏸ Mettre en pause</button>
            <button type="button" onclick="bulkCamp('resume')">▶ Reprendre</button>
            <button type="button" onclick="bulkCamp('cancel')" class="danger">🚫 Annuler</button>
            <button type="button" onclick="bulkCamp('delete')" class="danger">🗑 Supprimer</button>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const helper = window.BulkBar.init({
            barId: 'camp-bulk-bar',
            checkboxSelector: '#campaigns-table .bulk-checkbox',
        });
        window.bulkCamp = async function(action) {
            if (!helper) return;
            const ids = helper.getSelectedIds();
            if (ids.length === 0) return;
            const labels = { pause: 'METTRE EN PAUSE', resume: 'REPRENDRE', cancel: 'ANNULER', delete: 'SUPPRIMER' };
            const reason = action === 'cancel'
                ? prompt(`Motif d'annulation (optionnel) pour ${ids.length} campagne(s) :`)
                : null;
            if (action === 'cancel' && reason === null) return;
            if (!confirm(`Confirmer : ${labels[action]} ${ids.length} campagne(s) ?`)) return;
            await window.BulkBar.submit({
                url: '{{ route('admin.campaigns.bulk') }}',
                ids,
                payload: { action, cancel_reason: reason ?? '' },
            });
        };
    });
    </script>
    @endif

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

    {{-- ══ MODAL FACTURATION RAPIDE ══ --}}
    <div id="modal-billing" class="modal-overlay" style="display:none;">
        <div class="modal" style="max-width:460px;width:100%;">
            <div class="modal-header">
                <div class="modal-title">💰 Facturation — <span id="bill-campaign-name" style="font-size:15px;font-weight:500;"></span></div>
                <button class="modal-close" onclick="closeBillingModal()">✕</button>
            </div>
            <div class="modal-body">
                <div style="display:grid;gap:14px;">
                    <div>
                        <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);display:block;margin-bottom:6px;">Statut</label>
                        <select id="bill-status" style="width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);" onchange="onBillStatusChange()">
                            <option value="brouillon">📝 Brouillon</option>
                            <option value="envoyee">📤 Envoyée</option>
                            <option value="payee">✅ Payée</option>
                            <option value="annulee">🚫 Annulée</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);display:block;margin-bottom:6px;">Montant TTC (FCFA)</label>
                        <input type="number" id="bill-amount" step="1" min="0"
                               style="width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);box-sizing:border-box;">
                    </div>
                    <div id="bill-paid-at-group">
                        <label style="font-size:11px;font-weight:600;text-transform:uppercase;color:var(--text3);display:block;margin-bottom:6px;">Date de paiement</label>
                        <input type="date" id="bill-paid-at"
                               style="width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);box-sizing:border-box;">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost" onclick="closeBillingModal()">Annuler</button>
                <button class="btn btn-primary" id="bill-submit-btn" onclick="submitBilling()">✅ Enregistrer</button>
            </div>
        </div>
    </div>

    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
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
        .alert-close {
            background: transparent;
            border: none;
            color: var(--accent);
            font-size: 16px;
            font-weight: 600;
            line-height: 1;
            padding: 4px 8px;
            margin-left: 4px;
            cursor: pointer;
            opacity: 0.7;
            border-radius: 6px;
            transition: opacity 0.15s, background 0.15s;
        }
        .alert-close:hover {
            opacity: 1;
            background: rgba(232,160,32,0.15);
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
        .filter-input, .filter-select, .ci-select {
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
        .btn-danger { background: var(--danger); color: red; }
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
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .filters-grid { grid-template-columns: 1fr; }
            .data-table { font-size: 12px; }
            .data-table th, .data-table td { padding: 8px 10px; }
        }
    </style>

    @push('scripts')
<script>
// ══ MODAL FACTURATION RAPIDE ══
let _billCampaignId = null;

function openBillingModal(id, name, totalAmount, currentStatus, currentAmount, paidAt) {
    _billCampaignId = id;
    document.getElementById('bill-campaign-name').textContent = name;
    document.getElementById('bill-status').value = currentStatus || 'brouillon';
    document.getElementById('bill-amount').value = currentAmount ? parseInt(currentAmount.replace(/\s/g,'')) : Math.round(totalAmount);
    document.getElementById('bill-paid-at').value = paidAt || '';
    onBillStatusChange();
    document.getElementById('modal-billing').style.display = 'flex';
}

function closeBillingModal() {
    document.getElementById('modal-billing').style.display = 'none';
    _billCampaignId = null;
}

function onBillStatusChange() {
    const status = document.getElementById('bill-status').value;
    const group = document.getElementById('bill-paid-at-group');
    group.style.display = status === 'payee' ? 'block' : 'none';
    if (status === 'payee' && !document.getElementById('bill-paid-at').value) {
        document.getElementById('bill-paid-at').value = new Date().toISOString().split('T')[0];
    }
}

// Toast réutilisable pour la page campagnes (succès / erreur, avec lien optionnel)
function showCampaignToast(message, type = 'success', linkUrl = null, linkLabel = null) {
    let host = document.getElementById('campaign-toast-host');
    if (!host) {
        host = document.createElement('div');
        host.id = 'campaign-toast-host';
        host.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;';
        document.body.appendChild(host);
    }
    const colors = type === 'error'
        ? { bg: '#fee2e2', fg: '#991b1b', bd: '#fca5a5' }
        : { bg: '#dcfce7', fg: '#166534', bd: '#86efac' };

    const t = document.createElement('div');
    t.style.cssText = `padding:12px 16px;background:${colors.bg};color:${colors.fg};border:1px solid ${colors.bd};border-radius:10px;font-size:13px;font-weight:600;box-shadow:0 6px 16px rgba(0,0,0,.1);min-width:260px;max-width:420px;display:flex;flex-direction:column;gap:6px;`;

    const msg = document.createElement('div');
    msg.textContent = message;
    t.appendChild(msg);

    if (linkUrl && linkLabel) {
        const a = document.createElement('a');
        a.href = linkUrl;
        a.textContent = linkLabel + ' →';
        a.style.cssText = `color:${colors.fg};text-decoration:underline;font-size:12px;font-weight:700;`;
        t.appendChild(a);
    }

    host.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 4500);
    setTimeout(() => t.remove(), 4900);
}

async function submitBilling() {
    if (!_billCampaignId) return;
    const btn = document.getElementById('bill-submit-btn');
    btn.disabled = true;
    btn.textContent = '…';

    const body = new FormData();
    body.append('_method', 'PATCH');
    body.append('_token', document.querySelector('meta[name=csrf-token]')?.content || '{{ csrf_token() }}');
    body.append('status', document.getElementById('bill-status').value);
    body.append('amount_ttc', document.getElementById('bill-amount').value);
    const paidAt = document.getElementById('bill-paid-at').value;
    if (paidAt) body.append('paid_at', paidAt);

    try {
        const res = await fetch(`/admin/campaigns/${_billCampaignId}/billing-quick`, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body,
        });

        // Erreurs de validation (422) — Laravel renvoie {message, errors:{field:[...]}}
        if (res.status === 422) {
            const data = await res.json().catch(() => ({}));
            const firstError = data.errors
                ? Object.values(data.errors).flat()[0]
                : (data.message || 'Données invalides.');
            showCampaignToast(firstError, 'error');
            return;
        }

        const data = await res.json();
        if (!data.ok) {
            showCampaignToast(data.message || 'Erreur lors de la mise à jour.', 'error');
            return;
        }

        closeBillingModal();

        // Toast avec lien direct vers la fiche facture
        showCampaignToast(
            data.message || 'Facture mise à jour.',
            'success',
            data.invoice_url || null,
            'Voir la facture'
        );

        // Mise à jour in-place de la ligne — pas de fetchData() ni de
        // spinner, le bouton facturation reflète instantanément le nouveau
        // statut. Fallback sur fetchData() si le serveur n'a pas renvoyé
        // de row_html (compat anciennes réponses).
        if (data.row_html && data.campaign_id) {
            const oldRow = document.querySelector(`tr[data-campaign-row="${data.campaign_id}"]`);
            if (oldRow) {
                const wrapper = document.createElement('tbody');
                wrapper.innerHTML = data.row_html.trim();
                const newRow = wrapper.firstElementChild;
                if (newRow) oldRow.replaceWith(newRow);
            } else if (typeof fetchData === 'function') {
                fetchData();
            }
        } else if (typeof fetchData === 'function') {
            fetchData();
        }
    } catch(e) {
        console.error(e);
        showCampaignToast('Erreur réseau. Réessayez.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '✅ Enregistrer';
    }
}

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
        commune_id: '',
        zone_id: '',
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
        currentFilters.commune_id = urlParams.get('commune_id') || '';
        currentFilters.zone_id = urlParams.get('zone_id') || '';

        // Remplir les champs
        document.getElementById('filter-search').value = currentFilters.search;
        document.getElementById('filter-client').value = currentFilters.client_id;
        document.getElementById('filter-status').value = currentFilters.status;
        document.getElementById('filter-date-from').value = currentFilters.date_from;
        document.getElementById('filter-date-to').value = currentFilters.date_to;
        
        const factureSelect = document.getElementById('filter-facture');
        if (factureSelect) factureSelect.value = currentFilters.non_facturee;
        
        document.getElementById('filter-commune').value = currentFilters.commune_id;
        document.getElementById('filter-zone').value = currentFilters.zone_id;

        updateActiveStat();
        updateResetButton();

        // Événements
        document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
        document.getElementById('filter-client').addEventListener('change', applyFilters);
        document.getElementById('filter-status').addEventListener('change', applyFilters);
        document.getElementById('filter-date-from').addEventListener('change', applyFilters);
        document.getElementById('filter-date-to').addEventListener('change', applyFilters);
        
        const factureFilter = document.getElementById('filter-facture');
        if (factureFilter) factureFilter.addEventListener('change', applyFilters);
        
        document.getElementById('filter-commune').addEventListener('change', applyFilters);
        document.getElementById('filter-zone').addEventListener('change', applyFilters);
        
        // Cartes stats
        document.querySelectorAll('.stat-card').forEach(card => {
            card.addEventListener('click', (e) => {
                e.preventDefault();
                const filterValue = card.dataset.value;
                const statusSelect = document.getElementById('filter-status');
                statusSelect.value = filterValue;
                applyFilters();
            });
        });

        const resetBtn = document.getElementById('btn-reset');
        if (resetBtn) resetBtn.addEventListener('click', resetFilters);
        
        // Gestion historique navigateur
        window.addEventListener('popstate', function() {
            const params = new URLSearchParams(window.location.search);
            currentFilters.search = params.get('search') || '';
            currentFilters.client_id = params.get('client_id') || '';
            currentFilters.status = params.get('status') || '';
            currentFilters.date_from = params.get('date_from') || '';
            currentFilters.date_to = params.get('date_to') || '';
            currentFilters.commune_id = params.get('commune_id') || '';
            currentFilters.zone_id = params.get('zone_id') || '';
            
            // Mettre à jour les champs
            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-client').value = currentFilters.client_id;
            document.getElementById('filter-status').value = currentFilters.status;
            document.getElementById('filter-date-from').value = currentFilters.date_from;
            document.getElementById('filter-date-to').value = currentFilters.date_to;
            document.getElementById('filter-commune').value = currentFilters.commune_id;
            document.getElementById('filter-zone').value = currentFilters.zone_id;
            
            updateResetButton();
            updateActiveStat();
            fetchData();
        });
    }

    function applyFilters() {
        currentFilters.search = document.getElementById('filter-search').value;
        currentFilters.client_id = document.getElementById('filter-client').value;
        currentFilters.status = document.getElementById('filter-status').value;
        currentFilters.date_from = document.getElementById('filter-date-from').value;
        currentFilters.date_to = document.getElementById('filter-date-to').value;
        
        const factureSelect = document.getElementById('filter-facture');
        currentFilters.non_facturee = factureSelect ? factureSelect.value : '';
        
        currentFilters.commune_id = document.getElementById('filter-commune').value;
        currentFilters.zone_id = document.getElementById('filter-zone').value;
        currentFilters.page = 1;

        updateResetButton();
        updateActiveStat();
        fetchData();
    }

    function resetFilters() {
        currentFilters = {
            search: '', client_id: '', status: '', 
            date_from: '', date_to: '', non_facturee: '',
            commune_id: '', zone_id: '', page: 1
        };
        
        document.getElementById('filter-search').value = '';
        document.getElementById('filter-client').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-date-from').value = '';
        document.getElementById('filter-date-to').value = '';
        
        const factureSelect = document.getElementById('filter-facture');
        if (factureSelect) factureSelect.value = '';
        
        document.getElementById('filter-commune').value = '';
        document.getElementById('filter-zone').value = '';

        updateResetButton();
        updateActiveStat();
        fetchData();
    }

    function updateActiveStat() {
        const activeStatus = currentFilters.status;
        document.querySelectorAll('.stat-card').forEach(card => {
            const cardValue = card.dataset.value;
            if ((!activeStatus && !cardValue) || (cardValue === activeStatus)) {
                card.classList.add('active');
            } else {
                card.classList.remove('active');
            }
        });
    }

    function updateResetButton() {
        const hasFilters = currentFilters.search || currentFilters.client_id || 
                           currentFilters.status || currentFilters.date_from || 
                           currentFilters.date_to || currentFilters.non_facturee ||
                           currentFilters.commune_id || currentFilters.zone_id;
        const resetWrapper = document.getElementById('reset-wrapper');
        if (resetWrapper) {
            resetWrapper.style.display = hasFilters ? 'flex' : 'none';
        }
    }

    async function fetchData() {
        if (isLoading) return;
        isLoading = true;
        
        // Afficher un indicateur de chargement
        const tableBody = document.getElementById('table-body');
        const originalHtml = tableBody.innerHTML;
        tableBody.innerHTML = '<tr><td colspan="10" class="text-center"><div class="spinner"></div> Chargement...</td></tr>';
        
        const params = new URLSearchParams();
        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
        if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
        if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
        params.set('ajax', '1');
        params.set('page', currentFilters.page);

        try {
            const response = await fetch(`${currentUrl}?${params}`, {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) throw new Error('Erreur réseau');
            
            const data = await response.json();
            document.getElementById('table-body').innerHTML = data.html;
            document.getElementById('pagination-container').innerHTML = data.pagination;
            
            if (data.stats && data.stats.total !== undefined) {
                document.getElementById('total-count').textContent = data.stats.total + ' résultat(s)';

                // Met à jour le KPI "Total" (carte data-value vide = 'all')
                document.querySelectorAll('.stat-card').forEach(card => {
                    const numEl = card.querySelector('.stat-number');
                    if (!numEl) return;
                    const v = card.dataset.value;
                    if (!v) {
                        // Carte 'all' = total filtré
                        numEl.textContent = data.stats.total;
                    } else if (data.stats.counts && data.stats.counts[v] !== undefined) {
                        numEl.textContent = data.stats.counts[v];
                    }
                });
            }
            
            // Mettre à jour l'URL sans recharger
            const newUrl = buildUrl();
            window.history.pushState({}, '', newUrl);
            
            // Réattacher les événements aux nouveaux boutons
            attachPaginationEvents();
            
        } catch (error) {
            console.error('Erreur:', error);
            tableBody.innerHTML = '<tr><td colspan="10" class="text-center text-red">Erreur de chargement</td></tr>';
        } finally {
            isLoading = false;
        }
    }
    
    function attachPaginationEvents() {
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = new URL(this.href);
                const page = url.searchParams.get('page');
                if (page) {
                    currentFilters.page = parseInt(page);
                    fetchData();
                }
            });
        });
    }

    function buildUrl() {
        const params = new URLSearchParams();
        if (currentFilters.search) params.set('search', currentFilters.search);
        if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
        if (currentFilters.status) params.set('status', currentFilters.status);
        if (currentFilters.date_from) params.set('date_from', currentFilters.date_from);
        if (currentFilters.date_to) params.set('date_to', currentFilters.date_to);
        if (currentFilters.commune_id) params.set('commune_id', currentFilters.commune_id);
        if (currentFilters.zone_id) params.set('zone_id', currentFilters.zone_id);
        if (currentFilters.non_facturee) params.set('non_facturee', currentFilters.non_facturee);
        if (currentFilters.page > 1) params.set('page', currentFilters.page);
        
        const query = params.toString();
        return query ? `${currentUrl}?${query}` : currentUrl;
    }

    function debounce(func, wait) {
        return function(...args) {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => func(...args), wait);
        };
    }

    // Démarrer
    init();
})();
</script>

<style>
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
    margin-right: 8px;
    vertical-align: middle;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.text-center { text-align: center; }
.text-red { color: #ef4444; }
</style>
@endpush
</x-admin-layout>
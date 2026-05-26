<x-admin-layout title="Réservations">
    <x-slot:topbarActions>
        @can('create', App\Models\Reservation::class)
            <a href="{{ route('admin.reservations.disponibilites') }}" class="btn btn-primary">
                + Nouvelle réservation
            </a>
        @endcan
    </x-slot:topbarActions>

    {{-- ══ KPI cards — design unifié ══ --}}
    @php
    $statCards = [
        ['key'=>'total',      'label'=>'Total',      'sub'=>'toutes réservations',  'color'=>'var(--accent)', 'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="9" y1="3" x2="9" y2="21"/></svg>'],
        ['key'=>'en_attente', 'label'=>'En option',  'sub'=>'en attente de confirmation', 'color'=>'#f97316', 'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
        ['key'=>'confirme',   'label'=>'Confirmées', 'sub'=>'validées par le client','color'=>'#22c55e',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>'],
        ['key'=>'termine',    'label'=>'Terminées',  'sub'=>'campagnes achevées',    'color'=>'#3b82f6',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>'],
        ['key'=>'refuse',     'label'=>'Refusées',   'sub'=>'refus client',          'color'=>'#ef4444',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'],
        ['key'=>'annule',     'label'=>'Annulées',   'sub'=>'annulations internes',  'color'=>'#6b7280',       'icon'=>'<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>'],
    ];
    $activeStatus = request('status');
    $assignedMe   = request()->boolean('assigned_me');
    $hasAnyFilter = request('search') || request('status') || request('type') || request('client_id') || request('periode') || $assignedMe;
    @endphp
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:20px">
        @foreach($statCards as $sc)
        @php
            $isTotal  = $sc['key'] === 'total';
            // is-active uniquement quand l'utilisateur a cliqué sur un statut
            // précis (pas sur "Total" par défaut).
            $isActive = !$isTotal && $activeStatus === $sc['key'];
        @endphp
        <a href="#" class="kpi-card {{ $isActive ? 'is-active' : '' }}"
           data-kpi="{{ $sc['key'] }}" data-value="{{ $isTotal ? '' : $sc['key'] }}"
           style="--kpi-color:{{ $sc['color'] }};{{ $isActive ? 'border-color:'.$sc['color'].';' : '' }}"
           onmouseenter="if(!this.classList.contains('is-active')){this.style.borderColor='{{ $sc['color'] }}';this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,.12)'}"
           onmouseleave="if(!this.classList.contains('is-active')){this.style.borderColor='';this.style.transform='';this.style.boxShadow=''}">
            <div class="kpi-card__top-bar" style="background:{{ $sc['color'] }}"></div>
            <div class="kpi-card__icon" style="color:{{ $sc['color'] }}">{!! $sc['icon'] !!}</div>
            <div class="kpi-card__value" data-kpi-value="{{ $sc['key'] }}" style="color:{{ $sc['color'] }}">{{ $counts[$sc['key']] ?? 0 }}</div>
            <div class="kpi-card__label">{{ $sc['label'] }}</div>
            <div class="kpi-card__sub">{{ $sc['sub'] }}</div>
            @if($sc['key'] === 'en_attente' && ($newCount ?? 0) > 0)
                <div class="kpi-card__sub" style="color:#f97316;font-weight:700">✦ {{ $newCount }} nouvelle(s)</div>
            @endif
            <div class="kpi-card__arrow" style="color:{{ $sc['color'] }}">→</div>
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

            {{-- Filtre "Mes réservations" : visible UNIQUEMENT pour admin / MP.
                 Le commercial voit déjà uniquement ses dossiers via le RBAC
                 serveur (ReservationController::index utilise forCommercialUser),
                 donc le filtre serait redondant pour lui. --}}
            @if(in_array(auth()->user()?->role?->value, ['admin', 'mediaplanner']))
            <div class="filter-group">
                <label class="filter-label">👤 Mes réservations</label>
                <button type="button" id="filter-assigned-me-btn"
                        data-active="{{ $assignedMe ? '1' : '0' }}"
                        title="Filtrer pour n'afficher que les réservations dont je suis le commercial assigné ou le créateur"
                        style="display:inline-flex;align-items:center;gap:8px;height:38px;cursor:pointer;padding:0 14px;border-radius:10px;font-size:13px;font-weight:600;white-space:nowrap;transition:all .15s;
                               border:1px solid {{ $assignedMe ? 'var(--accent)' : 'var(--border2)' }};
                               background:{{ $assignedMe ? 'var(--accent)' : 'var(--surface2)' }};
                               color:{{ $assignedMe ? '#fff' : 'var(--text2)' }};">
                    <span>{{ $assignedMe ? '✓' : '○' }}</span>
                    <span>Affectées à moi</span>
                </button>
                {{-- Input caché pour préserver le pattern JS existant qui lit
                     ['filter-assigned-me'].checked. Le bouton ci-dessus pilote
                     ce champ. --}}
                <input type="checkbox" id="filter-assigned-me" data-filter="assigned_me"
                       {{ $assignedMe ? 'checked' : '' }} style="display:none">
            </div>
            @endif

            <div class="filter-group" id="reset-wrapper" style="display:none;">
                <label class="filter-label" style="visibility:hidden;">Actions</label>
                <button id="btn-reset" class="reset-btn">↺ Réinitialiser</button>
            </div>
        </div>
    </div>

    {{-- ══ TABLEAU DES RÉSERVATIONS ══ --}}
    @php
        $canBulk = in_array(auth()->user()->role?->value, ['admin', 'mediaplanner']);
    @endphp
    <div class="card">
        {{-- En-tête normal — visible quand aucune sélection. --}}
        <div class="card-header" id="resa-card-header-default">
            <span class="card-title">Réservations</span>
            <div class="stats-info">
                <span id="total-count" class="total-count">{{ $reservations->total() }} résultat(s)</span>
                @if(($newCount ?? 0) > 0)
                <span class="new-badge">✦ {{ $newCount }} nouvelle(s)</span>
                @endif
                <span id="poll-indicator" style="font-size:11px;color:var(--text3);margin-left:8px;" title="Actualisation automatique toutes les 30s"></span>
            </div>
        </div>

        @if($canBulk)
        {{-- Toolbar d'actions inline — apparaît à la place du card-header
             quand au moins 1 réservation est cochée. Pattern Gmail :
             actions visibles en haut, jamais de barre flottante. --}}
        <div id="resa-bulk-toolbar" class="bulk-toolbar">
            <div class="bulk-toolbar-left">
                <button type="button" id="resa-bulk-clear"
                        class="bulk-clear-btn"
                        title="Tout désélectionner">✕</button>
                <span class="bulk-count-label">
                    <strong id="resa-bulk-count">0</strong>
                    <span id="resa-bulk-count-noun">réservation sélectionnée</span>
                </span>
            </div>
            <div class="bulk-actions">
                <button type="button" onclick="bulkResa('cancel')" class="bulk-action-btn danger-soft">
                    🚫 Annuler
                </button>
                <button type="button" onclick="bulkResa('delete')" class="bulk-action-btn danger-solid">
                    🗑 Supprimer
                </button>
            </div>
        </div>
        @endif

        <div class="table-responsive">
            <table class="data-table" id="reservations-table">
                <thead>
                        <th style="width:60px;text-align:center;padding:0;">
                            @if($canBulk)
                                {{-- Combo Gmail : checkbox + dropdown ⌄ avec fond hover --}}
                                <div class="bulk-header-combo">
                                    <input type="checkbox" data-bulk-select-all aria-label="Tout sélectionner" id="resa-select-all-input">
                                    <button type="button" id="resa-select-dropdown-btn"
                                            class="bulk-dropdown-btn"
                                            aria-label="Options de sélection"
                                            title="Options de sélection">▾</button>
                                    <div id="resa-select-dropdown-menu" class="bulk-dropdown-menu">
                                        <button type="button" data-select-mode="all" class="resa-select-option">Toutes (page)</button>
                                        <button type="button" data-select-mode="none" class="resa-select-option">Aucune</button>
                                        <div class="resa-select-divider"></div>
                                        <button type="button" data-select-mode="en_attente" class="resa-select-option">⏳ En attente</button>
                                        <button type="button" data-select-mode="confirme" class="resa-select-option">✅ Confirmées</button>
                                        <button type="button" data-select-mode="annule" class="resa-select-option">🚫 Annulées</button>
                                        <div class="resa-select-divider"></div>
                                        <button type="button" data-select-mode="invert" class="resa-select-option">↔ Inverser</button>
                                    </div>
                                </div>
                            @else
                                <input type="checkbox" data-bulk-select-all aria-label="Tout sélectionner" id="resa-select-all-input">
                            @endif
                        </th>
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

        @if($canBulk)
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ── Pattern Gmail-style : toolbar inline (pas de barre flottante)
            //   - checkbox + dropdown ⌄ dans le header de colonne
            //   - toolbar d'actions au-dessus du tableau quand sélection > 0
            //   - le card-header normal est masqué pendant la sélection
            const tableBody    = document.querySelector('#reservations-table tbody');
            const checkAll     = document.getElementById('resa-select-all-input');
            const toolbar      = document.getElementById('resa-bulk-toolbar');
            const cardHeader   = document.getElementById('resa-card-header-default');
            const countEl      = document.getElementById('resa-bulk-count');
            const nounEl       = document.getElementById('resa-bulk-count-noun');
            const clearBtn     = document.getElementById('resa-bulk-clear');
            const dropdownBtn  = document.getElementById('resa-select-dropdown-btn');
            const dropdownMenu = document.getElementById('resa-select-dropdown-menu');

            function checkboxes() {
                return Array.from(document.querySelectorAll('#reservations-table .bulk-checkbox'));
            }
            function selectedIds() {
                return checkboxes()
                    .filter(cb => cb.checked)
                    .map(cb => parseInt(cb.value, 10))
                    .filter(Boolean);
            }
            function refreshState() {
                const total    = checkboxes().length;
                const selected = selectedIds().length;
                // Bascule toolbar / card-header — l'un OU l'autre, jamais les deux.
                if (selected > 0) {
                    toolbar.classList.add('visible');
                    cardHeader.style.display = 'none';
                    countEl.textContent = selected;
                    nounEl.textContent  = selected > 1 ? 'réservations sélectionnées' : 'réservation sélectionnée';
                } else {
                    toolbar.classList.remove('visible');
                    cardHeader.style.display = 'flex';
                }
                // Highlight des lignes cochées (effet visuel feedback immédiat)
                checkboxes().forEach(cb => {
                    cb.closest('tr')?.classList.toggle('bulk-selected', cb.checked);
                });
                // État checkbox "tout"
                if (checkAll) {
                    if (selected === 0)               { checkAll.checked = false; checkAll.indeterminate = false; }
                    else if (selected === total)      { checkAll.checked = true;  checkAll.indeterminate = false; }
                    else                              { checkAll.checked = false; checkAll.indeterminate = true;  }
                }
            }

            // Checkbox d'en-tête : toggle global
            if (checkAll) {
                checkAll.addEventListener('change', () => {
                    const should = checkAll.checked;
                    checkboxes().forEach(cb => { cb.checked = should; });
                    refreshState();
                });
            }

            // Checkboxes des lignes : event delegation pour survivre aux
            // refresh AJAX du tbody (filtres, polling 30 s).
            tableBody?.addEventListener('change', e => {
                if (e.target?.classList?.contains('bulk-checkbox')) refreshState();
            });

            // Bouton ✕ Désélectionner
            clearBtn?.addEventListener('click', () => {
                checkboxes().forEach(cb => { cb.checked = false; });
                refreshState();
            });

            // Dropdown ⌄ Options de sélection — utilise une classe .open
            // (au lieu d'inline display) pour rester compatible avec le CSS.
            dropdownBtn?.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownMenu.classList.toggle('open');
            });
            document.addEventListener('click', (e) => {
                if (!dropdownMenu) return;
                if (!dropdownMenu.contains(e.target) && e.target !== dropdownBtn) {
                    dropdownMenu.classList.remove('open');
                }
            });
            dropdownMenu?.addEventListener('click', (e) => {
                const opt = e.target.closest('[data-select-mode]');
                if (!opt) return;
                const mode = opt.dataset.selectMode;
                checkboxes().forEach(cb => {
                    const row = cb.closest('tr');
                    const status = row?.dataset?.status ?? '';
                    switch (mode) {
                        case 'all':        cb.checked = true;  break;
                        case 'none':       cb.checked = false; break;
                        case 'invert':     cb.checked = !cb.checked; break;
                        case 'en_attente': cb.checked = (status === 'en_attente'); break;
                        case 'confirme':   cb.checked = (status === 'confirme');   break;
                        case 'annule':     cb.checked = (status === 'annule');     break;
                    }
                });
                dropdownMenu.classList.remove('open');
                refreshState();
            });

            // Actions groupées (annuler / supprimer)
            window.bulkResa = async function(action) {
                const ids = selectedIds();
                if (ids.length === 0) return;
                const verb = action === 'cancel' ? 'annuler' : 'SUPPRIMER';
                const reason = action === 'cancel'
                    ? prompt(`Motif d'annulation (optionnel) pour ${ids.length} réservation(s) :`)
                    : null;
                if (action === 'cancel' && reason === null) return;
                if (!confirm(`Confirmer : ${verb} ${ids.length} réservation(s) ? Cette action est irréversible.`)) return;

                const tok = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                try {
                    const res = await fetch('{{ route('admin.reservations.bulk') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': tok,
                        },
                        body: JSON.stringify({ action, ids, cancel_reason: reason ?? '' }),
                    });
                    if (res.ok) location.reload();
                    else {
                        const data = await res.json().catch(() => ({}));
                        alert('Erreur : ' + (data.message || res.statusText));
                    }
                } catch (e) {
                    alert('Erreur réseau : ' + e.message);
                }
            };

            // Re-sync après chaque mutation du tbody (refresh AJAX filtres/polling)
            if (tableBody) {
                new MutationObserver(refreshState).observe(tableBody, { childList: true, subtree: true });
            }
            refreshState();
        });
        </script>
        @endif

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

    {{-- ══════════════════════════════════════════════════════
         MODAL "VOIR LES PANNEAUX" — chargée en AJAX (design moderne)
    ══════════════════════════════════════════════════════ --}}
    <div id="modal-panels" class="modal-overlay" style="display:none;" onclick="closePanelsModal(event)">
        <div class="modal panels-modal" onclick="event.stopPropagation()">
            <div class="modal-header" style="background:linear-gradient(135deg, var(--surface2), var(--surface));">
                <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;">
                    <span style="font-size:24px;flex-shrink:0;">🪧</span>
                    <div style="min-width:0;">
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1.2px;color:var(--text3);font-weight:600;">Panneaux liés</div>
                        <h3 class="modal-title" style="margin:2px 0 0;font-size:18px;">
                            Réservation <span id="panels-modal-ref" style="color:var(--accent);font-family:monospace;"></span>
                        </h3>
                    </div>
                </div>
                <button class="modal-close" onclick="closePanelsModal()" type="button" aria-label="Fermer">✕</button>
            </div>

            <div class="panels-modal-meta" id="panels-modal-meta"></div>

            <div class="modal-body panels-modal-body">
                {{-- Loading state --}}
                <div id="panels-modal-loading" class="panels-state">
                    <div class="panels-spinner"></div>
                    <div style="margin-top:12px;font-size:13px;color:var(--text3);">Chargement des panneaux…</div>
                </div>

                {{-- Grid (rempli par JS) --}}
                <div id="panels-modal-grid" class="panels-grid" style="display:none;"></div>

                {{-- Empty state --}}
                <div id="panels-modal-empty" class="panels-state" style="display:none;">
                    <div style="font-size:64px;opacity:.4;">🪧</div>
                    <div style="margin-top:8px;font-weight:600;color:var(--text2);">Aucun panneau lié</div>
                    <div style="margin-top:4px;font-size:12px;color:var(--text3);">Cette réservation n'a pas encore de panneau associé.</div>
                </div>
            </div>

            <div class="modal-footer" style="justify-content:space-between;">
                <div id="panels-modal-total" style="font-size:13px;color:var(--text2);"></div>
                <button type="button" onclick="closePanelsModal()" class="btn btn-ghost">Fermer</button>
            </div>
        </div>
    </div>

    <style>
        /* ═══ MODALE PANNEAUX — design moderne ═══ */
        .panels-modal {
            max-width: 960px;
            width: 100%;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        .panels-modal-meta {
            padding: 12px 24px;
            background: var(--surface2);
            border-bottom: 1px solid var(--border);
            font-size: 12px;
            color: var(--text2);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
        }
        .panels-modal-meta .meta-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--surface);
            border: 1px solid var(--border);
            font-size: 11px;
            font-weight: 600;
        }
        .panels-modal-meta .meta-chip.status-en-attente { color: #f97316; border-color: rgba(249,115,22,.4); background: rgba(249,115,22,.08); }
        .panels-modal-meta .meta-chip.status-confirme   { color: #22c55e; border-color: rgba(34,197,94,.4);  background: rgba(34,197,94,.08); }
        .panels-modal-meta .meta-chip.status-annule     { color: #ef4444; border-color: rgba(239,68,68,.4);  background: rgba(239,68,68,.08); }
        .panels-modal-meta .meta-chip.status-termine    { color: #6b7280; border-color: rgba(107,114,128,.4);background: rgba(107,114,128,.08); }

        .panels-modal-body {
            flex: 1;
            overflow-y: auto;
            padding: 18px 24px;
            background: var(--surface);
        }

        .panels-state {
            text-align: center;
            padding: 60px 20px;
        }
        .panels-spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid var(--accent);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .panels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 14px;
        }
        .panel-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--surface2);
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
            display: flex;
            flex-direction: column;
        }
        .panel-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent);
            box-shadow: 0 12px 28px rgba(0,0,0,.18);
        }
        .panel-card-photo {
            position: relative;
            height: 140px;
            background: var(--surface3);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .panel-card-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .panel-card-photo .photo-fallback {
            font-size: 36px;
            opacity: .5;
        }
        .panel-card-photo .lit-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(232,160,32,0.95);
            color: #000;
            font-size: 10px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            box-shadow: 0 2px 6px rgba(0,0,0,.3);
        }
        .panel-card-body { padding: 12px 14px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
        .panel-card-ref  {
            font-family: monospace;
            font-weight: 700;
            font-size: 13px;
            color: var(--accent);
            letter-spacing: .5px;
        }
        .panel-card-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .panel-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px;
        }
        .panel-card-rate {
            margin-top: auto;
            padding-top: 8px;
            border-top: 1px dashed var(--border);
            font-size: 12px;
            font-weight: 700;
            color: var(--accent);
        }

        @media (max-width: 640px) {
            .panels-grid { grid-template-columns: 1fr; }
            .panels-modal-meta { padding: 10px 14px; font-size: 11px; }
            .panels-modal-body { padding: 14px; }
        }
    </style>

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
        .data-table tr.bulk-selected td { background: var(--accent-dim) !important; }
        .data-table tr.bulk-selected td:first-of-type { box-shadow: inset 3px 0 0 var(--accent); }

        /* ── Sélection multiple type Gmail ──────────────────────────────
           Les checkboxes natives sont invisibles sur fond sombre. On les
           grossit + accent-color pour les rendre lisibles, et on ajoute
           une zone de clic généreuse autour. */
        .bulk-checkbox,
        #resa-select-all-input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent);
            cursor: pointer;
            margin: 0;
            vertical-align: middle;
        }
        .bulk-cell {
            text-align: center;
            padding: 0 4px;
            cursor: pointer; /* clic n'importe où dans la cellule = toggle */
        }
        .bulk-cell:hover .bulk-checkbox { outline: 2px solid var(--accent); outline-offset: 1px; border-radius: 3px; }

        /* Combo header : [☑] [⌄] aligné, fond hover marqué */
        .bulk-header-combo {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 4px 6px;
            border-radius: 6px;
            position: relative;
        }
        .bulk-header-combo:hover { background: var(--surface3); }
        .bulk-dropdown-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text2);
            font-size: 13px;
            padding: 2px 4px;
            line-height: 1;
            border-radius: 4px;
            transition: color .12s, background .12s;
        }
        .bulk-dropdown-btn:hover { color: var(--text); background: var(--surface); }
        .bulk-dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            margin-top: 4px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.18);
            min-width: 190px;
            z-index: 50;
            text-align: left;
            overflow: hidden;
        }
        .bulk-dropdown-menu.open { display: block; }
        .resa-select-option {
            display: block;
            width: 100%;
            text-align: left;
            padding: 9px 14px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            color: var(--text);
            font-family: inherit;
            transition: background .12s;
        }
        .resa-select-option:hover { background: var(--surface2); }
        .resa-select-divider {
            height: 1px;
            background: var(--border);
            margin: 4px 0;
        }

        /* Toolbar bulk : apparition sliding, fond accent doux pour signaler le mode sélection */
        .bulk-toolbar {
            display: none;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 12px 18px;
            background: var(--accent-dim);
            border-bottom: 1px solid var(--accent);
            animation: bulkSlideDown .16s ease-out;
        }
        .bulk-toolbar.visible { display: flex; }
        @keyframes bulkSlideDown {
            from { opacity: 0; transform: translateY(-4px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .bulk-toolbar-left { display: flex; align-items: center; gap: 10px; }
        .bulk-clear-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text2);
            font-size: 18px;
            padding: 4px 10px;
            border-radius: 6px;
            line-height: 1;
            transition: background .12s, color .12s;
        }
        .bulk-clear-btn:hover { background: var(--surface); color: var(--text); }
        .bulk-count-label { font-size: 14px; font-weight: 600; color: var(--text); }
        .bulk-count-label strong { color: var(--accent); font-size: 16px; margin-right: 2px; }
        .bulk-actions { display: flex; gap: 8px; }
        .bulk-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid;
            transition: background .12s, transform .08s;
            font-family: inherit;
        }
        .bulk-action-btn:active { transform: translateY(1px); }
        .bulk-action-btn.danger-soft {
            border-color: rgba(239,68,68,.35);
            background: var(--surface);
            color: #ef4444;
        }
        .bulk-action-btn.danger-soft:hover { background: rgba(239,68,68,.12); }
        .bulk-action-btn.danger-solid {
            border-color: #ef4444;
            background: #ef4444;
            color: #fff;
        }
        .bulk-action-btn.danger-solid:hover { background: #dc2626; }

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
        .btn-danger { background: var(--danger); color: #696666; }
        .btn-danger:hover { background: #dc2626; color: #fff; transform: translateY(-1px); }
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

    // ─── Modale "Voir les panneaux" — design moderne ──────────────
    const PANEL_PLACEHOLDER = '/images/panel-placeholder.svg';

    const STATUS_LABELS = {
        en_attente: { label: 'En option',   class: 'status-en-attente' },
        confirme:   { label: 'Confirmée',   class: 'status-confirme'   },
        annule:     { label: 'Annulée',     class: 'status-annule'     },
        refuse:     { label: 'Refusée',     class: 'status-annule'     },
        termine:    { label: 'Terminée',    class: 'status-termine'    },
    };

    async function openPanelsModal(reservationId, reference) {
        const modal   = document.getElementById('modal-panels');
        const loading = document.getElementById('panels-modal-loading');
        const grid    = document.getElementById('panels-modal-grid');
        const empty   = document.getElementById('panels-modal-empty');
        const meta    = document.getElementById('panels-modal-meta');
        const totalEl = document.getElementById('panels-modal-total');

        document.getElementById('panels-modal-ref').textContent = reference;
        loading.style.display = 'block';
        grid.style.display    = 'none';
        empty.style.display   = 'none';
        grid.innerHTML        = '';
        meta.innerHTML        = '';
        totalEl.textContent   = '';
        modal.style.display   = 'flex';

        try {
            const url  = `/admin/reservations/${reservationId}/panels-list`;
            const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            loading.style.display = 'none';

            // ─── Méta-info enrichie en chips colorés ───
            const r = data.reservation;
            const st = STATUS_LABELS[r.status] || { label: r.status || '—', class: '' };
            meta.innerHTML = `
                <span class="meta-chip ${st.class}">${st.label}</span>
                <span class="meta-chip">📅 ${r.start_date} → ${r.end_date}</span>
                <span class="meta-chip">🪧 ${r.count} panneau${r.count > 1 ? 'x' : ''}</span>
            `;

            if (!data.panels.length) {
                empty.style.display = 'block';
                return;
            }

            // ─── Grille de cards modernes ───
            grid.style.display = 'grid';
            grid.innerHTML = data.panels.map(p => `
                <div class="panel-card">
                    <div class="panel-card-photo">
                        ${p.photo_url
                            ? `<img src="${p.photo_url}" alt="${escapeAttr(p.reference)}" loading="lazy"
                                  onerror="this.onerror=null;this.outerHTML='<span class=&quot;photo-fallback&quot;>🪧</span>';">`
                            : `<span class="photo-fallback">🪧</span>`}
                        ${p.is_lit ? '<span class="lit-badge">💡 LED</span>' : ''}
                    </div>
                    <div class="panel-card-body">
                        <div class="panel-card-ref">${escapeHtml(p.reference)}</div>
                        <div class="panel-card-name" title="${escapeAttr(p.name)}">${escapeHtml(p.name)}</div>
                        <div class="panel-card-meta">
                            <span>📍 ${escapeHtml(p.commune)}</span>
                            <span>📏 ${escapeHtml(p.format)}</span>
                        </div>
                        <div class="panel-card-rate">
                            ${Number(p.monthly_rate || 0).toLocaleString('fr-FR')} FCFA/mois
                        </div>
                    </div>
                </div>
            `).join('');

            // ─── Total dans le footer ───
            const total = data.panels.reduce((s, p) => s + Number(p.monthly_rate || 0), 0);
            totalEl.innerHTML = `Total mensuel : <strong style="color:var(--accent);">${total.toLocaleString('fr-FR')} FCFA</strong>`;

        } catch (e) {
            loading.style.display = 'none';
            empty.style.display = 'block';
            empty.innerHTML = `
                <div style="font-size:64px;opacity:.5;">⚠️</div>
                <div style="margin-top:8px;font-weight:600;color:var(--text2);">Erreur de chargement</div>
                <div style="margin-top:4px;font-size:12px;color:var(--text3);">Impossible de charger les panneaux. Réessayez ou rafraîchissez la page.</div>
            `;
        }
    }

    function closePanelsModal(e) {
        if (!e || e.target === document.getElementById('modal-panels') || e.target.closest('.modal-close')) {
            document.getElementById('modal-panels').style.display = 'none';
        }
    }

    // Fermer avec Échap
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closePanelsModal();
    });

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function escapeAttr(s) { return escapeHtml(s).replace(/`/g, '&#96;'); }
    
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
        let pollingTimer = null;
        let isPolling = false;

        function init() {
            const urlParams = new URLSearchParams(window.location.search);
            currentFilters.search = urlParams.get('search') || '';
            currentFilters.status = urlParams.get('status') || '';
            currentFilters.type = urlParams.get('type') || '';
            currentFilters.client_id = urlParams.get('client_id') || '';
            currentFilters.periode = urlParams.get('periode') || '';
            currentFilters.assigned_me = urlParams.get('assigned_me') === '1' ? '1' : '';

            document.getElementById('filter-search').value = currentFilters.search;
            document.getElementById('filter-status').value = currentFilters.status;
            document.getElementById('filter-type').value = currentFilters.type;
            document.getElementById('filter-client').value = currentFilters.client_id;
            document.getElementById('filter-periode').value = currentFilters.periode;
            const assignedEl = document.getElementById('filter-assigned-me');
            const assignedBtn = document.getElementById('filter-assigned-me-btn');
            if (assignedEl) assignedEl.checked = !!currentFilters.assigned_me;
            syncAssignedBtnUI();

            updateActiveStat();
            updateResetButton();

            document.getElementById('filter-search').addEventListener('input', debounce(applyFilters, 400));
            document.getElementById('filter-status').addEventListener('change', applyFilters);
            document.getElementById('filter-type').addEventListener('change', applyFilters);
            document.getElementById('filter-client').addEventListener('change', applyFilters);
            document.getElementById('filter-periode').addEventListener('change', applyFilters);
            if (assignedEl) assignedEl.addEventListener('change', () => { syncAssignedBtnUI(); applyFilters(); });
            // Pilotage du bouton toggle "Mes à traiter" → met à jour le
            // checkbox caché et déclenche applyFilters via le change event.
            if (assignedBtn) {
                assignedBtn.addEventListener('click', () => {
                    if (!assignedEl) return;
                    assignedEl.checked = !assignedEl.checked;
                    assignedEl.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            function syncAssignedBtnUI() {
                if (!assignedBtn || !assignedEl) return;
                const active = !!assignedEl.checked;
                assignedBtn.dataset.active = active ? '1' : '0';
                assignedBtn.style.background  = active ? 'var(--accent)' : 'var(--surface2)';
                assignedBtn.style.borderColor = active ? 'var(--accent)' : 'var(--border2)';
                assignedBtn.style.color       = active ? '#fff' : 'var(--text2)';
                const ic = assignedBtn.querySelector('span:first-child');
                if (ic) ic.textContent = active ? '✓' : '○';
            }
            
            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
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
            const assignedEl = document.getElementById('filter-assigned-me');
            currentFilters.assigned_me = assignedEl?.checked ? '1' : '';
            currentFilters.page = 1;

            updateResetButton();
            updateActiveStat();
            fetchData();
        }

        function resetFilters() {
            currentFilters = { search: '', status: '', type: '', client_id: '', periode: '', assigned_me: '', page: 1 };
            document.getElementById('filter-search').value = '';
            document.getElementById('filter-status').value = '';
            document.getElementById('filter-type').value = '';
            document.getElementById('filter-client').value = '';
            document.getElementById('filter-periode').value = '';
            const assignedEl = document.getElementById('filter-assigned-me');
            if (assignedEl) assignedEl.checked = false;

            updateResetButton();
            updateActiveStat();
            fetchData();
        }

        function updateActiveStat() {
            const activeStatus = currentFilters.status;
            document.querySelectorAll('.kpi-card[data-kpi]').forEach(card => {
                const cardValue = card.dataset.value;
                // is-active UNIQUEMENT quand un statut précis est filtré ET que
                // la card correspond. La card "Total" (cardValue='') reste neutre.
                if (activeStatus && cardValue === activeStatus) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
                }
            });
        }

        function updateResetButton() {
            const hasFilters = currentFilters.search || currentFilters.status ||
                               currentFilters.type || currentFilters.client_id ||
                               currentFilters.periode || currentFilters.assigned_me;
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
                if (currentFilters.assigned_me) params.set('assigned_me', '1');
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

                    // Réinitialiser le timer de polling après un fetch manuel
                    startPolling();

                } catch (error) {
                    console.error('Erreur:', error);
                    document.getElementById('table-body').innerHTML = '<tr><td colspan="10" class="text-center py-10 text-red-500">❌ Erreur de chargement</td></tr>';
                } finally {
                    isLoading = false;
                }
            }

        async function fetchDataSilent() {
            if (isLoading || isPolling) return;
            isPolling = true;
            const params = new URLSearchParams();
            if (currentFilters.search) params.set('search', currentFilters.search);
            if (currentFilters.status) params.set('status', currentFilters.status);
            if (currentFilters.type) params.set('type', currentFilters.type);
            if (currentFilters.client_id) params.set('client_id', currentFilters.client_id);
            if (currentFilters.periode) params.set('periode', currentFilters.periode);
            if (currentFilters.assigned_me) params.set('assigned_me', '1');
            params.set('ajax', '1');
            try {
                const response = await fetch(`${currentUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) return;
                const data = await response.json();
                document.getElementById('table-body').innerHTML = data.html;
                const paginationContainer = document.getElementById('pagination-container');
                if (paginationContainer && data.pagination) paginationContainer.innerHTML = data.pagination;
                if (data.stats) updateStats(data.stats);
                const now = new Date();
                const hh = now.getHours().toString().padStart(2, '0');
                const mm = now.getMinutes().toString().padStart(2, '0');
                const indicator = document.getElementById('poll-indicator');
                if (indicator) indicator.textContent = `↻ ${hh}:${mm}`;
            } catch (e) {
                // échec silencieux — le prochain cycle réessaiera
            } finally {
                isPolling = false;
            }
        }

        function startPolling() {
            if (pollingTimer) clearInterval(pollingTimer);
            pollingTimer = setInterval(() => {
                if (!document.hidden) fetchDataSilent();
            }, 30000);
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
            document.getElementById('total-count').textContent = (stats.total ?? 0) + ' résultat(s)';
            // Met à jour chaque card via data-kpi-value (pattern unifié projet)
            document.querySelectorAll('[data-kpi-value]').forEach(el => {
                const k = el.dataset.kpiValue;
                if (stats[k] !== undefined) {
                    el.textContent = new Intl.NumberFormat('fr-FR').format(stats[k]);
                }
            });
        }

        function debounce(func, wait) {
            return function(...args) {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => func(...args), wait);
            };
        }

        init();
        startPolling();
    })();
    </script>
    @endpush
</x-admin-layout>
<x-admin-layout title="Clients">
    <x-slot:topbarActions>
        <button type="button" onclick="document.getElementById('modal-import-clients').style.display='flex'"
                class="btn btn-ghost btn-sm">
            📥 Importer
        </button>
        <a href="{{ route('admin.clients.create') }}" class="btn btn-primary">
            + Nouveau client
        </a>
    </x-slot:topbarActions>

    {{-- ══ STATS (sans CA total — déplacé dans la fiche client) ══ --}}
    <div class="ci-stats-grid">
        <div class="ci-stat">
            <div class="ci-stat-icon">👥</div>
            <div class="ci-stat-body">
                <div class="ci-stat-num">{{ $stats['total'] }}</div>
                <div class="ci-stat-label">Total clients</div>
            </div>
        </div>
        <div class="ci-stat">
            <div class="ci-stat-icon">📡</div>
            <div class="ci-stat-body">
                <div class="ci-stat-num">{{ $stats['actifs'] }}</div>
                <div class="ci-stat-label">Avec campagne active</div>
            </div>
        </div>
        <div class="ci-stat ci-stat-actions">
            <div class="ci-stat-label" style="margin-bottom:10px">Exports & Import</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <button class="ci-export-btn" onclick="document.getElementById('modal-import-clients').style.display='flex'">📥 Import Excel</button>
                <a class="ci-export-btn" href="{{ route('admin.clients.import.template') }}" style="text-decoration:none">📋 Modèle CSV</a>
            </div>
        </div>
    </div>

    {{-- ══ FILTRES ══ --}}
    <div class="ci-filters">
        <div class="ci-filter-group">
            <label class="ci-filter-label">Recherche</label>
            <div class="ci-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8" />
                    <path d="M21 21l-4.35-4.35" />
                </svg>
                <input type="text" id="filter-search" class="ci-input" placeholder="Nom, NCC, email, téléphone…"
                    autocomplete="off">
            </div>
        </div>
        <div class="ci-filter-group">
            <label class="ci-filter-label">Secteur</label>
            <select id="filter-sector" class="ci-select">
                <option value="">Tous les secteurs</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector }}" {{ request('sector') === $sector ? 'selected' : '' }}>
                        {{ $sector }}</option>
                @endforeach
            </select>
        </div>
        <div class="ci-filter-group">
            <label class="ci-filter-label">Trier par</label>
            <select id="filter-sort" class="ci-select">
                <option value="name">Nom A-Z</option>
                <option value="created_at">Plus récents</option>
                <option value="campaigns_count">Nb campagnes</option>
            </select>
        </div>
        <div class="ci-filter-group" id="reset-wrapper" style="display:none">
            <label class="ci-filter-label" style="visibility:hidden">Actions</label>
            <button id="btn-reset" class="ci-btn-reset">↺ Réinitialiser</button>
        </div>
    </div>

    {{-- ══ TABLE ══ --}}
    <div class="ci-card">
        <div class="ci-card-header">
            <span class="ci-card-title">Portefeuille clients</span>
            <span id="total-count" class="ci-count-badge">{{ $clients->total() }} client(s)</span>
        </div>

        <div class="ci-table-wrap">
            <table class="ci-table" id="clients-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th class="ci-hide-sm">Secteur</th>
                        <th>Campagnes</th>
                        <th class="ci-hide-md">Réservations</th>
                        <th class="ci-hide-md">Contact</th>
                        <th>Compte</th>
                        <th style="width:50px"></th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    @include('admin.clients.partials.table-rows', ['clients' => $clients])
                </tbody>
            </table>
        </div>

        {{-- Skeleton loader --}}
        <div id="table-skeleton" style="display:none;padding:20px">
            @for ($i = 0; $i < 5; $i++)
                <div
                    style="display:flex;gap:12px;align-items:center;padding:12px 0;border-bottom:1px solid var(--border)">
                    <div class="ci-skel" style="width:36px;height:36px;border-radius:50%;flex-shrink:0"></div>
                    <div style="flex:1;display:flex;flex-direction:column;gap:6px">
                        <div class="ci-skel" style="width:40%;height:13px;border-radius:4px"></div>
                        <div class="ci-skel" style="width:25%;height:11px;border-radius:4px"></div>
                    </div>
                    <div class="ci-skel" style="width:60px;height:13px;border-radius:4px"></div>
                    <div class="ci-skel" style="width:50px;height:22px;border-radius:20px"></div>
                </div>
            @endfor
        </div>

        <div id="pagination-container" class="ci-pagination">{{ $clients->links() }}</div>
    </div>

    {{-- ══ TOAST ══ --}}
    <div id="ci-toast-container"
        style="position:fixed;bottom:24px;right:24px;z-index:1000;display:flex;flex-direction:column;gap:8px;pointer-events:none">
    </div>

    {{-- ══ MODAL SUPPRESSION ══ --}}
    <div id="modal-delete" class="ci-modal-overlay" onclick="CI.modal.close('modal-delete')">
        <div class="ci-modal" style="max-width:420px" onclick="event.stopPropagation()">
            <div class="ci-modal-icon ci-modal-icon-danger">🗑</div>
            <h3 class="ci-modal-title">Supprimer le client</h3>
            <p class="ci-modal-desc">
                Supprimer <strong id="del-name" class="ci-text-gold"></strong> ?<br>
                Le client sera archivé. Ses données historiques seront conservées.
            </p>
            <div id="del-warning" class="ci-alert ci-alert-danger" style="display:none">
                ⚠️ Ce client a des campagnes actives. La suppression pourrait être bloquée.
            </div>
            <div class="ci-alert ci-alert-danger" style="font-size:12px">
                Ses réservations passeront en lecture seule.
            </div>
            <div class="ci-modal-footer">
                <button class="ci-btn ci-btn-ghost" onclick="CI.modal.close('modal-delete')">Annuler</button>
                <form id="del-form" method="POST" style="display:inline">
                    @csrf @method('DELETE')
                    <button type="submit" class="ci-btn ci-btn-danger">🗑 Supprimer</button>
                </form>
            </div>
        </div>
    </div>

    {{-- ══ MODAL COMPTE ══ --}}
    <div id="modal-account" class="ci-modal-overlay" onclick="CI.modal.close('modal-account')">
        <div class="ci-modal" style="max-width:460px" onclick="event.stopPropagation()">
            <button class="ci-modal-close" onclick="CI.modal.close('modal-account')">✕</button>
            <div id="account-modal-content">{{-- JS --}}</div>
        </div>
    </div>

    <style>
        /* ── STATS ── */
        .ci-stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px
        }

        .ci-stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: border-color .2s
        }

        .ci-stat:hover {
            border-color: var(--accent)
        }

        .ci-stat-gold {
            border-color: rgba(232, 160, 32, .2);
            background: rgba(232, 160, 32, .03)
        }

        .ci-stat-actions {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px
        }

        .ci-stat-icon {
            font-size: 22px;
            flex-shrink: 0
        }

        .ci-stat-num {
            font-size: 22px;
            font-weight: 800;
            color: var(--text);
            line-height: 1;
            font-family: var(--font-mono, monospace)
        }

        .ci-stat-label {
            font-size: 11px;
            color: var(--text3);
            margin-top: 3px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px
        }

        .ci-export-btn {
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text2);
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all .15s
        }

        .ci-export-btn:hover {
            border-color: var(--accent);
            color: var(--accent)
        }

        /* ── FILTRES ── */
        .ci-filters {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: flex-end
        }

        .ci-filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            flex: 1;
            min-width: 150px
        }

        .ci-filter-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: .5px
        }

        .ci-input-icon {
            position: relative
        }

        .ci-input-icon svg {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text3);
            pointer-events: none
        }

        .ci-input-icon .ci-input {
            padding-left: 32px
        }

        .ci-input,
        .ci-select {
            width: 100%;
            background: var(--surface2);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            font-family: inherit;
            transition: border-color .15s
        }

        .ci-input:focus,
        .ci-select:focus {
            outline: none;
            border-color: var(--accent)
        }

        .ci-btn-reset {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text2);
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            cursor: pointer;
            transition: all .15s;
            width: 100%
        }

        .ci-btn-reset:hover {
            border-color: var(--accent);
            color: var(--accent)
        }

        /* ── CARD / TABLE ── */
        .ci-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden
        }

        .ci-card-header {
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between
        }

        .ci-card-title {
            font-weight: 700;
            font-size: 14px
        }

        .ci-count-badge {
            font-size: 12px;
            color: var(--text3);
            background: var(--surface2);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 3px 12px
        }

        .ci-table-wrap {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch
        }

        .ci-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px
        }

        .ci-table thead th {
            padding: 11px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: var(--text3);
            text-transform: uppercase;
            letter-spacing: .5px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap
        }

        .ci-table tbody tr {
            border-bottom: 1px solid rgba(255, 255, 255, .04);
            transition: background .12s
        }

        .ci-table tbody tr:hover {
            background: rgba(255, 255, 255, .02)
        }

        .ci-table tbody tr:last-child {
            border-bottom: none
        }

        .ci-table td {
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text2);
            vertical-align: middle
        }

        .ci-pagination {
            padding: 14px 16px;
            border-top: 1px solid var(--border)
        }

        /* ── SKELETON ── */
        .ci-skel {
            background: linear-gradient(90deg, var(--surface2) 25%, var(--surface3, #1f2840) 50%, var(--surface2) 75%);
            background-size: 200% 100%;
            animation: ci-skel 1.4s infinite
        }

        @keyframes ci-skel {
            0% {
                background-position: 200% 0
            }

            100% {
                background-position: -200% 0
            }
        }

        /* ── MODALS ── */
        .ci-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .65);
            backdrop-filter: blur(6px);
            z-index: 500;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px
        }

        .ci-modal-overlay.open {
            display: flex
        }

        .ci-modal {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px 28px;
            width: 100%;
            max-width: 420px;
            position: relative;
            animation: ci-modal-in .2s ease
        }

        @keyframes ci-modal-in {
            from {
                opacity: 0;
                transform: scale(.95) translateY(8px)
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0)
            }
        }

        .ci-modal-close {
            position: absolute;
            top: 14px;
            right: 14px;
            background: transparent;
            border: none;
            color: var(--text3);
            cursor: pointer;
            font-size: 18px;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all .15s
        }

        .ci-modal-close:hover {
            background: var(--surface2);
            color: var(--text)
        }

        .ci-modal-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 16px
        }

        .ci-modal-icon-danger {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .2)
        }

        .ci-modal-icon-info {
            background: rgba(59, 130, 246, .1);
            border: 1px solid rgba(59, 130, 246, .2)
        }

        .ci-modal-icon-success {
            background: rgba(34, 197, 94, .1);
            border: 1px solid rgba(34, 197, 94, .2)
        }

        .ci-modal-icon-gold {
            background: rgba(232, 160, 32, .1);
            border: 1px solid rgba(232, 160, 32, .2)
        }

        .ci-modal-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            margin-bottom: 8px
        }

        .ci-modal-desc {
            font-size: 13px;
            color: var(--text2);
            text-align: center;
            line-height: 1.6;
            margin-bottom: 14px
        }

        .ci-modal-footer {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
            flex-wrap: wrap
        }

        .ci-text-gold {
            color: var(--accent)
        }

        /* ── ALERTS ── */
        .ci-alert {
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 12px;
            margin-bottom: 10px;
            line-height: 1.5
        }

        .ci-alert-danger {
            background: rgba(239, 68, 68, .08);
            border: 1px solid rgba(239, 68, 68, .2);
            color: #fca5a5
        }

        .ci-alert-info {
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .2);
            color: #93c5fd
        }

        .ci-alert-success {
            background: rgba(34, 197, 94, .08);
            border: 1px solid rgba(34, 197, 94, .2);
            color: #86efac
        }

        .ci-alert-gold {
            background: rgba(232, 160, 32, .08);
            border: 1px solid rgba(232, 160, 32, .2);
            color: #fde68a
        }

        /* ── BOUTONS ── */
        .ci-btn {
            border-radius: 8px;
            padding: 9px 20px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .15s;
            border: 1px solid transparent;
            font-family: inherit
        }

        .ci-btn-ghost {
            background: transparent;
            border-color: var(--border);
            color: var(--text2)
        }

        .ci-btn-ghost:hover {
            border-color: var(--accent);
            color: var(--text)
        }

        .ci-btn-danger {
            background: rgba(239, 68, 68, .15);
            border-color: rgba(239, 68, 68, .35);
            color: #fca5a5
        }

        .ci-btn-danger:hover {
            background: rgba(239, 68, 68, .25)
        }

        .ci-btn-primary {
            background: var(--accent);
            color: #0a0d14;
            border-color: var(--accent)
        }

        .ci-btn-primary:hover {
            opacity: .9
        }

        .ci-btn-gold-outline {
            background: transparent;
            border-color: rgba(232, 160, 32, .4);
            color: var(--accent)
        }

        .ci-btn-gold-outline:hover {
            background: rgba(232, 160, 32, .08)
        }

        /* ── ACCOUNT MODAL CONTENT ── */
        .am-option {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all .15s;
            border: 1px solid transparent;
            width: 100%;
            background: var(--surface2);
            margin-bottom: 10px;
            text-align: left
        }

        .am-option:hover {
            border-color: var(--accent);
            background: rgba(232, 160, 32, .04)
        }

        .am-option.danger:hover {
            border-color: rgba(239, 68, 68, .4);
            background: rgba(239, 68, 68, .05)
        }

        .am-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0
        }

        .am-title {
            font-size: 13px;
            font-weight: 600;
            color: var(--text)
        }

        .am-sub {
            font-size: 11px;
            color: var(--text3);
            margin-top: 2px
        }

        /* ── TOAST ── */
        .ci-toast {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 240px;
            max-width: 360px;
            pointer-events: all;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .4);
            animation: ci-toast-in .25s ease;
            cursor: pointer
        }

        .ci-toast.out {
            animation: ci-toast-out .25s ease forwards
        }

        @keyframes ci-toast-in {
            from {
                opacity: 0;
                transform: translateX(20px)
            }

            to {
                opacity: 1;
                transform: translateX(0)
            }
        }

        @keyframes ci-toast-out {
            from {
                opacity: 1;
                transform: translateX(0)
            }

            to {
                opacity: 0;
                transform: translateX(20px)
            }
        }

        .ci-toast-success {
            border-color: rgba(34, 197, 94, .3)
        }

        .ci-toast-error {
            border-color: rgba(239, 68, 68, .3)
        }

        .ci-toast-info {
            border-color: rgba(59, 130, 246, .3)
        }

        .ci-toast-warning {
            border-color: rgba(251, 191, 36, .3)
        }

        /* ── RESPONSIVE ── */
        @media(max-width:900px) {
            .ci-stats-grid {
                grid-template-columns: repeat(2, 1fr)
            }

            .ci-hide-md {
                display: none
            }
        }

        @media(max-width:600px) {
            .ci-stats-grid {
                grid-template-columns: 1fr
            }

            .ci-hide-sm {
                display: none
            }

            .ci-filters {
                flex-direction: column
            }

            .ci-filter-group {
                min-width: 100%
            }
        }
    </style>

    {{-- ══ MODAL IMPORT EXCEL ══ --}}
    <div id="modal-import-clients" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:16px"
         onclick="if(event.target===this)this.style.display='none'">
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;width:100%;max-width:520px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.35)">
            <div style="padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:22px">📥</span>
                    <div>
                        <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:var(--text3);font-weight:600">Module Clients</div>
                        <h3 style="font-size:16px;font-weight:600;color:var(--text);margin:2px 0 0">Import Excel / CSV</h3>
                    </div>
                </div>
                <button type="button" onclick="document.getElementById('modal-import-clients').style.display='none'"
                        style="background:none;border:none;font-size:18px;color:var(--text3);cursor:pointer">✕</button>
            </div>
            <form method="POST" action="{{ route('admin.clients.import') }}" enctype="multipart/form-data">
                @csrf
                <div style="padding:20px 22px">
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:12px 14px;margin-bottom:14px;font-size:12px;color:var(--text2);line-height:1.5">
                        <strong style="color:var(--text)">Format attendu :</strong>
                        <span style="font-family:ui-monospace,monospace;color:var(--accent);">nom · email · telephone · entreprise · ncc · contact · secteur · adresse</span>
                        <br>
                        Les doublons (même email ou même NCC) sont ignorés silencieusement.
                        <br>
                        <a href="{{ route('admin.clients.import.template') }}" style="color:var(--accent);text-decoration:underline;font-weight:600">📋 Télécharger le modèle CSV</a>
                    </div>

                    <label style="display:block;font-size:12px;color:var(--text2);margin-bottom:6px;font-weight:500">
                        Fichier Excel ou CSV (max 5 Mo)
                    </label>
                    <input type="file" name="file" required accept=".xlsx,.xls,.csv,.txt"
                           style="width:100%;padding:10px;border:1px dashed var(--border);border-radius:8px;background:var(--surface2);color:var(--text)">
                </div>
                <div style="padding:14px 22px;border-top:1px solid var(--border);background:var(--surface2);display:flex;gap:8px;justify-content:flex-end">
                    <button type="button" onclick="document.getElementById('modal-import-clients').style.display='none'"
                            class="btn btn-ghost btn-sm">Annuler</button>
                    <button type="submit" class="btn btn-primary btn-sm">📥 Lancer l'import</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            // ══ NAMESPACE GLOBAL ══
            window.CI = {

                // ── TOAST ──
                toast(msg, type = 'success', duration = 4000) {
                    const icons = {
                        success: '✅',
                        error: '❌',
                        info: 'ℹ️',
                        warning: '⚠️'
                    };
                    const container = document.getElementById('ci-toast-container');
                    const el = document.createElement('div');
                    el.className = `ci-toast ci-toast-${type}`;
                    el.innerHTML = `<span style="flex-shrink:0">${icons[type]}</span><span>${msg}</span>`;
                    el.onclick = () => this._removeToast(el);
                    container.appendChild(el);
                    setTimeout(() => this._removeToast(el), duration);
                    return el;
                },
                _removeToast(el) {
                    if (!el.parentNode) return;
                    el.classList.add('out');
                    setTimeout(() => el.remove(), 250);
                },

                // ── MODAL ──
                modal: {
                    open(id) {
                        document.getElementById(id)?.classList.add('open');
                    },
                    close(id) {
                        document.getElementById(id)?.classList.remove('open');
                    },
                },

                // ── FETCH AVEC CSRF ──
                async fetchPost(url, extraHeaders = {}) {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...extraHeaders,
                        },
                    });
                    return res.json();
                },

                async fetchDelete(url) {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    return res.json();
                },

                async fetchDelete(url) {
                    const res = await fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                            'Accept': 'application/json',
                        },
                    });
                    return res.json();
                },
            };

            // ── MODAL SUPPRESSION ──
            function openDeleteClient(id, name, activeCampaigns) {
                document.getElementById('del-name').textContent = name;
                document.getElementById('del-form').action = `/admin/clients/${id}`;
                document.getElementById('del-warning').style.display = activeCampaigns > 0 ? 'block' : 'none';
                CI.modal.open('modal-delete');
            }

            // ── MODAL COMPTE ──
            let _currentClientId = null;

            function openAccountModal(id, name, hasAccount) {
                _currentClientId = id;
                const body = document.getElementById('account-modal-content');

                if (hasAccount) {
                    body.innerHTML = `
            <div class="ci-modal-icon ci-modal-icon-success" style="margin-bottom:14px">🔐</div>
            <h3 class="ci-modal-title">${escHtml(name)}</h3>
            <p class="ci-modal-desc">Compte client actif — gérer les accès</p>
            <div class="ci-alert ci-alert-success" style="text-align:center;margin-bottom:16px">
                ✅ Ce client peut accéder à son espace client
            </div>
            <button onclick="_doResetPassword()" class="am-option" style="border:1px solid var(--border)">
                <div class="am-icon" style="background:rgba(59,130,246,.1)">🔑</div>
                <div><div class="am-title">Réinitialiser le mot de passe</div><div class="am-sub">Un nouveau MDP temporaire sera envoyé par email</div></div>
            </button>
            <button onclick="_doRevokeAccount()" class="am-option danger" style="border:1px solid rgba(239,68,68,.15)">
                <div class="am-icon" style="background:rgba(239,68,68,.1)">🚫</div>
                <div><div class="am-title" style="color:#fca5a5">Révoquer l'accès</div><div class="am-sub">Le client ne pourra plus se connecter</div></div>
            </button>
            <div class="ci-modal-footer" style="margin-top:10px">
                <button class="ci-btn ci-btn-ghost" onclick="CI.modal.close('modal-account')">Fermer</button>
            </div>`;
                } else {
                    body.innerHTML = `
            <div class="ci-modal-icon ci-modal-icon-gold" style="margin-bottom:14px">👤</div>
            <h3 class="ci-modal-title">${escHtml(name)}</h3>
            <p class="ci-modal-desc">Ce client n'a pas encore d'accès espace client.</p>
            <div class="ci-alert ci-alert-gold" style="text-align:center;margin-bottom:16px">
                📧 Un email avec les identifiants sera envoyé automatiquement
            </div>
            <div class="ci-modal-footer">
                <button class="ci-btn ci-btn-ghost" onclick="CI.modal.close('modal-account')">Annuler</button>
                <button class="ci-btn ci-btn-primary" onclick="_doCreateAccount()">📧 Créer le compte</button>
            </div>`;
                }
                CI.modal.open('modal-account');
            }

            async function _doCreateAccount() {
                try {
                    const data = await CI.fetchPost(`/admin/clients/${_currentClientId}/account`);
                    CI.modal.close('modal-account');
                    CI.toast(data.message ?? 'Compte créé', data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 1800);
                } catch {
                    CI.toast('Erreur lors de la création du compte', 'error');
                }
            }

            async function _doResetPassword() {
                if (!confirm('Envoyer un nouveau mot de passe temporaire à ce client ?')) return;
                try {
                    const data = await CI.fetchPost(`/admin/clients/${_currentClientId}/account/reset`);
                    CI.modal.close('modal-account');
                    CI.toast(data.message ?? 'Mot de passe réinitialisé', data.success ? 'success' : 'error');
                } catch {
                    CI.toast('Erreur lors de la réinitialisation', 'error');
                }
            }

            async function _doRevokeAccount() {
                if (!confirm('Révoquer définitivement l\'accès espace client de ce client ?')) return;
                try {
                    const data = await CI.fetchDelete(`/admin/clients/${_currentClientId}/account`);
                    CI.modal.close('modal-account');
                    CI.toast(data.message ?? 'Accès révoqué', data.success ? 'success' : 'error');
                    if (data.success) setTimeout(() => location.reload(), 1800);
                } catch {
                    CI.toast('Erreur lors de la révocation', 'error');
                }
            }

            function escHtml(s) {
                return String(s ?? '').replace(/[&<>"']/g, m =>
                    ({
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    } [m]));
            }

            // ── TOUCHE ESC ──
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    ['modal-delete', 'modal-account'].forEach(id => CI.modal.close(id));
                    document.querySelectorAll('.ci-dd-menu').forEach(m => m.classList.remove('open'));
                }
            });

            // ── FERMER DROPDOWNS AU CLIC DEHORS ──
            document.addEventListener('click', e => {
                if (!e.target.closest('.ci-dd')) {
                    document.querySelectorAll('.ci-dd-menu').forEach(m => m.classList.remove('open'));
                }
            });

            // ── FILTRES DYNAMIQUES ──
            (function() {
                let filters = {
                    search: '{{ request('search') }}',
                    sector: '{{ request('sector') }}',
                    sort: '{{ request('sort', 'name') }}',
                };
                let isLoading = false;
                let debTimer = null;
                const baseUrl = '{{ route('admin.clients.index') }}';

                function init() {
                    const s = document.getElementById('filter-search');
                    const sec = document.getElementById('filter-sector');
                    const srt = document.getElementById('filter-sort');
                    const rst = document.getElementById('btn-reset');

                    s.value = filters.search;
                    sec.value = filters.sector;
                    srt.value = filters.sort;
                    updateReset();

                    s.addEventListener('input', () => {
                        clearTimeout(debTimer);
                        debTimer = setTimeout(applyFilters, 380);
                    });
                    sec.addEventListener('change', applyFilters);
                    srt.addEventListener('change', applyFilters);
                    rst.addEventListener('click', resetFilters);

                    // Pagination dynamique
                    document.getElementById('pagination-container').addEventListener('click', e => {
                        const link = e.target.closest('a[href]');
                        if (!link) return;
                        e.preventDefault();
                        const page = new URL(link.href).searchParams.get('page');
                        if (page) {
                            filters.page = page;
                            fetchData();
                        }
                    });
                }

                function applyFilters() {
                    filters.search = document.getElementById('filter-search').value;
                    filters.sector = document.getElementById('filter-sector').value;
                    filters.sort = document.getElementById('filter-sort').value;
                    filters.page = 1;
                    updateReset();
                    fetchData();
                }

                function resetFilters() {
                    filters = {
                        search: '',
                        sector: '',
                        sort: 'name',
                        page: 1
                    };
                    document.getElementById('filter-search').value = '';
                    document.getElementById('filter-sector').value = '';
                    document.getElementById('filter-sort').value = 'name';
                    updateReset();
                    fetchData();
                }

                function updateReset() {
                    const hasFilter = filters.search || filters.sector || filters.sort !== 'name';
                    document.getElementById('reset-wrapper').style.display = hasFilter ? 'flex' : 'none';
                }

                async function fetchData() {
                    if (isLoading) return;
                    isLoading = true;
                    showSkeleton(true);

                    const params = new URLSearchParams();
                    if (filters.search) params.set('search', filters.search);
                    if (filters.sector) params.set('sector', filters.sector);
                    if (filters.sort && filters.sort !== 'name') params.set('sort', filters.sort);
                    if (filters.page && filters.page > 1) params.set('page', filters.page);
                    params.set('ajax', '1');

                    try {
                        const res = await fetch(`${baseUrl}?${params}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        });
                        if (!res.ok) throw new Error();
                        const data = await res.json();
                        document.getElementById('table-body').innerHTML = data.html;
                        document.getElementById('pagination-container').innerHTML = data.pagination;
                        document.getElementById('total-count').textContent = data.total + ' client(s)';
                        window.history.replaceState({}, '', params.toString() ? `${baseUrl}?${params}` : baseUrl);
                    } catch {
                        CI.toast('Erreur lors du chargement', 'error');
                    } finally {
                        isLoading = false;
                        showSkeleton(false);
                    }
                }

                function showSkeleton(show) {
                    document.getElementById('table-body').style.opacity = show ? '0.4' : '1';
                    document.getElementById('table-skeleton').style.display = show ? 'block' : 'none';
                }

                init();
            })();
        </script>
    @endpush
</x-admin-layout>

<x-admin-layout title="Disponibilités & Panneaux">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    {{-- ══ TOPBAR : Fil d'Ariane + bouton Confirmer ══ --}}
    <x-slot:topbarActions>
        {{-- Fil d'Ariane / Retour --}}
        <nav class="flex items-center gap-2 text-xs text-[var(--text3)]" aria-label="Fil d'ariane">
            <a href="{{ route('admin.reservations.index') }}"
               class="hover:text-[var(--text)] transition-colors flex items-center gap-1">
                ← Réservations
            </a>
            <span aria-hidden="true">/</span>
            <span class="text-[var(--text2)] font-medium">Disponibilités</span>
        </nav>

        <div id="topbar-confirm-wrapper" style="display:none">
            <button class="btn btn-primary" onclick="DISPO.openConfirmModal()">
                ✅ Confirmer (<span id="topbar-count">0</span>)
            </button>
        </div>
    </x-slot:topbarActions>

    <script>
        window.__DISPO__ = {
            communes:   {!! json_encode($communes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) !!},
            zones:      {!! json_encode($zones->map(fn($z) => ['id' => $z->id, 'name' => $z->name])->values()) !!},
            formats:    {!! json_encode($formats->map(fn($f) => ['id' => $f->id, 'name' => $f->name, 'width' => $f->width, 'height' => $f->height])->values()) !!},
            dimensions: {!! json_encode($dimensions) !!},
            clients:    {!! json_encode($clients->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()) !!},
            agencies:   {!! json_encode($agencies->map(fn($a) => ['id' => $a->id, 'name' => $a->name])->values()) !!},
            ajaxUrl:    '{{ route('admin.reservations.disponibilites.panneaux') }}',
            confirmUrl: '{{ route('admin.reservations.confirmer-selection') }}',
            csrf:       '{{ csrf_token() }}',
            colors:     ['#3b82f6', '#a855f7', '#f97316', '#14b8a6', '#e20613', '#22c55e'],
            hasErrors:  {{ $errors->any() ? 'true' : 'false' }},
            flashErrors:{!! json_encode($errors->all()) !!},
        };
    </script>

    <div id="dispo-app">

        {{-- ══ FILTRES ══ --}}
        <div class="bg-[var(--surface)] rounded-2xl border border-[var(--border)] p-5 mb-4 shadow-sm">

            {{-- Recherche (mot entier / début de token côté back — voir SearchScope) --}}
            <div class="mb-4">
                <div class="relative max-w-lg">
                    <span class="abs-icon-left text-[var(--text3)] text-sm pointer-events-none">🔍</span>
                    <input type="text" id="f-search"
                        class="filter-input w-full pl-9 pr-10"
                        placeholder="Référence, nom, zone, commune..."
                        oninput="DISPO.onSearch(this.value)">
                    <button id="btn-clear-search"
                        class="abs-icon-right text-[var(--text3)] hover:text-[var(--text)] hidden text-sm"
                        onclick="DISPO.clearSearch()">✕</button>
                </div>
            </div>

            {{-- Ligne 1 : Commune / Zone / Format / Dimensions --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <div>
                    <div class="filter-label-row">
                        <label class="filter-label">📍 Commune</label>
                        <span id="badge-commune_ids" class="ms-badge hidden"></span>
                    </div>
                    <div class="ms-wrapper" data-key="commune_ids" data-placeholder="Toutes"></div>
                </div>
                <div>
                    <div class="filter-label-row">
                        <label class="filter-label">🗺️ Zone</label>
                        <span id="badge-zone_ids" class="ms-badge hidden"></span>
                    </div>
                    <div class="ms-wrapper" data-key="zone_ids" data-placeholder="Toutes"></div>
                </div>
                <div>
                    <div class="filter-label-row">
                        <label class="filter-label">📏 Format</label>
                        <span id="badge-format_ids" class="ms-badge hidden"></span>
                    </div>
                    <div class="ms-wrapper" data-key="format_ids" data-placeholder="Tous"></div>
                </div>
                <div>
                    <label class="filter-label block">📐 Dimensions</label>
                    <select id="f-dimensions" class="filter-select w-full"
                        onchange="DISPO.set('dimensions', this.value)">
                        <option value="">Toutes</option>
                    </select>
                </div>
            </div>

            {{-- Ligne 2 : Éclairage / Statut / Source / Régie --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div>
                    <label class="filter-label block">💡 Éclairage</label>
                    <select id="f-is_lit" class="filter-select w-full" onchange="DISPO.set('is_lit', this.value)">
                        <option value="">Tous</option>
                        <option value="1">💡 Éclairé</option>
                        <option value="0">🌙 Non éclairé</option>
                    </select>
                </div>
                <div>
                    <label class="filter-label block">📊 Statut</label>
                    <select id="f-statut" class="filter-select w-full" onchange="DISPO.set('statut', this.value)">
                        <option value="tous">Tous</option>
                        <option value="libre">✅ Disponible</option>
                        <option value="occupe">🔒 Occupé</option>
                        <option value="option">⏳ En option</option>
                        <option value="maintenance">🔧 Maintenance</option>
                    </select>
                </div>
                <div>
                    <label class="filter-label block">🏢 Source</label>
                    <select id="f-source" class="filter-select w-full" onchange="DISPO.onSourceChange(this.value)">
                        <option value="all">📦 Tous</option>
                        <option value="internal">🏢 Internes</option>
                        <option value="external">🤝 Externes</option>
                    </select>
                </div>
                <div id="wrapper-agencies">
                    <div class="filter-label-row">
                        <label class="filter-label">🤝 Régie</label>
                        <span id="badge-agency_ids" class="ms-badge hidden"></span>
                    </div>
                    <div class="ms-wrapper" data-key="agency_ids" data-placeholder="Toutes"></div>
                </div>
            </div>

            {{-- Ligne 3 : Période + Stats + Reset --}}
            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-[var(--border)]">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="filter-label">📅 Période</span>
                    <div class="flex items-center gap-2 bg-[var(--surface2)] px-3 py-1.5 rounded-xl border border-[var(--border2)]">
                        <input type="date" id="f-du"
                            class="bg-transparent border-none text-sm text-[var(--text)] focus:outline-none"
                            onchange="DISPO.onDateChange('du', this.value)">
                        <span class="text-[var(--text3)] text-xs">→</span>
                        <input type="date" id="f-au"
                            class="bg-transparent border-none text-sm text-[var(--text)] focus:outline-none"
                            onchange="DISPO.onDateChange('au', this.value)">
                    </div>
                    <div id="date-error" class="hidden text-xs text-red-500 bg-red-500/10 px-3 py-1 rounded-lg"></div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <div id="stats-bar" class="flex gap-2 flex-wrap">
                        <span id="stat-total"   class="stat-pill">📊 <strong>0</strong> panneaux</span>
                        <span id="stat-dispo"   class="stat-pill hidden">✅ <strong>0</strong> dispos</span>
                        <span id="stat-occupes" class="stat-pill hidden">🔒 <strong>0</strong> occupés</span>
                        <span id="stat-options" class="stat-pill hidden">⏳ <strong>0</strong> options</span>
                        <span id="stat-ext"     class="stat-pill hidden">🤝 <strong>0</strong> externes</span>
                    </div>
                    <button id="btn-reset"
                        class="hidden px-3 py-1.5 text-xs text-[var(--text3)] border border-[var(--border2)] rounded-xl hover:border-red-500 hover:text-red-500 transition-all"
                        onclick="DISPO.reset()">↻ Réinitialiser</button>
                </div>
            </div>

            {{-- Tags actifs --}}
            <div id="tags-bar" class="hidden flex-wrap items-center gap-2 mt-3 pt-3 border-t border-[var(--border)]">
                <span class="text-xs text-[var(--text3)]">Filtres :</span>
                <div id="tags-list" class="flex flex-wrap gap-2"></div>
            </div>
        </div>

        {{-- ══ BARRE OUTILS ══ --}}
        <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div class="flex items-center gap-1 bg-[var(--surface)] border border-[var(--border)] rounded-xl p-1">
                <button id="btn-view-grid" onclick="DISPO.setView('grid')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-[var(--accent)] text-white">⊞ Grille</button>
                <button id="btn-view-list" onclick="DISPO.setView('list')"
                    class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-[var(--text3)] hover:text-[var(--text)]">☰ Liste</button>
            </div>

            {{-- Export PDF --}}
            <div class="flex gap-2 flex-wrap">
                <button onclick="DISPO.exportPdf('images')"
                    class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-[var(--surface)] border border-[var(--border)] rounded-xl text-red-500 hover:border-red-500 hover:bg-red-500/5 transition-all">
                    📋 PDF images
                </button>

                {{-- ── dropdown PDF liste (id unique) ── --}}
                <div style="position:relative;display:inline-block;" id="dispo-export-wrap">
                    <button onclick="document.getElementById('dispo-export-dropdown').classList.toggle('hidden')"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-[var(--surface)] border border-[var(--border)] rounded-xl text-[var(--blue)] hover:border-[var(--blue)] hover:bg-blue-500/5 transition-all">
                        📄 PDF liste ▾
                    </button>
                    <div id="dispo-export-dropdown" class="hidden"
                        style="position:absolute;top:calc(100% + 6px);right:0;z-index:200;
                               background:var(--surface);border:1px solid var(--border2);
                               border-radius:10px;padding:14px;min-width:200px;
                               box-shadow:0 8px 24px rgba(0,0,0,.15);">
                        <label for="dispo-hide-status"
                            style="display:flex;align-items:center;gap:8px;margin-bottom:12px;cursor:pointer;">
                            <input type="checkbox" id="dispo-hide-status"
                                style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;">
                            <span style="font-size:13px;color:var(--text2);">Masquer le statut</span>
                        </label>
                        <button onclick="DISPO.exportPdf('liste')" class="btn btn-primary btn-sm" style="width:100%;">
                            📄 Générer PDF liste
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ ZONE PANNEAUX ══ --}}
        <div id="panels-outer">
            <div id="loader" class="text-center py-20 text-[var(--text3)]">
                <div class="text-4xl mb-3 animate-spin inline-block">⟳</div>
                <div class="text-sm font-semibold">Chargement…</div>
            </div>
            <div id="panels-grid"
                style="display:none;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;"></div>
            <div id="panels-list" style="display:none;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;min-width:900px;">
                    <thead>
                        <tr style="border-bottom:2px solid var(--border);">
                            <th style="width:36px;padding:10px 8px;"></th>
                            <th class="list-th">Réf.</th>
                            <th class="list-th">Emplacement</th>
                            <th class="list-th">Format</th>
                            <th class="list-th">Dims</th>
                            <th class="list-th">Tarif</th>
                            <th class="list-th">Statut</th>
                            <th style="padding:10px 8px;width:60px;"></th>
                        </tr>
                    </thead>
                    <tbody id="panels-list-body"></tbody>
                </table>
            </div>
            <div id="empty-state" style="display:none" class="text-center py-24 text-[var(--text3)]">
                <div class="text-6xl mb-4">🪧</div>
                <div id="empty-title" class="text-lg font-bold text-[var(--text2)] mb-2">Aucun panneau</div>
                <div id="empty-sub" class="text-sm mb-6">Modifiez vos filtres ou créez un panneau.</div>
            </div>
            <div id="pagination-bar" class="hidden mt-6 flex justify-center items-center gap-4">
                <button id="btn-prev" onclick="DISPO.prevPage()" class="btn btn-ghost btn-sm" disabled>← Précédent</button>
                <span id="pag-info" class="text-sm text-[var(--text3)]"></span>
                <button id="btn-next" onclick="DISPO.nextPage()" class="btn btn-ghost btn-sm">Suivant →</button>
            </div>
        </div>

        {{-- ══ BARRE SÉLECTION (fixe bas de page) ══ --}}
        <div id="sel-bar"
            style="display:none;position:fixed;bottom:0;left:235px;right:0;z-index:300;
                   background:var(--surface);border-top:2px solid var(--accent);
                   padding:12px 24px;box-shadow:0 -8px 32px rgba(0,0,0,.2)">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-4">
                    <div>
                        <span id="sel-count" class="text-3xl font-black text-[var(--accent)]">0</span>
                        <span class="text-sm text-[var(--text2)] ml-2">panneau(x) — </span>
                        <span id="sel-amount" class="text-base font-bold text-[var(--accent)]">0 FCFA/mois</span>
                    </div>
                    <div id="sel-ext-badge"
                        class="hidden px-2 py-0.5 text-xs text-blue-500 border border-blue-500/30 bg-blue-500/10 rounded-lg">
                        dont <span id="sel-ext-n">0</span> externe(s)
                    </div>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button class="btn btn-ghost btn-sm" onclick="DISPO.clearSelectionConfirm()">✕ Vider</button>
                    <button class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.4)"
                        onclick="DISPO.exportSelPdf('images')">📄 PDF images</button>
                    <button class="btn btn-ghost btn-sm" style="color:var(--blue);border-color:rgba(59,130,246,.4)"
                        onclick="DISPO.exportSelPdf('liste')">📋 PDF liste</button>
                    <button class="btn btn-primary" onclick="DISPO.openConfirmModal()">✅ Confirmer la sélection</button>
                </div>
            </div>
        </div>

        {{-- Formulaires PDF cachés --}}
        <form id="form-pdf-images" method="POST"
            action="{{ route('admin.reservations.disponibilites.pdf-images') }}" target="_blank" style="display:none">
            @csrf
            <div id="pdf-images-inputs"></div>
            <input type="hidden" name="start_date" id="pdf-start">
            <input type="hidden" name="end_date"   id="pdf-end">
        </form>
        <form id="form-pdf-liste" method="POST"
            action="{{ route('admin.reservations.disponibilites.pdf-liste') }}" target="_blank" style="display:none">
            @csrf
            <div id="pdf-liste-inputs"></div>
            <input type="hidden" name="start_date" id="pdf-liste-start">
            <input type="hidden" name="end_date"   id="pdf-liste-end">
        </form>

    </div>{{-- /dispo-app --}}

    {{-- ══════════════════════════════════════
         MODAL : CONFIRMER RÉSERVATION
    ══════════════════════════════════════ --}}
    <div id="modal-confirm"
        class="fixed inset-0 z-[9999] bg-black/75 backdrop-blur-sm items-center justify-center p-4"
        style="display:none" onclick="if(event.target===this)DISPO.closeConfirmModal()">

        <div class="bg-[var(--surface)] border border-[var(--border2)] rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col shadow-2xl"
            onclick="event.stopPropagation()">

            <div class="px-6 py-4 border-b border-[var(--border)] bg-[var(--surface2)] rounded-t-2xl flex justify-between items-center flex-shrink-0">
                <div>
                    <div class="font-bold text-[var(--text)] text-sm">✅ Nouvelle réservation</div>
                    <div id="modal-summary" class="text-xs text-[var(--text3)] mt-0.5"></div>
                </div>
                <button onclick="DISPO.closeConfirmModal()"
                    class="w-8 h-8 flex items-center justify-center bg-[var(--surface3)] border border-[var(--border2)] rounded-lg text-[var(--text3)] hover:text-red-500 hover:bg-red-500/10 transition-all text-sm">✕</button>
            </div>

            <form id="form-confirm" method="POST" action="{{ route('admin.reservations.confirmer-selection') }}"
                class="flex flex-col flex-1 overflow-hidden">
                @csrf
                <div id="hidden-panels"></div>

                <div class="p-5 overflow-y-auto flex-1 space-y-4">

                    <div id="modal-errors"
                        class="hidden bg-red-500/10 border border-red-500/30 rounded-xl p-3 text-sm text-red-500 space-y-1"></div>

                    <div class="flex items-center gap-2 bg-green-500/5 border border-green-500/20 rounded-xl px-3 py-2 text-xs text-green-500">
                        🛡️ Anti double-booking actif — Ctrl+Entrée pour confirmer
                    </div>

                    <div id="modal-ext-warn"
                        class="hidden items-center gap-2 bg-blue-500/5 border border-blue-500/20 rounded-xl px-3 py-2 text-xs text-blue-400">
                        🤝 Sélection avec panneaux externes — vérifiez leur disponibilité auprès de la régie.
                    </div>

                    {{-- Type --}}
                    <div>
                        <div class="filter-label mb-2">Type *</div>
                        <div class="grid grid-cols-2 gap-3">
                            <label id="lbl-option"
                                class="cursor-pointer p-3 rounded-xl border-2 border-orange-500 bg-orange-500/8 flex items-center gap-3"
                                onclick="DISPO.setType('option')">
                                <input type="radio" name="type" value="option" checked class="accent-orange-500">
                                <div>
                                    <div class="text-sm font-bold text-orange-400">⏳ Option</div>
                                    <div class="text-xs text-[var(--text3)]">Temporaire</div>
                                </div>
                            </label>
                            <label id="lbl-ferme"
                                class="cursor-pointer p-3 rounded-xl border border-[var(--border2)] bg-[var(--surface2)] flex items-center gap-3"
                                onclick="DISPO.setType('ferme')">
                                <input type="radio" name="type" value="ferme" class="accent-green-500">
                                <div>
                                    <div class="text-sm font-bold text-[var(--text2)]">🔒 Ferme</div>
                                    <div class="text-xs text-[var(--text3)]">Définitive</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Client --}}
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label class="filter-label">Client *</label>
                            <button type="button" onclick="DISPO.openQuickClientModal()"
                                class="flex items-center gap-1 text-xs text-[var(--accent)] hover:opacity-75 transition-opacity">
                                <span class="text-base leading-none">＋</span>
                                <span>Nouveau client</span>
                            </button>
                        </div>
                        <select name="client_id" id="modal-client-select" required class="modal-input w-full">
                            <option value="">— Sélectionner un client —</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div id="modal-client-err" class="hidden mt-1 text-xs text-red-500">
                            Veuillez sélectionner un client.
                        </div>
                    </div>

                    {{-- Nom campagne (réservation ferme uniquement) --}}
                    <div id="wrapper-campaign-name" class="hidden">
                        <label class="filter-label block mb-1">
                            Nom campagne <span class="text-[var(--text3)] font-normal">(optionnel)</span>
                        </label>
                        <input type="text" name="campaign_name" id="modal-campaign"
                            placeholder="Ex : Ramadan 2026" class="modal-input w-full">
                    </div>

                    {{-- Dates --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="filter-label block mb-1">Date début *</label>
                            <input type="date" name="start_date" id="modal-du" required
                                class="modal-input w-full" onchange="DISPO.calcEstimate()">
                        </div>
                        <div>
                            <label class="filter-label block mb-1">Date fin *</label>
                            <input type="date" name="end_date" id="modal-au" required
                                class="modal-input w-full" onchange="DISPO.calcEstimate()">
                        </div>
                    </div>

                    <div id="modal-date-err"
                        class="hidden text-xs text-red-500 bg-red-500/10 px-3 py-2 rounded-lg flex items-center gap-2">
                        <span>⚠️</span><span id="modal-date-err-text"></span>
                    </div>

                    {{-- Montant estimé --}}
                    <div class="flex justify-between items-center bg-[var(--accent-dim)] border border-[var(--accent)]/20 rounded-xl px-4 py-3">
                        <div class="text-xs text-[var(--text3)]">
                            Montant estimé <span id="modal-months" class="ml-1"></span>
                        </div>
                        <div class="text-xl font-black text-[var(--accent)]">
                            <span id="modal-total">—</span>
                            <span class="text-xs font-normal text-[var(--text3)]"> FCFA</span>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="filter-label block mb-1">
                            Notes <span class="text-[var(--text3)] font-normal">(optionnel)</span>
                        </label>
                        <textarea name="notes" rows="2" placeholder="Remarques…"
                            class="modal-input w-full resize-none min-h-[56px]"></textarea>
                    </div>

                </div>

                <div class="px-5 py-3 border-t border-[var(--border)] bg-[var(--surface2)] rounded-b-2xl flex justify-between items-center gap-3 flex-shrink-0">
                    <button type="button" onclick="DISPO.closeConfirmModal()"
                        class="px-4 py-2 text-sm border border-[var(--border2)] rounded-xl text-[var(--text3)] hover:border-[var(--accent)] hover:text-[var(--accent)] transition-all">
                        Annuler
                    </button>
                    <button type="button" id="modal-submit" onclick="DISPO.submitForm()"
                        class="px-5 py-2 bg-[var(--accent)] text-white font-bold text-sm rounded-xl hover:opacity-90 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span id="modal-submit-icon">✅</span>
                        <span id="modal-submit-txt">Confirmer et bloquer</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         MODAL : CRÉATION RAPIDE CLIENT
    ══════════════════════════════════════ --}}
    <div id="modal-quick-client"
        class="fixed inset-0 z-[10001] bg-black/80 backdrop-blur-sm items-center justify-center p-4"
        style="display:none" onclick="if(event.target===this)DISPO.closeQuickClientModal()">

        <div class="bg-[var(--surface)] border border-[var(--border2)] rounded-2xl w-full max-w-md shadow-2xl"
            onclick="event.stopPropagation()">

            <div class="px-5 py-4 border-b border-[var(--border)] bg-[var(--surface2)] rounded-t-2xl flex justify-between items-center">
                <div>
                    <div class="font-bold text-[var(--text)] text-sm">🏢 Nouveau client</div>
                    <div class="text-xs text-[var(--text3)] mt-0.5">Création rapide — champs essentiels</div>
                </div>
                <button onclick="DISPO.closeQuickClientModal()"
                    class="w-8 h-8 flex items-center justify-center bg-[var(--surface3)] border border-[var(--border2)] rounded-lg text-[var(--text3)] hover:text-red-500 hover:bg-red-500/10 transition-all text-sm">✕</button>
            </div>

            <form id="form-quick-client" onsubmit="DISPO.submitQuickClient(event)" class="p-5 space-y-4">
                <div id="quick-client-errors"
                    class="hidden bg-red-500/10 border border-red-500/30 rounded-xl p-3 text-sm text-red-500 space-y-1"></div>

                <div>
                    <label class="filter-label block mb-1">Nom / Raison sociale *</label>
                    <input type="text" id="qc-name" name="name" required placeholder="Ex : Brassivoire SA"
                        class="modal-input w-full" autocomplete="off">
                </div>
                <div>
                    <label class="filter-label block mb-1">NCC <span class="text-[var(--text3)] font-normal">(Numéro de Compte Client)</span></label>
                    <input type="text" id="qc-ncc" name="ncc" placeholder="Ex : CI-2024-00123"
                        maxlength="50" class="modal-input w-full" autocomplete="off">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="filter-label block mb-1">Email</label>
                        <input type="email" id="qc-email" name="email" placeholder="contact@..."
                            class="modal-input w-full" autocomplete="off">
                    </div>
                    <div>
                        <label class="filter-label block mb-1">Téléphone</label>
                        <input type="tel" id="qc-phone" name="phone" placeholder="+225 07 00 00 00"
                            class="modal-input w-full" autocomplete="off">
                    </div>
                </div>
                <div>
                    <label class="filter-label block mb-1">Nom du contact</label>
                    <input type="text" id="qc-contact" name="contact_name" placeholder="Ex : Jean Kouassi"
                        class="modal-input w-full" autocomplete="off">
                </div>

                <div class="flex items-start gap-2 bg-[var(--accent-dim)] border border-[var(--accent)]/20 rounded-xl px-3 py-2">
                    <span class="text-[var(--accent)] mt-0.5 flex-shrink-0">ℹ️</span>
                    <p class="text-xs text-[var(--text2)] leading-relaxed">
                        Création rapide — les champs supplémentaires pourront être complétés depuis la fiche client.
                    </p>
                </div>

                <div class="flex justify-between items-center pt-1">
                    <button type="button" onclick="DISPO.closeQuickClientModal()"
                        class="px-4 py-2 text-sm border border-[var(--border2)] rounded-xl text-[var(--text3)] hover:border-red-500 hover:text-red-500 transition-all">
                        Annuler
                    </button>
                    <button type="submit" id="qc-submit"
                        class="px-5 py-2 bg-[var(--accent)] text-white font-bold text-sm rounded-xl hover:opacity-90 transition-all flex items-center gap-2">
                        <span id="qc-submit-icon">🏢</span>
                        <span id="qc-submit-txt">Créer le client</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         MODAL : FICHE PANNEAU
    ══════════════════════════════════════ --}}
    <div id="modal-fiche"
        class="fixed inset-0 z-[9998] bg-black/70 backdrop-blur-sm items-center justify-center p-4"
        style="display:none" onclick="if(event.target===this)DISPO.closeFiche()">
        <div class="bg-[var(--surface)] border border-[var(--border2)] rounded-2xl w-full max-w-xl max-h-[85vh] overflow-y-auto shadow-2xl"
            onclick="event.stopPropagation()">
            <div class="px-5 py-4 border-b border-[var(--border)] flex justify-between items-center">
                <div id="fiche-title" class="font-bold text-[var(--text)] text-sm"></div>
                <button onclick="DISPO.closeFiche()" class="text-[var(--text3)] hover:text-[var(--text)]">✕</button>
            </div>
            <div id="fiche-body" class="p-5"></div>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         MODAL : ERREUR
    ══════════════════════════════════════ --}}
    <div id="modal-error"
        class="fixed inset-0 z-[10000] bg-black/70 backdrop-blur-sm items-center justify-center p-4"
        style="display:none" onclick="if(event.target===this)DISPO.closeError()">
        <div class="bg-[var(--surface)] border border-red-500/40 rounded-2xl w-full max-w-md shadow-2xl"
            onclick="event.stopPropagation()">
            <div class="px-5 py-4 border-b border-red-500/30 flex justify-between items-center bg-red-500/5 rounded-t-2xl">
                <div class="font-bold text-red-500 flex items-center gap-2"><span>⚠️</span> Erreur</div>
                <button onclick="DISPO.closeError()" class="text-[var(--text3)] hover:text-[var(--text)]">✕</button>
            </div>
            <div class="p-5">
                <div id="error-body" class="text-sm text-[var(--text2)] space-y-2"></div>
            </div>
            <div class="px-5 py-3 border-t border-[var(--border)] flex justify-end">
                <button onclick="DISPO.closeError()"
                    class="px-4 py-2 bg-[var(--surface2)] border border-[var(--border2)] rounded-xl text-sm text-[var(--text2)] hover:border-[var(--accent)] hover:text-[var(--accent)] transition-all">
                    Fermer
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════
         CSS
    ══════════════════════════════════════ --}}
    <style>
        /* ── Select2 override ── */
        #modal-client-select          { display: block !important; }
        .select2-container            { z-index: 10002 !important; }

        /* ── Labels uniformes ── */
        .filter-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: var(--text3);
            display: block;
            height: 16px;
            line-height: 16px;
            margin-bottom: 5px;
        }
        .filter-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 21px; /* label 16 + margin 5 */
            margin-bottom: 5px;
        }
        .filter-label-row .filter-label { margin-bottom: 0; }

        /* ── Contrôles : hauteur uniforme 40px ── */
        .filter-select,
        .filter-input,
        .ms-btn {
            height: 40px !important;
            min-height: 40px !important;
            padding-top: 0 !important;
            padding-bottom: 0 !important;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            font-size: 13px;
            color: var(--text);
            transition: border-color .2s;
            width: 100%;
            display: flex;
            align-items: center;
        }
        .filter-select {
            padding: 0 12px;
            cursor: pointer;
        }
        .filter-select:hover,
        .filter-select:focus { border-color: var(--accent); outline: none; }

        .filter-input {
            padding-left: 2.25rem;
            padding-right: 2.5rem;
            display: block; /* override flex for input */
            line-height: 38px;
        }
        .filter-input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 2px var(--accent-dim); }

        .abs-icon-left  { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); }
        .abs-icon-right { position:absolute; right:.75rem; top:50%; transform:translateY(-50%); }

        /* ── Stats pills ── */
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 9999px;
            font-size: 12px;
            color: var(--text2);
        }

        /* ── MS badge ── */
        .ms-badge {
            background: var(--accent);
            color: #fff;
            border-radius: 9999px;
            padding: 1px 8px;
            font-size: 10px;
            font-weight: 700;
        }

        /* ── Modal inputs ── */
        .modal-input {
            background: var(--surface2);
            border: 1px solid var(--border2);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 13px;
            color: var(--text);
            transition: border-color .2s;
            width: 100%;
        }
        .modal-input:focus { border-color: var(--accent); outline: none; box-shadow: 0 0 0 2px var(--accent-dim); }

        /* ── Table ── */
        .list-th {
            padding: 10px 8px;
            text-align: left;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text3);
            white-space: nowrap;
        }
        .tag {
            background: var(--surface3);
            color: var(--text2);
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
        }

        /* ══ MultiSelect custom ══ */
        .ms-wrapper { position: relative; }
        .ms-btn {
            width: 100%;
            padding: 0 30px 0 12px;
            cursor: pointer;
            text-align: left;
            flex-wrap: wrap;
            gap: 4px;
            position: relative;
        }
        .ms-btn:hover, .ms-btn.open { border-color: var(--accent); }
        .ms-btn::after {
            content: "▾";
            position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
            color: var(--text3); font-size: 11px; pointer-events: none;
        }
        .ms-btn.open::after { content: "▴"; }
        .ms-placeholder { color: var(--text3); font-size: 12px; }
        .ms-chip {
            background: var(--accent-dim); color: var(--accent);
            border-radius: 6px; padding: 2px 6px; font-size: 11px;
            display: inline-flex; align-items: center; gap: 3px;
        }
        .ms-chip button {
            background: none; border: none; color: var(--accent);
            cursor: pointer; opacity: .6; font-size: 11px; padding: 0;
        }
        .ms-chip button:hover { opacity: 1; }
        .ms-drop {
            position: absolute; top: calc(100% + 4px); left: 0; right: 0;
            z-index: 500; background: var(--surface2);
            border: 1px solid var(--border2); border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,.15);
            max-height: 280px; display: flex; flex-direction: column; overflow: hidden;
        }
        .ms-search { padding: 8px; border-bottom: 1px solid var(--border); }
        .ms-search input {
            width: 100%; height: 32px; padding: 0 10px;
            background: var(--surface); border: 1px solid var(--border2);
            border-radius: 8px; font-size: 12px; color: var(--text); outline: none;
        }
        .ms-search input:focus { border-color: var(--accent); }
        .ms-list { overflow-y: auto; flex: 1; }
        .ms-opt {
            padding: 9px 12px; font-size: 13px; cursor: pointer;
            display: flex; align-items: center; gap: 8px;
            color: var(--text2); border-bottom: 1px solid var(--border);
            transition: all .15s;
        }
        .ms-opt:last-child { border-bottom: none; }
        .ms-opt:hover { background: var(--surface3); color: var(--text); }
        .ms-opt.selected { background: var(--accent-dim); color: var(--accent); }
        .ms-opt input { accent-color: var(--accent); width: 15px; height: 15px; cursor: pointer; flex-shrink: 0; }
        .ms-foot {
            padding: 6px 12px; border-top: 1px solid var(--border);
            background: var(--surface3);
            display: flex; justify-content: space-between;
            font-size: 11px; color: var(--text3);
        }
        .ms-foot button { background: none; border: none; color: var(--accent); cursor: pointer; font-size: 11px; }

        /* ══ Cartes panneaux ══ */
        .panel-card {
            background: var(--surface2); border-radius: 14px; overflow: hidden;
            border: 2px solid var(--border);
            transition: transform .15s, box-shadow .15s, border-color .15s;
            position: relative; display: flex; flex-direction: column;
        }
        .panel-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.12); }
        .panel-card.selected {
            border-color: var(--accent) !important;
            box-shadow: 0 0 0 3px var(--accent-dim) !important;
        }
        .panel-card.selectable { cursor: pointer; }

        /* ══ Lignes liste ══ */
        .list-row { border-bottom: 1px solid var(--border); transition: background .1s; }
        .list-row:hover { background: var(--surface2); }
        .list-row.selected { background: var(--accent-dim); }
        .list-row td { padding: 10px 8px; vertical-align: middle; }

        /* ══ Animations ══ */
        @keyframes spin        { to { transform: rotate(360deg); } }
        @keyframes slideInToast{ from { opacity:0; transform:translateX(20px);} to { opacity:1; transform:translateX(0);} }
        @keyframes fadeIn      { from { opacity:0; transform:translateY(10px);} to { opacity:1; transform:translateY(0);} }
        .animate-spin    { animation: spin 1s linear infinite; }
        .animate-fade-in { animation: fadeIn .3s ease-out; }

        /* ══ Mobile ══ */
        @media(max-width:768px) {
            #sel-bar { left: 0; }
        }
    </style>

    @push('scripts')
    {{-- ══════════════════════════════════════
         JS — Select2 helpers
    ══════════════════════════════════════ --}}
    <script>
        function initConfirmSelect2() {
            const $sel = $('#modal-client-select');
            if ($sel.data('select2')) return;
            $sel.select2({
                dropdownParent: $('#modal-confirm'),
                placeholder:    '— Rechercher un client —',
                allowClear:     true,
                width:          '100%',
                language: {
                    noResults:  () => 'Aucun client trouvé',
                    searching:  () => 'Recherche…'
                },
            });
            injectSelect2Styles();
        }

        function injectSelect2Styles() {
            if (document.getElementById('select2-cible-styles')) return;
            const style = document.createElement('style');
            style.id = 'select2-cible-styles';
            style.textContent = `
.select2-container--default .select2-selection--single{background:var(--surface2)!important;border:1px solid var(--border2)!important;border-radius:10px!important;height:42px!important;display:flex!important;align-items:center!important}
.select2-container--default.select2-container--open .select2-selection--single{border-color:var(--accent)!important;box-shadow:0 0 0 2px var(--accent-dim)!important}
.select2-container--default .select2-selection--single .select2-selection__rendered{color:var(--text)!important;font-size:13px!important;padding-left:12px!important;line-height:42px!important}
.select2-container--default .select2-selection--single .select2-selection__placeholder{color:var(--text3)!important}
.select2-container--default .select2-selection--single .select2-selection__arrow{height:42px!important;right:8px!important}
.select2-dropdown{background:var(--surface2)!important;border:1px solid var(--border2)!important;border-radius:12px!important;box-shadow:0 12px 40px rgba(0,0,0,.2)!important;z-index:10002!important}
.select2-container--default .select2-search--dropdown{padding:8px!important;border-bottom:1px solid var(--border)!important;background:var(--surface)!important}
.select2-container--default .select2-search--dropdown .select2-search__field{background:var(--surface2)!important;border:1px solid var(--border2)!important;border-radius:8px!important;color:var(--text)!important;font-size:13px!important;padding:7px 10px!important;outline:none!important}
.select2-results__option{color:var(--text2)!important;font-size:13px!important;padding:9px 14px!important;border-bottom:1px solid var(--border)!important}
.select2-results__option--highlighted{background:var(--accent-dim)!important;color:var(--accent)!important}
.select2-results__option[aria-selected="true"]{background:var(--accent-dim)!important;color:var(--accent)!important;font-weight:600!important}`;
            document.head.appendChild(style);
        }

        function addClientToSelect2(id, name) {
            $('#modal-client-select').append(new Option(name, id, true, true)).trigger('change');
        }
    </script>

    {{-- ══════════════════════════════════════
         JS — Application principale DISPO
    ══════════════════════════════════════ --}}
    <script>
    (function () {
        'use strict';

        const D = window.__DISPO__;

        /* ── État global ── */
        const S = {
            f: {
                commune_ids: [],
                zone_ids:    [],
                format_ids:  [],
                agency_ids:  [],
                dimensions:  '',
                is_lit:      '',
                statut:      'tous',
                du:          '',
                au:          '',
                source:      'all',
                q:           ''
            },
            sel: { ids: [], rates: {}, sources: {} },
            view:          'grid',
            page:          1,
            pages:         1,
            total:         0,
            perPage:       48,
            loading:       false,
            reqId:         0,
            debounce:      null,
            searchDebounce:null,
            _lastPanels:   [],
        };

        const MS_DATA = {
            commune_ids: D.communes,
            zone_ids:    D.zones,
            format_ids:  D.formats,
            agency_ids:  D.agencies
        };

        /* ── Config statuts ── */
        const STATUS_CFG = {
            libre:           { l:'Disponible',  c:'#22c55e', b:'rgba(34,197,94,.08)',   bd:'rgba(34,197,94,.3)'   },
            occupe:          { l:'Occupé',       c:'#e20613', b:'rgba(226,6,19,.08)',    bd:'rgba(226,6,19,.3)'    },
            option_periode:  { l:'En option',    c:'#f97316', b:'rgba(249,115,22,.12)',  bd:'rgba(249,115,22,.5)'  },
            option:          { l:'En option',    c:'#f97316', b:'rgba(249,115,22,.12)',  bd:'rgba(249,115,22,.5)'  },
            confirme:        { l:'Confirmé',     c:'#81358a', b:'rgba(129,53,138,.08)',  bd:'rgba(129,53,138,.3)'  },
            maintenance:     { l:'Maintenance',  c:'#6b7280', b:'rgba(107,114,128,.08)', bd:'rgba(107,114,128,.3)' },
            a_verifier:      { l:'À vérifier',   c:'#94a3b8', b:'rgba(148,163,184,.08)', bd:'rgba(148,163,184,.3)' },
        };

        /* ════════════════════════════════════════
           API publique : window.DISPO
        ════════════════════════════════════════ */
        window.DISPO = {

            /* ── Setters filtres ── */
            set(k, v) {
                S.f[k] = v; S.page = 1;
                this._fetch(); this._syncUI();
            },

            /* ── Recherche (mot entier / début token) ── */
            onSearch(v) {
                S.f.q = v.trim(); S.page = 1;
                clearTimeout(S.searchDebounce);
                S.searchDebounce = setTimeout(() => { this._fetch(); this._syncUI(); }, 350);
                _el('btn-clear-search').classList.toggle('hidden', !v);
            },
            clearSearch() {
                S.f.q = ''; S.page = 1;
                _el('f-search').value = '';
                _el('btn-clear-search').classList.add('hidden');
                this._fetch(); this._syncUI();
            },

            /* ── Source : masquer/afficher régie ── */
            onSourceChange(v) {
                S.f.source = v;
                const wrap = _el('wrapper-agencies');
                if (wrap) {
                    if (v === 'internal') {
                        wrap.style.opacity   = '0.4';
                        wrap.style.pointerEvents = 'none';
                        S.f.agency_ids = [];
                        _syncMs('agency_ids');
                    } else {
                        wrap.style.opacity   = '1';
                        wrap.style.pointerEvents = '';
                    }
                }
                S.page = 1; this._fetch(); this._syncUI();
            },

            /* ── Dates ── */
            onDateChange(which, val) {
                if (which === 'du') {
                    S.f.du = val;
                    const next = new Date(val);
                    next.setDate(next.getDate() + 1);
                    const auEl = _el('f-au');
                    auEl.min = next.toISOString().split('T')[0];
                    if (S.f.au && S.f.au <= val) { S.f.au = ''; auEl.value = ''; }
                } else {
                    S.f.au = val;
                }
                _hideDateErr();
                if (S.f.du && S.f.au && S.f.au <= S.f.du) {
                    _showDateErr('La date de fin doit être après la date de début.');
                    S.f.au = ''; _el('f-au').value = '';
                    return;
                }
                S.page = 1; this._fetch(); this._syncUI();
            },

            /* ── Reset ── */
            reset() {
                S.f = {
                    commune_ids:[], zone_ids:[], format_ids:[], agency_ids:[],
                    dimensions:'', is_lit:'', statut:'tous', du:'', au:'',
                    source:'all', q:''
                };
                S.page = 1;
                ['f-dimensions','f-is_lit'].forEach(id => { const e = _el(id); if(e) e.value=''; });
                const s = _el('f-statut'); if(s) s.value = 'tous';
                const r = _el('f-source'); if(r) r.value = 'all';
                _el('f-du').value = ''; _el('f-au').value = '';
                _el('f-search').value = ''; _el('btn-clear-search').classList.add('hidden');
                ['commune_ids','zone_ids','format_ids','agency_ids'].forEach(_syncMs);
                const wrap = _el('wrapper-agencies');
                if (wrap) { wrap.style.opacity = '1'; wrap.style.pointerEvents = ''; }
                _hideDateErr();
                this._fetch(); this._syncUI();
            },

            /* ── Vue Grille / Liste ── */
            setView(mode) {
                S.view = mode;
                const grid = _el('panels-grid'), list = _el('panels-list');
                const btnG = _el('btn-view-grid'), btnL = _el('btn-view-list');
                if (!grid || !list) return;
                const on  = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-[var(--accent)] text-white';
                const off = 'px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-[var(--text3)] hover:text-[var(--text)]';
                if (mode === 'grid') {
                    grid.style.display = 'grid'; list.style.display = 'none';
                    btnG.className = on; btnL.className = off;
                } else {
                    grid.style.display = 'none'; list.style.display = 'block';
                    btnG.className = off; btnL.className = on;
                    if (S._lastPanels.length > 0) this._renderList(S._lastPanels);
                }
            },

            /* ── Export PDF (panneaux visibles) ── */
            exportPdf(type) {
                const ids = S._lastPanels.filter(p => p.source === 'internal').map(p => p.id);
                if (!ids.length) { alert('Aucun panneau interne à exporter.'); return; }
                this._submitPdfForm(type, ids);
            },

            /* ── Export PDF (sélection) ── */
            exportSelPdf(type) {
                const ids = S.sel.ids.filter(id => !String(id).startsWith('ext_'));
                if (!ids.length) { alert('Aucun panneau interne sélectionné.'); return; }
                this._submitPdfForm(type, ids);
            },

            /* Mutualise la soumission du formulaire PDF */
            _submitPdfForm(type, ids) {
                const isListe  = type === 'liste';
                const fId  = isListe ? 'form-pdf-liste'   : 'form-pdf-images';
                const iId  = isListe ? 'pdf-liste-inputs' : 'pdf-images-inputs';
                const sId  = isListe ? 'pdf-liste-start'  : 'pdf-start';
                const eId  = isListe ? 'pdf-liste-end'    : 'pdf-end';
                let html = ids.map(id => `<input type="hidden" name="panel_ids[]" value="${id}">`).join('');
                if (isListe) {
                    const hideStatus = document.getElementById('dispo-hide-status')?.checked;
                    if (hideStatus) html += `<input type="hidden" name="hide_status" value="1">`;
                }
                _el(iId).innerHTML = html;
                _el(sId).value = S.f.du || '';
                _el(eId).value = S.f.au || '';
                document.getElementById(fId).submit();
            },

            /* ── Pagination ── */
            prevPage() {
                if (S.page > 1) { S.page--; this._fetch(); }
            },
            nextPage() {
                if (S.page < S.pages) {
                    S.page++; this._fetch();
                    _el('panels-grid')?.scrollIntoView({ behavior:'smooth', block:'start' });
                }
            },

            /* ── Sélection d'un panneau ── */
            toggle(id, rate, source) {
                id = String(id);
                const idx = S.sel.ids.indexOf(id);
                if (idx === -1) {
                    S.sel.ids.push(id);
                    S.sel.rates[id]   = parseFloat(rate) || 0;
                    S.sel.sources[id] = source || 'internal';
                } else {
                    S.sel.ids.splice(idx, 1);
                    delete S.sel.rates[id];
                    delete S.sel.sources[id];
                }
                const sel  = S.sel.ids.includes(id);
                const card = document.querySelector(`.panel-card[data-id="${id}"]`);
                if (card) {
                    card.classList.toggle('selected', sel);
                    const btn = card.querySelector('.btn-sel');
                    if (btn) { btn.textContent = sel ? '✓ Sélectionné' : '+ Sélectionner'; btn.style.background = sel ? 'var(--accent)' : 'var(--surface3)'; btn.style.color = sel ? '#fff' : 'var(--text)'; }
                    const chk = card.querySelector('.card-chk'); if (chk) chk.checked = sel;
                }
                const row = document.querySelector(`.list-row[data-id="${id}"]`);
                if (row) {
                    row.classList.toggle('selected', sel);
                    const chk = row.querySelector('.card-chk'); if (chk) chk.checked = sel;
                }
                this._syncSelBar();
            },

            /* ── Vider sélection (avec confirmation si > 3) ── */
            clearSelectionConfirm() {
                if (S.sel.ids.length > 3 && !confirm(`Vider la sélection de ${S.sel.ids.length} panneau(x) ?`)) return;
                this.clearSelection();
            },
            clearSelection() {
                S.sel = { ids:[], rates:{}, sources:{} };
                document.querySelectorAll('.panel-card.selected,.list-row.selected').forEach(el => {
                    el.classList.remove('selected');
                    const btn = el.querySelector('.btn-sel');
                    if (btn) { btn.textContent = '+ Sélectionner'; btn.style.background = 'var(--surface3)'; btn.style.color = 'var(--text)'; }
                    const chk = el.querySelector('.card-chk'); if (chk) chk.checked = false;
                });
                this._syncSelBar();
            },

            /* ── Modal confirmation ── */
            openConfirmModal() {
                _el('modal-du').value = S.f.du || '';
                _el('modal-au').value = S.f.au || '';
                _el('hidden-panels').innerHTML = S.sel.ids.map(id => `<input type="hidden" name="panel_ids[]" value="${id}">`).join('');
                const hasExt = Object.values(S.sel.sources).includes('external');
                _el('modal-ext-warn').classList.toggle('hidden', !hasExt);
                _el('modal-ext-warn').classList.toggle('flex', hasExt);
                _el('modal-errors').classList.add('hidden');
                _el('modal-date-err').classList.add('hidden');
                _el('modal-client-err').classList.add('hidden');
                _el('modal-summary').textContent = `${S.sel.ids.length} panneau(x) sélectionné(s)`;
                this.calcEstimate();
                _show('modal-confirm');
                setTimeout(() => initConfirmSelect2(), 50);
            },
            closeConfirmModal() { _hide('modal-confirm'); },

            setType(type) {
                document.querySelector(`input[name="type"][value="${type}"]`).checked = true;
                const isOpt = type === 'option';
                _el('lbl-option').style.borderColor = isOpt ? '#f97316' : 'var(--border2)';
                _el('lbl-option').style.borderWidth  = isOpt ? '2px' : '1px';
                _el('lbl-ferme').style.borderColor   = !isOpt ? '#22c55e' : 'var(--border2)';
                _el('lbl-ferme').style.borderWidth   = !isOpt ? '2px' : '1px';
                _el('wrapper-campaign-name').classList.toggle('hidden', isOpt);
            },

            calcEstimate() {
                const du = _el('modal-du').value, au = _el('modal-au').value;
                if (du && au && au <= du) {
                    _el('modal-date-err').classList.remove('hidden');
                    _el('modal-date-err-text').textContent = 'La date de fin doit être après la date de début.';
                    _el('modal-total').textContent = '—'; _el('modal-months').textContent = '';
                    return;
                }
                _el('modal-date-err').classList.add('hidden');
                if (!du || !au) { _el('modal-total').textContent = '—'; _el('modal-months').textContent = ''; return; }
                const months = _months(du, au);
                const total  = S.sel.ids.reduce((s, id) => s + (S.sel.rates[id] || 0) * months, 0);
                _el('modal-total').textContent  = Math.round(total).toLocaleString('fr-FR');
                _el('modal-months').textContent = `(${months} mois)`;
            },

            submitForm() {
                const du = _el('modal-du').value, au = _el('modal-au').value;
                const client = $('#modal-client-select').val();
                const errors = [];
                if (!client)          { errors.push('Veuillez sélectionner un client.'); _el('modal-client-err').classList.remove('hidden'); }
                else                  { _el('modal-client-err').classList.add('hidden'); }
                if (!du)              errors.push('La date de début est obligatoire.');
                if (!au)              errors.push('La date de fin est obligatoire.');
                if (du && au && au <= du) errors.push('La date de fin doit être après la date de début.');
                if (errors.length) {
                    const box = _el('modal-errors');
                    box.innerHTML = errors.map(e => `<div class="flex gap-2"><span>⚠️</span><span>${e}</span></div>`).join('');
                    box.classList.remove('hidden');
                    return;
                }
                _el('hidden-panels').innerHTML = S.sel.ids.map(id => `<input type="hidden" name="panel_ids[]" value="${id}">`).join('');
                _el('modal-submit-txt').textContent = 'Envoi en cours…';
                _el('modal-submit').disabled = true;
                _el('form-confirm').submit();
            },

            /* ── Modal fiche panneau (enrichie : GPS, support, dims impression) ── */
            openFiche(p) {
                _el('fiche-title').textContent = `📋 ${p.reference} — ${p.name}`;
                const src = p.source === 'external' ? `🤝 ${p.agency_name}` : '🏢 Interne';
                /* Lien Google Maps si GPS disponible */
                const gpsVal = (p.latitude && p.longitude)
                    ? `<a href="https://maps.google.com/?q=${p.latitude},${p.longitude}" target="_blank"
                          style="color:var(--accent);text-decoration:none">
                          📍 ${Number(p.latitude).toFixed(5)}, ${Number(p.longitude).toFixed(5)}
                       </a>`
                    : '—';
                const fields = [
                    ['RÉFÉRENCE',        p.reference],
                    ['SOURCE',           src],
                    ['COMMUNE',          p.commune],
                    ['ZONE',             p.zone || '—'],
                    ['FORMAT',           p.format || '—'],
                    ['DIMENSIONS AFFICH.', p.dimensions || '—'],
                    ['DIM. IMPRESSION',  (p.print_width && p.print_height) ? `${p.print_width}×${p.print_height} m` : '—'],
                    ['SUPPORT',          p.support_type || '—'],
                    ['CATÉGORIE',        p.category || '—'],
                    ['ÉCLAIRAGE',        p.is_lit ? '💡 Éclairé' : 'Non éclairé'],
                    ['TRAFIC/JOUR',      p.daily_traffic > 0 ? p.daily_traffic.toLocaleString('fr-FR') + ' contacts' : '—'],
                    ['GPS',              gpsVal],
                ];
                const grid = fields.map(([l,v]) =>
                    `<div style="background:var(--surface2);border-radius:8px;padding:12px">
                       <div style="font-size:9px;color:var(--text3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">${l}</div>
                       <div style="font-size:13px;color:var(--text);font-weight:500">${v || '—'}</div>
                     </div>`
                ).join('');
                const zoneDesc = p.zone_description
                    ? `<div style="margin-top:12px">
                         <div style="font-size:10px;color:var(--text3);font-weight:700;text-transform:uppercase;margin-bottom:4px">Zone</div>
                         <div style="background:var(--surface2);border-radius:10px;padding:12px;font-size:12px;color:var(--text2)">${p.zone_description}</div>
                       </div>` : '';
                _el('fiche-body').innerHTML =
                    `<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px">${grid}</div>
                     <div style="background:var(--accent-dim);border:1px solid var(--accent)22;border-radius:12px;padding:16px;text-align:center;margin-bottom:12px">
                       <div style="font-size:10px;color:var(--text3);margin-bottom:4px">TARIF MENSUEL</div>
                       <div style="font-size:24px;font-weight:800;color:var(--accent)">${p.monthly_rate ? Math.round(p.monthly_rate).toLocaleString('fr-FR')+' FCFA' : '—'}</div>
                     </div>${zoneDesc}`;
                _show('modal-fiche');
            },
            closeFiche() { _hide('modal-fiche'); },

            /* ── Modal erreur ── */
            showError(msgs) {
                _el('error-body').innerHTML = (Array.isArray(msgs) ? msgs : [msgs])
                    .map(m => `<div class="flex gap-2 items-start"><span class="text-red-500">•</span><span>${m}</span></div>`).join('');
                _show('modal-error');
            },
            closeError() { _hide('modal-error'); },

            /* ── Modal création rapide client ── */
            openQuickClientModal() {
                const form = _el('form-quick-client'); if (form) form.reset();
                _el('quick-client-errors').classList.add('hidden');
                _el('qc-submit-txt').textContent = 'Créer le client';
                _el('qc-submit').disabled = false;
                _show('modal-quick-client');
                setTimeout(() => _el('qc-name')?.focus(), 100);
            },
            closeQuickClientModal() { _hide('modal-quick-client'); },

            async submitQuickClient(event) {
                event.preventDefault();
                const btn = _el('qc-submit'), errBox = _el('quick-client-errors');
                errBox.classList.add('hidden');
                const name = _el('qc-name').value.trim();
                if (!name) {
                    errBox.innerHTML = '<div class="flex gap-2"><span>⚠️</span><span>Le nom est obligatoire.</span></div>';
                    errBox.classList.remove('hidden');
                    _el('qc-name').focus();
                    return;
                }
                _el('qc-submit-icon').textContent = '⟳';
                _el('qc-submit-txt').textContent   = 'Création…';
                btn.disabled = true;
                try {
                    const res = await fetch('{{ secure_url(route('admin.clients.quick-store', [], false)) }}', {
                        method: 'POST',
                        headers: { 'Content-Type':'application/json', 'Accept':'application/json', 'X-CSRF-TOKEN':D.csrf },
                        body: JSON.stringify({
                            name:         _el('qc-name').value.trim(),
                            ncc:          _el('qc-ncc').value.trim()     || null,
                            email:        _el('qc-email').value.trim()   || null,
                            phone:        _el('qc-phone').value.trim()   || null,
                            contact_name: _el('qc-contact').value.trim() || null
                        })
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Erreur.'];
                        errBox.innerHTML = messages.map(m => `<div class="flex gap-2"><span>⚠️</span><span>${m}</span></div>`).join('');
                        errBox.classList.remove('hidden');
                        return;
                    }
                    addClientToSelect2(data.id, data.name);
                    this.closeQuickClientModal();
                    this.showSuccessToast(`Client "${data.name}" créé ✅`);
                } catch (err) {
                    errBox.innerHTML = `<div class="flex gap-2"><span>⚠️</span><span>Erreur réseau : ${err.message}</span></div>`;
                    errBox.classList.remove('hidden');
                } finally {
                    _el('qc-submit-icon').textContent = '🏢';
                    _el('qc-submit-txt').textContent   = 'Créer le client';
                    btn.disabled = false;
                }
            },

            /* ── Toast succès ── */
            showSuccessToast(message) {
                const toast = document.createElement('div');
                toast.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:10003;background:var(--surface);border:1px solid rgba(34,197,94,.4);border-left:3px solid #22c55e;color:var(--text);padding:12px 16px;border-radius:12px;font-size:13px;box-shadow:0 8px 32px rgba(0,0,0,.2);display:flex;align-items:center;gap:8px;animation:slideInToast .3s ease;max-width:360px;';
                toast.innerHTML = `<span style="color:#22c55e;font-size:16px;">✅</span><span>${message}</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity   = '0';
                    toast.style.transform = 'translateX(20px)';
                    toast.style.transition = 'all .3s ease';
                    setTimeout(() => toast.remove(), 300);
                }, 3500);
            },

            /* ══════════════════════════════════════
               Fetch AJAX
            ══════════════════════════════════════ */
            _fetch(delay) {
                clearTimeout(S.debounce);
                S.debounce = setTimeout(() => this._doFetch(), delay !== undefined ? delay : 300);
            },

            async _doFetch() {
                const rid = ++S.reqId;
                S.loading = true;
                _showLoader();
                const p = new URLSearchParams();
                S.f.commune_ids.forEach(id => p.append('commune_ids[]', id));
                S.f.zone_ids.forEach(id    => p.append('zone_ids[]', id));
                S.f.format_ids.forEach(id  => p.append('format_ids[]', id));
                S.f.agency_ids.forEach(id  => p.append('agency_ids[]', id));
                if (S.f.dimensions) p.set('dimensions', S.f.dimensions);
                if (S.f.is_lit !== '') p.set('is_lit', S.f.is_lit);
                if (S.f.statut !== 'tous') p.set('statut', S.f.statut);
                if (S.f.du) p.set('dispo_du', S.f.du);
                if (S.f.au) p.set('dispo_au', S.f.au);
                if (S.f.source !== 'all') p.set('source', S.f.source);
                if (S.f.q) p.set('q', S.f.q);
                p.set('page',     S.page);
                p.set('per_page', S.perPage);

                /* Persister les filtres dans l'URL (améliore navigation / partage) */
                try { history.replaceState(null, '', '?' + p.toString()); } catch(_) {}

                try {
                    const safeUrl = D.ajaxUrl.replace(/^http:\/\//i, 'https://');
                    const res = await fetch(`${safeUrl}?${p}`, {
                        headers: { Accept:'application/json', 'X-CSRF-TOKEN':D.csrf }
                    });
                    if (rid !== S.reqId) return;
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    const data = await res.json();
                    S.loading = false;
                    if (data.date_error) { _showDateErr(data.date_error); _showEmpty(data.date_error, ''); return; }
                    S.pages        = data.stats.pages || 1;
                    S.total        = data.stats.total || 0;
                    S._lastPanels  = data.panels || [];
                    this._renderPanels(data.panels);
                    this._renderStats(data.stats, data.has_period);
                    this._renderPagination(data.stats);
                    this._syncPanelsOuterMargin();
                } catch (err) {
                    if (rid !== S.reqId) return;
                    S.loading = false;
                    _showEmpty('Erreur de chargement', 'Vérifiez votre connexion.');
                    console.error('[DISPO]', err);
                }
            },

            /* ══════════════════════════════════════
               Rendu : Grille
            ══════════════════════════════════════ */
            _renderPanels(panels) {
                const grid = _el('panels-grid'), empty = _el('empty-state');
                _hide('loader');
                if (!panels || panels.length === 0) {
                    grid.innerHTML = ''; _el('panels-list-body').innerHTML = '';
                    empty.style.display = 'block';
                    grid.style.display  = 'none';
                    return;
                }
                empty.style.display = 'none';
                const frag = document.createDocumentFragment();
                panels.forEach(p => {
                    const div = document.createElement('div');
                    div.innerHTML = this._cardHtml(p);
                    frag.appendChild(div.firstElementChild);
                });
                grid.innerHTML     = '';
                grid.style.display = S.view === 'grid' ? 'grid' : 'none';
                grid.appendChild(frag);
                if (S.view === 'list') this._renderList(panels);
                /* Restaurer état sélection après re-render */
                S.sel.ids.forEach(id => {
                    const card = grid.querySelector(`.panel-card[data-id="${id}"]`);
                    if (!card) return;
                    card.classList.add('selected');
                    const btn = card.querySelector('.btn-sel');
                    if (btn) { btn.textContent = '✓ Sélectionné'; btn.style.background = 'var(--accent)'; btn.style.color = '#fff'; }
                    const chk = card.querySelector('.card-chk'); if (chk) chk.checked = true;
                });
            },

            /* ── HTML d'une carte panneau ── */
            _cardHtml(p) {
                const sc      = STATUS_CFG[p.display_status] || STATUS_CFG.libre;
                const bg      = D.colors[p.card_color_idx || 0] || '#3b82f6';
                const isSel   = S.sel.ids.includes(String(p.id));
                /* FIX : forcer HTTPS pour éviter mixed-content */
                const safeUrl = p.photo_url?.replace(/^http:\/\//i, 'https://');
                const validUrl = safeUrl && (safeUrl.startsWith('https://') || safeUrl.startsWith('/'));
                const thumbStyle = validUrl
                    ? `background:url('${safeUrl}') center/cover no-repeat;`
                    : `background:${bg};`;
                const tags = [
                    p.format     ? `<span class="tag">${p.format}</span>` : '',
                    p.dimensions ? `<span class="tag">${p.dimensions}</span>` : '',
                    p.is_lit     ? `<span class="tag" style="color:var(--accent)">💡</span>` : '',
                    p.support_type ? `<span class="tag">${p.support_type}</span>` : '',
                ].filter(Boolean).join('');

                /* Badge "libération prochaine" (plus visible) */
                const releaseHtml = p.release_info
                    ? `<div style="margin-top:6px;padding:5px 10px;border-radius:8px;font-size:11px;font-weight:600;
                                   background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.4);
                                   color:#f97316;display:flex;align-items:center;gap:4px;">
                         📅 Libre le ${p.release_info.label}
                       </div>`
                    : '';

                const selBtn = p.is_selectable
                    ? `<button type="button" class="btn-sel"
                          style="flex:1.2;font-size:11px;padding:6px 10px;border-radius:7px;
                                 background:${isSel?'var(--accent)':'var(--surface3)'};
                                 color:${isSel?'#fff':'var(--text)'};
                                 border:1px solid ${isSel?'transparent':'var(--border2)'};
                                 cursor:pointer;transition:all .15s;"
                          onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')">
                          ${isSel?'✓ Sélectionné':'+ Sélectionner'}
                       </button>`
                    : `<div style="flex:1.2;padding:6px 10px;background:var(--surface3);border-radius:7px;
                                   font-size:11px;color:var(--text3);text-align:center;border:1px solid var(--border);">
                         ${sc.l}
                       </div>`;

                const safeP = encodeURIComponent(JSON.stringify(p));
                const extBadge = p.source === 'external'
                    ? `<div style="position:absolute;top:8px;left:8px;z-index:2;font-size:9px;font-weight:700;
                                   padding:2px 7px;border-radius:6px;background:rgba(59,130,246,.15);
                                   color:#60a5fa;border:1px solid rgba(59,130,246,.3)">🤝 ${p.agency_name}</div>`
                    : '';
                const chkBox = p.is_selectable
                    ? `<div style="position:absolute;top:10px;left:10px;z-index:2;">
                         <input type="checkbox" class="card-chk"
                            style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;"
                            ${isSel?'checked':''}
                            onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')">
                       </div>` : '';

                return `<div class="panel-card${p.is_selectable?' selectable':''}${isSel?' selected':''}"
                              data-id="${p.id}"
                              ${p.is_selectable?`onclick="DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')"`:''}>`
                    + extBadge + chkBox
                    + `<div style="position:absolute;top:8px;right:8px;z-index:2;padding:4px 10px;border-radius:20px;
                                   font-size:10px;font-weight:700;background:${sc.c};color:white;
                                   text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 8px rgba(0,0,0,.3);">
                         ${sc.l}
                       </div>
                       <div style="height:96px;flex-shrink:0;position:relative;overflow:hidden;${thumbStyle}">
                         <div style="position:absolute;inset:0;background:${validUrl?'linear-gradient(to bottom,rgba(0,0,0,.1),rgba(0,0,0,.65))':'rgba(0,0,0,.15)'}"></div>
                         <div style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);
                                     background:rgba(0,0,0,.75);border-radius:7px;padding:4px 14px;
                                     font-family:monospace;font-size:13px;font-weight:700;color:#fff;
                                     letter-spacing:1.5px;white-space:nowrap;backdrop-filter:blur(4px);">
                           ${p.reference}
                         </div>
                       </div>
                       <div style="padding:12px 14px;flex:1;display:flex;flex-direction:column;">
                         <div style="font-size:10px;color:var(--text3);margin-bottom:2px;">${p.commune}${p.zone&&p.zone!=='—'?' · '+p.zone:''}</div>
                         <div style="font-weight:700;font-size:13px;color:var(--text);margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${p.name}">${p.name}</div>
                         <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">${tags}</div>
                         ${p.zone_description?`<div style="font-size:11px;color:var(--text2);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${p.zone_description}">📍 ${p.zone_description}</div>`:''}
                         <div style="margin-top:auto;padding-top:8px;border-top:1px solid var(--border);">
                           <div style="font-size:17px;font-weight:800;color:var(--accent);margin-bottom:6px;">
                             ${p.monthly_rate?Math.round(p.monthly_rate/1000).toLocaleString('fr-FR')+'K <span style="font-size:11px;font-weight:400;color:var(--text3)">FCFA/mois</span>':'<span style="font-size:13px;color:var(--text3)">Tarif non défini</span>'}
                           </div>
                           ${releaseHtml}
                           <div style="display:flex;gap:6px;margin-top:8px;">
                             <button type="button" style="flex:none;font-size:10px;padding:6px 10px;border-radius:7px;background:var(--surface);border:1px solid var(--border);color:var(--text2);cursor:pointer;"
                                onclick="event.stopPropagation();DISPO.openFiche(JSON.parse(decodeURIComponent(this.dataset.p)))"
                                data-p="${safeP}">📋 Fiche</button>
                             ${selBtn}
                           </div>
                         </div>
                       </div>
                    </div>`;
            },

            /* ══════════════════════════════════════
               Rendu : Liste
            ══════════════════════════════════════ */
            _renderList(panels) {
                const tbody = _el('panels-list-body'); if (!tbody) return;
                const frag = document.createDocumentFragment();
                panels.forEach(p => {
                    const sc    = STATUS_CFG[p.display_status] || STATUS_CFG.libre;
                    const isSel = S.sel.ids.includes(String(p.id));
                    const tr    = document.createElement('tr');
                    tr.className    = `list-row${isSel?' selected':''}`;
                    tr.dataset.id   = p.id;
                    if (p.is_selectable) tr.onclick = () => DISPO.toggle(p.id, p.monthly_rate, p.source);
                    const safeP = encodeURIComponent(JSON.stringify(p));
                    tr.innerHTML =
                        `<td style="padding:10px 8px;width:36px;text-align:center;">${p.is_selectable
                            ? `<input type="checkbox" class="card-chk" style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;" ${isSel?'checked':''} onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')">`
                            : `<span style="font-size:12px;opacity:.4;">🔒</span>`}</td>
                         <td style="padding:10px 8px;">
                           <span style="font-family:monospace;font-weight:700;font-size:12px;padding:3px 8px;border-radius:6px;background:${sc.b};color:${sc.c}">${p.reference}</span>
                           ${p.source==='external'?`<span style="display:block;font-size:9px;color:#60a5fa;margin-top:2px;">🤝 ${p.agency_name}</span>`:''}
                         </td>
                         <td style="padding:10px 8px;">
                           <div style="font-weight:600;font-size:13px;color:var(--text);">${p.name}</div>
                           <div style="font-size:11px;color:var(--text3);">${p.commune}${p.zone&&p.zone!=='—'?' · '+p.zone:''}</div>
                         </td>
                         <td style="padding:10px 8px;font-size:12px;color:var(--text2);">${p.format||'—'}</td>
                         <td style="padding:10px 8px;font-size:12px;color:var(--text2);">${p.dimensions||'—'}${p.is_lit?' 💡':''}</td>
                         <td style="padding:10px 8px;">
                           <div style="font-weight:700;color:var(--accent);font-size:13px;">${p.monthly_rate?Math.round(p.monthly_rate/1000).toLocaleString('fr-FR')+'K':'—'} <span style="font-size:10px;font-weight:400;color:var(--text3)">FCFA</span></div>
                         </td>
                         <td style="padding:10px 8px;">
                           <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;background:${sc.b};color:${sc.c};border:1px solid ${sc.bd}">${sc.l}</span>
                           ${p.release_info?`<div style="font-size:10px;color:var(--text3);margin-top:3px;">📅 ${p.release_info.label}</div>`:''}
                         </td>
                         <td style="padding:10px 8px;">
                           <button type="button" style="font-size:10px;padding:5px 10px;border-radius:6px;background:var(--surface2);border:1px solid var(--border2);color:var(--text2);cursor:pointer;"
                              onclick="event.stopPropagation();DISPO.openFiche(JSON.parse(decodeURIComponent(this.dataset.p)))"
                              data-p="${safeP}">📋 Fiche</button>
                         </td>`;
                    frag.appendChild(tr);
                });
                tbody.innerHTML = '';
                tbody.appendChild(frag);
                /* Restaurer sélection */
                S.sel.ids.forEach(id => {
                    const row = tbody.querySelector(`.list-row[data-id="${id}"]`);
                    if (row) { row.classList.add('selected'); const chk = row.querySelector('.card-chk'); if(chk) chk.checked = true; }
                });
                _el('panels-list').style.display = 'block';
            },

            /* ── Stats ── */
            _renderStats(stats, hasPeriod) {
                const set = (id, html, show = true) => {
                    const el = _el(id); if (!el) return;
                    el.style.display = show ? 'inline-flex' : 'none';
                    if (show) el.innerHTML = html;
                };
                set('stat-total',   `📊 <strong>${stats.total}</strong> panneau(x)`);
                set('stat-dispo',   `✅ <strong>${stats.disponibles}</strong> disponible(s)`,  hasPeriod && stats.disponibles > 0);
                set('stat-occupes', `🔒 <strong>${stats.occupes}</strong> occupé(s)`,          hasPeriod && stats.occupes > 0);
                set('stat-options', `⏳ <strong>${stats.options || 0}</strong> en option`,      hasPeriod && (stats.options || 0) > 0);
                set('stat-ext',     `🤝 <strong>${stats.externes}</strong> externe(s)`,         stats.externes > 0);
            },

            /* ── Pagination ── */
            _renderPagination(stats) {
                const bar = _el('pagination-bar'), info = _el('pag-info');
                const prev = _el('btn-prev'), next = _el('btn-next');
                if (!bar) return;
                if (stats.pages <= 1) { bar.classList.add('hidden'); return; }
                bar.classList.remove('hidden');
                const from = (S.page-1)*S.perPage+1, to = Math.min(S.page*S.perPage, stats.total);
                if (info) info.textContent = `${from}–${to} sur ${stats.total}`;
                if (prev) prev.disabled = S.page <= 1;
                if (next) next.disabled = S.page >= stats.pages;
            },

            /* ── Barre de sélection (bas de page) ── */
            _syncSelBar() {
                const n     = S.sel.ids.length;
                const total = Object.values(S.sel.rates).reduce((s,r) => s+r, 0);
                const nExt  = Object.values(S.sel.sources).filter(s => s === 'external').length;
                _el('sel-bar').style.display = n > 0 ? 'block' : 'none';
                const tw = _el('topbar-confirm-wrapper'); if (tw) tw.style.display = n > 0 ? 'block' : 'none';
                _el('sel-count').textContent  = n;
                _el('sel-amount').textContent = Math.round(total).toLocaleString('fr-FR') + ' FCFA/mois';
                _el('topbar-count').textContent = n;
                const eb = _el('sel-ext-badge');
                if (eb) { eb.classList.toggle('hidden', nExt === 0); _el('sel-ext-n').textContent = nExt; }
                this._syncPanelsOuterMargin();
            },

            /* Ajuster le margin-bottom pour que la barre fixe ne masque pas le contenu */
            _syncPanelsOuterMargin() {
                const outer = _el('panels-outer'); if (!outer) return;
                const bar   = _el('sel-bar');
                const barH  = (bar && bar.style.display !== 'none') ? bar.offsetHeight + 16 : 20;
                outer.style.marginBottom = barH + 'px';
                outer.style.transition   = 'margin-bottom .2s ease';
            },

            /* ── Synchronisation UI (tags, reset btn) ── */
            _syncUI() {
                const f = S.f;
                const active = f.commune_ids.length || f.zone_ids.length || f.format_ids.length
                    || f.agency_ids.length || f.dimensions || f.is_lit !== ''
                    || f.statut !== 'tous' || f.du || f.au || f.source !== 'all' || f.q;
                _el('btn-reset').classList.toggle('hidden', !active);
                this._renderTags();
            },

            _renderTags() {
                const f = S.f, tags = [];
                const addMS = (ids, key, data) => ids.forEach(id => {
                    const it = data.find(x => x.id === id || x.id === parseInt(id));
                    if (it) tags.push({ l: it.name, rm: () => {
                        const i = S.f[key].indexOf(id); if (i>-1) S.f[key].splice(i,1);
                        S.page = 1; _syncMs(key); this._fetch(); this._syncUI();
                    }});
                });
                addMS(f.commune_ids, 'commune_ids', D.communes);
                addMS(f.zone_ids,    'zone_ids',    D.zones);
                addMS(f.format_ids,  'format_ids',  D.formats);
                addMS(f.agency_ids,  'agency_ids',  D.agencies);
                if (f.dimensions) tags.push({ l: f.dimensions, rm: () => { S.f.dimensions=''; _el('f-dimensions').value=''; S.page=1; this._fetch(); this._syncUI(); } });
                if (f.is_lit==='1') tags.push({ l:'💡 Éclairé',    rm: () => { S.f.is_lit=''; _el('f-is_lit').value=''; S.page=1; this._fetch(); this._syncUI(); } });
                if (f.is_lit==='0') tags.push({ l:'Non éclairé',   rm: () => { S.f.is_lit=''; _el('f-is_lit').value=''; S.page=1; this._fetch(); this._syncUI(); } });
                if (f.statut!=='tous') tags.push({ l:'Statut: '+f.statut, rm: () => { S.f.statut='tous'; _el('f-statut').value='tous'; S.page=1; this._fetch(); this._syncUI(); } });
                if (f.q) tags.push({ l:'🔍 '+f.q, rm: () => { S.f.q=''; _el('f-search').value=''; _el('btn-clear-search').classList.add('hidden'); S.page=1; this._fetch(); this._syncUI(); } });
                const bar = _el('tags-bar'), list = _el('tags-list');
                if (!bar || !list) return;
                bar.classList.toggle('hidden', tags.length === 0);
                bar.classList.toggle('flex', tags.length > 0);
                list.innerHTML = tags.map((t,i) =>
                    `<span class="ms-chip">${t.l}<button type="button" onclick="__tagRm(${i})" title="Retirer">✕</button></span>`
                ).join('');
                window.__tagCbs = tags.map(t => t.rm);
            },
        }; // fin window.DISPO

        /* ══════════════════════════════════════
           MultiSelect custom
        ══════════════════════════════════════ */
        const MS = {};

        function buildMs(wrapper) {
            const key = wrapper.dataset.key, ph = wrapper.dataset.placeholder || 'Sélectionner';
            const data = MS_DATA[key] || [];
            const btn  = document.createElement('button');
            btn.type      = 'button';
            btn.className = 'ms-btn';
            btn.innerHTML = `<span class="ms-tags-inner"><span class="ms-placeholder">${ph}</span></span>`;
            const drop = document.createElement('div'); drop.className = 'ms-drop'; drop.style.display = 'none';
            const srch = document.createElement('div'); srch.className = 'ms-search';
            const si   = document.createElement('input'); si.type = 'text'; si.placeholder = 'Rechercher…'; si.autocomplete = 'off';
            srch.appendChild(si); drop.appendChild(srch);
            const listEl = document.createElement('div'); listEl.className = 'ms-list'; drop.appendChild(listEl);
            const foot = document.createElement('div'); foot.className = 'ms-foot';
            foot.innerHTML = `<span id="ms-foot-${key}">0 sélectionné(s)</span><div><button type="button" onclick="__msAll('${key}')">Tout</button><button type="button" onclick="__msClear('${key}')">Aucun</button></div>`;
            drop.appendChild(foot);
            wrapper.appendChild(btn); wrapper.appendChild(drop);

            function render(q = '') {
                const sel = S.f[key];
                const filtered = q ? data.filter(i => i.name.toLowerCase().includes(q.toLowerCase())) : data;
                if (!filtered.length) { listEl.innerHTML = '<div class="ms-opt" style="justify-content:center;font-style:italic">Aucun résultat</div>'; return; }
                const frag = document.createDocumentFragment();
                filtered.forEach(item => {
                    const isSel = sel.includes(item.id) || sel.includes(String(item.id));
                    const lbl   = document.createElement('label');
                    lbl.className   = 'ms-opt' + (isSel ? ' selected' : '');
                    lbl.dataset.id  = item.id;
                    const dim = (key === 'format_ids' && item.width && item.height)
                        ? ` <small style="color:var(--text3)">(${Math.round(item.width)}×${Math.round(item.height)}m)</small>` : '';
                    lbl.innerHTML = `<input type="checkbox" ${isSel?'checked':''}> ${item.name}${dim}`;
                    lbl.querySelector('input').addEventListener('change', () => {
                        const arr = S.f[key], idx = arr.indexOf(item.id);
                        if (idx === -1) arr.push(item.id); else arr.splice(idx, 1);
                        lbl.classList.toggle('selected', arr.includes(item.id));
                        updateTrigger(); updateFoot(); S.page = 1; DISPO._fetch(); DISPO._syncUI();
                    });
                    frag.appendChild(lbl);
                });
                listEl.innerHTML = ''; listEl.appendChild(frag);
            }

            function updateTrigger() {
                const sel = S.f[key], inner = btn.querySelector('.ms-tags-inner');
                if (!inner) return;
                if (!sel.length) { inner.innerHTML = `<span class="ms-placeholder">${ph}</span>`; }
                else {
                    inner.innerHTML = sel.map(id => {
                        const it = data.find(x => x.id === id || x.id === parseInt(id));
                        return it ? `<span class="ms-chip">${it.name}<button type="button" onclick="event.preventDefault();event.stopPropagation();__msRemove('${key}',${id})" title="Retirer">✕</button></span>` : '';
                    }).join('');
                }
                const badge = _el(`badge-${key}`);
                if (badge) { badge.textContent = sel.length; badge.classList.toggle('hidden', sel.length === 0); }
                listEl.querySelectorAll('label.ms-opt').forEach(l => {
                    const id = parseInt(l.dataset.id), c = l.querySelector('input');
                    const s = sel.includes(id) || sel.includes(String(id));
                    if (c) c.checked = s; l.classList.toggle('selected', s);
                });
            }
            function updateFoot() { const el = _el(`ms-foot-${key}`); if(el) el.textContent = S.f[key].length + ' sélectionné(s)'; }

            let stimer;
            si.addEventListener('input', () => { clearTimeout(stimer); stimer = setTimeout(() => render(si.value), 150); });
            btn.addEventListener('click', e => {
                e.stopPropagation();
                const isOpen = drop.style.display !== 'none';
                _closeAllMs();
                if (!isOpen) { drop.style.display = 'flex'; btn.classList.add('open'); render(''); si.value = ''; si.focus(); updateFoot(); }
            });
            MS[key] = { el:wrapper, btn, drop, listEl, render, updateTrigger, updateFoot };
        }

        function _syncMs(key) { MS[key]?.updateTrigger(); }
        function _closeAllMs() { Object.values(MS).forEach(m => { m.drop.style.display='none'; m.btn.classList.remove('open'); }); }

        window.__msAll = k => {
            const d = MS_DATA[k]||[], q = MS[k]?.drop?.querySelector('.ms-search input')?.value?.toLowerCase()||'';
            const visible = q ? d.filter(i=>i.name.toLowerCase().includes(q)) : d;
            visible.forEach(i => { if (!S.f[k].includes(i.id) && !S.f[k].includes(String(i.id))) S.f[k].push(i.id); });
            MS[k]?.updateTrigger(); MS[k]?.updateFoot(); S.page=1; DISPO._fetch(); DISPO._syncUI();
        };
        window.__msClear = k => { S.f[k]=[]; MS[k]?.updateTrigger(); MS[k]?.updateFoot(); S.page=1; DISPO._fetch(); DISPO._syncUI(); };
        window.__msRemove = (k,id) => {
            const i = S.f[k].indexOf(id), i2 = S.f[k].indexOf(String(id));
            if (i>-1) S.f[k].splice(i,1); else if (i2>-1) S.f[k].splice(i2,1);
            MS[k]?.updateTrigger(); MS[k]?.updateFoot(); S.page=1; DISPO._fetch(); DISPO._syncUI();
        };
        window.__tagRm = i => window.__tagCbs?.[i]?.();
        document.addEventListener('click', _closeAllMs);

        /* ══════════════════════════════════════
           Helpers DOM
        ══════════════════════════════════════ */
        function _el(id)   { return document.getElementById(id); }
        function _show(id) { const el=_el(id); if(el) el.style.display='flex'; }
        function _hide(id) { const el=_el(id); if(el) el.style.display='none'; }

        function _showLoader() {
            const l = _el('loader'), g = _el('panels-grid'), e = _el('empty-state'), p = _el('pagination-bar');
            if (l) l.style.display = 'block';
            if (g) { g.innerHTML = ''; g.style.display = 'none'; }
            const tb = _el('panels-list-body'); if (tb) tb.innerHTML = '';
            if (e) e.style.display = 'none';
            if (p) p.classList.add('hidden');
        }
        function _showEmpty(title, sub) {
            _hide('loader');
            const g = _el('panels-grid'); if (g) { g.innerHTML=''; g.style.display='none'; }
            const tb = _el('panels-list-body'); if (tb) tb.innerHTML = '';
            const e = _el('empty-state'); if (e) e.style.display = 'block';
            const t = _el('empty-title'); if (t) t.textContent = title;
            const s = _el('empty-sub');   if (s) s.textContent = sub;
        }
        function _showDateErr(msg) { const el=_el('date-error'); if(el){el.textContent='⚠️ '+msg; el.classList.remove('hidden');} }
        function _hideDateErr()    { const el=_el('date-error'); if(el) el.classList.add('hidden'); }

        /* ══════════════════════════════════════
           Calcul des mois (corrigé)
           - 0 jours → invalide (retourne 0)
           - < 15 jours → 0.5 mois
           - arrondi au demi-mois
        ══════════════════════════════════════ */
        function _months(s, e) {
            const totalDays = Math.round(
                (new Date(e+'T00:00:00') - new Date(s+'T00:00:00')) / (1000*60*60*24)
            );
            if (totalDays <= 0) return 0;
            if (totalDays < 15) return 0.5;
            const full   = Math.floor(totalDays / 30);
            const remain = totalDays % 30;
            let frac = 0;
            if (remain >= 1 && remain <= 15)  frac = 0.5;
            else if (remain > 15)             frac = 1;
            return Math.max(full + frac, 0.5);
        }

        /* ══════════════════════════════════════
           Init
        ══════════════════════════════════════ */
        document.addEventListener('DOMContentLoaded', () => {
            /* Construire les multi-selects */
            document.querySelectorAll('.ms-wrapper').forEach(buildMs);

            /* Remplir le select Dimensions */
            const dimSel = _el('f-dimensions');
            if (dimSel) D.dimensions.forEach(d => {
                const o = document.createElement('option'); o.value=d; o.textContent=d; dimSel.appendChild(o);
            });

            /* Raccourcis clavier */
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') {
                    DISPO.closeConfirmModal();
                    DISPO.closeFiche();
                    DISPO.closeError();
                    _closeAllMs();
                }
                /* Ctrl/Cmd + Entrée : confirmer la sélection */
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    if (S.sel.ids.length > 0) DISPO.openConfirmModal();
                }
            });

            /* Afficher erreurs flash */
            if (D.hasErrors && D.flashErrors.length > 0) DISPO.showError(D.flashErrors);

            /* Charger immédiatement (loader déjà visible par défaut dans le HTML) */
            DISPO._fetch(0);
            DISPO._syncSelBar();
        });

    })(); // fin IIFE

    /* Fermer dropdown export au clic extérieur */
    document.addEventListener('click', function (e) {
        const wrap = document.getElementById('dispo-export-wrap');
        if (wrap && !wrap.contains(e.target)) {
            const dd = document.getElementById('dispo-export-dropdown');
            if (dd) dd.classList.add('hidden');
        }
    });
    </script>
    @endpush

</x-admin-layout>

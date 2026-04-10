<x-admin-layout title="Disponibilités & Panneaux">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<x-slot:topbarActions>
    <div id="topbar-confirm-wrapper" style="display:none">
        <button class="btn btn-primary" onclick="DISPO.openConfirmModal()">
            ✅ Confirmer (<span id="topbar-count">0</span>)
        </button>
    </div>
</x-slot:topbarActions>

{{-- ══ DONNÉES SERVEUR ══ --}}
<script>
window.__DISPO__ = {
    communes:   {!! json_encode($communes->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])->values()) !!},
    zones:      {!! json_encode($zones->map(fn($z) => ['id'=>$z->id,'name'=>$z->name])->values()) !!},
    formats:    {!! json_encode($formats->map(fn($f) => ['id'=>$f->id,'name'=>$f->name,'width'=>$f->width,'height'=>$f->height])->values()) !!},
    dimensions: {!! json_encode($dimensions) !!},
    clients:    {!! json_encode($clients->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])->values()) !!},
    agencies:   {!! json_encode($agencies->map(fn($a) => ['id'=>$a->id,'name'=>$a->name])->values()) !!},
    ajaxUrl:    '{{ route('admin.reservations.disponibilites.panneaux') }}',
    confirmUrl: '{{ route('admin.reservations.confirmer-selection') }}',
    csrf:       '{{ csrf_token() }}',
    colors:     ['#3b82f6','#a855f7','#f97316','#14b8a6','#e8a020','#22c55e'],
    hasErrors:  {{ $errors->any() ? 'true' : 'false' }},
    flashErrors:{!! json_encode($errors->all()) !!},
};
</script>

<div id="dispo-app">

{{-- ══ FILTRES ══ --}}
<div class="bg-[#1a1a2a] rounded-2xl border border-[#2a2a35] p-5 mb-4">

    {{-- Recherche --}}
    <div class="mb-4">
        <div class="relative max-w-lg">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm pointer-events-none">🔍</span>
            <input type="text" id="f-search"
                   class="w-full h-11 pl-9 pr-10 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 placeholder:text-gray-500 focus:border-[#e8a020] focus:outline-none focus:ring-2 focus:ring-[#e8a020]/20 transition-all"
                   placeholder="Référence, nom, zone, commune..."
                   oninput="DISPO.onSearch(this.value)">
            <button id="btn-clear-search"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 hidden text-sm"
                    onclick="DISPO.clearSearch()">✕</button>
        </div>
    </div>

    {{-- Ligne 1 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="filter-label">📍 Commune</label>
                <span id="badge-commune_ids" class="ms-badge hidden"></span>
            </div>
            <div class="ms-wrapper" data-key="commune_ids" data-placeholder="Toutes"></div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="filter-label">🗺️ Zone</label>
                <span id="badge-zone_ids" class="ms-badge hidden"></span>
            </div>
            <div class="ms-wrapper" data-key="zone_ids" data-placeholder="Toutes"></div>
        </div>
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="filter-label">📏 Format</label>
                <span id="badge-format_ids" class="ms-badge hidden"></span>
            </div>
            <div class="ms-wrapper" data-key="format_ids" data-placeholder="Tous"></div>
        </div>
        <div>
            <label class="filter-label block mb-1">📐 Dimensions</label>
            <select id="f-dimensions" class="filter-select w-full" onchange="DISPO.set('dimensions', this.value)">
                <option value="">Toutes</option>
            </select>
        </div>
    </div>

    {{-- Ligne 2 --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div>
            <label class="filter-label block mb-1">💡 Éclairage</label>
            <select id="f-is_lit" class="filter-select w-full" onchange="DISPO.set('is_lit', this.value)">
                <option value="">Tous</option>
                <option value="1">💡 Éclairé</option>
                <option value="0">🌙 Non éclairé</option>
            </select>
        </div>
        <div>
            <label class="filter-label block mb-1">📊 Statut</label>
            <select id="f-statut" class="filter-select w-full" onchange="DISPO.set('statut', this.value)">
                <option value="tous">Tous</option>
                <option value="libre">✅ Disponible</option>
                <option value="occupe">🔒 Occupé</option>
                <option value="option">⏳ En option</option>
                <option value="maintenance">🔧 Maintenance</option>
            </select>
        </div>
        <div>
            <label class="filter-label block mb-1">🏢 Source</label>
            <select id="f-source" class="filter-select w-full" onchange="DISPO.onSourceChange(this.value)">
                <option value="all">📦 Tous</option>
                <option value="internal">🏢 Internes</option>
                <option value="external">🤝 Externes</option>
            </select>
        </div>
        <div id="wrapper-agencies">
            <div class="flex items-center justify-between mb-1">
                <label class="filter-label">🤝 Régie</label>
                <span id="badge-agency_ids" class="ms-badge hidden"></span>
            </div>
            <div class="ms-wrapper" data-key="agency_ids" data-placeholder="Toutes"></div>
        </div>
    </div>

    {{-- Période + stats --}}
    <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-[#2a2a35]">
        <div class="flex flex-wrap items-center gap-3">
            <span class="filter-label">📅 Période</span>
            <div class="flex items-center gap-2 bg-[#252530] px-3 py-1.5 rounded-xl border border-[#3a3a48]">
                <input type="date" id="f-du" class="bg-transparent border-none text-sm text-gray-200 focus:outline-none" onchange="DISPO.onDateChange('du', this.value)">
                <span class="text-gray-600 text-xs">→</span>
                <input type="date" id="f-au" class="bg-transparent border-none text-sm text-gray-200 focus:outline-none" onchange="DISPO.onDateChange('au', this.value)">
            </div>
            <div id="date-error" class="hidden text-xs text-red-400 bg-red-400/10 px-3 py-1 rounded-lg"></div>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div id="stats-bar" class="flex gap-2 flex-wrap">
                <span id="stat-total"   class="stat-pill">📊 <strong>0</strong> panneaux</span>
                <span id="stat-dispo"   class="stat-pill hidden">✅ <strong>0</strong> dispos</span>
                <span id="stat-occupes" class="stat-pill hidden">🔒 <strong>0</strong> occupés</span>
                <span id="stat-options" class="stat-pill hidden">⏳ <strong>0</strong> options</span>
                <span id="stat-ext"     class="stat-pill hidden">🤝 <strong>0</strong> externes</span>
            </div>
            <button id="btn-reset" class="hidden px-3 py-1.5 text-xs text-gray-400 border border-[#3a3a48] rounded-xl hover:border-red-500 hover:text-red-500 transition-all" onclick="DISPO.reset()">
                ↻ Réinitialiser
            </button>
        </div>
    </div>

    {{-- Tags actifs --}}
    <div id="tags-bar" class="hidden flex-wrap items-center gap-2 mt-3 pt-3 border-t border-[#2a2a35]">
        <span class="text-xs text-gray-500">Filtres :</span>
        <div id="tags-list" class="flex flex-wrap gap-2"></div>
    </div>
</div>

{{-- ══ BARRE OUTILS ══ --}}
<div class="flex items-center justify-between mb-4 flex-wrap gap-3">
    <div class="flex items-center gap-1 bg-[#1a1a2a] border border-[#2a2a35] rounded-xl p-1">
        <button id="btn-view-grid" onclick="DISPO.setView('grid')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-[#e8a020] text-black">⊞ Grille</button>
        <button id="btn-view-list" onclick="DISPO.setView('list')" class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-400 hover:text-gray-200">☰ Liste</button>
    </div>
    <div class="flex gap-2 flex-wrap">
        <button onclick="DISPO.exportPdf('images')" target="_blank" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-[#1a1a2a] border border-[#2a2a35] rounded-xl text-red-400 hover:border-red-400 hover:bg-red-400/5 transition-all">
            📋 PDF images
        </button>
        <button onclick="DISPO.exportPdf('liste')" target="_blank" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-[#1a1a2a] border border-[#2a2a35] rounded-xl text-orange-400 hover:border-orange-400 hover:bg-orange-400/5 transition-all">
            📄 PDF liste
        </button>
    </div>
</div>

{{-- ══ ZONE PANNEAUX ══ --}}
<div id="panels-outer" style="margin-bottom:120px">
    <div id="loader" style="display:none" class="text-center py-20 text-gray-400">
        <div class="text-4xl mb-3 animate-spin inline-block">⟳</div>
        <div class="text-sm font-semibold">Chargement…</div>
    </div>
    <div id="panels-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:16px;"></div>
    <div id="panels-list" style="display:none;overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;min-width:900px;">
            <thead>
                <tr style="border-bottom:2px solid #2a2a35;">
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
    <div id="empty-state" style="display:none" class="text-center py-24 text-gray-500">
        <div class="text-6xl mb-4">🪧</div>
        <div id="empty-title" class="text-lg font-bold text-gray-300 mb-2">Aucun panneau</div>
        <div id="empty-sub"   class="text-sm mb-6">Modifiez vos filtres ou créez un panneau.</div>
    </div>
    <div id="pagination-bar" class="hidden mt-6 flex justify-center items-center gap-4">
        <button id="btn-prev" onclick="DISPO.prevPage()" class="btn btn-ghost btn-sm" disabled>← Précédent</button>
        <span   id="pag-info" class="text-sm text-gray-400"></span>
        <button id="btn-next" onclick="DISPO.nextPage()" class="btn btn-ghost btn-sm">Suivant →</button>
    </div>
</div>

{{-- ══ BARRE SÉLECTION ══ --}}
<div id="sel-bar" style="display:none;position:fixed;bottom:0;left:235px;right:0;z-index:300;background:var(--surface);border-top:2px solid var(--accent);padding:12px 24px;box-shadow:0 -8px 32px rgba(0,0,0,.5)">
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-4">
            <div>
                <span id="sel-count"  class="text-3xl font-black text-[#e8a020]">0</span>
                <span class="text-sm text-gray-400 ml-2">panneau(x) — </span>
                <span id="sel-amount" class="text-base font-bold text-[#e8a020]">0 FCFA/mois</span>
            </div>
            <div id="sel-ext-badge" class="hidden px-2 py-0.5 text-xs text-blue-400 border border-blue-400/30 bg-blue-400/10 rounded-lg">
                dont <span id="sel-ext-n">0</span> externe(s)
            </div>
        </div>
        <div class="flex gap-2">
            <button class="btn btn-ghost btn-sm" onclick="DISPO.clearSelection()">✕ Vider</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.4)" onclick="DISPO.exportSelPdf('images')">📄 PDF images</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--blue);border-color:rgba(59,130,246,.4)" onclick="DISPO.exportSelPdf('liste')">📋 PDF liste</button>
            <button class="btn btn-primary" onclick="DISPO.openConfirmModal()">✅ Confirmer la sélection</button>
        </div>
    </div>
</div>

{{-- Formulaires PDF cachés --}}
<form id="form-pdf-images" method="POST" action="{{ route('admin.reservations.disponibilites.pdf-images') }}" target="_blank" style="display:none">
    @csrf
    <div id="pdf-images-inputs"></div>
    <input type="hidden" name="start_date" id="pdf-start">
    <input type="hidden" name="end_date"   id="pdf-end">
</form>
<form id="form-pdf-liste" method="POST" action="{{ route('admin.reservations.disponibilites.pdf-liste') }}" target="_blank" style="display:none">
    @csrf
    <div id="pdf-liste-inputs"></div>
    <input type="hidden" name="start_date" id="pdf-liste-start">
    <input type="hidden" name="end_date"   id="pdf-liste-end">
</form>

</div>{{-- /dispo-app --}}

{{-- ══ MODAL CONFIRMER — VERSION CORRIGÉE ══ --}}
<div id="modal-confirm"
     class="fixed inset-0 z-[9999] bg-black/75 backdrop-blur-sm items-center justify-center p-4"
     style="display:none"
     onclick="if(event.target===this)DISPO.closeConfirmModal()">

    <div class="bg-[#1e1e2e] border border-[#3a3a48] rounded-2xl w-full max-w-lg max-h-[90vh] flex flex-col shadow-2xl"
         onclick="event.stopPropagation()">

        <div class="px-6 py-4 border-b border-[#3a3a48] bg-[#252535] rounded-t-2xl flex justify-between items-center flex-shrink-0">
            <div>
                <div class="font-bold text-white text-sm">✅ Nouvelle réservation</div>
                <div id="modal-summary" class="text-xs text-gray-500 mt-0.5"></div>
            </div>
            <button onclick="DISPO.closeConfirmModal()"
                    class="w-8 h-8 flex items-center justify-center bg-white/5 border border-[#3a3a48] rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-400/10 transition-all text-sm">✕</button>
        </div>

        <form id="form-confirm" method="POST"
              action="{{ route('admin.reservations.confirmer-selection') }}"
              class="flex flex-col flex-1 overflow-hidden">
            @csrf
            <div id="hidden-panels"></div>

            <div class="p-5 overflow-y-auto flex-1 space-y-4">

                {{-- Erreurs --}}
                <div id="modal-errors"
                     class="hidden bg-red-500/10 border border-red-500/30 rounded-xl p-3 text-sm text-red-400 space-y-1"></div>

                {{-- Anti double-booking --}}
                <div class="flex items-center gap-2 bg-green-500/5 border border-green-500/20 rounded-xl px-3 py-2 text-xs text-green-400">
                    🛡️ Anti double-booking actif
                </div>

                {{-- Avertissement panneaux externes --}}
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
                                <div class="text-xs text-gray-500">Temporaire</div>
                            </div>
                        </label>
                        <label id="lbl-ferme"
                               class="cursor-pointer p-3 rounded-xl border border-[#3a3a48] bg-[#252530] flex items-center gap-3"
                               onclick="DISPO.setType('ferme')">
                            <input type="radio" name="type" value="ferme" class="accent-green-500">
                            <div>
                                <div class="text-sm font-bold text-gray-400">🔒 Ferme</div>
                                <div class="text-xs text-gray-500">Définitive</div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- Client + bouton création rapide --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="filter-label">Client *</label>
                        <button type="button"
                                onclick="DISPO.openQuickClientModal()"
                                class="flex items-center gap-1 text-xs text-[#e8a020] hover:text-yellow-300 transition-colors">
                            <span class="text-base leading-none">＋</span>
                            <span>Nouveau client</span>
                        </button>
                    </div>

                    {{-- Le select natif — Select2 va s'y greffer --}}
                    <select name="client_id" id="modal-client-select" required class="modal-input w-full">
                        <option value="">— Sélectionner un client —</option>
                        @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>

                    {{-- Message d'erreur inline --}}
                    <div id="modal-client-err" class="hidden mt-1 text-xs text-red-400">
                        Veuillez sélectionner un client.
                    </div>
                </div>

                {{-- Nom campagne (ferme seulement) --}}
                <div id="wrapper-campaign-name" class="hidden">
                    <label class="filter-label block mb-1">
                        Nom campagne
                        <span class="text-gray-600 font-normal">(optionnel)</span>
                    </label>
                    <input type="text" name="campaign_name" id="modal-campaign" placeholder="Ex : Ramadan 2026" class="modal-input w-full">
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="filter-label block mb-1">Date début *</label>
                        <input type="date" name="start_date" id="modal-du" required class="modal-input w-full" onchange="DISPO.calcEstimate()">
                    </div>
                    <div>
                        <label class="filter-label block mb-1">Date fin *</label>
                        <input type="date" name="end_date" id="modal-au" required class="modal-input w-full" onchange="DISPO.calcEstimate()">
                    </div>
                </div>

                {{-- Erreur dates --}}
                <div id="modal-date-err"
                     class="hidden text-xs text-red-400 bg-red-400/10 px-3 py-2 rounded-lg flex items-center gap-2">
                    <span>⚠️</span>
                    <span id="modal-date-err-text"></span>
                </div>

                {{-- Montant estimé --}}
                <div class="flex justify-between items-center bg-[#e8a020]/5 border border-[#e8a020]/20 rounded-xl px-4 py-3">
                    <div class="text-xs text-gray-400">
                        Montant estimé
                        <span id="modal-months" class="text-gray-600 ml-1"></span>
                    </div>
                    <div class="text-xl font-black text-[#e8a020]">
                        <span id="modal-total">—</span>
                        <span class="text-xs font-normal text-gray-500"> FCFA</span>
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="filter-label block mb-1">
                        Notes
                        <span class="text-gray-600 font-normal">(optionnel)</span>
                    </label>
                    <textarea name="notes" rows="2" placeholder="Remarques…" class="modal-input w-full resize-none min-h-[56px]"></textarea>
                </div>

            {{-- Footer --}}
            <div class="px-5 py-3 border-t border-[#3a3a48] bg-[#252535] rounded-b-2xl flex justify-between items-center gap-3 flex-shrink-0">
                <button type="button"
                        onclick="DISPO.closeConfirmModal()"
                        class="px-4 py-2 text-sm border border-[#3a3a48] rounded-xl text-gray-400 hover:border-[#e8a020] hover:text-[#e8a020] transition-all">
                    Annuler
                </button>
                <button type="button"
                        id="modal-submit"
                        onclick="DISPO.submitForm()"
                        class="px-5 py-2 bg-[#e8a020] text-black font-bold text-sm rounded-xl hover:bg-yellow-400 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="modal-submit-icon">✅</span>
                    <span id="modal-submit-txt">Confirmer et bloquer</span>
                </button>
            </div>
        </form>
    </div>
</div>


{{-- ══ MODAL CRÉATION RAPIDE CLIENT ══ --}}
<div id="modal-quick-client"
     class="fixed inset-0 z-[10001] bg-black/80 backdrop-blur-sm items-center justify-center p-4"
     style="display:none"
     onclick="if(event.target===this)DISPO.closeQuickClientModal()">

    <div class="bg-[#1e1e2e] border border-[#3a3a48] rounded-2xl w-full max-w-md shadow-2xl"
         onclick="event.stopPropagation()">

        {{-- Header --}}
        <div class="px-5 py-4 border-b border-[#3a3a48] bg-[#252535] rounded-t-2xl flex justify-between items-center">
            <div>
                <div class="font-bold text-white text-sm">🏢 Nouveau client</div>
                <div class="text-xs text-gray-500 mt-0.5">Création rapide — champs essentiels</div>
            </div>
            <button onclick="DISPO.closeQuickClientModal()"
                    class="w-8 h-8 flex items-center justify-center bg-white/5 border border-[#3a3a48] rounded-lg text-gray-400 hover:text-red-400 hover:bg-red-400/10 transition-all text-sm">✕</button>
        </div>

        <form id="form-quick-client"
              onsubmit="DISPO.submitQuickClient(event)"
              class="p-5 space-y-4">

            {{-- Erreurs --}}
            <div id="quick-client-errors"
                 class="hidden bg-red-500/10 border border-red-500/30 rounded-xl p-3 text-sm text-red-400 space-y-1"></div>

            {{-- Nom (obligatoire) --}}
            <div>
                <label class="filter-label block mb-1">
                    Nom / Raison sociale *
                </label>
                <input type="text" id="qc-name" name="name" required placeholder="Ex : Brassivoire SA" class="modal-input w-full" autocomplete="off">
            </div>

            {{-- NCC --}}
            <div>
                <label class="filter-label block mb-1">
                    NCC
                    <span class="text-gray-600 font-normal">(Numéro de Compte Client)</span>
                </label>
                <input type="text" id="qc-ncc" name="ncc" placeholder="Ex : CI-2024-00123" maxlength="50" class="modal-input w-full" autocomplete="off">
            </div>

            {{-- Email + Téléphone --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="filter-label block mb-1">Email</label>
                    <input type="email" id="qc-email" name="email" placeholder="contact@..." class="modal-input w-full" autocomplete="off">
                </div>
                <div>
                    <label class="filter-label block mb-1">Téléphone</label>
                    <input type="tel" id="qc-phone" name="phone" placeholder="+225 07 00 00 00" class="modal-input w-full" autocomplete="off">
                </div>
            </div>

            {{-- Contact --}}
            <div>
                <label class="filter-label block mb-1">Nom du contact</label>
                <input type="text" id="qc-contact" name="contact_name" placeholder="Ex : Jean Kouassi" class="modal-input w-full" autocomplete="off">
            </div>

            {{-- Note info --}}
            <div class="flex items-start gap-2 bg-[#e8a020]/5 border border-[#e8a020]/20 rounded-xl px-3 py-2">
                <span class="text-[#e8a020] mt-0.5 flex-shrink-0">ℹ️</span>
                <p class="text-xs text-gray-400 leading-relaxed">
                    Création rapide — les champs supplémentaires (secteur, adresse…)
                    pourront être complétés depuis la fiche client.
                </p>
            </div>

            {{-- Actions --}}
            <div class="flex justify-between items-center pt-1">
                <button type="button"
                        onclick="DISPO.closeQuickClientModal()"
                        class="px-4 py-2 text-sm border border-[#3a3a48] rounded-xl text-gray-400 hover:border-red-500 hover:text-red-400 transition-all">
                    Annuler
                </button>
                <button type="submit"
                        id="qc-submit"
                        class="px-5 py-2 bg-[#e8a020] text-black font-bold text-sm rounded-xl hover:bg-yellow-400 transition-all flex items-center gap-2">
                    <span id="qc-submit-icon">🏢</span>
                    <span id="qc-submit-txt">Créer le client</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL FICHE ══ --}}
<div id="modal-fiche" class="fixed inset-0 z-[9998] bg-black/70 backdrop-blur-sm items-center justify-center p-4" style="display:none" onclick="if(event.target===this)DISPO.closeFiche()">
    <div class="bg-[#1e1e2e] border border-[#3a3a48] rounded-2xl w-full max-w-xl max-h-[85vh] overflow-y-auto shadow-2xl" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-[#3a3a48] flex justify-between items-center">
            <div id="fiche-title" class="font-bold text-white text-sm"></div>
            <button onclick="DISPO.closeFiche()" class="text-gray-400 hover:text-white">✕</button>
        </div>
        <div id="fiche-body" class="p-5"></div>
    </div>
</div>

{{-- ══ MODAL ERREUR ══ --}}
<div id="modal-error" class="fixed inset-0 z-[10000] bg-black/70 backdrop-blur-sm items-center justify-center p-4" style="display:none" onclick="if(event.target===this)DISPO.closeError()">
    <div class="bg-[#1e1e2e] border border-red-500/40 rounded-2xl w-full max-w-md shadow-2xl" onclick="event.stopPropagation()">
        <div class="px-5 py-4 border-b border-red-500/30 flex justify-between items-center bg-red-500/5 rounded-t-2xl">
            <div class="font-bold text-red-400 flex items-center gap-2"><span>⚠️</span> Erreur</div>
            <button onclick="DISPO.closeError()" class="text-gray-400 hover:text-white">✕</button>
        </div>
        <div class="p-5"><div id="error-body" class="text-sm text-gray-300 space-y-2"></div></div>
        <div class="px-5 py-3 border-t border-[#3a3a48] flex justify-end">
            <button onclick="DISPO.closeError()" class="px-4 py-2 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-300 hover:border-[#e8a020] hover:text-[#e8a020] transition-all">Fermer</button>
        </div>
    </div>
</div>

<style>

/* Animation toast */
@keyframes slideInToast {
    from { opacity:0; transform:translateX(20px); }
    to   { opacity:1; transform:translateX(0);    }
}

/* Select2 — reset du select natif (Select2 le cache) */
#modal-client-select {
    display: block !important;  /* Select2 le rend invisible via son propre container */
}

/* Correction z-index Select2 dans modal */
.select2-container {
    z-index: 10000 !important;
}
.modal-confirm .select2-dropdown,
#modal-confirm ~ .select2-container .select2-dropdown {
    z-index: 10001 !important;
}

.filter-label  { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#6b7280; }
.filter-select { height:40px;padding:0 12px;background:#252530;border:1px solid #3a3a48;border-radius:10px;font-size:13px;color:#e8e8f0;cursor:pointer;transition:border-color .2s;width:100%; }
.filter-select:hover,.filter-select:focus { border-color:#e8a020;outline:none; }
.ms-badge    { background:#e8a020;color:#000;border-radius:9999px;padding:1px 8px;font-size:10px;font-weight:700; }
.stat-pill   { display:inline-flex;align-items:center;gap:4px;padding:3px 10px;background:#252530;border:1px solid #3a3a48;border-radius:9999px;font-size:12px;color:#9ca3af; }
.modal-input { background:#252530;border:1px solid #3a3a48;border-radius:10px;padding:9px 12px;font-size:13px;color:#e8e8f0;transition:border-color .2s;width:100%; }
.modal-input:focus { border-color:#e8a020;outline:none;box-shadow:0 0 0 2px rgba(232,160,32,.2); }
.list-th { padding:10px 8px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#6b7280;white-space:nowrap; }
.tag { background:var(--surface3,#252530);color:var(--text2,#9ca3af);font-size:10px;padding:2px 6px;border-radius:4px; }
.ms-wrapper { position:relative; }
.ms-btn { width:100%;min-height:40px;padding:6px 30px 6px 12px;background:#252530;border:1px solid #3a3a48;border-radius:10px;font-size:13px;color:#e8e8f0;cursor:pointer;text-align:left;display:flex;align-items:center;flex-wrap:wrap;gap:4px;position:relative;transition:border-color .2s; }
.ms-btn:hover,.ms-btn.open { border-color:#e8a020; }
.ms-btn::after  { content:"▾";position:absolute;right:10px;top:50%;transform:translateY(-50%);color:#6b7280;font-size:11px;pointer-events:none; }
.ms-btn.open::after { content:"▴"; }
.ms-placeholder { color:#6b7280;font-size:12px; }
.ms-chip { background:rgba(232,160,32,.12);color:#e8a020;border-radius:6px;padding:2px 6px;font-size:11px;display:inline-flex;align-items:center;gap:3px; }
.ms-chip button { background:none;border:none;color:#e8a020;cursor:pointer;opacity:.6;font-size:11px;padding:0; }
.ms-chip button:hover { opacity:1; }
.ms-drop { position:absolute;top:calc(100% + 4px);left:0;right:0;z-index:500;background:#252530;border:1px solid #3a3a48;border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,.5);max-height:280px;display:flex;flex-direction:column;overflow:hidden; }
.ms-search { padding:8px;border-bottom:1px solid #3a3a48; }
.ms-search input { width:100%;height:32px;padding:0 10px;background:#1a1a2a;border:1px solid #3a3a48;border-radius:8px;font-size:12px;color:#e8e8f0;outline:none; }
.ms-search input:focus { border-color:#e8a020; }
.ms-list { overflow-y:auto;flex:1; }
.ms-opt { padding:9px 12px;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px;color:#9ca3af;border-bottom:1px solid #3a3a48;transition:all .15s; }
.ms-opt:last-child { border-bottom:none; }
.ms-opt:hover { background:#2d2d3a;color:#e8e8f0; }
.ms-opt.selected { background:rgba(232,160,32,.1);color:#e8a020; }
.ms-opt input { accent-color:#e8a020;width:15px;height:15px;cursor:pointer;flex-shrink:0; }
.ms-foot { padding:6px 12px;border-top:1px solid #3a3a48;background:#2d2d3a;display:flex;justify-content:space-between;font-size:11px;color:#6b7280; }
.ms-foot button { background:none;border:none;color:#e8a020;cursor:pointer;font-size:11px; }
.panel-card { background:#252530;border-radius:14px;overflow:hidden;border:2px solid #3a3a48;transition:transform .15s,box-shadow .15s,border-color .15s;position:relative;display:flex;flex-direction:column; }
.panel-card:hover { transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.3); }
.panel-card.selected { border-color:#e8a020 !important;box-shadow:0 0 0 3px rgba(232,160,32,.25) !important; }
.panel-card.selectable { cursor:pointer; }
.list-row { border-bottom:1px solid #1e1e2e;transition:background .1s; }
.list-row:hover { background:#252530; }
.list-row.selected { background:rgba(232,160,32,.04); }
.list-row td { padding:10px 8px;vertical-align:middle; }
@keyframes spin { to { transform:rotate(360deg); } }
.animate-spin { animation:spin 1s linear infinite; }
@media (max-width:768px) { #sel-bar { left:0; } }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
</style>

@push('scripts')

<script>
// ══════════════════════════════════════════════════════════════
// FONCTIONS OPTIMISÉES POUR L'EXPORT PDF
// ══════════════════════════════════════════════════════════════

// Optimisation : ouverture dans un nouvel onglet sans rechargement
async function exportPdfOptimized(panelIds, type = 'images', startDate = null, endDate = null) {
    if (!panelIds || panelIds.length === 0) {
        showToast('Veuillez sélectionner au moins un panneau', 'warning');
        return;
    }
    
    // Afficher un indicateur de chargement léger
    const loadingToast = showLoadingToast('Génération du PDF en cours...');
    
    try {
        // Créer un formulaire dynamique
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = type === 'images' 
            ? '{{ route("admin.reservations.disponibilites.pdf-images") }}' 
            : '{{ route("admin.reservations.disponibilites.pdf-liste") }}';
        form.target = '_blank'; // ← Ouvre dans un nouvel onglet
        form.style.display = 'none';
        
        // Ajouter CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        // Ajouter les IDs des panneaux
        panelIds.forEach(id => {
            const input = document.createElement('input');
            input.name = 'panel_ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        // Ajouter les dates si disponibles
        if (startDate) {
            const startInput = document.createElement('input');
            startInput.name = 'start_date';
            startInput.value = startDate;
            form.appendChild(startInput);
        }
        
        if (endDate) {
            const endInput = document.createElement('input');
            endInput.name = 'end_date';
            endInput.value = endDate;
            form.appendChild(endInput);
        }
        
        document.body.appendChild(form);
        form.submit();
        
        // Nettoyer après soumission
        setTimeout(() => {
            document.body.removeChild(form);
            closeLoadingToast(loadingToast);
            showToast('PDF généré avec succès', 'success');
        }, 500);
        
    } catch (error) {
        console.error('Erreur:', error);
        closeLoadingToast(loadingToast);
        showToast('Erreur lors de la génération du PDF', 'error');
    }
}

// Toast de chargement
function showLoadingToast(message) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-4 right-4 z-50 bg-gray-900 text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-3 text-sm';
    toast.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>${message}</span>
    `;
    document.body.appendChild(toast);
    return toast;
}

function closeLoadingToast(toast) {
    if (toast && toast.parentNode) {
        toast.remove();
    }
}

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
    toast.className = `fixed bottom-4 right-4 z-50 ${bgColor} text-white px-4 py-2 rounded-lg shadow-lg text-sm animate-fade-in`;
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ══════════════════════════════════════════════════════════════
// REMPLACER LES ANCIENNES FONCTIONS D'EXPORT
// ══════════════════════════════════════════════════════════════

// Remplacer l'ancienne fonction exportPdf
window.DISPO.exportPdf = function(type) {
    const ids = S._lastPanels.filter(p => p.source === 'internal').map(p => p.id);
    exportPdfOptimized(ids, type, S.f.du, S.f.au);
};

// Remplacer l'ancienne fonction exportSelPdf
window.DISPO.exportSelPdf = function(type) {
    const internalIds = S.sel.ids.filter(id => !String(id).startsWith('ext_'));
    exportPdfOptimized(internalIds, type, S.f.du, S.f.au);
};

</script>

<script>
// ══════════════════════════════════════════════════════════════
// SELECT2 — INITIALISATION CORRECTE
// Doit être appelé UNE SEULE FOIS après que la modal est visible
// ══════════════════════════════════════════════════════════════

/**
 * Initialise Select2 sur #modal-client-select.
 * Appelé lors de l'ouverture de la modal confirm, pas au DOMContentLoaded.
 * Évite le bug "dropdown outside viewport" quand la modal est display:none.
 */
function initConfirmSelect2() {
    const $sel = $('#modal-client-select');

    // Éviter la double initialisation
    if ($sel.data('select2')) return;

    $sel.select2({
        dropdownParent: $('#modal-confirm'),   // ← CRITIQUE : ancre le dropdown dans la modal
        placeholder: '— Rechercher un client —',
        allowClear: true,
        width: '100%',
        language: {
            noResults: () => 'Aucun client trouvé',
            searching: () => 'Recherche…',
        },
    });

    // Style Select2 pour correspondre à la charte CIBLE CI
    injectSelect2Styles();
}

/**
 * Injecte les styles Select2 une seule fois pour correspondre à la charte.
 */
function injectSelect2Styles() {
    if (document.getElementById('select2-cible-styles')) return;

    const style = document.createElement('style');
    style.id = 'select2-cible-styles';
    style.textContent = `
        /* Container */
        .select2-container--default .select2-selection--single {
            background: #252530 !important;
            border: 1px solid #3a3a48 !important;
            border-radius: 10px !important;
            height: 42px !important;
            display: flex !important;
            align-items: center !important;
            transition: border-color .2s !important;
        }
        .select2-container--default .select2-selection--single:focus,
        .select2-container--default.select2-container--open .select2-selection--single {
            border-color: #e8a020 !important;
            box-shadow: 0 0 0 2px rgba(232,160,32,.15) !important;
            outline: none !important;
        }
        /* Texte sélectionné */
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #e8e8f0 !important;
            font-size: 13px !important;
            font-family: 'DM Sans', sans-serif !important;
            padding-left: 12px !important;
            line-height: 42px !important;
        }
        /* Placeholder */
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #6b7280 !important;
        }
        /* Flèche */
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px !important;
            right: 8px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: #6b7280 transparent transparent transparent !important;
        }
        .select2-container--default.select2-container--open .select2-selection__arrow b {
            border-color: transparent transparent #e8a020 transparent !important;
        }
        /* Clear button */
        .select2-container--default .select2-selection--single .select2-selection__clear {
            color: #6b7280 !important;
            margin-right: 20px !important;
            font-size: 16px !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__clear:hover {
            color: #ef4444 !important;
        }
        /* Dropdown */
        .select2-dropdown {
            background: #252530 !important;
            border: 1px solid #3a3a48 !important;
            border-radius: 12px !important;
            box-shadow: 0 12px 40px rgba(0,0,0,.6) !important;
            overflow: hidden !important;
            z-index: 99999 !important;
        }
        /* Search box dans dropdown */
        .select2-container--default .select2-search--dropdown {
            padding: 8px !important;
            border-bottom: 1px solid #3a3a48 !important;
            background: #1e1e2e !important;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            background: #1a1a2a !important;
            border: 1px solid #3a3a48 !important;
            border-radius: 8px !important;
            color: #e8e8f0 !important;
            font-size: 13px !important;
            padding: 7px 10px !important;
            outline: none !important;
            font-family: 'DM Sans', sans-serif !important;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #e8a020 !important;
        }
        /* Options */
        .select2-results__options {
            max-height: 200px !important;
            overflow-y: auto !important;
            scrollbar-width: thin !important;
            scrollbar-color: #3a3a48 transparent !important;
        }
        .select2-results__option {
            color: #9ca3af !important;
            font-size: 13px !important;
            padding: 9px 14px !important;
            font-family: 'DM Sans', sans-serif !important;
            border-bottom: 1px solid rgba(58,58,72,.4) !important;
            transition: all .1s !important;
        }
        .select2-results__option:last-child { border-bottom: none !important; }
        .select2-results__option--highlighted {
            background: rgba(232,160,32,.12) !important;
            color: #e8a020 !important;
        }
        .select2-results__option[aria-selected="true"] {
            background: rgba(232,160,32,.08) !important;
            color: #e8a020 !important;
            font-weight: 600 !important;
        }
        /* Message aucun résultat */
        .select2-results__option--message {
            color: #6b7280 !important;
            font-style: italic !important;
            font-size: 12px !important;
        }
    `;
    document.head.appendChild(style);
}

// ══════════════════════════════════════════════════════════════
// MISE À JOUR DU SELECT2 après création d'un client
// ══════════════════════════════════════════════════════════════

/**
 * Ajoute un nouveau client dans le Select2 et le sélectionne.
 * Appelé après succès de storeQuick().
 */
function addClientToSelect2(id, name) {
    const $sel = $('#modal-client-select');

    // Créer l'option et l'ajouter
    const newOption = new Option(name, id, true, true);
    $sel.append(newOption).trigger('change');

    // Feedback visuel
    const $container = $sel.next('.select2-container');
    if ($container.length) {
        $container.find('.select2-selection').css({
            'border-color': '#22c55e',
            'box-shadow': '0 0 0 2px rgba(34,197,94,.15)',
        });
        setTimeout(() => {
            $container.find('.select2-selection').css({
                'border-color': '',
                'box-shadow': '',
            });
        }, 2000);
    }
}

</script>

<script>

(function(){
'use strict';

const D = window.__DISPO__;

const S = {
    f: { commune_ids:[],zone_ids:[],format_ids:[],agency_ids:[],dimensions:'',is_lit:'',statut:'tous',du:'',au:'',source:'all',q:'' },
    sel: { ids:[],rates:{},sources:{} },
    view:'grid', page:1, pages:1, total:0, perPage:48,
    loading:false, reqId:0, debounce:null, searchDebounce:null, _lastPanels:[],
};

const MS_DATA = { commune_ids:D.communes, zone_ids:D.zones, format_ids:D.formats, agency_ids:D.agencies };

const STATUS_CFG = {
    libre:          { l:'Disponible', c:'#22c55e', b:'rgba(34,197,94,.08)',   bd:'rgba(34,197,94,.3)' },
    occupe:         { l:'Occupé',     c:'#ef4444', b:'rgba(239,68,68,.08)',   bd:'rgba(239,68,68,.3)' },
    option_periode: { l:'En option',  c:'#e8a020', b:'rgba(232,160,32,.08)', bd:'rgba(232,160,32,.3)' },
    option:         { l:'Option',     c:'#e8a020', b:'rgba(232,160,32,.08)', bd:'rgba(232,160,32,.3)' },
    confirme:       { l:'Confirmé',   c:'#a855f7', b:'rgba(168,85,247,.08)', bd:'rgba(168,85,247,.3)' },
    maintenance:    { l:'Maintenance',c:'#6b7280', b:'rgba(107,114,128,.08)',bd:'rgba(107,114,128,.3)' },
    a_verifier:     { l:'À vérifier', c:'#94a3b8', b:'rgba(148,163,184,.08)',bd:'rgba(148,163,184,.3)' },
};



window.DISPO = {
    set(k,v){S.f[k]=v;S.page=1;this._fetch();this._syncUI();},
    onSearch(v){S.f.q=v.trim();S.page=1;clearTimeout(S.searchDebounce);S.searchDebounce=setTimeout(()=>{this._fetch();this._syncUI();},350);_el('btn-clear-search').classList.toggle('hidden',!v);},
    clearSearch(){S.f.q='';S.page=1;_el('f-search').value='';_el('btn-clear-search').classList.add('hidden');this._fetch();this._syncUI();},
    onSourceChange(v){S.f.source=v;if(v==='internal'){S.f.agency_ids=[];_syncMs('agency_ids');}S.page=1;this._fetch();this._syncUI();},
    onDateChange(which,val){
        if(which==='du'){S.f.du=val;const next=new Date(val);next.setDate(next.getDate()+1);const auEl=_el('f-au');auEl.min=next.toISOString().split('T')[0];if(S.f.au&&S.f.au<=val){S.f.au='';auEl.value='';}}
        else{S.f.au=val;}
        _hideDateErr();
        if(S.f.du&&S.f.au&&S.f.au<=S.f.du){_showDateErr('La date de fin doit être après la date de début.');S.f.au='';_el('f-au').value='';return;}
        S.page=1;this._fetch();this._syncUI();
    },
    reset(){
        S.f={commune_ids:[],zone_ids:[],format_ids:[],agency_ids:[],dimensions:'',is_lit:'',statut:'tous',du:'',au:'',source:'all',q:''};
        S.page=1;
        ['f-dimensions','f-is_lit'].forEach(id=>{const el=_el(id);if(el)el.value='';});
        const s=_el('f-statut');if(s)s.value='tous';
        const r=_el('f-source');if(r)r.value='all';
        _el('f-du').value='';_el('f-au').value='';_el('f-search').value='';
        _el('btn-clear-search').classList.add('hidden');
        ['commune_ids','zone_ids','format_ids','agency_ids'].forEach(_syncMs);
        _hideDateErr();this._fetch();this._syncUI();
    },
    setView(mode){
        S.view=mode;
        const grid=_el('panels-grid'),list=_el('panels-list'),btnG=_el('btn-view-grid'),btnL=_el('btn-view-list');
        if(!grid||!list) return;
        if(mode==='grid'){grid.style.display='grid';list.style.display='none';btnG.className='px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-[#e8a020] text-black';btnL.className='px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-400 hover:text-gray-200';}
        else{grid.style.display='none';list.style.display='block';btnG.className='px-3 py-1.5 rounded-lg text-xs font-bold transition-all text-gray-400 hover:text-gray-200';btnL.className='px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-[#e8a020] text-black';if(S._lastPanels.length>0)this._renderList(S._lastPanels);}
    },
    exportPdf(type){
        const formId=type==='images'?'form-pdf-images':'form-pdf-liste';
        const inputsId=type==='images'?'pdf-images-inputs':'pdf-liste-inputs';
        const startId=type==='images'?'pdf-start':'pdf-liste-start';
        const endId=type==='images'?'pdf-end':'pdf-liste-end';
        const ids=S._lastPanels.filter(p=>p.source==='internal').map(p=>p.id);
        if(ids.length===0){alert('Aucun panneau interne à exporter.');return;}
        const container=_el(inputsId);
        container.innerHTML=ids.map(id=>`<input type="hidden" name="panel_ids[]" value="${id}">`).join('');
        _el(startId).value=S.f.du||'';
        _el(endId).value=S.f.au||'';
        document.getElementById(formId).submit();
    },
    exportSelPdf(type){
        const internalIds=S.sel.ids.filter(id=>!String(id).startsWith('ext_'));
        if(internalIds.length===0){alert('Aucun panneau interne sélectionné.');return;}
        const formId=type==='images'?'form-pdf-images':'form-pdf-liste';
        const inputsId=type==='images'?'pdf-images-inputs':'pdf-liste-inputs';
        const startId=type==='images'?'pdf-start':'pdf-liste-start';
        const endId=type==='images'?'pdf-end':'pdf-liste-end';
        const container=_el(inputsId);
        container.innerHTML=internalIds.map(id=>`<input type="hidden" name="panel_ids[]" value="${id}">`).join('');
        _el(startId).value=S.f.du||'';
        _el(endId).value=S.f.au||'';
        document.getElementById(formId).submit();
    },
    prevPage(){if(S.page>1){S.page--;this._fetch();}},
    nextPage(){if(S.page<S.pages){S.page++;this._fetch();_el('panels-grid')?.scrollIntoView({behavior:'smooth',block:'start'});}},
    toggle(id,rate,source){
        id=String(id);
        const idx=S.sel.ids.indexOf(id);
        if(idx===-1){S.sel.ids.push(id);S.sel.rates[id]=parseFloat(rate)||0;S.sel.sources[id]=source||'internal';}
        else{S.sel.ids.splice(idx,1);delete S.sel.rates[id];delete S.sel.sources[id];}
        const sel=S.sel.ids.includes(id);
        const card=document.querySelector(`.panel-card[data-id="${id}"]`);
        if(card){card.classList.toggle('selected',sel);const btn=card.querySelector('.btn-sel');if(btn){btn.textContent=sel?'✓ Sélectionné':'+ Sélectionner';btn.style.background=sel?'var(--accent)':'var(--surface3)';btn.style.color=sel?'#000':'var(--text)';}const chk=card.querySelector('.card-chk');if(chk)chk.checked=sel;}
        const row=document.querySelector(`.list-row[data-id="${id}"]`);
        if(row){row.classList.toggle('selected',sel);const chk=row.querySelector('.card-chk');if(chk)chk.checked=sel;}
        this._syncSelBar();
    },
    clearSelection(){S.sel={ids:[],rates:{},sources:{}};document.querySelectorAll('.panel-card.selected,.list-row.selected').forEach(el=>{el.classList.remove('selected');const btn=el.querySelector('.btn-sel');if(btn){btn.textContent='+ Sélectionner';btn.style.background='var(--surface3)';btn.style.color='var(--text)';}const chk=el.querySelector('.card-chk');if(chk)chk.checked=false;});this._syncSelBar();},
    openConfirmModal() {
        _el('modal-du').value = S.f.du || '';
        _el('modal-au').value = S.f.au || '';

        _el('hidden-panels').innerHTML = S.sel.ids
            .map(id => `<input type="hidden" name="panel_ids[]" value="${id}">`)
            .join('');

        const hasExt = Object.values(S.sel.sources).includes('external');
        _el('modal-ext-warn').classList.toggle('hidden', !hasExt);
        _el('modal-ext-warn').classList.toggle('flex', hasExt);
        _el('modal-errors').classList.add('hidden');
        _el('modal-date-err').classList.add('hidden');
        _el('modal-client-err').classList.add('hidden');
        _el('modal-summary').textContent = `${S.sel.ids.length} panneau(x) sélectionné(s)`;

        this.calcEstimate();
        _show('modal-confirm');

        // ← CORRECTION : initialiser Select2 APRÈS affichage de la modal
        setTimeout(() => initConfirmSelect2(), 50);
    },
    closeConfirmModal() {
        _hide('modal-confirm');
    },    
    
    setType(type){
        document.querySelector(`input[name="type"][value="${type}"]`).checked=true;
        const isOpt=type==='option';
        _el('lbl-option').style.borderColor=isOpt?'#f97316':'#3a3a48';_el('lbl-option').style.borderWidth=isOpt?'2px':'1px';
        _el('lbl-ferme').style.borderColor=!isOpt?'#22c55e':'#3a3a48';_el('lbl-ferme').style.borderWidth=!isOpt?'2px':'1px';
        _el('wrapper-campaign-name').classList.toggle('hidden',isOpt);
    },
    calcEstimate(){
        const du=_el('modal-du').value,au=_el('modal-au').value;
        if(du&&au&&au<=du){_el('modal-date-err').classList.remove('hidden');_el('modal-date-err-text').textContent='La date de fin doit être après la date de début.';_el('modal-total').textContent='—';_el('modal-months').textContent='';return;}
        _el('modal-date-err').classList.add('hidden');
        if(!du||!au){_el('modal-total').textContent='—';_el('modal-months').textContent='';return;}
        const months=_months(du,au);
        const total=S.sel.ids.reduce((s,id)=>s+(S.sel.rates[id]||0)*months,0);
        _el('modal-total').textContent=Math.round(total).toLocaleString('fr-FR');
        _el('modal-months').textContent=`(${months} mois)`;
    },
    submitForm() {
        const du     = _el('modal-du').value;
        const au     = _el('modal-au').value;
        const client = $('#modal-client-select').val();  // ← via Select2
        const errors = [];

        if (!client) {
            errors.push('Veuillez sélectionner un client.');
            _el('modal-client-err').classList.remove('hidden');
        } else {
            _el('modal-client-err').classList.add('hidden');
        }
        if (!du)            errors.push('La date de début est obligatoire.');
        if (!au)            errors.push('La date de fin est obligatoire.');
        if (du && au && au <= du) errors.push('La date de fin doit être après la date de début.');

        if (errors.length > 0) {
            const box = _el('modal-errors');
            box.innerHTML = errors
                .map(e => `<div class="flex gap-2"><span>⚠️</span><span>${e}</span></div>`)
                .join('');
            box.classList.remove('hidden');
            return;
        }

        // Synchroniser la valeur Select2 → hidden inputs
        _el('hidden-panels').innerHTML = S.sel.ids
            .map(id => `<input type="hidden" name="panel_ids[]" value="${id}">`)
            .join('');

        const btn = _el('modal-submit');
        _el('modal-submit-txt').textContent = 'Envoi en cours…';
        btn.disabled = true;

        _el('form-confirm').submit();
    },

    openFiche(p){
        _el('fiche-title').textContent=`📋 ${p.reference} — ${p.name}`;
        const src=p.source==='external'?`🤝 ${p.agency_name}`:'🏢 Interne';
        const fields=[['RÉFÉRENCE',p.reference],['SOURCE',src],['COMMUNE',p.commune],['ZONE',p.zone],['FORMAT',p.format],['DIMENSIONS',p.dimensions||'—'],['ÉCLAIRAGE',p.is_lit?'💡 Éclairé':'Non éclairé'],['TRAFIC/JOUR',p.daily_traffic>0?p.daily_traffic.toLocaleString('fr-FR')+' contacts':'—']];
        _el('fiche-body').innerHTML=`<div class="grid grid-cols-2 gap-2 mb-4">${fields.map(([l,v])=>`<div class="bg-[#252530] rounded-lg p-3"><div class="text-[9px] text-gray-500 font-bold uppercase tracking-wider mb-1">${l}</div><div class="text-sm text-gray-200 font-medium">${v||'—'}</div></div>`).join('')}</div><div class="bg-[#e8a020]/5 border border-[#e8a020]/20 rounded-xl p-4 text-center mb-3"><div class="text-xs text-gray-500 mb-1">TARIF MENSUEL</div><div class="text-2xl font-black text-[#e8a020]">${p.monthly_rate?Math.round(p.monthly_rate).toLocaleString('fr-FR')+' FCFA':'—'}</div></div>${p.zone_description?`<div class="text-xs text-gray-500 mb-1 uppercase font-bold">Zone</div><div class="bg-[#252530] rounded-xl p-3 text-xs text-gray-300">${p.zone_description}</div>`:''}`;
        _show('modal-fiche');
    },
    closeFiche(){_hide('modal-fiche');},
    showError(msgs){_el('error-body').innerHTML=(Array.isArray(msgs)?msgs:[msgs]).map(m=>`<div class="flex gap-2 items-start"><span class="text-red-400">•</span><span>${m}</span></div>`).join('');_show('modal-error');},
    closeError(){_hide('modal-error');},

    // ══ MÉTHODES CRÉATION RAPIDE CLIENT ══
    openQuickClientModal() {
        const form = _el('form-quick-client');
        if (form) form.reset();
        _el('quick-client-errors').classList.add('hidden');
        _el('qc-submit-txt').textContent = 'Créer le client';
        _el('qc-submit').disabled = false;
        _show('modal-quick-client');
        // Focus sur le premier champ
        setTimeout(() => _el('qc-name')?.focus(), 100);
    },

    closeQuickClientModal() {
        _hide('modal-quick-client');
    },
    async submitQuickClient(event) {
        event.preventDefault();

        const btn = _el('qc-submit');
        const errBox = _el('quick-client-errors');
        errBox.classList.add('hidden');

        // Validation front légère
        const name = _el('qc-name').value.trim();
        if (!name) {
            errBox.innerHTML = '<div class="flex gap-2"><span>⚠️</span><span>Le nom est obligatoire.</span></div>';
            errBox.classList.remove('hidden');
            _el('qc-name').focus();
            return;
        }

        // Loader
        _el('qc-submit-icon').textContent = '⟳';
        _el('qc-submit-txt').textContent  = 'Création…';
        btn.disabled = true;

        try {
            const res = await fetch('{{ route("admin.clients.quick-store") }}', {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': D.csrf,
                },
                body: JSON.stringify({
                    name:         _el('qc-name').value.trim(),
                    ncc:          _el('qc-ncc').value.trim()    || null,
                    email:        _el('qc-email').value.trim()  || null,
                    phone:        _el('qc-phone').value.trim()  || null,
                    contact_name: _el('qc-contact').value.trim()|| null,
                }),
            });

            const data = await res.json();

            if (!res.ok) {
                // Erreurs de validation Laravel
                const messages = data.errors
                    ? Object.values(data.errors).flat()
                    : [data.message || 'Erreur lors de la création.'];

                errBox.innerHTML = messages
                    .map(m => `<div class="flex gap-2"><span>⚠️</span><span>${m}</span></div>`)
                    .join('');
                errBox.classList.remove('hidden');
                return;
            }

            // Succès — ajouter au Select2 et fermer
            addClientToSelect2(data.id, data.name);
            this.closeQuickClientModal();
            this.showSuccessToast(`Client "${data.name}" créé ✅`);

        } catch (err) {
            errBox.innerHTML = `<div class="flex gap-2"><span>⚠️</span><span>Erreur réseau : ${err.message}</span></div>`;
            errBox.classList.remove('hidden');
        } finally {
            _el('qc-submit-icon').textContent = '🏢';
            _el('qc-submit-txt').textContent  = 'Créer le client';
            btn.disabled = false;
        }
    },

    showSuccessToast(message) {
        const toast = document.createElement('div');
        toast.style.cssText = `
            position:fixed; bottom:24px; right:24px; z-index:10002;
            background:#1e1e2e; border:1px solid rgba(34,197,94,.4);
            border-left:3px solid #22c55e;
            color:#e8e8f0; padding:12px 16px; border-radius:12px;
            font-size:13px; font-family:'DM Sans',sans-serif;
            box-shadow:0 8px 32px rgba(0,0,0,.5);
            display:flex; align-items:center; gap:8px;
            animation: slideInToast .3s ease;
            max-width:360px;
        `;
        toast.innerHTML = `<span style="color:#22c55e;font-size:16px;">✅</span><span>${message}</span>`;
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            toast.style.transition = 'all .3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    _fetch(delay){clearTimeout(S.debounce);S.debounce=setTimeout(()=>this._doFetch(),delay!==undefined?delay:300);},

    async _doFetch(){
        const rid=++S.reqId;S.loading=true;_showLoader();
        const p=new URLSearchParams();
        S.f.commune_ids.forEach(id=>p.append('commune_ids[]',id));
        S.f.zone_ids.forEach(id=>p.append('zone_ids[]',id));
        S.f.format_ids.forEach(id=>p.append('format_ids[]',id));
        S.f.agency_ids.forEach(id=>p.append('agency_ids[]',id));
        if(S.f.dimensions)p.set('dimensions',S.f.dimensions);
        if(S.f.is_lit!=='')p.set('is_lit',S.f.is_lit);
        if(S.f.statut!=='tous')p.set('statut',S.f.statut);
        if(S.f.du)p.set('dispo_du',S.f.du);
        if(S.f.au)p.set('dispo_au',S.f.au);
        if(S.f.source!=='all')p.set('source',S.f.source);
        if(S.f.q)p.set('q',S.f.q);
        p.set('page',S.page);p.set('per_page',S.perPage);
        try{
            const res=await fetch(`${D.ajaxUrl}?${p}`,{headers:{Accept:'application/json','X-CSRF-TOKEN':D.csrf}});
            if(rid!==S.reqId)return;
            if(!res.ok)throw new Error(`HTTP ${res.status}`);
            const data=await res.json();
            S.loading=false;
            if(data.date_error){_showDateErr(data.date_error);_showEmpty(data.date_error,'');return;}
            S.pages=data.stats.pages||1;S.total=data.stats.total||0;S._lastPanels=data.panels||[];
            this._renderPanels(data.panels);this._renderStats(data.stats,data.has_period);this._renderPagination(data.stats);
        }catch(err){if(rid!==S.reqId)return;S.loading=false;_showEmpty('Erreur de chargement','Vérifiez votre connexion.');console.error('[DISPO]',err);}
    },

    _renderPanels(panels){
        const grid=_el('panels-grid'),empty=_el('empty-state');_hide('loader');
        if(!panels||panels.length===0){grid.innerHTML='';_el('panels-list-body').innerHTML='';empty.style.display='block';return;}
        empty.style.display='none';
        const frag=document.createDocumentFragment();
        panels.forEach(p=>{const div=document.createElement('div');div.innerHTML=this._cardHtml(p);frag.appendChild(div.firstElementChild);});
        grid.innerHTML='';grid.appendChild(frag);
        if(S.view==='list')this._renderList(panels);
        S.sel.ids.forEach(id=>{const card=grid.querySelector(`.panel-card[data-id="${id}"]`);if(!card)return;card.classList.add('selected');const btn=card.querySelector('.btn-sel');if(btn){btn.textContent='✓ Sélectionné';btn.style.background='var(--accent)';btn.style.color='#000';}const chk=card.querySelector('.card-chk');if(chk)chk.checked=true;});
    },

    _cardHtml(p){
        const sc=STATUS_CFG[p.display_status]||STATUS_CFG.libre;
        const bg=D.colors[p.card_color_idx||0]||'#3b82f6';
        const isSel=S.sel.ids.includes(String(p.id));
        const thumbStyle=p.photo_url?`background:url('${p.photo_url}') center/cover no-repeat;`:`background:${bg};`;
        const tags=[p.format?`<span class="tag">${p.format}</span>`:'',p.dimensions?`<span class="tag">${p.dimensions}</span>`:'',p.is_lit?`<span class="tag" style="color:#e8a020">💡</span>`:''].filter(Boolean).join('');
        const releaseHtml=p.release_info?`<div style="margin-top:4px;padding:4px 8px;border-radius:6px;font-size:10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);"><span style="color:${p.release_info.color==='green'?'#22c55e':p.release_info.color==='orange'?'#e8a020':'#9ca3af'}">📅 ${p.release_info.label}</span></div>`:'';
        const selBtn=p.is_selectable?`<button type="button" class="btn-sel" style="flex:1.2;font-size:11px;padding:6px 10px;border-radius:7px;background:${isSel?'var(--accent)':'var(--surface3)'};color:${isSel?'#000':'var(--text)'};border:1px solid ${isSel?'transparent':'var(--border2,#3a3a48)'};cursor:pointer;transition:all .15s;" onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')">${isSel?'✓ Sélectionné':'+ Sélectionner'}</button>`:`<div style="flex:1.2;padding:6px 10px;background:var(--surface3,#1a1a2a);border-radius:7px;font-size:11px;color:var(--text3,#6b7280);text-align:center;border:1px solid var(--border,#2a2a35);">${sc.l}</div>`;
        const safeP=encodeURIComponent(JSON.stringify(p));
        return `<div class="panel-card${p.is_selectable?' selectable':''}${isSel?' selected':''}" data-id="${p.id}" ${p.is_selectable?`onclick="DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')"`:''}>${p.source==='external'?`<div style="position:absolute;top:8px;left:8px;z-index:2;font-size:9px;font-weight:700;padding:2px 7px;border-radius:6px;background:rgba(59,130,246,.15);color:#60a5fa;border:1px solid rgba(59,130,246,.3)">🤝 ${p.agency_name}</div>`:''} ${p.is_selectable?`<div style="position:absolute;top:10px;left:10px;z-index:2;"><input type="checkbox" class="card-chk" style="accent-color:#e8a020;width:16px;height:16px;cursor:pointer;" ${isSel?'checked':''} onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')"></div>`:''}<div style="position:absolute;top:8px;right:8px;z-index:2;padding:4px 10px;border-radius:20px;font-size:10px;font-weight:700;background:${sc.c};color:white;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 8px rgba(0,0,0,.3);">${sc.l}</div><div style="height:96px;flex-shrink:0;position:relative;overflow:hidden;${thumbStyle}"><div style="position:absolute;inset:0;background:${p.photo_url?'linear-gradient(to bottom,rgba(0,0,0,.1),rgba(0,0,0,.65))':'rgba(0,0,0,.15)'}"></div><div style="position:absolute;bottom:8px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.75);border-radius:7px;padding:4px 14px;font-family:monospace;font-size:13px;font-weight:700;color:#fff;letter-spacing:1.5px;white-space:nowrap;backdrop-filter:blur(4px);">${p.reference}</div></div><div style="padding:12px 14px;flex:1;display:flex;flex-direction:column;"><div style="font-size:10px;color:var(--text3,#6b7280);margin-bottom:2px;">${p.commune}${p.zone&&p.zone!=='—'?' · '+p.zone:''}</div><div style="font-weight:700;font-size:13px;color:var(--text,#e8e8f0);margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${p.name}">${p.name}</div><div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px;">${tags}</div>${p.zone_description?`<div style="font-size:11px;color:var(--text2,#9ca3af);margin-bottom:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${p.zone_description}">📍 ${p.zone_description}</div>`:''}<div style="margin-top:auto;padding-top:8px;border-top:1px solid var(--border,#2a2a35);"><div style="font-size:17px;font-weight:800;color:var(--accent,#e8a020);margin-bottom:6px;">${p.monthly_rate?Math.round(p.monthly_rate/1000).toLocaleString('fr-FR')+'K <span style="font-size:11px;font-weight:400;color:var(--text3,#6b7280)">FCFA/mois</span>':'<span style="font-size:13px;color:var(--text3,#6b7280)">Tarif non défini</span>'}</div>${releaseHtml}<div style="display:flex;gap:6px;margin-top:8px;"><button type="button" style="flex:none;font-size:10px;padding:6px 10px;border-radius:7px;background:var(--surface,#1a1a2a);border:1px solid var(--border,#2a2a35);color:var(--text2,#9ca3af);cursor:pointer;" onclick="event.stopPropagation();DISPO.openFiche(JSON.parse(decodeURIComponent(this.dataset.p)))" data-p="${safeP}">📋 Fiche</button>${selBtn}</div></div></div></div>`;
    },

    _renderList(panels){
        const tbody=_el('panels-list-body');if(!tbody)return;
        const frag=document.createDocumentFragment();
        panels.forEach(p=>{
            const sc=STATUS_CFG[p.display_status]||STATUS_CFG.libre;
            const isSel=S.sel.ids.includes(String(p.id));
            const tr=document.createElement('tr');tr.className=`list-row${isSel?' selected':''}`;tr.dataset.id=p.id;
            if(p.is_selectable)tr.onclick=()=>DISPO.toggle(p.id,p.monthly_rate,p.source);
            const safeP=encodeURIComponent(JSON.stringify(p));
            tr.innerHTML=`<td style="padding:10px 8px;width:36px;text-align:center;">${p.is_selectable?`<input type="checkbox" class="card-chk" style="accent-color:#e8a020;width:15px;height:15px;cursor:pointer;" ${isSel?'checked':''} onclick="event.stopPropagation();DISPO.toggle('${p.id}',${p.monthly_rate},'${p.source}')">`:`<span style="font-size:12px;opacity:.4;">🔒</span>`}</td><td style="padding:10px 8px;"><span style="font-family:monospace;font-weight:700;font-size:12px;padding:3px 8px;border-radius:6px;background:${sc.b};color:${sc.c}">${p.reference}</span>${p.source==='external'?`<span style="display:block;font-size:9px;color:#60a5fa;margin-top:2px;">🤝 ${p.agency_name}</span>`:''}</td><td style="padding:10px 8px;"><div style="font-weight:600;font-size:13px;color:#e8e8f0;">${p.name}</div><div style="font-size:11px;color:#6b7280;">${p.commune}${p.zone&&p.zone!=='—'?' · '+p.zone:''}</div></td><td style="padding:10px 8px;font-size:12px;color:#9ca3af;">${p.format||'—'}</td><td style="padding:10px 8px;font-size:12px;color:#9ca3af;">${p.dimensions||'—'}${p.is_lit?' 💡':''}</td><td style="padding:10px 8px;"><div style="font-weight:700;color:#e8a020;font-size:13px;">${p.monthly_rate?Math.round(p.monthly_rate/1000).toLocaleString('fr-FR')+'K':'—'} <span style="font-size:10px;font-weight:400;color:#6b7280">FCFA</span></div></td><td style="padding:10px 8px;"><span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px;background:${sc.b};color:${sc.c};border:1px solid ${sc.bd}">${sc.l}</span>${p.release_info?`<div style="font-size:10px;color:#6b7280;margin-top:3px;">📅 ${p.release_info.label}</div>`:''}</td><td style="padding:10px 8px;"><button type="button" style="font-size:10px;padding:5px 10px;border-radius:6px;background:#1a1a2a;border:1px solid #3a3a48;color:#9ca3af;cursor:pointer;" onclick="event.stopPropagation();DISPO.openFiche(JSON.parse(decodeURIComponent(this.dataset.p)))" data-p="${safeP}">📋 Fiche</button></td>`;
            frag.appendChild(tr);
        });
        tbody.innerHTML='';tbody.appendChild(frag);
        S.sel.ids.forEach(id=>{const row=tbody.querySelector(`.list-row[data-id="${id}"]`);if(row){row.classList.add('selected');const chk=row.querySelector('.card-chk');if(chk)chk.checked=true;}});
    },

    _renderStats(stats,hasPeriod){
        const set=(id,html,show=true)=>{const el=_el(id);if(!el)return;el.style.display=show?'inline-flex':'none';if(show)el.innerHTML=html;};
        set('stat-total',`📊 <strong>${stats.total}</strong> panneau(x)`);
        set('stat-dispo',  `✅ <strong>${stats.disponibles}</strong> dispos`,   hasPeriod&&stats.disponibles>0);
        set('stat-occupes',`🔒 <strong>${stats.occupes}</strong> occupés`,      hasPeriod&&stats.occupes>0);
        set('stat-options',`⏳ <strong>${stats.options}</strong> options`,      hasPeriod&&(stats.options||0)>0);
        set('stat-ext',    `🤝 <strong>${stats.externes}</strong> externes`,    stats.externes>0);
    },

    _renderPagination(stats){
        const bar=_el('pagination-bar'),info=_el('pag-info'),prev=_el('btn-prev'),next=_el('btn-next');
        if(!bar)return;if(stats.pages<=1){bar.classList.add('hidden');return;}
        bar.classList.remove('hidden');
        const from=(S.page-1)*S.perPage+1,to=Math.min(S.page*S.perPage,stats.total);
        if(info)info.textContent=`${from}–${to} sur ${stats.total}`;
        if(prev)prev.disabled=S.page<=1;if(next)next.disabled=S.page>=stats.pages;
    },

    _syncSelBar(){
        const n=S.sel.ids.length,total=Object.values(S.sel.rates).reduce((s,r)=>s+r,0),nExt=Object.values(S.sel.sources).filter(s=>s==='external').length;
        _el('sel-bar').style.display=n>0?'block':'none';
        const tw=_el('topbar-confirm-wrapper');if(tw)tw.style.display=n>0?'block':'none';
        _el('sel-count').textContent=n;_el('sel-amount').textContent=Math.round(total).toLocaleString('fr-FR')+' FCFA/mois';
        _el('topbar-count').textContent=n;
        const eb=_el('sel-ext-badge');if(eb){eb.classList.toggle('hidden',nExt===0);_el('sel-ext-n').textContent=nExt;}
    },

    _syncUI(){
        const f=S.f,active=f.commune_ids.length||f.zone_ids.length||f.format_ids.length||f.agency_ids.length||f.dimensions||f.is_lit!==''||f.statut!=='tous'||f.du||f.au||f.source!=='all'||f.q;
        _el('btn-reset').classList.toggle('hidden',!active);this._renderTags();
    },

    _renderTags(){
        const f=S.f,tags=[];
        const addMS=(ids,key,data)=>ids.forEach(id=>{const it=data.find(x=>x.id===id||x.id===parseInt(id));if(it)tags.push({l:it.name,rm:()=>{const i=S.f[key].indexOf(id);if(i>-1)S.f[key].splice(i,1);S.page=1;_syncMs(key);this._fetch();this._syncUI();}});});
        addMS(f.commune_ids,'commune_ids',D.communes);addMS(f.zone_ids,'zone_ids',D.zones);addMS(f.format_ids,'format_ids',D.formats);addMS(f.agency_ids,'agency_ids',D.agencies);
        if(f.dimensions)tags.push({l:f.dimensions,rm:()=>{S.f.dimensions='';_el('f-dimensions').value='';S.page=1;this._fetch();this._syncUI();}});
        if(f.is_lit==='1')tags.push({l:'💡 Éclairé',rm:()=>{S.f.is_lit='';_el('f-is_lit').value='';S.page=1;this._fetch();this._syncUI();}});
        if(f.is_lit==='0')tags.push({l:'Non éclairé',rm:()=>{S.f.is_lit='';_el('f-is_lit').value='';S.page=1;this._fetch();this._syncUI();}});
        if(f.statut!=='tous')tags.push({l:'Statut: '+f.statut,rm:()=>{S.f.statut='tous';_el('f-statut').value='tous';S.page=1;this._fetch();this._syncUI();}});
        if(f.q)tags.push({l:'🔍 '+f.q,rm:()=>{S.f.q='';_el('f-search').value='';_el('btn-clear-search').classList.add('hidden');S.page=1;this._fetch();this._syncUI();}});
        const bar=_el('tags-bar'),list=_el('tags-list');if(!bar||!list)return;
        bar.classList.toggle('hidden',tags.length===0);bar.classList.toggle('flex',tags.length>0);
        list.innerHTML=tags.map((t,i)=>`<span class="ms-chip">${t.l}<button type="button" onclick="__tagRm(${i})" title="Retirer">✕</button></span>`).join('');
        window.__tagCbs=tags.map(t=>t.rm);
    },
};

// ══ MULTISELECT ══
const MS={};
function buildMs(wrapper){
    const key=wrapper.dataset.key,ph=wrapper.dataset.placeholder||'Sélectionner',data=MS_DATA[key]||[];
    const btn=document.createElement('button');btn.type='button';btn.className='ms-btn';btn.innerHTML=`<span class="ms-tags-inner"><span class="ms-placeholder">${ph}</span></span>`;
    const drop=document.createElement('div');drop.className='ms-drop';drop.style.display='none';
    const srch=document.createElement('div');srch.className='ms-search';const si=document.createElement('input');si.type='text';si.placeholder='Rechercher…';si.autocomplete='off';srch.appendChild(si);drop.appendChild(srch);
    const listEl=document.createElement('div');listEl.className='ms-list';drop.appendChild(listEl);
    const foot=document.createElement('div');foot.className='ms-foot';foot.innerHTML=`<span id="ms-foot-${key}">0 sélectionné(s)</span><div><button type="button" onclick="__msAll('${key}')">Tout</button><button type="button" onclick="__msClear('${key}')">Aucun</button></div>`;drop.appendChild(foot);
    wrapper.appendChild(btn);wrapper.appendChild(drop);

    function render(q=''){
        const sel=S.f[key],filtered=q?data.filter(i=>i.name.toLowerCase().includes(q.toLowerCase())):data;
        if(filtered.length===0){listEl.innerHTML='<div class="ms-opt" style="justify-content:center;font-style:italic">Aucun résultat</div>';return;}
        const frag=document.createDocumentFragment();
        filtered.forEach(item=>{
            const isSel=sel.includes(item.id)||sel.includes(String(item.id));
            const lbl=document.createElement('label');lbl.className='ms-opt'+(isSel?' selected':'');lbl.dataset.id=item.id;
            const dim=(key==='format_ids'&&item.width&&item.height)?` <small style="color:#6b7280">(${Math.round(item.width)}×${Math.round(item.height)}m)</small>`:'';
            lbl.innerHTML=`<input type="checkbox" ${isSel?'checked':''}> ${item.name}${dim}`;
            lbl.querySelector('input').addEventListener('change',()=>{const arr=S.f[key];const idx=arr.indexOf(item.id);if(idx===-1)arr.push(item.id);else arr.splice(idx,1);lbl.classList.toggle('selected',arr.includes(item.id));updateTrigger();updateFoot();S.page=1;DISPO._fetch();DISPO._syncUI();});
            frag.appendChild(lbl);
        });
        listEl.innerHTML='';listEl.appendChild(frag);
    }

    function updateTrigger(){
        const sel=S.f[key],inner=btn.querySelector('.ms-tags-inner');if(!inner)return;
        if(sel.length===0){inner.innerHTML=`<span class="ms-placeholder">${ph}</span>`;}
        else{inner.innerHTML=sel.map(id=>{const it=data.find(x=>x.id===id||x.id===parseInt(id));return it?`<span class="ms-chip">${it.name}<button type="button" onclick="event.preventDefault();event.stopPropagation();__msRemove('${key}',${id})" title="Retirer">✕</button></span>`:'';}).join('');}
        const badge=_el(`badge-${key}`);if(badge){badge.textContent=sel.length;badge.classList.toggle('hidden',sel.length===0);}
        listEl.querySelectorAll('label.ms-opt').forEach(l=>{const id=parseInt(l.dataset.id);const c=l.querySelector('input');const s=sel.includes(id)||sel.includes(String(id));if(c)c.checked=s;l.classList.toggle('selected',s);});
    }

    function updateFoot(){const n=S.f[key].length;const el=_el(`ms-foot-${key}`);if(el)el.textContent=n+' sélectionné(s)';}

    let stimer;si.addEventListener('input',()=>{clearTimeout(stimer);stimer=setTimeout(()=>render(si.value),150);});
    btn.addEventListener('click',e=>{e.stopPropagation();const isOpen=drop.style.display!=='none';_closeAllMs();if(!isOpen){drop.style.display='flex';btn.classList.add('open');render('');si.value='';si.focus();updateFoot();}});
    MS[key]={el:wrapper,btn,drop,listEl,render,updateTrigger,updateFoot};
}

function _syncMs(key){MS[key]?.updateTrigger();}
function _closeAllMs(){Object.values(MS).forEach(m=>{m.drop.style.display='none';m.btn.classList.remove('open');});}
window.__msAll=k=>{const d=MS_DATA[k]||[];const q=MS[k]?.drop?.querySelector('.ms-search input')?.value?.toLowerCase()||'';const visible=q?d.filter(i=>i.name.toLowerCase().includes(q)):d;visible.forEach(i=>{if(!S.f[k].includes(i.id)&&!S.f[k].includes(String(i.id)))S.f[k].push(i.id);});MS[k]?.updateTrigger();MS[k]?.updateFoot();S.page=1;DISPO._fetch();DISPO._syncUI();};
window.__msClear=k=>{S.f[k]=[];MS[k]?.updateTrigger();MS[k]?.updateFoot();S.page=1;DISPO._fetch();DISPO._syncUI();};
window.__msRemove=(k,id)=>{const i=S.f[k].indexOf(id);const i2=S.f[k].indexOf(String(id));if(i>-1)S.f[k].splice(i,1);else if(i2>-1)S.f[k].splice(i2,1);MS[k]?.updateTrigger();MS[k]?.updateFoot();S.page=1;DISPO._fetch();DISPO._syncUI();};
document.addEventListener('click',_closeAllMs);

// ══ UTILS ══
function _el(id){return document.getElementById(id);}
function _show(id){const el=_el(id);if(el)el.style.display='flex';}
function _hide(id){const el=_el(id);if(el)el.style.display='none';}
function _showLoader(){const l=_el('loader'),g=_el('panels-grid'),e=_el('empty-state'),p=_el('pagination-bar');if(l)l.style.display='block';if(g)g.innerHTML='';const tb=_el('panels-list-body');if(tb)tb.innerHTML='';if(e)e.style.display='none';if(p)p.classList.add('hidden');}
function _showEmpty(title,sub){_hide('loader');const g=_el('panels-grid');if(g)g.innerHTML='';const tb=_el('panels-list-body');if(tb)tb.innerHTML='';const e=_el('empty-state');if(e)e.style.display='block';const t=_el('empty-title');if(t)t.textContent=title;const s=_el('empty-sub');if(s)s.textContent=sub;}
function _showDateErr(msg){const el=_el('date-error');if(el){el.textContent='⚠️ '+msg;el.classList.remove('hidden');}}
function _hideDateErr(){const el=_el('date-error');if(el)el.classList.add('hidden');}
function _months(s,e){const a=new Date(s),b=new Date(e);const m=Math.floor((b-a)/(1000*60*60*24*30));const rem=Math.floor((b-new Date(a.getFullYear(),a.getMonth()+m,a.getDate()))/(1000*60*60*24));return Math.max(rem>0?m+1:m,1);}

document.addEventListener('DOMContentLoaded',()=>{
    document.querySelectorAll('.ms-wrapper').forEach(buildMs);
    const dimSel=_el('f-dimensions');
    if(dimSel)D.dimensions.forEach(d=>{const o=document.createElement('option');o.value=d;o.textContent=d;dimSel.appendChild(o);});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'){DISPO.closeConfirmModal();DISPO.closeFiche();DISPO.closeError();_closeAllMs();}});
    if(D.hasErrors&&D.flashErrors.length>0)DISPO.showError(D.flashErrors);
    DISPO._fetch(0);DISPO._syncSelBar();
    initClientSelect();
});
})();
</script>
@endpush
</x-admin-layout>
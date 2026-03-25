<x-admin-layout title="Disponibilités & Panneaux">

<x-slot:topbarActions>
    <div id="topbar-confirm-wrapper" style="display:none">
        <button class="btn btn-primary" onclick="APP.openConfirmModal()">
            ✅ Confirmer sélection (<span id="topbar-count">0</span>)
        </button>
    </div>
</x-slot:topbarActions>

{{-- ══ DONNÉES INIT ══ --}}
<script>
window.__DISPO_INIT__ = {
    communes:   {!! json_encode($communes->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])) !!},
    zones:      {!! json_encode($zones->map(fn($z) => ['id'=>$z->id,'name'=>$z->name])) !!},
    formats:    {!! json_encode($formats->map(fn($f) => ['id'=>$f->id,'name'=>$f->name,'width'=>$f->width,'height'=>$f->height])) !!},
    dimensions: {!! json_encode($dimensions) !!},
    clients:    {!! json_encode($clients->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])) !!},
    agencies:   {!! json_encode($agencies->map(fn($a) => ['id'=>$a->id,'name'=>$a->name])) !!},
    ajaxUrl:    '{{ route('admin.disponibilites.panneaux') }}',
    csrfToken:  '{{ csrf_token() }}',
    cardColors: ['#3b82f6','#a855f7','#f97316','#14b8a6','#e8a020','#22c55e'],
};
</script>

<div id="dispo-app">

{{-- ══ FILTRES AVEC DESIGN AMÉLIORÉ (Tailwind) ══ --}}
<div class="bg-[#1a1a2a] rounded-2xl border border-[#2a2a35] p-5 mb-6">
    {{-- Barre de recherche --}}
    <div class="mb-5">
        <div class="relative max-w-md">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">🔍</span>
            <input type="text" id="f-search" 
                   class="w-full h-11 pl-9 pr-10 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 placeholder:text-gray-500 focus:border-[#e8a020] focus:outline-none focus:ring-2 focus:ring-[#e8a020]/20"
                   placeholder="Rechercher par référence, nom, zone, commune..."
                   oninput="APP.onSearchChange(this.value)">
            <button type="button" id="clear-search" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 hidden" onclick="APP.clearSearch()">✕</button>
        </div>
    </div>

    {{-- Grille des filtres --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        {{-- Commune --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 flex justify-between">
                <span>📍 Commune</span>
                <span id="badge-communes" class="bg-[#e8a020] text-black rounded-full px-2 py-0.5 text-[10px] font-bold hidden"></span>
            </label>
            <div class="multiselect-wrapper mt-1" id="ms-communes" data-filter="commune_ids" data-placeholder="Sélectionner"></div>
        </div>

        {{-- Zone --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 flex justify-between">
                <span>🗺️ Zone</span>
                <span id="badge-zones" class="bg-[#e8a020] text-black rounded-full px-2 py-0.5 text-[10px] font-bold hidden"></span>
            </label>
            <div class="multiselect-wrapper mt-1" id="ms-zones" data-filter="zone_ids" data-placeholder="Sélectionner"></div>
        </div>

        {{-- Format --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 flex justify-between">
                <span>📏 Format</span>
                <span id="badge-formats" class="bg-[#e8a020] text-black rounded-full px-2 py-0.5 text-[10px] font-bold hidden"></span>
            </label>
            <div class="multiselect-wrapper mt-1" id="ms-formats" data-filter="format_ids" data-placeholder="Sélectionner"></div>
        </div>

        {{-- Dimensions --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">📐 Dimensions</label>
            <select id="f-dimensions" class="w-full h-10 mt-1 px-3 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 focus:border-[#e8a020] focus:outline-none" onchange="APP.setFilter('dimensions', this.value)">
                <option value="">Toutes</option>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        {{-- Éclairage --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">💡 Éclairage</label>
            <select id="f-is-lit" class="w-full h-10 mt-1 px-3 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 focus:border-[#e8a020] focus:outline-none" onchange="APP.setFilter('is_lit', this.value)">
                <option value="">Tous</option>
                <option value="1">💡 Éclairé</option>
                <option value="0">🌙 Non éclairé</option>
            </select>
        </div>

        {{-- Statut --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">📊 Statut</label>
            <select id="f-statut" class="w-full h-10 mt-1 px-3 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 focus:border-[#e8a020] focus:outline-none" onchange="APP.setFilter('statut', this.value)">
                <option value="tous">Tous</option>
                <option value="libre">✅ Disponible</option>
                <option value="occupe">🔒 Occupé</option>
                <option value="option">⏳ En option</option>
                <option value="confirme">✓ Confirmé</option>
                <option value="maintenance">🔧 Maintenance</option>
            </select>
        </div>

        {{-- Source --}}
        <div>
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">🏢 Source</label>
            <select id="f-source" class="w-full h-10 mt-1 px-3 bg-[#252530] border border-[#3a3a48] rounded-xl text-sm text-gray-200 focus:border-[#e8a020] focus:outline-none" onchange="APP.onSourceChange(this.value)">
                <option value="all">📦 Tous</option>
                <option value="internal">🏢 Internes</option>
                <option value="external">🤝 Externes</option>
            </select>
        </div>

        {{-- Régie (caché par défaut) --}}
        <div id="filter-agency-wrapper">
            <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 flex justify-between">
                <span>🤝 Régie</span>
                <span id="badge-agencies" class="bg-[#e8a020] text-black rounded-full px-2 py-0.5 text-[10px] font-bold hidden"></span>
            </label>
            <div class="multiselect-wrapper mt-1" id="ms-agencies" data-filter="agency_ids" data-placeholder="Sélectionner"></div>
        </div>
    </div>

    {{-- Bouton reset --}}
    <div class="flex justify-end mb-4">
        <button type="button" id="btn-reset-filters" class="px-4 py-2 text-sm text-gray-400 border border-[#3a3a48] rounded-xl hover:border-red-500 hover:text-red-500 transition hidden" onclick="APP.resetFilters()">
            ↻ Réinitialiser
        </button>
    </div>

    {{-- Période et statistiques --}}
    <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-[#2a2a35]">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs font-semibold text-gray-500">📅 Période</span>
            <div class="flex items-center gap-2 bg-[#252530] px-3 py-1 rounded-xl border border-[#3a3a48]">
                <input type="date" id="f-dispo-du" class="bg-transparent border-none text-sm text-gray-200 focus:outline-none" onchange="APP.onStartDateChange(this.value)">
                <span class="text-gray-500">→</span>
                <input type="date" id="f-dispo-au" class="bg-transparent border-none text-sm text-gray-200 focus:outline-none" onchange="APP.onEndDateChange(this.value)">
            </div>
            <div id="date-error-msg" class="text-xs text-red-400 bg-red-400/10 px-3 py-1 rounded-lg hidden"></div>
        </div>

        <div class="flex flex-wrap gap-2" id="stats-container">
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs" id="stat-total">
                <span>📊</span>
                <span class="font-bold text-white">0</span>
                <span class="text-gray-500">panneaux</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs hidden" id="stat-dispo">
                <span>✅</span>
                <span class="font-bold text-green-400">0</span>
                <span class="text-gray-500">dispos</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs hidden" id="stat-occupes">
                <span>🔒</span>
                <span class="font-bold text-red-400">0</span>
                <span class="text-gray-500">occupés</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs hidden" id="stat-options">
                <span>⏳</span>
                <span class="font-bold text-[#e8a020]">0</span>
                <span class="text-gray-500">options</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs hidden" id="stat-externes">
                <span>🤝</span>
                <span class="font-bold text-blue-400">0</span>
                <span class="text-gray-500">externes</span>
            </div>
            <div class="flex items-center gap-2 px-3 py-1 bg-[#252530] rounded-full border border-[#3a3a48] text-xs hidden" id="stat-a-verifier">
                <span>❓</span>
                <span class="font-bold text-gray-400">0</span>
                <span class="text-gray-500">à vérifier</span>
            </div>
        </div>
    </div>

    {{-- Tags actifs --}}
    <div id="active-tags" class="flex items-center gap-3 mt-4 pt-3 border-t border-[#2a2a35] flex-wrap hidden">
        <span class="text-xs text-gray-500">Filtres actifs :</span>
        <div id="tags-container" class="flex flex-wrap gap-2"></div>
    </div>
</div>


{{-- ══ CONTENU DYNAMIQUE ══ --}}
<div id="panels-container" style="margin-bottom:120px">

    <div id="loader" style="display:none;text-align:center;padding:60px;color:var(--text2)">
        <div style="font-size:32px;margin-bottom:12px;
                    animation:spin 1s linear infinite;display:inline-block">⟳</div>
        <div style="font-size:14px;font-weight:600">Chargement…</div>
    </div>

    <div id="panels-grid"
         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">
    </div>

    {{-- Pagination --}}
    <div id="pagination-bar"
         style="display:none;margin-top:20px;padding:12px 0;
                display:flex;justify-content:center;align-items:center;gap:12px">
        <button id="btn-prev" class="btn btn-ghost btn-sm" onclick="APP.prevPage()">← Précédent</button>
        <span id="pagination-info" style="font-size:13px;color:var(--text2)"></span>
        <button id="btn-next" class="btn btn-ghost btn-sm" onclick="APP.nextPage()">Suivant →</button>
    </div>

    <div id="empty-state" style="display:none;text-align:center;padding:80px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:12px">🪧</div>
        <div id="empty-title" style="font-size:15px;font-weight:600;margin-bottom:6px">Aucun panneau trouvé</div>
        <div id="empty-sub"   style="font-size:13px">Modifiez vos filtres.</div>
    </div>
</div>

{{-- ══ BARRE SÉLECTION ══ --}}
<div id="selection-bar"
     style="display:none;position:fixed;bottom:0;left:235px;right:0;
            background:var(--surface);border-top:2px solid var(--accent);
            padding:12px 24px;z-index:300;box-shadow:0 -4px 24px rgba(0,0,0,0.4)">
    <div style="display:flex;align-items:center;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:14px">
            <span id="sel-count" style="font-size:26px;font-weight:800;color:var(--accent)"></span>
            <div>
                <div style="font-size:11px;font-weight:600;color:var(--text2)">panneau(x) sélectionné(s)</div>
                <div style="font-size:14px;font-weight:800;color:var(--accent)">
                    <span id="sel-total"></span> FCFA/mois
                </div>
            </div>
            <div id="sel-ext-badge"
                 style="display:none;padding:3px 10px;background:rgba(96,165,250,0.12);
                        border:1px solid rgba(96,165,250,0.3);border-radius:6px;
                        font-size:11px;color:#60a5fa">
                dont <span id="sel-ext-count">0</span> externe(s)
            </div>
        </div>
        <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" onclick="APP.clearSelection()">✕ Vider</button>
            <button class="btn btn-ghost btn-sm" style="color:var(--red);border-color:rgba(239,68,68,.4)">📄 PDF</button>
            <button class="btn btn-primary" onclick="APP.openConfirmModal()">✅ Confirmer la sélection</button>
        </div>
    </div>
</div>

</div>{{-- fin #dispo-app --}}

{{-- ══ MODAL CONFIRMER ══ --}}
<div id="modal-confirm" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) APP.closeConfirmModal()">
    <div class="modal" style="max-width:560px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">✅ Confirmer la réservation</span>
            <button class="modal-close" onclick="APP.closeConfirmModal()">✕</button>
        </div>
        <form method="POST"
              action="{{ route('admin.reservations.confirmer-selection') }}"
              id="confirm-form"
              x-data="{ type: 'option' }">
            @csrf
            <div id="hidden-panel-inputs"></div>
            <div class="modal-body">
                @if($errors->any())
                <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);
                            border-radius:8px;padding:12px;margin-bottom:14px">
                    @foreach($errors->all() as $e)
                    <div style="color:var(--red);font-size:12px;display:flex;gap:5px;margin-bottom:3px">
                        <span>⚠️</span><span>{{ $e }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
                <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);
                            border-radius:8px;padding:10px 14px;margin-bottom:14px;
                            font-size:12px;color:var(--green)">
                    🛡️ Anti double-booking actif.
                </div>
                <div id="modal-ext-warning"
                     style="display:none;background:rgba(96,165,250,0.08);
                            border:1px solid rgba(96,165,250,0.3);border-radius:8px;
                            padding:10px 14px;margin-bottom:14px;font-size:12px;color:#60a5fa">
                    🤝 Votre sélection contient des panneaux externes. Confirmez leur disponibilité auprès de la régie.
                </div>
                <div style="display:flex;gap:8px;margin-bottom:14px">
                    <label style="flex:1;cursor:pointer;padding:10px;border-radius:8px;
                                  display:flex;align-items:center;gap:8px;"
                           :style="type==='option'
                               ? 'border:2px solid #f97316;background:rgba(249,115,22,0.08)'
                               : 'border:1px solid var(--border2);background:var(--surface2)'">
                        <input type="radio" name="type" value="option" x-model="type" style="accent-color:#f97316">
                        <div>
                            <div style="font-size:12px;font-weight:700">⏳ Option</div>
                            <div style="font-size:10px;color:var(--text2)">Temporaire</div>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer;padding:10px;border-radius:8px;
                                  display:flex;align-items:center;gap:8px;"
                           :style="type==='ferme'
                               ? 'border:2px solid #22c55e;background:rgba(34,197,94,0.08)'
                               : 'border:1px solid var(--border2);background:var(--surface2)'">
                        <input type="radio" name="type" value="ferme" x-model="type" style="accent-color:#22c55e">
                        <div>
                            <div style="font-size:12px;font-weight:700">🔒 Ferme</div>
                            <div style="font-size:10px;color:var(--text2)">Définitive</div>
                        </div>
                    </label>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id" required
                                style="background:var(--surface2);border:1px solid var(--border2);
                                       border-radius:8px;padding:8px 12px;color:var(--text);
                                       font-size:13px;outline:none;width:100%">
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg" x-show="type === 'ferme'" x-transition>
                        <label>Nom campagne <span style="font-size:10px;color:var(--text3)">(opt.)</span></label>
                        <input type="text" name="campaign_name" placeholder="Ex: Ramadan 2026">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">
                    <div class="mfg">
                        <label>Date début *</label>
                        <input type="date" name="start_date" id="confirm-start" required>
                    </div>
                    <div class="mfg">
                        <label>Date fin *</label>
                        <input type="date" name="end_date" id="confirm-end" required>
                    </div>
                </div>
                <div class="mfg">
                    <label>Note interne</label>
                    <textarea name="notes" rows="2" placeholder="Remarques…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="APP.closeConfirmModal()">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="APP.submitConfirm()">✅ Confirmer et bloquer</button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL FICHE ══ --}}
<div id="modal-fiche" class="modal-overlay" style="display:none"
     onclick="if(event.target===this) document.getElementById('modal-fiche').style.display='none'">
    <div class="modal" style="max-width:600px;max-height:85vh;overflow-y:auto"
         onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="fiche-title">📋 Fiche technique</span>
            <button class="modal-close"
                    onclick="document.getElementById('modal-fiche').style.display='none'">✕</button>
        </div>
        <div class="modal-body" id="fiche-body"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost"
                    onclick="document.getElementById('modal-fiche').style.display='none'">Fermer</button>
        </div>
    </div>
</div>


<style>
/* ============================================
   VARIABLES GLOBALES
   ============================================ */
:root {
    --filter-bg: #1a1a2a;
    --filter-surface: #252530;
    --filter-surface-hover: #2d2d3a;
    --filter-border: #3a3a48;
    --filter-accent: #e8a020;
    --filter-accent-dim: rgba(232, 160, 32, 0.12);
    --filter-text: #e8e8f0;
    --filter-text-dim: #9ca3af;
    --filter-text-muted: #6b7280;
    --filter-success: #22c55e;
    --filter-danger: #ef4444;
    --filter-warning: #e8a020;
    --filter-info: #60a5fa;
}

/* ============================================
   CONTAINER PRINCIPAL
   ============================================ */
.dispo-filters {
    background: var(--filter-bg);
    border-radius: 20px;
    border: 1px solid var(--filter-border);
    padding: 20px 24px;
    margin-bottom: 24px;
    transition: all 0.2s ease;
}

/* ============================================
   RECHERCHE
   ============================================ */
.filter-search {
    margin-bottom: 24px;
}

.search-input-wrapper {
    position: relative;
    max-width: 480px;
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 16px;
    color: var(--filter-text-muted);
    pointer-events: none;
    z-index: 1;
}

.search-field {
    width: 100%;
    height: 46px;
    padding: 0 40px 0 42px;
    background: var(--filter-surface);
    border: 1px solid var(--filter-border);
    border-radius: 14px;
    font-size: 14px;
    color: var(--filter-text);
    transition: all 0.2s;
}

.search-field:focus {
    outline: none;
    border-color: var(--filter-accent);
    box-shadow: 0 0 0 3px var(--filter-accent-dim);
}

.search-field::placeholder {
    color: var(--filter-text-muted);
}

.search-clear-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    color: var(--filter-text-muted);
    cursor: pointer;
    font-size: 14px;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s;
}

.search-clear-btn:hover {
    background: var(--filter-surface-hover);
    color: var(--filter-text);
}

/* ============================================
   GRILLE PRINCIPALE
   ============================================ */
.filters-main-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
    align-items: start;
    position: relative;
}

.filters-column {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-item-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--filter-text-muted);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.filter-count-badge {
    background: var(--filter-accent);
    color: #000;
    border-radius: 12px;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 700;
    display: none;
}

.filter-count-badge:not(:empty) {
    display: inline-block;
}

/* ============================================
   SELECTS STANDARDS
   ============================================ */
.filter-select {
    height: 40px;
    padding: 0 12px;
    background: var(--filter-surface);
    border: 1px solid var(--filter-border);
    border-radius: 12px;
    font-size: 13px;
    color: var(--filter-text);
    cursor: pointer;
    transition: all 0.2s;
}

.filter-select:hover {
    border-color: var(--filter-accent);
    background: var(--filter-surface-hover);
}

.filter-select:focus {
    outline: none;
    border-color: var(--filter-accent);
    box-shadow: 0 0 0 2px var(--filter-accent-dim);
}

/* ============================================
   MULTISELECT — OPTIMISÉ GRANDS VOLUMES
   ============================================ */
.multiselect-wrapper {
    position: relative;
    width: 100%;
}

.ms-trigger {
    width: 100%;
    min-height: 40px;
    padding: 6px 32px 6px 12px;
    background: #252530;
    border: 1px solid #3a3a48;
    border-radius: 12px;
    font-size: 13px;
    color: #e8e8f0;
    cursor: pointer;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
    position: relative;
}

.ms-trigger:hover {
    border-color: #e8a020;
    background: #2d2d3a;
}

.ms-trigger::after {
    content: "▾";
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 12px;
    color: #6b7280;
}

.ms-trigger.open::after {
    content: "▴";
}

.ms-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
    overflow: hidden;
    max-height: 80px;
    overflow-y: auto;
}

.ms-placeholder {
    color: #6b7280;
    font-size: 12px;
}

.ms-tag {
    background: rgba(232, 160, 32, 0.12);
    color: #e8a020;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.ms-tag button {
    background: none;
    border: none;
    color: #e8a020;
    cursor: pointer;
    font-size: 12px;
    padding: 0;
    opacity: 0.6;
}

.ms-tag button:hover {
    opacity: 1;
}

.ms-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    z-index: 1000;
    background: #252530;
    border: 1px solid #3a3a48;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    max-height: 300px;
    overflow-y: auto;
    overflow-x: hidden;
}

.ms-dropdown::-webkit-scrollbar {
    width: 5px;
}

.ms-dropdown::-webkit-scrollbar-track {
    background: #1a1a2a;
    border-radius: 10px;
}

.ms-dropdown::-webkit-scrollbar-thumb {
    background: #e8a020;
    border-radius: 10px;
}

.ms-option {
    padding: 10px 12px;
    font-size: 13px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #9ca3af;
    border-bottom: 1px solid #3a3a48;
    transition: all 0.15s;
}

.ms-option:last-child {
    border-bottom: none;
}

.ms-option:hover {
    background: #2d2d3a;
    color: #e8e8f0;
}

.ms-option.selected {
    background: rgba(232, 160, 32, 0.12);
    color: #e8a020;
}

.ms-option input {
    accent-color: #e8a020;
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* ============================================
   SEARCH DANS MULTISELECT
   ============================================ */
.ms-search {
    padding: 8px;
    border-bottom: 1px solid #3a3a48;
    background: #252530;
    position: sticky;
    top: 0;
    z-index: 2;
}

.ms-search input {
    width: 100%;
    height: 34px;
    padding: 0 10px;
    background: #1a1a2a;
    border: 1px solid #3a3a48;
    border-radius: 8px;
    font-size: 12px;
    color: #e8e8f0;
    outline: none;
    transition: all 0.2s;
}

.ms-search input::placeholder {
    color: #6b7280;
}

.ms-search input:focus {
    border-color: #e8a020;
    box-shadow: 0 0 0 2px rgba(232,160,32,0.2);
    background: #1a1a2a;
}

.ms-footer {
    padding: 8px 12px;
    border-top: 1px solid #3a3a48;
    display: flex;
    justify-content: space-between;
    font-size: 11px;
    color: #6b7280;
    background: #2d2d3a;
}

.ms-footer button {
    background: none;
    border: none;
    color: #e8a020;
    cursor: pointer;
    margin-left: 12px;
}

/* ============================================
   BOUTON RESET - BIEN POSITIONNÉ
   ============================================ */
.filter-reset {
    position: absolute;
    top: 0;
    right: 0;
    display: flex;
    align-items: flex-start;
}

.reset-btn {
    height: 40px;
    padding: 0 20px;
    background: var(--filter-surface);
    border: 1px solid var(--filter-border);
    border-radius: 12px;
    color: var(--filter-text-muted);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.reset-btn:hover {
    background: var(--filter-surface-hover);
    border-color: var(--filter-danger);
    color: var(--filter-danger);
}

/* ============================================
   PÉRIODE ET STATISTIQUES
   ============================================ */
.filter-period-stats {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    padding: 16px 0 0;
    margin-top: 8px;
    border-top: 1px solid var(--filter-border);
}

.period-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.period-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--filter-text-muted);
    letter-spacing: 0.5px;
}

.date-range {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--filter-surface);
    padding: 4px 12px;
    border-radius: 12px;
    border: 1px solid var(--filter-border);
}

.date-input {
    background: transparent;
    border: none;
    padding: 8px 0;
    font-size: 13px;
    color: var(--filter-text);
    font-family: monospace;
}

.date-input:focus {
    outline: none;
}

.date-sep {
    color: var(--filter-text-muted);
    font-size: 12px;
}

.date-error-msg {
    font-size: 12px;
    color: var(--filter-danger);
    background: rgba(239, 68, 68, 0.1);
    padding: 4px 12px;
    border-radius: 8px;
}

.stats-badges {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: var(--filter-surface);
    border-radius: 20px;
    font-size: 12px;
    border: 1px solid var(--filter-border);
    transition: all 0.2s;
}

.stat-icon {
    font-size: 13px;
}

.stat-number {
    font-weight: 700;
    font-size: 14px;
    color: var(--filter-text);
}

.stat-text {
    color: var(--filter-text-muted);
    font-size: 11px;
}

.stat-success .stat-number { color: var(--filter-success); }
.stat-danger .stat-number  { color: var(--filter-danger); }
.stat-warning .stat-number { color: var(--filter-warning); }
.stat-info .stat-number    { color: var(--filter-info); }
.stat-muted .stat-number   { color: var(--filter-text-muted); }

/* ============================================
   TAGS ACTIFS
   ============================================ */
.active-tags-bar {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--filter-border);
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.tags-label {
    font-size: 11px;
    color: var(--filter-text-muted);
    font-weight: 500;
}

.tags-list {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* ============================================
   CARDS PANNEAUX
   ============================================ */
.panel-card {
    background: var(--filter-surface);
    border-radius: 14px;
    overflow: hidden;
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 300px;
    border: 2px solid var(--filter-border);
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    contain: layout style;
}

.panel-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
}

.panel-card.selected {
    border-color: var(--filter-accent);
    box-shadow: 0 0 0 3px rgba(232, 160, 32, 0.3);
}

.panel-card.selectable {
    cursor: pointer;
}

.ext-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 2;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 700;
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    white-space: nowrap;
}

/* ============================================
   ANIMATIONS
   ============================================ */
@keyframes dropdownFade {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

/* ============================================
   RESPONSIVE
   ============================================ */
@media (max-width: 1200px) {
    .filters-main-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 14px;
    }
    
    .filter-reset {
        position: relative;
        grid-column: span 3;
        justify-content: flex-end;
        margin-top: 8px;
        padding-right: 0;
    }
}

@media (max-width: 992px) {
    .filters-main-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .filter-reset {
        grid-column: span 2;
        margin-top: 8px;
    }
}

@media (max-width: 768px) {
    .dispo-filters {
        padding: 16px;
    }
    
    .filters-main-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .filter-reset {
        position: relative;
        grid-column: span 1;
        justify-content: flex-start;
        margin-top: 4px;
    }
    
    .reset-btn {
        width: 100%;
        justify-content: center;
    }
    
    .filter-period-stats {
        flex-direction: column;
        align-items: stretch;
    }
    
    .period-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .date-range {
        justify-content: space-between;
    }
    
    .date-input {
        flex: 1;
    }
    
    .stats-badges {
        justify-content: flex-start;
    }
}

@media (max-width: 480px) {
    .stats-badges {
        gap: 6px;
    }
    
    .stat-item {
        padding: 3px 8px;
    }
    
    .stat-text {
        display: none;
    }
    
    .ms-dropdown {
        min-width: 220px;
        max-width: 280px;
    }
}
</style>

@push('scripts')
<script>
(function () {
'use strict';

const INIT   = window.__DISPO_INIT__;
const COLORS = INIT.cardColors;

// ══ STATE ════════════════════════════════════════════════════════
const STATE = {
    filters: {
        commune_ids: [], zone_ids: [], format_ids: [],
        dimensions: '', is_lit: '', statut: 'tous',
        dispo_du: '', dispo_au: '',
        source: 'all', agency_ids: [],
        q: '',
    },
    selection: { ids: [], rates: {}, sources: {} },
    loading:        false,
    debounceTimer:  null,
    searchTimer:    null,
    lastRequestId:  0,
    currentPage:    1,
    totalPages:     1,
    perPage:        48,
    totalCount:     0,
};

// ══ APP ══════════════════════════════════════════════════════════
window.APP = {

    // ── Filtres simples ────────────────────────────────────────
    setFilter(key, value) {
        STATE.filters[key] = value;
        STATE.currentPage  = 1;
        this._triggerFetch();
        this._updateUI();
    },

    onSourceChange(value) {
        STATE.filters.source = value;
        if (value === 'internal') {
            STATE.filters.agency_ids = [];
            this._syncAllMultiselects();
        }
        STATE.currentPage = 1;
        this._triggerFetch();
        this._updateUI();
    },

    onSearchChange(value) {
        STATE.filters.q = value.trim();
        STATE.currentPage = 1;
        clearTimeout(STATE.searchTimer);
        STATE.searchTimer = setTimeout(() => {
            this._triggerFetch(0); // délai 0 — déjà debounced
            this._updateUI();
        }, 300);
    },

    // ── Multiselect ────────────────────────────────────────────
    toggleMultiFilter(key, id) {
        id = parseInt(id);
        const arr = STATE.filters[key];
        const idx = arr.indexOf(id);
        if (idx === -1) arr.push(id);
        else arr.splice(idx, 1);
        STATE.currentPage = 1;
        this._syncMultiselectTrigger(key);
        this._triggerFetch();
        this._updateUI();
    },

    removeMultiFilter(key, id) {
        id = parseInt(id);
        const arr = STATE.filters[key];
        const idx = arr.indexOf(id);
        if (idx !== -1) arr.splice(idx, 1);
        STATE.currentPage = 1;
        this._syncMultiselectTrigger(key);
        this._triggerFetch();
        this._updateUI();
    },

    _syncMultiselectTrigger(key) {
        document.querySelectorAll('.multiselect-wrapper').forEach(el => {
            if (el.dataset.filter === key && el.__ms) el.__ms.updateTrigger();
        });
    },

    _syncAllMultiselects() {
        document.querySelectorAll('.multiselect-wrapper').forEach(el => {
            if (el.__ms) el.__ms.updateTrigger();
        });
    },

    // ── Dates ──────────────────────────────────────────────────
    onStartDateChange(val) {
        STATE.filters.dispo_du = val;
        STATE.currentPage = 1;
        const endInput = document.getElementById('f-dispo-au');
        if (val && endInput) {
            const d = new Date(val);
            d.setDate(d.getDate() + 1);
            endInput.min = d.toISOString().split('T')[0];
            if (endInput.value && endInput.value <= val) {
                endInput.value = '';
                STATE.filters.dispo_au = '';
            }
        }
        this._hideDateError();
        this._triggerFetch();
        this._updateUI();
    },

    onEndDateChange(val) {
        STATE.filters.dispo_au = val;
        STATE.currentPage = 1;
        if (STATE.filters.dispo_du && val && val <= STATE.filters.dispo_du) {
            this._showDateError('La date de fin doit être après la date de début.');
            STATE.filters.dispo_au = '';
            document.getElementById('f-dispo-au').value = '';
            return;
        }
        this._hideDateError();
        this._triggerFetch();
        this._updateUI();
    },

    // ── Pagination ─────────────────────────────────────────────
    prevPage() {
        if (STATE.currentPage > 1) {
            STATE.currentPage--;
            this._fetchPanels();
        }
    },

    nextPage() {
        if (STATE.currentPage < STATE.totalPages) {
            STATE.currentPage++;
            this._fetchPanels();
            // scroll en haut de la grille
            document.getElementById('panels-grid')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    },

    // ── Reset ──────────────────────────────────────────────────
    resetFilters() {
        STATE.filters = {
            commune_ids: [], zone_ids: [], format_ids: [],
            dimensions: '', is_lit: '', statut: 'tous',
            dispo_du: '', dispo_au: '',
            source: 'all', agency_ids: [],
            q: '',
        };
        STATE.currentPage = 1;

        // Reset DOM
        ['f-dimensions', 'f-is-lit'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        const s = document.getElementById('f-statut'); if (s) s.value = 'tous';
        const r = document.getElementById('f-source'); if (r) r.value = 'all';
        const du = document.getElementById('f-dispo-du');
        const au = document.getElementById('f-dispo-au');
        if (du) du.value = '';
        if (au) { au.value = ''; au.min = ''; }
        const sq = document.getElementById('f-search'); if (sq) sq.value = '';

        this._syncAllMultiselects();
        this._hideDateError();
        this._triggerFetch();
        this._updateUI();
    },

    // ── Sélection ──────────────────────────────────────────────
    togglePanel(id, rate, source) {
        rate   = parseFloat(rate) || 0;
        source = source || 'internal';
        const idx = STATE.selection.ids.indexOf(id);
        if (idx === -1) {
            STATE.selection.ids.push(id);
            STATE.selection.rates[id]   = rate;
            STATE.selection.sources[id] = source;
        } else {
            STATE.selection.ids.splice(idx, 1);
            delete STATE.selection.rates[id];
            delete STATE.selection.sources[id];
        }
        const sel  = STATE.selection.ids.includes(id);
        const card = document.querySelector(`.panel-card[data-id="${id}"]`);
        if (card) {
            card.classList.toggle('selected', sel);
            const btn = card.querySelector('.btn-select');
            if (btn) {
                btn.textContent      = sel ? '✓ Sélectionné' : '+ Sélectionner';
                btn.style.background = sel ? 'var(--accent)' : 'var(--surface3)';
                btn.style.color      = sel ? '#000' : 'var(--text)';
                btn.style.border     = sel ? 'none' : '1px solid var(--border2)';
            }
            const chk = card.querySelector('.card-checkbox');
            if (chk) chk.checked = sel;
        }
        this._updateSelectionBar();
    },

    clearSelection() {
        STATE.selection = { ids: [], rates: {}, sources: {} };
        document.querySelectorAll('.panel-card.selected').forEach(c => {
            c.classList.remove('selected');
            const btn = c.querySelector('.btn-select');
            if (btn) {
                btn.textContent = '+ Sélectionner';
                btn.style.background = 'var(--surface3)';
                btn.style.color = 'var(--text)';
                btn.style.border = '1px solid var(--border2)';
            }
            const chk = c.querySelector('.card-checkbox');
            if (chk) chk.checked = false;
        });
        this._updateSelectionBar();
    },

    // ── Modals ─────────────────────────────────────────────────
    openConfirmModal() {
        const cs = document.getElementById('confirm-start');
        const ce = document.getElementById('confirm-end');
        if (cs) cs.value = STATE.filters.dispo_du || '';
        if (ce) ce.value = STATE.filters.dispo_au || '';

        const container = document.getElementById('hidden-panel-inputs');
        if (container) {
            container.innerHTML = '';
            STATE.selection.ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'panel_ids[]'; inp.value = id;
                container.appendChild(inp);
            });
        }
        const hasExt = Object.values(STATE.selection.sources).includes('external');
        const w = document.getElementById('modal-ext-warning');
        if (w) w.style.display = hasExt ? 'block' : 'none';

        document.getElementById('modal-confirm').style.display = 'flex';
    },

    closeConfirmModal() {
        document.getElementById('modal-confirm').style.display = 'none';
    },

    submitConfirm() {
        const container = document.getElementById('hidden-panel-inputs');
        if (container) {
            container.innerHTML = '';
            STATE.selection.ids.forEach(id => {
                const inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'panel_ids[]'; inp.value = id;
                container.appendChild(inp);
            });
        }
        document.getElementById('confirm-form').submit();
    },

    openFiche(panel) {
        const tarif = panel.monthly_rate
            ? Number(panel.monthly_rate).toLocaleString('fr-FR') + ' FCFA'
            : 'Non renseigné';
        const srcLabel = panel.source === 'external' ? `🤝 ${panel.agency_name}` : '🏢 Interne';

        document.getElementById('fiche-title').textContent = `📋 ${panel.reference} — ${panel.name}`;
        document.getElementById('fiche-body').innerHTML = `
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px">
                ${[
                    ['RÉFÉRENCE', panel.reference], ['SOURCE', srcLabel],
                    ['COMMUNE', panel.commune],      ['ZONE', panel.zone],
                    ['FORMAT', panel.format],         ['DIMENSIONS', panel.dimensions],
                    ['CATÉGORIE', panel.category],    ['ÉCLAIRAGE', panel.is_lit ? '💡 Éclairé' : 'Non éclairé'],
                    ['TRAFIC/JOUR', panel.daily_traffic ? panel.daily_traffic.toLocaleString('fr-FR') + ' contacts/j' : '—'],
                ].map(([l, v]) => `
                    <div style="background:var(--surface2);border-radius:8px;padding:9px">
                        <div style="font-size:9px;color:var(--text3);font-weight:700;letter-spacing:.5px;margin-bottom:3px">${l}</div>
                        <div style="font-size:13px;color:var(--text);font-weight:500">${v || '—'}</div>
                    </div>`).join('')}
            </div>
            <div style="background:rgba(232,160,32,0.08);border:1px solid rgba(232,160,32,0.3);
                        border-radius:10px;padding:12px;text-align:center;margin-bottom:12px">
                <div style="font-size:9px;color:var(--text3);font-weight:700;letter-spacing:.5px;margin-bottom:2px">TARIF MENSUEL</div>
                <div style="font-size:22px;font-weight:800;color:var(--accent)">${tarif}</div>
            </div>
            ${panel.zone_description ? `
            <div style="font-size:9px;color:var(--text3);font-weight:700;letter-spacing:.5px;margin-bottom:5px">DESCRIPTION DE ZONE</div>
            <div style="background:var(--surface2);border-radius:8px;padding:10px;font-size:12px;color:var(--text2);line-height:1.5">
                ${panel.zone_description}
            </div>` : ''}`;

        document.getElementById('modal-fiche').style.display = 'flex';
    },

    // ══ PRIVÉ ════════════════════════════════════════════════════

    _triggerFetch(delay) {
        clearTimeout(STATE.debounceTimer);
        const d = delay !== undefined ? delay
            : (STATE.filters.dispo_du && STATE.filters.dispo_au ? 350 : 250);
        STATE.debounceTimer = setTimeout(() => this._fetchPanels(), d);
    },

    async _fetchPanels() {
        const requestId = ++STATE.lastRequestId;
        STATE.loading   = true;
        this._showLoader();

        const f      = STATE.filters;
        const params = new URLSearchParams();

        // Filtres multi-valeurs
        f.commune_ids.forEach(id => params.append('commune_ids[]', id));
        f.zone_ids.forEach(id    => params.append('zone_ids[]', id));
        f.format_ids.forEach(id  => params.append('format_ids[]', id));
        f.agency_ids.forEach(id  => params.append('agency_ids[]', id));

        // Filtres scalaires
        if (f.dimensions)    params.set('dimensions', f.dimensions);
        if (f.is_lit !== '') params.set('is_lit', f.is_lit);
        if (f.statut !== 'tous') params.set('statut', f.statut);
        if (f.dispo_du)      params.set('dispo_du', f.dispo_du);
        if (f.dispo_au)      params.set('dispo_au', f.dispo_au);
        if (f.source !== 'all') params.set('source', f.source);
        if (f.q)             params.set('q', f.q);

        // Pagination
        params.set('page',     STATE.currentPage);
        params.set('per_page', STATE.perPage);

        try {
            const resp = await fetch(`${INIT.ajaxUrl}?${params}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': INIT.csrfToken }
            });
            if (!resp.ok) throw new Error('Erreur ' + resp.status);
            const data = await resp.json();
            if (requestId !== STATE.lastRequestId) return; // requête obsolète

            STATE.loading = false;
            if (data.date_error) {
                this._showDateError(data.date_error);
                this._showEmpty(data.date_error, '');
            } else {
                this._hideDateError();
                STATE.totalPages = data.stats.pages || 1;
                STATE.totalCount = data.stats.total || 0;
                this._renderPanels(data.panels);
                this._updateStats(data.stats, data.has_period);
                this._renderPagination(data.stats);
            }
        } catch (err) {
            if (requestId !== STATE.lastRequestId) return;
            STATE.loading = false;
            this._showEmpty('Erreur de chargement. Réessayez.', '');
            console.error(err);
        }
    },

    _renderPanels(panels) {
        const grid  = document.getElementById('panels-grid');
        const empty = document.getElementById('empty-state');

        document.getElementById('loader').style.display = 'none';

        if (!panels || panels.length === 0) {
            grid.innerHTML       = '';
            empty.style.display  = 'block';
            document.getElementById('empty-title').textContent = 'Aucun panneau trouvé';
            document.getElementById('empty-sub').textContent   = 'Modifiez vos filtres.';
            return;
        }

        empty.style.display = 'none';

        // Fragment DOM — évite les reflows répétés
        const fragment = document.createDocumentFragment();
        panels.forEach(p => {
            const div = document.createElement('div');
            div.innerHTML = this._renderCard(p);
            fragment.appendChild(div.firstElementChild);
        });
        grid.innerHTML = '';
        grid.appendChild(fragment);

        // Restaurer sélection visuelle
        STATE.selection.ids.forEach(id => {
            const card = grid.querySelector(`.panel-card[data-id="${id}"]`);
            if (!card) return;
            card.classList.add('selected');
            const btn = card.querySelector('.btn-select');
            if (btn) {
                btn.textContent = '✓ Sélectionné';
                btn.style.background = 'var(--accent)';
                btn.style.color = '#000';
                btn.style.border = 'none';
            }
            const chk = card.querySelector('.card-checkbox');
            if (chk) chk.checked = true;
        });
    },

    _renderCard(p) {
        const STATUS = {
            libre:          { label:'Disponible', c:'#22c55e', bg:'rgba(34,197,94,0.08)',  bd:'rgba(34,197,94,0.3)' },
            occupe:         { label:'Occupé',      c:'#ef4444', bg:'rgba(239,68,68,0.08)',  bd:'rgba(239,68,68,0.3)' },
            option_periode: { label:'En option',   c:'#e8a020', bg:'rgba(232,160,32,0.08)', bd:'rgba(232,160,32,0.3)' },
            confirme:       { label:'Confirmé',    c:'#a855f7', bg:'rgba(168,85,247,0.08)', bd:'rgba(168,85,247,0.3)' },
            option:         { label:'Option',      c:'#e8a020', bg:'rgba(232,160,32,0.08)', bd:'rgba(232,160,32,0.3)' },
            maintenance:    { label:'Maintenance', c:'#6b7280', bg:'rgba(107,114,128,0.08)',bd:'rgba(107,114,128,0.3)' },
            a_verifier:     { label:'À vérifier',  c:'#94a3b8', bg:'rgba(148,163,184,0.08)',bd:'rgba(148,163,184,0.3)' },
        };

        const sc    = STATUS[p.display_status] || STATUS.libre;
        const bg    = COLORS[p.card_color_idx] || '#3b82f6';
        const isSel = STATE.selection.ids.includes(p.id);

        const agencyBadge = p.source === 'external'
            ? `<div class="ext-badge">🤝 ${p.agency_name}</div>` : '';

        const checkbox = p.is_selectable ? `
            <div style="position:absolute;top:10px;left:10px;z-index:3">
                <input type="checkbox" class="card-checkbox" ${isSel ? 'checked' : ''}
                       onclick="event.stopPropagation();APP.togglePanel('${p.id}',${p.monthly_rate},'${p.source}')"
                       style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer">
            </div>` : '';

        const releaseHtml = p.release_info ? `
            <div style="margin:3px 0 5px;padding:3px 7px;border-radius:5px;font-size:10px;
                        background:rgba(239,68,68,0.06);border:1px solid rgba(239,68,68,0.15)">
                <span style="color:${p.release_info.color === 'green' ? 'var(--green)' : p.release_info.color === 'orange' ? 'var(--accent)' : 'var(--text2)'}">
                    📅 ${p.release_info.label}
                </span>
            </div>` : '';

        const tags = [
            p.format     ? `<span style="background:var(--surface3);color:var(--text2);font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600">${p.format}</span>` : '',
            p.dimensions ? `<span style="background:var(--surface3);color:var(--text2);font-size:10px;padding:2px 6px;border-radius:4px">${p.dimensions}</span>` : '',
            p.is_lit     ? `<span style="background:rgba(232,160,32,0.12);color:var(--accent);font-size:10px;padding:2px 6px;border-radius:4px">💡</span>` : '',
            p.daily_traffic > 0 ? `<span style="background:var(--surface3);color:var(--text2);font-size:10px;padding:2px 6px;border-radius:4px">👁 ${p.daily_traffic.toLocaleString('fr-FR')}/j</span>` : '',
        ].filter(Boolean).join('');

        const price = p.monthly_rate
            ? `${Math.round(p.monthly_rate / 1000).toLocaleString('fr-FR')}K <span style="font-size:11px;font-weight:400;color:var(--text3)">FCFA/mois</span>`
            : `<span style="font-size:13px;color:var(--text3)">Tarif non défini</span>`;

        // Sérialisation sûre pour l'attribut data
        const panelData = encodeURIComponent(JSON.stringify(p));

        const statusLabels = {
            occupe:'🔒 Occupé', maintenance:'🔧 Maintenance',
            option_periode:'⏳ Option', option:'⏳ Option',
            confirme:'✅ Confirmé', a_verifier:'❓ À vérifier',
        };

        const actionBtn = p.is_selectable ? `
            <button type="button" class="btn btn-sm btn-select"
                    onclick="event.stopPropagation();APP.togglePanel('${p.id}',${p.monthly_rate},'${p.source}')"
                    style="flex:1.2;font-size:11px;border-radius:7px;padding:5px 8px;
                           background:${isSel ? 'var(--accent)' : 'var(--surface3)'};
                           color:${isSel ? '#000' : 'var(--text)'};
                           border:${isSel ? 'none' : '1px solid var(--border2)'}">
                ${isSel ? '✓ Sélectionné' : '+ Sélectionner'}
            </button>` : `
            <div style="flex:1.2;padding:5px 8px;background:var(--surface3);border-radius:7px;
                        font-size:11px;color:var(--text3);text-align:center;border:1px solid var(--border2)">
                ${statusLabels[p.display_status] || sc.label}
            </div>`;

        const borderColor = isSel ? 'var(--accent)'
            : (p.source === 'external' ? 'rgba(96,165,250,0.25)' : sc.bd);

        return `
<div class="panel-card selectable ${isSel ? 'selected' : ''}"
     data-id="${p.id}"
     data-source="${p.source}"
     ${p.is_selectable ? `onclick="APP.togglePanel('${p.id}',${p.monthly_rate},'${p.source}')"` : ''}
     style="border-color:${borderColor}">
    <div style="position:absolute;top:8px;right:8px;z-index:2;padding:2px 8px;border-radius:20px;
                font-size:10px;font-weight:700;background:${sc.bg};color:${sc.c};border:1px solid ${sc.bd}">
        ${sc.label}
    </div>
    ${agencyBadge}
    ${checkbox}
    <div style="background:${sc.bg};height:90px;flex-shrink:0;
                display:flex;justify-content:center;align-items:center">
        <div style="background:${bg};border-radius:7px;padding:7px 16px;
                    font-family:monospace;font-size:13px;font-weight:700;
                    color:#fff;letter-spacing:1px;box-shadow:0 4px 10px rgba(0,0,0,0.3)">
            ${p.reference}
        </div>
    </div>
    <div style="padding:10px 12px;flex:1;display:flex;flex-direction:column">
        <div style="font-size:10px;color:var(--text3);margin-bottom:1px">
            ${p.commune}${p.zone && p.zone !== '—' ? ' · ' + p.zone : ''}
        </div>
        <div style="font-weight:700;font-size:13px;color:var(--text);margin-bottom:6px;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="${p.name}">
            ${p.name}
        </div>
        <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:6px">${tags}</div>
        ${p.zone_description ? `
        <div style="font-size:11px;color:var(--text2);margin-bottom:5px;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
             title="${p.zone_description}">📍 ${p.zone_description}</div>` : ''}
        <div style="margin-top:auto;padding-top:7px;border-top:1px solid var(--border)">
            <div style="font-size:15px;font-weight:800;color:var(--accent);margin-bottom:4px">${price}</div>
            ${releaseHtml}
            <div style="display:flex;gap:5px">
                <button type="button" class="btn btn-ghost btn-sm"
                        onclick="event.stopPropagation();APP.openFiche(JSON.parse(decodeURIComponent(this.dataset.panel)))"
                        data-panel="${panelData}"
                        style="flex:1;font-size:10px;padding:4px 6px">📋 Fiche</button>
                ${actionBtn}
            </div>
        </div>
    </div>
</div>`;
    },

    _renderPagination(stats) {
        const bar  = document.getElementById('pagination-bar');
        const info = document.getElementById('pagination-info');
        const prev = document.getElementById('btn-prev');
        const next = document.getElementById('btn-next');

        if (!bar) return;

        if (stats.pages <= 1) {
            bar.style.display = 'none';
            return;
        }

        bar.style.display = 'flex';
        const from = (STATE.currentPage - 1) * STATE.perPage + 1;
        const to   = Math.min(STATE.currentPage * STATE.perPage, stats.total);
        if (info) info.textContent = `${from}–${to} sur ${stats.total} panneaux`;
        if (prev) prev.disabled = STATE.currentPage <= 1;
        if (next) next.disabled = STATE.currentPage >= stats.pages;
    },

    _updateStats(stats, hasPeriod) {
        const el = id => document.getElementById(id);

        if (el('stat-total'))
            el('stat-total').innerHTML = `<strong style="color:var(--text)">${stats.total}</strong> panneau(x)`;

        if (hasPeriod) {
            this._showStat('stat-dispo',   stats.disponibles > 0, `✅ <strong>${stats.disponibles}</strong> disponible(s)`);
            this._showStat('stat-occupes', stats.occupes > 0,     `🔒 <strong>${stats.occupes}</strong> occupé(s)`);
            this._showStat('stat-options', stats.options > 0,     `⏳ <strong>${stats.options}</strong> en option`);
        } else {
            ['stat-dispo', 'stat-occupes', 'stat-options'].forEach(id => {
                const e = el(id); if (e) e.style.display = 'none';
            });
        }
        this._showStat('stat-externes',   stats.externes   > 0, `🤝 <strong>${stats.externes}</strong> externe(s)`);
        this._showStat('stat-a-verifier', stats.a_verifier > 0, `❓ <strong>${stats.a_verifier}</strong> à vérifier`);
    },

    _showStat(id, condition, html) {
        const el = document.getElementById(id);
        if (!el) return;
        el.style.display = condition ? 'inline' : 'none';
        if (condition) el.innerHTML = html;
    },

    _updateSelectionBar() {
        const n     = STATE.selection.ids.length;
        const total = Object.values(STATE.selection.rates).reduce((s, r) => s + r, 0);
        const nExt  = Object.values(STATE.selection.sources).filter(s => s === 'external').length;

        const el = id => document.getElementById(id);
        const bar = el('selection-bar');
        if (bar) bar.style.display = n > 0 ? 'block' : 'none';

        const tw = el('topbar-confirm-wrapper');
        if (tw) tw.style.display = n > 0 ? 'block' : 'none';

        if (el('sel-count'))    el('sel-count').textContent    = n;
        if (el('sel-total'))    el('sel-total').textContent    = Math.round(total).toLocaleString('fr-FR');
        if (el('topbar-count')) el('topbar-count').textContent = n;

        const badge = el('sel-ext-badge');
        if (badge) badge.style.display = nExt > 0 ? 'flex' : 'none';
        const cnt = el('sel-ext-count');
        if (cnt) cnt.textContent = nExt;
    },

    _updateUI() {
        const f = STATE.filters;
        const hasFilters = f.commune_ids.length || f.zone_ids.length || f.format_ids.length ||
            f.agency_ids.length || f.dimensions || f.is_lit !== '' ||
            f.statut !== 'tous' || f.dispo_du || f.dispo_au ||
            f.source !== 'all' || f.q;

        const resetBtn = document.getElementById('btn-reset-filters');
        if (resetBtn) resetBtn.style.display = hasFilters ? 'block' : 'none';
        this._renderActiveTags();
    },

    _renderActiveTags() {
        const container = document.getElementById('active-tags');
        const tagsDiv   = document.getElementById('tags-container');
        if (!container || !tagsDiv) return;

        const f = STATE.filters;
        const tags = [];

        const addMulti = (ids, data, key, prefix = '') =>
            ids.forEach(id => {
                const item = data.find(x => x.id === id);
                if (item) tags.push({ label: prefix + item.name, action: `APP.removeMultiFilter('${key}',${id})` });
            });

        addMulti(f.commune_ids, INIT.communes,  'commune_ids');
        addMulti(f.zone_ids,    INIT.zones,     'zone_ids');
        addMulti(f.format_ids,  INIT.formats,   'format_ids');
        addMulti(f.agency_ids,  INIT.agencies,  'agency_ids', '🤝 ');

        if (f.dimensions) tags.push({ label: f.dimensions,
            action: `APP.setFilter('dimensions','');document.getElementById('f-dimensions').value=''` });
        if (f.is_lit === '1') tags.push({ label: '💡 Éclairé',
            action: `APP.setFilter('is_lit','');document.getElementById('f-is-lit').value=''` });
        if (f.is_lit === '0') tags.push({ label: 'Non éclairé',
            action: `APP.setFilter('is_lit','');document.getElementById('f-is-lit').value=''` });
        if (f.statut !== 'tous') tags.push({ label: 'Statut : ' + f.statut,
            action: `APP.setFilter('statut','tous');document.getElementById('f-statut').value='tous'` });
        if (f.source !== 'all') tags.push({ label: f.source === 'internal' ? '🏢 Mes panneaux' : '🤝 Externes',
            action: `APP.onSourceChange('all');document.getElementById('f-source').value='all'` });
        if (f.dispo_du) tags.push({ label: 'Du ' + f.dispo_du,
            action: `APP.setFilter('dispo_du','');document.getElementById('f-dispo-du').value=''` });
        if (f.dispo_au) tags.push({ label: 'Au ' + f.dispo_au,
            action: `APP.setFilter('dispo_au','');document.getElementById('f-dispo-au').value=''` });
        if (f.q) tags.push({ label: '🔍 ' + f.q,
            action: `APP.setFilter('q','');document.getElementById('f-search').value=''` });

        container.style.display = tags.length > 0 ? 'flex' : 'none';
        tagsDiv.innerHTML = tags.map(t => `
            <span class="ms-tag">
                ${t.label}
                <button type="button" onclick="${t.action}" title="Retirer">✕</button>
            </span>`).join('');
    },

    _showLoader() {
        document.getElementById('loader').style.display      = 'block';
        document.getElementById('panels-grid').innerHTML     = '';
        document.getElementById('empty-state').style.display = 'none';
        document.getElementById('pagination-bar') && (document.getElementById('pagination-bar').style.display = 'none');
    },

    _showEmpty(title, sub) {
        document.getElementById('loader').style.display      = 'none';
        document.getElementById('panels-grid').innerHTML     = '';
        const empty = document.getElementById('empty-state');
        empty.style.display = 'block';
        document.getElementById('empty-title').textContent = title;
        document.getElementById('empty-sub').textContent   = sub;
    },

    _showDateError(msg) {
        const el = document.getElementById('date-error-msg');
        if (el) { el.textContent = '⚠️ ' + msg; el.style.display = 'block'; }
    },
    _hideDateError() {
        const el = document.getElementById('date-error-msg');
        if (el) el.style.display = 'none';
    },
};

// ══ MULTISELECT — optimisé 1000+ items avec recherche intégrée ══
function buildMultiselect(wrapper) {
    const filter      = wrapper.dataset.filter;
    const placeholder = wrapper.dataset.placeholder || 'Sélectionner';

    const DATA_MAP = {
        commune_ids: INIT.communes,
        zone_ids:    INIT.zones,
        format_ids:  INIT.formats,
        agency_ids:  INIT.agencies,
    };
    const data = DATA_MAP[filter] || [];

    // Badge key : agency_ids → agencies (exception), sinon strip _ids + s
    const badgeKey = filter === 'agency_ids' ? 'agencies' : filter.replace('_ids', 's');

    wrapper.style.position = 'relative';

    // ── Trigger ─────────────────────────────────────────────────
    const trigger = document.createElement('button');
    trigger.type      = 'button';
    trigger.className = 'ms-trigger';
    trigger.innerHTML = `<span class="ms-tags"><span class="ms-placeholder">${placeholder}</span></span>
                         <span style="font-size:10px;color:var(--text3);flex-shrink:0">▾</span>`;

    // ── Dropdown ─────────────────────────────────────────────────
    const dropdown = document.createElement('div');
    dropdown.className     = 'ms-dropdown';
    dropdown.style.display = 'none';

    // Barre de recherche intégrée (utile pour 1000+ communes)
    const searchWrapper = document.createElement('div');
    searchWrapper.className = 'ms-search';
    const searchInput = document.createElement('input');
    searchInput.type        = 'text';
    searchInput.placeholder = 'Rechercher…';
    searchInput.autocomplete = 'off';
    searchInput.className = 'ms-search-input';
    searchWrapper.appendChild(searchInput);
    dropdown.appendChild(searchWrapper);

    // Liste scrollable
    const list = document.createElement('div');
    list.className = 'ms-list';
    dropdown.appendChild(list);

    // Footer : tout sélectionner / tout désélectionner
    const footer = document.createElement('div');
    footer.className = 'ms-footer';
    footer.innerHTML = `<span id="ms-count-${filter}">0 sélectionné(s)</span>
                        <div style="display:flex;gap:8px">
                            <button type="button" onclick="window.__ms_selectAll('${filter}')">Tout</button>
                            <button type="button" onclick="window.__ms_clearAll('${filter}')">Aucun</button>
                        </div>`;
    dropdown.appendChild(footer);

    wrapper.appendChild(trigger);
    wrapper.appendChild(dropdown);

    // ── Rendu de la liste (avec filtre texte) ─────────────────────
    function renderList(filterText) {
        const q       = (filterText || '').toLowerCase().trim();
        const filtered = q ? data.filter(item => item.name.toLowerCase().includes(q)) : data;

        if (filtered.length === 0) {
            list.innerHTML = '<div class="ms-option" style="color:var(--text3);font-style:italic;justify-content:center">Aucun résultat</div>';
            return;
        }

        // Fragment DOM pour perf avec 1000+ items
        const frag = document.createDocumentFragment();
        const selectedIds = STATE.filters[filter];

        filtered.forEach(item => {
            const isSel = selectedIds.includes(item.id);
            const label = document.createElement('label');
            label.className = 'ms-option' + (isSel ? ' selected' : '');
            label.dataset.id = item.id;

            const dimStr = (filter === 'format_ids' && item.width && item.height)
                ? ` <span style="color:var(--text3);font-size:10px">(${Math.round(item.width)}×${Math.round(item.height)}m)</span>`
                : '';

            label.innerHTML = `<input type="checkbox" ${isSel ? 'checked' : ''} style="accent-color:var(--accent)"> ${item.name}${dimStr}`;
            label.querySelector('input').addEventListener('change', () => {
                APP.toggleMultiFilter(filter, item.id);
                // Mise à jour visuelle locale sans re-render complet
                label.classList.toggle('selected', STATE.filters[filter].includes(item.id));
                updateFooterCount();
            });
            frag.appendChild(label);
        });

        list.innerHTML = '';
        list.appendChild(frag);
    }

    function updateFooterCount() {
        const n   = STATE.filters[filter].length;
        const el  = document.getElementById(`ms-count-${filter}`);
        if (el) el.textContent = n + ' sélectionné(s)';
        const badge = document.getElementById('badge-' + badgeKey);
        if (badge) { badge.textContent = n; badge.style.display = n > 0 ? 'inline' : 'none'; }
    }

    // Recherche dans le dropdown — debounced 150ms
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => renderList(searchInput.value), 150);
    });

    // Helpers globaux pour "Tout / Aucun"
    window.__ms_selectAll = window.__ms_selectAll || {};
    window.__ms_clearAll  = window.__ms_clearAll  || {};
    window[`__ms_selectAll`] = function(key) {
        const src = DATA_MAP[key] || [];
        const q   = key === filter ? searchInput.value.toLowerCase().trim() : '';
        const visible = q ? src.filter(i => i.name.toLowerCase().includes(q)) : src;
        visible.forEach(item => {
            if (!STATE.filters[key].includes(item.id))
                STATE.filters[key].push(item.id);
        });
        APP._syncMultiselectTrigger(key);
        APP._triggerFetch();
        APP._updateUI();
        renderList(searchInput.value);
        updateFooterCount();
    };
    window[`__ms_clearAll`] = function(key) {
        STATE.filters[key] = [];
        STATE.currentPage  = 1;
        APP._syncMultiselectTrigger(key);
        APP._triggerFetch();
        APP._updateUI();
        renderList(searchInput.value);
        updateFooterCount();
    };

    // ── Ouvrir / fermer ───────────────────────────────────────────
    trigger.addEventListener('click', e => {
        e.stopPropagation();
        const isOpen = dropdown.style.display !== 'none';
        closeAllDropdowns();
        if (!isOpen) {
            dropdown.style.display = 'block';
            trigger.classList.add('open');
            renderList(''); // rendu initial complet
            searchInput.value = '';
            searchInput.focus();
            updateFooterCount();
        }
    });

    // ── updateTrigger (appelé par APP._syncMultiselectTrigger) ─────
    const ms = {
        updateTrigger() {
            const selected = STATE.filters[filter];
            const tagsDiv  = trigger.querySelector('.ms-tags');

            if (selected.length === 0) {
                tagsDiv.innerHTML = `<span class="ms-placeholder">${placeholder}</span>`;
            } else {
                tagsDiv.innerHTML = selected.map(id => {
                    const item = data.find(x => x.id === id);
                    return item ? `
                        <span class="ms-tag">
                            ${item.name}
                            <button type="button"
                                    onclick="event.preventDefault();event.stopPropagation();
                                             APP.removeMultiFilter('${filter}',${id})"
                                    title="Retirer">✕</button>
                        </span>` : '';
                }).join('');
            }

            // Sync checkboxes visibles dans la liste
            list.querySelectorAll('label.ms-option').forEach(label => {
                const id  = parseInt(label.dataset.id);
                const chk = label.querySelector('input');
                const sel = selected.includes(id);
                if (chk) chk.checked = sel;
                label.classList.toggle('selected', sel);
            });

            updateFooterCount();
        },
    };

    wrapper.__ms = ms;
    return ms;
}

function closeAllDropdowns() {
    document.querySelectorAll('.ms-dropdown').forEach(d => d.style.display = 'none');
    document.querySelectorAll('.ms-trigger').forEach(t => t.classList.remove('open'));
}

document.addEventListener('click', closeAllDropdowns);

// ══ INIT ═════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    // Construire tous les multiselects
    document.querySelectorAll('.multiselect-wrapper').forEach(el => buildMultiselect(el));

    // Peupler select dimensions
    const dimSelect = document.getElementById('f-dimensions');
    if (dimSelect) {
        INIT.dimensions.forEach(dim => {
            const opt = document.createElement('option');
            opt.value = dim; opt.textContent = dim;
            dimSelect.appendChild(opt);
        });
    }

    // Escape = fermer
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            APP.closeConfirmModal();
            document.getElementById('modal-fiche').style.display = 'none';
            closeAllDropdowns();
        }
    });

    // Premier chargement
    APP._fetchPanels();
    APP._updateSelectionBar();
});

})(); // IIFE
</script>
@endpush
</x-admin-layout>
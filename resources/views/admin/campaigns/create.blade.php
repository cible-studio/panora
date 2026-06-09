<x-admin-layout title="Nouvelle campagne">

<x-slot:topbarLeft>
    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        Retour
    </a>
</x-slot:topbarLeft>

{{-- Style local : design refonte (sections cards distinctes + sidebar aperçu live).
     Logique préservée : Alpine + Select2 + modal client AJAX intacts. --}}
<style>
    .cc-grid {
        display: grid;
        grid-template-columns: minmax(0,1fr) 320px;
        gap: 22px;
        max-width: 1180px;
        margin: 0 auto;
        align-items: flex-start;
    }
    @media (max-width: 1024px) {
        .cc-grid { grid-template-columns: 1fr; }
        .cc-aside { position: static !important; }
    }
    .cc-section {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px 22px;
        margin-bottom: 14px;
    }
    .cc-section-head {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
    }
    .cc-section-icon {
        width: 36px; height: 36px;
        border-radius: 10px;
        background: rgba(232,160,32,.10);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .cc-section-title {
        font-size: 14px;
        font-weight: 800;
        color: var(--text);
        margin: 0;
    }
    .cc-section-sub {
        font-size: 11.5px;
        color: var(--text3);
        margin-top: 1px;
    }
    .cc-field-label {
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text3);
        letter-spacing: .5px;
        text-transform: uppercase;
        display: block;
        margin-bottom: 6px;
    }
    .cc-field-label .req { color: #ef4444; margin-left: 2px; }
    .cc-input,
    .cc-select,
    .cc-textarea {
        width: 100%;
        background: var(--surface2);
        border: 1px solid var(--border2);
        border-radius: 9px;
        padding: 10px 14px;
        color: var(--text);
        font-size: 13px;
        outline: none;
        box-sizing: border-box;
        transition: border-color .15s, box-shadow .15s;
    }
    .cc-input:focus,
    .cc-select:focus,
    .cc-textarea:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(232,160,32,.14);
    }
    .cc-input.is-error,
    .cc-select.is-error { border-color: #ef4444; }
    .cc-hint {
        font-size: 10.5px;
        color: var(--text3);
        margin-top: 5px;
        line-height: 1.4;
    }
    .cc-error {
        font-size: 11px;
        color: #ef4444;
        margin-top: 4px;
    }
    .cc-aside {
        position: sticky;
        top: 12px;
    }
    .cc-preview {
        background: linear-gradient(155deg, rgba(232,160,32,.05) 0%, rgba(58,168,53,.05) 100%);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 18px 18px 14px;
    }
    .cc-preview-title {
        font-size: 10.5px;
        font-weight: 800;
        color: var(--text3);
        text-transform: uppercase;
        letter-spacing: .5px;
        display: flex; align-items: center; gap: 6px;
        margin-bottom: 12px;
    }
    .cc-preview-name {
        font-size: 16px;
        font-weight: 800;
        color: var(--text);
        line-height: 1.3;
        margin-bottom: 14px;
        word-break: break-word;
    }
    .cc-preview-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        border-top: 1px dashed var(--border);
        font-size: 12px;
    }
    .cc-preview-row:first-of-type { border-top: 0; }
    .cc-preview-key { color: var(--text3); }
    .cc-preview-val { color: var(--text); font-weight: 700; text-align: right; }
    .cc-duration-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(58,168,53,.10);
        border: 1px solid rgba(58,168,53,.25);
        color: #15803d;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 800;
    }
    .cc-duration-warn {
        background: rgba(239,68,68,.08);
        border-color: rgba(239,68,68,.25);
        color: #b91c1c;
    }
    .cc-row-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    @media (max-width: 540px) { .cc-row-2 { grid-template-columns: 1fr; } }

    .cc-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        padding: 18px 22px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        margin-top: 14px;
    }
    .cc-btn-create {
        background: linear-gradient(135deg, var(--accent), var(--accent-dark));
        color: #fff;
        border: 0;
        padding: 11px 22px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 800;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 16px rgba(232,160,32,.25);
        transition: transform .15s, box-shadow .15s;
    }
    .cc-btn-create:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(232,160,32,.32);
    }
    .cc-btn-cancel {
        background: var(--surface2);
        border: 1px solid var(--border2);
        color: var(--text2);
        padding: 11px 18px;
        border-radius: 10px;
        font-size: 13px;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }
</style>

<div style="font-size:12px;color:var(--text3);margin:0 auto 14px;max-width:1180px">
    <a href="{{ route('admin.campaigns.index') }}" style="color:var(--text3);text-decoration:none">Campagnes</a>
    <span style="margin:0 6px">›</span>
    <span style="color:var(--text)">Nouvelle campagne</span>
</div>

<form method="POST" action="{{ route('admin.campaigns.store') }}"
      x-data="campaignCreate()" x-init="init()">
    @csrf

    <div class="cc-grid">

        {{-- ═════════════ COLONNE FORMULAIRE ═════════════ --}}
        <div>

            {{-- Bandeau pré-rempli depuis réservation --}}
            @if($preselectedReservation)
            <div style="margin-bottom:14px;padding:14px 18px;background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.3);border-radius:12px;display:flex;gap:12px;align-items:flex-start">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(34,197,94,.18);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">✓</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:800;color:#15803d;margin-bottom:3px">Pré-rempli depuis {{ $preselectedReservation->reference }}</div>
                    <div style="font-size:11.5px;color:var(--text2);line-height:1.5">
                        <strong>{{ $preselectedReservation->client?->name }}</strong> ·
                        {{ $preselectedReservation->panels->count() + $preselectedReservation->externalPanels->count() }} panneau(x) ·
                        {{ number_format($preselectedReservation->total_amount, 0, ',', ' ') }} FCFA ·
                        {{ $preselectedReservation->start_date->format('d/m/Y') }} → {{ $preselectedReservation->end_date->format('d/m/Y') }}
                    </div>
                </div>
            </div>
            @endif

            {{-- ── Section : Identité ── --}}
            <div class="cc-section">
                <div class="cc-section-head">
                    <div class="cc-section-icon">📢</div>
                    <div>
                        <h3 class="cc-section-title">Identité de la campagne</h3>
                        <p class="cc-section-sub">Nom affiché partout dans Panora — sois explicite (client + période).</p>
                    </div>
                </div>

                <label class="cc-field-label">Nom de la campagne <span class="req">*</span></label>
                <input type="text" name="name" x-model="campaignName"
                       value="{{ old('name', $preselectedReservation ? 'Campagne ' . $preselectedReservation->client?->name . ' — ' . now()->format('Y') : '') }}"
                       placeholder="Ex : Campagne NOËL 2026 — CLIENT X"
                       class="cc-input {{ $errors->has('name') ? 'is-error' : '' }}">
                @error('name')<p class="cc-error">{{ $message }}</p>@enderror
                <p class="cc-hint">💡 Format recommandé : « Campagne [thème] [année] — [client] »</p>

                <div class="cc-row-2" style="margin-top:14px">
                    {{-- Client --}}
                    <div>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <label class="cc-field-label" style="margin-bottom:0">Client <span class="req">*</span></label>
                            <button type="button" onclick="openQuickClient()"
                                    style="font-size:10.5px;color:var(--accent);background:none;border:0;cursor:pointer;font-weight:700;padding:0">
                                + Créer un client
                            </button>
                        </div>
                        <select name="client_id" id="campaign-client-select" x-model="selectedClientId"
                                class="cc-select {{ $errors->has('client_id') ? 'is-error' : '' }}">
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}"
                                    {{ old('client_id', $preselectedReservation?->client_id) == $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('client_id')<p class="cc-error">{{ $message }}</p>@enderror
                    </div>

                    {{-- Réservation --}}
                    <div>
                        <label class="cc-field-label">Réservation confirmée <span style="color:var(--text3);font-weight:400;text-transform:none;letter-spacing:0">(optionnel)</span></label>
                        <select name="reservation_id"
                                @change="onReservationChange($event)"
                                class="cc-select">
                            <option value="">Aucune réservation liée</option>
                            @foreach($reservations as $r)
                                @php $rTotal = $r->panels->count() + $r->externalPanels->count(); @endphp
                                <option value="{{ $r->id }}"
                                        data-start="{{ $r->start_date->format('Y-m-d') }}"
                                        data-end="{{ $r->end_date->format('Y-m-d') }}"
                                        data-client="{{ $r->client_id }}"
                                        data-amount="{{ $r->total_amount }}"
                                        data-panels="{{ $rTotal }}"
                                    {{ old('reservation_id', $preselectedReservation?->id) == $r->id ? 'selected' : '' }}>
                                    {{ $r->reference }} — {{ $r->client?->name }} ({{ $rTotal }} pan. · {{ number_format($r->total_amount, 0, ',', ' ') }} FCFA)
                                </option>
                            @endforeach
                        </select>
                        @error('reservation_id')<p class="cc-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </div>

            {{-- ── Section : Période ── --}}
            <div class="cc-section">
                <div class="cc-section-head">
                    <div class="cc-section-icon" style="background:rgba(58,168,53,.10)">📅</div>
                    <div>
                        <h3 class="cc-section-title">Période d'affichage</h3>
                        <p class="cc-section-sub">Dates de début et fin — la durée détermine la facturation.</p>
                    </div>
                </div>

                <div class="cc-row-2">
                    <div>
                        <label class="cc-field-label">Date début <span class="req">*</span></label>
                        <input type="date" name="start_date" x-ref="startDate" x-model="startDate"
                               value="{{ old('start_date', $preselectedReservation?->start_date->format('Y-m-d')) }}"
                               class="cc-input {{ $errors->has('start_date') ? 'is-error' : '' }}">
                        @error('start_date')<p class="cc-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="cc-field-label">Date fin <span class="req">*</span></label>
                        <input type="date" name="end_date" x-ref="endDate" x-model="endDate"
                               value="{{ old('end_date', $preselectedReservation?->end_date->format('Y-m-d')) }}"
                               class="cc-input {{ $errors->has('end_date') ? 'is-error' : '' }}">
                        @error('end_date')<p class="cc-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                {{-- Badge durée calculé en live --}}
                <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap" x-show="startDate && endDate" x-cloak>
                    <span class="cc-duration-badge" :class="{ 'cc-duration-warn': durationDays < 0 }">
                        <span x-show="durationDays >= 0">⏱ <span x-text="durationDays"></span> jours
                            (~<span x-text="durationMonths"></span> mois facturables)</span>
                        <span x-show="durationDays < 0">⚠ Date fin avant date début</span>
                    </span>
                </div>
            </div>

            {{-- ── Section : Assignation ── --}}
            <div class="cc-section">
                <div class="cc-section-head">
                    <div class="cc-section-icon" style="background:rgba(59,130,246,.10)">👤</div>
                    <div>
                        <h3 class="cc-section-title">Assignation commerciale</h3>
                        <p class="cc-section-sub">Qui suit cette campagne ? Reçoit les notifications.</p>
                    </div>
                </div>

                <label class="cc-field-label">Commercial assigné</label>
                <select name="commercial_user_id" class="cc-select {{ $errors->has('commercial_user_id') ? 'is-error' : '' }}">
                    <option value="">— Par défaut : moi-même —</option>
                    @foreach($commerciaux ?? [] as $c)
                        <option value="{{ $c->id }}" {{ old('commercial_user_id', auth()->id()) == $c->id ? 'selected' : '' }}>
                            {{ $c->name }} ({{ strtoupper((string) ($c->role?->value ?? $c->role ?? '')) }})
                        </option>
                    @endforeach
                </select>
                <p class="cc-hint">📧 Reçoit les emails + notifications in-app : poses, piges, fin imminente, etc.</p>
                @error('commercial_user_id')<p class="cc-error">{{ $message }}</p>@enderror
            </div>

            {{-- ── Section : Notes ── --}}
            <div class="cc-section">
                <div class="cc-section-head">
                    <div class="cc-section-icon" style="background:rgba(107,114,128,.10)">📝</div>
                    <div>
                        <h3 class="cc-section-title">Notes internes</h3>
                        <p class="cc-section-sub">Visible uniquement par l'équipe — pas envoyé au client.</p>
                    </div>
                </div>

                <textarea name="notes" rows="3" class="cc-textarea"
                          placeholder="Brief créa, contraintes spécifiques, contacts agence, etc.">{{ old('notes') }}</textarea>
            </div>

            {{-- ── Actions ── --}}
            <div class="cc-actions">
                <a href="{{ route('admin.campaigns.index') }}" class="cc-btn-cancel">Annuler</a>
                <button type="submit" class="cc-btn-create">
                    <span>✨</span> Créer la campagne
                </button>
            </div>
        </div>

        {{-- ═════════════ SIDEBAR APERÇU LIVE ═════════════ --}}
        <aside class="cc-aside">
            <div class="cc-preview">
                <div class="cc-preview-title">🔍 Aperçu en direct</div>
                <div class="cc-preview-name" x-text="campaignName || '(Nouvelle campagne)'"></div>

                <div class="cc-preview-row">
                    <span class="cc-preview-key">Client</span>
                    <span class="cc-preview-val" x-text="selectedClientLabel() || '—'"></span>
                </div>
                <div class="cc-preview-row">
                    <span class="cc-preview-key">Période</span>
                    <span class="cc-preview-val">
                        <span x-show="startDate && endDate && durationDays >= 0">
                            <span x-text="formatDate(startDate)"></span> → <span x-text="formatDate(endDate)"></span>
                        </span>
                        <span x-show="!startDate || !endDate">—</span>
                        <span x-show="startDate && endDate && durationDays < 0" style="color:#b91c1c">⚠ invalide</span>
                    </span>
                </div>
                <div class="cc-preview-row">
                    <span class="cc-preview-key">Durée</span>
                    <span class="cc-preview-val">
                        <span x-show="durationDays > 0"><span x-text="durationDays"></span> jours</span>
                        <span x-show="durationDays <= 0" style="color:var(--text3);font-weight:400">—</span>
                    </span>
                </div>
                <div class="cc-preview-row">
                    <span class="cc-preview-key">Mois facturables</span>
                    <span class="cc-preview-val">
                        <span x-show="durationMonths > 0"><span x-text="durationMonths"></span></span>
                        <span x-show="durationMonths <= 0" style="color:var(--text3);font-weight:400">—</span>
                    </span>
                </div>

                @if($preselectedReservation)
                    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--border)">
                        <div style="font-size:10px;font-weight:800;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px">Hérité de la résa</div>
                        <div class="cc-preview-row">
                            <span class="cc-preview-key">Panneaux</span>
                            <span class="cc-preview-val">{{ $preselectedReservation->panels->count() + $preselectedReservation->externalPanels->count() }}</span>
                        </div>
                        <div class="cc-preview-row">
                            <span class="cc-preview-key">Montant résa</span>
                            <span class="cc-preview-val" style="color:var(--accent)">{{ number_format($preselectedReservation->total_amount, 0, ',', ' ') }} F</span>
                        </div>
                    </div>
                @endif

                <div style="margin-top:14px;padding:9px 12px;background:rgba(232,160,32,.08);border-radius:8px;font-size:10.5px;color:var(--text2);line-height:1.5">
                    💡 Après création, tu pourras attacher des panneaux supplémentaires et générer la facture FNE depuis la fiche campagne.
                </div>
            </div>
        </aside>
    </div>
</form>

{{-- Select2 + jQuery (CDN, isolé à cette page) --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<style>
    /* Harmonisation Select2 avec le thème refonte */
    .select2-container--default .select2-selection--single {
        background: var(--surface2) !important;
        border: 1px solid var(--border2) !important;
        border-radius: 9px !important;
        height: 41px !important;
        padding: 4px 6px !important;
        color: var(--text) !important;
        transition: border-color .15s, box-shadow .15s;
    }
    .select2-container--focus .select2-selection--single,
    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: var(--accent) !important;
        box-shadow: 0 0 0 3px rgba(232,160,32,.14) !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: var(--text) !important;
        line-height: 31px !important;
        padding-left: 8px !important;
        font-size: 13px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 39px !important;
        right: 6px !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__placeholder {
        color: var(--text3) !important;
    }
    .select2-dropdown {
        background: var(--surface) !important;
        border: 1px solid var(--border) !important;
        border-radius: 10px !important;
        overflow: hidden !important;
        box-shadow: 0 10px 28px rgba(0,0,0,.16) !important;
    }
    .select2-container--default .select2-search--dropdown .select2-search__field {
        background: var(--surface2) !important;
        border: 1px solid var(--border2) !important;
        border-radius: 8px !important;
        color: var(--text) !important;
        padding: 9px 12px !important;
        font-size: 13px !important;
    }
    .select2-container--default .select2-results__option {
        padding: 10px 14px !important;
        font-size: 13px !important;
        color: var(--text) !important;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background: rgba(232,160,32,.14) !important;
        color: var(--text) !important;
    }
    .select2-container--default .select2-results__option[aria-selected=true] {
        background: var(--surface2) !important;
        color: var(--text) !important;
    }
    .select2-container--open .select2-dropdown { z-index: 10500 !important; }
    .select2-container { width: 100% !important; z-index: 1; }

    /* Modale "Créer client" doit dominer Select2 */
    #quick-client-modal { z-index: 99999 !important; isolation: isolate; }
    #quick-client-modal > div { position: relative; z-index: 99999; }

    [x-cloak] { display: none !important; }
</style>

@push('scripts')
<script>
function campaignCreate() {
    return {
        selectedClientId: '{{ old('client_id', $preselectedReservation?->client_id ?? '') }}',
        campaignName: '{{ old('name', $preselectedReservation ? 'Campagne ' . addslashes($preselectedReservation->client?->name ?? '') . ' — ' . now()->format('Y') : '') }}',
        startDate: '{{ old('start_date', $preselectedReservation?->start_date->format('Y-m-d') ?? '') }}',
        endDate:   '{{ old('end_date',   $preselectedReservation?->end_date->format('Y-m-d')   ?? '') }}',

        get durationDays() {
            if (!this.startDate || !this.endDate) return 0;
            const s = new Date(this.startDate);
            const e = new Date(this.endDate);
            if (isNaN(s) || isNaN(e)) return 0;
            return Math.round((e - s) / (1000 * 60 * 60 * 24)) + 1;
        },
        get durationMonths() {
            // Mois facturables : règle CIBLE ~30 jours/mois, arrondi à
            // 1 décimale (cohérence avec Campaign::billableMonths côté serveur)
            const d = this.durationDays;
            if (d <= 0) return 0;
            return Math.max(1, Math.round(d / 30 * 10) / 10);
        },

        formatDate(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            if (isNaN(d)) return '';
            return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        },
        selectedClientLabel() {
            const sel = document.getElementById('campaign-client-select');
            if (!sel || !sel.value) return '';
            return sel.options[sel.selectedIndex]?.text || '';
        },

        init() {
            // Si réservation pré-sélectionnée au chargement, déclencher le remplissage
            const select = this.$el.querySelector('select[name="reservation_id"]');
            if (select && select.value) {
                this.fillFromOption(select.options[select.selectedIndex]);
            }

            // Initialise Select2 sur client + réservation (recherche)
            const self = this;
            $(function() {
                const $client = $('#campaign-client-select');
                $client.select2({
                    placeholder: '🔍 Rechercher un client…',
                    allowClear: false,
                    width: '100%',
                    language: {
                        noResults: () => 'Aucun client trouvé',
                        searching: () => 'Recherche…',
                    },
                });
                $client.on('change', function() { self.selectedClientId = this.value; });

                $('select[name="reservation_id"]').select2({
                    placeholder: 'Aucune réservation liée',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: () => 'Aucune réservation',
                        searching: () => 'Recherche…',
                    },
                }).on('change', function() {
                    const opt = this.options[this.selectedIndex];
                    self.fillFromOption(opt);
                });
            });
        },

        onReservationChange(event) {
            const option = event.target.options[event.target.selectedIndex];
            this.fillFromOption(option);
        },

        fillFromOption(option) {
            if (!option || !option.value) return;

            const start  = option.dataset.start;
            const end    = option.dataset.end;
            const client = option.dataset.client;

            if (start) { this.$refs.startDate.value = start; this.startDate = start; }
            if (end)   { this.$refs.endDate.value   = end;   this.endDate   = end; }
            if (client) {
                this.selectedClientId = client;
                $('#campaign-client-select').val(client).trigger('change.select2');
            }
        }
    }
}
</script>
@endpush

{{-- ══ MODALE CRÉATION CLIENT RAPIDE ══
     Endpoint : admin.clients.quick-store (déjà utilisé sur disponibilités). --}}
<div id="quick-client-modal"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:9999;align-items:center;justify-content:center;padding:16px"
     onclick="if(event.target===this) closeQuickClient()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:16px;width:100%;max-width:520px;padding:24px 26px;position:relative;box-shadow:0 24px 64px rgba(0,0,0,.4)"
         onclick="event.stopPropagation()">
        <button type="button" onclick="closeQuickClient()"
                style="position:absolute;top:14px;right:16px;background:none;border:0;color:var(--text3);cursor:pointer;font-size:22px;line-height:1">✕</button>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
            <div style="width:42px;height:42px;border-radius:12px;background:rgba(232,160,32,.14);display:flex;align-items:center;justify-content:center;font-size:20px">👤</div>
            <div>
                <h3 style="font-size:16px;font-weight:800;color:var(--text);margin:0">Créer un nouveau client</h3>
                <p style="font-size:11.5px;color:var(--text3);margin:2px 0 0">Sera créé et sélectionné automatiquement.</p>
            </div>
        </div>

        <div id="quick-client-errors"
             style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:9px;padding:10px 12px;font-size:12px;color:#ef4444;margin-bottom:14px"></div>

        <div style="display:flex;flex-direction:column;gap:12px">
            <div>
                <label class="cc-field-label">Nom <span class="req">*</span></label>
                <input id="qc-name" type="text" required class="cc-input">
            </div>
            <div class="cc-row-2">
                <div>
                    <label class="cc-field-label">NCC</label>
                    <input id="qc-ncc" type="text" class="cc-input">
                </div>
                <div>
                    <label class="cc-field-label">Téléphone</label>
                    <input id="qc-phone" type="text" class="cc-input">
                </div>
            </div>
            <div>
                <label class="cc-field-label">Email</label>
                <input id="qc-email" type="email" class="cc-input">
            </div>
            <div>
                <label class="cc-field-label">Interlocuteur</label>
                <input id="qc-contact" type="text" class="cc-input">
            </div>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
            <button type="button" onclick="closeQuickClient()" class="cc-btn-cancel">Annuler</button>
            <button id="qc-submit" type="button" onclick="submitQuickClient()" class="cc-btn-create" style="padding:10px 20px">
                <span id="qc-submit-icon">+</span>
                <span id="qc-submit-txt">Créer le client</span>
            </button>
        </div>
    </div>
</div>

<script>
function openQuickClient() {
    document.getElementById('quick-client-errors').style.display = 'none';
    ['qc-name','qc-ncc','qc-phone','qc-email','qc-contact'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('qc-submit').disabled = false;
    document.getElementById('qc-submit-txt').textContent = 'Créer le client';
    document.getElementById('qc-submit-icon').textContent = '+';
    document.getElementById('quick-client-modal').style.display = 'flex';
    setTimeout(() => document.getElementById('qc-name')?.focus(), 100);
}
function closeQuickClient() {
    document.getElementById('quick-client-modal').style.display = 'none';
}
async function submitQuickClient() {
    const errBox = document.getElementById('quick-client-errors');
    const name = document.getElementById('qc-name').value.trim();
    if (!name) {
        errBox.textContent = '⚠️ Le nom est obligatoire.';
        errBox.style.display = 'block';
        document.getElementById('qc-name').focus();
        return;
    }
    const btn = document.getElementById('qc-submit');
    btn.disabled = true;
    document.getElementById('qc-submit-icon').textContent = '⟳';
    document.getElementById('qc-submit-txt').textContent = 'Création…';
    try {
        const r = await fetch('{{ route('admin.clients.quick-store') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({
                name,
                ncc: document.getElementById('qc-ncc').value.trim() || null,
                phone: document.getElementById('qc-phone').value.trim() || null,
                email: document.getElementById('qc-email').value.trim() || null,
                contact_name: document.getElementById('qc-contact').value.trim() || null,
            }),
        });
        const data = await r.json();
        if (!r.ok || data.success === false) {
            errBox.textContent = '⚠️ ' + (data.message || 'Erreur lors de la création.');
            errBox.style.display = 'block';
            btn.disabled = false;
            document.getElementById('qc-submit-icon').textContent = '+';
            document.getElementById('qc-submit-txt').textContent = 'Créer le client';
            return;
        }
        const created = data.client || data;
        if (!created || !created.id || !created.name) {
            errBox.textContent = '⚠️ Réponse inattendue du serveur — client créé mais non sélectionnable.';
            errBox.style.display = 'block';
            btn.disabled = false;
            document.getElementById('qc-submit-icon').textContent = '+';
            document.getElementById('qc-submit-txt').textContent = 'Créer le client';
            return;
        }
        const sel = document.getElementById('campaign-client-select');
        const opt = new Option(created.name, created.id, true, true);
        sel.add(opt);
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        if (window.jQuery) jQuery(sel).trigger('change.select2');
        closeQuickClient();
    } catch (e) {
        errBox.textContent = '⚠️ Erreur réseau : ' + e.message;
        errBox.style.display = 'block';
        btn.disabled = false;
        document.getElementById('qc-submit-icon').textContent = '+';
        document.getElementById('qc-submit-txt').textContent = 'Créer le client';
    }
}
</script>

</x-admin-layout>

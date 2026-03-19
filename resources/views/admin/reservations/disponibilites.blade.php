<x-admin-layout title="Disponibilités & Panneaux">

<x-slot:topbarActions>
    <button class="btn btn-primary"
            id="btn-confirm-top"
            style="display:none;"
            onclick="document.getElementById('btn-confirm-bottom').click()">
        ✅ Confirmer sélection
    </button>
</x-slot:topbarActions>

<div x-data="disponibilites()" x-init="init()">

{{-- ══════════════════════════════════════════════════════════════
     FILTRES COMBINABLES
══════════════════════════════════════════════════════════════ --}}
<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="padding:14px 16px;">
        <form id="filter-form"
              method="GET"
              action="{{ route('admin.reservations.disponibilites') }}"
              onsubmit="return validateDatesDisponibilites()">

            {{-- Ligne 1 : Localisation + Période --}}
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;
                        margin-bottom:10px;">

                <div class="filter-group">
                    <label class="filter-label">Commune</label>
                    <select name="commune_id" class="filter-select">
                        <option value="">Toutes</option>
                        @foreach($communes as $c)
                        <option value="{{ $c->id }}"
                                {{ request('commune_id') == $c->id ? 'selected' : '' }}>
                            {{ $c->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Zone</label>
                    <select name="zone_id" class="filter-select">
                        <option value="">Toutes</option>
                        @foreach($zones as $z)
                        <option value="{{ $z->id }}"
                                {{ request('zone_id') == $z->id ? 'selected' : '' }}>
                            {{ $z->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Format</label>
                    <select name="format_id" class="filter-select">
                        <option value="">Tous</option>
                        @foreach($formats as $f)
                        <option value="{{ $f->id }}"
                                {{ request('format_id') == $f->id ? 'selected' : '' }}>
                            {{ $f->name }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Dimensions</label>
                    <select name="dimensions" class="filter-select">
                        <option value="">Toutes</option>
                        @foreach($dimensions as $dim)
                        <option value="{{ $dim }}"
                                {{ request('dimensions') === $dim ? 'selected' : '' }}>
                            {{ $dim }}
                        </option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Éclairage</label>
                    <select name="is_lit" class="filter-select">
                        <option value="">Tous</option>
                        <option value="1" {{ request('is_lit') === '1' ? 'selected' : '' }}>
                            💡 Éclairé
                        </option>
                        <option value="0" {{ request('is_lit') === '0' ? 'selected' : '' }}>
                            Non éclairé
                        </option>
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Statut</label>
                    <select name="statut" class="filter-select">
                        <option value="tous" {{ request('statut','tous') === 'tous' ? 'selected' : '' }}>
                            Tous
                        </option>
                        <option value="libre"       {{ request('statut') === 'libre'       ? 'selected' : '' }}>Disponible</option>
                        <option value="occupe"      {{ request('statut') === 'occupe'       ? 'selected' : '' }}>Occupé</option>
                        <option value="confirme"    {{ request('statut') === 'confirme'     ? 'selected' : '' }}>Confirmé</option>
                        <option value="option"      {{ request('statut') === 'option'       ? 'selected' : '' }}>Option</option>
                        <option value="maintenance" {{ request('statut') === 'maintenance'  ? 'selected' : '' }}>Maintenance</option>
                    </select>
                </div>
            </div>

            {{-- Ligne 2 : Dates disponibilité --}}
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;
                        padding-top:10px;border-top:1px solid var(--border);">
                <div style="font-size:12px;color:var(--text2);
                            display:flex;align-items:center;gap:6px;padding-bottom:2px;">
                    📅 <strong>Période de disponibilité :</strong>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Du</label>
                    <input type="date"
                           name="dispo_du"
                           id="dispo-du"
                           value="{{ request('dispo_du') }}"
                           class="filter-input"
                           onchange="onStartDateChange(this.value)"/>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Au</label>
                    <input type="date"
                           name="dispo_au"
                           id="dispo-au"
                           value="{{ request('dispo_au') }}"
                           min="{{ request('dispo_du') ? \Carbon\Carbon::parse(request('dispo_du'))->addDay()->format('Y-m-d') : '' }}"
                           class="filter-input"
                           onchange="onEndDateChange(this.value)"/>
                </div>

                {{-- Erreur dates serveur --}}
                @if(isset($dateError) && $dateError)
                <div style="padding:6px 12px;background:rgba(239,68,68,0.08);
                            border:1px solid rgba(239,68,68,0.3);border-radius:8px;
                            font-size:12px;color:var(--red);display:flex;
                            align-items:center;gap:6px;">
                    ⚠️ {{ $dateError }}
                </div>
                @endif

                <div id="date-error-inline" style="display:none;padding:6px 12px;
                     background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);
                     border-radius:8px;font-size:12px;color:var(--red);
                     align-items:center;gap:6px;">
                </div>

                <div style="display:flex;gap:6px;margin-left:auto;">
                    <button type="submit" class="btn btn-primary btn-sm">🔍 Filtrer</button>
                    @if(request()->hasAny(['commune_id','zone_id','format_id','dispo_du','dispo_au','statut','dimensions','is_lit']))
                    <a href="{{ route('admin.reservations.disponibilites') }}"
                       class="btn btn-ghost btn-sm">↺ Reset</a>
                    @endif
                </div>
            </div>

        </form>

        {{-- Stats résultats --}}
        @if($allPanels->isNotEmpty())
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);
                    display:flex;gap:16px;flex-wrap:wrap;">
            @php
                $libre       = $allPanels->filter(fn($p) => !$occupiedIds->contains($p->id) && $p->status->value === 'libre')->count();
                $occupes     = $occupiedIds->count();
                $maintenance = $allPanels->filter(fn($p) => $p->status->value === 'maintenance')->count();
            @endphp
            <span style="font-size:12px;color:var(--text2);">
                <strong style="color:var(--text);">{{ $allPanels->count() }}</strong> panneau(x) affiché(s)
            </span>
            @if($startDate && $endDate && !($dateError ?? null))
            <span style="font-size:12px;color:#22c55e;">
                ✅ <strong>{{ $libre }}</strong> disponible(s)
            </span>
            <span style="font-size:12px;color:var(--red);">
                🔒 <strong>{{ $occupes }}</strong> occupé(s) sur la période
            </span>
            @endif
            @if($maintenance > 0)
            <span style="font-size:12px;color:var(--text3);">
                🔧 <strong>{{ $maintenance }}</strong> en maintenance
            </span>
            @endif
        </div>
        @endif
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════
     GRILLE PANNEAUX — tailles uniformes
══════════════════════════════════════════════════════════════ --}}
@if($allPanels->isEmpty())
    <div style="text-align:center;padding:80px;color:var(--text3);">
        <div style="font-size:48px;margin-bottom:12px;">🪧</div>
        <div style="font-size:15px;font-weight:600;margin-bottom:6px;">
            Aucun panneau trouvé
        </div>
        <div style="font-size:13px;">Modifiez vos filtres pour afficher des panneaux.</div>
    </div>
@else
<div style="display:grid;
            grid-template-columns:repeat(auto-fill,minmax(260px,1fr));
            gap:14px;margin-bottom:120px;">

    @foreach($allPanels as $panel)
    @php
        $isOccupied = $occupiedIds->contains($panel->id);
        $isOption   = isset($optionIds) && $optionIds->contains($panel->id);

        if ($isOccupied && $startDate && $endDate && !($dateError ?? null)) {
            $displayStatus = 'occupe';
        } elseif ($isOption && $startDate && $endDate && !($dateError ?? null)) {
            $displayStatus = 'option_periode';
        } else {
            $displayStatus = $panel->status->value;
        }

        $statusConfig = [
            'libre'          => ['label'=>'Disponible',      'color'=>'#22c55e','bg'=>'rgba(34,197,94,0.08)',  'border'=>'rgba(34,197,94,0.3)',  'selectable'=>true],
            'occupe'         => ['label'=>'Occupé',           'color'=>'#ef4444','bg'=>'rgba(239,68,68,0.08)', 'border'=>'rgba(239,68,68,0.3)',  'selectable'=>false],
            'option_periode' => ['label'=>'En option',        'color'=>'#e8a020','bg'=>'rgba(232,160,32,0.08)','border'=>'rgba(232,160,32,0.3)', 'selectable'=>false],
            'confirme'       => ['label'=>'Confirmé',         'color'=>'#a855f7','bg'=>'rgba(168,85,247,0.08)','border'=>'rgba(168,85,247,0.3)', 'selectable'=>false],
            'option'         => ['label'=>'Option',           'color'=>'#e8a020','bg'=>'rgba(232,160,32,0.08)','border'=>'rgba(232,160,32,0.3)', 'selectable'=>false],
            'maintenance'    => ['label'=>'Maintenance',      'color'=>'#6b7280','bg'=>'rgba(107,114,128,0.08)','border'=>'rgba(107,114,128,0.3)','selectable'=>false],
        ];
        $sc = $statusConfig[$displayStatus] ?? $statusConfig['libre'];

        $cardColors = ['#3b82f6','#a855f7','#f97316','#14b8a6','#e8a020','#22c55e'];
        $cardBg     = $cardColors[abs(crc32($panel->reference) % count($cardColors))];

        $isSelectable = $sc['selectable'];

        // ── Date de libération du panneau ─────────────────────────────
        $releaseDate     = null;
        $releaseDaysLeft = null;
        if (isset($releaseDates) && ($occupiedIds->contains($panel->id) || $optionIds->contains($panel->id))) {
            $rdRaw = $releaseDates->get($panel->id);
            if ($rdRaw) {
                $releaseDate     = \Carbon\Carbon::parse($rdRaw);
                $releaseDaysLeft = (int) now()->startOfDay()->diffInDays($releaseDate->startOfDay(), false);
            }
        }
    @endphp

    {{-- ── Carte panneau — hauteur fixe uniforme ── --}}
    <div style="background:var(--surface);
                border:1px solid {{ $sc['border'] }};
                border-radius:14px;overflow:hidden;position:relative;
                transition:transform 0.15s,box-shadow 0.15s;
                display:flex;flex-direction:column;
                min-height:320px;"
         :style="selectedIds.includes({{ $panel->id }})
             ? 'border-color:var(--accent);box-shadow:0 0 0 2px rgba(232,160,32,0.3);'
             : ''"
         @mouseenter="$el.style.transform='translateY(-2px)';$el.style.boxShadow='0 8px 24px rgba(0,0,0,0.2)'"
         @mouseleave="$el.style.transform='translateY(0)';$el.style.boxShadow=selectedIds.includes({{ $panel->id }})?'0 0 0 2px rgba(232,160,32,0.3)':''">

        {{-- Badge statut --}}
        <div style="position:absolute;top:10px;right:10px;z-index:2;
                    padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;
                    background:{{ $sc['bg'] }};color:{{ $sc['color'] }};
                    border:1px solid {{ $sc['border'] }};">
            {{ $sc['label'] }}
        </div>

        {{-- Checkbox sélection --}}
        @if($isSelectable)
        <div style="position:absolute;top:10px;left:10px;z-index:2;">
            <input type="checkbox"
                   :checked="selectedIds.includes({{ $panel->id }})"
                   @change="togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }})"
                   @click.stop
                   style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer;">
        </div>
        @endif

        {{-- Visuel carte — hauteur fixe --}}
        <div style="background:{{ $sc['bg'] }};padding:28px 20px 14px;
                    display:flex;justify-content:center;align-items:center;
                    height:100px;flex-shrink:0;cursor:{{ $isSelectable ? 'pointer' : 'default' }};"
             @if($isSelectable)
             @click="togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }})"
             @endif>
            <div style="background:{{ $cardBg }};border-radius:8px;
                        padding:8px 20px;font-family:monospace;font-size:14px;
                        font-weight:700;color:#fff;letter-spacing:1px;
                        box-shadow:0 4px 12px rgba(0,0,0,0.3);">
                {{ $panel->reference }}
            </div>
        </div>

        {{-- Infos panneau — flex grow pour hauteur uniforme --}}
        <div style="padding:12px 14px;flex:1;display:flex;flex-direction:column;">

            {{-- Nom --}}
            <div style="font-size:10px;color:var(--text3);margin-bottom:2px;">
                {{ $panel->commune?->name ?? '—' }}
            </div>
            <div style="font-weight:700;font-size:13px;color:var(--text);
                        margin-bottom:8px;overflow:hidden;text-overflow:ellipsis;
                        white-space:nowrap;" title="{{ $panel->name }}">
                {{ $panel->name }}
            </div>

            {{-- Tags --}}
            <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px;">
                @if($panel->category)
                <span style="background:var(--surface3);color:var(--text2);font-size:10px;
                             padding:2px 6px;border-radius:4px;font-weight:600;">
                    {{ strtoupper(substr($panel->category->name ?? 'STD', 0, 3)) }}
                </span>
                @endif
                @if($panel->format)
                <span style="background:var(--surface3);color:var(--text2);font-size:10px;
                             padding:2px 6px;border-radius:4px;">
                    {{ $panel->format->width ?? '?' }}×{{ $panel->format->height ?? '?' }}m
                </span>
                @endif
                @if($panel->is_lit)
                <span style="background:rgba(232,160,32,0.12);color:var(--accent);font-size:10px;
                             padding:2px 6px;border-radius:4px;">💡</span>
                @endif
                @if($panel->daily_traffic)
                <span style="background:var(--surface3);color:var(--text2);font-size:10px;
                             padding:2px 6px;border-radius:4px;">
                    👁 {{ number_format($panel->daily_traffic) }}k/j
                </span>
                @endif
            </div>

            {{-- Emplacement --}}
            @if($panel->zone_description)
            <div style="font-size:11px;color:var(--text2);margin-bottom:6px;
                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                 title="{{ $panel->zone_description }}">
                📍 {{ $panel->zone_description }}
            </div>
            @endif

            {{-- Prix -- poussé en bas --}}
            <div style="margin-top:auto;padding-top:8px;
                        border-top:1px solid var(--border);">
                <div style="font-size:17px;font-weight:800;color:var(--accent);margin-bottom:6px;">
                    @if($panel->monthly_rate)
                        {{ number_format($panel->monthly_rate / 1000, 0, ',', ' ') }}K
                        <span style="font-size:11px;font-weight:400;color:var(--text3);">FCFA/mois</span>
                    @else
                        <span style="font-size:13px;color:var(--text3);">Tarif non défini</span>
                    @endif
                </div>

                {{-- Date de libération ── --}}
                @if($releaseDate && !$isSelectable)
                <div style="margin-top:4px;margin-bottom:6px;padding:5px 8px;
                            background:{{ $displayStatus === 'option_periode' || $displayStatus === 'option'
                                ? 'rgba(232,160,32,0.08)'
                                : 'rgba(239,68,68,0.06)' }};
                            border-radius:6px;font-size:10px;border:1px solid
                            {{ $displayStatus === 'option_periode' || $displayStatus === 'option'
                                ? 'rgba(232,160,32,0.2)'
                                : 'rgba(239,68,68,0.15)' }};">
                    @if($releaseDaysLeft === 0)
                        <span style="color:var(--green);">⚡ Libre aujourd'hui</span>
                    @elseif($releaseDaysLeft === 1)
                        <span style="color:var(--text2);">📅 Libre demain</span>
                    @elseif($releaseDaysLeft > 0)
                        <span style="color:var(--text2);">
                            📅 Libre le {{ $releaseDate->format('d/m/Y') }}
                            <span style="color:var(--text3);">(dans {{ $releaseDaysLeft }}j)</span>
                        </span>
                    @else
                        <span style="color:var(--green);">✅ Date passée</span>
                    @endif
                </div>
                @endif

                {{-- Actions --}}
                <div style="display:flex;gap:6px;">
                    <button type="button"
                            class="btn btn-ghost btn-sm"
                            style="flex:1;font-size:11px;"
                            @click.stop="openFiche({{ $panel->toJson() }})">
                        📋 Fiche
                    </button>
                    @if($isSelectable)
                    <button type="button"
                            class="btn btn-sm"
                            :style="selectedIds.includes({{ $panel->id }})
                                ? 'background:var(--accent);color:#000;flex:1.2;font-size:11px;'
                                : 'background:var(--surface3);color:var(--text);flex:1.2;font-size:11px;border:1px solid var(--border2);border-radius:7px;'"
                            @click.stop="togglePanel({{ $panel->id }}, {{ $panel->monthly_rate ?? 0 }})">
                        <span x-text="selectedIds.includes({{ $panel->id }}) ? '✓ Sélectionné' : '+ Sélectionner'"></span>
                    </button>
                    @else
                    <div style="flex:1.2;padding:6px 10px;background:var(--surface3);
                                border-radius:7px;font-size:11px;color:var(--text3);
                                text-align:center;">
                        @if($displayStatus === 'occupe')🔒 Occupé
                        @elseif($displayStatus === 'maintenance')🔧 Maintenance
                        @elseif($displayStatus === 'option' || $displayStatus === 'option_periode')⏳ Option
                        @elseif($displayStatus === 'confirme')✅ Confirmé
                        @else {{ $sc['label'] }}
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endforeach

</div>
@endif

{{-- ── Barre sélection bottom ───────────────────────────────────── --}}
<div x-show="selectedIds.length > 0"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     style="position:fixed;bottom:0;left:235px;right:0;
            background:var(--surface);border-top:2px solid var(--accent);
            padding:14px 24px;display:flex;align-items:center;
            justify-content:space-between;z-index:100;
            box-shadow:0 -8px 32px rgba(0,0,0,0.4);">

    <div style="display:flex;align-items:center;gap:16px;">
        <div>
            <span x-text="selectedIds.length"
                  style="font-size:22px;font-weight:800;color:var(--accent);"></span>
            <span style="font-size:13px;color:var(--text2);margin-left:4px;">
                panneau(x) sélectionné(s)
            </span>
        </div>
        <div style="font-size:16px;font-weight:700;color:var(--text);">
            <span x-text="formatTotal()"></span>
            <span style="font-size:12px;font-weight:400;color:var(--text3);margin-left:3px;">
                FCFA/mois
            </span>
        </div>
    </div>

    <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-ghost btn-sm"
                @click="selectedIds = []; selectedRates = {}">
            ✕ Tout désélectionner
        </button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="border-color:var(--red);color:var(--red);">
            📄 PDF images
        </button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="border-color:var(--blue);color:var(--blue);">
            📋 PDF liste
        </button>
        <button type="button" id="btn-confirm-bottom"
                class="btn btn-primary"
                @click="openConfirmModal()">
            ✅ Confirmer la sélection
        </button>
    </div>
</div>

</div>{{-- fin x-data --}}

{{-- ══════════════════════════════════════════════════════════════
     MODAL — CONFIRMER SÉLECTION
══════════════════════════════════════════════════════════════ --}}
<div id="modal-confirm-selection" class="modal-overlay" style="display:none;"
     onclick="if(event.target===this) closeConfirmModal()">
    <div class="modal" style="max-width:580px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">✅ Confirmer la campagne</span>
            <button class="modal-close" onclick="closeConfirmModal()">✕</button>
        </div>
        <form method="POST"
              action="{{ route('admin.reservations.confirmer-selection') }}"
              x-data="{ type: 'option' }">
            @csrf
            <div id="hidden-panel-inputs"></div>

            <div class="modal-body">
                <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);
                            border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:12px;
                            color:var(--green);">
                    🛡️ Anti double-booking : conflits vérifiés automatiquement avant confirmation.
                </div>

                <div style="display:flex;gap:8px;margin-bottom:16px;">
                    <label style="flex:1;cursor:pointer;padding:12px;border-radius:10px;
                                  display:flex;align-items:center;gap:10px;"
                           :style="type==='option'
                               ? 'border:1px solid var(--orange);background:rgba(249,115,22,0.08);'
                               : 'border:1px solid var(--border2);background:var(--surface2);'">
                        <input type="radio" name="type" value="option" x-model="type"
                               style="accent-color:var(--orange);">
                        <div>
                            <div style="font-size:13px;font-weight:600;">⏳ Mise sous option</div>
                            <div style="font-size:11px;color:var(--text2);">Blocage temporaire</div>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer;padding:12px;border-radius:10px;
                                  display:flex;align-items:center;gap:10px;"
                           :style="type==='ferme'
                               ? 'border:1px solid var(--green);background:rgba(34,197,94,0.08);'
                               : 'border:1px solid var(--border2);background:var(--surface2);'">
                        <input type="radio" name="type" value="ferme" x-model="type"
                               style="accent-color:var(--green);">
                        <div>
                            <div style="font-size:13px;font-weight:600;">🔒 Réservation ferme</div>
                            <div style="font-size:11px;color:var(--text2);">Confirmation définitive</div>
                        </div>
                    </label>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id" required
                                style="background:var(--surface2);border:1px solid var(--border2);
                                       border-radius:8px;padding:9px 12px;color:var(--text);
                                       font-size:13px;outline:none;width:100%;">
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg">
                        <label>Nom de la campagne</label>
                        <input type="text" name="campaign_name"
                               placeholder="ex: Lancement produit Ramadan"/>
                    </div>
                </div>

                <div class="form-2col">
                    <div class="mfg">
                        <label>Date début *</label>
                        <input type="date" name="start_date"
                               value="{{ request('dispo_du') }}"
                               id="modal-start-date"
                               required/>
                    </div>
                    <div class="mfg">
                        <label>Date fin *</label>
                        <input type="date" name="end_date"
                               value="{{ request('dispo_au') }}"
                               id="modal-end-date"
                               required/>
                    </div>
                </div>

                <div class="mfg">
                    <label>Note interne</label>
                    <textarea name="notes"
                              placeholder="Ex: Confirmation reçue par email le…"
                              style="min-height:70px;"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost"
                        onclick="closeConfirmModal()">Annuler</button>
                <button type="submit" class="btn btn-primary">
                    ✅ Confirmer et bloquer les panneaux
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── Modal fiche technique ──────────────────────────────────── --}}
<div x-data="{ open: false, panel: {} }"
     x-on:open-fiche-panel.window="panel = $event.detail; open = true"
     x-show="open" class="modal-overlay"
     @click.self="open = false" style="display:none;">
    <div class="modal" style="max-width:680px;max-height:85vh;overflow-y:auto;"
         @click.stop>
        <div class="modal-header">
            <span class="modal-title">📋 Fiche technique</span>
            <button class="modal-close" @click="open = false">✕</button>
        </div>
        <div class="modal-body">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px;">
                @foreach([
                    ['RÉFÉRENCE','reference'],['NOM','name'],['COMMUNE','commune'],
                    ['GPS LAT','latitude'],['GPS LNG','longitude'],['ZONE','zone'],
                    ['FORMAT','format'],['DIMENSIONS','dimensions'],['ÉCLAIRÉ','is_lit'],
                    ['TRAFIC','daily_traffic'],['TARIF/MOIS','monthly_rate'],['STATUT','status'],
                ] as [$label,$key])
                <div>
                    <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                                letter-spacing:.6px;margin-bottom:4px;">{{ $label }}</div>
                    <div style="font-size:13px;font-weight:500;color:var(--text);">
                        <template x-if="'{{ $key }}' === 'is_lit'">
                            <span x-text="panel.is_lit ? '💡 Oui' : 'Non'"></span>
                        </template>
                        <template x-if="'{{ $key }}' === 'monthly_rate'">
                            <span x-text="panel.monthly_rate
                                ? Number(panel.monthly_rate).toLocaleString('fr-FR') + ' FCFA'
                                : '—'"></span>
                        </template>
                        <template x-if="'{{ $key }}' !== 'is_lit' && '{{ $key }}' !== 'monthly_rate'">
                            <span x-text="panel['{{ $key }}'] || '—'"></span>
                        </template>
                    </div>
                </div>
                @endforeach
            </div>
            <div>
                <div style="font-size:10px;color:var(--text3);text-transform:uppercase;
                            letter-spacing:.6px;margin-bottom:6px;">DESCRIPTION DE ZONE</div>
                <div style="background:var(--surface3);border-radius:8px;padding:12px;
                            font-size:13px;color:var(--text2);"
                     x-text="panel.zone_description || 'Aucune description.'"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" @click="open = false">Fermer</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── Alpine component disponibilités ────────────────────────────────
function disponibilites() {
    return {
        selectedIds:   [],
        selectedRates: {},

        init() {
            // Afficher bouton topbar si sélection non vide
            this.$watch('selectedIds', ids => {
                document.getElementById('btn-confirm-top').style.display =
                    ids.length > 0 ? 'block' : 'none';
            });
        },

        togglePanel(id, rate) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) {
                this.selectedIds.push(id);
                this.selectedRates[id] = parseFloat(rate) || 0;
            } else {
                this.selectedIds.splice(idx, 1);
                delete this.selectedRates[id];
            }
        },

        formatTotal() {
            const total = Object.values(this.selectedRates).reduce((s, r) => s + r, 0);
            return Math.round(total).toLocaleString('fr-FR');
        },

        openFiche(panelJson) {
            this.$dispatch('open-fiche-panel', panelJson);
        },

        openConfirmModal() {
            // Injecter les inputs hidden
            const container = document.getElementById('hidden-panel-inputs');
            if (container) {
                container.innerHTML = '';
                this.selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type  = 'hidden';
                    input.name  = 'panel_ids[]';
                    input.value = id;
                    container.appendChild(input);
                });
            }
            document.getElementById('modal-confirm-selection').style.display = 'flex';
        },
    };
}

function closeConfirmModal() {
    document.getElementById('modal-confirm-selection').style.display = 'none';
}

// ── Validation dates ────────────────────────────────────────────────
function onStartDateChange(startVal) {
    const endInput = document.getElementById('dispo-au');
    if (startVal && endInput) {
        const minDate = new Date(startVal);
        minDate.setDate(minDate.getDate() + 1);
        endInput.min = minDate.toISOString().split('T')[0];
        if (endInput.value && endInput.value <= startVal) {
            endInput.value = '';
            showDateErr('La date de fin a été réinitialisée (antérieure au début).');
        } else {
            clearDateErr();
        }
    }
}

function onEndDateChange(endVal) {
    const startVal = document.getElementById('dispo-du')?.value;
    if (startVal && endVal && endVal <= startVal) {
        showDateErr('La date de fin doit être après la date de début.');
        document.getElementById('dispo-au').value = '';
    } else {
        clearDateErr();
    }
}

function validateDatesDisponibilites() {
    const start = document.getElementById('dispo-du')?.value;
    const end   = document.getElementById('dispo-au')?.value;
    if (!start && !end) return true;
    if (start && !end) { showDateErr('Veuillez renseigner la date de fin.'); return false; }
    if (!start && end) { showDateErr('Veuillez renseigner la date de début.'); return false; }
    if (end <= start)  { showDateErr('La date de fin doit être après la date de début.'); return false; }
    clearDateErr();
    return true;
}

function showDateErr(msg) {
    const el = document.getElementById('date-error-inline');
    if (el) { el.innerHTML = '⚠️ ' + msg; el.style.display = 'flex'; }
}
function clearDateErr() {
    const el = document.getElementById('date-error-inline');
    if (el) el.style.display = 'none';
}

document.addEventListener('DOMContentLoaded', () => {
    const startVal = document.getElementById('dispo-du')?.value;
    if (startVal) {
        const minDate = new Date(startVal);
        minDate.setDate(minDate.getDate() + 1);
        const endInput = document.getElementById('dispo-au');
        if (endInput) endInput.min = minDate.toISOString().split('T')[0];
    }
});

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') closeConfirmModal();
});
</script>
@endpush

</x-admin-layout>
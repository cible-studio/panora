<x-admin-layout title="Disponibilités & Panneaux">

<x-slot:topbarActions>
    <div style="display:flex;gap:8px;align-items:center;">
        {{-- Toggle vue --}}
        <div style="display:flex;background:var(--surface2);border:1px solid var(--border2);
                    border-radius:8px;overflow:hidden;" id="view-toggle">
            <button onclick="setView('grid')" id="btn-grid"
                    style="padding:6px 10px;border:none;cursor:pointer;font-size:14px;
                           background:var(--accent);color:#000;transition:all .15s;"
                    title="Vue grille">⊞</button>
            <button onclick="setView('list')" id="btn-list"
                    style="padding:6px 10px;border:none;cursor:pointer;font-size:14px;
                           background:transparent;color:var(--text2);transition:all .15s;"
                    title="Vue liste">☰</button>
        </div>
        <div id="confirm-btn-wrapper" style="display:none;">
            <button class="btn btn-primary" onclick="openConfirmModal()">
                ✅ Confirmer sélection (<span id="top-count">0</span>)
            </button>
        </div>
    </div>
</x-slot:topbarActions>

<div x-data="disponibilites()" x-init="init()">

{{-- ══ ALERTE PANNEAUX OCCUPÉS SUR PÉRIODE ══ --}}
@if($startDate && $endDate && !$dateError && ($occupiedIds->count() + $optionIds->count()) > 0)
@php
    $alertPanels = $allPanels->filter(fn($p) =>
        $occupiedIds->contains($p->id) || $optionIds->contains($p->id)
    )->take(8);
    $alertCount = $occupiedIds->count() + $optionIds->count();
@endphp
<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);
            border-radius:12px;padding:14px 16px;margin-bottom:14px;">
    <div style="font-size:13px;font-weight:700;color:var(--red);margin-bottom:10px;">
        ⚡ ⚠️ {{ $alertCount }} panneau(x) occupé(s) sur votre période
    </div>
    @foreach($alertPanels as $ap)
    @php
        $isConf = $occupiedIds->contains($ap->id) && !$optionIds->contains($ap->id);
        $isOpt  = $optionIds->contains($ap->id);
    @endphp
    <div style="display:flex;align-items:center;gap:8px;font-size:12px;
                color:var(--text2);padding:4px 0;border-bottom:1px solid rgba(239,68,68,0.1);">
        <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;
                     background:{{ $isOpt ? '#e8a020' : '#ef4444' }};
                     display:inline-block;"></span>
        <strong style="font-family:monospace;color:{{ $isOpt ? 'var(--accent)' : 'var(--red)' }};">
            {{ $ap->reference }}
        </strong>
        <span>— {{ $ap->name }}</span>
        <span style="color:var(--text3);">({{ $ap->commune?->name ?? '—' }})</span>
        <span style="margin-left:auto;font-size:11px;padding:2px 7px;border-radius:4px;
                     background:{{ $isOpt ? 'rgba(232,160,32,0.15)' : 'rgba(239,68,68,0.15)' }};
                     color:{{ $isOpt ? 'var(--accent)' : 'var(--red)' }};">
            {{ $isOpt ? 'En option' : 'Confirmé' }}
        </span>
    </div>
    @endforeach
</div>
@endif

{{-- ══ STATS ══ --}}
@if($startDate && $endDate && !$dateError)
@php
    $libres       = $allPanels->filter(fn($p) =>
        !$occupiedIds->contains($p->id) && !$optionIds->contains($p->id)
        && $p->status->value !== 'maintenance'
    )->count();
    $occupes      = $occupiedIds->count();
    $options      = $optionIds->count();
    $maintenances = $allPanels->filter(fn($p) => $p->status->value === 'maintenance')->count();
@endphp
<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap;">
    @foreach([
        [$libres,       'DISPONIBLES',  '#22c55e', 'rgba(34,197,94,0.08)',  'rgba(34,197,94,0.3)'],
        [$occupes,      'CONFIRMÉS',    '#a855f7', 'rgba(168,85,247,0.08)','rgba(168,85,247,0.3)'],
        [$options,      'EN OPTION',    '#e8a020', 'rgba(232,160,32,0.08)','rgba(232,160,32,0.3)'],
        [$maintenances, 'MAINTENANCE',  '#6b7280', 'rgba(107,114,128,0.08)','rgba(107,114,128,0.3)'],
        [$allPanels->count(),'TOTAL',  'var(--text)','var(--surface)','var(--border)'],
    ] as [$val, $lbl, $col, $bg, $bd])
    <div style="background:{{ $bg }};border:1px solid {{ $bd }};
                border-radius:8px;padding:7px 14px;
                display:flex;align-items:center;gap:8px;">
        <span style="font-size:18px;font-weight:800;color:{{ $col }};">{{ $val }}</span>
        <span style="font-size:10px;color:var(--text2);font-weight:700;">{{ $lbl }}</span>
    </div>
    @endforeach
</div>
@endif

{{-- ══ FILTRES — UNE LIGNE COMPACTE COMME LA MAQUETTE ══ --}}
<form method="GET" action="{{ route('admin.reservations.disponibilites') }}"
      id="filter-form"
      style="background:var(--surface);border:1px solid var(--border);
             border-radius:12px;padding:12px 16px;margin-bottom:14px;">

    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">

        {{-- Commune --}}
        <div>
            <label class="filter-label">COMMUNE</label>
            <select name="commune_ids[]" class="filter-select"
                    onchange="this.form.submit()"
                    style="min-width:130px;">
                <option value="">Toutes</option>
                @foreach($communes as $c)
                <option value="{{ $c->id }}"
                    {{ in_array($c->id, (array)request()->get('commune_ids', [])) ? 'selected' : '' }}>
                    {{ $c->name }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Zone --}}
        <div>
            <label class="filter-label">ZONE</label>
            <select name="zone_ids[]" class="filter-select"
                    onchange="this.form.submit()"
                    style="min-width:130px;">
                <option value="">Toutes</option>
                @foreach($zones as $z)
                <option value="{{ $z->id }}"
                    {{ in_array($z->id, (array)request()->get('zone_ids', [])) ? 'selected' : '' }}>
                    {{ $z->name }}
                </option>
                @endforeach
            </select>
        </div>

        {{-- Format --}}
        <div>
            <label class="filter-label">FORMAT</label>
            <select name="format_ids[]" class="filter-select"
                    onchange="this.form.submit()"
                    style="min-width:110px;">
                <option value="">Tous</option>
                @foreach($formats as $f)
                <option value="{{ $f->id }}"
                    {{ in_array($f->id, (array)request()->get('format_ids', [])) ? 'selected' : '' }}>
                    {{ $f->name }}
                    @if($f->width && $f->height) ({{ (int)$f->width }}×{{ (int)$f->height }}m) @endif
                </option>
                @endforeach
            </select>
        </div>

        {{-- Éclairage --}}
        <div>
            <label class="filter-label">ÉCLAIRAGE</label>
            <select name="is_lit" class="filter-select"
                    onchange="this.form.submit()"
                    style="min-width:120px;">
                <option value="" {{ request('is_lit') === '' || request('is_lit') === null ? 'selected' : '' }}>
                    Tous
                </option>
                <option value="1" {{ request('is_lit') === '1' ? 'selected' : '' }}>💡 Éclairé</option>
                <option value="0" {{ request('is_lit') === '0' ? 'selected' : '' }}>Non éclairé</option>
            </select>
        </div>

        {{-- Dispo du --}}
        <div>
            <label class="filter-label">DISPO DU</label>
            <input type="date" name="dispo_du" id="input-dispo-du"
                   value="{{ $startDate }}"
                   class="filter-input"
                   onchange="syncMinDate()"/>
        </div>

        {{-- Au --}}
        <div>
            <label class="filter-label">AU</label>
            <input type="date" name="dispo_au" id="input-dispo-au"
                   value="{{ $endDate }}"
                   class="filter-input"/>
        </div>

        {{-- Statut --}}
        <div>
            <label class="filter-label">STATUT</label>
            <select name="statut" class="filter-select"
                    onchange="this.form.submit()"
                    style="min-width:130px;">
                <option value="tous"
                    {{ ($statut ?? 'tous') === 'tous' ? 'selected' : '' }}>Tous</option>
                <option value="libre"
                    {{ ($statut ?? '') === 'libre' ? 'selected' : '' }}>✅ Disponible</option>
                <option value="occupe"
                    {{ ($statut ?? '') === 'occupe' ? 'selected' : '' }}>🔒 Occupé (période)</option>
                <option value="option"
                    {{ ($statut ?? '') === 'option' ? 'selected' : '' }}>⏳ En option</option>
                <option value="confirme"
                    {{ ($statut ?? '') === 'confirme' ? 'selected' : '' }}>✅ Confirmé</option>
                <option value="maintenance"
                    {{ ($statut ?? '') === 'maintenance' ? 'selected' : '' }}>🔧 Maintenance</option>
            </select>
        </div>

        {{-- Boutons --}}
        <div style="display:flex;gap:6px;align-items:flex-end;">
            <button type="submit" class="btn btn-primary btn-sm"
                    style="height:35px;padding:0 14px;">
                🔍 Filtrer
            </button>
            @if(request()->hasAny(['commune_ids','zone_ids','format_ids','dispo_du','dispo_au','statut','is_lit']))
            <a href="{{ route('admin.reservations.disponibilites') }}"
               class="btn btn-ghost btn-sm"
               style="height:35px;padding:0 12px;">✕</a>
            @endif
        </div>
    </div>

    {{-- Ligne exports --}}
    <div style="display:flex;gap:6px;margin-top:10px;padding-top:10px;
                border-top:1px solid var(--border);align-items:center;flex-wrap:wrap;">
        <span style="font-size:11px;color:var(--text3);margin-right:4px;">Export :</span>
        <button type="button" class="btn btn-ghost btn-sm"
                style="height:28px;font-size:11px;padding:0 10px;">
            📊 Excel
        </button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="height:28px;font-size:11px;padding:0 10px;">
            📋 CSV
        </button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="height:28px;font-size:11px;padding:0 10px;
                       color:var(--red);border-color:rgba(239,68,68,.4);">
            📄 PDF avec images
        </button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="height:28px;font-size:11px;padding:0 10px;
                       color:var(--red);border-color:rgba(239,68,68,.4);">
            📄 PDF liste
        </button>
        <div style="margin-left:auto;font-size:11px;color:var(--text3);">
            {{ $allPanels->count() }} panneau(x) affiché(s)
        </div>
    </div>

    @if(isset($dateError) && $dateError)
    <div style="margin-top:10px;padding:9px 12px;background:rgba(239,68,68,0.08);
                border:1px solid rgba(239,68,68,0.3);border-radius:8px;
                font-size:12px;color:var(--red);display:flex;gap:6px;align-items:center;">
        <span>⚠️</span><span>{{ $dateError }}</span>
    </div>
    @endif
</form>

{{-- ══ CONTENU — VUE GRILLE / LISTE ══ --}}
<div id="view-grid">
@if($allPanels->isEmpty())
<div style="text-align:center;padding:80px;color:var(--text3);">
    <div style="font-size:40px;margin-bottom:12px;">🔍</div>
    <div style="font-size:15px;font-weight:600;margin-bottom:6px;">Aucun panneau trouvé</div>
    <div style="font-size:13px;">Ajustez les filtres pour voir des résultats.</div>
</div>
@else
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));
            gap:12px;margin-bottom:100px;">

    @foreach($allPanels as $panel)
    @php
        $panelStatus        = $panel->status->value;
        $isOccupiedOnPeriod = $startDate && $endDate && $occupiedIds->contains($panel->id);
        $isOptionOnPeriod   = $startDate && $endDate && $optionIds->contains($panel->id);

        if ($panelStatus === 'maintenance') {
            $displayStatus = 'maintenance';
        } elseif ($isOccupiedOnPeriod && !$isOptionOnPeriod) {
            $displayStatus = 'occupe';
        } elseif ($isOptionOnPeriod) {
            $displayStatus = 'option';
        } else {
            $displayStatus = $panelStatus;
        }

        $isSelectable = in_array($displayStatus, ['libre']) && $panelStatus !== 'maintenance';

        $sc = match($displayStatus) {
            'libre'       => ['label'=>'Disponible', 'c'=>'#22c55e','bg'=>'rgba(34,197,94,0.08)', 'bd'=>'rgba(34,197,94,0.3)'],
            'occupe'      => ['label'=>'Occupé',     'c'=>'#ef4444','bg'=>'rgba(239,68,68,0.08)', 'bd'=>'rgba(239,68,68,0.3)'],
            'option'      => ['label'=>'En option',  'c'=>'#e8a020','bg'=>'rgba(232,160,32,0.08)','bd'=>'rgba(232,160,32,0.3)'],
            'confirme'    => ['label'=>'Confirmé',   'c'=>'#a855f7','bg'=>'rgba(168,85,247,0.08)','bd'=>'rgba(168,85,247,0.3)'],
            'maintenance' => ['label'=>'Maintenance','c'=>'#6b7280','bg'=>'rgba(107,114,128,0.08)','bd'=>'rgba(107,114,128,0.3)'],
            default       => ['label'=>$panelStatus, 'c'=>'#6b7280','bg'=>'rgba(107,114,128,0.08)','bd'=>'rgba(107,114,128,0.3)'],
        };

        $colors    = ['#3b82f6','#a855f7','#f97316','#14b8a6','#e8a020','#22c55e'];
        $cardColor = $colors[abs(crc32($panel->reference)) % count($colors)];

        $ficheData = [
            'id'               => $panel->id,
            'reference'        => $panel->reference,
            'name'             => $panel->name,
            'commune'          => $panel->commune?->name ?? '—',
            'zone'             => $panel->zone?->name ?? '—',
            'format'           => $panel->format?->name ?? '—',
            'format_width'     => $panel->format?->width,
            'format_height'    => $panel->format?->height,
            'category'         => $panel->category?->name ?? '—',
            'is_lit'           => (bool) $panel->is_lit,
            'monthly_rate'     => (float) ($panel->monthly_rate ?? 0),
            'daily_traffic'    => (int) ($panel->daily_traffic ?? 0),
            'zone_description' => $panel->zone_description ?? '',
            'status'           => $sc['label'],
            'status_color'     => $sc['c'],
        ];
    @endphp

    {{-- CARTE --}}
    <div class="panel-card {{ $isSelectable ? 'selectable' : 'not-selectable' }}"
         data-id="{{ $panel->id }}"
         data-rate="{{ (float)($panel->monthly_rate ?? 0) }}"
         data-selectable="{{ $isSelectable ? '1' : '0' }}"
         style="background:var(--surface);border:2px solid {{ $sc['bd'] }};
                border-radius:14px;overflow:hidden;position:relative;
                display:flex;flex-direction:column;
                transition:transform 0.15s,box-shadow 0.15s,border-color 0.15s;
                cursor:{{ $isSelectable ? 'pointer' : 'default' }};"
         @if($isSelectable)
         onmouseenter="cardHover(this, true)"
         onmouseleave="cardHover(this, false)"
         onclick="cardClick({{ $panel->id }}, {{ (float)($panel->monthly_rate ?? 0) }})"
         @endif>

        {{-- Badge statut --}}
        <div style="position:absolute;top:8px;right:8px;z-index:3;
                    padding:2px 8px;border-radius:20px;font-size:10px;font-weight:700;
                    background:{{ $sc['bg'] }};color:{{ $sc['c'] }};
                    border:1px solid {{ $sc['bd'] }};">
            {{ $sc['label'] }}
        </div>

        {{-- Checkbox --}}
        @if($isSelectable)
        <div style="position:absolute;top:8px;left:8px;z-index:3;">
            <input type="checkbox"
                   id="chk-{{ $panel->id }}"
                   :checked="selectedIds.includes({{ $panel->id }})"
                   @change.stop="togglePanel({{ $panel->id }}, {{ (float)($panel->monthly_rate ?? 0) }})"
                   onclick="event.stopPropagation()"
                   style="accent-color:var(--accent);width:15px;height:15px;cursor:pointer;">
        </div>
        @endif

        {{-- Visuel panneau façon billboard --}}
        <div style="background:{{ $sc['bg'] }};height:96px;flex-shrink:0;
                    display:flex;flex-direction:column;justify-content:center;align-items:center;
                    position:relative;overflow:hidden;">
            {{-- Panneau stylisé --}}
            <div style="background:{{ $cardColor }};border-radius:6px;
                        padding:6px 16px;font-family:monospace;font-size:13px;
                        font-weight:800;color:#fff;letter-spacing:1px;
                        box-shadow:0 3px 10px rgba(0,0,0,0.35);
                        position:relative;z-index:1;">
                {{ $panel->reference }}
            </div>
            {{-- Poteau --}}
            <div style="width:3px;height:18px;background:rgba(255,255,255,0.3);
                        margin-top:0;border-radius:0 0 2px 2px;position:relative;z-index:1;">
            </div>
        </div>

        {{-- Contenu --}}
        <div style="padding:10px 12px;flex:1;display:flex;flex-direction:column;">

            <div style="font-size:10px;color:var(--text3);margin-bottom:1px;
                        display:flex;justify-content:space-between;">
                <span>{{ $panel->commune?->name ?? '—' }}</span>
                @if($panel->zone)
                <span style="color:var(--text3);">{{ $panel->zone->name }}</span>
                @endif
            </div>

            <div style="font-weight:700;font-size:13px;color:var(--text);
                        height:34px;overflow:hidden;line-height:1.3;
                        display:-webkit-box;-webkit-line-clamp:2;
                        -webkit-box-orient:vertical;margin-bottom:6px;">
                {{ $panel->name }}
            </div>

            {{-- Tags --}}
            <div style="display:flex;gap:3px;flex-wrap:wrap;margin-bottom:6px;min-height:18px;">
                @if($panel->format)
                <span style="background:var(--surface3);color:var(--text2);
                             font-size:9px;padding:1px 5px;border-radius:3px;font-weight:600;">
                    {{ $panel->format->name }}
                </span>
                @endif
                @if($panel->format?->width && $panel->format?->height)
                <span style="background:var(--surface3);color:var(--text2);
                             font-size:9px;padding:1px 5px;border-radius:3px;">
                    {{ (int)$panel->format->width }}×{{ (int)$panel->format->height }}m
                </span>
                @endif
                @if($panel->is_lit)
                <span style="background:rgba(232,160,32,0.12);color:var(--accent);
                             font-size:9px;padding:1px 5px;border-radius:3px;">
                    💡
                </span>
                @endif
            </div>

            {{-- Tarif --}}
            <div style="margin-top:auto;padding-top:6px;border-top:1px solid var(--border);">
                <div style="font-size:14px;font-weight:800;color:var(--accent);">
                    @if($panel->monthly_rate)
                        {{ number_format($panel->monthly_rate/1000, 0) }}K FCFA/mois
                    @else
                        <span style="color:var(--text3);font-size:11px;">Tarif non renseigné</span>
                    @endif
                </div>
                @if($panel->daily_traffic)
                <div style="font-size:10px;color:var(--text3);margin-top:1px;">
                    👁 {{ number_format($panel->daily_traffic) }} contacts/j
                </div>
                @endif
            </div>

            {{-- Boutons --}}
            <div style="display:flex;gap:5px;margin-top:8px;">
                <button type="button"
                        class="btn btn-ghost btn-sm"
                        style="flex:1;font-size:10px;padding:4px 6px;"
                        onclick="event.stopPropagation(); openFiche({{ json_encode($ficheData) }})">
                    📋 Fiche
                </button>
                @if($isSelectable)
                <button type="button"
                        class="btn btn-sm"
                        id="selbtn-{{ $panel->id }}"
                        style="flex:1.5;font-size:10px;padding:4px 6px;border-radius:6px;
                               background:var(--surface3);color:var(--text);
                               border:1px solid var(--border2);transition:all .15s;"
                        onclick="event.stopPropagation(); cardClick({{ $panel->id }}, {{ (float)($panel->monthly_rate ?? 0) }})">
                    + Sélectionner
                </button>
                @else
                <div style="flex:1.5;padding:4px 6px;background:var(--surface3);
                            border-radius:6px;font-size:10px;color:var(--text3);
                            text-align:center;border:1px solid var(--border2);">
                    @if($displayStatus === 'maintenance') 🔧 Maintenance
                    @elseif($displayStatus === 'occupe')  🔒 Occupé
                    @elseif($displayStatus === 'option')  ⏳ Option
                    @elseif($displayStatus === 'confirme') ✅ Confirmé
                    @else Non disponible
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
</div>

{{-- ══ VUE LISTE ══ --}}
<div id="view-list" style="display:none;margin-bottom:100px;">
    <div style="background:var(--surface);border:1px solid var(--border);
                border-radius:12px;overflow:hidden;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="border-bottom:1px solid var(--border);background:var(--surface2);">
                    <th style="padding:10px 14px;width:40px;"></th>
                    @foreach(['RÉF.','EMPLACEMENT','GPS','TYPE','IMPRESSION','DIMS','TARIF','STATUT','MAINTENANCE'] as $h)
                    <th style="padding:10px 12px;text-align:left;font-size:10px;
                               font-weight:700;color:var(--text3);letter-spacing:.5px;
                               text-transform:uppercase;white-space:nowrap;">
                        {{ $h }}
                    </th>
                    @endforeach
                    <th style="padding:10px 12px;"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($allPanels as $panel)
                @php
                    $panelStatus        = $panel->status->value;
                    $isOccupiedOnPeriod = $startDate && $endDate && $occupiedIds->contains($panel->id);
                    $isOptionOnPeriod   = $startDate && $endDate && $optionIds->contains($panel->id);

                    if ($panelStatus === 'maintenance') {
                        $displayStatus = 'maintenance';
                    } elseif ($isOccupiedOnPeriod && !$isOptionOnPeriod) {
                        $displayStatus = 'occupe';
                    } elseif ($isOptionOnPeriod) {
                        $displayStatus = 'option';
                    } else {
                        $displayStatus = $panelStatus;
                    }

                    $isSelectable = $displayStatus === 'libre' && $panelStatus !== 'maintenance';

                    $sc = match($displayStatus) {
                        'libre'       => ['label'=>'Disponible','c'=>'#22c55e','bg'=>'rgba(34,197,94,0.1)','bd'=>'rgba(34,197,94,0.3)'],
                        'occupe'      => ['label'=>'Occupé',    'c'=>'#ef4444','bg'=>'rgba(239,68,68,0.1)','bd'=>'rgba(239,68,68,0.3)'],
                        'option'      => ['label'=>'En option', 'c'=>'#e8a020','bg'=>'rgba(232,160,32,0.1)','bd'=>'rgba(232,160,32,0.3)'],
                        'confirme'    => ['label'=>'Confirmé',  'c'=>'#a855f7','bg'=>'rgba(168,85,247,0.1)','bd'=>'rgba(168,85,247,0.3)'],
                        'maintenance' => ['label'=>'Maintenance','c'=>'#6b7280','bg'=>'rgba(107,114,128,0.1)','bd'=>'rgba(107,114,128,0.3)'],
                        default       => ['label'=>$panelStatus,'c'=>'#6b7280','bg'=>'rgba(107,114,128,0.1)','bd'=>'rgba(107,114,128,0.3)'],
                    };

                    $ficheData = [
                        'id' => $panel->id, 'reference' => $panel->reference,
                        'name' => $panel->name, 'commune' => $panel->commune?->name ?? '—',
                        'zone' => $panel->zone?->name ?? '—', 'format' => $panel->format?->name ?? '—',
                        'format_width' => $panel->format?->width, 'format_height' => $panel->format?->height,
                        'category' => $panel->category?->name ?? '—', 'is_lit' => (bool)$panel->is_lit,
                        'monthly_rate' => (float)($panel->monthly_rate ?? 0),
                        'daily_traffic' => (int)($panel->daily_traffic ?? 0),
                        'zone_description' => $panel->zone_description ?? '',
                        'status' => $sc['label'], 'status_color' => $sc['c'],
                    ];
                @endphp
                <tr style="border-bottom:1px solid var(--border);transition:background .12s;"
                    onmouseover="this.style.background='var(--surface2)'"
                    onmouseout="this.style.background=''">
                    <td style="padding:10px 14px;text-align:center;">
                        @if($isSelectable)
                        <input type="checkbox"
                               :checked="selectedIds.includes({{ $panel->id }})"
                               @change="togglePanel({{ $panel->id }}, {{ (float)($panel->monthly_rate ?? 0) }})"
                               style="accent-color:var(--accent);width:14px;height:14px;cursor:pointer;">
                        @endif
                    </td>
                    <td style="padding:10px 12px;">
                        <span style="font-family:monospace;font-size:12px;font-weight:800;
                                     color:var(--accent);">{{ $panel->reference }}</span>
                    </td>
                    <td style="padding:10px 12px;">
                        <div style="font-weight:600;font-size:13px;">{{ $panel->name }}</div>
                        <div style="font-size:11px;color:var(--text3);">
                            {{ $panel->commune?->name ?? '—' }}, {{ $panel->zone?->name ?? '—' }}
                        </div>
                    </td>
                    <td style="padding:10px 12px;font-size:11px;color:var(--text3);">
                        @if($panel->latitude && $panel->longitude)
                        📍 {{ number_format($panel->latitude, 4) }},
                        {{ number_format($panel->longitude, 4) }}
                        @else
                        —
                        @endif
                    </td>
                    <td style="padding:10px 12px;">
                        @if($panel->category)
                        <span style="background:var(--surface3);color:var(--text2);
                                     font-size:10px;padding:2px 7px;border-radius:4px;
                                     font-weight:700;">
                            {{ strtoupper(substr($panel->category->name, 0, 3)) }}
                        </span>
                        @else
                        <span style="color:var(--text3);">—</span>
                        @endif
                    </td>
                    <td style="padding:10px 12px;font-size:12px;color:var(--text2);">
                        {{ $panel->format?->name ?? '—' }}
                    </td>
                    <td style="padding:10px 12px;font-size:11px;color:var(--text2);">
                        @if($panel->format?->width && $panel->format?->height)
                        {{ (int)$panel->format->width }}×{{ (int)$panel->format->height }}m
                        @else —
                        @endif
                    </td>
                    <td style="padding:10px 12px;font-weight:700;color:var(--accent);font-size:12px;">
                        @if($panel->monthly_rate)
                        {{ number_format($panel->monthly_rate/1000, 0) }}K FCFA
                        @else —
                        @endif
                    </td>
                    <td style="padding:10px 12px;">
                        <span style="padding:2px 8px;border-radius:20px;font-size:10px;
                                     font-weight:700;background:{{ $sc['bg'] }};
                                     color:{{ $sc['c'] }};border:1px solid {{ $sc['bd'] }};">
                            {{ $sc['label'] }}
                        </span>
                    </td>
                    <td style="padding:10px 12px;">
                        <span style="font-size:10px;padding:2px 7px;border-radius:4px;
                                     background:var(--surface3);color:var(--text2);">
                            <!-- Afficher le statut de la maintenance de tout les panneaux presente dans la bd -->
                            {{ $panel->maintenance_status ? ucfirst($panel->maintenance_status) : '—' }}
                        </span>
                    </td>
                    <td style="padding:10px 12px;">
                        <button type="button"
                                class="btn btn-ghost btn-sm"
                                style="font-size:11px;"
                                onclick="openFiche({{ json_encode($ficheData) }})">
                            Fiche
                        </button>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="11"
                        style="text-align:center;padding:60px;color:var(--text3);">
                        Aucun panneau trouvé.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ══ BARRE SÉLECTION BAS DE PAGE ══ --}}
<div x-show="selectedIds.length > 0"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     style="position:fixed;bottom:0;left:235px;right:0;
            background:var(--surface);border-top:2px solid var(--accent);
            padding:11px 24px;display:flex;align-items:center;
            justify-content:space-between;z-index:300;
            box-shadow:0 -4px 24px rgba(0,0,0,0.4);">
    <div style="display:flex;align-items:center;gap:12px;">
        <span style="font-size:28px;font-weight:800;color:var(--accent);line-height:1;"
              x-text="selectedIds.length"></span>
        <div>
            <div style="font-size:11px;font-weight:600;color:var(--text2);">panneau(x) sélectionné(s)</div>
            <div style="font-size:14px;font-weight:800;color:var(--accent);">
                <span x-text="formatTotal()"></span> FCFA/mois
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;">
        <button type="button" class="btn btn-ghost btn-sm"
                @click="clearSelection()">✕ Vider</button>
        <button type="button" class="btn btn-ghost btn-sm"
                style="color:var(--red);border-color:rgba(239,68,68,.4);">
            📄 PDF
        </button>
        <button type="button" class="btn btn-primary"
                @click="openConfirmModal()">
            ✅ Confirmer la sélection
        </button>
    </div>
</div>

</div>{{-- fin x-data --}}

{{-- ══ MODAL CONFIRMER ══ --}}
<div x-data="{ open: false, type: 'option' }"
     x-on:open-modal.window="if($event.detail === 'confirm-selection') open = true"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
    <div class="modal" style="max-width:540px;" @click.stop>
        <div class="modal-header">
            <span class="modal-title">✅ Confirmer la réservation</span>
            <button class="modal-close" @click="open = false">✕</button>
        </div>
        <form method="POST"
              action="{{ route('admin.reservations.confirmer-selection') }}"
              id="confirm-form">
            @csrf
            <div id="hidden-panel-inputs"></div>
            <div class="modal-body">

                @if($errors->any())
                <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);
                            border-radius:8px;padding:12px;margin-bottom:14px;">
                    @foreach($errors->all() as $e)
                    <div style="color:var(--red);font-size:12px;display:flex;gap:5px;margin-bottom:3px;">
                        <span>⚠️</span><span>{{ $e }}</span>
                    </div>
                    @endforeach
                </div>
                @endif

                <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.3);
                            border-radius:8px;padding:9px 12px;margin-bottom:12px;
                            font-size:12px;color:var(--green);">
                    🛡️ Anti double-booking actif.
                </div>

                <div style="display:flex;gap:8px;margin-bottom:12px;">
                    <label style="flex:1;cursor:pointer;padding:9px;border-radius:8px;
                                  display:flex;align-items:center;gap:7px;"
                           :style="type==='option'
                               ? 'border:2px solid #f97316;background:rgba(249,115,22,0.08);'
                               : 'border:1px solid var(--border2);background:var(--surface2);'">
                        <input type="radio" name="type" value="option" x-model="type"
                               style="accent-color:#f97316;">
                        <div>
                            <div style="font-size:12px;font-weight:700;">⏳ Option</div>
                            <div style="font-size:10px;color:var(--text2);">Blocage temporaire</div>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer;padding:9px;border-radius:8px;
                                  display:flex;align-items:center;gap:7px;"
                           :style="type==='ferme'
                               ? 'border:2px solid #22c55e;background:rgba(34,197,94,0.08);'
                               : 'border:1px solid var(--border2);background:var(--surface2);'">
                        <input type="radio" name="type" value="ferme" x-model="type"
                               style="accent-color:#22c55e;">
                        <div>
                            <div style="font-size:12px;font-weight:700;">🔒 Ferme</div>
                            <div style="font-size:10px;color:var(--text2);">Confirmation définitive</div>
                        </div>
                    </label>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div class="mfg">
                        <label>Client *</label>
                        <select name="client_id"
                                style="background:var(--surface2);border:1px solid var(--border2);
                                       border-radius:8px;padding:8px 12px;color:var(--text);
                                       font-size:13px;outline:none;width:100%;" required>
                            <option value="">— Sélectionner —</option>
                            @foreach($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mfg" x-show="type === 'ferme'" x-transition>
                        <label>Nom campagne <span style="font-size:10px;color:var(--text3);">(opt.)</span></label>
                        <input type="text" name="campaign_name"
                               placeholder="Ex: Ramadan 2026"/>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                    <div class="mfg">
                        <label>Date début *</label>
                        <input type="date" name="start_date"
                               value="{{ $startDate }}" required/>
                    </div>
                    <div class="mfg">
                        <label>Date fin *</label>
                        <input type="date" name="end_date"
                               value="{{ $endDate }}" required/>
                    </div>
                </div>

                <div class="mfg">
                    <label>Note interne</label>
                    <textarea name="notes" rows="2"
                              placeholder="Remarques, instructions…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" @click="open = false">Annuler</button>
                <button type="button" class="btn btn-primary"
                        onclick="submitConfirm()">
                    ✅ Confirmer et bloquer
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══ MODAL FICHE TECHNIQUE ══ --}}
<div x-data="{ open: false, p: {} }"
     x-on:open-modal.window="if($event.detail?.name === 'fiche-panel') {
         p = $event.detail.data; open = true;
     }"
     x-show="open"
     class="modal-overlay"
     @click.self="open = false"
     style="display:none;">
    <div class="modal" style="max-width:600px;" @click.stop>
        <div class="modal-header">
            <span class="modal-title">
                📋 <span x-text="p.reference"
                         style="font-family:monospace;color:var(--accent);"></span>
                — <span x-text="p.name" style="font-size:14px;"></span>
            </span>
            <button class="modal-close" @click="open = false">✕</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;">

            <div style="text-align:center;margin-bottom:14px;">
                <span style="padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;"
                      :style="'background:'+p.status_color+'20;color:'+p.status_color+
                               ';border:1px solid '+p.status_color+'50;'"
                      x-text="p.status"></span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:14px;">
                @foreach([
                    ['COMMUNE',     'p.commune'],
                    ['ZONE',        'p.zone'],
                    ['FORMAT',      'p.format'],
                    ['DIMENSIONS',  "(p.format_width && p.format_height) ? p.format_width + '×' + p.format_height + 'm' : '—'"],
                    ['CATÉGORIE',   'p.category'],
                    ['ÉCLAIRAGE',   "p.is_lit ? '💡 Éclairé' : 'Non éclairé'"],
                    ['TRAFIC / JOUR', "p.daily_traffic ? Number(p.daily_traffic).toLocaleString('fr-FR') + ' contacts' : '—'"],
                    ['IMPRESSION',  "p.category ?? '—'"],
                ] as [$lbl, $expr])
                <div style="background:var(--surface2);border-radius:8px;padding:9px;">
                    <div style="font-size:9px;color:var(--text3);font-weight:700;
                                letter-spacing:.5px;margin-bottom:3px;">{{ $lbl }}</div>
                    <div style="font-size:13px;color:var(--text);font-weight:500;"
                         x-text="{{ $expr }}"></div>
                </div>
                @endforeach
            </div>

            <div style="background:rgba(232,160,32,0.08);border:1px solid rgba(232,160,32,0.3);
                        border-radius:10px;padding:12px;text-align:center;margin-bottom:12px;">
                <div style="font-size:9px;color:var(--text3);font-weight:700;
                            letter-spacing:.5px;margin-bottom:2px;">TARIF MENSUEL</div>
                <div style="font-size:22px;font-weight:800;color:var(--accent);"
                     x-text="p.monthly_rate
                         ? Number(p.monthly_rate).toLocaleString('fr-FR') + ' FCFA'
                         : 'Non renseigné'">
                </div>
            </div>

            <div x-show="p.zone_description">
                <div style="font-size:9px;color:var(--text3);font-weight:700;
                            letter-spacing:.5px;margin-bottom:5px;">DESCRIPTION DE ZONE</div>
                <div style="background:var(--surface2);border-radius:8px;padding:10px;
                            font-size:12px;color:var(--text2);line-height:1.5;"
                     x-text="p.zone_description"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-ghost" @click="open = false">Fermer</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
// ── État global sélection ─────────────────────────────────────────
let _selIds   = [];
let _selRates = {};
let _alpineInst = null;

// Restaurer depuis sessionStorage au chargement
(function() {
    try {
        const s = sessionStorage.getItem('progicia_dispo_sel');
        if (s) {
            const d = JSON.parse(s);
            _selIds   = d.ids   || [];
            _selRates = d.rates || {};
        }
    } catch(e) {}

    // Nettoyer si succès
    @if(session('success'))
        sessionStorage.removeItem('progicia_dispo_sel');
        _selIds   = [];
        _selRates = {};
    @endif
})();

function disponibilites() {
    return {
        selectedIds:   _selIds,
        selectedRates: _selRates,

        init() {
            _alpineInst = this;
            // Sync visuels au chargement
            this.$nextTick(() => {
                this.selectedIds.forEach(id => updateCardVisual(id, true));
            });
        },

        togglePanel(id, rate) {
            const idx = this.selectedIds.indexOf(id);
            if (idx === -1) {
                this.selectedIds.push(id);
                this.selectedRates[id] = parseFloat(rate) || 0;
                updateCardVisual(id, true);
            } else {
                this.selectedIds.splice(idx, 1);
                delete this.selectedRates[id];
                updateCardVisual(id, false);
            }
            _selIds   = this.selectedIds;
            _selRates = this.selectedRates;
            saveSession();
            updateTopBar(this.selectedIds.length);
        },

        clearSelection() {
            this.selectedIds.forEach(id => updateCardVisual(id, false));
            this.selectedIds   = [];
            this.selectedRates = {};
            _selIds   = [];
            _selRates = {};
            saveSession();
            updateTopBar(0);
        },

        formatTotal() {
            const t = Object.values(this.selectedRates).reduce((s,r) => s+r, 0);
            return Math.round(t).toLocaleString('fr-FR');
        },

        openConfirmModal() {
            this.$dispatch('open-modal', 'confirm-selection');
            this.$nextTick(() => syncHiddenInputs(this.selectedIds));
        },
    };
}

// ── Fonctions globales ────────────────────────────────────────────
function cardClick(id, rate) {
    if (_alpineInst) _alpineInst.togglePanel(id, rate);
}

function cardHover(el, entering) {
    if (!entering) {
        const isSelected = _selIds.includes(parseInt(el.dataset.id));
        el.style.transform = '';
        el.style.boxShadow = isSelected ? '0 0 0 3px rgba(232,160,32,0.4)' : '';
    } else {
        el.style.transform = 'translateY(-3px)';
        el.style.boxShadow = '0 8px 24px rgba(0,0,0,0.3)';
    }
}

function updateCardVisual(id, selected) {
    // Grille
    const card = document.querySelector(`.panel-card[data-id="${id}"]`);
    if (card) {
        card.style.borderColor = selected ? 'var(--accent)' : '';
        card.style.boxShadow   = selected ? '0 0 0 3px rgba(232,160,32,0.3)' : '';
    }
    // Checkbox
    const chk = document.getElementById(`chk-${id}`);
    if (chk) chk.checked = selected;
    // Bouton sélectionner
    const btn = document.getElementById(`selbtn-${id}`);
    if (btn) {
        btn.style.background = selected ? 'var(--accent)' : 'var(--surface3)';
        btn.style.color      = selected ? '#000' : 'var(--text)';
        btn.style.border     = selected ? 'none' : '1px solid var(--border2)';
        btn.textContent      = selected ? '✓ Sélectionné' : '+ Sélectionner';
    }
}

function updateTopBar(count) {
    const wrapper = document.getElementById('confirm-btn-wrapper');
    const topCount = document.getElementById('top-count');
    if (wrapper) wrapper.style.display = count > 0 ? 'block' : 'none';
    if (topCount) topCount.textContent = count;
}

function saveSession() {
    try {
        sessionStorage.setItem('progicia_dispo_sel', JSON.stringify({
            ids:   _selIds,
            rates: _selRates,
        }));
    } catch(e) {}
}

function syncHiddenInputs(ids) {
    const container = document.getElementById('hidden-panel-inputs');
    if (!container) return;
    container.innerHTML = '';
    ids.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'panel_ids[]'; inp.value = id;
        container.appendChild(inp);
    });
}

function submitConfirm() {
    syncHiddenInputs(_selIds);
    document.getElementById('confirm-form').submit();
}

function openConfirmModal() {
    if (_alpineInst) _alpineInst.openConfirmModal();
}

function openFiche(data) {
    if (_alpineInst) {
        _alpineInst.$dispatch('open-modal', { name: 'fiche-panel', data });
    }
}

// ── Toggle vue grille/liste ────────────────────────────────────────
function setView(v) {
    const grid = document.getElementById('view-grid');
    const list = document.getElementById('view-list');
    const btnG = document.getElementById('btn-grid');
    const btnL = document.getElementById('btn-list');
    if (v === 'grid') {
        grid.style.display = 'block'; list.style.display = 'none';
        btnG.style.background = 'var(--accent)'; btnG.style.color = '#000';
        btnL.style.background = 'transparent';  btnL.style.color = 'var(--text2)';
    } else {
        grid.style.display = 'none'; list.style.display = 'block';
        btnL.style.background = 'var(--accent)'; btnL.style.color = '#000';
        btnG.style.background = 'transparent';  btnG.style.color = 'var(--text2)';
    }
    localStorage.setItem('progicia_dispo_view', v);
}

// ── Sync date ──────────────────────────────────────────────────────
function syncMinDate() {
    const s = document.getElementById('input-dispo-du');
    const e = document.getElementById('input-dispo-au');
    if (!s || !e) return;
    if (s.value) {
        const d = new Date(s.value); d.setDate(d.getDate()+1);
        e.min = d.toISOString().split('T')[0];
        if (e.value && e.value <= s.value) {
            e.value = '';
            e.form && e.form.submit();
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    syncMinDate();
    // Restaurer vue préférée
    const savedView = localStorage.getItem('progicia_dispo_view') || 'grid';
    setView(savedView);
    // Init visuel
    updateTopBar(_selIds.length);
    _selIds.forEach(id => updateCardVisual(id, true));
});
</script>
@endpush
</x-admin-layout>
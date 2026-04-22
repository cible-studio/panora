{{-- resources/views/admin/piges/create.blade.php --}}
<x-admin-layout title="Uploader des piges">

<x-slot:topbarActions>
    <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost btn-sm" style="display:flex;align-items:center;gap:5px">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Retour
    </a>
</x-slot:topbarActions>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

<script>
window.__PIGE__ = {
    searchCampaignsUrl: '{{ route("admin.pose-tasks.search-campaigns") }}',
    campaignPanelsUrl:  '{{ route("admin.piges.campaign-panels") }}',
    searchPanelsUrl:    '{{ route("admin.pose-tasks.search-panels") }}',
    csrf: '{{ csrf_token() }}',
    preselectedCampaign: @if($preselectedCampaign) {
        id:    {{ $preselectedCampaign->id }},
        name:  @json($preselectedCampaign->name),
        label: @json($preselectedCampaign->status->label()),
        icon:  @json($preselectedCampaign->status->uiConfig()['icon']),
        color: @json($preselectedCampaign->status->uiConfig()['color']),
        blocked: {{ $preselectedCampaign->status->isTerminal() ? 'true' : 'false' }},
    } @else null @endif,
    preselectedPanel: @if($preselectedPanel) {
        id:        {{ $preselectedPanel->id }},
        reference: @json($preselectedPanel->reference),
        name:      @json($preselectedPanel->name),
        commune:   @json($preselectedPanel->commune?->name ?? '—'),
    } @else null @endif,
    maxFileSizeMb: 30,
};
</script>

<style>
/* ═══════════════════════════════════════════════════════════════
   STYLES — Select2 GLOBAL (body-level, pas dans la section)
   Le dropdown est rendu dans <body> pour éviter tout overflow:hidden
   ═══════════════════════════════════════════════════════════════ */

/* Base sections */
.pg-section { background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:14px;position:relative }
.pg-section-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:16px }
.pg-section-title { font-size:14px;font-weight:700;color:var(--text) }
.pg-section-sub { font-size:11px;color:var(--text3);margin-top:2px }
.pg-step { width:26px;height:26px;background:var(--accent);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;margin-top:1px }
.pg-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:5px }
.pg-input { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);transition:border-color .2s;box-sizing:border-box;outline:none }
.pg-input:focus { border-color:var(--accent) }
.pg-select { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);cursor:pointer;outline:none }
.err-box { background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:18px }

/* Panel row */
.panel-row { display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;box-sizing:border-box;user-select:none }
.panel-row:last-child { border-bottom:none }
.panel-row:hover { background:rgba(232,160,32,.04) }
.panel-row.active { background:rgba(232,160,32,.09);border-left:3px solid var(--accent) }
.panel-selected-display { display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(232,160,32,.06);border:1.5px solid rgba(232,160,32,.3);border-radius:10px;margin-bottom:12px }

/* Commune pills */
.commune-pill { font-size:10px;color:var(--text3);background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:2px 9px;cursor:pointer;transition:all .12s;white-space:nowrap }
.commune-pill:hover,.commune-pill.active { background:rgba(232,160,32,.1);border-color:rgba(232,160,32,.4);color:var(--accent) }

/* Drop zone */
.drop-zone { border:2px dashed var(--border);border-radius:12px;padding:32px 20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--surface2);min-height:120px;display:flex;align-items:center;justify-content:center }
.drop-zone.dragging { border-color:var(--accent);background:rgba(232,160,32,.04) }
.drop-zone:hover { border-color:rgba(232,160,32,.4) }

/* Photo preview */
.photo-prev { position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:var(--surface2);border:1px solid var(--border) }
.photo-prev img { width:100%;height:100%;object-fit:cover }
.photo-prev .rm { position:absolute;top:4px;right:4px;width:20px;height:20px;background:rgba(0,0,0,.75);border:none;border-radius:50%;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:0 }
.photo-prev .label { position:absolute;bottom:0;left:0;right:0;padding:3px 5px;background:rgba(0,0,0,.6);font-size:9px;color:#fff;overflow:hidden;text-overflow:ellipsis;white-space:nowrap }

/* ── Select2 — rendu dans body (dropdownParent: $('body')) ── */
.select2-container--default .select2-selection--single {
    height:40px!important; border-radius:10px!important;
    border:1px solid var(--border)!important; background:var(--surface2)!important;
    display:flex; align-items:center;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height:40px!important; color:var(--text)!important;
    padding-left:12px!important; font-size:13px; padding-right:28px!important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height:38px!important; right:6px!important;
}
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color:var(--accent)!important; outline:none!important; box-shadow:none!important;
}
/* Dropdown global — z-index élevé, pas limité par overflow parent */
.select2-dropdown {
    background:var(--surface)!important;
    border:1px solid var(--border)!important;
    border-radius:12px!important;
    box-shadow:0 12px 40px rgba(0,0,0,.35)!important;
    overflow:hidden!important;
    z-index:99999!important;
    animation:s2FadeIn .12s ease;
}
@keyframes s2FadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:none} }

.select2-container--default .select2-search--dropdown .select2-search__field {
    background:var(--surface2)!important; border:1px solid var(--border)!important;
    border-radius:8px!important; color:var(--text)!important;
    padding:6px 10px!important; font-size:13px; outline:none;
    margin:8px!important; width:calc(100% - 16px)!important; box-sizing:border-box;
}
.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color:var(--accent)!important;
}
.select2-results__option { padding:0!important; font-size:13px; color:var(--text); transition:background .1s }
.select2-results__option--highlighted { background:rgba(232,160,32,.08)!important; color:var(--text)!important }
.select2-results__option[aria-selected=true] { background:rgba(232,160,32,.12)!important; color:var(--text)!important }
.select2-container { width:100%!important }
.select2-results__options { max-height:300px; overflow-y:auto }
.select2-results__message { color:var(--text3)!important; font-size:12px; padding:12px!important; text-align:center }

/* Items dans le dropdown */
.s2-camp { display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border) }
.s2-camp:last-child { border-bottom:none }
.s2-camp.blocked { opacity:.45 }
.s2-panel-opt { display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border) }
.s2-panel-opt:last-child { border-bottom:none }
.s2-ref { font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);flex-shrink:0 }

@keyframes spin { to { transform:rotate(360deg) } }
</style>

<div style="max-width:900px;margin:0 auto 40px;padding:0 14px">

    @if($errors->any())
    <div class="err-box">
        <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:8px;display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Veuillez corriger les erreurs
        </div>
        <ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444;display:flex;flex-direction:column;gap:3px">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Info métier --}}
    <div style="background:rgba(59,130,246,.06);border:1px solid rgba(59,130,246,.2);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:flex-start;gap:10px">
        <svg width="14" height="14" style="flex-shrink:0;margin-top:1px" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <div style="font-size:12px;color:#3b82f6;line-height:1.5">
            <strong>Pige = preuve photo d'affichage.</strong>
            Vous pouvez piger une campagne active avec ou sans pose préalable enregistrée, ou uploader une pige hors campagne pour un contrôle spot.
        </div>
    </div>

    <form method="POST" action="{{ route('admin.piges.store') }}" enctype="multipart/form-data" id="pige-form">
        @csrf

        {{-- ══ S1 : CAMPAGNE ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">1</div>
                <div>
                    <div class="pg-section-title">Campagne <span style="font-weight:400;font-size:11px;color:var(--text3)">(optionnel)</span></div>
                    <div class="pg-section-sub">Associez la pige à une campagne — toutes les campagnes sont accessibles</div>
                </div>
            </div>

            {{-- Select2 campagne — dropdownParent: body pour éviter overflow --}}
            <select id="sel-campaign" name="campaign_id" style="width:100%">
                <option value="">— Pige sans campagne (contrôle spot) —</option>
                @if($preselectedCampaign)
                <option value="{{ $preselectedCampaign->id }}" selected>{{ $preselectedCampaign->name }}</option>
                @endif
            </select>

            {{-- Info campagne sélectionnée --}}
            <div id="campaign-info" style="display:none;margin-top:10px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--surface2)">
                <div style="display:flex;align-items:center;gap:10px">
                    <span id="ci-icon" style="font-size:20px"></span>
                    <div style="flex:1;min-width:0">
                        <div id="ci-name" style="font-size:13px;font-weight:700;color:var(--text)"></div>
                        <div style="display:flex;gap:10px;margin-top:3px;flex-wrap:wrap">
                            <span id="ci-status" style="font-size:11px;font-weight:600"></span>
                            <span id="ci-dates"  style="font-size:11px;color:var(--text3)"></span>
                            <span id="ci-panels" style="font-size:11px;color:var(--text3)"></span>
                        </div>
                    </div>
                    <div id="ci-warning" style="display:none;font-size:10px;font-weight:700;color:#f97316;padding:3px 8px;background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:6px;white-space:nowrap">
                        Campagne terminée
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ S2 : PANNEAU ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">2</div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                        <div class="pg-section-title">Panneau *</div>
                        <div id="panel-selected-badge" style="display:none;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(232,160,32,.12);color:var(--accent);white-space:nowrap"></div>
                    </div>
                    <div class="pg-section-sub">Sélectionnez un panneau parmi ceux de la campagne, ou cherchez librement dans le réseau</div>
                </div>
            </div>

            <input type="hidden" name="panel_id" id="panel-id-input" value="{{ old('panel_id', $preselectedPanel?->id) }}">

            @if($preselectedPanel)
            <div class="panel-selected-display" id="panel-current">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                <span id="pc-ref" style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">{{ $preselectedPanel->reference }}</span>
                <span id="pc-name" style="font-size:12px;color:var(--text)">{{ $preselectedPanel->name }}</span>
                <span id="pc-commune" style="font-size:10px;color:var(--text3)">📍 {{ $preselectedPanel->commune?->name }}</span>
                <button type="button" onclick="PIGE.clearPanel()" style="margin-left:auto;background:none;border:none;color:var(--text3);cursor:pointer;line-height:0">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            @else
            <div id="panel-current" class="panel-selected-display" style="display:none">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                <span id="pc-ref" style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)"></span>
                <span id="pc-name" style="font-size:12px;color:var(--text)"></span>
                <span id="pc-commune" style="font-size:10px;color:var(--text3)"></span>
                <button type="button" onclick="PIGE.clearPanel()" style="margin-left:auto;background:none;border:none;color:var(--text3);cursor:pointer;line-height:0">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            @endif

            {{-- Zone panneaux de la campagne (virtualisation) --}}
            <div id="panels-campaign-zone" style="display:{{ $preselectedCampaign ? 'block' : 'none' }}">

                {{-- Toolbar filtre --}}
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
                    <div style="position:relative;flex:1;min-width:150px">
                        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="panel-filter-input"
                               placeholder="Filtrer par référence, nom, commune…"
                               style="width:100%;height:36px;padding:0 10px 0 30px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box;transition:border-color .2s"
                               oninput="PIGE.filterPanels(this.value)"
                               onfocus="this.style.borderColor='var(--accent)'"
                               onblur="this.style.borderColor='var(--border)'">
                    </div>
                    <div id="panels-stats-label" style="font-size:11px;color:var(--text3);white-space:nowrap"></div>
                </div>

                {{-- Filtres commune --}}
                <div id="commune-filters" style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:8px"></div>

                {{-- Loader --}}
                <div id="panels-loader" style="display:none;text-align:center;padding:24px;color:var(--text3)">
                    <div style="width:20px;height:20px;border:2px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 10px"></div>
                    Chargement des panneaux…
                </div>

                {{-- Message état --}}
                <div id="panels-state-msg" style="display:none"></div>

                {{-- Liste virtualisée --}}
                <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
                    <div id="panels-viewport" style="max-height:340px;overflow-y:auto;position:relative" onscroll="PIGE.onScroll()">
                        <div id="panels-spacer" style="position:relative">
                            <div id="panels-rendered" style="position:absolute;left:0;right:0;top:0"></div>
                        </div>
                    </div>
                    <div id="panels-footer" style="display:none;padding:7px 14px;border-top:1px solid var(--border);background:var(--surface2);font-size:11px;color:var(--text3);display:flex;justify-content:space-between;align-items:center">
                        <span id="footer-count"></span>
                        <span id="footer-selected" style="color:var(--accent);font-weight:600"></span>
                    </div>
                </div>
            </div>

            {{-- Zone panneau libre (Select2 AJAX — dans body) --}}
            <div id="panels-free-zone" style="display:{{ $preselectedCampaign ? 'none' : ($preselectedPanel ? 'none' : 'block') }}">
                <select id="sel-panel-free" style="width:100%">
                    <option value="">Rechercher un panneau par référence ou nom…</option>
                    @if($preselectedPanel && !$preselectedCampaign)
                    <option value="{{ $preselectedPanel->id }}" selected>{{ $preselectedPanel->reference }} · {{ $preselectedPanel->name }}</option>
                    @endif
                </select>
                <div style="font-size:11px;color:var(--text3);margin-top:6px;display:flex;align-items:center;gap:5px">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    Tapez au moins 2 caractères · Tous les panneaux du réseau sont disponibles
                </div>
            </div>

            {{-- Message initial --}}
            <div id="panels-hint" style="display:none;text-align:center;padding:24px;color:var(--text3);font-size:12px;border:1.5px dashed var(--border);border-radius:10px">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.3"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Sélectionnez une campagne pour voir ses panneaux
            </div>
        </div>

        {{-- ══ S3 : PHOTOS ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">3</div>
                <div style="flex:1">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div class="pg-section-title">Photos *</div>
                        <span id="photo-counter" style="display:none;font-size:11px;font-weight:600;padding:2px 9px;border-radius:12px;background:rgba(232,160,32,.1);color:var(--accent)"></span>
                    </div>
                    <div class="pg-section-sub">Max 10 photos · JPG, PNG, WebP · <strong>30 Mo max</strong> par photo</div>
                </div>
            </div>

            <div id="drop-zone" class="drop-zone"
                 onclick="document.getElementById('photos-input').click()"
                 ondragover="PIGE.onDragOver(event)"
                 ondragleave="PIGE.onDragLeave(event)"
                 ondrop="PIGE.onDrop(event)">
                <div id="dz-idle">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 12px;color:var(--text3);opacity:.4">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <div style="font-size:14px;font-weight:600;color:var(--text2);margin-bottom:4px">Glissez-déposez vos photos ici</div>
                    <div style="font-size:12px;color:var(--text3)">ou <span style="color:var(--accent);font-weight:600">cliquez pour sélectionner</span></div>
                    <div style="font-size:10px;color:var(--text3);margin-top:8px;opacity:.7">JPG · PNG · WebP · 30 Mo max · 10 photos max</div>
                </div>
                <div id="dz-drag" style="display:none;font-size:14px;font-weight:700;color:var(--accent)">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2" style="display:block;margin:0 auto 8px">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    Déposez ici
                </div>
            </div>

            <input type="file" id="photos-input" name="photos[]" multiple
                   accept="image/jpeg,image/png,image/webp"
                   style="display:none" onchange="PIGE.onFileSelect(this.files)">

            <div id="preview-grid" style="display:none;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:8px;margin-top:12px"></div>
            <div id="photo-error" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:8px;font-size:12px;color:#ef4444"></div>
        </div>

        {{-- ══ S4 : MÉTADONNÉES ══ --}}
        <div class="pg-section">
            <div class="pg-section-header">
                <div class="pg-step">4</div>
                <div><div class="pg-section-title">Métadonnées <span style="font-weight:400;color:var(--text3);font-size:11px">(optionnel)</span></div></div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label class="pg-label">Date & heure de prise de vue</label>
                    <input type="datetime-local" name="taken_at" class="pg-input" value="{{ old('taken_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label class="pg-label">GPS Latitude</label>
                    <input type="number" name="gps_lat" step="0.0000001" class="pg-input" placeholder="5.3464" value="{{ old('gps_lat') }}">
                </div>
                <div>
                    <label class="pg-label">GPS Longitude</label>
                    <input type="number" name="gps_lng" step="0.0000001" class="pg-input" placeholder="-4.0267" value="{{ old('gps_lng') }}">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label class="pg-label">Technicien vérificateur</label>
                    <select name="user_id" class="pg-select">
                        <option value="">— Moi ({{ auth()->user()->name }}) —</option>
                        @foreach($techniciens as $t)
                        <option value="{{ $t->id }}" {{ old('user_id')==$t->id?'selected':'' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:flex;align-items:flex-end">
                    <button type="button" onclick="PIGE.getGeo()" id="btn-geo"
                            style="height:40px;padding:0 14px;font-size:11px;color:var(--accent);background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:10px;cursor:pointer;display:flex;align-items:center;gap:6px;white-space:nowrap">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        Obtenir ma position GPS
                    </button>
                </div>
            </div>

            <div>
                <label class="pg-label">Notes <span style="font-weight:400;color:var(--text3)">(remarques terrain, conditions d'affichage…)</span></label>
                <textarea name="notes" class="pg-input" style="height:auto;resize:none;padding:10px 12px" rows="2"
                          placeholder="Ex: Visuel bien positionné, éclairage fonctionnel, aucune dégradation…">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- ══ ACTIONS ══ --}}
        <div style="display:flex;gap:10px;align-items:center;padding-top:2px">
            <button type="submit" id="btn-submit" class="btn btn-primary"
                    style="min-width:200px;display:flex;align-items:center;justify-content:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                <span id="btn-label">Uploader les piges</span>
            </button>
            <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost">Annuler</a>
            <div id="submit-summary" style="display:none;font-size:12px;color:var(--text3)"></div>
        </div>
    </form>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

@push('scripts')
<script>
(function(){
'use strict';
const D = window.__PIGE__;
const MAX_MB = D.maxFileSizeMb || 30;

// ── État global ───────────────────────────────────────────────
let _allPanels       = [];
let _filteredPanels  = [];
let _selectedPanelId = D.preselectedPanel?.id || null;
let _activeCommune   = null;
let _files           = [];
const ROW_H = 52, OVERSCAN = 5;
let _visStart = 0, _visEnd = 0;

// ══════════════════════════════════════════════════════════════
// SELECT2 CAMPAGNE
// dropdownParent: $('body') ← CORRECTION PRINCIPALE
// Cela rend le dropdown dans le <body> au lieu de la section
// → plus aucun problème de overflow:hidden ou z-index
// ══════════════════════════════════════════════════════════════
function fmtCamp(c) {
    if (!c.id) return $(`<span style="color:var(--text3);font-size:13px">${c.text}</span>`);
    const blocked = c.blocked ? ' blocked' : '';
    return $(`<div class="s2-camp${blocked}">
        <span style="font-size:18px;flex-shrink:0">${c.icon || '📢'}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.text}</div>
            <div style="font-size:10px;color:${c.color || 'var(--text3)'}">
                ${[c.label, c.dates, c.total_panels ? c.total_panels + ' panneaux' : ''].filter(Boolean).join(' · ')}
            </div>
        </div>
        ${c.blocked ? '<span style="font-size:10px;color:#f97316;white-space:nowrap;flex-shrink:0">Terminée</span>' : ''}
    </div>`);
}

$('#sel-campaign').select2({
    placeholder:        '— Pige sans campagne (contrôle spot) —',
    allowClear:         true,
    minimumInputLength: 0,
    language: {
        searching:    () => 'Recherche…',
        noResults:    () => 'Aucune campagne trouvée',
        inputTooShort:() => 'Tapez pour rechercher une campagne',
        loadingMore:  () => 'Chargement…',
    },
    ajax: {
        url:      D.searchCampaignsUrl,
        dataType: 'json',
        delay:    250,
        headers:  { 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
        data:     p => ({ q: p.term || '', status: '' }), // status vide = toutes campagnes
        processResults: data => ({
            results: data.map(c => ({ id: c.id, text: c.name, ...c }))
        }),
        cache: true,
    },
    templateResult:    fmtCamp,
    templateSelection: c => c.id ? `${c.icon || '📢'} ${c.text}` : c.text,
    // ↓ CLEF : rendu dans body, pas dans la section
    dropdownParent: $('body'),
    width: '100%',
});

if (D.preselectedCampaign) {
    const pc = D.preselectedCampaign;
    $('#sel-campaign').append(new Option(pc.name, pc.id, true, true)).trigger('change');
}

$('#sel-campaign').on('change', function() {
    const d = $(this).select2('data')[0];
    if (!d?.id) PIGE.onCampCleared();
    else PIGE.onCampSelected(d);
});

// ══════════════════════════════════════════════════════════════
// SELECT2 PANNEAU LIBRE — aussi rendu dans body
// ══════════════════════════════════════════════════════════════
function fmtPanel(p) {
    if (!p.id) return $(`<span style="color:var(--text3)">${p.text}</span>`);
    return $(`<div class="s2-panel-opt">
        <span class="s2-ref">${p.reference || ''}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.text}</div>
            <div style="font-size:10px;color:var(--text3)">📍 ${p.commune || '—'}</div>
        </div>
    </div>`);
}

$('#sel-panel-free').select2({
    placeholder:        'Rechercher un panneau par référence ou nom…',
    allowClear:         true,
    minimumInputLength: 2,
    language: {
        inputTooShort: () => 'Tapez au moins 2 caractères',
        searching:     () => 'Recherche…',
        noResults:     () => 'Aucun panneau trouvé',
    },
    ajax: {
        url:      D.searchPanelsUrl,
        dataType: 'json',
        delay:    250,
        headers:  { 'X-CSRF-TOKEN': D.csrf, 'Accept': 'application/json' },
        data:     p => ({ q: p.term }),
        processResults: data => ({
            results: data.map(p => ({ id: p.id, text: p.name, ...p }))
        }),
        cache: true,
    },
    templateResult:    fmtPanel,
    templateSelection: p => p.id ? `${p.reference || ''} · ${p.text}` : p.text,
    dropdownParent: $('body'), // ← aussi dans body
    width: '100%',
});

$('#sel-panel-free').on('change', function() {
    const d = $(this).select2('data')[0];
    if (d?.id) {
        _selectedPanelId = parseInt(d.id);
        document.getElementById('panel-id-input').value = d.id;
        PIGE._showCurrentPanel(d.reference || '', d.text, d.commune || '—');
        PIGE._updateSubmitLabel();
    }
});

// ══════════════════════════════════════════════════════════════
// LOGIQUE
// ══════════════════════════════════════════════════════════════
window.PIGE = {

    onCampSelected(camp) {
        // Afficher infos campagne
        document.getElementById('ci-icon').textContent   = camp.icon   || '📢';
        document.getElementById('ci-name').textContent   = camp.text   || '';
        document.getElementById('ci-status').textContent = camp.label  || '';
        document.getElementById('ci-status').style.color = camp.color  || 'var(--text3)';
        document.getElementById('ci-dates').textContent  = camp.dates  || '';
        document.getElementById('ci-panels').textContent = camp.total_panels ? camp.total_panels + ' panneaux' : '';
        document.getElementById('ci-warning').style.display = camp.blocked ? 'inline-block' : 'none';
        document.getElementById('campaign-info').style.display = 'flex';

        // Basculer zones
        document.getElementById('panels-free-zone').style.display     = 'none';
        document.getElementById('panels-hint').style.display          = 'none';
        document.getElementById('panels-campaign-zone').style.display = 'block';

        this._loadPanels(camp.id);
    },

    onCampCleared() {
        document.getElementById('campaign-info').style.display          = 'none';
        document.getElementById('panels-campaign-zone').style.display   = 'none';
        document.getElementById('panels-free-zone').style.display       = 'block';
        _allPanels = []; _filteredPanels = [];
    },

    clearPanel() {
        _selectedPanelId = null;
        document.getElementById('panel-id-input').value = '';
        document.getElementById('panel-current').style.display = 'none';
        const badge = document.getElementById('panel-selected-badge');
        if (badge) badge.style.display = 'none';
        this._updateSubmitLabel();
    },

    _showCurrentPanel(ref, name, commune) {
        document.getElementById('pc-ref').textContent    = ref;
        document.getElementById('pc-name').textContent   = name;
        document.getElementById('pc-commune').textContent= '📍 ' + commune;
        document.getElementById('panel-current').style.display = 'flex';
        const badge = document.getElementById('panel-selected-badge');
        if (badge) { badge.textContent = ref + ' sélectionné ✓'; badge.style.display = 'inline-block'; }
    },

    // ── Chargement panneaux avec messages d'état clairs ──────
    async _loadPanels(campaignId) {
        _allPanels = []; _filteredPanels = []; _activeCommune = null;
        _visStart = 0; _visEnd = 0;

        const loader   = document.getElementById('panels-loader');
        const stateMsg = document.getElementById('panels-state-msg');
        const rendered = document.getElementById('panels-rendered');
        const footer   = document.getElementById('panels-footer');
        const communes = document.getElementById('commune-filters');
        const statsLbl = document.getElementById('panels-stats-label');
        const filterIn = document.getElementById('panel-filter-input');

        loader.style.display   = 'block';
        stateMsg.style.display = 'none';
        rendered.innerHTML     = '';
        footer.style.display   = 'none';
        communes.innerHTML     = '';
        statsLbl.textContent   = '';
        filterIn.value         = '';

        try {
            const res = await fetch(
                `${D.campaignPanelsUrl}?campaign_id=${campaignId}`,
                { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf } }
            );

            if (!res.ok) {
                throw new Error(`Erreur serveur (${res.status})`);
            }

            const data = await res.json();

            if (!data.panels || !Array.isArray(data.panels)) {
                throw new Error('Réponse invalide du serveur');
            }

            _allPanels = data.panels;
            loader.style.display = 'none';

            // ── Message si la campagne n'a pas de panneaux ─────
            if (_allPanels.length === 0) {
                stateMsg.style.display = 'block';
                stateMsg.innerHTML = `
                    <div style="padding:24px;text-align:center;color:var(--text3);border:1px solid var(--border);border-radius:10px;background:var(--surface2)">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 10px;opacity:.3">
                            <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
                        </svg>
                        <div style="font-size:13px;font-weight:600;margin-bottom:6px">Aucun panneau dans cette campagne</div>
                        <div style="font-size:12px;line-height:1.5">
                            Cette campagne ne contient pas encore de panneaux associés.<br>
                            Allez dans la <a href="/admin/campaigns" style="color:var(--accent);text-decoration:none;font-weight:600">gestion des campagnes</a> pour ajouter des panneaux,
                            ou sélectionnez un panneau libre ci-dessous.
                        </div>
                        <button type="button"
                                onclick="PIGE._switchToFreePanel()"
                                style="margin-top:12px;padding:7px 16px;background:rgba(232,160,32,.1);border:1px solid rgba(232,160,32,.3);color:var(--accent);border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">
                            🔍 Chercher un panneau librement
                        </button>
                    </div>`;
                return;
            }

            // Stats
            const avecPige = _allPanels.filter(p => p.pige_total > 0).length;
            const sansPige = _allPanels.length - avecPige;
            statsLbl.innerHTML =
                `<span style="color:#f97316;font-weight:700">${sansPige} sans pige</span>` +
                ` · <span style="color:#22c55e">${avecPige} déjà pigé(s)</span>`;

            this._buildCommunes();
            this._applyFilter();
            this._renderVirtual();

        } catch (err) {
            loader.style.display = 'none';
            stateMsg.style.display = 'block';
            stateMsg.innerHTML = `
                <div style="padding:20px;border:1px solid rgba(239,68,68,.25);border-radius:10px;background:rgba(239,68,68,.04)">
                    <div style="display:flex;align-items:flex-start;gap:10px">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" style="flex-shrink:0;margin-top:1px">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                        <div>
                            <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:4px">
                                Impossible de charger les panneaux
                            </div>
                            <div style="font-size:12px;color:rgba(239,68,68,.8);line-height:1.5;margin-bottom:10px">
                                ${err.message || 'Vérifiez votre connexion et réessayez.'}
                                Cela peut arriver si la campagne n'a pas encore de panneaux associés dans le système.
                            </div>
                            <div style="display:flex;gap:8px;flex-wrap:wrap">
                                <button type="button" onclick="PIGE._loadPanels(${campaignId})"
                                        style="padding:6px 14px;background:rgba(232,160,32,.1);border:1px solid rgba(232,160,32,.3);color:var(--accent);border-radius:8px;font-size:11px;font-weight:600;cursor:pointer">
                                    ⟳ Réessayer
                                </button>
                                <button type="button" onclick="PIGE._switchToFreePanel()"
                                        style="padding:6px 14px;background:var(--surface2);border:1px solid var(--border);color:var(--text2);border-radius:8px;font-size:11px;font-weight:600;cursor:pointer">
                                    🔍 Chercher un panneau librement
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
        }
    },

    // Option de secours : chercher un panneau sans campagne
    _switchToFreePanel() {
        document.getElementById('panels-campaign-zone').style.display = 'none';
        document.getElementById('panels-free-zone').style.display     = 'block';
        document.getElementById('panels-state-msg').style.display     = 'none';
    },

    _buildCommunes() {
        const communes = [...new Set(_allPanels.map(p => p.commune).filter(Boolean))].sort();
        const wrap = document.getElementById('commune-filters');
        if (communes.length <= 1) { wrap.innerHTML = ''; return; }
        wrap.innerHTML = communes.map(c => {
            const count = _allPanels.filter(p => p.commune === c).length;
            return `<button type="button" class="commune-pill" data-commune="${c}"
                            onclick="PIGE.filterCommune('${c.replace(/'/g, "\\'")}')">
                        ${c} <span style="opacity:.5;font-size:9px">${count}</span>
                    </button>`;
        }).join('');
    },

    filterCommune(c) {
        _activeCommune = _activeCommune === c ? null : c;
        document.querySelectorAll('.commune-pill').forEach(p =>
            p.classList.toggle('active', p.dataset.commune === _activeCommune)
        );
        this._applyFilter();
        this._renderVirtual();
    },

    _filterTimer: null,
    filterPanels(q) {
        clearTimeout(this._filterTimer);
        this._filterTimer = setTimeout(() => { this._applyFilter(q); this._renderVirtual(); }, 80);
    },

    _applyFilter(q = document.getElementById('panel-filter-input')?.value || '') {
        const lq = q.trim().toLowerCase();
        _filteredPanels = _allPanels.filter(p => {
            const mt = !lq ||
                p.reference.toLowerCase().includes(lq) ||
                p.name.toLowerCase().includes(lq) ||
                (p.commune || '').toLowerCase().includes(lq);
            const mc = !_activeCommune || p.commune === _activeCommune;
            return mt && mc;
        });

        const vis = _filteredPanels.length, tot = _allPanels.length;
        const fc = document.getElementById('footer-count');
        if (fc) fc.textContent = vis < tot ? `${vis} sur ${tot} panneaux` : `${tot} panneaux`;
        document.getElementById('panels-footer').style.display = 'flex';
    },

    onScroll() { this._renderVirtual(); },

    _renderVirtual() {
        const panels = _filteredPanels, count = panels.length;
        const vp = document.getElementById('panels-viewport');
        const sp = document.getElementById('panels-spacer');
        const rd = document.getElementById('panels-rendered');

        if (count === 0) {
            sp.style.height = '0';
            rd.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text3);font-size:12px">
                Aucun panneau correspond à votre recherche.
            </div>`;
            return;
        }

        sp.style.height = (count * ROW_H) + 'px';
        sp.style.position = 'relative';
        const st = vp.scrollTop, vh = vp.clientHeight || 340;
        const start = Math.max(0, Math.floor(st / ROW_H) - OVERSCAN);
        const end   = Math.min(count - 1, Math.ceil((st + vh) / ROW_H) + OVERSCAN);

        if (start === _visStart && end === _visEnd && rd.children.length > 0) {
            this._syncActive(); return;
        }
        _visStart = start; _visEnd = end;

        rd.style.cssText = `position:absolute;top:${start * ROW_H}px;left:0;right:0`;
        const frag = document.createDocumentFragment();
        for (let i = start; i <= end; i++) frag.appendChild(this._buildRow(panels[i]));
        rd.innerHTML = '';
        rd.appendChild(frag);
    },

    _buildRow(p) {
        const row = document.createElement('div');
        const isActive = p.id === _selectedPanelId;
        row.className  = 'panel-row' + (isActive ? ' active' : '');
        row.dataset.id = p.id;
        row.style.height     = ROW_H + 'px';
        row.style.boxSizing  = 'border-box';

        // Badge pige
        const pigeBadge = p.pige_total > 0
            ? `<span style="padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(34,197,94,.08);color:#22c55e;flex-shrink:0">📸 ${p.pige_verifie}/${p.pige_total}</span>`
            : `<span style="padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(249,115,22,.08);color:#f97316;flex-shrink:0">Pas de pige</span>`;

        // Badge pose
        const poseBadge = p.pose_status
            ? `<span style="padding:2px 7px;border-radius:8px;font-size:9px;background:rgba(34,197,94,.08);color:#22c55e;flex-shrink:0">
                ${p.pose_status === 'realisee' ? '✓ Posé' : '📅 Prévu'}
               </span>`
            : '';

        row.innerHTML = `
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);flex-shrink:0">${p.reference}</span>
                    <span style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
                </div>
                <div style="font-size:10px;color:var(--text3)">📍 ${p.commune}</div>
            </div>
            <div style="display:flex;gap:4px;align-items:center;flex-shrink:0">${poseBadge}${pigeBadge}</div>
        `;

        row.addEventListener('click', () => {
            _selectedPanelId = p.id;
            document.getElementById('panel-id-input').value = p.id;
            this._showCurrentPanel(p.reference, p.name, p.commune);
            this._syncActive();
            this._updateSubmitLabel();
            const fs = document.getElementById('footer-selected');
            if (fs) fs.textContent = `${p.reference} sélectionné ✓`;
        });

        return row;
    },

    _syncActive() {
        document.querySelectorAll('#panels-rendered .panel-row').forEach(r =>
            r.classList.toggle('active', parseInt(r.dataset.id) === _selectedPanelId)
        );
    },

    _updateSubmitLabel() {
        const n      = _files.length;
        const hasPanel = !!document.getElementById('panel-id-input').value;
        const btn    = document.getElementById('btn-label');
        const sum    = document.getElementById('submit-summary');

        if (n > 0 && hasPanel) {
            btn.textContent  = `Uploader ${n} photo${n > 1 ? 's' : ''}`;
            sum.style.display = 'inline';
            sum.textContent   = `→ ${n} photo${n > 1 ? 's' : ''} prête${n > 1 ? 's' : ''}`;
        } else if (n > 0) {
            btn.textContent  = `Uploader ${n} photo${n > 1 ? 's' : ''}`;
            sum.style.display = 'none';
        } else {
            btn.textContent  = 'Uploader les piges';
            sum.style.display = 'none';
        }
    },

    // ── Drag & Drop ───────────────────────────────────────────
    onDragOver(e) {
        e.preventDefault();
        document.getElementById('drop-zone').classList.add('dragging');
        document.getElementById('dz-idle').style.display = 'none';
        document.getElementById('dz-drag').style.display = 'block';
    },
    onDragLeave() {
        document.getElementById('drop-zone').classList.remove('dragging');
        document.getElementById('dz-idle').style.display = 'block';
        document.getElementById('dz-drag').style.display = 'none';
    },
    onDrop(e) { e.preventDefault(); this.onDragLeave(); this.onFileSelect(e.dataTransfer.files); },

    onFileSelect(fileList) {
        const MAX = 10, MAX_SZ = MAX_MB * 1024 * 1024;
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        const errs = [];

        Array.from(fileList).forEach(f => {
            if (_files.length >= MAX) { errs.push(`Maximum ${MAX} photos par upload.`); return; }
            if (!allowed.includes(f.type)) { errs.push(`${f.name} : format non supporté (JPG, PNG, WebP uniquement).`); return; }
            if (f.size > MAX_SZ) { errs.push(`${f.name} : trop volumineux (max ${MAX_MB} Mo, taille actuelle : ${(f.size/1024/1024).toFixed(1)} Mo).`); return; }
            // Déduplique par nom + taille
            if (_files.some(x => x.name === f.name && x.size === f.size)) { errs.push(`${f.name} : déjà sélectionné.`); return; }
            _files.push(f);
        });

        const errEl = document.getElementById('photo-error');
        if (errs.length) { errEl.innerHTML = errs.join('<br>'); errEl.style.display = 'block'; }
        else { errEl.style.display = 'none'; }

        this._renderPreviews();
        this._updateSubmitLabel();
    },

    _renderPreviews() {
        const grid = document.getElementById('preview-grid');
        const ctr  = document.getElementById('photo-counter');
        grid.style.display = _files.length ? 'grid' : 'none';
        ctr.style.display  = _files.length ? 'inline' : 'none';
        ctr.textContent    = `${_files.length} photo${_files.length > 1 ? 's' : ''} sélectionnée${_files.length > 1 ? 's' : ''}`;

        grid.innerHTML = '';
        _files.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'photo-prev';
            const url = URL.createObjectURL(f);
            div.innerHTML = `
                <img src="${url}" alt="" onload="URL.revokeObjectURL('${url}')">
                <div class="label">${f.name} · ${(f.size/1024/1024).toFixed(1)}Mo</div>
                <button type="button" class="rm" onclick="PIGE.rmPhoto(${i})">
                    <svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>`;
            grid.appendChild(div);
        });
    },

    rmPhoto(i) { _files.splice(i, 1); this._renderPreviews(); this._updateSubmitLabel(); },

    getGeo() {
        if (!navigator.geolocation) return;
        const btn = document.getElementById('btn-geo');
        btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Localisation…';
        btn.disabled  = true;
        navigator.geolocation.getCurrentPosition(
            pos => {
                document.querySelector('[name=gps_lat]').value = pos.coords.latitude.toFixed(7);
                document.querySelector('[name=gps_lng]').value = pos.coords.longitude.toFixed(7);
                btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> GPS obtenu ✓';
                btn.style.color       = '#22c55e';
                btn.style.borderColor = 'rgba(34,197,94,.3)';
                btn.disabled = false;
            },
            () => { btn.innerHTML = 'Position indisponible'; btn.disabled = false; }
        );
    },
};

// ══════════════════════════════════════════════════════════════
// SOUMISSION
// ══════════════════════════════════════════════════════════════
document.getElementById('pige-form').addEventListener('submit', function(e) {
    const panelId = document.getElementById('panel-id-input').value;
    const errEl   = document.getElementById('photo-error');

    if (!panelId) {
        e.preventDefault();
        errEl.textContent = 'Veuillez sélectionner un panneau avant d\'uploader les piges.';
        errEl.style.display = 'block';
        document.getElementById('panels-campaign-zone').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    if (_files.length === 0) {
        e.preventDefault();
        errEl.textContent = 'Veuillez sélectionner au moins une photo de pige.';
        errEl.style.display = 'block';
        document.getElementById('drop-zone').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    // Injecter fichiers dans l'input
    try {
        const dt = new DataTransfer();
        _files.forEach(f => dt.items.add(f));
        document.getElementById('photos-input').files = dt.files;
    } catch (e) { /* Safari fallback — les fichiers sont déjà dans l'input si sélectionnés via click */ }
});

// Init
if (D.preselectedCampaign) PIGE._loadPanels(D.preselectedCampaign.id);
if (D.preselectedPanel) PIGE._updateSubmitLabel();
window.addEventListener('resize', () => { if (_filteredPanels.length > 0) PIGE._renderVirtual(); });
})();
</script>
@endpush
</x-admin-layout>
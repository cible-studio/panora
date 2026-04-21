<x-admin-layout title="Nouvelle tâche de pose">

<x-slot:topbarActions>
    <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost btn-sm">← Retour</a>
</x-slot:topbarActions>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

<script>
window.__POSE__ = {
    searchCampaignsUrl: '{{ route("admin.pose-tasks.search-campaigns") }}',
    campaignPanelsUrl:  '{{ route("admin.pose-tasks.campaign-panels") }}',
    searchPanelsUrl:    '{{ route("admin.pose-tasks.search-panels") }}',
    csrf: '{{ csrf_token() }}',
    preselectedCampaign: @if($preselectedCampaign) {
        id: {{ $preselectedCampaign->id }},
        name: @json($preselectedCampaign->name),
        status: @json($preselectedCampaign->status->value),
        label: @json($preselectedCampaign->status->label()),
        icon: @json($preselectedCampaign->status->uiConfig()['icon']),
        color: @json($preselectedCampaign->status->uiConfig()['color']),
        blocked: {{ $preselectedCampaign->status->isTerminal() ? 'true' : 'false' }},
        total_panels: {{ $preselectedCampaign->total_panels ?? 0 }},
        dates: '{{ $preselectedCampaign->start_date?->format("d/m/Y") }} → {{ $preselectedCampaign->end_date?->format("d/m/Y") }}',
    } @else null @endif,
};
</script>

<!-- afficher sur tout la page -->
<div style="max-width:900px;margin:0 auto 40px;padding:0 14px">

    @if($errors->any())
    <div class="err-box">
        <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:8px;display:flex;align-items:center;gap:8px">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Veuillez corriger les erreurs
        </div>
        <ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444;display:flex;flex-direction:column;gap:3px">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.pose-tasks.store') }}" id="pose-form">
        @csrf

        {{-- ══ S1 : CAMPAGNE ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">1</div>
                <div>
                    <div class="pose-section-title">Campagne</div>
                    <div class="pose-section-sub">Sélectionnez la campagne concernée par cette session de pose</div>
                </div>
            </div>

            <div id="campaign-field-wrap">
                <select id="sel-campaign" name="campaign_id" style="width:100%">
                    <option value="">Rechercher une campagne… (Actif, En pose)</option>
                    @if($preselectedCampaign)
                    <option value="{{ $preselectedCampaign->id }}" selected>{{ $preselectedCampaign->name }}</option>
                    @endif
                </select>
            </div>

            {{-- Info campagne --}}
            <div id="campaign-info" style="display:none;margin-top:10px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--surface2)">
                <div style="display:flex;align-items:center;gap:10px">
                    <span id="ci-icon" style="font-size:20px"></span>
                    <div style="flex:1;min-width:0">
                        <div id="ci-name" style="font-size:13px;font-weight:700;color:var(--text)"></div>
                        <div style="display:flex;gap:10px;margin-top:3px;flex-wrap:wrap">
                            <span id="ci-status" style="font-size:11px;font-weight:600"></span>
                            <span id="ci-dates" style="font-size:11px;color:var(--text3)"></span>
                            <span id="ci-panels" style="font-size:11px;color:var(--text3)"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="campaign-blocked-alert" style="display:none;margin-top:10px;padding:10px 14px;border-radius:10px;font-size:12px;font-weight:600;border:1px solid rgba(239,68,68,.3);background:rgba(239,68,68,.08);color:#ef4444"></div>

            {{-- Toggle sans campagne --}}
            <div class="no-camp-toggle" onclick="POSE.toggleNoCampaign()">
                <input type="checkbox" id="chk-no-campaign" style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer" onclick="event.stopPropagation()">
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text)">🔧 Pose sans campagne</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:1px">Intervention technique, maintenance, remplacement visuel hors campagne</div>
                </div>
            </div>
        </div>

        {{-- ══ S2 : PANNEAUX — avec virtualisation pour 50+ ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">2</div>
                <div style="flex:1;min-width:0">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                        <div class="pose-section-title">Panneaux à poser</div>
                        {{-- Compteur temps réel --}}
                        <div id="sel-counter" style="display:none;font-size:12px;font-weight:700;padding:3px 10px;border-radius:20px;background:rgba(232,160,32,.12);color:var(--accent);white-space:nowrap">
                            <span id="sel-count">0</span> sélectionné(s)
                        </div>
                    </div>
                    <div class="pose-section-sub">Sélectionnez les panneaux concernés · multi-sélection disponible</div>
                </div>
            </div>

            {{-- Message initial --}}
            <div id="panels-hint" class="panels-hint">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 10px;opacity:.3"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Sélectionnez une campagne pour voir ses panneaux<br>
                <span style="font-size:11px;opacity:.6">ou cochez "Pose sans campagne" pour un panneau unique</span>
            </div>

            {{-- Zone campagne : toolbar + liste virtualisée --}}
            <div id="panels-campaign-zone" style="display:none">

                {{-- Toolbar : recherche + stats + actions rapides --}}
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
                    <div style="position:relative;flex:1;min-width:160px">
                        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="panel-search-input" placeholder="Filtrer les panneaux par référence, nom, commune…"
                               style="width:100%;height:36px;padding:0 10px 0 30px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box;transition:border-color .2s"
                               oninput="POSE.filterPanels(this.value)"
                               onfocus="this.style.borderColor='var(--accent)'"
                               onblur="this.style.borderColor='var(--border)'">
                    </div>
                    <div id="panels-stats-text" style="font-size:11px;color:var(--text3);white-space:nowrap"></div>
                </div>

                {{-- Boutons sélection rapide --}}
                <div style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap">
                    <button type="button" onclick="POSE.selectAll()" class="qbtn">✓ Tout</button>
                    <button type="button" onclick="POSE.selectNonePosed()" class="qbtn qbtn-accent">⏳ Sans pose</button>
                    <button type="button" onclick="POSE.selectNone()" class="qbtn">✗ Aucun</button>
                    <div id="commune-filters" style="display:flex;gap:4px;flex-wrap:wrap;margin-left:4px"></div>
                </div>

                {{-- Liste virtualisée --}}
                <div id="panels-loader" style="display:none;text-align:center;padding:20px;color:var(--text3)">
                    <div style="width:20px;height:20px;border:2px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 8px"></div>
                    Chargement des panneaux…
                </div>

                {{-- Conteneur scroll avec hauteur fixe pour virtualisation --}}
                <div id="panels-scroll-container"
                     style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
                    <div id="panels-list-viewport"
                         style="max-height:380px;overflow-y:auto;position:relative"
                         onscroll="POSE.onScroll()">
                        <div id="panels-list-spacer" style="position:relative">
                            <div id="panels-list-rendered"></div>
                        </div>
                    </div>
                    {{-- Footer stats --}}
                    <div id="panels-list-footer" style="display:none;padding:8px 14px;border-top:1px solid var(--border);background:var(--surface2);font-size:11px;color:var(--text3);display:flex;justify-content:space-between">
                        <span id="footer-visible"></span>
                        <span id="footer-selected" style="color:var(--accent);font-weight:600"></span>
                    </div>
                </div>
            </div>

            {{-- Zone panneau libre --}}
            <div id="panels-free-zone" style="display:none">
                <select id="sel-panel-free" style="width:100%">
                    <option value="">Rechercher un panneau par référence ou nom…</option>
                </select>
                <input type="hidden" name="panel_ids[]" id="panel-free-hidden" value="">
                <div style="font-size:11px;color:var(--text3);margin-top:6px">💡 Tapez au moins 2 caractères pour rechercher.</div>
            </div>
        </div>

        {{-- ══ S3 : ÉQUIPE & PLANNING ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">3</div>
                <div><div class="pose-section-title">Équipe & Planning</div><div class="pose-section-sub">Assignation et date de la pose terrain</div></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <label class="pose-label">Technicien assigné</label>
                    <select name="assigned_user_id" class="pose-select">
                        <option value="">— Non assigné —</option>
                        @foreach($techniciens as $t)
                        <option value="{{ $t->id }}" {{ old('assigned_user_id')==$t->id?'selected':'' }}>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="pose-label">Nom d'équipe <span style="font-weight:400;color:var(--text3)">(opt.)</span></label>
                    <input type="text" name="team_name" value="{{ old('team_name') }}" class="pose-input" placeholder="Ex: Équipe B, Koffi & Fils">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="pose-label">Date & heure planifiée *</label>
                    <input type="datetime-local" name="scheduled_at" required class="pose-input"
                           value="{{ old('scheduled_at', now()->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label class="pose-label">Statut initial</label>
                    <select name="status" class="pose-select">
                        <option value="planifiee" {{ old('status','planifiee')==='planifiee'?'selected':'' }}>📅 Planifiée</option>
                        <option value="en_cours"  {{ old('status')==='en_cours'?'selected':'' }}>🔧 En cours (immédiat)</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ══ S4 : NOTES ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">4</div>
                <div><div class="pose-section-title">Notes <span style="font-weight:400;color:var(--text3);font-size:11px">(optionnel)</span></div></div>
            </div>
            <textarea name="notes" class="pose-input" style="height:auto;resize:none;padding:10px 12px" rows="2"
                      placeholder="Instructions terrain, matériel nécessaire, accès particulier, contacts…">{{ old('notes') }}</textarea>
        </div>

        {{-- ══ ACTIONS ══ --}}
        <div style="display:flex;gap:10px;align-items:center">
            <button type="submit" id="btn-submit" class="btn btn-primary" style="min-width:220px;display:flex;align-items:center;justify-content:center;gap:8px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                <span id="btn-submit-label">Créer la tâche de pose</span>
            </button>
            <a href="{{ route('admin.pose-tasks.index') }}" class="btn btn-ghost">Annuler</a>
            {{-- Récapitulatif sélection visible avant submit --}}
            <div id="submit-summary" style="display:none;font-size:12px;color:var(--text3)"></div>
        </div>
    </form>
</div>

{{-- ════ STYLES ════ --}}
<style>
@keyframes spin   { to { transform:rotate(360deg) } }
@keyframes fadeIn { from { opacity:0;transform:translateY(-4px) } to { opacity:1;transform:none } }

.err-box { background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:18px }

.pose-section        { background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:14px }
.pose-section-header { display:flex;align-items:flex-start;gap:12px;margin-bottom:16px }
.pose-section-title  { font-size:14px;font-weight:700;color:var(--text) }
.pose-section-sub    { font-size:11px;color:var(--text3);margin-top:2px }
.pose-step           { width:26px;height:26px;background:var(--accent);color:#000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;margin-top:1px }
.pose-label          { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:5px }
.pose-input          { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);transition:border-color .2s;box-sizing:border-box;outline:none }
.pose-input:focus    { border-color:var(--accent) }
.pose-select         { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);cursor:pointer;outline:none }

.no-camp-toggle { margin-top:12px;padding:10px 14px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;display:flex;align-items:center;gap:10px;cursor:pointer;transition:border-color .15s }
.no-camp-toggle:hover { border-color:var(--accent) }

.panels-hint { text-align:center;padding:28px;color:var(--text3);font-size:13px;border:1.5px dashed var(--border);border-radius:10px }

/* Quick buttons */
.qbtn { font-size:11px;color:var(--text3);background:none;border:1px solid var(--border);border-radius:7px;padding:4px 10px;cursor:pointer;transition:all .12s }
.qbtn:hover { border-color:var(--accent);color:var(--text) }
.qbtn-accent { color:var(--accent);background:rgba(232,160,32,.08);border-color:rgba(232,160,32,.3) }

/* Commune filter pills */
.commune-pill { font-size:10px;color:var(--text3);background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:2px 9px;cursor:pointer;transition:all .12s;white-space:nowrap }
.commune-pill:hover, .commune-pill.active { background:rgba(232,160,32,.1);border-color:rgba(232,160,32,.4);color:var(--accent) }

/* Rows panneaux */
.panel-row { display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;user-select:none }
.panel-row:last-child { border-bottom:none }
.panel-row:hover { background:rgba(232,160,32,.04) }
.panel-row.selected { background:rgba(232,160,32,.07) }
.panel-row.hidden { display:none }
.panel-row input[type=checkbox] { accent-color:var(--accent);width:15px;height:15px;flex-shrink:0;cursor:pointer }

/* Select2 */
.select2-container--default .select2-selection--single { height:40px!important;border-radius:10px!important;border:1px solid var(--border)!important;background:var(--surface2)!important;display:flex;align-items:center }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px!important;color:var(--text)!important;padding-left:12px!important;font-size:13px }
.select2-container--default .select2-selection--single .select2-selection__arrow { height:38px!important;right:6px!important }
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single { border-color:var(--accent)!important;outline:none!important;box-shadow:none!important }
.select2-dropdown { background:var(--surface)!important;border:1px solid var(--border)!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.25)!important;overflow:hidden;animation:fadeIn .15s ease }
.select2-container--default .select2-search--dropdown .select2-search__field { background:var(--surface2)!important;border:1px solid var(--border)!important;border-radius:8px!important;color:var(--text)!important;padding:6px 10px!important;font-size:13px;outline:none;margin:8px!important;width:calc(100% - 16px)!important;box-sizing:border-box }
.select2-container--default .select2-search--dropdown .select2-search__field:focus { border-color:var(--accent)!important }
.select2-results__option { padding:0!important;font-size:13px;color:var(--text);transition:background .1s }
.select2-results__option--highlighted { background:rgba(232,160,32,.08)!important;color:var(--text)!important }
.select2-results__option[aria-selected=true] { background:rgba(232,160,32,.12)!important;color:var(--text)!important }
.select2-container { width:100%!important }
.select2-results__options { max-height:280px;overflow-y:auto }
.select2-results__message { color:var(--text3)!important;font-size:12px;padding:12px!important;text-align:center }
.s2-opt { display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border) }
.s2-opt:last-child { border-bottom:none }
.s2-opt-ref { font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);flex-shrink:0 }
.s2-opt.blocked { opacity:.4 }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

@push('scripts')
<script>
(function(){
'use strict';
const D = window.__POSE__;

// ── État global ───────────────────────────────────────────────
let _allPanels      = [];   // tableau complet chargé depuis AJAX
let _filteredPanels = [];   // tableau après filtre texte + commune
let _selectedIds    = new Set();  // IDs cochés
let _noCampaign     = false;
let _currentCampId  = null;
let _activeCommune  = null; // filtre commune actif

// ── Virtualisation ─────────────────────────────────────────────
const ROW_HEIGHT    = 52;   // hauteur estimée d'une row en px
const OVERSCAN      = 5;    // rows supplémentaires au-dessus/dessous
let _visibleStart   = 0;
let _visibleEnd     = 0;

// ══════════════════════════════════════════════════════════════
// SELECT2 CAMPAGNE
// ══════════════════════════════════════════════════════════════
function fmtCamp(c) {
    if (!c.id) return $(`<span style="color:var(--text3)">${c.text}</span>`);
    const blocked = c.blocked ? ' blocked' : '';
    return $(`<div class="s2-opt${blocked}">
        <span style="font-size:18px;flex-shrink:0">${c.icon||''}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.text}</div>
            <div style="font-size:10px;color:${c.color||'var(--text3)'};">${[c.label,c.dates,c.total_panels?c.total_panels+' panneaux':''].filter(Boolean).join(' · ')}</div>
        </div>
        ${c.blocked?'<span style="font-size:10px;color:#ef4444">Bloquée</span>':''}
    </div>`);
}

$('#sel-campaign').select2({
    placeholder:'Rechercher une campagne…',
    allowClear:true, minimumInputLength:0,
    language:{ searching:()=>'Recherche…', noResults:()=>'Aucune campagne trouvée' },
    ajax:{
        url:D.searchCampaignsUrl, dataType:'json', delay:250,
        headers:{'X-CSRF-TOKEN':D.csrf,Accept:'application/json'},
        data:params=>({q:params.term||'',status:'actif,pose'}),
        processResults:data=>({results:data.map(c=>({id:c.id,text:c.name,...c}))}),
        cache:true,
    },
    templateResult:fmtCamp,
    templateSelection:c=>c.id?`${c.icon||''} ${c.text}`:c.text,
    dropdownParent:$('#campaign-field-wrap'), width:'100%',
});

$('#sel-campaign').on('change', function(){
    const d = $(this).select2('data')[0];
    if (!d?.id) POSE.onCampCleared(); else POSE.onCampSelected(d);
});

// Pré-sélection URL
if (D.preselectedCampaign && !D.preselectedCampaign.blocked) {
    const pc = D.preselectedCampaign;
    $('#sel-campaign').append(new Option(pc.name, pc.id, true, true)).trigger('change');
}

// ══════════════════════════════════════════════════════════════
// SELECT2 PANNEAU LIBRE
// ══════════════════════════════════════════════════════════════
function fmtPanel(p) {
    if (!p.id) return $(`<span style="color:var(--text3)">${p.text}</span>`);
    return $(`<div class="s2-opt">
        <span class="s2-opt-ref">${p.reference||''}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.text}</div>
            <div style="font-size:10px;color:var(--text3)">📍 ${p.commune||'—'}</div>
        </div>
    </div>`);
}

$('#sel-panel-free').select2({
    placeholder:'Rechercher un panneau…',
    allowClear:true, minimumInputLength:2,
    language:{inputTooShort:()=>'Tapez au moins 2 caractères',searching:()=>'Recherche…',noResults:()=>'Aucun panneau trouvé'},
    ajax:{
        url:D.searchPanelsUrl, dataType:'json', delay:250,
        headers:{'X-CSRF-TOKEN':D.csrf,Accept:'application/json'},
        data:params=>({q:params.term}),
        processResults:data=>({results:data.map(p=>({id:p.id,text:p.name,...p}))}),
        cache:true,
    },
    templateResult:fmtPanel,
    templateSelection:p=>p.id?`${p.reference||''} · ${p.text}`:p.text,
    dropdownParent:$('#panels-free-zone'), width:'100%',
});

$('#sel-panel-free').on('change', function(){
    const v = $(this).val();
    document.getElementById('panel-free-hidden').value = v || '';
    POSE._updateSubmitLabel();
});

// ══════════════════════════════════════════════════════════════
// LOGIQUE PRINCIPALE
// ══════════════════════════════════════════════════════════════
window.POSE = {

    onCampSelected(camp) {
        _currentCampId = camp.id; _noCampaign = false;
        document.getElementById('chk-no-campaign').checked = false;
        $('#sel-campaign').prop('disabled',false).css('opacity','');

        // Afficher infos campagne
        const inf = document.getElementById('campaign-info');
        document.getElementById('ci-icon').textContent   = camp.icon||'';
        document.getElementById('ci-name').textContent   = camp.text||'';
        document.getElementById('ci-status').textContent = camp.label||'';
        document.getElementById('ci-status').style.color = camp.color||'var(--text3)';
        document.getElementById('ci-dates').textContent  = camp.dates||'';
        document.getElementById('ci-panels').textContent = camp.total_panels?camp.total_panels+' panneaux':'';
        inf.style.display = 'flex';

        const blocked = document.getElementById('campaign-blocked-alert');
        if (camp.blocked) {
            blocked.style.display = 'block';
            blocked.textContent = `Campagne ${camp.label} — création de poses impossible.`;
            this._hide(); document.getElementById('panels-hint').style.display='block';
            document.getElementById('btn-submit').disabled=true; return;
        }
        blocked.style.display = 'none';
        document.getElementById('btn-submit').disabled = false;
        this._hide();
        document.getElementById('panels-campaign-zone').style.display = 'block';
        this._loadPanels(camp.id);
    },

    onCampCleared() {
        _currentCampId = null;
        document.getElementById('campaign-info').style.display='none';
        document.getElementById('campaign-blocked-alert').style.display='none';
        document.getElementById('btn-submit').disabled=false;
        this._hide();
        if (!_noCampaign) document.getElementById('panels-hint').style.display='block';
    },

    toggleNoCampaign() {
        const chk = document.getElementById('chk-no-campaign');
        chk.checked = !chk.checked; _noCampaign = chk.checked;
        if (chk.checked) {
            $('#sel-campaign').val(null).trigger('change');
            document.getElementById('campaign-info').style.display='none';
            $('#sel-campaign').prop('disabled',true).css('opacity','.4');
            _currentCampId=null; _allPanels=[]; _selectedIds.clear();
            this._hide();
            document.getElementById('panels-free-zone').style.display='block';
        } else {
            $('#sel-campaign').prop('disabled',false).css('opacity','');
            this._hide();
            document.getElementById('panels-hint').style.display='block';
            document.getElementById('panel-free-hidden').value='';
            $('#sel-panel-free').val(null).trigger('change');
        }
        this._updateSubmitLabel();
    },

    _hide() {
        ['panels-hint','panels-campaign-zone','panels-free-zone'].forEach(id=>{
            document.getElementById(id).style.display='none';
        });
    },

    // ── Chargement panneaux campagne (AJAX) ───────────────────
    async _loadPanels(campaignId) {
        _allPanels=[]; _filteredPanels=[]; _selectedIds.clear(); _activeCommune=null;
        document.getElementById('panels-loader').style.display='block';
        document.getElementById('panels-list-rendered').innerHTML='';
        document.getElementById('commune-filters').innerHTML='';
        document.getElementById('panels-stats-text').textContent='';
        document.getElementById('panels-list-footer').style.display='none';
        document.getElementById('sel-counter').style.display='none';
        document.getElementById('panel-search-input').value='';

        try {
            const res = await fetch(`${D.campaignPanelsUrl}?campaign_id=${campaignId}`,
                {headers:{Accept:'application/json','X-CSRF-TOKEN':D.csrf}});
            if (!res.ok) throw new Error();
            const data = await res.json();
            _allPanels = data.panels;

            // Pré-sélectionner panneaux sans pose
            _allPanels.forEach(p => { if (!p.has_task) _selectedIds.add(p.id); });

            this._buildCommuneFilters();
            this._applyFilter();
            this._updateStats(data.stats);
            this._renderVirtual();

        } catch {
            document.getElementById('panels-list-rendered').innerHTML =
                `<div style="padding:20px;text-align:center;color:#ef4444;font-size:12px">
                    Erreur de chargement.
                    <button type="button" onclick="POSE._loadPanels(${campaignId})"
                            style="background:none;border:none;color:#ef4444;text-decoration:underline;cursor:pointer">
                        Réessayer
                    </button>
                </div>`;
        } finally {
            document.getElementById('panels-loader').style.display='none';
        }
    },

    // ── Filtres commune ───────────────────────────────────────
    _buildCommuneFilters() {
        const communes = [...new Set(_allPanels.map(p=>p.commune).filter(Boolean))].sort();
        const wrap = document.getElementById('commune-filters');
        if (communes.length <= 1) { wrap.innerHTML=''; return; }

        wrap.innerHTML = communes.map(c =>
            `<button type="button" class="commune-pill" data-commune="${c}" onclick="POSE.filterCommune('${c.replace(/'/g,"\\'")}')">${c}</button>`
        ).join('');
    },

    filterCommune(commune) {
        _activeCommune = _activeCommune === commune ? null : commune;
        document.querySelectorAll('.commune-pill').forEach(p => {
            p.classList.toggle('active', p.dataset.commune === _activeCommune);
        });
        this._applyFilter();
        this._renderVirtual();
    },

    // ── Filtre texte (debounce) ───────────────────────────────
    _filterTimer: null,
    filterPanels(q) {
        clearTimeout(this._filterTimer);
        this._filterTimer = setTimeout(() => {
            this._applyFilter(q);
            this._renderVirtual();
        }, 80); // très réactif car tout est en mémoire
    },

    _applyFilter(q = document.getElementById('panel-search-input')?.value || '') {
        const lq = q.trim().toLowerCase();
        _filteredPanels = _allPanels.filter(p => {
            const matchText = !lq ||
                p.reference.toLowerCase().includes(lq) ||
                p.name.toLowerCase().includes(lq) ||
                (p.commune||'').toLowerCase().includes(lq);
            const matchCommune = !_activeCommune || p.commune === _activeCommune;
            return matchText && matchCommune;
        });

        const vis = _filteredPanels.length;
        const tot = _allPanels.length;
        const footVis = document.getElementById('footer-visible');
        if (footVis) footVis.textContent = vis < tot ? `${vis} affiché(s) sur ${tot}` : `${tot} panneau(x)`;
        document.getElementById('panels-list-footer').style.display = 'flex';
    },

    _updateStats(stats) {
        document.getElementById('panels-stats-text').innerHTML =
            `<span style="color:#f97316;font-weight:700">${stats.sans_pose} à poser</span>` +
            ` · <span style="color:#e8a020">${stats.avec_pose} planifiée(s)</span>` +
            ` · <span style="color:#22c55e">${stats.avec_pige} pigée(s)</span>`;
    },

    // ── Rendu virtualisé ──────────────────────────────────────
    // Pour 50+ panneaux : ne rendu que les rows visibles + overscan
    onScroll() {
        this._renderVirtual();
    },

    _renderVirtual() {
        const panels   = _filteredPanels;
        const count    = panels.length;
        const viewport = document.getElementById('panels-list-viewport');
        const spacer   = document.getElementById('panels-list-spacer');
        const rendered = document.getElementById('panels-list-rendered');

        if (count === 0) {
            spacer.style.height = '0';
            rendered.innerHTML = `<div style="padding:20px;text-align:center;color:var(--text3);font-size:12px">Aucun panneau correspond à votre recherche.</div>`;
            return;
        }

        const totalH    = count * ROW_HEIGHT;
        const scrollTop = viewport.scrollTop;
        const viewH     = viewport.clientHeight || 380;

        const start = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - OVERSCAN);
        const end   = Math.min(count - 1, Math.ceil((scrollTop + viewH) / ROW_HEIGHT) + OVERSCAN);

        // Si même plage → skip
        if (start === _visibleStart && end === _visibleEnd && rendered.children.length > 0) {
            this._syncCheckboxes(); return;
        }
        _visibleStart = start; _visibleEnd = end;

        spacer.style.height  = totalH + 'px';
        spacer.style.position= 'relative';

        // Positionner le rendered au bon offset
        rendered.style.position   = 'absolute';
        rendered.style.top        = (start * ROW_HEIGHT) + 'px';
        rendered.style.left       = '0';
        rendered.style.right      = '0';

        const frag = document.createDocumentFragment();
        for (let i = start; i <= end; i++) {
            const p = panels[i];
            frag.appendChild(this._buildRow(p));
        }
        rendered.innerHTML = '';
        rendered.appendChild(frag);

        this._updateCounter();
    },

    _buildRow(p) {
        const row = document.createElement('div');
        row.className = 'panel-row' + (_selectedIds.has(p.id) ? ' selected' : '');
        row.dataset.id = p.id;
        row.style.height = ROW_HEIGHT + 'px';
        row.style.boxSizing = 'border-box';

        // Badge pose
        let poseBadge = '';
        if (p.has_task) {
            const cm = {planifiee:{c:'#e8a020',i:'📅'},en_cours:{c:'#3b82f6',i:'🔧'},realisee:{c:'#22c55e',i:'✅'}};
            const cfg = cm[p.task_status]||{c:'#6b7280',i:'❓'};
            poseBadge = `<span style="padding:2px 7px;border-radius:10px;font-size:9px;font-weight:700;background:${cfg.c}18;color:${cfg.c};white-space:nowrap;flex-shrink:0">${cfg.i} ${p.task_date||p.task_status}</span>`;
        } else {
            poseBadge = `<span style="padding:2px 7px;border-radius:10px;font-size:9px;font-weight:700;background:rgba(249,115,22,.08);color:#f97316;white-space:nowrap;flex-shrink:0">⏳ À poser</span>`;
        }
        const pigeBadge = p.has_pige
            ? `<span style="padding:2px 6px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(34,197,94,.08);color:#22c55e;flex-shrink:0">📸</span>` : '';

        row.innerHTML = `
            <input type="checkbox" value="${p.id}" ${_selectedIds.has(p.id)?'checked':''} style="accent-color:var(--accent);width:15px;height:15px;flex-shrink:0;cursor:pointer">
            <div style="flex:1;min-width:0;cursor:pointer">
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);flex-shrink:0">${p.reference}</span>
                    <span style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
                </div>
                <div style="font-size:10px;color:var(--text3)">📍 ${p.commune}</div>
            </div>
            <div style="display:flex;gap:4px;align-items:center;flex-shrink:0">${pigeBadge}${poseBadge}</div>
        `;

        const chk = row.querySelector('input[type=checkbox]');
        row.addEventListener('click', e => {
            if (e.target.tagName !== 'INPUT') { chk.checked = !chk.checked; }
            if (chk.checked) { _selectedIds.add(p.id); row.classList.add('selected'); }
            else { _selectedIds.delete(p.id); row.classList.remove('selected'); }
            this._updateCounter();
            this._updateSubmitLabel();
        });
        chk.addEventListener('change', () => {
            if (chk.checked) { _selectedIds.add(p.id); row.classList.add('selected'); }
            else { _selectedIds.delete(p.id); row.classList.remove('selected'); }
            this._updateCounter();
            this._updateSubmitLabel();
        });

        return row;
    },

    _syncCheckboxes() {
        document.querySelectorAll('#panels-list-rendered .panel-row').forEach(row => {
            const id = parseInt(row.dataset.id);
            const chk = row.querySelector('input[type=checkbox]');
            if (!chk) return;
            chk.checked = _selectedIds.has(id);
            row.classList.toggle('selected', _selectedIds.has(id));
        });
    },

    _updateCounter() {
        const n = _selectedIds.size;
        const counter = document.getElementById('sel-counter');
        const footSel = document.getElementById('footer-selected');
        document.getElementById('sel-count').textContent = n;
        counter.style.display = n > 0 ? 'inline-block' : 'none';
        if (footSel) footSel.textContent = n > 0 ? `${n} sélectionné(s)` : '';
    },

    _updateSubmitLabel() {
        const btn = document.getElementById('btn-submit-label');
        const sum = document.getElementById('submit-summary');
        if (_noCampaign) {
            const fv = document.getElementById('panel-free-hidden')?.value;
            btn.textContent = fv ? 'Créer la tâche de pose' : 'Créer la tâche de pose';
            sum.style.display = 'none';
        } else if (_selectedIds.size > 0) {
            const n = _selectedIds.size;
            btn.textContent = `Créer ${n} tâche${n>1?'s':''} de pose`;
            sum.style.display = 'inline';
            sum.textContent = `→ ${n} panneau${n>1?'x':''} sélectionné${n>1?'s':''}`;
        } else {
            btn.textContent = 'Créer la tâche de pose';
            sum.style.display = 'none';
        }
    },

    // ── Sélections rapides ────────────────────────────────────
    selectAll() {
        _filteredPanels.forEach(p => _selectedIds.add(p.id));
        this._renderVirtual(); this._updateCounter(); this._updateSubmitLabel();
    },
    selectNonePosed() {
        _selectedIds.clear();
        _filteredPanels.filter(p => !p.has_task).forEach(p => _selectedIds.add(p.id));
        this._renderVirtual(); this._updateCounter(); this._updateSubmitLabel();
    },
    selectNone() {
        _filteredPanels.forEach(p => _selectedIds.delete(p.id));
        this._renderVirtual(); this._updateCounter(); this._updateSubmitLabel();
    },
};

// ══════════════════════════════════════════════════════════════
// SOUMISSION — construire les hidden inputs depuis _selectedIds
// ══════════════════════════════════════════════════════════════
document.getElementById('pose-form').addEventListener('submit', function(e) {
    if (_noCampaign) {
        // Vérifier panneau libre
        const fv = document.getElementById('panel-free-hidden')?.value;
        if (!fv) {
            e.preventDefault();
            document.querySelector('.err-box') || document.getElementById('pose-form').insertAdjacentHTML('afterbegin',
                '<div class="err-box"><ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444"><li>Veuillez sélectionner un panneau.</li></ul></div>');
            document.getElementById('panels-free-zone').scrollIntoView({behavior:'smooth',block:'center'});
            return;
        }
        // Désactiver le hidden vide si présent
        const ph = document.getElementById('panel-free-hidden');
        if (ph && !ph.value) ph.disabled = true;
        return;
    }

    // Mode campagne : injecter les panel_ids depuis _selectedIds
    if (_selectedIds.size === 0) {
        e.preventDefault();
        const existing = document.querySelector('.err-box');
        if (!existing) {
            document.getElementById('pose-form').insertAdjacentHTML('afterbegin',
                '<div class="err-box"><ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444"><li>Veuillez sélectionner au moins un panneau.</li></ul></div>');
        }
        document.getElementById('panels-campaign-zone').scrollIntoView({behavior:'smooth',block:'center'});
        return;
    }

    // Supprimer les checkboxes DOM (qui peuvent être partiels vu la virtualisation)
    document.querySelectorAll('#panels-list-rendered input[type=checkbox]').forEach(c => c.disabled=true);
    document.getElementById('panel-free-hidden').disabled = true;

    // Injecter les vrais panel_ids depuis le Set (source de vérité)
    const form = document.getElementById('pose-form');
    _selectedIds.forEach(id => {
        const inp = document.createElement('input');
        inp.type='hidden'; inp.name='panel_ids[]'; inp.value=id;
        form.appendChild(inp);
    });
});

// Resize handler pour recalcul virtualisation
window.addEventListener('resize', () => { if (_filteredPanels.length > 0) POSE._renderVirtual(); });

})();
</script>
@endpush
</x-admin-layout>
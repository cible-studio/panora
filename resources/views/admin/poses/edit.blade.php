<x-admin-layout title="Modifier tâche — {{ $poseTask->panel?->reference }}">

<x-slot:topbarLeft>
    <a href="{{ route('admin.pose-tasks.show', $poseTask) }}" class="btn btn-ghost btn-sm">← Retour</a>
</x-slot:topbarLeft>

<x-slot:topbarActions>
    @if(!in_array($poseTask->status, ['realisee','annulee']))
    <form method="POST" action="{{ route('admin.pose.complete', $poseTask) }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Marquer cette tâche comme réalisée ?')">✅ Marquer réalisée</button>
    </form>
    @endif
</x-slot:topbarActions>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

<script>
window.__EDIT__ = {
    searchCampaignsUrl:'{{ route("admin.pose-tasks.search-campaigns") }}',
    campaignPanelsUrl: '{{ route("admin.pose-tasks.campaign-panels") }}',
    searchPanelsUrl:   '{{ route("admin.pose-tasks.search-panels") }}',
    csrf: '{{ csrf_token() }}',
    current: {
        panel_id:      {{ $poseTask->panel_id }},
        panel_ref:     @json($poseTask->panel?->reference),
        panel_name:    @json($poseTask->panel?->name),
        panel_commune: @json($poseTask->panel?->commune?->name ?? '—'),
        campaign_id:   {{ $poseTask->campaign_id ?? 'null' }},
        campaign_name: @json($poseTask->campaign?->name),
        campaign_label:@json($poseTask->campaign?->status?->label()),
        campaign_icon: @json($poseTask->campaign?->status?->uiConfig()['icon'] ?? ''),
        campaign_color:@json($poseTask->campaign?->status?->uiConfig()['color'] ?? '#6b7280'),
        no_campaign:   {{ $poseTask->campaign_id ? 'false' : 'true' }},
    },
};
</script>

<div style="max-width:900px;margin:0 auto 40px;padding:0 14px">

    {{-- Bannière tâche --}}
    <div style="background:rgba(232,160,32,.06);border:1px solid rgba(232,160,32,.2);border-radius:12px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:12px">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4z"/></svg>
        <div>
            <div style="font-size:13px;font-weight:700;color:var(--text)">Modification — tâche #{{ $poseTask->id }}</div>
            <div style="font-size:11px;color:var(--text3);margin-top:1px">
                Panneau : <span style="font-family:monospace;color:var(--accent);font-weight:700">{{ $poseTask->panel?->reference }}</span>
                · Campagne : <span style="color:var(--text2)">{{ $poseTask->campaign?->name ?? 'Aucune' }}</span>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="err-box">
        <div style="font-size:13px;font-weight:700;color:#ef4444;margin-bottom:8px">⚠️ Erreurs</div>
        <ul style="margin:0;padding-left:18px;font-size:12px;color:#ef4444;display:flex;flex-direction:column;gap:3px">
            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.pose-tasks.update', $poseTask) }}">
        @csrf @method('PUT')

        {{-- ══ S1 : CAMPAGNE ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">1</div>
                <div><div class="pose-section-title">Campagne</div><div class="pose-section-sub">Modifiez la campagne associée si nécessaire</div></div>
            </div>

            <div id="campaign-field-wrap">
                <select id="sel-campaign" name="campaign_id" style="width:100%">
                    <option value="">— Aucune campagne (intervention technique) —</option>
                    @if($poseTask->campaign)
                    <option value="{{ $poseTask->campaign_id }}" selected>{{ $poseTask->campaign->name }}</option>
                    @endif
                </select>
            </div>

            <div id="campaign-info" style="display:{{ $poseTask->campaign_id ? 'flex' : 'none' }};align-items:center;gap:10px;margin-top:10px;padding:10px 14px;border-radius:10px;border:1px solid var(--border);background:var(--surface2)">
                <span id="ci-icon" style="font-size:20px">{{ $poseTask->campaign?->status?->uiConfig()['icon'] ?? '' }}</span>
                <div style="flex:1;min-width:0">
                    <div id="ci-name" style="font-size:13px;font-weight:700;color:var(--text)">{{ $poseTask->campaign?->name }}</div>
                    <span id="ci-status" style="font-size:11px;font-weight:600;color:{{ $poseTask->campaign?->status?->uiConfig()['color'] ?? 'var(--text3)' }}">{{ $poseTask->campaign?->status?->label() }}</span>
                </div>
            </div>

            <div class="no-camp-toggle" onclick="EDIT.toggleNoCampaign()">
                <input type="checkbox" id="chk-no-campaign" style="accent-color:var(--accent);width:16px;height:16px;cursor:pointer"
                       onclick="event.stopPropagation()" {{ !$poseTask->campaign_id ? 'checked' : '' }}>
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text)">🔧 Pose sans campagne</div>
                    <div style="font-size:11px;color:var(--text3);margin-top:1px">Intervention technique, maintenance</div>
                </div>
            </div>
        </div>

        {{-- ══ S2 : PANNEAU ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">2</div>
                <div style="flex:1">
                    <div style="display:flex;align-items:center;justify-content:space-between">
                        <div class="pose-section-title">Panneau</div>
                        <div id="panels-stats-text" style="font-size:11px;color:var(--text3)"></div>
                    </div>
                    <div class="pose-section-sub">Panneau concerné — verrouillé en édition</div>
                </div>
            </div>

            {{-- Panneau actuel (toujours visible) --}}
            <div id="panel-current" style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--surface2);border:1.5px solid var(--accent);border-radius:10px;margin-bottom:12px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                <span id="cur-ref" style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">{{ $poseTask->panel?->reference }}</span>
                <div style="flex:1;min-width:0">
                    <div id="cur-name" style="font-size:12px;color:var(--text);font-weight:500">{{ $poseTask->panel?->name }}</div>
                    <div id="cur-commune" style="font-size:10px;color:var(--text3)">📍 {{ $poseTask->panel?->commune?->name ?? '—' }}</div>
                </div>
                <span style="font-size:10px;padding:2px 8px;border-radius:8px;background:rgba(232,160,32,.12);color:var(--accent);font-weight:700;white-space:nowrap">Sélectionné ✓</span>
            </div>

            <input type="hidden" name="panel_id" id="edit-panel-id" value="{{ $poseTask->panel_id }}">

            {{-- Note verrou — le panneau d'une pose ne peut pas être modifié
                 après création (le changer = créer une autre intervention).
                 Pour basculer sur un autre panneau, annuler cette pose et en
                 créer une nouvelle. --}}
            <div style="display:flex;align-items:flex-start;gap:8px;padding:10px 12px;border-radius:10px;border:1px solid var(--border);background:var(--surface2);font-size:12px;color:var(--text2);margin-bottom:12px">
                🔒 <div>Le panneau d'une pose <strong>ne peut pas être changé</strong> après création. Pour intervenir sur un autre panneau, annule cette pose et crée-en une nouvelle.</div>
            </div>

            {{-- Liste panneaux campagne avec recherche (masquée en édition :
                 le panneau est verrouillé, seul le current ci-dessus s'affiche). --}}
            <div id="panels-campaign-zone" style="display:none">
                <div style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
                    <div style="position:relative;flex:1">
                        <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);pointer-events:none" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                        <input type="text" id="edit-panel-search" placeholder="Filtrer les panneaux…"
                               style="width:100%;height:34px;padding:0 10px 0 30px;background:var(--surface2);border:1px solid var(--border);border-radius:8px;font-size:12px;color:var(--text);outline:none;box-sizing:border-box"
                               oninput="EDIT.filterPanels(this.value)"
                               onfocus="this.style.borderColor='var(--accent)'"
                               onblur="this.style.borderColor='var(--border)'">
                    </div>
                    <div id="commune-filters-edit" style="display:flex;gap:4px;flex-wrap:wrap"></div>
                </div>

                <div id="edit-panels-loader" style="display:none;text-align:center;padding:16px;color:var(--text3)">
                    <div style="width:16px;height:16px;border:2px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 6px"></div>
                    Chargement…
                </div>

                {{-- Vue scroll avec virtualisation légère --}}
                <div style="border:1px solid var(--border);border-radius:10px;overflow:hidden">
                    <div id="edit-panels-viewport" style="max-height:280px;overflow-y:auto" onscroll="EDIT.onScroll()">
                        <div id="edit-panels-spacer" style="position:relative">
                            <div id="edit-panels-rendered" style="position:absolute;left:0;right:0;top:0"></div>
                        </div>
                    </div>
                </div>
                <div style="padding:6px 14px;border-top:1px solid var(--border);background:var(--surface2);font-size:11px;color:var(--text3);display:flex;justify-content:space-between;border-radius:0 0 10px 10px">
                    <span id="edit-footer-visible"></span>
                    <span id="edit-footer-info" style="color:var(--text3)">Cliquez sur un panneau pour le sélectionner</span>
                </div>
            </div>

            {{-- Panneau libre (sans campagne) — masqué en édition aussi. --}}
            <div id="panels-free-zone" style="display:none">
                <select id="sel-panel-free" style="width:100%">
                    <option value="">Rechercher un panneau par référence ou nom…</option>
                    @if(!$poseTask->campaign_id && $poseTask->panel)
                    <option value="{{ $poseTask->panel_id }}" selected>{{ $poseTask->panel->name }}</option>
                    @endif
                </select>
                <div style="font-size:11px;color:var(--text3);margin-top:6px">💡 Tapez au moins 2 caractères pour rechercher.</div>
            </div>
        </div>

        {{-- ══ S3 : ÉQUIPE & PLANNING ══ --}}
        <div class="pose-section">
            <div class="pose-section-header">
                <div class="pose-step">3</div>
                <div><div class="pose-section-title">Équipe & Planning</div></div>
            </div>
            {{-- 2026-06-18 (mission équipes pose tasks) ─ Voir create.blade.php
                 pour la doc complète. Pareil ici sur l'édition : selects vrais,
                 boutons "+ Nouveau" pour création rapide sans quitter le form. --}}
            @php $oldTeam = old('team_name', $poseTask->team_name); @endphp
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px">
                        <label class="pose-label" style="margin:0">Technicien</label>
                        <button type="button" onclick="openQuickTechModal({target_select_id:'sel-technicien'})"
                                style="background:none;border:1px dashed var(--accent);color:var(--accent);font-size:11px;font-weight:700;padding:1px 9px;border-radius:8px;cursor:pointer"
                                title="Créer un technicien rapidement">+ Nouveau</button>
                    </div>
                    {{-- 2026-06-19 — Multi-équipe : data-teams (JSON array). Auto-fill équipe seulement si 1 seule. --}}
                    <select name="assigned_user_id" id="sel-technicien" class="pose-select" data-team-by-user='@json($teamByUser ?? [])'>
                        <option value="">— Non assigné —</option>
                        @foreach($techniciens as $t)
                        @php $teamsList = $teamByUser[$t->id] ?? []; @endphp
                        <option value="{{ $t->id }}" {{ old('assigned_user_id',$poseTask->assigned_user_id)==$t->id?'selected':'' }}
                                data-teams="{{ json_encode($teamsList) }}">{{ $t->name }}{{ count($teamsList) > 1 ? ' (' . count($teamsList) . ' équipes)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px">
                        <label class="pose-label" style="margin:0">Équipe <span style="font-weight:400;color:var(--text3)">(opt.)</span></label>
                        {{-- 2026-06-19 — Modale rapide (au lieu de naviguer). --}}
                        <button type="button" onclick="openQuickTeamModal({target_select_id:'sel-team'})"
                                style="background:none;border:1px dashed var(--accent);color:var(--accent);font-size:11px;font-weight:700;padding:1px 9px;border-radius:8px;cursor:pointer"
                                title="Créer une équipe rapidement (sans quitter ce formulaire)">+ Nouvelle équipe</button>
                    </div>
                    <select name="team_name" id="sel-team" class="pose-select">
                        <option value="">— Aucune / hérite du technicien —</option>
                        @foreach(($teams ?? collect()) as $team)
                            <option value="{{ $team->name }}" {{ $oldTeam === $team->name ? 'selected' : '' }}>{{ $team->name }}</option>
                        @endforeach
                        @if($oldTeam && !($teams ?? collect())->contains('name', $oldTeam))
                            <option value="{{ $oldTeam }}" selected>{{ $oldTeam }} (legacy)</option>
                        @endif
                    </select>
                </div>
            </div>
            @push('scripts')
            <script>
            (function () {
                var selTech = document.getElementById('sel-technicien');
                var selTeam = document.getElementById('sel-team');
                if (!selTech || !selTeam) return;
                var teamByUser = {};
                try { teamByUser = JSON.parse(selTech.dataset.teamByUser || '{}'); } catch (e) {}
                selTech.addEventListener('change', function () {
                    // 2026-06-19 — Multi-équipe : teamByUser[id] est un array.
                    // Auto-fill uniquement si le tech a EXACTEMENT 1 équipe.
                    var teamsList = teamByUser[selTech.value];
                    if (!Array.isArray(teamsList) || teamsList.length !== 1) return;
                    var teamName = teamsList[0];
                    var opt = Array.from(selTeam.options).find(function (o) { return o.value === teamName; });
                    if (!opt) {
                        opt = new Option(teamName, teamName, false, false);
                        selTeam.appendChild(opt);
                    }
                    selTeam.value = teamName;
                });
            })();
            </script>
            @endpush
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <label class="pose-label">Date planifiée *</label>
                    <input type="datetime-local" name="scheduled_at" required class="pose-input"
                           value="{{ old('scheduled_at',$poseTask->scheduled_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <div>
                    <label class="pose-label">Statut *</label>
                    <select name="status" class="pose-select">
                        <option value="planifiee" {{ old('status',$poseTask->status)==='planifiee'?'selected':'' }}>📅 Planifiée</option>
                        <option value="en_cours"  {{ old('status',$poseTask->status)==='en_cours'?'selected':'' }}>🔧 En cours</option>
                        <option value="annulee"   {{ old('status',$poseTask->status)==='annulee'?'selected':'' }}>🚫 Annulée</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- ══ S4 : NOTES ══ --}}
        <div class="pose-section">
            <div class="pose-section-header"><div class="pose-step">4</div><div><div class="pose-section-title">Notes <span style="font-weight:400;color:var(--text3);font-size:11px">(opt.)</span></div></div></div>
            <textarea name="notes" class="pose-input" style="height:auto;resize:none;padding:10px 12px" rows="2">{{ old('notes',$poseTask->notes) }}</textarea>
        </div>

        {{-- Lien piges --}}
        @if($poseTask->campaign_id)
        <div style="background:var(--surface);border:1px solid var(--border);border-radius:12px;padding:14px 16px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between">
            <div style="font-size:12px;color:var(--text2)">📸 Gérer les piges de ce panneau pour cette campagne</div>
            <a href="{{ route('admin.piges.index', ['campaign_id'=>$poseTask->campaign_id,'panel_id'=>$poseTask->panel_id]) }}"
               style="font-size:11px;color:var(--accent);font-weight:600;text-decoration:none;padding:5px 12px;background:rgba(232,160,32,.08);border:1px solid rgba(232,160,32,.25);border-radius:8px">
                Voir les piges →
            </a>
        </div>
        @endif

        <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn-primary" style="min-width:180px;display:flex;align-items:center;justify-content:center;gap:7px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
                Enregistrer
            </button>
            <a href="{{ route('admin.pose-tasks.show', $poseTask) }}" class="btn btn-ghost">Annuler</a>
        </div>
    </form>
</div>

<style>
@keyframes spin   { to { transform:rotate(360deg) } }
@keyframes fadeIn { from { opacity:0;transform:translateY(-4px) } to { opacity:1;transform:none } }

.err-box { background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.3);border-radius:12px;padding:14px 18px;margin-bottom:16px }
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

.panel-edit-row { display:flex;align-items:center;gap:10px;padding:9px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .1s;box-sizing:border-box }
.panel-edit-row:last-child { border-bottom:none }
.panel-edit-row:hover { background:rgba(232,160,32,.04) }
.panel-edit-row.active { background:rgba(232,160,32,.08);border-left:3px solid var(--accent) }

.commune-pill { font-size:10px;color:var(--text3);background:var(--surface2);border:1px solid var(--border);border-radius:12px;padding:2px 9px;cursor:pointer;transition:all .12px;white-space:nowrap }
.commune-pill.active { background:rgba(232,160,32,.1);border-color:rgba(232,160,32,.4);color:var(--accent) }

/* Select2 */
.select2-container--default .select2-selection--single { height:40px!important;border-radius:10px!important;border:1px solid var(--border)!important;background:var(--surface2)!important;display:flex;align-items:center }
.select2-container--default .select2-selection--single .select2-selection__rendered { line-height:40px!important;color:var(--text)!important;padding-left:12px!important;padding-right:56px!important;font-size:13px;position:relative }
.select2-container--default .select2-selection--single .select2-selection__arrow { height:38px!important;right:6px!important }
/* Bouton "×" clear : positionné séparé du texte (sinon "Nom campagne×" illisible). */
.select2-container--default .select2-selection--single .select2-selection__clear {
    position:absolute!important; right:30px!important; top:50%!important;
    transform:translateY(-50%); float:none!important; margin:0!important;
    width:22px; height:22px; line-height:22px;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:50%; background:rgba(0,0,0,.05); border:0;
    color:var(--text3); font-size:14px; font-weight:600; cursor:pointer;
    transition:background .12s, color .12s;
}
.select2-container--default .select2-selection--single .select2-selection__clear:hover {
    background:rgba(239,68,68,.10); color:#ef4444;
}
.select2-container--default .select2-selection--single .select2-selection__clear > span { display:none; }
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single { border-color:var(--accent)!important;outline:none!important;box-shadow:none!important }
.select2-dropdown { background:var(--surface)!important;border:1px solid var(--border)!important;border-radius:12px!important;box-shadow:0 8px 24px rgba(0,0,0,.25)!important;overflow:hidden;animation:fadeIn .15s ease }
.select2-container--default .select2-search--dropdown .select2-search__field { background:var(--surface2)!important;border:1px solid var(--border)!important;border-radius:8px!important;color:var(--text)!important;padding:6px 10px!important;font-size:13px;outline:none;margin:8px!important;width:calc(100% - 16px)!important;box-sizing:border-box }
.select2-container--default .select2-search--dropdown .select2-search__field:focus { border-color:var(--accent)!important }
.select2-results__option { padding:0!important;font-size:13px;color:var(--text);transition:background .1s }
.select2-results__option--highlighted { background:rgba(232,160,32,.08)!important;color:var(--text)!important }
.select2-results__option[aria-selected=true] { background:rgba(232,160,32,.12)!important;color:var(--text)!important }
.select2-container { width:100%!important }
.select2-results__options { max-height:260px;overflow-y:auto }
.select2-results__message { color:var(--text3)!important;font-size:12px;padding:12px!important;text-align:center }
.s2-opt { display:flex;align-items:center;gap:10px;padding:10px 14px;border-bottom:1px solid var(--border) }
.s2-opt:last-child { border-bottom:none }
.s2-opt-ref { font-family:monospace;font-size:12px;font-weight:700;color:var(--accent);flex-shrink:0 }
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

@push('scripts')
<script>
(function(){
'use strict';
const D = window.__EDIT__;
const ROW_H = 50;
const OVER  = 4;

let _allPanels      = [];
let _filteredPanels = [];
let _selectedPanelId= D.current.panel_id;
let _noCampaign     = D.current.no_campaign;
let _activeCommune  = null;
let _visStart = 0, _visEnd = 0;

// ── Select2 Campagne ──────────────────────────────────────────
function fmtCamp(c) {
    if (!c.id) return $(`<span style="color:var(--text3)">${c.text}</span>`);
    return $(`<div class="s2-opt">
        <span style="font-size:18px;flex-shrink:0">${c.icon||''}</span>
        <div style="flex:1;min-width:0">
            <div style="font-size:13px;font-weight:600;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${c.text}</div>
            <div style="font-size:10px;color:${c.color||'var(--text3)'};">${c.label||''} · ${c.total_panels||0} panneaux</div>
        </div>${c.blocked?'<span style="font-size:10px;color:#ef4444">Bloquée</span>':''}
    </div>`);
}

$('#sel-campaign').select2({
    placeholder:'Rechercher une campagne…', allowClear:true, minimumInputLength:0,
    language:{searching:()=>'Recherche…',noResults:()=>'Aucune campagne'},
    ajax:{url:D.searchCampaignsUrl,dataType:'json',delay:250,
          headers:{'X-CSRF-TOKEN':D.csrf,Accept:'application/json'},
          data:params=>({q:params.term||''}),
          processResults:data=>({results:data.map(c=>({id:c.id,text:c.name,...c}))}),cache:true},
    templateResult:fmtCamp,
    templateSelection:c=>c.id?`${c.icon||''} ${c.text}`:c.text,
    dropdownParent:$('#campaign-field-wrap'), width:'100%',
});

$('#sel-campaign').on('change', function(){
    const d=$(this).select2('data')[0];
    if(!d?.id) EDIT.onCampCleared(); else EDIT.onCampSelected(d);
});

// ── Select2 Panneau libre ─────────────────────────────────────
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
    placeholder:'Rechercher un panneau…', allowClear:true, minimumInputLength:2,
    language:{inputTooShort:()=>'Tapez 2 caractères min',searching:()=>'Recherche…',noResults:()=>'Aucun panneau'},
    ajax:{url:D.searchPanelsUrl,dataType:'json',delay:250,
          headers:{'X-CSRF-TOKEN':D.csrf,Accept:'application/json'},
          data:params=>({q:params.term}),
          processResults:data=>({results:data.map(p=>({id:p.id,text:p.name,...p}))}),cache:true},
    templateResult:fmtPanel,
    templateSelection:p=>p.id?`${p.reference||''} · ${p.text}`:p.text,
    dropdownParent:$('#panels-free-zone'), width:'100%',
});

$('#sel-panel-free').on('change', function(){
    const d=$(this).select2('data')[0];
    if(d?.id){
        _selectedPanelId = parseInt(d.id);
        document.getElementById('edit-panel-id').value = d.id;
        EDIT._updateCurrentDisplay(d.reference||'', d.text, d.commune||'—');
    }
});

// ── Logique principale ────────────────────────────────────────
window.EDIT = {

    onCampSelected(camp) {
        _noCampaign=false;
        document.getElementById('chk-no-campaign').checked=false;
        $('#sel-campaign').prop('disabled',false).css('opacity','');
        const inf=document.getElementById('campaign-info');
        document.getElementById('ci-icon').textContent=camp.icon||'';
        document.getElementById('ci-name').textContent=camp.text||'';
        document.getElementById('ci-status').textContent=camp.label||'';
        document.getElementById('ci-status').style.color=camp.color||'var(--text3)';
        inf.style.display='flex';
        document.getElementById('panels-campaign-zone').style.display='block';
        document.getElementById('panels-free-zone').style.display='none';
        this._loadPanels(camp.id);
    },

    onCampCleared() {
        document.getElementById('campaign-info').style.display='none';
        if (!_noCampaign) document.getElementById('panels-campaign-zone').style.display='none';
    },

    toggleNoCampaign() {
        const chk=document.getElementById('chk-no-campaign');
        chk.checked=!chk.checked; _noCampaign=chk.checked;
        if(chk.checked){
            $('#sel-campaign').val(null).trigger('change');
            document.getElementById('campaign-info').style.display='none';
            $('#sel-campaign').prop('disabled',true).css('opacity','.4');
            document.getElementById('panels-campaign-zone').style.display='none';
            document.getElementById('panels-free-zone').style.display='block';
        } else {
            $('#sel-campaign').prop('disabled',false).css('opacity','');
            document.getElementById('panels-free-zone').style.display='none';
        }
    },

    async _loadPanels(campaignId) {
        _allPanels=[]; _filteredPanels=[]; _activeCommune=null;
        document.getElementById('edit-panels-loader').style.display='block';
        document.getElementById('edit-panels-rendered').innerHTML='';
        document.getElementById('panels-stats-text').textContent='';
        document.getElementById('edit-panel-search').value='';
        document.getElementById('commune-filters-edit').innerHTML='';
        try {
            const res=await fetch(`${D.campaignPanelsUrl}?campaign_id=${campaignId}`,
                {headers:{Accept:'application/json','X-CSRF-TOKEN':D.csrf}});
            if(!res.ok) throw new Error();
            const data=await res.json();
            _allPanels=data.panels;
            const s=data.stats;
            document.getElementById('panels-stats-text').innerHTML=
                `<span style="color:#f97316">${s.sans_pose} à poser</span> · <span style="color:#22c55e">${s.avec_pige} pigée(s)</span>`;
            this._buildCommunes();
            this._applyFilter();
            this._renderVirtual();
        } catch {
            document.getElementById('edit-panels-rendered').innerHTML=
                '<div style="padding:14px;text-align:center;color:#ef4444;font-size:12px">Erreur de chargement.</div>';
        } finally {
            document.getElementById('edit-panels-loader').style.display='none';
        }
    },

    _buildCommunes() {
        const communes=[...new Set(_allPanels.map(p=>p.commune).filter(Boolean))].sort();
        const wrap=document.getElementById('commune-filters-edit');
        if(communes.length<=1){wrap.innerHTML='';return;}
        wrap.innerHTML=communes.map(c=>
            `<button type="button" class="commune-pill" data-commune="${c}" onclick="EDIT.filterCommune('${c.replace(/'/g,"\\'")}')">${c}</button>`
        ).join('');
    },

    filterCommune(c) {
        _activeCommune=_activeCommune===c?null:c;
        document.querySelectorAll('#commune-filters-edit .commune-pill').forEach(p=>{
            p.classList.toggle('active',p.dataset.commune===_activeCommune);
        });
        this._applyFilter(); this._renderVirtual();
    },

    _filterTimer:null,
    filterPanels(q){
        clearTimeout(this._filterTimer);
        this._filterTimer=setTimeout(()=>{this._applyFilter(q);this._renderVirtual();},80);
    },

    _applyFilter(q=document.getElementById('edit-panel-search')?.value||''){
        const lq=q.trim().toLowerCase();
        _filteredPanels=_allPanels.filter(p=>{
            const mt=!lq||p.reference.toLowerCase().includes(lq)||p.name.toLowerCase().includes(lq)||(p.commune||'').toLowerCase().includes(lq);
            const mc=!_activeCommune||p.commune===_activeCommune;
            return mt&&mc;
        });
        const vis=_filteredPanels.length,tot=_allPanels.length;
        const fv=document.getElementById('edit-footer-visible');
        if(fv) fv.textContent=vis<tot?`${vis} sur ${tot} panneaux`:`${tot} panneaux`;
    },

    onScroll(){ this._renderVirtual(); },

    _renderVirtual(){
        const panels=_filteredPanels, count=panels.length;
        const vp=document.getElementById('edit-panels-viewport');
        const sp=document.getElementById('edit-panels-spacer');
        const rd=document.getElementById('edit-panels-rendered');

        if(count===0){
            sp.style.height='0';
            rd.innerHTML='<div style="padding:16px;text-align:center;color:var(--text3);font-size:12px">Aucun panneau trouvé.</div>';
            return;
        }
        sp.style.height=(count*ROW_H)+'px';
        sp.style.position='relative';
        const st=vp.scrollTop, vh=vp.clientHeight||280;
        const start=Math.max(0,Math.floor(st/ROW_H)-OVER);
        const end=Math.min(count-1,Math.ceil((st+vh)/ROW_H)+OVER);

        if(start===_visStart&&end===_visEnd&&rd.children.length>0) return;
        _visStart=start; _visEnd=end;

        rd.style.position='absolute'; rd.style.top=(start*ROW_H)+'px'; rd.style.left='0'; rd.style.right='0';

        const frag=document.createDocumentFragment();
        for(let i=start;i<=end;i++) frag.appendChild(this._buildRow(panels[i]));
        rd.innerHTML=''; rd.appendChild(frag);
    },

    _buildRow(p){
        const row=document.createElement('div');
        const isActive=p.id===_selectedPanelId;
        row.className='panel-edit-row'+(isActive?' active':'');
        row.dataset.id=p.id;
        row.style.height=ROW_H+'px';row.style.boxSizing='border-box';

        const cm={planifiee:{c:'#e8a020',i:'📅'},en_cours:{c:'#3b82f6',i:'🔧'},realisee:{c:'#22c55e',i:'✅'}};
        const pose=p.has_task&&p.task_status?
            `<span style="padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;background:${cm[p.task_status]?.c||'#6b7280'}18;color:${cm[p.task_status]?.c||'#6b7280'};flex-shrink:0">${cm[p.task_status]?.i||'?'} ${p.task_date||p.task_status}</span>`:
            `<span style="padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(249,115,22,.08);color:#f97316;flex-shrink:0">⏳ À poser</span>`;
        const pige=p.has_pige?`<span style="padding:2px 6px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(34,197,94,.08);color:#22c55e;flex-shrink:0">📸</span>`:'';
        const cur=isActive?`<span style="padding:2px 7px;border-radius:8px;font-size:9px;font-weight:700;background:rgba(232,160,32,.15);color:var(--accent);flex-shrink:0">Actuel ✓</span>`:'';

        row.innerHTML=`
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:6px">
                    <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">${p.reference}</span>
                    <span style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
                </div>
                <div style="font-size:10px;color:var(--text3)">📍 ${p.commune}</div>
            </div>
            <div style="display:flex;gap:4px;align-items:center;flex-shrink:0">${pige}${pose}${cur}</div>
        `;

        row.addEventListener('click',()=>{
            _selectedPanelId=p.id;
            document.getElementById('edit-panel-id').value=p.id;
            this._updateCurrentDisplay(p.reference,p.name,p.commune);
            document.querySelectorAll('#edit-panels-rendered .panel-edit-row').forEach(r=>{
                r.classList.toggle('active',parseInt(r.dataset.id)===p.id);
            });
        });
        return row;
    },

    _updateCurrentDisplay(ref,name,commune){
        document.getElementById('cur-ref').textContent=ref;
        document.getElementById('cur-name').textContent=name;
        document.getElementById('cur-commune').textContent='📍 '+commune;
        document.getElementById('edit-footer-info').textContent=`Panneau sélectionné : ${ref}`;
    },

    init(){
        if(D.current.campaign_id) this._loadPanels(D.current.campaign_id);
        if(_noCampaign){
            $('#sel-campaign').prop('disabled',true).css('opacity','.4');
        }
    },
};

$(document).ready(()=>EDIT.init());
})();
</script>
@endpush

{{-- Modale "+ Nouveau technicien" (création rapide AJAX) — 2026-06-18 --}}
@include('admin.partials._quick_tech_modal', ['target_select_id' => 'sel-technicien'])
@include('admin.partials._quick_team_modal', [
    'target_select_id' => 'sel-team',
    // TOUS les techniciens — ceux déjà dans une équipe affichent un badge
    // "↻ équipe X" et la sélection les TRANSFÈRE vers la nouvelle équipe.
    'available_techs'  => \App\Models\User::techniciens()->with('poseTeams:id,name')->get(['id', 'name', 'agent_code']),
])
</x-admin-layout>
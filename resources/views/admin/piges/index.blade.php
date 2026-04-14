<x-admin-layout title="Piges Photos">

{{-- ════ DONNÉES SERVEUR ════ --}}
<script>
window.__PIGES__ = {
    uploadUrl:           '{{ route("admin.piges.upload") }}',
    panelsByCampaignUrl: '{{ route("admin.piges.panels-by-campaign") }}',
    exportPdfUrl:        '{{ route("admin.piges.export-pdf") }}',
    csrf:                '{{ csrf_token() }}',
    campaigns: {!! json_encode($campaigns->map(fn($c) => [
        'id'      => $c->id,
        'name'    => $c->name,
        'status'  => $c->status->value,
        'label'   => $c->status->label(),
        'blocked' => $c->status->isTerminal(),
        'icon'    => $c->status->uiConfig()['icon'],
        'color'   => $c->status->uiConfig()['color'],
    ])) !!},
    panels: {!! json_encode($panels->map(fn($p) => ['id'=>$p->id,'reference'=>$p->reference,'name'=>$p->name])) !!},
    clients: {!! json_encode($clients->map(fn($c) => ['id'=>$c->id,'name'=>$c->name])) !!},
};
</script>

{{-- ════ STATS KPI ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    @php
    $kpis = [
        ['label'=>'Total piges',  'val'=>$stats->total,      'color'=>'#e8a020'],
        ['label'=>'En attente',   'val'=>$stats->en_attente, 'color'=>'#f97316'],
        ['label'=>'Vérifiées',    'val'=>$stats->verifie,    'color'=>'#22c55e'],
        ['label'=>'Rejetées',     'val'=>$stats->rejete,     'color'=>'#ef4444'],
    ];
    $icons = [
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    ];
    @endphp
    @foreach($kpis as $i => $k)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:4px solid {{ $k['color'] }}">
        <div style="color:{{ $k['color'] }};margin-bottom:8px">{!! $icons[$i] !!}</div>
        <div style="font-size:28px;font-weight:800;color:{{ $k['color'] }};line-height:1">{{ number_format($k['val']) }}</div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-top:4px">{{ $k['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- ════ FILTRES ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:14px 18px;margin-bottom:16px">
    <form id="form-filters" method="GET" action="{{ route('admin.piges.index') }}">
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:10px">
            <div>
                <label class="flbl">Recherche</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Panneau, campagne…" class="finp" oninput="PG.debounce()">
            </div>
            <div>
                <label class="flbl">Client</label>
                <select name="client_id" class="fsel" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    @foreach($clients as $c)
                    <option value="{{ $c->id }}" {{ request('client_id')==$c->id?'selected':'' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flbl">Campagne</label>
                <select name="campaign_id" class="fsel" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach($campaigns as $c)
                    <option value="{{ $c->id }}" {{ request('campaign_id')==$c->id?'selected':'' }}>
                        {{ $c->status->uiConfig()['icon'] }} {{ Str::limit($c->name, 22) }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flbl">Statut</label>
                <select name="status" class="fsel" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="en_attente" {{ request('status')==='en_attente'?'selected':'' }}>En attente</option>
                    <option value="verifie"    {{ request('status')==='verifie'?'selected':'' }}>Vérifiées</option>
                    <option value="rejete"     {{ request('status')==='rejete'?'selected':'' }}>Rejetées</option>
                </select>
            </div>
            <div style="display:flex;gap:6px;align-items:flex-end">
                @if(request()->hasAny(['q','client_id','campaign_id','status','date_from','date_to']))
                <a href="{{ route('admin.piges.index') }}" style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;color:var(--text3);text-decoration:none" title="Réinitialiser">↺</a>
                @endif
                <button type="button" onclick="PG.openUploadModal()" class="btn btn-primary" style="height:40px;font-size:12px;padding:0 16px;white-space:nowrap">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:inline;margin-right:5px;vertical-align:middle"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Nouvelle pige
                </button>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:11px;color:var(--text3);font-weight:600;text-transform:uppercase">Période :</span>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="finp" style="width:auto;height:36px" onchange="this.form.submit()">
            <span style="color:var(--text3)">→</span>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="finp" style="width:auto;height:36px" onchange="this.form.submit()">
            <div style="margin-left:auto;font-size:12px;color:var(--text3)">
                <strong style="color:var(--text)">{{ number_format($piges->total()) }}</strong> pige(s) · page {{ $piges->currentPage() }}/{{ $piges->lastPage() }}
            </div>
            @if(request()->hasAny(['campaign_id','client_id']))
            <button type="button" onclick="PG.exportPdf()" style="padding:6px 14px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.3);color:#3b82f6;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer">
                Rapport PDF
            </button>
            @endif
        </div>
    </form>
</div>

{{-- ════ GRILLE PIGES ════ --}}
@if($piges->isEmpty())
<div style="text-align:center;padding:80px;background:var(--surface);border:1px solid var(--border);border-radius:14px">
    <div style="color:var(--text3);opacity:.3;margin-bottom:16px">
        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
    </div>
    <div style="font-size:16px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune pige trouvée</div>
    <div style="font-size:13px;color:var(--text3);margin-bottom:20px">
        @if(request()->hasAny(['q','client_id','campaign_id','status'])) Modifiez vos filtres.
        @else Aucune pige n'a encore été enregistrée.
        @endif
    </div>
    <button onclick="PG.openUploadModal()" class="btn btn-primary">+ Ajouter une pige</button>
</div>
@else
<div id="pige-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(225px,1fr));gap:14px;margin-bottom:24px">
    @foreach($piges as $pige)
    @php
    $sc = match($pige->status) {
        'verifie' => ['color'=>'#22c55e','label'=>'Vérifié',   'bg'=>'rgba(34,197,94,.88)'],
        'rejete'  => ['color'=>'#ef4444','label'=>'Rejeté',    'bg'=>'rgba(239,68,68,.88)'],
        default   => ['color'=>'#f97316','label'=>'En attente','bg'=>'rgba(249,115,22,.88)'],
    };
    $cs  = $pige->campaign?->status;
    $cui = $cs?->uiConfig();
    @endphp
    <div class="pige-card" data-id="{{ $pige->id }}" onclick="PG.openLightbox({{ $pige->id }})">
        <div style="position:relative;height:165px;background:var(--surface2);overflow:hidden;border-radius:10px 10px 0 0">
            <img src="{{ asset('storage/'.$pige->photo_path) }}" loading="lazy"
                 alt="Pige {{ $pige->panel?->reference }}"
                 style="width:100%;height:100%;object-fit:cover;display:block"
                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
            <div style="display:none;width:100%;height:100%;align-items:center;justify-content:center;color:var(--text3);font-size:32px;opacity:.3">📷</div>

            {{-- Badge statut pige --}}
            <div style="position:absolute;top:8px;right:8px;padding:3px 9px;border-radius:20px;font-size:10px;font-weight:700;background:{{ $sc['bg'] }};color:#fff;backdrop-filter:blur(4px)">{{ $sc['label'] }}</div>

            {{-- Badge statut campagne --}}
            @if($cs && $cui)
            <div style="position:absolute;top:8px;left:8px;padding:2px 7px;border-radius:6px;font-size:9px;font-weight:700;background:rgba(0,0,0,.65);color:#fff;backdrop-filter:blur(4px)">{{ $cui['icon'] }} {{ $cs->label() }}</div>
            @endif

            {{-- GPS --}}
            @if($pige->hasGps())
            <div style="position:absolute;bottom:8px;left:8px;padding:2px 7px;border-radius:5px;font-size:9px;background:rgba(0,0,0,.6);color:#fff">📍 GPS</div>
            @endif

            {{-- Client supprimé --}}
            @if($pige->campaign?->client?->trashed())
            <div style="position:absolute;bottom:8px;right:8px;padding:2px 7px;border-radius:5px;font-size:9px;background:rgba(239,68,68,.8);color:#fff">⚠️ Client</div>
            @endif
        </div>

        <div style="padding:10px 12px">
            <div style="font-family:monospace;font-size:11px;font-weight:700;color:var(--accent);margin-bottom:2px">{{ $pige->panel?->reference ?? '—' }}</div>
            <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $pige->panel?->name }}">{{ Str::limit($pige->panel?->name ?? '—', 26) }}</div>
            <div style="font-size:10px;color:var(--text3);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ Str::limit($pige->campaign?->name ?? 'Sans campagne', 24) }}</div>
            <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3)">
                <span>{{ $pige->taken_at?->format('d/m/Y') ?? '—' }}</span>
                <span>{{ Str::limit($pige->takenBy?->name ?? '—', 12) }}</span>
            </div>

            {{-- Actions rapides --}}
            @if($pige->isPending())
            <div style="display:flex;gap:6px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)" onclick="event.stopPropagation()">
                <button onclick="PG.quickVerify({{ $pige->id }}, this)" style="flex:1;padding:5px 0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s">Valider</button>
                <button onclick="PG.openRejectModal({{ $pige->id }})" style="flex:1;padding:5px 0;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer">Rejeter</button>
            </div>
            @endif

            @if($pige->isRejected() && $pige->rejection_reason)
            <div style="margin-top:7px;padding:5px 8px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-radius:6px;font-size:10px;color:#ef4444;line-height:1.3">{{ Str::limit($pige->rejection_reason, 55) }}</div>
            @endif
        </div>
    </div>
    @endforeach
</div>

@if($piges->hasPages())
<div style="display:flex;justify-content:center;align-items:center;gap:8px;padding:12px 0 24px">
    @if($piges->onFirstPage())<span class="btn btn-ghost btn-sm" style="opacity:.35;cursor:not-allowed">← Préc.</span>
    @else<a href="{{ $piges->previousPageUrl() }}" class="btn btn-ghost btn-sm">← Préc.</a>@endif
    <span style="font-size:12px;color:var(--text3);padding:0 8px">{{ $piges->currentPage() }} / {{ $piges->lastPage() }}</span>
    @if($piges->hasMorePages())<a href="{{ $piges->nextPageUrl() }}" class="btn btn-ghost btn-sm">Suiv. →</a>
    @else<span class="btn btn-ghost btn-sm" style="opacity:.35;cursor:not-allowed">Suiv. →</span>@endif
</div>
@endif
@endif

{{-- ═══════════════════════════════════════════════════════
     LIGHTBOX
═══════════════════════════════════════════════════════ --}}
<div id="lightbox" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeLightbox()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;width:100%;max-width:920px;max-height:92vh;overflow:hidden;display:flex;box-shadow:0 24px 80px rgba(0,0,0,.6)" onclick="event.stopPropagation()">
        <div style="flex:1.6;background:#000;display:flex;align-items:center;justify-content:center;position:relative;min-height:480px">
            <div id="lb-spinner" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:#000"><div style="width:36px;height:36px;border:3px solid rgba(255,255,255,.15);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></div></div>
            <img id="lb-photo" src="" alt="Pige" style="max-width:100%;max-height:580px;object-fit:contain;opacity:0;transition:opacity .3s">
            <div id="lb-gps-badge" style="display:none;position:absolute;bottom:12px;left:12px;background:rgba(0,0,0,.75);color:#fff;padding:6px 12px;border-radius:8px;font-size:11px;backdrop-filter:blur(4px)">
                📍 <span id="lb-gps-text"></span>
                <a id="lb-maps-link" href="#" target="_blank" style="color:#e8a020;margin-left:8px;font-size:10px">Maps →</a>
            </div>
        </div>
        <div style="width:290px;flex-shrink:0;display:flex;flex-direction:column;overflow-y:auto;border-left:1px solid var(--border)">
            <div style="padding:14px 16px;background:var(--surface2);border-bottom:1px solid var(--border);flex-shrink:0">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
                    <div>
                        <div style="font-size:10px;color:var(--text3);margin-bottom:4px">PIGE #<span id="lb-id"></span></div>
                        <div id="lb-status-badge" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700"></div>
                    </div>
                    <button onclick="PG.closeLightbox()" style="width:30px;height:30px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text3);cursor:pointer;font-size:13px">✕</button>
                </div>
                <div id="lb-campaign-bar" style="display:none;padding:7px 10px;border-radius:8px;font-size:11px;font-weight:600;border:1px solid"></div>
            </div>
            <div style="padding:14px 16px;flex:1">
                @foreach([['PANNEAU','lb-panel'],['COMMUNE','lb-commune'],['CAMPAGNE','lb-campaign'],['CLIENT','lb-client'],['PRISE LE','lb-date'],['TECHNICIEN','lb-user'],['NOTES','lb-notes']] as [$lbl,$id])
                <div style="margin-bottom:11px">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:2px">{{ $lbl }}</div>
                    <div id="{{ $id }}" style="font-size:12px;color:var(--text);font-weight:500;line-height:1.4">—</div>
                </div>
                @endforeach
                <div id="lb-reject-box" style="display:none;margin-top:4px;padding:10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:8px">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#ef4444;margin-bottom:4px">MOTIF REJET</div>
                    <div id="lb-reject-reason" style="font-size:12px;color:#ef4444;line-height:1.4"></div>
                </div>
                <div id="lb-verified-box" style="display:none;margin-top:8px;padding:10px;background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2);border-radius:8px">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#22c55e;margin-bottom:4px">VALIDÉE PAR</div>
                    <div id="lb-verified-info" style="font-size:12px;color:#22c55e;line-height:1.4"></div>
                </div>
            </div>
            <div id="lb-actions" style="padding:12px 16px;border-top:1px solid var(--border);display:flex;flex-direction:column;gap:7px;flex-shrink:0">
                <div style="color:var(--text3);font-size:12px;text-align:center;padding:8px">Chargement…</div>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     MODAL UPLOAD — Étapes guidées avec panneaux par campagne
═══════════════════════════════════════════════════════ --}}
<div id="modal-upload" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeUploadModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;width:100%;max-width:620px;max-height:94vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.6)" onclick="event.stopPropagation()">

        {{-- Header --}}
        <div style="padding:16px 20px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
            <div>
                <div style="font-size:15px;font-weight:700;color:var(--text);display:flex;align-items:center;gap:8px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#e8a020" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    Nouvelle pige terrain
                </div>
                <div style="font-size:11px;color:var(--text3);margin-top:2px">Preuve d'affichage panneau publicitaire</div>
            </div>
            <button onclick="PG.closeUploadModal()" style="width:30px;height:30px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text3);cursor:pointer;font-size:13px">✕</button>
        </div>

        {{-- Alerte contexte campagne --}}
        <div id="ctx-alert" style="display:none;padding:10px 20px;border-bottom:1px solid var(--border);font-size:12px;font-weight:600;flex-shrink:0"></div>

        {{-- Body scrollable --}}
        <div style="overflow-y:auto;flex:1">
            <form id="form-upload" enctype="multipart/form-data" style="padding:18px 20px">
                @csrf

                {{-- ÉTAPE 1 : Campagne --}}
                <div style="margin-bottom:18px">
                    <div class="step-label">
                        <span class="step-num">1</span>
                        Campagne concernée
                    </div>
                    <select id="sel-campaign" name="campaign_id" class="fsel w-full" onchange="PG.onCampaignChange()">
                        <option value="">— Pige libre (sans campagne) —</option>
                        @foreach($campaigns as $c)
                        @php $cui = $c->status->uiConfig(); @endphp
                        <option value="{{ $c->id }}"
                                data-status="{{ $c->status->value }}"
                                data-blocked="{{ $c->status->isTerminal()?'1':'0' }}"
                                data-icon="{{ $cui['icon'] }}"
                                data-color="{{ $cui['color'] }}"
                                {{ request('campaign_id')==$c->id?'selected':'' }}>
                            {{ $cui['icon'] }} {{ $c->name }} ({{ $c->status->label() }})
                        </option>
                        @endforeach
                    </select>
                </div>

                {{-- ÉTAPE 2 : Panneaux --}}
                <div style="margin-bottom:18px">
                    <div class="step-label" style="justify-content:space-between">
                        <span style="display:flex;align-items:center;gap:8px">
                            <span class="step-num">2</span>
                            Panneau(x) à piger
                        </span>
                        <span id="panels-stats" style="font-size:10px;color:var(--text3)"></span>
                    </div>

                    {{-- Sans campagne : select simple --}}
                    <div id="panel-free">
                        <select name="panel_id" id="sel-panel-free" class="fsel w-full">
                            <option value="">— Sélectionner un panneau —</option>
                            @foreach($panels as $p)
                            <option value="{{ $p->id }}">{{ $p->reference }} · {{ Str::limit($p->name, 22) }}</option>
                            @endforeach
                        </select>
                        <div style="font-size:11px;color:var(--text3);margin-top:6px">
                            💡 Sélectionnez une campagne ci-dessus pour voir uniquement ses panneaux avec leur statut pige.
                        </div>
                    </div>

                    {{-- Avec campagne : liste interactive --}}
                    <div id="panel-campaign" style="display:none">
                        <div id="panels-loader" style="display:none;text-align:center;padding:20px;color:var(--text3)">
                            <div style="width:20px;height:20px;border:2px solid rgba(255,255,255,.1);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;margin:0 auto 8px"></div>
                            Chargement des panneaux…
                        </div>
                        <div id="panels-list" style="border:1px solid var(--border);border-radius:10px;overflow:hidden;max-height:220px;overflow-y:auto"></div>
                        <div id="panels-select-all" style="display:none;margin-top:6px;display:flex;gap:8px">
                            <button type="button" onclick="PG.selectAllPanels(false)" style="font-size:11px;color:var(--text3);background:none;border:none;cursor:pointer;text-decoration:underline">Tout sélectionner</button>
                            <button type="button" onclick="PG.selectAllPanels(true)" style="font-size:11px;color:var(--text3);background:none;border:none;cursor:pointer;text-decoration:underline">Panneaux sans pige</button>
                        </div>
                    </div>
                </div>

                {{-- ÉTAPE 3 : Photo --}}
                <div style="margin-bottom:16px">
                    <div class="step-label"><span class="step-num">3</span> Photo terrain</div>
                    <div id="drop-zone" style="border:2px dashed var(--border);border-radius:12px;height:140px;display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative;overflow:hidden;transition:border-color .2s,background .2s"
                         onclick="document.getElementById('inp-photo').click()"
                         ondragover="event.preventDefault();this.style.borderColor='var(--accent)';this.style.background='rgba(232,160,32,.03)'"
                         ondragleave="this.style.borderColor='';this.style.background=''"
                         ondrop="PG.handleDrop(event)">
                        <div id="drop-placeholder" style="text-align:center;color:var(--text3);pointer-events:none">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="display:block;margin:0 auto 8px;opacity:.4"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                            <div style="font-size:12px;font-weight:600">Cliquez ou glissez une photo</div>
                            <div style="font-size:10px;margin-top:3px;opacity:.7">JPEG, PNG, WebP · Max 30 Mo</div>
                        </div>
                        <img id="drop-preview" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:10px">
                        <div id="drop-info" style="display:none;position:absolute;bottom:6px;right:8px;background:rgba(0,0,0,.6);color:#fff;padding:2px 8px;border-radius:5px;font-size:10px"></div>
                    </div>
                    <input type="file" id="inp-photo" name="photo" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none" onchange="PG.previewPhoto(this)">
                </div>

                {{-- ÉTAPE 4 : Détails --}}
                <div>
                    <div class="step-label"><span class="step-num">4</span> Détails</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:10px">
                        <div>
                            <label class="flbl">Date prise *</label>
                            <input type="date" name="taken_at" required class="finp" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="flbl">GPS Lat <span style="font-weight:400;text-transform:none;font-size:9px">(ex: 5.3401)</span></label>
                            <input type="number" name="gps_lat" step="0.0000001" min="-90" max="90" class="finp" placeholder="5.3401">
                        </div>
                        <div>
                            <label class="flbl">GPS Lng <span style="font-weight:400;text-transform:none;font-size:9px">(ex: -4.0263)</span></label>
                            <input type="number" name="gps_lng" step="0.0000001" min="-180" max="180" class="finp" placeholder="-4.0263">
                        </div>
                    </div>
                    <div>
                        <label class="flbl">Notes / Observations</label>
                        <textarea name="notes" rows="2" class="finp" style="height:auto;resize:none;padding:10px 12px" placeholder="État du visuel, conditions, remarques…"></textarea>
                    </div>
                </div>

                {{-- Erreurs --}}
                <div id="upload-errors" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:10px 14px;font-size:12px;color:#ef4444;margin-top:12px"></div>

                {{-- Progress --}}
                <div id="upload-progress" style="display:none;margin-top:12px">
                    <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-bottom:4px">
                        <span id="progress-label">Upload en cours…</span>
                        <span id="progress-pct">0%</span>
                    </div>
                    <div style="background:var(--surface2);border-radius:10px;height:6px;overflow:hidden">
                        <div id="progress-bar" style="background:var(--accent);height:100%;width:0%;border-radius:10px;transition:width .3s"></div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Footer --}}
        <div style="padding:14px 20px;border-top:1px solid var(--border);background:var(--surface2);display:flex;justify-content:space-between;align-items:center;flex-shrink:0">
            <button type="button" onclick="PG.closeUploadModal()" class="btn btn-ghost btn-sm">Annuler</button>
            <button type="button" id="btn-submit" onclick="PG.submitUpload()" class="btn btn-primary" style="min-width:180px;display:flex;align-items:center;justify-content:center;gap:7px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span id="btn-submit-text">Enregistrer la pige</span>
            </button>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     MODAL REJET
═══════════════════════════════════════════════════════ --}}
<div id="modal-reject" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeRejectModal()">
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:20px;width:100%;max-width:440px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.5)" onclick="event.stopPropagation()">
        <div style="padding:16px 20px;background:var(--surface2);border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
            <div style="font-size:14px;font-weight:700;color:#ef4444;display:flex;align-items:center;gap:8px">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                Rejeter la pige
            </div>
            <button onclick="PG.closeRejectModal()" style="width:30px;height:30px;background:var(--surface);border:1px solid var(--border);border-radius:8px;color:var(--text3);cursor:pointer">✕</button>
        </div>
        <div style="padding:18px 20px">
            <div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.2);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:12px;color:#ef4444">Le technicien devra soumettre une nouvelle photo.</div>
            <div>
                <label class="flbl">Motif de rejet *</label>
                <textarea id="reject-input" rows="3" class="finp" style="height:auto;resize:none;padding:10px 12px;margin-top:4px" placeholder="Ex: Photo floue, mauvais angle, panneau non visible, visuel incorrect…"></textarea>
                <div id="reject-counter" style="text-align:right;font-size:10px;color:var(--text3);margin-top:3px">0 / 500</div>
            </div>
            <div id="reject-errors" style="display:none;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:8px 12px;font-size:12px;color:#ef4444;margin-top:10px"></div>
            <div style="display:flex;justify-content:space-between;margin-top:14px">
                <button onclick="PG.closeRejectModal()" class="btn btn-ghost btn-sm">Annuler</button>
                <button id="btn-reject-confirm" onclick="PG.submitReject()" style="padding:9px 20px;background:#ef4444;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;transition:opacity .15s">Confirmer le rejet</button>
            </div>
        </div>
    </div>
</div>

{{-- ════ STYLES ════ --}}
<style>
@keyframes spin    { to { transform: rotate(360deg) } }
@keyframes fadeIn  { from { opacity:0;transform:translateY(4px) } to { opacity:1;transform:none } }
@keyframes cardFlip{ 0%{opacity:1} 50%{opacity:0;transform:scale(.97)} 100%{opacity:1;transform:none} }

.flbl { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px }
.finp { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);transition:border-color .2s;box-sizing:border-box;outline:none }
.finp:focus { border-color:var(--accent) }
.fsel { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);cursor:pointer;outline:none }
.w-full { width:100% }

.modal-overlay { position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px }

.pige-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s;animation:fadeIn .3s ease }
.pige-card:hover { transform:translateY(-3px);box-shadow:0 8px 28px rgba(0,0,0,.25);border-color:var(--accent) }
.pige-card-flip { animation:cardFlip .4s ease }

.step-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text3);margin-bottom:8px;display:flex;align-items:center;gap:8px }
.step-num { background:var(--accent);color:#000;width:18px;height:18px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;flex-shrink:0 }

/* Items panneaux */
.panel-row { display:flex;align-items:center;gap:12px;padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;transition:background .12s }
.panel-row:last-child { border-bottom:none }
.panel-row:hover { background:rgba(232,160,32,.05) }
.panel-row.selected { background:rgba(232,160,32,.07) }
.panel-row input[type=checkbox] { accent-color:var(--accent);width:16px;height:16px;flex-shrink:0;cursor:pointer }
</style>

@push('scripts')
<script>
(function(){
'use strict';
const D = window.__PIGES__;

const STATUS_UI = {
    en_attente: { color:'#f97316', bg:'rgba(249,115,22,.12)', border:'rgba(249,115,22,.3)', label:'En attente' },
    verifie:    { color:'#22c55e', bg:'rgba(34,197,94,.12)',  border:'rgba(34,197,94,.3)',  label:'Vérifié'    },
    rejete:     { color:'#ef4444', bg:'rgba(239,68,68,.12)',  border:'rgba(239,68,68,.3)',  label:'Rejeté'     },
};
const CAMP_UI = {
    actif:   { color:'#22c55e', bg:'rgba(34,197,94,.08)',   border:'rgba(34,197,94,.25)',  icon:'📡' },
    pose:    { color:'#3b82f6', bg:'rgba(59,130,246,.08)',  border:'rgba(59,130,246,.25)', icon:'🔧' },
    termine: { color:'#6b7280', bg:'rgba(107,114,128,.08)', border:'rgba(107,114,128,.3)', icon:'✅' },
    annule:  { color:'#ef4444', bg:'rgba(239,68,68,.08)',   border:'rgba(239,68,68,.3)',   icon:'🚫' },
};

const _lbCache  = {};
let _rejectId   = null;
let _uploading  = false;
let _dbTimer    = null;

window.PG = {

    // ── Debounce recherche ────────────────────────────────────
    debounce() { clearTimeout(_dbTimer); _dbTimer = setTimeout(()=>document.getElementById('form-filters').submit(), 380); },

    // ── MODAL UPLOAD ─────────────────────────────────────────
    openUploadModal(campaignId = null) {
        document.getElementById('modal-upload').style.display = 'flex';
        if (campaignId) {
            const sel = document.getElementById('sel-campaign');
            if (sel) { sel.value = String(campaignId); this.onCampaignChange(); }
        }
    },

    closeUploadModal() {
        document.getElementById('modal-upload').style.display = 'none';
        document.getElementById('form-upload').reset();
        ['drop-preview','drop-info'].forEach(id => document.getElementById(id).style.display='none');
        document.getElementById('drop-placeholder').style.display = 'block';
        document.getElementById('drop-zone').style.borderColor = '';
        document.getElementById('upload-errors').style.display = 'none';
        document.getElementById('upload-progress').style.display = 'none';
        document.getElementById('ctx-alert').style.display = 'none';
        document.getElementById('panel-free').style.display = 'block';
        document.getElementById('panel-campaign').style.display = 'none';
        document.getElementById('panels-list').innerHTML = '';
        document.getElementById('panels-stats').textContent = '';
        document.getElementById('panels-select-all').style.display = 'none';
        const btn = document.getElementById('btn-submit');
        btn.disabled = false; btn.style.opacity = ''; btn.style.cursor = '';
        document.getElementById('btn-submit-text').textContent = 'Enregistrer la pige';
        _uploading = false;
    },

    // ── Changement de campagne → charger ses panneaux ────────
    async onCampaignChange() {
        const sel = document.getElementById('sel-campaign');
        const opt = sel?.options[sel.selectedIndex];
        const campaignId = sel?.value;
        const ctxAlert = document.getElementById('ctx-alert');
        const btn = document.getElementById('btn-submit');
        const freeWrap = document.getElementById('panel-free');
        const campWrap = document.getElementById('panel-campaign');

        ctxAlert.style.display = 'none';
        btn.disabled = false; btn.style.opacity = ''; btn.style.cursor = '';

        if (!campaignId) {
            freeWrap.style.display = 'block';
            campWrap.style.display = 'none';
            document.getElementById('panels-stats').textContent = '';
            document.getElementById('panels-select-all').style.display = 'none';
            return;
        }

        const isBlocked = opt?.dataset.blocked === '1';
        const status    = opt?.dataset.status;
        const ui        = CAMP_UI[status] || {};

        if (isBlocked) {
            ctxAlert.style.display = 'block';
            ctxAlert.style.cssText = `display:block;padding:10px 20px;border-bottom:1px solid var(--border);font-size:12px;font-weight:600;background:${ui.bg || '#111'};color:${ui.color || '#fff'};border-bottom-color:${ui.border || '#333'}`;
            ctxAlert.textContent = `${ui.icon || '🚫'} Campagne ${status === 'termine' ? 'terminée' : 'annulée'} — impossible d'ajouter des piges.`;
            btn.disabled = true; btn.style.opacity = '.4'; btn.style.cursor = 'not-allowed';
            freeWrap.style.display = 'none'; campWrap.style.display = 'none';
            return;
        }

        if (status !== 'actif') {
            ctxAlert.style.cssText = `display:block;padding:10px 20px;border-bottom:1px solid var(--border);font-size:12px;font-weight:600;background:${ui.bg};color:${ui.color};border-bottom-color:${ui.border}`;
            ctxAlert.textContent = `${ui.icon} Statut campagne : ${opt?.text?.match(/\(([^)]+)\)/)?.[1] || status} — vérifiez avec votre responsable avant d'uploader.`;
        }

        freeWrap.style.display = 'none';
        campWrap.style.display = 'block';
        document.getElementById('panels-loader').style.display = 'block';
        document.getElementById('panels-list').innerHTML = '';
        document.getElementById('panels-stats').textContent = '';
        document.getElementById('panels-select-all').style.display = 'none';

        try {
            const res = await fetch(`${D.panelsByCampaignUrl}?campaign_id=${campaignId}`, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const data = await res.json();
            this._renderPanelsList(data.panels, data.stats);
        } catch(err) {
            document.getElementById('panels-list').innerHTML =
                `<div style="padding:20px;text-align:center;color:#ef4444;font-size:12px">
                    Erreur de chargement.
                    <button onclick="PG.onCampaignChange()" style="background:none;border:none;color:#ef4444;text-decoration:underline;cursor:pointer">Réessayer</button>
                </div>`;
        } finally {
            document.getElementById('panels-loader').style.display = 'none';
        }
    },

    _renderPanelsList(panels, stats) {
        const list = document.getElementById('panels-list');
        const statsEl = document.getElementById('panels-stats');
        const selectAll = document.getElementById('panels-select-all');

        if (!panels || panels.length === 0) {
            list.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text3);font-size:12px">Cette campagne n\'a aucun panneau associé.</div>';
            return;
        }

        // Stats résumé
        statsEl.innerHTML = `
            <span style="color:#22c55e;font-weight:700">${stats.avec_pige} ✅</span>
            <span style="color:var(--text3);margin:0 4px">·</span>
            <span style="color:#f97316;font-weight:700">${stats.sans_pige} manquante(s)</span>
            ${stats.poses_done > 0 ? `<span style="color:var(--text3);margin:0 4px">·</span><span style="color:#3b82f6">${stats.poses_done} posé(s)</span>` : ''}
            ${stats.complete ? '<span style="color:#22c55e;margin-left:6px">— Complet ✓</span>' : ''}
        `;

        // Boutons sélection
        selectAll.style.display = 'flex';

        const frag = document.createDocumentFragment();
        panels.forEach(p => {
            const row = document.createElement('div');
            row.className = 'panel-row';
            row.dataset.panelId = p.id;

            // Badge pige
            let pigeBadge = '';
            if (p.has_pige) {
                const col = p.pige_status === 'verifie' ? '#22c55e' : '#f97316';
                const bg  = p.pige_status === 'verifie' ? 'rgba(34,197,94,.1)' : 'rgba(249,115,22,.1)';
                const ico = p.pige_status === 'verifie' ? '✅' : '⏳';
                pigeBadge = `<span style="padding:2px 8px;border-radius:12px;font-size:9px;font-weight:700;background:${bg};color:${col};white-space:nowrap;flex-shrink:0">${ico} ${p.pige_date || p.pige_status}</span>`;
            } else {
                pigeBadge = `<span style="padding:2px 8px;border-radius:12px;font-size:9px;font-weight:700;background:rgba(239,68,68,.08);color:#ef4444;white-space:nowrap;flex-shrink:0">❌ Manquante</span>`;
            }

            // Badge pose
            const poseBadge = p.pose_done
                ? `<span style="padding:2px 7px;border-radius:10px;font-size:9px;background:rgba(59,130,246,.1);color:#3b82f6;white-space:nowrap;flex-shrink:0">🔧 Posé</span>`
                : '';

            row.innerHTML = `
                <input type="checkbox" name="panel_ids[]" value="${p.id}" id="pchk-${p.id}" ${!p.has_pige ? 'checked' : ''}>
                <label for="pchk-${p.id}" style="flex:1;cursor:pointer;min-width:0">
                    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                        <span style="font-family:monospace;font-size:12px;font-weight:700;color:var(--accent)">${p.reference}</span>
                        <span style="font-size:12px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${p.name}</span>
                    </div>
                    <div style="font-size:10px;color:var(--text3);margin-top:1px">${p.commune}</div>
                </label>
                <div style="display:flex;gap:4px;flex-shrink:0">${poseBadge}${pigeBadge}</div>
            `;

            const chk = row.querySelector('input[type=checkbox]');
            row.addEventListener('click', e => {
                if (e.target !== chk) { chk.checked = !chk.checked; }
                row.classList.toggle('selected', chk.checked);
            });
            chk.addEventListener('change', () => row.classList.toggle('selected', chk.checked));
            if (chk.checked) row.classList.add('selected');

            frag.appendChild(row);
        });

        list.innerHTML = '';
        list.appendChild(frag);
    },

    // ── Sélection rapide des panneaux ─────────────────────────
    selectAllPanels(onlySansPige = false) {
        const chks = document.querySelectorAll('#panels-list input[type=checkbox]');
        chks.forEach(chk => {
            if (onlySansPige) {
                const row = chk.closest('.panel-row');
                // Vérifier si le badge dit "Manquante"
                const hasMissing = row?.querySelector('[style*="Manquante"]') !== null ||
                                   row?.textContent.includes('Manquante');
                chk.checked = hasMissing;
            } else {
                chk.checked = true;
            }
            chk.closest('.panel-row')?.classList.toggle('selected', chk.checked);
        });
    },

    // ── Preview photo ─────────────────────────────────────────
    previewPhoto(input) {
        const file = input.files?.[0];
        if (!file) return;
        if (file.size > 30 * 1024 * 1024) {
            this._showErr('La photo dépasse 30 Mo.');
            input.value = ''; return;
        }
        const allowed = ['image/jpeg','image/jpg','image/png','image/webp'];
        if (!allowed.includes(file.type)) {
            this._showErr('Format non supporté. JPEG, PNG ou WebP uniquement.');
            input.value = ''; return;
        }
        const r = new FileReader();
        r.onload = e => {
            const prev = document.getElementById('drop-preview');
            const info = document.getElementById('drop-info');
            prev.src = e.target.result; prev.style.display = 'block';
            document.getElementById('drop-placeholder').style.display = 'none';
            const kb = (file.size / 1024).toFixed(0);
            info.textContent = kb > 1024 ? (kb/1024).toFixed(1)+' Mo' : kb+' Ko';
            info.style.display = 'block';
            document.getElementById('drop-zone').style.borderColor = 'var(--accent)';
        };
        r.readAsDataURL(file);
    },

    handleDrop(e) {
        e.preventDefault();
        const file = e.dataTransfer?.files?.[0];
        if (!file?.type.startsWith('image/')) return;
        const inp = document.getElementById('inp-photo');
        const dt = new DataTransfer(); dt.items.add(file); inp.files = dt.files;
        this.previewPhoto(inp);
        e.currentTarget.style.borderColor = ''; e.currentTarget.style.background = '';
    },

    // ── Submit upload (multi-panneaux en séquence) ────────────
    async submitUpload() {
        if (_uploading) return;
        const errEl = document.getElementById('upload-errors');
        errEl.style.display = 'none';

        const campaignId = document.getElementById('sel-campaign')?.value;
        const photo      = document.getElementById('inp-photo')?.files?.[0];
        const takenAt    = document.querySelector('[name=taken_at]')?.value;

        // Validation client
        const errors = [];
        if (!photo)   errors.push('La photo est obligatoire.');
        if (!takenAt) errors.push('La date de prise de vue est obligatoire.');

        let panelIds = [];
        if (campaignId) {
            const checked = [...document.querySelectorAll('#panels-list input[type=checkbox]:checked')];
            panelIds = checked.map(c => c.value);
            if (panelIds.length === 0) errors.push('Sélectionnez au moins un panneau de la campagne.');
        } else {
            const freePanel = document.getElementById('sel-panel-free')?.value;
            if (!freePanel) errors.push('Sélectionnez un panneau.');
            else panelIds = [freePanel];
        }

        if (errors.length > 0) {
            errEl.innerHTML = errors.map(e => `<div>⚠️ ${e}</div>`).join('');
            errEl.style.display = 'block';
            return;
        }

        _uploading = true;
        const btn  = document.getElementById('btn-submit');
        const text = document.getElementById('btn-submit-text');
        btn.disabled = true;
        document.getElementById('upload-progress').style.display = 'block';

        let successCount = 0;
        const uploadErrors = [];

        for (let i = 0; i < panelIds.length; i++) {
            const pct = Math.round((i / panelIds.length) * 100);
            document.getElementById('progress-label').textContent = `Upload panneau ${i+1}/${panelIds.length}…`;
            document.getElementById('progress-bar').style.width  = pct + '%';
            document.getElementById('progress-pct').textContent  = pct + '%';
            text.textContent = `${i+1}/${panelIds.length} en cours…`;

            const fd = new FormData(document.getElementById('form-upload'));
            // Supprimer les champs panel_ids[] et remettre panel_id simple
            fd.delete('panel_ids[]');
            fd.set('panel_id', panelIds[i]);
            if (campaignId) fd.set('campaign_id', campaignId);

            try {
                const res = await fetch(D.uploadUrl, { method: 'POST', body: fd });
                let data = {};
                try { data = await res.json(); } catch {}
                if (!res.ok) {
                    let msg = data.message || `Erreur pour le panneau ${panelIds[i]}`;
                    if (data.errors) msg = Object.values(data.errors).flat().join(', ');
                    uploadErrors.push(msg);
                } else {
                    successCount++;
                }
            } catch(err) {
                uploadErrors.push(`Erreur réseau pour panneau ${panelIds[i]}.`);
            }
        }

        document.getElementById('progress-bar').style.width = '100%';
        document.getElementById('progress-pct').textContent = '100%';

        if (successCount > 0) {
            this.closeUploadModal();
            window.Toast?.success(`${successCount} pige(s) enregistrée(s) avec succès. ✅`);
            setTimeout(() => window.location.reload(), 700);
        } else {
            errEl.innerHTML = uploadErrors.map(e => `<div>⚠️ ${e}</div>`).join('');
            errEl.style.display = 'block';
            document.getElementById('upload-progress').style.display = 'none';
            btn.disabled = false; btn.style.opacity = ''; btn.style.cursor = '';
            text.textContent = 'Enregistrer la pige';
            _uploading = false;
        }
    },

    _showErr(msg) {
        const el = document.getElementById('upload-errors');
        el.innerHTML = `⚠️ ${msg}`; el.style.display = 'block';
    },

    // ── Lightbox ──────────────────────────────────────────────
    async openLightbox(id) {
        const lb = document.getElementById('lightbox');
        lb.style.display = 'flex';
        document.getElementById('lb-spinner').style.display = 'flex';
        document.getElementById('lb-photo').style.opacity = '0';
        ['lb-gps-badge','lb-reject-box','lb-verified-box'].forEach(i => document.getElementById(i).style.display='none');
        document.getElementById('lb-campaign-bar').style.display = 'none';
        document.getElementById('lb-actions').innerHTML = '<div style="color:var(--text3);font-size:12px;text-align:center;padding:8px">Chargement…</div>';
        try {
            const data = _lbCache[id] || await this._fetchPige(id);
            if (!_lbCache[id]) _lbCache[id] = data;
            this._renderLightbox(data);
        } catch {
            document.getElementById('lb-actions').innerHTML = `<div style="color:#ef4444;font-size:12px;text-align:center">Erreur. <button onclick="delete _lbCache[${id}];PG.openLightbox(${id})" style="background:none;border:none;color:#ef4444;text-decoration:underline;cursor:pointer">Réessayer</button></div>`;
        }
    },

    async _fetchPige(id) {
        const res = await fetch(`/admin/piges/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': D.csrf } });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    },

    _renderLightbox(data) {
        const el = id => document.getElementById(id);
        const img = el('lb-photo');
        img.onload  = () => { el('lb-spinner').style.display='none'; img.style.opacity='1'; };
        img.onerror = () => { el('lb-spinner').style.display='none'; img.style.opacity='1'; };
        img.src = data.photo_url;

        el('lb-id').textContent      = data.id;
        el('lb-panel').textContent   = (data.panel_ref||'—') + ' · ' + (data.panel_name||'—');
        el('lb-commune').textContent = data.commune    || '—';
        el('lb-campaign').textContent= data.campaign   || 'Sans campagne';
        el('lb-client').innerHTML    = data.client
            ? (data.client_deleted ? `${data.client} <span style="color:#ef4444;font-size:10px">(supprimé)</span>` : data.client)
            : '—';
        el('lb-date').textContent    = data.taken_at   || '—';
        el('lb-user').textContent    = data.taken_by   || '—';
        el('lb-notes').textContent   = data.notes      || 'Aucune note';

        const sui = STATUS_UI[data.status] || {};
        const badge = el('lb-status-badge');
        badge.textContent = sui.label || data.status;
        badge.style.cssText = `background:${sui.bg};color:${sui.color};border:1px solid ${sui.border};border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700`;

        if (data.campaign_status) {
            const cui = CAMP_UI[data.campaign_status] || {};
            const bar = el('lb-campaign-bar');
            bar.style.display = 'block';
            bar.style.background = cui.bg; bar.style.color = cui.color; bar.style.borderColor = cui.border;
            bar.textContent = `${cui.icon} Campagne : ${data.campaign_status_label || data.campaign_status}`;
        }

        if (data.has_gps) {
            el('lb-gps-text').textContent = `${data.gps_lat}, ${data.gps_lng}`;
            el('lb-maps-link').href = data.maps_url || '#';
            el('lb-gps-badge').style.display = 'block';
        }

        if (data.status === 'rejete' && data.rejection_reason) {
            el('lb-reject-reason').textContent = data.rejection_reason;
            el('lb-reject-box').style.display = 'block';
        }

        if (data.status === 'verifie' && data.verified_by) {
            el('lb-verified-info').textContent = `${data.verified_by} · ${data.verified_at||''}`;
            el('lb-verified-box').style.display = 'block';
        }

        let html = '';
        if (data.status === 'en_attente') {
            html += `<button onclick="PG.quickVerify(${data.id},this)" style="width:100%;padding:9px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">✅ Valider la pige</button>
            <button onclick="PG.closeLightbox();PG.openRejectModal(${data.id})" style="width:100%;padding:9px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">❌ Rejeter</button>`;
        }
        if (data.can_delete !== false) {
            html += `<button onclick="PG.deletePige(${data.id})" style="width:100%;padding:8px;background:transparent;border:1px solid rgba(239,68,68,.2);color:var(--text3);border-radius:10px;font-size:11px;cursor:pointer">🗑️ Supprimer</button>`;
        }
        html += `<a href="/admin/piges/${data.id}" style="display:block;text-align:center;padding:8px;color:var(--text3);text-decoration:none;font-size:12px;border:1px solid var(--border);border-radius:10px">Fiche complète →</a>`;
        el('lb-actions').innerHTML = html;
    },

    closeLightbox() { document.getElementById('lightbox').style.display = 'none'; },

    // ── Quick verify ─────────────────────────────────────────
    async quickVerify(pigeId, btn) {
        if (btn._verifying) return;
        btn._verifying = true;
        const orig = btn.innerHTML;
        btn.innerHTML = '⟳'; btn.disabled = true; btn.style.opacity = '.7';
        try {
            const res = await fetch(`/admin/piges/${pigeId}/verify`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': D.csrf, Accept: 'application/json' }
            });
            const data = await res.json();
            if (data.success || data.already) {
                delete _lbCache[pigeId];
                this._flashCard(pigeId, 'verifie');
                window.Toast?.success(data.message || 'Pige validée ✅');
                if (document.getElementById('lightbox').style.display !== 'none') this.closeLightbox();
            } else {
                window.Toast?.error(data.message || 'Erreur.');
                btn.innerHTML = orig; btn.disabled = false; btn.style.opacity = '';
            }
        } catch { window.Toast?.error('Erreur réseau.'); btn.innerHTML=orig; btn.disabled=false; btn.style.opacity=''; }
        finally { btn._verifying = false; }
    },

    _flashCard(pigeId, newStatus) {
        const card = document.querySelector(`.pige-card[data-id="${pigeId}"]`);
        if (!card) return;
        card.classList.add('pige-card-flip');
        setTimeout(() => {
            const badge = card.querySelector('[style*="border-radius:20px"]');
            if (badge) {
                if (newStatus === 'verifie') { badge.textContent='Vérifié'; badge.style.background='rgba(34,197,94,.88)'; }
                if (newStatus === 'rejete')  { badge.textContent='Rejeté';  badge.style.background='rgba(239,68,68,.88)'; }
            }
            const acts = card.querySelector('[style*="border-top:1px solid var(--border)"]');
            if (acts) acts.style.display = 'none';
            card.classList.remove('pige-card-flip');
        }, 200);
    },

    // ── Delete ────────────────────────────────────────────────
    async deletePige(pigeId) {
        if (!confirm('Supprimer définitivement cette pige ? Irréversible.')) return;
        try {
            const res = await fetch(`/admin/piges/${pigeId}`, { method:'DELETE', headers:{ Accept:'application/json','X-CSRF-TOKEN':D.csrf } });
            const data = await res.json();
            if (data.success) {
                this.closeLightbox();
                const card = document.querySelector(`.pige-card[data-id="${pigeId}"]`);
                if (card) { card.style.animation='fadeIn .3s ease reverse'; setTimeout(()=>card.remove(),280); }
                window.Toast?.success(data.message);
            } else { window.Toast?.error(data.message); }
        } catch { window.Toast?.error('Erreur réseau.'); }
    },

    // ── Rejet modal ───────────────────────────────────────────
    openRejectModal(pigeId) {
        _rejectId = pigeId;
        document.getElementById('reject-input').value = '';
        document.getElementById('reject-errors').style.display = 'none';
        document.getElementById('reject-counter').textContent = '0 / 500';
        document.getElementById('modal-reject').style.display = 'flex';
        setTimeout(() => document.getElementById('reject-input').focus(), 50);
    },

    closeRejectModal() { document.getElementById('modal-reject').style.display = 'none'; _rejectId = null; },

    async submitReject() {
        const reason = document.getElementById('reject-input').value.trim();
        const errEl  = document.getElementById('reject-errors');
        const btn    = document.getElementById('btn-reject-confirm');
        errEl.style.display = 'none';
        if (!reason || reason.length < 5) { errEl.textContent='Le motif doit faire au moins 5 caractères.'; errEl.style.display='block'; return; }
        if (reason.length > 500) { errEl.textContent='500 caractères maximum.'; errEl.style.display='block'; return; }
        if (!_rejectId) return;
        const orig = btn.innerHTML; btn.disabled=true; btn.style.opacity='.6'; btn.innerHTML='⟳ Traitement…';
        try {
            const res = await fetch(`/admin/piges/${_rejectId}/reject`, {
                method:'POST', headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':D.csrf},
                body: JSON.stringify({ rejection_reason: reason })
            });
            const data = await res.json();
            if (data.success || data.already) {
                delete _lbCache[_rejectId];
                this._flashCard(_rejectId, 'rejete');
                this.closeRejectModal();
                window.Toast?.warning(data.message);
            } else { errEl.textContent = data.message||'Erreur.'; errEl.style.display='block'; }
        } catch { errEl.textContent='Erreur réseau. Réessayez.'; errEl.style.display='block'; }
        finally { btn.disabled=false; btn.style.opacity=''; btn.innerHTML=orig; }
    },

    exportPdf() { window.open(D.exportPdfUrl+'?'+new URLSearchParams(window.location.search).toString(),'_blank'); },
};

document.getElementById('reject-input')?.addEventListener('input', function() {
    document.getElementById('reject-counter').textContent = this.value.length + ' / 500';
});

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    ['lightbox','modal-upload','modal-reject'].forEach(id => {
        const el = document.getElementById(id);
        if (el?.style.display !== 'none') {
            if (id==='lightbox') PG.closeLightbox();
            else if (id==='modal-upload') PG.closeUploadModal();
            else PG.closeRejectModal();
        }
    });
});
})();
</script>
@endpush
</x-admin-layout>
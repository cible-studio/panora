<x-admin-layout title="Piges Photos">

{{-- ════ DONNÉES SERVEUR ════ --}}
<script>
window.__PIGES__ = {
    uploadUrl:    '{{ route("admin.piges.upload") }}',
    exportPdfUrl: '{{ route("admin.piges.export-pdf") }}',
    csrf:         '{{ csrf_token() }}',
    campaigns:    {!! json_encode($campaigns->map(fn($c)=>['id'=>$c->id,'name'=>$c->name])) !!},
    clients:      {!! json_encode($clients->map(fn($c)=>['id'=>$c->id,'name'=>$c->name])) !!},
};
</script>

{{-- ════ STATS ════ --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
    @php
    $statCards = [
        ['label'=>'Total piges',   'val'=>$stats->total,      'icon'=>'📸', 'color'=>'#e8a020'],
        ['label'=>'En attente',    'val'=>$stats->en_attente, 'icon'=>'⏳', 'color'=>'#f97316'],
        ['label'=>'Vérifiées',     'val'=>$stats->verifie,    'icon'=>'✅', 'color'=>'#22c55e'],
        ['label'=>'Rejetées',      'val'=>$stats->rejete,     'icon'=>'❌', 'color'=>'#ef4444'],
    ];
    @endphp
    @foreach($statCards as $sc)
    <div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;border-left:3px solid {{ $sc['color'] }}">
        <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);margin-bottom:6px">
            {{ $sc['icon'] }} {{ $sc['label'] }}
        </div>
        <div style="font-size:26px;font-weight:800;color:{{ $sc['color'] }}">{{ number_format($sc['val']) }}</div>
    </div>
    @endforeach
</div>

{{-- ════ FILTRES ════ --}}
<div style="background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:16px 20px;margin-bottom:16px">
    <form id="form-filters" method="GET" action="{{ route('admin.piges.index') }}">

        {{-- Ligne 1 --}}
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px;align-items:end">
            {{-- Recherche --}}
            <div>
                <label class="filter-label">🔍 Recherche</label>
                <div style="position:relative">
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Panneau, campagne…"
                           class="filter-input" style="padding-left:36px"
                           oninput="PG.debounce()">
                    <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text3);font-size:13px;pointer-events:none">🔍</span>
                    @if(request('q'))
                    <button type="button" onclick="PG.clearSearch()"
                            style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text3);cursor:pointer;font-size:12px">✕</button>
                    @endif
                </div>
            </div>
            {{-- Client --}}
            <div>
                <label class="filter-label">🏢 Client</label>
                <select name="client_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous les clients</option>
                    @foreach($clients as $c)
                        <option value="{{ $c->id }}" {{ request('client_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Campagne --}}
            <div>
                <label class="filter-label">📢 Campagne</label>
                <select name="campaign_id" class="filter-select" onchange="this.form.submit()">
                    <option value="">Toutes</option>
                    @foreach($campaigns as $c)
                        <option value="{{ $c->id }}" {{ request('campaign_id') == $c->id ? 'selected' : '' }}>{{ Str::limit($c->name,25) }}</option>
                    @endforeach
                </select>
            </div>
            {{-- Statut --}}
            <div>
                <label class="filter-label">📊 Statut</label>
                <select name="status" class="filter-select" onchange="this.form.submit()">
                    <option value="">Tous</option>
                    <option value="en_attente" {{ request('status')==='en_attente' ? 'selected' : '' }}>⏳ En attente</option>
                    <option value="verifie"    {{ request('status')==='verifie'    ? 'selected' : '' }}>✅ Vérifiées</option>
                    <option value="rejete"     {{ request('status')==='rejete'     ? 'selected' : '' }}>❌ Rejetées</option>
                </select>
            </div>
            {{-- Actions --}}
            <div style="display:flex;gap:6px;align-items:flex-end">
                @if(request()->hasAny(['q','client_id','campaign_id','status','date_from','date_to']))
                <a href="{{ route('admin.piges.index') }}"
                   style="flex:1;padding:9px 8px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;text-align:center;font-size:12px;color:var(--text2);text-decoration:none" title="Réinitialiser">↺</a>
                @endif
                <button type="button" onclick="PG.openUploadModal()"
                        class="btn btn-primary" style="flex:1;white-space:nowrap;font-size:12px">
                    📸 Ajouter
                </button>
            </div>
        </div>

        {{-- Ligne 2 — Période --}}
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <span style="font-size:11px;font-weight:600;color:var(--text3);text-transform:uppercase">📅 Période :</span>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="filter-input" style="width:auto" onchange="this.form.submit()">
            <span style="color:var(--text3);font-size:12px">→</span>
            <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="filter-input" style="width:auto" onchange="this.form.submit()">
            {{-- Export PDF --}}
            @if(request()->hasAny(['campaign_id','client_id']))
            <div style="margin-left:auto">
                <button type="button" onclick="PG.exportPdf()"
                        style="padding:8px 14px;background:var(--surface);border:1px solid rgba(239,68,68,.4);color:#ef4444;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer">
                    📄 Rapport PDF client
                </button>
            </div>
            @endif
        </div>

    </form>
</div>

{{-- ════ RÉSULTATS INFO ════ --}}
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div style="font-size:12px;color:var(--text3)">
        <strong style="color:var(--text)">{{ number_format($piges->total()) }}</strong> pige(s) ·
        page {{ $piges->currentPage() }}/{{ $piges->lastPage() }}
    </div>
    @if($piges->hasPages())
    <div style="font-size:12px;color:var(--text3)">
        Affichage {{ $piges->firstItem() }}–{{ $piges->lastItem() }}
    </div>
    @endif
</div>

{{-- ════ GRILLE PHOTOS ════ --}}
@if($piges->isEmpty())
    <div style="text-align:center;padding:80px 20px;color:var(--text3)">
        <div style="font-size:56px;margin-bottom:12px">📸</div>
        <div style="font-size:16px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune pige trouvée</div>
        <div style="font-size:13px;margin-bottom:20px">Modifiez vos filtres ou ajoutez une nouvelle pige.</div>
        <button onclick="PG.openUploadModal()" class="btn btn-primary">📸 Ajouter une pige</button>
    </div>
@else
    <div id="pige-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;margin-bottom:24px">
        @foreach($piges as $pige)
        @php
        $statusColor = match($pige->status) {
            'verifie'    => '#22c55e',
            'rejete'     => '#ef4444',
            default      => '#f97316',
        };
        $statusLabel = match($pige->status) {
            'verifie'    => '✅ Vérifié',
            'rejete'     => '❌ Rejeté',
            default      => '⏳ En attente',
        };
        @endphp
        <div class="pige-card" data-id="{{ $pige->id }}" onclick="PG.openLightbox({{ $pige->id }})">

            {{-- Photo --}}
            <div style="position:relative;overflow:hidden;border-radius:10px 10px 0 0;height:150px;background:var(--surface2)">
                <img src="{{ asset('storage/' . $pige->photo_path) }}"
                     alt="Pige #{{ $pige->id }}"
                     loading="lazy"
                     style="width:100%;height:150px;object-fit:cover;display:block">

                {{-- Badge statut --}}
                <div style="position:absolute;top:8px;right:8px;padding:3px 10px;border-radius:20px;font-size:10px;font-weight:700;background:{{ $statusColor }};color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.3)">
                    {{ $statusLabel }}
                </div>

                {{-- Badge GPS --}}
                @if($pige->hasGps())
                <div style="position:absolute;bottom:8px;left:8px;padding:2px 7px;border-radius:6px;font-size:9px;background:rgba(0,0,0,.65);color:#fff;backdrop-filter:blur(4px)">
                    📍 GPS
                </div>
                @endif
            </div>

            {{-- Infos --}}
            <div style="padding:10px 12px">
                <div style="font-family:monospace;font-size:11px;font-weight:700;color:var(--accent);margin-bottom:3px">
                    {{ $pige->panel?->reference ?? '—' }}
                </div>
                <div style="font-size:12px;font-weight:600;color:var(--text);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $pige->panel?->name }}">
                    {{ Str::limit($pige->panel?->name ?? '—', 28) }}
                </div>
                <div style="font-size:11px;color:var(--text3);margin-bottom:6px">
                    📢 {{ Str::limit($pige->campaign?->name ?? 'Sans campagne', 22) }}
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div style="font-size:10px;color:var(--text3)">
                        📅 {{ $pige->taken_at?->format('d/m/Y') ?? '—' }}
                    </div>
                    <div style="font-size:10px;color:var(--text3)">
                        🧑 {{ Str::limit($pige->takenBy?->name ?? '—', 12) }}
                    </div>
                </div>

                {{-- Actions rapides --}}
                @if($pige->isPending())
                <div style="display:flex;gap:6px;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)" onclick="event.stopPropagation()">
                    <form method="POST" action="{{ route('admin.piges.verify', $pige) }}" style="flex:1">
                        @csrf
                        <button type="submit" style="width:100%;padding:5px 0;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer">
                            ✅ Valider
                        </button>
                    </form>
                    <button onclick="PG.openRejectModal({{ $pige->id }})"
                            style="flex:1;padding:5px 0;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:7px;font-size:11px;font-weight:600;cursor:pointer">
                        ❌ Rejeter
                    </button>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Pagination --}}
    @if($piges->hasPages())
    <div style="display:flex;justify-content:center;align-items:center;gap:8px;padding:12px 0">
        @if($piges->onFirstPage())
            <span class="btn btn-ghost btn-sm" style="opacity:.4">← Précédent</span>
        @else
            <a href="{{ $piges->previousPageUrl() }}" class="btn btn-ghost btn-sm">← Précédent</a>
        @endif
        <span style="font-size:12px;color:var(--text3)">{{ $piges->currentPage() }} / {{ $piges->lastPage() }}</span>
        @if($piges->hasMorePages())
            <a href="{{ $piges->nextPageUrl() }}" class="btn btn-ghost btn-sm">Suivant →</a>
        @else
            <span class="btn btn-ghost btn-sm" style="opacity:.4">Suivant →</span>
        @endif
    </div>
    @endif
@endif

{{-- ══════════════════════════════════
     LIGHTBOX — Détail d'une pige
══════════════════════════════════ --}}
<div id="lightbox" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeLightbox()">
    <div class="modal-box" style="max-width:900px;display:flex;gap:0;padding:0;overflow:hidden" onclick="event.stopPropagation()">

        {{-- Photo gauche --}}
        <div style="flex:1.4;background:#000;min-height:500px;display:flex;align-items:center;justify-content:center;position:relative">
            <img id="lb-photo" src="" alt="Pige" style="max-width:100%;max-height:560px;object-fit:contain;display:block">
            <div id="lb-gps-badge" style="position:absolute;bottom:12px;left:12px;background:rgba(0,0,0,.7);color:#fff;padding:5px 10px;border-radius:8px;font-size:11px;display:none">
                📍 <span id="lb-gps"></span>
                <a id="lb-maps-link" href="#" target="_blank" style="color:#e8a020;margin-left:6px;text-decoration:none">Voir sur Maps →</a>
            </div>
        </div>

        {{-- Infos droite --}}
        <div style="width:280px;flex-shrink:0;padding:20px;overflow-y:auto;background:var(--surface)">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:16px">
                <div>
                    <div style="font-size:11px;color:var(--text3);margin-bottom:2px">PIGE #<span id="lb-id"></span></div>
                    <div id="lb-status-badge" style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700"></div>
                </div>
                <button onclick="PG.closeLightbox()" style="background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:6px 10px;color:var(--text2);cursor:pointer">✕</button>
            </div>

            {{-- Infos --}}
            <div style="space-y:0">
                @foreach([
                    ['PANNEAU',   'lb-panel'],
                    ['COMMUNE',   'lb-commune'],
                    ['CAMPAGNE',  'lb-campaign'],
                    ['CLIENT',    'lb-client'],
                    ['PRISE LE',  'lb-date'],
                    ['TECHNICIEN','lb-user'],
                    ['NOTES',     'lb-notes'],
                ] as [$label, $id])
                <div style="margin-bottom:12px">
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:var(--text3);margin-bottom:2px">{{ $label }}</div>
                    <div id="{{ $id }}" style="font-size:13px;color:var(--text);font-weight:500">—</div>
                </div>
                @endforeach
            </div>

            {{-- Actions --}}
            <div id="lb-actions" style="margin-top:16px;border-top:1px solid var(--border);padding-top:14px;display:flex;flex-direction:column;gap:8px"></div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════
     MODAL UPLOAD
══════════════════════════════════ --}}
<div id="modal-upload" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeUploadModal()">
    <div class="modal-box" style="max-width:500px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div>
                <div class="modal-title">📸 Nouvelle pige</div>
                <div style="font-size:11px;color:var(--text3)">Preuve d'affichage terrain</div>
            </div>
            <button onclick="PG.closeUploadModal()" class="modal-close">✕</button>
        </div>

        <form id="form-upload" enctype="multipart/form-data" onsubmit="PG.submitUpload(event)" class="modal-body">

            @csrf

            {{-- Preview photo --}}
            <div id="photo-preview-zone"
                 style="border:2px dashed var(--border);border-radius:14px;height:160px;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-bottom:16px;overflow:hidden;position:relative;transition:border-color .2s"
                 onclick="document.getElementById('input-photo').click()"
                 ondragover="event.preventDefault();this.style.borderColor='var(--accent)'"
                 ondrop="PG.handleDrop(event)">
                <div id="photo-placeholder" style="text-align:center;color:var(--text3)">
                    <div style="font-size:36px;margin-bottom:6px">📷</div>
                    <div style="font-size:12px">Cliquez ou glissez une photo</div>
                    <div style="font-size:10px;color:var(--text3);margin-top:3px">JPEG, PNG, WEBP · Max 8 Mo</div>
                </div>
                <img id="photo-preview-img" src="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:12px">
            </div>
            <input type="file" id="input-photo" name="photo" accept="image/*" style="display:none" onchange="PG.previewPhoto(this)">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
                <div>
                    <label class="filter-label">Panneau *</label>
                    <select name="panel_id" required class="filter-select w-full">
                        <option value="">— Sélectionner —</option>
                        @foreach($panels as $p)
                            <option value="{{ $p->id }}">{{ $p->reference }} · {{ Str::limit($p->name,20) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="filter-label">Campagne</label>
                    <select name="campaign_id" class="filter-select w-full">
                        <option value="">Sans campagne</option>
                        @foreach($campaigns as $c)
                            <option value="{{ $c->id }}" {{ request('campaign_id') == $c->id ? 'selected' : '' }}>{{ Str::limit($c->name,25) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:12px">
                <div>
                    <label class="filter-label">Date prise *</label>
                    <input type="date" name="taken_at" required class="filter-input" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}">
                </div>
                <div>
                    <label class="filter-label">GPS Lat</label>
                    <input type="number" name="gps_lat" step="0.0000001" class="filter-input" placeholder="5.3401">
                </div>
                <div>
                    <label class="filter-label">GPS Lng</label>
                    <input type="number" name="gps_lng" step="0.0000001" class="filter-input" placeholder="-4.0263">
                </div>
            </div>

            <div style="margin-bottom:16px">
                <label class="filter-label">Notes</label>
                <textarea name="notes" rows="2" class="filter-input" style="resize:none" placeholder="Observations sur l'état du visuel…"></textarea>
            </div>

            {{-- Erreurs --}}
            <div id="upload-errors" class="hidden bg-red-500/10 border border-red-500/30 rounded-xl p-3 text-sm text-red-400 mb-3"></div>

            <div style="display:flex;justify-content:space-between;align-items:center">
                <button type="button" onclick="PG.closeUploadModal()" class="btn btn-ghost btn-sm">Annuler</button>
                <button type="submit" id="btn-upload-submit" class="btn btn-primary">
                    <span id="upload-icon">📸</span>
                    <span id="upload-txt">Enregistrer la pige</span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ══════════════════════════════════
     MODAL REJET
══════════════════════════════════ --}}
<div id="modal-reject" class="modal-overlay" style="display:none" onclick="if(event.target===this)PG.closeRejectModal()">
    <div class="modal-box" style="max-width:420px" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">❌ Rejeter la pige</div>
            <button onclick="PG.closeRejectModal()" class="modal-close">✕</button>
        </div>
        <form id="form-reject" method="POST" class="modal-body" onsubmit="PG.submitReject(event)">
            @csrf
            <div style="background:rgba(239,68,68,.05);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:12px;margin-bottom:14px;font-size:12px;color:#ef4444">
                ⚠️ Le technicien devra soumettre une nouvelle photo.
            </div>
            <div>
                <label class="filter-label">Motif de rejet *</label>
                <textarea name="rejection_reason" rows="3" required class="filter-input" style="resize:none;margin-top:4px"
                          placeholder="Ex: Photo floue, mauvais angle, panneau non visible, visuel absent…"></textarea>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:14px">
                <button type="button" onclick="PG.closeRejectModal()" class="btn btn-ghost btn-sm">Annuler</button>
                <button type="submit" style="padding:8px 18px;background:#ef4444;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                    ❌ Confirmer le rejet
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ════ STYLES ════ --}}
<style>
.filter-label { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text3);display:block;margin-bottom:4px; }
.filter-input { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);transition:border-color .2s; }
.filter-input:focus { border-color:var(--accent);outline:none; }
.filter-select { width:100%;height:40px;padding:0 12px;background:var(--surface2);border:1px solid var(--border);border-radius:10px;font-size:13px;color:var(--text);cursor:pointer; }
.filter-select:focus { border-color:var(--accent);outline:none; }

.pige-card { background:var(--surface);border:1px solid var(--border);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .15s,box-shadow .15s,border-color .15s; }
.pige-card:hover { transform:translateY(-3px);box-shadow:0 8px 24px rgba(0,0,0,.25);border-color:var(--accent); }

.modal-overlay { position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.75);backdrop-filter:blur(4px);display:flex;align-items:center;justify-content:center;padding:16px; }
.modal-box { background:var(--surface);border:1px solid var(--border);border-radius:20px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.5); }
.modal-header { display:flex;justify-content:space-between;align-items:center;padding:18px 20px;border-bottom:1px solid var(--border);background:var(--surface2);border-radius:20px 20px 0 0; }
.modal-title { font-weight:700;font-size:14px;color:var(--text); }
.modal-body { padding:20px; }
.modal-close { width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--surface3);border:1px solid var(--border);border-radius:8px;color:var(--text3);cursor:pointer; }
.modal-close:hover { color:#ef4444;border-color:rgba(239,68,68,.4); }
.hidden { display:none!important; }
</style>

{{-- ════ JAVASCRIPT ════ --}}
@push('scripts')
<script>
(function(){
'use strict';
const D = window.__PIGES__;

// Cache piges data pour lightbox
const pigesCache = {};

window.PG = {

    // ── SEARCH DEBOUNCE ──
    _st: null,
    debounce(){
        clearTimeout(this._st);
        this._st = setTimeout(() => document.getElementById('form-filters').submit(), 400);
    },
    clearSearch(){
        document.querySelector('[name="q"]').value = '';
        document.getElementById('form-filters').submit();
    },

    // ── UPLOAD MODAL ──
    openUploadModal(){
        document.getElementById('modal-upload').style.display = 'flex';
    },
    closeUploadModal(){
        document.getElementById('modal-upload').style.display = 'none';
        document.getElementById('form-upload').reset();
        document.getElementById('photo-preview-img').style.display = 'none';
        document.getElementById('photo-placeholder').style.display = 'block';
        document.getElementById('upload-errors').classList.add('hidden');
    },
    previewPhoto(input){
        if (!input.files?.[0]) return;
        const file = input.files[0];
        if (file.size > 8 * 1024 * 1024) {
            this.showUploadError('La photo ne doit pas dépasser 8 Mo.');
            input.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photo-preview-img').src = e.target.result;
            document.getElementById('photo-preview-img').style.display = 'block';
            document.getElementById('photo-placeholder').style.display = 'none';
        };
        reader.readAsDataURL(file);
    },
    handleDrop(e){
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (!file?.type.startsWith('image/')) return;
        const input = document.getElementById('input-photo');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        this.previewPhoto(input);
    },
    async submitUpload(e){
        e.preventDefault();
        const btn = document.getElementById('btn-upload-submit');
        document.getElementById('upload-errors').classList.add('hidden');
        document.getElementById('upload-icon').textContent = '⟳';
        document.getElementById('upload-txt').textContent  = 'Upload en cours…';
        btn.disabled = true;

        try {
            const fd = new FormData(document.getElementById('form-upload'));
            fd.set('_token', D.csrf);
            const res = await fetch(D.uploadUrl, { method:'POST', body:fd });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                const msgs = data.errors
                    ? Object.values(data.errors).flat()
                    : [data.message || 'Erreur lors de l\'upload.'];
                this.showUploadError(msgs.join('<br>'));
                return;
            }
            this.closeUploadModal();
            window.location.reload();
        } catch(err) {
            this.showUploadError('Erreur réseau : ' + err.message);
        } finally {
            document.getElementById('upload-icon').textContent = '📸';
            document.getElementById('upload-txt').textContent  = 'Enregistrer la pige';
            btn.disabled = false;
        }
    },
    showUploadError(msg){
        const el = document.getElementById('upload-errors');
        el.innerHTML = '⚠️ ' + msg;
        el.classList.remove('hidden');
    },

    // ── LIGHTBOX ──
    openLightbox(id){
        // Récupère les données depuis le DOM de la card
        const card   = document.querySelector(`.pige-card[data-id="${id}"]`);
        const imgSrc = card?.querySelector('img')?.src || '';

        // Chercher les infos textuelles de la card
        const ref      = card?.querySelector('[style*="monospace"]')?.textContent?.trim() || '—';
        const panelNm  = card?.querySelectorAll('[style*="font-weight:600"]')?.[0]?.textContent?.trim() || '—';
        const campNm   = card?.querySelector('[style*="camp"]')?.textContent?.trim() || '—';

        document.getElementById('lb-photo').src = imgSrc;
        document.getElementById('lb-id').textContent       = id;
        document.getElementById('lb-panel').textContent    = ref + ' · ' + panelNm;

        // Fetch les données complètes
        fetch(`/admin/piges/${id}`, { headers:{ Accept:'application/json', 'X-CSRF-TOKEN':D.csrf } })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                const el = (sid) => document.getElementById(sid);
                el('lb-id').textContent       = data.id;
                el('lb-panel').textContent    = (data.panel_ref || '—') + ' · ' + (data.panel_name || '—');
                el('lb-commune').textContent  = data.commune    || '—';
                el('lb-campaign').textContent = data.campaign   || 'Sans campagne';
                el('lb-client').textContent   = data.client     || '—';
                el('lb-date').textContent     = data.taken_at   || '—';
                el('lb-user').textContent     = data.taken_by   || '—';
                el('lb-notes').textContent    = data.notes || 'Aucune note';

                // GPS
                if (data.gps_lat && data.gps_lng) {
                    el('lb-gps').textContent = data.gps_lat + ', ' + data.gps_lng;
                    el('lb-maps-link').href  = `https://maps.google.com/?q=${data.gps_lat},${data.gps_lng}`;
                    el('lb-gps-badge').style.display = 'block';
                }

                // Status badge
                const colors = { verifie:'#22c55e', rejete:'#ef4444', en_attente:'#f97316' };
                const labels = { verifie:'✅ Vérifié', rejete:'❌ Rejeté', en_attente:'⏳ En attente' };
                const badge  = el('lb-status-badge');
                badge.textContent        = labels[data.status] || data.status;
                badge.style.background   = (colors[data.status] || '#f97316') + '22';
                badge.style.color        = colors[data.status] || '#f97316';
                badge.style.border       = '1px solid ' + (colors[data.status] || '#f97316') + '44';
                badge.style.borderRadius = '20px';
                badge.style.padding      = '3px 10px';
                badge.style.fontSize     = '11px';
                badge.style.fontWeight   = '700';

                // Actions
                const actEl = el('lb-actions');
                actEl.innerHTML = '';
                if (data.status === 'en_attente') {
                    actEl.innerHTML = `
                        <form method="POST" action="/admin/piges/${data.id}/verify">
                            <input type="hidden" name="_token" value="${D.csrf}">
                            <button type="submit" style="width:100%;padding:8px;background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.3);color:#22c55e;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">
                                ✅ Valider la pige
                            </button>
                        </form>
                        <button onclick="PG.closeLightbox();PG.openRejectModal(${data.id})"
                                style="width:100%;padding:8px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.25);color:#ef4444;border-radius:10px;font-size:12px;font-weight:700;cursor:pointer">
                            ❌ Rejeter
                        </button>`;
                }
                if (data.status === 'rejete' && data.rejection_reason) {
                    actEl.innerHTML = `<div style="font-size:11px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);padding:10px;border-radius:10px;color:#ef4444">
                        <strong>Motif rejet :</strong><br>${data.rejection_reason}
                    </div>`;
                }
                actEl.innerHTML += `<a href="/admin/piges/${data.id}" style="display:block;text-align:center;padding:8px;color:var(--text3);text-decoration:none;font-size:12px;border:1px solid var(--border);border-radius:10px">
                    Voir la fiche complète →
                </a>`;
            })
            .catch(() => {});

        document.getElementById('lightbox').style.display = 'flex';
    },
    closeLightbox(){
        document.getElementById('lightbox').style.display = 'none';
        document.getElementById('lb-gps-badge').style.display = 'none';
    },

    // ── REJECT MODAL ──
    _rejectId: null,
    openRejectModal(id){
        this._rejectId = id;
        document.getElementById('form-reject').dataset.pigeId = id;
        document.getElementById('modal-reject').style.display = 'flex';
    },
    closeRejectModal(){
        document.getElementById('modal-reject').style.display = 'none';
        document.querySelector('#form-reject textarea').value = '';
    },
    async submitReject(e){
        e.preventDefault();
        const id     = this._rejectId;
        const reason = document.querySelector('#form-reject textarea').value.trim();
        if (!reason || reason.length < 5) {
            alert('Le motif de rejet doit faire au moins 5 caractères.');
            return;
        }
        try {
            await fetch(`/admin/piges/${id}/reject`, {
                method: 'POST',
                headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN':D.csrf },
                body: JSON.stringify({ rejection_reason: reason }),
            });
            this.closeRejectModal();
            window.location.reload();
        } catch(err) { alert('Erreur réseau.'); }
    },

    // ── EXPORT PDF ──
    exportPdf(){
        const params = new URLSearchParams(window.location.search);
        const url    = D.exportPdfUrl + '?' + params.toString();
        window.open(url, '_blank');
    },
};

// ESC key
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        PG.closeLightbox();
        PG.closeUploadModal();
        PG.closeRejectModal();
    }
});

})();
</script>
@endpush

</x-admin-layout>
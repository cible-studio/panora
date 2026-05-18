<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Pige — {{ $campaign->name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: #f1f5f9; color: #0f172a;
        font-size: 14px; line-height: 1.5;
        -webkit-font-smoothing: antialiased;
        padding-bottom: 80px;
    }

    .wrap { max-width: 800px; margin: 0 auto; padding: 0 14px; }

    /* HEADER avec logo CIBLE */
    .header {
        background: #0a0c10; color: #fff;
        padding: 14px 16px; margin-bottom: 14px;
        border-bottom: 3px solid #e8a020;
    }
    .header-inner {
        max-width: 800px; margin: 0 auto;
        display: flex; align-items: center; gap: 14px;
    }
    .header img { height: 56px; }
    @media (max-width: 480px) { .header img { height: 48px; } }
    .header-meta { flex: 1; min-width: 0; }
    .header-meta .badge {
        display: inline-block;
        font-size: 9px; font-weight: 700; color: #e8a020;
        text-transform: uppercase; letter-spacing: 1.5px;
        background: rgba(232,160,32,.1);
        padding: 2px 8px; border-radius: 10px;
    }
    .header-meta h1 {
        font-size: 13px; font-weight: 600; color: #fff;
        margin-top: 4px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* CAMPAGNE INFO COMPACT */
    .campaign-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 14px 16px;
        margin-bottom: 12px;
        border-left: 4px solid #e8a020;
    }
    .campaign-card .lbl {
        font-size: 9px; font-weight: 700; color: #94a3b8;
        text-transform: uppercase; letter-spacing: 1.5px;
    }
    .campaign-card h2 {
        font-size: 16px; font-weight: 800; color: #0f172a;
        margin: 4px 0 6px;
    }
    .campaign-card .meta {
        font-size: 11.5px; color: #64748b;
        display: flex; flex-wrap: wrap; gap: 10px;
    }

    .closed-banner {
        background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5;
        border-radius: 10px; padding: 14px 16px;
        margin-bottom: 14px; font-size: 13px; font-weight: 600;
    }

    /* STICKY TOOLBAR : tech + filtres + recherche */
    .toolbar-sticky {
        position: sticky; top: 0; z-index: 50;
        background: #f1f5f9;
        padding: 8px 0; margin: 0 -14px 12px;
        padding-left: 14px; padding-right: 14px;
        border-bottom: 1px solid #e2e8f0;
    }
    .tech-row, .search-row, .filter-row {
        display: flex; align-items: center; gap: 8px;
        margin-bottom: 8px;
    }
    .tech-row label, .search-row label {
        font-size: 10px; font-weight: 700; color: #64748b;
        text-transform: uppercase; letter-spacing: 1px;
        flex-shrink: 0;
    }
    .tech-row input, .search-row input {
        flex: 1; padding: 9px 12px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 8px; font-size: 13px; color: #0f172a;
        outline: none;
    }
    .tech-row input:focus, .search-row input:focus { border-color: #e8a020; }

    .filter-row { flex-wrap: wrap; gap: 6px; margin-bottom: 0; }
    .filter-chip {
        padding: 6px 10px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 16px; font-size: 11.5px; font-weight: 600;
        color: #475569; cursor: pointer;
        white-space: nowrap;
        transition: all .15s;
    }
    .filter-chip:hover { border-color: #cbd5e1; }
    .filter-chip.active {
        background: #e8a020; color: #0a0c10;
        border-color: #e8a020;
    }
    .filter-chip .count {
        display: inline-block; padding: 0 6px;
        background: rgba(0,0,0,.08); border-radius: 8px;
        margin-left: 4px; font-size: 10px;
    }
    .filter-chip.active .count { background: rgba(0,0,0,.15); }

    /* PROGRESS GLOBAL */
    .progress-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 12px 16px;
        margin-bottom: 12px;
    }
    .progress-card .head {
        display: flex; justify-content: space-between;
        font-size: 12px; margin-bottom: 6px;
    }
    .progress-bar {
        height: 7px; background: #f1f5f9; border-radius: 6px; overflow: hidden;
    }
    .progress-bar > div {
        height: 100%; background: linear-gradient(90deg, #e8a020, #f97316);
        transition: width .4s ease;
    }

    /* COMMUNE GROUP */
    .commune-group {
        margin-bottom: 14px;
    }
    .commune-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 10px 14px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 10px;
        cursor: pointer; user-select: none;
        margin-bottom: 6px;
        transition: background .15s;
    }
    .commune-header:hover { background: #f8fafc; }
    .commune-header h3 {
        font-size: 13px; font-weight: 700; color: #0f172a;
        display: flex; align-items: center; gap: 8px;
    }
    .commune-header .count-pill {
        font-size: 10px; padding: 2px 8px;
        background: rgba(232,160,32,.12); color: #c2570d;
        border-radius: 10px; font-weight: 700;
    }
    .commune-header .arrow {
        font-size: 14px; color: #64748b;
        transition: transform .2s;
    }
    .commune-group.collapsed .arrow { transform: rotate(-90deg); }
    .commune-group.collapsed .commune-body { display: none; }
    .commune-body { display: flex; flex-direction: column; gap: 8px; padding-top: 4px; }

    /* PANEL CARD */
    .panel-card {
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 10px; overflow: hidden;
    }
    .panel-card.is-done { border-left: 3px solid #22c55e; }
    .panel-card.is-pending { border-left: 3px solid #3b82f6; }
    .panel-card.is-rejected { border-left: 3px solid #ef4444; }

    .panel-head {
        padding: 10px 14px;
        display: flex; align-items: center; justify-content: space-between; gap: 8px;
    }
    .panel-ref {
        font-family: monospace, 'Courier New', sans-serif;
        font-weight: 700; color: #c2570d; font-size: 12px;
    }
    .panel-name {
        font-weight: 600; color: #0f172a; font-size: 13px;
        margin-top: 1px;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .panel-meta { font-size: 11px; color: #64748b; }
    .panel-status {
        display: inline-block; padding: 3px 9px;
        border-radius: 12px; font-size: 10px; font-weight: 700;
        flex-shrink: 0;
    }
    .panel-status-todo    { background: #fef3c7; color: #b45309; }
    .panel-status-pending { background: #dbeafe; color: #1d4ed8; }
    .panel-status-done    { background: #dcfce7; color: #166534; }
    .panel-status-rejected{ background: #fee2e2; color: #b91c1c; }

    /* Status stack (pose + pige côte à côte) */
    .status-stack { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
    .pose-pill {
        display: inline-block; padding: 2px 8px;
        border-radius: 11px; font-size: 9.5px; font-weight: 700;
        white-space: nowrap;
    }
    .pose-todo { background: #fef3c7; color: #92400e; }
    .pose-done { background: #dcfce7; color: #15803d; }

    /* Action row : 2 boutons côte à côte (pose + photo) */
    .action-row {
        display: flex; gap: 8px; flex-wrap: wrap;
    }
    .action-btn {
        flex: 1; min-width: 130px;
        padding: 11px 12px;
        font-weight: 700; font-size: 13px;
        border: none; border-radius: 10px;
        cursor: pointer; text-align: center;
        transition: all .15s;
    }
    .action-btn:disabled { opacity: .5; cursor: not-allowed; }
    .pose-btn {
        background: #22c55e; color: #fff;
    }
    .pose-btn:hover { background: #16a34a; }
    .upload-btn {
        background: #e8a020; color: #0a0c10;
    }
    .upload-btn:hover { background: #d4910f; }
    .pose-done-tag {
        flex: 1; min-width: 130px;
        padding: 11px 12px;
        font-weight: 700; font-size: 13px;
        text-align: center;
        background: rgba(34,197,94,.12); color: #15803d;
        border: 1px solid rgba(34,197,94,.3);
        border-radius: 10px;
    }

    .panel-body { padding: 0 14px 12px; }
    /* .upload-btn déjà défini plus haut dans .action-btn / .upload-btn */
    .upload-btn:disabled { background: #cbd5e1; color: #fff; cursor: not-allowed; }

    /* PIGE TILES déjà prises */
    .pige-list {
        display: flex; gap: 6px; overflow-x: auto;
        padding: 4px 0; margin-top: 8px;
    }
    .pige-thumb {
        position: relative; flex-shrink: 0;
        width: 64px; height: 64px; border-radius: 6px;
        overflow: hidden; background: #f1f5f9;
        border: 1px solid #e2e8f0;
        cursor: pointer;
    }
    .pige-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .pige-thumb .badge {
        position: absolute; top: 3px; right: 3px;
        font-size: 9px; padding: 1px 5px; border-radius: 6px;
        background: rgba(255,255,255,.95); color: #0f172a;
        font-weight: 700;
    }

    /* Bandeau rejet : motif visible pour que le tech sache quoi corriger */
    .rejet-banner {
        margin-top: 10px;
        background: #fee2e2;
        border: 1px solid rgba(220,38,38,.3);
        border-left: 3px solid #dc2626;
        border-radius: 8px;
        padding: 8px 10px;
    }
    .rejet-banner-title {
        font-size: 11px;
        font-weight: 800;
        color: #dc2626;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .3px;
    }
    .rejet-banner-reason {
        font-size: 13px;
        color: #0f172a;
        line-height: 1.4;
        font-style: italic;
        margin-top: 2px;
    }

    /* MODAL UPLOAD — photo GRANDE */
    .modal {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.85); z-index: 1000;
        align-items: flex-start; justify-content: center;
        padding: 0;
    }
    .modal.open { display: flex; }
    .modal-content {
        background: #fff; max-width: 720px; width: 100%;
        min-height: 100vh;
        padding: 20px;
        display: flex; flex-direction: column;
    }
    .modal h3 { font-size: 16px; font-weight: 700; margin-bottom: 16px; padding-right: 30px; }
    .modal-close {
        position: absolute; top: 16px; right: 16px;
        background: rgba(0,0,0,.05); border: none;
        width: 32px; height: 32px; border-radius: 16px;
        cursor: pointer; font-size: 16px; color: #64748b;
    }

    .field { margin-bottom: 14px; }
    .field label {
        display: block; font-size: 11px; font-weight: 700;
        color: #64748b; text-transform: uppercase;
        letter-spacing: .8px; margin-bottom: 5px;
    }
    .field input,
    .field textarea {
        width: 100%; padding: 11px 13px;
        background: #f8fafc; border: 1px solid #e2e8f0;
        border-radius: 8px; font-size: 14px; color: #0f172a;
        outline: none; font-family: inherit;
    }
    .field input:focus,
    .field textarea:focus { border-color: #e8a020; }
    .field input[type="file"] {
        padding: 14px; cursor: pointer;
        background: #fefce8; border: 2px dashed #facc15;
        font-weight: 600; color: #854d0e;
    }

    /* PREVIEW PHOTO LARGE */
    .preview-wrap {
        position: relative;
        width: 100%; min-height: 200px; max-height: 70vh;
        background: #f8fafc; border-radius: 10px;
        margin-bottom: 14px;
        display: none;
        overflow: hidden;
    }
    .preview-wrap.shown { display: block; }
    .preview-wrap img {
        width: 100%; height: auto; max-height: 70vh;
        object-fit: contain;
        display: block;
    }
    .preview-empty {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 60px 20px; color: #94a3b8;
        background: #f8fafc; border: 2px dashed #e2e8f0;
        border-radius: 10px; margin-bottom: 14px;
        font-size: 13px; text-align: center;
    }
    .preview-empty .icon { font-size: 38px; margin-bottom: 8px; }

    .gps-info {
        font-size: 12px; color: #64748b;
        background: #f8fafc; padding: 8px 12px;
        border-radius: 6px; margin-bottom: 12px;
        display: flex; align-items: center; gap: 6px;
    }
    .gps-info.ok { color: #16a34a; background: #dcfce7; }

    .modal-actions {
        display: flex; gap: 8px; justify-content: flex-end;
        margin-top: auto; padding-top: 14px;
        border-top: 1px solid #f1f5f9;
        position: sticky; bottom: 0; background: #fff;
    }
    .btn-cancel {
        padding: 11px 18px; background: #f1f5f9; color: #475569;
        border: 1px solid #e2e8f0; border-radius: 8px;
        cursor: pointer; font-size: 13px; font-weight: 600;
    }

    /* LIGHTBOX pour zoomer une miniature */
    .lightbox {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,.92); z-index: 2000;
        align-items: center; justify-content: center; padding: 16px;
    }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 100%; max-height: 95vh; object-fit: contain; }
    .lightbox-close {
        position: absolute; top: 14px; right: 14px;
        background: rgba(255,255,255,.15); color: #fff;
        border: none; width: 36px; height: 36px; border-radius: 18px;
        cursor: pointer; font-size: 18px;
    }

    /* TOAST */
    .toast-host {
        position: fixed; top: 16px; left: 16px; right: 16px;
        z-index: 9999; pointer-events: none;
    }
    .toast {
        background: #dcfce7; color: #166534; border: 1px solid #86efac;
        padding: 12px 16px; border-radius: 10px;
        font-size: 13px; font-weight: 600; margin-bottom: 8px;
        max-width: 800px; margin-left: auto; margin-right: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,.1);
        pointer-events: auto;
    }
    .toast.error { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

    /* EMPTY STATE filtré */
    .empty-filtered {
        text-align: center; padding: 40px 20px;
        color: #94a3b8; background: #fff;
        border: 1px dashed #e2e8f0; border-radius: 12px;
        font-size: 13px;
    }

    .footer {
        text-align: center; font-size: 11px; color: #94a3b8;
        margin-top: 30px; padding: 16px;
    }
</style>
</head>
<body>

<div class="header">
    <div class="header-inner">
        <img src="{{ asset('images/panora-blanc.png') }}" alt="Panora" onerror="this.style.display='none'">
        <div class="header-meta">
            <span class="badge">PANORA · CIBLE CI · Terrain</span>
            <h1>{{ $campaign->name }}</h1>
        </div>
    </div>
</div>

<div class="wrap">
    <div class="campaign-card">
        <div class="lbl">Campagne</div>
        <h2>{{ $campaign->name }}</h2>
        <div class="meta">
            <span>📅 {{ $campaign->start_date->format('d/m/Y') }} → {{ $campaign->end_date->format('d/m/Y') }}</span>
            <span>👥 {{ $campaign->client?->name ?? '—' }}</span>
            <span>🪧 {{ $campaign->panels->count() }} panneau(x)</span>
        </div>
    </div>

    @if($isClosed)
        <div class="closed-banner">
            ⚠️ Cette campagne est <strong>{{ $campaign->status->label() }}</strong>.
            La prise de pige est désactivée.
        </div>
    @else

    @php
        $panelsByCommune = $campaign->panels->groupBy(fn($p) => $p->commune?->name ?? 'Sans commune')->sortKeys();
        $totalPiges     = $existingPiges->flatten()->count();
        $totalPanels    = $campaign->panels->count();
        $countDone      = $campaign->panels->filter(fn($p) => ($existingPiges[$p->id] ?? collect())->contains(fn($x) => $x->status === 'verifie'))->count();
        $countPending   = $campaign->panels->filter(fn($p) => !($existingPiges[$p->id] ?? collect())->contains(fn($x) => $x->status === 'verifie') && ($existingPiges[$p->id] ?? collect())->isNotEmpty())->count();
        $countTodo      = $totalPanels - $countDone - $countPending;
        $progressPct    = $totalPanels > 0 ? round((($countDone + $countPending) / $totalPanels) * 100) : 0;
    @endphp

    <div class="progress-card">
        <div class="head">
            <span><strong>{{ $countDone + $countPending }}</strong> / {{ $totalPanels }} pigés</span>
            <span style="color:#e8a020;font-weight:700;">{{ $progressPct }}%</span>
        </div>
        <div class="progress-bar"><div style="width:{{ $progressPct }}%"></div></div>
    </div>

    {{-- ─── TOOLBAR STICKY (tech, recherche, filtres) ─── --}}
    <div class="toolbar-sticky">
        <div class="tech-row">
            <label for="tech-name">Tech.</label>
            <input type="text" id="tech-name" placeholder="Votre prénom et nom" maxlength="100">
        </div>
        <div class="search-row">
            <label for="search-input">🔍</label>
            <input type="text" id="search-input" placeholder="Rechercher (réf., nom, commune…)" autocomplete="off">
        </div>
        <div class="filter-row">
            <button type="button" class="filter-chip active" data-filter="all">
                Tous <span class="count">{{ $totalPanels }}</span>
            </button>
            <button type="button" class="filter-chip" data-filter="todo">
                📷 À piger <span class="count">{{ $countTodo }}</span>
            </button>
            <button type="button" class="filter-chip" data-filter="pending">
                ⏳ En attente <span class="count">{{ $countPending }}</span>
            </button>
            <button type="button" class="filter-chip" data-filter="done">
                ✓ Pigé <span class="count">{{ $countDone }}</span>
            </button>
        </div>
    </div>

    {{-- ─── LISTE GROUPÉE PAR COMMUNE ─── --}}
    <div id="panels-container">
        @foreach($panelsByCommune as $communeName => $panels)
            <div class="commune-group" data-commune="{{ \Illuminate\Support\Str::slug($communeName) }}">
                <div class="commune-header" onclick="this.parentElement.classList.toggle('collapsed')">
                    <h3>📍 {{ $communeName }} <span class="count-pill">{{ $panels->count() }}</span></h3>
                    <span class="arrow">▾</span>
                </div>
                <div class="commune-body">
                    @foreach($panels as $panel)
                        @php
                            $panelPiges = $existingPiges[$panel->id] ?? collect();
                            $hasVerified = $panelPiges->contains(fn($p) => $p->status === 'verifie');
                            $hasPending  = $panelPiges->contains(fn($p) => $p->status === 'en_attente');
                            $hasRejected = $panelPiges->isNotEmpty() && $panelPiges->every(fn($p) => $p->status === 'rejete');
                            $pigeKey     = $hasVerified ? 'done' : ($hasPending ? 'pending' : ($hasRejected ? 'rejected' : 'todo'));
                            $pigeClass   = "panel-status-{$pigeKey}";
                            $pigeLabel   = match($pigeKey) {
                                'done'     => '✓ Pigé',
                                'pending'  => '⏳ En attente',
                                'rejected' => '✕ Rejetée',
                                default    => '📷 À piger',
                            };

                            // Lot 9.3 — État pose par panneau
                            $poseTask  = $poseTasks[$panel->id] ?? null;
                            $isPosed   = $poseTask?->status === 'realisee';
                            $poseLabel = $isPosed ? '🪧 Posé' : '🛠 À poser';

                            // Statut combiné pour filtrage (todo/pending/done/rejected)
                            $statusKey = $isPosed && $hasVerified ? 'done'
                                       : ($pigeKey === 'pending' ? 'pending'
                                       : ($pigeKey === 'rejected' ? 'rejected' : 'todo'));
                            $cardClass = match($statusKey) {
                                'done'     => 'is-done',
                                'pending'  => 'is-pending',
                                'rejected' => 'is-rejected',
                                default    => '',
                            };
                            $haystack = mb_strtolower(($panel->reference ?? '') . ' ' . ($panel->name ?? '') . ' ' . $communeName);
                        @endphp
                        <div class="panel-card {{ $cardClass }}"
                             data-panel-id="{{ $panel->id }}"
                             data-status="{{ $statusKey }}"
                             data-pose-status="{{ $isPosed ? 'done' : 'todo' }}"
                             data-search="{{ $haystack }}">
                            <div class="panel-head">
                                <div style="min-width:0;">
                                    <div class="panel-ref">{{ $panel->reference }}</div>
                                    <div class="panel-name">{{ \Illuminate\Support\Str::limit($panel->name, 60) }}</div>
                                    <div class="panel-meta">
                                        @if($panel->format?->name){{ $panel->format->name }}@endif
                                        @if($panel->is_lit) · 💡 Éclairé @endif
                                    </div>
                                </div>
                                <div class="status-stack">
                                    @if($poseTask)
                                        <span class="pose-pill pose-{{ $isPosed ? 'done' : 'todo' }}">{{ $poseLabel }}</span>
                                    @endif
                                    <span class="panel-status {{ $pigeClass }}">{{ $pigeLabel }}</span>
                                </div>
                            </div>

                            <div class="panel-body">
                                <div class="action-row">
                                    @if($poseTask && !$isPosed)
                                        <button type="button" class="action-btn pose-btn"
                                                onclick="PigeCollect.markPosed({{ $panel->id }}, '{{ addslashes($panel->reference) }}')">
                                            ✓ Pose effectuée
                                        </button>
                                    @elseif($isPosed)
                                        <span class="pose-done-tag" title="Pose validée le {{ $poseTask->done_at?->format('d/m/Y H:i') }}">
                                            🪧 Pose validée
                                        </span>
                                    @endif
                                    <button type="button" class="action-btn upload-btn"
                                            onclick="PigeCollect.openUpload({{ $panel->id }}, '{{ addslashes($panel->reference) }}', '{{ addslashes(\Illuminate\Support\Str::limit($panel->name, 50)) }}')">
                                        📷 Photo de pige
                                    </button>
                                </div>

                                @if($panelPiges->isNotEmpty())
                                    <div class="pige-list">
                                        @foreach($panelPiges as $p)
                                            <div class="pige-thumb"
                                                 onclick="PigeCollect.zoom('{{ \Illuminate\Support\Facades\Storage::url($p->photo_path) }}')">
                                                <img src="{{ \Illuminate\Support\Facades\Storage::url($p->photo_path) }}" alt="" loading="lazy">
                                                <span class="badge">
                                                    @if($p->status === 'verifie') ✓
                                                    @elseif($p->status === 'rejete') ✕
                                                    @else ⏳
                                                    @endif
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Affichage explicite du motif si au moins une pige
                                         a été rejetée — le tech doit savoir pourquoi pour
                                         refaire correctement la photo. --}}
                                    @php $rejetedPiges = $panelPiges->where('status', 'rejete'); @endphp
                                    @if($rejetedPiges->isNotEmpty())
                                        <div class="rejet-banner">
                                            <div class="rejet-banner-title">⚠️ Photo refusée — à refaire :</div>
                                            @foreach($rejetedPiges as $rj)
                                                <div class="rejet-banner-reason">
                                                    « {{ $rj->rejection_reason ?: 'Aucun motif précisé par le superviseur.' }} »
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div id="empty-filtered" class="empty-filtered" style="display:none;">
            Aucun panneau ne correspond à votre recherche / filtre.
        </div>
    </div>

    @endif

    <div class="footer">
        <strong>CIBLE CI</strong> — Lien sécurisé attribué à cette campagne.
    </div>
</div>

{{-- ─── MODAL UPLOAD avec photo grande ─── --}}
<div class="modal" id="upload-modal" onclick="if(event.target===this)PigeCollect.close()">
    <div class="modal-content" onclick="event.stopPropagation()">
        <button type="button" class="modal-close" onclick="PigeCollect.close()">✕</button>
        <h3 id="upload-modal-title">Prendre une photo</h3>

        <div id="preview-empty" class="preview-empty">
            <div class="icon">📷</div>
            <div>Sélectionnez ou prenez une photo<br>pour la prévisualiser ici.</div>
        </div>
        <div id="preview-wrap" class="preview-wrap">
            <img id="upload-preview-img" alt="">
        </div>

        <div id="upload-gps" class="gps-info">📍 GPS désactivé — la photo sera envoyée sans coordonnées.</div>

        <div class="field">
            <label>Photo</label>
            <input type="file" id="upload-photo" accept="image/*" capture="environment">
        </div>
        <div class="field">
            <label>Notes (optionnel)</label>
            <textarea id="upload-notes" rows="2" placeholder="Conditions du panneau, remarques…" maxlength="500"></textarea>
        </div>

        <div class="modal-actions">
            <button type="button" class="btn-cancel" onclick="PigeCollect.close()">Annuler</button>
            <button type="button" class="upload-btn" id="upload-submit" onclick="PigeCollect.submit()" style="width:auto;">
                Envoyer
            </button>
        </div>
    </div>
</div>

{{-- ─── LIGHTBOX zoom ─── --}}
<div class="lightbox" id="lightbox" onclick="PigeCollect.closeZoom()">
    <button type="button" class="lightbox-close">✕</button>
    <img id="lightbox-img" alt="">
</div>

<div id="toast-host" class="toast-host"></div>

<script>
window.PigeCollect = (function () {
    const token = '{{ $token }}';
    const csrf  = document.querySelector('meta[name="csrf-token"]').content;
    const modal = document.getElementById('upload-modal');
    const lightbox = document.getElementById('lightbox');
    let currentPanelId = null;
    let currentGps = { lat: null, lng: null };

    function requestGps() {
        if (!('geolocation' in navigator)) return;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                currentGps.lat = pos.coords.latitude.toFixed(6);
                currentGps.lng = pos.coords.longitude.toFixed(6);
                const el = document.getElementById('upload-gps');
                if (el) {
                    el.textContent = `📍 GPS détecté : ${currentGps.lat}, ${currentGps.lng}`;
                    el.classList.add('ok');
                }
            },
            (err) => { console.warn('GPS refusé/indisponible:', err.message); },
            { enableHighAccuracy: true, timeout: 6000, maximumAge: 60000 }
        );
    }

    function showToast(message, type = 'success') {
        const host = document.getElementById('toast-host');
        const t = document.createElement('div');
        t.className = 'toast ' + (type === 'error' ? 'error' : '');
        t.textContent = message;
        host.appendChild(t);
        setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; }, 3000);
        setTimeout(() => t.remove(), 3400);
    }

    // ── Tech name persisté ──
    const techInput = document.getElementById('tech-name');
    if (techInput) {
        techInput.value = localStorage.getItem('pige_tech_name') || '';
        techInput.addEventListener('change', () => {
            localStorage.setItem('pige_tech_name', techInput.value.trim());
        });
    }

    // ── Recherche live ──
    const searchInput = document.getElementById('search-input');
    let activeFilter = 'all';

    function applyFiltersAndSearch() {
        const term = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        document.querySelectorAll('.panel-card').forEach(card => {
            const matchesSearch = !term || card.dataset.search.includes(term);
            const matchesFilter = activeFilter === 'all' || card.dataset.status === activeFilter;
            const visible = matchesSearch && matchesFilter;
            card.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        // Cacher les groupes commune qui n'ont plus aucun panneau visible
        document.querySelectorAll('.commune-group').forEach(group => {
            const visiblePanels = group.querySelectorAll('.panel-card:not([style*="display: none"])').length;
            group.style.display = visiblePanels > 0 ? '' : 'none';
        });

        document.getElementById('empty-filtered').style.display = visibleCount === 0 ? 'block' : 'none';
    }

    searchInput?.addEventListener('input', applyFiltersAndSearch);

    document.querySelectorAll('.filter-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            activeFilter = chip.dataset.filter;
            applyFiltersAndSearch();
        });
    });

    // ── Compression côté client ──
    // Les photos smartphone modernes font 8-30 MB en mode HQ — uploader tel
    // quel = très lent en 4G. On compresse en JPEG 0.85 redimensionné à
    // max 2400px sur le côté le plus long avant envoi : passe de ~12 MB à
    // ~800 KB-1.5 MB sans perte visible. Garde l'EXIF GPS si présent
    // (orientation gérée nativement par les navigateurs récents).
    let compressedFile = null;

    async function compressImage(file) {
        // Laisse passer les très petits fichiers tels quels
        if (file.size < 500 * 1024) return file;

        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = (ev) => {
                const img = new Image();
                img.onload = () => {
                    const MAX = 2400;
                    let { width, height } = img;
                    if (width > MAX || height > MAX) {
                        if (width >= height) {
                            height = Math.round((MAX / width) * height);
                            width = MAX;
                        } else {
                            width = Math.round((MAX / height) * width);
                            height = MAX;
                        }
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    canvas.getContext('2d').drawImage(img, 0, 0, width, height);
                    canvas.toBlob(
                        (blob) => {
                            if (!blob) return resolve(file); // fallback original
                            const newName = (file.name || 'photo.jpg').replace(/\.(heic|heif|png|webp)$/i, '.jpg');
                            resolve(new File([blob], newName, { type: 'image/jpeg', lastModified: Date.now() }));
                        },
                        'image/jpeg',
                        0.85,
                    );
                };
                img.onerror = () => resolve(file);
                img.src = ev.target.result;
            };
            reader.onerror = () => resolve(file);
            reader.readAsDataURL(file);
        });
    }

    // ── Preview ─ scale image to fill modal nicely ──
    const fileInput = document.getElementById('upload-photo');
    fileInput?.addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const url = URL.createObjectURL(file);
        const img = document.getElementById('upload-preview-img');
        img.src = url;
        document.getElementById('preview-empty').style.display = 'none';
        document.getElementById('preview-wrap').classList.add('shown');

        // Compresse en arrière-plan pendant que l'utilisateur tape ses notes
        try {
            compressedFile = await compressImage(file);
            const sizeKb = Math.round(compressedFile.size / 1024);
            const orig   = Math.round(file.size / 1024);
            console.log(`[pige] compressed ${orig} KB → ${sizeKb} KB`);
        } catch (err) {
            compressedFile = file; // fallback
            console.warn('[pige] compression failed, sending original:', err);
        }
    });

    return {
        openUpload(panelId, ref, name) {
            currentPanelId = panelId;
            compressedFile = null;
            document.getElementById('upload-modal-title').textContent = `Photo — ${ref}`;
            document.getElementById('upload-photo').value = '';
            document.getElementById('upload-notes').value = '';
            document.getElementById('preview-wrap').classList.remove('shown');
            document.getElementById('upload-preview-img').src = '';
            document.getElementById('preview-empty').style.display = 'flex';
            modal.classList.add('open');
            if (currentGps.lat === null) requestGps();
        },
        close() {
            modal.classList.remove('open');
            currentPanelId = null;
        },
        zoom(url) {
            document.getElementById('lightbox-img').src = url;
            lightbox.classList.add('open');
        },
        closeZoom() {
            lightbox.classList.remove('open');
            document.getElementById('lightbox-img').src = '';
        },
        async markPosed(panelId, ref) {
            if (!confirm(`Confirmer la pose effectuée pour le panneau ${ref} ?`)) return;

            try {
                const r = await fetch(`/pige/${token}/posed`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        _token: csrf,
                        panel_id: String(panelId),
                        tech_name: techInput?.value || '',
                    }),
                });

                if (r.status === 422) {
                    const data = await r.json().catch(() => ({}));
                    showToast(data.message || 'Données invalides.', 'error');
                    return;
                }

                const data = await r.json();
                if (!r.ok || !data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return;
                }

                showToast(data.message || 'Pose validée.');

                // MAJ in-place : remplacer bouton par tag + pill pose → done
                const card = document.querySelector(`.panel-card[data-panel-id="${panelId}"]`);
                if (card) {
                    card.dataset.poseStatus = 'done';
                    const posePill = card.querySelector('.pose-pill');
                    if (posePill) {
                        posePill.classList.remove('pose-todo');
                        posePill.classList.add('pose-done');
                        posePill.textContent = '🪧 Posé';
                    }
                    const poseBtn = card.querySelector('.pose-btn');
                    if (poseBtn) {
                        const tag = document.createElement('span');
                        tag.className = 'pose-done-tag';
                        tag.textContent = '🪧 Pose validée';
                        tag.title = `Validée le ${data.done_at}`;
                        poseBtn.replaceWith(tag);
                    }
                }
            } catch (e) {
                console.error(e);
                showToast('Erreur réseau. Réessayez.', 'error');
            }
        },
        async submit() {
            const original = document.getElementById('upload-photo').files[0];
            if (!original) {
                showToast('Sélectionnez une photo.', 'error');
                return;
            }
            if (!currentPanelId) return;

            const btn = document.getElementById('upload-submit');
            btn.disabled = true;
            btn.textContent = 'Compression…';

            // Si la compression n'est pas encore finie (timing), on l'attend
            // ici en synchrone — sinon on prend le fichier déjà compressé.
            const fileToSend = compressedFile && compressedFile.size > 0
                ? compressedFile
                : await compressImage(original);

            btn.textContent = 'Envoi…';

            const fd = new FormData();
            fd.append('_token',   csrf);
            fd.append('panel_id', String(currentPanelId));
            fd.append('photo',    fileToSend);
            fd.append('notes',    document.getElementById('upload-notes').value);
            fd.append('tech_name', techInput?.value || '');
            if (currentGps.lat !== null) {
                fd.append('gps_lat', currentGps.lat);
                fd.append('gps_lng', currentGps.lng);
            }

            try {
                const r = await fetch(`/pige/${token}/upload`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    body: fd,
                });

                if (r.status === 422) {
                    const data = await r.json().catch(() => ({}));
                    const first = data.errors ? Object.values(data.errors).flat()[0] : (data.message || 'Données invalides.');
                    showToast(first, 'error');
                    return;
                }

                const data = await r.json();
                if (!data.ok) {
                    showToast(data.message || 'Erreur.', 'error');
                    return;
                }

                this.close();
                showToast(data.message || 'Pige envoyée.');

                // MAJ in-place : ajout thumb + statut → en attente
                const card = document.querySelector(`.panel-card[data-panel-id="${currentPanelId}"]`);
                if (card) {
                    let list = card.querySelector('.pige-list');
                    if (!list) {
                        list = document.createElement('div');
                        list.className = 'pige-list';
                        card.querySelector('.panel-body').appendChild(list);
                    }
                    const thumb = document.createElement('div');
                    thumb.className = 'pige-thumb';
                    thumb.onclick = () => PigeCollect.zoom(data.photo_url);
                    thumb.innerHTML = `<img src="${data.photo_url}" alt=""><span class="badge">⏳</span>`;
                    list.appendChild(thumb);

                    const oldStatus = card.dataset.status;
                    if (oldStatus !== 'done') {
                        card.dataset.status = 'pending';
                        card.classList.remove('is-done', 'is-rejected');
                        card.classList.add('is-pending');
                        const statusEl = card.querySelector('.panel-status');
                        if (statusEl) {
                            statusEl.className = 'panel-status panel-status-pending';
                            statusEl.textContent = '⏳ En attente';
                        }
                    }
                    applyFiltersAndSearch();
                }
            } catch (e) {
                console.error(e);
                showToast('Erreur réseau. Réessayez.', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Envoyer';
            }
        },
    };
})();

// Échap = fermer modale ou lightbox
document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (document.getElementById('lightbox').classList.contains('open')) {
        PigeCollect.closeZoom();
    } else if (document.getElementById('upload-modal').classList.contains('open')) {
        PigeCollect.close();
    }
});
</script>
</body>
</html>

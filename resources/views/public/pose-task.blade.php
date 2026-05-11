<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#c2570d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Pose {{ $task->panel?->reference ?? '' }} — CIBLE CI</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" type="image/png">
    <style>
        :root {
            --orange: #c2570d;
            --orange-2: #e26a17;
            --orange-dim: #fff3e6;
            --bg: #f3f4f6;
            --card: #ffffff;
            --border: #e5e7eb;
            --text: #111827;
            --text2: #4b5563;
            --text3: #9ca3af;
            --green: #16a34a;
            --green-bg: #ecfdf5;
            --blue: #2563eb;
            --blue-bg: #eff6ff;
            --warn: #d97706;
            --warn-bg: #fef3c7;
            --red: #dc2626;
            --red-bg: #fee2e2;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html, body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.45;
            font-size: 16px;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
        }
        body { padding-bottom: 90px; }

        /* ── TOP BAR ─────────────────────────────────────────────── */
        .topbar {
            background: linear-gradient(180deg, var(--orange) 0%, var(--orange-2) 100%);
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 2px 6px rgba(0,0,0,.08);
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 17px; letter-spacing: -.2px; }
        .topbar-brand img { height: 26px; }
        .topbar-tel {
            background: rgba(255,255,255,.18);
            color: #fff;
            border: 1px solid rgba(255,255,255,.3);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 36px;
        }

        /* ── CONTAINER ──────────────────────────────────────────── */
        .wrap {
            max-width: 560px;
            margin: 0 auto;
            padding: 14px 14px 0;
        }

        /* ── STATUS BANNER ──────────────────────────────────────── */
        .status-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 14px;
            font-weight: 700;
            font-size: 16px;
            border: 1px solid transparent;
        }
        .status-banner.planifiee { background: var(--blue-bg); color: var(--blue); border-color: rgba(37,99,235,.2); }
        .status-banner.en_cours  { background: var(--warn-bg); color: var(--warn); border-color: rgba(217,119,6,.2); }
        .status-banner.realisee  { background: var(--green-bg); color: var(--green); border-color: rgba(22,163,74,.2); }
        .status-banner.annulee   { background: var(--red-bg); color: var(--red); border-color: rgba(220,38,38,.2); }
        .status-banner .ic { font-size: 24px; line-height: 1; }
        .status-banner .lbl { flex: 1; }
        .status-banner .meta { font-size: 12px; font-weight: 500; opacity: .85; }

        /* ── CARDS ──────────────────────────────────────────────── */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 2px rgba(0,0,0,.03);
        }
        .card-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--text3);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── PANNEAU INFO ───────────────────────────────────────── */
        .panel-ref {
            font-family: ui-monospace, monospace;
            font-size: 24px;
            font-weight: 800;
            color: var(--orange);
            letter-spacing: -.3px;
            margin-bottom: 4px;
            word-break: break-all;
        }
        .panel-name {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .panel-meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 16px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }
        .meta-item .lbl { font-size: 11px; color: var(--text3); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 2px; }
        .meta-item .val { font-size: 14px; font-weight: 600; color: var(--text); word-break: break-word; }
        .meta-item.full { grid-column: 1 / -1; }

        .btn-maps {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background: var(--blue);
            color: #fff;
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            margin-top: 14px;
            min-height: 52px;
        }

        /* ── PROGRESSION ────────────────────────────────────────── */
        .prog-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
        .prog-val { font-size: 32px; font-weight: 800; color: var(--orange); line-height: 1; min-width: 80px; }
        .prog-val small { display: block; font-size: 12px; color: var(--text3); font-weight: 600; margin-top: 4px; }
        .prog-bar { flex: 1; height: 12px; background: var(--border); border-radius: 999px; overflow: hidden; }
        .prog-fill { height: 100%; background: linear-gradient(90deg, var(--orange), var(--orange-2)); border-radius: 999px; transition: width .35s ease; }
        input[type="range"] {
            -webkit-appearance: none;
            appearance: none;
            width: 100%;
            height: 10px;
            background: var(--border);
            border-radius: 999px;
            outline: none;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--orange);
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,.3);
        }
        input[type="range"]::-moz-range-thumb {
            width: 26px; height: 26px;
            border-radius: 50%;
            background: var(--orange);
            cursor: pointer;
            border: 0;
        }

        .quick-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 6px;
            margin-top: 14px;
        }
        .quick-btn {
            background: var(--orange-dim);
            color: var(--orange);
            border: 1px solid rgba(194,87,13,.2);
            border-radius: 10px;
            padding: 11px 0;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            min-height: 44px;
        }
        .quick-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); }

        .field {
            margin-top: 14px;
        }
        .field label { display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:var(--text2); }
        .field textarea, .field input[type="text"] {
            width: 100%;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 15px;
            font-family: inherit;
            color: var(--text);
            min-height: 80px;
            resize: vertical;
        }

        /* ── PHOTOS ─────────────────────────────────────────────── */
        .photos-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 12px;
        }
        .photos-indicator.empty { background: var(--red-bg); color: var(--red); }
        .photos-indicator.ok    { background: var(--green-bg); color: var(--green); }
        .photos-indicator .ic { font-size: 18px; }

        .photo-thumbs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-bottom: 12px;
        }
        .photo-thumb {
            position: relative;
            aspect-ratio: 1;
            border-radius: 10px;
            overflow: hidden;
            background: var(--border);
            border: 2px solid transparent;
        }
        .photo-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .photo-thumb .status-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: rgba(0,0,0,.7);
            color: #fff;
            font-size: 14px;
            width: 22px; height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .photo-thumb.verifie  { border-color: var(--green); }
        .photo-thumb.rejete   { border-color: var(--red); }
        .photo-thumb.en_attente { border-color: var(--warn); }
        .photo-del {
            position: absolute;
            bottom: 4px;
            right: 4px;
            background: rgba(220,38,38,.92);
            color: #fff;
            border: 0;
            width: 28px; height: 28px;
            border-radius: 50%;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 4px rgba(0,0,0,.3);
            font-family: inherit;
            line-height: 1;
        }
        .photo-del:active { transform: scale(.92); }

        .photo-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--orange);
            color: #fff;
            padding: 16px 18px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 800;
            border: 0;
            width: 100%;
            cursor: pointer;
            min-height: 60px;
            box-shadow: 0 2px 6px rgba(194,87,13,.25);
        }
        .photo-cta:active { transform: translateY(1px); }
        .photo-cta svg { width: 22px; height: 22px; }
        .photo-cta:disabled { opacity: .5; cursor: not-allowed; }

        .uploading {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--warn-bg);
            color: var(--warn);
            padding: 12px 14px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 14px;
            font-weight: 600;
        }
        .spinner {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ── BOUTONS PRINCIPAUX ─────────────────────────────────── */
        .btn-primary {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: 0;
            padding: 15px 18px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            min-height: 56px;
            font-family: inherit;
        }
        .btn-primary:active { transform: translateY(1px); }
        .btn-primary:disabled { opacity: .55; cursor: not-allowed; }

        /* ── BARRE DONE (sticky bottom) ─────────────────────────── */
        .done-bar {
            position: fixed;
            left: 0; right: 0; bottom: 0;
            padding: 12px 14px calc(14px + env(safe-area-inset-bottom));
            background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.95) 18%, #fff 100%);
            z-index: 40;
        }
        .done-bar .inner {
            max-width: 560px;
            margin: 0 auto;
        }
        .btn-done {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: var(--green);
            color: #fff;
            border: 0;
            padding: 18px 18px;
            border-radius: 14px;
            font-size: 17px;
            font-weight: 800;
            cursor: pointer;
            min-height: 64px;
            box-shadow: 0 4px 14px rgba(22,163,74,.35);
            font-family: inherit;
        }
        .btn-done:active { transform: translateY(1px); }
        .btn-done:disabled {
            background: var(--text3);
            box-shadow: none;
            cursor: not-allowed;
        }
        .btn-done svg { width: 24px; height: 24px; }

        /* ── TOASTS ──────────────────────────────────────────── */
        .toast-wrap {
            position: fixed;
            top: 70px;
            left: 12px;
            right: 12px;
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 8px;
            pointer-events: none;
        }
        .toast {
            background: #1f2937;
            color: #fff;
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 14px rgba(0,0,0,.25);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn .2s ease;
            pointer-events: auto;
        }
        .toast.success { background: var(--green); }
        .toast.error   { background: var(--red); }
        .toast.warn    { background: var(--warn); }
        @keyframes slideIn { from { transform: translateY(-10px); opacity:0; } to { transform: translateY(0); opacity:1; } }

        /* ── LIGHTBOX ────────────────────────────────────────── */
        .lightbox {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 200;
            padding: 16px;
        }
        .lightbox.open { display: flex; }
        .lightbox img { max-width: 100%; max-height: 100%; border-radius: 8px; }
        .lightbox-close {
            position: absolute;
            top: 16px; right: 16px;
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 0;
            width: 40px; height: 40px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
        }

        /* ── MODAL CONFIRM ───────────────────────────────────── */
        .modal {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.55);
            display: none;
            align-items: flex-end;
            justify-content: center;
            z-index: 250;
            padding: 14px;
        }
        .modal.open { display: flex; }
        .modal-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            width: 100%;
            max-width: 480px;
            text-align: center;
        }
        .modal-card h3 { font-size: 18px; font-weight: 800; margin-bottom: 8px; }
        .modal-card p { color: var(--text2); font-size: 14px; margin-bottom: 18px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button {
            flex: 1;
            padding: 14px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            border: 0;
            cursor: pointer;
            font-family: inherit;
            min-height: 50px;
        }
        .modal-actions .cancel { background: var(--border); color: var(--text); }
        .modal-actions .confirm { background: var(--green); color: #fff; }

        /* ── RESPONSIVE petits écrans ────────────────────────── */
        @media (max-width: 360px) {
            .panel-ref { font-size: 20px; }
            .panel-name { font-size: 15px; }
            .quick-grid { grid-template-columns: repeat(3, 1fr); }
            .photo-thumbs { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

@php
    $statusVal = $task->status ?? 'planifiee';
    $isFinal   = $isDone || $isCancelled;
    $statusUi  = match($statusVal) {
        'planifiee' => ['ic' => '📅', 'lbl' => 'Planifiée', 'cls' => 'planifiee'],
        'en_cours'  => ['ic' => '🔧', 'lbl' => 'En cours',  'cls' => 'en_cours'],
        'realisee'  => ['ic' => '✅', 'lbl' => 'Pose effectuée', 'cls' => 'realisee'],
        'annulee'   => ['ic' => '🚫', 'lbl' => 'Annulée',   'cls' => 'annulee'],
        default     => ['ic' => '📋', 'lbl' => $statusVal,  'cls' => 'planifiee'],
    };
    $hasPige = $piges->isNotEmpty();
@endphp

<header class="topbar">
    <div class="topbar-brand">
        <span style="font-size:22px;">🪧</span>
        <span>CIBLE&nbsp;CI</span>
    </div>
    @if($task->technicien?->whatsapp_number)
        <a class="topbar-tel" href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $task->technicien->whatsapp_number) }}" target="_blank" rel="noopener">
            📞 Superviseur
        </a>
    @endif
</header>

<div class="toast-wrap" id="toast-wrap"></div>

<div class="wrap">

    {{-- ═══ STATUT EN COURS ═══ --}}
    <div class="status-banner {{ $statusUi['cls'] }}">
        <span class="ic">{{ $statusUi['ic'] }}</span>
        <div class="lbl">
            <div id="status-label">{{ $statusUi['lbl'] }}</div>
            @if($task->done_at)
                <div class="meta">Terminée le {{ $task->done_at->format('d/m/Y H:i') }}</div>
            @elseif($task->scheduled_at)
                <div class="meta">Planifiée le {{ $task->scheduled_at->format('d/m/Y H:i') }}</div>
            @endif
        </div>
    </div>

    {{-- ═══ PANNEAU ═══ --}}
    <div class="card">
        <div class="card-title">🪧 Panneau</div>
        <div class="panel-ref">{{ $task->panel?->reference ?? '—' }}</div>
        <div class="panel-name">{{ $task->panel?->name ?? '—' }}</div>

        <div class="panel-meta-grid">
            @if($task->panel?->adresse)
            <div class="meta-item full">
                <div class="lbl">Adresse</div>
                <div class="val">{{ $task->panel->adresse }}</div>
            </div>
            @endif
            @if($task->panel?->commune)
            <div class="meta-item">
                <div class="lbl">Commune</div>
                <div class="val">{{ $task->panel->commune->name }}</div>
            </div>
            @endif
            @if($task->panel?->quartier)
            <div class="meta-item">
                <div class="lbl">Quartier</div>
                <div class="val">{{ $task->panel->quartier }}</div>
            </div>
            @endif
            @if($task->panel?->format)
            <div class="meta-item">
                <div class="lbl">Format</div>
                <div class="val">{{ $task->panel->format->name }}</div>
            </div>
            @endif
        </div>

        @if($task->panel?->latitude && $task->panel?->longitude)
            <a class="btn-maps"
               href="https://www.google.com/maps?q={{ $task->panel->latitude }},{{ $task->panel->longitude }}"
               target="_blank" rel="noopener">
                🗺️ Ouvrir dans Google Maps
            </a>
        @endif
    </div>

    {{-- ═══ CAMPAGNE / CLIENT ═══ --}}
    @if($task->campaign)
    <div class="card">
        <div class="card-title">🎯 Campagne</div>
        <div style="font-size:16px;font-weight:700;margin-bottom:4px;">{{ $task->campaign->name }}</div>
        @if($task->campaign->client)
            <div style="font-size:14px;color:var(--text2);margin-bottom:8px;">Client : <strong>{{ $task->campaign->client->name }}</strong></div>
        @endif
        @if($task->campaign->start_date && $task->campaign->end_date)
            <div style="font-size:13px;color:var(--text3);">
                📆 Du {{ $task->campaign->start_date->format('d/m/Y') }} au {{ $task->campaign->end_date->format('d/m/Y') }}
            </div>
        @endif
    </div>
    @endif

    {{-- ═══ PROGRESSION (cachée si terminé) ═══ --}}
    @if(!$isFinal)
    <div class="card" id="card-progress">
        <div class="card-title">📊 Progression</div>
        <div class="prog-row">
            <div class="prog-val">
                <span id="prog-pct">{{ (int) $task->progress_percent }}</span>%
                <small>avancement</small>
            </div>
            <div class="prog-bar">
                <div class="prog-fill" id="prog-fill" style="width:{{ (int) $task->progress_percent }}%"></div>
            </div>
        </div>
        <input type="range" id="prog-slider" min="0" max="100" step="5" value="{{ (int) $task->progress_percent }}">
        <div class="quick-grid">
            @foreach([0, 25, 50, 75, 100] as $p)
                <button type="button" class="quick-btn" data-pct="{{ $p }}">{{ $p }}%</button>
            @endforeach
        </div>
        <div class="field">
            <label for="note">Note pour le superviseur (optionnel)</label>
            <textarea id="note" placeholder="Ex : difficultés rencontrées, panneau cassé..."></textarea>
        </div>
        <button type="button" class="btn-primary" id="btn-update" style="margin-top:14px;">
            ⬆ Mettre à jour
        </button>
    </div>
    @endif

    {{-- ═══ PHOTOS ═══ --}}
    <div class="card">
        <div class="card-title">📷 Photos terrain</div>

        <div class="photos-indicator {{ $hasPige ? 'ok' : 'empty' }}" id="photos-indicator">
            <span class="ic">{{ $hasPige ? '✅' : '⚠️' }}</span>
            <span><strong id="photos-count">{{ $piges->count() }}</strong>
                  photo(s) {{ $hasPige ? 'transmise(s)' : '— aucune pige fournie' }}</span>
        </div>

        <div class="photo-thumbs" id="photo-thumbs" style="{{ $hasPige ? '' : 'display:none;' }}">
            @foreach($piges as $pige)
                @php
                    $statusIcon = ['en_attente' => '⏳', 'verifie' => '✓', 'rejete' => '✕'][$pige->status] ?? '?';
                    $canDelete  = !$isFinal && $pige->status !== 'verifie';
                @endphp
                <div class="photo-thumb {{ $pige->status }}"
                     data-pige-id="{{ $pige->id }}"
                     data-status="{{ $pige->status }}"
                     data-url="{{ \Illuminate\Support\Facades\Storage::url($pige->photo_path) }}">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($pige->photo_path) }}"
                         alt="Pige {{ $pige->id }}" loading="lazy">
                    <span class="status-badge" title="{{ $pige->status }}">{{ $statusIcon }}</span>
                    @if($canDelete)
                    <button type="button" class="photo-del" aria-label="Supprimer la photo"
                            data-pige-id="{{ $pige->id }}">🗑</button>
                    @endif
                </div>
            @endforeach
        </div>
        @if($hasPige && !$isFinal)
        <div style="font-size:11px;color:var(--text3);margin-bottom:10px;text-align:center;">
            🗑 Pour <strong>remplacer</strong> une photo, supprimez-la puis reprenez-en une nouvelle.
        </div>
        @endif

        @if(!$isFinal)
        <button type="button" class="photo-cta" id="btn-photo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            Prendre une photo
        </button>
        <input type="file" id="photo-input"
               accept="image/*" capture="environment"
               style="display:none;">
        <div id="uploading" class="uploading" style="display:none;">
            <span class="spinner"></span>
            <span id="uploading-msg">Compression de la photo…</span>
        </div>
        @endif
    </div>

</div>

{{-- ═══ BARRE DONE FIXÉE EN BAS ═══ --}}
@if(!$isFinal)
<div class="done-bar">
    <div class="inner">
        <button type="button" class="btn-done" id="btn-done">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            ✓ Pose effectuée
        </button>
        <div style="text-align:center;font-size:11px;color:var(--text3);margin-top:6px;">
            Confirmer une fois la bâche posée et photographiée.
        </div>
    </div>
</div>
@endif

{{-- ═══ LIGHTBOX ═══ --}}
<div class="lightbox" id="lightbox">
    <button class="lightbox-close" id="lightbox-close">✕</button>
    <img id="lightbox-img" alt="Aperçu pige">
</div>

{{-- ═══ MODAL CONFIRM ═══ --}}
<div class="modal" id="modal-confirm">
    <div class="modal-card">
        <h3 id="modal-title">Confirmer ?</h3>
        <p id="modal-body">Êtes-vous sûr ?</p>
        <div class="modal-actions">
            <button type="button" class="cancel" id="modal-cancel">Annuler</button>
            <button type="button" class="confirm" id="modal-ok">Confirmer</button>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';
    const CSRF = document.querySelector('meta[name=csrf-token]').content;
    const ROUTE_UPDATE       = "{{ route('pige.public.intervention.update', $task->public_token) }}";
    const ROUTE_DONE         = "{{ route('pige.public.intervention.done',   $task->public_token) }}";
    const ROUTE_PHOTO        = "{{ route('pige.public.intervention.photo',  $task->public_token) }}";
    const ROUTE_PHOTO_DELETE = "{{ route('pige.public.intervention.photo.delete', ['token' => $task->public_token, 'pigeId' => 'PIGE_ID']) }}";

    // ── Toast helper ─────────────────────────────────────────
    function toast(msg, type) {
        type = type || 'success';
        const w = document.getElementById('toast-wrap');
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = msg;
        w.appendChild(el);
        setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 3500);
        setTimeout(() => el.remove(), 4000);
    }

    // ── Modal helper ─────────────────────────────────────────
    function confirmModal(title, body) {
        return new Promise(resolve => {
            const m = document.getElementById('modal-confirm');
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-body').textContent  = body;
            m.classList.add('open');
            const cleanup = () => {
                m.classList.remove('open');
                document.getElementById('modal-ok').onclick = null;
                document.getElementById('modal-cancel').onclick = null;
            };
            document.getElementById('modal-ok').onclick = () => { cleanup(); resolve(true); };
            document.getElementById('modal-cancel').onclick = () => { cleanup(); resolve(false); };
        });
    }

    // ── Progression ──────────────────────────────────────────
    const slider = document.getElementById('prog-slider');
    const fill   = document.getElementById('prog-fill');
    const pctTxt = document.getElementById('prog-pct');
    const note   = document.getElementById('note');

    function setProgUi(p) {
        if (pctTxt) pctTxt.textContent = p;
        if (fill)   fill.style.width = p + '%';
        if (slider) slider.value = p;
        document.querySelectorAll('.quick-btn').forEach(b => {
            b.classList.toggle('active', Number(b.dataset.pct) === p);
        });
    }

    if (slider) {
        slider.addEventListener('input', e => setProgUi(Number(e.target.value)));
        document.querySelectorAll('.quick-btn').forEach(btn => {
            btn.addEventListener('click', () => setProgUi(Number(btn.dataset.pct)));
        });
        setProgUi(Number(slider.value));
    }

    document.getElementById('btn-update')?.addEventListener('click', async () => {
        const pct = Number(slider.value);
        const n   = (note.value || '').trim();
        const btn = document.getElementById('btn-update');
        btn.disabled = true;
        try {
            const r = await fetch(ROUTE_UPDATE, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({ progress: pct, note: n }).toString(),
            });
            const data = await r.json();
            if (data.ok) {
                toast(data.message || 'Progression enregistrée.', 'success');
                if (note) note.value = '';
                if (data.is_done) setTimeout(() => location.reload(), 1200);
            } else {
                toast(data.message || 'Erreur.', 'error');
            }
        } catch (e) {
            toast('Réseau indisponible. Réessayez.', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    // ── Photo capture + compression ──────────────────────────
    const photoInput = document.getElementById('photo-input');
    const photoBtn   = document.getElementById('btn-photo');
    const upInfo     = document.getElementById('uploading');
    const upMsg      = document.getElementById('uploading-msg');

    photoBtn?.addEventListener('click', () => photoInput.click());

    async function compressImage(file, maxSize = 2400, quality = 0.85) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                let w = img.width, h = img.height;
                if (w > maxSize || h > maxSize) {
                    if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                    else       { w = Math.round(w * maxSize / h); h = maxSize; }
                }
                const c = document.createElement('canvas');
                c.width = w; c.height = h;
                c.getContext('2d').drawImage(img, 0, 0, w, h);
                c.toBlob(b => b ? resolve(b) : reject(new Error('compression KO')), 'image/jpeg', quality);
            };
            img.onerror = () => reject(new Error('Image illisible'));
            img.src = url;
        });
    }

    async function getGps() {
        if (!navigator.geolocation) return { lat: null, lng: null };
        return new Promise(resolve => {
            navigator.geolocation.getCurrentPosition(
                pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
                () => resolve({ lat: null, lng: null }),
                { enableHighAccuracy: true, timeout: 4000, maximumAge: 30000 }
            );
        });
    }

    photoInput?.addEventListener('change', async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        photoInput.value = ''; // reset pour permettre re-upload même fichier

        photoBtn.disabled = true;
        upInfo.style.display = 'flex';

        try {
            upMsg.textContent = 'Compression de la photo…';
            const blob = await compressImage(file);

            upMsg.textContent = 'Localisation GPS…';
            const gps = await getGps();

            upMsg.textContent = 'Envoi vers le serveur…';
            const fd = new FormData();
            fd.append('photo', blob, 'photo.jpg');
            if (gps.lat !== null) { fd.append('gps_lat', gps.lat); fd.append('gps_lng', gps.lng); }

            const r = await fetch(ROUTE_PHOTO, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: fd,
            });
            const data = await r.json();
            if (data.ok) {
                toast(data.message || 'Photo envoyée.', 'success');
                injectThumbnail(data.pige);
                refreshPhotoCount();
            } else {
                toast(data.message || 'Échec de l\'envoi.', 'error');
            }
        } catch (err) {
            toast('Erreur : ' + (err.message || 'inconnue'), 'error');
        } finally {
            photoBtn.disabled = false;
            upInfo.style.display = 'none';
        }
    });

    function injectThumbnail(pige) {
        if (!pige?.photo_url) return;
        const grid = document.getElementById('photo-thumbs');
        if (!grid) return;
        grid.style.display = '';

        const t = document.createElement('div');
        t.className = 'photo-thumb ' + (pige.status || 'en_attente');
        t.dataset.url     = pige.photo_url;
        t.dataset.pigeId  = pige.id;
        t.dataset.status  = pige.status || 'en_attente';
        t.innerHTML =
            `<img src="${pige.photo_url}" alt="">
             <span class="status-badge" title="${pige.status || 'en_attente'}">⏳</span>
             <button type="button" class="photo-del" aria-label="Supprimer la photo" data-pige-id="${pige.id}">🗑</button>`;
        grid.insertBefore(t, grid.firstChild);
        bindThumb(t);
    }

    function refreshPhotoCount() {
        const grid = document.getElementById('photo-thumbs');
        const all = grid ? grid.querySelectorAll('.photo-thumb') : [];
        const n = all.length;
        const ind = document.getElementById('photos-indicator');
        if (grid) grid.style.display = n > 0 ? '' : 'none';
        if (ind) {
            ind.classList.toggle('ok', n > 0);
            ind.classList.toggle('empty', n === 0);
            const ic = ind.querySelector('.ic');
            if (ic) ic.textContent = n > 0 ? '✅' : '⚠️';
            const txt = ind.querySelector('span:last-child');
            if (txt) txt.innerHTML =
                `<strong id="photos-count">${n}</strong> photo(s) ${n > 0 ? 'transmise(s)' : '— aucune pige fournie'}`;
        }
    }

    // ── Lightbox + suppression photo ─────────────────────────
    const lb = document.getElementById('lightbox');
    const lbImg = document.getElementById('lightbox-img');

    function bindThumb(el) {
        // Clic sur la vignette (zone image) = ouvre lightbox.
        // Clic sur le bouton 🗑 dans la vignette = supprime (event.stop)
        const img = el.querySelector('img');
        img?.addEventListener('click', () => {
            lbImg.src = el.dataset.url;
            lb.classList.add('open');
        });
        const del = el.querySelector('.photo-del');
        del?.addEventListener('click', async (e) => {
            e.stopPropagation();
            const pigeId = del.dataset.pigeId;
            const ok = await confirmModal(
                'Supprimer cette photo ?',
                'Vous pourrez en reprendre une nouvelle ensuite.'
            );
            if (!ok) return;

            const url = ROUTE_PHOTO_DELETE.replace('PIGE_ID', pigeId);
            try {
                del.disabled = true;
                const r = await fetch(url, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': CSRF,
                        'Accept':       'application/json',
                    },
                });
                const data = await r.json();
                if (data.ok) {
                    toast(data.message || 'Photo supprimée.', 'success');
                    el.remove();
                    refreshPhotoCount();
                } else {
                    toast(data.message || 'Suppression impossible.', 'error');
                    del.disabled = false;
                }
            } catch (err) {
                toast('Erreur réseau : ' + err.message, 'error');
                del.disabled = false;
            }
        });
    }
    document.querySelectorAll('.photo-thumb').forEach(bindThumb);
    document.getElementById('lightbox-close')?.addEventListener('click', () => lb.classList.remove('open'));
    lb?.addEventListener('click', e => { if (e.target === lb) lb.classList.remove('open'); });

    // ── Bouton "Pose effectuée" ──────────────────────────────
    document.getElementById('btn-done')?.addEventListener('click', async () => {
        const photoCount = document.querySelectorAll('#photo-thumbs .photo-thumb').length;
        const warn = photoCount === 0
            ? '⚠️ Aucune photo envoyée. Confirmez quand même que la pose est faite ?'
            : 'Confirmer que la pose a bien été effectuée ?';
        const ok = await confirmModal('Pose effectuée', warn);
        if (!ok) return;

        const btn = document.getElementById('btn-done');
        btn.disabled = true;
        try {
            const r = await fetch(ROUTE_DONE, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            });
            const data = await r.json();
            if (data.ok) {
                toast(data.message || 'Pose confirmée.', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                toast(data.message || 'Erreur.', 'error');
                btn.disabled = false;
            }
        } catch (e) {
            toast('Réseau indisponible. Réessayez.', 'error');
            btn.disabled = false;
        }
    });
})();
</script>
</body>
</html>

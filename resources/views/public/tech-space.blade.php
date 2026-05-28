<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mes poses — {{ $tech->name }} · Panora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #e8a020;
            --accent-dark: #c2570d;
            --bg: #f8f9fb;
            --surface: #ffffff;
            --surface2: #f4f5f7;
            --border: #e5e7eb;
            --text: #111827;
            --text2: #4b5563;
            --text3: #9ca3af;
            --planned: #e8a020;
            --en-route: #8b5cf6;
            --in-progress: #3b82f6;
            --done: #22c55e;
            --cancelled: #ef4444;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.55;
        }

        /* ── Header sticky ─────────────────────────────────────── */
        .header {
            position: sticky; top: 0; z-index: 50;
            background: #0f172a;
            color: #fff;
            padding: 14px 16px;
            box-shadow: 0 1px 8px rgba(0,0,0,.12);
        }
        .header .brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 12px; font-weight: 600; letter-spacing: .5px;
            text-transform: uppercase; opacity: .8;
        }
        .header h1 {
            font-size: 18px; font-weight: 700; margin: 4px 0 0;
            letter-spacing: -0.2px;
        }
        .header .stats {
            display: flex; gap: 16px; margin-top: 10px;
            font-size: 12px;
        }
        .header .stats .stat {
            background: rgba(255,255,255,.08);
            padding: 6px 10px; border-radius: 8px;
            display: flex; align-items: center; gap: 6px;
        }
        .header .stats .stat strong { color: var(--accent); }

        /* ── Container ─────────────────────────────────────────── */
        .container { padding: 16px; max-width: 600px; margin: 0 auto; }
        .day-section { margin-bottom: 22px; }
        .day-header {
            display: flex; align-items: center; justify-content: space-between;
            margin: 0 0 10px;
            padding: 0 4px;
        }
        .day-header h2 {
            font-size: 13px; font-weight: 700;
            color: var(--text2);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .day-header .count {
            font-size: 11px; font-weight: 600;
            background: var(--surface);
            color: var(--text2);
            border: 1px solid var(--border);
            padding: 3px 9px; border-radius: 999px;
        }

        /* Bandeau "en retard" — orange vif */
        .day-header.overdue h2 { color: var(--cancelled); }
        .day-header.overdue .count {
            background: rgba(239,68,68,.10);
            border-color: rgba(239,68,68,.30);
            color: var(--cancelled);
        }

        /* ── Card pose ────────────────────────────────────────── */
        .pose {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .pose-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 10px; margin-bottom: 8px;
        }
        .pose-ref {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 13px; font-weight: 700;
            color: var(--accent-dark);
        }
        .pose-name {
            font-size: 13px; color: var(--text);
            font-weight: 500;
            margin-top: 2px;
        }
        .pose-meta {
            font-size: 11px; color: var(--text3);
            display: flex; flex-wrap: wrap; gap: 10px;
            margin-top: 6px;
        }
        .pose-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .pose-campaign {
            font-size: 11px; color: var(--text2);
            background: var(--surface2);
            padding: 3px 8px; border-radius: 6px;
            margin-top: 6px;
            display: inline-block;
        }

        .status-badge {
            font-size: 11px; font-weight: 700;
            padding: 4px 9px; border-radius: 999px;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 4px;
            border: 1px solid transparent;
        }

        /* ── Actions buttons ──────────────────────────────────── */
        .actions { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }
        .btn {
            flex: 1; min-width: 0;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--text);
            font-size: 12px; font-weight: 600;
            cursor: pointer;
            text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: all .15s;
            min-height: 38px;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .btn-route   { background: var(--en-route); color: #fff; border-color: var(--en-route); }
        .btn-work    { background: var(--in-progress); color: #fff; border-color: var(--in-progress); }
        .btn-done    { background: var(--done); color: #fff; border-color: var(--done); }
        .btn-photo   { background: var(--surface2); border-color: var(--border); }
        .btn-photo input[type=file] { display: none; }

        /* ── Empty state ──────────────────────────────────────── */
        .empty {
            text-align: center; padding: 60px 20px;
            color: var(--text3);
        }
        .empty .icon { font-size: 48px; margin-bottom: 12px; }
        .empty h2 { font-size: 18px; color: var(--text); margin: 0 0 6px; }
        .empty p { margin: 0; font-size: 14px; }

        /* ── Toast ────────────────────────────────────────────── */
        #toast-container {
            position: fixed; top: 80px; left: 50%; transform: translateX(-50%);
            z-index: 100; max-width: 90%; pointer-events: none;
        }
        .toast {
            background: var(--text); color: #fff;
            padding: 12px 18px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 6px 20px rgba(0,0,0,.25);
            margin-bottom: 8px;
            opacity: 0; transform: translateY(-10px);
            transition: all .25s ease-out;
            pointer-events: auto;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: var(--done); }
        .toast.error   { background: var(--cancelled); }

        /* ── Footer ───────────────────────────────────────────── */
        .footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text3);
            font-size: 11px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="brand">
        <span>🛠️</span> Panora · Espace Technicien
    </div>
    <h1>Bonjour {{ $tech->name }}</h1>
    <div class="stats">
        <div class="stat">📋 <strong data-total-active>{{ $totalActive }}</strong> pose{{ $totalActive > 1 ? 's' : '' }} à faire</div>
        <div class="stat">✅ <strong>{{ $totalDone }}</strong> faite{{ $totalDone > 1 ? 's' : '' }} au total</div>
    </div>
</div>

<div class="container">

    @if($totalActive === 0)
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Aucune pose à effectuer</h2>
            <p>Tu es à jour ! Tes prochaines missions arriveront via WhatsApp.</p>
        </div>
    @else
        @php
            $dayLabels = [
                'overdue'  => ['En retard',     'overdue'],
                'today'    => ['Aujourd\'hui',  ''],
                'tomorrow' => ['Demain',        ''],
                'week'     => ['Cette semaine', ''],
                'later'    => ['Plus tard',     ''],
            ];
        @endphp

        {{-- Barre de recherche live — utile dès que le tech a une vingtaine
             de poses. Filtre côté JS sur référence + nom + commune + campagne. --}}
        @if($totalActive >= 8)
            <div style="margin-bottom:14px;position:relative">
                <input type="search" id="pose-search" placeholder="🔍 Rechercher un panneau, commune, campagne…"
                       style="width:100%;padding:11px 14px;border:1px solid var(--border);border-radius:10px;background:var(--surface);color:var(--text);font-size:14px;font-family:inherit;outline:none;-webkit-appearance:none"
                       autocomplete="off">
                <div id="pose-search-empty"
                     style="display:none;margin-top:10px;padding:14px;text-align:center;color:var(--text3);background:var(--surface);border:1px dashed var(--border);border-radius:10px;font-size:13px">
                    Aucune pose ne correspond à ta recherche.
                </div>
            </div>
        @endif

        @foreach(['overdue', 'today', 'tomorrow', 'week', 'later'] as $dayKey)
            @if(isset($groupedByDay[$dayKey]) && $groupedByDay[$dayKey]->count() > 0)
                <div class="day-section">
                    <div class="day-header {{ $dayLabels[$dayKey][1] }}">
                        <h2>{{ $dayLabels[$dayKey][0] }}</h2>
                        <span class="count">{{ $groupedByDay[$dayKey]->count() }} pose{{ $groupedByDay[$dayKey]->count() > 1 ? 's' : '' }}</span>
                    </div>

                    @foreach($groupedByDay[$dayKey] as $task)
                        @php
                            // Le modèle PoseTask n'a pas de cast `status` vers
                            // PoseTaskStatus::class (déclarations partout dans
                            // le code utilisent ->value, et un cast casserait
                            // les comparaisons string existantes). On cast
                            // donc localement dans la vue pour accéder à
                            // ->color(), ->label(), ->allowedTransitions().
                            $status = $task->status instanceof \App\Enums\PoseTaskStatus
                                ? $task->status
                                : \App\Enums\PoseTaskStatus::from((string) $task->status);
                            $statusColor = $status->color();
                            $statusBg = match($status->value) {
                                'planifiee' => 'rgba(232,160,32,.10)',
                                'en_route'  => 'rgba(139,92,246,.10)',
                                'en_cours'  => 'rgba(59,130,246,.10)',
                                'realisee'  => 'rgba(34,197,94,.10)',
                                'annulee'   => 'rgba(239,68,68,.10)',
                                default     => 'var(--surface2)',
                            };
                            $statusBorder = match($status->value) {
                                'planifiee' => 'rgba(232,160,32,.30)',
                                'en_route'  => 'rgba(139,92,246,.30)',
                                'en_cours'  => 'rgba(59,130,246,.30)',
                                'realisee'  => 'rgba(34,197,94,.30)',
                                'annulee'   => 'rgba(239,68,68,.30)',
                                default     => 'var(--border)',
                            };
                            $allowedNext = $status->allowedTransitions();
                            $canRoute = in_array(\App\Enums\PoseTaskStatus::EN_ROUTE, $allowedNext, true);
                            $canWork  = in_array(\App\Enums\PoseTaskStatus::IN_PROGRESS, $allowedNext, true);
                            $canDone  = in_array(\App\Enums\PoseTaskStatus::COMPLETED, $allowedNext, true);
                        @endphp
                        @php
                            // Données concaténées pour la recherche live (lowercase, sans accents
                            // pas indispensable pour l'usage CIBLE CI mais pratique).
                            $searchHay = mb_strtolower(implode(' ', array_filter([
                                $task->panel?->reference,
                                $task->panel?->name,
                                $task->panel?->commune?->name,
                                $task->panel?->quartier,
                                $task->panel?->adresse,
                                $task->campaign?->name,
                                $task->campaign?->client?->name,
                            ])));
                        @endphp
                        <div class="pose" data-task-id="{{ $task->id }}" data-search="{{ $searchHay }}">
                            <div class="pose-head">
                                <div style="flex:1; min-width:0">
                                    <div class="pose-ref">{{ $task->panel?->reference ?? '—' }}</div>
                                    <div class="pose-name">{{ $task->panel?->name ?? '' }}</div>
                                    <div class="pose-meta">
                                        @php
                                            // Lien Google Maps : on construit une URL de recherche
                                            // depuis l'adresse + commune (ou juste la commune si
                                            // pas d'adresse). Aide le tech à se rendre sur place
                                            // sans recherche manuelle.
                                            $locationParts = array_filter([
                                                $task->panel?->adresse,
                                                $task->panel?->quartier,
                                                $task->panel?->commune?->name,
                                                'Côte d\'Ivoire',
                                            ]);
                                            $mapsQuery = urlencode(implode(', ', $locationParts));
                                            $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . $mapsQuery;
                                        @endphp
                                        @if($task->panel?->commune)
                                            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener"
                                               style="color:inherit;text-decoration:none;background:rgba(59,130,246,.08);padding:2px 8px;border-radius:6px;border:1px solid rgba(59,130,246,.18)">
                                                📍 {{ $task->panel->commune->name }}{{ $task->panel?->quartier ? ' · '.$task->panel->quartier : '' }} ↗
                                            </a>
                                        @endif
                                        @if($task->panel?->format)
                                            <span>📐 {{ $task->panel->format->name }}</span>
                                        @endif
                                        @if($task->scheduled_at)
                                            <span>⏰ {{ \Carbon\Carbon::parse($task->scheduled_at)->format('d/m H:i') }}</span>
                                        @endif
                                    </div>
                                    @if($task->panel?->adresse)
                                        <div class="pose-meta" style="margin-top:4px">
                                            <span style="color:var(--text2)">🏠 {{ $task->panel->adresse }}</span>
                                        </div>
                                    @endif
                                    @if($task->campaign)
                                        <div class="pose-campaign">📢 {{ $task->campaign->name }}{{ $task->campaign->client ? ' · ' . $task->campaign->client->name : '' }}</div>
                                    @endif
                                </div>
                                <span class="status-badge" data-status
                                      style="background:{{ $statusBg }};color:{{ $statusColor }};border-color:{{ $statusBorder }}">
                                    {{ $status->icon() }} {{ $status->label() }}
                                </span>
                            </div>

                            <div class="actions">
                                @if($canRoute)
                                    <button class="btn btn-route" data-action="status" data-status-value="en_route">
                                        🚗 En route
                                    </button>
                                @endif
                                @if($canWork)
                                    <button class="btn btn-work" data-action="status" data-status-value="en_cours">
                                        🔧 Démarrer
                                    </button>
                                @endif
                                @if($canDone)
                                    <label class="btn btn-photo" data-action="photo">
                                        📸 Photo + Terminer
                                        <input type="file" accept="image/*" capture="environment" data-photo-input>
                                    </label>
                                @endif
                                <button type="button" class="btn btn-report-sm" data-action="report">
                                    ⚠️ Signaler un problème
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
    @endif

    <div class="footer">
        Panora · CIBLE CI<br>
        <span style="opacity:.6">Lien personnel — ne pas partager</span>
    </div>
</div>

<div id="toast-container"></div>

{{-- Overlay succès plein écran (feedback fort terrain) --}}
<div id="ts-success" aria-hidden="true">
    <div class="ts-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none"/><path fill="none" d="M14 27l8 8 16-16"/></svg></div>
    <div class="ts-msg" id="ts-success-msg">Envoyé&nbsp;!</div>
</div>

{{-- Modal "Signaler un problème" --}}
<div id="ts-report-modal" aria-hidden="true">
    <div class="ts-report-card">
        <h3>⚠️ Signaler un problème</h3>
        <p class="ts-report-sub" id="ts-report-ref">Choisis ce qui ne va pas. Le superviseur sera alerté.</p>
        <div class="ts-report-opts">
            <button type="button" class="ts-report-opt" data-type="panneau_casse">🪧 Panneau cassé / abîmé</button>
            <button type="button" class="ts-report-opt" data-type="acces_bloque">🚧 Accès bloqué / impossible</button>
            <button type="button" class="ts-report-opt" data-type="mauvaise_adresse">📍 Mauvaise adresse / introuvable</button>
            <button type="button" class="ts-report-opt" data-type="autre">📝 Autre problème</button>
        </div>
        <textarea id="ts-report-note" placeholder="Précisions (facultatif)…"></textarea>
        <div class="ts-report-actions">
            <button type="button" class="ts-btn-ghost" id="ts-report-cancel">Annuler</button>
            <button type="button" class="ts-btn-send" id="ts-report-send" disabled>Envoyer l'alerte</button>
        </div>
    </div>
</div>

<style>
    /* UX "sans lecture" : actions plus grosses */
    .actions .btn { min-height: 52px; font-size: 16px; }
    .btn-report-sm {
        width:100%; margin-top:8px; min-height:46px;
        background:rgba(217,119,6,.10); color:#b45309;
        border:1px solid rgba(217,119,6,.30); border-radius:12px;
        font-weight:700; cursor:pointer;
    }
    .btn-report-sm:active { transform: translateY(1px); }
    /* Overlay succès */
    #ts-success {
        position:fixed; inset:0; z-index:9999; display:none;
        flex-direction:column; align-items:center; justify-content:center; gap:16px;
        background:rgba(22,163,74,.97); color:#fff;
    }
    #ts-success.show { display:flex; animation:tsFade .2s ease; }
    @keyframes tsFade { from{opacity:0} to{opacity:1} }
    .ts-check svg { width:120px; height:120px; }
    .ts-check circle { stroke:#fff; stroke-width:3; stroke-dasharray:151; stroke-dashoffset:151; animation:tsC .5s ease forwards; }
    .ts-check path { stroke:#fff; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:tsK .35s .35s ease forwards; }
    @keyframes tsC { to{stroke-dashoffset:0} }
    @keyframes tsK { to{stroke-dashoffset:0} }
    .ts-msg { font-size:23px; font-weight:800; }
    /* Modal report */
    #ts-report-modal {
        position:fixed; inset:0; z-index:9998; display:none;
        align-items:flex-end; justify-content:center; background:rgba(15,23,42,.55); padding:0;
    }
    #ts-report-modal.show { display:flex; }
    .ts-report-card {
        background:#fff; width:100%; max-width:520px; border-radius:18px 18px 0 0;
        padding:20px 18px calc(18px + env(safe-area-inset-bottom)); animation:tsUp .25s ease;
    }
    @keyframes tsUp { from{transform:translateY(40px);opacity:.5} to{transform:translateY(0);opacity:1} }
    .ts-report-card h3 { font-size:18px; margin:0 0 4px; }
    .ts-report-sub { font-size:13px; color:#475569; margin:0 0 14px; }
    .ts-report-opts { display:flex; flex-direction:column; gap:8px; }
    .ts-report-opt {
        text-align:left; padding:14px; min-height:52px;
        background:#f6f7f9; border:1.5px solid #e8eaee; border-radius:12px;
        font-size:15px; font-weight:600; color:#0f172a; cursor:pointer;
    }
    .ts-report-opt.sel { border-color:#d97706; background:rgba(217,119,6,.10); color:#b45309; }
    #ts-report-note { width:100%; margin-top:10px; min-height:64px; padding:10px 12px; border:1px solid #e8eaee; border-radius:12px; font:inherit; font-size:14px; resize:vertical; }
    .ts-report-actions { display:flex; gap:10px; margin-top:14px; }
    .ts-btn-ghost { flex:1; min-height:50px; background:#f1f5f9; border:none; border-radius:12px; font-weight:700; font-size:15px; cursor:pointer; }
    .ts-btn-send { flex:2; min-height:50px; background:#d97706; color:#fff; border:none; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; }
    .ts-btn-send:disabled { opacity:.5; }
</style>

<script>
(function() {
    'use strict';
    const CSRF  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const TOKEN = @json($token);

    // ── Feedback fort : overlay plein écran + vibration ──
    function flashSuccess(msg) {
        const ov = document.getElementById('ts-success');
        const m  = document.getElementById('ts-success-msg');
        if (m && msg) m.innerHTML = msg;
        if (navigator.vibrate) { try { navigator.vibrate([40, 60, 120]); } catch (e) {} }
        if (ov) { ov.classList.add('show'); setTimeout(() => ov.classList.remove('show'), 900); }
    }

    function toast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = message;
        document.getElementById('toast-container').appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, 3000);
    }

    // ── Géolocalisation robuste (best-effort, ne bloque pas l'upload) ──
    // 1er essai haute précision (10 s — zones difficiles), retry en précision
    // dégradée (réseau/cellule) avant d'abandonner. Renvoie aussi acc (±m).
    function getPosition() {
        if (!navigator.geolocation) return Promise.resolve(null);
        const attempt = (opts) => new Promise(resolve => {
            navigator.geolocation.getCurrentPosition(
                pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy }),
                ()  => resolve(null),
                opts
            );
        });
        return attempt({ enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 })
            .then(r => r || attempt({ enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }));
    }

    // ── Changement de statut ──────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="status"]');
        if (!btn) return;
        e.preventDefault();

        const pose = btn.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        const newStatus = btn.dataset.statusValue;
        if (!taskId || !newStatus) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ ...';

        try {
            const url = `/tech/${TOKEN}/poses/${taskId}/status`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ status: newStatus }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                toast(data.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            // Mise à jour DOM locale (pas de reload qui ferait remonter
            // en haut de page et perdrait le contexte de scroll du tech).
            const badge = pose.querySelector('[data-status]');
            if (badge) {
                badge.textContent = data.status_icon + ' ' + data.status_label;
                badge.style.color           = data.status_color;
                badge.style.background      = hexToRgba(data.status_color, 0.10);
                badge.style.borderColor     = hexToRgba(data.status_color, 0.30);
            }

            // Cache les boutons d'action sauf "Photo + Terminer" qui doit
            // rester accessible quel que soit le statut intermédiaire.
            // Si on vient de passer en "en_route" → on cache "🚗 En route"
            // (déjà fait) ; si "en_cours" → on cache "🚗" + "🔧".
            const actions = pose.querySelector('.actions');
            if (actions && newStatus === 'en_route') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
            }
            if (actions && newStatus === 'en_cours') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
                actions.querySelector('[data-status-value="en_cours"]')?.remove();
            }

            btn.disabled = false;
            btn.innerHTML = originalText;
            toast(data.message, 'success');
        } catch (err) {
            toast('Erreur réseau', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Convertit "#RRGGBB" en "rgba(r,g,b,alpha)" pour styliser le badge.
    function hexToRgba(hex, alpha) {
        const m = hex.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
        if (!m) return hex;
        return `rgba(${parseInt(m[1],16)},${parseInt(m[2],16)},${parseInt(m[3],16)},${alpha})`;
    }

    // ── Upload photo + auto-completion ───────────────────────
    document.addEventListener('change', async (e) => {
        const input = e.target.closest('[data-photo-input]');
        if (!input || !input.files?.[0]) return;
        const label = input.closest('label');
        const pose  = label?.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        if (!taskId) return;

        const file = input.files[0];
        const originalLabel = label.innerHTML;
        label.innerHTML = '📍 GPS…';
        label.style.pointerEvents = 'none';

        const gps = await getPosition();
        label.innerHTML = (gps && gps.acc) ? `📍 ±${Math.round(gps.acc)} m · envoi…` : '⏳ Envoi…';

        const form = new FormData();
        form.append('photo', file);
        if (gps) {
            form.append('gps_lat', gps.lat.toFixed(6));
            form.append('gps_lng', gps.lng.toFixed(6));
        }
        // Idempotence anti double-envoi / reprise réseau
        form.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2))));

        try {
            const url = `/tech/${TOKEN}/poses/${taskId}/photo`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: form,
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                toast(data.error || 'Erreur upload', 'error');
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                input.value = '';
                return;
            }
            flashSuccess('Photo envoyée&nbsp;!');

            // Pose réalisée → retire la card avec une petite animation
            // de fade-out plutôt que de recharger la page (préserve le
            // scroll position du tech pour les autres poses).
            if (pose) {
                pose.style.transition = 'all .4s ease-out';
                pose.style.opacity   = '0';
                pose.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    pose.remove();
                    refreshDayCounters();
                }, 400);
            }
        } catch (err) {
            toast('Erreur réseau', 'error');
            label.innerHTML = originalLabel;
            label.style.pointerEvents = '';
            input.value = '';
        }
    });

    // ── Recherche live ─────────────────────────────────────
    // Filtre les cards par référence/nom/commune/campagne. Active
    // dès que le tech tape (debounce 100ms).
    const searchInput = document.getElementById('pose-search');
    const searchEmpty = document.getElementById('pose-search-empty');
    if (searchInput) {
        let debounce;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(applySearch, 100);
        });
    }
    function applySearch() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.pose').forEach(card => {
            const hay = card.dataset.search || '';
            const match = q === '' || hay.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        // Cache les sections de jour vides après filtrage
        document.querySelectorAll('.day-section').forEach(section => {
            const has = Array.from(section.querySelectorAll('.pose'))
                .some(p => p.style.display !== 'none');
            section.style.display = has ? '' : 'none';
        });
        if (searchEmpty) {
            searchEmpty.style.display = (q !== '' && visible === 0) ? 'block' : 'none';
        }
    }

    // Recalcule les compteurs "X poses" sous chaque date après retrait
    // d'une pose terminée (évite l'incohérence visuelle).
    function refreshDayCounters() {
        document.querySelectorAll('.day-section').forEach(section => {
            const remaining = section.querySelectorAll('.pose').length;
            const counter = section.querySelector('.count');
            if (remaining === 0) {
                section.remove();
            } else if (counter) {
                counter.textContent = remaining + ' pose' + (remaining > 1 ? 's' : '');
            }
        });
        // Met à jour le compteur global du header
        const totalActiveEl = document.querySelector('[data-total-active]');
        if (totalActiveEl) {
            const total = document.querySelectorAll('.pose').length;
            totalActiveEl.textContent = total;
        }
        // Si plus aucune pose, affiche l'empty state
        if (document.querySelectorAll('.pose').length === 0) {
            location.reload();
        }
    }

    // ── Signaler un problème (1 tap) ─────────────────────────
    (function initReport() {
        const modal  = document.getElementById('ts-report-modal');
        const refEl  = document.getElementById('ts-report-ref');
        const noteEl = document.getElementById('ts-report-note');
        const sendBtn= document.getElementById('ts-report-send');
        const cancel = document.getElementById('ts-report-cancel');
        if (!modal) return;
        let currentTaskId = null, selectedType = null;

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="report"]');
            if (!btn) return;
            e.preventDefault();
            const pose = btn.closest('[data-task-id]');
            currentTaskId = pose?.dataset.taskId || null;
            if (!currentTaskId) return;
            selectedType = null;
            if (noteEl) noteEl.value = '';
            sendBtn.disabled = true;
            modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.remove('sel'));
            const ref = pose.querySelector('.pose-ref')?.textContent?.trim();
            if (refEl) refEl.textContent = ref ? ('Panneau ' + ref + ' — choisis le problème.') : 'Choisis ce qui ne va pas.';
            modal.classList.add('show');
        });

        modal.querySelectorAll('.ts-report-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                selectedType = opt.dataset.type;
                modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.toggle('sel', o === opt));
                sendBtn.disabled = false;
            });
        });
        cancel?.addEventListener('click', () => modal.classList.remove('show'));
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });

        sendBtn?.addEventListener('click', async () => {
            if (!currentTaskId || !selectedType) return;
            sendBtn.disabled = true;
            try {
                const res = await fetch(`/tech/${TOKEN}/poses/${currentTaskId}/report`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ type: selectedType, note: (noteEl?.value || '').trim() }),
                });
                const data = await res.json();
                modal.classList.remove('show');
                if (res.ok && data.ok) {
                    flashSuccess('Signalement envoyé&nbsp;!');
                } else {
                    toast(data.error || data.message || 'Erreur', 'error');
                    sendBtn.disabled = false;
                }
            } catch (err) {
                toast('Erreur réseau', 'error');
                sendBtn.disabled = false;
            }
        });
    })();
})();
</script>

</body>
</html>

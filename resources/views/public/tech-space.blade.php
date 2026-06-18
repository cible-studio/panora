<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mes poses — {{ $tech->name }} · Panora</title>

    {{-- Favicon Panora (aligné sur le layout admin pour cohérence onglet) --}}
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    {{-- PWA : manifeste + theme color + apple-touch-icon. Le Service Worker
         est enregistré côté JS plus bas (lignes ~2824 dans le <script>). --}}
    @include('public.tech.partials._pwa_install')

    {{-- Select2 v4 — source AJAX paginée, indispensable pour scaler la
         recherche au-delà de 200+ poses (le SSR ne rend que les 200 plus
         urgentes — la recherche sert de point d'entrée pour le reste). --}}
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">

    @include('public.tech.partials._styles')
</head>
<body>

@include('public.tech.partials._topbar', [
    'tech'            => $tech,
    'token'           => $token,
    'pigesRejected'   => $pigesRejected ?? 0,
    'pigesTotal'      => $pigesTotal ?? 0,
    'totalActive'     => $totalActive ?? 0,
    'activeToday'     => $activeToday ?? 0,
    'pigesSentToday'  => $pigesSentToday ?? 0,
    'doneToday'       => $doneToday ?? 0,
    'zonesTodayCount' => $zonesTodayCount ?? 0,
    'totalAssigned'   => $totalAssigned ?? 0,
    'totalDone'       => $totalDone ?? 0,
    'progressPct'     => $progressPct ?? 0,
    'zonesTodayList'  => $zonesTodayList ?? [],
])

{{-- Bandeau live : nouvelle pose assignée pendant que tu es sur la page --}}
@include('public.tech.partials._banner_new_task')

{{-- ═══ BARRE DE CONTRÔLES STICKY ═══
     - Select2 recherche AJAX paginée (source : tech.space.search) →
       trouve n'importe quelle pose même hors SSR. Le tech sélectionne,
       on scroll vers la carte (ou on la matérialise si elle n'est pas
       dans la liste rendue).
     - Bouton "🧭 Distance" : géolocalise le tech et trie les cards par
       distance haversine croissante (calcul JS local sur lat/lng déjà
       en data-attr).
     - Bouton "🖨 Feuille de route" : lien vers /poses/route-sheet (vue
       imprimable A4 avec toutes les poses).
--}}
@if($totalActive > 0)
    @include('public.tech.partials._controls_bar', ['token' => $token])
@endif

{{-- ═══ SOMMAIRE ZONES STICKY (TOC) ═══
     Une rangée scrollable horizontalement de chips zones, chacun
     avec mini-progress + compteur. Tap → scroll smooth vers la
     section commune. Indispensable au-delà de 4-5 zones (sans ça
     le tech perd l'orientation dans une longue liste).
--}}
@if(!empty($allZones) && count($allZones) > 1)
<div class="zones-toc">
    <div class="zones-toc-inner">
        @foreach($allZones as $z)
            @php
                $zid = 'zone-' . md5($z['name']);
                $hasOverdue = false; // calculé via dataset côté JS si besoin
            @endphp
            <a href="#{{ $zid }}" class="zone-toc-chip" data-zone="{{ $z['name'] }}" title="{{ $z['done'] }}/{{ $z['total'] }} faites · {{ $z['pct'] }}%">
                <span>📍 {{ $z['name'] }}</span>
                <span class="ztc-prog"><span class="ztc-prog-fill" style="width:{{ $z['pct'] }}%"></span></span>
                <span class="ztc-num">{{ $z['active'] }}</span>
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="container">

    @if($totalActive === 0)
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Bravo, rien à poser !</h2>
            <p>Tu es à jour. Tu recevras un message WhatsApp dès qu'il y aura un nouveau panneau.</p>
        </div>
    @else
        {{-- ═══ BANDEAU CAP SSR ═══
             Si on a plus de poses qu'on ne peut raisonnablement rendre
             en SSR (cap 200 par défaut, configurable), on prévient le
             tech : "X poses au total — voici les 200 les plus urgentes,
             pour les autres utilise la recherche". --}}
        @if(($totalActive ?? 0) > ($totalRendered ?? 0))
            <div class="ssr-cap-banner">
                <span style="font-size:16px;line-height:1.2">⚡</span>
                <div>
                    Tu as <strong>{{ $totalActive }} panneaux</strong> à poser.
                    On te montre d'abord les <strong>{{ $totalRendered }} plus pressés</strong>.
                    <br>Pour les autres : utilise la <strong>recherche en haut</strong> 🔍
                    ou la <strong>🖨 liste papier</strong>.
                </div>
            </div>
        @endif

        {{-- Banner mode tournée — visible quand TSP optimisé activé --}}
        <div class="tour-summary" id="ts-tour-summary">
            <span>🚀</span>
            <span>Ton chemin : <strong id="ts-tour-count">0</strong> arrêts · <strong id="ts-tour-total">0 km</strong> en tout</span>
            <button type="button" id="ts-tour-quit">Annuler</button>
        </div>

        {{-- ═══ HERO « PROCHAINE POSE » ═══ --}}
        @if(!empty($nextTask))
            @include('public.tech.partials._focus_card', ['task' => $nextTask])
        @endif

        {{-- ═══ CHIPS FILTRES ═══ --}}
        @include('public.tech.partials._filters_chips')

        @php $today = \Carbon\Carbon::today(); @endphp
        @include('public.tech.partials._pose_list', [
            'groupedByCommune' => $groupedByCommune,
            'doneByCommune'    => $doneByCommune,
            'today'            => $today,
        ])

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

@include('public.tech.partials._modal_report')

<script>
(function() {
    'use strict';
    const CSRF  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const TOKEN = @json($token);

    // ── KPI cliquables : filtre la liste des poses sans reload ─────
    function applyKpiFilter(name) {
        const poses = document.querySelectorAll('.pose[data-task-id]');
        let visible = 0;
        poses.forEach(p => {
            let show = true;
            if (name === 'today') show = p.dataset.scheduledToday === '1';
            p.classList.toggle('is-filtered-out', !show);
            if (show) {
                visible++;
                p.classList.add('is-revealed');
                setTimeout(() => p.classList.remove('is-revealed'), 400);
            }
        });

        // Masque les sections commune désormais vides
        document.querySelectorAll('.day-section').forEach(section => {
            const remaining = section.querySelectorAll('.pose:not(.is-filtered-out)').length;
            section.style.display = remaining === 0 ? 'none' : '';
        });

        // Empty state si le filtre ne laisse rien
        let emptyEl = document.getElementById('kpi-filter-empty');
        if (visible === 0 && name !== 'all') {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.id = 'kpi-filter-empty';
                emptyEl.style.cssText = 'background:var(--surface);border:1px dashed var(--border);border-radius:14px;padding:32px 18px;text-align:center;color:var(--text3);margin-bottom:16px';
                emptyEl.innerHTML = '<div style="font-size:36px;margin-bottom:8px;opacity:.4">🗓️</div><div style="font-size:14px;font-weight:700;color:var(--text2);margin-bottom:4px" data-empty-title></div><div style="font-size:12px" data-empty-sub></div>';
                document.querySelector('.container').insertBefore(emptyEl, document.querySelector('.day-section'));
            }
            const titles = {
                today: ['Rien à poser aujourd\'hui', 'Tu recevras un message WhatsApp dès qu\'il y a du nouveau.'],
            };
            const [t, s] = titles[name] || ['Aucun panneau ici', ''];
            emptyEl.querySelector('[data-empty-title]').textContent = t;
            emptyEl.querySelector('[data-empty-sub]').textContent   = s;
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    }

    document.querySelectorAll('[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.kpiFilter;
            document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(b => {
                const active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            applyKpiFilter(name);
        });
    });

    // KPI "Zones" : pas un filtre — scroll smooth vers la première zone.
    const zonesBtn = document.querySelector('[data-kpi-action="scroll-zones"]');
    if (zonesBtn) {
        zonesBtn.addEventListener('click', () => {
            const firstSection = document.querySelector('.day-section');
            if (firstSection) firstSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // ── Progression auto : "Y aller" bumpe en_route (25%) + ouvre Maps ──
    document.addEventListener('click', async (e) => {
        const goBtn = e.target.closest('[data-go-maps]');
        if (!goBtn) return;
        const pose = goBtn.closest('[data-task-id]');
        if (!pose) return;
        const currentStatus = pose.dataset.taskStatus;
        // Bump uniquement si statut planifiee (pas régression sur en_cours, etc.)
        if (currentStatus !== 'planifiee') return;
        const taskId = pose.dataset.taskId;
        try {
            const r = await fetch(`/tech/${TOKEN}/poses/${taskId}/status`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'status=en_route',
                credentials: 'same-origin',
            });
            if (r.ok) {
                pose.dataset.taskStatus = 'en_route';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = '#8b5cf6'; // violet EN_ROUTE
            }
        } catch (e) { /* silencieux, on n'empêche pas Maps */ }
        // Le lien suit son cours (target=_blank) — pas de preventDefault.
    });

    // ── Bouton "Sur place" : bump en_cours (60%) ────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="arrive"]');
        if (!btn || btn.disabled) return;
        const pose = btn.closest('[data-task-id]');
        if (!pose) return;
        const taskId = pose.dataset.taskId;
        const original = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '⏳ …';
        try {
            const r = await fetch(`/tech/${TOKEN}/poses/${taskId}/status`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'status=en_cours',
                credentials: 'same-origin',
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                pose.dataset.taskStatus = 'en_cours';
                btn.innerHTML = '✓ Sur place';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = '#3b82f6'; // bleu IN_PROGRESS
                toast('Tu es bien sur place — bonne pose !', 'success');
            } else {
                btn.disabled = false; btn.innerHTML = original;
                toast(data.error || 'Erreur', 'error');
            }
        } catch (err) {
            btn.disabled = false; btn.innerHTML = original;
            toast('Pas de réseau — réessaie quand ça revient', 'error');
        }
    });

    // ── Polling heartbeat — KPI live + détection nouvelle pose ────
    const HEARTBEAT_URL = "{{ route('tech.space.heartbeat', $token) }}";
    const POLL_MS = 20000;
    const liveDot = document.querySelector('[data-live-indicator]');
    // Plus haut ID de pose actuellement dans le DOM — sert de baseline pour
    // détecter "nouvelle pose assignée" entre 2 ticks heartbeat.
    let lastKnownTaskId = Array.from(document.querySelectorAll('.pose[data-task-id]'))
        .reduce((max, el) => Math.max(max, parseInt(el.dataset.taskId, 10) || 0), 0);
    let firstTick = true;

    function bumpKpi(name, newVal) {
        const el = document.querySelector(`[data-kpi-value="${name}"]`);
        if (!el) return;
        const oldVal = parseInt(el.textContent.trim(), 10) || 0;
        if (oldVal === newVal) return;
        el.textContent = newVal;
        el.classList.remove('kpi-bump');
        void el.offsetWidth; // force reflow pour relancer l'anim
        el.classList.add('kpi-bump');
    }

    async function heartbeatTick() {
        try {
            const r = await fetch(HEARTBEAT_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;

            // Pulse "live"
            if (liveDot) {
                liveDot.classList.add('is-pulsing');
                setTimeout(() => liveDot.classList.remove('is-pulsing'), 600);
            }

            bumpKpi('totalActive',     d.totalActive);
            bumpKpi('activeToday',     d.activeToday);
            bumpKpi('pigesSentToday',  d.pigesSentToday);
            bumpKpi('zonesTodayCount', d.zonesTodayCount);

            // Le sub-label "Aujourd'hui" rappelle aussi le nb posées du jour
            const doneTodayEl = document.querySelector('[data-done-today]');
            if (doneTodayEl) doneTodayEl.textContent = d.doneToday;

            // MAJ chip "Mes piges" badge (rejected si > 0, sinon total)
            const chipBadge = document.querySelector('[data-piges-chip-badge]');
            if (chipBadge) {
                const v = d.pigesRejected > 0 ? d.pigesRejected : d.pigesTotal;
                if (parseInt(chipBadge.textContent.trim(), 10) !== v) {
                    chipBadge.textContent = v;
                }
            }

            // Détection nouvelle pose assignée
            if (!firstTick && d.latestTaskId > lastKnownTaskId) {
                const banner = document.querySelector('[data-new-task-banner]');
                if (banner) banner.style.display = 'block';
            }
            lastKnownTaskId = Math.max(lastKnownTaskId, d.latestTaskId || 0);
            firstTick = false;
        } catch (e) { /* silencieux */ }
    }

    setTimeout(heartbeatTick, 1500);
    setInterval(heartbeatTick, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) heartbeatTick();
    });

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

    // ── Compression image côté client (canvas) ─────────────────
    // Réduit la photo à 2400 px max + JPEG q=0.85. Bénéfices :
    //  - convertit HEIC/HEIF iPhone en JPEG (sinon GD serveur refuse) ;
    //  - ramène 20-30 MB de photo brute à 200-500 KB ;
    //  - upload rapide même en 4G médiocre.
    // Best-effort : si le navigateur ne sait pas décoder (HEIC sur vieux
    // Android), on renvoie le fichier original — le serveur tentera
    // (Intervention) et a un fallback "stockage tel quel".
    async function compressImage(file, maxSize = 2400, quality = 0.85) {
        try {
            return await new Promise((resolve, reject) => {
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    let w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
                    if (w > maxSize || h > maxSize) {
                        if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                        else       { w = Math.round(w * maxSize / h); h = maxSize; }
                    }
                    const c = document.createElement('canvas');
                    c.width = w; c.height = h;
                    c.getContext('2d').drawImage(img, 0, 0, w, h);
                    c.toBlob(b => b ? resolve(b) : reject(new Error('compress')), 'image/jpeg', quality);
                };
                img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
                img.src = url;
            });
        } catch (e) {
            // Décodage impossible (ex: HEIC sur navigateur sans support natif).
            // On laisse passer l'original — le serveur fera ce qu'il peut.
            return file;
        }
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
            toast('Pas de réseau — réessaie quand ça revient', 'error');
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

    // ── Modale "justifier la pige malgré signalement" ───────
    // Le tech a signalé un problème non résolu sur cette pose mais
    // tente d'envoyer une pige : on impose une justification écrite
    // (min 10 caractères) qui sera tracée dans pige.notes côté admin.
    // Retourne une Promise<string|null> — null si annulation.
    function askContradictionReason(signalLabel) {
        return new Promise((resolve) => {
            // Construit la modale à la volée pour éviter d'alourdir le DOM
            // initial. Une seule instance à la fois (remove à la fermeture).
            const overlay = document.createElement('div');
            overlay.style.cssText = `position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;padding:16px`;
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:14px;max-width:440px;width:100%;box-shadow:0 30px 80px -20px rgba(0,0,0,.4);overflow:hidden">
                    <div style="padding:16px 20px;background:linear-gradient(180deg,#fff7ed,#fff);border-bottom:1px solid #fed7aa;display:flex;align-items:flex-start;gap:10px">
                        <div style="font-size:22px;line-height:1">⚠️</div>
                        <div>
                            <div style="font-size:15px;font-weight:800;color:#9a3412;margin-bottom:2px">Tu envoies une photo malgré le souci</div>
                            <div style="font-size:12.5px;color:#b45309;line-height:1.45">
                                Tu as déjà dit : <strong>« ${signalLabel} »</strong>.
                                Si tu envoies quand même la photo, dis pourquoi (le bureau le verra).
                            </div>
                        </div>
                    </div>
                    <div style="padding:16px 20px">
                        <label style="display:block;font-size:12.5px;font-weight:700;color:#1f2937;margin-bottom:6px">
                            Dis pourquoi <span style="color:#ef4444">*</span>
                        </label>
                        <textarea id="contradiction-reason-input" rows="3"
                                  maxlength="1000"
                                  placeholder="Ex : le panneau a été réparé, ou l'affiche est encore lisible…"
                                  style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px;resize:vertical;font-family:inherit"></textarea>
                        <div id="contradiction-reason-counter" style="font-size:11px;color:#6b7280;text-align:right;margin-top:4px">0 / 10 lettres mini</div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                            <button type="button" data-action="cancel"
                                    style="padding:9px 16px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;color:#4b5563">
                                Annuler
                            </button>
                            <button type="button" data-action="confirm" disabled
                                    style="padding:9px 18px;background:#f97316;border:none;color:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;opacity:.5">
                                Envoyer quand même
                            </button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            const ta      = overlay.querySelector('#contradiction-reason-input');
            const counter = overlay.querySelector('#contradiction-reason-counter');
            const btnOk   = overlay.querySelector('[data-action="confirm"]');
            const btnNo   = overlay.querySelector('[data-action="cancel"]');
            ta.focus();
            ta.addEventListener('input', () => {
                const n = ta.value.trim().length;
                counter.textContent = `${n} / 10 lettres mini`;
                const ok = n >= 10;
                btnOk.disabled = !ok;
                btnOk.style.opacity = ok ? '1' : '.5';
                btnOk.style.cursor  = ok ? 'pointer' : 'not-allowed';
            });
            function close(val) { overlay.remove(); resolve(val); }
            btnOk.addEventListener('click', () => {
                const v = ta.value.trim();
                if (v.length >= 10) close(v);
            });
            btnNo.addEventListener('click', () => close(null));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(null); });
            document.addEventListener('keydown', function esc(ev) {
                if (ev.key === 'Escape') { close(null); document.removeEventListener('keydown', esc); }
            });
        });
    }

    // ── Upload photo + auto-completion ───────────────────────
    // ── Aperçu photo avant upload ────────────────────────────────
    // Le tech voit ce qu'il s'apprête à envoyer (flou, cadrage, etc.) et
    // peut "Reprendre" sans avoir envoyé une mauvaise photo. Retour :
    //   Promise<boolean> — true = envoyer ; false = annulé / reprendre
    function askPhotoPreview(file, panelRef) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const overlay = document.createElement('div');
            overlay.style.cssText = `position:fixed;inset:0;z-index:99998;background:rgba(15,23,42,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px;animation:fadeIn .2s`;
            overlay.innerHTML = `
                <style>@keyframes fadeIn{from{opacity:0}to{opacity:1}}</style>
                <div style="color:#fff;font-size:13px;font-weight:600;margin-bottom:8px;text-align:center">
                    Voici ta photo${panelRef ? ' · <strong>'+panelRef+'</strong>' : ''}
                </div>
                <img src="${url}" alt="Aperçu" style="max-width:100%;max-height:60vh;border-radius:14px;box-shadow:0 16px 40px -8px rgba(0,0,0,.6);object-fit:contain;background:#000">
                <div style="color:#cbd5e1;font-size:11.5px;margin-top:10px;text-align:center;line-height:1.4">
                    Regarde si on voit bien le panneau et l'affiche. Si oui, envoie. Sinon, refais.
                </div>
                <div style="display:flex;gap:10px;margin-top:18px;width:100%;max-width:380px">
                    <button type="button" data-act="cancel"
                            style="flex:1;padding:13px 14px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;-webkit-tap-highlight-color:transparent">
                        📷 Refaire
                    </button>
                    <button type="button" data-act="confirm"
                            style="flex:1;padding:13px 14px;background:linear-gradient(135deg,#e8a020,#c2570d);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 8px 20px -4px rgba(232,160,32,.5);-webkit-tap-highlight-color:transparent">
                        ✅ Envoyer
                    </button>
                </div>
            `;
            document.body.appendChild(overlay);
            const close = (val) => {
                URL.revokeObjectURL(url);
                overlay.remove();
                resolve(val);
            };
            overlay.querySelector('[data-act="confirm"]').addEventListener('click', () => close(true));
            overlay.querySelector('[data-act="cancel"]').addEventListener('click',  () => close(false));
            document.addEventListener('keydown', function esc(ev) {
                if (ev.key === 'Escape') { close(false); document.removeEventListener('keydown', esc); }
            });
        });
    }

    document.addEventListener('change', async (e) => {
        const input = e.target.closest('[data-photo-input]');
        if (!input || !input.files?.[0]) return;
        const label = input.closest('label');
        const pose  = label?.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        if (!taskId) return;

        // 0. Aperçu : le tech voit sa photo avant qu'on déclenche quoi que
        //    ce soit (compression, GPS, upload). S'il refuse, on reset
        //    l'input — il pourra reprendre sans pénalité.
        const preview = input.files[0];
        const panelRef = pose?.querySelector('.pose-ref')?.textContent?.trim()
                       || pose?.dataset.taskId;
        const confirmed = await askPhotoPreview(preview, panelRef);
        if (!confirmed) {
            input.value = '';
            return;
        }

        // Garde-fou contradiction : si signalement non résolu sur cette pose,
        // on demande une justification AVANT de compresser/uploader pour ne
        // pas perdre le travail si annulation. La justification part dans
        // FormData et le serveur la trace dans pige.notes.
        let contradictionReason = null;
        const blockingLabel = pose?.dataset.blockingSignalLabel;
        if (blockingLabel) {
            contradictionReason = await askContradictionReason(blockingLabel);
            if (contradictionReason === null) {
                // Tech a annulé → on reset l'input et on n'envoie rien.
                input.value = '';
                return;
            }
        }

        const file = input.files[0];
        const originalLabel = label.innerHTML;
        label.innerHTML = '🔄 Compression…';
        label.style.pointerEvents = 'none';

        // 1) Compression locale (HEIC iPhone → JPEG, gros fichier → ~500 KB)
        const blob = await compressImage(file);

        // 2) GPS pendant la compression aurait gagné un peu de temps, on garde
        //    la séquence simple : compress puis GPS puis envoi.
        label.innerHTML = '📍 GPS…';
        const gps = await getPosition();
        label.innerHTML = (gps && gps.acc) ? `📍 ±${Math.round(gps.acc)} m · envoi…` : '⏳ Envoi…';

        // 3) FormData. Si compression a réussi → blob JPEG, sinon file original.
        const form = new FormData();
        const isBlob = blob instanceof Blob && blob !== file;
        form.append('photo', blob, isBlob ? 'photo.jpg' : (file.name || 'photo.jpg'));
        if (gps) {
            form.append('gps_lat', gps.lat.toFixed(6));
            form.append('gps_lng', gps.lng.toFixed(6));
        }
        // Idempotence anti double-envoi / reprise réseau
        form.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2))));

        // Si on a une justification de contradiction signalement → on l'ajoute
        // pour que le serveur ne renvoie pas le 422 dédié et trace la note.
        if (contradictionReason) {
            form.append('contradicts_signalement_reason', contradictionReason);
        }

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
            const data = await res.json().catch(() => ({}));

            // Fallback défensif : si le serveur réclame une justification
            // (data-attribute mal posé / cache JS périmé / route forcée),
            // on ouvre la modale ici, on re-tente l'upload avec la raison.
            if (res.status === 422 && data.requires_contradiction_reason) {
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                const reason = await askContradictionReason(data.signalement_label || 'un problème');
                if (reason === null) { input.value = ''; return; }
                form.set('contradicts_signalement_reason', reason);
                label.innerHTML = '⏳ Renvoi…';
                label.style.pointerEvents = 'none';
                const res2 = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: form,
                });
                const data2 = await res2.json().catch(() => ({}));
                if (!res2.ok || !data2.ok) {
                    toast(data2.error || `Erreur ${res2.status}`, 'error');
                    label.innerHTML = originalLabel;
                    label.style.pointerEvents = '';
                    input.value = '';
                    return;
                }
                Object.assign(data, data2); // continue avec data du retry
            } else if (!res.ok || !data.ok) {
                // Remonte d'abord les erreurs de validation Laravel (422),
                // sinon le message du controller, sinon un fallback explicite
                // avec le code HTTP — beaucoup plus utile sur le terrain.
                const validation = data.errors ? Object.values(data.errors).flat().join(' · ') : '';
                const msg = validation || data.error || data.message || `Erreur ${res.status}`;
                toast(msg, 'error');
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                input.value = '';
                return;
            }
            flashSuccess('Photo envoyée&nbsp;! Bravo 🎉');

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
            // En mode offline (ou erreur fetch), on enqueue la photo pour
            // un rejouage automatique au retour réseau (Background Sync).
            // Évite au tech de perdre sa photo après avoir parcouru un km
            // pour atteindre un panneau dans une zone sans réseau.
            if (typeof window.queueOfflinePhoto === 'function'
                && (navigator.onLine === false || err.name === 'TypeError')) {
                try {
                    await window.queueOfflinePhoto(taskId, blob instanceof Blob ? blob : file, gps, contradictionReason);
                    label.innerHTML = '📤 En attente';
                    setTimeout(() => { label.innerHTML = originalLabel; label.style.pointerEvents = ''; input.value = ''; }, 1500);
                    return;
                } catch (e) { /* fallback toast classique */ }
            }
            toast('Pas de réseau — réessaie quand ça revient', 'error');
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
        const noteEl   = document.getElementById('ts-report-note');
        const sendBtn  = document.getElementById('ts-report-send');
        const cancel   = document.getElementById('ts-report-cancel');
        const photoInp = document.getElementById('ts-report-photo');
        const photoLbl = document.getElementById('ts-report-photo-label');
        const photoTxt = document.getElementById('ts-report-photo-label-text');
        let attachedPhoto = null;

        photoInp?.addEventListener('change', async () => {
            const f = photoInp.files?.[0];
            if (!f) {
                attachedPhoto = null;
                photoLbl?.classList.remove('has-file');
                if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
                return;
            }
            // Compresse côté client (réutilise la fonction du flux photo principal)
            try {
                attachedPhoto = await compressImage(f);
                photoLbl?.classList.add('has-file');
                if (photoTxt) photoTxt.textContent = '✓ Photo prête';
            } catch (e) {
                attachedPhoto = f; // fallback original
                photoLbl?.classList.add('has-file');
                if (photoTxt) photoTxt.textContent = '✓ Photo prête (non compressée)';
            }
        });
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
            // Reset photo jointe pour ne pas hériter d'un précédent signalement
            attachedPhoto = null;
            if (photoInp) photoInp.value = '';
            photoLbl?.classList.remove('has-file');
            if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
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
                // Si photo jointe → multipart, sinon JSON (plus léger).
                let res;
                if (attachedPhoto) {
                    const fd = new FormData();
                    fd.append('type', selectedType);
                    fd.append('note', (noteEl?.value || '').trim());
                    fd.append('photo', attachedPhoto, 'signalement.jpg');
                    res = await fetch(`/tech/${TOKEN}/poses/${currentTaskId}/report`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: fd,
                    });
                } else {
                    res = await fetch(`/tech/${TOKEN}/poses/${currentTaskId}/report`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ type: selectedType, note: (noteEl?.value || '').trim() }),
                    });
                }
                const data = await res.json();
                modal.classList.remove('show');
                if (res.ok && data.ok) {
                    flashSuccess('Souci envoyé au bureau&nbsp;!');

                    // Inscrit le rappel "déjà signalé" sur la ligne de la
                    // pose : le tech ne re-signalera plus le même problème
                    // sans s'en rendre compte (cas plusieurs panneaux).
                    // 9 motifs — source unique App\Enums\DelayReason (M3 SLA enrichi).
                    const TYPE_LABELS = {
                        @foreach(\App\Enums\DelayReason::cases() as $motif)
                        '{{ $motif->value }}': @json($motif->label()),
                        @endforeach
                    };
                    const pose = document.querySelector(`.pose-line[data-task-id="${currentTaskId}"]`);
                    if (pose) {
                        pose.classList.add('has-problem');
                        const banner = pose.querySelector('[data-problem-banner]');
                        const lbl = pose.querySelector('[data-problem-label]');
                        const whn = pose.querySelector('[data-problem-when]');
                        if (banner) banner.style.display = '';
                        if (lbl) lbl.textContent = TYPE_LABELS[selectedType] || 'Problème signalé';
                        if (whn) whn.textContent = "à l'instant";
                    }
                } else {
                    toast(data.error || data.message || 'Erreur', 'error');
                    sendBtn.disabled = false;
                }
            } catch (err) {
                toast('Pas de réseau — réessaie quand ça revient', 'error');
                sendBtn.disabled = false;
            }
        });
    })();
})();
</script>

{{-- ═══ Bandeau hors-ligne — affiché par le SW quand on perd le réseau ═══ --}}
<div class="offline-banner" id="ts-offline-banner">
    📵 Pas de réseau — tu peux quand même prendre des photos, on les enverra dès que ça revient.
</div>

{{-- ═══ Select2 + nouveau module SCALE ═══
     Chargés en fin de body pour ne pas bloquer le rendu initial. La lib
     Select2 est cachée par le Service Worker dès la 1ère visite. --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js" defer></script>

<script>
window.addEventListener('DOMContentLoaded', function () {
    // ═══════════════════════════════════════════════════════════════
    // MODULE SCALE — recherche Select2, filtres combinés, TOC zones,
    // tri par distance, URL persistance, lazy reveal, PWA / offline.
    // Conçu pour rester O(N) sur le nombre de cards SSR + scaler
    // grâce à l'endpoint search côté serveur pour le reste.
    // ═══════════════════════════════════════════════════════════════

    if (typeof jQuery === 'undefined') return; // defer pas encore résolu

    const $ = window.jQuery;
    const TOKEN = @json($token);
    const SEARCH_URL = "{{ route('tech.space.search', $token) }}";
    const ROUTE_SHEET_URL = "{{ route('tech.space.route-sheet', $token) }}";

    // ─── 1. État des filtres (combinable) ─────────────────────────
    const filterState = {
        kpi: 'all',       // 'all' | 'today' (compatibilité KPI grid existant)
        chips: new Set(), // 'late' | 'today' | 'problem' | 'reject' | 'en_route' | 'en_cours'
        zone: null,       // optionnel : restreindre à une commune
        distance: false,  // tri par distance activé
        geo: null,        // { lat, lng } position tech si captée
    };

    // ─── 2. Lecture / écriture URL (bookmark / share / back-fwd) ──
    function readFiltersFromUrl() {
        const u = new URL(location.href);
        const kpi = u.searchParams.get('kpi');
        if (kpi === 'today') filterState.kpi = 'today';
        const chips = u.searchParams.get('chips');
        if (chips) chips.split(',').filter(Boolean).forEach(c => filterState.chips.add(c));
        const zone = u.searchParams.get('zone');
        if (zone) filterState.zone = zone;
        if (u.searchParams.get('sort') === 'distance') filterState.distance = true;
    }
    function writeFiltersToUrl() {
        const u = new URL(location.href);
        u.searchParams.delete('kpi');
        u.searchParams.delete('chips');
        u.searchParams.delete('zone');
        u.searchParams.delete('sort');
        if (filterState.kpi !== 'all') u.searchParams.set('kpi', filterState.kpi);
        if (filterState.chips.size)    u.searchParams.set('chips', [...filterState.chips].join(','));
        if (filterState.zone)          u.searchParams.set('zone', filterState.zone);
        if (filterState.distance)      u.searchParams.set('sort', 'distance');
        try { history.replaceState(null, '', u.toString()); } catch (e) { /* old browsers */ }
    }

    // ─── 3. Test d'un card vs filtres actifs ──────────────────────
    function matchesFilters(el) {
        // Combine KPI + chips. Un chip "today" et un KPI "today" sont
        // équivalents — la double-coche n'a pas d'effet.
        const status     = el.dataset.taskStatus;
        const isLate     = el.dataset.late === '1';
        const isToday    = el.dataset.scheduledToday === '1';
        const hasProblem = el.dataset.hasProblem === '1';
        const hasReject  = el.dataset.hasReject === '1';
        const commune    = el.dataset.commune || '';

        if (filterState.kpi === 'today' && !isToday) return false;
        if (filterState.zone && commune !== filterState.zone) return false;

        for (const c of filterState.chips) {
            if (c === 'late'     && !isLate)    return false;
            if (c === 'today'    && !isToday)   return false;
            if (c === 'problem'  && !hasProblem) return false;
            if (c === 'reject'   && !hasReject) return false;
            if (c === 'en_route' && status !== 'en_route') return false;
            if (c === 'en_cours' && status !== 'en_cours') return false;
        }
        return true;
    }

    // ─── 4. Applique les filtres au DOM + recalc compteurs/sections ─
    function applyFilters() {
        const poses = document.querySelectorAll('.pose[data-task-id]');
        let visible = 0;
        poses.forEach(p => {
            const match = matchesFilters(p);
            p.style.display = match ? '' : 'none';
            p.classList.toggle('is-filtered-out', !match);
            if (match) visible++;
        });

        // Masque les sections vides
        document.querySelectorAll('.day-section').forEach(sec => {
            const has = sec.querySelector('.pose:not([style*="display: none"]):not([style*="display:none"])');
            sec.style.display = has ? '' : 'none';
        });

        // Empty state si aucun match
        const empty = document.getElementById('ts-empty-filter');
        if (empty) {
            const anyFilter = filterState.kpi !== 'all' || filterState.chips.size > 0 || filterState.zone;
            empty.style.display = (anyFilter && visible === 0) ? 'block' : 'none';
        }

        // Bouton "Effacer" visible uniquement si filtres actifs
        const clearBtn = document.getElementById('ts-filter-clear');
        if (clearBtn) {
            clearBtn.style.display = (filterState.chips.size || filterState.kpi !== 'all' || filterState.zone)
                ? 'inline-block' : 'none';
        }
    }

    // ─── 5. Compteurs chips (live, basés sur les cards SSR) ──────
    function refreshChipCounts() {
        const counts = { late: 0, today: 0, problem: 0, reject: 0, en_route: 0, en_cours: 0 };
        document.querySelectorAll('.pose[data-task-id]').forEach(p => {
            if (p.dataset.late === '1')          counts.late++;
            if (p.dataset.scheduledToday === '1') counts.today++;
            if (p.dataset.hasProblem === '1')    counts.problem++;
            if (p.dataset.hasReject === '1')     counts.reject++;
            const st = p.dataset.taskStatus;
            if (st === 'en_route') counts.en_route++;
            if (st === 'en_cours') counts.en_cours++;
        });
        Object.entries(counts).forEach(([k, v]) => {
            const el = document.querySelector(`[data-cnt="${k}"]`);
            if (el) el.textContent = v;
        });
        // Masque chips à 0 (réduit le bruit visuel)
        document.querySelectorAll('.filter-chip[data-filter]').forEach(c => {
            const k = c.dataset.filter;
            if (counts[k] === 0 && !filterState.chips.has(k)) {
                c.style.display = 'none';
            } else {
                c.style.display = '';
            }
        });
    }

    // ─── 6. Branchement chips ─────────────────────────────────────
    document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
        chip.addEventListener('click', () => {
            const k = chip.dataset.filter;
            if (filterState.chips.has(k)) filterState.chips.delete(k);
            else filterState.chips.add(k);
            chip.classList.toggle('is-active', filterState.chips.has(k));
            writeFiltersToUrl();
            applyFilters();
        });
    });
    document.getElementById('ts-filter-clear')?.addEventListener('click', () => {
        filterState.chips.clear();
        filterState.kpi = 'all';
        filterState.zone = null;
        document.querySelectorAll('.filter-chip.is-active').forEach(c => c.classList.remove('is-active'));
        document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(c => {
            c.classList.toggle('is-active', c.dataset.kpiFilter === 'all');
            c.setAttribute('aria-pressed', c.dataset.kpiFilter === 'all' ? 'true' : 'false');
        });
        writeFiltersToUrl();
        applyFilters();
    });

    // ─── 7. Branchement KPI grid → filterState.kpi ────────────────
    // Le code existant écoutait déjà les data-kpi-filter et appelait
    // applyKpiFilter(). On surcharge ici en relayant vers applyFilters,
    // pour combiner KPI + chips (avant : KPI seul réinitialisait tout).
    document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Stoppe la propagation au handler legacy (qui resetait tout)
            e.stopImmediatePropagation();
            const name = btn.dataset.kpiFilter;
            filterState.kpi = name; // 'all' ou 'today'
            document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
            writeFiltersToUrl();
            applyFilters();
        }, true); // capture phase — précède le handler legacy
    });

    // ─── 8. TOC zones cliquable (smooth scroll vers section) ──────
    document.querySelectorAll('.zone-toc-chip').forEach(a => {
        a.addEventListener('click', (e) => {
            const href = a.getAttribute('href');
            if (!href || !href.startsWith('#')) return;
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                // Highlight bref de la section ciblée
                target.style.transition = 'box-shadow .8s';
                target.style.boxShadow = '0 0 0 3px rgba(232,160,32,.5)';
                setTimeout(() => target.style.boxShadow = '', 1200);
            }
        });
    });

    // ─── 9. Select2 — recherche AJAX paginée (full dataset) ──────
    const $search = $('#ts-search');
    if ($search.length) {
        $search.select2({
            placeholder: $search.data('placeholder') || 'Rechercher…',
            allowClear:  true,
            minimumInputLength: 0,
            dropdownParent: $('.controls-bar'),
            language: {
                inputTooShort: () => '',
                searching:     () => '🔄 On cherche…',
                noResults:     () => 'Aucun panneau trouvé',
                errorLoading:  () => 'Problème — réessaie',
            },
            ajax: {
                url: SEARCH_URL,
                dataType: 'json',
                delay: 220,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                data: (params) => {
                    // On respecte les chips actifs côté serveur aussi : la
                    // recherche globale reflète le contexte filtre.
                    const d = {
                        q:    params.term || '',
                        page: params.page || 1,
                        per_page: 20,
                    };
                    if (filterState.chips.has('late'))    d.late = 1;
                    if (filterState.chips.has('today'))   d.today = 1;
                    if (filterState.chips.has('problem')) d.problem = 1;
                    if (filterState.chips.has('reject'))  d.reject = 1;
                    if (filterState.chips.has('en_route')) d.status = 'en_route';
                    if (filterState.chips.has('en_cours')) d.status = 'en_cours';
                    if (filterState.zone) d.commune = filterState.zone;
                    if (filterState.distance && filterState.geo) {
                        d.sort = 'distance';
                        d.lat  = filterState.geo.lat;
                        d.lng  = filterState.geo.lng;
                    }
                    return d;
                },
                processResults: (data, params) => {
                    params.page = params.page || 1;
                    return {
                        results: (data.results || []).map(r => ({ ...r, id: r.id, text: r.text })),
                        pagination: { more: data.pagination?.more === true },
                    };
                },
                cache: true,
            },
            templateResult: formatSearchOption,
            templateSelection: (item) => item.text || item.ref || '🔍 Rechercher…',
            escapeMarkup: m => m, // on contrôle le HTML
        });

        function formatSearchOption(item) {
            if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
            const thumbStyle = item.thumb_url
                ? `style="background-image:url('${item.thumb_url}')"`
                : '';
            const thumb = item.thumb_url
                ? `<span class="s2-thumb" ${thumbStyle}></span>`
                : `<span class="s2-thumb">🪧</span>`;
            const pills = [];
            if (item.is_late)     pills.push('<span class="s2-pill late">⏰ Retard</span>');
            if (item.has_reject)  pills.push('<span class="s2-pill reject">🚫 Refusée</span>');
            if (item.has_problem) pills.push('<span class="s2-pill warn">⚠ Signalée</span>');
            const meta = [
                item.commune ? '📍 ' + item.commune : '',
                item.campaign ? '📢 ' + (item.campaign.length > 24 ? item.campaign.slice(0, 24) + '…' : item.campaign) : '',
            ].filter(Boolean).join(' · ');
            return $(`
                <div class="s2-row">
                    ${thumb}
                    <div class="s2-info">
                        <div class="s2-ref">${item.ref || ''} ${pills.join(' ')}</div>
                        <div class="s2-name">${item.name || ''}</div>
                        <div class="s2-meta">${meta}</div>
                    </div>
                </div>
            `);
        }

        // Sélection → scroll vers la card si en DOM, sinon affiche un
        // focus modal au-dessus de la liste avec les infos + Maps.
        $search.on('select2:select', function (e) {
            const item = e.params.data;
            if (!item || !item.id) return;
            const existing = document.querySelector(`.pose[data-task-id="${item.id}"]`);
            if (existing) {
                existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
                existing.style.transition = 'box-shadow .8s';
                existing.style.boxShadow = '0 0 0 3px var(--accent), 0 12px 36px -10px rgba(232,160,32,.5)';
                setTimeout(() => existing.style.boxShadow = '', 1800);
            } else {
                openFocusModal(item);
            }
            // Reset le select pour permettre une nouvelle recherche
            setTimeout(() => $search.val(null).trigger('change'), 100);
        });
    }

    // ─── 10. Focus modal : si la pose n'est pas en SSR, on l'ouvre ─
    //         dans une carte focus avec lien Maps + ref + adresse.
    function openFocusModal(item) {
        const existing = document.getElementById('ts-focus-modal');
        if (existing) existing.remove();
        const goUrl = (item.lat && item.lng)
            ? `https://www.google.com/maps/dir/?api=1&destination=${item.lat},${item.lng}`
            : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent([item.adresse, item.quartier, item.commune, "Côte d'Ivoire"].filter(Boolean).join(', '))}`;
        const ov = document.createElement('div');
        ov.id = 'ts-focus-modal';
        ov.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);display:flex;align-items:flex-end;justify-content:center;padding:0';
        ov.innerHTML = `
            <div style="background:#fff;width:100%;max-width:520px;border-radius:18px 18px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));animation:tsUp .25s ease">
                <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px">
                    ${item.thumb_url
                        ? `<span style="flex:0 0 64px;width:64px;height:64px;border-radius:12px;background:url('${item.thumb_url}') center/cover;border:1px solid var(--border)"></span>`
                        : `<span style="flex:0 0 64px;width:64px;height:64px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--text3)">🪧</span>`}
                    <div style="flex:1;min-width:0">
                        <div style="font-family:ui-monospace,monospace;font-size:17px;font-weight:800;color:var(--accent-dark)">${item.ref || ''}</div>
                        <div style="font-size:13px;color:var(--text);font-weight:600;margin-top:2px">${item.name || ''}</div>
                        <div style="font-size:11.5px;color:var(--text3);margin-top:4px">
                            📍 ${item.commune || '—'}${item.adresse ? ' · ' + item.adresse : ''}
                        </div>
                        ${item.campaign ? `<div style="font-size:11px;color:var(--text2);margin-top:3px">📢 ${item.campaign}</div>` : ''}
                    </div>
                </div>
                ${item.is_late ? '<div style="background:rgba(239,68,68,.08);color:#b91c1c;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:700;margin-bottom:10px">⏰ En retard — fais celui-ci en premier</div>' : ''}
                ${item.reject_reason ? `<div style="background:rgba(239,68,68,.08);color:#b91c1c;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:10px">🚫 Photo refusée : ${item.reject_reason}</div>` : ''}
                <div style="display:flex;gap:8px;margin-top:6px">
                    <a href="${goUrl}" target="_blank" rel="noopener" style="flex:1;min-height:48px;display:flex;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:800;border-radius:12px;text-decoration:none;font-size:14px">🧭 Y aller</a>
                    <button type="button" data-act="close" style="flex:0 0 96px;min-height:48px;background:#f3f4f6;color:#4b5563;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer">Fermer</button>
                </div>
                <div style="margin-top:12px;font-size:11px;color:var(--text3);line-height:1.5">
                    Ce panneau n'est pas dans ta liste du moment.
                    Va sur place, prends la photo et envoie — tout sera enregistré.
                </div>
            </div>`;
        document.body.appendChild(ov);
        ov.querySelector('[data-act="close"]').addEventListener('click', () => ov.remove());
        ov.addEventListener('click', (e) => { if (e.target === ov) ov.remove(); });
    }

    // ─── 11. Tri par distance GPS (haversine, calcul JS local) ────
    const distBtn = document.getElementById('ts-distance-btn');
    if (distBtn) {
        distBtn.addEventListener('click', async () => {
            if (filterState.distance) {
                // Toggle off — restaure l'ordre SSR original
                filterState.distance = false;
                filterState.geo = null;
                distBtn.classList.remove('is-active');
                document.getElementById('ts-distance-label').textContent = 'Distance';
                restoreSsrOrder();
                document.querySelectorAll('.pose-distance').forEach(e => e.remove());
                writeFiltersToUrl();
                return;
            }
            distBtn.classList.add('is-active');
            document.getElementById('ts-distance-label').textContent = '📡 Position…';
            const pos = await getGeoPosition();
            if (!pos) {
                distBtn.classList.remove('is-active');
                document.getElementById('ts-distance-label').textContent = 'Distance';
                toastSmall('On ne te trouve pas. Autorise le GPS sur ton téléphone.', 'error');
                return;
            }
            filterState.geo = { lat: pos.lat, lng: pos.lng };
            filterState.distance = true;
            document.getElementById('ts-distance-label').textContent = '✓ Proche';
            sortByDistance(pos.lat, pos.lng);
            writeFiltersToUrl();
        });
    }

    function getGeoPosition() {
        return new Promise(resolve => {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                () => resolve(null),
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
            );
        });
    }

    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
            + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function formatDistance(m) {
        if (m < 950) return Math.round(m) + ' m';
        return (m / 1000).toFixed(1).replace('.0', '') + ' km';
    }

    function sortByDistance(lat, lng) {
        // Pour chaque section commune, on trie les cards par distance
        // (la TOC zones reste pertinente : on va de proche en proche
        // DANS chaque zone). Au-delà : option future de re-grouper.
        document.querySelectorAll('.day-section').forEach(sec => {
            const cards = Array.from(sec.querySelectorAll('.pose[data-task-id]'));
            cards.forEach(c => {
                const pLat = parseFloat(c.dataset.lat);
                const pLng = parseFloat(c.dataset.lng);
                const d = (isNaN(pLat) || isNaN(pLng)) ? Infinity : haversine(lat, lng, pLat, pLng);
                c.dataset.distanceM = String(d);
                // Insère/MAJ le pill distance
                let pill = c.querySelector('.pose-distance');
                if (!pill) {
                    pill = document.createElement('span');
                    pill.className = 'pose-distance';
                    const sub = c.querySelector('.pose-sub');
                    if (sub) sub.appendChild(pill);
                }
                pill.textContent = '📡 ' + (isFinite(d) ? formatDistance(d) : '—');
            });
            cards.sort((a, b) => parseFloat(a.dataset.distanceM) - parseFloat(b.dataset.distanceM));
            cards.forEach(c => sec.appendChild(c));
        });
    }

    function restoreSsrOrder() {
        // L'ordre SSR initial est encodé par data-task-id croissant
        // (les plus urgentes ont les IDs les plus anciens — pas idéal).
        // Mieux : on conserve une trace de l'ordre SSR au load.
        document.querySelectorAll('.day-section').forEach(sec => {
            const cards = Array.from(sec.querySelectorAll('.pose[data-task-id]'));
            cards.sort((a, b) => (parseInt(a.dataset.ssrOrder || a.dataset.taskId, 10))
                              - (parseInt(b.dataset.ssrOrder || b.dataset.taskId, 10)));
            cards.forEach(c => sec.appendChild(c));
        });
    }

    // Mémorise l'ordre SSR initial pour restauration propre
    document.querySelectorAll('.day-section').forEach(sec => {
        Array.from(sec.querySelectorAll('.pose[data-task-id]')).forEach((c, i) => {
            c.dataset.ssrOrder = String(i);
        });
    });

    // ─── 12. Hero « Prochaine pose » : photo input → pipeline existant ─
    // L'input data-next-photo réutilise le handler change global déjà
    // codé plus haut (preview, GPS, compression, upload). Mais il faut
    // l'attacher à la card correspondante dans le DOM principal (sinon
    // pas de data-task-id sur le label). On délègue : au moment du
    // change, on simule un clic sur l'input de la card #data-task-id.
    const hero = document.getElementById('next-pose-hero');
    if (hero) {
        const nextTaskId = hero.dataset.nextTaskId;
        const heroInput  = hero.querySelector('[data-next-photo]');
        heroInput?.addEventListener('change', function () {
            const file = heroInput.files?.[0];
            if (!file) return;
            const targetCard = document.querySelector(`.pose-line[data-task-id="${nextTaskId}"]`);
            const targetInput = targetCard?.querySelector('[data-photo-input]');
            if (!targetInput) {
                // La pose n'est pas dans la liste rendue (au-delà du cap) :
                // dans ce cas on ouvre quand même le pipeline en simulant
                // un upload direct via fetch.
                directUploadFromHero(file, nextTaskId);
                heroInput.value = '';
                return;
            }
            // Transfère le fichier à l'input cible et déclenche son change
            const dt = new DataTransfer();
            dt.items.add(file);
            targetInput.files = dt.files;
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            heroInput.value = '';
        });
        // « Y aller » : déclenche aussi le bump status en_route comme la
        // ligne standard. On laisse le delegate global s'en charger en
        // posant un data-go-maps sur le lien (déjà fait dans le HTML).
        hero.querySelector('[data-next-go-maps]')?.setAttribute('data-go-maps', '1');
    }

    async function directUploadFromHero(file, taskId) {
        toastSmall('On prépare ta photo…', 'info');
        const fd = new FormData();
        fd.append('photo', file, 'photo.jpg');
        fd.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)));
        try {
            const r = await fetch(`/tech/${TOKEN}/poses/${taskId}/photo`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: fd,
            });
            const d = await r.json().catch(() => ({}));
            if (r.ok && d.ok) {
                toastSmall('Photo envoyée — panneau posé !', 'success');
                setTimeout(() => location.reload(), 1200);
            } else {
                toastSmall(d.error || `Erreur ${r.status}`, 'error');
            }
        } catch (e) {
            toastSmall('Pas de réseau — réessaie quand ça revient', 'error');
        }
    }

    function toastSmall(msg, type) {
        const c = document.getElementById('toast-container');
        if (!c) return;
        const t = document.createElement('div');
        t.className = 'toast ' + (type || 'success');
        t.textContent = msg;
        c.appendChild(t);
        requestAnimationFrame(() => t.classList.add('show'));
        setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2800);
    }

    // ─── 13. Init au load : URL → state → DOM ───────────────────
    readFiltersFromUrl();
    // Restaure les chips actifs depuis l'URL
    filterState.chips.forEach(k => {
        const chip = document.querySelector(`.filter-chip[data-filter="${k}"]`);
        chip?.classList.add('is-active');
    });
    if (filterState.kpi === 'today') {
        const kpiBtn = document.querySelector('.kpi-card[data-kpi-filter="today"]');
        kpiBtn?.classList.add('is-active');
        kpiBtn?.setAttribute('aria-pressed', 'true');
        document.querySelector('.kpi-card[data-kpi-filter="all"]')?.classList.remove('is-active');
    }
    refreshChipCounts();
    applyFilters();

    // ─── 14. Service Worker — PWA & cache offline ───────────────
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset('tech-sw.js') }}', { scope: '/' })
            .then(reg => {
                // Bonne hygiène : log si MAJ du SW dispo (n'active pas auto)
                reg.addEventListener('updatefound', () => {
                    /* nouvelle version en cours d'install — prendra effet
                       au prochain cold start de la PWA */
                });
            })
            .catch(() => { /* échec silencieux : pas critique */ });
    }

    // ─── 15. Détection online / offline + flush queue Background Sync ──
    const offlineBanner = document.getElementById('ts-offline-banner');
    function updateOfflineState() {
        if (!offlineBanner) return;
        if (navigator.onLine === false) offlineBanner.classList.add('show');
        else offlineBanner.classList.remove('show');
        // Retour online → on tente de rejouer la queue offline
        if (navigator.onLine !== false) flushUploadQueue();
    }
    window.addEventListener('online',  updateOfflineState);
    window.addEventListener('offline', updateOfflineState);
    updateOfflineState();

    // ═══════════════════════════════════════════════════════════════
    // ─── 16. MODE TOURNÉE — TSP nearest-neighbor côté serveur ────
    //
    // Le tech clique "🚀 Tournée" → on demande la géoloc → on POST l'IDs
    // des cards rendues à /poses/optimize → le serveur renvoie l'ordre
    // optimal + distances cumulées. On réordonne les cards en mode
    // "tournée" (badge numéro, ordre forcé sur toutes les sections,
    // groupage par zone désactivé visuellement).
    // ═══════════════════════════════════════════════════════════════
    const TOUR_URL = "{{ route('tech.space.optimize', $token) }}";
    const tourBtn = document.getElementById('ts-tour-btn');
    const tourSummary = document.getElementById('ts-tour-summary');
    let tourActive = false;
    let originalParentByCard = new Map(); // pour restaurer le DOM

    function preserveOriginalOrder() {
        document.querySelectorAll('.pose[data-task-id]').forEach(c => {
            originalParentByCard.set(c, { parent: c.parentNode, index: Array.from(c.parentNode.children).indexOf(c) });
        });
    }
    preserveOriginalOrder();

    function exitTourMode() {
        tourActive = false;
        tourBtn?.classList.remove('is-active');
        document.getElementById('ts-tour-label').textContent = 'Tournée';
        tourSummary?.classList.remove('show');
        document.querySelectorAll('.pose.tour-mode').forEach(c => {
            c.classList.remove('tour-mode');
            c.removeAttribute('data-tour-step');
            c.querySelector('.pose-tour-leg')?.remove();
        });
        // Restaure la position d'origine de chaque card
        originalParentByCard.forEach((info, card) => {
            const ref = info.parent.children[info.index];
            if (ref && ref !== card) info.parent.insertBefore(card, ref);
            else info.parent.appendChild(card);
        });
        document.querySelectorAll('.day-section').forEach(sec => { sec.style.display = ''; });
        applyFilters();
    }

    tourBtn?.addEventListener('click', async () => {
        if (tourActive) { exitTourMode(); return; }
        if (!navigator.geolocation) {
            toastSmall('GPS bloqué — autorise-le pour utiliser cette option.', 'error');
            return;
        }
        tourBtn.disabled = true;
        document.getElementById('ts-tour-label').textContent = '🛰…';
        const pos = await getGeoPosition();
        if (!pos) {
            tourBtn.disabled = false;
            document.getElementById('ts-tour-label').textContent = 'Tournée';
            toastSmall('On ne te trouve pas — autorise le GPS.', 'error');
            return;
        }
        document.getElementById('ts-tour-label').textContent = '🔄…';
        try {
            const ids = Array.from(document.querySelectorAll('.pose[data-task-id]'))
                .map(c => parseInt(c.dataset.taskId, 10)).filter(Boolean);
            const u = new URL(TOUR_URL, location.origin);
            u.searchParams.set('lat', pos.lat.toFixed(6));
            u.searchParams.set('lng', pos.lng.toFixed(6));
            u.searchParams.set('scope', 'rendered');
            ids.forEach(id => u.searchParams.append('ids[]', id));
            const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error('optimize');
            applyTourOrder(d.order, d.total_meters);
        } catch (e) {
            toastSmall('On n\'arrive pas à calculer — essaie encore.', 'error');
            document.getElementById('ts-tour-label').textContent = 'Tournée';
        } finally {
            tourBtn.disabled = false;
        }
    });

    function applyTourOrder(order, totalMeters) {
        tourActive = true;
        tourBtn.classList.add('is-active');
        document.getElementById('ts-tour-label').textContent = '✓';

        // 1. Annule les filtres pour révéler toutes les cards de la tournée
        document.querySelectorAll('.day-section').forEach(s => s.style.display = '');

        // 2. Crée une section "Tournée" en tête, déplace toutes les cards
        //    selon l'ordre TSP, ajoute le badge numéro + la distance leg.
        let tourSec = document.getElementById('ts-tour-section');
        if (!tourSec) {
            tourSec = document.createElement('div');
            tourSec.id = 'ts-tour-section';
            tourSec.className = 'day-section';
            tourSec.innerHTML = '<div class="commune-header"><div class="ch-left"><h2 style="color:#15803d">🚀 Mon chemin</h2><span class="count">' + order.length + ' arrêts</span></div></div>';
            const empty = document.getElementById('ts-empty-filter');
            empty.parentNode.insertBefore(tourSec, empty.nextSibling);
        } else {
            // reset content sauf header
            tourSec.querySelector('.count').textContent = order.length + ' arrêts';
            // remove previous cards
            Array.from(tourSec.querySelectorAll('.pose')).forEach(c => c.remove());
        }

        order.forEach((step, idx) => {
            const card = document.querySelector(`.pose[data-task-id="${step.id}"]`);
            if (!card) return;
            card.classList.add('tour-mode');
            card.setAttribute('data-tour-step', idx + 1);
            // Ajoute / met à jour le pill "leg distance"
            let leg = card.querySelector('.pose-tour-leg');
            if (!leg) {
                leg = document.createElement('span');
                leg.className = 'pose-tour-leg';
                const sub = card.querySelector('.pose-sub');
                if (sub) sub.appendChild(leg);
            }
            leg.textContent = '🚀 +' + formatDistance(step.leg_meters);
            tourSec.appendChild(card);
        });

        // 3. Masque les autres sections (les cards y ont été déplacées)
        document.querySelectorAll('.day-section').forEach(sec => {
            if (sec === tourSec) return;
            if (!sec.querySelector('.pose[data-task-id]')) sec.style.display = 'none';
        });

        // 4. Affiche la banner total
        document.getElementById('ts-tour-count').textContent = order.length;
        document.getElementById('ts-tour-total').textContent = totalMeters >= 1000
            ? (totalMeters / 1000).toFixed(1).replace('.0', '') + ' km'
            : totalMeters + ' m';
        tourSummary.classList.add('show');
        tourSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.getElementById('ts-tour-quit')?.addEventListener('click', exitTourMode);

    // ═══════════════════════════════════════════════════════════════
    // ─── 17. BACKGROUND SYNC photo offline ────────────────────────
    //
    // Stratégie : si le tech upload une photo en mode offline (ou si
    // l'upload échoue par timeout réseau), on enqueue le FormData
    // sérialisé en IndexedDB. Au retour online (ou au prochain load
    // de la page), on rejoue les uploads en arrière-plan. Le tech voit
    // un badge "📤 N en attente" dans la barre de contrôles + un toast
    // au succès du rejouage.
    //
    // Fonctionne sur Chrome / Edge / Android (Background Sync API) et
    // sur iOS Safari via fallback rejouage au load (online event).
    // ═══════════════════════════════════════════════════════════════
    const SYNC_DB  = 'panora-tech-uploads';
    const SYNC_STORE = 'queue';

    function openDb() {
        return new Promise((resolve, reject) => {
            const r = indexedDB.open(SYNC_DB, 1);
            r.onupgradeneeded = () => {
                const db = r.result;
                if (!db.objectStoreNames.contains(SYNC_STORE)) {
                    db.createObjectStore(SYNC_STORE, { keyPath: 'id', autoIncrement: true });
                }
            };
            r.onsuccess = () => resolve(r.result);
            r.onerror = () => reject(r.error);
        });
    }
    async function queueCount() {
        try {
            const db = await openDb();
            return new Promise(resolve => {
                const tx = db.transaction(SYNC_STORE, 'readonly');
                const req = tx.objectStore(SYNC_STORE).count();
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => resolve(0);
            });
        } catch (e) { return 0; }
    }
    async function refreshSyncBadge() {
        const n = await queueCount();
        const badge = document.getElementById('ts-sync-badge');
        const cnt   = document.getElementById('ts-sync-count');
        if (!badge) return;
        if (n > 0) { badge.style.display = ''; cnt.textContent = n; }
        else badge.style.display = 'none';
    }

    // Hook minimal : intercepte les échecs réseau d'upload photo (le
    // pipeline existant fait fetch /poses/{id}/photo). On enrichit ce
    // pipeline en ré-utilisant la fonction window.queueOfflinePhoto
    // qui peut être appelée depuis le handler photo en cas d'erreur.
    window.queueOfflinePhoto = async function (taskId, file, gps, contradictionReason) {
        try {
            const db = await openDb();
            const tx = db.transaction(SYNC_STORE, 'readwrite');
            const fileBuf = await file.arrayBuffer();
            tx.objectStore(SYNC_STORE).add({
                taskId,
                fileBuf,
                fileName: file.name || 'photo.jpg',
                fileType: file.type || 'image/jpeg',
                gps,
                contradictionReason,
                queuedAt: new Date().toISOString(),
                token: '{{ $token }}',
            });
            tx.oncomplete = () => {
                refreshSyncBadge();
                toastSmall('📤 Photo gardée — on l\'enverra dès que tu as du réseau', 'info');
            };
        } catch (e) {
            console.warn('queueOfflinePhoto failed', e);
        }
    };

    async function flushUploadQueue() {
        try {
            const db = await openDb();
            const all = await new Promise(resolve => {
                const tx = db.transaction(SYNC_STORE, 'readonly');
                const req = tx.objectStore(SYNC_STORE).getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => resolve([]);
            });
            if (!all.length) { refreshSyncBadge(); return; }
            let okCount = 0, failCount = 0;
            for (const entry of all) {
                try {
                    const blob = new Blob([entry.fileBuf], { type: entry.fileType });
                    const fd = new FormData();
                    fd.append('photo', blob, entry.fileName);
                    if (entry.gps?.lat) fd.append('gps_lat', entry.gps.lat);
                    if (entry.gps?.lng) fd.append('gps_lng', entry.gps.lng);
                    if (entry.contradictionReason) fd.append('contradicts_signalement_reason', entry.contradictionReason);
                    fd.append('client_uuid', 'queue-' + entry.id);
                    const r = await fetch(`/tech/${entry.token}/poses/${entry.taskId}/photo`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: fd,
                    });
                    if (r.ok) {
                        okCount++;
                        await new Promise(resolve => {
                            const tx = db.transaction(SYNC_STORE, 'readwrite');
                            tx.objectStore(SYNC_STORE).delete(entry.id);
                            tx.oncomplete = resolve; tx.onerror = resolve;
                        });
                    } else {
                        failCount++;
                    }
                } catch (e) {
                    failCount++;
                }
            }
            refreshSyncBadge();
            if (okCount > 0) {
                toastSmall(`✓ ${okCount} photo${okCount > 1 ? 's' : ''} envoyée${okCount > 1 ? 's' : ''} — merci !`, 'success');
            }
            if (failCount > 0) {
                toastSmall(`${failCount} photo${failCount > 1 ? 's' : ''} pas encore envoyée${failCount > 1 ? 's' : ''} — on réessaiera`, 'error');
            }
        } catch (e) {
            console.warn('flushUploadQueue failed', e);
        }
    }
    window.flushUploadQueue = flushUploadQueue;

    document.getElementById('ts-sync-badge')?.addEventListener('click', flushUploadQueue);

    // Init badge + flush si online (cas Safari sans Background Sync API)
    refreshSyncBadge();
    if (navigator.onLine !== false) flushUploadQueue();
});
</script>

</body>
</html>

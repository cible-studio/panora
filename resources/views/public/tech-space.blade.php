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

{{-- Phase 3 SM1 — publication TECH_CONFIG (csrf + token + routes + bootstrap)
     consommé par les modules JS chargés juste après. À garder AVANT le
     <script type="module"> qui suit. --}}
@include('public.tech.partials._js_config', ['token' => $token])

{{-- Phase 3 SM1 — entry des modules ES (api/state/offline/sw-register +
     features extraites au fur et à mesure des lots F/G). Le `?v=` invalide
     le cache navigateur à chaque déploiement (clé : APP_VERSION du .env). --}}
<script type="module" src="{{ asset('js/tech/tech-app.js') }}?v={{ config('app.version', '1') }}"></script>

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

    // ── Y aller + Sur place + Changement statut + Modale justifier — migrés vers
    //    public/js/tech/features/status-changes.js (SM1.5 Lot 2) ──

    // ── Polling heartbeat — migré vers public/js/tech/features/heartbeat.js (Phase 3 SM1) ──

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

    // ── Signaler un problème — migré vers public/js/tech/features/report.js (SM1.5 Lot 1) ──
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
    // Reste inline : hero (section 12) + background sync upload (17+).
    // Tout le reste a été migré vers public/js/tech/features/.
    // ═══════════════════════════════════════════════════════════════
    const TOKEN = @json($token);

    // ─── 1-8. État filtres + bindings chips/KPI/zone/TOC + URL persistance — migrés vers public/js/tech/features/filters.js (SM1.5 Lot 5) ──
    // ─── 9-10. Select2 + openFocusModal — migrés vers public/js/tech/features/search.js (SM1.5 Lot 3) ──
    // ─── 11. Distance haversine + memorize SSR order — migrés vers public/js/tech/features/geolocate.js (SM1.5 Lot 4) ──

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

    // ─── 13. Init au load (URL → state → DOM) — migré vers filters.js init() (SM1.5 Lot 5) ──
    // ─── 14. Service Worker — migré vers public/js/tech/core/sw-register.js (Phase 3 SM1) ──
    // ─── 15. Détection online/offline — migrée vers public/js/tech/core/offline.js (Phase 3 SM1) ──
    // ─── 16. MODE TOURNÉE — migré vers public/js/tech/features/geolocate.js (SM1.5 Lot 4) ──

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

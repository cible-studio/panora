// public/js/tech/features/upload.js — SM1.5 Lot 6.
//
// Pipeline complet de la pige terrain : aperçu → contradiction →
// compression → GPS → POST /photo → traitement 422 → success animation
// → refreshDayCounters. + raccourci Hero "Prochaine pose". + queue
// IndexedDB pour rejouage offline (background sync + flush au retour
// réseau).
//
// Source : 1er <script> inline pré-SM1.5 (lignes 261-561) + sections 12
// et 17 du 2e <script> (lignes 783-840 et 873-998 post-Lot 5).
// Migration 1:1 comportement-identique, sauf :
//   - flashSuccess / toast / toastSmall / compressImage : suppression
//     des copies inline, on consomme ui-helpers.js.
//   - askContradictionReason : import depuis status-changes.js (déjà
//     exporté en Lot 2).
//   - window.queueOfflinePhoto / window.flushUploadQueue : on garde
//     l'exposition window comme legacy bridge utilisée par offline.js,
//     MAIS offline.js bascule sur l'import nommé en Phase C (cf.
//     core/offline.js mis à jour dans le même commit).
//
// Dépendances :
//   - core/state.js          : (aucune lecture directe pour l'instant)
//   - core/api.js            : urlForTask
//   - core/ui-helpers.js     : flashSuccess, toast, toastSmall, compressImage
//   - features/status-changes.js : askContradictionReason
//   - window.TECH_CONFIG     : csrfToken, techToken, routes.photoTpl

import { urlForTask } from '../core/api.js';
import { compressImage, flashSuccess, toast, toastSmall } from '../core/ui-helpers.js';
import { askContradictionReason } from './status-changes.js';

// ───────────────────────────────────────────────────────────────
// BACKGROUND SYNC — IndexedDB queue pour photos uploads offline
// ───────────────────────────────────────────────────────────────
//
// Stratégie : si le tech upload une photo en mode offline (ou si l'upload
// échoue par timeout réseau), on enqueue le FormData sérialisé en
// IndexedDB. Au retour online (ou au prochain load de la page), on
// rejoue les uploads en arrière-plan. Le tech voit un badge "📤 N en
// attente" dans la barre de contrôles + un toast au succès du rejouage.
//
// Fonctionne sur Chrome / Edge / Android (Background Sync API) et sur
// iOS Safari via fallback rejouage au load (online event).
const SYNC_DB    = 'panora-tech-uploads';
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
        r.onerror   = () => reject(r.error);
    });
}

async function queueCount() {
    try {
        const db = await openDb();
        return new Promise(resolve => {
            const tx = db.transaction(SYNC_STORE, 'readonly');
            const req = tx.objectStore(SYNC_STORE).count();
            req.onsuccess = () => resolve(req.result);
            req.onerror   = () => resolve(0);
        });
    } catch (e) { return 0; }
}

export async function refreshSyncBadge() {
    const n = await queueCount();
    const badge = document.getElementById('ts-sync-badge');
    const cnt   = document.getElementById('ts-sync-count');
    if (!badge) return;
    if (n > 0) { badge.style.display = ''; cnt.textContent = n; }
    else badge.style.display = 'none';
}

export async function queueOfflinePhoto(taskId, file, gps, contradictionReason) {
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
            token: window.TECH_CONFIG?.techToken,
        });
        tx.oncomplete = () => {
            refreshSyncBadge();
            toastSmall('📤 Photo gardée — on l\'enverra dès que tu as du réseau', 'info');
        };
    } catch (e) {
        console.warn('queueOfflinePhoto failed', e);
    }
}

export async function flushUploadQueue() {
    try {
        const db = await openDb();
        const all = await new Promise(resolve => {
            const tx = db.transaction(SYNC_STORE, 'readonly');
            const req = tx.objectStore(SYNC_STORE).getAll();
            req.onsuccess = () => resolve(req.result || []);
            req.onerror   = () => resolve([]);
        });
        if (!all.length) { refreshSyncBadge(); return; }

        const csrf = window.TECH_CONFIG?.csrfToken
            || document.querySelector('meta[name="csrf-token"]')?.content;

        let okCount = 0, failCount = 0;
        for (const entry of all) {
            try {
                const blob = new Blob([entry.fileBuf], { type: entry.fileType });
                const fd = new FormData();
                fd.append('photo', blob, entry.fileName);
                if (entry.gps?.lat) fd.append('gps_lat', entry.gps.lat);
                if (entry.gps?.lng) fd.append('gps_lng', entry.gps.lng);
                if (entry.contradictionReason) {
                    fd.append('contradicts_signalement_reason', entry.contradictionReason);
                }
                fd.append('client_uuid', 'queue-' + entry.id);
                const url = `/tech/${entry.token}/poses/${entry.taskId}/photo`;
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
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

// ───────────────────────────────────────────────────────────────
// PIPELINE UPLOAD PRINCIPAL — aperçu / contradiction / compress / GPS / POST
// ───────────────────────────────────────────────────────────────

// Géolocalisation robuste (best-effort, ne bloque pas l'upload).
// 1er essai haute précision (10 s — zones difficiles), retry en précision
// dégradée (réseau/cellule) avant d'abandonner. Renvoie aussi acc (±m).
//
// Hotfix 2026-06-19 : log la raison de l'échec GPS pour diagnostic
// (permission denied / position unavailable / timeout). La fonction
// retourne toujours null en cas d'échec, mais window.__lastGpsError
// permet d'afficher le motif dans la modale T3.
function getPosition() {
    if (!navigator.geolocation) {
        window.__lastGpsError = 'API geolocation indisponible sur ce navigateur';
        console.warn('[gps] navigator.geolocation absent');
        return Promise.resolve(null);
    }
    const attempt = (opts, label) => new Promise(resolve => {
        navigator.geolocation.getCurrentPosition(
            pos => {
                window.__lastGpsError = null;
                resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy });
            },
            (err) => {
                const codes = { 1: 'PERMISSION_DENIED', 2: 'POSITION_UNAVAILABLE', 3: 'TIMEOUT' };
                const codeLabel = codes[err.code] || ('CODE_' + err.code);
                window.__lastGpsError = `${codeLabel}${err.message ? ' — ' + err.message : ''} (${label})`;
                console.warn(`[gps] ${label} échec :`, codeLabel, err.message);
                resolve(null);
            },
            opts
        );
    });
    return attempt({ enableHighAccuracy: true,  timeout: 10000, maximumAge: 15000 }, 'haute précision')
        .then(r => r || attempt({ enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }, 'précision dégradée'));
}

// SM2a Lot 3.1 — Refonte vers T3 (spec §3) : preview photo + bandeau
// noir overlay GPS + heure + boîte verdict GPS colorée selon distance
// haversine au panneau cible.
//
// Le tech voit ce qu'il s'apprête à envoyer (flou, cadrage, etc.) et
// peut "Refaire" sans avoir envoyé une mauvaise photo.
//
// Retour : Promise<{gps: {lat, lng, acc}|null} | false>
//   - false : tech a annulé (refaire / Escape)
//   - { gps } : tech a confirmé. gps réutilisé en aval pour ne pas
//               re-prompt la géoloc.
function haversineMetersT3(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDistanceT3(m) {
    if (m == null || isNaN(m)) return '—';
    if (m < 950) return Math.round(m) + ' m';
    return (m / 1000).toFixed(1).replace('.0', '') + ' km';
}

function verdictForDistance(m) {
    if (m == null || isNaN(m)) return null;
    if (m <  100) return { v: 'ok',   t: '✓ Position GPS bien enregistrée',
                           s: `Tu es à ${formatDistanceT3(m)} du panneau.`,   icon: '✓' };
    if (m <  500) return { v: 'warn', t: `⚠ Tu es à ${formatDistanceT3(m)} du panneau`,
                           s: 'Vérifie que c\'est bien le bon avant d\'envoyer.', icon: '⚠' };
    return            { v: 'bad',  t: `✗ Tu es à ${formatDistanceT3(m)} du panneau`,
                           s: 'C\'est probablement pas le bon — refais sur place.', icon: '✗' };
}

function askPhotoPreview(file, pose) {
    return new Promise((resolve) => {
        const overlay = document.getElementById('sm2-t3-overlay');
        if (!overlay) {
            // Fallback défensif : si le partial T3 n'est pas chargé pour
            // une raison X, on confirme direct (comportement legacy).
            console.warn('[sm2-t3] partial absent — fallback auto-confirm');
            resolve({ gps: null });
            return;
        }

        const url = URL.createObjectURL(file);
        const img = overlay.querySelector('[data-field="photo"]');
        img.src = url;

        const setText = (sel, txt) => {
            const el = overlay.querySelector(sel);
            if (el) el.textContent = txt;
        };
        const panelRef = pose?.querySelector('.pose-ref')?.textContent?.trim()
                      || pose?.querySelector('[data-field="ref"]')?.textContent?.trim()
                      || pose?.dataset.taskId
                      || '—';
        setText('[data-field="pose-ref"]', panelRef);

        // Heure de capture (proxy : maintenant, suffisamment précis).
        const now = new Date();
        setText('[data-field="time-text"]', now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }));

        // Verdict initial : pending
        const verdict = overlay.querySelector('[data-field="verdict"]');
        verdict.setAttribute('data-verdict', 'pending');
        setText('[data-field="verdict-icon"]', '⏳');
        setText('[data-field="verdict-title"]', 'Calcul de la position…');
        setText('[data-field="verdict-sub"]',   '');
        setText('[data-field="gps-text"]',      'Position en cours…');

        // Affiche la modale
        overlay.hidden = false;
        overlay.removeAttribute('aria-hidden');
        requestAnimationFrame(() => overlay.classList.add('is-open'));
        document.body.style.overflow = 'hidden';

        let capturedGps = null;

        // Lance le calcul GPS + verdict en parallèle de l'affichage.
        getPosition().then(gps => {
            capturedGps = gps;
            if (!gps) {
                // Hotfix 2026-06-19 : affiche le motif de l'échec GPS
                // (permission, position indisponible, timeout, navigateur)
                // pour que le tech puisse corriger (autoriser, sortir, etc.).
                const err = window.__lastGpsError || '';
                let title = 'Position GPS non disponible';
                let sub   = 'On envoie quand même, l\'admin vérifiera.';
                if (err.includes('PERMISSION_DENIED')) {
                    title = '🔒 GPS bloqué par le navigateur';
                    sub   = 'Autorise la géolocalisation dans les paramètres du site, puis recommence.';
                } else if (err.includes('POSITION_UNAVAILABLE')) {
                    title = '📡 Pas de signal GPS';
                    sub   = 'Sors à l\'extérieur ou vers une fenêtre, puis recommence.';
                } else if (err.includes('TIMEOUT')) {
                    title = '⏱️ GPS trop lent à répondre';
                    sub   = 'Attends quelques secondes et recommence.';
                } else if (err.includes('indisponible')) {
                    title = '🚫 Géolocalisation non supportée';
                    sub   = 'Ce navigateur ne fournit pas le GPS — bascule sur le téléphone.';
                }
                verdict.setAttribute('data-verdict', 'unknown');
                setText('[data-field="verdict-icon"]', '❓');
                setText('[data-field="verdict-title"]', title);
                setText('[data-field="verdict-sub"]',   sub);
                setText('[data-field="gps-text"]',      err || 'GPS bloqué');
                return;
            }
            setText('[data-field="gps-text"]',
                `${gps.lat.toFixed(4)}, ${gps.lng.toFixed(4)}${gps.acc ? ' ±' + Math.round(gps.acc) + ' m' : ''}`);

            const lat = parseFloat(pose?.dataset.lat);
            const lng = parseFloat(pose?.dataset.lng);
            if (!isFinite(lat) || !isFinite(lng)) {
                verdict.setAttribute('data-verdict', 'unknown');
                setText('[data-field="verdict-icon"]', '📍');
                setText('[data-field="verdict-title"]', 'Position du panneau inconnue');
                setText('[data-field="verdict-sub"]',   'On peut quand même envoyer.');
                return;
            }
            const m = haversineMetersT3(gps.lat, gps.lng, lat, lng);
            const ver = verdictForDistance(m);
            if (ver) {
                verdict.setAttribute('data-verdict', ver.v);
                setText('[data-field="verdict-icon"]', ver.icon);
                setText('[data-field="verdict-title"]', ver.t);
                setText('[data-field="verdict-sub"]',   ver.s);
            }
        });

        const close = (result) => {
            URL.revokeObjectURL(url);
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            setTimeout(() => { overlay.hidden = true; }, 220);
            document.body.style.overflow = '';
            // Détache les listeners temporaires
            document.removeEventListener('keydown', onEsc);
            overlay.removeEventListener('click', onClick);
            resolve(result);
        };
        const onEsc = (ev) => { if (ev.key === 'Escape') close(false); };
        const onClick = (ev) => {
            if (ev.target.closest('[data-action="t3-redo"]')) { ev.preventDefault(); close(false); return; }
            if (ev.target.closest('[data-action="t3-send"]')) { ev.preventDefault(); close({ gps: capturedGps }); return; }
        };
        document.addEventListener('keydown', onEsc);
        overlay.addEventListener('click', onClick);
    });
}

// SM2a Lot 3.2 — Écran T4 "Succès + pose suivante". Remplace
// l'ancien flashSuccess() (overlay 900ms) par un plein écran 4s qui
// célèbre la photo envoyée et propose la pose suivante. Calcul de la
// pose suivante côté JS depuis le DOM (priorité même commune).
function pickNextPose(currentPose) {
    const currentCommune = currentPose?.dataset.commune || '';
    const currentTaskId  = currentPose?.dataset.taskId  || '';
    const remaining = Array.from(document.querySelectorAll('.pose-line[data-task-id]'))
        .filter(p => p.dataset.taskId !== currentTaskId);
    if (!remaining.length) return null;
    const same = remaining.find(p => p.dataset.commune === currentCommune);
    return same || remaining[0];
}

function getTechFirstName() {
    // Lit le prénom depuis le H1 "Bonjour {name}" du topbar.
    const hello = document.querySelector('.hero-text h1')?.textContent || '';
    const m = hello.match(/Bonjour\s+(\S+)/i);
    return m ? m[1].replace(/[!.]/g, '') : '';
}

function showSuccessScreenT4(currentPose) {
    const overlay = document.getElementById('sm2-t4-overlay');
    if (!overlay) {
        flashSuccess('Photo envoyée&nbsp;! Bravo 🎉');
        return;
    }

    // Compteurs : le total ACTUEL inclut encore la pose courante (elle
    // n'a pas encore été fadée). Donc done = doneActuel+1, total =
    // doneActuel + remainingActuel.
    const totalActiveEl = document.querySelector('[data-total-active]');
    const remainingNow  = document.querySelectorAll('.pose-line[data-task-id]').length;
    // On lit le compteur "fait" depuis le sub-label "doneToday" ou on
    // recalcule via totalAssigned - remainingNow + 1. Simple : on
    // affiche juste "1 panneau fait de plus" si on n'a pas la baseline.
    const doneEl = overlay.querySelector('[data-field="done-count"]');
    const totalEl= overlay.querySelector('[data-field="total-count"]');
    // Compteurs déduits : on présume que le tech connaît son total via
    // le KPI "À faire" (data-kpi-value="totalActive"). Le compteur va
    // décrémenter quand la card sera retirée. Pour T4, on affiche
    // l'état "après cette photo" = totalRemaining - 1 (cette photo est
    // en train d'être validée par l'admin, donc encore en remainingNow).
    const totalActive = parseInt(totalActiveEl?.textContent || '0', 10);
    const done = Math.max(0, totalActive - (remainingNow - 1));
    if (doneEl)  doneEl.textContent  = String(done);
    if (totalEl) totalEl.textContent = String(totalActive);

    const firstName = getTechFirstName();
    const nameField = overlay.querySelector('[data-field="first-name"]');
    if (nameField) nameField.textContent = firstName ? firstName + ' !' : '!';

    const nextBlock = overlay.querySelector('[data-field="next-block"]');
    const doneBlock = overlay.querySelector('[data-field="done-block"]');
    const next = pickNextPose(currentPose);

    if (next) {
        nextBlock.hidden = false;
        doneBlock.hidden = true;

        const thumbEl = overlay.querySelector('[data-field="next-thumb"]');
        const thumbBg = next.querySelector('.pose-thumb')?.style.backgroundImage || '';
        const thumbUrl = thumbBg.match(/url\(['"]?(.+?)['"]?\)/)?.[1] || null;
        if (thumbUrl) {
            thumbEl.style.backgroundImage = `url('${thumbUrl}')`;
            thumbEl.textContent = '';
        } else {
            thumbEl.style.backgroundImage = '';
            thumbEl.textContent = '🪧';
        }

        const setNext = (sel, txt) => {
            const el = overlay.querySelector(sel);
            if (el) el.textContent = txt || '—';
        };
        setNext('[data-field="next-ref"]',     next.querySelector('.pose-ref')?.textContent?.trim());
        setNext('[data-field="next-name"]',    next.querySelector('.pose-name')?.textContent?.trim());
        setNext('[data-field="next-commune"]', next.dataset.commune ? '📍 ' + next.dataset.commune : '');

        // Mémorise la task suivante sur le panel (lue par les handlers)
        overlay.dataset.nextTaskId = next.dataset.taskId;
    } else {
        nextBlock.hidden = true;
        doneBlock.hidden = false;
        overlay.dataset.nextTaskId = '';
    }

    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';

    // Vibration succès sur mobile (cohérence avec flashSuccess actuel)
    if (navigator.vibrate) { try { navigator.vibrate([40, 60, 120]); } catch (e) {} }

    // Auto-close après 4s par défaut. Le tap utilisateur l'annule.
    const autoTimer = setTimeout(() => closeT4(), 4000);

    const onClick = (ev) => {
        if (ev.target.closest('[data-action="t4-continue"]')) {
            ev.preventDefault();
            clearTimeout(autoTimer);
            const nextId = overlay.dataset.nextTaskId;
            closeT4();
            // Ouvre le drawer T2 sur la pose suivante. import dynamique
            // pour éviter les cycles + retomber gracefully si le module
            // n'est pas chargé.
            if (nextId) {
                const target = document.querySelector(`.pose-line[data-task-id="${nextId}"]`);
                if (target) target.click();
            }
            return;
        }
        if (ev.target.closest('[data-action="t4-other"]')) {
            ev.preventDefault();
            clearTimeout(autoTimer);
            closeT4();
            return;
        }
    };
    function closeT4() {
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        setTimeout(() => { overlay.hidden = true; }, 250);
        document.body.style.overflow = '';
        overlay.removeEventListener('click', onClick);
    }
    overlay.addEventListener('click', onClick);
}

// Recalcule les compteurs "X poses" sous chaque date après retrait
// d'une pose terminée (évite l'incohérence visuelle), met à jour le
// total header, et reload la page si plus aucune pose.
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
    const totalActiveEl = document.querySelector('[data-total-active]');
    if (totalActiveEl) {
        const total = document.querySelectorAll('.pose').length;
        totalActiveEl.textContent = total;
    }
    if (document.querySelectorAll('.pose').length === 0) {
        location.reload();
    }
}

// Handler change délégué : se déclenche sur n'importe quel
// [data-photo-input] du DOM courant ou futur.
function bindMainUpload() {
    const csrf  = window.TECH_CONFIG?.csrfToken;
    const photoTpl = window.TECH_CONFIG?.routes?.photoTpl;

    document.addEventListener('change', async (e) => {
        const input = e.target.closest('[data-photo-input]');
        if (!input || !input.files?.[0]) return;
        const label  = input.closest('label');
        const pose   = label?.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        // Garde-fou anti-"undefined URL" (hotfix 2026-06-19) :
        // si le DOM ne porte pas data-task-id, on prévient le tech au
        // lieu d'envoyer un POST silencieux vers une route inexistante.
        if (!taskId || taskId === 'undefined') {
            console.error('[upload] data-task-id manquant — pose non identifiée. input=', input, 'pose=', pose);
            toast('Erreur : pose non identifiée. Recharge la page.', 'error');
            input.value = '';
            return;
        }

        // 0. Aperçu T3 : le tech voit sa photo + verdict GPS avant qu'on
        //    déclenche compression/upload. S'il refuse, on reset l'input.
        //    La modale retourne { gps } pour qu'on réutilise la position
        //    captée pendant la preview (évite un 2e prompt GPS).
        const preview = input.files[0];
        const previewResult = await askPhotoPreview(preview, pose);
        if (!previewResult) { input.value = ''; return; }

        // Garde-fou contradiction : signalement non résolu sur cette pose
        // → on demande la justification AVANT compression/upload pour ne
        // pas perdre le travail si annulation. Le serveur la trace dans
        // pige.notes.
        let contradictionReason = null;
        const blockingLabel = pose?.dataset.blockingSignalLabel;
        if (blockingLabel) {
            contradictionReason = await askContradictionReason(blockingLabel);
            if (contradictionReason === null) { input.value = ''; return; }
        }

        const file = input.files[0];
        const originalLabel = label.innerHTML;
        label.innerHTML = '🔄 Compression…';
        label.style.pointerEvents = 'none';

        // 1) Compression locale (HEIC iPhone → JPEG, gros fichier → ~500 KB)
        const blob = await compressImage(file);

        // 2) GPS — déjà capté en T3, on réutilise. Fallback si manquant.
        let gps = previewResult.gps;
        if (!gps) {
            label.innerHTML = '📍 GPS…';
            gps = await getPosition();
        }
        label.innerHTML = (gps && gps.acc) ? `📍 ±${Math.round(gps.acc)} m · envoi…` : '⏳ Envoi…';

        // 3) FormData
        const form = new FormData();
        const isBlob = blob instanceof Blob && blob !== file;
        form.append('photo', blob, isBlob ? 'photo.jpg' : (file.name || 'photo.jpg'));
        if (gps) {
            form.append('gps_lat', gps.lat.toFixed(6));
            form.append('gps_lng', gps.lng.toFixed(6));
        }
        form.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2))));
        if (contradictionReason) {
            form.append('contradicts_signalement_reason', contradictionReason);
        }

        try {
            const url = urlForTask(photoTpl, taskId);
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
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
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
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
                Object.assign(data, data2);
            } else if (!res.ok || !data.ok) {
                // Remonte d'abord les erreurs de validation Laravel (422),
                // sinon le message du controller, sinon fallback avec le
                // code HTTP — beaucoup plus utile sur le terrain.
                const validation = data.errors ? Object.values(data.errors).flat().join(' · ') : '';
                const msg = validation || data.error || data.message || `Erreur ${res.status}`;
                toast(msg, 'error');
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                input.value = '';
                return;
            }
            // SM2a Lot 3.2 — Écran T4 plein écran 4s + pose suivante
            // remplace l'ancien flashSuccess overlay 900ms. Lecture du
            // .pose-line réelle (pas le drawer T2) pour pickNextPose :
            // les "pose-line" représentent les cards encore actives.
            const sourcePose = document.querySelector(`.pose-line[data-task-id="${taskId}"]`) || pose;

            // SM2c B2 — Si c'était la dernière pose du jour, on affiche
            // l'écran "Fin de journée" plein écran avec confettis au
            // lieu de l'écran T4 succès standard.
            if (data.is_last_pose_of_day && typeof window.__sm2cShowEndOfDay === 'function') {
                window.__sm2cShowEndOfDay({ sourcePose });
            } else {
                showSuccessScreenT4(sourcePose);
            }

            // Pose réalisée → retire la card avec une petite animation
            // de fade-out plus, depuis le hotfix 2026-06-19, on RECHARGE
            // la page après l'écran T4 pour que le serveur recalcule
            // $nextTask (la "Card MAINTENANT" doit afficher la pose
            // suivante, pas rester figée sur la pose qu'on vient de
            // terminer).
            // Ferme aussi le drawer T2 si la pose était ouverte dedans.
            if (document.querySelector('#sm2-t2-overlay.is-open')) {
                document.querySelector('#sm2-t2-overlay')?.classList.remove('is-open');
                setTimeout(() => {
                    const ov = document.querySelector('#sm2-t2-overlay');
                    if (ov) ov.hidden = true;
                }, 220);
            }
            if (sourcePose) {
                sourcePose.style.transition = 'all .4s ease-out';
                sourcePose.style.opacity   = '0';
                sourcePose.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    sourcePose.remove();
                    refreshDayCounters();
                }, 400);
            }
            // 2026-07-06 : la focus card autonome (#next-pose-hero) a été
            // retirée — les poses sont désormais toutes dans la liste avec
            // un badge "🔥 MAINTENANT" sur la ligne highlightée. Le fade
            // de la ligne source (sourcePose) au-dessus suffit.
            // Recharge la page après que l'écran T4 ait fini son animation
            // (4 s) — laisse le tech voir le succès, puis le serveur
            // recalcule $nextTask = la pose suivante par priorité.
            setTimeout(() => { window.location.reload(); }, 4500);
        } catch (err) {
            // En mode offline (ou erreur fetch), on enqueue la photo pour
            // un rejouage automatique au retour réseau. Évite au tech de
            // perdre sa photo après avoir parcouru un km pour atteindre
            // un panneau dans une zone sans réseau.
            if (navigator.onLine === false || err.name === 'TypeError') {
                try {
                    await queueOfflinePhoto(taskId, blob instanceof Blob ? blob : file, gps, contradictionReason);
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
}

// 2026-07-06 : la focus card autonome #next-pose-hero a été RETIRÉE
// suite au feedback patronne. La pose la plus prioritaire est
// maintenant highlightée dans la liste via _pose_card + badge
// "🔥 MAINTENANT". Toutes les actions passent par le drawer T2
// (flux unifié entre pose urgente et poses normales).
// bindHero() supprimé — le delegate bindGoMaps de status-changes.js
// gère tous les [data-go-maps] via event delegation.

export function init() {
    bindMainUpload();
    refreshSyncBadge();
    if (navigator.onLine !== false) flushUploadQueue();
    document.getElementById('ts-sync-badge')?.addEventListener('click', flushUploadQueue);
}

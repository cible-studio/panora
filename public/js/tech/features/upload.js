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
function getPosition() {
    if (!navigator.geolocation) return Promise.resolve(null);
    const attempt = (opts) => new Promise(resolve => {
        navigator.geolocation.getCurrentPosition(
            pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy }),
            ()  => resolve(null),
            opts
        );
    });
    return attempt({ enableHighAccuracy: true,  timeout: 10000, maximumAge: 15000 })
        .then(r => r || attempt({ enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }));
}

// Le tech voit ce qu'il s'apprête à envoyer (flou, cadrage, etc.) et
// peut "Reprendre" sans avoir envoyé une mauvaise photo.
// Retour : Promise<boolean> — true = envoyer ; false = annulé / reprendre.
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
        if (!taskId) return;

        // 0. Aperçu : le tech voit sa photo avant qu'on déclenche quoi que
        //    ce soit (compression, GPS, upload). S'il refuse, on reset
        //    l'input — il pourra reprendre sans pénalité.
        const preview = input.files[0];
        const panelRef = pose?.querySelector('.pose-ref')?.textContent?.trim()
                       || pose?.dataset.taskId;
        const confirmed = await askPhotoPreview(preview, panelRef);
        if (!confirmed) { input.value = ''; return; }

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

        // 2) GPS
        label.innerHTML = '📍 GPS…';
        const gps = await getPosition();
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

// ── Hero "Prochaine pose" ───────────────────────────────────────
// L'input data-next-photo réutilise le handler change global
// ci-dessus. Mais il faut l'attacher à la card correspondante dans le
// DOM principal (sinon pas de data-task-id sur le label). On délègue :
// au change, on simule un click sur l'input de la card. Si la pose n'est
// pas dans la liste SSR (au-delà du cap), on passe par directUploadFromHero
// qui POST directement sans passer par la pipeline d'aperçu.
function bindHero() {
    const hero = document.getElementById('next-pose-hero');
    if (!hero) return;
    const nextTaskId = hero.dataset.nextTaskId;
    const heroInput  = hero.querySelector('[data-next-photo]');
    heroInput?.addEventListener('change', function () {
        const file = heroInput.files?.[0];
        if (!file) return;
        const targetCard = document.querySelector(`.pose-line[data-task-id="${nextTaskId}"]`);
        const targetInput = targetCard?.querySelector('[data-photo-input]');
        if (!targetInput) {
            directUploadFromHero(file, nextTaskId);
            heroInput.value = '';
            return;
        }
        const dt = new DataTransfer();
        dt.items.add(file);
        targetInput.files = dt.files;
        targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        heroInput.value = '';
    });
    // « Y aller » : déclenche aussi le bump status en_route comme la ligne
    // standard. On laisse le delegate global s'en charger en posant un
    // data-go-maps sur le lien (déjà fait dans le HTML).
    hero.querySelector('[data-next-go-maps]')?.setAttribute('data-go-maps', '1');
}

async function directUploadFromHero(file, taskId) {
    const csrf  = window.TECH_CONFIG?.csrfToken;
    const photoTpl = window.TECH_CONFIG?.routes?.photoTpl;
    toastSmall('On prépare ta photo…', 'info');
    const fd = new FormData();
    fd.append('photo', file, 'photo.jpg');
    fd.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)));
    try {
        const r = await fetch(urlForTask(photoTpl, taskId), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
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

export function init() {
    bindMainUpload();
    bindHero();
    refreshSyncBadge();
    if (navigator.onLine !== false) flushUploadQueue();
    document.getElementById('ts-sync-badge')?.addEventListener('click', flushUploadQueue);
}

// public/js/tech/core/ui-helpers.js — SM1.5.
//
// Helpers UI mutualisés consommés par plusieurs features (report.js,
// upload.js, status-changes.js, etc.).
//
// Source : helpers globaux du <script> inline pré-SM1.5 :
//   - flashSuccess  (lignes ~297-303)
//   - toast         (lignes ~305-315)
//   - toastSmall    (lignes ~1397-1406)
//   - compressImage (lignes ~325-350)
//
// Migration 1:1 comportement-identique.

/**
 * Overlay plein écran avec checkmark + vibration. Affiché 900 ms puis
 * disparaît. Utilisé après upload photo / signalement / bump de statut
 * pour donner un retour fort au tech sur le terrain (≈ "ça a marché").
 */
export function flashSuccess(msg) {
    const ov = document.getElementById('ts-success');
    const m  = document.getElementById('ts-success-msg');
    if (m && msg) m.innerHTML = msg;
    if (navigator.vibrate) { try { navigator.vibrate([40, 60, 120]); } catch (e) {} }
    if (ov) { ov.classList.add('show'); setTimeout(() => ov.classList.remove('show'), 900); }
}

/**
 * Toast classique (3 s). Types acceptés : 'success' (vert), 'error' (rouge),
 * 'info' (bleu). Conteneur DOM #toast-container présent dans le squelette.
 */
export function toast(message, type = 'success') {
    const el = document.createElement('div');
    el.className = 'toast ' + type;
    el.textContent = message;
    const c = document.getElementById('toast-container');
    if (!c) return;
    c.appendChild(el);
    requestAnimationFrame(() => el.classList.add('show'));
    setTimeout(() => {
        el.classList.remove('show');
        setTimeout(() => el.remove(), 300);
    }, 3000);
}

/**
 * Variante plus courte (2,8 s) — utilisée par les notifications offline
 * (upload mis en queue, flush queue) qui doivent rester discrètes.
 */
export function toastSmall(msg, type) {
    const c = document.getElementById('toast-container');
    if (!c) return;
    const t = document.createElement('div');
    t.className = 'toast ' + (type || 'success');
    t.textContent = msg;
    c.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2800);
}

/**
 * Compression image côté client (canvas) — réduit à 2400 px max + JPEG q=0.85.
 *   - convertit HEIC/HEIF iPhone en JPEG (sinon GD serveur refuse)
 *   - ramène 20-30 MB de photo brute à 200-500 KB
 *   - upload rapide même en 4G médiocre
 * Best-effort : si le navigateur ne sait pas décoder (HEIC sur vieux
 * Android), retourne le fichier original — le serveur tentera (Intervention)
 * et a un fallback "stockage tel quel".
 */
export async function compressImage(file, maxSize = 2400, quality = 0.85) {
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
        return file;
    }
}

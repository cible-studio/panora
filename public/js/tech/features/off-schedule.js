// public/js/tech/features/off-schedule.js — SM2c B1.
//
// Détecte côté client si une pose est ouverte hors créneau (tolérance
// 60 min autour du data-scheduled-at de la .pose-line). Intercepte le
// tap juste avant pose-drawer.js et affiche la modale B1. Le flag
// localStorage `off_schedule_ack_<taskId>` mémorise le consentement
// pour ne pas re-pop la modale en boucle.

const SEL_OVERLAY = '#sm2c-b1-overlay';
const TOLERANCE_MIN = 60;
const ACK_KEY_PREFIX = 'off_schedule_ack_';
let pendingPoseEl = null;
let nextClickEvent = null;

function isOffSchedule(poseEl) {
    const iso = poseEl?.dataset?.scheduledAt;
    if (!iso) return false;
    const scheduled = new Date(iso);
    if (isNaN(scheduled.getTime())) return false;
    const diffMin = Math.abs((Date.now() - scheduled.getTime()) / 60000);
    return diffMin > TOLERANCE_MIN;
}

function ackKey(taskId) { return ACK_KEY_PREFIX + taskId; }

function alreadyAcked(taskId) {
    try { return localStorage.getItem(ackKey(taskId)) === 'true'; }
    catch (e) { return false; }
}

function markAcked(taskId) {
    try { localStorage.setItem(ackKey(taskId), 'true'); }
    catch (e) {}
}

function describeOffset(scheduledAt) {
    const diffMs = scheduledAt - Date.now();
    const abs = Math.abs(diffMs);
    const h = Math.floor(abs / 3600000);
    const m = Math.floor((abs % 3600000) / 60000);
    const direction = diffMs > 0 ? 'en avance' : 'de retard';
    const parts = [];
    if (h > 0) parts.push(`${h}h`);
    if (m > 0) parts.push(`${m} min`);
    return `${parts.join(' ') || 'quelques minutes'} ${direction}`;
}

function open(poseEl) {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay || !poseEl) return;
    pendingPoseEl = poseEl;

    const iso = poseEl.dataset.scheduledAt;
    const scheduled = new Date(iso);
    const titleEl = overlay.querySelector('[data-field="title"]');
    const subEl   = overlay.querySelector('[data-field="sub"]');
    const offset  = describeOffset(scheduled);
    titleEl && (titleEl.textContent = 'Tu démarres cette pose ' + offset);
    subEl   && (subEl.textContent   = `Elle était prévue à ${scheduled.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}. Tu peux la faire maintenant mais elle apparaîtra hors créneau pour le chef.`);

    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';
}

function close() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    setTimeout(() => { overlay.hidden = true; }, 220);
    document.body.style.overflow = '';
}

function confirmAndOpenDrawer() {
    if (!pendingPoseEl) { close(); return; }
    const taskId = pendingPoseEl.dataset.taskId;
    if (taskId) markAcked(taskId);
    close();
    // Re-déclenche le click pose-drawer.js prend le relais (qui ouvre T2)
    const pose = pendingPoseEl;
    pendingPoseEl = null;
    setTimeout(() => {
        // Dispatche un click sur la pose-line — pose-drawer.js l'intercepte
        // via event delegation et ouvre le drawer T2.
        const ev = new MouseEvent('click', { bubbles: true, cancelable: true });
        // On marque l'event pour que notre intercepteur le laisse passer.
        ev.__b1Bypass = true;
        pose.dispatchEvent(ev);
    }, 50);
}

export function init() {
    if (!document.querySelector(SEL_OVERLAY)) return;

    // Capture phase pour intercepter AVANT pose-drawer.js
    document.addEventListener('click', (e) => {
        // Si c'est notre propre re-click bypass → laisse passer
        if (e.__b1Bypass) return;

        const closeBtn = e.target.closest('[data-action="b1-cancel"]');
        if (closeBtn) { e.preventDefault(); close(); return; }
        const confirmBtn = e.target.closest('[data-action="b1-confirm"]');
        if (confirmBtn) { e.preventDefault(); confirmAndOpenDrawer(); return; }

        const pose = e.target.closest('.pose-line[data-task-id]');
        if (!pose) return;
        // Ne pas voler le clic des boutons internes (déjà géré par
        // pose-drawer.js avec sa propre logique closest a/button/label/input)
        if (e.target.closest('a[href]') || e.target.closest('button')
            || e.target.closest('label')  || e.target.closest('input')) return;

        if (!isOffSchedule(pose)) return;
        const taskId = pose.dataset.taskId;
        if (alreadyAcked(taskId)) return;

        // Intercepte : bloque l'ouverture du drawer T2, affiche B1.
        e.preventDefault();
        e.stopPropagation();
        open(pose);
    }, true); // capture
}

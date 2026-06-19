// public/js/tech/features/off-schedule.js — SM2c B1.
//
// Détecte côté client si une pose est ouverte hors créneau (tolérance
// 60 min autour du data-scheduled-at de la .pose-line). Intercepte le
// tap juste avant pose-drawer.js et affiche la modale B1. Le flag
// localStorage `off_schedule_ack_<taskId>` mémorise le consentement
// pour ne pas re-pop la modale en boucle.

const SEL_OVERLAY = '#sm2c-b1-overlay';
// Hotfix 2026-06-19 : tolérance portée à 120 min (= 2h) cohérente avec
// le seuil "négligeable" du helper PHP HumanTimeDiff::formatScheduleDiff.
// On peut faire des poses 2h en avance/retard sans modale interruptive.
const TOLERANCE_MIN = 120;
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

// Aligné sur app/Helpers/HumanTimeDiff::formatScheduleDiff (PHP).
// Évite d'afficher "275h 30 min en avance" pour une pose dans 11 jours.
//
// Retourne null si l'écart est négligeable (< 2h) → la modale ne s'ouvre
// pas. Sinon une formulation humaine prête à coller à la suite du verbe.
//
// Exemples (depuis maintenant) :
//   - 30 min      → null
//   - 6h après    → "avec 6 heures d'avance"
//   - 6h avant    → "avec 6 heures de retard"
//   - 20h après   → "avec moins d'un jour d'avance"
//   - 3 jours    → "avec 3 jours d'avance"
//   - 15 jours    → "prévue dans 2 semaines"
//   - 2 mois      → "prévue le 19 août"
function describeOffset(scheduledAt) {
    const diffMs    = scheduledAt - Date.now();
    const absMin    = Math.abs(diffMs) / 60000;
    if (absMin < 120) return null;

    const isLate    = diffMs < 0;
    const direction = isLate ? 'de retard' : "d'avance";

    // 2h ≤ écart < 12h
    if (absMin < 12 * 60) {
        const h = Math.floor(absMin / 60);
        return `avec ${h} heures ${direction}`;
    }
    // 12h ≤ écart < 24h
    if (absMin < 24 * 60) {
        return `avec moins d'un jour ${direction}`;
    }

    const absDays = Math.floor(absMin / (24 * 60));

    // 1j ≤ écart < 7j
    if (absDays < 7) {
        const jourMot = absDays > 1 ? 'jours' : 'jour';
        return `avec ${absDays} ${jourMot} ${direction}`;
    }

    // 7j ≤ écart < 30j — semaines pour l'avance, jours pour le retard
    if (absDays < 30 && !isLate) {
        const semaines   = Math.floor(absDays / 7);
        const semaineMot = semaines > 1 ? 'semaines' : 'semaine';
        return `prévue dans ${semaines} ${semaineMot}`;
    }
    if (absDays < 30 && isLate) {
        return `avec ${absDays} jours de retard`;
    }

    // > 30 jours → date pleine (en français localisé via toLocaleDateString)
    const dateStr = scheduledAt.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
    return `prévue le ${dateStr}`;
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

    // Si offset null (< 2h, ne devrait pas arriver — déjà filtré par
    // isOffSchedule — mais défensif), on n'ouvre pas la modale.
    if (!offset) { pendingPoseEl = null; return; }

    titleEl && (titleEl.textContent = 'Tu démarres cette pose ' + offset);
    // Wording neutre / bienveillant (hotfix 2026-06-19) : on garde
    // l'info de la date/heure prévue, sans le "hors créneau pour le chef"
    // qui anxiogénise le tech terrain.
    const datePart = scheduled.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long' });
    const timePart = scheduled.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    subEl   && (subEl.textContent   = `Elle était prévue le ${datePart} à ${timePart}. Tu peux la faire maintenant, c'est noté.`);

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

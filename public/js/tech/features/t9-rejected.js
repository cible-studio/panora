// public/js/tech/features/t9-rejected.js — SM2a Lot 5.2.
//
// Pilote le drawer T9 "Photos à refaire". Déclenché :
//   - Au tap sur le bandeau rouge _banner_t9_rejected.blade.php (Phase 1
//     Lot 1.5, qui pointait vers /piges?status=rejete — bascule en data-
//     action="open-t9" en Phase 5).
//   - Manuellement via data-action="open-t9" depuis n'importe où.
//
// Fermeture : data-action="close-t9" + Escape + clic hors-drawer.
//
// Action "📷 Refaire la photo" (data-action="t9-redo") : cherche la
// .pose-line[data-task-id=X] dans le carnet et clique son input photo
// → la caméra s'ouvre via la pipeline upload.js standard. Si la pose
// n'est pas dans le DOM (cap SSR dépassé), on alerte le tech.

const SEL_OVERLAY = '#sm2-t9-overlay';

function readContacts() {
    return window.TECH_CONFIG?.contacts || {};
}

function populateChiefContact(overlay) {
    const { chiefPhone } = readContacts();
    overlay.querySelectorAll('[data-field="chief-call"]').forEach(a => {
        if (chiefPhone) {
            a.setAttribute('href', 'tel:' + chiefPhone);
            a.hidden = false;
        } else {
            a.hidden = true;
        }
    });
}

function open() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    populateChiefContact(overlay);
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

function redo(taskId) {
    const pose = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
    if (!pose) {
        alert('La pose n\'est plus dans la liste. Va dans Mes photos pour la retrouver.');
        return;
    }
    const input = pose.querySelector('[data-photo-input]');
    if (!input) {
        alert('Impossible de relancer la caméra. Recharge la page.');
        return;
    }
    close();
    // Petit délai pour laisser le drawer se refermer avant de pop la caméra.
    setTimeout(() => {
        try {
            input.click();
        } catch (e) {
            console.warn('[sm2-t9] input.click failed', e);
        }
    }, 250);
}

export function init() {
    if (!document.querySelector(SEL_OVERLAY)) return;

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="open-t9"]')) {
            e.preventDefault();
            open();
            return;
        }
        if (e.target.closest('[data-action="close-t9"]')) {
            e.preventDefault();
            close();
            return;
        }
        const redoBtn = e.target.closest('[data-action="t9-redo"]');
        if (redoBtn) {
            e.preventDefault();
            redo(redoBtn.dataset.taskId);
            return;
        }
        // Clic sur l'overlay hors-drawer → close
        const overlay = e.target.closest(SEL_OVERLAY);
        if (overlay && !e.target.closest('#sm2-t9-drawer')) close();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.querySelector(`${SEL_OVERLAY}.is-open`)) {
            close();
        }
    });
}

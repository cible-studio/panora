// public/js/tech/features/help.js — SM2a Lot 5.1.
//
// Pilote la modale T8 "Besoin d'aide ?" (spec §3 T8). Ouvre :
//   1. Au tap sur le bouton "?" jaune du header (data-action="open-help")
//   2. AUTOMATIQUEMENT à la première visite (flag localStorage
//      tech_first_use à true par défaut). Le flag passe à 'false' à la
//      fermeture pour ne plus auto-ouvrir aux visites suivantes.
//
// Les boutons "Voir tutoriel" et "Appeler mon chef" sont peuplés depuis
// TECH_CONFIG.contacts.{tutorialVideoUrl,chiefPhone} — masqués si valeurs
// manquantes.

const SEL_OVERLAY = '#sm2-t8-overlay';
const FLAG_KEY    = 'tech_first_use';

function readContacts() {
    return window.TECH_CONFIG?.contacts || {};
}

function populateContacts(overlay) {
    const { tutorialVideoUrl, chiefPhone } = readContacts();
    const tut  = overlay.querySelector('[data-field="tutorial-link"]');
    const call = overlay.querySelector('[data-field="chief-call"]');
    if (tut) {
        if (tutorialVideoUrl) {
            tut.setAttribute('href', tutorialVideoUrl);
            tut.hidden = false;
        } else {
            tut.hidden = true;
        }
    }
    if (call) {
        if (chiefPhone) {
            call.setAttribute('href', 'tel:' + chiefPhone);
            call.hidden = false;
        } else {
            call.hidden = true;
        }
    }
}

function open() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    populateContacts(overlay);
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
    // À la 1re fermeture, on flag le tech comme "déjà vu" pour ne plus
    // auto-ouvrir aux visites suivantes. Le bouton "?" reste actif.
    try { localStorage.setItem(FLAG_KEY, 'false'); } catch (e) {}
}

export function init() {
    if (!document.querySelector(SEL_OVERLAY)) return;

    // Ouverture / fermeture via data-action
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="open-help"]')) {
            e.preventDefault();
            open();
            return;
        }
        if (e.target.closest('[data-action="close-help"]')) {
            e.preventDefault();
            close();
            return;
        }
        // Tap hors-modale → ferme
        const overlay = e.target.closest(SEL_OVERLAY);
        if (overlay && !e.target.closest('.sm2-t8-modal')) close();
    });

    // Escape ferme
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.querySelector(`${SEL_OVERLAY}.is-open`)) {
            close();
        }
    });

    // Auto-open à la première visite (flag absent = première fois).
    // Si TECH_CONFIG.flags.firstUse explicite à false, on n'ouvre pas même
    // si le localStorage est neuf (pour les écrans d'admin / debug).
    let firstUse;
    try {
        const stored = localStorage.getItem(FLAG_KEY);
        firstUse = stored === null; // null = jamais visité
    } catch (e) {
        firstUse = false;
    }
    if (firstUse && window.TECH_CONFIG?.flags?.firstUse !== false) {
        // Délai très court pour laisser le carnet T1 se peindre avant de
        // pop la modale (évite un "flash" visuel).
        setTimeout(() => open(), 300);
    }
}

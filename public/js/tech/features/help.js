// public/js/tech/features/help.js — SM2a Lot 5.1 + hotfix 2026-06-19.
//
// Pilote la modale T8 "Besoin d'aide ?" (spec §3 T8). S'ouvre :
//   1. Au tap sur le bouton "?" jaune du header (data-action="open-help")
//   2. AUTOMATIQUEMENT à la 1re visite (flag 'tech_help_seen' absent)
//
// Une fois fermée, le flag 'tech_help_seen' = 'true' est écrit en
// localStorage → plus d'auto-open aux visites suivantes. Le bouton "?"
// reste actif et permet de rouvrir manuellement.
//
// Hotfix 2026-06-19 :
//   - Renommage du flag canonique 'tech_first_use' → 'tech_help_seen'
//     (sémantique inversée : 'true' = déjà vu, donc plus d'auto-open).
//   - On lit AUSSI l'ancien flag pour ne pas re-déclencher la modale
//     chez les techs qui l'avaient déjà fermée avant le rename.
//
// Les boutons "Voir tutoriel" et "Appeler mon chef" sont peuplés depuis
// TECH_CONFIG.contacts.{tutorialVideoUrl,chiefPhone} — masqués si valeurs
// manquantes.

const SEL_OVERLAY     = '#sm2-t8-overlay';
const FLAG_KEY        = 'tech_help_seen';   // 'true' = modale déjà vue
const LEGACY_FLAG_KEY = 'tech_first_use';   // 'false' = modale déjà vue (legacy)

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

function shouldAutoOpen() {
    try {
        // Si flag canonique présent → modale déjà vue
        if (localStorage.getItem(FLAG_KEY) === 'true') return false;
        // Sinon on regarde l'ancien flag (compat avec la 1re version SM2a)
        if (localStorage.getItem(LEGACY_FLAG_KEY) === 'false') return false;
        return true;
    } catch (e) {
        // Mode privé / localStorage indisponible : on ne harcèle pas le tech,
        // on n'auto-open pas (sinon ça boucle à chaque reload).
        return false;
    }
}

function markSeen() {
    try {
        localStorage.setItem(FLAG_KEY, 'true');
        localStorage.setItem(LEGACY_FLAG_KEY, 'false'); // dual-write rétro-compat
    } catch (e) { /* localStorage indispo — best effort */ }
}

function open() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    populateContacts(overlay);
    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.classList.add('sm2-help-open');
}

function close() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    setTimeout(() => { overlay.hidden = true; }, 220);
    document.body.classList.remove('sm2-help-open');
    // Toute fermeture (croix, "OK j'ai compris", tap backdrop, Esc) marque
    // la modale comme vue pour éviter le harcèlement à chaque reload.
    markSeen();
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

    // Auto-open à la 1re visite uniquement. TECH_CONFIG.flags.firstUse=false
    // permet de désactiver explicitement (pages admin / debug / vue dev).
    if (shouldAutoOpen() && window.TECH_CONFIG?.flags?.firstUse !== false) {
        setTimeout(() => open(), 300);
    }
}

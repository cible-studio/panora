// public/js/tech/features/pose-drawer.js — SM2a Lot 2.1A.
//
// Pilote le drawer T2 "Détail d'une pose". Pas d'AJAX : tout est lu sur
// place depuis le DOM de la pose-line ciblée. Le drawer copie les
// data-task-id / data-task-status / data-go-maps de la card source vers
// son root, ce qui permet aux handlers existants (upload.js / status-
// changes.js / report.js) de fonctionner SANS modification — ils
// font tous closest('[data-task-id]') qui matchera #sm2-t2-drawer.
//
// Déclenchement :
//   1. Tap sur une .pose-line (mais hors d'un bouton d'action : on ne
//      veut pas intercepter les taps sur Y aller / Photo / Souci).
//   2. URL `?focus=task_X` au load → ouverture auto (Lot 2.1B / WhatsApp).
//
// Fermeture :
//   - Tap sur l'overlay (hors du panneau)
//   - Tap sur le bouton ✕ ou ← Retour (data-action="close-detail")
//   - Touche Escape
//   - back natif du navigateur (popstate)

const SEL_OVERLAY = '#sm2-t2-overlay';
const SEL_DRAWER  = '#sm2-t2-drawer';

function readTaskData(poseEl) {
    const photoEl = poseEl.querySelector('.pose-thumb');
    const photoBg = photoEl?.style.backgroundImage || '';
    const photoUrl = photoBg.match(/url\(['"]?(.+?)['"]?\)/)?.[1] || null;

    return {
        id:        poseEl.dataset.taskId || '',
        status:    poseEl.dataset.taskStatus || '',
        commune:   poseEl.dataset.commune || '',
        ref:       poseEl.querySelector('.pose-ref')?.textContent?.trim() || '',
        name:      poseEl.querySelector('.pose-name')?.textContent?.trim() || '',
        photo:     photoUrl,
        goUrl:     poseEl.querySelector('[data-go-maps]')?.getAttribute('href') || '#',
        late:      poseEl.dataset.late === '1',
        hasReject: poseEl.dataset.hasReject === '1',
    };
}

function populateDrawer(drawer, data) {
    drawer.dataset.taskId       = data.id;
    drawer.dataset.taskStatus   = data.status;

    const set = (sel, value) => {
        const el = drawer.querySelector(sel);
        if (el) el.textContent = value || '—';
    };
    set('[data-field="commune"]',  data.commune || 'Commune');
    set('[data-field="name"]',     data.name || data.ref);
    set('[data-field="ref"]',      data.ref);

    const img = drawer.querySelector('[data-field="photo"]');
    const fbk = drawer.querySelector('[data-field="photo-fallback"]');
    if (data.photo) {
        img.src = data.photo;
        img.hidden = false;
        if (fbk) fbk.hidden = true;
    } else {
        img.removeAttribute('src');
        img.hidden = true;
        if (fbk) fbk.hidden = false;
    }

    // Bouton Y aller : copie de l'URL Google Maps de la card source.
    const goBtn = drawer.querySelector('.sm2-t2-btn-go');
    if (goBtn) goBtn.setAttribute('href', data.goUrl);

    // Reset l'input file pour ne pas garder une sélection précédente.
    const fileInput = drawer.querySelector('[data-photo-input]');
    if (fileInput) fileInput.value = '';
}

function open(poseEl, options = {}) {
    const overlay = document.querySelector(SEL_OVERLAY);
    const drawer  = document.querySelector(SEL_DRAWER);
    if (!overlay || !drawer || !poseEl) return;

    populateDrawer(drawer, readTaskData(poseEl));

    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';

    // Push history entry sauf en cas d'ouverture depuis popstate (loop)
    if (!options.fromHistory) {
        try {
            history.pushState({ sm2T2Drawer: true }, '', location.href);
        } catch (e) { /* navigateurs anciens */ }
    }
}

function close(options = {}) {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    // Délai pour laisser l'animation jouer avant hidden
    setTimeout(() => { overlay.hidden = true; }, 220);

    if (!options.fromHistory && history.state?.sm2T2Drawer) {
        try { history.back(); } catch (e) {}
    }
}

function isActionable(target) {
    // Évite d'ouvrir le drawer quand le tech tape un bouton d'action
    // (Y aller, J'y suis, Souci, label photo) déjà géré ailleurs.
    return target.closest('a[href]')
        || target.closest('button')
        || target.closest('label')
        || target.closest('input');
}

export function init() {
    if (!document.querySelector(SEL_DRAWER)) return;

    // Tap sur une .pose-line (hors zone d'action) → ouvre le drawer
    document.addEventListener('click', (e) => {
        const closeTrigger = e.target.closest('[data-action="close-detail"]');
        if (closeTrigger) { e.preventDefault(); close(); return; }

        const overlay = e.target.closest(SEL_OVERLAY);
        if (overlay && !e.target.closest(SEL_DRAWER)) {
            // Tap hors du panneau → close
            close();
            return;
        }

        const pose = e.target.closest('.pose-line[data-task-id]');
        if (!pose) return;

        // Ne pas voler le clic des boutons internes de la card
        if (isActionable(e.target)) return;

        e.preventDefault();
        open(pose);
    });

    // Touche Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.querySelector(`${SEL_OVERLAY}.is-open`)) {
            close();
        }
    });

    // back natif du navigateur ferme le drawer
    window.addEventListener('popstate', () => {
        if (document.querySelector(`${SEL_OVERLAY}.is-open`)) {
            close({ fromHistory: true });
        }
    });

    // Auto-open depuis ?focus=task_X (Lot 2.1B / WhatsApp deep link)
    const focus = new URLSearchParams(location.search).get('focus');
    const match = focus?.match(/^task_(\d+)$/);
    if (match) {
        const pose = document.querySelector(`.pose-line[data-task-id="${match[1]}"]`);
        if (pose) {
            requestAnimationFrame(() => {
                pose.scrollIntoView({ behavior: 'auto', block: 'center' });
                open(pose, { fromHistory: true });
            });
        }
    }
}

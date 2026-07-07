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
        lat:       poseEl.dataset.lat || '',
        lng:       poseEl.dataset.lng || '',
        ref:       poseEl.querySelector('.pose-ref')?.textContent?.trim() || '',
        name:      poseEl.querySelector('.pose-name')?.textContent?.trim() || '',
        photo:     photoUrl,
        goUrl:     poseEl.dataset.goUrl
                || poseEl.querySelector('[data-go-maps]')?.getAttribute('href')
                || '#',
        late:      poseEl.dataset.late === '1',
        hasReject: poseEl.dataset.hasReject === '1',
        // Jalons du chrono (2026-07-07 : 3-boutons + timer live)
        startedAt: poseEl.dataset.startedAt || null,
        arrivedAt: poseEl.dataset.arrivedAt || null,
    };
}

function populateDrawer(drawer, data) {
    drawer.dataset.taskId       = data.id;
    drawer.dataset.taskStatus   = data.status;
    drawer.dataset.lat          = data.lat;
    drawer.dataset.lng          = data.lng;

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

    // Jalons chrono
    drawer.dataset.startedAt = data.startedAt || '';
    drawer.dataset.arrivedAt = data.arrivedAt || '';

    // Bouton "Je suis arrivé" — désactivé si déjà posé, brille sinon
    const arrivedBtn = drawer.querySelector('[data-action="mark-arrived"]');
    if (arrivedBtn) {
        arrivedBtn.disabled = !!data.arrivedAt;
        arrivedBtn.classList.toggle('is-done', !!data.arrivedAt);
        if (data.arrivedAt) arrivedBtn.innerHTML = '✅ Arrivé — chrono en cours';
        else arrivedBtn.innerHTML = '📍 Je suis arrivé sur place';
    }

    renderTimer(drawer);
}

/**
 * Chrono en direct — étape actuelle + temps écoulé depuis le jalon
 * pertinent. Rendu à l'ouverture du drawer et rafraîchi toutes les 5s
 * par un setInterval démarré dans init().
 *
 * Feature 2026-07-07 (patronne) : "il faudrait un bouton en route,
 * début de pose, fin de pose + décompte du début du compte à rebours
 * pour timer le temps de pose".
 */
function renderTimer(drawer) {
    const stageEl = drawer.querySelector('[data-field="timer-stage"]');
    const valueEl = drawer.querySelector('[data-field="timer-value"]');
    if (!stageEl || !valueEl) return;

    const status = drawer.dataset.taskStatus;
    if (status === 'realisee') {
        stageEl.textContent = '✅ Pose terminée';
        valueEl.textContent = 'Bravo, envoyée au bureau.';
        return;
    }

    const arrivedAt = drawer.dataset.arrivedAt ? new Date(drawer.dataset.arrivedAt) : null;
    const startedAt = drawer.dataset.startedAt ? new Date(drawer.dataset.startedAt) : null;

    if (arrivedAt && !isNaN(arrivedAt)) {
        stageEl.innerHTML = '⏱️ <strong>Sur place</strong> — chrono actif';
        valueEl.textContent = 'Depuis ' + humanDelta(new Date() - arrivedAt);
        valueEl.classList.add('is-live');
    } else if (startedAt && !isNaN(startedAt)) {
        stageEl.innerHTML = '🚗 <strong>En route</strong>';
        valueEl.textContent = 'Départ il y a ' + humanDelta(new Date() - startedAt) + '. Tape « Je suis arrivé » à ton arrivée pour démarrer le chrono de pose.';
        valueEl.classList.remove('is-live');
    } else {
        stageEl.textContent = '⏳ Prêt à démarrer';
        valueEl.textContent = 'Touche « Y aller en voiture » pour partir.';
        valueEl.classList.remove('is-live');
    }
}

/**
 * Format humain d'une durée en millisecondes.
 *   < 1 min       → "45 sec"
 *   < 1 h         → "12 min 34 sec"
 *   >= 1 h        → "1 h 12 min"
 */
function humanDelta(ms) {
    if (!ms || ms < 0) ms = 0;
    const totalSec = Math.floor(ms / 1000);
    const h = Math.floor(totalSec / 3600);
    const m = Math.floor((totalSec % 3600) / 60);
    const s = totalSec % 60;
    if (h > 0) return h + ' h ' + m + ' min';
    if (m > 0) return m + ' min ' + s + ' sec';
    return s + ' sec';
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

/**
 * POST AJAX vers /tech/{token}/poses/{taskId}/progress
 * Sert au bouton "Je suis arrivé" (percent=50) — pose arrived_at côté
 * serveur + statut en_cours. Refonte 2026-07-07 : les 5 paliers ont
 * été remplacés par 3 boutons chronologiques + chrono live.
 */
async function submitProgress(taskId, percent) {
    const pathParts = location.pathname.split('/').filter(Boolean);
    const token = pathParts[1]; // /tech/{token}/...
    if (!token) return { ok: false };
    const url = `/tech/${token}/poses/${taskId}/progress`;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    try {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: 'percent=' + encodeURIComponent(percent),
        });
        const data = await res.json().catch(() => ({}));
        return { ok: res.ok, data };
    } catch (e) {
        return { ok: false, error: e };
    }
}

/** Boucle 5s qui rafraîchit le chrono en direct dans le drawer ouvert. */
let _timerInterval = null;
function startTimerTicker() {
    if (_timerInterval) return;
    _timerInterval = setInterval(() => {
        const drawer = document.querySelector(SEL_DRAWER);
        const overlay = document.querySelector(SEL_OVERLAY + '.is-open');
        if (drawer && overlay) renderTimer(drawer);
    }, 5000);
}

export function init() {
    if (!document.querySelector(SEL_DRAWER)) return;

    // Tap sur une .pose-line (hors zone d'action) → ouvre le drawer
    document.addEventListener('click', (e) => {
        const closeTrigger = e.target.closest('[data-action="close-detail"]');
        if (closeTrigger) { e.preventDefault(); close(); return; }

        // Handler "Je suis arrivé sur place" — pose arrived_at + démarre chrono.
        // Refonte 2026-07-07 : remplace la barre de 5 paliers par 3 boutons.
        const arrivedBtn = e.target.closest('[data-action="mark-arrived"]');
        if (arrivedBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (arrivedBtn.disabled) return;
            const drawer = document.querySelector(SEL_DRAWER);
            const taskId = drawer?.dataset.taskId;
            if (!taskId) return;
            arrivedBtn.disabled = true;
            arrivedBtn.innerHTML = '⏳ Enregistrement…';
            submitProgress(taskId, 50).then(r => {
                if (r.ok && r.data?.ok) {
                    const nowIso = new Date().toISOString();
                    drawer.dataset.arrivedAt = nowIso;
                    drawer.dataset.taskStatus = r.data.status || 'en_cours';
                    const line = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
                    if (line) {
                        line.dataset.arrivedAt = nowIso;
                        line.dataset.taskStatus = r.data.status || 'en_cours';
                        line.dataset.progress = 50;
                    }
                    // Exclusivité STRICTE : le serveur a reposé les autres
                    // poses actives du tech à PLANIFIÉE (started_at et
                    // arrived_at effacés). On sync leur DOM.
                    const reverted = Array.isArray(r.data.reverted_ids) ? r.data.reverted_ids : [];
                    reverted.forEach(id => {
                        const other = document.querySelector(`.pose-line[data-task-id="${id}"]`);
                        if (other) {
                            other.dataset.startedAt = '';
                            other.dataset.arrivedAt = '';
                            other.dataset.taskStatus = 'planifiee';
                            other.dataset.progress = 0;
                            const otherDot = other.querySelector('.pose-dot');
                            if (otherDot) otherDot.style.background = '#e8a020';
                        }
                    });
                    arrivedBtn.classList.add('is-done');
                    arrivedBtn.innerHTML = '✅ Arrivé — chrono en cours';
                    renderTimer(drawer);
                } else {
                    arrivedBtn.disabled = false;
                    arrivedBtn.innerHTML = '📍 Je suis arrivé sur place';
                }
            });
            return;
        }

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

    // Démarre le rafraîchissement 5s du chrono en direct (feature 2026-07-07).
    startTimerTicker();

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

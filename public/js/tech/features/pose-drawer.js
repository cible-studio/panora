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
        // SM2a Lot 3.1 : lat/lng remontés pour calcul Haversine côté T3
        lat:       poseEl.dataset.lat || '',
        lng:       poseEl.dataset.lng || '',
        ref:       poseEl.querySelector('.pose-ref')?.textContent?.trim() || '',
        name:      poseEl.querySelector('.pose-name')?.textContent?.trim() || '',
        photo:     photoUrl,
        // 2026-07-06 : les .pose-line n'ont plus de descendant [data-go-maps]
        // depuis la suppression de la focus card. On lit maintenant l'URL
        // Maps directement depuis data-go-url sur la pose-line. Fallback sur
        // l'ancien pattern pour compat (aucun consommateur restant à date).
        goUrl:     poseEl.dataset.goUrl
                || poseEl.querySelector('[data-go-maps]')?.getAttribute('href')
                || '#',
        late:      poseEl.dataset.late === '1',
        hasReject: poseEl.dataset.hasReject === '1',
        progress:  parseInt(poseEl.dataset.progress || '0', 10),
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

    // Barre de progression 5 paliers — sync visuelle depuis la valeur DB
    renderProgress(drawer, data.progress || 0);
}

/**
 * Illumine les paliers <= currentPct et met à jour le badge %.
 * Feature 2026-07-06 (patronne) : progression manuelle en 5 paliers.
 */
function renderProgress(drawer, currentPct) {
    const valueEl = drawer.querySelector('[data-field="progress-percent"]');
    if (valueEl) valueEl.textContent = currentPct + '%';

    drawer.querySelectorAll('.sm2-t2-progress-step').forEach(btn => {
        const step = parseInt(btn.dataset.progress || '0', 10);
        const dot  = btn.querySelector('.sm2-t2-progress-dot');
        // Active = palier atteint OU dépassé. Le palier courant reçoit
        // en plus une pastille plus grosse. La régression est autorisée
        // depuis 2026-07-06 : tous les paliers restent cliquables, y
        // compris ceux inférieurs pour corriger une saisie erronée.
        if (step > 0 && step <= currentPct) {
            btn.classList.add('is-reached');
            if (dot) dot.textContent = step === currentPct ? '⬤' : '●';
        } else {
            btn.classList.remove('is-reached');
            if (dot) dot.textContent = '◯';
        }
        btn.classList.toggle('is-current', step === currentPct);
    });

    const hint = drawer.querySelector('[data-field="progress-hint"]');
    if (hint) {
        if (currentPct >= 100) {
            hint.textContent = '✅ Fini — envoie la photo pour clôturer.';
        } else if (currentPct >= 75) {
            hint.textContent = 'Collage en cours — bientôt la photo !';
        } else if (currentPct >= 50) {
            hint.textContent = 'Tu es sur place — bonne pose !';
        } else if (currentPct >= 25) {
            hint.textContent = 'En route vers le panneau…';
        } else {
            hint.textContent = 'Touche un palier pour dire à l\'admin où tu en es.';
        }
    }
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
 * Feature 2026-07-06 : progression manuelle en 5 paliers.
 * Route absolue construite depuis window.location car les templates
 * publics tech ne partagent pas TECH_CONFIG.routes.progress à date.
 */
async function submitProgress(taskId, percent) {
    // /tech/{token}/... → on garde /tech/{token} et on ajoute la sous-route
    const pathParts = location.pathname.split('/').filter(Boolean);
    // pathParts[0] = 'tech', pathParts[1] = token, [2] = 'poses' ou autre
    const token = pathParts[1];
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

export function init() {
    if (!document.querySelector(SEL_DRAWER)) return;

    // Tap sur une .pose-line (hors zone d'action) → ouvre le drawer
    document.addEventListener('click', (e) => {
        const closeTrigger = e.target.closest('[data-action="close-detail"]');
        if (closeTrigger) { e.preventDefault(); close(); return; }

        // Handler paliers de progression : capture avant qu'un autre code
        // ne referme le drawer par erreur.
        const stepBtn = e.target.closest('.sm2-t2-progress-step');
        if (stepBtn) {
            e.preventDefault();
            e.stopPropagation();
            const drawer = document.querySelector(SEL_DRAWER);
            const taskId = drawer?.dataset.taskId;
            const percent = parseInt(stepBtn.dataset.progress || '0', 10);
            if (!taskId || isNaN(percent)) return;

            // Feedback optimiste immédiat (le UX prime, on ne bloque pas)
            renderProgress(drawer, percent);
            stepBtn.classList.add('is-loading');
            submitProgress(taskId, percent).then(r => {
                stepBtn.classList.remove('is-loading');
                if (r.ok && r.data?.ok) {
                    const applied = r.data.percent ?? percent;
                    renderProgress(drawer, applied);
                    // Sync la .pose-line source pour cohérence si on rouvre
                    const line = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
                    if (line) {
                        line.dataset.progress = applied;
                        if (r.data.status) line.dataset.taskStatus = r.data.status;
                    }
                    // Feedback discret côté badge
                    const val = drawer.querySelector('[data-field="progress-percent"]');
                    if (val) { val.classList.add('is-updated'); setTimeout(() => val.classList.remove('is-updated'), 600); }
                } else {
                    // Rollback : re-render depuis la source
                    const line = document.querySelector(`.pose-line[data-task-id="${taskId}"]`);
                    renderProgress(drawer, parseInt(line?.dataset.progress || '0', 10));
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

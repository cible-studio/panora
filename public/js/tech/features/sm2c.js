// public/js/tech/features/sm2c.js — SM2c
//
// Module unique pour les écrans bonus B1 / B2 / B3 + préférences + finitions.
// Ne change pas le pipeline upload — il s'y greffe via window.__sm2cShowEndOfDay
// (consommé par upload.js si is_last_pose_of_day=true dans la réponse).
//
// Sub-modules internes :
//   - initOffSchedule()   B1 — intercept tap pose-line si hors créneau
//   - registerEndOfDay()  B2 — fonction globale appelée par upload.js
//   - initNotifications() B3 — drawer + badge + filtres
//   - initPreferences()   Phase 4 — drawer préférences (localStorage)
//   - applyStoredPrefs()  Phase 4 — applique haute lisibilité au load

import { init as initOffSchedule } from './off-schedule.js';

// ═════════════════════════════════════════════════════════════════════
// B2 — Écran "Fin de journée"
// ═════════════════════════════════════════════════════════════════════
function registerEndOfDay() {
    window.__sm2cShowEndOfDay = function (opts = {}) {
        const overlay = document.getElementById('sm2c-b2-overlay');
        if (!overlay) return;

        // Prénom : publié par l'en-tête de la refonte (2026-11-12), sinon le
        // H1 de l'ancien topbar (pattern SM2a).
        const hello = document.querySelector('.hero-text h1')?.textContent || '';
        const m = hello.match(/Bonjour\s+(\S+)/i);
        const firstName = document.querySelector('.tx-jour[data-prenom]')?.dataset.prenom
                       || (m ? m[1].replace(/[!.]/g, '') : '');
        const nameEl = overlay.querySelector('[data-field="b2-first-name"]');
        if (nameEl) nameEl.textContent = firstName ? firstName + ' !' : '!';

        // Compteurs : total = somme des cards aujourd'hui (.pose-line +
        // .sm2-done-row), durée = depuis l'ouverture de l'app (approximation).
        // 2026-11-12 : le compteur du jour du serveur quand la photo le
        // renvoie (opts.jour, TechCarte::journee) ; sinon l'ancien calcul.
        const total = opts.jour?.faites ?? (document.querySelectorAll('.sm2-done-row').length
                    + 1); // +1 pour la pose qu'on vient juste de finir
        const totalEl = overlay.querySelector('[data-field="b2-total"]');
        if (totalEl) totalEl.textContent = String(total);

        // Pour la durée : on n'a pas de timestamp serveur initial sans
        // appel supplémentaire. On affiche "Aujourd'hui" en placeholder
        // honnête. Si on veut vraiment, on poll un endpoint dédié plus
        // tard (out of scope SM2c minimal).
        const durEl = overlay.querySelector('[data-field="b2-duration"]');
        if (durEl) durEl.textContent = 'Aujourd\'hui';
        const rateEl = overlay.querySelector('[data-field="b2-rate"]');
        if (rateEl) rateEl.textContent = '—';

        // Fade out la card courante en parallèle
        if (opts.sourcePose) {
            opts.sourcePose.style.transition = 'opacity .4s';
            opts.sourcePose.style.opacity = '0';
        }

        // Affiche l'overlay plein écran
        overlay.hidden = false;
        overlay.removeAttribute('aria-hidden');
        requestAnimationFrame(() => overlay.classList.add('is-open'));
        document.body.style.overflow = 'hidden';

        // Vibration succès
        if (navigator.vibrate) { try { navigator.vibrate([60, 80, 60, 80, 200]); } catch (e) {} }

        // Bind boutons
        const onClick = (e) => {
            if (e.target.closest('[data-action="b2-home"]')) {
                e.preventDefault();
                overlay.classList.remove('is-open');
                setTimeout(() => { overlay.hidden = true; location.reload(); }, 300);
                overlay.removeEventListener('click', onClick);
                return;
            }
            if (e.target.closest('[data-action="b2-request"]')) {
                e.preventDefault();
                alert('Demande envoyée à ton chef.');
                overlay.classList.remove('is-open');
                setTimeout(() => { overlay.hidden = true; location.reload(); }, 300);
                overlay.removeEventListener('click', onClick);
                return;
            }
        };
        overlay.addEventListener('click', onClick);
    };
}

// ═════════════════════════════════════════════════════════════════════
// B3 — Centre de notifications
// ═════════════════════════════════════════════════════════════════════
const B3_STATE = { filter: 'all', notifs: [] };

function fetchNotifs() {
    const url = window.TECH_CONFIG?.routes?.notifications;
    if (!url) return Promise.resolve([]);
    return fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
    }).then(r => r.ok ? r.json() : { items: [] }).then(d => d.items || []).catch(() => []);
}

function renderNotifs() {
    const list  = document.querySelector('[data-field="b3-list"]');
    const empty = document.querySelector('[data-field="b3-empty"]');
    if (!list || !empty) return;
    while (list.querySelector('li.sm2c-b3-row')) list.querySelector('li.sm2c-b3-row').remove();

    const filtered = B3_STATE.notifs.filter(n => {
        if (B3_STATE.filter === 'all') return true;
        if (B3_STATE.filter === 'rejects')  return n.type === 'photo_rejected';
        if (B3_STATE.filter === 'newposes') return n.type === 'new_pose';
        return true;
    });

    if (!filtered.length) {
        empty.hidden = false;
        list.hidden = true;
        return;
    }
    empty.hidden = true;
    list.hidden = false;

    const ICONS = {
        photo_rejected:  { emoji: '🚫', cls: 'reject'    },
        new_pose:        { emoji: '🆕', cls: 'newpose'   },
        photo_validated: { emoji: '✓',  cls: 'validated' },
    };

    filtered.forEach(n => {
        const li = document.createElement('li');
        li.className = 'sm2c-b3-row' + (n.read_at ? '' : ' is-unread');
        li.dataset.notifId = n.id;
        // Refonte 2026-11-12 : une alerte mène à sa pose (payload.task_id,
        // posé par PigeController et PoseTask à la création).
        li.dataset.notifType = n.type || '';
        li.dataset.taskId = n.payload?.task_id || '';
        li.tabIndex = 0;
        li.setAttribute('role', 'button');
        const ico = ICONS[n.type] || { emoji: '🔔', cls: '' };
        li.innerHTML = `
            <span class="sm2c-b3-row-icon sm2c-b3-row-icon--${ico.cls}">${ico.emoji}</span>
            <div class="sm2c-b3-row-body">
                <div class="sm2c-b3-row-title"></div>
                <div class="sm2c-b3-row-detail"></div>
                <div class="sm2c-b3-row-time"></div>
            </div>
            <span class="sm2c-b3-row-dot"></span>`;
        li.querySelector('.sm2c-b3-row-title').textContent = n.title || '—';
        li.querySelector('.sm2c-b3-row-detail').textContent = n.detail || '';
        li.querySelector('.sm2c-b3-row-time').textContent = formatRelTime(n.created_at);
        list.appendChild(li);
    });
}

function formatRelTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    const s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (s < 60)    return 'à l\'instant';
    if (s < 3600)  return `il y a ${Math.floor(s / 60)} min`;
    if (s < 86400) return `il y a ${Math.floor(s / 3600)} h`;
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
}

function refreshHelpBadge() {
    const unread = B3_STATE.notifs.filter(n => !n.read_at).length;
    // Refonte 2026-11-12 : point rouge de la cloche + pastille « Alertes »
    // de la barre du bas.
    document.querySelectorAll('[data-notifs-dot]').forEach(el => { el.hidden = unread === 0; });
    document.querySelectorAll('[data-notifs-count]').forEach(el => {
        el.hidden = unread === 0;
        el.textContent = unread > 9 ? '9+' : String(unread);
    });
    const btn = document.querySelector('.sm2-help-btn');
    if (!btn) return;
    btn.classList.toggle('has-notifs', unread > 0);
}

function openB3() {
    const overlay = document.getElementById('sm2c-b3-overlay');
    if (!overlay) return;
    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';
    renderNotifs();
}

function closeB3() {
    const overlay = document.getElementById('sm2c-b3-overlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    setTimeout(() => { overlay.hidden = true; }, 220);
    document.body.style.overflow = '';
}

function markAllRead() {
    const url = window.TECH_CONFIG?.routes?.notificationsMarkRead;
    if (!url) return;
    fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': window.TECH_CONFIG?.csrfToken,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ all: true }),
    }).then(() => {
        B3_STATE.notifs.forEach(n => { n.read_at = new Date().toISOString(); });
        renderNotifs();
        refreshHelpBadge();
    });
}

// Une alerte touchée : lue (serveur + pastilles), tiroir fermé, et la page
// mène à sa pose (événement « tx:alerte », features/refonte.js).
function ouvrirAlerte(row) {
    const id = row.dataset.notifId;
    const notif = B3_STATE.notifs.find(n => String(n.id) === String(id));
    if (notif && !notif.read_at) {
        notif.read_at = new Date().toISOString();
        refreshHelpBadge();
        const url = window.TECH_CONFIG?.routes?.notificationsMarkRead;
        if (url) {
            fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': window.TECH_CONFIG?.csrfToken,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: Number(id) }),
            }).catch(() => {});
        }
    }
    closeB3();
    document.dispatchEvent(new CustomEvent('tx:alerte', {
        detail: { type: row.dataset.notifType, taskId: row.dataset.taskId || null },
    }));
}

function initNotifications() {
    // Intercepte le tap sur le bouton aide "?" :
    //   - Si notifs non lues → ouvre B3 au lieu de T8
    //   - Sinon → laisse passer pour T8 (help.js)
    document.addEventListener('click', (e) => {
        // Refonte 2026-11-12 : la cloche et l'onglet « Alertes » ouvrent B3.
        if (e.target.closest('[data-action="open-b3"]')) {
            e.preventDefault(); openB3(); return;
        }
        // Le « ? » jaune de l'ancien topbar servait aussi d'entrée aux
        // alertes. Le « ? » de la refonte ouvre toujours l'aide (la cloche
        // a les alertes) : interception limitée à l'ancien bouton.
        const helpBtn = e.target.closest('.sm2-help-btn[data-action="open-help"]');
        if (helpBtn) {
            const hasUnread = B3_STATE.notifs.some(n => !n.read_at);
            if (hasUnread) {
                e.preventDefault();
                e.stopImmediatePropagation();
                openB3();
            }
            return;
        }
        if (e.target.closest('[data-action="close-b3"]')) {
            e.preventDefault(); closeB3(); return;
        }
        const filterBtn = e.target.closest('[data-b3-filter]');
        if (filterBtn) {
            B3_STATE.filter = filterBtn.dataset.b3Filter;
            document.querySelectorAll('.sm2c-b3-filter').forEach(b => {
                b.classList.toggle('is-active', b === filterBtn);
            });
            renderNotifs();
            return;
        }
        if (e.target.closest('[data-action="b3-mark-all"]')) {
            markAllRead(); return;
        }
        const row = e.target.closest('.sm2c-b3-row[data-notif-id]');
        if (row) { e.preventDefault(); ouvrirAlerte(row); return; }
        // Click sur overlay hors-drawer → close
        const overlay = e.target.closest('#sm2c-b3-overlay');
        if (overlay && !e.target.closest('.sm2c-b3-drawer')) closeB3();
    }, true);

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.querySelector('#sm2c-b3-overlay.is-open')) closeB3();
        // Alerte au clavier (Entrée / Espace), comme un bouton.
        const row = (e.key === 'Enter' || e.key === ' ') && e.target.closest?.('.sm2c-b3-row[data-notif-id]');
        if (row) { e.preventDefault(); ouvrirAlerte(row); }
    });

    // 1er fetch au load, puis refresh toutes les 60s. Le tiroir a pu être
    // ouvert avant la réponse (« Alertes » depuis une autre page, ?alertes=1) :
    // on le remplit dès qu'elle arrive.
    fetchNotifs().then(items => {
        B3_STATE.notifs = items;
        refreshHelpBadge();
        if (document.querySelector('#sm2c-b3-overlay.is-open')) renderNotifs();
    });
    setInterval(() => {
        fetchNotifs().then(items => {
            B3_STATE.notifs = items;
            refreshHelpBadge();
            if (document.querySelector('#sm2c-b3-overlay.is-open')) renderNotifs();
        });
    }, 60000);
}

// ═════════════════════════════════════════════════════════════════════
// Phase 4 — Préférences tech (localStorage only)
// ═════════════════════════════════════════════════════════════════════
const PREF_KEYS = {
    largeText: 'sm2c_pref_large_text',
    lowPower:  'sm2c_pref_low_power',
    noT7:      'sm2c_pref_no_t7_confirm',
};

function getPref(key) {
    try { return localStorage.getItem(key) === 'true'; }
    catch (e) { return false; }
}

function setPref(key, val) {
    try { localStorage.setItem(key, val ? 'true' : 'false'); }
    catch (e) {}
}

function applyStoredPrefs() {
    document.body.classList.toggle('sm2c-large-text', getPref(PREF_KEYS.largeText));
    document.body.classList.toggle('sm2c-low-power',  getPref(PREF_KEYS.lowPower));
}

function openPrefs() {
    const overlay = document.getElementById('sm2c-prefs-overlay');
    if (!overlay) return;
    // Refresh toggles selon localStorage
    overlay.querySelectorAll('[data-pref-key]').forEach(t => {
        t.classList.toggle('is-on', getPref(t.dataset.prefKey));
    });
    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';
}

function closePrefs() {
    const overlay = document.getElementById('sm2c-prefs-overlay');
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    setTimeout(() => { overlay.hidden = true; }, 220);
    document.body.style.overflow = '';
}

function initPreferences() {
    applyStoredPrefs();

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="open-prefs"]')) { e.preventDefault(); openPrefs(); return; }
        if (e.target.closest('[data-action="close-prefs"]')) { e.preventDefault(); closePrefs(); return; }
        const toggle = e.target.closest('[data-pref-key]');
        if (toggle) {
            const key = toggle.dataset.prefKey;
            const now = !getPref(key);
            setPref(key, now);
            toggle.classList.toggle('is-on', now);
            applyStoredPrefs();
            return;
        }
        const overlay = e.target.closest('#sm2c-prefs-overlay');
        if (overlay && !e.target.closest('.sm2c-prefs-drawer')) closePrefs();
    });

    // Tap long sur le header T1 (1 seconde) ouvre les préférences
    const header = document.querySelector('.header');
    if (header) {
        let touchTimer = null;
        const start = () => { clearTimeout(touchTimer); touchTimer = setTimeout(openPrefs, 1000); };
        const cancel = () => { clearTimeout(touchTimer); };
        header.addEventListener('touchstart', start, { passive: true });
        header.addEventListener('touchend', cancel);
        header.addEventListener('touchmove', cancel);
        header.addEventListener('touchcancel', cancel);
    }
}

// ═════════════════════════════════════════════════════════════════════
// Init principal
// ═════════════════════════════════════════════════════════════════════
export function init() {
    initOffSchedule();
    registerEndOfDay();
    initNotifications();
    initPreferences();
}

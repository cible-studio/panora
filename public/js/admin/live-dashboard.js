// public/js/admin/live-dashboard.js — SM2b Phase 2.
//
// Pilote le dashboard admin live (A1) :
//   - Polling 20s sur admin.dashboard.live (config via window.ADMIN_DASHBOARD_CONFIG)
//   - Pause si onglet caché (visibilitychange) — économie batterie + BDD
//   - Met à jour KPIs, liste techs, badge "as_of"
//   - Affiche le bandeau "événement live" sur les nouveaux events (dedup
//     par id stable) + auto-hide 30s
//   - Le badge "il y a Xs" est re-rendu à chaque seconde (mais le payload
//     n'est rafraîchi qu'à chaque poll)

const CFG = window.ADMIN_DASHBOARD_CONFIG || {};
const POLL_MS = CFG.pollMs || 20000;
const BANNER_HIDE_MS = 30000;

let pollTimer = null;
let secondsTimer = null;
let lastFetchAt = null;
let seenEventIds = new Set();
let bannerHideTimer = null;

// SM2b Phase 7 — Notifications visuelles côté admin.
// Compte les events "importants" arrivés pendant que l'onglet est caché.
// On préfixe document.title avec "(N) " + petit dot rouge favicon généré
// via Canvas data URL.
const ORIGINAL_TITLE = document.title;
const ORIGINAL_FAVICON = (() => {
    const link = document.querySelector('link[rel="icon"]');
    return link ? link.getAttribute('href') : null;
})();
let unseenCount = 0;
const IMPORTANT_EVENT_TYPES = new Set(['problem_reported', 'photo_rejected', 'photo_sent']);

function $(sel, root = document) { return root.querySelector(sel); }
function $$(sel, root = document) { return Array.from(root.querySelectorAll(sel)); }

function formatRelativeSeconds(date) {
    if (!date) return '—';
    const s = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (s < 5)  return 'à l\'instant';
    if (s < 60) return `il y a ${s} s`;
    if (s < 3600) return `il y a ${Math.floor(s / 60)} min`;
    return `il y a ${Math.floor(s / 3600)} h`;
}

function updateAsOfBadge() {
    const el = $('[data-field="as-of"]');
    if (!el) return;
    if (!lastFetchAt) {
        el.textContent = 'Connexion…';
        return;
    }
    el.textContent = `Mise à jour ${formatRelativeSeconds(lastFetchAt)}`;
}

function updateKpis(kpis) {
    if (!kpis) return;
    Object.entries(kpis).forEach(([key, val]) => {
        const el = document.querySelector(`[data-kpi="${key}"]`);
        if (!el) return;
        const prev = el.textContent;
        const next = String(val);
        if (prev !== next) {
            el.textContent = next;
            el.classList.remove('kpi-bump');
            void el.offsetWidth; // reflow
            el.classList.add('kpi-bump');
        }
    });
}

function updateTechsList(techs) {
    const tpl     = $('[data-field="tech-row-tpl"]');
    const listEl  = $('[data-field="techs-list"]');
    const emptyEl = $('[data-field="techs-empty"]');
    const countEl = $('[data-field="techs-count"]');
    const mapLink = $('.live-techs-map-link');
    if (!tpl || !listEl || !emptyEl) return;

    countEl && (countEl.textContent = `(${techs.length})`);

    if (!techs.length) {
        listEl.hidden = true;
        emptyEl.hidden = false;
        if (mapLink) mapLink.hidden = true;
        return;
    }
    listEl.hidden = false;
    emptyEl.hidden = true;
    if (mapLink) mapLink.hidden = false;

    // Diff simple : on remplace toute la liste (max 50 rows, peu coûteux)
    while (listEl.querySelector('li.live-tech-row')) {
        listEl.querySelector('li.live-tech-row').remove();
    }

    const statusLabels = {
        en_route:  '🚗 En route',
        sur_place: '📍 Sur place',
        inactif:   '⏸ En pause',
        autre:     '— Autre',
    };

    techs.forEach(tech => {
        const node = tpl.content.cloneNode(true);
        const row = node.querySelector('li');
        row.querySelector('[data-field="tech-initials"]').textContent = tech.initials || '?';
        row.querySelector('[data-field="tech-name"]').textContent     = tech.full_name || '—';
        const status = statusLabels[tech.current_status] || tech.current_status || '—';
        const loc    = tech.current_location_label ? ` · ${tech.current_location_label}` : '';
        const pose   = tech.current_pose_label ? ` · ${tech.current_pose_label}` : '';
        row.querySelector('[data-field="tech-status"]').textContent = `${status}${loc}${pose}`;

        const done  = tech.progress?.done  ?? 0;
        const total = tech.progress?.total ?? 0;
        row.querySelector('[data-field="tech-progress"]').textContent = `${done}/${total}`;
        const pct = total > 0 ? Math.round((done / total) * 100) : 0;
        row.querySelector('[data-field="tech-progress-fill"]').style.width = pct + '%';

        const seenAt = tech.last_seen_at ? new Date(tech.last_seen_at) : null;
        row.querySelector('[data-field="tech-seen"]').textContent = formatRelativeSeconds(seenAt);

        const openLink = row.querySelector('[data-field="tech-open"]');
        if (CFG.techDetailUrlTpl) {
            openLink.setAttribute('href', CFG.techDetailUrlTpl.replace('__USER__', tech.id));
        }

        listEl.appendChild(row);
    });
}

function showBanner(event) {
    const banner = $('[data-event-banner]');
    if (!banner) return;
    banner.querySelector('[data-field="event-label"]').textContent = event.label || event.type || 'Événement';
    const detail = [event.tech_full_name, event.location_label].filter(Boolean).join(' · ');
    banner.querySelector('[data-field="event-detail"]').textContent = detail || '';
    const cta = banner.querySelector('[data-field="event-cta"]');
    // SM2b Phase 5 — Si event.actionable_data porte un data-action,
    // on le pose sur le CTA pour que pige-validate.js puisse l'ouvrir.
    cta.removeAttribute('data-action');
    cta.removeAttribute('data-pige-id');
    if (event.actionable_data) {
        Object.entries(event.actionable_data).forEach(([k, v]) => {
            cta.setAttribute('data-' + k, v);
        });
    }
    if (event.actionable_url) {
        cta.setAttribute('href', event.actionable_url);
        cta.hidden = false;
    } else {
        cta.hidden = true;
    }
    banner.classList.add(`live-event-banner--${event.type}`);
    banner.hidden = false;
    requestAnimationFrame(() => banner.classList.add('is-open'));
    clearTimeout(bannerHideTimer);
    bannerHideTimer = setTimeout(hideBanner, BANNER_HIDE_MS);
}

function hideBanner() {
    const banner = $('[data-event-banner]');
    if (!banner) return;
    banner.classList.remove('is-open');
    setTimeout(() => {
        banner.hidden = true;
        // Nettoie les modificateurs de type pour le prochain événement
        banner.className = 'live-event-banner';
    }, 200);
}

function eventId(e) {
    // ID stable : type + horodatage + (pige_id|task_id|tech_initials)
    return `${e.type}-${e.at}-${e.pige_id || e.task_id || e.tech_initials || ''}`;
}

function handleEvents(events) {
    if (!Array.isArray(events) || !events.length) return;
    // 1er load : on remplit seenEventIds sans pop de banner (sinon flood).
    if (seenEventIds.size === 0 && lastFetchAt === null) {
        events.forEach(e => seenEventIds.add(eventId(e)));
        return;
    }
    const fresh = events.filter(e => !seenEventIds.has(eventId(e)));
    if (fresh.length) {
        // On ne montre qu'un seul banner à la fois (le plus récent).
        showBanner(fresh[0]);
        fresh.forEach(e => seenEventIds.add(eventId(e)));

        // SM2b Phase 7 — Si l'onglet est caché, on bumpe le compteur
        // de notifications "non lues" pour signaler dans le titre / favicon.
        if (document.hidden) {
            const importantCount = fresh.filter(e => IMPORTANT_EVENT_TYPES.has(e.type)).length;
            if (importantCount > 0) {
                unseenCount += importantCount;
                updateTabBadge();
            }
        }
    }
    // Cap mémoire : garde ~500 derniers ids
    if (seenEventIds.size > 500) {
        seenEventIds = new Set([...seenEventIds].slice(-500));
    }
}

function updateTabBadge() {
    if (unseenCount <= 0) {
        document.title = ORIGINAL_TITLE;
        setFavicon(ORIGINAL_FAVICON);
        return;
    }
    document.title = `(${unseenCount}) ` + ORIGINAL_TITLE.replace(/^\(\d+\)\s*/, '');
    setFavicon(buildBadgedFaviconDataUrl(unseenCount));
}

function setFavicon(href) {
    if (!href) return;
    let link = document.querySelector('link[rel="icon"]');
    if (!link) {
        link = document.createElement('link');
        link.rel = 'icon';
        document.head.appendChild(link);
    }
    link.setAttribute('href', href);
}

function buildBadgedFaviconDataUrl(n) {
    // Génère un favicon 64×64 avec un cercle rouge + chiffre blanc.
    // Pas de lib externe.
    const c = document.createElement('canvas');
    c.width = 64; c.height = 64;
    const ctx = c.getContext('2d');
    // Fond circulaire orange Panora
    ctx.fillStyle = '#fff';
    ctx.beginPath(); ctx.arc(32, 32, 30, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#ea580c';
    ctx.beginPath(); ctx.arc(32, 32, 26, 0, Math.PI * 2); ctx.fill();
    // Badge rouge en haut-droite
    ctx.fillStyle = '#b91c1c';
    ctx.beginPath(); ctx.arc(50, 14, 14, 0, Math.PI * 2); ctx.fill();
    // Texte du badge (clamp à 9+)
    const label = n > 9 ? '9+' : String(n);
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 16px Arial';
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(label, 50, 15);
    return c.toDataURL('image/png');
}

async function tick() {
    if (!CFG.endpoint) return;
    try {
        const res = await fetch(CFG.endpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        const wasFirstLoad = lastFetchAt === null;
        lastFetchAt = new Date();
        updateKpis(payload.kpis);
        updateTechsList(payload.techs_active || []);
        handleEvents(payload.live_events || []);
        updateAsOfBadge();
        if (wasFirstLoad) {
            // Force un repaint immédiat du badge "as_of" pour ne pas
            // laisser "Connexion…" à l'écran.
            requestAnimationFrame(updateAsOfBadge);
        }
    } catch (e) {
        console.warn('[live-dashboard] tick failed', e);
    }
}

function startPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(tick, POLL_MS);
    if (secondsTimer) clearInterval(secondsTimer);
    secondsTimer = setInterval(updateAsOfBadge, 1000);
}

function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    if (secondsTimer) { clearInterval(secondsTimer); secondsTimer = null; }
}

function init() {
    if (!$('[data-live-root]')) return;

    // 1er fetch immédiat + polling
    tick();
    startPolling();

    // Pause si onglet caché — économise BDD + batterie + reset badge
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            // SM2b Phase 7 — Retour sur l'onglet → on a "vu" les events,
            // reset le compteur + restore titre + favicon original.
            unseenCount = 0;
            updateTabBadge();
            tick();
            startPolling();
        }
    });

    // Fermeture manuelle du bandeau
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="dismiss-event"]')) {
            hideBanner();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

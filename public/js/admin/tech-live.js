// public/js/admin/tech-live.js — SM2b Phase 3.
//
// Fiche tech A2 : KPIs personnels + card "EN CE MOMENT" + timeline.
// Poll 2 endpoints à 20s :
//   - admin.dashboard.live → on extrait le tech ciblé pour KPIs + current_pose
//   - admin.tech.timeline  → frise chronologique du jour
// Pause si onglet caché.

const CFG = window.ADMIN_TECH_LIVE_CONFIG || {};
const POLL_MS = CFG.pollMs || 20000;

let pollTimer = null;

function $(sel, root = document) { return root.querySelector(sel); }

function relSeconds(d) {
    if (!d) return '—';
    const s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
    if (s < 5) return 'à l\'instant';
    if (s < 60) return `il y a ${s}s`;
    if (s < 3600) return `il y a ${Math.floor(s / 60)} min`;
    return `il y a ${Math.floor(s / 3600)} h`;
}

const EVENT_META = {
    tech_arrived:     { icon: '📍', color: '#ea580c' },
    pose_completed:   { icon: '✅', color: '#16a34a' },
    photo_sent:       { icon: '📷', color: '#1e40af' },
    photo_validated:  { icon: '✓',  color: '#16a34a' },
    photo_rejected:   { icon: '✗',  color: '#b91c1c' },
    problem_reported: { icon: '⚠',  color: '#b91c1c' },
};

async function tickKpis() {
    if (!CFG.dashboardEndpoint || !CFG.techId) return;
    try {
        const res = await fetch(CFG.dashboardEndpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const payload = await res.json();
        const me = (payload.techs_active || []).find(t => t.id === CFG.techId);
        const statusEl = $('[data-field="tech-status"]');
        if (!me) {
            statusEl && (statusEl.textContent = 'Hors ligne');
            ['done','in_progress','remaining','problems'].forEach(k => {
                const el = document.querySelector(`[data-kpi="${k}"]`);
                if (el) el.textContent = '—';
            });
            const card = $('[data-field="current-card"]');
            if (card) card.hidden = true;
            return;
        }

        statusEl && (statusEl.textContent = me.current_status === 'sur_place'
            ? 'Sur place'
            : me.current_status === 'en_route'
                ? 'En route'
                : me.current_status === 'inactif'
                    ? 'En pause'
                    : me.current_status || 'En activité');

        const done  = me.progress?.done ?? 0;
        const total = me.progress?.total ?? 0;
        const remaining = Math.max(0, total - done);
        const setK = (k, v) => {
            const el = document.querySelector(`[data-kpi="${k}"]`);
            if (el) {
                if (el.textContent !== String(v)) {
                    el.textContent = String(v);
                    el.classList.remove('kpi-bump');
                    void el.offsetWidth;
                    el.classList.add('kpi-bump');
                }
            }
        };
        setK('done', done);
        // "in_progress" sur la fiche = 1 si le tech a une pose active, 0 sinon
        setK('in_progress', me.current_status === 'sur_place' || me.current_status === 'en_route' ? 1 : 0);
        setK('remaining', remaining);

        // "Problems" — compté côté global, on ne distingue pas par tech ici.
        // À enrichir plus tard si la patronne demande.

        // Card EN CE MOMENT
        const card = $('[data-field="current-card"]');
        if (card) {
            if (me.current_pose_label) {
                $('[data-field="current-title"]').textContent = me.current_pose_label;
                $('[data-field="current-sub"]').textContent = [
                    me.current_location_label,
                    me.current_status === 'sur_place' ? 'Photo en cours' : 'En route',
                ].filter(Boolean).join(' · ');
                card.hidden = false;
            } else {
                card.hidden = true;
            }
        }
    } catch (e) { console.warn('[tech-live] kpis tick failed', e); }
}

async function tickTimeline() {
    if (!CFG.timelineEndpoint) return;
    try {
        const res = await fetch(CFG.timelineEndpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const payload = await res.json();
        renderTimeline(payload.events || []);
    } catch (e) { console.warn('[tech-live] timeline tick failed', e); }
}

function renderTimeline(events) {
    const list  = $('[data-field="timeline-list"]');
    const empty = $('[data-field="timeline-empty"]');
    const tpl   = $('[data-field="timeline-row-tpl"]');
    if (!list || !tpl) return;

    if (!events.length) {
        list.hidden = true;
        empty.hidden = false;
        return;
    }
    list.hidden = false;
    empty.hidden = true;

    while (list.querySelector('li.tech-live-event')) {
        list.querySelector('li.tech-live-event').remove();
    }

    // Tri DESC (plus récent en haut) — backend renvoie ASC, on inverse
    events.slice().reverse().forEach(ev => {
        const meta = EVENT_META[ev.type] || { icon: '•', color: '#9ca3af' };
        const node = tpl.content.cloneNode(true);
        const li   = node.querySelector('li');
        if (ev.is_current) li.classList.add('is-current');
        const dot = li.querySelector('[data-field="event-dot"]');
        dot.style.borderColor = meta.color;
        dot.style.background  = ev.is_current ? meta.color : '#fff';
        dot.textContent = meta.icon;

        li.querySelector('[data-field="event-label"]').textContent = ev.label || ev.type;
        li.querySelector('[data-field="event-time"]').textContent = formatTime(ev.at);
        li.querySelector('[data-field="event-subject"]').textContent = ev.subject || '—';

        const loc = li.querySelector('[data-field="event-location"]');
        if (ev.location) {
            loc.textContent = '📍 ' + ev.location;
            loc.hidden = false;
        }

        const extra = li.querySelector('[data-field="event-extra"]');
        if (ev.type === 'photo_rejected' && ev.meta?.reason) {
            extra.textContent = '💬 ' + ev.meta.reason;
            extra.hidden = false;
        }

        list.appendChild(node);
    });
}

function formatTime(isoStr) {
    if (!isoStr) return '—';
    const d = new Date(isoStr);
    return d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
}

function tick() { tickKpis(); tickTimeline(); }

function start() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(tick, POLL_MS);
}
function stop() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
}

function init() {
    if (!$('[data-tech-live]')) return;
    tick();
    start();
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) stop();
        else { tick(); start(); }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else { init(); }

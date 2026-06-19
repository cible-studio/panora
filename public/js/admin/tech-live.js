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
// Hotfix 2026-06-19 : filtre actif sur la timeline (null = tous les
// événements). Toggled depuis les KPI cards en haut de page.
//   - 'done'        → photo_sent(_off_schedule), pose_completed, photo_validated
//   - 'in_progress' → tech_arrived
//   - 'problems'    → problem_reported, photo_rejected
let currentFilter = null;
// Cache du dernier payload d'events pour réappliquer le filtre sans
// re-fetcher après un clic KPI.
let lastEvents = [];

const FILTER_TYPES = {
    done:        new Set(['photo_sent', 'photo_sent_off_schedule', 'pose_completed', 'photo_validated']),
    in_progress: new Set(['tech_arrived']),
    problems:    new Set(['problem_reported', 'photo_rejected']),
};

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
    tech_arrived:            { icon: '📍', color: '#ea580c' },
    pose_completed:          { icon: '✅', color: '#16a34a' },
    photo_sent:              { icon: '📷', color: '#1e40af' },
    photo_sent_off_schedule: { icon: '⏰', color: '#d97706' }, // SM2c B1 — hors créneau
    photo_validated:         { icon: '✓',  color: '#16a34a' },
    photo_rejected:          { icon: '✗',  color: '#b91c1c' },
    problem_reported:        { icon: '⚠',  color: '#b91c1c' },
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

        // Hotfix 2026-06-19 — statut + apparence du pulse vivant :
        //   - sur_place / en_route → "Sur place" / "En route" + pulse vert animé
        //   - inactif + remaining=0 + total>0 → "✅ Tournée terminée" + pulse vert fixe
        //   - inactif autre cas → "En pause" + pulse gris SANS animation (rien
        //     ne se passe en ce moment, anxiogène de faire clignoter en vert)
        const doneNow      = me.progress?.done       ?? 0;
        const totalNow     = me.progress?.total      ?? 0;
        const remainingNow = me.progress?.remaining  ?? Math.max(0, totalNow - doneNow);
        const isDone = me.current_status === 'inactif' && remainingNow === 0 && totalNow > 0;
        let statusText;
        if (me.current_status === 'sur_place')      statusText = 'Sur place';
        else if (me.current_status === 'en_route')  statusText = 'En route';
        else if (me.current_status === 'inactif')   statusText = isDone ? '✅ Tournée terminée' : 'En pause';
        else                                        statusText = me.current_status || 'En activité';
        statusEl && (statusEl.textContent = statusText);
        const subStatus = $('.tech-live-substatus');
        if (subStatus) {
            subStatus.classList.toggle('is-idle', me.current_status === 'inactif' && !isDone);
            subStatus.classList.toggle('is-done', isDone);
        }

        // Hotfix 2026-06-19 :
        //   - "FAITES AUJOURD'HUI" lit progress.done_today (nouvel attribut)
        //     au lieu de progress.done qui est désormais le total cumulé du
        //     tech (cohérent avec l'écran tech "5/7").
        //   - "RESTANT" lit progress.remaining (= poses actives non livrées)
        //     directement depuis le serveur au lieu d'un calcul fragile.
        //   Fallback : si le serveur ne renvoie pas done_today/remaining
        //   (vieux JSON cached), on retombe sur l'ancien calcul.
        const done       = me.progress?.done       ?? 0;
        const total      = me.progress?.total      ?? 0;
        const doneToday  = me.progress?.done_today ?? done;
        const remaining  = me.progress?.remaining  ?? Math.max(0, total - done);
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
        // Hotfix 2026-06-19 :
        // 'done' et 'in_progress' sont désormais RECALCULÉS depuis la
        // timeline elle-même par recomputeKpisFromTimeline() pour que
        // les compteurs collent EXACTEMENT à ce que le filtre affichera.
        // On garde une valeur de fallback ici tant que la timeline n'est
        // pas encore arrivée (premier tick).
        if (!lastEvents.length) {
            setK('done', doneToday);
            setK('in_progress', me.current_status === 'sur_place' || me.current_status === 'en_route' ? 1 : 0);
        }
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
    lastEvents = events || [];
    recomputeKpisFromTimeline();
    drawTimeline();
}

// Recompte 'done' / 'in_progress' / 'problems' depuis la timeline pour
// garantir cohérence avec ce que le filtre KPI affichera. Sans ça, le
// compteur "0 en cours" + une liste filtrée de 4 arrivées est confus.
function recomputeKpisFromTimeline() {
    const setK = (k, v) => {
        const el = document.querySelector(`[data-kpi="${k}"]`);
        if (!el) return;
        if (el.textContent !== String(v)) {
            el.textContent = String(v);
            el.classList.remove('kpi-bump');
            void el.offsetWidth;
            el.classList.add('kpi-bump');
        }
    };
    const doneCnt    = lastEvents.filter(e => FILTER_TYPES.done.has(e.type)).length;
    const arrivalCnt = lastEvents.filter(e => FILTER_TYPES.in_progress.has(e.type)).length;
    const probCnt    = lastEvents.filter(e => FILTER_TYPES.problems.has(e.type)).length;
    setK('done', doneCnt);
    setK('in_progress', arrivalCnt);
    setK('problems', probCnt > 0 ? probCnt : '—');
}

// Sépare le filtrage + rendu pour qu'un clic KPI puisse redessiner
// sans re-fetcher l'API.
function drawTimeline() {
    const list        = $('[data-field="timeline-list"]');
    const empty       = $('[data-field="timeline-empty"]');
    const emptyFilter = $('[data-field="timeline-empty-filter"]');
    const tpl         = $('[data-field="timeline-row-tpl"]');
    if (!list || !tpl) return;

    // Empty state global (aucun event du tout)
    if (!lastEvents.length) {
        list.hidden        = true;
        if (empty)       empty.hidden       = false;
        if (emptyFilter) emptyFilter.hidden = true;
        return;
    }

    // Applique le filtre KPI éventuel
    const filtered = currentFilter
        ? lastEvents.filter(e => FILTER_TYPES[currentFilter]?.has(e.type))
        : lastEvents;

    // Empty state filtré (events présents mais aucun ne matche)
    if (!filtered.length) {
        list.hidden        = true;
        if (empty)       empty.hidden       = true;
        if (emptyFilter) emptyFilter.hidden = false;
        return;
    }

    list.hidden        = false;
    if (empty)       empty.hidden       = true;
    if (emptyFilter) emptyFilter.hidden = true;

    while (list.querySelector('li.tech-live-event')) {
        list.querySelector('li.tech-live-event').remove();
    }

    // Tri DESC (plus récent en haut) — backend renvoie ASC, on inverse
    filtered.slice().reverse().forEach(ev => {
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
        // Hotfix 2026-06-19 — explicite ce qu'est "hors créneau" pour
        // que la patronne sache ce que ça veut dire au survol.
        if (ev.type === 'photo_sent_off_schedule') {
            const labelEl = li.querySelector('[data-field="event-label"]');
            if (labelEl) {
                labelEl.title = "Cette photo a été envoyée plus de 2 heures avant ou après l'horaire planifié de la pose. Le tech a confirmé démarrer hors créneau.";
            }
            extra.textContent = '⏰ Pose démarrée hors du créneau prévu (le tech a confirmé)';
            extra.hidden = false;
        }

        // Lien cliquable vers la pige / pose / signalement selon le type.
        // Hotfix 2026-06-19 — la patronne veut naviguer direct depuis la
        // frise vers la fiche détail concernée.
        const anchor = li.querySelector('[data-field="event-link"]');
        if (anchor) {
            if (ev.link_url) {
                anchor.setAttribute('href', ev.link_url);
                anchor.classList.add('is-clickable');
            } else {
                anchor.removeAttribute('href');
                anchor.classList.remove('is-clickable');
            }
        }

        list.appendChild(node);
    });
}

// ── Filtres KPI cards ─────────────────────────────────────────────
// Clic sur un KPI = toggle du filtre correspondant. Si filtre déjà
// actif, on le retire. Sinon on l'active (les autres se désactivent).
function bindKpiFilters() {
    document.querySelectorAll('[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            const want = btn.dataset.kpiFilter;
            currentFilter = (currentFilter === want) ? null : want;
            // Sync aria-pressed sur toutes les cartes
            document.querySelectorAll('[data-kpi-filter]').forEach(b => {
                const active = b.dataset.kpiFilter === currentFilter;
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
                b.classList.toggle('is-active', active);
            });
            drawTimeline();
        });
    });
    document.querySelector('[data-action="clear-filter"]')?.addEventListener('click', () => {
        currentFilter = null;
        document.querySelectorAll('[data-kpi-filter]').forEach(b => {
            b.setAttribute('aria-pressed', 'false');
            b.classList.remove('is-active');
        });
        drawTimeline();
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
    bindKpiFilters();
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

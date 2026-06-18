// public/js/tech/features/heartbeat.js — Phase 3 SM1.
//
// Polling 20s sur /tech/{token}/heartbeat. Met à jour les 4 KPIs cards
// (totalActive / activeToday / pigesSentToday / zonesTodayCount), le
// sub-label "X faites" de la card Aujourd'hui, le badge "Mes piges",
// et détecte une nouvelle pose assignée (latestTaskId > lastKnownTaskId)
// pour afficher le bandeau "🆕 On t'a donné un nouveau panneau".
//
// Polling actif uniquement quand le document est visible (visibilitychange).
//
// Source : bloc 4 du <script> inline (lignes 294-363 de tech-space.blade.php
// avant Phase 3).

import { getJson } from '../core/api.js';
import { state }   from '../core/state.js';

const POLL_MS = 20000;
let liveDot = null;
let heartbeatUrl = null;

function bumpKpi(name, newVal) {
    const el = document.querySelector(`[data-kpi-value="${name}"]`);
    if (!el) return;
    const oldVal = parseInt(el.textContent.trim(), 10) || 0;
    if (oldVal === newVal) return;
    el.textContent = newVal;
    el.classList.remove('kpi-bump');
    void el.offsetWidth; // force reflow pour relancer l'anim
    el.classList.add('kpi-bump');
}

async function heartbeatTick() {
    if (!heartbeatUrl) return;
    try {
        const r = await getJson(heartbeatUrl);
        if (!r.ok) return;
        const d = await r.json();
        if (!d.ok) return;

        // Pulse "live"
        if (liveDot) {
            liveDot.classList.add('is-pulsing');
            setTimeout(() => liveDot.classList.remove('is-pulsing'), 600);
        }

        bumpKpi('totalActive',     d.totalActive);
        bumpKpi('activeToday',     d.activeToday);
        bumpKpi('pigesSentToday',  d.pigesSentToday);
        bumpKpi('zonesTodayCount', d.zonesTodayCount);

        // Le sub-label "Aujourd'hui" rappelle aussi le nb posées du jour
        const doneTodayEl = document.querySelector('[data-done-today]');
        if (doneTodayEl) doneTodayEl.textContent = d.doneToday;

        // MAJ chip "Mes piges" badge (rejected si > 0, sinon total)
        const chipBadge = document.querySelector('[data-piges-chip-badge]');
        if (chipBadge) {
            const v = d.pigesRejected > 0 ? d.pigesRejected : d.pigesTotal;
            if (parseInt(chipBadge.textContent.trim(), 10) !== v) {
                chipBadge.textContent = v;
            }
        }

        // Détection nouvelle pose assignée
        if (!state.firstTick && d.latestTaskId > state.lastKnownTaskId) {
            const banner = document.querySelector('[data-new-task-banner]');
            if (banner) banner.style.display = 'block';
        }
        state.lastKnownTaskId = Math.max(state.lastKnownTaskId, d.latestTaskId || 0);
        state.firstTick = false;
    } catch (e) { /* silencieux */ }
}

export function init() {
    heartbeatUrl = window.TECH_CONFIG?.routes?.heartbeat;
    if (!heartbeatUrl) return;
    liveDot = document.querySelector('[data-live-indicator]');

    // Baseline lastKnownTaskId à partir des cards SSR (préserve comportement
    // pré-refonte : la 1re détection nouvelle pose se fait au 2e tick).
    state.lastKnownTaskId = Array.from(document.querySelectorAll('.pose[data-task-id]'))
        .reduce((max, el) => Math.max(max, parseInt(el.dataset.taskId, 10) || 0), 0);

    setTimeout(heartbeatTick, 1500);
    setInterval(heartbeatTick, window.TECH_CONFIG?.bootstrap?.heartbeatInterval || POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) heartbeatTick();
    });
}

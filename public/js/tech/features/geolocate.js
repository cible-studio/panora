// public/js/tech/features/geolocate.js — SM1.5 Lot 4.
//
// 2 features GPS distinctes mais couplées au même état :
//   1. "Près de moi" : tri par distance haversine (calcul JS local sur
//      lat/lng déjà en data-attr des cards). Reversible (toggle).
//   2. "Mon chemin" : TSP nearest-neighbor côté serveur. POST l'ID des
//      cards rendues à /poses/optimize → ordre optimal + leg distances.
//      Réordonne les cards en mode "tournée" avec badge numéro.
//      Reversible (exitTourMode restaure le DOM original).
//
// Source : blocs 11 et 16 du <script> inline pré-SM1.5 (lignes 778-879
// et 970-1104). Migration 1:1 comportement-identique.
//
// Depuis Lot 5 : import direct de state.filterState + applyFilters /
// writeFiltersToUrl exportés par filters.js, plus de ponts __sm15.
//
// Dépendances :
//   - core/state.js : filterState (distance, geo)
//   - core/ui-helpers.js : toastSmall
//   - features/filters.js : applyFilters, writeFiltersToUrl
//   - window.TECH_CONFIG.routes.optimize

import { state } from '../core/state.js';
import { toastSmall } from '../core/ui-helpers.js';
import { applyFilters, writeFiltersToUrl } from './filters.js';

function getGeoPosition() {
    return new Promise(resolve => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (p) => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 }
        );
    });
}

function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDistance(m) {
    if (m < 950) return Math.round(m) + ' m';
    return (m / 1000).toFixed(1).replace('.0', '') + ' km';
}

function sortByDistance(lat, lng) {
    document.querySelectorAll('.day-section').forEach(sec => {
        const cards = Array.from(sec.querySelectorAll('.pose[data-task-id]'));
        cards.forEach(c => {
            const pLat = parseFloat(c.dataset.lat);
            const pLng = parseFloat(c.dataset.lng);
            const d = (isNaN(pLat) || isNaN(pLng)) ? Infinity : haversine(lat, lng, pLat, pLng);
            c.dataset.distanceM = String(d);
            let pill = c.querySelector('.pose-distance');
            if (!pill) {
                pill = document.createElement('span');
                pill.className = 'pose-distance';
                const sub = c.querySelector('.pose-sub');
                if (sub) sub.appendChild(pill);
            }
            pill.textContent = '📡 ' + (isFinite(d) ? formatDistance(d) : '—');
        });
        cards.sort((a, b) => parseFloat(a.dataset.distanceM) - parseFloat(b.dataset.distanceM));
        cards.forEach(c => sec.appendChild(c));
    });
}

function restoreSsrOrder() {
    document.querySelectorAll('.day-section').forEach(sec => {
        const cards = Array.from(sec.querySelectorAll('.pose[data-task-id]'));
        cards.sort((a, b) => (parseInt(a.dataset.ssrOrder || a.dataset.taskId, 10))
                          - (parseInt(b.dataset.ssrOrder || b.dataset.taskId, 10)));
        cards.forEach(c => sec.appendChild(c));
    });
}

// ── Bind 1 : "Près de moi" (toggle distance) ─────────────────────
function bindDistance() {
    const distBtn = document.getElementById('ts-distance-btn');
    if (!distBtn) return;
    distBtn.addEventListener('click', async () => {
        const fs = state.filterState;
        if (fs.distance) {
            fs.distance = false;
            fs.geo = null;
            distBtn.classList.remove('is-active');
            const lbl = document.getElementById('ts-distance-label');
            if (lbl) lbl.textContent = 'Distance';
            restoreSsrOrder();
            document.querySelectorAll('.pose-distance').forEach(e => e.remove());
            writeFiltersToUrl();
            return;
        }
        distBtn.classList.add('is-active');
        const lbl = document.getElementById('ts-distance-label');
        if (lbl) lbl.textContent = '📡 Position…';
        const pos = await getGeoPosition();
        if (!pos) {
            distBtn.classList.remove('is-active');
            if (lbl) lbl.textContent = 'Distance';
            toastSmall('On ne te trouve pas. Autorise le GPS sur ton téléphone.', 'error');
            return;
        }
        fs.geo = { lat: pos.lat, lng: pos.lng };
        fs.distance = true;
        if (lbl) lbl.textContent = '✓ Proche';
        sortByDistance(pos.lat, pos.lng);
        writeFiltersToUrl();
    });
}

// ── Bind 2 : "Mon chemin" — TSP nearest-neighbor serveur ─────────
//
// État tournée : tourActive + originalParentByCard (Map). On préserve
// l'emplacement DOM original de chaque card AVANT de la reparenter dans
// la section #ts-tour-section. À l'exitTourMode, on rétablit.
let tourActive = false;
const originalParentByCard = new Map();

function preserveOriginalOrder() {
    document.querySelectorAll('.pose[data-task-id]').forEach(c => {
        originalParentByCard.set(c, { parent: c.parentNode, index: Array.from(c.parentNode.children).indexOf(c) });
    });
}

function exitTourMode() {
    tourActive = false;
    const tourBtn = document.getElementById('ts-tour-btn');
    tourBtn?.classList.remove('is-active');
    const lbl = document.getElementById('ts-tour-label');
    if (lbl) lbl.textContent = 'Tournée';
    document.getElementById('ts-tour-summary')?.classList.remove('show');
    document.querySelectorAll('.pose.tour-mode').forEach(c => {
        c.classList.remove('tour-mode');
        c.removeAttribute('data-tour-step');
        c.querySelector('.pose-tour-leg')?.remove();
    });
    originalParentByCard.forEach((info, card) => {
        const ref = info.parent.children[info.index];
        if (ref && ref !== card) info.parent.insertBefore(card, ref);
        else info.parent.appendChild(card);
    });
    document.querySelectorAll('.day-section').forEach(sec => { sec.style.display = ''; });
    applyFilters();
}

function applyTourOrder(order, totalMeters) {
    tourActive = true;
    const tourBtn = document.getElementById('ts-tour-btn');
    tourBtn?.classList.add('is-active');
    const lbl = document.getElementById('ts-tour-label');
    if (lbl) lbl.textContent = '✓';

    document.querySelectorAll('.day-section').forEach(s => s.style.display = '');

    let tourSec = document.getElementById('ts-tour-section');
    if (!tourSec) {
        tourSec = document.createElement('div');
        tourSec.id = 'ts-tour-section';
        tourSec.className = 'day-section';
        tourSec.innerHTML = '<div class="commune-header"><div class="ch-left"><h2 style="color:#15803d">🚀 Mon chemin</h2><span class="count">' + order.length + ' arrêts</span></div></div>';
        const empty = document.getElementById('ts-empty-filter');
        if (empty?.parentNode) empty.parentNode.insertBefore(tourSec, empty.nextSibling);
    } else {
        tourSec.querySelector('.count').textContent = order.length + ' arrêts';
        Array.from(tourSec.querySelectorAll('.pose')).forEach(c => c.remove());
    }

    order.forEach((step, idx) => {
        const card = document.querySelector(`.pose[data-task-id="${step.id}"]`);
        if (!card) return;
        card.classList.add('tour-mode');
        card.setAttribute('data-tour-step', idx + 1);
        let leg = card.querySelector('.pose-tour-leg');
        if (!leg) {
            leg = document.createElement('span');
            leg.className = 'pose-tour-leg';
            const sub = card.querySelector('.pose-sub');
            if (sub) sub.appendChild(leg);
        }
        leg.textContent = '🚀 +' + formatDistance(step.leg_meters);
        tourSec.appendChild(card);
    });

    document.querySelectorAll('.day-section').forEach(sec => {
        if (sec === tourSec) return;
        if (!sec.querySelector('.pose[data-task-id]')) sec.style.display = 'none';
    });

    const cntEl = document.getElementById('ts-tour-count');
    const totEl = document.getElementById('ts-tour-total');
    if (cntEl) cntEl.textContent = order.length;
    if (totEl) totEl.textContent = totalMeters >= 1000
        ? (totalMeters / 1000).toFixed(1).replace('.0', '') + ' km'
        : totalMeters + ' m';
    document.getElementById('ts-tour-summary')?.classList.add('show');
    tourSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function bindTour() {
    const tourBtn = document.getElementById('ts-tour-btn');
    const tourUrl = window.TECH_CONFIG?.routes?.optimize;
    if (!tourBtn || !tourUrl) return;

    tourBtn.addEventListener('click', async () => {
        if (tourActive) { exitTourMode(); return; }
        if (!navigator.geolocation) {
            toastSmall('GPS bloqué — autorise-le pour utiliser cette option.', 'error');
            return;
        }
        tourBtn.disabled = true;
        const lbl = document.getElementById('ts-tour-label');
        if (lbl) lbl.textContent = '🛰…';
        const pos = await getGeoPosition();
        if (!pos) {
            tourBtn.disabled = false;
            if (lbl) lbl.textContent = 'Tournée';
            toastSmall('On ne te trouve pas — autorise le GPS.', 'error');
            return;
        }
        if (lbl) lbl.textContent = '🔄…';
        try {
            const ids = Array.from(document.querySelectorAll('.pose[data-task-id]'))
                .map(c => parseInt(c.dataset.taskId, 10)).filter(Boolean);
            const u = new URL(tourUrl, location.origin);
            u.searchParams.set('lat', pos.lat.toFixed(6));
            u.searchParams.set('lng', pos.lng.toFixed(6));
            u.searchParams.set('scope', 'rendered');
            ids.forEach(id => u.searchParams.append('ids[]', id));
            const r = await fetch(u.toString(), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            if (!r.ok || !d.ok) throw new Error('optimize');
            applyTourOrder(d.order, d.total_meters);
        } catch (e) {
            toastSmall('On n\'arrive pas à calculer — essaie encore.', 'error');
            if (lbl) lbl.textContent = 'Tournée';
        } finally {
            tourBtn.disabled = false;
        }
    });

    document.getElementById('ts-tour-quit')?.addEventListener('click', exitTourMode);
}

// Mémorise l'ordre SSR initial pour restauration propre (distance ET tour).
function memorizeSsrOrder() {
    document.querySelectorAll('.day-section').forEach(sec => {
        Array.from(sec.querySelectorAll('.pose[data-task-id]')).forEach((c, i) => {
            c.dataset.ssrOrder = String(i);
        });
    });
}

export function init() {
    memorizeSsrOrder();
    preserveOriginalOrder();
    bindDistance();
    bindTour();
}

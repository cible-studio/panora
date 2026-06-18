// public/js/tech/features/filters.js — Phase 3 SM1.
//
// Filtres rapides combinables (chips late/today/problem/reject/en_route/
// en_cours) + KPI grid (data-kpi-filter all|today) + TOC zones cliquable.
// État persisté dans l'URL (?kpi=today&chips=late,problem&zone=Cocody&sort=distance)
// pour bookmark / share / back-forward.
//
// Source : sections 1-8 du bloc 2 du <script> inline (lignes 984-1159 de
// tech-space.blade.php avant Phase 3).
//
// Note : `filters.applyFilters` est exporté pour que d'autres features
// (geolocate.js mode tournée, focus.js) puissent re-déclencher l'évaluation
// après avoir modifié l'état partagé.

import { state } from '../core/state.js';

const filterState = state.filterState; // alias court — référence partagée

// ─── 2. Lecture / écriture URL (bookmark / share / back-fwd) ──
function readFiltersFromUrl() {
    const u = new URL(location.href);
    const kpi = u.searchParams.get('kpi');
    if (kpi === 'today') filterState.kpi = 'today';
    const chips = u.searchParams.get('chips');
    if (chips) chips.split(',').filter(Boolean).forEach(c => filterState.chips.add(c));
    const zone = u.searchParams.get('zone');
    if (zone) filterState.zone = zone;
    if (u.searchParams.get('sort') === 'distance') filterState.distance = true;
}
export function writeFiltersToUrl() {
    const u = new URL(location.href);
    u.searchParams.delete('kpi');
    u.searchParams.delete('chips');
    u.searchParams.delete('zone');
    u.searchParams.delete('sort');
    if (filterState.kpi !== 'all') u.searchParams.set('kpi', filterState.kpi);
    if (filterState.chips.size)    u.searchParams.set('chips', [...filterState.chips].join(','));
    if (filterState.zone)          u.searchParams.set('zone', filterState.zone);
    if (filterState.distance)      u.searchParams.set('sort', 'distance');
    try { history.replaceState(null, '', u.toString()); } catch (e) { /* old browsers */ }
}

// ─── 3. Test d'une card vs filtres actifs ──────────────────────
function matchesFilters(el) {
    const status     = el.dataset.taskStatus;
    const isLate     = el.dataset.late === '1';
    const isToday    = el.dataset.scheduledToday === '1';
    const hasProblem = el.dataset.hasProblem === '1';
    const hasReject  = el.dataset.hasReject === '1';
    const commune    = el.dataset.commune || '';

    if (filterState.kpi === 'today' && !isToday) return false;
    if (filterState.zone && commune !== filterState.zone) return false;

    for (const c of filterState.chips) {
        if (c === 'late'     && !isLate)    return false;
        if (c === 'today'    && !isToday)   return false;
        if (c === 'problem'  && !hasProblem) return false;
        if (c === 'reject'   && !hasReject) return false;
        if (c === 'en_route' && status !== 'en_route') return false;
        if (c === 'en_cours' && status !== 'en_cours') return false;
    }
    return true;
}

// ─── 4. Applique les filtres au DOM + recalc compteurs/sections ─
export function applyFilters() {
    const poses = document.querySelectorAll('.pose[data-task-id]');
    let visible = 0;
    poses.forEach(p => {
        const match = matchesFilters(p);
        p.style.display = match ? '' : 'none';
        p.classList.toggle('is-filtered-out', !match);
        if (match) visible++;
    });

    // Masque les sections vides
    document.querySelectorAll('.day-section').forEach(sec => {
        const has = sec.querySelector('.pose:not([style*="display: none"]):not([style*="display:none"])');
        sec.style.display = has ? '' : 'none';
    });

    // Empty state si aucun match
    const empty = document.getElementById('ts-empty-filter');
    if (empty) {
        const anyFilter = filterState.kpi !== 'all' || filterState.chips.size > 0 || filterState.zone;
        empty.style.display = (anyFilter && visible === 0) ? 'block' : 'none';
    }

    // Bouton "Effacer" visible uniquement si filtres actifs
    const clearBtn = document.getElementById('ts-filter-clear');
    if (clearBtn) {
        clearBtn.style.display = (filterState.chips.size || filterState.kpi !== 'all' || filterState.zone)
            ? 'inline-block' : 'none';
    }
}

// ─── 5. Compteurs chips (live, basés sur les cards SSR) ──────
function refreshChipCounts() {
    const counts = { late: 0, today: 0, problem: 0, reject: 0, en_route: 0, en_cours: 0 };
    document.querySelectorAll('.pose[data-task-id]').forEach(p => {
        if (p.dataset.late === '1')           counts.late++;
        if (p.dataset.scheduledToday === '1') counts.today++;
        if (p.dataset.hasProblem === '1')     counts.problem++;
        if (p.dataset.hasReject === '1')      counts.reject++;
        const st = p.dataset.taskStatus;
        if (st === 'en_route') counts.en_route++;
        if (st === 'en_cours') counts.en_cours++;
    });
    Object.entries(counts).forEach(([k, v]) => {
        const el = document.querySelector(`[data-cnt="${k}"]`);
        if (el) el.textContent = v;
    });
    // Masque chips à 0 (réduit le bruit visuel)
    document.querySelectorAll('.filter-chip[data-filter]').forEach(c => {
        const k = c.dataset.filter;
        if (counts[k] === 0 && !filterState.chips.has(k)) {
            c.style.display = 'none';
        } else {
            c.style.display = '';
        }
    });
}

function bindChips() {
    document.querySelectorAll('.filter-chip[data-filter]').forEach(chip => {
        chip.addEventListener('click', () => {
            const k = chip.dataset.filter;
            if (filterState.chips.has(k)) filterState.chips.delete(k);
            else filterState.chips.add(k);
            chip.classList.toggle('is-active', filterState.chips.has(k));
            writeFiltersToUrl();
            applyFilters();
        });
    });
    document.getElementById('ts-filter-clear')?.addEventListener('click', () => {
        filterState.chips.clear();
        filterState.kpi = 'all';
        filterState.zone = null;
        document.querySelectorAll('.filter-chip.is-active').forEach(c => c.classList.remove('is-active'));
        document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(c => {
            c.classList.toggle('is-active', c.dataset.kpiFilter === 'all');
            c.setAttribute('aria-pressed', c.dataset.kpiFilter === 'all' ? 'true' : 'false');
        });
        writeFiltersToUrl();
        applyFilters();
    });
}

// ─── 7. Branchement KPI grid → filterState.kpi ────────────────
// Le code legacy (bloc 1 du <script>) écoutait déjà data-kpi-filter via
// applyKpiFilter(). On surcharge en CAPTURE PHASE (3e arg true) pour
// stopper la propagation au handler legacy et faire un applyFilters
// combiné KPI+chips (avant : KPI seul resetait tout).
function bindKpiGrid() {
    document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopImmediatePropagation();
            const name = btn.dataset.kpiFilter;
            filterState.kpi = name;
            document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
            writeFiltersToUrl();
            applyFilters();
        }, true); // capture phase — précède le handler legacy
    });
}

// ─── 8. TOC zones cliquable (smooth scroll vers section) ──────
function bindTocZones() {
    document.querySelectorAll('.zone-toc-chip').forEach(a => {
        a.addEventListener('click', (e) => {
            const href = a.getAttribute('href');
            if (!href || !href.startsWith('#')) return;
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                target.style.transition = 'box-shadow .8s';
                target.style.boxShadow = '0 0 0 3px rgba(232,160,32,.5)';
                setTimeout(() => target.style.boxShadow = '', 1200);
            }
        });
    });
}

export function init() {
    readFiltersFromUrl();
    bindChips();
    bindKpiGrid();
    bindTocZones();
    refreshChipCounts();
    applyFilters();
}

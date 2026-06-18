// public/js/tech/features/filters.js — SM1.5 Lot 5.
//
// Brique centrale de l'interaction côté tech : combine 3 axes de filtre
// (KPI grid, chips, zone) + 1 axe de tri (distance haversine) sur les
// cards .pose SSR. Tous les états sont dans state.filterState ; chaque
// action repasse par writeFiltersToUrl() → applyFilters() pour rester
// O(N) et bookmarkable.
//
// Source : blocs 1-8 + 13 du <script> inline pré-SM1.5 (lignes 594-778
// et 853-867). Migration 1:1 comportement-identique.
//
// Dépendances :
//   - core/state.js : filterState (Set chips + kpi + zone + distance + geo)
//
// Consommateurs externes (imports) :
//   - search.js  : lit filterState (chips, zone, distance, geo) pour
//                  contextualiser l'AJAX Select2.
//   - geolocate.js : appelle applyFilters() + writeFiltersToUrl() lors
//                    des toggles distance / exit-tour.
//   - tech-app.js : appelle init() (les bindings) + initial state restore.

import { state } from '../core/state.js';

const filterState = state.filterState;

export function readFiltersFromUrl() {
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

// Test d'une card vs filtres actifs. Combine KPI + chips + zone.
// Un chip "today" et un KPI "today" sont équivalents — la double-coche
// n'a pas d'effet.
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

// Applique les filtres au DOM + recalc sections vides + empty state +
// visibilité du bouton "Effacer".
export function applyFilters() {
    const poses = document.querySelectorAll('.pose[data-task-id]');
    let visible = 0;
    poses.forEach(p => {
        const match = matchesFilters(p);
        p.style.display = match ? '' : 'none';
        p.classList.toggle('is-filtered-out', !match);
        if (match) visible++;
    });

    document.querySelectorAll('.day-section').forEach(sec => {
        const has = sec.querySelector('.pose:not([style*="display: none"]):not([style*="display:none"])');
        sec.style.display = has ? '' : 'none';
    });

    const empty = document.getElementById('ts-empty-filter');
    if (empty) {
        const anyFilter = filterState.kpi !== 'all' || filterState.chips.size > 0 || filterState.zone;
        empty.style.display = (anyFilter && visible === 0) ? 'block' : 'none';
    }

    const clearBtn = document.getElementById('ts-filter-clear');
    if (clearBtn) {
        clearBtn.style.display = (filterState.chips.size || filterState.kpi !== 'all' || filterState.zone)
            ? 'inline-block' : 'none';
    }
}

// Compteurs chips (live, basés sur les cards SSR). Masque les chips à 0
// SAUF s'ils sont actifs (sinon on ne pourrait plus les désactiver).
export function refreshChipCounts() {
    const counts = { late: 0, today: 0, problem: 0, reject: 0, en_route: 0, en_cours: 0 };
    document.querySelectorAll('.pose[data-task-id]').forEach(p => {
        if (p.dataset.late === '1')          counts.late++;
        if (p.dataset.scheduledToday === '1') counts.today++;
        if (p.dataset.hasProblem === '1')    counts.problem++;
        if (p.dataset.hasReject === '1')     counts.reject++;
        const st = p.dataset.taskStatus;
        if (st === 'en_route') counts.en_route++;
        if (st === 'en_cours') counts.en_cours++;
    });
    Object.entries(counts).forEach(([k, v]) => {
        const el = document.querySelector(`[data-cnt="${k}"]`);
        if (el) el.textContent = v;
    });
    document.querySelectorAll('.filter-chip[data-filter]').forEach(c => {
        const k = c.dataset.filter;
        if (counts[k] === 0 && !filterState.chips.has(k)) {
            c.style.display = 'none';
        } else {
            c.style.display = '';
        }
    });
}

// ── Branchements UI ──────────────────────────────────────────────
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
}

function bindClearAll() {
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

// Le HTML SSR pose déjà des handlers data-kpi-filter via le KPI grid
// historique (applyKpiFilter() inline supprimé en Phase 3 SM1). On
// intercepte ici en capture phase pour combiner KPI + chips au lieu
// de tout réinitialiser comme le faisait le handler legacy.
function bindKpiGrid() {
    document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopImmediatePropagation();
            const name = btn.dataset.kpiFilter;
            filterState.kpi = name; // 'all' ou 'today'
            document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(b => {
                b.classList.toggle('is-active', b === btn);
                b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
            });
            writeFiltersToUrl();
            applyFilters();
        }, true); // capture phase
    });
}

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

// Restaure l'UI depuis l'URL au load : chips actifs + KPI sélectionné +
// compteurs + applyFilters initial.
function bootstrapFromUrl() {
    readFiltersFromUrl();
    filterState.chips.forEach(k => {
        const chip = document.querySelector(`.filter-chip[data-filter="${k}"]`);
        chip?.classList.add('is-active');
    });
    if (filterState.kpi === 'today') {
        const kpiBtn = document.querySelector('.kpi-card[data-kpi-filter="today"]');
        kpiBtn?.classList.add('is-active');
        kpiBtn?.setAttribute('aria-pressed', 'true');
        document.querySelector('.kpi-card[data-kpi-filter="all"]')?.classList.remove('is-active');
    }
    refreshChipCounts();
    applyFilters();
}

export function init() {
    bindChips();
    bindClearAll();
    bindKpiGrid();
    bindTocZones();
    bootstrapFromUrl();
}

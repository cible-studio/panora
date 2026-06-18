// public/js/tech/features/search.js — Phase 3 SM1.
//
// Select2 v4 sur #ts-search. Source AJAX paginée vers tech.space.search.
// Permet au tech de trouver une pose qui n'est pas dans le SSR (cap 200
// → la recherche serveur tape la BDD entière). Au choix d'un résultat :
//   - si la card est dans le DOM → scroll vers elle + highlight bref
//   - sinon → openFocusModal() avec photo + ref + Maps + Fermer
//
// Source : sections 9-10 du bloc 2 du <script> inline (lignes 1161-1303
// de tech-space.blade.php avant Phase 3). jQuery + Select2 v4 chargés
// via <script defer> juste avant le <script> inline historique — ce
// module attend que jQuery soit prêt (check au début de init()).

import { state } from '../core/state.js';

const filterState = state.filterState;
let searchUrl = null;

function formatSearchOption($, item) {
    if (!item.id) return $('<span style="color:var(--text3)">' + (item.text || '') + '</span>');
    const thumbStyle = item.thumb_url ? `style="background-image:url('${item.thumb_url}')"` : '';
    const thumb = item.thumb_url
        ? `<span class="s2-thumb" ${thumbStyle}></span>`
        : `<span class="s2-thumb">🪧</span>`;
    const pills = [];
    if (item.is_late)     pills.push('<span class="s2-pill late">⏰ Retard</span>');
    if (item.has_reject)  pills.push('<span class="s2-pill reject">🚫 Refusée</span>');
    if (item.has_problem) pills.push('<span class="s2-pill warn">⚠ Signalée</span>');
    const meta = [
        item.commune ? '📍 ' + item.commune : '',
        item.campaign ? '📢 ' + (item.campaign.length > 24 ? item.campaign.slice(0, 24) + '…' : item.campaign) : '',
    ].filter(Boolean).join(' · ');
    return $(`
        <div class="s2-row">
            ${thumb}
            <div class="s2-info">
                <div class="s2-ref">${item.ref || ''} ${pills.join(' ')}</div>
                <div class="s2-name">${item.name || ''}</div>
                <div class="s2-meta">${meta}</div>
            </div>
        </div>
    `);
}

function openFocusModal(item) {
    const existing = document.getElementById('ts-focus-modal');
    if (existing) existing.remove();
    const goUrl = (item.lat && item.lng)
        ? `https://www.google.com/maps/dir/?api=1&destination=${item.lat},${item.lng}`
        : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent([item.adresse, item.quartier, item.commune, "Côte d'Ivoire"].filter(Boolean).join(', '))}`;
    const ov = document.createElement('div');
    ov.id = 'ts-focus-modal';
    ov.style.cssText = 'position:fixed;inset:0;z-index:9999;background:rgba(15,23,42,.55);display:flex;align-items:flex-end;justify-content:center;padding:0';
    ov.innerHTML = `
        <div style="background:#fff;width:100%;max-width:520px;border-radius:18px 18px 0 0;padding:20px 18px calc(18px + env(safe-area-inset-bottom));animation:tsUp .25s ease">
            <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px">
                ${item.thumb_url
                    ? `<span style="flex:0 0 64px;width:64px;height:64px;border-radius:12px;background:url('${item.thumb_url}') center/cover;border:1px solid var(--border)"></span>`
                    : `<span style="flex:0 0 64px;width:64px;height:64px;border-radius:12px;background:var(--surface2);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--text3)">🪧</span>`}
                <div style="flex:1;min-width:0">
                    <div style="font-family:ui-monospace,monospace;font-size:17px;font-weight:800;color:var(--accent-dark)">${item.ref || ''}</div>
                    <div style="font-size:13px;color:var(--text);font-weight:600;margin-top:2px">${item.name || ''}</div>
                    <div style="font-size:11.5px;color:var(--text3);margin-top:4px">
                        📍 ${item.commune || '—'}${item.adresse ? ' · ' + item.adresse : ''}
                    </div>
                    ${item.campaign ? `<div style="font-size:11px;color:var(--text2);margin-top:3px">📢 ${item.campaign}</div>` : ''}
                </div>
            </div>
            ${item.is_late ? '<div style="background:rgba(239,68,68,.08);color:#b91c1c;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:700;margin-bottom:10px">⏰ En retard — fais celui-ci en premier</div>' : ''}
            ${item.reject_reason ? `<div style="background:rgba(239,68,68,.08);color:#b91c1c;padding:8px 12px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:10px">🚫 Photo refusée : ${item.reject_reason}</div>` : ''}
            <div style="display:flex;gap:8px;margin-top:6px">
                <a href="${goUrl}" target="_blank" rel="noopener" style="flex:1;min-height:48px;display:flex;align-items:center;justify-content:center;gap:6px;background:linear-gradient(135deg,#3b82f6,#2563eb);color:#fff;font-weight:800;border-radius:12px;text-decoration:none;font-size:14px">🧭 Y aller</a>
                <button type="button" data-act="close" style="flex:0 0 96px;min-height:48px;background:#f3f4f6;color:#4b5563;border:none;border-radius:12px;font-weight:700;font-size:14px;cursor:pointer">Fermer</button>
            </div>
            <div style="margin-top:12px;font-size:11px;color:var(--text3);line-height:1.5">
                Ce panneau n'est pas dans ta liste du moment.
                Va sur place, prends la photo et envoie — tout sera enregistré.
            </div>
        </div>`;
    document.body.appendChild(ov);
    ov.querySelector('[data-act="close"]').addEventListener('click', () => ov.remove());
    ov.addEventListener('click', (e) => { if (e.target === ov) ov.remove(); });
}

function setupSelect2($) {
    const $search = $('#ts-search');
    if (!$search.length) return;
    $search.select2({
        placeholder: $search.data('placeholder') || 'Rechercher…',
        allowClear:  true,
        minimumInputLength: 0,
        dropdownParent: $('.controls-bar'),
        language: {
            inputTooShort: () => '',
            searching:     () => '🔄 On cherche…',
            noResults:     () => 'Aucun panneau trouvé',
            errorLoading:  () => 'Problème — réessaie',
        },
        ajax: {
            url: searchUrl,
            dataType: 'json',
            delay: 220,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            data: (params) => {
                const d = {
                    q:    params.term || '',
                    page: params.page || 1,
                    per_page: 20,
                };
                if (filterState.chips.has('late'))     d.late    = 1;
                if (filterState.chips.has('today'))    d.today   = 1;
                if (filterState.chips.has('problem'))  d.problem = 1;
                if (filterState.chips.has('reject'))   d.reject  = 1;
                if (filterState.chips.has('en_route')) d.status  = 'en_route';
                if (filterState.chips.has('en_cours')) d.status  = 'en_cours';
                if (filterState.zone) d.commune = filterState.zone;
                if (filterState.distance && filterState.geo) {
                    d.sort = 'distance';
                    d.lat  = filterState.geo.lat;
                    d.lng  = filterState.geo.lng;
                }
                return d;
            },
            processResults: (data, params) => {
                params.page = params.page || 1;
                return {
                    results: (data.results || []).map(r => ({ ...r, id: r.id, text: r.text })),
                    pagination: { more: data.pagination?.more === true },
                };
            },
            cache: true,
        },
        templateResult:    (item) => formatSearchOption($, item),
        templateSelection: (item) => item.text || item.ref || '🔍 Rechercher…',
        escapeMarkup: m => m,
    });

    // Sélection → scroll vers la card si en DOM, sinon focus modal.
    $search.on('select2:select', function (e) {
        const item = e.params.data;
        if (!item || !item.id) return;
        const existing = document.querySelector(`.pose[data-task-id="${item.id}"]`);
        if (existing) {
            existing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            existing.style.transition = 'box-shadow .8s';
            existing.style.boxShadow = '0 0 0 3px var(--accent), 0 12px 36px -10px rgba(232,160,32,.5)';
            setTimeout(() => existing.style.boxShadow = '', 1800);
        } else {
            openFocusModal(item);
        }
        setTimeout(() => $search.val(null).trigger('change'), 100);
    });
}

export function init() {
    searchUrl = window.TECH_CONFIG?.routes?.search;
    if (!searchUrl) return;

    // jQuery + Select2 sont chargés en <script defer> côté Blade. Ce module
    // ESM s'exécute à DOMContentLoaded, ce qui est APRÈS l'exécution des
    // scripts defer en théorie. Mais le brief Phase 3 précise que les
    // modules sont chargés via <script type="module"> qui est defer par
    // défaut → l'ordre est garanti.
    if (typeof window.jQuery === 'undefined') {
        // Fallback : si jQuery n'est pas encore là (cas pathologique),
        // on retente après un micro-délai.
        setTimeout(() => init(), 100);
        return;
    }
    setupSelect2(window.jQuery);
}

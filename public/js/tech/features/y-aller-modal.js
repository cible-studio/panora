// public/js/tech/features/y-aller-modal.js — SM2a Lot 2.2.
//
// Modale T7 "Confirmation Y aller" (spec §3 T7). S'interpose entre le
// clic sur un bouton [data-go-maps] et l'ouverture effective de Google
// Maps. Le tech voit une mini-carte stylisée + estim distance/temps, et
// confirme.
//
// Stratégie pour respecter status-changes.js sans le modifier :
//   - Intercept au document level EN CAPTURE phase → on attrape avant
//     les autres handlers et on bloque (preventDefault + stopPropagation).
//   - Quand le tech valide → on re-clique programmatiquement sur le
//     bouton original, mais on positionne un flag `bypassNext` que notre
//     propre intercepteur lit pour SE laisser passer. Les autres
//     handlers (bindGoMaps de status-changes.js qui bump en_route) fire
//     normalement.
//   - Quand le tech annule → on ne touche pas au bouton, juste fermeture.
//
// Lien GPS :
//   - Si le tech a déjà autorisé la géolocalisation (cache navigateur),
//     on calcule la distance haversine + estim temps à pied (5 km/h).
//   - Sinon : on affiche "—" et un fallback texte.

const SEL_OVERLAY = '#sm2-t7-overlay';
let bypassNext = false;
let pendingOriginalBtn = null;

function haversineMeters(lat1, lng1, lat2, lng2) {
    const R = 6371000;
    const toRad = d => d * Math.PI / 180;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function formatDistance(m) {
    if (m == null || isNaN(m)) return '—';
    if (m < 950) return Math.round(m) + ' m';
    return (m / 1000).toFixed(1).replace('.0', '') + ' km';
}

function formatWalkTime(m) {
    if (m == null || isNaN(m)) return '—';
    // 5 km/h = ~83.33 m/min → m / 83 = minutes
    const min = Math.max(1, Math.round(m / 83));
    if (min < 60) return min + ' min';
    const h = Math.floor(min / 60);
    return `${h}h${(min % 60).toString().padStart(2, '0')}`;
}

function getTechPosition() {
    return new Promise(resolve => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: false, timeout: 4000, maximumAge: 60000 }
        );
    });
}

async function populateModal(overlay, srcBtn) {
    // Cherche le container [data-task-id] le plus proche (.pose-line ou
    // #sm2-t2-drawer). On lit aussi name/commune.
    const carrier = srcBtn.closest('[data-task-id]');
    const poseName = carrier?.querySelector('.pose-name')?.textContent?.trim()
                  || carrier?.querySelector('[data-field="name"]')?.textContent?.trim()
                  || 'cette pose';
    overlay.querySelector('[data-field="pose-name"]').textContent = poseName;

    // Bouton "Ouvrir Google Maps" : on lui pose l'URL du bouton source +
    // les mêmes target/rel pour cohérence.
    const goLink = overlay.querySelector('[data-action="t7-confirm"]');
    goLink.setAttribute('href',  srcBtn.getAttribute('href') || '#');
    goLink.setAttribute('target', srcBtn.getAttribute('target') || '_blank');
    goLink.setAttribute('rel',    srcBtn.getAttribute('rel')    || 'noopener');

    // Reset stats
    overlay.querySelector('[data-field="time-est"]').textContent = '—';
    overlay.querySelector('[data-field="dist-est"]').textContent = '—';

    // Si la card source a lat/lng + permission GPS → calcul distance
    const lat = parseFloat(carrier?.dataset.lat);
    const lng = parseFloat(carrier?.dataset.lng);
    if (isFinite(lat) && isFinite(lng)) {
        const me = await getTechPosition();
        if (me) {
            const m = haversineMeters(me.lat, me.lng, lat, lng);
            overlay.querySelector('[data-field="dist-est"]').textContent = formatDistance(m);
            overlay.querySelector('[data-field="time-est"]').textContent = formatWalkTime(m);
        }
    }

    // ═══ EXCLUSIVITÉ EN ROUTE — Confirmation switch (2026-07-07) ═══
    // Si le tech est déjà "en route" ou "sur place" sur une AUTRE pose,
    // on affiche un bandeau warning dans le modal T7 pour qu'il confirme
    // vraiment le changement de destination.
    const currentTaskId = carrier?.dataset.taskId;
    const otherActive = document.querySelector(
        `.pose-line[data-task-status="en_route"]:not([data-task-id="${currentTaskId}"]),`
        + `.pose-line[data-task-status="en_cours"]:not([data-task-id="${currentTaskId}"])`
    );
    const warnEl = overlay.querySelector('[data-field="switch-warning"]');
    if (otherActive && warnEl) {
        const otherRef  = otherActive.querySelector('.pose-ref')?.textContent?.trim() || 'une autre pose';
        const otherName = otherActive.querySelector('.pose-name')?.textContent?.trim() || '';
        const otherCommune = otherActive.dataset.commune || '';
        const isOnSite = otherActive.dataset.taskStatus === 'en_cours';
        warnEl.innerHTML = `
            <div style="font-size:12.5px;font-weight:800;color:#b91c1c;margin-bottom:6px">
                ⚠ ${isOnSite ? 'Tu es sur place' : 'Tu es déjà en route vers'} :
            </div>
            <div style="font-size:13px;color:#7f1d1d;line-height:1.4">
                <strong>${otherRef}</strong>${otherName ? ' · ' + otherName : ''}${otherCommune ? ' <span style="opacity:.7">('+otherCommune+')</span>' : ''}
            </div>
            <div style="font-size:11.5px;color:#7f1d1d;margin-top:6px;font-style:italic">
                Si tu confirmes, cette pose reviendra à « planifiée ».
            </div>
        `;
        warnEl.hidden = false;
        // Change le libellé du bouton confirm pour être explicite
        const goLink = overlay.querySelector('[data-action="t7-confirm"]');
        if (goLink) goLink.innerHTML = '🔄 Oui, changer de destination';
    } else if (warnEl) {
        warnEl.hidden = true;
        warnEl.innerHTML = '';
        const goLink = overlay.querySelector('[data-action="t7-confirm"]');
        if (goLink) goLink.innerHTML = '🗺️ Ouvrir Google Maps';
    }
}

function open(srcBtn) {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    pendingOriginalBtn = srcBtn;
    populateModal(overlay, srcBtn);
    overlay.hidden = false;
    overlay.removeAttribute('aria-hidden');
    requestAnimationFrame(() => overlay.classList.add('is-open'));
    document.body.style.overflow = 'hidden';
}

function close() {
    const overlay = document.querySelector(SEL_OVERLAY);
    if (!overlay) return;
    overlay.classList.remove('is-open');
    overlay.setAttribute('aria-hidden', 'true');
    setTimeout(() => { overlay.hidden = true; }, 220);
    document.body.style.overflow = '';
    pendingOriginalBtn = null;
}

function confirm(e) {
    e.preventDefault();
    const btn = pendingOriginalBtn;
    close();
    if (!btn) return;
    // Re-clique le bouton original en bypass de notre intercepteur :
    // bindGoMaps de status-changes.js va alors bumper en_route, et la
    // navigation Google Maps se fait via le target="_blank" du <a>.
    bypassNext = true;
    btn.click();
    // Reset le flag à la fin du tick.
    setTimeout(() => { bypassNext = false; }, 100);
}

export function init() {
    if (!document.querySelector(SEL_OVERLAY)) return;

    // Capture phase pour intercepter AVANT bindGoMaps de status-changes.js
    document.addEventListener('click', (e) => {
        const goBtn = e.target.closest('[data-go-maps]');
        if (!goBtn) return;
        if (bypassNext) return; // notre propre re-click → laisser passer
        if (goBtn.closest(SEL_OVERLAY)) return; // clic interne à la modale
        e.preventDefault();
        e.stopPropagation();
        open(goBtn);
    }, true);

    // Boutons internes
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-action="t7-cancel"]')) {
            e.preventDefault();
            close();
            return;
        }
        if (e.target.closest('[data-action="t7-confirm"]')) {
            confirm(e);
            return;
        }
        // Clic sur l'overlay hors-modale → close
        const overlay = e.target.closest(SEL_OVERLAY);
        if (overlay && !e.target.closest('.sm2-t7-modal')) close();
    });

    // Escape ferme
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && document.querySelector(`${SEL_OVERLAY}.is-open`)) {
            close();
        }
    });
}

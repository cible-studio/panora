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

/* SM2b Phase 4 — Carte LIVE des techs (A3). Leaflet 1.9 + markercluster.
   Pas en module ES car Leaflet est en global script (window.L).

   Architecture :
     - tick() poll 20s admin.map.live
     - markers : Map<techId, L.Marker> pour mise à jour incrémentale
     - Polling pause si onglet caché.
     - Click sur un marqueur : popup avec nom + statut + lien fiche A2
     - Click sur la sidebar : pan+zoom sur le tech ciblé. */

(function () {
    var CFG = window.ADMIN_MAP_LIVE_CONFIG || {};
    var POLL_MS = CFG.pollMs || 20000;

    var map = null;
    var clusterLayer = null;
    var markers = {};         // techId → L.Marker
    var lastTechsById = {};   // techId → tech data (pour cache)
    var pollTimer = null;

    function $(sel) { return document.querySelector(sel); }

    function buildIcon(tech) {
        var status = tech.last_seen_at
            ? (Date.now() - new Date(tech.last_seen_at).getTime() < 5 * 60000 ? 'active' : 'inactive')
            : 'inactive';
        var color = status === 'active' ? '#16a34a'
            : tech.current_pose_label ? '#ea580c' : '#9ca3af';
        return L.divIcon({
            className: 'map-live-pin',
            html: '<div class="map-live-pin-inner" style="background:' + color + '">'
                +  '<span class="map-live-pin-initials">' + escapeHtml(tech.initials || '??') + '</span>'
                +  '</div>',
            iconSize: [40, 40],
            iconAnchor: [20, 20],
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

    function popupHtml(tech) {
        var poseUrl = CFG.techDetailUrlTpl ? CFG.techDetailUrlTpl.replace('__USER__', tech.id) : '#';
        var seenAt = tech.last_seen_at ? relativeTime(new Date(tech.last_seen_at)) : '—';
        return ''
            + '<div class="map-live-popup">'
            +   '<div class="map-live-popup-head"><strong>' + escapeHtml(tech.full_name) + '</strong></div>'
            +   '<div class="map-live-popup-meta">Dernier signal ' + escapeHtml(seenAt) + '</div>'
            +   (tech.current_pose_label
                ? '<div class="map-live-popup-pose">📍 ' + escapeHtml(tech.current_pose_label) + '</div>'
                : '')
            +   '<a class="map-live-popup-link" href="' + poseUrl + '">Voir la fiche →</a>'
            + '</div>';
    }

    function relativeTime(d) {
        var s = Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000));
        if (s < 60) return 'il y a ' + s + 's';
        if (s < 3600) return 'il y a ' + Math.floor(s / 60) + ' min';
        return 'il y a ' + Math.floor(s / 3600) + ' h';
    }

    function renderSidebar(techs) {
        var list = $('[data-field="side-list"]');
        var empty = $('[data-field="side-empty"]');
        var tpl = $('[data-field="side-row-tpl"]');
        var countEl = $('[data-field="techs-count"]');
        if (!list || !tpl) return;
        countEl && (countEl.textContent = techs.length + ' tech' + (techs.length > 1 ? 's' : '') + ' en ligne');

        if (!techs.length) {
            list.hidden = true;
            empty.hidden = false;
            return;
        }
        list.hidden = false;
        empty.hidden = true;

        // Reset
        Array.prototype.forEach.call(list.querySelectorAll('li.map-live-side-row'), function (n) { n.remove(); });

        techs.forEach(function (tech) {
            var node = tpl.content.cloneNode(true);
            var li = node.querySelector('li');
            li.dataset.techId = tech.id;
            li.querySelector('[data-field="side-initials"]').textContent = tech.initials || '??';
            li.querySelector('[data-field="side-name"]').textContent     = tech.full_name || '—';
            li.querySelector('[data-field="side-meta"]').textContent     =
                (tech.current_pose_label ? '📍 ' + tech.current_pose_label : 'Hors mission')
                + ' · ' + (tech.last_seen_at ? relativeTime(new Date(tech.last_seen_at)) : '—');
            li.addEventListener('click', function () { panToTech(tech.id); });
            list.appendChild(li);
        });
    }

    function panToTech(techId) {
        var m = markers[techId];
        if (!m || !map) return;
        var ll = m.getLatLng();
        map.flyTo(ll, Math.max(map.getZoom(), 14), { duration: .6 });
        m.openPopup();
    }

    function updateMarkers(techs) {
        if (!map) return;
        var newIds = new Set();
        techs.forEach(function (tech) {
            newIds.add(tech.id);
            var ll = [tech.lat, tech.lng];
            if (markers[tech.id]) {
                markers[tech.id].setLatLng(ll).setIcon(buildIcon(tech));
                markers[tech.id].setPopupContent(popupHtml(tech));
            } else {
                var m = L.marker(ll, { icon: buildIcon(tech) });
                m.bindPopup(popupHtml(tech));
                clusterLayer.addLayer(m);
                markers[tech.id] = m;
            }
            lastTechsById[tech.id] = tech;
        });
        // Supprimer les marqueurs des techs qui ne sont plus dans le payload
        Object.keys(markers).forEach(function (id) {
            if (!newIds.has(parseInt(id, 10))) {
                clusterLayer.removeLayer(markers[id]);
                delete markers[id];
                delete lastTechsById[id];
            }
        });

        // Focus initial sur un tech ?focus=ID
        if (CFG.focusTechId && markers[CFG.focusTechId]) {
            panToTech(parseInt(CFG.focusTechId, 10));
            CFG.focusTechId = null; // une seule fois
        }
    }

    function tick() {
        if (!CFG.endpoint) return;
        fetch(CFG.endpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (payload) {
            var techs = payload.techs || [];
            updateMarkers(techs);
            renderSidebar(techs);
        }).catch(function (e) { console.warn('[map-live] tick failed', e); });
    }

    function startPoll() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(tick, POLL_MS);
    }
    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function init() {
        if (!$('[data-map-live]')) return;
        // OpenStreetMap tile + base carte centrée Abidjan
        map = L.map('map-live-canvas', { zoomControl: true }).setView(
            CFG.defaultCenter || [5.345, -4.024],
            CFG.defaultZoom || 11
        );
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);

        clusterLayer = L.markerClusterGroup ? L.markerClusterGroup() : L.layerGroup();
        map.addLayer(clusterLayer);

        tick();
        startPoll();

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) stopPoll();
            else { tick(); startPoll(); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
})();

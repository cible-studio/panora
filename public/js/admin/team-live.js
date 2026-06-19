/* SM2b Phase 6 — Vue équipe LIVE (A5). Poll dashboard.live + filtre
   sur les membres de l'équipe. */

(function () {
    var CFG = window.ADMIN_TEAM_LIVE_CONFIG || {};
    var POLL_MS = CFG.pollMs || 20000;
    var pollTimer = null;
    var memberIds = new Set((CFG.memberIds || []).map(function (x) { return parseInt(x, 10); }));

    function $(s, r) { return (r || document).querySelector(s); }

    function tick() {
        if (!CFG.dashboardEndpoint) return;
        fetch(CFG.dashboardEndpoint, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (payload) {
            var teamTechs = (payload.techs_active || []).filter(function (t) { return memberIds.has(t.id); });
            renderStats(teamTechs);
            renderMembers(teamTechs);
        }).catch(function (e) { console.warn('[team-live] tick failed', e); });
    }

    function renderStats(teamTechs) {
        var done = 0, total = 0;
        teamTechs.forEach(function (t) {
            done  += t.progress?.done  || 0;
            total += t.progress?.total || 0;
        });
        setStat('done', done);
        setStat('total', total);
        setStat('online', teamTechs.length);
        // Pose/h moyenne : sur la base "done / heures écoulées depuis 00h"
        var now = new Date();
        var hoursElapsed = (now.getHours() + now.getMinutes() / 60) || 1;
        var rate = done > 0 ? (done / hoursElapsed).toFixed(1).replace('.0', '') : '—';
        setStat('rate', rate);
    }

    function setStat(key, val) {
        var el = document.querySelector('[data-stat="' + key + '"]');
        if (el && el.textContent !== String(val)) {
            el.textContent = String(val);
            el.classList.remove('kpi-bump');
            void el.offsetWidth;
            el.classList.add('kpi-bump');
        }
    }

    function renderMembers(teamTechs) {
        var byId = {};
        teamTechs.forEach(function (t) { byId[t.id] = t; });

        document.querySelectorAll('.team-live-card').forEach(function (card) {
            var mid = parseInt(card.dataset.memberId, 10);
            var tech = byId[mid];
            var statusEl = card.querySelector('[data-field="member-status"]');
            var progressEl = card.querySelector('[data-field="member-progress"]');
            var fillEl = card.querySelector('[data-field="member-progress-fill"]');

            if (!tech) {
                statusEl.textContent = 'Hors ligne';
                progressEl.textContent = '0/0';
                fillEl.style.width = '0%';
                card.classList.add('is-offline');
                return;
            }
            card.classList.remove('is-offline');
            var label =
                tech.current_status === 'sur_place' ? '📍 Sur place'
                : tech.current_status === 'en_route' ? '🚗 En route'
                : tech.current_status === 'inactif'  ? '⏸ En pause'
                : '🟢 En ligne';
            var locExtra = tech.current_pose_label ? (' · ' + tech.current_pose_label) : '';
            statusEl.textContent = label + locExtra;

            var done  = tech.progress?.done  || 0;
            var total = tech.progress?.total || 0;
            progressEl.textContent = done + '/' + total;
            var pct = total > 0 ? Math.round((done / total) * 100) : 0;
            fillEl.style.width = pct + '%';
        });
    }

    function startPoll() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(tick, POLL_MS);
    }
    function stopPoll() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    function init() {
        if (!$('[data-team-live]')) return;
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

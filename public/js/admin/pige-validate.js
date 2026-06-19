/* SM2b Phase 5 — Modale validation photo (A4).
   IIFE, partagée entre A1, A2 et A3. S'ouvre via :
     - bouton [data-action="open-validate-pige"][data-pige-id=X]
     - URL ?validate_pige=X dans la barre d'adresse (deep-link depuis
       le bandeau live event "photo_sent" qui contient l'actionable_url)
   Charge admin.piges.detail-json → remplit modale → tap Valider/Refuser
   appelle admin.piges.verify ou admin.piges.reject. */

(function () {
    var SEL_OVERLAY = '#pige-validate-overlay';
    var CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    var DETAIL_TPL = window.PIGE_VALIDATE_DETAIL_TPL || null;
    var currentPige = null;

    function $(s, r) { return (r || document).querySelector(s); }

    function open(pigeId) {
        var overlay = $(SEL_OVERLAY);
        if (!overlay) return;
        overlay.hidden = false;
        overlay.removeAttribute('aria-hidden');
        requestAnimationFrame(function () { overlay.classList.add('is-open'); });
        document.body.style.overflow = 'hidden';
        setState('loading');
        currentPige = pigeId;
        fetchDetail(pigeId);
    }

    function close() {
        var overlay = $(SEL_OVERLAY);
        if (!overlay) return;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        setTimeout(function () { overlay.hidden = true; }, 200);
        document.body.style.overflow = '';
        currentPige = null;
        // Cleanup ?validate_pige=
        if (location.search.indexOf('validate_pige=') >= 0) {
            try {
                var u = new URL(location.href);
                u.searchParams.delete('validate_pige');
                history.replaceState(null, '', u.toString());
            } catch (e) {}
        }
    }

    function setState(state) {
        ['loading', 'error', 'ok'].forEach(function (s) {
            var el = document.querySelector('[data-field="body-state-' + s + '"]');
            if (el) el.hidden = (s !== state);
        });
    }

    function fetchDetail(pigeId) {
        if (!DETAIL_TPL) { setState('error'); return; }
        var url = DETAIL_TPL.replace('__PIGE__', pigeId);
        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        }).then(function (data) {
            populate(data);
            setState('ok');
        }).catch(function (e) {
            console.warn('[pige-validate] fetch failed', e);
            setState('error');
        });
    }

    function populate(d) {
        var img = $('[data-field="pv-photo"]');
        if (img) img.src = d.photo_url || '';
        var ref = $('[data-field="pv-ref-photo"]');
        if (ref) {
            if (d.ref_photo_url) { ref.src = d.ref_photo_url; ref.parentElement.style.display = ''; }
            else { ref.parentElement.style.display = 'none'; }
        }
        setText('[data-field="pv-panel-ref"]',  d.panel?.reference);
        setText('[data-field="pv-panel-name"]', d.panel?.name);
        setText('[data-field="pv-commune"]',    d.panel?.commune);
        setText('[data-field="pv-tech"]',       d.tech?.name);
        setText('[data-field="pv-campaign"]',   d.campaign?.name);
        setText('[data-field="pv-dist"]',
            d.gps?.dist_m != null
                ? (d.gps.dist_m >= 950 ? (d.gps.dist_m / 1000).toFixed(1).replace('.0', '') + ' km' : d.gps.dist_m + ' m')
                : 'inconnue');
        setText('[data-field="pv-taken"]', d.taken_at ? formatDateTime(d.taken_at) : '—');

        // Reset reject form
        var rf = $('[data-field="reject-form"]');
        if (rf) rf.hidden = true;
        var ta = $('[data-field="reject-reason"]');
        if (ta) ta.value = '';
    }

    function setText(sel, val) {
        var el = $(sel);
        if (el) el.textContent = val != null && val !== '' ? val : '—';
    }

    function formatDateTime(iso) {
        var d = new Date(iso);
        return d.toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function fetchDetailUrlFor(pigeId, key) {
        // Reconstruit verify_url / reject_url depuis le pattern stocké dans
        // le payload détail. Si on n'a pas encore d'objet, on tape directement
        // /admin/piges/{id}/(verify|reject).
        return '/admin/piges/' + pigeId + '/' + key;
    }

    function doValidate() {
        if (!currentPige) return;
        var btn = $('[data-action="do-validate"]');
        if (btn) btn.disabled = true;
        fetch(fetchDetailUrlFor(currentPige, 'verify'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (btn) btn.disabled = false;
              if (res.ok && res.data?.success) {
                  toast('Photo validée ✓', 'success');
                  close();
              } else {
                  toast(res.data?.message || 'Erreur', 'error');
              }
          })
          .catch(function (e) {
              if (btn) btn.disabled = false;
              toast('Erreur réseau', 'error');
          });
    }

    function doReject() {
        if (!currentPige) return;
        var reason = $('[data-field="reject-reason"]').value.trim();
        if (!reason) {
            toast('Précise un motif de refus', 'error');
            return;
        }
        var btn = $('[data-action="do-reject"]');
        if (btn) btn.disabled = true;
        var fd = new FormData();
        fd.append('rejection_reason', reason);
        fetch(fetchDetailUrlFor(currentPige, 'reject'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': CSRF,
            },
            body: fd,
        }).then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
          .then(function (res) {
              if (btn) btn.disabled = false;
              if (res.ok && res.data?.success) {
                  toast('Refus envoyé au tech', 'success');
                  close();
              } else {
                  toast(res.data?.message || 'Erreur', 'error');
              }
          })
          .catch(function () {
              if (btn) btn.disabled = false;
              toast('Erreur réseau', 'error');
          });
    }

    function toast(msg, kind) {
        var t = document.createElement('div');
        t.className = 'pige-validate-toast pige-validate-toast--' + (kind || 'info');
        t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('is-open'); });
        setTimeout(function () {
            t.classList.remove('is-open');
            setTimeout(function () { t.remove(); }, 200);
        }, 2500);
    }

    function init() {
        if (!$(SEL_OVERLAY)) return;

        document.addEventListener('click', function (e) {
            var openBtn = e.target.closest('[data-action="open-validate-pige"]');
            if (openBtn) {
                e.preventDefault();
                open(openBtn.dataset.pigeId);
                return;
            }
            if (e.target.closest('[data-action="close-validate-pige"]')) {
                e.preventDefault(); close(); return;
            }
            if (e.target.closest('[data-action="retry-validate"]') && currentPige) {
                setState('loading'); fetchDetail(currentPige); return;
            }
            if (e.target.closest('[data-action="show-reject"]')) {
                $('[data-field="reject-form"]').hidden = false;
                $('[data-field="reject-reason"]').focus(); return;
            }
            if (e.target.closest('[data-action="cancel-reject"]')) {
                $('[data-field="reject-form"]').hidden = true; return;
            }
            if (e.target.closest('[data-action="do-validate"]')) { doValidate(); return; }
            if (e.target.closest('[data-action="do-reject"]'))   { doReject(); return; }
            var prefixBtn = e.target.closest('[data-reason-prefix]');
            if (prefixBtn) {
                var ta = $('[data-field="reject-reason"]');
                if (ta) {
                    var pref = prefixBtn.dataset.reasonPrefix;
                    ta.value = pref ? (pref + ' ' + ta.value.replace(/^\[[^\]]+\]\s*/, '')) : ta.value.replace(/^\[[^\]]+\]\s*/, '');
                    ta.focus();
                }
                return;
            }
            // Click sur overlay hors-modale → close
            var overlay = e.target.closest(SEL_OVERLAY);
            if (overlay && !e.target.closest('.pige-validate-modal')) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && document.querySelector(SEL_OVERLAY + '.is-open')) close();
        });

        // Deep-link ?validate_pige=ID au load
        var qs = new URLSearchParams(location.search);
        var pid = qs.get('validate_pige');
        if (pid) setTimeout(function () { open(pid); }, 200);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else { init(); }
})();

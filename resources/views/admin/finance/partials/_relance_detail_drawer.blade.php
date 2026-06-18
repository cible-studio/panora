{{-- Drawer latéral "Voir le détail" d'une relance — Bloc 3 Famille D (2026-06-18).
     À @include() sur les pages qui listent des relances avec un bouton 👁
     déclenchant openRelanceDetail(relanceId).

     Le contenu est chargé en AJAX via /admin/finance/relances/{id}/detail
     (méthode FinanceDashboardController::relanceDetail), qui renvoie
     resources/views/admin/finance/partials/relance-detail.blade.php. --}}

<div id="rd-overlay" class="rd-overlay" onclick="closeRelanceDetail()">
    <aside id="rd-drawer" class="rd-drawer" onclick="event.stopPropagation()">
        <header class="rd-drawer-head">
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:18px">📞</span>
                <span style="font-weight:800;font-size:15px;color:var(--text)">Détail de la relance</span>
            </div>
            <button type="button" class="rd-close" onclick="closeRelanceDetail()" title="Fermer (Échap)">✕</button>
        </header>
        <div id="rd-body" class="rd-drawer-body">
            <div class="rd-loading">Chargement…</div>
        </div>
    </aside>
</div>

<style>
.rd-overlay {
    position:fixed; inset:0;
    background:rgba(15,23,42,.45);
    display:none;
    z-index:9998;
    animation:rdFadeIn .15s ease;
}
.rd-overlay.is-open { display:block; }
.rd-drawer {
    position:absolute; top:0; right:0; bottom:0;
    width:min(540px, 95vw);
    background:var(--surface, #fff);
    box-shadow:-12px 0 32px rgba(0,0,0,.18);
    display:flex; flex-direction:column;
    transform:translateX(100%);
    animation:rdSlideIn .25s ease forwards;
    z-index:9999;
}
@keyframes rdFadeIn { from{opacity:0} to{opacity:1} }
@keyframes rdSlideIn { from{transform:translateX(100%)} to{transform:translateX(0)} }
.rd-drawer-head {
    display:flex; align-items:center; justify-content:space-between;
    padding:14px 18px;
    border-bottom:1px solid var(--border);
    background:var(--surface2, #f8fafc);
}
.rd-close {
    background:none; border:1px solid var(--border);
    width:30px; height:30px; border-radius:8px;
    cursor:pointer; font-size:14px; color:var(--text2);
    display:inline-flex; align-items:center; justify-content:center;
    transition:background .12s, color .12s, border-color .12s;
}
.rd-close:hover { background:#fee2e2; color:#b91c1c; border-color:#fca5a5; }
.rd-drawer-body { padding:18px; overflow-y:auto; flex:1; }
.rd-loading { color:var(--text3); font-style:italic; text-align:center; padding:40px 0; }

/* Contenu (depuis relance-detail.blade.php) */
.rd-section { display:flex; flex-direction:column; gap:10px; margin-bottom:18px; padding-bottom:16px; border-bottom:1px solid var(--border); }
.rd-meta { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.rd-meta-label { font-size:11px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.4px; }
.rd-meta-date { font-size:13px; font-weight:700; color:var(--text); }
.rd-outcome { display:inline-block; padding:4px 12px; border-radius:999px; font-size:12px; font-weight:700; align-self:flex-start; }
.rd-grid { display:grid; grid-template-columns:repeat(2, 1fr); gap:12px; margin-bottom:18px; }
.rd-field { display:flex; flex-direction:column; gap:3px; }
.rd-field-label { font-size:10px; font-weight:800; color:var(--text3); text-transform:uppercase; letter-spacing:.4px; }
.rd-field-value { font-size:13px; color:var(--text); font-weight:600; }
.rd-muted { color:var(--text3); }
.rd-link { color:var(--accent); text-decoration:none; font-weight:700; }
.rd-link:hover { text-decoration:underline; }
.rd-block { margin-bottom:16px; padding:12px 14px; background:var(--surface2, #f8fafc); border-radius:10px; border:1px solid var(--border); }
.rd-block-title { font-size:11px; font-weight:800; color:var(--text2); text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; }
.rd-text { font-size:13px; color:var(--text); line-height:1.55; white-space:pre-wrap; word-wrap:break-word; }
</style>

<script>
(function() {
    if (window.__rdDrawerInit) return;
    window.__rdDrawerInit = true;

    const DETAIL_URL = @json(route('admin.finance.relances.detail', ['relance' => '__ID__']));

    window.openRelanceDetail = function(id) {
        const overlay = document.getElementById('rd-overlay');
        const body    = document.getElementById('rd-body');
        if (!overlay || !body) return;
        body.innerHTML = '<div class="rd-loading">Chargement…</div>';
        overlay.classList.add('is-open');
        document.body.style.overflow = 'hidden';

        fetch(DETAIL_URL.replace('__ID__', id), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.text() : Promise.reject(r.status))
            .then(html => { body.innerHTML = html; })
            .catch(err => {
                body.innerHTML = '<div style="color:#b91c1c;padding:20px;text-align:center">Impossible de charger le détail (' + err + ').</div>';
            });
    };

    window.closeRelanceDetail = function() {
        const overlay = document.getElementById('rd-overlay');
        if (!overlay) return;
        overlay.classList.remove('is-open');
        document.body.style.overflow = '';
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeRelanceDetail();
    });
})();
</script>

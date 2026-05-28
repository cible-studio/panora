<x-admin-layout title="Validation rapide des piges">

<x-slot:topbarLeft>
    <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost btn-sm" style="display:inline-flex;align-items:center;gap:6px">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"/>
        </svg>
        Retour
    </a>
    @if(($suspectCount ?? 0) > 0)
        @if(request()->boolean('suspect_first'))
            <a href="{{ route('admin.piges.validation') }}" class="btn btn-ghost btn-sm" style="margin-left:8px">
                ↩ Ordre chronologique
            </a>
        @else
            <a href="{{ route('admin.piges.validation', ['suspect_first' => 1]) }}" class="btn btn-sm" style="margin-left:8px;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.3)">
                ⚠ Voir d'abord les {{ $suspectCount }} douteuse(s)
            </a>
        @endif
    @endif
</x-slot:topbarLeft>

@php
    $total = $piges->count();
@endphp

@if($total === 0)
    <div style="text-align:center;padding:80px 20px;color:var(--text3)">
        <div style="font-size:48px;margin-bottom:14px;opacity:.5">✅</div>
        <div style="font-size:16px;font-weight:700;color:var(--text2);margin-bottom:6px">Aucune pige en attente</div>
        <div style="font-size:13px;margin-bottom:20px;max-width:360px;margin-left:auto;margin-right:auto;line-height:1.5">
            Toutes les piges sont validées. Vous recevrez une alerte dès qu'une nouvelle photo arrive du terrain.
        </div>
        <a href="{{ route('admin.piges.index') }}" class="btn btn-ghost">Retour à la liste</a>
    </div>
@else

<style>
    .valid-wrap { display:grid; grid-template-columns:1fr 360px; gap:18px; height:calc(100vh - 130px); min-height:600px; }
    @media (max-width:1100px) { .valid-wrap { grid-template-columns:1fr; height:auto } }

    .valid-photo {
        background:#0a0c10; border:1px solid var(--border); border-radius:14px;
        overflow:hidden; position:relative; display:flex; align-items:center; justify-content:center;
    }
    .valid-photo img { max-width:100%; max-height:100%; object-fit:contain; }
    .valid-photo-empty { color:#6b7280; font-size:13px; }

    .valid-side {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:18px; display:flex; flex-direction:column; gap:14px; overflow:auto;
    }

    .valid-nav {
        position:absolute; top:50%; transform:translateY(-50%);
        background:rgba(0,0,0,.55); color:#fff; border:none; cursor:pointer;
        width:44px; height:64px; border-radius:8px; font-size:22px;
        display:flex; align-items:center; justify-content:center;
        transition:background .15s, transform .15s;
    }
    .valid-nav:hover { background:rgba(0,0,0,.75); transform:translateY(-50%) scale(1.05); }
    .valid-nav:disabled { opacity:.25; cursor:not-allowed; }
    .valid-nav.prev { left:14px; }
    .valid-nav.next { right:14px; }

    .valid-counter {
        position:absolute; top:14px; left:14px;
        background:rgba(0,0,0,.6); color:#fff; padding:5px 12px; border-radius:20px;
        font-size:13px; font-weight:700; backdrop-filter:blur(4px);
    }

    .valid-kbd {
        display:inline-block; padding:2px 7px;
        background:var(--surface2); border:1px solid var(--border); border-bottom-width:2px;
        border-radius:5px; font-family:ui-monospace,Menlo,Consolas,monospace;
        font-size:11px; font-weight:700; color:var(--text2);
    }

    .valid-action-btn {
        flex:1; padding:12px; border-radius:10px; font-size:14px; font-weight:700;
        cursor:pointer; transition:all .15s; border:1px solid transparent;
        display:flex; align-items:center; justify-content:center; gap:8px;
    }
    .valid-action-btn:hover { transform:translateY(-1px); }
    .valid-btn-approve { background:#22c55e; color:#fff; }
    .valid-btn-approve:hover { background:#16a34a; }
    .valid-btn-reject  { background:rgba(239,68,68,.1); color:#ef4444; border-color:rgba(239,68,68,.3); }
    .valid-btn-reject:hover { background:rgba(239,68,68,.18); }

    .valid-info-row { display:flex; justify-content:space-between; gap:8px; font-size:12px; padding:6px 0; border-bottom:1px solid var(--border); }
    .valid-info-row:last-child { border-bottom:none; }
    .valid-info-row .lbl { color:var(--text3); }
    .valid-info-row .val { color:var(--text); font-weight:600; text-align:right; }

    .valid-modal-overlay {
        position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:1000;
        display:none; align-items:center; justify-content:center; padding:20px;
    }
    .valid-modal-overlay.show { display:flex; }
    .valid-modal {
        background:var(--surface); border:1px solid var(--border); border-radius:14px;
        padding:22px; max-width:440px; width:100%;
    }
    .valid-progress {
        height:4px; background:var(--surface2); border-radius:999px; overflow:hidden;
    }
    .valid-progress-fill { height:100%; background:linear-gradient(90deg,#22c55e,#86efac); transition:width .25s; }
</style>

{{-- Header KPI --}}
<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:14px;flex-wrap:wrap">
    <div style="display:flex;align-items:center;gap:14px">
        <div style="width:42px;height:42px;border-radius:11px;background:rgba(59,130,246,.12);display:flex;align-items:center;justify-content:center">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3b82f6" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </div>
        <div>
            <div style="font-size:18px;font-weight:800;color:var(--text)">Validation rapide</div>
            <div style="font-size:12px;color:var(--text3)">
                <strong id="valid-remaining">{{ $total }}</strong> pige(s) en attente · navigation clavier disponible
            </div>
        </div>
    </div>
    <div style="display:flex;align-items:center;gap:10px;font-size:11px;color:var(--text3)">
        <span><span class="valid-kbd">V</span> Valider</span>
        <span><span class="valid-kbd">R</span> Rejeter</span>
        <span><span class="valid-kbd">←</span><span class="valid-kbd">→</span> Naviguer</span>
    </div>
</div>

{{-- Barre progression --}}
<div class="valid-progress" style="margin-bottom:14px">
    <div id="valid-progress-fill" class="valid-progress-fill" style="width:0%"></div>
</div>

<div class="valid-wrap">
    {{-- ── Zone photo (gauche) ──────────────────────────── --}}
    <div class="valid-photo" id="valid-photo-zone">
        <span class="valid-counter">
            <span id="valid-current-index">1</span> / {{ $total }}
        </span>

        <button type="button" id="valid-prev" class="valid-nav prev" title="Précédente (←)">‹</button>
        <button type="button" id="valid-next" class="valid-nav next" title="Suivante (→)">›</button>

        <img id="valid-image" src="" alt="Pige" style="display:none">
        <div id="valid-empty" class="valid-photo-empty">Chargement…</div>
    </div>

    {{-- ── Panneau infos + actions (droite) ──────────────── --}}
    <div class="valid-side">
        <div>
            <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:var(--text3);letter-spacing:.5px;margin-bottom:8px">
                Pige courante
            </div>
            <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:16px;font-weight:800;color:var(--accent)" id="valid-ref">—</div>
            <div style="font-size:13px;color:var(--text);margin-top:3px" id="valid-name">—</div>
        </div>

        <div style="background:var(--surface2);border-radius:10px;padding:10px 14px">
            <div class="valid-info-row">
                <span class="lbl">Commune</span>
                <span class="val" id="valid-commune">—</span>
            </div>
            <div class="valid-info-row">
                <span class="lbl">Campagne</span>
                <span class="val" id="valid-campaign">—</span>
            </div>
            <div class="valid-info-row">
                <span class="lbl">Technicien</span>
                <span class="val" id="valid-tech">—</span>
            </div>
            <div class="valid-info-row">
                <span class="lbl">Prise le</span>
                <span class="val" id="valid-taken">—</span>
            </div>
            <div class="valid-info-row">
                <span class="lbl">GPS photo</span>
                <span class="val" id="valid-gps">—</span>
            </div>
        </div>

        {{-- Actions principales --}}
        <div style="display:flex;gap:8px">
            <button type="button" id="valid-btn-reject" class="valid-action-btn valid-btn-reject">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Rejeter <span class="valid-kbd" style="margin-left:4px">R</span>
            </button>
            <button type="button" id="valid-btn-approve" class="valid-action-btn valid-btn-approve">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Valider <span class="valid-kbd" style="margin-left:4px;background:rgba(255,255,255,.18);color:#fff;border-color:rgba(255,255,255,.3)">V</span>
            </button>
        </div>

        <a id="valid-detail-link" href="#" target="_blank"
           style="text-align:center;font-size:12px;color:var(--text3);text-decoration:none;padding:6px;display:block">
            Voir la fiche complète →
        </a>
    </div>
</div>

{{-- Modal motif rejet --}}
<div class="valid-modal-overlay" id="valid-reject-modal">
    <div class="valid-modal">
        <div style="font-size:15px;font-weight:700;color:var(--text);margin-bottom:6px">Motif du rejet</div>
        <div style="font-size:12px;color:var(--text3);margin-bottom:14px">Le technicien recevra ce motif et un nouveau lien WhatsApp pour reprendre la pige.</div>

        <select id="valid-reject-reason" class="filter-select" style="width:100%;margin-bottom:10px">
            <option value="flou">📷 Photo floue / illisible</option>
            <option value="mauvais_panneau">🪧 Mauvais panneau</option>
            <option value="cadrage">🔲 Mauvais cadrage</option>
            <option value="luminosite">💡 Luminosité insuffisante</option>
            <option value="vandalisme">⚠ Vandalisme visible</option>
            <option value="autre">📝 Autre raison</option>
        </select>

        <textarea id="valid-reject-notes" class="filter-input" rows="2" placeholder="Précisions (optionnel)"
                  style="width:100%;resize:vertical;margin-bottom:14px"></textarea>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" id="valid-cancel-reject" class="btn btn-ghost btn-sm">Annuler</button>
            <button type="button" id="valid-confirm-reject" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none">
                ❌ Confirmer le rejet
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const PIGES = @json($piges->map(fn($p) => [
        'id'         => $p->id,
        'photo_url'  => $p->photo_path ? asset('storage/' . $p->photo_path) : null,
        'reference'  => $p->panel?->reference,
        'name'       => $p->panel?->name,
        'commune'    => $p->panel?->commune?->name,
        'campaign'   => $p->campaign?->name,
        'tech'       => $p->technicien?->name,
        'taken'      => $p->taken_at?->format('d/m/Y H:i') ?? $p->created_at->format('d/m/Y H:i'),
        'gps_lat'    => $p->gps_lat,
        'gps_lng'    => $p->gps_lng,
        'panel_lat'  => $p->panel?->latitude,
        'panel_lng'  => $p->panel?->longitude,
        'geo_dist'   => $p->geo_distance_m,
        'geo_badge'  => $p->geoBadge(),
        'detail_url' => route('admin.piges.show', $p),
        'verify_url' => route('admin.piges.verify', $p),
        'reject_url' => route('admin.piges.reject', $p),
    ]));
    const CSRF = '{{ csrf_token() }}';

    let currentIndex = 0;
    let processing = false;

    const $img      = document.getElementById('valid-image');
    const $empty    = document.getElementById('valid-empty');
    const $ref      = document.getElementById('valid-ref');
    const $name     = document.getElementById('valid-name');
    const $commune  = document.getElementById('valid-commune');
    const $campaign = document.getElementById('valid-campaign');
    const $tech     = document.getElementById('valid-tech');
    const $taken    = document.getElementById('valid-taken');
    const $gps      = document.getElementById('valid-gps');
    const $idx      = document.getElementById('valid-current-index');
    const $remain   = document.getElementById('valid-remaining');
    const $progress = document.getElementById('valid-progress-fill');
    const $prev     = document.getElementById('valid-prev');
    const $next     = document.getElementById('valid-next');
    const $approve  = document.getElementById('valid-btn-approve');
    const $reject   = document.getElementById('valid-btn-reject');
    const $detail   = document.getElementById('valid-detail-link');
    const $modal    = document.getElementById('valid-reject-modal');

    function render() {
        const p = PIGES[currentIndex];
        if (!p) {
            $empty.textContent = 'Plus de pige à valider — vous pouvez fermer cette page.';
            $img.style.display = 'none';
            $empty.style.display = 'block';
            $ref.textContent = '—';
            $name.textContent = 'Toutes traitées';
            return;
        }
        // Photo
        if (p.photo_url) {
            $img.src = p.photo_url;
            $img.style.display = 'block';
            $empty.style.display = 'none';
        } else {
            $img.style.display = 'none';
            $empty.style.display = 'block';
            $empty.textContent = 'Photo introuvable';
        }
        $ref.textContent      = p.reference ?? '—';
        $name.textContent     = p.name ?? '—';
        $commune.textContent  = p.commune ?? '—';
        $campaign.textContent = p.campaign ?? '—';
        $tech.textContent     = p.tech ?? '— Non assigné —';
        $taken.textContent    = p.taken ?? '—';

        // Cohérence GPS — verdict serveur anti-fraude (geo_check), avec
        // distance pige↔panneau quand elle est connue.
        const b = p.geo_badge;
        if (b) {
            const distTxt = (p.geo_dist !== null && p.geo_dist !== undefined) ? ` · ${p.geo_dist} m` : '';
            $gps.innerHTML = `<span style="display:inline-flex;align-items:center;gap:4px;font-weight:600;padding:2px 8px;border-radius:999px;color:${b.color};background:${b.bg}">${b.icon} ${b.label}${distTxt}</span>`;
        } else {
            $gps.textContent = '—';
        }

        $idx.textContent = currentIndex + 1;
        $detail.href = p.detail_url;
        $prev.disabled = currentIndex === 0;
        $next.disabled = currentIndex >= PIGES.length - 1;

        // Progression : nb traitées / total initial
        const totalInit = {{ $total }};
        const done = totalInit - PIGES.length + currentIndex;
        $progress.style.width = totalInit > 0 ? Math.round((done / totalInit) * 100) + '%' : '0%';
        $remain.textContent = PIGES.length;
    }

    async function callAction(url, body = null) {
        if (processing) return;
        processing = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': CSRF,
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
                body: body ? JSON.stringify(body) : null,
            });
            return res.ok ? await res.json() : null;
        } finally {
            processing = false;
        }
    }

    async function approve() {
        const p = PIGES[currentIndex];
        if (!p) return;
        const res = await callAction(p.verify_url);
        if (res) {
            // Retirer de la liste, ne pas incrémenter l'index (la suivante prend la place)
            PIGES.splice(currentIndex, 1);
            if (currentIndex >= PIGES.length) currentIndex = Math.max(0, PIGES.length - 1);
            render();
        }
    }

    function openRejectModal() {
        if (!PIGES[currentIndex]) return;
        $modal.classList.add('show');
        document.getElementById('valid-reject-reason').focus();
    }
    function closeRejectModal() { $modal.classList.remove('show'); }

    async function confirmReject() {
        const p = PIGES[currentIndex];
        if (!p) return;
        const reason = document.getElementById('valid-reject-reason').value;
        const notes  = document.getElementById('valid-reject-notes').value.trim();
        const res = await callAction(p.reject_url, { reason, notes });
        if (res) {
            PIGES.splice(currentIndex, 1);
            if (currentIndex >= PIGES.length) currentIndex = Math.max(0, PIGES.length - 1);
            closeRejectModal();
            document.getElementById('valid-reject-notes').value = '';
            render();
        }
    }

    $prev.addEventListener('click', () => { if (currentIndex > 0) { currentIndex--; render(); } });
    $next.addEventListener('click', () => { if (currentIndex < PIGES.length - 1) { currentIndex++; render(); } });
    $approve.addEventListener('click', approve);
    $reject.addEventListener('click', openRejectModal);
    document.getElementById('valid-cancel-reject').addEventListener('click', closeRejectModal);
    document.getElementById('valid-confirm-reject').addEventListener('click', confirmReject);

    // Raccourcis clavier (désactivés si modal ouvert ou input focused)
    document.addEventListener('keydown', (e) => {
        if ($modal.classList.contains('show')) {
            if (e.key === 'Escape') closeRejectModal();
            return;
        }
        // Ignorer si on tape dans un input/textarea
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

        if (e.key === 'ArrowLeft')  { e.preventDefault(); if (currentIndex > 0) { currentIndex--; render(); } }
        else if (e.key === 'ArrowRight') { e.preventDefault(); if (currentIndex < PIGES.length - 1) { currentIndex++; render(); } }
        else if (e.key === 'v' || e.key === 'V') { e.preventDefault(); approve(); }
        else if (e.key === 'r' || e.key === 'R') { e.preventDefault(); openRejectModal(); }
    });

    render();
})();
</script>

@endif

</x-admin-layout>

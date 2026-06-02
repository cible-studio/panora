<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Mes piges — {{ $tech->name }} · Panora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #e8a020; --accent-dark: #c2570d;
            --bg: #f8f9fb; --surface: #ffffff; --surface2: #f4f5f7;
            --border: #e5e7eb;
            --text: #111827; --text2: #4b5563; --text3: #9ca3af;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg); color: var(--text); font-size: 14px;
            -webkit-font-smoothing: antialiased;
        }

        .header {
            position: sticky; top: 0; z-index: 50;
            background: linear-gradient(180deg, #fff 0%, #fffaf0 100%);
            padding: 14px 16px 12px;
            padding-top: max(14px, env(safe-area-inset-top));
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 18px -8px rgba(232,160,32,.18);
        }
        .header-top {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 10px;
        }
        .back-btn {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 6px 11px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 12px; font-weight: 700;
            text-decoration: none;
        }
        .back-btn:active { transform: scale(.97); }
        .header h1 {
            font-size: 17px; font-weight: 800; margin: 0;
            color: var(--text); letter-spacing: -0.2px;
        }
        .kpi-row {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;
            margin-top: 10px;
        }
        .kpi-tile {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 10px; padding: 8px 9px; text-align: center;
            position: relative; overflow: hidden;
            text-decoration: none; color: inherit; cursor: pointer;
            transition: transform .15s, border-color .15s, box-shadow .15s, background .15s;
        }
        .kpi-tile:hover {
            border-color: color-mix(in srgb, var(--tile-clr, var(--accent)) 60%, transparent);
            box-shadow: 0 4px 14px -6px color-mix(in srgb, var(--tile-clr, var(--accent)) 50%, transparent);
        }
        .kpi-tile:active { transform: scale(.97); }
        .kpi-tile.is-active {
            background: color-mix(in srgb, var(--tile-clr, var(--accent)) 10%, var(--surface));
            border-color: var(--tile-clr, var(--accent));
            box-shadow: 0 6px 18px -8px color-mix(in srgb, var(--tile-clr, var(--accent)) 60%, transparent);
        }
        .kpi-tile .kt-value {
            font-size: 17px; font-weight: 800;
            font-family: ui-monospace, monospace;
            transition: transform .25s cubic-bezier(.16,1,.3,1);
        }
        .kpi-tile .kt-value.kt-bump { animation: ktBump .55s cubic-bezier(.16,1,.3,1); }
        @keyframes ktBump {
            0%   { transform: scale(1); }
            30%  { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
        .kpi-tile .kt-label {
            font-size: 9.5px; font-weight: 700; letter-spacing: .4px;
            text-transform: uppercase; color: var(--text3); margin-top: 1px;
        }
        .kpi-tile.kt-total    { --tile-clr: #6b7280; }
        .kpi-tile.kt-pending  { --tile-clr: #f59e0b; }
        .kpi-tile.kt-verified { --tile-clr: #22c55e; }
        .kpi-tile.kt-rejected { --tile-clr: #ef4444; }
        .kpi-tile.kt-total    .kt-value { color: var(--text); }
        .kpi-tile.kt-pending  .kt-value { color: #f59e0b; }
        .kpi-tile.kt-verified .kt-value { color: #22c55e; }
        .kpi-tile.kt-rejected .kt-value { color: #ef4444; }

        /* Live indicator dans le header */
        .live-indicator {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 9.5px; font-weight: 700;
            color: #22c55e; letter-spacing: .4px; text-transform: uppercase;
            opacity: 0; transition: opacity .4s; margin-left: 8px;
        }
        .live-indicator.is-pulsing { opacity: 1; }
        .live-indicator::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: #22c55e;
        }

        .container { padding: 14px; max-width: 600px; margin: 0 auto; }

        .pige-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 14px; margin-bottom: 10px; overflow: hidden;
            display: flex; gap: 0;
            transition: transform .15s, border-color .15s;
        }
        .pige-thumb {
            flex: 0 0 110px; height: 110px;
            background: var(--surface2) center/cover no-repeat;
            position: relative; cursor: zoom-in;
        }
        .pige-status-pill {
            position: absolute; top: 6px; left: 6px;
            padding: 3px 8px; border-radius: 999px;
            font-size: 10px; font-weight: 800; letter-spacing: .3px;
            text-transform: uppercase;
            backdrop-filter: blur(3px);
        }
        .pige-status-pill.s-pending  { background: rgba(245,158,11,.92); color: #fff; }
        .pige-status-pill.s-verified { background: rgba(34,197,94,.92); color: #fff; }
        .pige-status-pill.s-rejected { background: rgba(239,68,68,.92); color: #fff; }
        .pige-body {
            flex: 1; min-width: 0; padding: 10px 12px;
            display: flex; flex-direction: column; gap: 4px;
        }
        .pige-ref {
            font-family: ui-monospace, monospace; font-size: 13px;
            font-weight: 800; color: var(--accent-dark);
        }
        .pige-meta {
            font-size: 12px; color: var(--text2);
            display: flex; flex-direction: column; gap: 1px;
            line-height: 1.4;
        }
        .pige-meta .when {
            font-size: 11px; color: var(--text3);
        }
        .pige-rejected-msg {
            margin-top: 6px; padding: 8px 10px;
            background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.20);
            border-radius: 8px; font-size: 12px; color: #b91c1c;
            line-height: 1.4;
        }
        .pige-rejected-msg strong { display:block; margin-bottom: 2px; }

        .empty {
            background: var(--surface); border: 1px dashed var(--border);
            border-radius: 14px; padding: 50px 20px;
            text-align: center; color: var(--text3);
        }
        .empty .icon { font-size: 44px; margin-bottom: 10px; opacity: .35; }
        .empty h2 { color: var(--text2); margin: 0 0 6px; font-size: 15px; }

        .pagination {
            display: flex; justify-content: center; gap: 6px;
            margin-top: 16px; flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 6px 11px; border-radius: 8px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); text-decoration: none; font-size: 12px;
        }
        .pagination .active, .pagination span[aria-current] {
            background: var(--accent); color: #fff; border-color: var(--accent);
            font-weight: 800;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <a href="{{ route('tech.space', $token) }}" class="back-btn">← Retour</a>
        <h1 style="flex:1">📸 Mes piges</h1>
        <span class="live-indicator" data-live-indicator>live</span>
    </div>
    {{-- KPI tiles cliquables comme filtres (server-side ?status=) +
         polling 20s anime les valeurs au passage de chaque tick. --}}
    <div class="kpi-row" role="group" aria-label="Filtres piges">
        <a href="{{ route('tech.space.piges', $token) }}"
           class="kpi-tile kt-total {{ !$statusFilter ? 'is-active' : '' }}"
           aria-pressed="{{ !$statusFilter ? 'true' : 'false' }}">
            <div class="kt-value" data-kpi-value="pigesTotal">{{ $kpi['total'] }}</div>
            <div class="kt-label">Total</div>
        </a>
        <a href="{{ route('tech.space.piges', [$token, 'status' => 'en_attente']) }}"
           class="kpi-tile kt-pending {{ $statusFilter === 'en_attente' ? 'is-active' : '' }}"
           aria-pressed="{{ $statusFilter === 'en_attente' ? 'true' : 'false' }}">
            <div class="kt-value" data-kpi-value="pigesPending">{{ $kpi['pending'] }}</div>
            <div class="kt-label">En attente</div>
        </a>
        <a href="{{ route('tech.space.piges', [$token, 'status' => 'verifie']) }}"
           class="kpi-tile kt-verified {{ $statusFilter === 'verifie' ? 'is-active' : '' }}"
           aria-pressed="{{ $statusFilter === 'verifie' ? 'true' : 'false' }}">
            <div class="kt-value" data-kpi-value="pigesVerified">{{ $kpi['verified'] }}</div>
            <div class="kt-label">Validées</div>
        </a>
        <a href="{{ route('tech.space.piges', [$token, 'status' => 'rejete']) }}"
           class="kpi-tile kt-rejected {{ $statusFilter === 'rejete' ? 'is-active' : '' }}"
           aria-pressed="{{ $statusFilter === 'rejete' ? 'true' : 'false' }}">
            <div class="kt-value" data-kpi-value="pigesRejected">{{ $kpi['rejected'] }}</div>
            <div class="kt-label">Refusées</div>
        </a>
    </div>
</div>

<div class="container">
    @forelse($piges as $p)
        @php
            $statusCfg = match($p->status) {
                'en_attente' => ['cls' => 's-pending', 'label' => '⏳ En attente'],
                'verifie'    => ['cls' => 's-verified','label' => '✓ Validée'],
                'rejete'     => ['cls' => 's-rejected','label' => '✕ Refusée'],
                default      => ['cls' => '', 'label' => $p->status],
            };
            $photoUrl = $p->photo_path
                ? \Illuminate\Support\Facades\Storage::url($p->photo_path)
                : null;
        @endphp
        <div class="pige-card">
            @if($photoUrl)
                <a class="pige-thumb"
                   style="background-image:url('{{ $photoUrl }}')"
                   href="{{ $photoUrl }}" target="_blank" rel="noopener">
                    <span class="pige-status-pill {{ $statusCfg['cls'] }}">
                        {{ $statusCfg['label'] }}
                    </span>
                </a>
            @else
                <div class="pige-thumb" style="display:flex;align-items:center;justify-content:center;font-size:28px;color:var(--text3)">
                    📷
                    <span class="pige-status-pill {{ $statusCfg['cls'] }}">
                        {{ $statusCfg['label'] }}
                    </span>
                </div>
            @endif
            <div class="pige-body">
                <div class="pige-ref">{{ $p->panel?->reference ?? '—' }}</div>
                <div class="pige-meta">
                    @if($p->panel?->name)
                        <span>{{ $p->panel->name }}</span>
                    @endif
                    @if($p->panel?->commune?->name)
                        <span>📍 {{ $p->panel->commune->name }}</span>
                    @endif
                    @if($p->campaign?->name)
                        <span>📢 {{ $p->campaign->name }}</span>
                    @endif
                    <span class="when">
                        {{ $p->taken_at?->format('d/m/Y H:i') }}
                        @if($p->verified_at && $p->status !== 'en_attente')
                            · {{ $p->status === 'verifie' ? 'validée' : 'refusée' }}
                            le {{ $p->verified_at->format('d/m H:i') }}
                        @endif
                    </span>
                </div>

                @if($p->status === 'rejete' && $p->rejection_reason)
                    <div class="pige-rejected-msg">
                        <strong>Motif du refus :</strong>
                        {{ $p->rejection_reason }}
                        <div style="margin-top:4px;font-size:11px;color:#7f1d1d">
                            Retourne à <a href="{{ route('tech.space', $token) }}" style="color:#7f1d1d;font-weight:700">Mes poses</a> et reprends une photo conforme.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="empty">
            <div class="icon">📷</div>
            <h2>Aucune pige envoyée</h2>
            <p style="font-size:12.5px;margin:0">Tes photos de pose apparaîtront ici dès la première envoyée.</p>
        </div>
    @endforelse

    @if($piges->hasPages())
        <div class="pagination">
            {{ $piges->links() }}
        </div>
    @endif
</div>

<script>
// Polling heartbeat 20s — met à jour les KPI tiles sans reload.
// Pas de reload de la liste : si tu cliques un filtre, la page se
// recharge (server-side filter), donc le KPI live anime seulement
// le compteur en haut, pas le contenu en bas.
(function () {
    const URL = "{{ route('tech.space.heartbeat', $token) }}";
    const liveDot = document.querySelector('[data-live-indicator]');

    function bumpVal(name, newVal) {
        const el = document.querySelector(`[data-kpi-value="${name}"]`);
        if (!el) return;
        const oldVal = parseInt(el.textContent.trim(), 10) || 0;
        if (oldVal === newVal) return;
        el.textContent = newVal;
        el.classList.remove('kt-bump');
        void el.offsetWidth;
        el.classList.add('kt-bump');
    }

    async function tick() {
        try {
            const r = await fetch(URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;
            if (liveDot) {
                liveDot.classList.add('is-pulsing');
                setTimeout(() => liveDot.classList.remove('is-pulsing'), 600);
            }
            bumpVal('pigesTotal',    d.pigesTotal);
            bumpVal('pigesPending',  d.pigesPending);
            bumpVal('pigesVerified', d.pigesVerified);
            bumpVal('pigesRejected', d.pigesRejected);
        } catch (e) { /* silencieux */ }
    }
    setTimeout(tick, 1500);
    setInterval(tick, 20000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) tick();
    });
})();
</script>

</body>
</html>

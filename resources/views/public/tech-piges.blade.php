<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Mes photos — {{ $tech->name }} · Panora</title>

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

        /* Toggle Statut actuel / Historique complet */
        .view-toggle {
            display: flex; gap: 6px; margin-top: 10px;
            flex-wrap: wrap;
        }
        .vt-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 11.5px; font-weight: 700;
            text-decoration: none; cursor: pointer; font-family: inherit;
            transition: all .15s;
        }
        .vt-chip:active { transform: scale(.96); }
        .vt-chip.is-active {
            background: var(--accent); color: #fff; border-color: var(--accent);
            box-shadow: 0 4px 12px -4px rgba(232,160,32,.45);
        }
        .vt-chip .vt-badge {
            background: rgba(0,0,0,.10); padding: 1px 6px; border-radius: 999px;
            font-size: 10px; font-weight: 800;
            font-family: ui-monospace, monospace;
        }
        .vt-chip.is-active .vt-badge { background: rgba(255,255,255,.25); }

        /* Pige obsolète (mode Historique) : opacité réduite + badge "ancienne" */
        .pige-card.is-superseded {
            opacity: .65;
            border-style: dashed;
        }
        .pige-card.is-superseded .pige-thumb {
            filter: grayscale(.4);
        }
        .pige-superseded-badge {
            display: inline-block; margin-top: 4px;
            padding: 2px 7px; border-radius: 999px;
            background: rgba(107,114,128,.12); color: #4b5563;
            font-size: 9.5px; font-weight: 800;
            letter-spacing: .3px; text-transform: uppercase;
        }

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
        /* La vue public.tech.partials._pagination encapsule les liens dans un <nav>
           (accessibilité) — c'est lui qui porte la mise en ligne. */
        .pagination nav {
            display: flex; justify-content: center; gap: 6px;
            flex-wrap: wrap; width: 100%;
        }
        .pagination a, .pagination span {
            padding: 6px 11px; border-radius: 8px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); text-decoration: none; font-size: 12px;
            line-height: 1.2; min-width: 34px; text-align: center;
        }
        .pagination a:active { background: var(--surface2); }
        .pagination .active, .pagination span[aria-current] {
            background: var(--accent); color: #fff; border-color: var(--accent);
            font-weight: 800;
        }
        /* Garde-fou : si une vue de pagination tierce réintroduit des SVG
           (thème Tailwind par défaut), ils restent à taille raisonnable
           au lieu de s'afficher en pleine page. */
        .pagination svg { width: 14px; height: 14px; vertical-align: middle; }
    </style>
    {{-- Refonte 2026-11-12 (maquette validée) : galerie, en-tête, barre du bas. --}}
    <link rel="stylesheet" href="{{ \App\Support\Actif::url('css/tech/tx-refonte.css') }}">
</head>
<body class="tx tx-photos">

{{-- Refonte, étape 3 (2026-11-12, maquette validée) : « Mes photos » en
     galerie, la barre du bas, et « Refaire » qui mène à la photo à refaire.
     Les règles ne changent pas : la dernière photo par panneau par défaut
     (?view=all : tout l'historique), filtres ?status=, compteurs du même
     mode que la liste (TechSpaceController::piges). --}}
@php
    $vp = $view === 'all' ? ['view' => 'all'] : [];
    $currentLinkArgs = $statusFilter ? [$token, 'status' => $statusFilter] : [$token];
    $allLinkArgs = array_merge($currentLinkArgs, ['view' => 'all']);
    $hiddenInCurrent = max(0, $historyTotal - $kpi['total']);

    // Photos remplacées par une plus récente (mode « tout l'historique ») :
    // une seule requête pour la page.
    // Règle unique : Pige::scopeDerniereParPanneau (un axe entier garde
    // toutes ses photos, une par pied).
    if ($view === 'all' && $piges->isNotEmpty()) {
        $courantes = \App\Models\Pige::query()
            ->whereIn('id', $piges->pluck('id'))
            ->derniereParPanneau()
            ->pluck('id')
            ->flip();
        foreach ($piges as $_p) {
            $_p->is_superseded = !$courantes->has($_p->id);
        }
    }

    $quand = function ($d) {
        if (!$d) return '—';
        $d = \Carbon\Carbon::parse($d);
        return ($d->isToday() ? 'Aujourd’hui' : ($d->isYesterday() ? 'Hier' : $d->format('d/m'))) . ' ' . $d->format('H\hi');
    };
    $filtres = [
        ['statut' => null,         'cle' => 'pigesTotal',    'nb' => $kpi['total'],    'libelle' => 'Toutes',    'cls' => ''],
        ['statut' => 'en_attente', 'cle' => 'pigesPending',  'nb' => $kpi['pending'],  'libelle' => 'À valider', 'cls' => 'tx-chip-att'],
        ['statut' => 'verifie',    'cle' => 'pigesVerified', 'nb' => $kpi['verified'], 'libelle' => 'Validées',  'cls' => 'tx-chip-ok'],
        ['statut' => 'rejete',     'cle' => 'pigesRejected', 'nb' => $kpi['rejected'], 'libelle' => 'Refusées',  'cls' => 'tx-chip-refus'],
    ];
@endphp

<header class="tx-bandeau">
    <div class="tx-bandeau-t">
        <h1>Mes photos</h1>
        <small>
            @if($view === 'current')
                La dernière par panneau
                @if($hiddenInCurrent > 0) · <a href="{{ route('tech.space.piges', $allLinkArgs) }}">tout l'historique</a>@endif
            @else
                Tout l'historique · <a href="{{ route('tech.space.piges', $currentLinkArgs) }}">la dernière par panneau</a>
            @endif
        </small>
    </div>
    <span class="live-indicator" data-live-indicator>live</span>
</header>

<main class="tx-photos-corps">
    <nav class="tx-chips" aria-label="Trier mes photos">
        @foreach($filtres as $f)
            @php $on = $statusFilter === $f['statut']; @endphp
            <a href="{{ route('tech.space.piges', array_merge($f['statut'] ? [$token, 'status' => $f['statut']] : [$token], $vp)) }}"
               class="tx-chip {{ $f['cls'] }} {{ $on ? 'on' : '' }}" aria-pressed="{{ $on ? 'true' : 'false' }}">
                <b data-kpi-value="{{ $f['cle'] }}">{{ $f['nb'] }}</b><span>{{ $f['libelle'] }}</span>
            </a>
        @endforeach
    </nav>

    @if($view === 'current' && $hiddenInCurrent > 0)
        <p class="tx-photos-info">
            ⓘ {{ $hiddenInCurrent }} photo{{ $hiddenInCurrent > 1 ? 's' : '' }} plus ancienne{{ $hiddenInCurrent > 1 ? 's' : '' }}
            masquée{{ $hiddenInCurrent > 1 ? 's' : '' }} : remplacée{{ $hiddenInCurrent > 1 ? 's' : '' }} par une nouvelle photo
            du même panneau. <a href="{{ route('tech.space.piges', $allLinkArgs) }}">Tout l'historique</a>
        </p>
    @endif

    @if($piges->isEmpty())
        <div class="tx-photos-vide">
            <div style="font-size:40px;opacity:.35">📷</div>
            <strong>Pas encore de photo</strong>
            <p style="margin:4px 0 0;font-size:13px">Dès que tu envoies ta première photo, elle apparaît ici.</p>
        </div>
    @else
        <div class="tx-galerie">
            @foreach($piges as $p)
                @php
                    [$pillCls, $pillTxt] = match($p->status) {
                        'en_attente' => ['tx-pill-attente', 'À valider'],
                        'verifie'    => ['tx-pill-fait', 'Validée'],
                        'rejete'     => ['tx-pill-retard', 'Refusée'],
                        default      => ['tx-pill-planifiee', (string) $p->status],
                    };
                    $vignette = $p->photo_path ? \Illuminate\Support\Facades\Storage::url($p->photo_thumb ?: $p->photo_path) : null;
                    $grande = $p->photo_path ? \Illuminate\Support\Facades\Storage::url($p->photo_path) : null;
                    $ancienne = (bool) ($p->is_superseded ?? false);
                @endphp
                <article class="tx-cliche {{ $ancienne ? 'tx-cliche-ancienne' : '' }}"
                         @if($ancienne) title="Tu as refait une photo de ce panneau après celle-ci." @endif>
                    @if($grande)
                        <a class="tx-cliche-img" href="{{ $grande }}" target="_blank" rel="noopener"
                           style="background-image:url('{{ $vignette }}')" aria-label="Voir la photo de {{ $p->panel?->reference }} en grand">
                            <span class="tx-pill {{ $pillCls }}">{{ $pillTxt }}</span>
                        </a>
                    @else
                        <div class="tx-cliche-img"><span class="tx-pill {{ $pillCls }}">{{ $pillTxt }}</span></div>
                    @endif
                    <div class="tx-cliche-txt">
                        <span class="tx-ref">{{ $p->panel?->reference ?? '—' }}</span>
                        <span>{{ $quand($p->taken_at) }}{{ $ancienne ? ' · ↻ refaite après' : '' }}</span>
                        @if($p->panel?->name)<span class="tx-cliche-nom">{{ $p->panel->name }}</span>@endif
                        @if($p->campaign?->name)<span class="tx-cliche-nom">{{ $p->campaign->name }}</span>@endif
                        @if($p->status === 'rejete' && $p->rejection_reason)
                            <span class="tx-cliche-motif">{{ $p->rejection_reason }}</span>
                        @endif
                        @if($p->status === 'rejete' && !$ancienne)
                            {{-- « Photos à refaire » de Ma journée (t9-rejected.js) rouvre
                                 l'appareil photo sur la bonne pose. --}}
                            <a class="tx-mini tx-mini-refaire" href="{{ route('tech.space', ['token' => $token, 'refaire' => 1]) }}">Refaire</a>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        @if($piges->hasPages())
            {{-- Vue dédiée : cette page n'a pas Tailwind (cf. _pagination). --}}
            <div class="pagination">
                {{ $piges->links('public.tech.partials._pagination') }}
            </div>
        @endif
    @endif
</main>

@include('public.tech.partials._tx_nav', ['token' => $token, 'actif' => 'photos', 'tech' => $tech])

<script>
// Polling heartbeat 20s — met à jour les KPI tiles sans reload.
// Pas de reload de la liste : si tu cliques un filtre, la page se
// recharge (server-side filter), donc le KPI live anime seulement
// le compteur en haut, pas le contenu en bas.
(function () {
    const URL = "{{ route('tech.space.heartbeat', $token) }}";
    const VUE_COURANTE = @json($view === 'current');
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
            // 2026-11-12 : le heartbeat compte « la dernière par panneau ».
            // En mode « tout l'historique », il remplaçait les compteurs de
            // la page par d'autres chiffres. « Refusées » : le heartbeat ne
            // garde que les campagnes en cours (bandeau de Ma journée), la
            // page les compte toutes — on laisse celui de la page.
            if (!VUE_COURANTE) return;
            bumpVal('pigesTotal',    d.pigesTotal);
            bumpVal('pigesPending',  d.pigesPending);
            bumpVal('pigesVerified', d.pigesVerified);
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

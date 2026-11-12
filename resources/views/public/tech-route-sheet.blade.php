{{-- Feuille de route papier du technicien — refonte du 2026-11-12 (style de
     « Ma journée »). Lisible sur téléphone (plus de tableau à 7 colonnes qui
     débordait) et imprimable en A4 : une fiche par pose, avec sa case à
     cocher ; les pieds d'un axe regroupés sous l'axe (AxeService::grouperParAxe),
     chacun avec sa case. Mêmes données qu'avant (TechSpaceController::routeSheet).

     Paramètres : $tech, $token, $total, $groupedByCommune, $isLate. --}}
@php
    $axes = app(\App\Services\Axes\AxeService::class);
    $maintenant = now()->locale('fr');
    $toutes = $groupedByCommune->flatten(1);
    $nbRetard = $toutes->filter(fn ($t) => $isLate($t))->count();
    $nbCommunes = $groupedByCommune->count();
    // Un pied sans position prend celle de son axe (Panel::positionTerrain).
    $gps = function ($panel) {
        $pos = $panel?->positionTerrain();
        return $pos ? number_format($pos['lat'], 5, '.', '') . ', ' . number_format($pos['lng'], 5, '.', '') : null;
    };
    $statut = function ($task) {
        $s = $task->status instanceof \App\Enums\PoseTaskStatus ? $task->status : \App\Enums\PoseTaskStatus::tryFrom((string) $task->status);
        return [$s?->value ?? (string) $task->status, $s?->label() ?? ucfirst((string) $task->status)];
    };
    $quand = fn ($d) => $d ? \Carbon\Carbon::parse($d)->locale('fr')->isoFormat('ddd D MMM, HH[h]mm') : null;
    $entreesParCommune = $groupedByCommune->map(
        fn ($tasks) => $axes->grouperParAxe($tasks, fn ($t) => $t->panel, fn ($t) => $t->campaign_id)
    );
    $nbAxes = $entreesParCommune->flatten(1)->filter(fn ($e) => $e['axe'])->count();
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feuille de route — {{ $tech->name }} · Panora</title>
    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="shortcut icon" href="{{ asset('images/faviconl.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: #e8a020; --accent-dark: #c2570d; --encre: #0f172a;
            --texte2: #475467; --texte3: #8a94a6; --bord: #e4e7ec; --fond: #f7f8fa;
            --ko: #dc2626; --ko-bg: #fef2f2; --route: #7c3aed; --place: #2563eb;
        }
        @page { size: A4 portrait; margin: 12mm 11mm; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; background: var(--fond); color: var(--encre);
            font-family: 'Inter', -apple-system, 'Segoe UI', sans-serif; font-size: 14px; line-height: 1.4; }

        /* Barre d'écran (pas imprimée) */
        .barre { position: sticky; top: 0; z-index: 10; display: flex; gap: 8px; align-items: center;
            padding: 10px 16px; padding-top: max(10px, env(safe-area-inset-top));
            background: #fff; border-bottom: 1px solid var(--bord); }
        .barre a, .barre button { display: inline-flex; align-items: center; gap: 6px; padding: 10px 14px; border-radius: 12px;
            font: 700 14px/1 inherit; text-decoration: none; cursor: pointer; border: 1px solid var(--bord); background: #fff; color: var(--encre); }
        .barre .imprimer { margin-left: auto; background: var(--accent); border-color: var(--accent); color: #fff; }
        .barre svg { width: 17px; height: 17px; }

        .feuille { max-width: 760px; margin: 0 auto; padding: 16px; }
        .tete { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start;
            padding-bottom: 12px; border-bottom: 2px solid var(--accent); margin-bottom: 14px; }
        .tete .kicker { font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--accent-dark); }
        .tete h1 { margin: 2px 0 0; font-size: 22px; letter-spacing: -.02em; }
        .tete .date { font-size: 13px; color: var(--texte2); margin-top: 2px; }
        .tete img { height: 26px; width: auto; flex: none; }

        .resume { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 18px; }
        .resume span { background: #fff; border: 1px solid var(--bord); border-radius: 999px; padding: 6px 12px; font-size: 13px; color: var(--texte2); }
        .resume strong { color: var(--encre); }
        .resume .retard strong { color: var(--ko); }

        .commune { margin-bottom: 18px; }
        .commune h2 { display: flex; justify-content: space-between; gap: 10px; align-items: baseline; margin: 0 0 8px;
            font-size: 13px; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; color: var(--accent-dark); }
        .commune h2 small { font-size: 12px; letter-spacing: 0; text-transform: none; color: var(--texte3); font-weight: 600; }

        .fiche { display: grid; grid-template-columns: 22px minmax(0, 1fr); gap: 10px; background: #fff; border: 1px solid var(--bord);
            border-radius: 12px; padding: 10px 12px; margin-bottom: 8px; break-inside: avoid; }
        .fiche.retard { border-left: 3px solid var(--ko); }
        .case { width: 18px; height: 18px; border: 2px solid #98a2b3; border-radius: 4px; margin-top: 2px; }
        .l1 { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .ref { font: 700 13px/1.3 ui-monospace, 'SF Mono', Menlo, monospace; color: var(--accent-dark); }
        .nom { font-weight: 700; margin-top: 1px; }
        .meta { display: flex; flex-wrap: wrap; gap: 2px 12px; margin-top: 4px; font-size: 12.5px; color: var(--texte2); }
        .meta .gps { font-family: ui-monospace, 'SF Mono', Menlo, monospace; font-size: 12px; }
        .badge { font-size: 10.5px; font-weight: 800; border-radius: 6px; padding: 2px 7px; white-space: nowrap; }
        .b-retard { background: var(--ko-bg); color: var(--ko); }
        .b-statut { background: #fff7ed; color: #b45309; }
        .b-statut[data-st="en_route"] { background: #f5f3ff; color: var(--route); }
        .b-statut[data-st="en_cours"] { background: #eff6ff; color: var(--place); }
        .b-axe { background: var(--accent); color: #fff; text-transform: uppercase; }
        .visuel { color: #1d4ed8; font-weight: 700; }

        /* Un axe : ses pieds, une case chacun */
        .axe { background: #fff; border: 1px solid var(--bord); border-radius: 12px; margin-bottom: 8px; break-inside: avoid; overflow: hidden; }
        .axe-tete { padding: 10px 12px; background: #fffaf1; border-bottom: 1px solid var(--bord); }
        .pied { display: grid; grid-template-columns: 22px 56px minmax(0, 1fr) auto; gap: 10px; align-items: center;
            padding: 8px 12px; border-top: 1px solid #f2f4f7; font-size: 13px; }
        .axe-tete + .pied { border-top: 0; }
        .pied .num { font-weight: 800; color: var(--texte2); }
        .pied .quand { font-size: 12px; color: var(--texte3); }

        .vide { text-align: center; padding: 60px 16px; color: var(--texte3); font-size: 16px; }
        .pied-de-page { margin-top: 16px; padding-top: 10px; border-top: 1px solid var(--bord); font-size: 11px; color: var(--texte3); text-align: center; }

        @media print {
            body { background: #fff; font-size: 10pt; }
            .barre { display: none !important; }
            .feuille { max-width: none; padding: 0; }
            .fiche, .axe { border-color: #d0d5dd; }
            .tete h1 { font-size: 17pt; }
        }
    </style>
</head>
<body>

<div class="barre">
    <a href="{{ route('tech.space', $token) }}">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>Ma journée
    </a>
    <button type="button" class="imprimer" onclick="window.print()">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M7 9V3h10v6M7 17H4v-7h16v7h-3M7 14h10v7H7z"/></svg>Imprimer
    </button>
</div>

<main class="feuille">
    <header class="tete">
        <div>
            <div class="kicker">Feuille de route</div>
            <h1>{{ $tech->name }}</h1>
            <div class="date">{{ ucfirst($maintenant->isoFormat('dddd D MMMM YYYY')) }} · faite à {{ $maintenant->format('H\hi') }}</div>
        </div>
        <img src="{{ asset('images/panora.png') }}" alt="Panora">
    </header>

    @if($total === 0)
        <div class="vide">🎉 Rien à poser — tu es à jour !</div>
    @else
        <div class="resume">
            <span><strong>{{ $total }}</strong> à poser</span>
            <span><strong>{{ $nbCommunes }}</strong> commune{{ $nbCommunes > 1 ? 's' : '' }}</span>
            @if($nbAxes > 0)<span><strong>{{ $nbAxes }}</strong> axe{{ $nbAxes > 1 ? 's' : '' }}</span>@endif
            <span class="retard"><strong>{{ $nbRetard }}</strong> en retard</span>
        </div>

        @foreach($entreesParCommune as $commune => $entrees)
            @php
                $tachesCommune = $groupedByCommune[$commune];
                $retardCommune = $tachesCommune->filter(fn ($t) => $isLate($t))->count();
            @endphp
            <section class="commune">
                <h2>📍 {{ $commune }}
                    <small>{{ $tachesCommune->count() }} à poser{{ $retardCommune ? ' · ' . $retardCommune . ' en retard' : '' }}</small>
                </h2>

                @foreach($entrees as $e)
                    @if($e['axe'])
                        @php
                            $axe = $e['axe'];
                            $premier = $e['items']->first();
                        @endphp
                        <div class="axe">
                            <div class="axe-tete">
                                <div class="l1">
                                    <span class="badge b-axe">⛓ Axe · {{ $e['items']->count() }} pied{{ $e['items']->count() > 1 ? 's' : '' }}</span>
                                    <span class="ref">{{ $axe->reference }}</span>
                                </div>
                                <div class="nom">{{ $axes->nomCourt($axe->name) }}</div>
                                <div class="meta">
                                    @if($premier->panel?->adresse)<span>{{ $premier->panel->adresse }}</span>@endif
                                    @if($g = $gps($premier->panel))<span class="gps">📍 {{ $g }}</span>@endif
                                    @if($premier->campaign)<span>📢 {{ \Illuminate\Support\Str::limit($premier->campaign->name, 40) }}{{ $premier->campaign->client ? ' · ' . \Illuminate\Support\Str::limit($premier->campaign->client->name, 25) : '' }}</span>@endif
                                    @if($v = $premier->libelleVague())<span class="visuel">🌊 {{ \Illuminate\Support\Str::limit($v, 45) }}</span>@endif
                                    @if($premier->panel?->format?->name)<span>{{ $premier->panel->format->name }} par pied</span>@endif
                                </div>
                            </div>
                            @foreach($e['items'] as $task)
                                @php [$st, $lib] = $statut($task); @endphp
                                <div class="pied">
                                    <span class="case"></span>
                                    <span class="num">Pied {{ $task->panel?->pied_numero }}</span>
                                    <span><span class="ref">{{ $task->panel?->reference }}</span>
                                        @if($isLate($task))<span class="badge b-retard">⏰ Retard</span>@endif</span>
                                    <span class="quand">{{ $quand($task->scheduled_at) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        @php
                            $task = $e['items']->first();
                            $panel = $task->panel;
                            [$st, $lib] = $statut($task);
                            $late = $isLate($task);
                        @endphp
                        <div class="fiche {{ $late ? 'retard' : '' }}">
                            <span class="case"></span>
                            <div>
                                <div class="l1">
                                    <span class="ref">{{ $panel?->reference ?? '—' }}</span>
                                    @if($late)<span class="badge b-retard">⏰ Retard</span>@endif
                                    @if($st !== 'planifiee')<span class="badge b-statut" data-st="{{ $st }}">{{ $lib }}</span>@endif
                                </div>
                                <div class="nom">{{ $panel?->name ?? '—' }}</div>
                                <div class="meta">
                                    @if($q = $quand($task->scheduled_at))<span>📅 {{ $q }}</span>@endif
                                    @if($panel?->adresse || $panel?->quartier)<span>{{ implode(' · ', array_filter([$panel?->adresse, $panel?->quartier])) }}</span>@endif
                                    @if($g = $gps($panel))<span class="gps">📍 {{ $g }}</span>@endif
                                    @if($panel?->format?->name)<span>{{ $panel->format->name }}</span>@endif
                                    @if($task->campaign)<span>📢 {{ \Illuminate\Support\Str::limit($task->campaign->name, 40) }}{{ $task->campaign->client ? ' · ' . \Illuminate\Support\Str::limit($task->campaign->client->name, 25) : '' }}</span>@endif
                                    @if($v = $task->libelleVague())<span class="visuel">🌊 Visuel : {{ \Illuminate\Support\Str::limit($v, 45) }}</span>@endif
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </section>
        @endforeach
    @endif

    <div class="pied-de-page">
        Panora · Feuille de route de {{ $tech->name }}, faite le {{ $maintenant->format('d/m/Y à H\hi') }} · garde ce papier avec toi
    </div>
</main>

<script>
    // Arrivée par « Imprimer la feuille de route » (#print) : la fenêtre
    // d'impression s'ouvre tout de suite.
    if (location.hash === '#print') {
        setTimeout(() => window.print(), 400);
    }
</script>

</body>
</html>

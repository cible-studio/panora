<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Mes poses — {{ $tech->name }} · Panora</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --accent: #e8a020;
            --accent-dark: #c2570d;
            --bg: #f8f9fb;
            --surface: #ffffff;
            --surface2: #f4f5f7;
            --border: #e5e7eb;
            --text: #111827;
            --text2: #4b5563;
            --text3: #9ca3af;
            --planned: #e8a020;
            --en-route: #8b5cf6;
            --in-progress: #3b82f6;
            --done: #22c55e;
            --cancelled: #ef4444;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            margin: 0; padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text);
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.55;
        }

        /* ── Header sticky — charte CIBLE claire ───────────────── */
        .header {
            position: sticky; top: 0; z-index: 50;
            background: var(--surface);
            color: var(--text);
            padding: 14px 16px;
            padding-top: max(14px, env(safe-area-inset-top));
            border-bottom: 1px solid var(--border);
            box-shadow: 0 2px 10px rgba(15,23,42,.04);
        }
        .header-row {
            display: flex; align-items: center; gap: 14px;
        }
        .brand-logo {
            flex: 0 0 auto;
            height: 44px; width: auto; display: block;
            object-fit: contain;
        }
        .header-info { flex: 1; min-width: 0; }
        .brand-kicker {
            font-size: 10px; font-weight: 800; letter-spacing: 1.3px;
            text-transform: uppercase; color: var(--accent-dark);
            display: block;
        }
        .header h1 {
            font-size: 20px; font-weight: 800; margin: 1px 0 0;
            letter-spacing: -0.2px; color: var(--text);
        }
        .header .stats {
            display: flex; gap: 8px; margin-top: 10px;
            font-size: 12.5px; flex-wrap: wrap;
        }
        .header .stats .stat {
            padding: 5px 10px; border-radius: 999px;
            display: inline-flex; align-items: center; gap: 5px;
            border: 1px solid var(--border); background: var(--surface2);
            color: var(--text2);
        }
        .header .stats .stat strong { color: var(--text); font-weight: 800; }
        .header .stats .stat-todo strong { color: var(--accent-dark); }
        .header .stats .stat-done strong { color: var(--done); }

        /* ── Container ─────────────────────────────────────────── */
        .container { padding: 16px; max-width: 600px; margin: 0 auto; }
        .day-section { margin-bottom: 22px; }
        .day-header {
            display: flex; align-items: center; justify-content: space-between;
            margin: 0 0 10px;
            padding: 0 4px;
        }
        .day-header h2 {
            font-size: 13px; font-weight: 700;
            color: var(--text2);
            margin: 0;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .day-header .count {
            font-size: 11px; font-weight: 600;
            background: var(--surface);
            color: var(--text2);
            border: 1px solid var(--border);
            padding: 3px 9px; border-radius: 999px;
        }

        /* Bandeau "en retard" — orange vif */
        .day-header.overdue h2 { color: var(--cancelled); }
        .day-header.overdue .count {
            background: rgba(239,68,68,.10);
            border-color: rgba(239,68,68,.30);
            color: var(--cancelled);
        }

        /* ── Card pose ────────────────────────────────────────── */
        .pose {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .pose-head {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 10px; margin-bottom: 8px;
        }
        .pose-ref {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 13px; font-weight: 700;
            color: var(--accent-dark);
        }
        .pose-name {
            font-size: 13px; color: var(--text);
            font-weight: 500;
            margin-top: 2px;
        }
        .pose-meta {
            font-size: 11px; color: var(--text3);
            display: flex; flex-wrap: wrap; gap: 10px;
            margin-top: 6px;
        }
        .pose-meta span { display: inline-flex; align-items: center; gap: 4px; }
        .pose-campaign {
            font-size: 11px; color: var(--text2);
            background: var(--surface2);
            padding: 3px 8px; border-radius: 6px;
            margin-top: 6px;
            display: inline-block;
        }

        .status-badge {
            font-size: 11px; font-weight: 700;
            padding: 4px 9px; border-radius: 999px;
            white-space: nowrap;
            display: inline-flex; align-items: center; gap: 4px;
            border: 1px solid transparent;
        }

        /* ── Actions buttons ──────────────────────────────────── */
        .actions { display: flex; gap: 6px; margin-top: 12px; flex-wrap: wrap; }
        .btn {
            flex: 1; min-width: 0;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--surface);
            color: var(--text);
            font-size: 12px; font-weight: 600;
            cursor: pointer;
            text-align: center;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            transition: all .15s;
            min-height: 38px;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: var(--accent); color: #fff; border-color: var(--accent); }
        .btn-route   { background: var(--en-route); color: #fff; border-color: var(--en-route); }
        .btn-work    { background: var(--in-progress); color: #fff; border-color: var(--in-progress); }
        .btn-done    { background: var(--done); color: #fff; border-color: var(--done); }
        .btn-photo   { background: var(--surface2); border-color: var(--border); }
        .btn-photo input[type=file] { display: none; }

        /* ── Empty state ──────────────────────────────────────── */
        .empty {
            text-align: center; padding: 60px 20px;
            color: var(--text3);
        }
        .empty .icon { font-size: 48px; margin-bottom: 12px; }
        .empty h2 { font-size: 18px; color: var(--text); margin: 0 0 6px; }
        .empty p { margin: 0; font-size: 14px; }

        /* ── Toast ────────────────────────────────────────────── */
        #toast-container {
            position: fixed; top: 80px; left: 50%; transform: translateX(-50%);
            z-index: 100; max-width: 90%; pointer-events: none;
        }
        .toast {
            background: var(--text); color: #fff;
            padding: 12px 18px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 6px 20px rgba(0,0,0,.25);
            margin-bottom: 8px;
            opacity: 0; transform: translateY(-10px);
            transition: all .25s ease-out;
            pointer-events: auto;
        }
        .toast.show { opacity: 1; transform: translateY(0); }
        .toast.success { background: var(--done); }
        .toast.error   { background: var(--cancelled); }

        /* ── Footer ───────────────────────────────────────────── */
        .footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text3);
            font-size: 11px;
        }

        /* ═══ PARTIE B — refonte zone/lignes compactes ═══ */
        /* Barre de progression dans l'en-tête (charte claire) */
        .progress-wrap { margin-top: 12px; }
        .progress-bar {
            height: 10px; border-radius: 999px;
            background: var(--surface2);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .progress-fill {
            height: 100%; border-radius: 999px;
            background: linear-gradient(90deg, #16a34a, #22c55e);
            transition: width .5s cubic-bezier(.4,.0,.2,1);
        }
        .progress-meta {
            font-size: 11.5px; color: var(--text3);
            margin-top: 6px; font-weight: 600;
            display: flex; justify-content: space-between;
        }
        .progress-meta strong { color: var(--text); }

        /* En-tête de zone (commune) — avec mini barre de progression de la zone */
        .commune-header {
            display: flex; align-items: center; gap: 14px;
            width: 100%; padding: 12px 14px;
            background: var(--surface); color: var(--text);
            border: 1px solid var(--border); border-radius: 14px;
            margin-bottom: 10px;
            text-align: left;
            box-shadow: 0 1px 2px rgba(15,23,42,.04);
            transition: box-shadow .15s, transform .08s;
        }
        .commune-header .ch-left {
            display: flex; align-items: center; gap: 10px;
            flex: 0 0 auto;
        }
        .commune-header h2 {
            margin: 0; font-size: 15px; font-weight: 800;
            display: flex; align-items: center; gap: 8px;
        }
        .commune-header .count {
            font-size: 11.5px; font-weight: 700;
            background: var(--surface2); border: 1px solid var(--border);
            color: var(--text2); padding: 3px 9px; border-radius: 999px;
            white-space: nowrap;
        }
        /* Mini barre de progression PAR ZONE — visuel "ABOBO 40%" immédiat */
        .commune-header .ch-progress {
            flex: 1 1 auto; min-width: 60px;
            height: 6px; border-radius: 999px;
            background: var(--surface2);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .commune-header .ch-progress-fill {
            height: 100%; border-radius: 999px;
            background: linear-gradient(90deg, var(--accent), var(--accent-dark));
            transition: width .5s cubic-bezier(.4,.0,.2,1);
        }
        .commune-header.has-overdue { border-color: rgba(239,68,68,.30); background: rgba(239,68,68,.03); }
        .commune-header.has-overdue h2::before { content: '🔥'; }
        .commune-header.has-overdue .ch-progress-fill {
            background: linear-gradient(90deg, #f97316, #ef4444);
        }

        /* Ligne pose compacte — vignette + ref + statut, tap = caméra */
        .pose-line {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 0;
            margin-bottom: 10px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(15,23,42,.05);
            transition: box-shadow .15s, transform .08s;
        }
        .pose-line:active { transform: scale(.997); box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .pose-main {
            display: flex; align-items: center; gap: 12px;
            padding: 12px; min-height: 80px;
            cursor: pointer; position: relative;
        }
        .pose-main input[type=file] { display: none; }
        .pose-thumb {
            flex: 0 0 68px; width: 68px; height: 68px;
            border-radius: 12px;
            background-color: var(--surface2);
            background-size: cover; background-position: center;
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: var(--text3);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.5);
        }
        .pose-info { flex: 1; min-width: 0; }
        .pose-info .pose-ref {
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
            font-size: 15px; font-weight: 800; color: var(--accent-dark);
            display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
        }
        .pose-info .pose-name {
            font-size: 13px; color: var(--text); font-weight: 600;
            margin-top: 1px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .pose-info .pose-sub {
            font-size: 11px; color: var(--text3); margin-top: 3px;
            display: flex; gap: 8px; flex-wrap: wrap;
        }
        .pose-info .pose-sub .late {
            color: var(--cancelled); font-style: normal; font-weight: 700;
            background: rgba(239,68,68,.08); padding: 1px 6px; border-radius: 6px;
        }
        .pose-dot {
            flex: 0 0 14px; width: 14px; height: 14px; border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(15,23,42,.05), 0 0 0 5px currentColor;
            opacity: .9;
        }
        .pose-cam {
            flex: 0 0 38px; font-size: 22px;
            color: var(--accent-dark); opacity: .85;
            display: flex; align-items: center; justify-content: center;
            background: rgba(232,160,32,.08);
            width: 38px; height: 38px; border-radius: 10px;
        }
        /* Actions secondaires "Y aller" + "Problème" — barre en bas de la ligne */
        .pose-actions-row {
            display: flex; gap: 0;
            border-top: 1px solid var(--border);
        }
        .pose-act {
            flex: 1; padding: 10px; min-height: 44px;
            background: transparent; border: 0;
            text-align: center; text-decoration: none;
            font-size: 13px; font-weight: 700; color: var(--text2);
            cursor: pointer; font-family: inherit;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .pose-act + .pose-act { border-left: 1px solid var(--border); }
        .pose-act:active { background: var(--surface2); }
        .pose-act.act-go { color: #2563eb; }
        .pose-act.act-warn { color: #b45309; }

        /* Bandeau "déjà signalé" — visible et persistent au-dessus de la
           ligne de la pose. Le tech le voit à chaque chargement et ne
           re-signale plus le même problème sans réfléchir. */
        .pose-reported-banner {
            font-size: 12px; font-weight: 700;
            padding: 8px 12px;
            background: rgba(245, 158, 11, .10);
            color: #b45309;
            border-bottom: 1px solid rgba(245, 158, 11, .22);
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
        }
        .pose-reported-banner .reported-when {
            color: #92400e; font-weight: 500;
        }
        /* La carte d'une pose avec problème déjà signalé a un liseré
           ambré subtil pour qu'on la repère dans la liste. */
        .pose-line.has-problem {
            border-color: rgba(245, 158, 11, .35);
            box-shadow: 0 1px 3px rgba(245, 158, 11, .15);
        }
        .pose-line.has-problem .pose-act.act-warn {
            background: rgba(245, 158, 11, .08);
        }

        /* Bandeau ROUGE — photo refusée par MP (motif visible) */
        .pose-rejected-banner {
            font-size: 12.5px; font-weight: 700;
            padding: 10px 12px;
            background: rgba(239, 68, 68, .10);
            color: #b91c1c;
            border-bottom: 1px solid rgba(239, 68, 68, .25);
            display: block;
        }
        .pose-rejected-banner .reject-reason {
            font-weight: 500;
        }
        .pose-line.has-reject {
            border-color: rgba(239, 68, 68, .45);
            box-shadow: 0 1px 4px rgba(239, 68, 68, .18);
        }
    </style>
</head>
<body>

<div class="header">
    <div class="header-row">
        <img src="{{ asset('images/panora.png') }}" alt="Panora by CIBLE" class="brand-logo">
        <div class="header-info">
            <span class="brand-kicker">Espace technicien · CIBLE CI</span>
            <h1>Bonjour {{ $tech->name }}</h1>
            <div class="stats">
                <div class="stat stat-todo">📋 <strong data-total-active>{{ $totalActive }}</strong> à faire</div>
                <div class="stat stat-done">✅ <strong>{{ $totalDone }}</strong> faite{{ $totalDone > 1 ? 's' : '' }}</div>
            </div>
        </div>
    </div>
    @if(($totalAssigned ?? 0) > 0)
    <div class="progress-wrap">
        <div class="progress-bar"><div class="progress-fill" style="width:{{ $progressPct ?? 0 }}%"></div></div>
        <div class="progress-meta">
            <span>{{ $totalDone }}/{{ $totalAssigned }} terminées</span>
            <span><strong>{{ $progressPct }}%</strong></span>
        </div>
    </div>
    @endif
</div>

<div class="container">

    @if($totalActive === 0)
        <div class="empty">
            <div class="icon">🎉</div>
            <h2>Aucune pose à effectuer</h2>
            <p>Tu es à jour ! Tes prochaines missions arriveront via WhatsApp.</p>
        </div>
    @else
        {{-- Recherche live (référence / nom / commune / campagne) --}}
        @if($totalActive >= 6)
            <div style="margin-bottom:14px;position:relative">
                <input type="search" id="pose-search" placeholder="🔍 Rechercher un panneau, commune, campagne…"
                       style="width:100%;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--surface);color:var(--text);font-size:14px;font-family:inherit;outline:none;-webkit-appearance:none"
                       autocomplete="off">
                <div id="pose-search-empty"
                     style="display:none;margin-top:10px;padding:14px;text-align:center;color:var(--text3);background:var(--surface);border:1px dashed var(--border);border-radius:10px;font-size:13px">
                    Aucune pose ne correspond à ta recherche.
                </div>
            </div>
        @endif

        @php $today = \Carbon\Carbon::today(); @endphp
        @foreach($groupedByCommune as $communeName => $tasks)
            @php
                $hasOverdue = $tasks->contains(function ($t) use ($today) {
                    $d = $t->scheduled_at ?? $t->created_at;
                    return $d && \Carbon\Carbon::parse($d)->startOfDay()->lt($today);
                });
            @endphp
            @php
                $doneZone   = $doneByCommune[$communeName] ?? 0;
                $activeZone = $tasks->count();
                $totalZone  = $activeZone + $doneZone;
                $pctZone    = $totalZone > 0 ? (int) round($doneZone / $totalZone * 100) : 0;
            @endphp
            <div class="day-section">
                <div class="commune-header {{ $hasOverdue ? 'has-overdue' : '' }}">
                    <div class="ch-left">
                        <h2>📍 {{ $communeName }}</h2>
                        <span class="count">{{ $doneZone }}/{{ $totalZone }} faite{{ $totalZone > 1 ? 's' : '' }}</span>
                    </div>
                    <div class="ch-progress" title="{{ $pctZone }}% de la zone terminée">
                        <div class="ch-progress-fill" style="width:{{ $pctZone }}%"></div>
                    </div>
                </div>

                @foreach($tasks as $task)
                    @php
                        $status = $task->status instanceof \App\Enums\PoseTaskStatus
                            ? $task->status
                            : \App\Enums\PoseTaskStatus::from((string) $task->status);
                        $statusColor = $status->color();

                        $sched = $task->scheduled_at ?? $task->created_at;
                        $isLate = $sched && \Carbon\Carbon::parse($sched)->startOfDay()->lt($today);

                        // Photo cible du panneau : 1re photo si dispo, sinon placeholder
                        $firstPhoto = $task->panel?->photos?->sortBy('ordre')->first();
                        $thumbUrl   = $firstPhoto ? asset('storage/' . $firstPhoto->path) : null;

                        // "Y aller" : direction GPS si lat/lng dispo, sinon recherche adresse
                        $hasGps = $task->panel?->latitude && $task->panel?->longitude;
                        if ($hasGps) {
                            $goUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $task->panel->latitude . ',' . $task->panel->longitude;
                        } else {
                            $loc = array_filter([$task->panel?->adresse, $task->panel?->quartier, $task->panel?->commune?->name, 'Côte d\'Ivoire']);
                            $goUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(implode(', ', $loc));
                        }

                        $searchHay = mb_strtolower(implode(' ', array_filter([
                            $task->panel?->reference, $task->panel?->name,
                            $task->panel?->commune?->name, $task->panel?->quartier,
                            $task->panel?->adresse, $task->campaign?->name,
                            $task->campaign?->client?->name,
                        ])));
                    @endphp
                    @php
                        // Dernier signalement de problème terrain (s'il y en a un)
                        $lastProblem = $task->lastProblemReport;
                        $problemLabels = [
                            'panneau_casse'    => 'Panneau cassé',
                            'acces_bloque'     => 'Accès bloqué',
                            'mauvaise_adresse' => 'Mauvaise adresse',
                            'autre'            => 'Autre problème',
                        ];
                        $problemType  = $lastProblem?->payload['type'] ?? null;
                        $problemLabel = $problemLabels[$problemType] ?? null;
                        $problemAgo   = $lastProblem?->created_at?->diffForHumans(null, true);
                    @endphp
                    @php $rejPige = $task->latestRejectedPige; @endphp
                    <div class="pose pose-line {{ $lastProblem ? 'has-problem' : '' }} {{ $rejPige ? 'has-reject' : '' }}"
                         data-task-id="{{ $task->id }}"
                         data-search="{{ $searchHay }}"
                         data-lat="{{ $task->panel?->latitude }}"
                         data-lng="{{ $task->panel?->longitude }}">
                        {{-- Bandeau ROUGE "photo refusée par le superviseur" — motif
                             visible direct, le tech sait quoi corriger en re-prenant
                             la photo. Prioritaire sur le bandeau signalement. --}}
                        @if($rejPige)
                            <div class="pose-rejected-banner">
                                🚫 <strong>Photo refusée par le superviseur</strong>
                                @if($rejPige->rejection_reason)
                                    · <span class="reject-reason">{{ $rejPige->rejection_reason }}</span>
                                @endif
                                <div style="font-size:11px;opacity:.85;margin-top:2px">
                                    Reprends une photo et envoie-la depuis cette pose.
                                </div>
                            </div>
                        @endif
                        {{-- Bandeau "déjà signalé" — rappel au tech pour ne pas
                             re-signaler le même problème sans le savoir. --}}
                        <div class="pose-reported-banner" data-problem-banner
                             style="{{ $lastProblem ? '' : 'display:none' }}">
                            ⚠ Tu as déjà signalé : <strong data-problem-label>{{ $problemLabel ?: '—' }}</strong>
                            <span class="reported-when" data-problem-when>{{ $problemAgo ? 'il y a '.$problemAgo : '' }}</span>
                        </div>
                        {{-- Geste 1 : tap n'importe où sur la ligne = caméra arrière --}}
                        <label class="pose-main" data-action="photo">
                            <input type="file" accept="image/*" capture="environment" data-photo-input>
                            @if($thumbUrl)
                                <span class="pose-thumb" style="background-image:url('{{ $thumbUrl }}')"></span>
                            @else
                                <span class="pose-thumb" title="Pas de photo de référence">🪧</span>
                            @endif
                            <div class="pose-info">
                                <div class="pose-ref">
                                    {{ $task->panel?->reference ?? '—' }}
                                </div>
                                @if($task->panel?->name)
                                    <div class="pose-name">{{ $task->panel->name }}</div>
                                @endif
                                <div class="pose-sub">
                                    @if($isLate)
                                        <span class="late">⏰ En retard</span>
                                    @endif
                                    @if($task->campaign)
                                        <span>📢 {{ Str::limit($task->campaign->name, 28) }}</span>
                                    @endif
                                    @if($task->scheduled_at)
                                        <span>{{ \Carbon\Carbon::parse($task->scheduled_at)->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                            <span class="pose-dot" style="background:{{ $statusColor }}" title="{{ $status->label() }}"></span>
                            <span class="pose-cam" aria-hidden="true">📷</span>
                        </label>
                        <div class="pose-actions-row">
                            <a class="pose-act act-go" href="{{ $goUrl }}" target="_blank" rel="noopener">🧭 Y aller</a>
                            <button type="button" class="pose-act act-warn" data-action="report">⚠️ Problème</button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endforeach

    @endif

    <div class="footer">
        Panora · CIBLE CI<br>
        <span style="opacity:.6">Lien personnel — ne pas partager</span>
    </div>
</div>

<div id="toast-container"></div>

{{-- Overlay succès plein écran (feedback fort terrain) --}}
<div id="ts-success" aria-hidden="true">
    <div class="ts-check"><svg viewBox="0 0 52 52"><circle cx="26" cy="26" r="24" fill="none"/><path fill="none" d="M14 27l8 8 16-16"/></svg></div>
    <div class="ts-msg" id="ts-success-msg">Envoyé&nbsp;!</div>
</div>

{{-- Modal "Signaler un problème" --}}
<div id="ts-report-modal" aria-hidden="true">
    <div class="ts-report-card">
        <h3>⚠️ Signaler un problème</h3>
        <p class="ts-report-sub" id="ts-report-ref">Choisis ce qui ne va pas. Le superviseur sera alerté.</p>
        <div class="ts-report-opts">
            <button type="button" class="ts-report-opt" data-type="panneau_casse">🪧 Panneau cassé / abîmé</button>
            <button type="button" class="ts-report-opt" data-type="acces_bloque">🚧 Accès bloqué / impossible</button>
            <button type="button" class="ts-report-opt" data-type="mauvaise_adresse">📍 Mauvaise adresse / introuvable</button>
            <button type="button" class="ts-report-opt" data-type="autre">📝 Autre problème</button>
        </div>
        <textarea id="ts-report-note" placeholder="Précisions (facultatif)…"></textarea>
        <label class="ts-report-photo-btn" id="ts-report-photo-label">
            <input type="file" id="ts-report-photo" accept="image/*" capture="environment" hidden>
            <span id="ts-report-photo-label-text">📷 Joindre une photo (facultatif)</span>
        </label>
        <div class="ts-report-actions">
            <button type="button" class="ts-btn-ghost" id="ts-report-cancel">Annuler</button>
            <button type="button" class="ts-btn-send" id="ts-report-send" disabled>Envoyer l'alerte</button>
        </div>
    </div>
</div>

<style>
    /* UX "sans lecture" : actions plus grosses */
    .actions .btn { min-height: 52px; font-size: 16px; }
    .btn-report-sm {
        width:100%; margin-top:8px; min-height:46px;
        background:rgba(217,119,6,.10); color:#b45309;
        border:1px solid rgba(217,119,6,.30); border-radius:12px;
        font-weight:700; cursor:pointer;
    }
    .btn-report-sm:active { transform: translateY(1px); }
    /* Overlay succès */
    #ts-success {
        position:fixed; inset:0; z-index:9999; display:none;
        flex-direction:column; align-items:center; justify-content:center; gap:16px;
        background:rgba(22,163,74,.97); color:#fff;
    }
    #ts-success.show { display:flex; animation:tsFade .2s ease; }
    @keyframes tsFade { from{opacity:0} to{opacity:1} }
    .ts-check svg { width:120px; height:120px; }
    .ts-check circle { stroke:#fff; stroke-width:3; stroke-dasharray:151; stroke-dashoffset:151; animation:tsC .5s ease forwards; }
    .ts-check path { stroke:#fff; stroke-width:4; stroke-linecap:round; stroke-linejoin:round; stroke-dasharray:40; stroke-dashoffset:40; animation:tsK .35s .35s ease forwards; }
    @keyframes tsC { to{stroke-dashoffset:0} }
    @keyframes tsK { to{stroke-dashoffset:0} }
    .ts-msg { font-size:23px; font-weight:800; }
    /* Modal report */
    #ts-report-modal {
        position:fixed; inset:0; z-index:9998; display:none;
        align-items:flex-end; justify-content:center; background:rgba(15,23,42,.55); padding:0;
    }
    #ts-report-modal.show { display:flex; }
    .ts-report-card {
        background:#fff; width:100%; max-width:520px; border-radius:18px 18px 0 0;
        padding:20px 18px calc(18px + env(safe-area-inset-bottom)); animation:tsUp .25s ease;
    }
    @keyframes tsUp { from{transform:translateY(40px);opacity:.5} to{transform:translateY(0);opacity:1} }
    .ts-report-card h3 { font-size:18px; margin:0 0 4px; }
    .ts-report-sub { font-size:13px; color:#475569; margin:0 0 14px; }
    .ts-report-opts { display:flex; flex-direction:column; gap:8px; }
    .ts-report-opt {
        text-align:left; padding:14px; min-height:52px;
        background:#f6f7f9; border:1.5px solid #e8eaee; border-radius:12px;
        font-size:15px; font-weight:600; color:#0f172a; cursor:pointer;
    }
    .ts-report-opt.sel { border-color:#d97706; background:rgba(217,119,6,.10); color:#b45309; }
    #ts-report-note { width:100%; margin-top:10px; min-height:64px; padding:10px 12px; border:1px solid #e8eaee; border-radius:12px; font:inherit; font-size:14px; resize:vertical; }
    .ts-report-photo-btn {
        display:flex; align-items:center; justify-content:center; gap:8px;
        margin-top:10px; min-height:46px; padding:0 14px;
        background:rgba(59,130,246,.08); border:1.5px dashed rgba(59,130,246,.4);
        color:#2563eb; border-radius:12px;
        font-size:13px; font-weight:700; cursor:pointer;
    }
    .ts-report-photo-btn.has-file {
        background:rgba(34,197,94,.08); border-color:rgba(34,197,94,.45); color:#16a34a;
        border-style:solid;
    }
    .ts-report-actions { display:flex; gap:10px; margin-top:14px; }
    .ts-btn-ghost { flex:1; min-height:50px; background:#f1f5f9; border:none; border-radius:12px; font-weight:700; font-size:15px; cursor:pointer; }
    .ts-btn-send { flex:2; min-height:50px; background:#d97706; color:#fff; border:none; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; }
    .ts-btn-send:disabled { opacity:.5; }
</style>

<script>
(function() {
    'use strict';
    const CSRF  = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const TOKEN = @json($token);

    // ── Feedback fort : overlay plein écran + vibration ──
    function flashSuccess(msg) {
        const ov = document.getElementById('ts-success');
        const m  = document.getElementById('ts-success-msg');
        if (m && msg) m.innerHTML = msg;
        if (navigator.vibrate) { try { navigator.vibrate([40, 60, 120]); } catch (e) {} }
        if (ov) { ov.classList.add('show'); setTimeout(() => ov.classList.remove('show'), 900); }
    }

    function toast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.textContent = message;
        document.getElementById('toast-container').appendChild(el);
        requestAnimationFrame(() => el.classList.add('show'));
        setTimeout(() => {
            el.classList.remove('show');
            setTimeout(() => el.remove(), 300);
        }, 3000);
    }

    // ── Compression image côté client (canvas) ─────────────────
    // Réduit la photo à 2400 px max + JPEG q=0.85. Bénéfices :
    //  - convertit HEIC/HEIF iPhone en JPEG (sinon GD serveur refuse) ;
    //  - ramène 20-30 MB de photo brute à 200-500 KB ;
    //  - upload rapide même en 4G médiocre.
    // Best-effort : si le navigateur ne sait pas décoder (HEIC sur vieux
    // Android), on renvoie le fichier original — le serveur tentera
    // (Intervention) et a un fallback "stockage tel quel".
    async function compressImage(file, maxSize = 2400, quality = 0.85) {
        try {
            return await new Promise((resolve, reject) => {
                const img = new Image();
                const url = URL.createObjectURL(file);
                img.onload = () => {
                    URL.revokeObjectURL(url);
                    let w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
                    if (w > maxSize || h > maxSize) {
                        if (w > h) { h = Math.round(h * maxSize / w); w = maxSize; }
                        else       { w = Math.round(w * maxSize / h); h = maxSize; }
                    }
                    const c = document.createElement('canvas');
                    c.width = w; c.height = h;
                    c.getContext('2d').drawImage(img, 0, 0, w, h);
                    c.toBlob(b => b ? resolve(b) : reject(new Error('compress')), 'image/jpeg', quality);
                };
                img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
                img.src = url;
            });
        } catch (e) {
            // Décodage impossible (ex: HEIC sur navigateur sans support natif).
            // On laisse passer l'original — le serveur fera ce qu'il peut.
            return file;
        }
    }

    // ── Géolocalisation robuste (best-effort, ne bloque pas l'upload) ──
    // 1er essai haute précision (10 s — zones difficiles), retry en précision
    // dégradée (réseau/cellule) avant d'abandonner. Renvoie aussi acc (±m).
    function getPosition() {
        if (!navigator.geolocation) return Promise.resolve(null);
        const attempt = (opts) => new Promise(resolve => {
            navigator.geolocation.getCurrentPosition(
                pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, acc: pos.coords.accuracy }),
                ()  => resolve(null),
                opts
            );
        });
        return attempt({ enableHighAccuracy: true, timeout: 10000, maximumAge: 15000 })
            .then(r => r || attempt({ enableHighAccuracy: false, timeout: 8000, maximumAge: 60000 }));
    }

    // ── Changement de statut ──────────────────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="status"]');
        if (!btn) return;
        e.preventDefault();

        const pose = btn.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        const newStatus = btn.dataset.statusValue;
        if (!taskId || !newStatus) return;

        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '⏳ ...';

        try {
            const url = `/tech/${TOKEN}/poses/${taskId}/status`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify({ status: newStatus }),
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                toast(data.error || 'Erreur', 'error');
                btn.disabled = false;
                btn.innerHTML = originalText;
                return;
            }

            // Mise à jour DOM locale (pas de reload qui ferait remonter
            // en haut de page et perdrait le contexte de scroll du tech).
            const badge = pose.querySelector('[data-status]');
            if (badge) {
                badge.textContent = data.status_icon + ' ' + data.status_label;
                badge.style.color           = data.status_color;
                badge.style.background      = hexToRgba(data.status_color, 0.10);
                badge.style.borderColor     = hexToRgba(data.status_color, 0.30);
            }

            // Cache les boutons d'action sauf "Photo + Terminer" qui doit
            // rester accessible quel que soit le statut intermédiaire.
            // Si on vient de passer en "en_route" → on cache "🚗 En route"
            // (déjà fait) ; si "en_cours" → on cache "🚗" + "🔧".
            const actions = pose.querySelector('.actions');
            if (actions && newStatus === 'en_route') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
            }
            if (actions && newStatus === 'en_cours') {
                actions.querySelector('[data-status-value="en_route"]')?.remove();
                actions.querySelector('[data-status-value="en_cours"]')?.remove();
            }

            btn.disabled = false;
            btn.innerHTML = originalText;
            toast(data.message, 'success');
        } catch (err) {
            toast('Erreur réseau', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    // Convertit "#RRGGBB" en "rgba(r,g,b,alpha)" pour styliser le badge.
    function hexToRgba(hex, alpha) {
        const m = hex.match(/^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i);
        if (!m) return hex;
        return `rgba(${parseInt(m[1],16)},${parseInt(m[2],16)},${parseInt(m[3],16)},${alpha})`;
    }

    // ── Upload photo + auto-completion ───────────────────────
    document.addEventListener('change', async (e) => {
        const input = e.target.closest('[data-photo-input]');
        if (!input || !input.files?.[0]) return;
        const label = input.closest('label');
        const pose  = label?.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        if (!taskId) return;

        const file = input.files[0];
        const originalLabel = label.innerHTML;
        label.innerHTML = '🔄 Compression…';
        label.style.pointerEvents = 'none';

        // 1) Compression locale (HEIC iPhone → JPEG, gros fichier → ~500 KB)
        const blob = await compressImage(file);

        // 2) GPS pendant la compression aurait gagné un peu de temps, on garde
        //    la séquence simple : compress puis GPS puis envoi.
        label.innerHTML = '📍 GPS…';
        const gps = await getPosition();
        label.innerHTML = (gps && gps.acc) ? `📍 ±${Math.round(gps.acc)} m · envoi…` : '⏳ Envoi…';

        // 3) FormData. Si compression a réussi → blob JPEG, sinon file original.
        const form = new FormData();
        const isBlob = blob instanceof Blob && blob !== file;
        form.append('photo', blob, isBlob ? 'photo.jpg' : (file.name || 'photo.jpg'));
        if (gps) {
            form.append('gps_lat', gps.lat.toFixed(6));
            form.append('gps_lng', gps.lng.toFixed(6));
        }
        // Idempotence anti double-envoi / reprise réseau
        form.append('client_uuid', (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(16).slice(2))));

        try {
            const url = `/tech/${TOKEN}/poses/${taskId}/photo`;
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: form,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.ok) {
                // Remonte d'abord les erreurs de validation Laravel (422),
                // sinon le message du controller, sinon un fallback explicite
                // avec le code HTTP — beaucoup plus utile sur le terrain.
                const validation = data.errors ? Object.values(data.errors).flat().join(' · ') : '';
                const msg = validation || data.error || data.message || `Erreur ${res.status}`;
                toast(msg, 'error');
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                input.value = '';
                return;
            }
            flashSuccess('Photo envoyée&nbsp;!');

            // Pose réalisée → retire la card avec une petite animation
            // de fade-out plutôt que de recharger la page (préserve le
            // scroll position du tech pour les autres poses).
            if (pose) {
                pose.style.transition = 'all .4s ease-out';
                pose.style.opacity   = '0';
                pose.style.transform = 'translateX(20px)';
                setTimeout(() => {
                    pose.remove();
                    refreshDayCounters();
                }, 400);
            }
        } catch (err) {
            toast('Erreur réseau', 'error');
            label.innerHTML = originalLabel;
            label.style.pointerEvents = '';
            input.value = '';
        }
    });

    // ── Recherche live ─────────────────────────────────────
    // Filtre les cards par référence/nom/commune/campagne. Active
    // dès que le tech tape (debounce 100ms).
    const searchInput = document.getElementById('pose-search');
    const searchEmpty = document.getElementById('pose-search-empty');
    if (searchInput) {
        let debounce;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounce);
            debounce = setTimeout(applySearch, 100);
        });
    }
    function applySearch() {
        const q = (searchInput?.value || '').trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.pose').forEach(card => {
            const hay = card.dataset.search || '';
            const match = q === '' || hay.includes(q);
            card.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        // Cache les sections de jour vides après filtrage
        document.querySelectorAll('.day-section').forEach(section => {
            const has = Array.from(section.querySelectorAll('.pose'))
                .some(p => p.style.display !== 'none');
            section.style.display = has ? '' : 'none';
        });
        if (searchEmpty) {
            searchEmpty.style.display = (q !== '' && visible === 0) ? 'block' : 'none';
        }
    }

    // Recalcule les compteurs "X poses" sous chaque date après retrait
    // d'une pose terminée (évite l'incohérence visuelle).
    function refreshDayCounters() {
        document.querySelectorAll('.day-section').forEach(section => {
            const remaining = section.querySelectorAll('.pose').length;
            const counter = section.querySelector('.count');
            if (remaining === 0) {
                section.remove();
            } else if (counter) {
                counter.textContent = remaining + ' pose' + (remaining > 1 ? 's' : '');
            }
        });
        // Met à jour le compteur global du header
        const totalActiveEl = document.querySelector('[data-total-active]');
        if (totalActiveEl) {
            const total = document.querySelectorAll('.pose').length;
            totalActiveEl.textContent = total;
        }
        // Si plus aucune pose, affiche l'empty state
        if (document.querySelectorAll('.pose').length === 0) {
            location.reload();
        }
    }

    // ── Signaler un problème (1 tap) ─────────────────────────
    (function initReport() {
        const modal  = document.getElementById('ts-report-modal');
        const refEl  = document.getElementById('ts-report-ref');
        const noteEl   = document.getElementById('ts-report-note');
        const sendBtn  = document.getElementById('ts-report-send');
        const cancel   = document.getElementById('ts-report-cancel');
        const photoInp = document.getElementById('ts-report-photo');
        const photoLbl = document.getElementById('ts-report-photo-label');
        const photoTxt = document.getElementById('ts-report-photo-label-text');
        let attachedPhoto = null;

        photoInp?.addEventListener('change', async () => {
            const f = photoInp.files?.[0];
            if (!f) {
                attachedPhoto = null;
                photoLbl?.classList.remove('has-file');
                if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
                return;
            }
            // Compresse côté client (réutilise la fonction du flux photo principal)
            try {
                attachedPhoto = await compressImage(f);
                photoLbl?.classList.add('has-file');
                if (photoTxt) photoTxt.textContent = '✓ Photo prête';
            } catch (e) {
                attachedPhoto = f; // fallback original
                photoLbl?.classList.add('has-file');
                if (photoTxt) photoTxt.textContent = '✓ Photo prête (non compressée)';
            }
        });
        if (!modal) return;
        let currentTaskId = null, selectedType = null;

        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action="report"]');
            if (!btn) return;
            e.preventDefault();
            const pose = btn.closest('[data-task-id]');
            currentTaskId = pose?.dataset.taskId || null;
            if (!currentTaskId) return;
            selectedType = null;
            if (noteEl) noteEl.value = '';
            sendBtn.disabled = true;
            modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.remove('sel'));
            const ref = pose.querySelector('.pose-ref')?.textContent?.trim();
            if (refEl) refEl.textContent = ref ? ('Panneau ' + ref + ' — choisis le problème.') : 'Choisis ce qui ne va pas.';
            // Reset photo jointe pour ne pas hériter d'un précédent signalement
            attachedPhoto = null;
            if (photoInp) photoInp.value = '';
            photoLbl?.classList.remove('has-file');
            if (photoTxt) photoTxt.textContent = '📷 Joindre une photo (facultatif)';
            modal.classList.add('show');
        });

        modal.querySelectorAll('.ts-report-opt').forEach(opt => {
            opt.addEventListener('click', () => {
                selectedType = opt.dataset.type;
                modal.querySelectorAll('.ts-report-opt').forEach(o => o.classList.toggle('sel', o === opt));
                sendBtn.disabled = false;
            });
        });
        cancel?.addEventListener('click', () => modal.classList.remove('show'));
        modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('show'); });

        sendBtn?.addEventListener('click', async () => {
            if (!currentTaskId || !selectedType) return;
            sendBtn.disabled = true;
            try {
                // Si photo jointe → multipart, sinon JSON (plus léger).
                let res;
                if (attachedPhoto) {
                    const fd = new FormData();
                    fd.append('type', selectedType);
                    fd.append('note', (noteEl?.value || '').trim());
                    fd.append('photo', attachedPhoto, 'signalement.jpg');
                    res = await fetch(`/tech/${TOKEN}/poses/${currentTaskId}/report`, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: fd,
                    });
                } else {
                    res = await fetch(`/tech/${TOKEN}/poses/${currentTaskId}/report`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                        body: JSON.stringify({ type: selectedType, note: (noteEl?.value || '').trim() }),
                    });
                }
                const data = await res.json();
                modal.classList.remove('show');
                if (res.ok && data.ok) {
                    flashSuccess('Signalement envoyé&nbsp;!');

                    // Inscrit le rappel "déjà signalé" sur la ligne de la
                    // pose : le tech ne re-signalera plus le même problème
                    // sans s'en rendre compte (cas plusieurs panneaux).
                    const TYPE_LABELS = {
                        panneau_casse:    'Panneau cassé',
                        acces_bloque:     'Accès bloqué',
                        mauvaise_adresse: 'Mauvaise adresse',
                        autre:            'Autre problème',
                    };
                    const pose = document.querySelector(`.pose-line[data-task-id="${currentTaskId}"]`);
                    if (pose) {
                        pose.classList.add('has-problem');
                        const banner = pose.querySelector('[data-problem-banner]');
                        const lbl = pose.querySelector('[data-problem-label]');
                        const whn = pose.querySelector('[data-problem-when]');
                        if (banner) banner.style.display = '';
                        if (lbl) lbl.textContent = TYPE_LABELS[selectedType] || 'Problème signalé';
                        if (whn) whn.textContent = "à l'instant";
                    }
                } else {
                    toast(data.error || data.message || 'Erreur', 'error');
                    sendBtn.disabled = false;
                }
            } catch (err) {
                toast('Erreur réseau', 'error');
                sendBtn.disabled = false;
            }
        });
    })();
})();
</script>

</body>
</html>

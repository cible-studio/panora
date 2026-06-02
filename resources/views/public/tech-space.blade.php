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

        /* ── Header sticky — dashboard tech CIBLE ──────────────── */
        .header {
            position: sticky; top: 0; z-index: 50;
            background: linear-gradient(180deg, #fff 0%, #fffaf0 100%);
            color: var(--text);
            padding: 14px 16px 12px;
            padding-top: max(14px, env(safe-area-inset-top));
            border-bottom: 1px solid var(--border);
            box-shadow: 0 4px 18px -8px rgba(232,160,32,.18);
        }
        .header-top {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 12px;
        }
        .brand-logo {
            flex: 0 0 auto;
            height: 38px; width: auto; display: block;
            object-fit: contain;
        }
        .header-kicker {
            flex: 1; min-width: 0;
            font-size: 10px; font-weight: 800; letter-spacing: 1.3px;
            text-transform: uppercase; color: var(--accent-dark);
            line-height: 1.2;
        }
        .header-chip {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 11px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 11.5px; font-weight: 700;
            text-decoration: none; flex-shrink: 0;
            transition: transform .15s, border-color .15s;
            white-space: nowrap;
        }
        .header-chip .chip-text { display: inline; }
        @media (max-width: 420px) {
            /* Sous 420px, le chip se réduit à l'icône + badge pour laisser
               la place au kicker "Espace Technicien" + logo sans tronquer. */
            .header-chip .chip-text { display: none; }
            .header-chip { padding: 6px 9px; }
            .header-kicker { font-size: 9px; }
        }
        .header-chip:active { transform: scale(.97); border-color: var(--accent); }
        .header-chip .chip-badge {
            background: var(--accent); color: #fff; font-weight: 800;
            padding: 1px 6px; border-radius: 999px; font-size: 10px;
            min-width: 16px; text-align: center;
        }
        .header-chip.has-warn .chip-badge { background: #ef4444; }

        /* Hero — salut + résumé */
        .hero {
            display: flex; align-items: center; gap: 12px;
            margin-bottom: 14px;
        }
        .hero-avatar {
            width: 46px; height: 46px; border-radius: 50%;
            background: linear-gradient(135deg, #e8a020 0%, #c2570d 100%);
            color: #fff; font-weight: 800; font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; box-shadow: 0 4px 12px -2px rgba(232,160,32,.45);
        }
        .hero-text { flex: 1; min-width: 0; }
        .hero-text h1 {
            font-size: 19px; font-weight: 800; margin: 0;
            letter-spacing: -0.3px; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .hero-subline {
            font-size: 12px; color: var(--text2);
            margin-top: 1px;
        }

        /* Grille KPI — 4 cartes, 2x2 sur mobile, 1x4 sur grand écran */
        .kpi-grid {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 8px; margin-bottom: 12px;
        }
        @media (max-width: 480px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .kpi-card {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 10px 11px;
            display: flex; flex-direction: column; gap: 2px;
            position: relative; overflow: hidden;
            /* L'élément est devenu button/a interactif */
            text-align: left; cursor: pointer; font: inherit; color: inherit;
            text-decoration: none;
            transition: transform .15s cubic-bezier(.16,1,.3,1),
                        border-color .15s, box-shadow .15s, background .15s;
        }
        .kpi-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0;
            width: 3px; background: var(--kpi-clr, var(--accent));
            transition: width .2s;
        }
        .kpi-card:hover {
            border-color: color-mix(in srgb, var(--kpi-clr, var(--accent)) 60%, transparent);
            box-shadow: 0 6px 20px -8px color-mix(in srgb, var(--kpi-clr, var(--accent)) 50%, transparent);
        }
        .kpi-card:active { transform: scale(.97); }
        .kpi-card.is-active {
            background: color-mix(in srgb, var(--kpi-clr, var(--accent)) 8%, var(--surface));
            border-color: var(--kpi-clr, var(--accent));
            box-shadow: 0 6px 22px -6px color-mix(in srgb, var(--kpi-clr, var(--accent)) 50%, transparent);
        }
        .kpi-card.is-active::before { width: 5px; }
        .kpi-card .kpi-label {
            font-size: 9.5px; font-weight: 800; letter-spacing: .6px;
            text-transform: uppercase; color: var(--text3);
            line-height: 1.2;
        }
        .kpi-card .kpi-value {
            font-size: 22px; font-weight: 800; color: var(--kpi-clr, var(--text));
            line-height: 1.1; margin-top: 1px;
            font-family: ui-monospace, 'SF Mono', monospace;
            transition: transform .25s cubic-bezier(.16,1,.3,1);
        }
        .kpi-card .kpi-value.kpi-bump { animation: kpiBump .6s cubic-bezier(.16,1,.3,1); }
        @keyframes kpiBump {
            0%   { transform: scale(1); }
            30%  { transform: scale(1.25); color: var(--kpi-clr, var(--text)); }
            100% { transform: scale(1); }
        }
        .kpi-card .kpi-sub {
            font-size: 10.5px; color: var(--text3); margin-top: 1px;
            line-height: 1.2; min-height: 13px;
        }
        .kpi-todo  { --kpi-clr: #f97316; }
        .kpi-today { --kpi-clr: #3b82f6; }
        .kpi-piges { --kpi-clr: #22c55e; }
        .kpi-zones { --kpi-clr: #8b5cf6; }

        /* Bandeau "nouvelle pose assignée" (polling détecte nouvelle assignation) */
        .new-task-banner {
            display: none; cursor: pointer; margin: 0 16px 12px;
            padding: 10px 14px; border-radius: 10px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: #fff; font-weight: 700; font-size: 13px;
            text-align: center; box-shadow: 0 8px 24px -6px rgba(59,130,246,.5);
            animation: pulseBlue 1.6s ease-in-out infinite;
        }
        @keyframes pulseBlue {
            0%, 100% { transform: scale(1); }
            50%      { transform: scale(1.015); }
        }

        /* Live dot dans le header (visible pendant le polling actif) */
        .live-indicator {
            display: inline-flex; align-items: center; gap: 4px;
            font-size: 9.5px; font-weight: 700;
            color: #22c55e; letter-spacing: .4px; text-transform: uppercase;
            opacity: 0; transition: opacity .4s;
        }
        .live-indicator.is-pulsing { opacity: 1; }
        .live-indicator::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: #22c55e;
        }

        /* Animation entrée/sortie cards quand on filtre */
        .pose.is-filtered-out { display: none; }
        .pose.is-revealed { animation: revealCard .35s cubic-bezier(.16,1,.3,1) both; }
        @keyframes revealCard {
            from { opacity: 0; transform: translateY(6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* Progression à paliers (10/25/50/75/100) */
        .progress-staged {
            background: var(--surface); border: 1px solid var(--border);
            border-radius: 12px; padding: 11px 13px;
        }
        .progress-staged-head {
            display: flex; justify-content: space-between; align-items: baseline;
            font-size: 11px; color: var(--text2); margin-bottom: 6px;
            font-weight: 600;
        }
        .progress-staged-head strong {
            font-size: 14px; color: var(--text); font-weight: 800;
            font-family: ui-monospace, 'SF Mono', monospace;
        }
        .progress-staged-track {
            position: relative; height: 10px;
            background: #f1f5f9; border-radius: 999px; overflow: hidden;
        }
        .progress-staged-fill {
            height: 100%; border-radius: 999px;
            background: linear-gradient(90deg, #f97316 0%, #f59e0b 25%, #3b82f6 50%, #22c55e 100%);
            transition: width .5s cubic-bezier(.16,1,.3,1);
        }
        .progress-staged-marks {
            display: flex; justify-content: space-between;
            margin-top: 5px;
        }
        .progress-staged-marks span {
            font-size: 9.5px; color: var(--text3); font-weight: 700;
            font-family: ui-monospace, monospace;
            position: relative;
        }
        .progress-staged-marks span.passed { color: var(--text); }
        .progress-staged-marks span.passed::before {
            content: '✓ '; color: #22c55e;
        }

        /* Récap zones aujourd'hui — petit chip */
        .today-recap {
            margin-top: 10px; font-size: 11.5px; color: var(--text2);
            display: flex; align-items: center; gap: 6px; flex-wrap: wrap;
            line-height: 1.4;
        }
        .today-recap .zone-pill {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 9px; border-radius: 999px;
            background: rgba(139, 92, 246, .10); color: #6d28d9;
            font-weight: 700; font-size: 11px;
            border: 1px solid rgba(139, 92, 246, .20);
        }

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
        .pose-act.act-arrive { color: #6d28d9; }
        .pose-act.act-arrive:disabled {
            color: #22c55e; opacity: 1; cursor: default;
            background: rgba(34,197,94,.08);
        }
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

@php
    $initial = mb_strtoupper(mb_substr($tech->name, 0, 1));
    $zonesLabel = $zonesTodayCount > 0
        ? $zonesTodayCount . ' zone' . ($zonesTodayCount > 1 ? 's' : '')
        : 'Aucune zone';
    $heroSub = $totalActive > 0
        ? "$totalActive pose" . ($totalActive > 1 ? 's' : '') . " à faire · $zonesLabel à couvrir"
        : 'Aucune pose en attente — tu es à jour';
@endphp
<div class="header">

    <div class="header-top">
        <img src="{{ asset('images/panora.png') }}" alt="Panora by CIBLE" class="brand-logo">
        <span class="live-indicator" data-live-indicator>live</span>
        <div style="flex:1"></div>
        {{-- Bloc droit : label "Espace Technicien" + chip "Mes piges" côte à côte. --}}
        <span class="header-kicker" style="flex:0 0 auto;text-align:right;line-height:1.15">Espace<br>Technicien</span>
        <a href="{{ route('tech.space.piges', $token) }}"
           class="header-chip {{ ($pigesRejected ?? 0) > 0 ? 'has-warn' : '' }}"
           aria-label="Mes piges">
            <span aria-hidden="true">📸</span><span class="chip-text">Mes piges</span>
            @if(($pigesTotal ?? 0) > 0)
                <span class="chip-badge" data-piges-chip-badge>
                    {{ ($pigesRejected ?? 0) > 0 ? $pigesRejected : $pigesTotal }}
                </span>
            @endif
        </a>
    </div>

    <div class="hero">
        <div class="hero-avatar">{{ $initial }}</div>
        <div class="hero-text">
            <h1>Bonjour {{ $tech->name }}</h1>
            <div class="hero-subline">{{ $heroSub }}</div>
        </div>
    </div>

    {{-- Grille KPI — 4 cartes cliquables (filtre la liste en dessous).
         Polling 20s met à jour data-kpi-value en douceur. État actif
         marqué par aria-pressed + classe 'is-active'. --}}
    <div class="kpi-grid" role="group" aria-label="Filtres de poses">
        <button type="button" class="kpi-card kpi-todo is-active" data-kpi-filter="all" aria-pressed="true">
            <div class="kpi-label">À faire</div>
            <div class="kpi-value" data-kpi-value="totalActive" data-total-active>{{ $totalActive }}</div>
            <div class="kpi-sub">poses en attente</div>
        </button>
        <button type="button" class="kpi-card kpi-today" data-kpi-filter="today" aria-pressed="false">
            <div class="kpi-label">Aujourd'hui</div>
            <div class="kpi-value" data-kpi-value="activeToday">{{ $activeToday ?? 0 }}</div>
            <div class="kpi-sub">à faire ce jour @if(($doneToday ?? 0) > 0)· <strong data-done-today>{{ $doneToday }}</strong> faite{{ $doneToday > 1 ? 's' : '' }}@endif</div>
        </button>
        <a href="{{ route('tech.space.piges', $token) }}" class="kpi-card kpi-piges" data-kpi-link>
            <div class="kpi-label">Piges</div>
            <div class="kpi-value" data-kpi-value="pigesSentToday">{{ $pigesSentToday ?? 0 }}</div>
            <div class="kpi-sub">envoyée{{ ($pigesSentToday ?? 0) > 1 ? 's' : '' }} ce jour</div>
        </a>
        <button type="button" class="kpi-card kpi-zones" data-kpi-action="scroll-zones">
            <div class="kpi-label">Zones</div>
            <div class="kpi-value" data-kpi-value="zonesTodayCount">{{ $zonesTodayCount ?? 0 }}</div>
            <div class="kpi-sub">tap pour naviguer ↓</div>
        </button>
    </div>

    {{-- Progression à paliers visuels (10/25/50/75/100) — encourageant et lisible --}}
    @if(($totalAssigned ?? 0) > 0)
    <div class="progress-staged">
        <div class="progress-staged-head">
            <span>Progression globale</span>
            <strong>{{ $totalDone }}/{{ $totalAssigned }} · {{ $progressPct }}%</strong>
        </div>
        <div class="progress-staged-track">
            <div class="progress-staged-fill" style="width:{{ $progressPct ?? 0 }}%"></div>
        </div>
        <div class="progress-staged-marks">
            @foreach([10, 25, 50, 75, 100] as $m)
                <span class="{{ $progressPct >= $m ? 'passed' : '' }}">{{ $m }}%</span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Récap zones de la journée (visible si au moins une zone) --}}
    @if(!empty($zonesTodayList))
    <div class="today-recap">
        <span style="font-weight:700;color:var(--text2)">📍 Zones du jour :</span>
        @foreach(array_slice($zonesTodayList, 0, 4) as $zone)
            <span class="zone-pill">{{ $zone }}</span>
        @endforeach
        @if(count($zonesTodayList) > 4)
            <span style="color:var(--text3);font-size:11px">+{{ count($zonesTodayList) - 4 }}</span>
        @endif
    </div>
    @endif
</div>

{{-- Bandeau live : nouvelle pose assignée pendant que tu es sur la page --}}
<div class="new-task-banner" data-new-task-banner onclick="window.location.reload()">
    🆕 <span data-new-task-text>Nouvelle pose assignée</span> — clic pour actualiser
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
                    @php
                        $sched = $task->scheduled_at ?? $task->created_at;
                        $isToday = $sched && \Carbon\Carbon::parse($sched)->isToday();
                    @endphp
                    <div class="pose pose-line {{ $lastProblem ? 'has-problem' : '' }} {{ $rejPige ? 'has-reject' : '' }}"
                         data-task-id="{{ $task->id }}"
                         data-task-status="{{ $status->value }}"
                         data-search="{{ $searchHay }}"
                         data-lat="{{ $task->panel?->latitude }}"
                         data-lng="{{ $task->panel?->longitude }}"
                         data-scheduled-today="{{ $isToday ? '1' : '0' }}"
                         data-commune="{{ $task->panel?->commune?->name }}"
                         @if($lastProblem)
                         data-blocking-signal-type="{{ $problemType }}"
                         data-blocking-signal-label="{{ $problemLabel }}"
                         @endif>
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
                            <a class="pose-act act-go" href="{{ $goUrl }}" target="_blank" rel="noopener" data-go-maps>🧭 Y aller</a>
                            {{-- Bouton "Sur place" : visible si pas encore terminé.
                                 Désactivé si déjà en_cours pour éviter les re-clics. --}}
                            <button type="button"
                                    class="pose-act act-arrive"
                                    data-action="arrive"
                                    {{ $status->value === 'en_cours' ? 'disabled' : '' }}>
                                @if($status->value === 'en_cours')
                                    ✓ Sur place
                                @else
                                    📍 Sur place
                                @endif
                            </button>
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

    // ── KPI cliquables : filtre la liste des poses sans reload ─────
    function applyKpiFilter(name) {
        const poses = document.querySelectorAll('.pose[data-task-id]');
        let visible = 0;
        poses.forEach(p => {
            let show = true;
            if (name === 'today') show = p.dataset.scheduledToday === '1';
            p.classList.toggle('is-filtered-out', !show);
            if (show) {
                visible++;
                p.classList.add('is-revealed');
                setTimeout(() => p.classList.remove('is-revealed'), 400);
            }
        });

        // Masque les sections commune désormais vides
        document.querySelectorAll('.day-section').forEach(section => {
            const remaining = section.querySelectorAll('.pose:not(.is-filtered-out)').length;
            section.style.display = remaining === 0 ? 'none' : '';
        });

        // Empty state si le filtre ne laisse rien
        let emptyEl = document.getElementById('kpi-filter-empty');
        if (visible === 0 && name !== 'all') {
            if (!emptyEl) {
                emptyEl = document.createElement('div');
                emptyEl.id = 'kpi-filter-empty';
                emptyEl.style.cssText = 'background:var(--surface);border:1px dashed var(--border);border-radius:14px;padding:32px 18px;text-align:center;color:var(--text3);margin-bottom:16px';
                emptyEl.innerHTML = '<div style="font-size:36px;margin-bottom:8px;opacity:.4">🗓️</div><div style="font-size:14px;font-weight:700;color:var(--text2);margin-bottom:4px" data-empty-title></div><div style="font-size:12px" data-empty-sub></div>';
                document.querySelector('.container').insertBefore(emptyEl, document.querySelector('.day-section'));
            }
            const titles = {
                today: ['Pas de pose prévue aujourd\'hui', 'Tes prochaines missions arriveront via WhatsApp.'],
            };
            const [t, s] = titles[name] || ['Aucune pose dans cette catégorie', ''];
            emptyEl.querySelector('[data-empty-title]').textContent = t;
            emptyEl.querySelector('[data-empty-sub]').textContent   = s;
            emptyEl.style.display = '';
        } else if (emptyEl) {
            emptyEl.style.display = 'none';
        }
    }

    document.querySelectorAll('[data-kpi-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            const name = btn.dataset.kpiFilter;
            document.querySelectorAll('.kpi-card[data-kpi-filter]').forEach(b => {
                const active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            applyKpiFilter(name);
        });
    });

    // KPI "Zones" : pas un filtre — scroll smooth vers la première zone.
    const zonesBtn = document.querySelector('[data-kpi-action="scroll-zones"]');
    if (zonesBtn) {
        zonesBtn.addEventListener('click', () => {
            const firstSection = document.querySelector('.day-section');
            if (firstSection) firstSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    }

    // ── Progression auto : "Y aller" bumpe en_route (25%) + ouvre Maps ──
    document.addEventListener('click', async (e) => {
        const goBtn = e.target.closest('[data-go-maps]');
        if (!goBtn) return;
        const pose = goBtn.closest('[data-task-id]');
        if (!pose) return;
        const currentStatus = pose.dataset.taskStatus;
        // Bump uniquement si statut planifiee (pas régression sur en_cours, etc.)
        if (currentStatus !== 'planifiee') return;
        const taskId = pose.dataset.taskId;
        try {
            const r = await fetch(`/tech/${TOKEN}/poses/${taskId}/status`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'status=en_route',
                credentials: 'same-origin',
            });
            if (r.ok) {
                pose.dataset.taskStatus = 'en_route';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = '#8b5cf6'; // violet EN_ROUTE
            }
        } catch (e) { /* silencieux, on n'empêche pas Maps */ }
        // Le lien suit son cours (target=_blank) — pas de preventDefault.
    });

    // ── Bouton "Sur place" : bump en_cours (60%) ────────────────────
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="arrive"]');
        if (!btn || btn.disabled) return;
        const pose = btn.closest('[data-task-id]');
        if (!pose) return;
        const taskId = pose.dataset.taskId;
        const original = btn.innerHTML;
        btn.disabled = true; btn.innerHTML = '⏳ …';
        try {
            const r = await fetch(`/tech/${TOKEN}/poses/${taskId}/status`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'status=en_cours',
                credentials: 'same-origin',
            });
            const data = await r.json().catch(() => ({}));
            if (r.ok && data.ok) {
                pose.dataset.taskStatus = 'en_cours';
                btn.innerHTML = '✓ Sur place';
                const dot = pose.querySelector('.pose-dot');
                if (dot) dot.style.background = '#3b82f6'; // bleu IN_PROGRESS
                toast('Position confirmée — bonne pose !', 'success');
            } else {
                btn.disabled = false; btn.innerHTML = original;
                toast(data.error || 'Erreur', 'error');
            }
        } catch (err) {
            btn.disabled = false; btn.innerHTML = original;
            toast('Erreur réseau', 'error');
        }
    });

    // ── Polling heartbeat — KPI live + détection nouvelle pose ────
    const HEARTBEAT_URL = "{{ route('tech.space.heartbeat', $token) }}";
    const POLL_MS = 20000;
    const liveDot = document.querySelector('[data-live-indicator]');
    // Plus haut ID de pose actuellement dans le DOM — sert de baseline pour
    // détecter "nouvelle pose assignée" entre 2 ticks heartbeat.
    let lastKnownTaskId = Array.from(document.querySelectorAll('.pose[data-task-id]'))
        .reduce((max, el) => Math.max(max, parseInt(el.dataset.taskId, 10) || 0), 0);
    let firstTick = true;

    function bumpKpi(name, newVal) {
        const el = document.querySelector(`[data-kpi-value="${name}"]`);
        if (!el) return;
        const oldVal = parseInt(el.textContent.trim(), 10) || 0;
        if (oldVal === newVal) return;
        el.textContent = newVal;
        el.classList.remove('kpi-bump');
        void el.offsetWidth; // force reflow pour relancer l'anim
        el.classList.add('kpi-bump');
    }

    async function heartbeatTick() {
        try {
            const r = await fetch(HEARTBEAT_URL, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!r.ok) return;
            const d = await r.json();
            if (!d.ok) return;

            // Pulse "live"
            if (liveDot) {
                liveDot.classList.add('is-pulsing');
                setTimeout(() => liveDot.classList.remove('is-pulsing'), 600);
            }

            bumpKpi('totalActive',     d.totalActive);
            bumpKpi('activeToday',     d.activeToday);
            bumpKpi('pigesSentToday',  d.pigesSentToday);
            bumpKpi('zonesTodayCount', d.zonesTodayCount);

            // Le sub-label "Aujourd'hui" rappelle aussi le nb posées du jour
            const doneTodayEl = document.querySelector('[data-done-today]');
            if (doneTodayEl) doneTodayEl.textContent = d.doneToday;

            // MAJ chip "Mes piges" badge (rejected si > 0, sinon total)
            const chipBadge = document.querySelector('[data-piges-chip-badge]');
            if (chipBadge) {
                const v = d.pigesRejected > 0 ? d.pigesRejected : d.pigesTotal;
                if (parseInt(chipBadge.textContent.trim(), 10) !== v) {
                    chipBadge.textContent = v;
                }
            }

            // Détection nouvelle pose assignée
            if (!firstTick && d.latestTaskId > lastKnownTaskId) {
                const banner = document.querySelector('[data-new-task-banner]');
                if (banner) banner.style.display = 'block';
            }
            lastKnownTaskId = Math.max(lastKnownTaskId, d.latestTaskId || 0);
            firstTick = false;
        } catch (e) { /* silencieux */ }
    }

    setTimeout(heartbeatTick, 1500);
    setInterval(heartbeatTick, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) heartbeatTick();
    });

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

    // ── Modale "justifier la pige malgré signalement" ───────
    // Le tech a signalé un problème non résolu sur cette pose mais
    // tente d'envoyer une pige : on impose une justification écrite
    // (min 10 caractères) qui sera tracée dans pige.notes côté admin.
    // Retourne une Promise<string|null> — null si annulation.
    function askContradictionReason(signalLabel) {
        return new Promise((resolve) => {
            // Construit la modale à la volée pour éviter d'alourdir le DOM
            // initial. Une seule instance à la fois (remove à la fermeture).
            const overlay = document.createElement('div');
            overlay.style.cssText = `position:fixed;inset:0;z-index:99999;background:rgba(15,23,42,.6);backdrop-filter:blur(2px);display:flex;align-items:center;justify-content:center;padding:16px`;
            overlay.innerHTML = `
                <div style="background:#fff;border-radius:14px;max-width:440px;width:100%;box-shadow:0 30px 80px -20px rgba(0,0,0,.4);overflow:hidden">
                    <div style="padding:16px 20px;background:linear-gradient(180deg,#fff7ed,#fff);border-bottom:1px solid #fed7aa;display:flex;align-items:flex-start;gap:10px">
                        <div style="font-size:22px;line-height:1">⚠️</div>
                        <div>
                            <div style="font-size:15px;font-weight:800;color:#9a3412;margin-bottom:2px">Pige malgré signalement</div>
                            <div style="font-size:12.5px;color:#b45309;line-height:1.45">
                                Tu as signalé ce panneau comme <strong>« ${signalLabel} »</strong>.
                                Si tu envoies quand même une pige, justifie-le (le superviseur le verra).
                            </div>
                        </div>
                    </div>
                    <div style="padding:16px 20px">
                        <label style="display:block;font-size:12.5px;font-weight:700;color:#1f2937;margin-bottom:6px">
                            Justification <span style="color:#ef4444">*</span>
                        </label>
                        <textarea id="contradiction-reason-input" rows="3"
                                  maxlength="1000"
                                  placeholder="Ex: panneau finalement remis en état, ou photo du visuel encore visible malgré la casse, etc."
                                  style="width:100%;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:13.5px;resize:vertical;font-family:inherit"></textarea>
                        <div id="contradiction-reason-counter" style="font-size:11px;color:#6b7280;text-align:right;margin-top:4px">0 / 10 min</div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:14px">
                            <button type="button" data-action="cancel"
                                    style="padding:9px 16px;background:#f3f4f6;border:1px solid #d1d5db;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;color:#4b5563">
                                Annuler la pige
                            </button>
                            <button type="button" data-action="confirm" disabled
                                    style="padding:9px 18px;background:#f97316;border:none;color:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;opacity:.5">
                                Envoyer quand même
                            </button>
                        </div>
                    </div>
                </div>`;
            document.body.appendChild(overlay);
            const ta      = overlay.querySelector('#contradiction-reason-input');
            const counter = overlay.querySelector('#contradiction-reason-counter');
            const btnOk   = overlay.querySelector('[data-action="confirm"]');
            const btnNo   = overlay.querySelector('[data-action="cancel"]');
            ta.focus();
            ta.addEventListener('input', () => {
                const n = ta.value.trim().length;
                counter.textContent = `${n} / 10 min`;
                const ok = n >= 10;
                btnOk.disabled = !ok;
                btnOk.style.opacity = ok ? '1' : '.5';
                btnOk.style.cursor  = ok ? 'pointer' : 'not-allowed';
            });
            function close(val) { overlay.remove(); resolve(val); }
            btnOk.addEventListener('click', () => {
                const v = ta.value.trim();
                if (v.length >= 10) close(v);
            });
            btnNo.addEventListener('click', () => close(null));
            overlay.addEventListener('click', (e) => { if (e.target === overlay) close(null); });
            document.addEventListener('keydown', function esc(ev) {
                if (ev.key === 'Escape') { close(null); document.removeEventListener('keydown', esc); }
            });
        });
    }

    // ── Upload photo + auto-completion ───────────────────────
    // ── Aperçu photo avant upload ────────────────────────────────
    // Le tech voit ce qu'il s'apprête à envoyer (flou, cadrage, etc.) et
    // peut "Reprendre" sans avoir envoyé une mauvaise photo. Retour :
    //   Promise<boolean> — true = envoyer ; false = annulé / reprendre
    function askPhotoPreview(file, panelRef) {
        return new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const overlay = document.createElement('div');
            overlay.style.cssText = `position:fixed;inset:0;z-index:99998;background:rgba(15,23,42,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:14px;animation:fadeIn .2s`;
            overlay.innerHTML = `
                <style>@keyframes fadeIn{from{opacity:0}to{opacity:1}}</style>
                <div style="color:#fff;font-size:13px;font-weight:600;margin-bottom:8px;text-align:center">
                    Aperçu de la photo${panelRef ? ' · <strong>'+panelRef+'</strong>' : ''}
                </div>
                <img src="${url}" alt="Aperçu" style="max-width:100%;max-height:60vh;border-radius:14px;box-shadow:0 16px 40px -8px rgba(0,0,0,.6);object-fit:contain;background:#000">
                <div style="color:#cbd5e1;font-size:11.5px;margin-top:10px;text-align:center;line-height:1.4">
                    Vérifie que le panneau et l'affichage sont nets et bien visibles avant d'envoyer.
                </div>
                <div style="display:flex;gap:10px;margin-top:18px;width:100%;max-width:380px">
                    <button type="button" data-act="cancel"
                            style="flex:1;padding:13px 14px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;-webkit-tap-highlight-color:transparent">
                        📷 Reprendre
                    </button>
                    <button type="button" data-act="confirm"
                            style="flex:1;padding:13px 14px;background:linear-gradient(135deg,#e8a020,#c2570d);color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:800;cursor:pointer;box-shadow:0 8px 20px -4px rgba(232,160,32,.5);-webkit-tap-highlight-color:transparent">
                        ✅ Envoyer
                    </button>
                </div>
            `;
            document.body.appendChild(overlay);
            const close = (val) => {
                URL.revokeObjectURL(url);
                overlay.remove();
                resolve(val);
            };
            overlay.querySelector('[data-act="confirm"]').addEventListener('click', () => close(true));
            overlay.querySelector('[data-act="cancel"]').addEventListener('click',  () => close(false));
            document.addEventListener('keydown', function esc(ev) {
                if (ev.key === 'Escape') { close(false); document.removeEventListener('keydown', esc); }
            });
        });
    }

    document.addEventListener('change', async (e) => {
        const input = e.target.closest('[data-photo-input]');
        if (!input || !input.files?.[0]) return;
        const label = input.closest('label');
        const pose  = label?.closest('[data-task-id]');
        const taskId = pose?.dataset.taskId;
        if (!taskId) return;

        // 0. Aperçu : le tech voit sa photo avant qu'on déclenche quoi que
        //    ce soit (compression, GPS, upload). S'il refuse, on reset
        //    l'input — il pourra reprendre sans pénalité.
        const preview = input.files[0];
        const panelRef = pose?.querySelector('.pose-ref')?.textContent?.trim()
                       || pose?.dataset.taskId;
        const confirmed = await askPhotoPreview(preview, panelRef);
        if (!confirmed) {
            input.value = '';
            return;
        }

        // Garde-fou contradiction : si signalement non résolu sur cette pose,
        // on demande une justification AVANT de compresser/uploader pour ne
        // pas perdre le travail si annulation. La justification part dans
        // FormData et le serveur la trace dans pige.notes.
        let contradictionReason = null;
        const blockingLabel = pose?.dataset.blockingSignalLabel;
        if (blockingLabel) {
            contradictionReason = await askContradictionReason(blockingLabel);
            if (contradictionReason === null) {
                // Tech a annulé → on reset l'input et on n'envoie rien.
                input.value = '';
                return;
            }
        }

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

        // Si on a une justification de contradiction signalement → on l'ajoute
        // pour que le serveur ne renvoie pas le 422 dédié et trace la note.
        if (contradictionReason) {
            form.append('contradicts_signalement_reason', contradictionReason);
        }

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

            // Fallback défensif : si le serveur réclame une justification
            // (data-attribute mal posé / cache JS périmé / route forcée),
            // on ouvre la modale ici, on re-tente l'upload avec la raison.
            if (res.status === 422 && data.requires_contradiction_reason) {
                label.innerHTML = originalLabel;
                label.style.pointerEvents = '';
                const reason = await askContradictionReason(data.signalement_label || 'un problème');
                if (reason === null) { input.value = ''; return; }
                form.set('contradicts_signalement_reason', reason);
                label.innerHTML = '⏳ Renvoi…';
                label.style.pointerEvents = 'none';
                const res2 = await fetch(url, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: form,
                });
                const data2 = await res2.json().catch(() => ({}));
                if (!res2.ok || !data2.ok) {
                    toast(data2.error || `Erreur ${res2.status}`, 'error');
                    label.innerHTML = originalLabel;
                    label.style.pointerEvents = '';
                    input.value = '';
                    return;
                }
                Object.assign(data, data2); // continue avec data du retry
            } else if (!res.ok || !data.ok) {
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

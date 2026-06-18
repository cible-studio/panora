{{-- Bloc <style> principal de tech-space.blade.php. Extrait tel quel en
     Phase 2 SM1 (rendu pixel-identique). Aucune modif des règles CSS. --}}
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

        /* ═══ MODULE SCALE — search Select2 / filtres / TOC / hero / distance ═══
           Conçu pour rester lisible à 500 / 2000 / 5000+ poses. Toutes les
           interactions sont indexées sur dataset pour rester O(N) sans
           reflow. Voir le bloc JS en bas pour la logique. */

        /* Barre de contrôles sticky : Select2 + bouton tri distance + bouton imprimer */
        .controls-bar {
            position: sticky; top: 0; z-index: 49;
            background: linear-gradient(180deg, #fffaf0 0%, rgba(255,250,240,.96) 100%);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            padding: 10px 16px 8px;
            margin: 0;
        }
        .controls-bar-row {
            display: flex; gap: 8px; align-items: center;
            max-width: 600px; margin: 0 auto;
        }
        .controls-bar .ctrl-btn {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; gap: 5px;
            padding: 9px 12px; border-radius: 10px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 12px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            text-decoration: none; white-space: nowrap;
            transition: border-color .15s, background .15s, transform .08s;
        }
        .controls-bar .ctrl-btn:active { transform: scale(.96); }
        .controls-bar .ctrl-btn.is-active {
            background: rgba(232,160,32,.10); color: var(--accent-dark);
            border-color: var(--accent);
        }
        .controls-bar .select2-container { flex: 1 1 auto; min-width: 0; }

        /* Style Select2 (champ search) aligné sur le look CIBLE */
        .controls-bar .select2-selection--single {
            height: 42px !important;
            border: 1px solid var(--border) !important;
            border-radius: 10px !important;
            background: var(--surface) !important;
            font-family: inherit !important;
        }
        .controls-bar .select2-selection__rendered {
            line-height: 42px !important;
            font-size: 13.5px !important;
            color: var(--text2) !important;
            padding-left: 14px !important;
        }
        .controls-bar .select2-selection__arrow {
            height: 42px !important;
        }
        .controls-bar .select2-selection__placeholder {
            color: var(--text3) !important;
        }
        .select2-dropdown {
            border: 1px solid var(--border) !important;
            border-radius: 12px !important;
            box-shadow: 0 24px 60px -16px rgba(0,0,0,.18) !important;
            overflow: hidden;
            z-index: 9999;
        }
        .select2-search--dropdown .select2-search__field {
            border: 1px solid var(--border) !important;
            border-radius: 8px !important;
            padding: 9px 12px !important;
            font-size: 13.5px !important;
            outline: none !important;
            box-shadow: none !important;
        }
        .select2-results__option--highlighted {
            background: rgba(232,160,32,.12) !important;
            color: var(--text) !important;
        }
        .s2-row {
            display: flex; gap: 10px; align-items: flex-start;
            padding: 4px 0;
        }
        .s2-row .s2-thumb {
            flex: 0 0 38px; width: 38px; height: 38px;
            border-radius: 8px; background-size: cover; background-position: center;
            background-color: var(--surface2); border: 1px solid var(--border);
            font-size: 18px; color: var(--text3);
            display: flex; align-items: center; justify-content: center;
        }
        .s2-row .s2-info { flex: 1; min-width: 0; }
        .s2-row .s2-ref {
            font-family: ui-monospace, monospace; font-weight: 800;
            color: var(--accent-dark); font-size: 13px;
            display: flex; align-items: center; gap: 6px;
        }
        .s2-row .s2-name {
            font-size: 12px; color: var(--text); margin-top: 1px;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .s2-row .s2-meta {
            font-size: 10.5px; color: var(--text3); margin-top: 2px;
            display: flex; gap: 6px; flex-wrap: wrap;
        }
        .s2-pill {
            display: inline-block; padding: 1px 6px; border-radius: 6px;
            font-size: 9.5px; font-weight: 700;
        }
        .s2-pill.late   { background: rgba(239,68,68,.12); color: #b91c1c; }
        .s2-pill.today  { background: rgba(59,130,246,.12); color: #1d4ed8; }
        .s2-pill.warn   { background: rgba(245,158,11,.14); color: #b45309; }
        .s2-pill.reject { background: rgba(239,68,68,.18); color: #b91c1c; }

        /* Banner cap SSR : affiché si total > rendered, oriente vers la search */
        .ssr-cap-banner {
            margin: 0 0 12px;
            padding: 10px 14px; border-radius: 12px;
            background: linear-gradient(135deg, rgba(232,160,32,.08), rgba(194,87,13,.04));
            border: 1px solid rgba(232,160,32,.25);
            color: var(--accent-dark);
            font-size: 12.5px; font-weight: 600; line-height: 1.45;
            display: flex; gap: 8px; align-items: flex-start;
        }
        .ssr-cap-banner strong { color: var(--accent-dark); font-weight: 800; }

        /* Chips filtres horizontaux scrollables */
        .filters-row {
            display: flex; gap: 6px; overflow-x: auto;
            padding: 0 0 6px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            margin: 8px 0 4px;
        }
        .filters-row::-webkit-scrollbar { display: none; }
        .filter-chip {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; gap: 5px;
            padding: 7px 12px; border-radius: 999px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 12px; font-weight: 600;
            cursor: pointer; font-family: inherit;
            white-space: nowrap;
            transition: all .15s;
        }
        .filter-chip:active { transform: scale(.96); }
        .filter-chip.is-active {
            background: var(--accent); color: #fff; border-color: var(--accent);
            box-shadow: 0 4px 14px -4px rgba(232,160,32,.5);
        }
        .filter-chip .chip-count {
            background: rgba(0,0,0,.06); padding: 1px 6px; border-radius: 999px;
            font-size: 10px; font-weight: 800;
            font-family: ui-monospace, monospace;
        }
        .filter-chip.is-active .chip-count { background: rgba(255,255,255,.25); color: #fff; }
        .filter-clear {
            flex: 0 0 auto;
            background: transparent; border: 0;
            color: var(--text3); font-size: 12px; font-weight: 700;
            cursor: pointer; text-decoration: underline;
            padding: 7px 4px;
        }

        /* Sommaire zones sticky (TOC) — scroll horizontal, 1 tap = jump zone */
        .zones-toc {
            position: sticky; top: 58px; z-index: 48;
            background: var(--bg);
            padding: 8px 16px; margin: 0;
            border-bottom: 1px solid var(--border);
            overflow: hidden;
        }
        .zones-toc-inner {
            display: flex; gap: 6px; overflow-x: auto;
            max-width: 600px; margin: 0 auto;
            -webkit-overflow-scrolling: touch; scrollbar-width: none;
        }
        .zones-toc-inner::-webkit-scrollbar { display: none; }
        .zone-toc-chip {
            flex: 0 0 auto;
            display: inline-flex; align-items: center; gap: 7px;
            padding: 6px 10px; border-radius: 10px;
            background: var(--surface); border: 1px solid var(--border);
            color: var(--text2); font-size: 11.5px; font-weight: 700;
            cursor: pointer; font-family: inherit;
            white-space: nowrap; text-decoration: none;
            transition: border-color .15s, transform .08s;
        }
        .zone-toc-chip:active { transform: scale(.97); }
        .zone-toc-chip.has-overdue { border-color: rgba(239,68,68,.35); color: #b91c1c; }
        .zone-toc-chip .ztc-prog {
            display: inline-block; width: 30px; height: 4px;
            background: var(--surface2); border-radius: 999px; overflow: hidden;
        }
        .zone-toc-chip .ztc-prog-fill {
            height: 100%; background: var(--accent); border-radius: 999px;
            transition: width .5s;
        }
        .zone-toc-chip .ztc-num {
            font-family: ui-monospace, monospace; font-size: 10.5px;
            opacity: .8;
        }

        /* Hero "Prochaine pose" — gros bouton focus, le tech voit ce qui compte */
        .next-pose-hero {
            margin: 0 0 14px;
            background: linear-gradient(135deg, #fff 0%, #fffaf0 100%);
            border: 2px solid var(--accent);
            border-radius: 16px;
            padding: 14px 14px 12px;
            box-shadow: 0 12px 32px -10px rgba(232,160,32,.25);
            position: relative; overflow: hidden;
        }
        .next-pose-hero::before {
            content: 'À FAIRE EN PREMIER';
            position: absolute; top: 8px; right: 12px;
            font-size: 9px; font-weight: 800; letter-spacing: 1px;
            color: var(--accent-dark); opacity: .65;
        }
        .next-pose-hero .nph-top {
            display: flex; gap: 12px; align-items: flex-start;
        }
        .next-pose-hero .nph-thumb {
            flex: 0 0 60px; width: 60px; height: 60px;
            border-radius: 12px;
            background-size: cover; background-position: center;
            background-color: var(--surface2); border: 1px solid var(--border);
            font-size: 26px; color: var(--text3);
            display: flex; align-items: center; justify-content: center;
        }
        .next-pose-hero .nph-info { flex: 1; min-width: 0; }
        .next-pose-hero .nph-ref {
            font-family: ui-monospace, monospace; font-weight: 800;
            font-size: 16px; color: var(--accent-dark);
        }
        .next-pose-hero .nph-name {
            font-size: 13px; color: var(--text); margin-top: 1px;
            font-weight: 600;
            overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .next-pose-hero .nph-meta {
            font-size: 11px; color: var(--text3); margin-top: 3px;
            display: flex; gap: 8px; flex-wrap: wrap;
        }
        .next-pose-hero .nph-meta .late {
            color: #b91c1c; font-weight: 700;
            background: rgba(239,68,68,.08); padding: 1px 6px; border-radius: 6px;
        }
        .next-pose-hero .nph-actions {
            display: flex; gap: 8px; margin-top: 12px;
        }
        .next-pose-hero .nph-act {
            flex: 1; min-height: 44px;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            font-size: 13px; font-weight: 800;
            border-radius: 10px; cursor: pointer;
            font-family: inherit; text-decoration: none;
            transition: transform .08s, box-shadow .15s;
        }
        .next-pose-hero .nph-act:active { transform: scale(.97); }
        .next-pose-hero .nph-act.go {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: #fff; border: none;
            box-shadow: 0 6px 16px -4px rgba(59,130,246,.45);
        }
        .next-pose-hero .nph-act.cam {
            background: linear-gradient(135deg, #e8a020, #c2570d);
            color: #fff; border: none;
            box-shadow: 0 6px 16px -4px rgba(232,160,32,.45);
        }
        .next-pose-hero .nph-act.cam input { display: none; }

        /* Affichage distance par card quand tri par distance activé */
        .pose-distance {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 1px 7px; border-radius: 999px;
            background: rgba(59,130,246,.10);
            color: #1d4ed8; font-weight: 700;
            font-size: 11px;
        }

        /* Badge numéro de tournée (TSP) — visible en mode "Optimiser tournée" */
        .pose-line.tour-mode .pose-thumb::after {
            content: attr(data-tour-step);
            position: absolute;
            top: -8px; left: -8px;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff; width: 22px; height: 22px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800;
            box-shadow: 0 4px 10px -2px rgba(34,197,94,.5);
            border: 2px solid #fff;
            font-family: ui-monospace, monospace;
        }
        .pose-line.tour-mode .pose-thumb { position: relative; }
        .pose-line.tour-mode {
            border-color: rgba(34,197,94,.35);
            box-shadow: 0 0 0 1px rgba(34,197,94,.15), 0 4px 12px -4px rgba(34,197,94,.2);
        }
        .pose-tour-leg {
            display: inline-flex; align-items: center; gap: 3px;
            padding: 1px 7px; border-radius: 999px;
            background: rgba(34,197,94,.12);
            color: #15803d; font-weight: 700;
            font-size: 11px;
        }

        /* Banner total tournée affiché en haut.
           ⚠ Pas de `display: flex` dans la base (sinon override `display: none`
           et la bannière apparaît au load avant tout calcul). Seule `.show`
           bascule en flex — sans ça "Quitter" était inopérant (la classe
           tombait mais le display restait flex). */
        .tour-summary {
            display: none;
            margin: 0 0 12px;
            padding: 10px 14px; border-radius: 12px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid rgba(34,197,94,.35);
            color: #166534;
            font-size: 12.5px; font-weight: 700; line-height: 1.45;
            gap: 10px; align-items: center;
        }
        .tour-summary.show { display: flex; }
        .tour-summary strong { color: #14532d; }
        .tour-summary button {
            margin-left: auto;
            background: transparent; border: 1px solid rgba(34,197,94,.4);
            color: #166534; padding: 5px 10px; border-radius: 8px;
            font-size: 11.5px; font-weight: 700; cursor: pointer; font-family: inherit;
        }

        /* Bandeau offline (Service Worker fallback) */
        .offline-banner {
            display: none; position: fixed; left: 0; right: 0; bottom: 0;
            background: #1f2937; color: #fff;
            padding: 10px 16px;
            font-size: 12.5px; font-weight: 700; text-align: center;
            z-index: 9999;
            border-top: 1px solid #374151;
            transform: translateY(100%);
            transition: transform .3s;
            padding-bottom: calc(10px + env(safe-area-inset-bottom));
        }
        .offline-banner.show { display: block; transform: translateY(0); }

        /* Virtualisation : cards pas encore rendues = placeholder léger */
        .pose-line.lazy-pending {
            min-height: 80px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
            background-size: 200% 100%;
            border-color: transparent;
            animation: lazyShimmer 1.4s infinite;
        }
        @keyframes lazyShimmer {
            from { background-position: 200% 50%; }
            to   { background-position: -200% 50%; }
        }
        .pose-line.lazy-pending > * { visibility: hidden; }

        /* PRINT — quand on imprime depuis l'espace tech, on cache tout sauf
           ce qui est utile (en réalité on redirige vers /route-sheet). */
        @media print {
            .header, .controls-bar, .zones-toc, .new-task-banner,
            #toast-container, .offline-banner { display: none !important; }
        }
</style>

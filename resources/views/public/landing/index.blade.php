<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Panora — La plateforme métier des régies OOH</title>
    <meta name="description" content="Panora est la plateforme complète pour gérer votre régie d'affichage extérieur : panneaux, campagnes, facturation FNE, équipes terrain, taxes communales. Éprouvée en production.">

    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════════════
           Landing Panora — vitrine commerciale — WIP develop
           Charte : orange #e8a020/#ea580c · violet #8b5cf6 · bleu #3b82f6
           ═══════════════════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #0f172a;
            line-height: 1.55;
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
        }
        img { max-width: 100%; height: auto; display: block; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; }

        :root {
            --orange:      #ea580c;
            --orange-2:    #e8a020;
            --violet:      #8b5cf6;
            --violet-2:    #7c3aed;
            --blue:        #3b82f6;
            --green:       #22c55e;
            --ink:         #0f172a;
            --ink-2:       #334155;
            --ink-3:       #64748b;
            --ink-4:       #94a3b8;
            --bg:          #ffffff;
            --bg-soft:     #f8fafc;
            --border:      #e2e8f0;
            --border-soft: #f1f5f9;
            --shadow-sm:   0 1px 3px rgba(15,23,42,.06);
            --shadow-md:   0 6px 20px -6px rgba(15,23,42,.14);
            --shadow-lg:   0 20px 50px -15px rgba(15,23,42,.2);
        }

        .container { max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        @media (max-width: 640px) { .container { padding: 0 18px; } }

        /* ── Nav ─────────────────────────────────────────────── */
        .nav {
            position: sticky; top: 0; z-index: 50;
            background: rgba(255,255,255,.92);
            backdrop-filter: saturate(180%) blur(10px);
            -webkit-backdrop-filter: saturate(180%) blur(10px);
            border-bottom: 1px solid var(--border);
        }
        .nav-inner {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px; max-width: 1120px; margin: 0 auto;
        }
        .nav-brand {
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 900; font-size: 18px; color: var(--ink);
            letter-spacing: -.5px;
        }
        .nav-brand .dot {
            width: 12px; height: 12px; border-radius: 3px;
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            box-shadow: 0 2px 6px rgba(234,88,12,.35);
        }
        .nav-links { display: flex; gap: 22px; align-items: center; }
        .nav-links a {
            font-size: 13.5px; font-weight: 600; color: var(--ink-2);
            transition: color .15s;
        }
        .nav-links a:hover { color: var(--orange); }
        .nav-cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 16px; border-radius: 10px;
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            color: #fff !important; font-weight: 800; font-size: 13px;
            box-shadow: 0 4px 12px -2px rgba(234,88,12,.35);
            transition: transform .1s, box-shadow .15s;
        }
        .nav-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 18px -2px rgba(234,88,12,.5); }
        @media (max-width: 720px) {
            .nav-links a:not(.nav-cta) { display: none; }
        }

        /* ── Hero ────────────────────────────────────────────── */
        .hero {
            padding: 80px 0 90px;
            background:
                radial-gradient(circle at 15% 10%, rgba(232,160,32,.10), transparent 40%),
                radial-gradient(circle at 85% 20%, rgba(139,92,246,.08), transparent 45%),
                linear-gradient(180deg, #fffbf5 0%, #ffffff 100%);
            position: relative; overflow: hidden;
        }
        .hero-grid {
            display: grid; grid-template-columns: 1.15fr .85fr; gap: 60px; align-items: center;
        }
        @media (max-width: 900px) {
            .hero-grid { grid-template-columns: 1fr; gap: 50px; }
            .hero { padding: 50px 0 60px; }
        }
        .hero-eyebrow {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 999px;
            background: rgba(234,88,12,.09); color: #b45309;
            font-size: 11.5px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .8px;
            margin-bottom: 18px;
        }
        .hero-eyebrow::before {
            content: ''; width: 6px; height: 6px; border-radius: 50%;
            background: var(--orange); box-shadow: 0 0 0 3px rgba(234,88,12,.2);
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%,100% { box-shadow: 0 0 0 3px rgba(234,88,12,.2); }
            50%     { box-shadow: 0 0 0 6px rgba(234,88,12,.05); }
        }
        .hero-title {
            font-size: clamp(32px, 4.5vw, 52px);
            font-weight: 900; line-height: 1.08;
            letter-spacing: -1.5px; color: var(--ink);
            margin-bottom: 20px;
        }
        .hero-title span {
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            -webkit-background-clip: text; background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-sub {
            font-size: clamp(15px, 1.5vw, 18px);
            color: var(--ink-3); max-width: 540px;
            margin-bottom: 30px; line-height: 1.6;
        }
        .hero-ctas { display: flex; gap: 12px; flex-wrap: wrap; }
        .btn-primary, .btn-secondary {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 26px; border-radius: 12px;
            font-weight: 800; font-size: 15px;
            border: none; transition: transform .1s, box-shadow .15s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            color: #fff;
            box-shadow: 0 6px 20px -4px rgba(234,88,12,.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 28px -4px rgba(234,88,12,.55); }
        .btn-secondary {
            background: #fff; color: var(--ink);
            border: 1.5px solid var(--border);
        }
        .btn-secondary:hover { border-color: var(--orange); color: var(--orange); }

        .hero-proof {
            display: flex; gap: 20px; margin-top: 32px; flex-wrap: wrap;
            font-size: 12.5px; color: var(--ink-3);
        }
        .hero-proof strong { color: var(--ink); font-weight: 800; }
        .hero-proof-item { display: flex; align-items: center; gap: 6px; }
        .hero-proof-item svg { color: var(--green); flex-shrink: 0; }

        /* Mockup hero */
        .hero-mockup {
            position: relative;
        }
        .hero-mockup-main {
            background: linear-gradient(135deg, #1e293b, #0f172a);
            border-radius: 20px;
            padding: 12px 12px 0;
            box-shadow: var(--shadow-lg);
            position: relative;
            transform: perspective(1200px) rotateY(-6deg) rotateX(4deg);
        }
        .hero-mockup-main::before {
            content: '';
            position: absolute; top: 10px; left: 14px;
            width: 8px; height: 8px; border-radius: 50%; background: #ef4444;
            box-shadow: 14px 0 0 #f59e0b, 28px 0 0 #22c55e;
        }
        .hero-mockup-screen {
            background: #fff; border-radius: 8px 8px 0 0;
            padding: 40px 20px 20px; min-height: 320px;
            display: flex; flex-direction: column; gap: 12px;
        }
        .hero-mockup-bar {
            height: 10px; background: linear-gradient(90deg, var(--orange), var(--orange-2));
            border-radius: 999px; width: 60%;
        }
        .hero-mockup-line {
            height: 8px; background: var(--border-soft);
            border-radius: 999px;
        }
        .hero-mockup-line.short { width: 40%; }
        .hero-mockup-line.medium { width: 65%; }
        .hero-mockup-cards {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px;
            margin-top: 8px;
        }
        .hero-mockup-card {
            background: linear-gradient(135deg, #fffbf0, #fff7e6);
            border: 1px solid rgba(232,160,32,.2);
            border-radius: 8px; padding: 10px;
            height: 60px;
        }
        .hero-mockup-card:nth-child(2) {
            background: linear-gradient(135deg, #f5f0ff, #ede4ff);
            border-color: rgba(139,92,246,.2);
        }
        .hero-mockup-card:nth-child(3) {
            background: linear-gradient(135deg, #f0f9ff, #dbeafe);
            border-color: rgba(59,130,246,.2);
        }
        .hero-mockup-mini {
            position: absolute; bottom: -30px; right: -20px;
            background: #fff; border-radius: 16px;
            padding: 14px 18px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
            transform: rotate(3deg);
        }
        .hero-mockup-mini-icon {
            width: 36px; height: 36px; border-radius: 10px;
            background: linear-gradient(135deg, var(--violet), var(--violet-2));
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-size: 16px;
        }
        .hero-mockup-mini-label {
            font-size: 11px; color: var(--ink-3); font-weight: 700;
            text-transform: uppercase; letter-spacing: .5px;
        }
        .hero-mockup-mini-value {
            font-size: 15px; color: var(--ink); font-weight: 900;
        }
        @media (max-width: 900px) {
            .hero-mockup-main { transform: none; }
            .hero-mockup-mini { display: none; }
        }

        /* ── Section commune ─────────────────────────────────── */
        section.block { padding: 90px 0; }
        @media (max-width: 720px) { section.block { padding: 60px 0; } }
        .block-header { text-align: center; max-width: 720px; margin: 0 auto 60px; }
        .block-eyebrow {
            display: inline-block; padding: 4px 12px; border-radius: 999px;
            background: rgba(139,92,246,.10); color: var(--violet-2);
            font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .8px;
            margin-bottom: 14px;
        }
        .block-title {
            font-size: clamp(26px, 3vw, 36px); font-weight: 900;
            letter-spacing: -1px; margin-bottom: 14px;
        }
        .block-sub {
            font-size: 16px; color: var(--ink-3); line-height: 1.6;
        }

        /* ── Modules (grille 6) ──────────────────────────────── */
        .modules-grid {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;
        }
        @media (max-width: 900px) { .modules-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .modules-grid { grid-template-columns: 1fr; } }
        .module-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 16px; padding: 24px;
            transition: transform .15s, box-shadow .2s, border-color .15s;
        }
        .module-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: rgba(234,88,12,.25);
        }
        .module-icon {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; margin-bottom: 14px;
        }
        .module-icon.o { background: rgba(234,88,12,.10); }
        .module-icon.v { background: rgba(139,92,246,.10); }
        .module-icon.b { background: rgba(59,130,246,.10); }
        .module-icon.g { background: rgba(34,197,94,.10); }
        .module-title {
            font-size: 16px; font-weight: 800; margin-bottom: 6px;
            color: var(--ink);
        }
        .module-desc {
            font-size: 13.5px; color: var(--ink-3); line-height: 1.55;
        }

        /* ── Différenciateurs (3 blocs alternés) ─────────────── */
        .diff-block {
            display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: center;
            margin-bottom: 90px;
        }
        .diff-block:last-child { margin-bottom: 0; }
        .diff-block.reverse { direction: rtl; }
        .diff-block.reverse > * { direction: ltr; }
        @media (max-width: 800px) {
            .diff-block, .diff-block.reverse { grid-template-columns: 1fr; direction: ltr; gap: 30px; margin-bottom: 60px; }
        }
        .diff-tag {
            display: inline-block; padding: 4px 10px; border-radius: 6px;
            background: rgba(234,88,12,.08); color: var(--orange);
            font-size: 11px; font-weight: 800;
            text-transform: uppercase; letter-spacing: .8px;
            margin-bottom: 12px;
        }
        .diff-tag.v { background: rgba(139,92,246,.10); color: var(--violet-2); }
        .diff-tag.b { background: rgba(59,130,246,.10); color: #1d4ed8; }
        .diff-title {
            font-size: clamp(22px, 2.4vw, 30px); font-weight: 900;
            letter-spacing: -.6px; margin-bottom: 14px;
        }
        .diff-desc {
            font-size: 15px; color: var(--ink-3); line-height: 1.65;
            margin-bottom: 18px;
        }
        .diff-list { list-style: none; padding: 0; margin: 0; }
        .diff-list li {
            display: flex; align-items: flex-start; gap: 10px;
            padding: 8px 0; font-size: 14px; color: var(--ink-2);
        }
        .diff-list li::before {
            content: '✓'; flex-shrink: 0;
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--green); color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 900;
            margin-top: 1px;
        }
        .diff-visual {
            aspect-ratio: 4 / 3;
            background: linear-gradient(135deg, #f8fafc, #fff);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow-md);
            padding: 20px;
            display: flex; align-items: center; justify-content: center;
            position: relative; overflow: hidden;
        }
        .diff-visual .placeholder {
            color: var(--ink-4); font-size: 12px; font-style: italic;
            text-align: center; padding: 20px;
        }
        .diff-visual .mockup-ph {
            width: 200px; aspect-ratio: 9 / 19; background: linear-gradient(180deg, #1e293b 0 60px, #fff 60px);
            border-radius: 26px; padding: 10px;
            box-shadow: 0 20px 50px -10px rgba(15,23,42,.35);
            position: relative;
        }
        .diff-visual .mockup-ph::before {
            content: ''; position: absolute;
            top: 8px; left: 50%; transform: translateX(-50%);
            width: 60px; height: 18px; border-radius: 0 0 12px 12px; background: #0f172a;
        }
        .diff-visual .mockup-btn {
            background: linear-gradient(135deg, var(--green), #16a34a);
            color: #fff; border-radius: 8px; padding: 10px;
            font-size: 11px; font-weight: 800; text-align: center;
            margin: 8px 6px;
        }
        .diff-visual .mockup-btn.v { background: linear-gradient(135deg, var(--violet), var(--violet-2)); }
        .diff-visual .mockup-btn.o { background: linear-gradient(135deg, var(--orange-2), var(--orange)); }
        .diff-visual .mockup-chrono {
            background: rgba(139,92,246,.08); border: 1px solid rgba(139,92,246,.2);
            border-radius: 10px; padding: 10px; margin: 8px 6px;
            text-align: center;
        }
        .diff-visual .mockup-chrono-lbl { font-size: 9px; color: var(--violet-2); font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .diff-visual .mockup-chrono-val { font-size: 16px; color: var(--violet-2); font-weight: 900; margin-top: 4px; }

        /* ── Comment démarrer (steps) ────────────────────────── */
        .steps-grid {
            display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;
        }
        @media (max-width: 800px) { .steps-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .steps-grid { grid-template-columns: 1fr; } }
        .step-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 14px; padding: 22px 20px;
            position: relative;
        }
        .step-num {
            position: absolute; top: -14px; left: 20px;
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            color: #fff; font-weight: 900; font-size: 14px;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px -2px rgba(234,88,12,.4);
        }
        .step-title {
            font-size: 15px; font-weight: 800; margin: 10px 0 8px;
            color: var(--ink);
        }
        .step-desc { font-size: 13px; color: var(--ink-3); line-height: 1.55; }

        /* ── FAQ ─────────────────────────────────────────────── */
        .faq-list {
            max-width: 780px; margin: 0 auto;
            display: flex; flex-direction: column; gap: 10px;
        }
        .faq-item {
            background: var(--bg-soft); border: 1px solid var(--border-soft);
            border-radius: 12px; overflow: hidden;
        }
        .faq-item summary {
            padding: 18px 22px; font-weight: 700; font-size: 15px;
            color: var(--ink); cursor: pointer;
            list-style: none;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px;
        }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-item summary::after {
            content: '+'; color: var(--orange);
            font-size: 22px; font-weight: 300; line-height: 1;
            transition: transform .2s;
        }
        .faq-item[open] summary::after { content: '−'; }
        .faq-item .faq-a {
            padding: 0 22px 20px; font-size: 14px; color: var(--ink-3); line-height: 1.65;
        }

        /* ── Formulaire ──────────────────────────────────────── */
        .cta-section {
            padding: 90px 0;
            background:
                radial-gradient(circle at 80% 20%, rgba(234,88,12,.10), transparent 45%),
                radial-gradient(circle at 20% 80%, rgba(139,92,246,.08), transparent 40%),
                linear-gradient(180deg, #fffbf5 0%, #ffffff 100%);
        }
        .cta-grid {
            display: grid; grid-template-columns: 1fr 1.15fr; gap: 60px; align-items: start;
        }
        @media (max-width: 900px) { .cta-grid { grid-template-columns: 1fr; gap: 40px; } }
        .cta-copy .cta-title {
            font-size: clamp(26px, 3vw, 34px); font-weight: 900;
            letter-spacing: -.8px; margin-bottom: 16px;
        }
        .cta-copy .cta-sub {
            font-size: 15px; color: var(--ink-3); line-height: 1.65; margin-bottom: 24px;
        }
        .cta-copy .cta-contact-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px 16px; background: #fff; border: 1px solid var(--border);
            border-radius: 12px; margin-bottom: 10px;
        }
        .cta-copy .cta-contact-icon {
            width: 40px; height: 40px; border-radius: 10px;
            background: rgba(234,88,12,.08); color: var(--orange);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .cta-copy .cta-contact-label {
            font-size: 11px; color: var(--ink-3); text-transform: uppercase;
            letter-spacing: .5px; font-weight: 700;
        }
        .cta-copy .cta-contact-value {
            font-size: 14px; color: var(--ink); font-weight: 700;
        }

        form.demo-form {
            background: #fff; border: 1px solid var(--border);
            border-radius: 20px; padding: 30px;
            box-shadow: var(--shadow-md);
        }
        .form-row {
            display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px;
        }
        @media (max-width: 560px) { .form-row { grid-template-columns: 1fr; } }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label {
            font-size: 12px; font-weight: 700; color: var(--ink-2);
        }
        .form-group label .req { color: var(--orange); }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 11px 14px;
            background: var(--bg-soft);
            border: 1.5px solid transparent; border-radius: 10px;
            font-family: inherit; font-size: 14px; color: var(--ink);
            transition: border-color .15s, background .15s;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none; border-color: var(--orange);
            background: #fff;
        }
        .form-group textarea { resize: vertical; min-height: 90px; }
        .form-hp { position: absolute; left: -9999px; opacity: 0; pointer-events: none; }
        .form-submit {
            width: 100%; margin-top: 10px;
            padding: 14px 20px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
            color: #fff; font-weight: 800; font-size: 15px;
            box-shadow: 0 6px 20px -4px rgba(234,88,12,.4);
            transition: transform .1s, box-shadow .15s;
        }
        .form-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px -4px rgba(234,88,12,.55); }
        .form-note { font-size: 11.5px; color: var(--ink-4); text-align: center; margin-top: 12px; }

        .flash-success {
            padding: 14px 16px; background: rgba(34,197,94,.08);
            border: 1px solid rgba(34,197,94,.3); color: #166534;
            border-radius: 12px; margin-bottom: 16px;
            font-size: 14px; font-weight: 700;
        }
        .flash-error {
            padding: 14px 16px; background: rgba(239,68,68,.08);
            border: 1px solid rgba(239,68,68,.3); color: #b91c1c;
            border-radius: 12px; margin-bottom: 16px;
            font-size: 14px; font-weight: 700;
        }
        .field-errors {
            font-size: 12px; color: #b91c1c; margin-top: 2px;
        }

        /* ── Footer ──────────────────────────────────────────── */
        footer {
            padding: 40px 0 24px;
            background: #0f172a; color: #cbd5e1;
        }
        .footer-inner {
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 16px;
        }
        .footer-brand {
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 900; font-size: 16px; color: #fff;
        }
        .footer-brand .dot {
            width: 10px; height: 10px; border-radius: 3px;
            background: linear-gradient(135deg, var(--orange-2), var(--orange));
        }
        .footer-nav a {
            font-size: 12.5px; color: #94a3b8; margin: 0 10px;
            transition: color .15s;
        }
        .footer-nav a:hover { color: #fff; }
        .footer-copy {
            width: 100%; text-align: center; font-size: 11.5px;
            color: #64748b; margin-top: 20px;
            padding-top: 20px; border-top: 1px solid #1e293b;
        }
    </style>
</head>
<body>

    {{-- ═══════════════════ NAV ═══════════════════ --}}
    <nav class="nav">
        <div class="nav-inner">
            <a href="{{ route('landing.show') }}" class="nav-brand">
                <span class="dot"></span> Panora
            </a>
            <div class="nav-links">
                <a href="#modules">Modules</a>
                <a href="#difference">Différences</a>
                <a href="#steps">Démarrage</a>
                <a href="#faq">FAQ</a>
                <a href="#demo" class="nav-cta">Demander une démo</a>
            </div>
        </div>
    </nav>

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-copy">
                <span class="hero-eyebrow">Éprouvé en production</span>
                <h1 class="hero-title">
                    La plateforme métier<br>
                    des <span>régies OOH</span>.
                </h1>
                <p class="hero-sub">
                    Panneaux, campagnes, facturation FNE, équipes terrain, taxes communales — tout au même endroit. Pensé pour la réalité africaine, pas pour un template global.
                </p>
                <div class="hero-ctas">
                    <a href="#demo" class="btn-primary">
                        Demander une démo
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                    <a href="#modules" class="btn-secondary">Voir en détail ↓</a>
                </div>
                <div class="hero-proof">
                    <div class="hero-proof-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Utilisé <strong>chaque jour</strong> sur le terrain</span>
                    </div>
                    <div class="hero-proof-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Conforme <strong>FNE Côte d'Ivoire</strong></span>
                    </div>
                    <div class="hero-proof-item">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Espace tech <strong>PWA mobile</strong></span>
                    </div>
                </div>
            </div>
            <div class="hero-mockup">
                <div class="hero-mockup-main">
                    <div class="hero-mockup-screen">
                        <div class="hero-mockup-bar"></div>
                        <div class="hero-mockup-line medium"></div>
                        <div class="hero-mockup-line short"></div>
                        <div class="hero-mockup-cards">
                            <div class="hero-mockup-card"></div>
                            <div class="hero-mockup-card"></div>
                            <div class="hero-mockup-card"></div>
                        </div>
                        <div class="hero-mockup-line medium" style="margin-top:6px"></div>
                        <div class="hero-mockup-line"></div>
                        <div class="hero-mockup-line short"></div>
                    </div>
                </div>
                <div class="hero-mockup-mini">
                    <div class="hero-mockup-mini-icon">⏱️</div>
                    <div>
                        <div class="hero-mockup-mini-label">Sur place</div>
                        <div class="hero-mockup-mini-value">12 min 34 s</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULES ═══════════════════ --}}
    <section class="block" id="modules">
        <div class="container">
            <div class="block-header">
                <span class="block-eyebrow">6 modules · 1 seule plateforme</span>
                <h2 class="block-title">Tout ce qu'une régie OOH utilise vraiment.</h2>
                <p class="block-sub">
                    Fini l'Excel qui traine, le WhatsApp qui déborde, la facturation qui prend 3 jours. Panora couvre chaque étape du cycle : de la vente à l'encaissement.
                </p>
            </div>
            <div class="modules-grid">
                <div class="module-card">
                    <div class="module-icon o">🗺️</div>
                    <h3 class="module-title">Inventaire panneaux</h3>
                    <p class="module-desc">Géolocalisation GPS, photos, dimensions, tarifs, statut, historique. Recherche instantanée sur 500+ panneaux.</p>
                </div>
                <div class="module-card">
                    <div class="module-icon v">📅</div>
                    <h3 class="module-title">Campagnes & réservations</h3>
                    <p class="module-desc">Planification visuelle, conflits détectés automatiquement, propositions PDF pour vos clients, workflow de validation.</p>
                </div>
                <div class="module-card">
                    <div class="module-icon b">📱</div>
                    <h3 class="module-title">Espace tech mobile</h3>
                    <p class="module-desc">PWA installable, offline-first. Vos techniciens marquent leur départ, arrivée, prennent la photo — vous suivez en temps réel.</p>
                </div>
                <div class="module-card">
                    <div class="module-icon o">💰</div>
                    <h3 class="module-title">Facturation FNE</h3>
                    <p class="module-desc">Génération de factures conformes à la norme FNE Côte d'Ivoire. Numérotation, calcul TVA, échéances, relances automatiques.</p>
                </div>
                <div class="module-card">
                    <div class="module-icon g">🏛️</div>
                    <h3 class="module-title">Taxes communales</h3>
                    <p class="module-desc">Snapshot des taux à la date d'émission. Suivi paiement mairie par mairie. Fini les mauvaises surprises en fin d'année.</p>
                </div>
                <div class="module-card">
                    <div class="module-icon v">📊</div>
                    <h3 class="module-title">Pilotage & rapports</h3>
                    <p class="module-desc">Dashboards KPIs live, performance commerciale, taux d'occupation, rapports Excel et PDF harmonisés, exports comptables.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ DIFFÉRENCIATEURS ═══════════════════ --}}
    <section class="block" id="difference" style="background:var(--bg-soft)">
        <div class="container">
            <div class="block-header">
                <span class="block-eyebrow">Ce qui fait la différence</span>
                <h2 class="block-title">Pensé pour le terrain, pas pour un slide.</h2>
                <p class="block-sub">
                    Panora est né de l'exploitation quotidienne d'une régie active. Chaque fonctionnalité résout un vrai problème vécu — pas une case cochée dans un cahier des charges.
                </p>
            </div>

            <div class="diff-block">
                <div>
                    <span class="diff-tag v">Terrain temps réel</span>
                    <h3 class="diff-title">Vos techniciens dans la poche.</h3>
                    <p class="diff-desc">
                        Une PWA installable, offline-first, pensée pour un usage à une main dans la rue. 3 boutons — Y aller, Arrivé, Photo — et un chrono qui tourne. Vous voyez tout depuis l'admin, sans appeler.
                    </p>
                    <ul class="diff-list">
                        <li>Géolocalisation + Google Maps intégré</li>
                        <li>Chrono automatique départ → arrivée → photo</li>
                        <li>Exclusivité stricte : une pose active à la fois</li>
                        <li>Signalement de problème en 1 tap</li>
                    </ul>
                </div>
                <div class="diff-visual">
                    <div class="mockup-ph">
                        <div style="height:38px"></div>
                        <div style="padding:6px 8px;font-size:9px;color:#64748b;font-weight:700">← Retour</div>
                        <div style="padding:0 8px;font-size:11px;color:#ea580c;font-weight:800">ABG-002</div>
                        <div style="padding:0 8px 6px;font-size:10px;color:#0f172a;font-weight:700">Rond point hôtel</div>
                        <div class="mockup-btn">🗺️ Y aller en voiture</div>
                        <div class="mockup-btn v">📍 Je suis arrivé</div>
                        <div class="mockup-btn o">📷 Photo (fin)</div>
                        <div class="mockup-chrono">
                            <div class="mockup-chrono-lbl">⏱️ Sur place</div>
                            <div class="mockup-chrono-val">12 min 34 s</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="diff-block reverse">
                <div>
                    <span class="diff-tag">Conformité fiscale</span>
                    <h3 class="diff-title">FNE + taxes communales, sans erreur.</h3>
                    <p class="diff-desc">
                        La seule solution qui gère à la fois la Facture Normalisée Électronique (FNE Côte d'Ivoire) et les taxes communales par snapshot. Chaque facture émise fige le taux du jour — plus de recalcul en boucle, plus de litige avec la mairie.
                    </p>
                    <ul class="diff-list">
                        <li>Numérotation FNE automatique conforme</li>
                        <li>Snapshot du taux communal à l'émission</li>
                        <li>Audit trail complet (qui, quoi, quand)</li>
                        <li>Export comptable Excel 1 clic</li>
                    </ul>
                </div>
                <div class="diff-visual">
                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;width:80%;box-shadow:0 8px 20px rgba(0,0,0,.08)">
                        <div style="display:flex;justify-content:space-between;padding-bottom:8px;border-bottom:1px solid #e2e8f0;margin-bottom:10px">
                            <div style="font-size:9px;color:#64748b;font-weight:700;text-transform:uppercase">Facture FNE</div>
                            <div style="font-size:10px;color:#0f172a;font-weight:800">FNE-2026-0842</div>
                        </div>
                        <div style="height:6px;background:#f1f5f9;border-radius:3px;margin-bottom:6px;width:70%"></div>
                        <div style="height:6px;background:#f1f5f9;border-radius:3px;margin-bottom:14px;width:50%"></div>
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:#0f172a;font-weight:700;padding:6px 0">
                            <span>Total HT</span><span>2 450 000</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;padding:6px 0">
                            <span>TVA 18%</span><span>441 000</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:11px;color:#64748b;padding:6px 0">
                            <span>Taxe commune</span><span>98 000</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;color:#ea580c;font-weight:900;padding:10px 0 0;border-top:1px solid #e2e8f0;margin-top:6px">
                            <span>Total TTC</span><span>2 989 000 FCFA</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="diff-block">
                <div>
                    <span class="diff-tag b">Vision direction</span>
                    <h3 class="diff-title">Le pouls de la régie, sur un écran.</h3>
                    <p class="diff-desc">
                        Dashboards live sur le CA, le taux d'occupation par commune, les performances commerciales, les techniciens en activité. Une seule page pour comprendre où va la régie ce mois-ci.
                    </p>
                    <ul class="diff-list">
                        <li>KPIs temps réel (poses, CA, encaissement)</li>
                        <li>Classement commerciaux personnalisable</li>
                        <li>Rapports Excel & PDF harmonisés</li>
                        <li>Alertes intelligentes (retards, litiges)</li>
                    </ul>
                </div>
                <div class="diff-visual">
                    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px;width:90%;box-shadow:0 8px 20px rgba(0,0,0,.08)">
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
                            <div style="background:linear-gradient(135deg,#fff7ed,#fef3c7);border-radius:8px;padding:10px;text-align:center">
                                <div style="font-size:15px;font-weight:900;color:#ea580c">78%</div>
                                <div style="font-size:8px;color:#64748b;font-weight:700">OCCUPATION</div>
                            </div>
                            <div style="background:linear-gradient(135deg,#f5f0ff,#ede4ff);border-radius:8px;padding:10px;text-align:center">
                                <div style="font-size:15px;font-weight:900;color:#7c3aed">42</div>
                                <div style="font-size:8px;color:#64748b;font-weight:700">POSES/JOUR</div>
                            </div>
                            <div style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);border-radius:8px;padding:10px;text-align:center">
                                <div style="font-size:15px;font-weight:900;color:#15803d">6</div>
                                <div style="font-size:8px;color:#64748b;font-weight:700">TECHS LIVE</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:flex-end;gap:4px;height:70px">
                            @for($i=0;$i<12;$i++)
                                <div style="flex:1;background:linear-gradient(180deg,#ea580c,#e8a020);border-radius:2px 2px 0 0;height:{{ rand(30,100) }}%;opacity:.85"></div>
                            @endfor
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ STEPS ═══════════════════ --}}
    <section class="block" id="steps">
        <div class="container">
            <div class="block-header">
                <span class="block-eyebrow">Comment démarrer</span>
                <h2 class="block-title">De la première démo à la production : 2 à 4 semaines.</h2>
                <p class="block-sub">
                    On n'est pas un self-service. On vous accompagne du diagnostic à la formation de vos équipes, en passant par l'import de vos données existantes.
                </p>
            </div>
            <div class="steps-grid">
                <div class="step-card">
                    <div class="step-num">1</div>
                    <h3 class="step-title">Démo personnalisée</h3>
                    <p class="step-desc">30 min visio pour comprendre votre régie et vous montrer Panora en conditions réelles.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">2</div>
                    <h3 class="step-title">Onboarding & import</h3>
                    <p class="step-desc">On récupère vos panneaux, clients, campagnes en cours depuis Excel ou autre outil.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">3</div>
                    <h3 class="step-title">Formation équipe</h3>
                    <p class="step-desc">Session admin (dashboard, factures) + session terrain (mobile) pour vos techniciens.</p>
                </div>
                <div class="step-card">
                    <div class="step-num">4</div>
                    <h3 class="step-title">Production</h3>
                    <p class="step-desc">Vous prenez la main. On reste disponible pour les questions et les évolutions.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ FAQ ═══════════════════ --}}
    <section class="block" id="faq" style="background:var(--bg-soft)">
        <div class="container">
            <div class="block-header">
                <span class="block-eyebrow">Questions fréquentes</span>
                <h2 class="block-title">Ce qu'on nous demande souvent.</h2>
            </div>
            <div class="faq-list">
                <details class="faq-item">
                    <summary>Combien coûte Panora ?</summary>
                    <div class="faq-a">Chaque régie a une taille et des besoins différents (nombre de panneaux, nombre d'utilisateurs, options WhatsApp, formations). On établit un devis personnalisé après la démo — contactez-nous pour en discuter.</div>
                </details>
                <details class="faq-item">
                    <summary>Combien de temps pour être opérationnel ?</summary>
                    <div class="faq-a">Entre 2 et 4 semaines selon la taille de votre parc et votre disponibilité pour la formation. L'import de données prend 3 à 5 jours, la formation admin + terrain 2 sessions, puis vous passez en production.</div>
                </details>
                <details class="faq-item">
                    <summary>Mes données sont-elles sécurisées ?</summary>
                    <div class="faq-a">Oui. Hébergement Afrique de l'Ouest, sauvegardes quotidiennes chiffrées, audit trail complet (chaque action utilisateur est tracée), authentification forte, RGPD-friendly. Vous restez propriétaire de vos données à 100%.</div>
                </details>
                <details class="faq-item">
                    <summary>Nos techniciens ne connaissent pas Excel. Ça va aller ?</summary>
                    <div class="faq-a">C'est justement pour ça que l'espace tech Panora est conçu. 3 boutons, une photo, c'est tout. Les techniciens l'installent comme une application mobile classique et l'utilisent immédiatement — sans formation Excel, sans compte email.</div>
                </details>
                <details class="faq-item">
                    <summary>Peut-on utiliser Panora en dehors de la Côte d'Ivoire ?</summary>
                    <div class="faq-a">Oui. Le module FNE est spécifique CI mais peut être désactivé. Les taxes communales sont paramétrables pour tout pays (Sénégal, Cameroun, Burkina, Mali…). L'interface est en français, adaptée aux réalités africaines.</div>
                </details>
                <details class="faq-item">
                    <summary>Peut-on migrer depuis notre outil actuel ?</summary>
                    <div class="faq-a">Oui, on prend en charge la migration depuis Excel, Google Sheets, ou tout autre outil de gestion. Vous nous envoyez vos fichiers, on met tout en place. Aucune perte de données historiques.</div>
                </details>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ FORMULAIRE DEMO ═══════════════════ --}}
    <section class="cta-section" id="demo">
        <div class="container">
            <div class="cta-grid">
                <div class="cta-copy">
                    <h2 class="cta-title">Prêt à professionnaliser votre régie ?</h2>
                    <p class="cta-sub">Une démo de 30 minutes, sans engagement, adaptée à votre parc et à vos processus. On vous rappelle sous 48h.</p>
                    <div class="cta-contact-item">
                        <div class="cta-contact-icon">📧</div>
                        <div>
                            <div class="cta-contact-label">Email</div>
                            <div class="cta-contact-value">studio@cible-ci.com</div>
                        </div>
                    </div>
                    <div class="cta-contact-item">
                        <div class="cta-contact-icon">🕐</div>
                        <div>
                            <div class="cta-contact-label">Réponse sous</div>
                            <div class="cta-contact-value">48 heures ouvrées</div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="{{ route('landing.demo.submit') }}" class="demo-form" id="demo-form">
                    @csrf

                    @if(session('demo_sent'))
                        <div class="flash-success">
                            ✅ Merci ! Votre demande a bien été envoyée. On revient vers vous très vite.
                        </div>
                    @endif
                    @if(session('demo_error'))
                        <div class="flash-error">
                            ⚠ {{ session('demo_error') }}
                        </div>
                    @endif

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom complet <span class="req">*</span></label>
                            <input type="text" id="nom" name="nom" value="{{ old('nom') }}" required maxlength="100" autocomplete="name">
                            @error('nom')<div class="field-errors">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label for="regie">Nom de la régie <span class="req">*</span></label>
                            <input type="text" id="regie" name="regie" value="{{ old('regie') }}" required maxlength="150" autocomplete="organization">
                            @error('regie')<div class="field-errors">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="role">Votre rôle <span class="req">*</span></label>
                            <select id="role" name="role" required>
                                <option value="">— Choisir —</option>
                                <option value="direction" {{ old('role')==='direction' ? 'selected' : '' }}>Direction / Fondation</option>
                                <option value="commercial" {{ old('role')==='commercial' ? 'selected' : '' }}>Commercial</option>
                                <option value="operations" {{ old('role')==='operations' ? 'selected' : '' }}>Opérations / Terrain</option>
                                <option value="autre" {{ old('role')==='autre' ? 'selected' : '' }}>Autre</option>
                            </select>
                            @error('role')<div class="field-errors">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-group">
                            <label for="tel">Téléphone <span class="req">*</span></label>
                            <input type="tel" id="tel" name="tel" value="{{ old('tel') }}" required maxlength="30" autocomplete="tel" placeholder="+225 07 XX XX XX XX">
                            @error('tel')<div class="field-errors">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom:14px">
                        <label for="email">Email <span class="req">*</span></label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="150" autocomplete="email">
                        @error('email')<div class="field-errors">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group full">
                        <label for="message">Message (facultatif)</label>
                        <textarea id="message" name="message" maxlength="1500" placeholder="Nombre de panneaux, contexte, questions…">{{ old('message') }}</textarea>
                        @error('message')<div class="field-errors">{{ $message }}</div>@enderror
                    </div>

                    {{-- Honeypot anti-spam --}}
                    <input type="text" name="website" class="form-hp" tabindex="-1" autocomplete="off">

                    <button type="submit" class="form-submit">
                        Envoyer la demande de démo →
                    </button>
                    <div class="form-note">
                        En envoyant, vous acceptez que Panora vous contacte pour organiser la démo. Aucun spam, aucun partage.
                    </div>
                </form>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ FOOTER ═══════════════════ --}}
    <footer>
        <div class="container">
            <div class="footer-inner">
                <div class="footer-brand">
                    <span class="dot"></span> Panora
                </div>
                <div class="footer-nav">
                    <a href="#modules">Modules</a>
                    <a href="#difference">Différences</a>
                    <a href="#steps">Démarrage</a>
                    <a href="#faq">FAQ</a>
                    <a href="#demo">Contact</a>
                </div>
            </div>
            <div class="footer-copy">
                © {{ date('Y') }} Panora — Plateforme de gestion pour régies OOH.
            </div>
        </div>
    </footer>

</body>
</html>

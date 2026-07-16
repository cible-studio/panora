<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo_title ?? 'Panora — Système d\'exploitation des régies OOH' }}</title>
    <meta name="description" content="{{ $seo_description ?? 'Panora unifie l\'exploitation d\'une régie d\'affichage extérieur : inventaire, campagnes, terrain, facturation FNE, taxes communales, direction. Éprouvé en Afrique de l\'Ouest.' }}">

    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,400;9..144,500;9..144,600;9..144,700;9..144,800&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════════════
           Panora — Landing V2 · Style éditorial premium
           Direction A validée par la patronne (Linear / Stripe inspiré)
           Charte : noir + blanc + orange accent unique
           ═══════════════════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 17px;
            line-height: 1.65;
            color: #111827;
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }
        img { max-width: 100%; height: auto; display: block; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; }

        /* Serif éditorial pour les titres — Fraunces (variable font moderne) */
        h1, h2, h3, .serif {
            font-family: 'Fraunces', Georgia, 'Times New Roman', serif;
            font-optical-sizing: auto;
            font-weight: 500;
            letter-spacing: -0.02em;
            line-height: 1.15;
            color: #0b0f19;
        }

        :root {
            --ink:      #0b0f19;
            --ink-2:    #1f2937;
            --ink-3:    #4b5563;
            --ink-4:    #6b7280;
            --ink-5:    #9ca3af;
            --line:     #e5e7eb;
            --line-2:   #f3f4f6;
            --bg:       #ffffff;
            --bg-soft:  #fafaf7;
            --bg-cream: #fbf9f4;
            --accent:   #d94e1f;
            --accent-2: #b83d15;
            --accent-soft: #fdf5f0;
        }

        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        .wrap-narrow { max-width: 780px; margin: 0 auto; padding: 0 24px; }
        @media (max-width: 640px) { .wrap, .wrap-narrow { padding: 0 20px; } }

        /* ═══════════════════ NAVIGATION ═══════════════════ */
        header.nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(255, 255, 255, 0.86);
            backdrop-filter: saturate(180%) blur(14px);
            -webkit-backdrop-filter: saturate(180%) blur(14px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .nav-inner {
            max-width: 1120px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 24px;
        }
        .nav-brand {
            font-family: 'Fraunces', serif;
            font-weight: 600;
            font-size: 22px;
            letter-spacing: -0.03em;
            color: var(--ink);
        }
        .nav-brand::first-letter { color: var(--accent); }
        .nav-links {
            display: flex; align-items: center; gap: 32px;
            font-size: 14px; font-weight: 500;
        }
        .nav-links a {
            color: var(--ink-3);
            transition: color 0.15s ease;
            position: relative;
        }
        .nav-links a:hover { color: var(--ink); }
        .nav-links a.is-active { color: var(--ink); font-weight: 600; }
        .nav-links a.is-active::after {
            content: ''; position: absolute;
            left: 0; right: 0; bottom: -8px;
            height: 2px; background: var(--accent);
            border-radius: 2px;
        }
        .nav-cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 999px;
            background: var(--ink);
            color: #fff !important;
            font-weight: 600; font-size: 13.5px;
            transition: transform 0.15s ease, background 0.2s ease;
        }
        .nav-cta:hover {
            background: var(--accent);
            transform: translateY(-1px);
        }
        .nav-cta::after { display: none !important; }

        @media (max-width: 820px) {
            .nav-links { gap: 20px; font-size: 13px; }
            .nav-links a:not(.nav-cta) { display: none; }
        }

        /* ═══════════════════ SECTIONS ═══════════════════ */
        section {
            padding: 100px 0;
            border-bottom: 1px solid var(--line-2);
        }
        section:last-of-type { border-bottom: none; }
        @media (max-width: 720px) { section { padding: 70px 0; } }

        .eyebrow {
            display: inline-block;
            font-family: 'Inter', sans-serif;
            font-size: 12px; font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 22px;
        }

        h1.hero-title {
            font-size: clamp(44px, 6.5vw, 80px);
            font-weight: 400;
            letter-spacing: -0.035em;
            line-height: 1.05;
            margin-bottom: 28px;
        }
        h1.hero-title em {
            font-style: italic;
            font-weight: 300;
            color: var(--accent);
        }
        h2.section-title {
            font-size: clamp(30px, 4vw, 46px);
            font-weight: 500;
            margin-bottom: 20px;
        }
        h2.section-title em {
            font-style: italic;
            font-weight: 400;
            color: var(--accent);
        }
        h3.block-title {
            font-size: clamp(22px, 2.5vw, 28px);
            font-weight: 500;
            margin-bottom: 14px;
        }
        p.lead {
            font-size: 20px; line-height: 1.55;
            color: var(--ink-3);
            margin-bottom: 28px;
            max-width: 640px;
        }
        p.body {
            font-size: 17px;
            color: var(--ink-2);
            margin-bottom: 20px;
        }
        p.body + p.body { margin-top: -6px; }

        /* Liens inline "→" style éditorial */
        a.arrow-link {
            display: inline-flex; align-items: center; gap: 6px;
            font-weight: 600;
            color: var(--ink);
            border-bottom: 1px solid var(--accent);
            padding-bottom: 2px;
            transition: color 0.15s ease, gap 0.2s ease;
        }
        a.arrow-link:hover {
            color: var(--accent);
            gap: 12px;
        }
        a.arrow-link::after { content: '→'; font-weight: 400; }

        /* Boutons CTA */
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 15px 28px;
            border-radius: 999px;
            font-weight: 600; font-size: 15px;
            border: none;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
            font-family: 'Inter', sans-serif;
        }
        .btn-dark {
            background: var(--ink);
            color: #fff;
        }
        .btn-dark:hover {
            background: var(--accent);
            transform: translateY(-1px);
            box-shadow: 0 12px 32px -8px rgba(217, 78, 31, 0.35);
        }
        .btn-outline {
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--line);
        }
        .btn-outline:hover {
            border-color: var(--ink);
            transform: translateY(-1px);
        }

        /* Cartes / composants récurrents */
        .card-list {
            display: grid;
            gap: 1px;
            background: var(--line-2);
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
        }
        .card-item {
            background: var(--bg);
            padding: 34px 32px;
            transition: background 0.2s ease;
        }
        .card-item:hover { background: var(--bg-soft); }
        .card-item .num {
            font-family: 'Fraunces', serif;
            font-size: 14px;
            color: var(--accent);
            margin-bottom: 10px;
            font-weight: 500;
            letter-spacing: 0.05em;
        }
        .card-item h4 {
            font-family: 'Fraunces', serif;
            font-size: 22px;
            font-weight: 500;
            letter-spacing: -0.01em;
            margin-bottom: 10px;
            color: var(--ink);
        }
        .card-item p {
            font-size: 15px;
            color: var(--ink-3);
            line-height: 1.6;
            margin-bottom: 12px;
        }

        /* Grille 2 colonnes éditoriale */
        .split-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .split-2 { grid-template-columns: 1fr; gap: 40px; }
        }

        /* Aside "note" style éditorial */
        .aside-note {
            border-left: 3px solid var(--accent);
            padding: 8px 0 8px 22px;
            font-size: 16px;
            color: var(--ink-3);
            font-style: italic;
            margin: 32px 0;
        }

        /* Placeholder pour futurs screenshots réels */
        .screenshot-frame {
            background: linear-gradient(135deg, var(--bg-cream), var(--bg-soft));
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 24px;
            aspect-ratio: 16 / 10;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink-4);
            font-size: 13px;
            text-align: center;
            box-shadow: 0 30px 60px -30px rgba(11, 15, 25, 0.12);
        }
        .screenshot-frame small {
            display: block;
            font-size: 11px;
            color: var(--ink-5);
            margin-top: 6px;
            font-family: monospace;
        }

        /* ═══════════════════ FOOTER ═══════════════════ */
        footer.site-footer {
            padding: 60px 0 40px;
            background: var(--ink);
            color: rgba(255,255,255,0.72);
            border: none;
        }
        .footer-inner {
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 30px;
        }
        .footer-brand {
            font-family: 'Fraunces', serif;
            font-size: 24px;
            font-weight: 500;
            color: #fff;
            letter-spacing: -0.02em;
        }
        .footer-brand::first-letter { color: var(--accent); }
        .footer-brand-baseline {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 13px;
            color: rgba(255,255,255,0.55);
            margin-top: 6px;
            font-weight: 400;
            letter-spacing: 0;
            font-style: normal;
        }
        .footer-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px 40px;
            font-size: 14px;
        }
        .footer-links a {
            color: rgba(255,255,255,0.65);
            transition: color 0.15s ease;
            padding: 4px 0;
        }
        .footer-links a:hover { color: #fff; }
        .footer-bottom {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            text-align: center;
        }
    </style>
    @stack('head')
</head>
<body>

    {{-- ═══════════════════ NAVIGATION ═══════════════════ --}}
    <header class="nav">
        <div class="nav-inner">
            <a href="{{ route('landing.show') }}" class="nav-brand">Panora</a>
            <nav class="nav-links">
                @foreach($nav as $item)
                    @if(!empty($item['is_brand'])) @continue @endif
                    @php
                        $isActive = ($current === $item['id']);
                        $classes  = !empty($item['is_cta']) ? 'nav-cta' : ($isActive ? 'is-active' : '');
                    @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $classes }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    {{-- ═══════════════════ CONTENU DE LA PAGE ═══════════════════ --}}
    <main>
        @yield('content')
    </main>

    {{-- ═══════════════════ FOOTER ═══════════════════ --}}
    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-inner">
                <div>
                    <span class="footer-brand">Panora</span>
                    <span class="footer-brand-baseline">Le système d'exploitation<br>des régies OOH en Afrique de l'Ouest.</span>
                </div>
                <div class="footer-links">
                    <a href="{{ route('landing.show') }}">Accueil</a>
                    <a href="{{ route('landing.produit') }}">Le produit</a>
                    <a href="{{ route('landing.pour-directions') }}">Pour la direction</a>
                    <a href="{{ route('landing.pour-commerciaux') }}">Pour les commerciaux</a>
                    <a href="{{ route('landing.demo') }}">Demander une démo</a>
                    <a href="mailto:studio@cible-ci.com">studio@cible-ci.com</a>
                </div>
            </div>
            <div class="footer-bottom">
                © {{ date('Y') }} Panora. Tous droits réservés.
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

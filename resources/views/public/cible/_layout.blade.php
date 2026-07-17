<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo_title ?? 'CIBLE CI — Régie publicitaire N°1 en Côte d\'Ivoire' }}</title>
    <meta name="description" content="{{ $seo_description ?? 'CIBLE CI · 30 ans d\'expertise · 364 panneaux dans 31 communes. Régie publicitaire, communication mobile et 360°. Visez juste avec CIBLE.' }}">

    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════════════
           CIBLE CI — Site vitrine · Direction éditoriale
           Autorité tranquille + preuve terrain. Style institutionnel
           ivoirien : serif Playfair pour les titres (prestige), Inter
           body (lisibilité), palette orange CIBLE + noir profond bleu.
           ═══════════════════════════════════════════════════════════════ */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            font-size: 17px;
            line-height: 1.65;
            color: #0f172a;
            background: #ffffff;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        img { max-width: 100%; height: auto; display: block; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; cursor: pointer; }

        h1, h2, h3, .serif {
            font-family: 'Playfair Display', Georgia, serif;
            font-weight: 500;
            letter-spacing: -0.015em;
            line-height: 1.15;
            color: #0f172a;
        }

        :root {
            --ink:        #0f172a;       /* Noir profond bleu — texte principal */
            --ink-2:      #1e293b;
            --ink-3:      #475569;
            --ink-4:      #64748b;
            --ink-5:      #94a3b8;
            --line:       #e2e8f0;
            --line-2:     #f1f5f9;
            --bg:         #ffffff;
            --bg-cream:   #fbf8f1;        /* Fond crème institutionnel */
            --bg-warm:    #f5efe0;
            --accent:     #e8a020;        /* Orange CIBLE officiel */
            --accent-2:   #c9871a;
            --accent-soft:#fef7e6;
            --gold:       #b8860b;        /* Or pour distinctions */
            --deep:       #0b1120;        /* Noir hero */
        }

        .wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .wrap-narrow { max-width: 820px; margin: 0 auto; padding: 0 24px; }
        @media (max-width: 640px) { .wrap, .wrap-narrow { padding: 0 20px; } }

        /* ═══════════════════ NAVIGATION ═══════════════════ */
        header.nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(255, 255, 255, 0.94);
            backdrop-filter: saturate(180%) blur(12px);
            -webkit-backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .nav-inner {
            max-width: 1280px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px;
            gap: 24px;
        }
        .nav-brand {
            display: inline-flex; align-items: center; gap: 12px;
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 22px;
            letter-spacing: 0.02em;
            color: var(--ink);
        }
        .nav-brand-mark {
            display: inline-block;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--accent);
            position: relative;
        }
        .nav-brand-mark::before,
        .nav-brand-mark::after {
            content: ''; position: absolute; inset: 0;
            border-radius: 50%;
            border: 2px solid var(--ink);
        }
        .nav-brand-mark::before { inset: 6px; }
        .nav-brand-mark::after  { inset: 12px; background: var(--ink); border: none; }
        .nav-brand small {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent);
            margin-top: 2px;
        }

        .nav-links {
            display: flex; align-items: center; gap: 28px;
            font-size: 14px; font-weight: 500;
        }
        .nav-links a { color: var(--ink-3); transition: color 0.15s ease; position: relative; }
        .nav-links a:hover { color: var(--ink); }
        .nav-links a.is-active { color: var(--ink); font-weight: 600; }
        .nav-links a.is-active::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: -8px;
            height: 2px;
            background: var(--accent);
        }
        .nav-cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 10px 20px;
            background: var(--accent);
            color: var(--ink) !important;
            font-weight: 700;
            font-size: 13.5px;
            border-radius: 4px;
            transition: transform 0.15s ease, box-shadow 0.2s ease;
        }
        .nav-cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px -6px rgba(232, 160, 32, 0.5);
        }
        .nav-cta::after { display: none !important; }

        @media (max-width: 900px) {
            .nav-links { gap: 18px; font-size: 13px; }
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
            font-size: 11px; font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 22px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--accent);
        }

        h1.hero-title {
            font-size: clamp(40px, 5.8vw, 72px);
            font-weight: 400;
            letter-spacing: -0.02em;
            line-height: 1.08;
            margin-bottom: 28px;
        }
        h1.hero-title em {
            font-style: italic;
            color: var(--accent);
        }
        h2.section-title {
            font-size: clamp(30px, 3.8vw, 44px);
            font-weight: 500;
            margin-bottom: 20px;
        }
        h2.section-title em { font-style: italic; color: var(--accent); }
        h3.block-title {
            font-size: clamp(20px, 2vw, 26px);
            font-weight: 600;
            margin-bottom: 14px;
        }
        p.lead {
            font-size: 20px; line-height: 1.55;
            color: var(--ink-3);
            margin-bottom: 28px;
            max-width: 680px;
        }
        p.body { font-size: 17px; color: var(--ink-2); margin-bottom: 18px; }

        a.arrow-link {
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 600;
            color: var(--ink);
            border-bottom: 2px solid var(--accent);
            padding-bottom: 2px;
            transition: gap 0.2s ease;
        }
        a.arrow-link:hover { gap: 14px; color: var(--accent); }
        a.arrow-link::after { content: '→'; font-weight: 400; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 15px 30px;
            font-weight: 700; font-size: 14.5px;
            border: none;
            border-radius: 4px;
            transition: transform 0.15s ease, box-shadow 0.2s ease, background 0.2s ease;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.01em;
        }
        .btn-accent {
            background: var(--accent);
            color: var(--ink);
        }
        .btn-accent:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 28px -8px rgba(232, 160, 32, 0.5);
        }
        .btn-dark {
            background: var(--ink);
            color: #fff;
        }
        .btn-dark:hover {
            background: var(--accent);
            color: var(--ink);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            color: var(--ink);
            border: 1.5px solid var(--ink);
        }
        .btn-outline:hover { background: var(--ink); color: #fff; }

        /* Split 2 colonnes réutilisable */
        .split-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 70px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .split-2 { grid-template-columns: 1fr; gap: 40px; }
        }

        /* Placeholder terrain */
        .terrain-placeholder {
            background: linear-gradient(135deg, var(--bg-cream), var(--bg-warm));
            border: 1px solid var(--line);
            border-radius: 8px;
            aspect-ratio: 4 / 3;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink-4); font-size: 13px; text-align: center;
            padding: 20px;
        }
        .terrain-placeholder small {
            display: block;
            font-size: 11px;
            color: var(--ink-5);
            margin-top: 8px;
            font-family: monospace;
        }
        .terrain-placeholder strong {
            font-family: 'Playfair Display', serif;
            font-weight: 500;
            font-size: 18px;
            color: var(--ink-3);
            display: block;
            margin-bottom: 6px;
        }

        /* Aside note */
        .aside-note {
            border-left: 3px solid var(--accent);
            padding: 8px 0 8px 22px;
            font-size: 16px;
            color: var(--ink-3);
            font-style: italic;
            margin: 28px 0;
        }

        /* ═══════════════════ FOOTER ═══════════════════ */
        footer.site-footer {
            background: var(--deep);
            color: rgba(255,255,255,0.7);
            padding: 70px 0 30px;
            border: none;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }
        @media (max-width: 900px) { .footer-grid { grid-template-columns: 1fr 1fr; gap: 30px; } }
        @media (max-width: 560px) { .footer-grid { grid-template-columns: 1fr; } }

        .footer-brand-block {
            font-family: 'Playfair Display', serif;
            font-size: 26px;
            font-weight: 600;
            color: #fff;
            letter-spacing: 0.02em;
        }
        .footer-brand-block small {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-top: 4px;
        }
        .footer-brand-block p {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0;
            text-transform: none;
            color: rgba(255,255,255,0.55);
            margin-top: 14px;
            font-style: italic;
        }

        .footer-col h4 {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.5);
            margin-bottom: 16px;
        }
        .footer-col a, .footer-col p {
            display: block;
            font-size: 14px;
            color: rgba(255,255,255,0.72);
            transition: color 0.15s;
            padding: 4px 0;
            margin: 0;
        }
        .footer-col a:hover { color: var(--accent); }

        .footer-bottom {
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 20px;
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }
    </style>
    @stack('head')
</head>
<body>

    {{-- ═══════════════════ NAVIGATION ═══════════════════ --}}
    <header class="nav">
        <div class="nav-inner">
            <a href="{{ route('cible.home') }}" class="nav-brand" aria-label="CIBLE CI — accueil">
                <span class="nav-brand-mark" aria-hidden="true"></span>
                <span>
                    CIBLE
                    <small>Régie publicitaire · Côte d'Ivoire</small>
                </span>
            </a>
            <nav class="nav-links">
                @foreach($nav as $item)
                    @php
                        $isActive = ($current === $item['id']);
                        $classes  = !empty($item['is_cta']) ? 'nav-cta' : ($isActive ? 'is-active' : '');
                    @endphp
                    <a href="{{ route($item['route']) }}" class="{{ $classes }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    {{-- ═══════════════════ FOOTER ═══════════════════ --}}
    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand-block">
                        CIBLE
                        <small>By CIBLE CI</small>
                        <p>« Visez juste avec CIBLE ».<br>30 ans d'expertise en communication extérieure en Côte d'Ivoire.</p>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Explorer</h4>
                    <a href="{{ route('cible.home') }}">Accueil</a>
                    <a href="{{ route('cible.qui') }}">Qui sommes-nous</a>
                    <a href="{{ route('cible.services') }}">Nos services</a>
                    <a href="{{ route('cible.reseau') }}">Le réseau</a>
                    <a href="{{ route('cible.references') }}">Références</a>
                </div>
                <div class="footer-col">
                    <h4>Contact</h4>
                    <a href="tel:+2250798496674">07 98 49 66 74</a>
                    <a href="mailto:commercial@cible-ci.com">commercial@cible-ci.com</a>
                    <p>Rue des ambassadeurs<br>Riviera M'badon, Abidjan</p>
                </div>
                <div class="footer-col">
                    <h4>Suivez-nous</h4>
                    <a href="https://facebook.com/cible.ci" target="_blank" rel="noopener">Facebook</a>
                    <a href="https://ci.linkedin.com/company/cible-ci" target="_blank" rel="noopener">LinkedIn</a>
                    <a href="{{ route('cible.contact') }}">Demander un devis</a>
                </div>
            </div>
            <div class="footer-bottom">
                <div>© {{ date('Y') }} CIBLE CI · Régie publicitaire · Tous droits réservés.</div>
                <div>« On te voit partout »</div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

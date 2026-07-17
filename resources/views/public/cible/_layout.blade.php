<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo_title ?? 'CIBLE CI — Régie publicitaire N°1 en Côte d\'Ivoire' }}</title>
    <meta name="description" content="{{ $seo_description ?? 'CIBLE CI · 30 ans d\'expertise · 364 panneaux dans 31 communes. Régie publicitaire, communication mobile et 360°. Vous visez juste.' }}">

    <link rel="icon" href="{{ asset('images/faviconl.png') }}" media="(prefers-color-scheme: light)">
    <link rel="icon" href="{{ asset('images/favicond.png') }}" media="(prefers-color-scheme: dark)">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* ═══════════════════════════════════════════════════════════════
           CIBLE CI — Charte officielle
           Logo : objectif photo multicolore + « CIBLE » bâton bold
           Baseline : « Vous visez juste »
           Palette : les 5 teintes du logo — bleu · violet · vert · rouge · or
                     sur base noir profond + blanc / crème
           Typo : Inter uniquement, poids variable (body 400/500, titres 800/900)
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

        h1, h2, h3, h4 {
            font-family: 'Inter', sans-serif;
            font-weight: 900;
            letter-spacing: -0.02em;
            line-height: 1.05;
            color: #0f172a;
        }

        :root {
            /* Base noir/blanc/crème */
            --ink:        #0f172a;
            --ink-2:      #1e293b;
            --ink-3:      #475569;
            --ink-4:      #64748b;
            --ink-5:      #94a3b8;
            --line:       #e2e8f0;
            --line-2:     #f1f5f9;
            --bg:         #ffffff;
            --bg-cream:   #fbf8f0;
            --bg-warm:    #f5efe0;
            --deep:       #0b0f19;

            /* Palette CIBLE — les 5 teintes de l'objectif du logo */
            --cible-jaune:  #f5b71c;      /* Accent principal — chaud, contraste noir */
            --cible-jaune-2:#dca011;
            --cible-jaune-soft:#fdf3d4;
            --cible-rouge:  #d8291a;
            --cible-rouge-soft:#fbe4e2;
            --cible-bleu:   #2c5eaa;
            --cible-bleu-soft:#e3ecf7;
            --cible-vert:   #3aa24f;
            --cible-vert-soft:#e0f2e4;
            --cible-violet: #6d3e9e;
            --cible-violet-soft:#efe4f7;

            --accent:       var(--cible-jaune);
            --accent-2:     var(--cible-jaune-2);
            --accent-soft:  var(--cible-jaune-soft);
        }

        .wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .wrap-narrow { max-width: 820px; margin: 0 auto; padding: 0 24px; }
        @media (max-width: 640px) { .wrap, .wrap-narrow { padding: 0 20px; } }

        /* ═══════════════════ NAVIGATION ═══════════════════ */
        header.nav {
            position: sticky; top: 0; z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: saturate(180%) blur(12px);
            -webkit-backdrop-filter: saturate(180%) blur(12px);
            border-bottom: 1px solid rgba(0,0,0,0.06);
        }
        .nav-inner {
            max-width: 1280px; margin: 0 auto;
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 24px;
            gap: 24px;
        }
        .nav-brand { display: inline-flex; align-items: center; }
        .nav-brand img {
            height: 46px; width: auto; display: block;
            transition: transform 0.2s ease;
        }
        .nav-brand:hover img { transform: scale(1.03); }

        .nav-links {
            display: flex; align-items: center; gap: 30px;
            font-size: 14px; font-weight: 600;
        }
        .nav-links a {
            color: var(--ink-3);
            transition: color 0.15s ease;
            position: relative;
        }
        .nav-links a:hover { color: var(--ink); }
        .nav-links a.is-active { color: var(--ink); }
        .nav-links a.is-active::after {
            content: '';
            position: absolute;
            left: 0; right: 0; bottom: -8px;
            height: 3px;
            background: var(--cible-jaune);
        }
        .nav-cta {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 11px 22px;
            background: var(--cible-jaune);
            color: var(--ink) !important;
            font-weight: 800;
            font-size: 13px;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            border-radius: 4px;
            transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
        }
        .nav-cta:hover {
            background: var(--ink);
            color: var(--cible-jaune) !important;
            transform: translateY(-1px);
            box-shadow: 0 10px 22px -8px rgba(15, 23, 42, 0.4);
        }
        .nav-cta::after { display: none !important; }

        @media (max-width: 900px) {
            .nav-links { gap: 18px; font-size: 13px; }
            .nav-links a:not(.nav-cta) { display: none; }
            .nav-brand img { height: 38px; }
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
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--ink);
            padding: 6px 12px;
            background: var(--cible-jaune);
            border-radius: 3px;
            margin-bottom: 22px;
        }

        h1.hero-title {
            font-size: clamp(42px, 6vw, 76px);
            font-weight: 900;
            letter-spacing: -0.03em;
            line-height: 1.03;
            margin-bottom: 28px;
            text-transform: uppercase;
        }
        h1.hero-title em {
            font-style: normal;
            color: var(--cible-jaune);
        }
        h2.section-title {
            font-size: clamp(30px, 4vw, 46px);
            font-weight: 900;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: -0.02em;
        }
        h2.section-title em { font-style: normal; color: var(--cible-jaune); }
        h3.block-title {
            font-size: clamp(20px, 2.2vw, 26px);
            font-weight: 800;
            margin-bottom: 14px;
        }
        p.lead {
            font-size: 20px; line-height: 1.55;
            color: var(--ink-3);
            margin-bottom: 28px;
            max-width: 680px;
            font-weight: 400;
        }
        p.body { font-size: 17px; color: var(--ink-2); margin-bottom: 18px; }

        a.arrow-link {
            display: inline-flex; align-items: center; gap: 8px;
            font-weight: 800;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--ink);
            border-bottom: 2px solid var(--cible-jaune);
            padding-bottom: 3px;
            transition: gap 0.2s ease;
        }
        a.arrow-link:hover { gap: 14px; }
        a.arrow-link::after { content: '→'; font-weight: 400; }

        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 15px 30px;
            font-weight: 800;
            font-size: 13.5px;
            border: none;
            border-radius: 4px;
            transition: transform 0.15s, box-shadow 0.2s, background 0.2s;
            font-family: 'Inter', sans-serif;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            cursor: pointer;
        }
        .btn-accent {
            background: var(--cible-jaune);
            color: var(--ink);
        }
        .btn-accent:hover {
            background: var(--ink);
            color: var(--cible-jaune);
            transform: translateY(-1px);
            box-shadow: 0 12px 28px -8px rgba(15, 23, 42, 0.4);
        }
        .btn-dark {
            background: var(--ink);
            color: #fff;
        }
        .btn-dark:hover {
            background: var(--cible-jaune);
            color: var(--ink);
            transform: translateY(-1px);
        }
        .btn-outline {
            background: transparent;
            color: var(--ink);
            border: 2px solid var(--ink);
        }
        .btn-outline:hover {
            background: var(--ink);
            color: #fff;
        }

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
            border-radius: 6px;
            aspect-ratio: 4 / 3;
            display: flex; align-items: center; justify-content: center;
            color: var(--ink-4);
            font-size: 13px;
            text-align: center;
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
            font-family: 'Inter', sans-serif;
            font-weight: 800;
            font-size: 15px;
            color: var(--ink-3);
            display: block;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Aside note */
        .aside-note {
            border-left: 4px solid var(--cible-jaune);
            padding: 8px 0 8px 22px;
            font-size: 16px;
            color: var(--ink-3);
            font-style: italic;
            margin: 28px 0;
        }

        /* Barre multi-couleurs signature (5 teintes du logo) */
        .cible-rainbow {
            display: block;
            height: 4px;
            background: linear-gradient(90deg,
                var(--cible-bleu)   0%,   var(--cible-bleu)   20%,
                var(--cible-violet) 20%,  var(--cible-violet) 40%,
                var(--cible-vert)   40%,  var(--cible-vert)   60%,
                var(--cible-rouge)  60%,  var(--cible-rouge)  80%,
                var(--cible-jaune)  80%,  var(--cible-jaune)  100%);
        }

        /* ═══════════════════ FOOTER ═══════════════════ */
        footer.site-footer {
            background: var(--deep);
            color: rgba(255,255,255,0.7);
            padding: 70px 0 30px;
            border: none;
            position: relative;
        }
        footer.site-footer::before {
            content: '';
            display: block;
            position: absolute; top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg,
                var(--cible-bleu)   0%,   var(--cible-bleu)   20%,
                var(--cible-violet) 20%,  var(--cible-violet) 40%,
                var(--cible-vert)   40%,  var(--cible-vert)   60%,
                var(--cible-rouge)  60%,  var(--cible-rouge)  80%,
                var(--cible-jaune)  80%,  var(--cible-jaune)  100%);
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 50px;
        }
        @media (max-width: 900px) { .footer-grid { grid-template-columns: 1fr 1fr; gap: 30px; } }
        @media (max-width: 560px) { .footer-grid { grid-template-columns: 1fr; } }

        .footer-brand-block img {
            height: 62px; width: auto;
            margin-bottom: 20px;
        }
        .footer-brand-block p {
            font-family: 'Inter', sans-serif;
            font-size: 14.5px;
            font-weight: 400;
            letter-spacing: 0;
            text-transform: none;
            color: rgba(255,255,255,0.55);
            font-style: italic;
            line-height: 1.6;
        }

        .footer-col h4 {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--cible-jaune);
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
        .footer-col a:hover { color: var(--cible-jaune); }

        .footer-bottom {
            padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex; justify-content: space-between; align-items: center;
            flex-wrap: wrap; gap: 20px;
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }
        .footer-bottom .signature {
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            font-style: italic;
            color: var(--cible-jaune);
            letter-spacing: 0.02em;
        }
    </style>
    @stack('head')
</head>
<body>

    {{-- ═══════════════════ NAVIGATION ═══════════════════ --}}
    <header class="nav">
        <div class="nav-inner">
            <a href="{{ route('cible.home') }}" class="nav-brand" aria-label="CIBLE CI — accueil">
                <img src="{{ asset('images/logol.png') }}" alt="CIBLE — Vous visez juste">
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
                        <img src="{{ asset('images/logon.png') }}" alt="CIBLE — Vous visez juste">
                        <p>Régie publicitaire N°1 en Côte d'Ivoire.<br>30 ans d'expertise en communication extérieure.</p>
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
                <div class="signature">« Vous visez juste »</div>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>

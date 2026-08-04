{{-- ═══════════════════════════════════════════════════════════════════
     Layout site vitrine CIBLE — refonte v3 (2026-07-29)
     Palette 5 couleurs (rouge + jaune/vert/bleu/violet) · Poppins+Nunito
     Design system unifié entre les 6 pages : layout / hero / boutons /
     footer / SVG symbols / palette CSS.
════════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo_title ?? 'CIBLE — Régie publicitaire en Côte d\'Ivoire · Vous visez juste' }}</title>
    <meta name="description" content="{{ $seo_description ?? 'Régie publicitaire ivoirienne depuis 1994. Affichage grand format, publicité mobile, communication 360°. 364 panneaux dans 31 communes. Devis sous 24 h.' }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="fr_CI">
    <meta property="og:site_name" content="CIBLE">
    <meta property="og:title" content="{{ $seo_title ?? 'CIBLE — Régie publicitaire en Côte d\'Ivoire' }}">
    <meta property="og:description" content="{{ $seo_description ?? 'Affichage grand format, publicité mobile, communication 360°. Vous visez juste.' }}">
    <meta property="og:url" content="{{ url()->current() }}">

    <script type="application/ld+json">
    {
      "@@context":"https://schema.org",
      "@@type":"LocalBusiness",
      "@@id":"{{ url('/cible') }}#organisation",
      "name":"CIBLE",
      "alternateName":"CIBLE CI",
      "slogan":"Vous visez juste",
      "description":"Régie publicitaire et studio créatif en Côte d'Ivoire. Affichage grand format, publicité mobile, communication 360°.",
      "url":"{{ url('/cible') }}",
      "telephone":"+2250700780628",
      "email":"commercial@cible-ci.com",
      "foundingDate":"1994",
      "address":{"@@type":"PostalAddress","streetAddress":"Rue des Ambassadeurs, Riviera M'Badon","postOfficeBoxNumber":"10 BP 1029","addressLocality":"Abidjan","addressCountry":"CI"},
      "areaServed":{"@@type":"Country","name":"Côte d'Ivoire"}
    }
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;800;900&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
    :root{
      --rouge:#E20613; --jaune:#FAB80B; --vert:#3AA835; --bleu:#3F7FC0; --violet:#81358A;
      --gris:#E6E6E6; --noir:#111111; --blanc:#FFFFFF;
      --titre:'Poppins',sans-serif;
      --corps:'Nunito',sans-serif;
      --pad:clamp(20px,5vw,84px);
      --tuile:url("data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22200%22%20height%3D%22200%22%20viewBox%3D%220%200%20200%20200%22%3E%3Cg%20fill%3D%22none%22%20stroke%3D%22%23F3C63F%22%20stroke-width%3D%223%22%20opacity%3D%22.55%22%3E%3Cpath%20d%3D%22M40%2050%20L120%2050%20M120%2050%20L100%2035%20M120%2050%20L100%2065%22%2F%3E%3Cpath%20d%3D%22M60%20120%20L150%20130%20M150%20130%20L130%20120%20M150%20130%20L135%20143%22%2F%3E%3Cpath%20d%3D%22M30%20170%20L110%20160%20M110%20160%20L95%20150%20M110%20160%20L98%20172%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E");
    }
    *{box-sizing:border-box;margin:0;padding:0}
    html{scroll-behavior:smooth}
    body{background:var(--blanc);color:var(--noir);font-family:var(--corps);font-size:17px;line-height:1.6;-webkit-font-smoothing:antialiased;overflow-x:hidden}
    a{color:inherit;text-decoration:none}
    img{display:block;max-width:100%}
    :focus-visible{outline:3px solid var(--rouge);outline-offset:3px}
    section{position:relative;overflow:hidden}

    .num{font-family:var(--titre);font-variant-numeric:tabular-nums}
    /* Titres 2026-08-04ter — refonte échelle globale :
       - line-height 1.05 pour éviter la superposition Poppins 900
       - .t1 ramené de clamp(38,6.2,86) → clamp(32,4.8vw,64) :
         l'ancienne taille faisait déborder les titres de section sur
         3-4 lignes ("Cinq territoires...", "Trois distinctions...").
         La nouvelle taille tient sur 1-2 lignes sur desktop et reste
         impactful. C'est LA taille unifiée pour tous les titres de
         section du site (h1 hero + h2 sections). */
    .t1{font-family:var(--titre);font-weight:900;line-height:1.05;letter-spacing:-.028em;font-size:clamp(32px,4.8vw,64px)}
    .t2{font-family:var(--titre);font-weight:800;line-height:1.08;letter-spacing:-.02em;font-size:clamp(24px,3vw,38px)}
    .sur{font-family:var(--titre);font-weight:700;text-transform:uppercase;font-size:12px;letter-spacing:.2em}

    /* ═══ nav ═══ */
    header.site{position:sticky;top:0;z-index:80;background:rgba(255,255,255,.94);backdrop-filter:blur(8px);border-bottom:1px solid #E4E4E4}
    .nav{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:12px var(--pad)}
    .logo-w{display:flex;align-items:center;gap:12px;text-decoration:none}
    .logo-w img{height:52px;width:auto;display:block}
    .liens{display:flex;gap:22px;font-family:var(--titre);font-weight:700;font-size:13px}
    .liens a{position:relative;padding:6px 0}
    .liens a::after{content:"";position:absolute;left:0;right:100%;bottom:0;height:3px;background:var(--c,var(--rouge));transition:right .28s cubic-bezier(.2,.8,.3,1)}
    .liens a:hover::after,.liens a[aria-current="page"]::after{right:0}
    .liens a:nth-child(1){--c:var(--violet)}
    .liens a:nth-child(2){--c:var(--rouge)}
    .liens a:nth-child(3){--c:var(--vert)}
    .liens a:nth-child(4){--c:var(--jaune)}
    .liens a:nth-child(5){--c:var(--bleu)}
    .bouton{display:inline-flex;align-items:center;gap:9px;font-family:var(--titre);font-weight:800;font-size:14px;padding:14px 26px;border-radius:999px;transition:transform .18s cubic-bezier(.2,.8,.3,1),background .18s,color .18s;cursor:pointer;border:0;text-decoration:none}
    .bouton .fl{width:15px;transition:transform .22s;flex-shrink:0}
    .bouton:hover .fl{transform:translateX(4px)}
    .b-rouge{background:var(--rouge);color:var(--blanc)} .b-rouge:hover{transform:translateY(-3px);background:#B00510}
    .b-ligne{border:2px solid var(--noir);background:transparent;color:var(--noir)} .b-ligne:hover{background:var(--noir);color:var(--blanc);transform:translateY(-3px)}
    .b-blanc{background:var(--blanc);color:var(--bleu)} .b-blanc:hover{transform:translateY(-3px)}

    /* menu burger */
    .burger{display:none;background:none;border:0;cursor:pointer;padding:8px}
    .burger span{display:block;width:26px;height:3px;background:var(--noir);border-radius:2px;margin:5px 0;transition:transform .25s,opacity .25s}
    .burger.on span:nth-child(1){transform:translateY(8px) rotate(45deg)}
    .burger.on span:nth-child(2){opacity:0}
    .burger.on span:nth-child(3){transform:translateY(-8px) rotate(-45deg)}

    @media(max-width:1000px){
      .liens{display:none;position:absolute;top:100%;left:0;right:0;flex-direction:column;background:#fff;border-bottom:1px solid #E4E4E4;padding:16px var(--pad);gap:16px}
      .liens.open{display:flex}
      .burger{display:block}
      header.site .bouton{display:none}
    }

    /* ═══ photos avec respiration ═══ */
    .photo{width:100%;height:100%;object-fit:cover;transform-origin:center;animation:respire 26s ease-in-out infinite alternate}
    @keyframes respire{from{transform:scale(1)}to{transform:scale(1.07)}}
    .slot{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:20px;background:linear-gradient(135deg,#D6D6D6,#EAEAEA);color:#5E5E5E;font-family:var(--titre);font-weight:700;font-size:12px;line-height:1.75;letter-spacing:.04em}
    .slot small{display:block;font-weight:400;opacity:.7;margin-top:6px;font-size:10px}
    .slot--sombre{background:linear-gradient(135deg,rgba(0,0,0,.35),rgba(0,0,0,.15));color:rgba(255,255,255,.9)}
    .slot::before{content:"🖼";font-size:32px;opacity:.4;margin-bottom:8px}

    /* ═══ ticker bandeau défilant ═══ */
    .ticker{padding:18px 0;overflow:hidden;white-space:nowrap;border-top:1px solid #E4E4E4;border-bottom:1px solid #E4E4E4}
    .piste{display:inline-block;animation:defile 38s linear infinite;font-family:var(--titre);font-weight:800;font-size:clamp(14px,1.7vw,21px)}
    .piste b{display:inline-block;margin:0 8px;padding:8px 20px;border-radius:999px;color:var(--blanc);font-weight:800}
    @keyframes defile{from{transform:translateX(0)}to{transform:translateX(-50%)}}

    /* ═══ footer ═══ */
    footer.site{background:var(--noir);color:var(--blanc);padding:clamp(46px,6vw,72px) var(--pad) 32px;position:relative;overflow:hidden;margin-top:80px}
    footer.site .pied{position:relative;z-index:2;display:grid;grid-template-columns:1.4fr 1fr 1fr 1fr;gap:34px}
    @media(max-width:900px){footer.site .pied{grid-template-columns:1fr 1fr}}
    @media(max-width:520px){footer.site .pied{grid-template-columns:1fr}}
    footer.site h5{font-family:var(--titre);font-weight:800;font-size:13px;color:var(--jaune);margin-bottom:14px;text-transform:uppercase;letter-spacing:.08em}
    footer.site li{list-style:none;margin-bottom:9px;font-size:14px;opacity:.82;line-height:1.55}
    footer.site li a:hover{color:var(--jaune);opacity:1}
    footer.site .slogan{margin-top:16px;opacity:.7;max-width:32ch;font-size:14px}
    footer.site .logo-foot img{height:64px;width:auto;display:block;margin-bottom:14px}
    .copy{position:relative;z-index:2;margin-top:40px;padding-top:20px;border-top:1px solid rgba(255,255,255,.16);font-size:13px;opacity:.55;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}

    /* ═══ révélation scroll ═══ */
    .rev{opacity:0;transform:translateY(24px);transition:opacity .75s cubic-bezier(.2,.7,.3,1),transform .75s cubic-bezier(.2,.7,.3,1)}
    .rev.vu{opacity:1;transform:none}

    /* ═══ boîte flash messages ═══ */
    .flash{max-width:1200px;margin:16px auto 0;padding:14px 20px;border-radius:12px;font-weight:600;font-family:var(--titre);font-size:14px}
    .flash-success{background:#dcfce7;border:1px solid #86efac;color:#166534}
    .flash-error{background:#fee2e2;border:1px solid #fca5a5;color:#991b1b}
    .flash-info{background:#dbeafe;border:1px solid #93c5fd;color:#1e40af}

    @media(prefers-reduced-motion:reduce){
      *,*::before,*::after{animation:none!important;transition:none!important}
      .rev{opacity:1;transform:none}
      html{scroll-behavior:auto}
    }

    @stack('page-css')
    </style>
    @stack('head')
</head>
<body>

@php
    $current = $current ?? 'home';
@endphp

<header class="site">
    <nav class="nav">
        {{-- Slogan "Vous visez juste" retiré 2026-08-04 : déjà inclus
             dans le logo image → doublon visuel. --}}
        <a href="{{ route('cible.home') }}" class="logo-w" aria-label="CIBLE, accueil">
            <img src="{{ asset('images/logol.png') }}" alt="CIBLE — Vous visez juste">
        </a>
        <div class="liens" id="site-links">
            <a href="{{ route('cible.qui') }}" {{ $current==='qui' ? 'aria-current=page' : '' }}>Qui sommes-nous</a>
            <a href="{{ route('cible.services') }}" {{ $current==='services' ? 'aria-current=page' : '' }}>Nos services</a>
            <a href="{{ route('cible.references') }}" {{ $current==='references' ? 'aria-current=page' : '' }}>Nos réalisations</a>
            <a href="{{ route('cible.reseau') }}" {{ $current==='reseau' ? 'aria-current=page' : '' }}>Notre réseau</a>
            <a href="{{ route('cible.contact') }}" {{ $current==='contact' ? 'aria-current=page' : '' }}>Contact</a>
        </div>
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander un devis</a>
        <button class="burger" id="burger" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
    </nav>
</header>

@if(session('success')) <div class="flash flash-success">✓ {{ session('success') }}</div> @endif
@if(session('error'))   <div class="flash flash-error">✕ {{ session('error') }}</div> @endif
@if(session('info'))    <div class="flash flash-info">ℹ {{ session('info') }}</div> @endif

@yield('content')

<footer class="site">
    <div class="pied">
        <div>
            {{-- "Vous visez juste" retiré 2026-08-04 : déjà dans le logo. --}}
            <div class="logo-foot">
                <img src="{{ asset('images/logon.png') }}" alt="CIBLE — Vous visez juste">
            </div>
            <p class="slogan">Régie publicitaire et studio créatif. 30 ans à rendre les marques visibles en Côte d'Ivoire.</p>
        </div>
        <div>
            <h5>Explorer</h5>
            <ul>
                <li><a href="{{ route('cible.qui') }}">Qui sommes-nous</a></li>
                <li><a href="{{ route('cible.services') }}">Nos services</a></li>
                <li><a href="{{ route('cible.references') }}">Réalisations</a></li>
                <li><a href="{{ route('cible.reseau') }}">Notre réseau</a></li>
            </ul>
        </div>
        <div>
            <h5>Contact</h5>
            <ul>
                <li><a href="tel:+2250700780628" class="num">+225 07 00 78 06 28</a></li>
                <li><a href="mailto:commercial@cible-ci.com">commercial@cible-ci.com</a></li>
                <li>Rue des Ambassadeurs<br>Riviera M'Badon<br>10 BP 1029 Abidjan 10</li>
            </ul>
        </div>
        <div>
            <h5>Reconnaissances</h5>
            <ul>
                <li>2016 · 2ᵉ prix meilleur publicitaire</li>
                <li>2019 · Chevalier de l'Ordre du Mérite de la Communication</li>
                <li>2020 · Officier de l'Ordre du Mérite National</li>
            </ul>
        </div>
    </div>
    <div class="copy">
        <span class="num">© {{ date('Y') }} CIBLE · Tous droits réservés</span>
        <span>Régie publicitaire · Côte d'Ivoire</span>
    </div>
</footer>

<script>
document.getElementById('burger')?.addEventListener('click', function() {
    const liens = document.getElementById('site-links');
    const open = liens.classList.toggle('open');
    this.classList.toggle('on', open);
    this.setAttribute('aria-expanded', open);
});
const doux = matchMedia('(prefers-reduced-motion: reduce)').matches;
if (!doux) {
    const io = new IntersectionObserver(e => e.forEach(x => {
        if (x.isIntersecting) { x.target.classList.add('vu'); io.unobserve(x.target); }
    }), { threshold: .12 });
    document.querySelectorAll('.rev').forEach(el => io.observe(el));
}
function anime(el) {
    if (el.dataset.brut) { el.textContent = el.dataset.brut; return; }
    const fin = parseInt(el.dataset.cible, 10);
    if (doux) { el.textContent = fin; return; }
    const t0 = performance.now(), d = 1150;
    const pas = t => {
        const p = Math.min((t - t0) / d, 1);
        el.textContent = Math.round(fin * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(pas);
    };
    requestAnimationFrame(pas);
}
const ioN = new IntersectionObserver(e => e.forEach(x => {
    if (x.isIntersecting) { anime(x.target); ioN.unobserve(x.target); }
}), { threshold: .5 });
document.querySelectorAll('[data-cible],[data-brut]').forEach(el => ioN.observe(el));
</script>

@stack('page-js')
</body>
</html>

@extends('public.cible._layout', [
    'seo_title'       => 'CIBLE — Régie publicitaire en Côte d\'Ivoire · 364 panneaux · Vous visez juste',
    'seo_description' => 'Régie publicitaire ivoirienne depuis 1994. Affichage grand format, publicité mobile, communication 360°. 364 panneaux dans 31 communes. Devis sous 24 h.',
])

@push('page-css')
    /* HERO */
    .hero{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,56px);align-items:center;padding:clamp(36px,5.5vw,80px) var(--pad) 0;position:relative}
    @media(max-width:920px){.hero{grid-template-columns:1fr}}
    .hero>*{position:relative;z-index:2}
    .hero .sur{color:var(--vert)}
    .hero h1{margin-top:16px;font-family:var(--titre);font-weight:900;line-height:.95;letter-spacing:-.038em;font-size:clamp(42px,7.2vw,96px)}
    .hero h1 .l{display:block;opacity:0;transform:translateY(26px);animation:monte .8s cubic-bezier(.2,.8,.3,1) forwards}
    .hero h1 .l:nth-child(1){animation-delay:.05s}
    .hero h1 .l:nth-child(2){animation-delay:.16s}
    .hero h1 .l:nth-child(3){animation-delay:.27s;color:var(--rouge)}
    @keyframes monte{to{opacity:1;transform:none}}
    .hero .sous-titre{margin-top:20px;max-width:52ch;font-family:var(--corps);font-weight:700;font-size:clamp(16px,1.6vw,20px);line-height:1.45;color:#3A3A3A;opacity:0;animation:monte .8s .36s cubic-bezier(.2,.8,.3,1) forwards}
    .hero .accroche{margin-top:16px;max-width:48ch;font-size:clamp(15px,1.4vw,18px);color:#4A4A4A;opacity:0;animation:monte .8s .46s cubic-bezier(.2,.8,.3,1) forwards}
    .hero .accroche strong{color:var(--rouge)}
    .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px;opacity:0;animation:monte .8s .52s cubic-bezier(.2,.8,.3,1) forwards}
    .hero-visuel{position:relative;aspect-ratio:1/1;border-radius:999px 28px 28px 999px;overflow:hidden;background:var(--gris);opacity:0;transform:scale(.94);animation:zoom .9s .3s cubic-bezier(.2,.8,.3,1) forwards}
    @keyframes zoom{to{opacity:1;transform:none}}
    @media(max-width:920px){.hero-visuel{border-radius:28px;aspect-ratio:4/3}}

    /* bande de 5 panneaux */
    .bande{display:flex;gap:10px;align-items:flex-end;padding:clamp(26px,4vw,46px) var(--pad) 0;position:relative;z-index:2}
    .bande div{flex:1;height:clamp(26px,4vw,52px);border-radius:46% 46% 8px 8px / 55% 55% 8px 8px;transform-origin:bottom;transform:scaleY(.12);animation:pousse .7s cubic-bezier(.2,.9,.3,1) forwards}
    .bande div:nth-child(1){animation-delay:.55s}
    .bande div:nth-child(2){animation-delay:.64s}
    .bande div:nth-child(3){animation-delay:.73s}
    .bande div:nth-child(4){animation-delay:.82s}
    .bande div:nth-child(5){animation-delay:.91s}
    @keyframes pousse{to{transform:scaleY(1)}}

    /* modules aperçu */
    .modules{padding:clamp(56px,8vw,100px) var(--pad)}
    .modules-tete{max-width:60ch;margin-bottom:44px}
    .modules-tete .sur{color:var(--bleu)}
    .modules-tete .t1{margin-top:14px}
    .modules-tete p{margin-top:18px;color:#444}
    .modules-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(14px,2vw,26px)}
    @media(max-width:900px){.modules-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.modules-grid{grid-template-columns:1fr}}
    .mod{padding:32px 26px;border-radius:20px;border:1px solid var(--gris);transition:transform .28s cubic-bezier(.2,.8,.3,1),border-color .28s,box-shadow .28s;background:#fff}
    .mod:hover{transform:translateY(-6px);border-color:var(--c);box-shadow:0 20px 40px -20px rgba(0,0,0,.15)}
    .mod .puce{width:44px;height:44px;border-radius:12px;background:var(--c);display:flex;align-items:center;justify-content:center;color:#fff;font-family:var(--titre);font-weight:800;font-size:18px;margin-bottom:20px}
    .mod h4{font-family:var(--titre);font-weight:800;font-size:20px;margin-bottom:10px;line-height:1.25}
    .mod p{font-size:14.5px;color:#555;line-height:1.6}
    .mod a{display:inline-block;margin-top:16px;font-family:var(--titre);font-weight:700;font-size:13px;color:var(--c);border-bottom:2px solid var(--c);padding-bottom:2px}

    /* marque teaser */
    .marque-teaser{display:grid;grid-template-columns:1fr 1fr;align-items:stretch;min-height:min(70vh,540px)}
    @media(max-width:900px){.marque-teaser{grid-template-columns:1fr}}
    .marque-teaser .img{border-radius:0 999px 999px 0;overflow:hidden;background:var(--gris)}
    @media(max-width:900px){.marque-teaser .img{border-radius:0 0 220px 220px;aspect-ratio:4/3}}
    .marque-teaser .txt{background:var(--violet);color:var(--blanc);padding:clamp(34px,5vw,80px);display:flex;flex-direction:column;justify-content:center}
    .marque-teaser .txt p{margin-top:20px;max-width:44ch;opacity:.95}
    .marque-teaser .txt .fort{margin-top:26px;font-family:var(--titre);font-weight:800;font-size:clamp(17px,2vw,22px)}
    .marque-teaser .txt a{display:inline-flex;margin-top:32px;background:#fff;color:var(--violet);padding:14px 26px;border-radius:999px;font-family:var(--titre);font-weight:800;font-size:14px;align-self:flex-start}

    /* CTA final */
    .cta-final{background:var(--noir);color:#fff;padding:clamp(60px,8vw,120px) var(--pad);text-align:center}
    .cta-final .t1{max-width:20ch;margin:0 auto;color:#fff}
    .cta-final p{margin-top:20px;max-width:52ch;margin-left:auto;margin-right:auto;color:rgba(255,255,255,.75)}
    .cta-final .bouton{margin-top:32px}
@endpush

@section('content')

{{-- ═══ HERO ═══ --}}
<section>
    <div class="hero">
        <div>
            <span class="sur">Régie &amp; studio · Côte d'Ivoire · depuis 1994</span>
            <h1>
                <span class="l">Votre marque,</span>
                <span class="l">partout où vit</span>
                <span class="l">votre audience.</span>
            </h1>
            <h2 class="sous-titre">Régie publicitaire en Côte d'Ivoire — 364 panneaux d'affichage dans 31 communes, publicité mobile et communication 360°.</h2>
            <p class="accroche">Nous n'offrons pas seulement de la visibilité : nous offrons une <strong>performance mesurable, orientée résultats</strong> — de l'exposition dans la rue jusqu'à la visite en point de vente.</p>
            <div class="actions">
                <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Rendre ma marque visible</a>
                <a class="bouton b-ligne" href="{{ route('cible.references') }}">Voir nos réalisations</a>
            </div>
        </div>
        <div class="hero-visuel">
            <div class="slot slot--sombre">
                Perroquet écarlate en vol
                <small>images/cible/perroquet-hero.jpg</small>
            </div>
        </div>
    </div>

    <div class="bande" aria-hidden="true">
        <div style="background:var(--rouge)"></div>
        <div style="background:var(--jaune)"></div>
        <div style="background:var(--vert)"></div>
        <div style="background:var(--violet)"></div>
        <div style="background:var(--bleu)"></div>
    </div>
</section>

{{-- ═══ TICKER ═══ --}}
<div class="ticker" aria-hidden="true" style="margin-top:clamp(24px,4vw,44px)">
    <div class="piste">
        <b style="background:var(--rouge)" class="num">364 panneaux</b>
        <b style="background:var(--jaune);color:#111" class="num">31 communes</b>
        <b style="background:var(--vert)" class="num">30 ans d'expertise</b>
        <b style="background:var(--violet)" class="num">5 territoires de visibilité</b>
        <b style="background:var(--bleu)" class="num">3 distinctions d'État</b>
        <b style="background:var(--noir)">De la rue au digital</b>
        <b style="background:var(--rouge)" class="num">364 panneaux</b>
        <b style="background:var(--jaune);color:#111" class="num">31 communes</b>
        <b style="background:var(--vert)" class="num">30 ans d'expertise</b>
        <b style="background:var(--violet)" class="num">5 territoires de visibilité</b>
        <b style="background:var(--bleu)" class="num">3 distinctions d'État</b>
        <b style="background:var(--noir)">De la rue au digital</b>
    </div>
</div>

{{-- ═══ APERÇU DES 5 MODULES ═══ --}}
<section class="modules">
    <div class="modules-tete rev">
        <span class="sur">Ce que nous faisons</span>
        <h2 class="t1">Cinq façons de rendre votre marque visible.</h2>
        <p>De l'affichage grand format sur les grands axes d'Abidjan à la campagne social media qui prolonge l'exposition, nous couvrons toute la chaîne — sur des supports que nous possédons ou pilotons directement.</p>
    </div>

    <div class="modules-grid">
        <div class="mod rev" style="--c:var(--rouge)">
            <div class="puce">01</div>
            <h4>Affichage grand format</h4>
            <p>364 panneaux dans 31 communes : classiques, lumipub, trivision, panoramiques, écrans digitaux. Un patrimoine géré en propre, avec preuve photo horodatée.</p>
            <a href="{{ route('cible.services') }}#rue">Explorer →</a>
        </div>
        <div class="mod rev" style="--c:var(--jaune)">
            <div class="puce">02</div>
            <h4>Publicité mobile</h4>
            <p>Camions publicitaires, motos, taxis, habillage de véhicules, chevalets. Le message va chercher l'audience là où elle est captive.</p>
            <a href="{{ route('cible.services') }}#mouvement">Explorer →</a>
        </div>
        <div class="mod rev" style="--c:var(--vert)">
            <div class="puce">03</div>
            <h4>Production audiovisuelle</h4>
            <p>Films institutionnels, spots TV et radio, motion design, contenus de marque. Une même équipe, de la conception à la diffusion.</p>
            <a href="{{ route('cible.services') }}#ecran">Explorer →</a>
        </div>
        <div class="mod rev" style="--c:var(--bleu)">
            <div class="puce">04</div>
            <h4>Communication digitale</h4>
            <p>Social media ads, SEO/SEA, activations interactives, drive-to-store. Nous transformons l'exposition en interaction, puis en visite.</p>
            <a href="{{ route('cible.services') }}#digital">Explorer →</a>
        </div>
        <div class="mod rev" style="--c:var(--violet)">
            <div class="puce">05</div>
            <h4>Street marketing &amp; brand experience</h4>
            <p>Opérations terrain, pop-up stores, roadshows, stands expérientiels. Le dernier mètre — là où la marque devient rencontre.</p>
            <a href="{{ route('cible.services') }}#terrain">Explorer →</a>
        </div>
        <div class="mod rev" style="--c:var(--noir)" style="background:var(--noir);color:#fff;border-color:var(--noir)">
            <div class="puce" style="background:var(--jaune);color:var(--noir)">→</div>
            <h4 style="color:#fff">Une campagne 360°&nbsp;?</h4>
            <p style="color:rgba(255,255,255,.85)">Nous orchestrons tous ces leviers dans une stratégie unique, mesurée du premier affichage jusqu'à la visite en point de vente.</p>
            <a href="{{ route('cible.contact') }}" style="color:var(--jaune);border-color:var(--jaune)">Nous consulter →</a>
        </div>
    </div>
</section>

{{-- ═══ MARQUE TEASER ═══ --}}
<section class="marque-teaser">
    <div class="img">
        <div class="slot">
            Deux perroquets écarlates
            <small>images/cible/perroquet-marque.jpg</small>
        </div>
    </div>
    <div class="txt rev">
        <span class="sur" style="opacity:.85">Notre histoire</span>
        <h2 class="t1" style="margin-top:14px">Se faire remarquer, c'est un métier.</h2>
        <p>Née dans l'affichage publicitaire en 1994, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire. Trente ans de terrain, fusionnés avec une exigence moderne de résultat.</p>
        <p class="fort">Créer l'impact. Construire la notoriété. Livrer la preuve.</p>
        <a href="{{ route('cible.qui') }}">Notre histoire complète →</a>
    </div>
</section>

{{-- ═══ CTA FINAL ═══ --}}
<section class="cta-final">
    <h2 class="t1">Un projet en tête&nbsp;?</h2>
    <p>Décrivez-nous votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée avec une proposition chiffrée.</p>
    <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander un devis</a>
</section>

@endsection

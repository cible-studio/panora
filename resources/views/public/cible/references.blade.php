@extends('public.cible._layout', [
    'seo_title'       => 'Réalisations — CIBLE · Nos campagnes en vrai',
    'seo_description' => 'Nos campagnes récentes : Orange, Cofina, Snedai, SGS, IFG, SIGFU et bien d\'autres. Preuves terrain, films, activations, stands.',
])

@push('page-css')
    .hero-ref{padding:clamp(60px,8vw,110px) var(--pad);background:linear-gradient(180deg,#fff,#F9F9F5)}
    .hero-ref .sur{color:var(--jaune)}
    .hero-ref h1{margin-top:14px;max-width:20ch}
    .hero-ref p{margin-top:24px;max-width:60ch;font-size:19px;color:#444}

    .travaux{padding:clamp(56px,8vw,100px) var(--pad)}
    .grille{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(14px,2vw,26px)}
    @media(max-width:900px){.grille{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.grille{grid-template-columns:1fr}}
    .carte{transition:transform .28s cubic-bezier(.2,.8,.3,1)}
    .carte:hover{transform:translateY(-8px)}
    .carte .vign{aspect-ratio:4/3;border-radius:22px;overflow:hidden;position:relative}
    .carte .vign::after{content:"";position:absolute;inset:auto 0 0 0;height:7px;background:var(--c);transform:scaleX(0);transform-origin:left;transition:transform .35s cubic-bezier(.2,.8,.3,1)}
    .carte:hover .vign::after{transform:scaleX(1)}
    .carte h4{font-family:var(--titre);font-weight:800;font-size:19px;margin-top:16px}
    .carte .meta{font-size:14px;color:#666;margin-top:4px;display:flex;align-items:center;gap:8px}
    .pastille{display:inline-block;width:10px;height:10px;border-radius:50%;background:var(--c)}

    .clients-band{padding:clamp(56px,8vw,100px) var(--pad);background:var(--gris)}
    .clients-band .entete{max-width:600px;margin-bottom:40px}
    .clients-band .sur{color:var(--vert)}
    .clients-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:16px;align-items:center}
    @media(max-width:900px){.clients-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:500px){.clients-grid{grid-template-columns:repeat(2,1fr)}}
    .clogo{aspect-ratio:2/1;background:#fff;border-radius:12px;display:flex;align-items:center;justify-content:center;padding:14px;transition:transform .2s}
    .clogo:hover{transform:translateY(-3px)}
    .clogo img{max-height:60px;max-width:100%;object-fit:contain}
    .clogo-txt{font-family:var(--titre);font-weight:800;color:#666;font-size:14px;text-align:center}
@endpush

@section('content')

<section class="hero-ref">
    <span class="sur">Nos réalisations</span>
    <h1 class="t1">Nos campagnes, en vrai.</h1>
    <p>Trente ans de campagnes concentrés en quelques exemples récents. Des marques qui ont choisi CIBLE parce qu'elles voulaient être vues — vraiment vues.</p>
</section>

<section class="travaux">
    <div class="grille rev">
        <article class="carte" style="--c:var(--violet)">
            <div class="vign"><div class="slot slot--sombre">Orange · Brand experience<small>images/cible/proj-orange.jpg</small></div></div>
            <h4>Orange</h4>
            <div class="meta"><span class="pastille"></span>Brand experience</div>
        </article>
        <article class="carte" style="--c:var(--vert)">
            <div class="vign"><div class="slot slot--sombre">Cofina · Film institutionnel<small>images/cible/proj-cofina.jpg</small></div></div>
            <h4>Cofina</h4>
            <div class="meta"><span class="pastille"></span>Film institutionnel</div>
        </article>
        <article class="carte" style="--c:var(--bleu)">
            <div class="vign"><div class="slot slot--sombre">Snedai · Stratégie 360°<small>images/cible/proj-snedai.jpg</small></div></div>
            <h4>Snedai</h4>
            <div class="meta"><span class="pastille"></span>Stratégie 360°</div>
        </article>
        <article class="carte" style="--c:var(--jaune)">
            <div class="vign"><div class="slot slot--sombre">SGS / SICTA<small>images/cible/proj-sgs.jpg</small></div></div>
            <h4>SGS · SICTA</h4>
            <div class="meta"><span class="pastille"></span>Création &amp; réseaux sociaux</div>
        </article>
        <article class="carte" style="--c:var(--rouge)">
            <div class="vign"><div class="slot slot--sombre">IFG · Stand expérientiel<small>images/cible/proj-ifg.jpg</small></div></div>
            <h4>IFG</h4>
            <div class="meta"><span class="pastille"></span>Stand expérientiel</div>
        </article>
        <article class="carte" style="--c:var(--violet)">
            <div class="vign"><div class="slot slot--sombre">SIGFU · Design &amp; architecture<small>images/cible/proj-sigfu.jpg</small></div></div>
            <h4>SIGFU</h4>
            <div class="meta"><span class="pastille"></span>Design &amp; architecture</div>
        </article>
    </div>
</section>

<section class="clients-band">
    <div class="entete rev">
        <span class="sur">Ils nous font confiance</span>
        <h2 class="t1">Les marques qui ont grandi avec nous.</h2>
    </div>
    <div class="clients-grid rev">
        @foreach([
            ['file'=>'client-banque-atlantique.png','alt'=>'Banque Atlantique'],
            ['file'=>'client-bgfibank.png','alt'=>'BGFIBank'],
            ['file'=>'client-danone.png','alt'=>'Danone'],
            ['file'=>'client-moov.png','alt'=>'Moov Africa'],
            ['file'=>'client-rimco.png','alt'=>'Rimco Motors'],
            ['file'=>'client-sipra.png','alt'=>'SIPRA'],
            ['file'=>'client-autre-1.png','alt'=>'Client partenaire'],
            ['file'=>'client-autre-2.png','alt'=>'Client partenaire'],
            ['file'=>'client-autre-3.png','alt'=>'Client partenaire'],
            ['file'=>'client-autre-4.png','alt'=>'Client partenaire'],
            ['file'=>'client-autre-5.png','alt'=>'Client partenaire'],
            ['file'=>'client-autre-6.png','alt'=>'Client partenaire'],
        ] as $c)
            <div class="clogo">
                @php $p = public_path('images/cible/' . $c['file']); @endphp
                @if(file_exists($p))
                    <img src="{{ asset('images/cible/' . $c['file']) }}" alt="{{ $c['alt'] }}" loading="lazy">
                @else
                    <div class="clogo-txt">{{ $c['alt'] }}</div>
                @endif
            </div>
        @endforeach
    </div>
</section>

<section style="padding:clamp(60px,8vw,100px) var(--pad);text-align:center;background:var(--noir);color:#fff">
    <h2 class="t1" style="color:#fff;max-width:22ch;margin:0 auto">Votre marque, la prochaine sur cette page&nbsp;?</h2>
    <div style="margin-top:32px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Démarrer un projet</a>
    </div>
</section>

@endsection

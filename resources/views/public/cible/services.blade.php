@extends('public.cible._layout', [
    'seo_title'       => 'Nos services — CIBLE · Régie, mobile, communication 360°',
    'seo_description' => 'Trois pôles complémentaires : régie publicitaire (364 panneaux, 6 formats), communication mobile (camions, motos, taxis, branding), communication 360° (stratégie, création, digital, street).',
])

@push('page-css')
    /* Hero */
    .hero-serv{padding:clamp(60px,8vw,110px) var(--pad);background:linear-gradient(180deg,#fff,#F9F9F5)}
    .hero-serv .sur{color:var(--bleu)}
    .hero-serv h1{margin-top:14px;max-width:22ch}
    .hero-serv p{margin-top:24px;max-width:62ch;font-size:19px;color:#444}

    /* Pôles — sections principales */
    .pole{padding:clamp(60px,8vw,110px) var(--pad);border-top:8px solid var(--c);position:relative;overflow:hidden}
    .pole-tag{display:inline-block;background:var(--c);color:#fff;padding:6px 14px;border-radius:999px;font-family:var(--titre);font-weight:800;font-size:11px;letter-spacing:.14em;text-transform:uppercase;margin-bottom:16px}
    .pole h2{font-family:var(--titre);font-weight:900;font-size:clamp(30px,4.2vw,52px);line-height:1.05;letter-spacing:-.025em}
    .pole h2 em{color:var(--c);font-style:normal}
    .pole > .wrap{display:grid;grid-template-columns:1fr 1fr;gap:clamp(30px,5vw,80px);align-items:start;max-width:1300px;margin:0 auto}
    .pole.rtl > .wrap{direction:rtl}
    .pole.rtl > .wrap > *{direction:ltr}
    @media(max-width:900px){.pole > .wrap{grid-template-columns:1fr}.pole.rtl > .wrap{direction:ltr}}
    .pole p.body{margin-top:18px;max-width:52ch;line-height:1.7;color:#333;font-size:16px}
    .pole .visu-col{display:flex;flex-direction:column;gap:20px}
    .pole .visu{aspect-ratio:4/5;border-radius:22px;overflow:hidden;background:var(--gris)}
    .pole .visu img{width:100%;height:100%;object-fit:cover}

    /* Bloc formats (pôle 1) */
    .block-h3{font-family:var(--titre);font-weight:800;font-size:20px;margin-top:32px;color:#111}
    .formats-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}
    @media(max-width:520px){.formats-grid{grid-template-columns:1fr}}
    .format-tile{display:flex;justify-content:space-between;align-items:center;gap:14px;padding:16px 18px;background:#fff;border:1.5px solid var(--gris);border-radius:12px;transition:border-color .2s,transform .2s}
    .format-tile:hover{border-color:var(--c);transform:translateY(-2px)}
    .format-tile strong{display:block;font-family:var(--titre);font-weight:800;font-size:15px;color:#111}
    .format-tile small{display:block;color:#666;font-size:13px;margin-top:2px}
    .format-tile span{font-family:var(--titre);font-weight:900;font-size:15px;color:var(--c);white-space:nowrap}

    /* Dispositifs mini (pôle 1 côté visuel) */
    .dispositifs-mini{background:#111;color:#fff;border-radius:16px;padding:24px 26px}
    .dispositifs-mini h4{font-family:var(--titre);font-weight:800;font-size:16px;color:var(--jaune);margin-bottom:14px}
    .dispositifs-mini ul{list-style:none}
    .dispositifs-mini li{padding:8px 0;border-bottom:1px dashed rgba(255,255,255,.15);font-size:14px;position:relative;padding-left:22px}
    .dispositifs-mini li::before{content:"▸";position:absolute;left:0;color:var(--jaune);font-weight:800}
    .dispositifs-mini li:last-child{border-bottom:0}

    /* Liste dispositifs (pôle 2) */
    .dispositifs-list{display:flex;flex-direction:column;gap:18px;margin-top:28px}
    .dispositifs-list > div{display:grid;grid-template-columns:60px 1fr;gap:18px;padding:20px;background:#fff;border-radius:14px;border:1px solid var(--gris);transition:border-color .2s,transform .2s}
    .dispositifs-list > div:hover{border-color:var(--c);transform:translateX(4px)}
    .dispositifs-list > div > span{font-size:32px;text-align:center;line-height:1}
    .dispositifs-list strong{font-family:var(--titre);font-weight:800;font-size:16px;color:#111;display:block;margin-bottom:6px}
    .dispositifs-list p{font-size:14px;color:#555;line-height:1.55}

    /* Offres 360 (pôle 3) */
    .offer-list{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:28px}
    @media(max-width:640px){.offer-list{grid-template-columns:1fr}}
    .offer-item{padding:22px 20px;background:var(--gris);border-radius:14px;border-left:4px solid var(--c);transition:transform .2s,box-shadow .2s}
    .offer-item:hover{transform:translateY(-3px);box-shadow:0 12px 24px -12px rgba(0,0,0,.12)}
    .offer-item h4{font-family:var(--titre);font-weight:800;font-size:16px;margin-bottom:8px;color:#111}
    .offer-item p{font-size:14px;color:#555;line-height:1.55}

    /* Workflow */
    .workflow{background:var(--noir);color:#fff;padding:clamp(60px,8vw,110px) var(--pad)}
    .workflow .entete{max-width:720px;margin-bottom:60px}
    .workflow .sur{color:var(--jaune)}
    .workflow h2{font-family:var(--titre);font-weight:900;font-size:clamp(28px,3.8vw,50px);line-height:1.05;letter-spacing:-.025em;color:#fff;margin-top:12px}
    .workflow h2 em{color:var(--jaune);font-style:normal}
    .workflow p{margin-top:20px;color:rgba(255,255,255,.75);font-size:17px;max-width:56ch}
    .workflow-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
    @media(max-width:900px){.workflow-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:500px){.workflow-grid{grid-template-columns:1fr}}
    .wf-step{padding:24px 22px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:14px;transition:transform .25s,border-color .25s;position:relative;overflow:hidden}
    .wf-step:hover{transform:translateY(-4px);border-color:var(--c)}
    .wf-step::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:var(--c);transform:scaleX(0);transform-origin:left;transition:transform .3s}
    .wf-step:hover::before{transform:scaleX(1)}
    .wf-step .n{font-family:var(--titre);font-weight:900;font-size:40px;color:var(--c);line-height:1;margin-bottom:14px}
    .wf-step h4{font-family:var(--titre);font-weight:800;font-size:17px;margin-bottom:8px;color:#fff}
    .wf-step p{font-size:13.5px;color:rgba(255,255,255,.7);line-height:1.55;margin-top:0;max-width:none}

    /* CTA */
    .cta-serv{padding:clamp(60px,8vw,120px) var(--pad);text-align:center}
    .cta-serv .t1{max-width:22ch;margin:0 auto}
    .cta-serv p{margin-top:20px;max-width:52ch;margin-left:auto;margin-right:auto;color:#666}
@endpush

@section('content')

<section class="hero-serv">
    <span class="sur">Nos services</span>
    <h1 class="t1">Trois pôles complémentaires, <em style="color:var(--rouge);font-style:normal">un seul interlocuteur.</em></h1>
    <p>Peu importe la forme que prendra votre campagne — panneau statique, camion mobile, écran digital, opération street ou dispositif global — vous travaillez avec la même équipe, du premier brief à la pige photo finale.</p>
</section>

{{-- ═══════════════════ PÔLE 01 · RÉGIE ═══════════════════ --}}
<section id="regie" class="pole" style="--c:var(--rouge)">
    <div class="wrap">
        <div class="rev">
            <span class="pole-tag">Pôle 01 · Régie publicitaire</span>
            <h2>La force d'un <em>réseau national.</em></h2>
            <p class="body">
                Notre cœur de métier depuis 30 ans. <strong>364 panneaux stratégiquement placés</strong>
                couvrent <strong>31 communes du pays</strong>. Chaque emplacement est référencé, contrôlé,
                maintenu — et disponible en visibilité temps réel pour votre équipe marketing.
            </p>

            <h3 class="block-h3">Formats disponibles</h3>
            <p style="color:#666;font-size:14.5px;margin-top:6px">Six catégories, treize formats — du petit format 2×1m au panoramique 14×5m.</p>

            <div class="formats-grid">
                @foreach([
                    ['Petit format',        '2×1m à 5×2m',     '2 → 10 m²'],
                    ['Classique',           '4×3m',            '12 m²'],
                    ['Grande dimension',    '6×3m à 6×4m',     '18 → 24 m²'],
                    ['Grand format',        '8×4m à 6×6m',     '32 → 36 m²'],
                    ['Très grand format',   '10×5m à 9×6m',    '50 → 54 m²'],
                    ['Panoramique',         '14×5m',           '70 m²'],
                ] as [$name, $dim, $surface])
                    <div class="format-tile">
                        <div>
                            <strong>{{ $name }}</strong>
                            <small>{{ $dim }}</small>
                        </div>
                        <span>{{ $surface }}</span>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="visu-col rev">
            <div class="visu">
                @php $img = 'images/cible/regie-lumipub.jpg'; @endphp
                @if(file_exists(public_path($img)))
                    <img src="{{ asset($img) }}" alt="Panneau publicitaire CIBLE — pôle régie" loading="lazy">
                @else
                    <div class="slot">Panneau publicitaire CIBLE<small>images/cible/regie-lumipub.jpg</small></div>
                @endif
            </div>
            <div class="dispositifs-mini">
                <h4>6 dispositifs affichage</h4>
                <ul>
                    <li>Panneaux classiques</li>
                    <li>Lumipub (caissons éclairés)</li>
                    <li>Trivision (3 visuels en rotation)</li>
                    <li>Panoramiques grand format</li>
                    <li>Écrans digitaux</li>
                    <li>Écrans en magasins</li>
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════ PÔLE 02 · MOBILE ═══════════════════ --}}
<section id="mobile" class="pole rtl" style="--c:var(--jaune);background:#FFFCF3">
    <div class="wrap">
        <div class="rev">
            <span class="pole-tag" style="color:#111">Pôle 02 · Communication mobile</span>
            <h2>Votre message <em>en mouvement.</em></h2>
            <p class="body">
                Là où le panneau statique attend son public, la publicité mobile va vers lui.
                Traversée d'Abidjan aux heures de pointe, présence pendant un événement,
                saturation d'un quartier commerçant en fin de semaine — chaque dispositif mobile
                a son usage stratégique.
            </p>

            <div class="dispositifs-list">
                <div><span>🚛</span><div><strong>Camions publicitaires</strong><p>Grandes surfaces d'affichage roulantes. Idéal pour lancement produit, opérations événementielles, saturation d'un axe.</p></div></div>
                <div><span>🏍</span><div><strong>Motos publicitaires</strong><p>Agilité pour circuler dans le trafic dense. Zones difficiles d'accès en camion, campagnes de proximité.</p></div></div>
                <div><span>🚗</span><div><strong>Branding véhicules</strong><p>Habillage complet de flottes d'entreprise ou de véhicules dédiés à la campagne.</p></div></div>
                <div><span>🚕</span><div><strong>Branding taxis &amp; cars</strong><p>Publicité sur les transports en commun urbains et inter-urbains — visibilité massive à coût maîtrisé.</p></div></div>
                <div><span>🪧</span><div><strong>Chevalets publicitaires</strong><p>Petits dispositifs pour événements, marchés, points de vente. Rapides à installer, économiques.</p></div></div>
            </div>
        </div>
        <div class="visu-col rev">
            <div class="visu">
                @php $img = 'images/cible/mobile-camion.jpg'; @endphp
                @if(file_exists(public_path($img)))
                    <img src="{{ asset($img) }}" alt="Camion publicitaire CIBLE — écran LED mobile" loading="lazy">
                @else
                    <div class="slot">Camion publicitaire CIBLE<small>images/cible/mobile-camion.jpg</small></div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════ PÔLE 03 · 360° ═══════════════════ --}}
<section id="globale" class="pole" style="--c:var(--violet)">
    <div class="wrap">
        <div class="rev">
            <span class="pole-tag">Pôle 03 · Communication 360°</span>
            <h2>De l'idée <em>à l'exécution.</em></h2>
            <p class="body">
                Un panneau vide ne dit rien. Un panneau mal conçu dit mal. Nous proposons une
                chaîne complète : <strong>stratégie, création graphique, production visuelle,
                présence digitale</strong> — pour que votre message soit aussi fort dans la rue
                que dans les fils d'actualité.
            </p>

            <div class="offer-list">
                @foreach([
                    ['Création graphique', 'Studio interne : visuels d\'affichage, adaptation aux formats, production print et digital.'],
                    ['Stratégie de communication', 'Recommandation d\'un plan média, choix des emplacements, calendrier de diffusion.'],
                    ['Street marketing', 'Opérations terrain de proximité : distribution, animation commerciale, échantillonnage.'],
                    ['Digital &amp; réseaux sociaux', 'Extension de votre campagne outdoor vers les plateformes numériques.'],
                    ['Relations presse', 'Mise en contact avec les médias ivoiriens, coordination d\'annonces institutionnelles.'],
                    ['Production audiovisuelle', 'Films institutionnels, spots TV et radio, motion design, contenus de marque.'],
                ] as [$name, $desc])
                    <div class="offer-item">
                        <h4>{{ $name }}</h4>
                        <p>{!! $desc !!}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="visu-col rev">
            <div class="visu">
                @php $img = 'images/cible/hero-plateau-night.jpg'; @endphp
                @if(file_exists(public_path($img)))
                    <img src="{{ asset($img) }}" alt="Campagne 360° CIBLE — panneau grand format" loading="lazy">
                @else
                    <div class="slot slot--sombre">Campagne 360° · panneau Guinness<small>images/cible/hero-plateau-night.jpg</small></div>
                @endif
            </div>
        </div>
    </div>
</section>

{{-- ═══════════════════ WORKFLOW ═══════════════════ --}}
<section class="workflow">
    <div class="entete rev">
        <span class="sur">Notre méthode</span>
        <h2>De la demande <em>à l'affichage.</em></h2>
        <p>Huit étapes, un interlocuteur unique, une traçabilité complète. Vous recevez la pige photo horodatée dès que votre affichage est en place — plus besoin de dépêcher quelqu'un pour vérifier.</p>
    </div>

    <div class="workflow-grid">
        @foreach([
            ['1', 'Demande',       'Vous exposez besoin, cible, budget.',     'var(--rouge)'],
            ['2', 'Sélection',     'Emplacements proposés selon zone et format.', 'var(--jaune)'],
            ['3', 'Proposition',   'Devis détaillé, tarifs, calendrier.',     'var(--vert)'],
            ['4', 'Validation',    'Signature de l\'accord.',                 'var(--bleu)'],
            ['5', 'Planification', 'Équipes terrain assignées.',              'var(--violet)'],
            ['6', 'Pose',          'Techniciens sur site.',                   'var(--rouge)'],
            ['7', 'Pige photo',    'Preuve horodatée envoyée.',               'var(--jaune)'],
            ['8', 'Suivi',         'Espace client dédié en ligne.',           'var(--vert)'],
        ] as [$n, $t, $d, $c])
            <div class="wf-step" style="--c:{{ $c }}">
                <div class="n">{{ $n }}</div>
                <h4>{{ $t }}</h4>
                <p>{{ $d }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- ═══════════════════ CTA ═══════════════════ --}}
<section class="cta-serv">
    <span class="sur" style="color:var(--rouge)">Prochaine étape</span>
    <h2 class="t1" style="margin-top:14px">Un projet à faire décoller&nbsp;?</h2>
    <p>Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée avec une proposition chiffrée.</p>
    <div style="margin-top:32px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander un devis</a>
        <a class="bouton b-ligne" href="{{ route('cible.references') }}">Voir nos réalisations</a>
    </div>
</section>

@endsection

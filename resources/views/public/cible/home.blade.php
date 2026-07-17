@extends('public.cible._layout', [
    'seo_title'       => 'CIBLE CI — 30 ans, 364 panneaux, 31 communes. Régie N°1 en Côte d\'Ivoire',
    'seo_description' => 'CIBLE CI : première régie publicitaire de Côte d\'Ivoire. 30 ans d\'expertise · 364 panneaux · 31 communes · affichage, mobile, digital. Visez juste avec CIBLE.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section class="hero-cible" style="padding: 90px 0 100px; position: relative; overflow: hidden; background: linear-gradient(180deg, #ffffff 0%, var(--bg-cream) 100%);">
        <div class="wrap" style="position: relative; z-index: 2;">
            <div class="hero-grid">
                <div>
                    <span class="eyebrow">Régie publicitaire · Côte d'Ivoire · Depuis 1994</span>
                    <h1 class="hero-title">
                        Depuis 30 ans,<br>
                        la Côte d'Ivoire<br>
                        nous a confié <em>ses murs.</em>
                    </h1>
                    <p class="lead">
                        Première régie publicitaire de Côte d'Ivoire, CIBLE opère un réseau
                        de 364 panneaux répartis dans 31 communes. De la stratégie à la pose,
                        du panneau statique à la campagne 360°, nous portons la parole des
                        marques qui comptent.
                    </p>
                    <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 32px;">
                        <a href="{{ route('cible.contact') }}" class="btn btn-accent">Demander un devis</a>
                        <a href="{{ route('cible.reseau') }}" class="btn btn-outline">Voir le réseau</a>
                    </div>
                    <div class="hero-signature">« On te voit partout »</div>
                </div>

                <div class="hero-visual">
                    <div class="terrain-placeholder" style="aspect-ratio: 4/5; border-radius: 4px;">
                        <div>
                            <strong>Photo hero terrain</strong>
                            Lumipub Plateau, nuit
                            <small>public/images/cible/hero-plateau-night.jpg</small>
                        </div>
                    </div>
                    <div class="hero-chip">
                        <span class="hero-chip-num">30</span>
                        <span class="hero-chip-lbl">ans d'expertise<br>en Côte d'Ivoire</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CHIFFRES CLÉS ═══════════════════ --}}
    <section class="metrics-cible" style="background: var(--ink); color: #fff; border: none;">
        <div class="wrap">
            <div class="metrics-grid">
                <div>
                    <span class="metric-num">30</span>
                    <span class="metric-label">Ans d'expertise</span>
                </div>
                <div>
                    <span class="metric-num">364</span>
                    <span class="metric-label">Panneaux</span>
                </div>
                <div>
                    <span class="metric-num">31</span>
                    <span class="metric-label">Communes couvertes</span>
                </div>
                <div>
                    <span class="metric-num">N°<em>1</em></span>
                    <span class="metric-label">Réseau d'affichage en CI</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ 3 PÔLES ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Nos pôles de métier</span>
                <h2 class="section-title">Trois métiers, <em>une même exigence.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Du panneau planté sur un boulevard d'Abidjan à la campagne mobile qui traverse
                    le pays, en passant par la stratégie de communication globale — trois pôles
                    complémentaires, un seul interlocuteur.
                </p>
            </div>

            <div class="pillars-grid-cible">
                <div class="pillar-c">
                    <div class="terrain-placeholder" style="aspect-ratio: 16/10; margin-bottom: 22px;">
                        <div>
                            <strong>Photo panneaux</strong>
                            Réseau affichage classique + lumipub
                            <small>public/images/cible/pole-1-affichage.jpg</small>
                        </div>
                    </div>
                    <div class="pillar-num">01</div>
                    <h3 class="block-title">Régie publicitaire</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        364 panneaux stratégiquement placés. Classiques, lumipub, trivision,
                        panoramiques, écrans digitaux et en magasins. Le patrimoine terrain
                        de la Côte d'Ivoire.
                    </p>
                    <a href="{{ route('cible.services') }}#regie" class="arrow-link" style="margin-top: 12px;">Découvrir</a>
                </div>

                <div class="pillar-c">
                    <div class="terrain-placeholder" style="aspect-ratio: 16/10; margin-bottom: 22px;">
                        <div>
                            <strong>Photo camion pub</strong>
                            Communication mobile en action
                            <small>public/images/cible/pole-2-mobile.jpg</small>
                        </div>
                    </div>
                    <div class="pillar-num">02</div>
                    <h3 class="block-title">Communication mobile</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        Camions publicitaires, motos, branding véhicules et taxis, chevalets
                        publicitaires. Votre message en mouvement, partout dans la ville.
                    </p>
                    <a href="{{ route('cible.services') }}#mobile" class="arrow-link" style="margin-top: 12px;">Découvrir</a>
                </div>

                <div class="pillar-c">
                    <div class="terrain-placeholder" style="aspect-ratio: 16/10; margin-bottom: 22px;">
                        <div>
                            <strong>Photo équipe créa</strong>
                            Studio · stratégie · digital
                            <small>public/images/cible/pole-3-360.jpg</small>
                        </div>
                    </div>
                    <div class="pillar-num">03</div>
                    <h3 class="block-title">Communication 360°</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        Création graphique, stratégie, street marketing, digital et réseaux
                        sociaux, relations presse. De l'idée à l'exécution, un partenaire global.
                    </p>
                    <a href="{{ route('cible.services') }}#globale" class="arrow-link" style="margin-top: 12px;">Découvrir</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ COUVERTURE GÉOGRAPHIQUE ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="align-items: center;">
                <div>
                    <span class="eyebrow">Notre couverture</span>
                    <h2 class="section-title">Présents dans <em>31 communes</em>,<br>de Bouaké à San-Pédro.</h2>
                    <p class="body">
                        Notre patrimoine terrain n'est pas un slogan : c'est
                        <strong>180 panneaux à Abidjan</strong> dans 14 communes du District,
                        et <strong>184 panneaux à l'intérieur du pays</strong> dans 17 villes
                        — de Bouaké (54) à Adiaké (1), en passant par Yamoussoukro, Korhogo,
                        Daloa, San-Pédro et Gagnoa.
                    </p>
                    <p class="body">
                        Aucun autre réseau ne combine cette densité en zone Abidjan avec cette
                        présence structurée en régions.
                    </p>
                    <div style="margin-top: 26px;">
                        <a href="{{ route('cible.reseau') }}" class="arrow-link">Voir le réseau complet</a>
                    </div>
                </div>
                <div>
                    <div class="terrain-placeholder" style="aspect-ratio: 4/3;">
                        <div>
                            <strong>Carte réseau CI</strong>
                            31 communes avec pins CIBLE
                            <small>public/images/cible/carte-reseau.jpg</small>
                        </div>
                    </div>
                    <div class="coverage-mini-stats">
                        <div><span>180</span><small>panneaux<br>Abidjan · 14 communes</small></div>
                        <div><span>184</span><small>panneaux<br>Intérieur · 17 villes</small></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CLIENTS ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="text-align: center; max-width: 620px; margin: 0 auto 50px;">
                <span class="eyebrow">Ils nous font confiance</span>
                <h2 class="section-title">Des marques qui pèsent, <em>qui reviennent.</em></h2>
            </div>

            <div class="clients-strip">
                @foreach(['Danone', 'SIPRA', 'Moov Africa', 'Banque Atlantique', 'BGFIBank', 'Rimco Motors'] as $c)
                    <div class="client-tile">
                        <span>{{ $c }}</span>
                        <small>logo à intégrer</small>
                    </div>
                @endforeach
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ route('cible.references') }}" class="arrow-link">Voir toutes nos références</a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ DISTINCTIONS ═══════════════════ --}}
    <section style="background: var(--ink); color: #fff; border: none;">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow" style="color: var(--accent);">Reconnaissances officielles</span>
                <h2 class="section-title" style="color: #fff;">Trois distinctions <em>de l'État ivoirien.</em></h2>
                <p class="body" style="color: rgba(255,255,255,0.7); font-size: 17px;">
                    L'excellence de la régie et de sa direction, saluée à trois reprises
                    par les institutions ivoiriennes de la communication et de la République.
                </p>
            </div>

            <div class="honors-grid">
                <div class="honor-card">
                    <div class="honor-medal">2016</div>
                    <h3>2ème prix<br>du meilleur publicitaire</h3>
                    <p>Distinction professionnelle du secteur de la publicité ivoirienne.</p>
                </div>
                <div class="honor-card">
                    <div class="honor-medal">2019</div>
                    <h3>Chevalier<br>de l'Ordre du Mérite<br>de la Communication</h3>
                    <p>Reconnaissance de la contribution à la structuration du métier.</p>
                </div>
                <div class="honor-card">
                    <div class="honor-medal">2020</div>
                    <h3>Officier<br>de l'Ordre du Mérite<br>National</h3>
                    <p>Distinction républicaine pour services rendus au pays.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ WORKFLOW CLIENT ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Notre méthode</span>
                <h2 class="section-title">De la demande à la campagne active, <em>en 8 étapes.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Un processus éprouvé, transparent, où l'annonceur voit ce qui se passe
                    à chaque étape — jusqu'à recevoir la pige photo horodatée depuis le terrain.
                </p>
            </div>

            <div class="workflow-grid">
                @foreach([
                    ['1', 'Demande client', 'Vous nous exposez votre besoin, votre cible, votre budget indicatif.'],
                    ['2', 'Sélection des emplacements', 'Nous proposons les panneaux disponibles par zone, format et période.'],
                    ['3', 'Proposition commerciale', 'Devis détaillé : panneaux, tarifs, calendrier, options.'],
                    ['4', 'Validation client', 'Signature de l\'accord, réservation des panneaux confirmée.'],
                    ['5', 'Planification de la pose', 'Les équipes terrain sont assignées, la logistique s\'organise.'],
                    ['6', 'Pose des visuels', 'Nos techniciens interviennent sur site à date programmée.'],
                    ['7', 'Pige photo', 'Preuve terrain horodatée : votre affichage est en place.'],
                    ['8', 'Suivi de campagne', 'Espace client dédié en ligne pour suivre votre campagne.'],
                ] as [$n, $t, $d])
                    <div class="wf-step">
                        <div class="wf-num">{{ $n }}</div>
                        <h4>{{ $t }}</h4>
                        <p>{{ $d }}</p>
                    </div>
                @endforeach
            </div>

            <div class="aside-note" style="max-width: 780px; margin: 40px auto 0;">
                Chaque annonceur reçoit un espace de suivi personnalisé où il consulte
                ses campagnes, télécharge ses piges d'affichage et ses factures en direct.
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA FINAL ═══════════════════ --}}
    <section style="text-align: center; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow">Prochaine étape</span>
            <h2 class="section-title" style="margin-bottom: 26px;">
                Votre campagne <em>commence ici.</em>
            </h2>
            <p class="lead" style="margin: 0 auto 36px; text-align: center;">
                Décrivez-nous votre besoin en 2 minutes. Notre équipe commerciale vous
                rappelle dans la journée ouvrée avec une proposition sur mesure.
            </p>
            <a href="{{ route('cible.contact') }}" class="btn btn-accent" style="font-size: 16px; padding: 17px 34px;">
                Demander un devis
            </a>
            <p style="margin-top: 24px; font-size: 13.5px; color: var(--ink-5);">
                Ou appelez directement le <a href="tel:+2250798496674" style="color: var(--ink-3); border-bottom: 1px solid var(--line);">07 98 49 66 74</a>
            </p>
        </div>
    </section>

    @push('head')
    <style>
        .hero-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 70px;
            align-items: center;
        }
        .hero-signature {
            margin-top: 40px;
            font-family: 'Playfair Display', serif;
            font-style: italic;
            font-size: 20px;
            color: var(--accent);
        }
        .hero-visual { position: relative; }
        .hero-chip {
            position: absolute;
            bottom: -30px; left: -30px;
            background: var(--ink);
            color: #fff;
            padding: 22px 26px;
            border-radius: 4px;
            box-shadow: 0 20px 40px -12px rgba(0,0,0,0.25);
            display: flex; align-items: center; gap: 16px;
        }
        .hero-chip-num {
            font-family: 'Playfair Display', serif;
            font-size: 54px;
            font-weight: 500;
            color: var(--accent);
            line-height: 0.9;
        }
        .hero-chip-lbl {
            font-size: 12.5px;
            font-weight: 600;
            letter-spacing: 0.05em;
            line-height: 1.4;
            color: rgba(255,255,255,0.85);
        }
        @media (max-width: 900px) {
            .hero-grid { grid-template-columns: 1fr; gap: 50px; }
            .hero-chip { position: static; margin-top: 20px; }
        }

        /* Chiffres */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
        }
        .metric-num {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 76px;
            font-weight: 400;
            color: var(--accent);
            line-height: 0.95;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .metric-num em { font-style: normal; color: rgba(255,255,255,0.9); }
        .metric-label {
            display: block;
            font-size: 12.5px;
            font-weight: 700;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.75);
        }
        @media (max-width: 720px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); gap: 30px; }
            .metric-num { font-size: 60px; }
        }

        /* Piliers */
        .pillars-grid-cible {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }
        .pillar-c {
            padding: 0;
            transition: transform 0.2s;
        }
        .pillar-c:hover { transform: translateY(-3px); }
        .pillar-num {
            display: inline-block;
            font-family: 'Playfair Display', serif;
            font-size: 14px;
            color: var(--accent);
            font-weight: 600;
            letter-spacing: 0.08em;
            margin-bottom: 12px;
        }
        @media (max-width: 900px) { .pillars-grid-cible { grid-template-columns: 1fr; } }

        /* Couverture */
        .coverage-mini-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 20px;
        }
        .coverage-mini-stats > div {
            background: #fff;
            border: 1px solid var(--line);
            padding: 18px;
            border-radius: 4px;
            text-align: center;
        }
        .coverage-mini-stats span {
            display: block;
            font-family: 'Playfair Display', serif;
            font-size: 40px;
            font-weight: 500;
            color: var(--accent);
            line-height: 1;
        }
        .coverage-mini-stats small {
            display: block;
            font-size: 11.5px;
            color: var(--ink-4);
            margin-top: 6px;
            line-height: 1.4;
        }

        /* Clients */
        .clients-strip {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 12px;
        }
        .client-tile {
            background: var(--bg-cream);
            border: 1px solid var(--line);
            padding: 24px 12px;
            border-radius: 4px;
            text-align: center;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            aspect-ratio: 3 / 2;
        }
        .client-tile span {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 500;
            color: var(--ink);
        }
        .client-tile small {
            font-size: 10px;
            color: var(--ink-5);
            margin-top: 6px;
            font-family: monospace;
        }
        @media (max-width: 900px) { .clients-strip { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 560px) { .clients-strip { grid-template-columns: repeat(2, 1fr); } }

        /* Distinctions */
        .honors-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .honor-card {
            padding: 34px 28px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 4px;
            position: relative;
        }
        .honor-medal {
            display: inline-flex;
            align-items: center; justify-content: center;
            width: 60px; height: 60px;
            border-radius: 50%;
            border: 2px solid var(--accent);
            color: var(--accent);
            font-family: 'Playfair Display', serif;
            font-weight: 500;
            font-size: 15px;
            margin-bottom: 20px;
        }
        .honor-card h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            font-weight: 500;
            color: #fff;
            line-height: 1.25;
            margin-bottom: 12px;
        }
        .honor-card p {
            font-size: 13.5px;
            color: rgba(255,255,255,0.6);
            line-height: 1.55;
        }
        @media (max-width: 900px) { .honors-grid { grid-template-columns: 1fr; } }

        /* Workflow */
        .workflow-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .wf-step {
            padding: 22px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px;
            border-top: 3px solid var(--accent);
        }
        .wf-num {
            font-family: 'Playfair Display', serif;
            font-size: 30px;
            font-weight: 500;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 12px;
        }
        .wf-step h4 {
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }
        .wf-step p {
            font-size: 13px;
            color: var(--ink-4);
            line-height: 1.55;
        }
        @media (max-width: 900px) { .workflow-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .workflow-grid { grid-template-columns: 1fr; } }
    </style>
    @endpush

@endsection

@extends('public.cible._layout', [
    'seo_title'       => 'Nos services — CIBLE CI · Régie, mobile, communication 360°',
    'seo_description' => 'Trois pôles de service CIBLE : régie publicitaire (364 panneaux), communication mobile (camions, motos, branding), et communication 360° (stratégie, créa, digital).',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap-narrow">
            <span class="eyebrow reveal reveal-fade">Nos services</span>
            <h1 class="hero-title reveal" data-delay="1" style="font-size: clamp(38px, 5vw, 60px);">
                Trois pôles complémentaires,<br>
                <em>un seul interlocuteur.</em>
            </h1>
            <p class="lead reveal" data-delay="2">
                Peu importe la forme que prendra votre campagne — panneau statique, camion
                mobile, écran digital, opération street ou dispositif global — vous
                travaillez avec la même équipe, du premier brief à la pige photo finale.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ PÔLE 1 · RÉGIE ═══════════════════ --}}
    <section id="regie">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow reveal reveal-fade">Pôle 01 · Régie publicitaire</span>
                    <h2 class="section-title reveal" data-delay="1">La force d'un <em>réseau national.</em></h2>
                    <p class="body">
                        Notre cœur de métier depuis 30 ans. 364 panneaux stratégiquement placés
                        couvrent 31 communes du pays. Chaque emplacement est référencé, contrôlé,
                        maintenu — et disponible en visibilité temps réel pour votre équipe
                        marketing.
                    </p>

                    <h3 class="block-title" style="margin-top: 30px;">Formats disponibles</h3>
                    <p class="body">
                        Six catégories, treize formats — du petit format 2×1m au panoramique
                        14×5m. Choisissez selon votre message, votre budget et l'emplacement.
                    </p>

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
                <div>
                    <div class="photo-tile reveal reveal-right" style="aspect-ratio: 4/5; margin-bottom: 20px;">
                        <img src="{{ asset('images/cible/regie-lumipub.jpg') }}" alt="Panneau publicitaire CIBLE — pôle régie">
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
        </div>
    </section>

    {{-- ═══════════════════ PÔLE 2 · MOBILE ═══════════════════ --}}
    <section id="mobile" style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow reveal reveal-fade">Pôle 02 · Communication mobile</span>
                    <h2 class="section-title reveal" data-delay="1">Votre message <em>en mouvement.</em></h2>
                    <p class="body">
                        Là où le panneau statique attend son public, la publicité mobile va
                        vers lui. Traversée d'Abidjan aux heures de pointe, présence pendant
                        un événement, saturation d'un quartier commerçant en fin de semaine —
                        chaque dispositif mobile a son usage stratégique.
                    </p>

                    <div class="dispositifs-list">
                        <div>
                            <span>🚛</span>
                            <div>
                                <strong>Camions publicitaires</strong>
                                <p>Grandes surfaces d'affichage roulantes. Idéal pour lancement produit, opérations événementielles, saturation d'un axe.</p>
                            </div>
                        </div>
                        <div>
                            <span>🏍</span>
                            <div>
                                <strong>Motos publicitaires</strong>
                                <p>Agilité pour circuler dans le trafic dense. Zones difficiles d'accès en camion, campagnes de proximité.</p>
                            </div>
                        </div>
                        <div>
                            <span>🚗</span>
                            <div>
                                <strong>Branding véhicules</strong>
                                <p>Habillage complet de flottes d'entreprise ou de véhicules dédiés à la campagne.</p>
                            </div>
                        </div>
                        <div>
                            <span>🚕</span>
                            <div>
                                <strong>Branding taxis &amp; cars</strong>
                                <p>Publicité sur les transports en commun urbains et inter-urbains — visibilité massive à coût maîtrisé.</p>
                            </div>
                        </div>
                        <div>
                            <span>🪧</span>
                            <div>
                                <strong>Chevalets publicitaires</strong>
                                <p>Petits dispositifs pour événements, marchés, points de vente. Rapides à installer, économiques.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="direction: ltr;">
                    <div class="photo-tile reveal reveal-left" style="aspect-ratio: 4/5;">
                        <img src="{{ asset('images/cible/mobile-camion.jpg') }}" alt="Camion publicitaire CIBLE — écran LED mobile">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ PÔLE 3 · 360° ═══════════════════ --}}
    <section id="globale">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow reveal reveal-fade">Pôle 03 · Communication 360°</span>
                    <h2 class="section-title reveal" data-delay="1">De l'idée <em>à l'exécution.</em></h2>
                    <p class="body">
                        Un panneau vide ne dit rien. Un panneau mal conçu dit mal. Nous
                        proposons une chaîne complète : stratégie, création graphique,
                        production visuelle, présence digitale — pour que votre message
                        soit aussi fort dans la rue que dans les fils d'actualité.
                    </p>

                    <div class="offer-list">
                        @foreach([
                            ['Création graphique', 'Studio interne : visuels d\'affichage, adaptation aux formats, production print et digital.'],
                            ['Stratégie de communication', 'Recommandation d\'un plan média, choix des emplacements, calendrier de diffusion.'],
                            ['Street marketing', 'Opérations terrain de proximité : distribution, animation commerciale, échantillonnage.'],
                            ['Digital &amp; réseaux sociaux', 'Extension de votre campagne outdoor vers les plateformes numériques.'],
                            ['Relations presse', 'Mise en contact avec les médias ivoiriens, coordination d\'annonces institutionnelles.'],
                        ] as [$name, $desc])
                            <div class="offer-item">
                                <h4>{{ $name }}</h4>
                                <p>{!! $desc !!}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <div class="photo-tile reveal reveal-right" style="aspect-ratio: 4/5;">
                        <img src="{{ asset('images/cible/hero-plateau-night.jpg') }}" alt="Panneau CIBLE Guinness — campagne 360°">
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ WORKFLOW ═══════════════════ --}}
    <section style="background: var(--ink); color: #fff; border: none;">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow" style="color: var(--accent);">Notre méthode</span>
                <h2 class="section-title reveal" data-delay="1" style="color: #fff;">De la demande <em>à l'affichage.</em></h2>
                <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                    Huit étapes, un interlocuteur unique, une traçabilité complète.
                    Vous recevez la pige photo horodatée dès que votre affichage est
                    en place — plus besoin de dépêcher quelqu'un pour vérifier.
                </p>
            </div>

            <div class="workflow-c">
                @foreach([
                    ['1', 'Demande', 'Vous exposez besoin, cible, budget.'],
                    ['2', 'Sélection', 'Emplacements proposés selon zone et format.'],
                    ['3', 'Proposition', 'Devis détaillé, tarifs, calendrier.'],
                    ['4', 'Validation', 'Signature de l\'accord.'],
                    ['5', 'Planification', 'Équipes terrain assignées.'],
                    ['6', 'Pose', 'Techniciens sur site.'],
                    ['7', 'Pige photo', 'Preuve horodatée envoyée.'],
                    ['8', 'Suivi', 'Espace client dédié en ligne.'],
                ] as [$n, $t, $d])
                    <div class="wfc-step">
                        <div class="wfc-num">{{ $n }}</div>
                        <h4>{{ $t }}</h4>
                        <p>{{ $d }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow reveal reveal-fade">Prochaine étape</span>
            <h2 class="section-title reveal" data-delay="1" style="margin-bottom: 26px;">
                Un besoin en tête ? <em>Parlons-en.</em>
            </h2>
            <a href="{{ route('cible.contact') }}" class="btn btn-accent" style="font-size: 16px; padding: 17px 34px;">
                Demander un devis
            </a>
        </div>
    </section>

    @push('head')
    <style>
        .formats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        .format-tile {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 16px;
            background: var(--bg-cream);
            border: 1px solid var(--line);
            border-radius: 4px;
        }
        .format-tile strong {
            display: block;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 15px;
            color: var(--ink);
        }
        .format-tile small {
            display: block;
            font-size: 12px;
            color: var(--ink-4);
            margin-top: 2px;
        }
        .format-tile span {
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 15px;
            color: var(--accent);
        }
        @media (max-width: 560px) { .formats-grid { grid-template-columns: 1fr; } }

        .dispositifs-mini {
            padding: 26px;
            background: var(--bg-cream);
            border: 1px solid var(--line);
            border-radius: 4px;
        }
        .dispositifs-mini h4 {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 14px;
        }
        .dispositifs-mini ul {
            list-style: none;
            padding: 0;
        }
        .dispositifs-mini li {
            padding: 8px 0;
            font-size: 14.5px;
            color: var(--ink-2);
            border-bottom: 1px dashed var(--line);
        }
        .dispositifs-mini li:last-child { border-bottom: none; }

        .dispositifs-list {
            display: flex; flex-direction: column;
            gap: 22px;
        }
        .dispositifs-list > div {
            display: flex; gap: 20px;
            align-items: flex-start;
        }
        .dispositifs-list span {
            font-size: 30px;
            flex-shrink: 0;
            width: 50px;
            display: inline-flex;
            justify-content: center;
        }
        .dispositifs-list strong {
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 19px;
            color: var(--ink);
            display: block;
            margin-bottom: 6px;
        }
        .dispositifs-list p {
            font-size: 14.5px;
            color: var(--ink-3);
            line-height: 1.6;
        }

        .offer-list {
            display: flex; flex-direction: column;
            gap: 8px;
        }
        .offer-item {
            padding: 22px 24px;
            background: var(--bg-cream);
            border: 1px solid var(--line);
            border-left: 3px solid var(--accent);
            border-radius: 4px;
        }
        .offer-item h4 {
            font-family: 'Inter', sans-serif;
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 6px;
            color: var(--ink);
        }
        .offer-item p {
            font-size: 14.5px;
            color: var(--ink-3);
            line-height: 1.6;
        }

        .workflow-c {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .wfc-step {
            padding: 22px 20px;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 4px;
            border-top: 3px solid var(--accent);
        }
        .wfc-num {
            font-family: 'Inter', sans-serif;
            font-size: 28px;
            color: var(--accent);
            line-height: 1;
            margin-bottom: 10px;
        }
        .wfc-step h4 {
            font-family: 'Inter', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 6px;
        }
        .wfc-step p {
            font-size: 12.5px;
            color: rgba(255,255,255,0.65);
            line-height: 1.55;
        }
        @media (max-width: 900px) { .workflow-c { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 560px) { .workflow-c { grid-template-columns: 1fr; } }
    </style>
    @endpush

@endsection

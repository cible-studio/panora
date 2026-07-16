@extends('public.landing._layout', [
    'seo_title'       => 'Panora — Le système d\'exploitation des régies OOH',
    'seo_description' => 'Panora unifie la vie d\'une régie d\'affichage extérieur : inventaire, campagnes, terrain, facturation FNE, taxes communales, direction. Éprouvé en Côte d\'Ivoire.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section class="hero-lp has-grain" style="padding: 90px 0 40px; position: relative; overflow: hidden; background: linear-gradient(180deg, #ffffff 0%, var(--bg-cream) 100%);">
        <span class="brand-blot blot-a"></span>
        <span class="brand-blot blot-d"></span>

        <div class="wrap" style="position: relative; z-index: 2;">
            <div class="hero-grid">
                <div>
                    <span class="eyebrow">Plateforme d'exploitation OOH · Côte d'Ivoire</span>
                    <h1 class="hero-title" style="margin-bottom: 24px;">
                        Une régie OOH<br>
                        ne se pilote plus<br>
                        dans <em>un tableur.</em>
                    </h1>
                    <p class="lead">
                        Panora réunit dans une seule plateforme tout ce qu'exige aujourd'hui l'exploitation
                        d'une régie d'affichage extérieur : inventaire, campagnes, terrain, facturation FNE,
                        taxes communales, relation client, performance. Le tissu numérique d'une régie moderne.
                    </p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 32px;">
                        <a href="{{ route('landing.demo') }}" class="btn btn-dark">Demander une démo</a>
                        <a href="{{ route('landing.produit') }}" class="btn btn-outline">Voir le produit</a>
                    </div>

                    <div class="hero-badges">
                        <span><strong>337</strong> panneaux</span>
                        <span><strong>31</strong> communes</span>
                        <span><strong>FNE</strong> conforme</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="hero-parrot">
                        <img src="{{ asset('images/peroquet.jpg') }}" alt="Panora — vision à 360°" loading="eager">
                        <div class="parrot-caption">
                            <span class="parrot-caption-eyebrow">Panora By CIBLE</span>
                            La vision à 360°<br>de votre régie.
                        </div>
                    </div>
                    <div class="hero-mini-cards">
                        <div class="mini-card">
                            <span>CA du mois</span>
                            <strong>6.6M<em>FCFA</em></strong>
                        </div>
                        <div class="mini-card">
                            <span>Panneaux actifs</span>
                            <strong>364</strong>
                        </div>
                        <div class="mini-card">
                            <span>Poses aujourd'hui</span>
                            <strong>15</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ SCREENSHOT HERO ═══════════════════ --}}
    <section style="padding: 40px 0 100px; border: none; background: var(--bg-cream);">
        <div class="wrap">
            <div style="text-align: center; margin-bottom: 40px;">
                <span class="eyebrow">L'écran d'ouverture, chaque matin</span>
                <h2 class="section-title" style="max-width: 700px; margin: 0 auto;">
                    Tout ce qui compte, <em>en 30 secondes.</em>
                </h2>
            </div>
            @include('public.landing._screenshot', [
                'src'     => 'dashboard-admin.png',
                'alt'     => 'Tableau de bord Panora — vue direction',
                'caption' => 'Panneaux actifs · CA mensuel · Factures en retard · Prévision 30 j · Top clients · Top communes · Alertes terrain. Aucune ressaisie.',
                'accent'  => true,
            ])
        </div>
    </section>

    @push('head')
    <style>
        /* HERO : grille 2 colonnes avec parrot signature à droite */
        .hero-grid {
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            gap: 70px;
            align-items: center;
        }
        .hero-badges {
            display: flex; gap: 8px; flex-wrap: wrap;
            margin-top: 48px;
            padding-top: 28px;
            border-top: 1px solid rgba(11,15,25,0.08);
        }
        .hero-badges span {
            display: inline-flex; align-items: baseline; gap: 6px;
            padding: 8px 14px;
            background: rgba(255,255,255,0.7);
            border: 1px solid rgba(11,15,25,0.08);
            border-radius: 999px;
            font-size: 13px;
            color: var(--ink-3);
        }
        .hero-badges strong {
            font-family: 'Fraunces', serif;
            color: var(--ink);
            font-size: 15px;
            font-weight: 600;
        }

        /* Visuel perroquet stylé */
        .hero-visual { position: relative; }
        .hero-parrot {
            position: relative;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 40px 80px -20px rgba(11,15,25,0.35),
                        0 20px 40px -20px rgba(217,78,31,0.25);
            aspect-ratio: 4/5;
            background: #0b0f19;
        }
        .hero-parrot img {
            width: 100%; height: 100%;
            object-fit: cover; object-position: center;
        }
        .parrot-caption {
            position: absolute;
            left: 24px; bottom: 24px; right: 24px;
            font-family: 'Fraunces', serif;
            font-size: 26px;
            font-weight: 400;
            line-height: 1.15;
            color: #fff;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }
        .parrot-caption-eyebrow {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--brand-yellow);
            margin-bottom: 10px;
        }

        /* Mini cards flottantes */
        .hero-mini-cards {
            position: absolute;
            right: -30px;
            bottom: -30px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .mini-card {
            background: #fff;
            padding: 14px 18px;
            border-radius: 12px;
            box-shadow: 0 12px 28px -10px rgba(11,15,25,0.25);
            border: 1px solid var(--line);
            min-width: 170px;
        }
        .mini-card span {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--ink-4);
            margin-bottom: 4px;
        }
        .mini-card strong {
            font-family: 'Fraunces', serif;
            font-size: 26px;
            font-weight: 500;
            color: var(--ink);
            letter-spacing: -0.02em;
        }
        .mini-card strong em {
            font-family: 'Inter', sans-serif;
            font-style: normal;
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-4);
            margin-left: 4px;
        }

        @media (max-width: 980px) {
            .hero-grid { grid-template-columns: 1fr; gap: 50px; }
            .hero-mini-cards { position: static; flex-direction: row; margin-top: 20px; overflow-x: auto; }
            .mini-card { min-width: 150px; flex-shrink: 0; }
        }
    </style>
    @endpush

    {{-- ═══════════════════ MANIFESTE ═══════════════════ --}}
    <section class="has-grain" style="background: var(--bg-warm); position: relative; overflow: hidden;">
        <span class="brand-blot blot-c"></span>
        <div class="wrap" style="position: relative; z-index: 2;">
            <div class="split-2">
                <div>
                    <span class="eyebrow">Ce que Panora défend</span>
                    <h2 class="section-title">
                        Un métier <em>rigoureux</em><br>
                        mérite un outil rigoureux.
                    </h2>
                </div>
                <div>
                    <p class="body">
                        Pendant longtemps, une régie OOH s'est administrée entre trois classeurs Excel,
                        un carnet de terrain et une messagerie WhatsApp. C'est ainsi qu'on perd des
                        factures, qu'on facture deux fois le même panneau, qu'on oublie une taxe
                        communale, qu'on découvre trop tard qu'une pose n'a jamais été photographiée.
                    </p>
                    <p class="body">
                        Panora part d'une conviction simple : chaque panneau, chaque campagne,
                        chaque pose, chaque facture, chaque taxe doit être <strong>enregistrée
                        une seule fois</strong>, à la source, par la personne concernée, et
                        immédiatement visible par tous ceux qui en ont besoin. Le reste — les
                        totaux, les rapports, les PDF, les alertes — se calcule tout seul.
                    </p>
                    <p class="body">
                        Le résultat n'est pas une simplification. C'est un changement de statut :
                        la régie passe d'artisanale à industrielle, sans perdre son savoir-faire local.
                    </p>
                    <div class="aside-note">
                        Aucun outil généraliste (CRM SaaS, ERP, tableur avancé) ne comprend
                        ce que « facturer un panneau au prorata mensuel avec taxe communale
                        et déclaration FNE » veut dire. Panora est né pour ça.
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ 4 PILIERS ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Ce que Panora contient</span>
                <h2 class="section-title">Quatre couches, <em>un seul système</em>.</h2>
                <p class="lead" style="margin-top: 8px;">
                    Chaque couche fonctionne sur la précédente. Aucune n'est optionnelle.
                    C'est ce qui interdit qu'un chiffre affiché ici contredise un chiffre affiché là-bas.
                </p>
            </div>

            <div class="pillars-grid">
                <div class="pillar" style="--pcolor: var(--brand-blue);">
                    <div class="pillar-top"><span class="pillar-num">01</span><span class="pillar-tag">Inventaire</span></div>
                    <h4>Le parc, tel qu'il est réellement.</h4>
                    <p>
                        Chaque panneau — interne ou régie partenaire — porte sa fiche, ses photos,
                        son format, son emplacement GPS, sa commune, son propriétaire, son historique
                        d'occupation et sa fiche technique. C'est la fondation.
                    </p>
                    <a href="{{ route('landing.produit') }}#inventaire" class="arrow-link">Voir le module</a>
                </div>

                <div class="pillar" style="--pcolor: var(--brand-yellow);">
                    <div class="pillar-top"><span class="pillar-num">02</span><span class="pillar-tag">Commercial &amp; campagnes</span></div>
                    <h4>De la proposition à la campagne terminée.</h4>
                    <p>
                        Propositions envoyées au client, réservations engageantes, campagnes
                        actives avec période exacte, taux de couverture, écart de facturation
                        détecté automatiquement — l'annonceur voit ce qu'il paie.
                    </p>
                    <a href="{{ route('landing.produit') }}#campagnes" class="arrow-link">Voir le module</a>
                </div>

                <div class="pillar" style="--pcolor: var(--brand-purple);">
                    <div class="pillar-top"><span class="pillar-num">03</span><span class="pillar-tag">Terrain</span></div>
                    <h4>La pose et la pige, jusqu'au technicien.</h4>
                    <p>
                        Chaque campagne génère ses tâches de pose. Chaque tâche est assignée à un
                        technicien qui la reçoit sur son téléphone (PWA installable). Il pointe,
                        photographie, remonte l'incident, valide. Preuve d'affichage disponible en temps réel.
                    </p>
                    <a href="{{ route('landing.produit') }}#terrain" class="arrow-link">Voir le module</a>
                </div>

                <div class="pillar" style="--pcolor: var(--brand-green);">
                    <div class="pillar-top"><span class="pillar-num">04</span><span class="pillar-tag">Comptable &amp; fiscal</span></div>
                    <h4>FNE, taxes communales, encaissements.</h4>
                    <p>
                        Factures FNE conformes CGI Côte d'Ivoire, taxes communales calculées par
                        commune et snapshotées à la date d'émission, échéanciers, versements
                        multiples, relances, exports comptables. Rien de fait à la main.
                    </p>
                    <a href="{{ route('landing.produit') }}#comptable" class="arrow-link">Voir le module</a>
                </div>
            </div>

            @push('head')
            <style>
                .pillars-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                }
                .pillar {
                    position: relative;
                    background: #fff;
                    border: 1px solid var(--line);
                    border-radius: 20px;
                    padding: 40px 34px 34px;
                    overflow: hidden;
                    transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
                }
                .pillar::before {
                    content: '';
                    position: absolute; top: 0; left: 0; right: 0;
                    height: 4px;
                    background: var(--pcolor);
                }
                .pillar::after {
                    content: '';
                    position: absolute;
                    top: -80px; right: -80px;
                    width: 220px; height: 220px;
                    border-radius: 50%;
                    background: var(--pcolor);
                    opacity: 0.05;
                    transition: transform 0.4s ease, opacity 0.3s ease;
                }
                .pillar:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 30px 60px -20px rgba(11,15,25,0.15);
                    border-color: var(--pcolor);
                }
                .pillar:hover::after {
                    transform: scale(1.15);
                    opacity: 0.10;
                }
                .pillar-top {
                    display: flex; align-items: center; gap: 14px;
                    margin-bottom: 20px;
                }
                .pillar-num {
                    display: inline-flex;
                    align-items: center; justify-content: center;
                    width: 44px; height: 44px;
                    background: var(--pcolor);
                    color: #fff;
                    font-family: 'Fraunces', serif;
                    font-weight: 600;
                    font-size: 16px;
                    border-radius: 50%;
                }
                .pillar-tag {
                    font-size: 11.5px;
                    font-weight: 700;
                    color: var(--pcolor);
                    letter-spacing: 0.12em;
                    text-transform: uppercase;
                }
                .pillar h4 {
                    font-family: 'Fraunces', serif;
                    font-size: 24px;
                    font-weight: 500;
                    letter-spacing: -0.01em;
                    margin-bottom: 14px;
                    color: var(--ink);
                    line-height: 1.2;
                }
                .pillar p {
                    font-size: 15px;
                    color: var(--ink-3);
                    line-height: 1.65;
                    margin-bottom: 18px;
                }
                @media (max-width: 780px) {
                    .pillars-grid { grid-template-columns: 1fr; }
                }
            </style>
            @endpush
        </div>
    </section>

    {{-- ═══════════════════ SECTION FNE ═══════════════════ --}}
    <section style="background: var(--ink); color: rgba(255,255,255,0.85); border: none;">
        <div class="wrap">
            <div class="split-2" style="align-items: center;">
                <div>
                    <span class="eyebrow" style="color: #fbb040;">Conformité Côte d'Ivoire</span>
                    <h2 class="section-title" style="color: #fff;">
                        Une facture, <em style="color: #fbb040;">une déclaration.</em>
                    </h2>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        Panora émet des factures normalisées électroniques (FNE) au format
                        exigé par la Direction Générale des Impôts. Ventilation TVA, taxes
                        additionnelles (TSP, TM, ODP), IFU émetteur, numérotation continue,
                        conservation légale, export PDF signé, audit trail complet.
                    </p>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        Les taux communaux sont snapshotés à la date d'émission — si une commune
                        modifie sa TM, vos anciennes factures restent parfaitement lisibles à
                        l'année N+5 avec les taux qui étaient en vigueur au moment de l'émission.
                    </p>
                    <a href="{{ route('landing.produit') }}#facturation" class="arrow-link" style="color: #fbb040; border-color: #fbb040;">
                        <span style="color: #fff;">Voir le module facturation</span>
                    </a>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'facture-pdf.png',
                        'alt'     => 'Facture FNE émise par Panora — PDF final',
                        'caption' => null,
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ PERSONA TEASERS ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 620px; margin-bottom: 60px;">
                <span class="eyebrow">Qui est concerné</span>
                <h2 class="section-title">Deux rôles, <em>deux vues.</em></h2>
            </div>

            <div class="split-2">
                <div style="padding: 40px; border: 1px solid var(--line); border-radius: 16px;">
                    <span class="eyebrow" style="color: var(--ink-4);">Direction &amp; comptabilité</span>
                    <h3 class="block-title">Vous voulez arrêter les surprises de fin de mois.</h3>
                    <p class="body" style="font-size: 16px; color: var(--ink-3);">
                        CA facturé vs. CA encaissé en temps réel, factures en retard chiffrées à
                        la minute, taxes communales dues par commune, écarts de facturation
                        détectés automatiquement, performance individuelle des commerciaux
                        et des techniciens.
                    </p>
                    <a href="{{ route('landing.pour-directions') }}" class="arrow-link">Voir la page direction</a>
                </div>
                <div style="padding: 40px; border: 1px solid var(--line); border-radius: 16px;">
                    <span class="eyebrow" style="color: var(--ink-4);">Commerciaux</span>
                    <h3 class="block-title">Vous voulez que l'annonceur voie ce qu'il achète.</h3>
                    <p class="body" style="font-size: 16px; color: var(--ink-3);">
                        Fiche campagne partageable, piges photos horodatées et vérifiées,
                        planning des poses, factures émises, réservations engageantes,
                        panier moyen et classement commercial. L'annonceur a son espace,
                        vous n'êtes plus interrompu pour un email de suivi.
                    </p>
                    <a href="{{ route('landing.pour-commerciaux') }}" class="arrow-link">Voir la page commerciaux</a>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CHIFFRES CIBLE ═══════════════════ --}}
    <section class="has-grid" style="background: var(--bg-cream); position: relative; overflow: hidden;">
        <span class="brand-blot blot-b"></span>
        <div class="wrap" style="position: relative; z-index: 2;">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">En production, aujourd'hui</span>
                <h2 class="section-title">Une régie ivoirienne exploite <em>Panora en continu.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Panora n'est pas un prototype. La plateforme tourne aujourd'hui sur un parc
                    réel, avec des factures FNE réellement émises, des techniciens réellement
                    équipés, des clients réellement connectés à leur espace.
                </p>
            </div>

            <div class="metrics-grid">
                <div class="metric">
                    <span class="metric-num">337</span>
                    <span class="metric-label">Panneaux gérés</span>
                    <span class="metric-hint">Internes + régies partenaires</span>
                </div>
                <div class="metric">
                    <span class="metric-num">31</span>
                    <span class="metric-label">Communes couvertes</span>
                    <span class="metric-hint">Abidjan + intérieur CI</span>
                </div>
                <div class="metric">
                    <span class="metric-num">11<em>+</em></span>
                    <span class="metric-label">Modules intégrés</span>
                    <span class="metric-hint">Une seule donnée source</span>
                </div>
                <div class="metric">
                    <span class="metric-num" style="color: var(--accent);">FNE</span>
                    <span class="metric-label">Conforme CGI 2026</span>
                    <span class="metric-hint">Ventilation TVA + taxes locales</span>
                </div>
            </div>
        </div>

        @push('head')
        <style>
            .metrics-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 0;
                background: rgba(255,255,255,0.6);
                border: 1px solid rgba(11,15,25,0.08);
                border-radius: 20px;
                overflow: hidden;
                backdrop-filter: blur(10px);
            }
            .metric {
                padding: 40px 30px 34px;
                position: relative;
                text-align: left;
                border-right: 1px solid rgba(11,15,25,0.08);
            }
            .metric:last-child { border-right: none; }
            .metric-num {
                display: block;
                font-family: 'Fraunces', serif;
                font-size: 80px;
                font-weight: 400;
                color: var(--ink);
                letter-spacing: -0.04em;
                line-height: 0.95;
                margin-bottom: 14px;
            }
            .metric-num em {
                font-style: normal;
                color: var(--accent);
                font-weight: 300;
            }
            .metric-label {
                display: block;
                font-size: 13px;
                font-weight: 700;
                color: var(--ink-2);
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin-bottom: 6px;
            }
            .metric-hint {
                display: block;
                font-size: 13px;
                color: var(--ink-4);
                font-style: italic;
            }
            @media (max-width: 900px) {
                .metrics-grid { grid-template-columns: repeat(2, 1fr); }
                .metric:nth-child(2) { border-right: none; }
                .metric:nth-child(1), .metric:nth-child(2) {
                    border-bottom: 1px solid rgba(11,15,25,0.08);
                }
                .metric-num { font-size: 60px; }
            }
        </style>
        @endpush
    </section>

    {{-- ═══════════════════ CTA FINAL ═══════════════════ --}}
    <section style="text-align: center; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow">Prochaine étape</span>
            <h2 class="section-title" style="margin-bottom: 26px;">
                Voir Panora <em>appliqué à votre régie.</em>
            </h2>
            <p class="lead" style="margin: 0 auto 36px; text-align: center;">
                Une démo de 45 minutes, sur vos données réelles ou sur un jeu de démonstration.
                Aucune installation à préparer. Ni engagement, ni carte bancaire.
            </p>
            <a href="{{ route('landing.demo') }}" class="btn btn-dark" style="font-size: 16px; padding: 17px 34px;">
                Demander une démo
            </a>
            <p style="margin-top: 24px; font-size: 13.5px; color: var(--ink-5);">
                Ou écrivez à <a href="mailto:studio@cible-ci.com" style="color: var(--ink-3); border-bottom: 1px solid var(--line);">studio@cible-ci.com</a> — nous vous répondons dans la journée ouvrée.
            </p>
        </div>
    </section>

@endsection

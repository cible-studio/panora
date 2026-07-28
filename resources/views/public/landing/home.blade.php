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
                        <span><strong>11+</strong> modules intégrés</span>
                        <span><strong>Multi-régie</strong> ready</span>
                        <span><strong>Conformité</strong> locale</span>
                    </div>
                </div>

                <div class="hero-visual">
                    <div class="hero-parrot">
                        <img src="{{ asset('images/peroquet.jpg') }}" alt="Panora — vision à 360°" loading="eager">
                        <div class="parrot-caption">
                            <span class="parrot-caption-eyebrow">Panora</span>
                            La vision à 360°<br>de votre régie.
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

    {{-- ═══════════════════ 3 MODULES STRATÉGIQUES ═══════════════════ --}}
    <section style="background: #fff;">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Souvent oubliés · toujours essentiels</span>
                <h2 class="section-title">Trois modules qui font <em>toute la différence.</em></h2>
                <p class="lead" style="margin-top: 8px;">
                    Au-delà des 4 piliers, Panora intègre nativement ce que les régies gèrent
                    encore à la main : les devis commerciaux, le suivi terrain de bout en bout,
                    et le portail annonceur.
                </p>
            </div>

            <div class="strat-grid">
                <div class="strat">
                    <div class="strat-icon" style="background: rgba(139,92,246,0.10); color: #6d28d9;">💼</div>
                    <h4>Devis commerciaux non-bloquants</h4>
                    <p>
                        Le commercial chiffre en 2 minutes depuis le catalogue, envoie un PDF pro
                        au prospect qui l'accepte / refuse / négocie en ligne. Cycle complet :
                        brouillon → envoyé → accepté → réservation ferme créée automatiquement,
                        avec double-check disponibilité au moment de l'acceptation. KPI de
                        conversion et pipeline dans le dashboard.
                    </p>
                </div>
                <div class="strat">
                    <div class="strat-icon" style="background: rgba(232,160,32,0.12); color: #c2570d;">🛠️</div>
                    <h4>Suivi terrain de bout en bout</h4>
                    <p>
                        Chaque campagne génère automatiquement des tâches de pose, assignables
                        aux techniciens sur leur téléphone (PWA). Ils pointent, photographient
                        (pige horodatée + GPS), remontent les incidents, valident. Les MP suivent
                        l'avancement en temps réel ; les commerciaux ont les preuves d'affichage
                        pour leurs clients ; les factures s'appuient sur des données terrain
                        vérifiées, pas déclaratives.
                    </p>
                </div>
                <div class="strat">
                    <div class="strat-icon" style="background: rgba(29,78,216,0.10); color: #1d4ed8;">👥</div>
                    <h4>Espace client annonceur</h4>
                    <p>
                        Chaque annonceur reçoit un portail sécurisé : ses campagnes en direct,
                        les piges validées téléchargeables, ses factures au format officiel, son
                        historique complet, un canal messagerie avec son commercial. Fini les
                        emails « où en est ma campagne ? » — le client se sert lui-même, 24/7.
                    </p>
                </div>
            </div>

            @push('head')
            <style>
                .strat-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 22px;
                }
                .strat {
                    padding: 34px 30px 28px;
                    background: #fff;
                    border: 1px solid var(--line);
                    border-radius: 18px;
                    transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease;
                }
                .strat:hover {
                    transform: translateY(-3px);
                    box-shadow: 0 20px 40px -18px rgba(11,15,25,0.12);
                    border-color: rgba(11,15,25,0.15);
                }
                .strat-icon {
                    width: 54px; height: 54px;
                    display: inline-flex; align-items: center; justify-content: center;
                    border-radius: 14px;
                    font-size: 26px;
                    margin-bottom: 20px;
                }
                .strat h4 {
                    font-family: 'Fraunces', serif;
                    font-size: 21px;
                    font-weight: 500;
                    letter-spacing: -0.01em;
                    margin-bottom: 12px;
                    color: var(--ink);
                    line-height: 1.25;
                }
                .strat p {
                    font-size: 14.5px;
                    color: var(--ink-3);
                    line-height: 1.65;
                }
                @media (max-width: 900px) {
                    .strat-grid { grid-template-columns: 1fr; }
                }
            </style>
            @endpush
        </div>
    </section>

    {{-- ═══════════════════ SECTION FNE / CONFORMITÉ LOCALE ═══════════════════ --}}
    <section style="background: var(--ink); color: rgba(255,255,255,0.85); border: none;">
        <div class="wrap">
            <div class="split-2" style="align-items: center;">
                <div>
                    <span class="eyebrow" style="color: #fbb040;">Conformité locale</span>
                    <h2 class="section-title" style="color: #fff;">
                        La fiscalité de <em style="color: #fbb040;">votre pays,</em><br>native.
                    </h2>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        La fiscalité change d'un pays à l'autre — Panora est conçu pour s'adapter.
                        <strong style="color:#fbb040">Aujourd'hui</strong>, le moteur est certifié
                        pour la <strong style="color:#fff">Côte d'Ivoire</strong> : factures
                        normalisées électroniques (FNE) au format DGI, ventilation TVA, TSP, TM,
                        ODP, IFU émetteur, numérotation continue, conservation légale, audit trail.
                    </p>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        Les taux communaux sont snapshotés à la date d'émission — si une commune
                        modifie sa TM, vos anciennes factures restent parfaitement lisibles à N+5
                        avec les taux d'origine. La même mécanique se transpose à d'autres pays
                        (Sénégal, Cameroun, Togo…) — nouveau moteur fiscal branché, reste du produit
                        inchangé.
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

    {{-- ═══════════════════ 6 RÔLES · 6 VUES ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Qui est concerné</span>
                <h2 class="section-title">Six métiers, <em>six vues.</em> Une seule vérité.</h2>
                <p class="lead" style="margin-top: 8px;">
                    Chaque rôle dispose de son écran, de ses actions, de ses alertes. Les données
                    circulent entre les rôles automatiquement — plus personne ne « redemande »
                    ce qu'un collègue a déjà saisi.
                </p>
            </div>

            <div class="roles-grid">
                <div class="role"><span class="role-tag" style="color:#0f172a">👔</span><h4>Direction</h4><p>Pilotage global : CA, encaissements, retards, perf commerciale, alertes stratégiques. Un seul écran d'ouverture pour le matin.</p></div>
                <div class="role"><span class="role-tag" style="color:#c2570d">🤝</span><h4>Commerciaux</h4><p>Portefeuille clients, devis, propositions, réservations, campagnes, factures. Espace client partagé, plus d'emails de suivi.</p></div>
                <div class="role"><span class="role-tag" style="color:#8b5cf6">🎯</span><h4>Média-planners</h4><p>Planning campagnes, occupation du parc, disponibilités, poses à assigner, alertes fin de campagne. Le cerveau opérationnel de la régie.</p></div>
                <div class="role"><span class="role-tag" style="color:#22c55e">🛠️</span><h4>Équipes terrain</h4><p>Tâches de pose sur mobile (PWA), photos horodatées + GPS, remontée d'incidents, planning journalier. Zéro papier.</p></div>
                <div class="role"><span class="role-tag" style="color:#1d4ed8">📊</span><h4>Comptable</h4><p>Émission factures FNE, ventilation taxes par commune, versements, relances, exports comptables, échéancier en temps réel.</p></div>
                <div class="role"><span class="role-tag" style="color:#94a3b8">🏢</span><h4>Client / Annonceur</h4><p>Portail sécurisé : campagnes en direct, piges validées, factures téléchargeables, messagerie commerciale. Autonome 24/7.</p></div>
            </div>

            @push('head')
            <style>
                .roles-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 18px;
                }
                .role {
                    padding: 26px 24px 22px;
                    background: #fff;
                    border: 1px solid var(--line);
                    border-radius: 14px;
                    transition: border-color .25s ease, transform .25s ease;
                }
                .role:hover { transform: translateY(-2px); border-color: rgba(11,15,25,0.15); }
                .role-tag { font-size: 26px; display: block; margin-bottom: 12px; }
                .role h4 {
                    font-family: 'Fraunces', serif;
                    font-size: 18px;
                    font-weight: 500;
                    margin-bottom: 8px;
                    color: var(--ink);
                }
                .role p { font-size: 13.5px; color: var(--ink-3); line-height: 1.6; }
                @media (max-width: 900px) { .roles-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (max-width: 600px) { .roles-grid { grid-template-columns: 1fr; } }
            </style>
            @endpush

            <div style="text-align:center;margin-top:36px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="{{ route('landing.pour-directions') }}" class="btn btn-outline">Vue direction</a>
                <a href="{{ route('landing.pour-commerciaux') }}" class="btn btn-outline">Vue commerciaux</a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ PERFORMANCE & SLA ═══════════════════ --}}
    <section style="background:#0f172a;color:rgba(255,255,255,0.85);border:none">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 50px;">
                <span class="eyebrow" style="color:#fbb040">Fiabilité &amp; performance</span>
                <h2 class="section-title" style="color:#fff">Un SaaS métier, <em style="color:#fbb040">pas un prototype.</em></h2>
                <p class="body" style="color:rgba(255,255,255,0.75);font-size:17px">
                    Panora est déployé selon des standards SaaS pro : monitoring 24/7, sauvegardes
                    chiffrées automatiques, mises à jour continues, journal d'audit sur les données
                    sensibles. Le contrat SLA cadre les délais, la conservation, la restitution
                    des données.
                </p>
            </div>

            <div class="sla-grid">
                <div class="sla"><span class="sla-num">99,5%</span><span class="sla-lbl">Uptime cible</span><span class="sla-hint">Monitoring 24/7 · alertes automatiques</span></div>
                <div class="sla"><span class="sla-num">&lt; 2 h</span><span class="sla-lbl">Temps de réaction incident</span><span class="sla-hint">Support pendant les heures ouvrées</span></div>
                <div class="sla"><span class="sla-num">J-1</span><span class="sla-lbl">Sauvegardes quotidiennes</span><span class="sla-hint">Restauration testée mensuellement</span></div>
                <div class="sla"><span class="sla-num">10 ans</span><span class="sla-lbl">Conservation légale</span><span class="sla-hint">Factures + audit trail préservés</span></div>
            </div>

            @push('head')
            <style>
                .sla-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 0;
                    background: rgba(255,255,255,0.04);
                    border: 1px solid rgba(255,255,255,0.10);
                    border-radius: 18px;
                    overflow: hidden;
                }
                .sla {
                    padding: 34px 26px;
                    border-right: 1px solid rgba(255,255,255,0.10);
                }
                .sla:last-child { border-right: none; }
                .sla-num {
                    display: block;
                    font-family: 'Fraunces', serif;
                    font-size: 48px;
                    font-weight: 400;
                    color: #fbb040;
                    line-height: 1;
                    margin-bottom: 12px;
                }
                .sla-lbl {
                    display: block;
                    font-size: 13px;
                    font-weight: 700;
                    color: #fff;
                    letter-spacing: 0.06em;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }
                .sla-hint { display: block; font-size: 12px; color: rgba(255,255,255,0.55); font-style: italic; }
                @media (max-width: 900px) {
                    .sla-grid { grid-template-columns: repeat(2, 1fr); }
                    .sla:nth-child(2) { border-right: none; }
                    .sla:nth-child(1), .sla:nth-child(2) { border-bottom: 1px solid rgba(255,255,255,0.10); }
                }
                @media (max-width: 500px) {
                    .sla-grid { grid-template-columns: 1fr; }
                    .sla { border-right: none; border-bottom: 1px solid rgba(255,255,255,0.10); }
                    .sla:last-child { border-bottom: none; }
                }
            </style>
            @endpush
        </div>
    </section>

    {{-- ═══════════════════ CAPACITÉS PRODUIT ═══════════════════ --}}
    <section class="has-grid" style="background: var(--bg-cream); position: relative; overflow: hidden;">
        <span class="brand-blot blot-b"></span>
        <div class="wrap" style="position: relative; z-index: 2;">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Ce que Panora sait faire</span>
                <h2 class="section-title">Une plateforme <em>éprouvée en production.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Panora tourne aujourd'hui sur des parcs réels, avec des factures officiellement
                    émises, des techniciens équipés terrain, et des annonceurs connectés à leur
                    espace client. Chaque brique est industrialisée, testée, et prête à passer
                    à l'échelle sur d'autres régies.
                </p>
            </div>

            <div class="metrics-grid">
                <div class="metric">
                    <span class="metric-num">11<em>+</em></span>
                    <span class="metric-label">Modules intégrés</span>
                    <span class="metric-hint">Une seule donnée source, zéro ressaisie</span>
                </div>
                <div class="metric">
                    <span class="metric-num">6</span>
                    <span class="metric-label">Rôles &amp; vues</span>
                    <span class="metric-hint">Direction · Commerce · MP · Terrain · Compta · Client</span>
                </div>
                <div class="metric">
                    <span class="metric-num">99,5<em>%</em></span>
                    <span class="metric-label">Uptime cible</span>
                    <span class="metric-hint">SLA production · monitoring 24/7</span>
                </div>
                <div class="metric">
                    <span class="metric-num" style="color: var(--accent);">FNE</span>
                    <span class="metric-label">Conformité locale</span>
                    <span class="metric-hint">CI aujourd'hui · adaptable ailleurs</span>
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

    {{-- ═══════════════════ TEASER FAQ ═══════════════════ --}}
    <section style="background: var(--bg-cream); border: none;">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 44px;">
                <span class="eyebrow">Questions fréquentes</span>
                <h2 class="section-title">Les questions que <em>toutes les régies</em> nous posent.</h2>
            </div>
            <div class="faq-teaser-grid">
                <div class="faq-teaser">
                    <h5>À qui s'adresse Panora ?</h5>
                    <p>Aux régies d'affichage extérieur (OOH) — de 50 à 10 000+ panneaux, avec ou sans réseau partenaire.</p>
                </div>
                <div class="faq-teaser">
                    <h5>Combien de temps de déploiement ?</h5>
                    <p>Entre 2 et 6 semaines selon la taille du parc et les intégrations souhaitées. Import du catalogue existant assuré.</p>
                </div>
                <div class="faq-teaser">
                    <h5>Comment sont sécurisées mes données ?</h5>
                    <p>Chaque régie a son espace isolé. Sauvegardes chiffrées quotidiennes, journal d'audit complet, RGPD-ready.</p>
                </div>
                <div class="faq-teaser">
                    <h5>Peut-on l'utiliser hors Côte d'Ivoire ?</h5>
                    <p>Oui — le moteur fiscal est modulaire. On ajoute le référentiel local du pays cible (taxes, format factures) sans réécrire le produit.</p>
                </div>
            </div>
            <div style="text-align:center;margin-top:36px">
                <a href="{{ route('landing.faq') }}" class="btn btn-outline">Voir toutes les questions</a>
            </div>

            @push('head')
            <style>
                .faq-teaser-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 18px;
                }
                .faq-teaser {
                    padding: 24px 26px;
                    background: #fff;
                    border: 1px solid var(--line);
                    border-radius: 14px;
                }
                .faq-teaser h5 {
                    font-family: 'Fraunces', serif;
                    font-size: 17px;
                    font-weight: 500;
                    margin-bottom: 8px;
                    color: var(--ink);
                }
                .faq-teaser p {
                    font-size: 14px;
                    color: var(--ink-3);
                    line-height: 1.6;
                }
                @media (max-width: 780px) { .faq-teaser-grid { grid-template-columns: 1fr; } }
            </style>
            @endpush
        </div>
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
                Ou écrivez à <a href="mailto:contact@panora.app" style="color: var(--ink-3); border-bottom: 1px solid var(--line);">contact@panora.app</a> — nous vous répondons dans la journée ouvrée.
            </p>
        </div>
    </section>

@endsection

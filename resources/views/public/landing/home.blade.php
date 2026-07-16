@extends('public.landing._layout', [
    'seo_title'       => 'Panora — Le système d\'exploitation des régies OOH',
    'seo_description' => 'Panora unifie la vie d\'une régie d\'affichage extérieur : inventaire, campagnes, terrain, facturation FNE, taxes communales, direction. Éprouvé en Côte d\'Ivoire.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 120px; padding-bottom: 60px;">
        <div class="wrap">
            <span class="eyebrow">Plateforme d'exploitation OOH · Côte d'Ivoire</span>
            <h1 class="hero-title">
                Une régie OOH ne se pilote plus<br>
                dans <em>un tableur.</em>
            </h1>
            <p class="lead">
                Panora réunit dans une seule plateforme tout ce qu'exige aujourd'hui l'exploitation
                d'une régie d'affichage extérieur : inventaire des panneaux, planification des campagnes,
                pilotage terrain jusqu'au technicien, facturation conforme FNE, taxes communales,
                relation client, alertes et performance. C'est le tissu numérique d'une régie moderne.
            </p>
            <div style="display: flex; gap: 14px; flex-wrap: wrap;">
                <a href="{{ route('landing.demo') }}" class="btn btn-dark">Demander une démo</a>
                <a href="{{ route('landing.produit') }}" class="btn btn-outline">Voir le produit en détail</a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ SCREENSHOT HERO ═══════════════════ --}}
    <section style="padding-top: 20px; padding-bottom: 100px; border: none;">
        <div class="wrap">
            @include('public.landing._screenshot', [
                'src'     => 'dashboard-admin.png',
                'alt'     => 'Tableau de bord Panora — vue direction',
                'caption' => 'Tableau de bord direction — panneaux actifs, chiffre d\'affaires, factures en retard, alertes terrain, top clients, top communes. Rafraîchi en continu.',
                'accent'  => true,
            ])
        </div>
    </section>

    {{-- ═══════════════════ MANIFESTE ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
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

            <div class="card-list" style="grid-template-columns: 1fr 1fr;">
                <div class="card-item">
                    <div class="num">01 · Inventaire</div>
                    <h4>Le parc, tel qu'il est réellement.</h4>
                    <p>
                        Chaque panneau — interne ou régie partenaire — porte sa fiche, ses photos,
                        son format, son emplacement GPS, sa commune, son propriétaire, son historique
                        d'occupation et sa fiche technique. C'est la fondation.
                    </p>
                    <a href="{{ route('landing.produit') }}#inventaire" class="arrow-link">Voir le module</a>
                </div>

                <div class="card-item">
                    <div class="num">02 · Commercial &amp; campagnes</div>
                    <h4>De la proposition à la campagne terminée.</h4>
                    <p>
                        Propositions envoyées au client, réservations engageantes, campagnes
                        actives avec période exacte, taux de couverture, écart de facturation
                        détecté automatiquement — l'annonceur voit ce qu'il paie.
                    </p>
                    <a href="{{ route('landing.produit') }}#campagnes" class="arrow-link">Voir le module</a>
                </div>

                <div class="card-item">
                    <div class="num">03 · Terrain</div>
                    <h4>La pose et la pige, jusqu'au technicien.</h4>
                    <p>
                        Chaque campagne génère ses tâches de pose. Chaque tâche est assignée à un
                        technicien qui la reçoit sur son téléphone (PWA installable). Il pointe,
                        photographie, remonte l'incident, valide. Preuve d'affichage disponible en temps réel.
                    </p>
                    <a href="{{ route('landing.produit') }}#terrain" class="arrow-link">Voir le module</a>
                </div>

                <div class="card-item">
                    <div class="num">04 · Comptable &amp; fiscal</div>
                    <h4>FNE, taxes communales, encaissements.</h4>
                    <p>
                        Factures FNE conformes CGI Côte d'Ivoire, taxes communales calculées par
                        commune et snapshotées à la date d'émission, échéanciers, versements
                        multiples, relances, exports comptables. Rien de fait à la main.
                    </p>
                    <a href="{{ route('landing.produit') }}#comptable" class="arrow-link">Voir le module</a>
                </div>
            </div>
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
    <section style="background: var(--bg-soft);">
        <div class="wrap">
            <div style="max-width: 680px; margin-bottom: 50px;">
                <span class="eyebrow">En production</span>
                <h2 class="section-title">Une régie ivoirienne exploite <em>Panora en continu.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Panora n'est pas un prototype. La plateforme tourne aujourd'hui sur un parc
                    réel, avec des factures FNE réellement émises, des techniciens réellement
                    équipés, des clients réellement connectés à leur espace.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px;">
                <div>
                    <div style="font-family: 'Fraunces', serif; font-size: 56px; font-weight: 400; color: var(--ink); letter-spacing: -0.03em; line-height: 1;">337</div>
                    <div style="font-size: 13px; color: var(--ink-4); margin-top: 8px; letter-spacing: 0.05em; text-transform: uppercase; font-weight: 600;">Panneaux gérés</div>
                </div>
                <div>
                    <div style="font-family: 'Fraunces', serif; font-size: 56px; font-weight: 400; color: var(--ink); letter-spacing: -0.03em; line-height: 1;">31</div>
                    <div style="font-size: 13px; color: var(--ink-4); margin-top: 8px; letter-spacing: 0.05em; text-transform: uppercase; font-weight: 600;">Communes couvertes</div>
                </div>
                <div>
                    <div style="font-family: 'Fraunces', serif; font-size: 56px; font-weight: 400; color: var(--ink); letter-spacing: -0.03em; line-height: 1;">10<span style="color: var(--accent);">+</span></div>
                    <div style="font-size: 13px; color: var(--ink-4); margin-top: 8px; letter-spacing: 0.05em; text-transform: uppercase; font-weight: 600;">Modules intégrés</div>
                </div>
                <div>
                    <div style="font-family: 'Fraunces', serif; font-size: 56px; font-weight: 400; color: var(--ink); letter-spacing: -0.03em; line-height: 1;">FNE</div>
                    <div style="font-size: 13px; color: var(--ink-4); margin-top: 8px; letter-spacing: 0.05em; text-transform: uppercase; font-weight: 600;">Conforme CGI 2026</div>
                </div>
            </div>

            <style>
                @media (max-width: 720px) {
                    section [style*="grid-template-columns: repeat(4, 1fr)"] {
                        grid-template-columns: repeat(2, 1fr) !important;
                        gap: 32px 24px !important;
                    }
                }
            </style>
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
                Ou écrivez à <a href="mailto:studio@cible-ci.com" style="color: var(--ink-3); border-bottom: 1px solid var(--line);">studio@cible-ci.com</a> — nous vous répondons dans la journée ouvrée.
            </p>
        </div>
    </section>

@endsection

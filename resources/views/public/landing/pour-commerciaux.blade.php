@extends('public.landing._layout', [
    'seo_title'       => 'Pour les commerciaux — Panora vous fait gagner du temps',
    'seo_description' => 'Panora pour les commerciaux d\'une régie OOH : proposition en 5 minutes, piges partageables, espace client, performance individuelle chiffrée.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap">
            <span class="eyebrow">Pour les commerciaux terrain</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5.5vw, 68px);">
                Vendre plus,<br>expliquer <em>moins.</em>
            </h1>
            <p class="lead">
                Un bon commercial OOH passe la majorité de sa journée à rassurer un annonceur :
                « oui votre campagne tourne bien », « oui votre panneau est en place », « oui
                votre facture est en cours d'émission ». Ce temps-là, c'est du temps qui ne
                génère aucun revenu. Panora l'élimine à sa source.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ ESPACE CLIENT ═══════════════════ --}}
    <section style="padding-top: 20px; padding-bottom: 60px; border: none;">
        <div class="wrap">
            @include('public.landing._screenshot', [
                'src'     => 'client-dashboard.png',
                'alt'     => 'Espace client — votre annonceur se suit tout seul',
                'caption' => 'Votre annonceur ouvre son espace, voit ses campagnes actives, ses poses réalisées, ses piges validées. Vos échanges se recentrent sur la stratégie.',
                'accent'  => true,
            ])
        </div>
    </section>

    {{-- ═══════════════════ 3 GAINS ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Ce que Panora vous rend</span>
                <h2 class="section-title">Trois gains <em>immédiats.</em></h2>
            </div>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;">
                <div style="padding: 36px 30px; background: #fff; border: 1px solid var(--line); border-radius: 14px;">
                    <div style="font-family: 'Fraunces', serif; font-size: 42px; font-weight: 400; color: var(--accent); line-height: 1; margin-bottom: 16px;">01</div>
                    <h3 class="block-title" style="font-size: 22px;">Une proposition en 5 minutes.</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        Vous ouvrez la vue disponibilités, filtrez par commune, zone, format,
                        période — vous cochez les panneaux libres, Panora génère la proposition
                        PDF avec photos, codes, prix. L'annonceur reçoit un document propre,
                        pas un mail avec 12 lignes de tableur.
                    </p>
                </div>
                <div style="padding: 36px 30px; background: #fff; border: 1px solid var(--line); border-radius: 14px;">
                    <div style="font-family: 'Fraunces', serif; font-size: 42px; font-weight: 400; color: var(--accent); line-height: 1; margin-bottom: 16px;">02</div>
                    <h3 class="block-title" style="font-size: 22px;">La preuve, sans effort.</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        Le technicien photographie le panneau posé, la pige est vérifiée par
                        la direction. Le client la voit apparaître dans son espace, sans que
                        vous ayez rien à faire. Fini les WhatsApp « pouvez-vous m'envoyer
                        la photo de mon affichage à Cocody s'il vous plaît ».
                    </p>
                </div>
                <div style="padding: 36px 30px; background: #fff; border: 1px solid var(--line); border-radius: 14px;">
                    <div style="font-family: 'Fraunces', serif; font-size: 42px; font-weight: 400; color: var(--accent); line-height: 1; margin-bottom: 16px;">03</div>
                    <h3 class="block-title" style="font-size: 22px;">Votre performance,<br>chiffrée.</h3>
                    <p style="font-size: 15px; color: var(--ink-3); line-height: 1.65;">
                        Votre CA, vos nouvelles campagnes, votre panier moyen, votre taux de
                        recouvrement — vous les voyez chaque matin. Vous savez où vous en êtes
                        pour votre commission, pour votre classement, pour votre challenge trimestriel.
                    </p>
                </div>
            </div>

            <style>
                @media (max-width: 900px) {
                    section [style*="grid-template-columns: repeat(3, 1fr)"] {
                        grid-template-columns: 1fr !important;
                        gap: 18px !important;
                    }
                }
            </style>
        </div>
    </section>

    {{-- ═══════════════════ FICHE CAMPAGNE ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">Votre outil de travail principal</span>
                    <h2 class="section-title">La fiche campagne, <em>votre bureau.</em></h2>
                    <p class="body">
                        Chaque campagne que vous ouvrez vous donne, en une seule vue : le client,
                        la période, le montant total, la progression (« se termine dans 44 jours,
                        49 % écoulée »), la réservation d'origine, les poses (planifiées, en cours,
                        réalisées), les piges photos, les factures émises et leurs paiements.
                    </p>
                    <p class="body">
                        Un annonceur qui appelle pour un point ? Vous êtes en avance : la fiche
                        vous a tout dit avant qu'il ne finisse sa phrase. Vous pouvez notifier
                        les changements au client en un clic, gérer les poses et piges terrain
                        sans quitter la fiche, prolonger la campagne.
                    </p>
                    <div class="aside-note">
                        Le PDF « avec photos » de la campagne, à envoyer au client en cours ou en
                        clôture — vue idéale pour un bilan de campagne convaincant.
                    </div>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'campagne-detail.png',
                        'alt'     => 'Fiche campagne — vue commercial',
                        'caption' => 'Une fiche, tout est là. Y compris l\'écart de facturation détecté, s\'il y en a un.',
                        'accent'  => true,
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ PERFORMANCE ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">Vos chiffres, vos décisions</span>
                    <h2 class="section-title">Le classement, <em>sans discussion.</em></h2>
                    <p class="body">
                        Panora consolide votre performance en temps réel sur la période choisie :
                        CA HT, CA TTC, encaissé, taux de recouvrement, nouvelles campagnes créées,
                        campagnes actives, panier moyen. Vous êtes classé au sein de l'équipe
                        selon la métrique retenue par la direction.
                    </p>
                    <p class="body">
                        Vous cliquez sur « Nouvelles campagnes créées » : Panora vous ouvre la
                        liste exacte des campagnes que vous avez créées sur la période. Vous
                        cliquez sur « Encaissé » : la liste des factures encaissées, dans l'ordre.
                        Aucune ambiguïté sur les chiffres qui composent votre commission.
                    </p>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'perf-commerciale.png',
                        'alt'     => 'Performance commerciale — votre classement',
                        'caption' => 'Cartes drill-down cliquables, classement par métrique, période paramétrable.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ ESPACE CLIENT ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">L'annonceur autonome</span>
                    <h2 class="section-title">Il vous appelle <em>pour du business,</em><br>plus pour du suivi.</h2>
                    <p class="body">
                        Chaque annonceur reçoit ses identifiants pour son espace privé. Il y
                        consulte à tout moment ses campagnes, ses piges d'affichage, ses factures,
                        ses paiements en attente. Il peut télécharger un bilan photo de campagne
                        d'un clic.
                    </p>
                    <p class="body">
                        Le résultat est visible dès la première semaine : vos échanges avec
                        vos annonceurs se recentrent sur ce qui compte — négocier une prolongation,
                        proposer un up-sell, préparer la prochaine campagne. Le temps que vous
                        passiez à rassurer est réinvesti à vendre.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'client-campagnes.png',
                        'alt'     => 'Espace client — liste des campagnes',
                        'caption' => 'Vue annonceur : mes campagnes, par statut, avec période, panneaux, montant.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; background: var(--ink); color: #fff; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow" style="color: #fbb040;">Voir concrètement</span>
            <h2 class="section-title" style="color: #fff; margin-bottom: 26px;">
                45 minutes pour <em style="color: #fbb040;">tout comprendre.</em>
            </h2>
            <p class="lead" style="color: rgba(255,255,255,0.75); margin: 0 auto 36px; text-align: center;">
                Une démonstration où on simule votre journée : accueil d'un nouvel annonceur,
                proposition, réservation, campagne, suivi, facturation, bilan. Sans jargon,
                sans démonstration abstraite.
            </p>
            <a href="{{ route('landing.demo') }}" class="btn" style="background: #fbb040; color: var(--ink); font-size: 16px; padding: 17px 34px;">
                Demander une démo
            </a>
        </div>
    </section>

@endsection

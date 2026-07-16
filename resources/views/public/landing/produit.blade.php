@extends('public.landing._layout', [
    'seo_title'       => 'Le produit Panora — modules, écrans, capacités',
    'seo_description' => 'Découvrez les modules Panora : inventaire, campagnes, terrain PWA, facturation FNE, taxes communales, espace client, alertes, signalements, performance.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap">
            <span class="eyebrow">Le produit, écran par écran</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5.5vw, 68px);">
                Chaque module<br>fait <em>un seul métier,</em><br>et le fait bien.
            </h1>
            <p class="lead">
                Panora est composé d'une dizaine de modules qui s'appuient sur la même donnée
                canonique. Vous n'avez jamais à ressaisir un panneau, un prix, un client, une date.
                Voici, dans l'ordre où on les utilise dans une régie, les modules qui structurent
                la plateforme.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ SOMMAIRE ═══════════════════ --}}
    <section style="padding: 40px 0; background: var(--bg-cream);">
        <div class="wrap">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px 32px; font-size: 14px;">
                <a href="#inventaire" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">01</span> Inventaire</a>
                <a href="#disponibilites" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">02</span> Disponibilités</a>
                <a href="#campagnes" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">03</span> Campagnes</a>
                <a href="#terrain" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">04</span> Pilotage terrain</a>
                <a href="#pige" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">05</span> Piges photos</a>
                <a href="#facturation" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">06</span> Facturation FNE</a>
                <a href="#taxes" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">07</span> Taxes communales</a>
                <a href="#client" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">08</span> Espace client</a>
                <a href="#alertes" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">09</span> Alertes</a>
                <a href="#signalements" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">10</span> Signalements</a>
                <a href="#performance" class="arrow-link" style="border: none; padding: 6px 0;"><span style="color: var(--ink-4); margin-right: 8px;">11</span> Performance</a>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 01 · INVENTAIRE ═══════════════════ --}}
    <section id="inventaire">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">01 · Inventaire</span>
                    <h2 class="section-title">Chaque panneau, <em>une fiche unique.</em></h2>
                    <p class="body">
                        Un panneau dans Panora, c'est une référence unique (ex. <code>ABG-002</code>),
                        un emplacement GPS, une commune, un format en mètres carrés, un type
                        (chevalet, 4×3, unipole, tri-vision…), un propriétaire (régie interne ou régie
                        partenaire), un prix de référence mensuel et un état de maintenance.
                    </p>
                    <p class="body">
                        Un panneau, une seule vérité. Impossible de le facturer sous deux prix
                        différents à deux clients simultanément — le système vous prévient dès
                        la réservation. Les régies partenaires ont leurs propres panneaux référencés
                        avec leur commission — vous voyez qui possède quoi en un clin d'œil.
                    </p>
                    <div class="aside-note">
                        Photos multiples, historique d'occupation sur les 24 derniers mois,
                        alertes de maintenance, catégorie, éclairage, orientation, dimensions
                        exactes. Le patrimoine décrit à la maille utile.
                    </div>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'disponibilites-grille.png',
                        'alt'     => 'Vue disponibilités — grille des panneaux',
                        'caption' => 'Vue grille : chaque panneau avec sa photo, son code, son prix et son état d\'occupation.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 02 · DISPONIBILITÉS ═══════════════════ --}}
    <section id="disponibilites" style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">02 · Disponibilités</span>
                    <h2 class="section-title">Ce qui est <em>libre</em> aujourd'hui.<br>Ce qui l'est demain.</h2>
                    <p class="body">
                        Le moteur de disponibilité est la brique invisible qui empêche la
                        double-vente. À une date donnée, sur une commune, une zone, un format,
                        un type d'éclairage — Panora vous dit exactement quels panneaux sont
                        libres, occupés, en maintenance ou en dérogation.
                    </p>
                    <p class="body">
                        Le commercial construit sa proposition à l'annonceur en sélectionnant
                        directement dans cette vue : chaque case cochée est engageante. Aucune
                        surprise le jour de la pose.
                    </p>
                    <p class="body">
                        Export PDF images (photos + code + prix) pour envoyer au client, export
                        PDF liste comptable, export Excel pour votre régie.
                    </p>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'disponibilites-filtres.png',
                        'alt'     => 'Filtres disponibilités — période, géographie, caractéristiques',
                        'caption' => 'Filtres croisés : période × commune × zone × format × éclairage × régie.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 03 · CAMPAGNES ═══════════════════ --}}
    <section id="campagnes">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">03 · Campagnes</span>
                    <h2 class="section-title">La colonne <em>vertébrale.</em></h2>
                    <p class="body">
                        Une campagne, dans Panora, connecte tout : le client, la période exacte
                        (jour à jour), les panneaux, le prix négocié panneau par panneau, le
                        commercial responsable, la réservation d'origine, les tâches de pose
                        générées automatiquement, les piges photos, les factures émises et leurs paiements.
                    </p>
                    <p class="body">
                        À chaque instant, Panora sait précisément où en est cette campagne :
                        écoulée à 49 %, se termine dans 44 jours, 8 poses planifiées, 1 réalisée,
                        montant total 45 000 FCFA HT. Si vous avez négocié un ajustement de prix
                        après signature, Panora détecte l'écart automatiquement et vous alerte
                        pour re-facturer si besoin.
                    </p>
                    <div class="aside-note">
                        Notification automatique au client à chaque changement structurant :
                        ajout / retrait de panneau, changement de période, ajustement de prix.
                        Il ne peut pas y avoir de malentendu.
                    </div>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'campagne-detail.png',
                        'alt'     => 'Fiche campagne détaillée — informations, progression, actions',
                        'caption' => 'Fiche campagne : informations, écart de facturation détecté, actions rapides, progression, facturation liée.',
                        'accent'  => true,
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 04 · TERRAIN ═══════════════════ --}}
    <section id="terrain" style="background: var(--ink); color: rgba(255,255,255,0.85); border: none;">
        <div class="wrap">
            <div class="split-2" style="align-items: center;">
                <div>
                    <span class="eyebrow" style="color: #fbb040;">04 · Pilotage terrain</span>
                    <h2 class="section-title" style="color: #fff;">
                        Le technicien <em style="color: #fbb040;">dans votre poche.</em>
                    </h2>
                    <p class="body" style="color: rgba(255,255,255,0.78); font-size: 17px;">
                        Chaque campagne génère automatiquement une tâche de pose par panneau.
                        Chaque tâche est assignée à un technicien qui la reçoit sur son téléphone —
                        Panora est une PWA (Progressive Web App) installable sans passer par un
                        store, avec Service Worker, cache offline, notifications push.
                    </p>
                    <p class="body" style="color: rgba(255,255,255,0.78); font-size: 17px;">
                        Le technicien voit sa journée organisée par zone géographique (progression
                        « 7 sur 15 faites »), reçoit l'itinéraire Google Maps du prochain panneau,
                        marque son statut (en route, sur place, réalisée), photographie l'affichage
                        posé, et remonte immédiatement tout incident (accès bloqué, panneau
                        dégradé, campagne déjà en place, etc.).
                    </p>
                    <p class="body" style="color: rgba(255,255,255,0.78); font-size: 17px;">
                        Vous voyez tout, en salle, en temps réel. Le PDG voit ses 18 poses en
                        retard listées explicitement, les 4 poses réalisées sans pige photo
                        (donc pas facturables), et peut relancer un technicien d'un clic.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'tech-mobile.png',
                        'alt'     => 'Espace technicien mobile — liste des poses du jour',
                        'device'  => 'mobile',
                        'caption' => null,
                    ])
                </div>
            </div>

            <div style="margin-top: 60px;">
                @include('public.landing._screenshot', [
                    'src'     => 'pose-ooh.png',
                    'alt'     => 'Vue admin Gestion Pose OOH — pilotage complet',
                    'caption' => 'Vue admin : 18 poses en retard, 4 sans pige photo, KPI en tête, tri par campagne / technicien / date.',
                ])
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 05 · PIGES ═══════════════════ --}}
    <section id="pige">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">05 · Piges photos</span>
                    <h2 class="section-title">Une pose sans pige,<br><em>ce n'est pas une pose.</em></h2>
                    <p class="body">
                        La règle métier est stricte : une pose sans preuve d'affichage
                        photographiée n'est pas facturable. Panora l'applique automatiquement —
                        le tableau de bord vous liste en permanence les poses réalisées sans pige,
                        pour empêcher toute facture émise sans preuve.
                    </p>
                    <p class="body">
                        Chaque pige porte : la photo terrain, le technicien, la date de prise de
                        vue, la géolocalisation, la campagne, le panneau. Un workflow de vérification
                        (vérifiée / rejetée / non conforme) permet à la direction d'archiver la
                        qualité de chaque pose. Les piges vérifiées deviennent des preuves d'affichage
                        que le client télécharge depuis son espace.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'piges-photos.png',
                        'alt'     => 'Piges photos — vue admin avec statuts et vérification',
                        'caption' => 'KPI en tête (total, en attente, vérifiées, rejetées), filtres par période, technicien, campagne, statut.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 06 · FACTURATION FNE ═══════════════════ --}}
    <section id="facturation" style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">06 · Facturation FNE</span>
                    <h2 class="section-title">Facture conforme,<br><em>calculée à la seconde.</em></h2>
                    <p class="body">
                        Une facture Panora, c'est un cycle de vie strict : brouillon → envoyée → soldée.
                        À chaque état, les actions disponibles changent (envoyer, solder manuellement,
                        annuler, revenir en brouillon). Aucun état illégal possible.
                    </p>
                    <p class="body">
                        La ventilation TVA (18 %), TSP (3 %), taxe municipale par commune, ODP,
                        est calculée automatiquement sur les lignes. L'échéancier prévisionnel
                        (acompte 30 % / solde 70 % par exemple) est généré à l'émission ; les
                        versements enregistrés le mettent à jour ligne à ligne.
                    </p>
                    <p class="body">
                        Export PDF conforme, audit trail intégral (qui a modifié quoi et quand),
                        historique complet des factures du client à côté pour navigation rapide.
                    </p>
                    <div class="aside-note">
                        Les montants sont stockés en entiers de FCFA (pas de centimes flottants).
                        Aucune erreur d'arrondi possible sur un cumul de 1 000 factures.
                    </div>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'facture-detail.png',
                        'alt'     => 'Fiche facture FNE — statut, actions, détails',
                        'caption' => 'Cycle brouillon → envoyée → soldée, versements enregistrés, autres factures du client à droite.',
                        'accent'  => true,
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 07 · TAXES ═══════════════════ --}}
    <section id="taxes">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">07 · Taxes communales</span>
                    <h2 class="section-title">31 communes.<br><em>31 barèmes.</em></h2>
                    <p class="body">
                        La taxe d'occupation du domaine public (ODP) et la taxe municipale (TM)
                        varient d'une commune à l'autre. Panora tient à jour le barème par commune
                        et calcule automatiquement, sur la période choisie, ce que vous devez à
                        chaque mairie — répartition Abidjan / intérieur, top 5 des communes les plus
                        taxées, évolution mensuelle des paiements, taux de couverture.
                    </p>
                    <p class="body">
                        Chaque commune a sa fiche : panneaux implantés, surface totale (m²), tarifs
                        ODP et TM, statut de paiement (payé / partiel / non payé), historique complet.
                        Vous n'oubliez plus une commune, vous ne payez plus deux fois la même échéance.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'taxes-communes.png',
                        'alt'     => 'Taxes communales — vue mensuelle Juillet 2026',
                        'caption' => 'Vue mensuelle : ODP théorique, TM théorique, déjà payé, solde restant, top 5 communes.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 08 · ESPACE CLIENT ═══════════════════ --}}
    <section id="client" style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">08 · Espace client</span>
                    <h2 class="section-title">L'annonceur <em>vous suit</em> tout seul.</h2>
                    <p class="body">
                        Chaque client (annonceur) a son espace privé. Il y consulte ses campagnes
                        actives et terminées, télécharge les piges d'affichage, voit les poses
                        réalisées, contacte son commercial, télécharge ses factures et suit ses
                        paiements. Aucun échange d'email pour un « où en est ma campagne ? ».
                    </p>
                    <p class="body">
                        Le client a son interlocuteur clairement identifié (photo, nom, rôle,
                        contact direct). Il peut inviter ses collègues (gestion d'équipe). Son
                        tableau de bord affiche exactement ce dont il a besoin — propositions
                        en attente, campagnes actives, poses réalisées, piges validées.
                    </p>
                    <div class="aside-note">
                        L'espace client est une différence fondamentale avec les régies qui
                        travaillent encore par WhatsApp et emails. Il change la perception que
                        l'annonceur a de sa régie : de fournisseur artisanal à partenaire structuré.
                    </div>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'client-dashboard.png',
                        'alt'     => 'Espace client — tableau de bord annonceur',
                        'caption' => 'Vue annonceur : propositions, campagnes actives, poses réalisées, piges validées, interlocuteur.',
                    ])
                </div>
            </div>

            <div style="margin-top: 60px;">
                @include('public.landing._screenshot', [
                    'src'     => 'client-campagnes.png',
                    'alt'     => 'Espace client — liste des campagnes',
                    'caption' => 'Liste des campagnes du client, par statut, avec période, panneaux, montant. Chaque ligne mène à la fiche détaillée.',
                ])
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 09 · ALERTES ═══════════════════ --}}
    <section id="alertes">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">09 · Alertes</span>
                    <h2 class="section-title">Panora <em>vous prévient</em>.</h2>
                    <p class="body">
                        Une régie a des dizaines de choses à surveiller : poses en retard, panneaux
                        en maintenance prolongée, poses réalisées sans pige photo, factures
                        échues, campagnes qui se terminent bientôt sans facture, changements de
                        configuration critiques…
                    </p>
                    <p class="body">
                        Panora consolide tout dans un flux d'alertes hiérarchisé par niveau
                        (danger / avertissement / information). La cloche du haut affiche le
                        compteur en continu. Chaque alerte pointe vers la fiche concernée. Vous
                        pouvez marquer lues, archiver, supprimer en lot.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'alertes.png',
                        'alt'     => 'Alertes & notifications — flux hiérarchisé',
                        'caption' => 'Flux d\'alertes : 667 actives, 69 danger, 295 avertissements. Actions en lot en bas.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 10 · SIGNALEMENTS ═══════════════════ --}}
    <section id="signalements" style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">10 · Signalements terrain</span>
                    <h2 class="section-title">Le terrain <em>vous parle,</em><br>vous répondez.</h2>
                    <p class="body">
                        Un technicien qui trouve un panneau vandalisé, un accès bloqué, une
                        campagne concurrente déjà en place — il ouvre un signalement en trois
                        clics, ajoute une photo, décrit. La direction voit le signalement dans
                        l'onglet « À traiter » avec la photo prise sur place.
                    </p>
                    <p class="body">
                        Trois actions : modifier le motif si mal qualifié, marquer traité, ou
                        mettre le panneau en maintenance (le sort automatiquement des
                        disponibilités). Statistiques d'analyse dans l'onglet dédié pour repérer
                        les zones à problème récurrent.
                    </p>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'signalements.png',
                        'alt'     => 'Signalements terrain — vue à traiter',
                        'caption' => 'Onglet À traiter : photo terrain, motif, technicien, actions. Analyse consolidée à côté.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ MODULE 11 · PERFORMANCE ═══════════════════ --}}
    <section id="performance">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">11 · Performance commerciale &amp; terrain</span>
                    <h2 class="section-title">Ce que <em>chacun</em> apporte.</h2>
                    <p class="body">
                        Performance commerciale : sur la période choisie, chaque commercial est
                        classé sur son CA HT, son CA TTC, ses nouvelles campagnes créées, ses
                        actives, son encaissé, son taux de recouvrement, son panier moyen. Les
                        cards KPI sont drill-down : un clic vous emmène directement à la liste
                        des campagnes ou factures qui composent ce chiffre.
                    </p>
                    <p class="body">
                        Performance technicien : idem côté opérations — nombre de poses réalisées,
                        piges vérifiées, retards, taux de qualité. La direction dispose d'objectifs
                        chiffrés pour piloter les équipes, sans jugement à l'estime.
                    </p>
                    <div class="aside-note">
                        Rapports exportables en PDF pour la présentation au comité de direction,
                        en Excel pour la paie / commissions.
                    </div>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'perf-commerciale.png',
                        'alt'     => 'Performance commerciale — classement et KPI',
                        'caption' => 'KPI drill-down, graphique 12 mois, classement par métrique choisie.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA FINAL ═══════════════════ --}}
    <section style="text-align: center; background: var(--ink); color: #fff; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow" style="color: #fbb040;">La suite</span>
            <h2 class="section-title" style="color: #fff; margin-bottom: 26px;">
                Chaque module <em style="color: #fbb040;">a été construit</em><br>parce qu'une régie en avait besoin.
            </h2>
            <p class="lead" style="color: rgba(255,255,255,0.75); margin: 0 auto 36px; text-align: center;">
                Une démonstration de 45 minutes suffit à voir Panora dans sa continuité.
                On vous montre ce qui compte pour votre organisation, sans démo commerciale creuse.
            </p>
            <a href="{{ route('landing.demo') }}" class="btn" style="background: #fbb040; color: var(--ink); font-size: 16px; padding: 17px 34px;">
                Demander une démo
            </a>
        </div>
    </section>

@endsection

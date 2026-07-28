@extends('public.landing._layout', [
    'seo_title'       => 'Pour la direction — Panora, votre tableau de bord opérationnel',
    'seo_description' => 'Panora pour la direction de régie OOH : KPIs temps réel, conformité FNE, contrôle des équipes, pilotage financier, audit trail complet.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap">
            <span class="eyebrow">Pour la direction générale &amp; comptable</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5.5vw, 68px);">
                Piloter une régie,<br>ce n'est plus <em>attendre<br>les rapports du lundi.</em>
            </h1>
            <p class="lead">
                Une régie OOH bien gérée est une entreprise à cycle court : une pose ratée
                aujourd'hui, c'est une facture différée dans deux semaines ; une taxe communale
                oubliée, c'est une amende dans trois mois. Panora rend ces signaux visibles
                à l'instant où ils apparaissent, pas trois semaines plus tard.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ SCREENSHOT DASHBOARD ═══════════════════ --}}
    <section style="padding-top: 0; padding-bottom: 100px; border: none;">
        <div class="wrap">
            @include('public.landing._screenshot', [
                'src'     => 'dashboard-admin.png',
                'alt'     => 'Tableau de bord direction Panora',
                'caption' => 'Votre tableau de bord d\'ouverture. Tout ce qui compte, ramené à une lecture de 30 secondes.',
                'accent'  => true,
            ])
        </div>
    </section>

    {{-- ═══════════════════ 4 QUESTIONS ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Ce que vous saurez tous les jours</span>
                <h2 class="section-title">Quatre questions, <em>quatre réponses immédiates.</em></h2>
            </div>

            <div class="card-list" style="grid-template-columns: 1fr 1fr;">
                <div class="card-item">
                    <div class="num">Question 01</div>
                    <h4>« Combien j'ai facturé ce mois, combien j'ai encaissé ? »</h4>
                    <p>
                        CA facturé cumulé, encaissements du mois, factures en retard chiffrées en
                        FCFA. Cliquez sur « À recouvrer » : la liste des factures ouvertes s'affiche,
                        triée par ancienneté. Cliquez sur une facture : vous voyez son échéancier
                        et ses versements enregistrés. Toujours à 2 clics d'un chiffre à sa source.
                    </p>
                </div>
                <div class="card-item">
                    <div class="num">Question 02</div>
                    <h4>« Mes équipes tournent ? »</h4>
                    <p>
                        18 poses en retard aujourd'hui, 4 poses sans pige photo, 1 signalement
                        terrain à traiter. Chacun de ces chiffres est cliquable — vous savez
                        quels panneaux, quels techniciens, quelles campagnes. Vos objectifs
                        opérationnels ne se perdent plus dans une messagerie.
                    </p>
                </div>
                <div class="card-item">
                    <div class="num">Question 03</div>
                    <h4>« Mes commerciaux performent ? »</h4>
                    <p>
                        Classement mensuel par CA, nouvelles campagnes créées, taux de recouvrement,
                        panier moyen. Vous voyez qui apporte, qui vend cher, qui n'encaisse pas.
                        Le commercial voit ses propres chiffres — la performance devient un sujet
                        objectif, pas une conversation.
                    </p>
                </div>
                <div class="card-item">
                    <div class="num">Question 04</div>
                    <h4>« Où en sont mes taxes communales ? »</h4>
                    <p>
                        Commune par commune, vous voyez ODP théorique, TM théorique, déjà payé,
                        solde restant, taux de couverture. Une commune non payée depuis 2 mois
                        remonte immédiatement. Chaque paiement enregistré met la vue à jour.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CONFORMITÉ ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">Conformité fiscale</span>
                    <h2 class="section-title">FNE, <em>sans le stress.</em></h2>
                    <p class="body">
                        La facturation normalisée électronique est aujourd'hui une exigence de la
                        Direction Générale des Impôts. Panora ne se contente pas de la respecter :
                        il vous en fait un actif. Toutes les factures sont numérotées en séquence
                        continue, ventilent la TVA au taux applicable, les taxes additionnelles
                        (TSP 3 %, TM, ODP), portent l'IFU émetteur et sont archivées.
                    </p>
                    <p class="body">
                        Les taux communaux sont snapshotés à la date d'émission. Si la mairie de
                        Cocody modifie sa TM en janvier 2027, les factures que vous avez émises
                        en décembre 2026 conservent leur taux d'origine — l'audit fiscal
                        n'accroche jamais.
                    </p>
                    <p class="body">
                        Chaque modification structurante d'une facture ou d'un paiement est
                        auditée nominativement (qui, quand, quoi). L'audit trail est consultable
                        depuis la fiche, sans intervention IT.
                    </p>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'facture-pdf.png',
                        'alt'     => 'Facture FNE PDF conforme CGI',
                        'caption' => 'PDF FNE conforme : ventilation TVA + autres taxes, IFU émetteur, numérotation continue.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ PILOTAGE ÉQUIPES ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div class="split-2" style="direction: rtl;">
                <div style="direction: ltr;">
                    <span class="eyebrow">Pilotage des équipes</span>
                    <h2 class="section-title">Chaque personne <em>a sa mesure.</em></h2>
                    <p class="body">
                        La performance individuelle est trop souvent floue dans une régie
                        traditionnelle. Qui vend cher ? Qui recouvre ? Qui fait tourner le
                        terrain ? Panora consolide en continu la performance de chaque commercial
                        et de chaque technicien sur la période que vous choisissez (mois, trimestre,
                        année) avec des critères objectifs.
                    </p>
                    <p class="body">
                        Vous exportez le rapport PDF pour votre comité de direction du mois,
                        ou en Excel pour calculer les commissions. Les cartes KPI sont cliquables
                        et vous emmènent à la liste précise qui compose ce chiffre — impossible
                        de contester le classement, il est traçable.
                    </p>
                </div>
                <div style="direction: ltr;">
                    @include('public.landing._screenshot', [
                        'src'     => 'perf-commerciale.png',
                        'alt'     => 'Performance commerciale — classement mensuel',
                        'caption' => 'Classement drill-down, tri par métrique, période paramétrable.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ ÉCARTS DÉTECTÉS ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow">Contrôle interne</span>
                    <h2 class="section-title">Les <em>écarts</em>, avant qu'ils ne coûtent.</h2>
                    <p class="body">
                        Une régie perd de l'argent à trois endroits classiques : une campagne
                        vendue à un prix mais facturée à un autre ; une pose réalisée mais oubliée
                        de la facturation ; un panneau facturé alors qu'il était en maintenance.
                    </p>
                    <p class="body">
                        Panora rapproche automatiquement <strong>réservation → campagne →
                        facturation</strong> et signale les écarts. Sur la fiche campagne, si le
                        montant facturé diffère du montant attendu par la réservation, une bannière
                        orange s'affiche avec le delta chiffré. Vous décidez : re-facturer, ajuster,
                        conserver l'écart s'il est intentionnel.
                    </p>
                    <div class="aside-note">
                        Aucun système ne rattrape une intention frauduleuse déterminée. Mais
                        Panora rend visible ce qui, dans une régie sous tableur, reste invisible
                        pendant des mois — donc redevient chiffrable.
                    </div>
                </div>
                <div>
                    @include('public.landing._screenshot', [
                        'src'     => 'campagne-detail.png',
                        'alt'     => 'Écart de facturation détecté sur une campagne',
                        'caption' => 'Bannière orange en tête : écart de 225 000 FCFA détecté sur la période 03/06 → 29/08. Lien vers la réservation source.',
                    ])
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; background: var(--ink); color: #fff; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow" style="color: #fbb040;">Voir la différence</span>
            <h2 class="section-title" style="color: #fff; margin-bottom: 26px;">
                Une démonstration <em style="color: #fbb040;">sur vos vrais chiffres.</em>
            </h2>
            <p class="lead" style="color: rgba(255,255,255,0.75); margin: 0 auto 36px; text-align: center;">
                Nous préparons un jeu de démonstration calqué sur la taille de votre régie
                (nombre de panneaux, de communes, de clients) et vous montrons Panora comme
                si c'était le vôtre. 45 minutes suffisent.
            </p>
            <a href="{{ route('landing.demo') }}" class="btn" style="background: #fbb040; color: var(--ink); font-size: 16px; padding: 17px 34px;">
                Demander une démo
            </a>
        </div>
    </section>

@endsection

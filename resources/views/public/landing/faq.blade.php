@extends('public.landing._layout', [
    'seo_title'       => 'FAQ — Panora, la plateforme d\'exploitation des régies OOH',
    'seo_description' => 'Toutes les questions fréquentes sur Panora : pour qui, prix, déploiement, sécurité, multi-régie, support, formation, migration.',
])

@section('content')

    {{-- ═══════════════════ HERO FAQ ═══════════════════ --}}
    <section style="padding: 90px 0 50px; background: linear-gradient(180deg, #ffffff 0%, var(--bg-cream) 100%);">
        <div class="wrap-narrow" style="text-align:center">
            <span class="eyebrow">Questions fréquentes</span>
            <h1 class="hero-title" style="font-size:52px;line-height:1.05;margin-bottom:22px">
                Tout ce que vous voulez savoir sur <em>Panora.</em>
            </h1>
            <p class="lead" style="margin: 0 auto 32px;">
                Si votre question ne figure pas dans cette liste, écrivez-nous : nous répondons
                dans la journée ouvrée.
            </p>
            <a href="{{ route('landing.demo') }}" class="btn btn-dark">Poser une question / demander une démo</a>
        </div>
    </section>

    {{-- ═══════════════════ FAQ CATÉGORIES ═══════════════════ --}}
    <section style="background: var(--bg-cream); padding: 60px 0 100px;">
        <div class="wrap">
            @php
                $faq = [
                    'Le produit' => [
                        [
                            'q' => "Qu'est-ce que Panora exactement ?",
                            'a' => "Panora est une plateforme SaaS conçue pour les régies d'affichage extérieur (OOH). Elle regroupe dans une seule application : inventaire du parc, cycle commercial (devis, propositions, réservations, campagnes), suivi terrain (poses, piges, maintenances), facturation officielle (FNE), gestion comptable, portail client, pilotage direction. Fini les tableurs éparpillés."
                        ],
                        [
                            'q' => "À qui s'adresse Panora ?",
                            'a' => "Aux régies d'affichage extérieur — de la structure de 50 panneaux à la régie nationale de plusieurs milliers de faces. Nos utilisateurs incluent commerciaux, média-planners, équipes terrain, comptables, direction générale et les annonceurs eux-mêmes via leur espace client sécurisé."
                        ],
                        [
                            'q' => "Peut-on utiliser Panora hors Côte d'Ivoire ?",
                            'a' => "Oui. Le moteur fiscal est modulaire : aujourd'hui il gère la conformité FNE Côte d'Ivoire (TVA, TSP, TM, ODP). Pour un autre pays, on branche le référentiel local (Sénégal, Cameroun, Togo, France, etc.) sans réécrire le produit. Nous accompagnons chaque déploiement international avec un audit fiscal préalable."
                        ],
                        [
                            'q' => "Y a-t-il une version démo à tester ?",
                            'a' => "Oui — nous vous offrons une démo live de 45 minutes avec un jeu de données pré-préparé ou une préconfiguration sur votre propre parc. Aucun engagement, aucune carte bancaire."
                        ],
                    ],

                    'Déploiement &amp; installation' => [
                        [
                            'q' => "Combien de temps pour déployer Panora dans notre régie ?",
                            'a' => "Entre 2 et 6 semaines selon la taille du parc et les intégrations demandées. Étapes typiques : (1) audit métier + validation configuration, (2) import du catalogue panneaux existant, (3) formation des équipes par rôle, (4) mise en production progressive avec accompagnement."
                        ],
                        [
                            'q' => "Faut-il installer quelque chose sur nos serveurs ?",
                            'a' => "Non. Panora est un SaaS accessible depuis un navigateur (Chrome, Firefox, Safari, Edge). L'appli mobile pour les techniciens est une PWA — pas de téléchargement app store, elle s'installe en un clic depuis le navigateur du téléphone."
                        ],
                        [
                            'q' => "Peut-on migrer nos données existantes (Excel, autre logiciel) ?",
                            'a' => "Oui. Import Excel du catalogue panneaux, des clients, des campagnes en cours. Pour une migration depuis un logiciel tiers, nous étudions le format d'export et proposons un plan de migration. La règle : aucun historique perdu."
                        ],
                        [
                            'q' => "Y a-t-il de la formation incluse ?",
                            'a' => "Oui. Chaque rôle bénéficie d'une session de formation dédiée : direction, commerciaux, MP, terrain, comptable. Manuels utilisateur PDF fournis. Une hotline dédiée pendant le premier mois pour lever tous les blocages."
                        ],
                    ],

                    'Prix &amp; engagement' => [
                        [
                            'q' => "Combien coûte Panora ?",
                            'a' => "Le prix dépend de la taille de votre parc et des modules activés. Trois formules types : starter (jusqu'à 200 panneaux), business (jusqu'à 2 000), enterprise (illimité + intégrations sur mesure). Devis personnalisé sous 72 h après une démo."
                        ],
                        [
                            'q' => "Y a-t-il un engagement de durée ?",
                            'a' => "Contrat annuel renouvelable, résiliable à échéance sans frais. Aucun frais d'entrée caché. Les frais de mise en service (formation + configuration) sont facturés une seule fois au démarrage."
                        ],
                        [
                            'q' => "Que se passe-t-il si nous décidons d'arrêter ?",
                            'a' => "Vous recevez un export complet de vos données (Excel, CSV, PDF des factures historiques) — c'est votre bien, jamais le nôtre. Vos données sont conservées 6 mois après résiliation puis supprimées définitivement sur simple demande écrite."
                        ],
                    ],

                    'Sécurité &amp; confidentialité' => [
                        [
                            'q' => "Comment sont sécurisées mes données ?",
                            'a' => "Chiffrement en transit (HTTPS obligatoire), sauvegardes chiffrées automatiques quotidiennes, mots de passe hachés (bcrypt), journal d'audit sur les données sensibles (factures, versements, données clients). Restauration testée mensuellement."
                        ],
                        [
                            'q' => "Les autres régies clientes de Panora voient-elles nos données ?",
                            'a' => "Non — impossible techniquement. Chaque régie a son espace complètement isolé (multi-tenant strict). Aucun croisement de données possible entre régies, y compris pour les comptes techniques Panora."
                        ],
                        [
                            'q' => "Êtes-vous conformes RGPD ?",
                            'a' => "Oui. Registre des traitements documenté, droits d'accès / rectification / portabilité / effacement respectés. Un DPO externe peut être mis à disposition. Contrat de sous-traitance des données signé au démarrage."
                        ],
                        [
                            'q' => "Quelle est votre disponibilité (SLA) ?",
                            'a' => "Objectif d'uptime 99,5 % (hors maintenance planifiée annoncée). Monitoring 24/7 avec alertes automatiques. Temps de réaction incident critique : &lt; 2 h ouvrées. Détails précis dans le contrat SLA."
                        ],
                    ],

                    'Support &amp; évolution' => [
                        [
                            'q' => "Quel est le support inclus ?",
                            'a' => "Support par email + WhatsApp pendant les heures ouvrées, base de connaissances en ligne, manuels utilisateur par rôle. Support premium (24/7 + numéro dédié) disponible en option."
                        ],
                        [
                            'q' => "À quelle fréquence Panora est-il mis à jour ?",
                            'a' => "Mises à jour continues, sans interruption pour vous. Nouvelles fonctionnalités livrées en moyenne toutes les 2 à 4 semaines. Roadmap publique consultable par les régies clientes."
                        ],
                        [
                            'q' => "Peut-on personnaliser Panora à nos besoins spécifiques ?",
                            'a' => "Oui, dans deux dimensions : (1) paramétrage sans code (types de panneaux, taxes locales, statuts campagne, rôles utilisateurs) — inclus. (2) Développements sur mesure (intégrations comptables, connecteurs CRM, modules métier spécifiques) — sur devis."
                        ],
                        [
                            'q' => "Peut-on avoir une intégration avec notre outil comptable / CRM ?",
                            'a' => "Oui — Panora expose une API REST documentée. Nous avons déjà réalisé des intégrations avec les principaux logiciels comptables du marché africain. Pour un CRM spécifique, nous étudions le connecteur à faire."
                        ],
                    ],
                ];
            @endphp

            @foreach($faq as $categoryTitle => $items)
                <div class="faq-category">
                    <h2 class="faq-cat-title">{!! $categoryTitle !!}</h2>
                    <div class="faq-items">
                        @foreach($items as $item)
                            <details class="faq-item">
                                <summary>
                                    <span class="faq-q">{{ $item['q'] }}</span>
                                    <span class="faq-chevron">+</span>
                                </summary>
                                <div class="faq-a">{{ $item['a'] }}</div>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- CTA bas de FAQ --}}
            <div style="text-align:center;margin-top:60px;padding:40px;background:#fff;border:1px solid var(--line);border-radius:16px">
                <h3 style="font-family:'Fraunces',serif;font-size:24px;font-weight:500;color:var(--ink);margin-bottom:12px">Une autre question ?</h3>
                <p style="color:var(--ink-3);margin-bottom:20px">Nous répondons dans la journée ouvrée.</p>
                <a href="{{ route('landing.demo') }}" class="btn btn-dark">Nous contacter</a>
            </div>
        </div>
    </section>

    @push('head')
    <style>
        .faq-category { margin-bottom: 44px; }
        .faq-cat-title {
            font-family: 'Fraunces', serif;
            font-size: 28px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--accent, #E8A020);
            letter-spacing: -0.01em;
        }
        .faq-items { display: flex; flex-direction: column; gap: 10px; }
        .faq-item {
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 12px;
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .faq-item[open] { border-color: var(--accent, #E8A020); box-shadow: 0 6px 20px -12px rgba(11,15,25,.15); }
        .faq-item summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 24px;
            cursor: pointer;
            list-style: none;
            user-select: none;
        }
        .faq-item summary::-webkit-details-marker { display: none; }
        .faq-q {
            flex: 1;
            font-family: 'Fraunces', serif;
            font-size: 17px;
            font-weight: 500;
            color: var(--ink);
            line-height: 1.35;
        }
        .faq-chevron {
            font-size: 24px;
            font-weight: 300;
            color: var(--ink-3);
            transition: transform .25s ease, color .25s ease;
            line-height: 1;
        }
        .faq-item[open] .faq-chevron {
            transform: rotate(45deg);
            color: var(--accent, #E8A020);
        }
        .faq-a {
            padding: 0 24px 22px 24px;
            font-size: 15px;
            line-height: 1.7;
            color: var(--ink-3);
        }
    </style>
    @endpush

@endsection

@extends('public.cible._layout', [
    'seo_title'       => 'Qui sommes-nous — CIBLE CI · 30 ans de communication extérieure',
    'seo_description' => 'CIBLE CI, régie publicitaire ivoirienne fondée en 1994. Trois décennies au service des marques, trois distinctions officielles de l\'État.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap-narrow">
            <span class="eyebrow">Qui sommes-nous</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5vw, 60px);">
                Trois décennies<br>à porter la parole<br>des <em>marques ivoiriennes.</em>
            </h1>
            <p class="lead">
                Fondée en 1994, CIBLE CI a grandi avec l'économie ivoirienne. Nous avons vu
                les grandes marques nationales émerger, les enseignes internationales
                s'installer, la publicité extérieure devenir une industrie. À chaque étape,
                nous avons construit, maintenu et étendu le réseau d'affichage qui fait
                aujourd'hui notre force.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ TIMELINE 30 ANS ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Notre histoire</span>
                <h2 class="section-title">Trente ans, <em>une même vocation.</em></h2>
            </div>

            <div class="timeline">
                <div class="tl-item">
                    <div class="tl-year">1994</div>
                    <div class="tl-content">
                        <h3>Fondation</h3>
                        <p>CIBLE est créée à Abidjan avec une conviction : la publicité extérieure
                        peut être un métier structuré, professionnel, au service des marques ivoiriennes.</p>
                    </div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">1994<br>—<br>2010</div>
                    <div class="tl-content">
                        <h3>Construction du patrimoine</h3>
                        <p>Panneau après panneau, commune après commune. Nous bâtissons méthodiquement
                        le réseau qui deviendra la première couverture publicitaire du pays.</p>
                    </div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">2010<br>—<br>2016</div>
                    <div class="tl-content">
                        <h3>Extension à l'intérieur du pays</h3>
                        <p>De Yamoussoukro à San-Pédro, de Bouaké à Korhogo, nous couvrons progressivement
                        toutes les villes de plus de 100 000 habitants. Le réseau devient national.</p>
                    </div>
                </div>
                <div class="tl-item is-honor">
                    <div class="tl-year">2016</div>
                    <div class="tl-content">
                        <h3>🏆 2ème prix du meilleur publicitaire</h3>
                        <p>Première reconnaissance professionnelle du secteur — la qualité de notre
                        travail est saluée par les pairs.</p>
                    </div>
                </div>
                <div class="tl-item is-honor">
                    <div class="tl-year">2019</div>
                    <div class="tl-content">
                        <h3>🏅 Chevalier de l'Ordre du Mérite de la Communication</h3>
                        <p>L'État ivoirien reconnaît la contribution de CIBLE à la structuration du
                        métier de la publicité extérieure dans le pays.</p>
                    </div>
                </div>
                <div class="tl-item is-honor">
                    <div class="tl-year">2020</div>
                    <div class="tl-content">
                        <h3>🎖️ Officier de l'Ordre du Mérite National</h3>
                        <p>Distinction républicaine remise en reconnaissance des services rendus
                        au pays au fil de trois décennies.</p>
                    </div>
                </div>
                <div class="tl-item">
                    <div class="tl-year">Aujourd'hui</div>
                    <div class="tl-content">
                        <h3>Le premier réseau du pays</h3>
                        <p>364 panneaux dans 31 communes. Trois pôles de service (régie, mobile, 360°).
                        Une équipe complète, du commercial au technicien terrain. Et la plateforme
                        numérique qui suit chaque campagne en temps réel.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ DISTINCTIONS DÉTAILLÉES ═══════════════════ --}}
    <section style="background: var(--ink); color: #fff; border: none;">
        <div class="wrap">
            <div class="split-2">
                <div>
                    <span class="eyebrow" style="color: var(--accent);">Reconnaissances</span>
                    <h2 class="section-title" style="color: #fff;">
                        Trois fois honorée<br>par <em>la République.</em>
                    </h2>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        Ces distinctions ne sont pas des trophées à afficher. Elles disent
                        une chose simple : la manière dont CIBLE fait son métier a fait école,
                        et sa contribution au tissu économique ivoirien a été reconnue au
                        plus haut niveau.
                    </p>
                    <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                        Pour un annonceur, ces reconnaissances sont un gage de sérieux et de
                        pérennité — la garantie de traiter avec une régie qui a construit
                        sa légitimité sur trois décennies.
                    </p>
                </div>
                <div>
                    <div class="terrain-placeholder" style="aspect-ratio: 3/4; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6);">
                        <div>
                            <strong style="color: rgba(255,255,255,0.85);">Photo cérémonie</strong>
                            Remise décoration officielle
                            <small style="color: rgba(255,255,255,0.4);">public/images/cible/decoration.jpg</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ ÉQUIPE ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">L'équipe</span>
                <h2 class="section-title">Chaque campagne mobilise <em>plusieurs métiers.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    De votre premier appel à la pose de votre affichage, sept métiers se
                    coordonnent chez CIBLE pour que votre campagne soit livrée dans les
                    règles. Voici qui vous accompagne.
                </p>
            </div>

            <div class="team-grid">
                @foreach([
                    ['Direction', 'Vision, décisions stratégiques, relations partenaires institutionnels.'],
                    ['Commerciaux', 'Votre interlocuteur unique. Écoute, proposition, négociation, suivi.'],
                    ['Média-planners', 'Sélectionnent les emplacements optimaux selon votre cible et votre budget.'],
                    ['Techniciens terrain', 'Interviennent sur les panneaux : pose, dépose, maintenance, contrôle.'],
                    ['Afficheurs', 'Posent physiquement vos visuels dans les délais convenus.'],
                    ['Studio création', 'Conçoivent vos visuels si vous n\'avez pas d\'agence — impact et lisibilité.'],
                    ['Développement', 'Font vivre la plateforme numérique qui centralise le suivi de vos campagnes.'],
                ] as [$role, $desc])
                    <div class="team-card">
                        <h4>{{ $role }}</h4>
                        <p>{{ $desc }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; background: var(--bg-cream); border: none;">
        <div class="wrap-narrow">
            <h2 class="section-title" style="margin-bottom: 26px;">
                Rejoignez les marques qui <em>nous font confiance.</em>
            </h2>
            <p class="lead" style="margin: 0 auto 32px; text-align: center;">
                Danone, SIPRA, Moov Africa, les banques ivoiriennes — elles ont toutes
                choisi CIBLE pour une raison : le sérieux du réseau et la clarté du suivi.
            </p>
            <a href="{{ route('cible.contact') }}" class="btn btn-accent" style="font-size: 16px; padding: 17px 34px;">
                Demander un devis
            </a>
        </div>
    </section>

    @push('head')
    <style>
        .timeline {
            display: grid;
            gap: 30px;
            max-width: 900px;
        }
        .tl-item {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 30px;
            align-items: start;
            padding: 24px 0;
            border-bottom: 1px solid var(--line);
        }
        .tl-item:last-child { border-bottom: none; }
        .tl-year {
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 22px;
            color: var(--accent);
            line-height: 1.2;
        }
        .tl-content h3 {
            font-family: 'Inter', sans-serif;
            font-size: 22px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--ink);
        }
        .tl-content p {
            font-size: 15.5px;
            color: var(--ink-3);
            line-height: 1.65;
        }
        .tl-item.is-honor .tl-content h3 { color: var(--accent); }

        @media (max-width: 720px) {
            .tl-item { grid-template-columns: 1fr; gap: 12px; }
            .tl-year { font-size: 18px; }
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
        }
        .team-card {
            padding: 26px 24px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px;
            border-left: 3px solid var(--accent);
        }
        .team-card h4 {
            font-family: 'Inter', sans-serif;
            font-size: 19px;
            font-weight: 500;
            color: var(--ink);
            margin-bottom: 10px;
        }
        .team-card p {
            font-size: 14px;
            color: var(--ink-3);
            line-height: 1.6;
        }
    </style>
    @endpush

@endsection

@extends('public.cible._layout', [
    'seo_title'       => 'Le réseau — CIBLE CI · 364 panneaux dans 31 communes',
    'seo_description' => 'Le réseau CIBLE : 364 panneaux publicitaires · 180 à Abidjan (14 communes) · 184 à l\'intérieur (17 villes) · Détail par commune.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 40px;">
        <div class="wrap-narrow">
            <span class="eyebrow reveal reveal-fade">Le réseau</span>
            <h1 class="hero-title reveal" data-delay="1" style="font-size: clamp(38px, 5vw, 60px);">
                364 panneaux.<br>
                31 communes.<br>
                <em>Toute la Côte d'Ivoire.</em>
            </h1>
            <p class="lead reveal" data-delay="2">
                Notre patrimoine terrain n'est pas un slogan. Chaque panneau est référencé,
                localisé, contrôlé — voici la répartition complète, par zone puis par commune.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ SPLIT CHIFFRES ═══════════════════ --}}
    <section style="padding: 40px 0 80px; border: none;">
        <div class="wrap">
            <div class="split-chiffres">
                <div class="split-block">
                    <div class="split-num">180</div>
                    <div class="split-lbl">panneaux · Zone Abidjan</div>
                    <div class="split-sub">14 communes du District Autonome<br>+ pseudo-commune Autoroute du Nord</div>
                </div>
                <div class="split-sep"></div>
                <div class="split-block">
                    <div class="split-num">184</div>
                    <div class="split-lbl">panneaux · Intérieur du pays</div>
                    <div class="split-sub">17 villes de Bouaké à Adiaké,<br>en passant par Yamoussoukro, San-Pédro, Korhogo…</div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ ZONE ABIDJAN ═══════════════════ --}}
    <section id="abidjan" style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 40px;">
                <span class="eyebrow reveal reveal-fade">Zone Abidjan · 180 panneaux</span>
                <h2 class="section-title reveal" data-delay="1">14 communes couvertes<br><em>dans le Grand Abidjan.</em></h2>
            </div>

            <div class="reseau-table-wrap">
                <table class="reseau-table">
                    <thead>
                        <tr>
                            <th>Commune</th>
                            <th class="num">Panneaux</th>
                            <th class="bar">Répartition</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $abidjan = [
                                ['Cocody', 30], ['Plateau', 28], ['Port-Bouët', 18], ['Yopougon', 15],
                                ['Treichville', 12], ['Bingerville', 11], ['Adjamé', 10], ['Bassam', 7],
                                ['Attecoubé', 6], ['Marcory', 4], ['Songon', 4], ['Assinie', 2],
                                ['Koumassi', 2], ['Autoroute du Nord', 2],
                            ];
                            $maxA = 30;
                        @endphp
                        @foreach($abidjan as [$name, $count])
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="num">{{ $count }}</td>
                                <td class="bar"><span class="bar-fill" style="width:{{ ($count/$maxA)*100 }}%"></span></td>
                            </tr>
                        @endforeach
                        <tr class="tot">
                            <td>Total Abidjan</td>
                            <td class="num"><strong>180</strong></td>
                            <td class="bar"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ ZONE INTÉRIEUR ═══════════════════ --}}
    <section id="interieur">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 40px;">
                <span class="eyebrow reveal reveal-fade">Zone Intérieur · 184 panneaux</span>
                <h2 class="section-title reveal" data-delay="1">17 villes<br><em>à travers le pays.</em></h2>
            </div>

            <div class="reseau-table-wrap">
                <table class="reseau-table">
                    <thead>
                        <tr>
                            <th>Ville</th>
                            <th class="num">Panneaux</th>
                            <th class="bar">Répartition</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $interieur = [
                                ['Bouaké', 54], ['Yamoussoukro', 30], ['Korhogo', 16], ['Daloa', 15],
                                ['San-Pédro', 14], ['Gagnoa', 11], ['Odienné', 8], ['Bondoukou', 8],
                                ['Abengourou', 6], ['Man', 5], ['Soubré', 5], ['Samo', 4],
                                ['Ferké', 4], ['Bonoua', 2], ['Assinie (intérieur)', 2],
                                ['Bouaflé', 1], ['Adiaké-Assinie', 1],
                            ];
                            $maxI = 54;
                        @endphp
                        @foreach($interieur as [$name, $count])
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="num">{{ $count }}</td>
                                <td class="bar"><span class="bar-fill" style="width:{{ ($count/$maxI)*100 }}%"></span></td>
                            </tr>
                        @endforeach
                        <tr class="tot">
                            <td>Total Intérieur</td>
                            <td class="num"><strong>184</strong></td>
                            <td class="bar"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CARTE PLACEHOLDER ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 40px;">
                <span class="eyebrow reveal reveal-fade">Carte du réseau</span>
                <h2 class="section-title reveal" data-delay="1">Visualisation <em>géographique.</em></h2>
                <p class="body">
                    Chaque pin représente un cluster de panneaux dans une commune. Zoom
                    disponible pour identifier chaque emplacement individuel.
                </p>
            </div>
            <div class="photo-tile reveal has-caption" style="aspect-ratio: 16/9; max-width: none;">
                <img src="{{ asset('images/cible/hero-plateau-night.jpg') }}" alt="Panneau CIBLE Abidjan — extrait du réseau">
                <div class="photo-caption">
                    <strong>Carte interactive à venir</strong>
                    <small>Coordonnées GPS des 364 panneaux · clustering par zoom (à intégrer avec Leaflet)</small>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; border: none;">
        <div class="wrap-narrow">
            <span class="eyebrow reveal reveal-fade">Un besoin ciblé ?</span>
            <h2 class="section-title reveal" data-delay="1" style="margin-bottom: 26px;">
                Choisissons <em>ensemble</em> vos emplacements.
            </h2>
            <p class="lead reveal" data-delay="2" style="margin: 0 auto 32px; text-align: center;">
                Notre équipe commerciale connaît le terrain par cœur. Dites-nous votre cible,
                votre budget, votre période — nous vous proposons les meilleurs emplacements.
            </p>
            <a href="{{ route('cible.contact') }}" class="btn btn-accent" style="font-size: 16px; padding: 17px 34px;">
                Demander un devis
            </a>
        </div>
    </section>

    @push('head')
    <style>
        .split-chiffres {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 40px;
            align-items: center;
            padding: 40px;
            background: linear-gradient(135deg, var(--bg-cream), #fff);
            border: 1px solid var(--line);
            border-radius: 8px;
        }
        .split-sep {
            width: 1px;
            height: 120px;
            background: var(--line);
        }
        .split-num {
            font-family: 'Inter', sans-serif;
            font-size: 90px;
            font-weight: 400;
            color: var(--accent);
            line-height: 0.95;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }
        .split-lbl {
            font-size: 15px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: 0.02em;
        }
        .split-sub {
            font-size: 13.5px;
            color: var(--ink-4);
            margin-top: 6px;
            line-height: 1.55;
        }
        @media (max-width: 720px) {
            .split-chiffres { grid-template-columns: 1fr; padding: 30px 24px; }
            .split-sep { display: none; }
            .split-num { font-size: 68px; }
        }

        .reseau-table-wrap { overflow-x: auto; }
        .reseau-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--line);
        }
        .reseau-table th {
            padding: 16px 20px;
            text-align: left;
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ink-4);
            border-bottom: 2px solid var(--line);
            background: var(--bg-cream);
        }
        .reseau-table th.num { text-align: right; width: 120px; }
        .reseau-table th.bar { width: 45%; }
        .reseau-table td {
            padding: 14px 20px;
            font-size: 15px;
            color: var(--ink-2);
            border-bottom: 1px solid var(--line-2);
        }
        .reseau-table td.num {
            text-align: right;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 18px;
            color: var(--accent);
        }
        .reseau-table td.bar { padding: 14px 20px; }
        .bar-fill {
            display: block;
            height: 8px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            border-radius: 4px;
            min-width: 4px;
        }
        .reseau-table tbody tr:hover td { background: rgba(232, 160, 32, 0.03); }
        .reseau-table tr.tot td {
            background: var(--ink);
            color: #fff;
            font-weight: 700;
            border-bottom: none;
        }
        .reseau-table tr.tot td.num strong {
            color: var(--accent);
            font-family: 'Inter', sans-serif;
            font-size: 22px;
        }
    </style>
    @endpush

@endsection

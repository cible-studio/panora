@extends('public.cible._layout', [
    'seo_title'       => 'Références — CIBLE CI · Danone, SIPRA, Moov, banques',
    'seo_description' => 'Les marques qui ont fait confiance à CIBLE : Danone, SIPRA, Moov Africa, Banque Atlantique, BGFIBank, Rimco Motors et bien d\'autres depuis 30 ans.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap-narrow">
            <span class="eyebrow">Nos références</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5vw, 60px);">
                Trente ans à porter<br>
                la parole des marques qui <em>font l'économie ivoirienne.</em>
            </h1>
            <p class="lead">
                De l'agroalimentaire à la banque, des télécoms à l'automobile, les grands
                noms de l'économie nous ont confié leur voix publique. Voici quelques-unes
                des marques qui nous font confiance — souvent depuis plusieurs années.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ CLIENTS PAR SECTEUR ═══════════════════ --}}
    <section style="background: var(--bg-cream);">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 50px;">
                <span class="eyebrow">Ils nous font confiance</span>
                <h2 class="section-title">Des <em>secteurs variés,</em> une même exigence.</h2>
            </div>

            <div class="secteur-blocks">
                @foreach([
                    ['Agroalimentaire', ['Danone', 'SIPRA']],
                    ['Télécoms',        ['Moov Africa']],
                    ['Banque & Finance', ['Banque Atlantique', 'BGFIBank']],
                    ['Automobile',      ['Rimco Motors']],
                    ['Autres secteurs', ['+ 6 marques', '(logos à intégrer)']],
                ] as [$secteur, $marques])
                    <div class="secteur-block">
                        <div class="secteur-label">{{ $secteur }}</div>
                        <div class="secteur-marques">
                            @foreach($marques as $m)
                                <div class="marque-chip">{{ $m }}</div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════ TÉMOIGNAGES (placeholder) ═══════════════════ --}}
    <section>
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 60px;">
                <span class="eyebrow">Ils en parlent</span>
                <h2 class="section-title">Ce qu'ils disent <em>de nous.</em></h2>
                <p class="body" style="font-size: 17px; color: var(--ink-3);">
                    Trois témoignages courts de directeurs marketing qui ont travaillé
                    avec CIBLE ces dernières années.
                </p>
            </div>

            <div class="temoignages-grid">
                @foreach([
                    [
                        'quote' => 'À intégrer — 2 phrases d\'un directeur marketing d\'un client majeur, expliquant pourquoi CIBLE reste sa régie de référence.',
                        'nom'   => 'Nom Prénom',
                        'poste' => 'Directeur Marketing',
                        'entreprise' => 'Entreprise (à confirmer)',
                    ],
                    [
                        'quote' => 'À intégrer — un témoignage d\'agence de com qui met en avant la fiabilité opérationnelle et la traçabilité des campagnes.',
                        'nom'   => 'Nom Prénom',
                        'poste' => 'Media Planner',
                        'entreprise' => 'Agence (à confirmer)',
                    ],
                    [
                        'quote' => 'À intégrer — un témoignage sur la couverture nationale, argument de l\'intérieur du pays (Bouaké, San-Pédro).',
                        'nom'   => 'Nom Prénom',
                        'poste' => 'Chef de produit',
                        'entreprise' => 'Marque (à confirmer)',
                    ],
                ] as $t)
                    <div class="temoignage-card">
                        <div class="tem-quote">« {{ $t['quote'] }} »</div>
                        <div class="tem-auteur">
                            <strong>{{ $t['nom'] }}</strong>
                            <small>{{ $t['poste'] }}<br>{{ $t['entreprise'] }}</small>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="aside-note" style="margin-top: 40px;">
                Les témoignages ci-dessus sont des <strong>placeholders</strong> — à remplacer
                par 3 vrais témoignages clients avant mise en ligne publique.
            </div>
        </div>
    </section>

    {{-- ═══════════════════ GALERIE CAMPAGNES ═══════════════════ --}}
    <section style="background: var(--ink); color: #fff; border: none;">
        <div class="wrap">
            <div style="max-width: 720px; margin-bottom: 50px;">
                <span class="eyebrow" style="color: var(--accent);">Galerie de campagnes</span>
                <h2 class="section-title" style="color: #fff;">Nos affichages <em>en situation.</em></h2>
                <p class="body" style="color: rgba(255,255,255,0.75); font-size: 17px;">
                    Quelques campagnes emblématiques que nous avons opérées ces dernières
                    années. Chaque affichage a été posé, contrôlé, pigé par nos équipes.
                </p>
            </div>

            <div class="galerie-grid">
                @for($i = 1; $i <= 6; $i++)
                    <div class="galerie-tile">
                        <div class="galerie-placeholder">
                            <strong>Photo campagne #{{ $i }}</strong>
                            <small>public/images/cible/campagne-{{ $i }}.jpg</small>
                        </div>
                        <div class="galerie-caption">
                            <span>Nom campagne</span>
                            <small>Client · Année</small>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CTA ═══════════════════ --}}
    <section style="text-align: center; border: none;">
        <div class="wrap-narrow">
            <h2 class="section-title" style="margin-bottom: 26px;">
                Rejoignez-les <em>sur nos murs.</em>
            </h2>
            <p class="lead" style="margin: 0 auto 32px; text-align: center;">
                Votre marque mérite d'être vue avec la même qualité d'exécution.
            </p>
            <a href="{{ route('cible.contact') }}" class="btn btn-accent" style="font-size: 16px; padding: 17px 34px;">
                Demander un devis
            </a>
        </div>
    </section>

    @push('head')
    <style>
        .secteur-blocks {
            display: flex; flex-direction: column;
            gap: 14px;
        }
        .secteur-block {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 30px;
            align-items: center;
            padding: 22px 26px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px;
            border-left: 3px solid var(--accent);
        }
        .secteur-label {
            font-family: 'Inter', sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--accent);
        }
        .secteur-marques {
            display: flex; flex-wrap: wrap; gap: 10px;
        }
        .marque-chip {
            padding: 8px 16px;
            background: var(--bg-cream);
            border: 1px solid var(--line);
            border-radius: 4px;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 15px;
            color: var(--ink);
        }
        @media (max-width: 720px) {
            .secteur-block { grid-template-columns: 1fr; gap: 12px; }
        }

        .temoignages-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .temoignage-card {
            padding: 34px 28px;
            background: var(--bg-cream);
            border: 1px solid var(--line);
            border-radius: 4px;
            display: flex; flex-direction: column;
            justify-content: space-between;
        }
        .tem-quote {
            font-family: 'Inter', sans-serif;
            font-style: italic;
            font-size: 18px;
            line-height: 1.55;
            color: var(--ink-2);
            margin-bottom: 24px;
        }
        .tem-auteur strong {
            display: block;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            font-weight: 700;
            color: var(--ink);
        }
        .tem-auteur small {
            display: block;
            font-size: 12.5px;
            color: var(--ink-4);
            margin-top: 4px;
            line-height: 1.5;
        }
        @media (max-width: 900px) { .temoignages-grid { grid-template-columns: 1fr; } }

        .galerie-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .galerie-tile {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.10);
            border-radius: 4px;
            overflow: hidden;
        }
        .galerie-placeholder {
            aspect-ratio: 4/3;
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(232,160,32,0.05));
            color: rgba(255,255,255,0.55);
            font-size: 12.5px;
            text-align: center;
            padding: 20px;
        }
        .galerie-placeholder strong {
            display: block;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 16px;
            color: rgba(255,255,255,0.8);
            margin-bottom: 6px;
        }
        .galerie-placeholder small {
            display: block;
            font-family: monospace;
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            margin-top: 4px;
        }
        .galerie-caption {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .galerie-caption span {
            display: block;
            font-family: 'Inter', sans-serif;
            font-weight: 500;
            font-size: 16px;
            color: #fff;
        }
        .galerie-caption small {
            display: block;
            font-size: 11.5px;
            color: rgba(255,255,255,0.5);
            margin-top: 3px;
        }
        @media (max-width: 900px) { .galerie-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 560px) { .galerie-grid { grid-template-columns: 1fr; } }
    </style>
    @endpush

@endsection

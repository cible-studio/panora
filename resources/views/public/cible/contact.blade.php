@extends('public.cible._layout', [
    'seo_title'       => 'Contact — CIBLE CI · Demande de devis',
    'seo_description' => 'Contactez CIBLE CI : demande de devis, informations sur le réseau, opportunités de partenariat. Réponse dans la journée ouvrée.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 40px;">
        <div class="wrap-narrow" style="text-align: center;">
            <span class="eyebrow">Contact</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5vw, 56px);">
                Parlons de <em>votre campagne.</em>
            </h1>
            <p class="lead" style="margin: 0 auto;">
                Décrivez votre besoin en quelques champs. Notre équipe commerciale vous
                rappelle dans la journée ouvrée avec une proposition sur mesure.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ FORMULAIRE + INFOS ═══════════════════ --}}
    <section style="padding-top: 40px; border: none;">
        <div class="wrap">
            <div class="split-2" style="gap: 80px;">
                {{-- Formulaire --}}
                <div>
                    @if(session('devis_sent'))
                        <div style="padding: 40px; background: #f0f9f0; border: 1px solid #86e186; border-radius: 8px; text-align: center;">
                            <div style="font-family: 'Playfair Display', serif; font-size: 32px; color: #1a7a1a; margin-bottom: 12px;">Bien reçu.</div>
                            <p style="color: #2d5f2d; font-size: 15px; line-height: 1.6;">
                                Votre demande est arrivée. Notre équipe commerciale vous
                                recontacte dans la journée ouvrée à l'adresse fournie.
                            </p>
                        </div>
                    @else
                        @if(session('devis_error'))
                            <div style="padding: 18px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; color: #991b1b; font-size: 14px; margin-bottom: 24px;">
                                {{ session('devis_error') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('cible.devis.submit') }}" style="display: grid; gap: 20px;">
                            @csrf

                            {{-- Honeypot --}}
                            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;opacity:0;" aria-hidden="true">

                            <div class="form-row-2">
                                <div>
                                    <label for="f-nom">Votre nom *</label>
                                    <input type="text" id="f-nom" name="nom" required maxlength="100" value="{{ old('nom') }}" placeholder="ex. Kouassi Aya">
                                    @error('nom') <span class="err">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="f-poste">Votre poste</label>
                                    <input type="text" id="f-poste" name="poste" maxlength="100" value="{{ old('poste') }}" placeholder="ex. Directrice Marketing">
                                </div>
                            </div>

                            <div>
                                <label for="f-entreprise">Entreprise / Marque *</label>
                                <input type="text" id="f-entreprise" name="entreprise" required maxlength="150" value="{{ old('entreprise') }}" placeholder="ex. Danone Côte d'Ivoire">
                                @error('entreprise') <span class="err">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-row-2">
                                <div>
                                    <label for="f-tel">Téléphone *</label>
                                    <input type="tel" id="f-tel" name="tel" required maxlength="30" value="{{ old('tel') }}" placeholder="+225 07 XX XX XX XX">
                                    @error('tel') <span class="err">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="f-email">Email professionnel *</label>
                                    <input type="email" id="f-email" name="email" required maxlength="150" value="{{ old('email') }}" placeholder="vous@votre-entreprise.ci">
                                    @error('email') <span class="err">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="f-besoin">Votre besoin *</label>
                                <select id="f-besoin" name="besoin" required>
                                    <option value="">— Choisir —</option>
                                    <option value="affichage"  {{ old('besoin') === 'affichage' ? 'selected' : '' }}>Affichage publicitaire (panneaux)</option>
                                    <option value="mobile"     {{ old('besoin') === 'mobile' ? 'selected' : '' }}>Communication mobile (camions, motos, branding)</option>
                                    <option value="360"        {{ old('besoin') === '360' ? 'selected' : '' }}>Campagne 360° (création + digital + terrain)</option>
                                    <option value="autre"      {{ old('besoin') === 'autre' ? 'selected' : '' }}>Autre / à préciser</option>
                                </select>
                                @error('besoin') <span class="err">{{ $message }}</span> @enderror
                            </div>

                            <div class="form-row-2">
                                <div>
                                    <label for="f-zone">Zone visée</label>
                                    <select id="f-zone" name="zone">
                                        <option value="">— Préférence —</option>
                                        <option value="abidjan"   {{ old('zone') === 'abidjan' ? 'selected' : '' }}>Zone Abidjan uniquement</option>
                                        <option value="interieur" {{ old('zone') === 'interieur' ? 'selected' : '' }}>Intérieur du pays</option>
                                        <option value="national"  {{ old('zone') === 'national' ? 'selected' : '' }}>National (Abidjan + intérieur)</option>
                                        <option value="autre"     {{ old('zone') === 'autre' ? 'selected' : '' }}>À définir ensemble</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="f-budget">Budget indicatif</label>
                                    <select id="f-budget" name="budget">
                                        <option value="">— Préférence —</option>
                                        <option value="moins1M" {{ old('budget') === 'moins1M' ? 'selected' : '' }}>Moins de 1M FCFA</option>
                                        <option value="1a5M"    {{ old('budget') === '1a5M' ? 'selected' : '' }}>1 à 5M FCFA</option>
                                        <option value="5a20M"   {{ old('budget') === '5a20M' ? 'selected' : '' }}>5 à 20M FCFA</option>
                                        <option value="plus20M" {{ old('budget') === 'plus20M' ? 'selected' : '' }}>Plus de 20M FCFA</option>
                                        <option value="pas-sur" {{ old('budget') === 'pas-sur' ? 'selected' : '' }}>À évaluer ensemble</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label for="f-periode">Période souhaitée</label>
                                <input type="text" id="f-periode" name="periode" maxlength="100" value="{{ old('periode') }}" placeholder="ex. Lancement produit octobre 2026 · 3 semaines">
                            </div>

                            <div>
                                <label for="f-msg">Message / précisions</label>
                                <textarea id="f-msg" name="message" rows="4" maxlength="2000" placeholder="ex. Nous préparons une campagne de notoriété autour d'un lancement produit. Grand public urbain, adultes 25-45 ans. Objectif : forte visibilité à Abidjan sur 3 semaines.">{{ old('message') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-accent" style="justify-content: center; font-size: 16px; padding: 17px 34px;">
                                Envoyer ma demande
                            </button>

                            <p style="font-size: 12.5px; color: var(--ink-5); text-align: center; margin-top: -4px;">
                                Réponse dans la journée ouvrée. Ni newsletter, ni prospection agressive.
                            </p>
                        </form>
                    @endif
                </div>

                {{-- Coordonnées --}}
                <div>
                    <div style="position: sticky; top: 100px;">
                        <span class="eyebrow">Coordonnées</span>
                        <h2 class="section-title" style="font-size: 30px; margin-bottom: 30px;">
                            Ou appelez<br>directement.
                        </h2>

                        <div class="coord-list">
                            <div class="coord-item">
                                <div class="coord-icon">📞</div>
                                <div>
                                    <div class="coord-label">Téléphone commercial</div>
                                    <a href="tel:+2250798496674" class="coord-val">07 98 49 66 74</a>
                                </div>
                            </div>
                            <div class="coord-item">
                                <div class="coord-icon">✉️</div>
                                <div>
                                    <div class="coord-label">Email</div>
                                    <a href="mailto:commercial@cible-ci.com" class="coord-val">commercial@cible-ci.com</a>
                                </div>
                            </div>
                            <div class="coord-item">
                                <div class="coord-icon">📍</div>
                                <div>
                                    <div class="coord-label">Notre siège</div>
                                    <div class="coord-val">Rue des ambassadeurs<br>Riviera M'badon, Abidjan<br>Côte d'Ivoire</div>
                                </div>
                            </div>
                        </div>

                        <div class="terrain-placeholder" style="aspect-ratio: 4/3; margin-top: 30px;">
                            <div>
                                <strong>Carte siège</strong>
                                Riviera M'badon
                                <small>Leaflet / OpenStreetMap</small>
                            </div>
                        </div>

                        <div style="margin-top: 30px; padding: 22px; background: var(--bg-cream); border-radius: 4px; font-size: 13.5px; color: var(--ink-3); line-height: 1.6; border-left: 3px solid var(--accent);">
                            <strong style="color: var(--ink); display: block; margin-bottom: 6px;">Horaires</strong>
                            Lundi au vendredi · 8h30 – 17h30<br>
                            Samedi matin sur rendez-vous
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ RÉSEAUX ═══════════════════ --}}
    <section style="text-align: center;">
        <div class="wrap-narrow">
            <span class="eyebrow">Suivez-nous</span>
            <h2 class="section-title" style="font-size: 30px; margin-bottom: 24px;">
                Retrouvez-nous <em>sur les réseaux.</em>
            </h2>
            <div style="display: flex; gap: 16px; justify-content: center; flex-wrap: wrap;">
                <a href="https://facebook.com/cible.ci" target="_blank" rel="noopener" class="btn btn-outline">Facebook</a>
                <a href="https://ci.linkedin.com/company/cible-ci" target="_blank" rel="noopener" class="btn btn-outline">LinkedIn</a>
            </div>
        </div>
    </section>

    @push('head')
    <style>
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        @media (max-width: 640px) { .form-row-2 { grid-template-columns: 1fr; } }

        form label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: var(--ink-2);
            margin-bottom: 8px;
            letter-spacing: 0.01em;
        }
        form input[type="text"],
        form input[type="tel"],
        form input[type="email"],
        form select,
        form textarea {
            width: 100%;
            padding: 13px 15px;
            border: 1.5px solid var(--line);
            border-radius: 4px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        form input:focus, form select:focus, form textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        form textarea { resize: vertical; min-height: 100px; font-family: 'Inter', sans-serif; }
        form select {
            appearance: none; -webkit-appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>");
            background-repeat: no-repeat; background-position: right 15px center;
            padding-right: 40px;
        }
        form .err {
            display: block;
            margin-top: 6px;
            font-size: 12.5px;
            color: #dc2626;
        }

        .coord-list { display: flex; flex-direction: column; gap: 20px; }
        .coord-item {
            display: flex; gap: 16px; align-items: flex-start;
            padding: 18px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 4px;
        }
        .coord-icon {
            font-size: 22px;
            width: 42px; height: 42px;
            border-radius: 50%;
            background: var(--accent-soft);
            display: inline-flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .coord-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--ink-4);
            margin-bottom: 4px;
        }
        .coord-val {
            font-family: 'Playfair Display', serif;
            font-size: 17px;
            font-weight: 500;
            color: var(--ink);
            line-height: 1.4;
        }
        a.coord-val:hover { color: var(--accent); }
    </style>
    @endpush

@endsection

@extends('public.landing._layout', [
    'seo_title'       => 'Demander une démo Panora',
    'seo_description' => 'Réservez une démonstration Panora de 45 minutes. Nous préparons le contexte à la taille de votre régie et vous montrons la plateforme sur vos vrais cas.',
])

@section('content')

    {{-- ═══════════════════ HERO ═══════════════════ --}}
    <section style="padding-top: 100px; padding-bottom: 60px;">
        <div class="wrap-narrow" style="text-align: center;">
            <span class="eyebrow">Demande de démonstration</span>
            <h1 class="hero-title" style="font-size: clamp(38px, 5.5vw, 64px);">
                Voir Panora <em>sur votre métier.</em>
            </h1>
            <p class="lead" style="margin: 0 auto;">
                Nous préparons une session de 45 minutes calquée sur la taille de votre régie —
                nombre de panneaux, de communes, de commerciaux, complexité des taxes locales.
                Vous voyez Panora comme si c'était le vôtre.
            </p>
        </div>
    </section>

    {{-- ═══════════════════ FORMULAIRE + PROMESSES ═══════════════════ --}}
    <section style="padding-top: 40px; border: none;">
        <div class="wrap">
            <div class="split-2" style="gap: 100px;">
                {{-- Formulaire --}}
                <div>
                    @if(session('demo_sent'))
                        <div style="padding: 40px; background: #f0f9f0; border: 1px solid #86e186; border-radius: 14px; text-align: center;">
                            <div style="font-family: 'Fraunces', serif; font-size: 32px; color: #1a7a1a; margin-bottom: 12px;">Reçu.</div>
                            <p style="color: #2d5f2d; font-size: 15px; line-height: 1.6;">
                                Votre demande est arrivée. Nous vous recontactons dans la journée
                                ouvrée à l'adresse fournie pour caler un créneau.
                            </p>
                        </div>
                    @else
                        @if(session('demo_error'))
                            <div style="padding: 18px; background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px; color: #991b1b; font-size: 14px; margin-bottom: 24px;">
                                {{ session('demo_error') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('landing.demo.submit') }}" style="display: grid; gap: 20px;">
                            @csrf

                            {{-- Honeypot --}}
                            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position: absolute; left: -9999px; opacity: 0;" aria-hidden="true">

                            <div>
                                <label for="f-nom">Votre nom complet *</label>
                                <input type="text" id="f-nom" name="nom" required maxlength="100" value="{{ old('nom') }}" placeholder="ex. Aminata Koffi">
                                @error('nom') <span class="err">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="f-regie">Nom de votre régie *</label>
                                <input type="text" id="f-regie" name="regie" required maxlength="150" value="{{ old('regie') }}" placeholder="ex. Régie Média Plus SARL">
                                @error('regie') <span class="err">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label for="f-role">Votre rôle dans la régie *</label>
                                <select id="f-role" name="role" required>
                                    <option value="">— Choisir —</option>
                                    <option value="direction"  {{ old('role') === 'direction' ? 'selected' : '' }}>Direction générale / PDG</option>
                                    <option value="commercial" {{ old('role') === 'commercial' ? 'selected' : '' }}>Direction commerciale</option>
                                    <option value="operations" {{ old('role') === 'operations' ? 'selected' : '' }}>Opérations / Terrain</option>
                                    <option value="autre"      {{ old('role') === 'autre' ? 'selected' : '' }}>Autre</option>
                                </select>
                                @error('role') <span class="err">{{ $message }}</span> @enderror
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div>
                                    <label for="f-tel">Téléphone *</label>
                                    <input type="tel" id="f-tel" name="tel" required maxlength="30" value="{{ old('tel') }}" placeholder="+225 07 XX XX XX XX">
                                    @error('tel') <span class="err">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="f-email">Email professionnel *</label>
                                    <input type="email" id="f-email" name="email" required maxlength="150" value="{{ old('email') }}" placeholder="vous@votre-regie.ci">
                                    @error('email') <span class="err">{{ $message }}</span> @enderror
                                </div>
                            </div>

                            <div>
                                <label for="f-nb">Taille de votre parc panneaux (indicatif)</label>
                                <select id="f-nb" name="nb_panneaux">
                                    <option value="">— Préférez ne pas dire —</option>
                                    <option value="0-50"    {{ old('nb_panneaux') === '0-50' ? 'selected' : '' }}>Moins de 50 panneaux</option>
                                    <option value="50-200"  {{ old('nb_panneaux') === '50-200' ? 'selected' : '' }}>50 à 200 panneaux</option>
                                    <option value="200-500" {{ old('nb_panneaux') === '200-500' ? 'selected' : '' }}>200 à 500 panneaux</option>
                                    <option value="500+"    {{ old('nb_panneaux') === '500+' ? 'selected' : '' }}>Plus de 500 panneaux</option>
                                </select>
                            </div>

                            <div>
                                <label for="f-urgence">Votre horizon</label>
                                <select id="f-urgence" name="urgence">
                                    <option value="">— Choisir —</option>
                                    <option value="immediat"    {{ old('urgence') === 'immediat' ? 'selected' : '' }}>Je cherche une solution maintenant</option>
                                    <option value="3mois"       {{ old('urgence') === '3mois' ? 'selected' : '' }}>Dans les 3 mois qui viennent</option>
                                    <option value="exploration" {{ old('urgence') === 'exploration' ? 'selected' : '' }}>Exploration, veille active</option>
                                </select>
                            </div>

                            <div>
                                <label for="f-msg">Un contexte particulier à nous partager ?</label>
                                <textarea id="f-msg" name="message" rows="4" maxlength="2000" placeholder="ex. Nous avons un audit FNE en octobre. Ou : notre outil actuel gère mal les régies partenaires. Ou : vide, on en parlera de vive voix.">{{ old('message') }}</textarea>
                            </div>

                            <button type="submit" class="btn btn-dark" style="justify-content: center; font-size: 16px; padding: 17px 34px;">
                                Envoyer ma demande de démo
                            </button>

                            <p style="font-size: 12.5px; color: var(--ink-5); text-align: center; margin-top: -4px;">
                                Nous vous recontactons dans la journée ouvrée. Ni newsletter, ni prospection agressive.
                            </p>
                        </form>
                    @endif
                </div>

                {{-- Ce que la démo contient --}}
                <div>
                    <div style="position: sticky; top: 100px;">
                        <span class="eyebrow">Ce que contient la démo</span>
                        <h2 class="section-title" style="font-size: 32px; margin-bottom: 30px;">
                            45 minutes<br>bien remplies.
                        </h2>

                        <div style="display: grid; gap: 26px;">
                            <div>
                                <div style="font-family: 'Fraunces', serif; font-size: 15px; color: var(--accent); font-weight: 500; margin-bottom: 4px;">min 0 → 10</div>
                                <p style="font-size: 15px; color: var(--ink-3); line-height: 1.6;">
                                    Vous racontez votre régie : parc, communes, équipe, ce qui vous fait perdre le plus de temps aujourd'hui.
                                </p>
                            </div>
                            <div>
                                <div style="font-family: 'Fraunces', serif; font-size: 15px; color: var(--accent); font-weight: 500; margin-bottom: 4px;">min 10 → 35</div>
                                <p style="font-size: 15px; color: var(--ink-3); line-height: 1.6;">
                                    On parcourt Panora sur un jeu calibré à votre taille : inventaire, disponibilités, proposition, réservation, campagne, poses terrain, piges, facture FNE, taxes communales, espace client.
                                </p>
                            </div>
                            <div>
                                <div style="font-family: 'Fraunces', serif; font-size: 15px; color: var(--accent); font-weight: 500; margin-bottom: 4px;">min 35 → 45</div>
                                <p style="font-size: 15px; color: var(--ink-3); line-height: 1.6;">
                                    Vos questions concrètes — reprise de vos données existantes, formation équipe, coût, calendrier possible.
                                </p>
                            </div>
                        </div>

                        <div style="margin-top: 40px; padding: 22px; background: var(--bg-cream); border-radius: 12px; font-size: 14px; color: var(--ink-3); line-height: 1.6;">
                            <strong style="color: var(--ink);">Format :</strong> visio (Google Meet, Teams, Zoom au choix)<br>
                            <strong style="color: var(--ink);">Prépa :</strong> aucune de votre côté<br>
                            <strong style="color: var(--ink);">Engagement :</strong> zéro, pas de carte bancaire
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════ CONTACT DIRECT ═══════════════════ --}}
    <section style="text-align: center;">
        <div class="wrap-narrow">
            <span class="eyebrow">Préférez-vous écrire ?</span>
            <h2 class="section-title" style="font-size: 32px; margin-bottom: 20px;">
                <a href="mailto:studio@cible-ci.com" style="color: var(--ink); border-bottom: 2px solid var(--accent); padding-bottom: 4px;">studio@cible-ci.com</a>
            </h2>
            <p style="font-size: 15px; color: var(--ink-3);">Réponse dans la journée ouvrée.</p>
        </div>
    </section>

    @push('head')
    <style>
        form label {
            display: block;
            font-size: 13px;
            font-weight: 600;
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
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            background: #fff;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        form input:focus,
        form select:focus,
        form textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        form textarea { resize: vertical; min-height: 100px; font-family: 'Inter', sans-serif; }
        form select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'><path d='M1 1l5 5 5-5' stroke='%236b7280' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>"); background-repeat: no-repeat; background-position: right 15px center; padding-right: 40px; }
        form .err { display: block; margin-top: 6px; font-size: 12.5px; color: #dc2626; }
    </style>
    @endpush

@endsection

@extends('public.cible._layout', [
    'seo_title'       => 'Contact — CIBLE · Demande de devis en 2 minutes',
    'seo_description' => 'Contactez CIBLE : demande de devis, informations sur le réseau, opportunités de partenariat. Réponse dans la journée ouvrée.',
])

@push('page-css')
    .contact-wrap{padding:clamp(60px,8vw,110px) var(--pad);background:var(--jaune);color:var(--noir);position:relative;overflow:hidden;min-height:60vh}
    .motif{position:absolute;inset:-10% -10%;background-image:var(--tuile);background-size:340px 340px;animation:motifDerive 60s linear infinite;pointer-events:none;z-index:0;opacity:.5}
    @keyframes motifDerive{from{background-position:0 0}to{background-position:340px 340px}}
    .contact-inner{position:relative;z-index:2;display:grid;grid-template-columns:1fr 1.2fr;gap:clamp(30px,5vw,80px);align-items:start}
    @media(max-width:900px){.contact-inner{grid-template-columns:1fr}}
    .contact-inner .intro .t1{max-width:16ch}
    .contact-inner .intro p{margin-top:20px;max-width:44ch;font-size:clamp(16px,1.6vw,20px)}
    /* Carte coordonnées 2026-08-04 — refonte propre :
       3 lignes avec icône colorée + label uppercase + valeur.
       Chaque ligne est distincte, hover surbrillance discrète. */
    .coord{
        margin-top:36px;background:var(--blanc);padding:8px;
        border-radius:20px;font-family:var(--titre);
        display:flex;flex-direction:column;
        box-shadow:0 12px 32px -12px rgba(0,0,0,.15);
    }
    .coord-row{
        display:flex;align-items:center;gap:16px;
        padding:16px 18px;border-radius:14px;
        text-decoration:none;color:var(--noir);
        transition:background .18s;
    }
    .coord-row + .coord-row{border-top:1px solid #F0F0F0}
    .coord-row:hover{background:#FAFAFA}
    .coord-icon{
        width:44px;height:44px;border-radius:12px;
        display:flex;align-items:center;justify-content:center;
        flex-shrink:0;font-size:18px;
    }
    .coord-icon-tel{background:rgba(226,6,19,.10);color:var(--rouge)}
    .coord-icon-mail{background:rgba(58,168,53,.10);color:var(--vert)}
    .coord-icon-addr{background:rgba(63,127,192,.10);color:var(--bleu)}
    .coord-txt{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1}
    .coord-lbl{
        font-family:var(--titre);font-weight:700;
        font-size:10px;text-transform:uppercase;
        letter-spacing:.12em;color:#888;
    }
    .coord-val{
        font-family:var(--titre);font-weight:800;
        font-size:16px;color:var(--noir);line-height:1.3;
        overflow-wrap:anywhere;
    }
    .coord-row.coord-tel .coord-val{color:var(--rouge);font-size:18px}
    .coord-val-sub{
        font-family:var(--corps);font-weight:600;
        font-size:13.5px;color:#555;line-height:1.5;margin-top:2px;
    }

    /* formulaire */
    .form-card{background:#fff;border-radius:24px;padding:clamp(28px,4vw,44px);box-shadow:0 20px 50px -20px rgba(0,0,0,.15)}
    .form-card h2{font-family:var(--titre);font-weight:800;font-size:24px;margin-bottom:8px}
    .form-card .sub{font-size:14px;color:#666;margin-bottom:28px}
    .form-row{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
    @media(max-width:600px){.form-row{grid-template-columns:1fr}}
    .form-field{display:flex;flex-direction:column;gap:6px;margin-bottom:16px}
    .form-field label{font-family:var(--titre);font-weight:700;font-size:12.5px;text-transform:uppercase;letter-spacing:.06em;color:#333}
    .form-field label .req{color:var(--rouge)}
    .form-field input,.form-field select,.form-field textarea{
        border:1.5px solid var(--gris);border-radius:10px;padding:12px 14px;
        font-family:var(--corps);font-size:15px;background:#fff;color:var(--noir);
        transition:border-color .2s,box-shadow .2s
    }
    .form-field input:focus,.form-field select:focus,.form-field textarea:focus{
        outline:none;border-color:var(--rouge);box-shadow:0 0 0 3px rgba(226,6,19,.12)
    }
    .form-field textarea{min-height:120px;resize:vertical}
    .form-field.error input,.form-field.error select,.form-field.error textarea{border-color:var(--rouge);background:#fef2f2}
    .form-field .err-msg{color:var(--rouge);font-size:13px;font-family:var(--titre);font-weight:600}
    .honeypot{position:absolute;left:-9999px;visibility:hidden}
    .form-submit{margin-top:20px;display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .form-submit p{font-size:12.5px;color:#666;flex:1}

    .success-msg{background:#dcfce7;border:2px solid #22c55e;color:#166534;padding:24px;border-radius:16px;text-align:center;font-family:var(--titre)}
    .success-msg h3{font-size:24px;margin-bottom:8px;color:#15803d}
    .success-msg p{font-weight:600}

    .error-msg{background:#fee2e2;border:2px solid #ef4444;color:#991b1b;padding:20px;border-radius:12px;font-family:var(--titre);font-weight:600;margin-bottom:20px}
@endpush

@section('content')

<section class="contact-wrap">
    <div class="motif" aria-hidden="true"></div>
    <div class="contact-inner">

        {{-- Colonne gauche : intro + coordonnées --}}
        <div class="intro rev">
            <span class="sur">Parlons de votre projet</span>
            <h1 class="t1" style="margin-top:14px">Entrons en contact.</h1>
            <p>Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la <strong>journée ouvrée</strong> avec une proposition chiffrée.</p>
            <div class="coord">
                <a href="tel:+2250700780628" class="coord-row coord-tel">
                    <span class="coord-icon coord-icon-tel">📞</span>
                    <span class="coord-txt">
                        <span class="coord-lbl">Téléphone</span>
                        <span class="coord-val num">+225 07 00 78 06 28</span>
                    </span>
                </a>
                <a href="mailto:commercial@cible-ci.com" class="coord-row">
                    <span class="coord-icon coord-icon-mail">✉</span>
                    <span class="coord-txt">
                        <span class="coord-lbl">Email commercial</span>
                        <span class="coord-val">commercial@cible-ci.com</span>
                    </span>
                </a>
                <div class="coord-row" style="cursor:default">
                    <span class="coord-icon coord-icon-addr">📍</span>
                    <span class="coord-txt">
                        <span class="coord-lbl">Adresse</span>
                        <span class="coord-val">Rue des Ambassadeurs</span>
                        <span class="coord-val-sub">Riviera M'Badon · 10 BP 1029 Abidjan 10</span>
                    </span>
                </div>
            </div>
        </div>

        {{-- Colonne droite : formulaire --}}
        <div class="form-card rev">
            @if(session('devis_sent'))
                <div class="success-msg">
                    <h3>✓ Message reçu !</h3>
                    <p>Merci — notre équipe vous rappelle dans la journée ouvrée.</p>
                </div>
            @else
                <h2>Demander un devis</h2>
                <p class="sub">Toutes les demandes reçoivent une réponse chiffrée dans les 24h ouvrées.</p>

                @if(session('devis_error'))
                    <div class="error-msg">⚠ {{ session('devis_error') }}</div>
                @endif
                @if($errors->any())
                    <div class="error-msg">⚠ Merci de corriger les champs marqués en rouge ci-dessous.</div>
                @endif

                <form method="POST" action="{{ route('cible.devis.submit') }}" novalidate>
                    @csrf

                    {{-- Honeypot anti-bot --}}
                    <div class="honeypot" aria-hidden="true">
                        <label>Ne pas remplir <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="form-row">
                        <div class="form-field @error('nom') error @enderror">
                            <label>Votre nom <span class="req">*</span></label>
                            <input type="text" name="nom" value="{{ old('nom') }}" required maxlength="100" placeholder="Prénom Nom">
                            @error('nom') <span class="err-msg">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field @error('entreprise') error @enderror">
                            <label>Entreprise <span class="req">*</span></label>
                            <input type="text" name="entreprise" value="{{ old('entreprise') }}" required maxlength="150" placeholder="Votre société">
                            @error('entreprise') <span class="err-msg">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field @error('email') error @enderror">
                            <label>Email <span class="req">*</span></label>
                            <input type="email" name="email" value="{{ old('email') }}" required maxlength="150" placeholder="vous@entreprise.ci">
                            @error('email') <span class="err-msg">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field @error('tel') error @enderror">
                            <label>Téléphone <span class="req">*</span></label>
                            <input type="tel" name="tel" value="{{ old('tel') }}" required maxlength="30" placeholder="+225 …">
                            @error('tel') <span class="err-msg">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="form-field @error('poste') error @enderror">
                        <label>Votre poste</label>
                        <input type="text" name="poste" value="{{ old('poste') }}" maxlength="100" placeholder="Ex : Directeur Marketing">
                    </div>

                    <div class="form-row">
                        <div class="form-field @error('besoin') error @enderror">
                            <label>Type de besoin <span class="req">*</span></label>
                            <select name="besoin" required>
                                <option value="">— Choisir —</option>
                                <option value="affichage" @selected(old('besoin')==='affichage')>Affichage grand format</option>
                                <option value="mobile" @selected(old('besoin')==='mobile')>Publicité mobile (camion, taxis…)</option>
                                <option value="360" @selected(old('besoin')==='360')>Campagne 360° (multi-canal)</option>
                                <option value="autre" @selected(old('besoin')==='autre')>Autre / à définir</option>
                            </select>
                            @error('besoin') <span class="err-msg">{{ $message }}</span> @enderror
                        </div>
                        <div class="form-field @error('zone') error @enderror">
                            <label>Zone visée</label>
                            <select name="zone">
                                <option value="">— À préciser —</option>
                                <option value="abidjan" @selected(old('zone')==='abidjan')>Abidjan</option>
                                <option value="interieur" @selected(old('zone')==='interieur')>Intérieur du pays</option>
                                <option value="national" @selected(old('zone')==='national')>National (Abidjan + intérieur)</option>
                                <option value="autre" @selected(old('zone')==='autre')>Autre / à définir</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-field @error('budget') error @enderror">
                            <label>Budget indicatif</label>
                            <select name="budget">
                                <option value="">— Fourchette —</option>
                                <option value="moins1M" @selected(old('budget')==='moins1M')>Moins de 1 M FCFA</option>
                                <option value="1a5M" @selected(old('budget')==='1a5M')>1 à 5 M FCFA</option>
                                <option value="5a20M" @selected(old('budget')==='5a20M')>5 à 20 M FCFA</option>
                                <option value="plus20M" @selected(old('budget')==='plus20M')>Plus de 20 M FCFA</option>
                                <option value="pas-sur" @selected(old('budget')==='pas-sur')>Pas encore défini</option>
                            </select>
                        </div>
                        <div class="form-field @error('periode') error @enderror">
                            <label>Période souhaitée</label>
                            <input type="text" name="periode" value="{{ old('periode') }}" maxlength="100" placeholder="Ex : Novembre-Décembre 2026">
                        </div>
                    </div>

                    <div class="form-field @error('message') error @enderror">
                        <label>Décrivez votre projet</label>
                        <textarea name="message" maxlength="2000" placeholder="En quelques lignes : objectif, cible, contexte, contraintes…">{{ old('message') }}</textarea>
                        @error('message') <span class="err-msg">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-submit">
                        <button type="submit" class="bouton b-rouge">Envoyer ma demande</button>
                        <p>Vos données ne sont utilisées que pour vous répondre. Aucun démarchage tiers.</p>
                    </div>
                </form>
            @endif
        </div>
    </div>
</section>

@endsection

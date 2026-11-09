<x-admin-layout title="Interlocuteurs des campagnes">
    {{-- 2026-11-09 : l'interlocuteur actuel de chaque campagne en cours, à
         choisir, créer ou modifier. Rien n'est enregistré sans un clic sur
         Appliquer. Règles : CampagnesInterlocuteursController. --}}
    @php
        $champ = 'width:100%;box-sizing:border-box;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:7px 9px;font-size:13px;color:var(--text)';
        $filtre = $champ . ';width:auto';
    @endphp

    {{-- .btn impose un display : sans cette règle, un bouton « hidden » resterait visible. --}}
    <style>
        .ecran-interlocuteurs [hidden] { display: none !important; }
        .ecran-interlocuteurs button:disabled { opacity: .45; cursor: not-allowed; }
    </style>

    <div class="card ecran-interlocuteurs">
        <div class="card-header" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <span class="card-title">
                <span id="nb-restantes">{{ $aRenseigner }}</span> à renseigner sur {{ $lignes->count() }} campagne(s)
                <span title="L'interlocuteur est la personne qui suit la campagne chez le client et peut répondre sur son renouvellement. Elle reçoit les mails de suivi. Choisissez-la, ou créez-la avec + Nouveau, puis cliquez sur Appliquer."
                      style="cursor:help;color:var(--text3);font-weight:400">ⓘ</span>
            </span>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <select id="filtre-situation" aria-label="Filtrer par situation" style="{{ $filtre }}">
                    <option value="">Toutes</option>
                    <option value="a-renseigner">À renseigner</option>
                    <option value="renseignee">Déjà renseignées</option>
                </select>
                @if($commerciaux->count() > 1)
                    <select id="filtre-commercial" aria-label="Filtrer par commercial" style="{{ $filtre }}">
                        <option value="">Tous les commerciaux</option>
                        @foreach($commerciaux as $nom)
                            <option value="{{ $nom }}">{{ $nom }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        </div>

        @if($lignes->isEmpty())
            <p style="padding:28px;text-align:center;color:var(--text3)">Aucune campagne en cours.</p>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Échéance</th>
                            <th>Client et campagne</th>
                            @if($commerciaux->isNotEmpty())<th>Commercial</th>@endif
                            <th style="min-width:340px">Interlocuteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lignes as $l)
                            @php($actuel = $l['interlocuteur'])
                            <tr data-ligne data-client="{{ $l['client_id'] }}" data-commercial="{{ $l['commercial'] }}"
                                data-situation="{{ $actuel ? 'renseignee' : 'a-renseigner' }}"
                                data-actuel="{{ $actuel['id'] ?? '' }}"
                                data-url="{{ route('admin.campaigns.interlocuteur', $l['id']) }}"
                                data-url-ajout="{{ route('admin.clients.contacts.rapide', $l['client_id']) }}">
                                <td style="white-space:nowrap;vertical-align:top">
                                    {{ $l['echeance'] }}
                                    @if($l['reconduction'])
                                        <div style="font-size:11px;color:var(--text3)">Reconduction</div>
                                    @endif
                                </td>
                                <td style="vertical-align:top">
                                    <strong>{{ $l['client'] }}</strong>
                                    <div style="font-size:12px;color:var(--text3)">{{ $l['campagne'] }}</div>
                                </td>
                                @if($commerciaux->isNotEmpty())
                                    <td style="vertical-align:top">{{ $l['commercial'] }}</td>
                                @endif
                                <td style="vertical-align:top">
                                    {{-- Interlocuteur actuel --}}
                                    <div data-lecture @if(!$actuel) hidden @endif style="display:flex;gap:10px;justify-content:space-between;align-items:flex-start">
                                        <div style="min-width:0">
                                            <strong data-nom>{{ $actuel['nom'] ?? '' }}</strong>
                                            <div data-detail style="font-size:12px;color:var(--text3)">{{ $actuel['detail'] ?? '' }}</div>
                                            <div data-exclu @if(empty($actuel['exclu'])) hidden @endif style="font-size:12px;color:var(--red,#ef4444)">Cette adresse ne reçoit plus nos mails : à changer</div>
                                        </div>
                                        <button type="button" data-modifier class="btn btn-ghost btn-sm">Modifier</button>
                                    </div>

                                    {{-- Choisir, créer ou modifier --}}
                                    <div data-edition @if($actuel) hidden @endif>
                                        <div data-aucun @if($actuel) hidden @endif style="font-size:12px;color:var(--text3);margin-bottom:5px">Aucun interlocuteur pour l'instant</div>
                                        <div style="display:flex;gap:8px;align-items:center">
                                            <select data-choix aria-label="Interlocuteur de {{ $l['campagne'] }}" style="{{ $champ }}">
                                                <option value="">{{ $l['contacts']->isEmpty() ? 'Aucun contact : cliquez sur + Nouveau' : "— Choisir l'interlocuteur —" }}</option>
                                                @foreach($l['contacts'] as $c)
                                                    <option value="{{ $c['id'] }}">{{ $c['libelle'] }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" data-ouvrir class="btn btn-ghost btn-sm" style="white-space:nowrap">+ Nouveau</button>
                                        </div>
                                        <div style="display:flex;gap:8px;margin-top:8px">
                                            <button type="button" data-appliquer class="btn btn-primary btn-sm" disabled>Appliquer</button>
                                            <button type="button" data-fermer class="btn btn-ghost btn-sm" @if(!$actuel) hidden @endif>Annuler</button>
                                        </div>

                                        <div data-formulaire hidden style="margin-top:8px;padding:10px;border:1px solid var(--border);border-radius:10px">
                                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">
                                                <input type="text" data-champ="name" placeholder="Nom et prénom *" maxlength="120" style="{{ $champ }}">
                                                <input type="text" data-champ="position" placeholder="Fonction" maxlength="100" style="{{ $champ }}">
                                                <input type="email" data-champ="email" placeholder="Mail direct *" maxlength="150" style="{{ $champ }}">
                                                <input type="tel" data-champ="phone" placeholder="Téléphone" maxlength="30" style="{{ $champ }}">
                                            </div>
                                            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
                                                <button type="button" data-annuler class="btn btn-ghost btn-sm">Annuler</button>
                                                <button type="button" data-valider class="btn btn-primary btn-sm">Créer et appliquer</button>
                                            </div>
                                        </div>
                                    </div>

                                    <div data-message aria-live="polite" style="font-size:12px;margin-top:5px"></div>
                                    <button type="button" data-autres hidden class="btn btn-ghost btn-sm" style="margin-top:5px"></button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
    (function () {
        var jeton = @json(csrf_token());
        var lignes = Array.prototype.slice.call(document.querySelectorAll('[data-ligne]'));
        var compteur = document.getElementById('nb-restantes');
        var ROUGE = 'var(--red,#ef4444)';

        function envoyer(url, corps) {
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': jeton },
                body: JSON.stringify(corps),
            }).then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (d) {
                    if (r.ok) return d;
                    var erreurs = d.errors ? Object.values(d.errors) : [];
                    throw new Error((erreurs[0] && erreurs[0][0]) || d.message || 'Enregistrement impossible, réessayez.');
                });
            });
        }

        function el(ligne, attribut) { return ligne.querySelector('[' + attribut + ']'); }

        function message(ligne, texte, couleur) {
            var m = el(ligne, 'data-message');
            m.textContent = texte;
            m.style.color = couleur || 'var(--text3)';
        }

        function mettreAJourCompteur() {
            compteur.textContent = lignes.filter(function (l) { return l.dataset.situation === 'a-renseigner'; }).length;
        }

        function majBouton(ligne) {
            var valeur = el(ligne, 'data-choix').value;
            el(ligne, 'data-appliquer').disabled = !valeur || valeur === ligne.dataset.actuel;
        }

        function ouvrirEdition(ligne) {
            el(ligne, 'data-lecture').hidden = true;
            el(ligne, 'data-edition').hidden = false;
            el(ligne, 'data-aucun').hidden = !!ligne.dataset.actuel;
            el(ligne, 'data-fermer').hidden = !ligne.dataset.actuel;
            el(ligne, 'data-autres').hidden = true;
            el(ligne, 'data-choix').value = ligne.dataset.actuel || '';
            message(ligne, '');
            majBouton(ligne);
        }

        function fermerEdition(ligne) {
            el(ligne, 'data-formulaire').hidden = true;
            el(ligne, 'data-edition').hidden = true;
            el(ligne, 'data-lecture').hidden = false;
        }

        function ajouterOption(clientId, id, libelle) {
            lignes.filter(function (l) { return l.dataset.client === clientId; }).forEach(function (l) {
                var liste = el(l, 'data-choix');
                if (liste.querySelector('option[value="' + id + '"]')) return;
                var o = document.createElement('option');
                o.value = id;
                o.textContent = libelle;
                liste.appendChild(o);
            });
        }

        function proposerAutres(ligne, id) {
            var bouton = el(ligne, 'data-autres');
            var autres = lignes.filter(function (l) {
                return l !== ligne && l.dataset.client === ligne.dataset.client && l.dataset.situation === 'a-renseigner';
            });
            bouton.hidden = autres.length === 0;
            if (!autres.length) return;
            bouton.textContent = 'Appliquer aussi à ' + (autres.length === 1 ? 'son autre campagne' : 'ses ' + autres.length + ' autres campagnes');
            bouton.onclick = function () {
                bouton.hidden = true;
                autres.forEach(function (l) { appliquer(l, id); });
            };
        }

        function appliquer(ligne, id) {
            message(ligne, 'Enregistrement…');
            el(ligne, 'data-appliquer').disabled = true;
            return envoyer(ligne.dataset.url, { client_contact_id: id }).then(function (d) {
                ajouterOption(ligne.dataset.client, d.id, d.libelle);
                ligne.dataset.actuel = String(d.id);
                ligne.dataset.situation = 'renseignee';
                el(ligne, 'data-nom').textContent = d.nom;
                el(ligne, 'data-detail').textContent = d.detail;
                el(ligne, 'data-exclu').hidden = true;
                fermerEdition(ligne);
                message(ligne, '✓ Enregistré', 'var(--green,#16a34a)');
                mettreAJourCompteur();
                proposerAutres(ligne, d.id);
            }).catch(function (e) {
                message(ligne, e.message, ROUGE);
                majBouton(ligne);
            });
        }

        lignes.forEach(function (ligne) {
            var choix = el(ligne, 'data-choix');
            var formulaire = el(ligne, 'data-formulaire');
            var champ = function (nom) { return formulaire.querySelector('[data-champ="' + nom + '"]'); };

            // Choisir dans la liste n'enregistre rien : seul Appliquer enregistre.
            choix.addEventListener('change', function () { majBouton(ligne); });
            el(ligne, 'data-appliquer').addEventListener('click', function () { appliquer(ligne, choix.value); });
            el(ligne, 'data-modifier').addEventListener('click', function () { ouvrirEdition(ligne); });
            el(ligne, 'data-fermer').addEventListener('click', function () { fermerEdition(ligne); message(ligne, ''); });
            el(ligne, 'data-ouvrir').addEventListener('click', function () {
                formulaire.hidden = !formulaire.hidden;
                if (!formulaire.hidden) champ('name').focus();
            });
            el(ligne, 'data-annuler').addEventListener('click', function () { formulaire.hidden = true; });
            el(ligne, 'data-valider').addEventListener('click', function () {
                var valeurs = { name: champ('name').value.trim(), position: champ('position').value.trim(), email: champ('email').value.trim(), phone: champ('phone').value.trim() };
                if (!valeurs.name || !valeurs.email) { message(ligne, 'Le nom et le mail sont obligatoires.', ROUGE); return; }

                message(ligne, 'Ajout…');
                envoyer(ligne.dataset.urlAjout, valeurs).then(function (d) {
                    ['name', 'position', 'email', 'phone'].forEach(function (n) { champ(n).value = ''; });
                    return appliquer(ligne, d.contact.id);
                }).catch(function (e) { message(ligne, e.message, ROUGE); });
            });
        });

        var filtreSituation = document.getElementById('filtre-situation');
        var filtreCommercial = document.getElementById('filtre-commercial');
        function filtrer() {
            lignes.forEach(function (l) {
                var situation = filtreSituation.value;
                var commercial = filtreCommercial ? filtreCommercial.value : '';
                l.hidden = (!!situation && l.dataset.situation !== situation) || (!!commercial && l.dataset.commercial !== commercial);
            });
        }
        filtreSituation.addEventListener('change', filtrer);
        if (filtreCommercial) filtreCommercial.addEventListener('change', filtrer);
    })();
    </script>
</x-admin-layout>

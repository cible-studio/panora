<x-admin-layout title="Interlocuteurs à renseigner">
    {{-- 2026-11-09 : chaque commercial choisit ou saisit l'interlocuteur de ses
         campagnes ici, sans ouvrir chaque campagne. Chaque choix s'enregistre
         aussitôt. Règles : CampagnesInterlocuteursController. --}}
    @php
        $champ = 'width:100%;box-sizing:border-box;background:var(--surface2);border:1px solid var(--border);border-radius:8px;padding:7px 9px;font-size:13px;color:var(--text)';
    @endphp

    <div class="card">
        <div class="card-header" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between">
            <span class="card-title">
                <span id="nb-restantes">{{ $lignes->count() }}</span> campagne(s) à renseigner
                <span title="Choisissez la personne qui suit la campagne chez le client et peut répondre sur son renouvellement. Elle recevra les mails de suivi. Si elle n'est pas dans la liste, utilisez + Nouveau."
                      style="cursor:help;color:var(--text3);font-weight:400">ⓘ</span>
            </span>
            @if($commerciaux->count() > 1)
                <select id="filtre-commercial" aria-label="Filtrer par commercial" style="{{ $champ }};width:auto">
                    <option value="">Tous les commerciaux</option>
                    @foreach($commerciaux as $nom)
                        <option value="{{ $nom }}">{{ $nom }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        @if($lignes->isEmpty())
            <p style="padding:28px;text-align:center;color:var(--text3)">Toutes les campagnes ont un interlocuteur.</p>
        @else
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Échéance</th>
                            <th>Client et campagne</th>
                            @if($commerciaux->isNotEmpty())<th>Commercial</th>@endif
                            <th style="min-width:320px">Interlocuteur</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lignes as $l)
                            <tr data-ligne data-client="{{ $l['client_id'] }}" data-commercial="{{ $l['commercial'] }}"
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
                                    <div style="display:flex;gap:8px;align-items:center">
                                        <select data-choix aria-label="Interlocuteur de {{ $l['campagne'] }}" style="{{ $champ }}">
                                            <option value="">{{ $l['contacts']->isEmpty() ? 'Aucun contact : cliquez sur + Nouveau' : '— Choisir —' }}</option>
                                            @foreach($l['contacts'] as $c)
                                                <option value="{{ $c['id'] }}">{{ $c['libelle'] }}</option>
                                            @endforeach
                                        </select>
                                        <button type="button" data-ouvrir class="btn btn-ghost btn-sm" style="white-space:nowrap">+ Nouveau</button>
                                    </div>
                                    <div data-etat aria-live="polite" style="font-size:12px;margin-top:5px"></div>
                                    <button type="button" data-autres hidden class="btn btn-ghost btn-sm" style="margin-top:5px"></button>

                                    <div data-formulaire hidden style="margin-top:8px;padding:10px;border:1px solid var(--border);border-radius:10px">
                                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px">
                                            <input type="text" data-champ="name" placeholder="Nom et prénom *" maxlength="120" style="{{ $champ }}">
                                            <input type="text" data-champ="position" placeholder="Fonction" maxlength="100" style="{{ $champ }}">
                                            <input type="email" data-champ="email" placeholder="Mail direct *" maxlength="150" style="{{ $champ }}">
                                            <input type="tel" data-champ="phone" placeholder="Téléphone" maxlength="30" style="{{ $champ }}">
                                        </div>
                                        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:8px">
                                            <button type="button" data-annuler class="btn btn-ghost btn-sm">Annuler</button>
                                            <button type="button" data-valider class="btn btn-primary btn-sm">Ajouter et choisir</button>
                                        </div>
                                    </div>
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

        function etat(ligne, texte, couleur) {
            var e = ligne.querySelector('[data-etat]');
            e.textContent = texte;
            e.style.color = couleur || 'var(--text3)';
        }

        function mettreAJourCompteur() {
            compteur.textContent = lignes.filter(function (l) { return !l.dataset.fait; }).length;
        }

        function autresDuClient(ligne) {
            return lignes.filter(function (l) {
                return l !== ligne && l.dataset.client === ligne.dataset.client && !l.dataset.fait;
            });
        }

        function proposerAutres(ligne, id) {
            var bouton = ligne.querySelector('[data-autres]');
            var autres = autresDuClient(ligne);
            bouton.hidden = autres.length === 0;
            if (!autres.length) return;
            bouton.textContent = 'Même personne pour ' + (autres.length === 1 ? 'son autre campagne' : 'ses ' + autres.length + ' autres campagnes');
            bouton.onclick = function () {
                bouton.hidden = true;
                autres.forEach(function (l) {
                    l.querySelector('[data-choix]').value = id;
                    enregistrer(l, id);
                });
            };
        }

        function enregistrer(ligne, id) {
            etat(ligne, 'Enregistrement…');
            ligne.querySelector('[data-autres]').hidden = true;
            return envoyer(ligne.dataset.url, { client_contact_id: id }).then(function () {
                ligne.dataset.fait = '1';
                etat(ligne, '✓ Enregistré', 'var(--green,#16a34a)');
                mettreAJourCompteur();
                proposerAutres(ligne, id);
            }).catch(function (e) {
                delete ligne.dataset.fait;
                etat(ligne, e.message, 'var(--red,#ef4444)');
                mettreAJourCompteur();
            });
        }

        function ajouterOption(clientId, contact, libelle) {
            lignes.filter(function (l) { return l.dataset.client === clientId; }).forEach(function (l) {
                var o = document.createElement('option');
                o.value = contact.id;
                o.textContent = libelle;
                l.querySelector('[data-choix]').appendChild(o);
            });
        }

        lignes.forEach(function (ligne) {
            var choix = ligne.querySelector('[data-choix]');
            var formulaire = ligne.querySelector('[data-formulaire]');
            var champ = function (nom) { return formulaire.querySelector('[data-champ="' + nom + '"]'); };

            choix.addEventListener('change', function () {
                if (choix.value) enregistrer(ligne, choix.value);
            });
            ligne.querySelector('[data-ouvrir]').addEventListener('click', function () {
                formulaire.hidden = !formulaire.hidden;
                if (!formulaire.hidden) champ('name').focus();
            });
            ligne.querySelector('[data-annuler]').addEventListener('click', function () { formulaire.hidden = true; });
            ligne.querySelector('[data-valider]').addEventListener('click', function () {
                var valeurs = { name: champ('name').value.trim(), position: champ('position').value.trim(), email: champ('email').value.trim(), phone: champ('phone').value.trim() };
                if (!valeurs.name || !valeurs.email) { etat(ligne, 'Le nom et le mail sont obligatoires.', 'var(--red,#ef4444)'); return; }

                etat(ligne, 'Ajout…');
                envoyer(ligne.dataset.urlAjout, valeurs).then(function (d) {
                    var libelle = d.contact.name + (valeurs.position ? ' — ' + valeurs.position : '') + ' (' + (d.contact.email || valeurs.email) + ')';
                    if (!choix.querySelector('option[value="' + d.contact.id + '"]')) ajouterOption(ligne.dataset.client, d.contact, libelle);
                    choix.value = d.contact.id;
                    formulaire.hidden = true;
                    ['name', 'position', 'email', 'phone'].forEach(function (n) { champ(n).value = ''; });
                    return enregistrer(ligne, d.contact.id);
                }).catch(function (e) { etat(ligne, e.message, 'var(--red,#ef4444)'); });
            });
        });

        var filtre = document.getElementById('filtre-commercial');
        if (filtre) {
            filtre.addEventListener('change', function () {
                lignes.forEach(function (l) { l.hidden = !!filtre.value && l.dataset.commercial !== filtre.value; });
            });
        }
    })();
    </script>
</x-admin-layout>

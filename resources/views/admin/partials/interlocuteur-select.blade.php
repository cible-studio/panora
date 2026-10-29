{{--
    Champ « Interlocuteur » d'un dossier (campagne, réservation, devis — 2026-10-29).
    Un client a plusieurs interlocuteurs ; le dossier précise lequel le suit.
    Obligatoire si le client en a (contrôlé par le serveur :
    App\Rules\InterlocuteurDuClient). La liste suit le champ client_id du
    même formulaire : elle est rechargée depuis Panora (route
    admin.clients.contacts.index) à chaque changement de client.

    « + Nouvel interlocuteur » : mini-formulaire sur place, créé par
    admin.clients.contacts.rapide (règles de Client::ajouterInterlocuteur),
    puis choisi d'office. Un client avec un seul interlocuteur le voit
    présélectionné.

    Paramètres :
      $clientId      client choisi au chargement (ou null)
      $selection     interlocuteur enregistré (ou null) ; old() prime
      $classeLabel   classe du libellé, $classeSelect classe de la liste,
      $styleSelect   style en ligne de la liste (optionnel),
      $classeErreur  classe du message d'erreur, $classeBloc classe du bloc
--}}
@php
    $interlocuteurs = $clientId
        ? \App\Models\ClientContact::where('client_id', $clientId)->orderByDesc('is_primary')->orderBy('name')->get()
        : collect();
    $choisi = old('client_contact_id', $selection ?? null);
    if (!$choisi && $interlocuteurs->count() === 1) {
        $choisi = $interlocuteurs->first()->id; // un seul interlocuteur : présélectionné
    }
    $peutAjouter = in_array(auth()->user()?->role?->value, ['admin', 'commercial', 'mediaplanner'], true);
    $champMini = 'width:100%;box-sizing:border-box;background:var(--surface2);border:1px solid var(--border2,var(--border));border-radius:8px;padding:8px 10px;color:var(--text);font-size:13px;';
@endphp
<div class="{{ $classeBloc ?? '' }}" data-interlocuteur
     data-url="{{ route('admin.clients.contacts.index', ['client' => '__ID__']) }}"
     data-url-ajout="{{ route('admin.clients.contacts.rapide', ['client' => '__ID__']) }}"
     data-peut-ajouter="{{ $peutAjouter ? 1 : 0 }}">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
        <label class="{{ $classeLabel ?? '' }}">
            Interlocuteur <span data-etoile style="color:var(--red,#ef4444)" @if($interlocuteurs->isEmpty()) hidden @endif>*</span>
            <span title="Le contact du client qui suit ce dossier : il reçoit les mails de suivi, et les documents en plus de l'adresse de la fiche."
                  style="cursor:help;color:var(--text3);font-weight:400">ⓘ</span>
        </label>
        @if($peutAjouter)
            <button type="button" data-ajout-ouvrir @if(!$clientId) hidden @endif
                    style="background:none;border:0;padding:0;cursor:pointer;font-size:11.5px;font-weight:700;color:var(--accent)">+ Nouvel interlocuteur</button>
        @endif
    </div>
    <select name="client_contact_id" class="{{ $classeSelect ?? '' }} {{ $errors->has('client_contact_id') ? 'is-error is-invalid error' : '' }}"
            @if(!empty($styleSelect)) style="{{ $styleSelect }}" @endif>
        @if(!$clientId)
            <option value="">— Choisir d'abord le client —</option>
        @elseif($interlocuteurs->isEmpty())
            <option value="">Aucun interlocuteur enregistré pour ce client</option>
        @else
            <option value="">— Choisir l'interlocuteur —</option>
            @foreach($interlocuteurs as $c)
                <option value="{{ $c->id }}" @selected((string) $choisi === (string) $c->id)>
                    {{ $c->name }}{{ $c->position ? ' — ' . $c->position : '' }} ({{ $c->email ?: 'sans adresse' }})
                </option>
            @endforeach
        @endif
    </select>
    @error('client_contact_id')<p class="{{ $classeErreur ?? '' }}" style="color:var(--red,#ef4444)">{{ $message }}</p>@enderror

    @if($peutAjouter)
        {{-- Pas de <form> : ce bloc vit souvent dans le formulaire du dossier. --}}
        <div data-ajout hidden style="margin-top:8px;padding:12px;border:1px solid var(--border);border-radius:10px;background:var(--surface)">
            <div style="font-size:12px;font-weight:700;color:var(--text);margin-bottom:8px">Nouvel interlocuteur</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                <input type="text" data-champ="name" placeholder="Nom et prénom *" maxlength="120" style="{{ $champMini }}">
                <input type="text" data-champ="position" placeholder="Fonction (ex. Directrice marketing)" maxlength="100" style="{{ $champMini }}">
                <input type="email" data-champ="email" placeholder="E-mail" maxlength="150" style="{{ $champMini }}">
                <input type="tel" data-champ="phone" placeholder="Téléphone" maxlength="30" style="{{ $champMini }}">
            </div>
            <div data-ajout-erreur hidden style="margin-top:8px;font-size:12px;color:#ef4444"></div>
            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:10px">
                <button type="button" data-ajout-annuler class="btn btn-ghost btn-sm">Annuler</button>
                <button type="button" data-ajout-valider class="btn btn-primary btn-sm">Ajouter et choisir</button>
            </div>
        </div>
    @endif
</div>

@once
<script>
(function () {
    // Recharge la liste quand le client change : sélection native, Alpine,
    // ou Select2 (y compris .trigger('change.select2') posé par le code).
    function brancher(bloc) {
        var formulaire = bloc.closest('form') || document;
        var client = formulaire.querySelector('[name="client_id"]');
        var liste = bloc.querySelector('select[name="client_contact_id"]');
        var etoile = bloc.querySelector('[data-etoile]');
        var ouvrirAjout = bloc.querySelector('[data-ajout-ouvrir]');
        var ajout = bloc.querySelector('[data-ajout]');
        if (!client || !liste) return;
        var dernier = client.value;

        function option(valeur, texte) {
            var o = document.createElement('option');
            o.value = valeur;
            o.textContent = texte;
            liste.appendChild(o);
        }

        function remplir(d, aChoisir) {
            liste.innerHTML = '';
            if (!d.contacts.length) { option('', 'Aucun interlocuteur enregistré pour ce client'); etoile.hidden = true; return; }
            option('', '— Choisir l\'interlocuteur —');
            d.contacts.forEach(function (c) { option(c.id, c.libelle + (c.prospect ? ' · prospect' : '')); });
            etoile.hidden = !d.obligatoire;
            // Le nouvel interlocuteur, sinon le seul interlocuteur du client.
            if (aChoisir) liste.value = String(aChoisir);
            else if (d.contacts.length === 1) liste.value = String(d.contacts[0].id);
        }

        function demander(aChoisir) {
            var demande = client.value;
            return fetch(bloc.dataset.url.replace('__ID__', encodeURIComponent(demande)), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (d) {
                    if (client.value !== demande) return; // réponse d'un client déjà quitté
                    remplir(d, aChoisir);
                })
                .catch(function () { liste.innerHTML = ''; option('', 'Liste indisponible : rechargez la page'); });
        }

        function charger() {
            if (client.value === dernier) return;
            dernier = client.value;
            liste.innerHTML = '';
            etoile.hidden = true;
            if (ouvrirAjout) ouvrirAjout.hidden = !client.value;
            if (ajout) ajout.hidden = true;
            if (!client.value) { option('', '— Choisir d\'abord le client —'); return; }
            option('', 'Chargement…');
            demander(null);
        }

        // ── Ajout rapide ──────────────────────────────────────────
        if (ouvrirAjout && ajout) {
            var champ = function (n) { return ajout.querySelector('[data-champ="' + n + '"]'); };
            var erreur = ajout.querySelector('[data-ajout-erreur]');
            var valider = ajout.querySelector('[data-ajout-valider]');
            var montrerErreur = function (texte) { erreur.textContent = texte; erreur.hidden = false; };

            ouvrirAjout.addEventListener('click', function () {
                ajout.hidden = false;
                erreur.hidden = true;
                ['name', 'position', 'email', 'phone'].forEach(function (n) { champ(n).value = ''; });
                champ('name').focus();
            });
            ajout.querySelector('[data-ajout-annuler]').addEventListener('click', function () { ajout.hidden = true; });
            // Entrée dans le mini-formulaire : ajoute, sans envoyer le formulaire du dossier.
            ajout.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); valider.click(); }
            });
            valider.addEventListener('click', function () {
                if (!champ('name').value.trim()) { montrerErreur("Le nom de l'interlocuteur est obligatoire."); champ('name').focus(); return; }
                var jeton = document.querySelector('meta[name="csrf-token"]');
                valider.disabled = true;
                fetch(bloc.dataset.urlAjout.replace('__ID__', encodeURIComponent(client.value)), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': jeton ? jeton.content : '' },
                    body: JSON.stringify({
                        name: champ('name').value.trim(), position: champ('position').value.trim(),
                        email: champ('email').value.trim(), phone: champ('phone').value.trim(),
                    }),
                })
                    .then(function (r) {
                        return r.json().catch(function () { return {}; }).then(function (d) { return { statut: r.status, d: d }; });
                    })
                    .then(function (res) {
                        valider.disabled = false;
                        if (res.statut === 403) { montrerErreur("Vous n'avez pas le droit d'ajouter un interlocuteur."); return; }
                        if (res.statut === 422) {
                            var premier = res.d.errors ? Object.values(res.d.errors)[0] : null;
                            montrerErreur((premier && premier[0]) || res.d.message || 'Vérifiez les champs.');
                            return;
                        }
                        if (!res.d.ok) { montrerErreur("L'interlocuteur n'a pas pu être ajouté. Réessayez."); return; }
                        ajout.hidden = true;
                        demander(res.d.contact.id);
                    })
                    .catch(function () { valider.disabled = false; montrerErreur("L'interlocuteur n'a pas pu être ajouté. Réessayez."); });
            });
        }

        // Le client peut changer par la liste native, Alpine, Select2 (chargé
        // parfois après ce script) ou du code : on écoute le natif et on
        // vérifie la valeur 3 fois par seconde. charger() ignore une valeur
        // inchangée : aucun appel en trop.
        client.addEventListener('change', charger);
        client.addEventListener('input', charger);
        setInterval(charger, 300);
    }

    function demarrer() { document.querySelectorAll('[data-interlocuteur]').forEach(brancher); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', demarrer); else demarrer();
})();
</script>
@endonce

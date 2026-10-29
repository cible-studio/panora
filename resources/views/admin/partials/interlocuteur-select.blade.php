{{--
    Champ « Interlocuteur » d'un dossier (campagne, réservation, devis — 2026-10-29).
    Un client a plusieurs interlocuteurs ; le dossier précise lequel le suit.
    Obligatoire si le client en a (contrôlé par le serveur :
    App\Rules\InterlocuteurDuClient). La liste suit le champ client_id du
    même formulaire : elle est rechargée depuis Panora (route
    admin.clients.contacts.index) à chaque changement de client.

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
@endphp
<div class="{{ $classeBloc ?? '' }}" data-interlocuteur
     data-url="{{ route('admin.clients.contacts.index', ['client' => '__ID__']) }}">
    <label class="{{ $classeLabel ?? '' }}">
        Interlocuteur <span data-etoile style="color:var(--red,#ef4444)" @if($interlocuteurs->isEmpty()) hidden @endif>*</span>
        <span title="Le contact du client qui suit ce dossier : il reçoit les mails de suivi, et les documents en plus de l'adresse de la fiche."
              style="cursor:help;color:var(--text3);font-weight:400">ⓘ</span>
    </label>
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
        if (!client || !liste) return;
        var dernier = client.value;

        function option(valeur, texte) {
            var o = document.createElement('option');
            o.value = valeur;
            o.textContent = texte;
            liste.appendChild(o);
        }

        function charger() {
            if (client.value === dernier) return;
            dernier = client.value;
            liste.innerHTML = '';
            etoile.hidden = true;
            if (!client.value) { option('', '— Choisir d\'abord le client —'); return; }
            option('', 'Chargement…');
            var demande = client.value;
            fetch(bloc.dataset.url.replace('__ID__', encodeURIComponent(demande)), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (d) {
                    if (client.value !== demande) return; // réponse d'un client déjà quitté
                    liste.innerHTML = '';
                    if (!d.contacts.length) { option('', 'Aucun interlocuteur enregistré pour ce client'); return; }
                    option('', '— Choisir l\'interlocuteur —');
                    d.contacts.forEach(function (c) { option(c.id, c.libelle + (c.prospect ? ' · prospect' : '')); });
                    etoile.hidden = !d.obligatoire;
                })
                .catch(function () { liste.innerHTML = ''; option('', 'Liste indisponible : rechargez la page'); });
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

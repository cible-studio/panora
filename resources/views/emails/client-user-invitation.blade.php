@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $roleLabel = $user->role === 'owner'
        ? 'Propriétaire — gère l\'équipe et accepte les propositions'
        : 'Collaborateur — accès en lecture seule (campagnes, propositions, piges, factures)';
    $preheader = "Vos identifiants pour accéder à votre espace client " . $client->name . " sur PANORA.";
@endphp

<x-mail.layout title="Accès à l'espace client" :preheader="$preheader">

    <span class="pill pill-success">🔑 Nouveau compte d'accès</span>

    <h1>Bonjour {{ $user->name }},</h1>

    <p>
        Le propriétaire du compte <strong>{{ $client->name }}</strong> vient de vous
        créer un accès à l'espace client PANORA, géré par la régie
        <strong>{{ $operator }}</strong>.
    </p>

    <p>
        Vous pouvez désormais consulter en temps réel vos campagnes en cours,
        propositions commerciales, piges photo et factures.
    </p>

    <h2>Vos identifiants</h2>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Identifiant (email)</div>
            <div class="val"><code>{{ $user->email }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Mot de passe</div>
            <div class="val"><span class="code-strong">{{ $plainPassword }}</span></div>
        </div>
        <div class="info-row">
            <div class="lbl">Rôle</div>
            <div class="val">{{ $roleLabel }}</div>
        </div>
    </div>

    <div class="alert alert-warning">
        🔒 Pour votre sécurité, ce mot de passe est <strong>provisoire</strong>.
        Pensez à le modifier dès votre première connexion (menu « Sécurité »
        dans l'espace client).
    </div>

    <div class="cta-wrap">
        <a href="{{ $loginUrl }}" class="cta">Se connecter à mon espace</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
            <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
        </div>
    </div>

    <p style="margin-top:28px;color:#6b7280;font-size:13px;">
        Si vous n'attendiez pas cet email, vous pouvez l'ignorer ou prévenir
        le propriétaire du compte <strong>{{ $client->name }}</strong>.
    </p>

    <x-slot:footerNote>
        Email automatique — pour toute question, contactez votre propriétaire de compte ou la régie {{ $operator }}.
    </x-slot:footerNote>

</x-mail.layout>

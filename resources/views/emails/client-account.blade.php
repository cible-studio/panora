@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title = $isReset ? 'Nouveau mot de passe' : 'Invitation Panora';
    $preheader = $isReset
        ? "Votre mot de passe espace client a été réinitialisé."
        : "{$operator} vous invite à rejoindre Panora, votre espace OOH dédié.";
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $isReset ? 'pill pill-warning' : 'pill pill-success' }}">
        {{ $isReset ? '🔑 Réinitialisation' : '✉️ Invitation' }}
    </span>

    <h1>
        @if($isReset)
            Votre mot de passe a été réinitialisé
        @else
            Bonjour {{ $client->name }},
        @endif
    </h1>

    @if($isReset)
        <p>Un nouveau mot de passe temporaire a été créé pour votre compte.
           Connectez-vous avec les identifiants ci-dessous et changez-le
           immédiatement depuis votre profil.</p>
    @else
        <p>Vous avez été invité(e) par <strong>{{ $operator }}</strong> à rejoindre
           <strong>Panora</strong> — la plateforme qui regroupe en un seul espace vos
           propositions commerciales, le suivi de vos campagnes d'affichage et vos
           factures.</p>
        <p>Voici vos identifiants de connexion :</p>
    @endif

    <div class="info">
        <div class="info-row">
            <div class="lbl">Adresse URL</div>
            <div class="val"><code>{{ url('/client/login') }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Email</div>
            <div class="val"><code>{{ $client->email }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Mot de passe temporaire</div>
            <div class="val"><span class="code-strong">{{ $motDePasse }}</span></div>
        </div>
    </div>

    <div class="alert alert-warning">
        Pour votre sécurité, ce mot de passe est <strong>temporaire</strong>.
        Pensez à le modifier dès votre première connexion depuis votre profil.
    </div>

    <div class="cta-wrap">
        <a href="{{ $loginUrl }}" class="cta">Accéder à mon espace</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
        </div>
    </div>

    <p style="margin-top:24px;color:#6b7280;font-size:13px;">
        Si vous n'attendiez pas cet email, ignorez-le ou contactez l'équipe
        commerciale de <strong>{{ $operator }}</strong>.
    </p>

    <x-slot:footerNote>
        Email automatique — merci de ne pas répondre à cette adresse.
    </x-slot:footerNote>

</x-mail.layout>

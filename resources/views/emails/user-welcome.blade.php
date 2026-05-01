@php
    $title = match ($context) {
        'activated'   => 'Compte activé',
        'reactivated' => 'Compte réactivé',
        default       => 'Bienvenue chez CIBLE CI',
    };
    $intro = match ($context) {
        'activated'   => 'Votre compte vient d\'être activé. Vous pouvez désormais accéder à la plateforme.',
        'reactivated' => 'Votre compte a été réactivé. Bon retour parmi nous.',
        default       => 'Un compte vient d\'être créé pour vous sur la plateforme CIBLE CI.',
    };
    $pillClass = $context === 'created' ? 'pill' : 'pill pill-success';
    $pillText  = match ($context) {
        'activated'   => 'Compte activé',
        'reactivated' => 'Compte réactivé',
        default       => 'Nouveau compte',
    };
    $roleLabel = \App\Enums\UserRole::labelFor($user->role);
    $preheader = $context === 'created'
        ? 'Vos identifiants pour accéder à la plateforme CIBLE CI.'
        : 'Votre compte est de nouveau actif sur CIBLE CI.';
@endphp

<x-mail.layout :title="$title" :preheader="$preheader">

    <span class="{{ $pillClass }}">{{ $pillText }}</span>

    <h1>Bonjour {{ $user->name }},</h1>
    <p>{{ $intro }}</p>

    <div class="info">
        <div class="info-row">
            <div class="lbl">Identifiant (email)</div>
            <div class="val"><code>{{ $user->email }}</code></div>
        </div>
        <div class="info-row">
            <div class="lbl">Rôle</div>
            <div class="val">{{ $roleLabel }}</div>
        </div>
        @if($temporaryPassword)
            <div class="info-row">
                <div class="lbl">Mot de passe</div>
                <div class="val"><span class="code-strong">{{ $temporaryPassword }}</span></div>
            </div>
        @endif
    </div>

    @if($temporaryPassword)
        <div class="alert alert-warning">
            Pour votre sécurité, ce mot de passe est <strong>temporaire</strong>.
            Il vous sera demandé d'en choisir un nouveau lors de votre première connexion.
        </div>
    @endif

    <div class="cta-wrap">
        <a href="{{ $loginUrl }}" class="cta">Se connecter à mon compte</a>
        <div class="cta-fallback">
            Si le bouton ne fonctionne pas, copiez ce lien :<br>
            <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
        </div>
    </div>

    <p style="margin-top:28px;color:#6b7280;font-size:13px;">
        Si vous n'attendiez pas cet email, vous pouvez l'ignorer.
        Pour toute question, contactez votre administrateur.
    </p>

    <x-slot:footerNote>
        Email automatique — merci de ne pas répondre à cette adresse.
    </x-slot:footerNote>

</x-mail.layout>

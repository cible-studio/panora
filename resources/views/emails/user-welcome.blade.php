@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $title = match ($context) {
        'activated'      => 'Compte activé',
        'reactivated'    => 'Compte réactivé',
        'password_reset' => 'Mot de passe réinitialisé',
        default          => "Bienvenue sur PANORA · {$operator}",
    };
    $intro = match ($context) {
        'activated'      => 'Votre compte vient d\'être activé. Vous pouvez désormais accéder à la plateforme.',
        'reactivated'    => 'Votre compte a été réactivé. Bon retour parmi nous.',
        'password_reset' => 'Votre administrateur a réinitialisé votre mot de passe. Voici vos nouveaux identifiants pour vous reconnecter.',
        default          => 'Un compte vient d\'être créé pour vous sur la plateforme PANORA.',
    };
    $pillClass = match ($context) {
        'password_reset' => 'pill pill-warning',
        'created'        => 'pill',
        default          => 'pill pill-success',
    };
    $pillText  = match ($context) {
        'activated'      => 'Compte activé',
        'reactivated'    => 'Compte réactivé',
        'password_reset' => '🔑 Nouveau mot de passe',
        default          => 'Nouveau compte',
    };
    $roleLabel = \App\Enums\UserRole::labelFor($user->role);
    $preheader = match ($context) {
        'created'        => 'Vos identifiants pour accéder à la plateforme PANORA.',
        'password_reset' => "Votre nouveau mot de passe pour PANORA · {$operator}.",
        default          => "Votre compte est de nouveau actif sur PANORA · {$operator}.",
    };
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
            Pensez à le modifier dès votre première connexion depuis votre profil
            (menu en haut à droite → <em>Mon profil</em>).
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

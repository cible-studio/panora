@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $roleLabel = \App\Enums\UserRole::labelFor($user->role);
    $intro = match ($context) {
        'activated'   => 'Votre compte vient d\'être activé.',
        'reactivated' => 'Votre compte a été réactivé.',
        default       => 'Un compte vient d\'être créé pour vous sur la plateforme PANORA.',
    };
@endphp
PANORA · {{ $operator }}

Bonjour {{ $user->name }},

{{ $intro }}

Vos identifiants :
- Email      : {{ $user->email }}
- Rôle       : {{ $roleLabel }}
@if($temporaryPassword)
- Mot de passe temporaire : {{ $temporaryPassword }}

Pour votre sécurité, ce mot de passe est temporaire. Pensez à le modifier
dès votre première connexion depuis votre profil (menu utilisateur).
@endif

Connectez-vous : {{ $loginUrl }}

Si vous n'attendiez pas cet email, vous pouvez l'ignorer.
Pour toute question, contactez votre administrateur.

—
Email automatique — merci de ne pas répondre.
© {{ date('Y') }} Panora · {{ $operator }}. Tous droits réservés.

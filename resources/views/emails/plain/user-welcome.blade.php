@php
    $roleLabel = \App\Enums\UserRole::labelFor($user->role);
    $intro = match ($context) {
        'activated'   => 'Votre compte vient d\'être activé.',
        'reactivated' => 'Votre compte a été réactivé.',
        default       => 'Un compte vient d\'être créé pour vous sur la plateforme CIBLE CI.',
    };
@endphp
CIBLE CI — Régie Publicitaire (Abidjan, Côte d'Ivoire)

Bonjour {{ $user->name }},

{{ $intro }}

Vos identifiants :
- Email      : {{ $user->email }}
- Rôle       : {{ $roleLabel }}
@if($temporaryPassword)
- Mot de passe temporaire : {{ $temporaryPassword }}

Pour votre sécurité, ce mot de passe est temporaire. Vous serez invité à le
changer lors de votre première connexion.
@endif

Connectez-vous : {{ $loginUrl }}

Si vous n'attendiez pas cet email, vous pouvez l'ignorer.
Pour toute question, contactez votre administrateur.

—
Email automatique — merci de ne pas répondre.
© {{ date('Y') }} CIBLE CI. Tous droits réservés.

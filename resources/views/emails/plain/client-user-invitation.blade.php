@php
    $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
    $roleLabel = $user->role === 'owner'
        ? 'Propriétaire (gère l\'équipe, accepte les propositions)'
        : 'Collaborateur (lecture seule)';
@endphp
PANORA · {{ $operator }}

Bonjour {{ $user->name }},

Le propriétaire du compte « {{ $client->name }} » vient de vous créer un
accès à l'espace client PANORA géré par la régie {{ $operator }}.

Vos identifiants de connexion :
- URL          : {{ $loginUrl }}
- Email        : {{ $user->email }}
- Mot de passe : {{ $plainPassword }}
- Rôle         : {{ $roleLabel }}

Pour votre sécurité, ce mot de passe est provisoire. Pensez à le modifier
dès votre première connexion (menu « Sécurité »).

Si vous n'attendiez pas cet email, vous pouvez l'ignorer ou prévenir
le propriétaire du compte {{ $client->name }}.

—
Email automatique — pour toute question, contactez le propriétaire du
compte ou la régie {{ $operator }}.
© {{ date('Y') }} Panora · {{ $operator }}. Tous droits réservés.

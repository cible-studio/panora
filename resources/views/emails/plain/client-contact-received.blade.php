@php $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')); @endphp
PANORA · {{ $operator }} — Nouveau message client

Un client vient d'envoyer un message via le formulaire « Contacter la régie ».

Expéditeur     : {{ $cm->from_name }}
Email          : {{ $cm->from_email }}
Référence      : #{{ $cm->id }}
Reçu le        : {{ $cm->created_at->format('d/m/Y à H:i') }}
Objet          : {{ $cm->subject }}

Contenu du message :
----------------------------------------------
{{ $cm->body }}
----------------------------------------------

Répondre depuis Panora (trace en historique) :
{{ $showUrl }}

Vous pouvez aussi répondre directement à cet email — l'adresse de
réponse pointe sur {{ $cm->from_email }}. Mais la trace ne sera
pas conservée dans /admin/messages.

—
Notification automatique — message archivé sous référence #{{ $cm->id }}.
© {{ date('Y') }} Panora · {{ $operator }}. Tous droits réservés.

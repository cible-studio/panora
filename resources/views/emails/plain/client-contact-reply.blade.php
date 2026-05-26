PANORA · {{ $operator }} — Réponse à votre message

Bonjour {{ $cm->from_name }},

Suite à votre message du {{ $cm->created_at->format('d/m/Y à H:i') }}
(« {{ \Illuminate\Support\Str::limit($cm->subject, 80) }} »),
voici notre retour :

----------------------------------------------
{{ $replyBody }}
----------------------------------------------

Pour rappel, votre message initial :

> {{ str_replace("\n", "\n> ", $cm->body) }}

Si cette réponse ne couvre pas votre demande, n'hésitez pas à revenir
vers nous via votre espace client (« Contacter la régie »).

—
Email envoyé par l'équipe {{ $operator }}.
Référence de votre message : #{{ $cm->id }}.
© {{ date('Y') }} Panora · {{ $operator }}. Tous droits réservés.

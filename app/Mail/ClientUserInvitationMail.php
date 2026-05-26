<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\ClientUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé à un nouvel utilisateur de l'espace client (owner ou
 * member) au moment où le propriétaire du compte le crée depuis
 * /client/equipe. Contient ses identifiants en clair + l'URL de
 * connexion + une recommandation de changer le mot de passe.
 *
 * Sécurité : le mot de passe n'est envoyé qu'au moment de la création
 * (canal email = courrier privé). Une fois changé par l'utilisateur,
 * la valeur d'origine n'est plus jamais accessible.
 */
class ClientUserInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ClientUser $user,
        public readonly string     $plainPassword,
        public readonly Client     $client,
    ) {}

    public function envelope(): Envelope
    {
        $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));
        return new Envelope(
            subject:  "Vos accès à l'espace client PANORA · {$operator}",
            tags:     ['client-user', 'invitation'],
            metadata: ['client_user_id' => (string) $this->user->id, 'client_id' => (string) $this->client->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-user-invitation',
            text: 'emails.plain.client-user-invitation',
            with: [
                'user'          => $this->user,
                'plainPassword' => $this->plainPassword,
                'client'        => $this->client,
                'loginUrl'      => route('client.login'),
            ],
        );
    }
}

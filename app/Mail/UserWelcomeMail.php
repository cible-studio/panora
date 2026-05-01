<?php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé à un utilisateur (admin/commercial/mediaplanner/...) à la
 * création ou activation de son compte.
 *
 * Cas d'usage :
 *   - Création par un admin → mot de passe temporaire fourni
 *   - Self-register (si activé) → confirmation que le compte est prêt
 *   - Réactivation → remerciement
 */
class UserWelcomeMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User    $user,
        public readonly ?string $temporaryPassword = null,
        public readonly string  $context = 'created', // 'created' | 'activated' | 'reactivated'
    ) {}

    public function envelope(): Envelope
    {
        // Subjects sobres et descriptifs — pas d'emoji, pas de "!"
        $subject = match ($this->context) {
            'activated'   => 'Votre compte CIBLE CI a été activé',
            'reactivated' => 'Votre compte CIBLE CI a été réactivé',
            default       => 'Bienvenue sur CIBLE CI - vos identifiants',
        };

        return new Envelope(
            subject:  $subject,
            tags:     ['user', 'welcome', $this->context],
            metadata: ['user_id' => (string) $this->user->id],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-welcome',
            text: 'emails.plain.user-welcome',    // Version texte (anti-spam)
            with: [
                'user'              => $this->user,
                'temporaryPassword' => $this->temporaryPassword,
                'context'           => $this->context,
                'loginUrl'          => route('login'),
            ],
        );
    }
}

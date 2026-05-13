<?php

namespace App\Mail;

use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Client $client,
        public readonly string $motDePasse,
        public readonly bool   $isReset = false
    ) {}

    public function envelope(): Envelope
    {
        $operator = config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI'));

        // PANORA est la plateforme (le SaaS), {operator} est la régie qui
        // l'utilise. Le client se connecte à PANORA via {operator}.
        $subject = $this->isReset
            ? "🔑 Réinitialisation de votre mot de passe — PANORA · {$operator}"
            : "🎉 Bienvenue sur PANORA — Votre espace client est prêt";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.client-account',
            with: [
                'client'     => $this->client,
                'motDePasse' => $this->motDePasse,
                'isReset'    => $this->isReset,
                'loginUrl'   => route('client.login'),
            ]
        );
    }
}
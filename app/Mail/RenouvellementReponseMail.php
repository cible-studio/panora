<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\Renouvellement;
use App\Services\Diffusion\ModelesMail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mail INTERNE : un client a répondu sur le renouvellement de sa campagne
 * (2026-10-22). Destinataire : le commercial de la campagne, commercial@
 * en copie (cf. RenouvellementService::prevenirEquipe).
 */
class RenouvellementReponseMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Renouvellement $dossier,
        public readonly Campaign $campagne,
        public readonly string $choix,
        public readonly ?string $message,
        public readonly ?string $noteTest = null,
    ) {}

    public function envelope(): Envelope
    {
        $client = $this->campagne->client?->name ?? 'Un client';
        $quoi   = match ($this->choix) {
            'renouveler' => 'veut renouveler',
            'ajuster'    => 'veut ajuster son dispositif',
            'refus'      => 'ne renouvelle pas',
            'rappel'     => 'demande à être rappelé',
            default      => 'a répondu',
        };

        return new Envelope(
            subject: ($this->noteTest ? '[TEST] ' : '') . "Renouvellement : {$client} {$quoi} — « {$this->campagne->name} »",
            tags: ['renouvellement-equipe'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.renouvellement-reponse',
            with: [
                'dossier'  => $this->dossier,
                'campagne' => $this->campagne,
                'client'   => $this->campagne->client,
                'choix'    => $this->choix,
                'libelle'  => ModelesMail::CHOIX[$this->choix] ?? $this->choix,
                'message'  => $this->message,
                'noteTest' => $this->noteTest,
                'lien'     => route('admin.renouvellements.index', ['campagne' => $this->campagne->id]),
            ],
        );
    }
}

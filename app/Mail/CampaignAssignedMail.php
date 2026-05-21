<?php

namespace App\Mail;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notification au commercial qu'une campagne lui a été assignée — il en
 * devient le référent client + reçoit les notifications associées.
 *
 * Pattern identique à ReservationAssignedMail.
 */
class CampaignAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly User     $assignedBy,
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        if ($this->assignedBy->email) {
            $replyTo[] = new Address($this->assignedBy->email, $this->assignedBy->name ?? '');
        }

        return new Envelope(
            subject:  "📋 Campagne assignée — {$this->campaign->name}",
            replyTo:  $replyTo,
            tags:     ['campaign', 'assigned'],
            metadata: [
                'campaign_id' => (string) $this->campaign->id,
                'assigned_to' => (string) $this->campaign->commercial_user_id,
            ],
        );
    }

    public function content(): Content
    {
        $this->campaign->loadMissing(['client', 'panels', 'externalPanels']);

        return new Content(
            view: 'emails.campaign-assigned',
            with: [
                'campaign'    => $this->campaign,
                'client'      => $this->campaign->client,
                'assignedBy'  => $this->assignedBy,
                'totalPanels' => $this->campaign->panels->count() + $this->campaign->externalPanels->count(),
                'showLink'    => route('admin.campaigns.show', $this->campaign),
            ],
        );
    }
}

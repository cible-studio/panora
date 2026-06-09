<?php

namespace App\Notifications;

use App\Models\InvoiceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification envoyée sur une alerte d'échéance facture (§7 cahier).
 *
 * Canaux dynamiques : in_app (database) + email, selon le canal posé sur
 * l'InvoiceAlert. Les destinataires sont :
 *   - canal in_app : l'utilisateur commercial responsable + admins
 *   - canal email  : le client (via Client::email)
 *
 * Le client est notifié SEULEMENT par email (in-app n'a pas de sens).
 * Les commerciaux/admins sont notifiés SEULEMENT in-app (pour les alertes
 * email-client, ils reçoivent un récap quotidien à part — Phase 5).
 */
class InvoiceScheduleAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InvoiceAlert $alert)
    {
    }

    public function via(object $notifiable): array
    {
        // Délégué à la cible : si on notifie un User → in-app (database).
        // Si on notifie un client (anonyme via Notifiable on-the-fly) →
        // email uniquement.
        if ($notifiable instanceof \App\Models\User) {
            return ['database'];
        }
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $alert    = $this->alert;
        $invoice  = $alert->invoice;
        $schedule = $alert->schedule;
        $direction = $alert->kind === 'before' ? 'à venir' : 'dépassée';

        $intro = $alert->kind === 'before'
            ? "Votre échéance de paiement arrive à échéance dans " . abs($alert->offset_days) . " jour(s)."
            : "Votre échéance de paiement est dépassée depuis " . abs($alert->offset_days) . " jour(s).";

        $msg = (new MailMessage)
            ->subject(($alert->kind === 'before' ? '📅 Rappel' : '🔴 Relance') . ' — Facture ' . $invoice->reference)
            ->greeting('Bonjour,')
            ->line($intro);

        if ($schedule) {
            $msg->line('Échéance : **' . ($schedule->label ?? 'Échéance') . '**')
                ->line('Date d\'échéance : ' . $schedule->due_date->format('d/m/Y'))
                ->line('Montant : **' . number_format((float) $schedule->amount, 0, ',', ' ') . ' FCFA**');
        }

        $msg->line('Reste à payer sur cette facture : **'
            . number_format($invoice->remainingAmount(), 0, ',', ' ') . ' FCFA**')
            ->line('Merci de bien vouloir régulariser votre situation dans les meilleurs délais.')
            ->salutation('Cordialement, ' . config('billing.company.name', 'CIBLE SARL'));

        return $msg;
    }

    public function toDatabase(object $notifiable): array
    {
        $invoice  = $this->alert->invoice;
        $schedule = $this->alert->schedule;

        return [
            'type'       => 'invoice_schedule_alert',
            'kind'       => $this->alert->kind,
            'offset_days'=> $this->alert->offset_days,
            'invoice_id' => $invoice->id,
            'reference'  => $invoice->reference,
            'client_name'=> $invoice->client?->name,
            'schedule_label'  => $schedule?->label,
            'schedule_amount' => $schedule?->amount,
            'due_date'   => $schedule?->due_date?->toDateString(),
            'remaining'  => $invoice->remainingAmount(),
            'message'    => ($this->alert->kind === 'before' ? '📅 Rappel' : '🔴 Relance')
                          . ' échéance ' . ($schedule?->label ?? 'facture')
                          . ' — ' . abs($this->alert->offset_days) . 'j '
                          . ($this->alert->kind === 'before' ? 'avant' : 'après') . ' échéance',
            'url'        => route('admin.invoices.show', $invoice),
        ];
    }
}

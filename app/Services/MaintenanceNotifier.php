<?php
namespace App\Services;

use App\Mail\MaintenanceMail;
use App\Models\Maintenance;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notifications maintenance — WhatsApp + mail orchestrés ensemble.
 *
 * Trois évènements pris en charge :
 *   - assigned : technicien notifié (WhatsApp si numéro, mail systématique)
 *   - priorityRaised : technicien notifié (WhatsApp si numéro)
 *   - resolved : signaleur + technicien notifiés par mail (info, pas urgent)
 *
 * Toutes les défaillances réseau (WhatsApp HTTP fail, SMTP down) sont
 * loggées mais ne propagent jamais — le métier ne doit pas casser à cause
 * d'un canal de notification indisponible.
 */
class MaintenanceNotifier
{
    public function __construct(
        protected WhatsAppService     $whatsapp,
        protected NotificationMailer  $mailer,
    ) {}

    /**
     * Maintenance vient d'être assignée à un technicien (création OU
     * changement d'assignation). Envoi des 2 canaux.
     */
    public function notifyAssigned(Maintenance $m): void
    {
        $m->loadMissing(['panel.commune', 'technicien', 'signaledBy']);
        $tech = $m->technicien;
        if (!$tech) {
            Log::info('maintenance.notify.assigned.skip.no_tech', ['maintenance_id' => $m->id]);
            return;
        }

        $this->sendWhatsAppToTech(
            $tech,
            $this->buildWhatsAppAssigned($m),
            ['event' => 'maintenance.assigned', 'maintenance_id' => $m->id]
        );

        $this->sendMailToTech(
            $tech,
            $m,
            'assigned'
        );
    }

    /**
     * Priorité passée à haute / urgente sur une maintenance ouverte.
     * Le technicien doit être prévenu rapidement → WhatsApp prioritaire,
     * pas de mail (sinon spam).
     */
    public function notifyPriorityRaised(Maintenance $m): void
    {
        $m->loadMissing(['panel', 'technicien']);
        $tech = $m->technicien;
        if (!$tech || $m->isLocked()) return;

        $this->sendWhatsAppToTech(
            $tech,
            $this->buildWhatsAppPriority($m),
            ['event' => 'maintenance.priority_raised', 'maintenance_id' => $m->id]
        );
    }

    /**
     * Maintenance résolue. Mail au signaleur (pour confirmer) + au
     * technicien (trace). Pas de WhatsApp ici — c'est une info froide.
     */
    public function notifyResolved(Maintenance $m): void
    {
        $m->loadMissing(['panel', 'technicien', 'signaledBy']);

        foreach (array_filter([$m->signaledBy, $m->technicien]) as $user) {
            if (!$user->email) continue;
            $this->mailer->sendSilently(
                $user->email,
                new MaintenanceMail($m, 'resolved'),
                context: ['maintenance_id' => $m->id, 'recipient_id' => $user->id, 'event' => 'resolved']
            );
        }
    }

    /**
     * Maintenance modifiée (description / type / priorité hors raise).
     * Mail simple au technicien si encore ouverte.
     */
    public function notifyUpdated(Maintenance $m): void
    {
        $m->loadMissing(['panel', 'technicien']);
        $tech = $m->technicien;
        if (!$tech || !$tech->email || $m->isLocked()) return;

        $this->mailer->sendSilently(
            $tech->email,
            new MaintenanceMail($m, 'updated'),
            context: ['maintenance_id' => $m->id, 'event' => 'updated']
        );
    }

    // ── Helpers internes ─────────────────────────────────────────────

    private function sendWhatsAppToTech(User $tech, string $message, array $context): void
    {
        if (!$tech->whatsapp_number) {
            Log::info('maintenance.notify.whatsapp.skip.no_number', array_merge($context, [
                'tech_id' => $tech->id,
            ]));
            return;
        }
        $this->whatsapp->send($tech->whatsapp_number, $message, array_merge($context, [
            'tech_id' => $tech->id,
        ]));
    }

    private function sendMailToTech(User $tech, Maintenance $m, string $context): void
    {
        if (!$tech->email) {
            Log::info('maintenance.notify.mail.skip.no_email', [
                'maintenance_id' => $m->id, 'tech_id' => $tech->id,
            ]);
            return;
        }
        $this->mailer->sendSilently(
            $tech->email,
            new MaintenanceMail($m, $context),
            context: ['maintenance_id' => $m->id, 'tech_id' => $tech->id, 'event' => $context]
        );
    }

    private function buildWhatsAppAssigned(Maintenance $m): string
    {
        $priorityIcon = ['urgente' => '🔴', 'haute' => '🟠', 'normale' => '🔵', 'faible' => '⚪'][$m->priorite] ?? '';
        $signaler = $m->signaledBy?->name ?? 'Admin';
        $commune  = $m->panel?->commune?->name;

        $lines = [
            "🔧 *Nouvelle maintenance CIBLE CI*",
            "",
            "Panneau : *{$m->panel?->reference}*" . ($m->panel?->name ? " — {$m->panel->name}" : ''),
            $commune ? "📍 {$commune}" : null,
            "",
            "Type : {$m->type_panne}",
            "Priorité : {$priorityIcon} " . ucfirst($m->priorite),
        ];

        if ($m->description) {
            $desc = mb_substr($m->description, 0, 200);
            $lines[] = "";
            $lines[] = "Description : {$desc}";
        }

        $lines[] = "";
        $lines[] = "Signalé par : {$signaler}";
        $lines[] = "Détails : " . route('admin.maintenances.show', $m);

        return implode("\n", array_filter($lines, fn($l) => $l !== null));
    }

    private function buildWhatsAppPriority(Maintenance $m): string
    {
        $priorityIcon = $m->priorite === 'urgente' ? '🔴' : '🟠';
        return implode("\n", [
            "{$priorityIcon} *Priorité {$m->priorite} — {$m->panel?->reference}*",
            "",
            "La priorité de la maintenance a été remontée.",
            "Type : {$m->type_panne}",
            "",
            "Détails : " . route('admin.maintenances.show', $m),
        ]);
    }
}

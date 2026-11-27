<?php

namespace App\Console\Commands;

use App\Mail\InvoiceIssuedMail;
use App\Mail\InvoiceScheduleReminderMail;
use App\Models\Invoice;
use App\Models\InvoiceAlert;
use App\Models\InvoiceSchedule;
use App\Models\User;
use App\Services\AlertService;
use App\Services\NotificationMailer;
use App\Services\PaymentService;
use App\Services\PublicLinkService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rappels et relances d'échéance des factures — SYSTÈME UNIQUE (2026-11-26).
 *
 * Règle validée le 26/11 (docs/formation/plan-videos-formation.md, points
 * 11 à 13) :
 *   - client : un rappel à J-7, puis des relances à J+7, J+15 et J+30,
 *     tant qu'il reste à payer (config billing.alerts.offsets) ;
 *   - comptable et commercial du dossier : une alerte à chaque étape,
 *     les admins voient tout ;
 *   - aucune relance pour une facture soldée, annulée ou en litige
 *     (seuls les statuts Invoice::STATUTS_RECLAMABLES sont relancés).
 *
 * L'échéance est celle de Invoice::echeance() : chaque échéance non soldée
 * de l'échéancier, ou à défaut la date d'émission + 30 jours.
 *
 * Avant, trois commandes se chevauchaient : finance:check-due-dates
 * (J-7 envoyé deux fois), invoices:send-reminders (comptée depuis la date
 * d'émission, arrêtée au premier versement partiel) et celle-ci (huit mails
 * par échéance, alertes « in-app » qu'aucun écran n'affichait). Les deux
 * premières sont retirées du planning (routes/console.php).
 *
 *   PASSE 1 — Préparation : pour chaque échéance des factures réclamables,
 *   crée les étapes manquantes (une par canal). Une étape dont la date est
 *   passée depuis plus de RATTRAPAGE_JOURS est notée « skipped » : on
 *   rattrape un arrêt du serveur, on n'envoie pas d'un coup des relances
 *   en retard de plusieurs semaines.
 *
 *   PASSE 2 — Envoi : chaque étape arrivée à sa date est revérifiée juste
 *   avant l'envoi (facture soldée, annulée, en litige, échéance payée ou
 *   modifiée → « skipped » avec la raison), puis envoyée.
 *
 *   PASSE 3 — Statut : PaymentService::syncInvoiceStatus() sur les factures
 *   réclamables (passage en retard, ou retour si l'échéance a été reportée).
 */
class InvoiceAlertsCommand extends Command
{
    protected $signature = 'invoices:alerts
                            {--dry-run : Affiche ce qui SERAIT fait sans rien envoyer}
                            {--invoice= : Restreint à une facture précise (id ou référence)}';

    protected $description = 'Rappels et relances d\'échéance des factures (J-7, J+7, J+15, J+30) et passage en retard.';

    /** Une étape manquée est encore envoyée pendant ce nombre de jours, pas au-delà. */
    public const RATTRAPAGE_JOURS = 7;

    public function handle(): int
    {
        $this->info('🔔 Rappels et relances d\'échéance des factures');
        $dry = (bool) $this->option('dry-run');

        $etapes = self::etapes();
        $channels = collect(config('billing.alerts.channels'))->filter()->keys()->all();

        $this->line('Étapes : ' . implode(', ', array_map(fn ($e) => self::libelleEtape($e[0], $e[1]), $etapes)));
        $this->line('Canaux : ' . (empty($channels) ? '(aucun)' : implode(', ', $channels)));

        $stats = ['created' => 0, 'skipped' => 0, 'sent' => 0, 'failed' => 0, 'statuts' => 0];

        // ───────────── Passe 1 : préparation des étapes ─────────────
        $invoices = $this->facturesReclamables();
        $today = now()->startOfDay();

        if (!empty($channels)) {
            foreach ($invoices as $invoice) {
                if ($invoice->remainingAmount() <= 0.01) continue;

                foreach (self::echeancesDe($invoice) as [$scheduleId, $date]) {
                    foreach ($etapes as [$kind, $offset]) {
                        foreach ($channels as $channel) {
                            $r = $this->preparerEtape($invoice, $scheduleId, $date, $kind, $offset, $channel, $today, $dry);
                            if ($r) $stats[$r]++;
                        }
                    }
                }
            }
        } else {
            $this->warn('Aucun canal activé (config billing.alerts.channels) : rien à préparer.');
        }
        $this->info("✓ Étapes préparées : {$stats['created']} — passées sans envoi : {$stats['skipped']}.");

        // ───────────── Passe 2 : envoi des étapes arrivées ─────────────
        $pending = InvoiceAlert::where('status', 'pending')
            ->whereDate('due_date', '<=', $today->toDateString())
            ->when($this->filtreFacture(), fn ($q, $ids) => $q->whereIn('invoice_id', $ids))
            ->with('invoice.client', 'invoice.campaign', 'invoice.commercialUser', 'invoice.schedules', 'invoice.payments', 'schedule')
            ->orderBy('due_date')
            ->get();

        foreach ($pending as $alert) {
            $raison = self::raisonDeNePasEnvoyer($alert, $today);
            if ($raison !== null) {
                if ($dry) {
                    $this->line("[dry] étape #{$alert->id} ({$alert->channel}) écartée : {$raison}");
                } else {
                    $alert->update(['status' => 'skipped', 'error_message' => mb_substr($raison, 0, 500)]);
                }
                $stats['skipped']++;
                continue;
            }

            try {
                if ($dry) {
                    $this->line("[dry] envoi étape #{$alert->id} ({$alert->channel}) — {$alert->invoice->reference} " . self::libelleEtape($alert->kind, $alert->offset_days));
                } else {
                    $this->dispatchAlert($alert, $today);
                    $alert->update(['status' => 'sent', 'sent_at' => now()]);
                }
                $stats['sent']++;
            } catch (\Throwable $e) {
                if (!$dry) {
                    $alert->update([
                        'status' => 'failed',
                        'error_message' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                }
                $stats['failed']++;
                Log::error('invoice.alert.failed', [
                    'alert_id' => $alert->id,
                    'invoice_id' => $alert->invoice_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        $this->info("✓ Envoyées : {$stats['sent']} — échecs : {$stats['failed']}.");

        // ───────────── Passe 3 : statut « en retard » ─────────────
        $sync = app(PaymentService::class);
        foreach ($this->facturesReclamables() as $invoice) {
            $avant = $invoice->status;
            if ($dry) {
                $cible = match ($invoice->paymentStatus()) {
                    'soldee' => 'payee', 'en_retard' => 'en_retard',
                    'partielle' => 'partiellement_payee', 'non_payee' => 'envoyee',
                    default => $avant,
                };
                if ($cible !== $avant) {
                    $this->line("[dry] facture {$invoice->reference} : {$avant} → {$cible}");
                    $stats['statuts']++;
                }
                continue;
            }
            $sync->syncInvoiceStatus($invoice);
            if ($invoice->status !== $avant) $stats['statuts']++;
        }
        $this->info("✓ Statuts mis à jour : {$stats['statuts']}.");

        return self::SUCCESS;
    }

    /**
     * Étapes du calendrier : [kind, offset_days], offset négatif avant
     * l'échéance. Config billing.alerts.offsets (J-7 ; J+7, J+15, J+30).
     *
     * @return list<array{0:string,1:int}>
     */
    public static function etapes(): array
    {
        $o = config('billing.alerts.offsets', ['before' => [], 'after' => []]);
        $etapes = [];
        foreach ($o['before'] ?? [] as $d) $etapes[] = ['before', -abs((int) $d)];
        foreach ($o['after'] ?? [] as $d) $etapes[] = ['after', abs((int) $d)];

        return $etapes;
    }

    /**
     * Échéances à suivre : chaque échéance non soldée de l'échéancier, ou,
     * sans échéancier, l'échéance par défaut de Invoice::echeance().
     *
     * @return list<array{0:?int,1:Carbon}>
     */
    public static function echeancesDe(Invoice $invoice): array
    {
        $schedules = $invoice->relationLoaded('schedules') ? $invoice->schedules : $invoice->schedules()->get();

        if ($schedules->isNotEmpty()) {
            return $schedules->whereNull('paid_at')
                ->filter(fn ($s) => $s->due_date)
                ->map(fn ($s) => [$s->id, $s->due_date->copy()->startOfDay()])
                ->values()->all();
        }

        $date = $invoice->echeance();

        return $date ? [[null, $date]] : [];
    }

    /**
     * Raison d'écarter une étape au moment de l'envoyer, ou null si elle
     * doit partir. Règle des points 11 et 12 (2026-11-26).
     */
    public static function raisonDeNePasEnvoyer(InvoiceAlert $alert, ?Carbon $today = null): ?string
    {
        $today ??= now()->startOfDay();
        $invoice = $alert->invoice;

        if (!$invoice) return 'Facture supprimée.';
        if (!in_array($invoice->status, Invoice::STATUTS_RECLAMABLES, true)) {
            return 'Facture « ' . Invoice::statusLabel($invoice->status) . ' » : aucune relance.';
        }
        if ($invoice->remainingAmount() <= 0.01) return 'Plus rien à payer.';

        $etapes = array_map(fn ($e) => $e[0] . ':' . $e[1], self::etapes());
        if (!in_array($alert->kind . ':' . $alert->offset_days, $etapes, true)) {
            return 'Étape retirée du calendrier (rappel J-7, relances J+7, J+15, J+30).';
        }
        if ($alert->channel === 'whatsapp') return 'Canal WhatsApp non disponible.';

        // L'échéance visée existe-t-elle encore, à la même date ?
        if ($alert->schedule_id) {
            $schedule = $alert->schedule;
            if (!$schedule) return 'Échéance supprimée de l\'échéancier.';
            if ($schedule->paid_at) return 'Échéance soldée.';
            $echeance = $schedule->due_date?->copy()->startOfDay();
        } else {
            if ($invoice->schedules->isNotEmpty()) return 'Un échéancier a remplacé l\'échéance par défaut.';
            $echeance = $invoice->echeance();
        }
        if (!$echeance) return 'Échéance sans date.';
        if (!$echeance->copy()->addDays($alert->offset_days)->isSameDay($alert->due_date)) {
            return 'Date d\'échéance modifiée depuis la préparation.';
        }

        if ($alert->due_date->copy()->startOfDay()->lt($today->copy()->subDays(self::RATTRAPAGE_JOURS))) {
            return 'Étape manquée depuis plus de ' . self::RATTRAPAGE_JOURS . ' jours.';
        }
        if ($alert->kind === 'before' && $echeance->lt($today)) {
            return 'Rappel avant échéance arrivé après l\'échéance.';
        }

        return null;
    }

    public static function libelleEtape(string $kind, int $offset): string
    {
        return $kind === 'before' ? 'J-' . abs($offset) : 'J+' . abs($offset);
    }

    /** Factures réclamables (envoyée, partielle, en retard), filtre --invoice compris. */
    protected function facturesReclamables()
    {
        return Invoice::query()
            ->whereIn('status', Invoice::STATUTS_RECLAMABLES)
            ->when($this->filtreFacture(), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->with('schedules', 'payments', 'client', 'campaign', 'commercialUser')
            ->get();
    }

    /** @return list<int>|null */
    protected function filtreFacture(): ?array
    {
        $f = $this->option('invoice');
        if (!$f) return null;

        return is_numeric($f)
            ? [(int) $f]
            : (Invoice::where('reference', $f)->pluck('id')->all() ?: [0]);
    }

    /**
     * Crée l'étape si elle manque. Si elle attend encore et que la date
     * d'échéance a bougé, sa date suit. Retourne 'created', 'skipped' ou null.
     */
    protected function preparerEtape(Invoice $invoice, ?int $scheduleId, Carbon $echeance, string $kind, int $offset, string $channel, Carbon $today, bool $dry): ?string
    {
        $date = $echeance->copy()->addDays($offset);

        $existante = InvoiceAlert::query()
            ->where('invoice_id', $invoice->id)
            ->when($scheduleId, fn ($q) => $q->where('schedule_id', $scheduleId), fn ($q) => $q->whereNull('schedule_id'))
            ->where('kind', $kind)
            ->where('offset_days', $offset)
            ->where('channel', $channel)
            ->first();

        if ($existante) {
            if ($existante->status === 'pending' && !$existante->due_date->isSameDay($date) && !$dry) {
                $existante->update(['due_date' => $date->toDateString()]);
            }
            return null;
        }

        $tropTard = $date->lt($today->copy()->subDays(self::RATTRAPAGE_JOURS));
        if ($dry) {
            if (!$tropTard && $date->lte($today)) {
                $this->line("[dry] {$invoice->reference} : " . self::libelleEtape($kind, $offset) . " ({$channel}) partirait aujourd'hui");
            }
            return $tropTard ? 'skipped' : 'created';
        }

        InvoiceAlert::create([
            'invoice_id'    => $invoice->id,
            'schedule_id'   => $scheduleId,
            'kind'          => $kind,
            'offset_days'   => $offset,
            'due_date'      => $date->toDateString(),
            'channel'       => $channel,
            'status'        => $tropTard ? 'skipped' : 'pending',
            'error_message' => $tropTard ? 'Date passée depuis plus de ' . self::RATTRAPAGE_JOURS . ' jours à la préparation : pas d\'envoi.' : null,
        ]);

        return $tropTard ? 'skipped' : 'created';
    }

    protected function dispatchAlert(InvoiceAlert $alert, Carbon $today): void
    {
        $invoice  = $alert->invoice;
        $schedule = $alert->schedule;
        $echeance = $schedule?->due_date?->copy()->startOfDay() ?? $invoice->echeance();
        $etape    = self::libelleEtape($alert->kind, $alert->offset_days);
        $f        = fn ($n) => number_format((float) $n, 0, ',', ' ');

        if ($alert->channel === 'in_app') {
            // Alerte du centre d'alertes. Sans destinataire nominatif, elle est
            // vue par le comptable et les admins (AlertService::broadcastTypesForRole) ;
            // une copie ciblée va au commercial du dossier.
            $type = $alert->kind === 'before' ? 'echeance_proche_j7' : 'echeance_depassee';
            $titre = $alert->kind === 'before'
                ? "📅 Échéance dans " . abs($alert->offset_days) . " jours — facture {$invoice->reference}"
                : "🔴 Relance {$etape} — facture {$invoice->reference}";

            $mailClient = in_array('email', collect(config('billing.alerts.channels'))->filter()->keys()->all(), true);
            $message = ($invoice->client?->name ?? 'Client') . ' — échéance « ' . ($schedule?->label ?? 'Solde') . ' » du '
                . $echeance?->format('d/m/Y')
                . ($schedule ? ' (' . $f($schedule->amount) . ' FCFA)' : '')
                . '. Reste à payer : ' . $f($invoice->remainingAmount()) . ' FCFA. '
                . (!$mailClient ? ''
                    : ($invoice->adressesClient()
                        ? 'Le client reçoit ' . ($alert->kind === 'before' ? 'un rappel' : 'une relance') . ' par mail.'
                        : '⚠ Le client n\'a pas d\'adresse mail : aucune relance automatique ne part, contactez-le.'));

            $opts = ['lien' => route('admin.invoices.show', $invoice), 'dedup_extra' => 'etape:' . $alert->id];
            AlertService::notify($type, $titre, $message, $invoice, $opts);

            $commercial = $invoice->commercialUser ?? $invoice->campaign?->resolveCommercialContact();
            $roleCommercial = $commercial?->role instanceof \BackedEnum ? $commercial->role->value : $commercial?->role;
            if ($commercial && !in_array($roleCommercial, ['admin', 'comptable'], true)) {
                // array_merge, pas « + » : la clé d'unicité doit différer de
                // celle du comptable, sinon l'alerte du commercial n'est pas créée.
                AlertService::notify($type, $titre, $message, $invoice, array_merge($opts, [
                    'user_id' => $commercial->id,
                    'dedup_extra' => 'etape:' . $alert->id . AlertService::MARQUE_COPIE_COMMERCIAL,
                ]));
            }
            return;
        }

        if ($alert->channel === 'email') {
            $adresses = $invoice->adressesClient(); // fiche + interlocuteur de la campagne (2026-10-29)
            if (!$adresses) {
                throw new \DomainException("Client #{$invoice->client_id} sans adresse mail : relance impossible.");
            }

            $link = PublicLinkService::create(
                target:    $invoice,
                type:      'invoice',
                expiresAt: now()->addDays(30),
                clientId:  $invoice->client_id,
                metadata:  ['etape_echeance' => $alert->id],
            );

            if ($alert->kind === 'before') {
                // Sans échéancier : l'échéance par défaut, pour le reste à payer.
                $sch = $schedule ?? (new InvoiceSchedule())->forceFill([
                    'invoice_id' => $invoice->id,
                    'label'      => 'Solde',
                    'amount'     => (int) round($invoice->remainingAmount()),
                    'due_date'   => $echeance,
                ]);
                $mail = new InvoiceScheduleReminderMail(
                    invoice:      $invoice,
                    schedule:     $sch,
                    link:         $link,
                    daysUntilDue: max(0, (int) $today->diffInDays($echeance, false)),
                );
            } else {
                $relances = array_values(array_filter(self::etapes(), fn ($e) => $e[0] === 'after'));
                $numero = 1 + (int) array_search($alert->offset_days, array_column($relances, 1), true);
                $mail = new InvoiceIssuedMail($invoice, $link, isReminder: true, reminderNumber: $numero);
            }

            $r = app(NotificationMailer::class)->send(
                $adresses,
                $mail,
                cc: null,
                context: ['invoice_id' => $invoice->id, 'etape' => $etape],
            );
            if (!$r->ok) {
                throw new \RuntimeException($r->message ?? 'Envoi du mail impossible.');
            }
            return;
        }

        throw new \DomainException('Canal ' . $alert->channel . ' non disponible.');
    }
}

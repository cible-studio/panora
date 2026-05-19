<?php

namespace App\Exceptions;

use Throwable;

/**
 * Exception levée quand une tentative de réservation/attribution d'un
 * panneau entre en conflit avec une réservation OU une campagne déjà
 * engagée sur la période.
 *
 * Utilisée comme FILET ULTIME : si pour quelque raison la pré-validation
 * applicative échoue à détecter le conflit (race condition, code legacy
 * qui contourne AvailabilityService, etc.), l'exception est levée juste
 * avant l'insert effectif — la transaction est rollback.
 *
 * Le détail des conflits (panneau, ref bloqueuse, date de libération)
 * est attaché pour permettre à l'UI d'afficher un message précis :
 *   "Le panneau PBT-CAIS001A est engagé sur la campagne CAMP-2025-014
 *    jusqu'au 22/05/2026. Libre à partir du 23/05/2026."
 */
class BookingConflictException extends \RuntimeException
{
    /**
     * @param  array<int, array{
     *     panel_id: int,
     *     reference?: ?string,
     *     blocking_source: 'reservation'|'campaign',
     *     blocking_ref: ?string,
     *     release_date: ?string,
     *     next_free: ?string
     *  }>  $conflicts
     */
    public function __construct(
        public array $conflicts = [],
        string $message = '',
        ?Throwable $previous = null,
    ) {
        if ($message === '') {
            $n = count($conflicts);
            $message = $n === 1
                ? "Conflit de réservation détecté sur 1 panneau."
                : "Conflit de réservation détecté sur {$n} panneaux.";
        }
        parent::__construct($message, 0, $previous);
    }

    /**
     * Message formaté pour l'UI : liste des panneaux conflictuels +
     * la prochaine date libre. Suffisant pour informer l'utilisateur
     * sur ce qu'il doit corriger.
     */
    public function userMessage(): string
    {
        $lines = [];
        foreach ($this->conflicts as $c) {
            $ref     = $c['reference'] ?? ('#' . $c['panel_id']);
            $source  = $c['blocking_source'] === 'campaign' ? 'campagne' : 'réservation';
            $blkRef  = $c['blocking_ref'] ?? '?';
            $nxtFree = $c['next_free']
                ? ' — libre à partir du ' . $c['next_free']
                : '';
            $lines[] = "• {$ref} — engagé sur {$source} {$blkRef}{$nxtFree}";
        }
        return $this->getMessage() . "\n\n" . implode("\n", $lines);
    }
}

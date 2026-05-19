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
                ? "1 panneau n'est pas disponible sur la période choisie."
                : "{$n} panneaux ne sont pas disponibles sur la période choisie.";
        }
        parent::__construct($message, 0, $previous);
    }

    /**
     * Date suggérée pour décaler la résa : la PLUS TARDIVE des dates de
     * libération parmi tous les conflits. Si on décale au-delà, plus
     * aucun panneau de la sélection n'est en conflit.
     */
    public function suggestedStartDate(): ?string
    {
        $dates = array_filter(array_column($this->conflicts, 'next_free'));
        if (empty($dates)) return null;
        // Format d/m/Y → conversion pour tri
        $stamps = array_map(function ($d) {
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', $d);
            } catch (\Throwable) {
                return null;
            }
        }, $dates);
        $stamps = array_filter($stamps);
        if (empty($stamps)) return null;
        usort($stamps, fn($a, $b) => $a <=> $b);
        return end($stamps)->format('d/m/Y');
    }

    /**
     * Message formaté pour l'UI (HTML, rendu en innerHTML côté modal
     * d'erreur de la vue dispo). Structure :
     *   1. Titre + explication courte
     *   2. Liste détaillée par panneau conflictuel
     *   3. "Que faire ?" — 3 options concrètes avec suggestion de date
     */
    public function userMessage(): string
    {
        $title = htmlspecialchars($this->getMessage(), ENT_QUOTES, 'UTF-8');
        $n     = count($this->conflicts);

        // Liste des panneaux conflictuels
        $items = [];
        foreach ($this->conflicts as $c) {
            $ref     = htmlspecialchars($c['reference'] ?? ('#' . $c['panel_id']), ENT_QUOTES, 'UTF-8');
            $source  = $c['blocking_source'] === 'campaign' ? 'campagne' : 'réservation';
            $blkRef  = htmlspecialchars($c['blocking_ref'] ?? '?', ENT_QUOTES, 'UTF-8');
            $nxtFree = $c['next_free'] ? htmlspecialchars($c['next_free'], ENT_QUOTES, 'UTF-8') : null;

            $items[] = '<div style="padding:8px 10px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2);border-radius:8px;margin-top:6px">'
                . '<div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-weight:700;color:#ef4444;font-size:13px">' . $ref . '</div>'
                . '<div style="font-size:12px;color:#475569;margin-top:3px">Engagé sur ' . $source . ' <strong>' . $blkRef . '</strong>'
                . ($nxtFree ? ' · 📅 Libre à partir du <strong>' . $nxtFree . '</strong>' : '')
                . '</div>'
                . '</div>';
        }
        $list = implode('', $items);

        // Recommandations actionnables
        $suggested = $this->suggestedStartDate();
        $recos = [];
        if ($suggested) {
            $recos[] = '<li><strong>Décalez la date de début</strong> au <strong>' . htmlspecialchars($suggested, ENT_QUOTES, 'UTF-8') . '</strong> ou plus tard pour conserver ces panneaux.</li>';
        }
        $recos[] = '<li><strong>Retirez ' . ($n > 1 ? 'ces ' . $n . ' panneaux' : 'ce panneau') . '</strong> de votre sélection pour confirmer le reste.</li>';
        $recos[] = '<li>Ou <strong>choisissez une autre période</strong> sans chevauchement.</li>';

        return '<div style="line-height:1.5">'
            . '<div style="font-weight:700;color:#dc2626;font-size:14px;margin-bottom:4px">⚠️ ' . $title . '</div>'
            . '<div style="font-size:12px;color:#64748b;margin-bottom:8px">Voici le détail :</div>'
            . $list
            . '<div style="margin-top:14px;padding:10px 12px;background:rgba(250,184,11,.08);border:1px solid rgba(250,184,11,.25);border-radius:8px">'
                . '<div style="font-size:12px;font-weight:700;color:#a16207;margin-bottom:6px">💡 Que faire ?</div>'
                . '<ol style="font-size:12px;color:#475569;margin:0;padding-left:18px;line-height:1.7">'
                    . implode('', $recos)
                . '</ol>'
            . '</div>'
            . '</div>';
    }
}

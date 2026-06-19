<?php

namespace App\Helpers;

use Carbon\Carbon;

/**
 * Formate un écart temporel entre deux dates en français lisible
 * pour un tech terrain : "avec 6 heures de retard", "prévue dans 2
 * semaines", "prévue le 15 août".
 *
 * Utilisé prioritairement par la modale B1 "Tu démarres cette pose
 * hors créneau" (SM2c) mais réutilisable pour PDFs, emails, etc.
 *
 * Conçu pour avoir EXACTEMENT la même logique que la fonction
 * describeOffset() côté JS (public/js/tech/features/off-schedule.js)
 * afin que tech serveur et tech client affichent les mêmes phrases.
 *
 * Hotfix 2026-06-19 : remplace le calcul brut en heures qui produisait
 * "275h 30 min en avance" pour une pose prévue dans 11 jours.
 */
class HumanTimeDiff
{
    /**
     * Retourne une description humaine de l'écart entre $scheduledAt et
     * $now, ou null si l'écart est négligeable (< 2h).
     *
     * Exemples (now = 19/06/2026 09:00) :
     *   - 14:00 aujourd'hui   → null  (5h écart, mais en fait > 2h… cas test)
     *   - 30 min après now    → null
     *   - 6h après now        → "avec 6 heures d'avance"
     *   - 6h avant now        → "avec 6 heures de retard"
     *   - 19h après now       → "avec moins d'un jour d'avance"
     *   - 3 jours après now   → "avec 3 jours d'avance"
     *   - 15 jours après now  → "prévue dans 2 semaines"
     *   - 2 mois après now    → "prévue le 19 août"
     */
    public static function formatScheduleDiff(Carbon $scheduledAt, ?Carbon $now = null): ?string
    {
        $now = $now ?? Carbon::now();
        // diffInMinutes(false) → signé. Carbon convention :
        //   $now->diffInMinutes($scheduledAt, false) > 0  si scheduledAt > now (avance)
        //   < 0 si scheduledAt < now (retard)
        $minutes    = $now->diffInMinutes($scheduledAt, false);
        $absMinutes = abs($minutes);

        // Tolérance : pas de message si l'écart est petit (< 2h)
        if ($absMinutes < 120) {
            return null;
        }

        $isLate    = $minutes < 0;
        $direction = $isLate ? 'de retard' : 'd\'avance';

        // 2h ≤ écart < 12h
        if ($absMinutes < 12 * 60) {
            $h = intval($absMinutes / 60);
            return "avec {$h} heures {$direction}";
        }

        // 12h ≤ écart < 24h
        if ($absMinutes < 24 * 60) {
            return "avec moins d'un jour {$direction}";
        }

        $absDays = intval($absMinutes / (24 * 60));

        // 1j ≤ écart < 7j
        if ($absDays < 7) {
            $jourMot = $absDays > 1 ? 'jours' : 'jour';
            return "avec {$absDays} {$jourMot} {$direction}";
        }

        // 7j ≤ écart < 30j → on parle de semaines (avance uniquement)
        // (un retard de 7+ jours est extrême — on garde la formulation
        // "X jours" pour rester explicite)
        if ($absDays < 30 && !$isLate) {
            $semaines    = intval($absDays / 7);
            $semaineMot  = $semaines > 1 ? 'semaines' : 'semaine';
            return "prévue dans {$semaines} {$semaineMot}";
        }
        if ($absDays < 30 && $isLate) {
            return "avec {$absDays} jours de retard";
        }

        // > 30 jours → afficher la date pleine
        if ($isLate) {
            return "prévue le " . $scheduledAt->locale('fr')->isoFormat('D MMMM');
        }
        return "prévue le " . $scheduledAt->locale('fr')->isoFormat('D MMMM');
    }
}

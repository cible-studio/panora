<?php

namespace App\Support;

/**
 * HumanDuration — formatage humain d'une durée en minutes.
 *
 * Source unique pour toutes les vues qui affichent des durées (perf
 * techniciens, perf équipes, PDF, KPI SLA, etc.).
 *
 * Règles :
 *   null           → '—'      (donnée manquante — pose non trackée)
 *   0 ou négatif   → '—'      (durée impossible)
 *   1..59 minutes  → 'X min'   ex : '45 min'
 *   60..1439 min   → 'X h Y min' ou 'X h' si Y=0
 *   1440+ minutes  → 'X j Y h'  ou 'X j' si Y=0
 *
 * Exemples :
 *   45      → '45 min'
 *   60      → '1 h'
 *   125     → '2 h 5 min'
 *   1440    → '1 j'
 *   21916   → '15 j 5 h'   (au lieu de '21916 min' illisible)
 *   6083    → '4 j 5 h'
 */
class HumanDuration
{
    /**
     * Formate un nombre de minutes en durée lisible.
     *
     * @param  int|float|null  $minutes
     * @return string
     */
    public static function fromMinutes($minutes): string
    {
        if ($minutes === null || $minutes === '' || $minutes === false) {
            return '—';
        }
        $m = (int) round((float) $minutes);
        if ($m <= 0) {
            return '—';
        }
        if ($m < 60) {
            return $m . ' min';
        }
        // < 24h → X h Y min
        if ($m < 1440) {
            $h = intdiv($m, 60);
            $r = $m % 60;
            return $r === 0 ? $h . ' h' : $h . ' h ' . $r . ' min';
        }
        // >= 24h → X j Y h (on n'affiche plus les minutes au-delà d'1 jour)
        $j = intdiv($m, 1440);
        $rh = intdiv($m % 1440, 60);
        return $rh === 0 ? $j . ' j' : $j . ' j ' . $rh . ' h';
    }

    /**
     * Formate un nombre d'heures en durée lisible (utilisé pour délai pige).
     *
     * @param  int|float|null  $hours
     * @return string
     */
    public static function fromHours($hours): string
    {
        if ($hours === null || $hours === '' || $hours === false) {
            return '—';
        }
        $h = (float) $hours;
        if ($h <= 0) {
            return '—';
        }
        // < 24h : on délègue à fromMinutes pour bénéficier du "45 min" pur
        //         quand $h < 1 (au lieu de "0 h 30 min").
        return self::fromMinutes((int) round($h * 60));
    }
}

<?php

namespace App\Services\Diffusion;

use App\Models\JourFerie;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Jours fériés de Côte d'Ivoire pour le calendrier de diffusion.
 *
 * Ce qui est pré-rempli automatiquement, année par année :
 *   • les fêtes à date fixe (Jour de l'an, Travail, Indépendance,
 *     Assomption, Toussaint, Paix, Noël) ;
 *   • les fêtes chrétiennes mobiles, calculées depuis Pâques (lundi de
 *     Pâques, Ascension, lundi de Pentecôte).
 *
 * Ce qui NE PEUT PAS l'être : les fêtes musulmanes (Aïd el-Fitr, Tabaski,
 * Maouloud, lendemain de la Nuit du Destin). Elles dépendent de
 * l'observation de la lune et sont fixées chaque année par décret —
 * l'admin les saisit dans l'écran « Diffusion des disponibilités ».
 */
class JoursFeriesService
{
    /** Fêtes à date fixe : 'mm-dd' => libellé */
    private const FIXES = [
        '01-01' => 'Jour de l\'an',
        '05-01' => 'Fête du Travail',
        '08-07' => 'Fête de l\'Indépendance',
        '08-15' => 'Assomption',
        '11-01' => 'Toussaint',
        '11-15' => 'Journée nationale de la Paix',
        '12-25' => 'Noël',
    ];

    /** Fêtes mobiles : décalage en jours après le dimanche de Pâques. */
    private const DEPUIS_PAQUES = [
        1  => 'Lundi de Pâques',
        39 => 'Ascension',
        50 => 'Lundi de Pentecôte',
    ];

    /**
     * Pré-remplit les fêtes fixes et chrétiennes d'une année.
     *
     * Ne fait rien si l'année contient déjà des fêtes pré-remplies : si
     * l'admin a volontairement supprimé une date, on ne la recrée pas
     * dans son dos à chaque calcul.
     */
    public function assurerAnnee(int $annee): void
    {
        $dejaFait = JourFerie::whereYear('date', $annee)
            ->whereIn('source', ['fixe', 'chretien'])
            ->exists();

        if ($dejaFait) {
            return;
        }

        $lignes = [];
        foreach ($this->feriesCalculables($annee) as $date => [$libelle, $source]) {
            $lignes[] = [
                'date'       => $date,
                'libelle'    => $libelle,
                'source'     => $source,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // insertOrIgnore : une saisie manuelle déjà présente à la même
        // date (contrainte unique) est conservée telle quelle.
        JourFerie::insertOrIgnore($lignes);
    }

    /**
     * Fêtes calculables d'une année, sans toucher à la base.
     *
     * @return array<string, array{0: string, 1: string}>  'Y-m-d' => [libellé, source]
     */
    public function feriesCalculables(int $annee): array
    {
        $jours = [];

        foreach (self::FIXES as $mmjj => $libelle) {
            $jours["{$annee}-{$mmjj}"] = [$libelle, 'fixe'];
        }

        $paques = self::dimancheDePaques($annee);
        foreach (self::DEPUIS_PAQUES as $decalage => $libelle) {
            $jours[$paques->copy()->addDays($decalage)->toDateString()] = [$libelle, 'chretien'];
        }

        ksort($jours);

        return $jours;
    }

    /**
     * Calendrier prêt à l'emploi autour d'une date : jours fériés de
     * l'année précédente, courante et suivante (un créneau de fin
     * décembre peut se décaler en janvier).
     */
    public function calendrier(?CarbonInterface $autour = null): DiffusionCalendrier
    {
        $annee = ($autour ?? now())->year;

        foreach ([$annee - 1, $annee, $annee + 1] as $a) {
            $this->assurerAnnee($a);
        }

        $dates = JourFerie::whereBetween('date', [
            Carbon::create($annee - 1, 1, 1)->toDateString(),
            Carbon::create($annee + 1, 12, 31)->toDateString(),
        ])->pluck('date');

        return new DiffusionCalendrier($dates);
    }

    /**
     * L'année a-t-elle au moins une fête musulmane saisie ? Sert à
     * afficher un avertissement à l'admin : sans ces dates, un envoi peut
     * tomber un jour de Tabaski.
     */
    public function musulmanesSaisies(int $annee): bool
    {
        return JourFerie::whereYear('date', $annee)->where('source', 'musulman')->exists();
    }

    /**
     * Dimanche de Pâques (calendrier grégorien) — algorithme de Meeus.
     * Calculé ici plutôt que via easter_date() pour ne pas dépendre de
     * l'extension PHP « calendar », absente de l'image Docker.
     */
    public static function dimancheDePaques(int $annee): Carbon
    {
        $a = $annee % 19;
        $b = intdiv($annee, 100);
        $c = $annee % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mois = intdiv($h + $l - 7 * $m + 114, 31);
        $jour = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($annee, $mois, $jour)->startOfDay();
    }
}

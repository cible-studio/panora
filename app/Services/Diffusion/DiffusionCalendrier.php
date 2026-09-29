<?php

namespace App\Services\Diffusion;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendrier de diffusion des disponibilités — SOURCE UNIQUE des dates.
 *
 * Règles validées le 2026-09-29 :
 *
 *   • Deux créneaux par mois : le 1er et le 15.
 *   • Envoi à 10h le jour du créneau. Si ce jour est un samedi, un
 *     dimanche ou un jour férié → premier jour ouvrable SUIVANT, 10h.
 *   • Période couverte : créneau du 1er → tout le mois ; créneau du 15
 *     → du 15 à la fin du mois.
 *   • La période suit le CRÉNEAU, pas le jour d'envoi : le 15 novembre
 *     2026 (dimanche, férié) part le lundi 16 mais couvre du 15 au 30.
 *
 * Classe pure : les jours fériés sont injectés, aucune requête ici. C'est
 * ce qui permet de tester chaque exemple du tableau validé sans base de
 * données (cf. tests/Unit/DiffusionCalendrierTest.php).
 */
final class DiffusionCalendrier
{
    /** Jours de créneau dans le mois. */
    public const JOURS_CRENEAUX = [1, 15];

    /** @var array<string, true>  clés 'Y-m-d' */
    private array $feries;

    /**
     * @param iterable<string|CarbonInterface> $feries  dates des jours fériés
     */
    public function __construct(iterable $feries = [])
    {
        $this->feries = [];
        foreach ($feries as $f) {
            $cle = $f instanceof CarbonInterface ? $f->toDateString() : Carbon::parse($f)->toDateString();
            $this->feries[$cle] = true;
        }
    }

    public function estFerie(CarbonInterface $jour): bool
    {
        return isset($this->feries[$jour->toDateString()]);
    }

    public function estOuvrable(CarbonInterface $jour): bool
    {
        return !$jour->isWeekend() && !$this->estFerie($jour);
    }

    /**
     * Le jour lui-même s'il est ouvrable, sinon le premier jour ouvrable
     * qui suit.
     */
    public function premierOuvrableAPartirDe(CarbonInterface $jour): Carbon
    {
        $d = Carbon::parse($jour->toDateString());

        // Garde-fou : une série de plus de 30 jours chômés consécutifs
        // trahirait une saisie erronée des jours fériés, pas une réalité.
        for ($i = 0; $i < 30 && !$this->estOuvrable($d); $i++) {
            $d->addDay();
        }

        return $d;
    }

    /** @return Carbon[]  les deux créneaux nominaux du mois (le 1er et le 15) */
    public function creneauxDuMois(int $annee, int $mois): array
    {
        return array_map(
            fn(int $jour) => Carbon::create($annee, $mois, $jour)->startOfDay(),
            self::JOURS_CRENEAUX
        );
    }

    /** Jour réel d'envoi d'un créneau (décalé si non ouvrable). */
    public function dateEnvoi(CarbonInterface $creneau): Carbon
    {
        return $this->premierOuvrableAPartirDe($creneau);
    }

    /**
     * Le créneau dont l'envoi tombe CE jour-là, ou null.
     *
     * On regarde les créneaux du mois courant ET du mois précédent : un
     * créneau du 15 décalé ne franchit jamais la fin du mois en pratique,
     * mais un 1er de mois très chômé non plus — la double lecture ne coûte
     * rien et évite un envoi perdu sur un calendrier inhabituel.
     */
    public function creneauDuJour(CarbonInterface $jour): ?Carbon
    {
        $j = Carbon::parse($jour->toDateString());

        if (!$this->estOuvrable($j)) {
            return null;
        }

        $precedent = $j->copy()->subMonthNoOverflow();
        $candidats = array_merge(
            $this->creneauxDuMois($precedent->year, $precedent->month),
            $this->creneauxDuMois($j->year, $j->month),
        );

        foreach ($candidats as $creneau) {
            if ($this->dateEnvoi($creneau)->isSameDay($j)) {
                return $creneau;
            }
        }

        return null;
    }

    /**
     * Créneau « en cours » à une date donnée — utilisé par l'envoi manuel.
     * Avant le 15 : créneau du 1er. À partir du 15 : créneau du 15.
     */
    public function creneauCourant(CarbonInterface $jour): Carbon
    {
        $j = Carbon::parse($jour->toDateString());

        return Carbon::create($j->year, $j->month, $j->day < 15 ? 1 : 15)->startOfDay();
    }

    /**
     * Période couverte par un créneau.
     *
     * @return array{0: Carbon, 1: Carbon}  [début, fin]
     */
    public function periode(CarbonInterface $creneau): array
    {
        $debut = Carbon::parse($creneau->toDateString())->startOfDay();

        return [$debut, $debut->copy()->endOfMonth()->startOfDay()];
    }

    /**
     * Prochain envoi à partir d'une date (incluse).
     *
     * @return array{creneau: Carbon, date_envoi: Carbon}
     */
    public function prochainEnvoi(CarbonInterface $apartir): array
    {
        $j = Carbon::parse($apartir->toDateString());

        // Mois précédent inclus : un créneau du 15 décalé peut encore être
        // à venir en tout début de mois suivant sur un calendrier chômé.
        $mois = $j->copy()->subMonthNoOverflow()->startOfMonth();
        for ($i = 0; $i < 4; $i++, $mois->addMonthNoOverflow()) {
            foreach ($this->creneauxDuMois($mois->year, $mois->month) as $creneau) {
                $envoi = $this->dateEnvoi($creneau);
                if ($envoi->greaterThanOrEqualTo($j)) {
                    return ['creneau' => $creneau, 'date_envoi' => $envoi];
                }
            }
        }

        // Inatteignable avec 4 mois d'avance, sauf calendrier absurde.
        $creneau = Carbon::create($j->year, $j->month, 1)->addMonthNoOverflow();

        return ['creneau' => $creneau, 'date_envoi' => $this->dateEnvoi($creneau)];
    }

    /**
     * Libellé français d'une période, pour l'objet du mail et les écrans.
     * « du 1er au 30 novembre 2026 » / « du 15 au 30 novembre 2026 »
     */
    public static function libellePeriode(CarbonInterface $debut, CarbonInterface $fin): string
    {
        $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        $jourDebut = $debut->day === 1 ? '1er' : (string) $debut->day;

        if ($debut->month === $fin->month && $debut->year === $fin->year) {
            return "du {$jourDebut} au {$fin->day} {$mois[$fin->month]} {$fin->year}";
        }

        return "du {$jourDebut} {$mois[$debut->month]} {$debut->year} au {$fin->day} {$mois[$fin->month]} {$fin->year}";
    }

    /** « novembre 2026 » */
    public static function libelleMois(CarbonInterface $date): string
    {
        $mois = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
                 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $mois[$date->month] . ' ' . $date->year;
    }
}

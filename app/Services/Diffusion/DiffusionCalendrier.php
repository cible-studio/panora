<?php

namespace App\Services\Diffusion;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Calendrier de diffusion des disponibilités — SOURCE UNIQUE des dates.
 *
 * Règles validées le 2026-10-30 (elles remplacent celles du 2026-09-29) :
 *
 *   • Deux catalogues par mois, qui couvrent toujours les mêmes périodes :
 *       — catalogue « du 1er » : du 1er à la fin du mois ;
 *       — catalogue « du 15 » : du 15 à la fin du mois.
 *
 *   • Mais ils partent AVANT la période qu'ils annoncent, pour que le
 *     client ait ses disponibilités à temps pour lancer sa campagne :
 *       — catalogue du 1er du mois M → envoyé entre le 20 et le 25 de M-1 ;
 *       — catalogue du 15 du mois M → envoyé entre le 1er et le 5 de M.
 *
 *   • Jour exact : le PREMIER JOUR OUVRABLE de la fenêtre. Le 20 s'il est
 *     ouvrable, sinon le 21, et ainsi de suite sans dépasser le 25. Le
 *     client reçoit donc au plus tôt, et l'envoi reste dans la fenêtre.
 *     Si toute la fenêtre est chômée — calendrier absurde — on part le
 *     dernier jour plutôt que de ne rien envoyer.
 *
 *   • Envoi à 10h, comme avant.
 *
 * AVANT le 2026-10-30, l'envoi avait lieu LE jour du créneau (le 1er ou
 * le 15, décalé au jour ouvrable suivant). Les clients recevaient donc le
 * catalogue du mois le jour où le mois commençait, trop tard pour caler
 * une campagne dessus.
 *
 * Classe pure : les jours fériés sont injectés, aucune requête ici. C'est
 * ce qui permet de tester chaque exemple du tableau validé sans base de
 * données (cf. tests/Unit/DiffusionCalendrierTest.php).
 */
final class DiffusionCalendrier
{
    /** Jours de créneau dans le mois. */
    public const JOURS_CRENEAUX = [1, 15];

    /**
     * Fenêtres d'envoi, par jour de créneau.
     *
     *   1  → [20, 25] du mois PRÉCÉDENT (décalage d'un mois)
     *   15 → [1, 5]   du mois du créneau
     *
     * @var array<int, array{jours: array{0:int,1:int}, mois_avant: int}>
     */
    public const FENETRES = [
        1  => ['jours' => [20, 25], 'mois_avant' => 1],
        15 => ['jours' => [1, 5],   'mois_avant' => 0],
    ];

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

    /**
     * Fenêtre d'envoi d'un créneau : les deux dates entre lesquelles le
     * catalogue doit partir.
     *
     * @return array{0: Carbon, 1: Carbon}  [premier jour, dernier jour]
     */
    public function fenetreEnvoi(CarbonInterface $creneau): array
    {
        $c = Carbon::parse($creneau->toDateString());
        $f = self::FENETRES[$c->day] ?? self::FENETRES[1];

        $mois = $c->copy()->startOfMonth()->subMonthsNoOverflow($f['mois_avant']);

        return [
            $mois->copy()->setDay($f['jours'][0])->startOfDay(),
            $mois->copy()->setDay($f['jours'][1])->startOfDay(),
        ];
    }

    /**
     * Jour réel d'envoi d'un créneau : le premier jour ouvrable de sa
     * fenêtre. Si toute la fenêtre est chômée, le dernier jour — mieux
     * vaut un envoi un jour férié que pas d'envoi du tout.
     */
    public function dateEnvoi(CarbonInterface $creneau): Carbon
    {
        [$debut, $fin] = $this->fenetreEnvoi($creneau);

        for ($j = $debut->copy(); $j->lessThanOrEqualTo($fin); $j->addDay()) {
            if ($this->estOuvrable($j)) {
                return $j;
            }
        }

        return $fin;
    }

    /**
     * Le créneau dont l'envoi tombe CE jour-là, ou null.
     *
     * Les catalogues partant AVANT leur période, on balaie le mois
     * précédent, le mois courant et le SUIVANT : le 22 novembre est le
     * jour d'envoi du catalogue du 1er décembre.
     */
    public function creneauDuJour(CarbonInterface $jour): ?Carbon
    {
        $j = Carbon::parse($jour->toDateString());

        if (!$this->estOuvrable($j)) {
            return null;
        }

        $candidats = [];
        foreach ([-1, 0, 1] as $decalage) {
            $m = $j->copy()->startOfMonth()->addMonthsNoOverflow($decalage);
            $candidats = array_merge($candidats, $this->creneauxDuMois($m->year, $m->month));
        }

        foreach ($candidats as $creneau) {
            if ($this->dateEnvoi($creneau)->isSameDay($j)) {
                return $creneau;
            }
        }

        return null;
    }

    /**
     * Créneau « en cours » à une date donnée — utilisé par l'envoi manuel.
     *
     * C'est la prochaine période à diffuser, celle que l'admin veut
     * envoyer s'il clique aujourd'hui :
     *   · avant le 20 → le catalogue du 15 du mois courant ;
     *   · à partir du 20 → le catalogue du 1er du mois suivant, dont la
     *     fenêtre d'envoi vient de s'ouvrir.
     *
     * La bascule suit la fenêtre d'envoi (le 20), pas le milieu du mois.
     */
    public function creneauCourant(CarbonInterface $jour): Carbon
    {
        $j = Carbon::parse($jour->toDateString());
        $ouvertureDuCatalogueSuivant = self::FENETRES[1]['jours'][0]; // le 20

        if ($j->day >= $ouvertureDuCatalogueSuivant) {
            return $j->copy()->startOfMonth()->addMonthNoOverflow()->startOfDay();
        }

        return Carbon::create($j->year, $j->month, 15)->startOfDay();
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

        // Mois précédent inclus par sécurité ; mois suivants parce qu'un
        // catalogue part avant sa période (le 1er décembre s'envoie en
        // novembre). Quatre mois d'avance suffisent largement.
        $mois = $j->copy()->subMonthNoOverflow()->startOfMonth();
        for ($i = 0; $i < 5; $i++, $mois->addMonthNoOverflow()) {
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

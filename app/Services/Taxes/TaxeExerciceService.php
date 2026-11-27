<?php

namespace App\Services\Taxes;

use App\Models\Commune;
use App\Models\TaxeReglement;
use App\Models\TaxeReprise;
use App\Services\TaxCalculationService;
use Illuminate\Support\Collection;

/**
 * ═══ SOURCE UNIQUE — l'exercice fiscal d'une mairie (2026-11-16) ═══
 *
 * Reprend la logique de la fiche de la direction (« GESTION DES TAXES (ODP
 * ET TM) DE LA RÉGIE CIBLE ») et l'automatise :
 *
 *     Total dû = Arriérés + ODP + TM (T1 + T2 + T3 + T4)
 *     Solde    = Total dû − règlements de l'exercice (non annulés)
 *
 * RÈGLES VALIDÉES PAR ÉCRIT LE 2026-11-16 :
 *   1. Les panneaux et les tarifs (datés) de Panora font foi.
 *   2. TM : toutes les campagnes d'un panneau, chaque mois de campagne dans
 *      le trimestre où il commence (TaxPeriodCalculator).
 *   3. Arriérés : le solde d'un exercice passe automatiquement dans le
 *      suivant ; un trop-payé vient en déduction.
 *   4. Règlement : montant global + exercice réglé + objet indicatif ; jamais
 *      effacé, annulé avec motif (TaxeReglement).
 *   5. Prévu : la TM des campagnes planifiées, affichée à part, hors solde.
 *   6. 2026 = exercice de reprise (option B) : le dû est ce que la mairie a
 *      réclamé, saisi par les comptables (TaxeReprise) ; le calcul de Panora
 *      est affiché pour contrôle. À partir de 2027, le dû est le calcul.
 *
 * Qui lit ce service : le Suivi des mairies (écran, relevé PDF, Excel),
 * la page Taxes (colonnes payé / solde), le rapport Taxes, le tableau de
 * bord, la synthèse mensuelle et l'alerte de début de trimestre. Aucun
 * de ces endroits ne refait le calcul.
 */
class TaxeExerciceService
{
    /** @var array<int, array<int, array>> année → commune_id → calcul Panora */
    private array $memoCalcul = [];

    /** @var array<int, array<int, array<int, int>>> année → commune_id → TM prévue par trimestre */
    private array $memoPrevu = [];

    /** @var array<int, Collection> année → exercices par commune */
    private array $memoExercices = [];

    public function __construct(protected TaxCalculationService $calc) {}

    public function exerciceReprise(): int
    {
        return (int) config('taxes.exercice_reprise', 2026);
    }

    /**
     * Les pieds Lumipub entrent-ils dans le dû ? Non, décision du
     * 2026-11-16 (config/taxes.php). Lu aussi par le rapport Taxes et la
     * synthèse mensuelle : un seul réglage pour tous les totaux.
     */
    public static function inclureLumipub(): bool
    {
        return (bool) config('taxes.inclure_lumipub', false);
    }

    /** Un exercice antérieur à la reprise n'est pas suivi (aucune donnée sûre). */
    public function estSuivi(int $annee): bool
    {
        return $annee >= $this->exerciceReprise();
    }

    public function estReprise(int $annee): bool
    {
        return $annee === $this->exerciceReprise();
    }

    /** Exercices proposés à l'écran : de la reprise à l'an prochain. */
    public function exercicesDisponibles(): array
    {
        return range(max($this->exerciceReprise(), (int) date('Y') + 1), $this->exerciceReprise());
    }

    // ══════════════════════════════════════════════════════════════
    //  CALCUL PANORA (ODP + TM, par commune et par mois)
    // ══════════════════════════════════════════════════════════════

    /**
     * Calcul de Panora pour une année : 12 appels mensuels au moteur unique
     * (TaxCalculationService::generateLines). Chaque mois prend le tarif en
     * vigueur ce mois-là ; l'ODP compte les mois calendaires, la TM les mois
     * de campagne qui commencent dans le mois — 12 mois = l'année, exactement.
     *
     * Le parc Panora (« on s'en tient aux panneaux qui existent sur Panora,
     * ce sont les vrais », 2026-11-16), HORS pieds Lumipub : « décoché »,
     * décision du même jour, comme la page Taxes (cf. inclureLumipub()).
     * La ligne d'axe, elle, n'est jamais taxée.
     *
     * @return array<int, array{odp: array<int,int>, tm: array<int,int>, base: array<string, array{panneaux:int, faces:int}>}>
     */
    public function calculPanora(int $annee): array
    {
        if (isset($this->memoCalcul[$annee])) {
            return $this->memoCalcul[$annee];
        }

        // 12 appels au moteur : mis en cache, la clé portant une empreinte du
        // parc, des campagnes et des tarifs — tout changement saisi par
        // l'application change l'empreinte, donc le calcul est refait.
        // Seule une écriture SQL brute qui ne touche ni updated_at ni le
        // nombre de lignes peut rester invisible : 10 minutes au plus.
        return $this->memoCalcul[$annee] = $this->enCache(
            "calcul.{$annee}",
            fn () => $this->calculerPanora($annee)
        );
    }

    private function calculerPanora(int $annee): array
    {
        $brut      = [];
        $physiques = [];   // commune_id → référence physique → [surface_face, faces]
        for ($mois = 1; $mois <= 12; $mois++) {
            $lignes = $this->calc->generateLines(
                TaxCalculationService::PERIOD_MONTHLY, $mois, $annee, ['inclure_lumipub' => self::inclureLumipub()]
            );
            foreach ($lignes as $l) {
                $cid  = (int) $l['commune_id'];
                $type = $l['type'] === TaxCalculationService::TYPE_TM ? 'tm' : 'odp';
                $brut[$cid][$type][$mois] = ($brut[$cid][$type][$mois] ?? 0) + (float) $l['amount'];

                // Base de la fiche (Dim / Nbr panneaux / Nbr faces) : un mât
                // physique par ligne ODP — les deux faces d'un double-face y
                // sont déjà fusionnées (faces_count), un pied Lumipub porte
                // ses faces (faces_pied).
                if ($type === 'odp') {
                    $ref = TaxCalculationService::referencePhysique((string) $l['reference']);
                    $physiques[$cid][$ref] = [
                        'surface' => (float) ($l['surface_face'] ?? $l['surface']),
                        'faces'   => TaxCalculationService::facesDeLigne($l),
                    ];
                }
            }
        }

        $resultat = [];
        foreach ($brut as $cid => $types) {
            $base = [];
            foreach ($physiques[$cid] ?? [] as $p) {
                $cle = $this->libelleSurface($p['surface']);
                $base[$cle]['surface']  = $p['surface'];
                $base[$cle]['panneaux'] = ($base[$cle]['panneaux'] ?? 0) + 1;
                $base[$cle]['faces']    = ($base[$cle]['faces'] ?? 0) + $p['faces'];
            }
            uasort($base, fn ($a, $b) => $a['surface'] <=> $b['surface']);

            $resultat[$cid] = [
                // FCFA entiers, arrondis au mois : les trimestres et l'année
                // sont des sommes de mois déjà arrondis, donc se raccordent.
                'odp'  => $this->moisArrondis($types['odp'] ?? []),
                'tm'   => $this->moisArrondis($types['tm'] ?? []),
                'base' => $base,
            ];
        }

        return $resultat;
    }

    /**
     * Prévu : TM des campagnes PLANIFIÉES (pas encore démarrées), par commune
     * et par trimestre. Jamais dans le solde (règle 5).
     *
     * @return array<int, array<int, int>> commune_id → [1..4 => montant]
     */
    public function prevuTM(int $annee): array
    {
        if (isset($this->memoPrevu[$annee])) {
            return $this->memoPrevu[$annee];
        }

        return $this->memoPrevu[$annee] = $this->enCache(
            "prevu.{$annee}",
            fn () => $this->calculerPrevu($annee)
        );
    }

    private function calculerPrevu(int $annee): array
    {
        $prevu = [];
        for ($t = 1; $t <= 4; $t++) {
            $lignes = $this->calc->generateLines(TaxCalculationService::PERIOD_QUARTERLY, $t, $annee, [
                'type'            => TaxCalculationService::TYPE_TM,
                'inclure_lumipub' => self::inclureLumipub(),
                'statuts_tm'      => [\App\Enums\CampaignStatus::PLANIFIE->value],
            ]);
            foreach ($lignes as $l) {
                $cid = (int) $l['commune_id'];
                $prevu[$cid][$t] = ($prevu[$cid][$t] ?? 0) + (float) $l['amount'];
            }
        }

        foreach ($prevu as $cid => $trims) {
            $prevu[$cid] = $this->trimestresComplets(array_map(fn ($v) => (int) round($v), $trims));
        }

        return $prevu;
    }

    /**
     * Cache court du calcul Panora, invalidé par l'empreinte des données
     * qui le fondent. Sans empreinte lisible (table absente…), pas de cache.
     */
    private function enCache(string $cle, \Closure $calcul): array
    {
        $empreinte = $this->empreinte();
        if ($empreinte === null) {
            return $calcul();
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "taxes.suivi.{$cle}.{$empreinte}",
            now()->addMinutes(10),
            $calcul
        );
    }

    private ?string $empreinte = null;

    /**
     * Ce qui change le calcul : panneaux (dont démantèlements), campagnes,
     * leurs panneaux, communes et historique des tarifs, et la règle
     * elle-même (TaxCalculationService modifié = nouveau déploiement).
     */
    private function empreinte(): ?string
    {
        if ($this->empreinte !== null) {
            return $this->empreinte;
        }

        try {
            $db = \Illuminate\Support\Facades\DB::connection();
            $morceaux = [
                $db->table('panels')->selectRaw('COUNT(*) n, MAX(updated_at) u, MAX(deleted_at) d')->first(),
                $db->table('campaigns')->selectRaw('COUNT(*) n, MAX(updated_at) u, MAX(deleted_at) d')->first(),
                $db->table('campaign_panels')->selectRaw('COUNT(*) n, MAX(id) i, MAX(updated_at) u')->first(),
                $db->table('communes')->selectRaw('COUNT(*) n, MAX(updated_at) u')->first(),
                $db->table('commune_tax_rate_history')->selectRaw('COUNT(*) n, MAX(updated_at) u')->first(),
                @filemtime(app_path('Services/TaxCalculationService.php')),
                @filemtime(__FILE__),
            ];
        } catch (\Throwable) {
            return null;
        }

        return $this->empreinte = md5(json_encode($morceaux));
    }

    // ══════════════════════════════════════════════════════════════
    //  L'EXERCICE — une ligne par mairie, comme la fiche
    // ══════════════════════════════════════════════════════════════

    /**
     * Toutes les mairies d'un exercice, triées par nom. Vide si l'exercice
     * est antérieur à la reprise.
     *
     * @return Collection<int, array> indexée par commune_id
     */
    public function exercices(int $annee): Collection
    {
        if (isset($this->memoExercices[$annee])) {
            return $this->memoExercices[$annee];
        }
        if (!$this->estSuivi($annee)) {
            return $this->memoExercices[$annee] = collect();
        }

        $reprise     = $this->estReprise($annee);
        $calcul      = $this->calculPanora($annee);
        $prevu       = $reprise ? [] : $this->prevuTM($annee);
        $precedent   = $reprise ? collect() : $this->exercices($annee - 1);
        $reprises    = TaxeReprise::where('exercice', $annee)->get()->keyBy('commune_id');
        $reglements  = TaxeReglement::where('exercice', $annee)
            ->orderBy('paid_at')->orderBy('id')
            ->get()
            ->groupBy('commune_id');

        $ids = collect(array_keys($calcul))
            ->merge($reprises->keys())
            ->merge($reglements->keys())
            ->merge($precedent->filter(fn ($e) => $e['solde'] !== 0)->keys())
            ->unique()
            ->all();

        $communes = Commune::whereIn('id', $ids)->get()->keyBy('id');

        $lignes = collect();
        foreach ($communes as $cid => $commune) {
            $lignes->put($cid, $this->construire(
                $commune,
                $annee,
                $reprise,
                $calcul[$cid] ?? null,
                $prevu[$cid] ?? null,
                $reprises->get($cid),
                $reglements->get($cid, collect()),
                $reprise ? 0 : (int) ($precedent->get($cid)['solde'] ?? 0),
            ));
        }

        $tri = $lignes->sortBy(fn ($e) => Commune::normalizeForMatch($e['commune']->name));

        return $this->memoExercices[$annee] = $tri;
    }

    /**
     * L'exercice d'une seule mairie — même calcul que la ligne du tableau
     * (une mairie sans rien sur l'exercice donne une ligne à zéro).
     */
    public function exercice(Commune $commune, int $annee): array
    {
        $ligne = $this->exercices($annee)->get($commune->id);
        if ($ligne) {
            return $ligne;
        }

        return $this->construire(
            $commune, $annee, $this->estReprise($annee), null, null, null, collect(), 0
        );
    }

    /**
     * Totaux d'un ensemble de lignes (tableau, onglet Abidjan / Intérieur).
     */
    public function totaux(Collection $lignes): array
    {
        $somme = fn (string $cle) => (int) $lignes->sum($cle);
        $trim  = fn (string $cle) => $this->trimestresComplets([
            1 => (int) $lignes->sum(fn ($l) => $l[$cle][1]),
            2 => (int) $lignes->sum(fn ($l) => $l[$cle][2]),
            3 => (int) $lignes->sum(fn ($l) => $l[$cle][3]),
            4 => (int) $lignes->sum(fn ($l) => $l[$cle][4]),
        ]);

        return [
            'mairies'    => $lignes->count(),
            'panneaux'   => (int) $lignes->sum('nb_panneaux'),
            'faces'      => (int) $lignes->sum('nb_faces'),
            'arrieres'   => $somme('arrieres'),
            'odp'        => $somme('odp'),
            'tm_trim'    => $trim('tm_trim'),
            'tm'         => $somme('tm'),
            'total_du'   => $somme('total_du'),
            'total_paye' => $somme('total_paye'),
            'solde'      => $somme('solde'),
            'reste'      => (int) $lignes->sum(fn ($l) => max(0, $l['solde'])),
            'exigible'   => $somme('exigible'),
            'prevu_tm'   => $somme('prevu_tm'),
            'controle'   => $somme('controle_total'),
        ];
    }

    /**
     * Sépare Abidjan et l'intérieur, comme les deux feuilles de la fiche.
     *
     * @return array{abidjan: Collection, interieur: Collection}
     */
    public function parZone(Collection $lignes): array
    {
        return [
            'abidjan'   => $lignes->filter(fn ($l) => $l['is_abidjan']),
            'interieur' => $lignes->reject(fn ($l) => $l['is_abidjan']),
        ];
    }

    /**
     * Libellés des statuts à l'écran et dans les documents — SOURCE UNIQUE
     * (2026-11-16 : « trouve des expressions simples et compréhensibles »).
     * [classe du badge, libellé, explication au survol].
     */
    public const LIBELLES_STATUT = [
        'a_saisir' => ['badge-orange', '✍ Réclamé à saisir', 'Ce que la mairie a réclamé pour l\'année de reprise n\'est pas encore saisi.'],
        'solde'    => ['badge-green',  '✓ À jour',           'Tout ce qui est dû est payé.'],
        'credit'   => ['badge-blue',   '↺ Payé en trop',     'La régie a payé plus que dû : la différence est déduite de l\'année suivante.'],
        'partiel'  => ['badge-orange', '◐ Payé en partie',   'Une partie est payée, il reste un solde.'],
        'non_paye' => ['badge-red',    '⏳ Rien payé',        'Aucun règlement saisi pour cette année.'],
        'rien_du'  => ['badge-gray',   '— Rien à payer',     'Aucune taxe due et aucun règlement.'],
    ];

    /**
     * Le statut à afficher : pendant l'année de reprise, une mairie dont le
     * montant réclamé n'est pas saisi est « à saisir » — sinon un versement
     * déjà enregistré la ferait apparaître « payé en trop ».
     */
    public static function statutAffiche(array $ligne): string
    {
        return !empty($ligne['a_saisir']) ? 'a_saisir' : $ligne['statut'];
    }

    /**
     * Statut lu par les écrans qui ont leurs propres filtres
     * (page Taxes : paye / partiel / non_paye / aucun, et a_saisir pendant
     * l'année de reprise — 2026-11-26). Lui passer statutAffiche(), pas le
     * statut brut. Les libellés affichés restent ceux de LIBELLES_STATUT.
     */
    public static function statutPageTaxes(string $statut): string
    {
        return match ($statut) {
            'solde', 'credit' => 'paye',
            'rien_du'         => 'aucun',
            default           => $statut,
        };
    }

    // ══════════════════════════════════════════════════════════════
    //  INTERNES
    // ══════════════════════════════════════════════════════════════

    private function construire(
        Commune $commune,
        int $annee,
        bool $reprise,
        ?array $calcul,
        ?array $prevu,
        ?TaxeReprise $saisie,
        Collection $reglements,
        int $arrieresReportes,
    ): array {
        $odpCalcMois = $calcul['odp'] ?? $this->moisVides();
        $tmCalcMois  = $calcul['tm']  ?? $this->moisVides();
        $odpCalcTrim = $this->parTrimestre($odpCalcMois);
        $tmCalcTrim  = $this->parTrimestre($tmCalcMois);
        $controle    = array_sum($odpCalcMois) + array_sum($tmCalcMois);

        if ($reprise) {
            // Exercice de reprise : le dû est ce que la mairie a réclamé.
            // L'ODP de la fiche est annuelle : elle est exigible d'emblée.
            $arrieres = (int) ($saisie?->arrieres ?? 0);
            $odp      = (int) ($saisie?->odp ?? 0);
            $odpTrim  = $this->trimestresComplets([1 => $odp]);
            $tmTrim   = $this->trimestresComplets($saisie ? $saisie->tmParTrimestre() : []);
        } else {
            $arrieres = $arrieresReportes;
            $odpTrim  = $odpCalcTrim;
            $odp      = array_sum($odpTrim);
            $tmTrim   = $tmCalcTrim;
        }

        $tm      = array_sum($tmTrim);
        $totalDu = $arrieres + $odp + $tm;

        $valides   = $reglements->filter(fn (TaxeReglement $r) => !$r->estAnnule());
        $totalPaye = (int) $valides->sum('montant');
        $solde     = $totalDu - $totalPaye;

        $prevuTrim = $this->trimestresComplets($prevu ?? []);
        $base      = $calcul['base'] ?? [];

        return [
            'commune'        => $commune,
            'commune_id'     => (int) $commune->id,
            'is_abidjan'     => $commune->isAbidjan(),
            'exercice'       => $annee,
            'mode'           => $reprise ? 'reprise' : 'calcul',
            'reprise_saisie' => $saisie !== null,
            'saisie'         => $saisie,
            'base'           => $base,
            'nb_panneaux'    => (int) collect($base)->sum('panneaux'),
            'nb_faces'       => (int) collect($base)->sum('faces'),
            'arrieres'       => $arrieres,
            'odp'            => $odp,
            'odp_trim'       => $odpTrim,
            'tm_trim'        => $tmTrim,
            'tm'             => $tm,
            'total_du'       => $totalDu,
            'reglements'     => $reglements,
            'nb_reglements'  => $valides->count(),
            'total_paye'     => $totalPaye,
            'solde'          => $solde,
            'exigible'       => $this->exigible($annee, $arrieres, $odpTrim, $tmTrim, $totalPaye),
            'prevu_trim'     => $prevuTrim,
            'prevu_tm'       => array_sum($prevuTrim),
            // Calcul de Panora, affiché pour contrôle pendant la reprise.
            'controle_odp'   => array_sum($odpCalcMois),
            'controle_tm'    => array_sum($tmCalcMois),
            'controle_total' => $controle,
            'controle_odp_mois' => $odpCalcMois,
            'controle_tm_mois'  => $tmCalcMois,
            'a_saisir'       => $reprise && $saisie === null && ($controle > 0 || $totalPaye > 0),
            'statut'         => $this->statut($totalDu, $totalPaye, $solde),
        ];
    }

    /**
     * Exigible à ce jour : ce que la mairie peut réclamer quand elle se
     * présente — arriérés, plus l'ODP et la TM des trimestres déjà commencés,
     * moins ce qui est payé. Exercice passé : tout ; exercice à venir : les
     * seuls arriérés. Jamais négatif.
     */
    private function exigible(int $annee, int $arrieres, array $odpTrim, array $tmTrim, int $paye): int
    {
        $anneeCourante = (int) now()->year;
        $trimestres = match (true) {
            $annee < $anneeCourante => 4,
            $annee > $anneeCourante => 0,
            default                 => (int) now()->quarter,
        };

        $du = $arrieres;
        for ($t = 1; $t <= $trimestres; $t++) {
            $du += $odpTrim[$t] + $tmTrim[$t];
        }

        return max(0, $du - $paye);
    }

    private function statut(int $totalDu, int $paye, int $solde): string
    {
        return match (true) {
            $totalDu === 0 && $paye === 0 => 'rien_du',
            $solde < 0                    => 'credit',
            $solde === 0                  => 'solde',
            $paye === 0                   => 'non_paye',
            default                       => 'partiel',
        };
    }

    /** @param array<int, float|int> $mois  @return array<int, int> [1..12] */
    private function moisArrondis(array $mois): array
    {
        $r = $this->moisVides();
        foreach ($mois as $m => $v) {
            $r[(int) $m] = (int) round((float) $v);
        }
        return $r;
    }

    /** @return array<int, int> */
    private function moisVides(): array
    {
        return array_fill(1, 12, 0);
    }

    /** @param array<int, int> $mois [1..12]  @return array<int, int> [1..4] */
    private function parTrimestre(array $mois): array
    {
        $t = [];
        for ($q = 1; $q <= 4; $q++) {
            $t[$q] = (int) (($mois[$q * 3 - 2] ?? 0) + ($mois[$q * 3 - 1] ?? 0) + ($mois[$q * 3] ?? 0));
        }
        return $t;
    }

    /** @return array<int, int> [1..4], les trimestres absents à 0 */
    private function trimestresComplets(array $t): array
    {
        return [
            1 => (int) ($t[1] ?? 0),
            2 => (int) ($t[2] ?? 0),
            3 => (int) ($t[3] ?? 0),
            4 => (int) ($t[4] ?? 0),
        ];
    }

    private function libelleSurface(float $surface): string
    {
        return rtrim(rtrim(number_format($surface, 2, '.', ''), '0'), '.');
    }
}

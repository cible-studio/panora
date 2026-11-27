<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Commune;
use App\Models\CommuneTaxPayment;
use App\Models\Panel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * TaxCalculationService — calcul des taxes communales TM / ODP / DB
 * selon les règles d'éligibilité spécifiées (Évolution 4).
 *
 * Règles d'éligibilité (synthèse spec) :
 *
 *   | Statut panneau                  | TM | ODP |
 *   | ──────────────────────────────  | ── | ─── |
 *   | Exploité avec campagne active   | ✓  |  ✓  |
 *   | Interne/maint. AVEC campagne    | ✓  |  ✓  |
 *   | Interne/maint. SANS campagne    | ✗  |  ✓  |
 *   | Vacant                          | ✗  |  ✓  |
 *
 * → ODP : tous les panneaux internes (catalogue), peu importe le
 *   statut ou l'occupation.
 * → TM : uniquement si une campagne ACTIVE existe sur la période,
 *   couvre le panneau (interne ou maintenance peu importe).
 *
 * Les tarifs sont résolus via Commune::ratesAt(date) — utilise
 * l'historique tarifaire pour garantir la cohérence rétroactive.
 */
class TaxCalculationService
{
    public const TYPE_TM  = 'tm';
    public const TYPE_ODP = 'odp';
    public const TYPES    = [self::TYPE_TM, self::TYPE_ODP];

    public const PERIOD_MONTHLY    = 'mensuel';
    public const PERIOD_QUARTERLY  = 'trimestriel';
    public const PERIOD_ANNUAL     = 'annuel';
    public const PERIOD_CUSTOM     = 'personnalise';

    public function __construct(protected TaxPeriodCalculator $period = new TaxPeriodCalculator()) {}

    /**
     * Produit la liste détaillée des lignes de taxes pour une période
     * donnée — une ligne par (panneau × type taxe éligible). Source
     * unique de vérité pour la vue détaillée, les rapports et les
     * exports.
     *
     * @param  string $periodType    'mensuel' | 'trimestriel' | 'annuel'
     * @param  int    $periodValue   1-12 (mois) | 1-4 (trim) | 0 (annuel)
     * @param  int    $year
     * @param  array  $filters       ['commune_id'=>?, 'client_id'=>?, 'campaign_id'=>?,
     *                               'type'=>?, 'exclure_maintenance'=>bool]
     *                               exclure_maintenance : false (défaut) = les panneaux
     *                               en maintenance restent comptés — l'ODP court tant
     *                               que le mât occupe le sol (règle du 2026-11-13).
     *                               exclure_categories : ids de PanelCategory à retirer
     *                               (ex. Chevalet). Vide = toutes les catégories. Les
     *                               panneaux sans catégorie sont toujours gardés.
     * @return Collection            Collection de lignes :
     *      {commune, commune_id, panel_id, reference, name, dimensions,
     *       format_id, surface, type, statut, client_name, client_id,
     *       campaign_name, campaign_id, period_start, period_end, months,
     *       unit, rate, rate_applied, amount, + faces_count/faces_refs
     *       sur les lignes ODP issues d'un mât double-face}
     */
    public function generateLines(string $periodType, int $periodValue, int $year, array $filters = [], ?int $periodEndValue = null): Collection
    {
        [$periodStart, $periodEnd, $months] = $this->resolvePeriod($periodType, $periodValue, $year, $periodEndValue);

        // Les colonnes d'axe n'existent qu'après la migration (cf. AxeService) :
        // sans ce garde-fou, l'écran casse sur un environnement non migré.
        $colonnesAxe = app(\App\Services\Axes\AxeService::class)->colonnesPresentes();

        // Pré-charge toutes les communes (pour leur historique tarifaire)
        // — peu nombreuses (~30) donc OK en mémoire.
        $communes = Commune::all()->keyBy('id');

        // Pré-charge les rates au début de la période pour chaque commune.
        // Choix design : on utilise les tarifs du début de période. Si on
        // change de tarif au milieu d'un mois, le calcul reste sur l'ancien
        // — cohérent avec la pratique fiscale (date d'émission de la base).
        // 2026-11-18 — Deux jeux par commune : panneaux non éclairés et
        // éclairés (case « Éclairé » de la fiche). Sans tarif éclairé, les
        // deux sont identiques (Commune::ratesAt, source unique).
        $ratesByCommune = $communes->mapWithKeys(
            fn($c) => [$c->id => [
                'normal'  => $c->ratesAt($periodStart),
                'eclaire' => $c->ratesAt($periodStart, true),
            ]]
        );

        // 2026-11-26 — Côté mairies, MOIS PAR MOIS (règle confirmée le 26/11) :
        // chaque mois prend le tarif en vigueur ce mois-là, comme le Suivi des
        // mairies (12 appels mensuels). On ne découpe que les communes dont le
        // tarif CHANGE pendant la période : pour toutes les autres, le calcul
        // reste exactement celui d'avant.
        $moisDeLaPeriode = $this->moisDeLaPeriode($periodStart, $periodEnd);
        $tarifsMensuels  = [];   // commune_id → 'normal'|'eclaire' → [tarifs du mois i]
        if (count($moisDeLaPeriode) > 1) {
            $communesQuiChangent = \App\Models\CommuneTaxRateHistory::query()
                ->where('effective_from', '>', $periodStart->toDateString())
                ->where('effective_from', '<=', $periodEnd->toDateString())
                ->distinct()->pluck('commune_id');
            foreach ($communesQuiChangent as $cid) {
                if (!isset($communes[$cid])) continue;
                foreach (['normal' => false, 'eclaire' => true] as $cle => $eclaire) {
                    $tarifsMensuels[$cid][$cle] = array_map(
                        fn ($m) => $communes[$cid]->ratesAt($m[0], $eclaire),
                        $moisDeLaPeriode
                    );
                }
            }
        }

        // Charge panneaux avec leur commune, format, dimensions.
        // ═══ Panneaux démantelés — prorata (2026-11-13) ═══
        // Le démantèlement est modélisé par le soft delete : deleted_at = la
        // date où le mât a quitté le sol (cf. moisExistencePanneau, ligne 646).
        // Le prorata EXISTAIT déjà — moisODPDansPeriode reçoit $panel->deleted_at
        // plus bas — mais `whereNull('deleted_at')` l'empêchait d'arriver ici :
        // la liste n'affichait jamais un panneau démantelé, alors que les
        // totaux par commune (panneauxPourCalcul, withTrashed) le comptaient.
        // Les deux écrans ne se raccordaient donc pas.
        // On reprend les démantelés dont la vie croise encore la période.
        $panelsQuery = Panel::withTrashed()
            ->with(['commune:id,name', 'format:id,name,width,height,surface'])
            ->where(fn ($q) => $q->whereNull('deleted_at')
                ->orWhere('deleted_at', '>=', $periodStart->toDateString()))
            // Lumipub (2026-11-13) : une ligne par PIED, jamais la ligne
            // d'axe. Exclus par défaut, réintégrés par la case « Lumipub ».
            ->avecTaxesAutomatiques(!empty($filters['inclure_lumipub']));

        // ═══ Maintenance — n'écarte plus le panneau (2026-11-13) ═══
        // RÈGLE VALIDÉE PAR ÉCRIT : « l'ODP reste due tant que le mât occupe
        // le sol ». Une panne ne retire pas le mât : l'ODP court.
        // Avant (TX-12), le filtre lisait panels.status — l'état DU JOUR. Un
        // panneau passé en maintenance aujourd'hui disparaissait de janvier,
        // février, mars… alors qu'il y était debout et occupé. Même défaut
        // que le sélecteur du devis : un état courant qui juge le passé.
        // La TM, elle, se régule seule : elle exige une campagne sur la
        // période (isEligible), donc un panneau en panne et vide donne 0.
        // 'exclure_maintenance' reste disponible pour retrouver l'ancienne
        // vue. (L'ancien drapeau 'include_maintenance' a été retiré le
        // 2026-11-17 : il n'avait plus d'effet depuis le 13/11, et deux
        // noms opposés pour une seule case trompaient la lecture.)
        if (!empty($filters['exclure_maintenance'])) {
            $panelsQuery->whereNotIn('status', ['maintenance']);
        }
        if (!empty($filters['commune_id'])) {
            $panelsQuery->where('commune_id', $filters['commune_id']);
        }
        // 2026-10-06 — Filtre par catégorie de panneau (demande MP : sortir
        // l'ODP « tout sauf les chevalets »). Ne change aucun montant :
        // retire seulement des panneaux de la liste.
        if (!empty($filters['exclure_categories'])) {
            $exclues = array_map('intval', (array) $filters['exclure_categories']);
            $panelsQuery->where(fn ($q) => $q->whereNull('category_id')
                ->orWhereNotIn('category_id', $exclues));
        }
        $panels = $panelsQuery->get();

        // Pour la règle TM : on a besoin de savoir si chaque panneau a
        // une campagne active sur la période. Une seule requête pour tous.
        $campaignAssignmentMap = $this->buildCampaignAssignmentMap(
            $panels->pluck('id')->all(),
            $periodStart,
            $periodEnd,
            $filters['campaign_id'] ?? null,
            $filters['client_id']   ?? null
        );

        // ═══ TM : TOUTES les campagnes du panneau — 2026-11-16 ═══
        // RÈGLE VALIDÉE PAR ÉCRIT : « toutes les campagnes d'un panneau sont
        // comptées, et chaque mois de campagne va dans le trimestre où il
        // commence ». Avant, la TM ne lisait que $campaignAssignmentMap, qui
        // ne garde que la campagne LA PLUS RÉCENTE : sur une année, un
        // panneau loué de janvier à mars puis de juin à décembre ne payait
        // que la seconde. La carte reste utilisée pour l'ODP (client affiché)
        // et le filtre client/campagne ; la TM a désormais sa propre liste.
        // 'statuts_tm' sert au « prévu » (campagnes planifiées), cf.
        // TaxeExerciceService ; par défaut, actif + terminé comme avant.
        $campagnesTM = $this->buildCampaignAssignmentsList(
            $panels->pluck('id')->all(),
            $periodStart,
            $periodEnd,
            $filters['campaign_id'] ?? null,
            $filters['client_id']   ?? null,
            $filters['statuts_tm']  ?? null
        );

        $filterType = $filters['type'] ?? null;

        // ═══ Faces d'un pied d'axe — 2026-11-13 ═══
        // Mesuré en production : le format d'un axe porte la surface d'UNE
        // FACE. Les 12 axes Lumipub ont un format « 3m² » alors que leur
        // nom annonce 3 faces par pied → un pied, c'est 9 m², pas 3.
        // Un seul appel pour tout le lot (cf. AxeService::facesParLot).
        $facesParPied = $colonnesAxe
            ? app(\App\Services\Axes\AxeService::class)->facesParLot($panels->pluck('id')->all())
            : [];

        $lines = collect();
        foreach ($panels as $panel) {
            $commune = $communes[$panel->commune_id] ?? null;
            if (!$commune) continue;
            $rates = $ratesByCommune[$commune->id][$panel->is_lit ? 'eclaire' : 'normal'];

            // Si filtre client/campagne actif → on ne garde le panneau que
            // s'il a une campagne assignée correspondante. Sinon (pas de
            // filtre client/campagne) on garde tous les panneaux pour
            // ODP et on filtre TM individuellement.
            $assignment = $campaignAssignmentMap[$panel->id] ?? null;
            $hasClientCampaignFilter = !empty($filters['client_id']) || !empty($filters['campaign_id']);
            if ($hasClientCampaignFilter && !$assignment && empty($campagnesTM[$panel->id])) continue;

            // Surface TAXABLE = surface d'une face × nombre de faces.
            // Vaut 1 face pour tout panneau ordinaire : rien ne change
            // hors des pieds d'axe.
            $faces      = $facesParPied[$panel->id] ?? 1;
            // 2026-11-27 (point 5 validé) : même surface que la facture
            // (PanelFormat::surface_m2 = largeur × hauteur, sinon la surface saisie).
            $surfaceUne = (float) ($panel->format?->surface_m2 ?? 0);
            $surface    = $surfaceUne * $faces;
            $dimensions = $panel->format?->name ?? '—';

            foreach (self::TYPES as $type) {
                if ($filterType && $filterType !== $type) continue;

                $unitRate = (float) ($rates[$type] ?? 0);
                // 2026-11-26 — TM : base nationale (config billing.tm_default,
                // 1 000 F/m²/mois) quand la commune n'a pas de tarif TM, comme
                // sur les factures (règle confirmée le 26/11 ; avant : 0).
                if ($type === self::TYPE_TM && $unitRate <= 0) {
                    $unitRate = self::tmParDefaut();
                }
                if ($unitRate <= 0) continue; // pas tarif → ligne ignorée

                // TM : une ligne par campagne (2026-11-16). ODP : une ligne
                // par panneau, portant la campagne la plus récente pour info.
                $occurrences = $type === self::TYPE_TM
                    ? ($campagnesTM[$panel->id] ?? [])
                    : ($this->isEligible($panel, $type, $assignment) ? [$assignment] : []);

                foreach ($occurrences as $occurrence) {

                // FIX TX-3 (2026-06-22, validé par patronne) — RÉVERSE DU TX-1.
                // Les tarifs ODP/TM stockés dans communes.odp_rate / tm_rate
                // sont en réalité MENSUELS (FCFA/m²/MOIS), pas annuels.
                // Convention officielle CIBLE CI documentée dans le seeder
                // CommuneTaxRatesSeeder.

                // ═══ RÈGLES MÉTIER VALIDÉES PAR ÉCRIT — 2026-07-29 ═══
                //
                // TM : nombre de "mois de date à date ENTAMÉS strictement"
                //      depuis le début de la campagne, dans la période filtre.
                //      Exemple : 15/03 → 30/04 = 2 mois (anniv 15/04 dépassé
                //      strictement par 30/04).
                //
                // ODP : facturation au MOIS CALENDAIRE. 1 seul jour dans
                //       le mois = mois entier compté (le panneau occupe
                //       le domaine public ce mois-là).
                //       TX-14 (2026-09-24) : règle établie sur la
                //       déclaration réelle de CIBLE, cf. plus bas.
                //
                // Historique : avant TX-9 (2026-07-29), la TM comptait les
                // mois calendaires touchés (1 jour dans mars + 1 jour dans
                // avril = 2 mois). L'ODP comptait aussi les mois. La nouvelle
                // règle est plus juste comptablement et alignée avec la
                // pratique terrain du MP (validée par la patronne).
                //
                // 2026-11-16 — Les mois se comptent depuis le début de LA
                // CAMPAGNE et chacun va dans la période où il commence
                // (moisTMCommencantDansPeriode). Avant, moisTMDansPeriode
                // recomptait depuis le 1er jour de la période : la somme des
                // 12 mois dépassait l'année. Sur une campagne contenue dans
                // la période (cas courant), le résultat ne change pas.
                if ($type === self::TYPE_TM
                    && !empty($occurrence['campaign_start'])
                    && !empty($occurrence['campaign_end'])) {
                    $lineMonths = $this->period->moisTMCommencantDansPeriode(
                        $occurrence['campaign_start'],
                        $occurrence['campaign_end'],
                        $periodStart,
                        $periodEnd
                    );
                    $rateApplied = $unitRate;   // tarif mensuel appliqué
                    $unitLabel   = 'mois';       // libellé pour l'affichage
                } elseif ($type === self::TYPE_ODP) {
                    // TX-10 (2026-09-23) — On part de odp_start_date, PAS de
                    // created_at. created_at = date de SAISIE dans Panora, or
                    // le parc existait physiquement bien avant l'app : s'y
                    // fier sous-facturait tous les panneaux historiques.
                    //   odp_start_date NULL → panneau préexistant, ODP due
                    //                         sur toute la période demandée.
                    //   odp_start_date remplie → ODP due à partir de là.
                    // (auto-remplie à la création — cf. Panel::booted())
                    $lineMonths = $this->period->moisODPDansPeriode(
                        $panel->odp_start_date ?? $periodStart,
                        $panel->deleted_at,
                        $periodStart,
                        $periodEnd
                    );
                    // TX-14 (2026-09-24) — RÈGLE ÉTABLIE SUR PIÈCE :
                    //   ODP = tarif mensuel × m² × nb de MOIS
                    // Source : la déclaration réelle de CIBLE,
                    // docs/ODP 2024 SAN PEDRO.xlsx (13/12/2024), dont les
                    // formules sont « surface × 12 × quantité × tarif »
                    // avec une colonne « NB MOIS » = 12 pour l'année.
                    // Le tarif s'applique tel quel (pas de ×3 : TX-9 le
                    // multipliait par 3 sur 4 trimestres, ce qui revenait
                    // déjà à 12 mois mais affichait un tarif faux).
                    // Le paiement trimestriel est une cadence de règlement
                    // (4 versements de 3 mois), pas un forfait.
                    $rateApplied = $unitRate;
                    $unitLabel   = 'mois';
                } else {
                    // Garde-fou : si TM sans dates campagne connues (rare).
                    $lineMonths  = $months;
                    $rateApplied = $unitRate;
                    $unitLabel   = 'mois';
                }

                if ($lineMonths === 0) continue;
                $amount = round($rateApplied * $surface * $lineMonths, 2);

                // Tarif qui change pendant la période : chaque mois à son tarif.
                $cleTarif = $panel->is_lit ? 'eclaire' : 'normal';
                if (isset($tarifsMensuels[$commune->id][$cleTarif])) {
                    $somme = 0.0;
                    $moisComptes = 0;
                    foreach ($moisDeLaPeriode as $i => [$debutMois, $finMois]) {
                        $tarifMois = (float) ($tarifsMensuels[$commune->id][$cleTarif][$i][$type] ?? 0);
                        if ($type === self::TYPE_TM && $tarifMois <= 0) {
                            $tarifMois = self::tmParDefaut();
                        }
                        $n = $this->moisDeLaLigneDans($type, $occurrence, $panel, $debutMois, $finMois, $months);
                        $somme += $tarifMois * $surface * $n;
                        $moisComptes += $n;
                    }
                    if ($moisComptes > 0) {
                        $amount      = round($somme, 2);
                        $rateApplied = $somme / ($surface * $moisComptes);   // tarif moyen, pour l'affichage
                    }
                }

                $lines->push([
                    'commune'        => $commune->name,
                    'commune_id'     => $commune->id,
                    'panel_id'       => $panel->id,
                    'reference'      => $panel->reference,
                    'name'           => $panel->name,
                    'dimensions'     => $dimensions,
                    // TX-12 — nécessaire au regroupement par format du
                    // dashboard /admin/taxes (TaxController::calcul()).
                    'format_id'      => $panel->format_id,
                    'surface'        => $surface,
                    'type'           => $type,
                    'statut'         => $panel->status?->value ?? 'libre',
                    'client_name'    => $occurrence['client_name']    ?? null,
                    'client_id'      => $occurrence['client_id']      ?? null,
                    'campaign_name'  => $occurrence['campaign_name']  ?? null,
                    'campaign_id'    => $occurrence['campaign_id']    ?? null,
                    // FIX 2026-06-26 — Vraies dates de campagne (null pour les
                    // lignes ODP sans campagne). Affichées dans la colonne
                    // "Période campagne" du détail / PDF / Excel.
                    'campaign_start' => $occurrence['campaign_start'] ?? null,
                    'campaign_end'   => $occurrence['campaign_end']   ?? null,
                    'period_start'   => $periodStart,
                    'period_end'     => $periodEnd,
                    'months'         => $lineMonths,        // Nb d'unités (mois pour TM, trimestres pour ODP)
                    'unit'           => $unitLabel,         // 'mois' | 'trimestre' (pour l'affichage)
                    'rate'           => $unitRate,          // Tarif mensuel stocké (référence)
                    'rate_applied'   => $rateApplied,       // Tarif effectivement appliqué (= tarif brut depuis TX-12)
                    'amount'         => $amount,
                    // 2026-11-13 — De quoi lire la ligne sans ouvrir la fiche :
                    // un pied d'axe Lumipub, et un mât démantelé en cours de
                    // période (le montant est déjà calculé au prorata).
                    'est_pied'       => $colonnesAxe && !empty($panel->axe_id),
                    'demantele_le'   => $panel->deleted_at,
                    // Faces du pied (2026-11-13) : la surface taxable vaut
                    // surface_une_face × faces. Remontée pour être lue —
                    // elle vient du NOM de l'axe, donc une erreur de saisie
                    // doit pouvoir se repérer sur la ligne.
                    'faces_pied'     => $faces,
                    'surface_face'   => $surfaceUne,
                ]);
                } // foreach $occurrences
            }
        }

        // TX-10 — L'ODP se paie par PANNEAU physique, pas par face.
        return $this->fusionnerFacesODP($lines);
    }

    /**
     * Référence "physique" d'un panneau : on retire le suffixe de face.
     *
     * Convention Panora : un panneau double-face est saisi comme deux
     * enregistrements dont la référence ne diffère que par un suffixe
     * A / B collé derrière le numéro (ABG-001A + ABG-001B).
     *
     *   ABG-001A    → ABG-001
     *   CDYT2-001B  → CDYT2-001
     *   SPBS-01     → SPBS-01   (pas de suffixe lettre → inchangé)
     *   ABG-PAN-01  → ABG-PAN-01
     *
     * Le chiffre obligatoire devant le A/B évite de mutiler une
     * référence qui finirait légitimement par une lettre.
     */
    public static function referencePhysique(string $reference): string
    {
        $base = preg_replace('/(\d)[AB]$/i', '$1', trim($reference));

        return $base !== null && $base !== '' ? $base : trim($reference);
    }

    /**
     * ═══ RÈGLE MÉTIER VALIDÉE PAR ÉCRIT — 2026-09-23 (patronne) ═══
     *
     * « Les panneaux à double face, dans Panora on considère chaque face
     *   comme un panneau, mais en réalité c'est un seul panneau : l'ODP
     *   se paye pour un, pas pour les faces. »
     *
     * L'ODP (Occupation du Domaine Public) taxe l'EMPRISE AU SOL : un mât
     * double-face occupe le même trottoir qu'un mono-face. On facturait
     * donc 2× le même panneau (2 lignes × 12 m² = 24 m² au lieu de 12).
     *
     * Ce qui est fusionné :
     *   - les lignes ODP uniquement — la TM (Taxe Municipale) reste par
     *     face, car elle taxe l'AFFICHAGE : 2 faces = 2 publicités = 2 TM.
     *   - regroupement par (commune, référence physique).
     *
     * Ce qui est retenu pour la ligne fusionnée :
     *   - surface : celle d'UNE face (la plus grande si les faces
     *     diffèrent — prudence fiscale, on ne sous-déclare pas).
     *   - trimestres : le max du groupe (le panneau physique existe dès
     *     que sa première face existe).
     *   - montant : recalculé, jamais additionné.
     *
     * Un panneau orphelin (ex : CDY-037B sans CDY-037A) reste seul dans
     * son groupe → aucune fusion, sa référence d'origine est conservée
     * telle quelle pour ne pas dérouter le comptable.
     */
    private function fusionnerFacesODP(Collection $lines): Collection
    {
        $odp = $lines->filter(fn($l) => ($l['type'] ?? null) === self::TYPE_ODP);
        if ($odp->count() < 2) {
            return $lines;
        }

        $autres  = $lines->reject(fn($l) => ($l['type'] ?? null) === self::TYPE_ODP);
        $fusions = collect();

        $groupes = $odp->groupBy(
            fn($l) => $l['commune_id'] . '|' . self::referencePhysique($l['reference'])
        );

        foreach ($groupes as $groupe) {
            // Tri par référence → la face A sert toujours de ligne pivot.
            // Rend le résultat déterministe quel que soit l'ordre SQL.
            $groupe = $groupe->sortBy('reference')->values();
            $ligne  = $groupe->first();

            if ($groupe->count() > 1) {
                $ligne['surface']   = (float) $groupe->max('surface');
                $ligne['months']    = (int) $groupe->max('months');
                $ligne['reference'] = self::referencePhysique($ligne['reference']);
                $ligne['amount']    = round(
                    $ligne['rate_applied'] * $ligne['surface'] * $ligne['months'],
                    2
                );

                // Traçabilité : affichée dans le détail / l'export Excel
                // pour que le comptable retrouve les faces d'origine.
                $ligne['faces_count'] = $groupe->count();
                $ligne['faces_refs']  = $groupe->pluck('reference')->all();
                $ligne['faces_panel_ids'] = $groupe->pluck('panel_id')->all();

                // Si la face pivot n'a pas de campagne mais l'autre si,
                // on remonte l'info plutôt que d'afficher un trou.
                foreach (['client_name', 'client_id', 'campaign_name', 'campaign_id',
                          'campaign_start', 'campaign_end'] as $cle) {
                    if (($ligne[$cle] ?? null) === null) {
                        $ligne[$cle] = $groupe->pluck($cle)->filter()->first();
                    }
                }
            }

            $fusions->push($ligne);
        }

        return $autres->merge($fusions)->values();
    }

    /**
     * Règle d'éligibilité par type. Réutilise une assignment précomputée
     * (résultat de buildCampaignAssignmentMap) pour éviter les requêtes
     * N+1 sur le statut campagne.
     */
    public function isEligible(Panel $panel, string $type, ?array $assignment): bool
    {
        return match ($type) {
            self::TYPE_TM  => $assignment !== null,  // campagne active requise
            self::TYPE_ODP => true,                  // tous panneaux internes
            default        => false,
        };
    }

    /**
     * Map panel_id → ['campaign_id'=>, 'campaign_name'=>, 'client_id'=>,
     * 'client_name'=>] pour les campagnes actives ou en cours sur la
     * période. Une seule requête pour tous les panneaux passés en
     * paramètre.
     *
     * Une campagne "active sur la période" = statut ACTIF, dates qui se
     * chevauchent avec [periodStart, periodEnd] (même partiellement).
     *
     * Si un panneau a plusieurs campagnes sur la période (rare mais
     * possible avec extensions), on prend la plus récente.
     */
    private function buildCampaignAssignmentMap(array $panelIds, Carbon $periodStart, Carbon $periodEnd, ?int $campaignId = null, ?int $clientId = null): array
    {
        if (empty($panelIds)) return [];

        $q = \DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaign_panels.panel_id', $panelIds)
            // FIX 2026-06-26 — Avant : seul 'actif' était pris en compte → les
            // campagnes TERMINEES (ex. AXA 01/02→28/02 statut "Terminée")
            // n'apparaissaient plus dans le détail TM des trimestres passés.
            // Or la TM est due pour toute occupation effective des panneaux,
            // qu'elle soit en cours ou archivée. On inclut donc 'termine'.
            ->whereIn('campaigns.status', [CampaignStatus::ACTIF->value, CampaignStatus::TERMINE->value])
            ->where('campaigns.start_date', '<=', $periodEnd->toDateString())
            ->where('campaigns.end_date',   '>=', $periodStart->toDateString())
            ->whereNull('campaigns.deleted_at')
            ->select(
                'campaign_panels.panel_id',
                'campaigns.id as campaign_id',
                'campaigns.name as campaign_name',
                'campaigns.start_date',
                'campaigns.end_date',
                'clients.id as client_id',
                'clients.name as client_name'
            );

        if ($campaignId) $q->where('campaigns.id', $campaignId);
        if ($clientId)   $q->where('campaigns.client_id', $clientId);

        $rows = $q->orderByDesc('campaigns.start_date')->get();

        $map = [];
        foreach ($rows as $r) {
            // Premier match = plus récent (orderByDesc), on garde celui-là.
            // ⚠ Une seule campagne par panneau : convient à l'ODP (client
            // affiché), PAS à la TM — cf. buildCampaignAssignmentsList().
            if (!isset($map[$r->panel_id])) {
                $map[$r->panel_id] = [
                    'campaign_id'    => (int) $r->campaign_id,
                    'campaign_name'  => $r->campaign_name,
                    // FIX 2026-06-26 — vraies dates de la campagne (pas le filtre).
                    // Affichées dans la colonne "Période campagne" du détail.
                    'campaign_start' => $r->start_date ? Carbon::parse($r->start_date) : null,
                    'campaign_end'   => $r->end_date   ? Carbon::parse($r->end_date)   : null,
                    'client_id'      => $r->client_id ? (int) $r->client_id : null,
                    'client_name'    => $r->client_name,
                ];
            }
        }
        return $map;
    }

    /**
     * TM (2026-11-16) — TOUTES les campagnes de chaque panneau qui touchent
     * la période, et pas seulement la plus récente.
     *
     * Map panel_id → liste de [campaign_id, campaign_name, campaign_start,
     * campaign_end, client_id, client_name], une entrée par campagne.
     *
     * @param  array|null $statuts  statuts de campagne retenus ; défaut :
     *                              actif + terminé (règle du 2026-06-26).
     *                              ['planifie'] sert au « prévu ».
     * @return array<int, array<int, array>>
     */
    private function buildCampaignAssignmentsList(array $panelIds, Carbon $periodStart, Carbon $periodEnd, ?int $campaignId = null, ?int $clientId = null, ?array $statuts = null): array
    {
        if (empty($panelIds)) return [];

        $statuts ??= [CampaignStatus::ACTIF->value, CampaignStatus::TERMINE->value];

        // Campagnes du panneau qui touchent la période (mêmes filtres pour
        // les panneaux encore rattachés et pour ceux qui en sont sortis).
        $campagnes = function ($q) use ($statuts, $periodStart, $periodEnd, $campaignId, $clientId) {
            $q->whereIn('campaigns.status', $statuts)
                ->whereNotNull('campaigns.start_date')
                ->whereNotNull('campaigns.end_date')
                ->where('campaigns.start_date', '<=', $periodEnd->toDateString())
                ->where('campaigns.end_date',   '>=', $periodStart->toDateString())
                ->whereNull('campaigns.deleted_at');
            if ($campaignId) $q->where('campaigns.id', $campaignId);
            if ($clientId)   $q->where('campaigns.client_id', $clientId);

            return $q;
        };
        $colonnes = ['campaigns.id as campaign_id', 'campaigns.name as campaign_name', 'campaigns.start_date',
            'campaigns.end_date', 'clients.id as client_id', 'clients.name as client_name'];

        // ═══ Campagne tenue par la LIGNE d'un axe — 2026-11-24 (règle validée) ═══
        // Les contrats signés avant le découpage en pieds tiennent la ligne de
        // l'axe. Le 13/11, seules les campagnes en cours sont passées sur les
        // pieds (axes:decouper-campagnes) ; les terminées (KOTON, VOODOO à
        // Cocody) sont restées sur la ligne. Or la ligne d'axe n'est jamais
        // taxée (Panel::scopeAvecTaxesAutomatiques) : leur TM n'était plus
        // comptée nulle part. Règle : une campagne qui tient la ligne d'un axe
        // compte pour la TM de CHACUN de ses pieds, sur ses dates, comme une
        // campagne découpée. Un pied rattaché lui-même l'emporte (compté une fois).
        $piedsParAxe = [];
        foreach (app(\App\Services\Axes\AxeService::class)->axeParPied($panelIds) as $pied => $axe) {
            $piedsParAxe[(int) $axe][] = (int) $pied;
        }
        $idsLus = array_values(array_unique(array_merge($panelIds, array_keys($piedsParAxe))));

        // "campagne-panneau" => [ligne de campagne, panel_id, rattaché aujourd'hui ?, clé de son historique]
        $paires = [];
        $retenir = function (iterable $lignes, bool $rattache, bool $lignesDAxe) use (&$paires, $piedsParAxe) {
            foreach ($lignes as $r) {
                $source = (int) $r->panel_id;
                if (isset($piedsParAxe[$source]) !== $lignesDAxe) continue;
                foreach ($piedsParAxe[$source] ?? [$source] as $panelId) {
                    $paires[$r->campaign_id . '-' . $panelId] ??= [$r, $panelId, $rattache, $r->campaign_id . '-' . $source];
                }
            }
        };

        $actuels = $campagnes(\DB::table('campaign_panels')
            ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
            ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
            ->where('campaign_panels.type', 'interne')
            ->whereIn('campaign_panels.panel_id', $idsLus))
            ->select(array_merge(['campaign_panels.panel_id'], $colonnes))
            ->get();
        $retenir($actuels, true, false);

        // ═══ Panneaux SORTIS d'une campagne — 2026-11-24 (règle validée) ═══
        // Retirer un panneau le détache de la campagne : il disparaissait de
        // la TM pour TOUTES les périodes, même les mois où il était affiché
        // (YOPCAIS-01A, BNI 2026, retiré le 24/11 et démantelé le 27/07).
        // L'historique des mouvements (depuis le 16/11) le retrouve, et la
        // TM court jusqu'à sa date réelle de sortie.
        $mouvements = collect();
        $retires = collect();
        if (self::historiquePresent()) {
            $retires = $campagnes(\DB::table('campaign_panel_mouvements')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panel_mouvements.campaign_id')
                ->leftJoin('clients', 'clients.id', '=', 'campaigns.client_id')
                ->where('campaign_panel_mouvements.sens', \App\Models\CampaignPanelMouvement::RETRAIT)
                ->whereIn('campaign_panel_mouvements.panel_id', $idsLus))
                ->select(array_merge(['campaign_panel_mouvements.panel_id'], $colonnes))
                ->distinct()
                ->get();
            $retenir($retires, false, false);
        }

        // Lignes d'axe en dernier : le pied rattaché lui-même garde la main.
        $retenir($actuels, true, true);
        $retenir($retires, false, true);

        if (self::historiquePresent()) {
            $champs = ['campaign_id', 'panel_id', 'sens', 'motif', 'source', 'created_at'];
            if (self::dateEffetPresente()) $champs[] = 'effet_le';
            $mouvements = \DB::table('campaign_panel_mouvements')
                ->whereIn('panel_id', $idsLus)
                ->whereIn('campaign_id', array_unique(array_map(fn ($p) => (int) $p[0]->campaign_id, $paires)) ?: [0])
                ->orderBy('id')
                ->get($champs)
                ->groupBy(fn ($m) => $m->campaign_id . '-' . $m->panel_id);
        }

        // Démantèlement : la TM s'arrête aussi à la date où le mât a quitté le sol.
        $demonteLe = Panel::withTrashed()->whereIn('id', $panelIds)->whereNotNull('deleted_at')
            ->pluck('deleted_at', 'id');

        $liste = [];
        $ordre = collect($paires)->sortBy(fn ($p) => [$p[0]->start_date, (int) $p[0]->campaign_id]);
        foreach ($ordre as [$r, $panelId, $rattache, $cleHistorique]) {
            // Pied d'un axe tenu par la campagne : l'historique est celui de la ligne d'axe.
            $intervalles = self::intervallesPresence(
                Carbon::parse($r->start_date),
                Carbon::parse($r->end_date),
                ($mouvements[$cleHistorique] ?? collect())->map(fn ($m) => [
                    'sens'   => $m->sens,
                    'motif'  => $m->motif,
                    'source' => $m->source,
                    'date'   => Carbon::parse($m->effet_le ?? $m->created_at)->startOfDay(),
                ])->sortBy(fn ($m) => $m['date']->timestamp)->values()->all(),
                $rattache,
                isset($demonteLe[$panelId]) ? Carbon::parse($demonteLe[$panelId]) : null,
            );

            foreach ($intervalles as [$debut, $fin]) {
                if ($debut->gt($periodEnd) || $fin->lt($periodStart)) continue;
                $liste[$panelId][] = [
                    'campaign_id'    => (int) $r->campaign_id,
                    'campaign_name'  => $r->campaign_name,
                    'campaign_start' => $debut,
                    'campaign_end'   => $fin,
                    'client_id'      => $r->client_id ? (int) $r->client_id : null,
                    'client_name'    => $r->client_name,
                ];
            }
        }

        return $liste;
    }

    /**
     * Présence d'un panneau dans une campagne (2026-11-24, règle validée) :
     * liste de [début, fin], bornée par les dates de la campagne et par le
     * démantèlement du panneau.
     *   · Sans historique : toute la campagne (s'il y est rattaché).
     *   · Ajouté en cours de campagne : à partir de son ajout. L'ajout fait à
     *     la création de la campagne (ou à un import) compte depuis le début.
     *   · Retiré : jusqu'à sa date réelle de sortie (saisie au retrait,
     *     sinon le jour du retrait). Un panneau présent avant le début de
     *     l'historique (16/11) compte depuis le début de la campagne.
     *
     * @param  list<array{sens: string, motif: ?string, source: ?string, date: Carbon}> $mouvements  dans l'ordre chronologique
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public static function intervallesPresence(Carbon $cDebut, Carbon $cFin, array $mouvements, bool $rattache, ?Carbon $demonteLe = null): array
    {
        $intervalles = [];
        $ouvert = null;

        foreach ($mouvements as $i => $m) {
            $d = $m['date'];
            if ($m['sens'] === \App\Models\CampaignPanelMouvement::AJOUT) {
                if ($ouvert === null) {
                    $depuisLeDebut = $m['motif'] === \App\Models\CampaignPanelMouvement::MOTIF_CREATION
                        || in_array($m['source'], [\App\Models\CampaignPanelMouvement::SOURCE_CREATION, \App\Models\CampaignPanelMouvement::SOURCE_IMPORT], true);
                    $ouvert = $depuisLeDebut ? $cDebut->copy() : $d->copy()->max($cDebut);
                }
            } else {
                // Premier mouvement = un retrait : le panneau était là avant l'historique.
                if ($ouvert === null && $i === 0) {
                    $ouvert = $cDebut->copy();
                }
                if ($ouvert !== null) {
                    $intervalles[] = [$ouvert, $d->copy()->min($cFin)];
                    $ouvert = null;
                }
            }
        }

        if ($mouvements === []) {
            if ($rattache) $intervalles[] = [$cDebut->copy(), $cFin->copy()];
        } elseif ($ouvert !== null) {
            $intervalles[] = [$ouvert, $cFin->copy()];
        } elseif ($rattache) {
            // Rattaché sans trace de son retour : présent depuis sa dernière sortie.
            $intervalles[] = [end($mouvements)['date']->copy()->max($cDebut), $cFin->copy()];
        }

        $resultat = [];
        foreach ($intervalles as [$debut, $fin]) {
            if ($demonteLe) $fin = $fin->min($demonteLe->copy()->startOfDay());
            if (!$fin->lt($debut)) $resultat[] = [$debut, $fin];
        }

        return $resultat;
    }

    /** La table de l'historique existe (migration du 16/11). */
    private static function historiquePresent(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('campaign_panel_mouvements');
    }

    /** La colonne de la date réelle existe (migration du 24/11). */
    private static function dateEffetPresente(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasColumn('campaign_panel_mouvements', 'effet_le');
    }

    /**
     * Bornes d'une période + nombre de mois pour la formule.
     * @return array{0:Carbon, 1:Carbon, 2:int}
     */
    public function resolvePeriod(string $periodType, int $periodValue, int $year, ?int $periodEndValue = null): array
    {
        // Hotfix TX-2 v2 (2026-06-22) : assertion bornes EXPLICITE en
        // tête de méthode. Avant : un periodValue=0 en trimestriel
        // donnait Carbon::create($year, -2, 1) avec rollover silencieux
        // sur l'année précédente, calcul incohérent en aval.
        if ($periodType === self::PERIOD_MONTHLY && ($periodValue < 1 || $periodValue > 12)) {
            throw new \InvalidArgumentException("Mois invalide pour mensuel : $periodValue (attendu 1..12).");
        }
        if ($periodType === self::PERIOD_QUARTERLY && ($periodValue < 1 || $periodValue > 4)) {
            throw new \InvalidArgumentException("Trimestre invalide : $periodValue (attendu 1..4).");
        }
        // FIX 2026-06-26 — Mode personnalisé : mois début ($periodValue) → mois fin ($periodEndValue).
        if ($periodType === self::PERIOD_CUSTOM) {
            if ($periodValue < 1 || $periodValue > 12 || $periodEndValue === null
                || $periodEndValue < $periodValue || $periodEndValue > 12) {
                throw new \InvalidArgumentException("Période personnalisée invalide : $periodValue → $periodEndValue (attendu 1..12 avec fin ≥ début).");
            }
        }
        return match ($periodType) {
            self::PERIOD_MONTHLY => [
                Carbon::create($year, $periodValue, 1)->startOfDay(),
                Carbon::create($year, $periodValue, 1)->endOfMonth(),
                1,
            ],
            self::PERIOD_QUARTERLY => [
                Carbon::create($year, ($periodValue - 1) * 3 + 1, 1)->startOfDay(),
                Carbon::create($year, ($periodValue - 1) * 3 + 3, 1)->endOfMonth(),
                3,
            ],
            self::PERIOD_ANNUAL => [
                Carbon::create($year, 1, 1)->startOfDay(),
                Carbon::create($year, 12, 31)->endOfDay(),
                12,
            ],
            self::PERIOD_CUSTOM => [
                Carbon::create($year, $periodValue, 1)->startOfDay(),
                Carbon::create($year, $periodEndValue, 1)->endOfMonth(),
                $periodEndValue - $periodValue + 1,
            ],
            default => throw new \InvalidArgumentException("Périodicité inconnue : $periodType"),
        };
    }

    /**
     * Trimestres couverts par une période (2026-11-24, PDF du détail des
     * taxes) : « T4 2026 », « T1 et T2 2026 », « T1 à T4 2026 ». Les périodes
     * de resolvePeriod() restent dans une même année.
     */
    public static function libelleTrimestres(Carbon $debut, Carbon $fin): string
    {
        $t1 = intdiv($debut->month - 1, 3) + 1;
        $t2 = intdiv($fin->month - 1, 3) + 1;
        $an = $fin->year;

        if ($debut->year !== $fin->year) {
            return "T{$t1} {$debut->year} à T{$t2} {$an}";
        }

        return match (true) {
            $t1 === $t2     => "T{$t1} {$an}",
            $t2 === $t1 + 1 => "T{$t1} et T{$t2} {$an}",
            default         => "T{$t1} à T{$t2} {$an}",
        };
    }

    /**
     * Trimestres d'une période, écrits en toutes lettres (2026-11-24, relevé
     * de TM pour la mairie) : « Trimestre 1 : de janvier à mars 2026 »,
     * « Trimestre 2 : d'avril à juin 2026 ». Un trimestre entamé seulement
     * donne ses mois couverts (« Trimestre 4 : novembre 2026 »).
     *
     * @return list<string>
     */
    public static function trimestresEnLettres(Carbon $debut, Carbon $fin): array
    {
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
            'septembre', 'octobre', 'novembre', 'décembre'];
        $de = fn (string $m) => preg_match('/^[aeiouéè]/u', $m) ? "d'{$m}" : "de {$m}";

        $lignes = [];
        $curseur = $debut->copy()->startOfMonth();
        while ($curseur->lte($fin)) {
            $t = intdiv($curseur->month - 1, 3) + 1;
            $finTrimestre = $curseur->copy()->month($t * 3)->endOfMonth();
            $dernier = $finTrimestre->lt($fin) ? $finTrimestre : $fin->copy();
            $m1 = $mois[$curseur->month - 1];
            $m2 = $mois[$dernier->month - 1];
            $lignes[] = "Trimestre {$t} : " . ($curseur->month === $dernier->month
                ? "{$m1} {$dernier->year}"
                : $de($m1) . " à {$m2} {$dernier->year}");
            $curseur = $finTrimestre->copy()->addDay()->startOfMonth();
        }

        return $lignes;
    }

    /**
     * Agrégation des lignes pour les KPI / résumés. Évite d'avoir à
     * reparcourir la collection manuellement à chaque vue.
     *
     * Hotfix TX-4 (2026-06-22) : ajout des alias 'odp_total' et 'tm_total'
     * lus par TaxController::showCommune et computeAnnualTotalDue (qui
     * retournaient 0 partout car ces clés n'étaient jamais émises — la
     * matrice mensuelle de la fiche commune affichait donc 0 DÛ partout).
     * Les anciennes clés 'total', 'by_type', 'by_commune', 'panels_count',
     * 'lines_count' restent intactes (rétro-compat details.blade.php).
     */
    /**
     * Clé stable d'une ligne de generateLines() (2026-11-18) : sert à la
     * sélection multiple du détail par panneau et aux exports de la
     * sélection. ODP : une ligne par panneau. TM : une ligne par panneau
     * ET par campagne (la campagne fait partie de la clé).
     */
    public static function facesDeLigne(array $ligne): int
    {
        // 2026-11-27 (point 13 validé) — Faces portées par une ligne ODP (un
        // mât) : double-face fusionné (faces_count) × faces d'un pied d'axe
        // (faces_pied). 1 pour un panneau ordinaire. Source unique de la page
        // Taxes, du Détail et du Suivi des mairies.
        return max(1, (int) ($ligne['faces_count'] ?? 1)) * max(1, (int) ($ligne['faces_pied'] ?? 1));
    }

    public static function cleLigne(array $ligne): string
    {
        return $ligne['panel_id'] . '-' . $ligne['type']
            . ($ligne['type'] === self::TYPE_TM ? '-' . (int) ($ligne['campaign_id'] ?? 0) : '');
    }

    public function summarize(Collection $lines): array
    {
        $byType = $lines->groupBy('type')->map->sum('amount');
        return [
            'total'        => (float) $lines->sum('amount'),
            'by_type'      => $byType,
            'by_commune'   => $lines->groupBy('commune')->map->sum('amount'),
            'panels_count' => $lines->pluck('panel_id')->unique()->count(),
            // 2026-11-27 (point 13) — mâts taxés (lignes ODP) et leurs faces,
            // même définition que la page Taxes et le Suivi des mairies.
            'mats_count'   => $lines->where('type', self::TYPE_ODP)->count(),
            'faces_count'  => (int) $lines->where('type', self::TYPE_ODP)->sum(fn ($l) => self::facesDeLigne($l)),
            'lines_count'  => $lines->count(),
            // Alias pour les consommateurs TaxController qui les attendaient.
            'odp_total'    => (float) ($byType[self::TYPE_ODP] ?? 0),
            'tm_total'     => (float) ($byType[self::TYPE_TM]  ?? 0),
        ];
    }

    // ══════════════════════════════════════════════════════════════
    //  PHASE 1 (2026-06-22) — Refonte API "calcul par commune sur
    //  période [debut, fin]". Source unique pour les calculs ODP/TM
    //  panneau-par-panneau (mois d'existence × mois d'occupation),
    //  remplace l'ancien calcul "tarif × surface × nbMois" qui était
    //  faux pour 3 raisons :
    //    1. ×12 parasite (TX-1) sur les périodes annuelles
    //    2. Pas de prise en compte de la date d'installation /
    //       démantèlement (TX-cas réels)
    //    3. TM toujours comptée même si panneau jamais occupé
    //
    //  Les méthodes ci-dessous travaillent par panneau (prorata mois)
    //  et reflètent la règle métier OOH formellement (cf. brief
    //  RÈGLES MÉTIER de la mission refonte taxes).
    // ══════════════════════════════════════════════════════════════

    /**
     * ODP totale due pour une commune sur la période [debut, fin].
     * Somme par panneau : tarif_ODP_MENSUEL × surface × mois_existence.
     * FIX TX-3 (2026-06-22) : tarifs mensuels confirmés patronne.
     *
     * ⚠ CODE MORT — NE PAS REBRANCHER EN L'ÉTAT (constaté 2026-09-23).
     *   Aucun appelant en production : TaxController et TaxesDetailsExport
     *   passent tous par generateLines(). Cette méthode applique encore
     *   l'ancienne règle MENSUELLE d'avant TX-9 (pas de forfait
     *   trimestriel ×3) et ignore la fusion des faces TX-10 (elle
     *   facturerait 2× un mât double-face). Conservée au titre de la
     *   règle N°3 du CLAUDE.md. Cf. docs/TECHNICAL_DEBT.md.
     *
     * @deprecated Utiliser generateLines() + summarize().
     */
    public function calculODPCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $total = 0.0;
        foreach ($this->panneauxPourCalcul($commune) as $panel) {
            $rate = (float) ($commune->ratesAt($debut, (bool) $panel->is_lit)['odp'] ?? 0); // éclairé : 2026-11-18
            if ($rate <= 0) continue;
            // Surface taxable = une face × nombre de faces (2026-11-13).
            $surface = (float) ($panel->format?->surface_m2 ?? 0) // 2026-11-27 : surface unique
                * app(\App\Services\Axes\AxeService::class)->facesParPied($panel);
            if ($surface <= 0) continue;
            $moisExistence = $this->moisExistencePanneau($panel, $debut, $fin);
            if ($moisExistence === 0) continue;
            $total += $rate * $surface * $moisExistence;
        }
        return (int) round($total);
    }

    /**
     * TM totale due pour une commune sur la période [debut, fin].
     * Somme par panneau : tarif_TM_MENSUEL × surface × mois_occupation.
     * Un panneau jamais occupé sur la période contribue 0.
     * FIX TX-3 (2026-06-22) : tarifs mensuels confirmés patronne.
     */
    public function calculTMCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $total = 0.0;
        foreach ($this->panneauxPourCalcul($commune) as $panel) {
            $rate = (float) ($commune->ratesAt($debut, (bool) $panel->is_lit)['tm'] ?? 0); // éclairé : 2026-11-18
            if ($rate <= 0) $rate = self::tmParDefaut();   // base nationale (2026-11-26)
            if ($rate <= 0) continue;
            // Surface taxable = une face × nombre de faces (2026-11-13).
            $surface = (float) ($panel->format?->surface_m2 ?? 0) // 2026-11-27 : surface unique
                * app(\App\Services\Axes\AxeService::class)->facesParPied($panel);
            if ($surface <= 0) continue;
            $moisOccupation = $this->moisOccupationPanneau($panel, $debut, $fin);
            if ($moisOccupation === 0) continue;
            $total += $rate * $surface * $moisOccupation;
        }
        return (int) round($total);
    }

    /**
     * Total DÛ (ODP + TM) pour une commune sur une période.
     */
    public function totalDuCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        return $this->calculODPCommune($commune, $debut, $fin)
             + $this->calculTMCommune($commune, $debut, $fin);
    }

    /**
     * Total PAYÉ par la régie à cette commune sur la période, filtré
     * STRICTEMENT par la date de versement (paid_at). Hotfix TX-6 :
     * c'est cette méthode qui évite qu'un versement 22/06/2026 apparaisse
     * dans le total payé de l'année 2021.
     */
    public function totalPayeCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $payments = CommuneTaxPayment::query()
            ->where('commune_id', $commune->id)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->get(['odp_paye', 'tm_paye']);
        return (int) $payments->sum(fn($p) => (int) $p->odp_paye + (int) $p->tm_paye);
    }

    /**
     * Solde restant = dû − payé. Peut être négatif (trop-payé).
     */
    public function soldeRestant(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        return $this->totalDuCommune($commune, $debut, $fin)
             - $this->totalPayeCommune($commune, $debut, $fin);
    }

    /**
     * Statut d'une commune sur une période : 'non_paye' | 'partiel' | 'paye'.
     */
    public function statutCommune(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): string
    {
        $du   = $this->totalDuCommune($commune, $debut, $fin);
        $paye = $this->totalPayeCommune($commune, $debut, $fin);
        if ($paye <= 0)      return 'non_paye';
        if ($paye >= $du)    return 'paye';
        return 'partiel';
    }

    /**
     * Taux de couverture (0..100). Si rien n'est dû, on retourne 100
     * (par convention : pas de dette = "couvert" → bouton vert).
     */
    public function tauxCouverture(Commune $commune, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $du = $this->totalDuCommune($commune, $debut, $fin);
        if ($du <= 0) return 100;
        $paye = $this->totalPayeCommune($commune, $debut, $fin);
        return min(100, (int) round(($paye / $du) * 100));
    }

    /**
     * Compte les mois calendaires d'existence d'un panneau dans une
     * période. Règle simple "1 jour dans le mois = 1 mois compté" :
     * - début effectif = max(periode.debut, panneau.created_at)
     * - fin effective  = min(periode.fin, panneau.deleted_at OR ∞)
     * - retourne 0 si fin < début (panneau hors période)
     *
     * Le panneau démantelé = panneau soft-deleted (deleted_at posé).
     * On reste compatible avec l'existant qui n'a pas de colonne
     * dismantled_at dédiée — décision validée en Phase α.
     */
    public function moisExistencePanneau(Panel $panel, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $createdAt = $panel->created_at ? Carbon::parse($panel->created_at) : null;
        $deletedAt = $panel->deleted_at ? Carbon::parse($panel->deleted_at) : null;

        $existeDepuis = $createdAt ?: $debut;
        $existeJusqua = $deletedAt;

        $debutEff = Carbon::parse($debut)->max($existeDepuis);
        $finEff   = $existeJusqua
            ? Carbon::parse($fin)->min($existeJusqua)
            : Carbon::parse($fin);

        if ($finEff->lessThan($debutEff)) return 0;

        return $this->compteMoisCalendaires($debutEff, $finEff);
    }

    /**
     * Compte les mois où un panneau a été occupé par au moins 1
     * campagne ACTIVE sur la période. Règle simple : 1 jour de campagne
     * dans le mois = 1 mois compté (alignée avec la pratique métier).
     *
     * Hotfix TM (clé règles métier) : un panneau jamais occupé contribue
     * 0 à la TM, conformément à la spec OOH ivoirienne.
     */
    public function moisOccupationPanneau(Panel $panel, CarbonInterface $debut, CarbonInterface $fin): int
    {
        $moisOccupes = 0;
        $current = Carbon::parse($debut)->startOfMonth();
        $finC    = Carbon::parse($fin)->endOfMonth();

        while ($current->lessThanOrEqualTo($finC)) {
            $moisFinC = $current->copy()->endOfMonth();
            $aCampagne = \DB::table('campaign_panels')
                ->join('campaigns', 'campaigns.id', '=', 'campaign_panels.campaign_id')
                ->where('campaign_panels.panel_id', $panel->id)
                // FIX 2026-06-26 — Inclut 'termine' (cf. buildCampaignAssignmentMap).
                // L'occupation passée des panneaux génère une TM même si la
                // campagne est désormais archivée.
                ->whereIn('campaigns.status', [CampaignStatus::ACTIF->value, CampaignStatus::TERMINE->value])
                ->whereNull('campaigns.deleted_at')
                ->where('campaigns.start_date', '<=', $moisFinC->toDateString())
                ->where('campaigns.end_date',   '>=', $current->toDateString())
                ->exists();
            if ($aCampagne) $moisOccupes++;
            $current->addMonth();
        }
        return $moisOccupes;
    }

    /**
     * Compte le nombre de mois calendaires couverts par [a, b] (1 jour
     * dans un mois suffit pour compter le mois).
     */
    protected function compteMoisCalendaires(CarbonInterface $a, CarbonInterface $b): int
    {
        $a = Carbon::parse($a)->startOfMonth();
        $b = Carbon::parse($b)->startOfMonth();
        if ($b->lessThan($a)) return 0;
        return (int) ($a->diffInMonths($b)) + 1;
    }

    /**
     * Sélection des panneaux d'une commune pour le calcul fiscal.
     * Décision Phase α : on prend TOUS les panneaux internes existants
     * (exclut soft-deleted MAIS leur deleted_at est ré-utilisé via
     * withTrashed() pour les calculs au prorata "panneau démantelé en
     * juillet" — voir moisExistencePanneau).
     *
     * À ce stade pragmatique : on prend les panneaux NON soft-deleted +
     * les soft-deleted dont deleted_at est >= debut de la période (sinon
     * impossible d'avoir des mois d'existence dans la fenêtre).
     */
    protected function panneauxPourCalcul(Commune $commune, bool $inclureLumipub = false)
    {
        return Panel::withTrashed()
            ->with('format:id,name,width,height,surface')
            ->where('commune_id', $commune->id)
            // Ligne d'axe toujours écartée ; pieds Lumipub sur demande
            // seulement (2026-11-13) — cf. Panel::scopeAvecTaxesAutomatiques.
            ->avecTaxesAutomatiques($inclureLumipub)
            ->get();
    }

    /** Tarif TM national par défaut (config billing.tm_default), 2026-11-26. */
    public static function tmParDefaut(): float
    {
        return (float) config('billing.tm_default', 1000);
    }

    /**
     * Les mois civils couverts par la période, bornés à la période :
     * [[début, fin], …] (2026-11-26, calcul mois par mois).
     *
     * @return list<array{0: \Carbon\Carbon, 1: \Carbon\Carbon}>
     */
    private function moisDeLaPeriode(\Carbon\Carbon $debut, \Carbon\Carbon $fin): array
    {
        $mois = [];
        $curseur = $debut->copy()->startOfMonth();
        while ($curseur->lte($fin)) {
            $mois[] = [
                $curseur->copy()->max($debut)->startOfDay(),
                $curseur->copy()->endOfMonth()->min($fin)->endOfDay(),
            ];
            $curseur->addMonthNoOverflow()->startOfMonth();
        }

        return $mois;
    }

    /**
     * Mois d'une ligne comptés dans une sous-période (mêmes règles que generateLines).
     * 2026-11-27 — $occurrence peut être null : ligne ODP d'un panneau sans
     * campagne. Avant, un tarif changeant dans la période faisait planter le
     * calcul pour ces panneaux (erreur 500 sur le Détail par panneau).
     */
    private function moisDeLaLigneDans(string $type, ?array $occurrence, $panel, \Carbon\Carbon $debut, \Carbon\Carbon $fin, int $moisParDefaut): int
    {
        if ($type === self::TYPE_TM && !empty($occurrence['campaign_start']) && !empty($occurrence['campaign_end'])) {
            return $this->period->moisTMCommencantDansPeriode($occurrence['campaign_start'], $occurrence['campaign_end'], $debut, $fin);
        }
        if ($type === self::TYPE_ODP) {
            return $this->period->moisODPDansPeriode($panel->odp_start_date ?? $debut, $panel->deleted_at, $debut, $fin);
        }

        return 1;   // garde-fou « TM sans dates » : un mois par mois de période
    }
}

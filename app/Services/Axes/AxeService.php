<?php

namespace App\Services\Axes;

use App\Models\Panel;
use Illuminate\Support\Collection;

/**
 * Axes multi-pieds — SOURCE UNIQUE de la règle (2026-11-11).
 *
 * Un **axe** est un tronçon de voie qui regroupe plusieurs **pieds**. Le
 * pied est l'unité de réservation, de disponibilité et de pige. Un pied
 * porte plusieurs **faces** (3 sur les axes Lumipub, 2 sur BK-002) et se
 * vend avec ses faces : 130 000 FCFA/mois = un pied, faces comprises
 * (tranché par la direction le 2026-11-11).
 *
 * Trois règles vivent ici, et nulle part ailleurs :
 *
 *   1. **Une ligne d'axe ne se vend pas.** Elle reste en base avec tout son
 *      historique — piges, poses, contrats signés avant le découpage — mais
 *      elle n'est ni proposée au catalogue, ni cochable, ni réservable.
 *      Sinon l'axe et ses pieds seraient comptés deux fois.
 *
 *   2. **Un pied hérite du blocage de son axe.** Les contrats signés avant
 *      le découpage portent sur la ligne de l'axe : tant que l'un d'eux
 *      court, aucun pied de cet axe n'est libre. Sans cette règle, on
 *      revendrait un axe déjà vendu en entier.
 *
 *   3. **La structure se lit dans le nom.** Le parc écrit déjà « (11P x 3F) »
 *      en texte libre dans `panels.name`. C'est la seule source pour
 *      amorcer le découpage, et elle couvre 13 axes sur 14.
 *
 * ⚠ Ce service ne touche NI au prix, NI à l'ODP, NI à la TM. La phase 1 est
 * fiscalement neutre : voir `InvoiceFromCampaignBuilder` (regroupement des
 * pieds sur leur axe) et docs/AXES_MULTI_PIEDS.md § 4.
 */
class AxeService
{
    /**
     * Les colonnes de la phase 1 existent-elles ?
     *
     * ⚠ Indispensable : le code part en production AVANT que la migration
     * soit jouée. Sans ce garde-fou, `pluck('axe_id')` casse tout
     * AvailabilityService — donc toute réservation — jusqu'à ce que
     * `php artisan migrate` passe.
     *
     * Mémorisé sur l'INSTANCE, pas en statique : un cache statique
     * survivrait d'un test à l'autre et mentirait sur un schéma différent.
     */
    private ?bool $colonnes = null;

    public function colonnesPresentes(): bool
    {
        return $this->colonnes ??= \Illuminate\Support\Facades\Schema::hasColumn('panels', 'axe_id');
    }

    /**
     * Lit « (11P x 3F) », « (12Panneaux double face) », « (2Px3F) ».
     *
     * Rend `['pieds' => 11, 'faces' => 3]`, ou null si le nom ne porte
     * aucun nombre exploitable — cas de `MRYLUP-001` « Marcory Résidentiel
     * (1 Lumipub) », qui est un Lumipub isolé et NON un axe.
     *
     * @return array{pieds: int, faces: int}|null
     */
    public function lireStructure(?string $nom): ?array
    {
        $nom = trim((string) $nom);
        if ($nom === '') {
            return null;
        }

        // « (11P x 3F) », « (2Px3F) », « ( 10 P x 3 F ) »
        if (preg_match('/\(\s*(\d+)\s*P\s*x\s*(\d+)\s*F\s*\)/i', $nom, $m)) {
            return ['pieds' => (int) $m[1], 'faces' => (int) $m[2]];
        }

        // « (12Panneaux double face) » → 12 pieds de 2 faces.
        if (preg_match('/\(\s*(\d+)\s*panneaux?\b([^)]*)\)/i', $nom, $m)) {
            $pieds = (int) $m[1];

            // « 1 Lumipub » n'est pas un axe : un seul support.
            if ($pieds < 2) {
                return null;
            }

            return [
                'pieds' => $pieds,
                'faces' => stripos($m[2], 'double') !== false ? 2 : 1,
            ];
        }

        return null;
    }

    /**
     * Panneaux non encore découpés dont le nom annonce un axe.
     *
     * Volontairement basé sur le NOM seul : le mot « axe » ou la catégorie
     * Lumipub ne suffisent pas (MRYLUP-001 porte les deux et n'est pas un
     * axe). Un panneau déjà découpé est écarté.
     *
     * @return Collection<int, Panel>
     */
    public function candidats(): Collection
    {
        if (! $this->colonnesPresentes()) {
            return collect();
        }

        return Panel::query()
            ->whereNull('axe_id')
            ->where('est_axe', false)
            ->get()
            ->filter(fn (Panel $p) => $this->lireStructure($p->name) !== null)
            ->values();
    }

    /**
     * Référence d'un pied : « CDYLUP-004-P01 ».
     *
     * ⚠ Elle se termine par des CHIFFRES, et c'est voulu.
     * `TaxCalculationService::referencePhysique()` fusionne les faces d'un
     * même mât en retirant un suffixe `A`/`B` collé à un chiffre. Un pied
     * nommé « -P01 » n'est donc jamais fusionné avec un autre, et la
     * fusion existante des double-faces reste intacte : le calcul de
     * l'ODP ne bouge pas d'un franc.
     */
    public function referencePied(string $referenceAxe, int $numero): string
    {
        return sprintf('%s-P%02d', rtrim($referenceAxe), $numero);
    }

    /** « Cocody Lumipub Axe St Jean - Carrefour Lavie — pied 3/11 ». */
    public function nomPied(string $nomAxe, int $numero, int $total): string
    {
        // Le « (11P x 3F) » du nom de l'axe n'a plus de sens sur un pied.
        $base = trim(preg_replace('/\(\s*\d+\s*P\s*x\s*\d+\s*F\s*\)/i', '', $nomAxe) ?? $nomAxe);
        $base = trim(preg_replace('/\(\s*\d+\s*panneaux?[^)]*\)/i', '', $base) ?? $base);

        return sprintf('%s — pied %d/%d', trim($base), $numero, $total);
    }

    /** « Axe St Jean – Carrefour Lavie » : le nom sans « (11P x 3F) ». */
    public function nomCourt(?string $nom): string
    {
        $court = trim(preg_replace(
            ['/\(\s*\d+\s*P\s*x\s*\d+\s*F\s*\)/i', '/\(\s*\d+\s*panneaux?[^)]*\)/i'], '', (string) $nom
        ) ?? '');

        return $court !== '' ? $court : (string) $nom;
    }

    /**
     * Sélecteurs de panneaux (2026-11-12, demande du MP) : les pieds d'un
     * même axe deviennent UNE entrée, à la place du premier, avec la liste
     * de ses pieds. Les autres lignes passent telles quelles.
     *
     * Purement de l'affichage : chaque ligne de pied garde le statut que
     * l'appelant a calculé (AvailabilityService) — rien n'est recalculé.
     *
     * @param  Collection<int, array>                    $lignes  lignes portant 'axe_id' et 'pied_numero'
     * @param  callable(Panel $axe, array $pieds, int $total): array $entree
     */
    public function regrouperLignes(Collection $lignes, callable $entree): Collection
    {
        $axeIds = $lignes->pluck('axe_id')->filter()->unique()->values()->all();
        if (empty($axeIds) || ! $this->colonnesPresentes()) {
            return $lignes->values();
        }

        $axes = Panel::with(['commune:id,name', 'format:id,name,width,height', 'photos'])
            ->whereIn('id', $axeIds)->get()->keyBy('id');
        $totaux = Panel::whereIn('axe_id', $axeIds)
            ->selectRaw('axe_id, COUNT(*) as total')->groupBy('axe_id')->pluck('total', 'axe_id');
        $parAxe = $lignes->filter(fn ($l) => ! empty($l['axe_id']))->groupBy('axe_id');

        $resultat = collect();
        $poses = [];
        foreach ($lignes as $ligne) {
            $axe = ! empty($ligne['axe_id']) ? $axes->get($ligne['axe_id']) : null;
            if ($axe === null) {
                $resultat->push($ligne);
                continue;
            }
            if (isset($poses[$axe->id])) {
                continue;
            }
            $poses[$axe->id] = true;

            $pieds = $parAxe->get($axe->id)->sortBy('pied_numero')->values()->all();
            $resultat->push($entree($axe, $pieds, (int) ($totaux[$axe->id] ?? count($pieds))));
        }

        return $resultat->values();
    }

    /**
     * Colonnes d'axe à ajouter à une projection `panel:…` — de quoi appeler
     * Panel::positionTerrain() / photoDeReference() et dire « Pied 3 ».
     * Vide tant que la migration des axes n'est pas passée.
     */
    public function colonnesPanneau(): string
    {
        return $this->colonnesPresentes() ? ',axe_id,pied_numero,est_axe' : '';
    }

    /**
     * L'axe d'un pied (position, nom, photo) à charger avec colonnesPanneau().
     *
     * @param  string $chemin   relation du panneau (« panel », « poseTask.panel »…)
     * @param  bool   $photos   aussi les photos de l'axe (photo de référence)
     */
    public function relationsAxe(string $chemin = 'panel', bool $photos = false): array
    {
        if (! $this->colonnesPresentes()) {
            return [];
        }
        $relations = ["{$chemin}.axe:id,reference,name,latitude,longitude"];
        if ($photos) {
            $relations[] = "{$chemin}.axe.photos:id,panel_id,path,ordre";
        }

        return $relations;
    }

    /**
     * Suivi des pieds d'axe sur leurs campagnes (2026-11-12, admin). Pour
     * chaque couple (axe, campagne) présent dans `$taches` : combien de
     * pieds ont une pose, combien sont posés, pigés (une photo non
     * refusée), validés (une photo vérifiée) — sur TOUTES les poses du
     * couple, pas seulement celles de la page. Poses annulées exclues.
     *
     * Les compteurs restent EN PIEDS (décision du 12/11) ; seul l'affichage
     * regroupe : « CDYLUP-004 · 3/5 pieds pigés ». Une photo se rattache à
     * sa pose comme PoseTask::piges() : par pose_task_id, sinon (ancienne
     * photo, lien de campagne) par panneau + campagne.
     *
     * @param  iterable<\App\Models\PoseTask> $taches
     * @return array{tache: array<int, array{cle: string, pied: ?int}>, axes: array<string, array{axe: Panel, pieds: int, poses: int, piges: int, validees: int}>}
     */
    public function suiviPoses(iterable $taches): array
    {
        $vide = ['tache' => [], 'axes' => []];
        $taches = collect($taches);
        if ($taches->isEmpty() || ! $this->colonnesPresentes()) {
            return $vide;
        }

        $pieds = Panel::whereIn('id', $taches->pluck('panel_id')->filter()->unique()->values())
            ->whereNotNull('axe_id')
            ->get(['id', 'axe_id', 'pied_numero'])
            ->keyBy('id');
        if ($pieds->isEmpty()) {
            return $vide;
        }

        $tache = [];
        foreach ($taches as $t) {
            $p = $pieds->get($t->panel_id);
            if ($p && $t->campaign_id) {
                $tache[$t->id] = ['cle' => $p->axe_id . '-' . $t->campaign_id, 'pied' => $p->pied_numero];
            }
        }
        if (empty($tache)) {
            return $vide;
        }

        $axeIds = $pieds->pluck('axe_id')->unique()->values();
        $campagnes = $taches->pluck('campaign_id')->filter()->unique()->values();
        $axes = Panel::whereIn('id', $axeIds)->get(['id', 'reference', 'name'])->keyBy('id');

        $photo = fn (string $condition) => "EXISTS (SELECT 1 FROM piges
            WHERE (piges.pose_task_id = pose_tasks.id
                   OR (piges.pose_task_id IS NULL AND piges.panel_id = pose_tasks.panel_id
                       AND piges.campaign_id = pose_tasks.campaign_id))
              AND {$condition})";

        $lignes = \Illuminate\Support\Facades\DB::table('pose_tasks')
            ->join('panels', 'panels.id', '=', 'pose_tasks.panel_id')
            ->whereIn('panels.axe_id', $axeIds)
            ->whereIn('pose_tasks.campaign_id', $campagnes)
            ->where('pose_tasks.status', '!=', \App\Enums\PoseTaskStatus::CANCELLED->value)
            ->groupBy('panels.axe_id', 'pose_tasks.campaign_id')
            ->selectRaw('panels.axe_id, pose_tasks.campaign_id, COUNT(DISTINCT pose_tasks.panel_id) AS pieds')
            ->selectRaw('COUNT(DISTINCT CASE WHEN pose_tasks.status = ? THEN pose_tasks.panel_id END) AS poses',
                [\App\Enums\PoseTaskStatus::COMPLETED->value])
            ->selectRaw('COUNT(DISTINCT CASE WHEN ' . $photo("piges.status <> 'rejete'") . ' THEN pose_tasks.panel_id END) AS piges')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ' . $photo("piges.status = 'verifie'") . ' THEN pose_tasks.panel_id END) AS validees')
            ->get();

        $suivi = [];
        foreach ($lignes as $l) {
            if ($axe = $axes->get($l->axe_id)) {
                $suivi[$l->axe_id . '-' . $l->campaign_id] = [
                    'axe' => $axe, 'pieds' => (int) $l->pieds, 'poses' => (int) $l->poses,
                    'piges' => (int) $l->piges, 'validees' => (int) $l->validees,
                ];
            }
        }

        return ['tache' => array_filter($tache, fn ($x) => isset($suivi[$x['cle']])), 'axes' => $suivi];
    }

    /**
     * Ligne de DEVIS pour un axe (2026-11-12) : une ligne par axe, qui
     * retient ses pieds proposés. Prix = somme des tarifs des pieds ; ODP
     * et TM à 0, saisies à la main (règles validées le 2026-11-12).
     *
     * @param  Collection<int, Panel> $pieds  pieds proposés
     * @return array  au format des lignes du formulaire de devis
     */
    public function ligneDevis(Panel $axe, Collection $pieds): array
    {
        $axe->loadMissing(['commune', 'format']);
        $n = $pieds->count();
        $surface = (float) ($axe->format?->surface ?? (($axe->format?->width ?? 0) * ($axe->format?->height ?? 0)));

        return [
            'panel_id'            => $axe->id,
            'designation'         => mb_substr(trim($axe->reference . ' — ' . $this->nomCourt($axe->name) . ' — ' . $n . ($n > 1 ? ' pieds' : ' pied')), 0, 200),
            'dimension_m2'        => $surface,
            'pu_ht_mensuel'       => (int) $pieds->sum(fn (Panel $p) => (int) ($p->monthly_rate ?? 0)),
            'quantite'            => 1,
            'duree_mois'          => 1,
            'commune_id'          => $axe->commune_id,
            'commune_name'        => $axe->commune?->name,
            'pieds'               => $pieds->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'odp_amount_override' => 0,
            'tm_amount_override'  => 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // RÈGLE 2 — un pied hérite du blocage de son axe
    // ══════════════════════════════════════════════════════════════

    /**
     * Pour une liste de panneaux, rend la carte `pied_id => axe_id` des
     * seuls panneaux qui sont des pieds.
     *
     * @param  int[] $panelIds
     * @return array<int, int>
     */
    public function axeParPied(array $panelIds): array
    {
        $panelIds = array_values(array_unique(array_filter(array_map('intval', $panelIds))));
        if (empty($panelIds) || ! $this->colonnesPresentes()) {
            return [];
        }

        return Panel::whereIn('id', $panelIds)
            ->whereNotNull('axe_id')
            ->pluck('axe_id', 'id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Les ids à interroger pour décider de la disponibilité : la liste
     * demandée, PLUS les axes parents des pieds qu'elle contient.
     *
     * @param  int[] $panelIds
     * @return int[]
     */
    public function avecAxesParents(array $panelIds): array
    {
        $axes = $this->axeParPied($panelIds);

        return array_values(array_unique(array_merge(
            array_map('intval', $panelIds),
            array_values($axes),
        )));
    }

    /**
     * Reporte le blocage d'un axe sur ses pieds, puis retire les axes
     * eux-mêmes du résultat s'ils n'étaient pas demandés.
     *
     * @param  int[]            $bloques     ids bloqués, axes compris
     * @param  int[]            $demandes    ids réellement demandés
     * @param  array<int, int>  $axeParPied  carte pied => axe
     * @return int[]
     */
    public function reporterBlocageDesAxes(array $bloques, array $demandes, array $axeParPied): array
    {
        $bloques  = array_map('intval', $bloques);
        $demandes = array_map('intval', $demandes);

        foreach ($axeParPied as $piedId => $axeId) {
            if (in_array($axeId, $bloques, true)) {
                $bloques[] = (int) $piedId;
            }
        }

        // Un axe interrogé par ricochet ne doit pas ressortir comme
        // « panneau bloqué » si l'appelant ne l'avait pas demandé.
        $bloques = array_intersect($bloques, $demandes);

        return array_values(array_unique($bloques));
    }

    /**
     * Même report, pour les méthodes qui rendent un motif de conflit par
     * panneau (`conflitsFermes`). Le pied reprend le motif de son axe, en
     * le disant explicitement : l'utilisateur doit comprendre que c'est
     * l'axe entier qui est pris, pas ce pied précis.
     *
     * @param  array<int, array<string, mixed>> $parPanneau  motifs indexés par panel_id
     * @param  array<int, int>                  $axeParPied
     * @return array<int, array<string, mixed>>
     */
    public function reporterMotifsDesAxes(array $parPanneau, array $axeParPied): array
    {
        foreach ($axeParPied as $piedId => $axeId) {
            if (isset($parPanneau[$axeId]) && ! isset($parPanneau[$piedId])) {
                $motif = $parPanneau[$axeId];
                $motif['herite_de_axe'] = (int) $axeId;
                $parPanneau[(int) $piedId] = $motif;
            }
        }

        return $parPanneau;
    }
}

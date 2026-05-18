<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelCategory;
use Illuminate\Support\Facades\DB;

/**
 * Génération des références panneaux selon le pattern historique :
 *   {COMMUNE_CODE}{CATEGORY_CODE}-{NN}{FACE?}
 *
 * Exemples :
 *   PBTCAIS-05A   → Port-Bouët, Caisson, n°5, face A
 *   YOPCAIS-02B   → Yopougon, Caisson, n°2, face B
 *   ADJ-002       → Adjamé, Classique (pas de code), n°2
 *   CDYLUP-001    → Cocody, Lampadaire, n°1
 *
 * Règles :
 *   - Les codes commune/catégorie sont stockés en BD (champ `code`).
 *   - Si absent, un code fallback est dérivé du nom (3 lettres
 *     normalisées). L'admin pourra le corriger ensuite.
 *   - Catégorie "Classique" = code vide → pas de suffix entre commune
 *     et numéro (ADJ-002 plutôt que ADJCLA-002).
 *   - Numéro = plus petit entier libre pour le préfixe, padding sur
 *     2 chiffres jusqu'à 99, puis 3 chiffres.
 *   - Face (A/B/C/D) est ajoutée en suffix si fournie.
 *   - Unicité globale garantie : si la combinaison existe déjà, on
 *     incrémente le numéro jusqu'à trouver un libre.
 */
class PanelReferenceGenerator
{
    /**
     * Génère la prochaine référence libre pour la combinaison donnée.
     *
     * @param  Commune              $commune
     * @param  PanelCategory|null   $category    null = Classique (pas de code)
     * @param  string|null          $face        A, B, C, D ou null
     * @param  int|null             $excludeId   panel à exclure (pour edit)
     */
    public function generate(
        Commune $commune,
        ?PanelCategory $category = null,
        ?string $face = null,
        ?int $excludeId = null,
    ): string {
        $base   = $this->basePrefix($commune, $category);
        $face   = $this->normalizeFace($face);
        $number = $this->nextNumberFor($base, $face, $excludeId);

        return $base . '-' . $number . $face;
    }

    /**
     * Décomposition pour l'aperçu UI :
     *   [
     *     'commune_code'  => 'PBT',
     *     'category_code' => 'CAIS',
     *     'base'          => 'PBTCAIS',
     *     'number'        => '05',
     *     'face'          => 'A',
     *     'reference'     => 'PBTCAIS-05A',
     *     'is_fallback'   => bool — true si un code a été dérivé du nom
     *   ]
     */
    public function preview(
        Commune $commune,
        ?PanelCategory $category = null,
        ?string $face = null,
        ?int $excludeId = null,
    ): array {
        $communeCode  = $commune->code;
        $categoryCode = $category?->code;

        $communeFallback  = !$communeCode;
        $categoryFallback = $category && $categoryCode === null; // null vs '' : '' = Classique volontaire

        if ($communeFallback) {
            $communeCode = $this->fallbackCode($commune->name);
        }
        if ($categoryFallback) {
            $categoryCode = $this->fallbackCode($category->name);
        }

        $base   = $communeCode . ($categoryCode ?? '');
        $face   = $this->normalizeFace($face);
        $number = $this->nextNumberFor($base, $face, $excludeId);

        return [
            'commune_code'         => $communeCode,
            'category_code'        => $categoryCode,
            'base'                 => $base,
            'number'               => $number,
            'face'                 => $face,
            'reference'            => $base . '-' . $number . $face,
            'commune_is_fallback'  => $communeFallback,
            'category_is_fallback' => $categoryFallback,
        ];
    }

    /**
     * Préfixe = code commune + code catégorie (ce dernier peut être vide
     * pour la catégorie Classique). Fallback sur le nom si code absent.
     */
    private function basePrefix(Commune $commune, ?PanelCategory $category): string
    {
        $cc = $commune->code ?: $this->fallbackCode($commune->name);
        $kc = $category?->code ?? ($category ? $this->fallbackCode($category->name) : '');

        return $cc . $kc;
    }

    /**
     * Normalise la face (A/B/C/D, majuscule, ou null).
     */
    private function normalizeFace(?string $face): string
    {
        if (!$face) {
            return '';
        }
        $face = strtoupper(trim($face));
        return in_array($face, ['A', 'B', 'C', 'D'], true) ? $face : '';
    }

    /**
     * Cherche le plus petit numéro libre pour le préfixe (avec ou sans
     * face donnée). Renvoie une chaîne déjà paddée.
     *
     * Stratégie : on liste tous les numéros existants pour le préfixe
     * et la face, on retourne le premier "trou" (1 par défaut).
     */
    private function nextNumberFor(string $base, string $face, ?int $excludeId): string
    {
        // Pattern d'extraction : {base}-{digits}{face?}
        // Ex : PBTCAIS-05A → digits = 05
        $like = $base . '-%';
        $query = Panel::query()
            ->where('reference', 'like', $like)
            ->withTrashed();
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $taken = [];
        foreach ($query->pluck('reference') as $ref) {
            // ex: PBTCAIS-05A  → on extrait "05"
            $tail = substr($ref, strlen($base) + 1); // après "{base}-"
            if (preg_match('/^(\d+)([A-Z]?)$/', $tail, $m)) {
                $num     = (int) $m[1];
                $refFace = $m[2];
                // Si on génère pour une face précise, on ne tient compte
                // que des références sans face OU avec la même face.
                // Sinon, on prend toutes les numérotations existantes
                // pour éviter les collisions sur le couple (num, face).
                if ($face === '' || $refFace === '' || $refFace === $face) {
                    $taken[$num] = true;
                }
            }
        }

        // Premier numéro libre à partir de 1
        $n = 1;
        while (isset($taken[$n])) {
            $n++;
        }

        return $n < 100 ? str_pad((string) $n, 2, '0', STR_PAD_LEFT)
                        : str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Code fallback dérivé d'un nom : 3 premières lettres normalisées,
     * en majuscules, sans accent ni espace. Pour proposer un code
     * raisonnable quand le champ `code` n'a pas encore été renseigné.
     */
    public function fallbackCode(string $name): string
    {
        $n = mb_strtolower(trim($name), 'UTF-8');
        $n = str_replace(
            ['à','â','ä','é','è','ê','ë','î','ï','ô','ö','ù','û','ü','ç',"'",'-',' '],
            ['a','a','a','e','e','e','e','i','i','o','o','u','u','u','c','','',''],
            $n
        );
        // Garde uniquement [a-z]
        $n = preg_replace('/[^a-z]/', '', $n);
        return strtoupper(substr($n, 0, 3));
    }

    /**
     * Vérifie qu'une référence est disponible (utile en edit).
     */
    public function isAvailable(string $reference, ?int $excludeId = null): bool
    {
        $q = Panel::query()->where('reference', $reference)->withTrashed();
        if ($excludeId) {
            $q->where('id', '!=', $excludeId);
        }
        return !$q->exists();
    }
}

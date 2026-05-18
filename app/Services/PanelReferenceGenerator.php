<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelCategory;
use Illuminate\Support\Facades\DB;

/**
 * Génération des références panneaux selon les patterns historiques
 * CIBLE CI. Modèle unifié :
 *
 *     reference = commune.code + category.code + numéro + face
 *
 * Le séparateur (tiret avant/après) est intégré au code catégorie
 * pour couvrir les deux conventions présentes dans le parc :
 *
 *   - Catégories "joined" (tiret après le code) :
 *       CAIS- → PBT + CAIS- + 05 + A = PBTCAIS-05A
 *       LUP-  → CDY + LUP-  + 001    = CDYLUP-001
 *       P-    → BKE + P-    + 008    = BKEP-008
 *
 *   - Catégories "prefixed" (tiret avant le code) :
 *       -CH   → ABO + -CH   + 001    = ABO-CH001
 *       -PM   → YOP + -PM   + 01     = YOP-PM01
 *       -PAN- → YOP + -PAN- + 01     = YOP-PAN-01
 *
 *   - Classique (pas de catégorie) :
 *       -     → ADJ + -     + 002    = ADJ-002
 *
 * Règles d'attribution :
 *   - Numéro = MAX existant + 1 pour le préfixe (jamais le premier
 *     trou : un panneau supprimé garde son numéro réservé à vie pour
 *     éviter toute confusion avec d'anciens contrats).
 *   - Appariement de face (B/C/D) : si l'utilisateur enregistre la
 *     face B et qu'il existe déjà la face A pour un numéro sans B
 *     correspondant, on propose ce numéro pour compléter le couple.
 *   - Padding : convention la plus fréquente dans le préfixe. Défaut
 *     3 chiffres (001, 002… — convention CIBLE CI).
 *   - Compte les soft-deleted : un numéro libéré n'est jamais recyclé.
 *   - Si le code commune/catégorie est vide en BD, fallback sur les
 *     3 premières lettres du nom (l'admin pourra le stabiliser).
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

        // Le séparateur est désormais intégré au code catégorie : on
        // concatène directement sans injecter un tiret supplémentaire.
        return $base . $number . $face;
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
        $communeFallback  = !$commune->code;
        $categoryFallback = $category && $category->code === null;

        $communeCode  = $commune->code ?: $this->fallbackCode($commune->name);
        $categorySeg  = $this->resolveCategorySegment($category);

        $base   = $communeCode . $categorySeg;
        $face   = $this->normalizeFace($face);
        $number = $this->nextNumberFor($base, $face, $excludeId);

        return [
            'commune_code'         => $communeCode,
            'category_code'        => $categorySeg,
            'base'                 => $base,
            'number'               => $number,
            'face'                 => $face,
            'reference'            => $base . $number . $face,
            'commune_is_fallback'  => $communeFallback,
            'category_is_fallback' => $categoryFallback,
        ];
    }

    /**
     * Préfixe = code commune + code catégorie (avec séparateur intégré).
     *
     * Rétrocompatibilité : si le code catégorie ne contient AUCUN
     * tiret (ancien format pré-normalisation), on injecte un tiret
     * en suffix pour rester cohérent avec la convention "joined"
     * historique. Cela permet au générateur de fonctionner correc-
     * tement même si la migration de normalisation n'a pas encore
     * été appliquée (fenêtre de déploiement).
     */
    private function basePrefix(Commune $commune, ?PanelCategory $category): string
    {
        $cc = $commune->code ?: $this->fallbackCode($commune->name);
        $kc = $this->resolveCategorySegment($category);
        return $cc . $kc;
    }

    /**
     * Résout le segment "catégorie" du préfixe en gérant tous les cas :
     *   - NULL category → "-" (Classique)
     *   - code NULL en BD → fallback lettres + "-"
     *   - code "" vide en BD → "-" (Classique explicite)
     *   - code sans tiret (ancien format) → on ajoute "-" en suffix
     *   - code avec tiret (format normalisé) → tel quel
     */
    private function resolveCategorySegment(?PanelCategory $category): string
    {
        if ($category === null) {
            return '-';
        }
        $code = $category->code;
        if ($code === null) {
            return $this->fallbackCode($category->name) . '-';
        }
        if ($code === '') {
            return '-';
        }
        // Format normalisé : contient au moins un tiret → tel quel
        if (str_contains($code, '-')) {
            return $code;
        }
        // Format hérité : code sans tiret → suffixe avec "-"
        return $code . '-';
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
     * Calcule le prochain numéro libre pour le préfixe, en s'appuyant
     * sur l'inventaire existant (panneaux actifs ET soft-deleted, pour
     * que les numéros supprimés ne soient JAMAIS recyclés — sécurité).
     *
     * Stratégie :
     *   1. On lit toutes les références existantes pour le préfixe et
     *      on les indexe par numéro → faces présentes.
     *   2. On détecte le padding utilisé (longueur la plus fréquente
     *      des chiffres). Défaut : 3 chiffres (001, 002…).
     *   3. **Appariement de face** (B/C/D) : si l'utilisateur demande
     *      la face B et qu'il existe déjà une face A sans face B avec
     *      le même numéro (cas typique : on enregistre le dos d'un
     *      panneau dont la face avant existe déjà), on propose le
     *      MÊME numéro pour que la nouvelle face complète le couple.
     *      Idem pour C (qui s'apparie avec A/B partiels) et D.
     *   4. **Incrémentation classique** : sinon, on prend MAX existant
     *      + 1 (jamais le premier trou — un panneau supprimé garde
     *      son numéro réservé à jamais).
     */
    private function nextNumberFor(string $base, string $face, ?int $excludeId): string
    {
        // Le séparateur étant intégré au base prefix (CAIS-, -CH, -PAN-,
        // …), on cherche toutes les refs commençant exactement par ce
        // préfixe. Le filtrage strict est ensuite assuré par la regex
        // qui valide que seuls des chiffres + une lettre optionnelle
        // suivent — toute ref avec autre chose entre la base et le
        // numéro est écartée (évite les faux positifs entre catégories
        // proches).
        $like = $this->escapeLike($base) . '%';
        $query = Panel::query()
            ->where('reference', 'like', $like)
            ->withTrashed();
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Map: numéro → set de faces présentes (ex : 7 → ['A'=>true])
        $byNum      = [];
        $padLengths = [];
        foreach ($query->pluck('reference') as $ref) {
            if (!str_starts_with($ref, $base)) continue;
            $tail = substr($ref, strlen($base)); // après la base complète
            if (preg_match('/^(\d+)([A-Z]?)$/', $tail, $m)) {
                $num                       = (int) $m[1];
                $refFace                   = $m[2];
                $byNum[$num][$refFace]     = true;
                $padLengths[strlen($m[1])] = ($padLengths[strlen($m[1])] ?? 0) + 1;
            }
        }

        // Padding : convention la plus fréquente pour ce préfixe.
        // Sinon défaut 3 chiffres (001, 002 — convention CIBLE CI).
        if (!empty($padLengths)) {
            arsort($padLengths);
            $padTo = (int) array_key_first($padLengths);
        } else {
            $padTo = 3;
        }

        // ── Appariement de face (B/C/D) ────────────────────────────
        // Cherche le plus grand numéro où la face demandée est libre
        // mais où AU MOINS une autre face existe — c'est le cas
        // typique "je viens enregistrer le dos d'un panneau dont la
        // face avant a déjà été saisie".
        $candidate = null;
        if (in_array($face, ['B', 'C', 'D'], true)) {
            foreach ($byNum as $num => $faces) {
                $hasRequested = isset($faces[$face]);
                // Y a-t-il au moins une autre face peuplée pour ce num ?
                $hasOtherFace = false;
                foreach ($faces as $f => $_) {
                    if ($f !== $face && $f !== '') { $hasOtherFace = true; break; }
                }
                if (!$hasRequested && $hasOtherFace) {
                    if ($candidate === null || $num > $candidate) {
                        $candidate = $num;
                    }
                }
            }
        }

        // ── Incrémentation classique : MAX + 1 ─────────────────────
        if ($candidate !== null) {
            $n = $candidate;
        } else {
            $n = empty($byNum) ? 1 : (max(array_keys($byNum)) + 1);
        }

        // Si le numéro dépasse la convention de padding, on étend
        // (ex : 999 avec padding 3 → 1000 passe à 4 chiffres).
        $padTo = max($padTo, strlen((string) $n));

        return str_pad((string) $n, $padTo, '0', STR_PAD_LEFT);
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

    /**
     * Échappe les caractères spéciaux LIKE (% et _) pour qu'un code
     * contenant accidentellement un underscore ne joke pas les
     * résultats.
     */
    private function escapeLike(string $s): string
    {
        return addcslashes($s, '%_\\');
    }
}

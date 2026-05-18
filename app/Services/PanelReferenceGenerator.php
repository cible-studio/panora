<?php

namespace App\Services;

use App\Models\Commune;
use App\Models\Panel;
use App\Models\PanelCategory;
use Illuminate\Support\Facades\DB;

/**
 * Génération des références panneaux selon la convention CIBLE CI
 * unifiée :
 *
 *     reference = commune.code + "-" + category.code + numéro + face
 *
 * Exemples :
 *   PBT  + - + CAIS + 001 + A = PBT-CAIS001A   (Caisson Port-Bouët)
 *   ABO  + - + CH   + 001 + ''= ABO-CH001      (Chevalet Abobo)
 *   CDY  + - + LUP  + 001     = CDY-LUP001     (Lampadaire Cocody)
 *   BKE  + - + P    + 001     = BKE-P001       (Pylône Bouaké)
 *   TVIL + - + ''   + 002 + A = TVIL-002A      (Classique Treichville)
 *   ADJ  + - + ''   + 001     = ADJ-001        (Classique Adjamé)
 *
 * Règles d'attribution :
 *   - Numéro = MAX existant + 1 pour le préfixe (jamais le premier
 *     trou — un panneau supprimé garde son numéro réservé à vie pour
 *     éviter toute confusion avec d'anciens contrats).
 *   - **Compatibilité legacy** : le compteur examine AUSSI les refs
 *     dans les anciens formats du parc (PBTCAIS-05A, YOP-PAN-01,
 *     ADJC-03A…). Ainsi, la numérotation continue sans collision
 *     même si on convertit progressivement vers le nouveau format.
 *   - Appariement de face (B/C/D) : si l'utilisateur enregistre la
 *     face B et qu'il existe déjà la face A pour un numéro sans B
 *     correspondant, on propose ce numéro pour compléter le couple.
 *   - Padding : 3 chiffres par défaut (001, 002…). Auto-extension
 *     si on déborde 999.
 *   - Compte les soft-deleted : un numéro libéré n'est jamais recyclé.
 *   - Si le code commune/catégorie est vide en BD, fallback sur les
 *     3 premières lettres du nom.
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
        [$communeCode, $catCode] = $this->resolveCodes($commune, $category);
        $face   = $this->normalizeFace($face);
        $number = $this->nextNumberFor($communeCode, $catCode, $face, $excludeId);

        return $communeCode . '-' . $catCode . $number . $face;
    }

    /**
     * Décomposition pour l'aperçu UI :
     *   [
     *     'commune_code'         => 'PBT',
     *     'category_code'        => 'CAIS',
     *     'base'                 => 'PBT-CAIS',
     *     'number'               => '001',
     *     'face'                 => 'A',
     *     'reference'            => 'PBT-CAIS001A',
     *     'commune_is_fallback'  => bool — true si code dérivé du nom
     *     'category_is_fallback' => bool
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

        [$communeCode, $catCode] = $this->resolveCodes($commune, $category);
        $face   = $this->normalizeFace($face);
        $number = $this->nextNumberFor($communeCode, $catCode, $face, $excludeId);

        return [
            'commune_code'         => $communeCode,
            'category_code'        => $catCode,
            'base'                 => $communeCode . '-' . $catCode,
            'number'               => $number,
            'face'                 => $face,
            'reference'            => $communeCode . '-' . $catCode . $number . $face,
            'commune_is_fallback'  => $communeFallback,
            'category_is_fallback' => $categoryFallback,
        ];
    }

    /**
     * Résout les deux codes (commune, catégorie) à partir des modèles.
     * Les codes sont nettoyés de tout tiret résiduel (cas où l'ancien
     * format avec séparateurs intégrés serait encore en BD avant que
     * la migration `simplify_panel_category_codes` ne tourne).
     *
     * @return array{0:string,1:string}  [commune_code, category_code]
     */
    private function resolveCodes(Commune $commune, ?PanelCategory $category): array
    {
        $communeCode = $commune->code ?: $this->fallbackCode($commune->name);

        if ($category === null) {
            $catCode = '';
        } elseif ($category->code === null) {
            $catCode = $this->fallbackCode($category->name);
        } else {
            $catCode = $category->code;
        }

        // Rétrocompat : retire les tirets résiduels (ex: "CAIS-",
        // "-CH", "-PAN-") issus de migrations intermédiaires. Le
        // nouveau format ajoute lui-même le tiret au bon endroit.
        $catCode = trim($catCode, '-');

        return [$communeCode, $catCode];
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
     * Calcule le prochain numéro libre pour le couple (commune, cat).
     *
     * Stratégie :
     *   1. On scanne TOUS les formats possibles (nouveau + legacy)
     *      pour le couple, afin que la numérotation continue sans
     *      collision sur les anciennes refs du parc (PBTCAIS-05A,
     *      YOP-PAN-01, CDYC-03A, …).
     *   2. On indexe les refs trouvées par numéro → faces présentes.
     *   3. **Appariement de face** (B/C/D) : si l'utilisateur demande
     *      la face B et qu'il existe déjà une face A sans face B
     *      avec le même numéro, on propose ce numéro pour compléter
     *      le couple. Idem pour C/D (trivision).
     *   4. **MAX+1** : sinon, on prend max existant + 1. Les soft-
     *      deleted sont comptés pour ne JAMAIS recycler un numéro.
     *
     * @param  string $communeCode  ex: PBT, YOP, ADJ
     * @param  string $catCode      ex: CAIS, CH, LUP, P — vide pour Classique
     */
    private function nextNumberFor(
        string $communeCode,
        string $catCode,
        string $face,
        ?int $excludeId
    ): string {
        // Patterns possibles pour la même catégorie selon les époques :
        //   - Nouveau format : "{COMMUNE}-{CAT}{NN}{FACE?}"  → PBT-CAIS001A
        //   - Legacy joined  : "{COMMUNE}{CAT}-{NN}{FACE?}"  → PBTCAIS-05A
        //   - Legacy double  : "{COMMUNE}-{CAT}-{NN}{FACE?}" → YOP-PAN-01
        //   - Legacy attach  : "{COMMUNE}-{CAT}{NN}{FACE?}"  (=nouveau)
        //   - Pour Classique : "{COMMUNE}-{NN}{FACE?}"       → TVIL-002A
        $cc = $this->escapeLike($communeCode);
        $kc = $this->escapeLike($catCode);

        $query = Panel::query()->withTrashed();
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        if ($catCode === '') {
            // Classique : seul format légitime → "{COMMUNE}-{NN}{FACE?}"
            // On évite les faux positifs (ex: ADJ-CAIS-…) via la regex.
            $query->where('reference', 'like', $cc . '-%');
        } else {
            // Avec catégorie : on accepte 3 formats. SQL "OR" permet de
            // tout récupérer en une seule requête.
            $query->where(function ($q) use ($cc, $kc) {
                $q->where('reference', 'like', $cc . '-' . $kc . '%')   // PBT-CAIS001A
                  ->orWhere('reference', 'like', $cc . $kc . '-%')      // PBTCAIS-05A
                  ->orWhere('reference', 'like', $cc . '-' . $kc . '-%'); // YOP-PAN-01
            });
        }

        // Regexes d'extraction du numéro selon le format détecté.
        // L'ordre est important : le plus spécifique en premier.
        $extractors = $catCode === ''
            ? [
                '/^' . preg_quote($communeCode, '/') . '-(\d+)([A-Z]?)$/',
            ]
            : [
                '/^' . preg_quote($communeCode . '-' . $catCode . '-', '/') . '(\d+)([A-Z]?)$/', // legacy double
                '/^' . preg_quote($communeCode . $catCode . '-', '/') . '(\d+)([A-Z]?)$/',       // legacy joined
                '/^' . preg_quote($communeCode . '-' . $catCode, '/') . '(\d+)([A-Z]?)$/',       // nouveau
            ];

        // Map: numéro → set de faces présentes
        $byNum = [];
        foreach ($query->pluck('reference') as $ref) {
            foreach ($extractors as $rx) {
                if (preg_match($rx, $ref, $m)) {
                    $num                   = (int) $m[1];
                    $refFace               = $m[2];
                    $byNum[$num][$refFace] = true;
                    break; // un seul format match par ref
                }
            }
        }

        // Padding : 3 chiffres fixes (001, 002 — convention CIBLE CI),
        // auto-extension si on déborde 999.
        $padTo = 3;

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

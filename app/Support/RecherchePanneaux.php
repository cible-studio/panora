<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Recherche d'un panneau par le texte saisi — SOURCE UNIQUE (2026-10-30).
 *
 * La règle vient de l'inventaire (PanelController::index), où elle a été
 * mise au point : **mot entier, insensible aux accents**.
 *
 *   · « ABG »        trouve ABG-002, pas CABG-001
 *   · « port bouet » trouve « Port Bouët »
 *   · cherche dans la référence, le nom, le quartier, l'adresse et la
 *     commune
 *
 * Elle était enfermée dans deux méthodes privées de PanelController. Le
 * devis, lui, faisait un simple LIKE : taper « bouet » n'y trouvait rien.
 * Les deux écrans appellent maintenant ce support — pas de seconde
 * écriture de la règle.
 */
final class RecherchePanneaux
{
    /** Accents → lettres simples. Même table des deux côtés, PHP et SQL. */
    private const ACCENTS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
    ];

    /**
     * Applique la recherche à une requête sur `panels`.
     *
     * Un terme vide laisse la requête inchangée : à l'appelant de décider
     * s'il affiche tout ou rien.
     */
    public static function appliquer(Builder $query, ?string $terme): Builder
    {
        $terme = trim((string) $terme);
        if ($terme === '') {
            return $query;
        }

        $motif = self::motif($terme);

        return $query->where(function ($q) use ($motif) {
            $q->whereRaw(self::sansAccents('reference') . ' REGEXP ?', [$motif])
              ->orWhereRaw(self::sansAccents('name') . ' REGEXP ?', [$motif])
              ->orWhereRaw(self::sansAccents('quartier') . ' REGEXP ?', [$motif])
              ->orWhereRaw(self::sansAccents('adresse') . ' REGEXP ?', [$motif])
              ->orWhereHas('commune', fn ($c) => $c->whereRaw(self::sansAccents('name') . ' REGEXP ?', [$motif]));
        });
    }

    /**
     * Expression régulière « mot entier » sur le terme normalisé.
     * Les bornes sont des caractères non alphanumériques, ou le début et
     * la fin de la chaîne : c'est ce qui distingue ABG-002 de CABG-001.
     */
    public static function motif(string $terme): string
    {
        return '(^|[^a-zA-Z0-9])' . preg_quote(self::normaliser($terme), '/') . '([^a-zA-Z0-9]|$)';
    }

    /** Minuscules et sans accents, côté PHP. */
    public static function normaliser(string $texte): string
    {
        return strtr(mb_strtolower($texte, 'UTF-8'), self::ACCENTS);
    }

    /**
     * La même normalisation, côté SQL : des REPLACE imbriqués autour d'un
     * LOWER. Ne dépend pas de la collation de la base — la recherche se
     * comporte pareil en utf8mb4_unicode_ci, _general_ci ou _bin.
     */
    public static function sansAccents(string $colonne): string
    {
        $expression = "LOWER({$colonne})";
        foreach (self::ACCENTS as $de => $vers) {
            $expression = "REPLACE({$expression}, '{$de}', '{$vers}')";
        }

        return $expression;
    }
}

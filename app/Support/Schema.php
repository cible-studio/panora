<?php

namespace App\Support;

/**
 * Schéma de l'espace d'administration.
 *
 * Le principe : décrire les champs éditables comme des DONNÉES, et laisser
 * l'admin fabriquer les formulaires, la validation et le rendu à partir de
 * cette description. Rendre un contenu modifiable coûte alors une ligne de
 * schéma, au lieu d'un écran, d'un contrôleur et d'une validation à écrire.
 *
 * C'est ce qui permet de couvrir tout le site sans que l'admin devienne
 * ingérable — et surtout, de continuer à l'étendre sans développeur pour
 * chaque nouveau champ.
 *
 * Types disponibles :
 *   texte   ligne simple
 *   zone    paragraphe (textarea)
 *   nombre  entier, pour les compteurs animés
 *   image   téléversement, avec repli sur le visuel livré avec le site
 *   liste   suite d'entrées courtes (un élément par ligne)
 */
class Schema
{
    /**
     * @return array<string, array{titre:string, resume:string, couleur:string, url:?string, groupes:array}>
     */
    public static function pages(): array
    {
        return config('admin-schema', []);
    }

    public static function page(string $cle): ?array
    {
        return self::pages()[$cle] ?? null;
    }

    /** Tous les champs d'une page, aplatis : clé => définition. */
    public static function champs(string $cle): array
    {
        $champs = [];

        foreach (self::page($cle)['groupes'] ?? [] as $groupe) {
            foreach ($groupe['champs'] ?? [] as $nom => $def) {
                $champs[$nom] = $def;
            }
        }

        return $champs;
    }

    /**
     * Règles de validation déduites du schéma.
     *
     * Les images sont exclues : elles arrivent en fichier et sont validées
     * séparément, avec leurs propres contraintes de format et de poids.
     */
    public static function regles(string $cle): array
    {
        $regles = [];

        foreach (self::champs($cle) as $nom => $def) {
            $type = $def['type'] ?? 'texte';

            $regles[$nom] = match ($type) {
                'nombre' => ['required', 'digits_between:1,7'],
                'zone'   => ['required', 'string', 'max:' . ($def['max'] ?? 2000)],
                'liste'  => ['nullable', 'string', 'max:' . ($def['max'] ?? 1500)],
                'image'  => null,
                default  => [($def['facultatif'] ?? false) ? 'nullable' : 'required', 'string', 'max:' . ($def['max'] ?? 300)],
            };
        }

        return array_filter($regles);
    }

    /** Champs de type image d'une page. */
    public static function images(string $cle): array
    {
        return array_filter(
            self::champs($cle),
            fn ($def) => ($def['type'] ?? '') === 'image'
        );
    }
}

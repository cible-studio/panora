<?php

namespace App\Support;

/**
 * Nettoyage des noms de fichiers saisis par l'utilisateur avant un
 * téléchargement (PDF, Excel…).
 *
 * Pourquoi cette classe existe (2026-09-23) :
 * La logique vivait en méthode privée `sanitizePdfFilename()` dans
 * ReservationController, et elle était figée sur l'extension `.pdf`.
 * Quand la même fonctionnalité a été demandée sur les exports du module
 * Taxes (PDF **et** Excel), la recopier aurait créé deux nettoyages
 * différents pour le même besoin — donc deux comportements possibles
 * face à un nom hostile. Elle est ici, et elle est la seule.
 *
 * Ce qui est retiré du nom saisi :
 *   - les caractères interdits par Windows et par l'en-tête HTTP
 *     Content-Disposition : / \ : * ? " < > |
 *   - les caractères de contrôle (\x00-\x1F), qui permettraient
 *     d'injecter un saut de ligne dans l'en-tête HTTP
 *   - les points et espaces en début/fin (Windows les refuse)
 *
 * Si le nom devient vide après nettoyage, on retombe sur le nom par
 * défaut : jamais de fichier sans nom.
 */
class DownloadFilename
{
    /** Longueur max du nom SANS extension (marge pour « .xlsx »). */
    private const MAX_LENGTH = 96;

    /**
     * @param  string|null $custom     Nom saisi par l'utilisateur (peut être null/vide)
     * @param  string      $fallback   Nom par défaut si $custom est vide ou invalide
     * @param  string      $extension  Extension voulue, sans le point ('pdf', 'xlsx'…)
     * @return string                  Nom sûr, extension garantie
     */
    public static function sanitize(?string $custom, string $fallback, string $extension = 'pdf'): string
    {
        $extension = ltrim(strtolower(trim($extension)), '.');
        if ($extension === '') {
            $extension = 'pdf';
        }

        $clean = self::clean((string) $custom, $extension);

        if ($clean === '') {
            // Le fallback passe par le même nettoyage : il est construit
            // à partir de libellés de période traduits, donc il peut
            // contenir des espaces ou des accents.
            $clean = self::clean($fallback, $extension);
        }

        if ($clean === '') {
            $clean = 'export';
        }

        return $clean . '.' . $extension;
    }

    /**
     * Nettoie un nom et retire l'extension attendue si l'utilisateur
     * l'a saisie lui-même (« rapport.pdf » → « rapport »).
     */
    private static function clean(string $name, string $extension): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        // Extension déjà saisie → on la retire, elle sera recollée après.
        $name = preg_replace('/\.' . preg_quote($extension, '/') . '$/i', '', $name) ?? $name;

        // Caractères interdits (système de fichiers + en-tête HTTP).
        $name = preg_replace('/[\/\\\\:\*\?"<>\|\x00-\x1F]/', '', $name) ?? '';
        // Espaces multiples → un seul.
        $name = preg_replace('/\s+/', ' ', $name) ?? '';
        // Windows refuse les points et espaces en début/fin.
        $name = trim($name, " .");

        if (mb_strlen($name) > self::MAX_LENGTH) {
            $name = rtrim(mb_substr($name, 0, self::MAX_LENGTH), " .");
        }

        return $name;
    }
}

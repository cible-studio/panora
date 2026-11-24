<?php

namespace App\Support;

/**
 * Montant en toutes lettres, en français (2026-11-24) — source unique.
 *
 * Utilisé par le PDF du devis (« Arrêté le présent devis à la somme de… »)
 * et par le relevé des taxes pour la mairie (« Arrêté le présent relevé à
 * la somme de… »). Avant, la fonction vivait dans la vue du devis.
 *
 * Règles d'accord : « quatre-vingts » et « deux cents » prennent un s
 * seulement en fin de nombre — pas devant « mille » (« deux cent mille »,
 * « quatre-vingt mille »), qui est invariable ; « millions » s'accorde.
 * Sans l'extension intl : elle n'est pas dans l'image de production.
 */
final class MontantEnLettres
{
    private const UNITES = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];

    private const DIZAINES = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante',
        'soixante', 'quatre-vingt', 'quatre-vingt'];

    public static function en(int $n): string
    {
        if ($n === 0) {
            return 'zéro';
        }
        if ($n < 0) {
            return 'moins ' . self::en(-$n);
        }

        $morceaux = [];
        if ($n >= 1_000_000_000) {
            $g = intdiv($n, 1_000_000_000);
            $morceaux[] = $g === 1 ? 'un milliard' : self::moinsDeMille($g, true) . ' milliards';
            $n %= 1_000_000_000;
        }
        if ($n >= 1_000_000) {
            $m = intdiv($n, 1_000_000);
            $morceaux[] = $m === 1 ? 'un million' : self::moinsDeMille($m, true) . ' millions';
            $n %= 1_000_000;
        }
        if ($n >= 1000) {
            $k = intdiv($n, 1000);
            // « mille » est invariable, et « cent » / « quatre-vingt » ne
            // prennent pas de s devant lui.
            $morceaux[] = $k === 1 ? 'mille' : self::moinsDeMille($k, false) . ' mille';
            $n %= 1000;
        }
        if ($n > 0) {
            $morceaux[] = self::moinsDeMille($n, true);
        }

        return implode(' ', $morceaux);
    }

    /** @param bool $finDeNombre  le s de « cents » / « quatre-vingts » n'existe qu'en fin de nombre */
    private static function moinsDeMille(int $n, bool $finDeNombre): string
    {
        if ($n < 100) {
            return self::moinsDeCent($n, $finDeNombre);
        }
        $c = intdiv($n, 100);
        $r = $n % 100;
        $cent = $c === 1 ? 'cent' : self::UNITES[$c] . ' cent' . ($r === 0 && $finDeNombre ? 's' : '');

        return $cent . ($r ? ' ' . self::moinsDeCent($r, $finDeNombre) : '');
    }

    private static function moinsDeCent(int $n, bool $finDeNombre): string
    {
        if ($n < 20) {
            return self::UNITES[$n];
        }
        $d = intdiv($n, 10);
        $u = $n % 10;
        if ($d === 7 || $d === 9) {
            return self::DIZAINES[$d] . ($d === 7 && $u === 1 ? ' et ' : '-') . self::UNITES[10 + $u];
        }
        if ($d === 8 && $u === 0) {
            return 'quatre-vingt' . ($finDeNombre ? 's' : '');
        }

        return self::DIZAINES[$d] . ($u === 0 ? '' : (($u === 1 && $d !== 8) ? ' et ' : '-') . self::UNITES[$u]);
    }
}

<?php

namespace App\Support;

/**
 * Mot de passe temporaire envoyé par mail — SOURCE UNIQUE (2026-11-02).
 *
 * Lisible au téléphone et facile à retaper, mais imprévisible. Il est
 * toujours accompagné de `must_change_password` : la personne le change
 * à sa première connexion, il ne sert qu'une fois.
 *
 * ⚠ CE QUE CETTE CLASSE REMPLACE. Jusqu'au 2026-11-02,
 * ClientController::generateReadablePassword() renvoyait la chaîne
 * « password » — littéralement, pour tous les comptes clients, à la
 * création comme à la réinitialisation (commit 9b3be699 du 2026-04-24,
 * « mot de passe par défaut pour les client »). Toute personne
 * connaissant l'adresse d'un client pouvait entrer dans son espace.
 *
 * Tirage : random_int(), le générateur sûr de PHP. array_rand() et
 * rand() ne le sont pas — leur suite est prévisible à partir de
 * quelques tirages, ce qui n'a rien de théorique pour un mot de passe.
 */
final class MotDePasseTemporaire
{
    /**
     * Mots courts, sans accent et sans homophone gênant : ils se dictent
     * au téléphone sans hésitation.
     */
    private const COULEURS = [
        'Bleu', 'Rouge', 'Vert', 'Jaune', 'Violet', 'Orange', 'Rose', 'Gris',
        'Noir', 'Blanc', 'Dore', 'Argent', 'Cuivre', 'Turquoise', 'Indigo', 'Corail',
    ];

    private const NOMS = [
        'Soleil', 'Lagune', 'Baobab', 'Savane', 'Ocean', 'Colline', 'Riviere', 'Palmier',
        'Etoile', 'Lagon', 'Sable', 'Nuage', 'Falaise', 'Comete', 'Horizon', 'Zenith',
        'Cascade', 'Mangrove', 'Delta', 'Recif', 'Dune', 'Canyon', 'Prairie', 'Sommet',
    ];

    /**
     * « Bleu-Soleil-4821 ».
     *
     * 16 × 24 × 9 000 ≈ 3,4 millions de combinaisons. Suffisant pour un
     * mot de passe à usage unique, changé dès la première connexion, et
     * protégé par la limitation des tentatives de connexion.
     */
    public static function generer(): string
    {
        return sprintf(
            '%s-%s-%d',
            self::COULEURS[random_int(0, count(self::COULEURS) - 1)],
            self::NOMS[random_int(0, count(self::NOMS) - 1)],
            random_int(1000, 9999),
        );
    }
}

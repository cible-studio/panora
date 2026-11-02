<?php

namespace Tests\Unit;

use App\Support\MotDePasseTemporaire;
use PHPUnit\Framework\TestCase;

/**
 * Mot de passe temporaire des comptes (2026-11-02).
 *
 * Jusqu'à cette date, ClientController::generateReadablePassword()
 * renvoyait la chaîne « password » : tous les espaces clients, créés ou
 * réinitialisés, partageaient le même mot de passe. Ces tests tiennent
 * la correction : le tirage est aléatoire, et la chaîne fautive ne peut
 * pas revenir dans le code sans les faire tomber.
 */
class MotDePasseTemporaireTest extends TestCase
{
    private const RACINE = __DIR__ . '/../../';

    private function source(string $chemin): string
    {
        $fichier = self::RACINE . $chemin;
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    /** Le fichier débarrassé de ses commentaires et de son indentation. */
    private function codeSansCommentaires(string $chemin): string
    {
        $fichier = self::RACINE . $chemin;
        $this->assertFileExists($fichier);

        return php_strip_whitespace($fichier);
    }

    // ── Le tirage ──────────────────────────────────────────────────

    public function test_le_mot_de_passe_n_est_jamais_la_chaine_password(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertNotSame('password', MotDePasseTemporaire::generer());
        }
    }

    public function test_deux_tirages_consecutifs_different(): void
    {
        // 3,4 millions de combinaisons : 500 tirages identiques seraient
        // le signe d'une constante déguisée.
        $tirages = [];
        for ($i = 0; $i < 500; $i++) {
            $tirages[MotDePasseTemporaire::generer()] = true;
        }

        $this->assertGreaterThan(
            400,
            count($tirages),
            'Le tirage doit être aléatoire, pas une valeur fixe ou quasi fixe.'
        );
    }

    public function test_la_forme_se_dicte_au_telephone(): void
    {
        // « Bleu-Soleil-4821 » : trois morceaux, sans accent, sans espace,
        // sans caractère qu'on confond à l'oral.
        for ($i = 0; $i < 50; $i++) {
            $mdp = MotDePasseTemporaire::generer();

            $this->assertMatchesRegularExpression(
                '/^[A-Z][a-z]+-[A-Z][a-z]+-\d{4}$/',
                $mdp,
                "Forme inattendue : {$mdp}"
            );
        }
    }

    public function test_le_tirage_utilise_le_generateur_sur(): void
    {
        // rand() et array_rand() sont prévisibles à partir de quelques
        // tirages : inacceptable pour un mot de passe, même temporaire.
        //
        // On lit le CODE sans les commentaires : la documentation de la
        // classe nomme justement ces fonctions pour expliquer pourquoi
        // elle les évite, et ne doit pas faire tomber le test.
        $code = $this->codeSansCommentaires('app/Support/MotDePasseTemporaire.php');

        $this->assertStringContainsString('random_int(', $code);
        $this->assertStringNotContainsString('array_rand(', $code);
        $this->assertDoesNotMatchRegularExpression('/\brand\(/', $code);
        $this->assertDoesNotMatchRegularExpression('/\bmt_rand\(/', $code);
    }

    // ── La règle n'est écrite qu'une fois ──────────────────────────

    public function test_aucun_mot_de_passe_en_dur_dans_les_comptes(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/ClientController.php',
            'app/Http/Controllers/Admin/UserController.php',
            'app/Support/MotDePasseTemporaire.php',
        ] as $fichier) {
            $code = $this->codeSansCommentaires($fichier);

            $this->assertDoesNotMatchRegularExpression(
                '/return\s+\x27password\x27\s*;/',
                $code,
                "{$fichier} : mot de passe en dur."
            );
            $this->assertDoesNotMatchRegularExpression(
                '/Hash::make\(\s*\x27[^\x27]+\x27\s*\)/',
                $code,
                "{$fichier} : Hash::make() sur une chaîne littérale."
            );
        }
    }

    public function test_le_client_passe_par_le_support_partage(): void
    {
        $source = $this->source('app/Http/Controllers/Admin/ClientController.php');

        $this->assertStringContainsString(
            'MotDePasseTemporaire::generer()',
            $source,
            'La création et la réinitialisation du compte client doivent tirer par le support partagé.'
        );
    }

    public function test_le_mot_de_passe_temporaire_force_le_changement(): void
    {
        // Le tirage ne vaut que pour une connexion : sans ce drapeau, un
        // mot de passe envoyé par mail resterait valable indéfiniment.
        $source = $this->source('app/Http/Controllers/Admin/ClientController.php');

        $this->assertSame(
            2,
            substr_count($source, "'must_change_password' => true"),
            'Création ET réinitialisation du compte client doivent forcer le changement.'
        );
    }
}

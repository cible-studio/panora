<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\User;
use App\Services\NotificationMailer;
use App\Services\RenvoiAcces;
use App\Services\ResultatRenvoi;
use PHPUnit\Framework\TestCase;

/**
 * « Renvoyer les accès » (2026-11-02).
 *
 * Demandé comme « un bouton simple pour renvoyer les accès ». Le mot de
 * passe en place n'est pas relisible : le geste réel est d'en tirer un
 * NOUVEAU et de l'envoyer. Ces tests tiennent les deux promesses faites
 * à l'utilisateur :
 *
 *   1. le geste est le MÊME pour le personnel et pour les clients, écrit
 *      une seule fois dans App\Services\RenvoiAcces ;
 *   2. l'interface dit que l'ancien mot de passe cesse de fonctionner,
 *      partout où le bouton apparaît.
 *
 * Les refus se testent sans base : ils tombent avant toute écriture.
 */
class RenvoiAccesTest extends TestCase
{
    private const RACINE = __DIR__ . '/../../';

    private function service(): RenvoiAcces
    {
        // Le mailer n'est jamais atteint par les cas de refus testés ici.
        return new RenvoiAcces(new NotificationMailer());
    }

    private function source(string $chemin): string
    {
        $fichier = self::RACINE . $chemin;
        $this->assertFileExists($fichier);

        return file_get_contents($fichier);
    }

    /**
     * Le fichier sans ses commentaires. Les contrôleurs expliquent en
     * commentaire ce qu'ils ne font PLUS, en nommant l'ancien code : la
     * documentation ne doit pas faire tomber le test.
     */
    private function codeSansCommentaires(string $chemin): string
    {
        $fichier = self::RACINE . $chemin;
        $this->assertFileExists($fichier);

        return php_strip_whitespace($fichier);
    }

    // ── Les refus : rien n'est touché ──────────────────────────────

    public function test_un_membre_du_personnel_sans_email_est_refuse(): void
    {
        $user = new User(['name' => 'Awa', 'email' => null, 'is_active' => true]);

        $resultat = $this->service()->pourUtilisateur($user);

        $this->assertFalse($resultat->ok);
        $this->assertStringContainsString("n'a pas d'email", $resultat->message);
        $this->assertNull($resultat->motDePasse);
    }

    public function test_un_compte_desactive_ne_recoit_pas_d_identifiants_frais(): void
    {
        // Renvoyer les accès à un compte fermé le rouvrirait de fait.
        $user = new User(['name' => 'Awa', 'email' => 'awa@cible-ci.com', 'is_active' => false]);

        $resultat = $this->service()->pourUtilisateur($user);

        $this->assertFalse($resultat->ok);
        $this->assertStringContainsString('désactivé', $resultat->message);
    }

    public function test_un_client_sans_compte_est_refuse(): void
    {
        // hasAccount() = password renseigné. Sans compte, il faut d'abord
        // le créer : ce n'est pas le même geste.
        $client = new Client(['name' => 'Orange CI', 'email' => 'contact@orange.ci']);

        $resultat = $this->service()->pourClient($client);

        $this->assertFalse($resultat->ok);
        $this->assertStringContainsString("n'a pas encore de compte", $resultat->message);
    }

    // ── Le mail bloqué n'enferme personne dehors ───────────────────

    public function test_un_mail_bloque_affiche_le_mot_de_passe_a_l_admin(): void
    {
        // Le mot de passe est déjà changé à ce stade : si l'admin ne le
        // voit pas, la personne ne peut plus entrer du tout.
        $resultat = ResultatRenvoi::envoiImpossible('Bleu-Soleil-4821', 'awa@cible-ci.com', 'SMTP indisponible');

        $this->assertTrue($resultat->ok, 'Le mot de passe a bien changé : ce n\'est pas un refus.');
        $this->assertSame('Bleu-Soleil-4821', $resultat->motDePasse);
        $this->assertStringContainsString('Bleu-Soleil-4821', $resultat->message);
        $this->assertStringContainsString('awa@cible-ci.com', $resultat->message);
        $this->assertStringContainsString('SMTP indisponible', $resultat->message);
    }

    // ── Un seul endroit porte la logique ───────────────────────────

    public function test_les_controleurs_ne_tirent_plus_de_mot_de_passe_eux_memes(): void
    {
        foreach ([
            'app/Http/Controllers/Admin/ClientController.php',
            'app/Http/Controllers/Admin/UserController.php',
        ] as $fichier) {
            $code = $this->codeSansCommentaires($fichier);

            $this->assertStringNotContainsString(
                'MotDePasseTemporaire::generer()',
                $code,
                "{$fichier} : le tirage appartient à RenvoiAcces, pas au contrôleur."
            );
            $this->assertStringNotContainsString(
                'generateReadablePassword',
                $code,
                "{$fichier} : méthode de tirage locale, supprimée le 2026-11-02."
            );
        }
    }

    public function test_le_client_et_le_personnel_passent_par_le_meme_service(): void
    {
        $client = $this->source('app/Http/Controllers/Admin/ClientController.php');
        $this->assertStringContainsString('RenvoiAcces::class)->creationPourClient(', $client);
        $this->assertStringContainsString('RenvoiAcces::class)->pourClient(', $client);

        $personnel = $this->source('app/Http/Controllers/Admin/UserController.php');
        $this->assertStringContainsString('RenvoiAcces::class)->pourUtilisateur(', $personnel);
    }

    public function test_le_mot_de_passe_client_reste_a_usage_unique(): void
    {
        // must_change_password est indissociable du tirage côté client :
        // une seule écriture, dans appliquerAuClient().
        $source = $this->source('app/Services/RenvoiAcces.php');

        $this->assertSame(
            1,
            substr_count($source, "'must_change_password' => true"),
            'Le drapeau doit être posé en un seul endroit, avec le tirage.'
        );
    }

    // ── L'interface ne promet pas l'impossible ─────────────────────

    public function test_la_liste_du_personnel_annonce_le_nouveau_mot_de_passe(): void
    {
        $vue = $this->source('resources/views/admin/users/index.blade.php');

        $this->assertStringContainsString("route('admin.users.acces.renvoyer', \$user)", $vue);
        $this->assertStringContainsString('cessera de fonctionner', $vue,
            "La confirmation doit dire que l'ancien mot de passe meurt.");

        // Un nom comme « N'Guessan » casserait la chaîne JS sans Js::from().
        $this->assertStringContainsString('Js::from($user->name)', $vue);
        $this->assertStringNotContainsString("confirm('Renvoyer les accès de {{ \$user->name }}", $vue);
    }

    public function test_la_modale_client_annonce_le_nouveau_mot_de_passe(): void
    {
        $vue = $this->source('resources/views/admin/clients/index.blade.php');

        $this->assertStringContainsString('Renvoyer les accès', $vue);
        $this->assertStringContainsString('cessera de fonctionner', $vue);
        $this->assertStringNotContainsString('Réinitialiser le mot de passe', $vue,
            'Ancien libellé : il laissait croire que le client gardait son accès.');
    }

    public function test_la_route_du_personnel_est_bridee(): void
    {
        // Un clic répété sur une liste ne doit pas réinitialiser dix
        // comptes par mégarde.
        $routes = $this->source('routes/admin.php');

        $this->assertStringContainsString("'users.acces.renvoyer'", $routes);
        $this->assertMatchesRegularExpression(
            '/renvoyer-acces.*?throttle:/s',
            $routes,
            'La route de renvoi doit être bridée.'
        );
    }
}

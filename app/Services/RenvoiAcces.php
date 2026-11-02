<?php

namespace App\Services;

use App\Mail\ClientAccountMail;
use App\Mail\UserWelcomeMail;
use App\Models\Client;
use App\Models\User;
use App\Support\MotDePasseTemporaire;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Mots de passe temporaires envoyés par mail — SOURCE UNIQUE (2026-11-02).
 *
 * Demandé par la direction : un bouton simple, sur le personnel comme
 * sur les clients, pour qu'une personne qui a perdu ses identifiants
 * les reçoive à nouveau sans qu'un admin ait à inventer un mot de passe.
 *
 * ⚠ CE QUE CE SERVICE NE PEUT PAS FAIRE. Il ne renvoie pas le mot de
 * passe existant : celui-ci n'est pas conservé, seule son empreinte
 * (le hash) l'est, et une empreinte ne se relit pas. Le bouton tire donc
 * un NOUVEAU mot de passe temporaire et l'envoie : l'ancien cesse de
 * fonctionner au moment du clic. Les libellés de l'interface le disent,
 * pour qu'aucun admin ne clique en croyant que rien ne change.
 *
 * Trois gestes, une seule mécanique (tirage → empreinte → mail → trace) :
 *   - creationPourClient() : le client reçoit son premier accès ;
 *   - pourClient()         : il le reçoit à nouveau ;
 *   - pourUtilisateur()    : un membre du personnel le reçoit à nouveau.
 *
 * Le personnel n'a PAS de colonne must_change_password (seule la table
 * clients en a une, migration du 2026-04-02) : son mot de passe
 * temporaire reste valable jusqu'à ce qu'il le change lui-même. C'est le
 * comportement existant ; ce service ne l'aggrave pas et ne le corrige
 * pas — la correction est inscrite dans TECHNICAL_DEBT.md.
 *
 * Les interlocuteurs du client (ClientUser, écran /equipe) sont gérés
 * par le client lui-même, pas par un admin : hors de ce service.
 */
final class RenvoiAcces
{
    public function __construct(private readonly NotificationMailer $mailer) {}

    // ── Le personnel ───────────────────────────────────────────────

    public function pourUtilisateur(User $user, ?int $parUtilisateurId = null): ResultatRenvoi
    {
        if (empty($user->email)) {
            return ResultatRenvoi::refuse("{$user->name} n'a pas d'email : impossible de lui envoyer ses accès.");
        }

        if (! $user->is_active) {
            // Un compte désactivé ne doit pas recevoir d'identifiants
            // frais : il faut d'abord le réactiver, décision explicite.
            return ResultatRenvoi::refuse("Le compte de {$user->name} est désactivé. Réactive-le d'abord.");
        }

        $motDePasse = MotDePasseTemporaire::generer();

        $user->update(['password' => Hash::make($motDePasse)]);

        return $this->envoyer(
            new UserWelcomeMail($user->fresh(), $motDePasse, 'password_reset'),
            $user->email,
            $motDePasse,
            'user.acces_renvoyes',
            ['user_id' => $user->id, 'par' => $parUtilisateurId],
        );
    }

    // ── Les clients ────────────────────────────────────────────────

    /** Premier accès : le client n'avait pas encore de compte. */
    public function creationPourClient(Client $client, ?int $parUtilisateurId = null): ResultatRenvoi
    {
        if (empty($client->email)) {
            return ResultatRenvoi::refuse("Ce client n'a pas d'email. Ajoutez-en un d'abord.");
        }

        $motDePasse = $this->appliquerAuClient($client);

        return $this->envoyer(
            new ClientAccountMail($client->fresh(), $motDePasse, false),
            $client->email,
            $motDePasse,
            'client.account_created',
            ['client_id' => $client->id, 'par' => $parUtilisateurId],
            succes: "✅ Compte créé. Identifiants envoyés à {$client->email}.",
            geste: 'Compte créé',
        );
    }

    /** Renvoi : le client a déjà un compte, il a perdu ses accès. */
    public function pourClient(Client $client, ?int $parUtilisateurId = null): ResultatRenvoi
    {
        if (! $client->hasAccount()) {
            return ResultatRenvoi::refuse("Ce client n'a pas encore de compte : crée-le d'abord.");
        }

        if (empty($client->email)) {
            return ResultatRenvoi::refuse("Ce client n'a pas d'email.");
        }

        $motDePasse = $this->appliquerAuClient($client);

        return $this->envoyer(
            new ClientAccountMail($client->fresh(), $motDePasse, true),
            $client->email,
            $motDePasse,
            'client.acces_renvoyes',
            ['client_id' => $client->id, 'par' => $parUtilisateurId],
        );
    }

    // ── La mécanique commune ───────────────────────────────────────

    /**
     * Tire le mot de passe et le pose sur le compte client. Le drapeau
     * must_change_password est indissociable du tirage : sans lui, un
     * mot de passe passé par la boîte mail resterait valable toujours.
     */
    private function appliquerAuClient(Client $client): string
    {
        $motDePasse = MotDePasseTemporaire::generer();

        $client->update([
            'password'             => Hash::make($motDePasse),
            'must_change_password' => true,
            'password_changed_at'  => null,
        ]);

        return $motDePasse;
    }

    private function envoyer(
        \Illuminate\Mail\Mailable $mail,
        string $email,
        string $motDePasse,
        string $action,
        array $trace,
        ?string $succes = null,
        string $geste = 'Mot de passe changé',
    ): ResultatRenvoi {
        // sendNow() : envoi synchrone. On veut savoir TOUT DE SUITE si le
        // mail est parti, car le mot de passe n'est plus relisible après.
        $envoi = $this->mailer->sendNow($email, $mail, context: ['action' => $action] + $trace);

        Log::info($action, $trace + ['mail_ok' => $envoi->ok]);

        return $envoi->ok
            ? ResultatRenvoi::envoye($succes ?? "📧 Nouveaux accès envoyés à {$email}.")
            : ResultatRenvoi::envoiImpossible($motDePasse, $email, $envoi->message, $geste);
    }
}

/**
 * Trois issues, pas deux : le mot de passe peut être changé alors que le
 * mail ne part pas. Dans ce cas l'admin DOIT voir le mot de passe tiré,
 * sinon la personne se retrouve enfermée dehors.
 */
final class ResultatRenvoi
{
    private function __construct(
        public readonly bool    $ok,
        public readonly string  $message,
        public readonly ?string $motDePasse = null,
    ) {}

    /** Mot de passe changé, mail parti. */
    public static function envoye(string $message): self
    {
        return new self(true, $message);
    }

    /** Rien n'a changé : le geste était impossible. */
    public static function refuse(string $message): self
    {
        return new self(false, $message);
    }

    /** Mot de passe changé, mail bloqué : à communiquer à la main. */
    public static function envoiImpossible(
        string $motDePasse,
        string $email,
        ?string $raison = null,
        string $geste = 'Mot de passe changé',
    ): self {
        return new self(
            true,
            "⚠️ {$geste}, mais l'email n'est pas parti"
                . ($raison ? " ({$raison})" : '')
                . ". Communique ce mot de passe à {$email} : {$motDePasse}",
            $motDePasse,
        );
    }
}

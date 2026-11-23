<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Mail\QuoteReponseClientMail;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Réponse du client à un devis (2026-11-23) — source unique des deux
 * chemins par lesquels le client répond :
 *   · l'espace client (ClientQuoteController, client connecté) ;
 *   · le lien privé du mail (PublicQuoteController, sans connexion).
 *
 * Avant, chaque contrôleur écrivait sa version du refus, et seul l'espace
 * client permettait de demander une modification. Personne n'était prévenu
 * par mail : le commercial voyait seulement « En négociation » sur la fiche,
 * sans le message du client. Désormais, chaque réponse (acceptation, refus,
 * modification) crée l'alerte ET envoie un mail au commercial du devis.
 *
 * L'acceptation elle-même reste dans QuoteConverter (création de l'option) ;
 * les contrôleurs appellent ensuite prevenirCommercial().
 */
class QuoteDecisionService
{
    public const ACCEPTATION  = 'acceptation';
    public const REFUS        = 'refus';
    public const MODIFICATION = 'modification';

    /** Canal par lequel le client a répondu. */
    public const CANAL_ESPACE = 'espace';
    public const CANAL_LIEN   = 'lien';

    public function __construct(private NotificationMailer $mailer) {}

    public function refuser(Quote $quote, ?string $motif, string $canal): void
    {
        $motif = self::nettoyer($motif);

        $quote->update([
            'status'          => QuoteStatus::REFUSE->value,
            'decision_at'     => now(),
            'decision_reason' => $motif,
        ]);

        AlertService::notify(
            'devis_refuse',
            '❌ Devis refusé — ' . $quote->reference,
            self::nomClient($quote) . ' a refusé le devis ' . $quote->reference
            . ($motif ? " (motif : {$motif})" : '') . self::mentionCanal($canal) . '.',
            $quote,
            ['user_id' => $quote->commercial_user_id, 'lien' => route('admin.quotes.show', $quote->id)]
        );

        $this->prevenirCommercial($quote, self::REFUS, $motif, $canal);
    }

    public function demanderModification(Quote $quote, string $demande, string $canal): void
    {
        $demande = self::nettoyer($demande);

        $quote->update([
            'status'          => QuoteStatus::EN_NEGOCIATION->value,
            'decision_at'     => now(),
            'decision_reason' => $demande,
        ]);

        AlertService::notify(
            'devis_en_negociation',
            '💬 Demande de modification — ' . $quote->reference,
            self::nomClient($quote) . ' demande une modification du devis ' . $quote->reference
            . self::mentionCanal($canal) . ' : ' . $demande,
            $quote,
            ['user_id' => $quote->commercial_user_id, 'lien' => route('admin.quotes.show', $quote->id)]
        );

        $this->prevenirCommercial($quote, self::MODIFICATION, $demande, $canal);
    }

    /**
     * Mail au commercial du devis (à défaut son créateur, à défaut les
     * admins actifs). Ne casse jamais le parcours du client : un échec
     * d'envoi est journalisé, la réponse reste enregistrée.
     *
     * @param string|null $suite  ce que l'acceptation a produit (option créée, conflit…)
     */
    public function prevenirCommercial(Quote $quote, string $reponse, ?string $message, string $canal, ?string $suite = null): void
    {
        try {
            $quote->loadMissing(['client', 'commercial', 'creator']);

            $pour = collect([$quote->commercial?->email, $quote->creator?->email])
                ->first(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));

            $destinataires = $pour
                ? [$pour]
                : User::query()->where('is_active', true)->where('role', 'admin')->pluck('email')
                    ->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values()->all();

            if (!$destinataires) {
                Log::warning('quote.reponse.sans_destinataire', ['quote_id' => $quote->id, 'reponse' => $reponse]);
                return;
            }

            $this->mailer->sendSilently(
                $destinataires,
                new QuoteReponseClientMail($quote, $reponse, $message, $canal, $suite),
                context: ['action' => 'quote.reponse_client', 'quote_id' => $quote->id, 'reponse' => $reponse],
            );
        } catch (\Throwable $e) {
            Log::error('quote.reponse.mail_echec', ['quote_id' => $quote->id, 'erreur' => $e->getMessage()]);
        }
    }

    /**
     * Ce que l'acceptation a produit, en une phrase pour le commercial,
     * à partir du résultat de QuoteConverter::convertFromQuote().
     */
    public static function suiteAcceptation(array $resultat): string
    {
        if (($resultat['status'] ?? null) === 'converted') {
            $resa  = $resultat['reservation'] ?? null;
            $ref   = $resa?->reference ? ' ' . $resa->reference : '';
            $date  = $resa?->bc_attendu_jusqu_au
                ? ' jusqu\'au ' . \Carbon\Carbon::parse($resa->bc_attendu_jusqu_au)->format('d/m/Y')
                : '';
            return "Réservation{$ref} créée en option, bon de commande attendu{$date}. "
                . 'Récupère le BC du client, puis clique sur « 📥 BC reçu » sur la réservation.';
        }

        if (($resultat['motif'] ?? null) === 'periode_absente') {
            return 'Acceptation enregistrée, mais les dates de campagne manquent : contacte le client pour les fixer.';
        }

        return 'Acceptation enregistrée, mais certains panneaux ne sont plus disponibles : contacte le client pour ajuster.';
    }

    public static function libelle(string $reponse): string
    {
        return match ($reponse) {
            self::ACCEPTATION  => 'Devis accepté',
            self::REFUS        => 'Devis refusé',
            self::MODIFICATION => 'Modification demandée',
            default            => 'Réponse du client',
        };
    }

    public static function libelleCanal(string $canal): string
    {
        return $canal === self::CANAL_LIEN ? 'lien privé du mail' : 'espace client';
    }

    private static function mentionCanal(string $canal): string
    {
        return $canal === self::CANAL_LIEN ? ' (via le lien privé du mail)' : '';
    }

    private static function nomClient(Quote $quote): string
    {
        return $quote->client?->name ?: 'Le client';
    }

    private static function nettoyer(?string $texte): ?string
    {
        $texte = trim((string) $texte);
        return $texte === '' ? null : mb_substr($texte, 0, 1000);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Adresse exclue des envois commerciaux : diffusion des disponibilités
 * et, depuis le 2026-10-21, mail de fin de campagne (J-7).
 *
 * motif : desinscription | rebond | spam | manuel
 * source : brevo (webhook) | lien (lien de désinscription des mails envoyés
 * par Panora) | panora (exclusion manuelle par l'admin).
 * Les clients étant inscrits d'office, on ne stocke que ces exceptions.
 */
class DiffusionDesinscription extends Model
{
    protected $table = 'diffusion_desinscriptions';

    protected $fillable = ['email', 'motif', 'source', 'survenu_at'];

    protected $casts = [
        'survenu_at' => 'datetime',
    ];

    public function libelleMotif(): string
    {
        return match ($this->motif) {
            'desinscription' => 'Désinscrit',
            'rebond'         => 'Adresse invalide',
            'spam'           => 'Signalé comme spam',
            'manuel'         => 'Exclu manuellement',
            default          => $this->motif,
        };
    }

    // ── Lien de désinscription des mails envoyés par Panora ──────────
    //
    // Les mails individuels (API transactionnelle de Brevo) n'ont pas le
    // lien de désinscription automatique des campagnes Brevo : Panora
    // fournit le sien. Signature HMAC dans le chemin, sans expiration (un
    // lien de désinscription doit marcher des mois plus tard) et sans
    // dépendre du protocole ni du domaine vus derrière le proxy.

    public static function lien(string $email): string
    {
        $email = mb_strtolower(trim($email));

        return route('desinscription.show', [
            'e'   => rtrim(strtr(base64_encode($email), '+/', '-_'), '='),
            'sig' => static::signature($email),
        ]);
    }

    /** Adresse portée par un lien, ou null si le lien a été altéré. */
    public static function emailDuLien(string $e, string $sig): ?string
    {
        $email = base64_decode(strtr($e, '-_', '+/'), true);

        if ($email === false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return hash_equals(static::signature($email), $sig) ? mb_strtolower($email) : null;
    }

    private static function signature(string $email): string
    {
        return substr(hash_hmac('sha256', 'desinscription|' . mb_strtolower($email), (string) config('app.key')), 0, 32);
    }
}

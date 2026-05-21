<?php

namespace App\Services;

use App\Models\PublicLink;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PublicLinkService — génération + résolution + audit des liens publics.
 *
 * Usage :
 *   $link = PublicLinkService::create($invoice, 'invoice', now()->addDays(30));
 *   $url  = $link->publicUrl();   // https://panora.app/p/{token}
 *
 *   $link = PublicLinkService::resolve($token, $request); // null si invalide
 *   if ($link?->isUsable()) { ... }
 */
class PublicLinkService
{
    /** Durées d'expiration par défaut selon le type. */
    public const DEFAULT_TTL = [
        'invoice'        => 30 * 24 * 3600,  // 30 jours
        'invoice_pay'    => 7  * 24 * 3600,  // 7 jours, one-time
        'pige'           => 30 * 24 * 3600,
        'pige_bundle'    => 30 * 24 * 3600,
        'reservation'    => 30 * 24 * 3600,
        'decap'          => 30 * 24 * 3600,
        'password_reset' => 24 * 3600,       // 24h
        'account_setup'  => 24 * 3600,
    ];

    /**
     * Génère un nouveau PublicLink pour une cible donnée.
     */
    public static function create(
        Model $target,
        string $type,
        ?Carbon $expiresAt = null,
        ?int $clientId = null,
        ?int $maxUses = null,
        array $metadata = [],
    ): PublicLink {
        $ttl = self::DEFAULT_TTL[$type] ?? (30 * 24 * 3600);

        return PublicLink::create([
            'token'              => self::generateToken(),
            'type'               => $type,
            'target_type'        => get_class($target),
            'target_id'          => $target->getKey(),
            'client_id'          => $clientId ?? ($target->client_id ?? null),
            'expires_at'         => $expiresAt ?? now()->addSeconds($ttl),
            'max_uses'           => $maxUses,
            'metadata'           => $metadata,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Récupère ou crée un lien actif pour une cible (évite les doublons).
     */
    public static function findOrCreate(
        Model $target,
        string $type,
        ?Carbon $expiresAt = null,
    ): PublicLink {
        $existing = PublicLink::active()
            ->where('type', $type)
            ->where('target_type', get_class($target))
            ->where('target_id', $target->getKey())
            ->latest()
            ->first();

        if ($existing) return $existing;

        return self::create($target, $type, $expiresAt);
    }

    /**
     * Résout un token et enregistre l'accès (audit log).
     * Retourne null si le token n'existe pas.
     */
    public static function resolve(string $token, ?Request $request = null): ?PublicLink
    {
        $link = PublicLink::where('token', $token)->first();
        if (!$link) {
            Log::warning('public_link.not_found', ['token_prefix' => substr($token, 0, 8)]);
            return null;
        }

        // Enregistre l'accès même si invalide (pour détecter le scan)
        $link->forceFill([
            'access_count'     => $link->access_count + 1,
            'last_accessed_at' => now(),
            'last_accessed_ip' => $request?->ip(),
            'last_accessed_ua' => $request ? substr($request->userAgent() ?? '', 0, 255) : null,
        ])->save();

        return $link;
    }

    /**
     * Marque le lien comme utilisé (pour les actions one-time : paiement, reset MDP).
     * Incrémente use_count et set used_at si premier usage.
     */
    public static function consume(PublicLink $link): bool
    {
        if (!$link->isUsable()) return false;

        $link->forceFill([
            'use_count' => $link->use_count + 1,
            'used_at'   => $link->used_at ?? now(),
        ])->save();

        return true;
    }

    /**
     * Révocation manuelle (depuis l'admin).
     */
    public static function revoke(PublicLink $link, ?string $reason = null): void
    {
        $link->forceFill([
            'revoked_at'         => now(),
            'revoked_by_user_id' => auth()->id(),
            'revoked_reason'     => $reason,
        ])->save();

        Log::info('public_link.revoked', [
            'id'     => $link->id,
            'type'   => $link->type,
            'reason' => $reason,
            'by'     => auth()->id(),
        ]);
    }

    /**
     * Token cryptographique 256 bits = 64 hex chars.
     * `random_bytes` utilise la source CSPRNG de l'OS (libsodium / /dev/urandom).
     */
    public static function generateToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (PublicLink::where('token', $token)->exists()); // collision quasi impossible mais safe

        return $token;
    }

    /**
     * Nettoyage des liens expirés / révoqués depuis > 90 jours (cron mensuel).
     */
    public static function purgeOld(int $daysOld = 90): int
    {
        return PublicLink::where(function ($q) use ($daysOld) {
            $q->where('expires_at', '<', now()->subDays($daysOld))
              ->orWhere('revoked_at', '<', now()->subDays($daysOld));
        })->delete();
    }
}

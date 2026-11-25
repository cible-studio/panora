<?php

namespace App\Services\Promos;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Promotions par mail — SOURCE UNIQUE (2026-11-25) : la page d'arrivée
 * (PromoController), le lien de réponse prérempli et le décompte des clics
 * (commande promos:clics) passent tous par ici. Réglages : config/promos.php.
 */
class PromoService
{
    /** Robots et filtres de sécurité des messageries, qui ouvrent les liens avant le client. */
    private const ROBOTS = '/bot|crawl|spider|slurp|preview|scanner|scan\b|curl|wget|python|java\/|go-http|headless|phantom|facebookexternalhit|proofpoint|mimecast|barracuda|safelinks|urldefense|symantec|trendmicro|fortiguard/i';

    /** @return array<string, mixed>|null */
    public function promo(string $code): ?array
    {
        $promo = config("promos.$code");

        return is_array($promo) ? $promo : null;
    }

    /**
     * Lien « mailto: » de la réponse préremplie, avec le format choisi, et
     * les adresses en copie (PROMO_COPIE, 25/11) déjà remplies.
     * Espaces en %20 (pas « + ») : les messageries l'exigent.
     */
    public function lienReponse(array $promo, ?string $format = null): string
    {
        $libelle = $format ? ($promo['formats'][$format]['libelle'] ?? '') : '';
        $message = str_replace(':format', $libelle, (string) ($promo['message'] ?? ''));
        $copie   = array_values(array_filter((array) ($promo['copie'] ?? []), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));

        return 'mailto:' . $promo['email'] . '?'
            . ($copie ? 'cc=' . implode(',', $copie) . '&' : '')
            . 'subject=' . rawurlencode((string) $promo['sujet'])
            . '&body=' . rawurlencode($message);
    }

    public static function estRobot(?string $navigateur): bool
    {
        return $navigateur === null || trim($navigateur) === '' || preg_match(self::ROBOTS, $navigateur) === 1;
    }

    /**
     * Compte un clic. Ne casse jamais la page : sans la table (migration
     * pas encore jouée) ou en cas d'erreur, le clic est seulement perdu.
     */
    public function compter(string $code, ?string $format, Request $request): void
    {
        // HEAD : vérification du lien par un filtre, pas une visite.
        if (!$request->isMethod('GET')) {
            return;
        }

        try {
            if (!Schema::hasTable('promo_clics')) {
                return;
            }
            $navigateur = $request->userAgent();
            DB::table('promo_clics')->insert([
                'promo'      => $code,
                'format'     => $format,
                'visiteur'   => substr(hash_hmac('sha256', $request->ip() . '|' . $navigateur, (string) config('app.key')), 0, 16),
                'robot'      => self::estRobot($navigateur),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('promos.clic_non_compte', ['promo' => $code, 'erreur' => $e->getMessage()]);
        }
    }

    /**
     * Bilan d'une promotion, robots à part.
     *
     * @return array{clics: int, visiteurs: int, robots: int, formats: array<string, int>, jours: array<string, int>}
     */
    public function bilan(string $code): array
    {
        $vide = ['clics' => 0, 'visiteurs' => 0, 'robots' => 0, 'formats' => [], 'jours' => []];
        if (!Schema::hasTable('promo_clics')) {
            return $vide;
        }

        $lignes = DB::table('promo_clics')->where('promo', $code)->get(['format', 'visiteur', 'robot', 'created_at']);
        $humains = $lignes->where('robot', false);

        return [
            'clics'     => $humains->count(),
            'visiteurs' => $humains->pluck('visiteur')->filter()->unique()->count(),
            'robots'    => $lignes->count() - $humains->count(),
            'formats'   => $humains->groupBy(fn ($l) => $l->format ?? 'sans format')->map->count()->sortDesc()->all(),
            'jours'     => $humains->groupBy(fn ($l) => substr((string) $l->created_at, 0, 10))->map->count()->sortKeys()->all(),
        ];
    }
}

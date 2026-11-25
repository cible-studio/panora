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

    /** Deux liens différents ouverts à moins de ces secondes d'écart, par le même visiteur : un robot. */
    public const RAFALE_SECONDES = 2;

    /** Délai pour confirmer l'affichage d'une page (au-delà, le jeton est refusé). */
    private const CONFIRMATION_MINUTES = 30;

    /**
     * Compte un clic et rend le jeton qui permettra à la page de confirmer
     * qu'elle s'est vraiment affichée (null si rien n'a été compté). Ne casse
     * jamais la page : sans la table ou en cas d'erreur, le clic est perdu.
     */
    public function compter(string $code, ?string $format, Request $request): ?string
    {
        // HEAD : vérification du lien par un filtre, pas une visite.
        if (!$request->isMethod('GET')) {
            return null;
        }

        try {
            if (!Schema::hasTable('promo_clics')) {
                return null;
            }
            $navigateur = $request->userAgent();
            $ligne = [
                'promo'      => $code,
                'format'     => $format,
                'visiteur'   => substr(hash_hmac('sha256', $request->ip() . '|' . $navigateur, (string) config('app.key')), 0, 16),
                'robot'      => self::estRobot($navigateur),
                'created_at' => now(),
            ];
            // 25/11 : false tant que la page n'a pas confirmé son affichage.
            if (self::colonneVerifie()) {
                $ligne['verifie'] = false;
            }

            return self::jeton(DB::table('promo_clics')->insertGetId($ligne));
        } catch (\Throwable $e) {
            Log::warning('promos.clic_non_compte', ['promo' => $code, 'erreur' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * La page s'est affichée dans un navigateur (son script a tourné) : le
     * clic vient d'une personne, pas d'un filtre qui vérifie les liens sans
     * rien afficher. Jeton signé, valable CONFIRMATION_MINUTES.
     */
    public function confirmer(string $jeton): bool
    {
        $id = (string) strstr($jeton, '.', true);
        if (!ctype_digit($id) || !hash_equals(self::jeton((int) $id), $jeton) || !self::colonneVerifie()) {
            return false;
        }

        return DB::table('promo_clics')->where('id', (int) $id)
            ->where('created_at', '>=', now()->subMinutes(self::CONFIRMATION_MINUTES))
            ->update(['verifie' => true]) > 0;
    }

    private static function jeton(int $id): string
    {
        return $id . '.' . substr(hash_hmac('sha256', 'promo-clic|' . $id, (string) config('app.key')), 0, 24);
    }

    private static function colonneVerifie(): bool
    {
        return Schema::hasColumn('promo_clics', 'verifie');
    }

    /**
     * Visiteurs qui ont ouvert deux liens différents à moins de
     * RAFALE_SECONDES d'écart : les filtres de sécurité des messageries
     * d'entreprise testent tous les liens d'un mail à son arrivée, avec un
     * navigateur ordinaire (constaté le 25/11 : trois liens à la même seconde).
     *
     * @return list<string>
     */
    public static function visiteursEnRafale(iterable $lignes): array
    {
        $rafales = [];
        foreach (collect($lignes)->filter(fn ($l) => $l->visiteur)->groupBy('visiteur') as $visiteur => $visites) {
            $visites = $visites->sortBy('created_at')->values();
            for ($i = 1; $i < $visites->count(); $i++) {
                $ecart = abs(strtotime((string) $visites[$i]->created_at) - strtotime((string) $visites[$i - 1]->created_at));
                if ($ecart <= self::RAFALE_SECONDES && ($visites[$i]->format ?? '') !== ($visites[$i - 1]->format ?? '')) {
                    $rafales[] = (string) $visiteur;
                    break;
                }
            }
        }

        return $rafales;
    }

    /**
     * Bilan d'une promotion : seuls les clics de clients comptent.
     *   · robots     : navigateur d'un robot ou d'un filtre connu ;
     *   · rafales    : plusieurs liens ouverts dans la même seconde ;
     *   · sans_affichage : page jamais affichée dans un navigateur (le lien a
     *                  été vérifié, pas lu). Les clics d'avant ce contrôle n'ont
     *                  pas cette information et restent comptés.
     *
     * @return array{clics: int, visiteurs: int, robots: int, rafales: int, sans_affichage: int, formats: array<string, int>, jours: array<string, int>}
     */
    public function bilan(string $code): array
    {
        $vide = ['clics' => 0, 'visiteurs' => 0, 'robots' => 0, 'rafales' => 0, 'sans_affichage' => 0, 'formats' => [], 'jours' => []];
        if (!Schema::hasTable('promo_clics')) {
            return $vide;
        }

        $champs = ['format', 'visiteur', 'robot', 'created_at'];
        if (self::colonneVerifie()) {
            $champs[] = 'verifie';
        }
        $lignes = DB::table('promo_clics')->where('promo', $code)->get($champs);

        $robots   = $lignes->filter(fn ($l) => (bool) $l->robot);
        $reste    = $lignes->reject(fn ($l) => (bool) $l->robot);
        $enRafale = self::visiteursEnRafale($reste);
        $rafales  = $reste->filter(fn ($l) => in_array((string) $l->visiteur, $enRafale, true));
        $reste    = $reste->reject(fn ($l) => in_array((string) $l->visiteur, $enRafale, true));
        // verifie : null = clic d'avant ce contrôle (gardé), false = jamais affiché.
        $sansAffichage = $reste->filter(fn ($l) => property_exists($l, 'verifie') && $l->verifie !== null && !(bool) $l->verifie);
        $clients  = $reste->reject(fn ($l) => property_exists($l, 'verifie') && $l->verifie !== null && !(bool) $l->verifie);

        return [
            'clics'          => $clients->count(),
            'visiteurs'      => $clients->pluck('visiteur')->filter()->unique()->count(),
            'robots'         => $robots->count(),
            'rafales'        => $rafales->count(),
            'sans_affichage' => $sansAffichage->count(),
            'formats'        => $clients->groupBy(fn ($l) => $l->format ?? 'sans format')->map->count()->sortDesc()->all(),
            'jours'          => $clients->groupBy(fn ($l) => substr((string) $l->created_at, 0, 10))->map->count()->sortKeys()->all(),
        ];
    }
}

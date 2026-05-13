<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * Middleware "sync réactif" — applique les transitions automatiques
 * de statut (campagnes, options, réservations) à chaque request admin,
 * sans attendre le cron 01h30.
 *
 * Mutex 60s via Cache : la 1re request de la minute paie la latence
 * (~300-800ms selon le nombre de transitions), les suivantes passent
 * en transparent. Évite de spammer la BD à chaque page load.
 *
 * Sync exécutées :
 *   - reservations:expire-options    Options dont la période est passée
 *   - campaigns:activate-planned     Campagnes planifiées dont start_date atteint
 *   - campaigns:sync-expired         Campagnes actives dont end_date dépassée
 *
 * `panels:sync-statuses` n'est PAS appelé ici (trop lourd, scan tous les
 * panneaux) — il reste planifié quotidiennement par le scheduler.
 */
class SyncReactiveState
{
    private const MUTEX_KEY = 'middleware.sync_reactive_state.lock';
    private const MUTEX_TTL = 60; // secondes

    public function handle(Request $request, Closure $next)
    {
        // Cache::add est atomique : retourne true seulement si la clé
        // n'existait pas. Sert de mutex distribué.
        if (Cache::add(self::MUTEX_KEY, 1, self::MUTEX_TTL)) {
            try {
                Artisan::call('reservations:expire-options');
                Artisan::call('campaigns:activate-planned');
                Artisan::call('campaigns:sync-expired');
            } catch (\Throwable $e) {
                // Une sync qui plante ne doit pas casser la request.
                report($e);
            }
        }

        return $next($request);
    }
}

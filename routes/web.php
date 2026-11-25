<?php
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));

Route::get('/', fn() => view('auth.login'));

// Sites vitrine (/cible, /decouvrir) : sur develop seulement, en aperçu
// jusqu’à validation (main reste sans vitrine, 2026-10-30).

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


// ── API interne : alertes (polling cloche + toasts) ──────────────
// Source : AlertController. Délégué au controller pour bénéficier du
// summary par niveau (badge couleur), du dedup, et du logging centralisé.
Route::middleware('auth')->prefix('api/alerts')->name('api.alerts.')->group(function () {
    Route::get('count',  [AlertController::class, 'apiCount'])->name('count');
    Route::get('latest', [AlertController::class, 'apiLatest'])->name('latest');
});

// ❌ Suppression de la route POST alerts/delete-seen — elle SUPPRIMAIT les
//    alertes au beforeunload (perte de données silencieuse). Remplacée par
//    le mark-all-as-read côté AlertController::index().



// ── Liens publics sécurisés (factures, piges, réservations, décap) ──
// Token 256 bits + expiration + révocation + audit + throttle 20/min/IP.
// Géré par PublicLinkController qui dispatche selon le type de lien.
Route::get('/p/{token}', [\App\Http\Controllers\PublicLinkController::class, 'show'])
    ->middleware(['throttle:20,1', \App\Http\Middleware\SetFrenchLocale::class])
    ->where('token', '[a-f0-9]{64}')
    ->name('public-link.show');

// ── Promotions envoyées par mail — page d'arrivée (2026-11-25) ────
// Le lien du mail mène ici plutôt qu'à un « mailto: » : Brevo compte alors
// le clic (client par client, lien par lien) et Panora aussi. La page
// ouvre la réponse préremplie. Cf. PromoService, config/promos.php.
Route::get('/offre/{code}/{format?}', [\App\Http\Controllers\PromoController::class, 'show'])
    ->middleware(['throttle:30,1'])
    ->where(['code' => '[a-z0-9-]{1,60}', 'format' => '[a-z0-9]{1,20}'])
    ->name('promo.show');
// La page confirme qu'elle s'est affichée : un client, pas un filtre (25/11).
Route::post('/offre-vu', [\App\Http\Controllers\PromoController::class, 'vu'])
    ->middleware(['throttle:30,1'])
    ->name('promo.vu');

// ── Devis publics — clients SANS COMPTE (lien reçu par email) ────
// Token 256 bits + throttle 20/min/IP. Cf. PublicQuoteController pour
// les contrôles (statut envoyé, non expiré, non archivé).
Route::prefix('devis/{token}')
    ->middleware(['throttle:20,1'])
    ->where(['token' => '[a-f0-9]{64}'])
    ->group(function () {
        Route::get('/',        [\App\Http\Controllers\PublicQuoteController::class, 'show'])   ->name('public.quote.show');
        Route::get('/pdf',     [\App\Http\Controllers\PublicQuoteController::class, 'pdf'])    ->name('public.quote.pdf');
        Route::post('/accept', [\App\Http\Controllers\PublicQuoteController::class, 'accept']) ->name('public.quote.accept');
        Route::post('/refuse', [\App\Http\Controllers\PublicQuoteController::class, 'refuse']) ->name('public.quote.refuse');
        // 2026-11-23 — demander une modification sans compte client.
        Route::post('/modification', [\App\Http\Controllers\PublicQuoteController::class, 'requestModification'])->name('public.quote.request-modification');
    });

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


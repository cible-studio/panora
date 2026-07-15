<?php
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));

Route::get('/', fn() => view('auth.login'));

// ─── Landing publique — vitrine commerciale Panora (WIP develop) ───
// Domaine final à décider — accessible temporairement via /decouvrir.
Route::get('/decouvrir', [\App\Http\Controllers\LandingController::class, 'show'])
    ->name('landing.show');
Route::post('/decouvrir/demande-demo', [\App\Http\Controllers\LandingController::class, 'submitDemoRequest'])
    ->middleware('throttle:5,10')
    ->name('landing.demo.submit');

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

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


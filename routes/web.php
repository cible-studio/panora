<?php
use App\Http\Controllers\Admin\AlertController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));

Route::get('/', fn() => view('auth.login'));

// ─── Site vitrine CIBLE CI — régie publicitaire (WIP develop) ───
// Direction éditoriale : autorité tranquille + preuve terrain.
// Contexte : CIBLE utilise Panora en interne ; le site est la face publique.
Route::prefix('cible')->name('cible.')->group(function () {
    Route::get('/',                 [\App\Http\Controllers\CibleController::class, 'home'])->name('home');
    Route::get('/qui-sommes-nous',  [\App\Http\Controllers\CibleController::class, 'qui'])->name('qui');
    Route::get('/services',         [\App\Http\Controllers\CibleController::class, 'services'])->name('services');
    Route::get('/reseau',           [\App\Http\Controllers\CibleController::class, 'reseau'])->name('reseau');
    Route::get('/references',       [\App\Http\Controllers\CibleController::class, 'references'])->name('references');
    Route::get('/contact',          [\App\Http\Controllers\CibleController::class, 'contact'])->name('contact');
    Route::post('/devis',           [\App\Http\Controllers\CibleController::class, 'submitDevis'])
        ->middleware('throttle:5,10')
        ->name('devis.submit');
    // Endpoint public JSON pour la carte réseau — agrégat par commune,
    // aucune info commerciale sensible (pas de rate ni statut individuel).
    // Throttle défensif contre les scrapers automatiques.
    Route::get('/api/reseau-map',   [\App\Http\Controllers\CibleController::class, 'mapData'])
        ->middleware('throttle:60,1')
        ->name('api.reseau-map');
});

// ─── Landing publique — vitrine commerciale Panora (WIP develop) ───
// Refonte V2 (2026-07-15) : direction éditoriale premium avec 5 sous-pages.
Route::prefix('decouvrir')->name('landing.')->group(function () {
    Route::get('/',                   [\App\Http\Controllers\LandingController::class, 'home'])->name('show');
    Route::get('/produit',            [\App\Http\Controllers\LandingController::class, 'produit'])->name('produit');
    Route::get('/pour-directions',    [\App\Http\Controllers\LandingController::class, 'pourDirections'])->name('pour-directions');
    Route::get('/pour-commerciaux',   [\App\Http\Controllers\LandingController::class, 'pourCommerciaux'])->name('pour-commerciaux');
    Route::get('/demo',               [\App\Http\Controllers\LandingController::class, 'demo'])->name('demo');
    Route::get('/faq',                [\App\Http\Controllers\LandingController::class, 'faq'])->name('faq');
    Route::post('/demande-demo',      [\App\Http\Controllers\LandingController::class, 'submitDemoRequest'])
        ->middleware('throttle:5,10')
        ->name('demo.submit');
});

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
    });

require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


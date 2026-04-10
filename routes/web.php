<?php
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Client\ClientAuthController;
use App\Http\Controllers\Client\ClientDashboardController;

Route::get('/health', fn() => response()->json(['status' => 'ok', 'time' => now()]));

Route::get('/', fn() => view('auth.login'));

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Auth client (sans middleware)
Route::prefix('client')->name('client.')->group(function () {
    Route::get('/login', [ClientAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [ClientAuthController::class, 'login'])->name('login.post');
    Route::post('/logout', [ClientAuthController::class, 'logout'])->name('logout');

    // Changer mot de passe (après première connexion)
    Route::get('/change-password', [ClientAuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [ClientAuthController::class, 'changePassword'])->name('password.update');

    // Dashboard (avec auth client)
    Route::middleware('auth:client')->group(function () {
        Route::get('/dashboard', [ClientDashboardController::class, 'index'])->name('dashboard');
        Route::get('/propositions', [ClientDashboardController::class, 'propositions'])->name('propositions');
        Route::get('/propositions/{token}', [ClientDashboardController::class, 'propositionDetail'])->name('proposition.detail');
        Route::post('/propositions/{token}/confirm', [ClientDashboardController::class, 'confirmProposition'])->name('proposition.confirm');
        Route::post('/propositions/{token}/reject', [ClientDashboardController::class, 'rejectProposition'])->name('proposition.reject');
        Route::get('/campagnes', [ClientDashboardController::class, 'campagnes'])->name('campagnes');
        Route::get('/campagnes/{campaign}', [ClientDashboardController::class, 'campagneDetail'])->name('campagne.detail');
        Route::get('/profil', [ClientDashboardController::class, 'profil'])->name('profil');
        Route::post('/profil', [ClientDashboardController::class, 'updateProfil'])->name('profil.update');
    });

    Route::get('/change-password', [ClientAuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/change-password', [ClientAuthController::class, 'updatePassword'])->name('password.update');
});

// ── API interne : alertes (polling) ───────────────────────────
Route::middleware('auth')->get('/api/alerts/count', function () {
    return response()->json([
        'count' => \App\Models\Alert::where('is_read', false)->count()
    ]);
})->name('api.alerts.count');

Route::middleware('auth')->get('/api/alerts/latest', function () {
    $alerts = \App\Models\Alert::where('is_read', false)
        ->latest()
        ->limit(5)
        ->get(['id', 'type', 'niveau', 'title', 'message', 'created_at']);
    return response()->json($alerts);
})->name('api.alerts.latest');



require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


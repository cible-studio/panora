<?php
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;
use App\Http\Controllers\Admin\ReservationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('welcome'));

Route::get('/dashboard', fn() => view('dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// ══════════════════════════════════════════════════════════
// ROUTES ADMIN — Dev B
// ══════════════════════════════════════════════════════════
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {

    // ── Clients ────────────────────────────────────────────
    Route::resource('clients', ClientController::class);

    // ── Régies externes ────────────────────────────────────
    Route::resource('external-agencies', ExternalAgencyController::class)
        ->except(['create', 'edit']);
    Route::post('external-agencies/{externalAgency}/panels',
        [ExternalAgencyController::class, 'storePanel'])
        ->name('external-agencies.panels.store');
    Route::put('external-agencies/{externalAgency}/panels/{panel}',
        [ExternalAgencyController::class, 'updatePanel'])
        ->name('external-agencies.panels.update');
    Route::delete('external-agencies/{externalAgency}/panels/{panel}',
        [ExternalAgencyController::class, 'destroyPanel'])
        ->name('external-agencies.panels.destroy');

    // ── Disponibilités (⚠️ avant resource reservations) ───
    Route::get('disponibilites',
        [ReservationController::class, 'disponibilites'])
        ->name('reservations.disponibilites');

    Route::post('disponibilites/confirmer',
        [ReservationController::class, 'confirmerSelection'])
        ->name('reservations.confirmer-selection');

    // ── API AJAX — rate limité ─────────────────────────────
    Route::get('reservations/available-panels',
        [ReservationController::class, 'availablePanels'])
        ->name('reservations.available-panels')
        ->middleware('throttle:60,1');

    // ── Réservations CRUD ──────────────────────────────────
    Route::resource('reservations', ReservationController::class)
        ->except(['create', 'store']);

    Route::patch('reservations/{reservation}/status',
        [ReservationController::class, 'updateStatus'])
        ->name('reservations.update-status');

    Route::patch('reservations/{reservation}/annuler',
        [ReservationController::class, 'annuler'])
        ->name('reservations.annuler');

    Route::post('reservations/mark-seen',
        [ReservationController::class, 'markSeen'])
        ->name('reservations.mark-seen');
});
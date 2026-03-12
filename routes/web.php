<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ExternalAgencyController;




Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

/*|--------------------------------------------------------------------------
|  ROUTE ADMIN CREER PAR DEV B
|--------------------------------------------------------------------------
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will be assigned to the "web"
| middleware group. Make something great!
|*/

// ── Routes Admin ──────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {

    // Clients
    Route::resource('clients', ClientController::class);

    // Régies CRUD
    Route::resource('external-agencies', ExternalAgencyController::class)
        ->except(['create', 'edit']);

    // Panneaux imbriqués
    Route::post(
        'external-agencies/{externalAgency}/panels',
        [ExternalAgencyController::class, 'storePanel']
    )->name('external-agencies.panels.store');

    Route::put(
        'external-agencies/{externalAgency}/panels/{panel}',
        [ExternalAgencyController::class, 'updatePanel']
    )->name('external-agencies.panels.update');

    Route::delete(
        'external-agencies/{externalAgency}/panels/{panel}',
        [ExternalAgencyController::class, 'destroyPanel']
    )->name('external-agencies.panels.destroy');

});
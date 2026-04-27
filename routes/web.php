<?php
use App\Http\Controllers\Admin\AlertController;
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

Route::post('alerts/delete-seen', [AlertController::class, 'deleteSeen'])
    ->name('admin.alerts.delete-seen');



require __DIR__ . '/auth.php';
require __DIR__ . '/admin.php';


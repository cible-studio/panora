<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use App\Models\Campaign;
use App\Observers\ReservationObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Gate::policy(Reservation::class, ReservationPolicy::class);
        Gate::policy(Campaign::class, \App\Policies\CampaignPolicy::class);
        Reservation::observe(ReservationObserver::class);

        // Force UTF8 uniquement sur MySQL (pas SQLite)
        if (config('database.default') === 'mysql') {
            \DB::statement('SET NAMES utf8');
        }
        
    }
}

<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use App\Models\Reservation;
use App\Policies\ReservationPolicy;
use App\Models\Campaign;
use App\Models\ExternalPanel;

use Illuminate\Support\Facades\URL;

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

        Reservation::observe(\App\Observers\ReservationObserver::class);

        if (class_exists(\App\Observers\ExternalPanelObserver::class)) {
            ExternalPanel::observe(\App\Observers\ExternalPanelObserver::class);
        }


        if (app()->runningInConsole() === false || app()->environment('production')) {
            
            try {
                if (config('database.default') === 'mysql') {
                    \DB::statement('SET NAMES utf8mb4');
                }

                // ✅ Force HTTPS
                if ($this->app->environment('production')) {
                    URL::forceScheme('https');
                    $this->app['request']->server->set('HTTPS', 'on');

                }

            } catch (\Exception $e) {
                // Silencieux pendant le build Docker
            }
        }
    }
}
<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use App\Models\Campaign;
use App\Models\Commune;
use App\Models\ExternalPanel;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\Panel;
use App\Models\Pige;
use App\Models\PoseTask;
use App\Models\Reservation;
use App\Policies\CampaignPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\MaintenancePolicy;
use App\Policies\PanelPolicy;
use App\Policies\PigePolicy;
use App\Policies\PoseTaskPolicy;
use App\Policies\PropositionPolicy;
use App\Policies\ReservationPolicy;

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

        // ─── View composer : injecte les logos Panora dans tous les PDFs ─
        // Évite d'avoir à les passer manuellement depuis chaque controller.
        \Illuminate\Support\Facades\View::composer(['pdf.*', 'admin.*.pdf.*', 'admin.rapports.*-pdf', 'admin.rapports.pdf.*'], function ($view) {
            $assets = new class { use \App\Support\PdfAssets {
                getPanoraLogoDark as public;
                getPanoraLogoLight as public;
                getCibleLogoDark as public;
                getCibleLogoLight as public;
            } };
            $view->with([
                'logoPanoraDark'  => $assets->getPanoraLogoDark(),
                'logoPanoraLight' => $assets->getPanoraLogoLight(),
                // Logos CIBLE (régie) — choix par défaut pour le branding
                // des exports. logoCibleDark = logob.png (header foncé),
                // logoCibleLight = logol.png (header clair).
                'logoCibleDark'   => $assets->getCibleLogoDark(),
                'logoCibleLight'  => $assets->getCibleLogoLight(),
                'operatorName'    => config('app.operator_name', env('OPERATOR_NAME', 'CIBLE CI')),
                'platformName'    => 'Panora',
            ]);
        });

        // ─── Inliner CSS pour tous les emails ──────────────────────────
        // Sans ça, Gmail/Outlook strippent le <style> au forward et le
        // mail transféré perd son design. tijsverkoyen/css-to-inline-styles
        // est déjà installé en dépendance. On hook avant envoi.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSending::class,
            function (\Illuminate\Mail\Events\MessageSending $event) {
                $message = $event->message;
                $html    = $message->getHtmlBody();
                if (!$html || mb_strlen($html) < 50) return;
                try {
                    $inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
                    $message->html($inliner->convert($html));
                } catch (\Throwable $e) {
                    // Mieux vaut un mail design dégradé qu'un envoi qui plante.
                    \Illuminate\Support\Facades\Log::warning('mail.css_inline.failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        );

        // ─── Policies (refonte rôles selon docs/ROLES_VALIDES.md) ────
        // Note : PropositionPolicy partage le modèle Reservation. On
        // l'enregistre sous une "Gate" nommée pour pouvoir l'utiliser
        // avec @can('proposition.send', $reservation) sans collision.
        Gate::policy(Reservation::class,  ReservationPolicy::class);
        Gate::policy(Campaign::class,     CampaignPolicy::class);
        Gate::policy(Invoice::class,      InvoicePolicy::class);
        Gate::policy(PoseTask::class,     PoseTaskPolicy::class);
        Gate::policy(Pige::class,         PigePolicy::class);
        Gate::policy(Maintenance::class,  MaintenancePolicy::class);
        Gate::policy(Panel::class,        PanelPolicy::class);

        // Gates dédiées Proposition (workflow construction → envoi → décision).
        // Usage Blade : @can('proposition.send', $reservation)
        // Usage controller : $this->authorize('proposition.send', $reservation)
        foreach (['build', 'submit', 'markReady', 'send', 'relancer', 'cancel', 'view'] as $action) {
            Gate::define("proposition.{$action}", [PropositionPolicy::class, $action]);
        }

        Reservation::observe(\App\Observers\ReservationObserver::class);

        if (class_exists(\App\Observers\ExternalPanelObserver::class)) {
            ExternalPanel::observe(\App\Observers\ExternalPanelObserver::class);
        }

        // T9 : déclenche l'enquête de satisfaction quand campaign passe en "termine"
        if (class_exists(\App\Observers\CampaignObserver::class)) {
            Campaign::observe(\App\Observers\CampaignObserver::class);
        }

        // Évolution 4 : historisation tarifaire automatique à chaque
        // modification d'un tarif ODP/TM/DB sur une commune.
        if (class_exists(\App\Observers\CommuneObserver::class)) {
            Commune::observe(\App\Observers\CommuneObserver::class);
        }

        // Unification workflow pose/pige : quand le technicien upload une
        // Pige via le lien unique, la PoseTask liée passe automatiquement
        // à COMPLETED et une alerte est envoyée au MP/admin pour validation.
        if (class_exists(\App\Observers\PigeObserver::class)) {
            \App\Models\Pige::observe(\App\Observers\PigeObserver::class);
        }

        // Auto-envoi WhatsApp dès qu'un tech est assigné à une PoseTask
        // (création ou réassignation). Mutex 60s pour éviter le spam.
        if (class_exists(\App\Observers\PoseTaskObserver::class)) {
            \App\Models\PoseTask::observe(\App\Observers\PoseTaskObserver::class);
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
<?php

namespace Tests\Unit\Concerns;

use App\Models\Campaign;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Base SQLite réduite pour les tests du renouvellement (2026-10-22) : les
 * migrations du projet ne passent pas sous SQLite (cf. TECHNICAL_DEBT.md),
 * on crée les seules colonnes utiles, plus les vraies migrations du
 * journal et des renouvellements. Brevo est simulé.
 */
trait BaseRenouvellement
{
    /** Base réduite + données de référence (à appeler dans setUp). */
    protected function preparerBaseRenouvellement(): void
    {

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email'); $t->string('role')->nullable();
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('clients', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('phone')->nullable();
            $t->string('contact_name')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('client_contacts', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('client_id'); $t->string('name'); $t->string('email')->nullable();
            $t->string('phone')->nullable(); $t->boolean('is_primary')->default(false);
            $t->string('role')->default('autre'); $t->string('position')->nullable(); $t->text('notes')->nullable();
            $t->boolean('receives_notifications')->default(true); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('commercial_user_id')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('client_id'); $t->unsignedBigInteger('reservation_id')->nullable();
            $t->unsignedBigInteger('user_id')->nullable(); $t->unsignedBigInteger('commercial_user_id')->nullable();
            $t->string('status'); $t->date('start_date')->nullable(); $t->date('end_date')->nullable();
            $t->unsignedInteger('total_panels')->nullable(); $t->unsignedBigInteger('total_amount')->nullable();
            $t->softDeletes(); $t->timestamps();
        });
        Schema::create('panels', function (Blueprint $t) {
            $t->id(); $t->string('reference'); $t->unsignedBigInteger('commune_id')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('campaign_panels', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('campaign_id'); $t->unsignedBigInteger('panel_id')->nullable();
            $t->unsignedBigInteger('external_panel_id')->nullable(); $t->string('type')->default('interne'); $t->timestamps();
        });
        Schema::create('diffusion_desinscriptions', function (Blueprint $t) {
            $t->id(); $t->string('email')->unique(); $t->string('motif'); $t->string('source')->default('brevo');
            $t->timestamp('survenu_at')->nullable(); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_21_100000_create_relances_fin_campagne_table.php'))->up();
        (require base_path('database/migrations/2026_10_22_100000_create_renouvellements_tables.php'))->up();
        (require base_path('database/migrations/2026_10_23_100000_add_rappel_le_to_renouvellements.php'))->up();
        (require base_path('database/migrations/2026_10_28_120000_add_statut_commercial_to_clients.php'))->up();
        (require base_path('database/migrations/2026_10_29_100000_add_interlocuteur_to_campaigns_reservations_quotes.php'))->up();

        Carbon::setTestNow('2026-11-01 10:00:00'); // J-60 de la campagne de référence

        config([
            'diffusion.fin_campagne.mode'  => 'production',
            'diffusion.fin_campagne.copie' => 'commercial@cible-ci.com',
            'diffusion.confirmation_email' => 'studio@cible-ci.com',
            'diffusion.renouvellement.retenue_jours' => 30,
            'brevo.api_key'      => 'cle-de-test',
            'brevo.sender.email' => 'commercial@cible-ci.com',
            'brevo.lists.tests'  => null,
            'mail.staff_alerts_enabled' => true,
        ]);

        Mail::fake();
        Http::fake(fn() => Http::response(['messageId' => '<id@smtp-relay.brevo.com>'], 201));

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Créateur', 'email' => 'createur@cible-ci.com', 'role' => 'admin'],
            ['id' => 2, 'name' => 'K. Yao', 'email' => 'k.yao@cible-ci.com', 'role' => 'commercial'],
            ['id' => 3, 'name' => 'Marie MP', 'email' => 'mp@cible-ci.com', 'role' => 'mediaplanner'],
        ]);
        DB::table('clients')->insert(['id' => 10, 'name' => 'ORANGE CI', 'email' => 'contact@orange.ci', 'phone' => '+225 01 02 03 04']);
        DB::table('client_contacts')->insert(['client_id' => 10, 'name' => 'Awa Koné', 'email' => 'awa@orange.ci', 'phone' => '+225 07 00 00 00', 'is_primary' => true]);
        DB::table('panels')->insert([['id' => 100, 'reference' => 'ABJ-100'], ['id' => 101, 'reference' => 'ABJ-101']]);
    }

    private function campagne(array $attr = []): Campaign
    {
        $id = DB::table('campaigns')->insertGetId($attr + [
            'name' => 'Affichage annuel', 'client_id' => 10, 'user_id' => 1, 'commercial_user_id' => 2,
            'status' => 'actif', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
            'total_panels' => 2, 'total_amount' => 18000000,
        ]);
        DB::table('campaign_panels')->insert([
            ['campaign_id' => $id, 'panel_id' => 100, 'type' => 'interne'],
            ['campaign_id' => $id, 'panel_id' => 101, 'type' => 'interne'],
        ]);

        return Campaign::with(['client.contacts', 'commercial', 'user'])->findOrFail($id);
    }

    private function service(): RenouvellementService
    {
        return app(RenouvellementService::class);
    }

}

<?php

namespace Tests\Unit;

use App\Models\InvoiceAlert;
use App\Models\User;
use App\Notifications\InvoiceScheduleAlertNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Table `notifications` (2026-10-30) : le canal 'database' de
 * InvoiceScheduleAlertNotification (alertes d'échéance in-app des
 * commerciaux et admins) n'avait pas de table et chaque envoi échouait.
 */
class NotificationsTableTest extends TestCase
{
    private const MIGRATION = 'database/migrations/2026_10_30_090000_create_notifications_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('email')->nullable(); $t->string('role')->nullable(); $t->timestamps();
        });
        DB::table('users')->insert(['id' => 2, 'name' => 'K. Yao', 'email' => 'k.yao@cible-ci.com', 'role' => 'commercial']);
    }

    public function test_une_alerte_d_echeance_in_app_est_enregistree(): void
    {
        (require base_path(self::MIGRATION))->up();
        (require base_path(self::MIGRATION))->up(); // déjà là : ne fait rien

        // Canal réel (via() : un User → 'database') ; contenu simplifié.
        $notification = new class(new InvoiceAlert()) extends InvoiceScheduleAlertNotification {
            public function toDatabase(object $notifiable): array
            {
                return ['type' => 'invoice_schedule_alert', 'message' => '📅 Rappel échéance'];
            }
        };
        $user = User::findOrFail(2);
        $user->notifyNow($notification);

        $this->assertSame(1, $user->unreadNotifications()->count());
        $ligne = DB::table('notifications')->first();
        $this->assertSame(User::class, $ligne->notifiable_type);
        $this->assertSame(2, (int) $ligne->notifiable_id);
        $this->assertSame('📅 Rappel échéance', json_decode($ligne->data, true)['message']);
    }

    public function test_down_supprime_la_table(): void
    {
        $migration = require base_path(self::MIGRATION);
        $migration->up();
        $migration->down();

        $this->assertFalse(Schema::hasTable('notifications'));
    }
}

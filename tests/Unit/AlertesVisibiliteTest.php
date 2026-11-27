<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\AlertService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Alertes visibles par l'utilisateur — règle unique (2026-11-27) :
 * AlertService::scopeCurrentUser(), lue par le centre d'alertes, la cloche
 * et le tableau de bord. Avant, le tableau de bord ne filtrait que le
 * commercial (le comptable voyait les alertes de tous), et l'admin voyait
 * chaque alerte d'échéance deux fois (la sienne et la copie du commercial).
 */
class AlertesVisibiliteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('alerts', function (Blueprint $t) {
            $t->id(); $t->string('type'); $t->string('niveau')->default('info');
            $t->string('title'); $t->text('message')->nullable();
            $t->string('related_type')->nullable(); $t->unsignedBigInteger('related_id')->nullable();
            $t->string('dedup_key')->nullable(); $t->unsignedBigInteger('user_id')->nullable();
            $t->string('lien')->nullable(); $t->boolean('is_read')->default(false);
            $t->timestamp('triggered_at')->nullable(); $t->timestamp('archived_at')->nullable();
            $t->timestamps();
        });
        $ligne = fn ($type, $titre, $user, $dedup) => [
            'type' => $type, 'title' => $titre, 'user_id' => $user, 'dedup_key' => $dedup,
            'is_read' => false, 'triggered_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('alerts')->insert([
            $ligne('echeance_depassee', 'Relance J+15 (comptable et admins)', null, 'echeance_depassee:Invoice:1:etape:5'),
            $ligne('echeance_depassee', 'Relance J+15 (copie du commercial)', 2, 'echeance_depassee:Invoice:1:etape:5' . AlertService::MARQUE_COPIE_COMMERCIAL),
            $ligne('reservation_nouvelle', 'Réservation d\'un autre commercial', 3, 'reservation_nouvelle:Reservation:9:-'),
            $ligne('pose_planifiee', 'Pose planifiée', null, 'pose_planifiee:PoseTask:4:-'),
        ]);
    }

    private function titresPour(int $id, UserRole $role): array
    {
        $this->actingAs((new User())->forceFill(['id' => $id, 'role' => $role]));

        return app(AlertService::class)->latest(10)->pluck('title')->sort()->values()->all();
    }

    public function test_chaque_role_voit_ses_alertes(): void
    {
        $this->assertSame(
            ['Pose planifiée', 'Relance J+15 (comptable et admins)', 'Réservation d\'un autre commercial'],
            $this->titresPour(1, UserRole::ADMIN),
            'l\'admin voit tout, sauf la copie destinée au commercial'
        );
        $this->assertSame(['Relance J+15 (copie du commercial)'], $this->titresPour(2, UserRole::COMMERCIAL));
        $this->assertSame(['Relance J+15 (comptable et admins)'], $this->titresPour(6, UserRole::COMPTABLE),
            'le comptable : ni la copie du commercial, ni les alertes des autres');
        $this->assertSame(['Pose planifiée'], $this->titresPour(7, UserRole::MEDIAPLANNER));
    }

    public function test_le_compteur_suit_la_meme_regle(): void
    {
        $this->actingAs((new User())->forceFill(['id' => 6, 'role' => UserRole::COMPTABLE]));
        $this->assertSame(1, app(AlertService::class)->unreadCount());
    }

    public function test_la_regle_n_est_plus_recopiee(): void
    {
        $tableau = file_get_contents(base_path('app/Http/Controllers/Admin/DashboardController.php'));
        $this->assertStringNotContainsString("Alert::where('is_read', false)", $tableau,
            'le tableau de bord passe par AlertService');
        $this->assertStringContainsString('->latest(5)', $tableau);

        $centre = file_get_contents(base_path('app/Http/Controllers/Admin/AlertController.php'));
        $this->assertStringNotContainsString('broadcastTypesForRole', $centre, 'plus de copie de la règle');

        $relances = file_get_contents(base_path('app/Console/Commands/InvoiceAlertsCommand.php'));
        $this->assertStringContainsString('AlertService::MARQUE_COPIE_COMMERCIAL', $relances);
    }
}

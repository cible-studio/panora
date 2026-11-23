<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Storage;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Config;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Copie des sauvegardes vers Hetzner (2026-11-09) : après trois nuits de
 * « GatewayTimeout » sur un morceau de l'archive, le nombre de morceaux
 * envoyés en même temps est réduit. Le réglage doit arriver jusqu'à l'envoi
 * par morceaux du SDK, pas seulement exister dans la configuration.
 */
class SauvegardeS3EnvoiTest extends TestCase
{
    private function optionsEnvoi(): array
    {
        config([
            'filesystems.disks.backups-s3.key'      => 'cle',
            'filesystems.disks.backups-s3.secret'   => 'secret',
            'filesystems.disks.backups-s3.bucket'   => 'seau',
            'filesystems.disks.backups-s3.region'   => 'hel1',
            'filesystems.disks.backups-s3.endpoint' => 'https://exemple.test',
        ]);
        Storage::forgetDisk('backups-s3');

        $adaptateur = Storage::disk('backups-s3')->getAdapter();
        $this->assertInstanceOf(AwsS3V3Adapter::class, $adaptateur);

        return (new ReflectionMethod($adaptateur, 'createOptionsFromConfig'))->invoke($adaptateur, new Config());
    }

    /** 2026-11-23 — récidive du 21/11 : un seul morceau à la fois (2 avant). */
    public function test_l_archive_part_un_morceau_a_la_fois(): void
    {
        $this->assertSame(1, $this->optionsEnvoi()['concurrency']);
    }

    /**
     * Rattrapage de l'après-midi (2026-11-23) : seuls les disques sans la
     * copie du jour sont refaits ; un disque illisible compte comme manquant.
     */
    public function test_le_rattrapage_ne_refait_que_les_copies_manquantes(): void
    {
        $maintenant = \Carbon\Carbon::parse('2026-11-24 14:00');
        $nuit       = \Carbon\Carbon::parse('2026-11-24 04:40');
        $veille     = \Carbon\Carbon::parse('2026-11-23 04:40');
        $choix      = fn (array $d) => \App\Console\Commands\RattraperSauvegarde::disquesARattraper($d, $maintenant);

        $this->assertSame([], $choix(['backups' => $nuit, 'backups-s3' => $nuit]), 'tout est là');
        $this->assertSame(['backups-s3'], $choix(['backups' => $nuit, 'backups-s3' => $veille]), 'panne Hetzner du 21/11');
        $this->assertSame(['backups-s3'], $choix(['backups' => $nuit, 'backups-s3' => null]), 'Hetzner illisible');
        $this->assertSame(['backups', 'backups-s3'], $choix(['backups' => $veille, 'backups-s3' => $veille]), 'nuit sans sauvegarde');
    }

    public function test_le_rattrapage_est_planifie_l_apres_midi(): void
    {
        $this->assertSame('14:00', config('backup.heure_rattrapage'));
        $this->assertMatchesRegularExpression(
            "/Schedule::command\('sauvegardes:rattraper'\)\s+->dailyAt\(config\('backup\.heure_rattrapage', '14:00'\)\)\s+->when\(fn \(\) => config\('backup\.actif'\)\)/",
            file_get_contents(base_path('routes/console.php'))
        );
        $this->artisan('sauvegardes:rattraper --help')->assertSuccessful();
    }

    public function test_le_rattrapage_simule_ne_lance_rien(): void
    {
        Storage::fake('backups');
        config(['backup.backup.destination.disks' => ['backups']]);

        $this->artisan('sauvegardes:rattraper --simuler')
            ->expectsOutputToContain('Simulation : sauvegarde complète à refaire.')
            ->assertSuccessful();
    }

    public function test_la_sauvegarde_evite_le_creneau_sature_et_espace_ses_essais(): void
    {
        $this->assertSame('04:40', config('backup.heure'));
        $this->assertSame(3, config('backup.backup.tries'));
        $this->assertSame(1800, config('backup.backup.retry_delay'));
        $this->assertMatchesRegularExpression(
            "/Schedule::command\('backup:run'\)\s+->dailyAt\(config\('backup\.heure', '04:40'\)\)/",
            file_get_contents(base_path('routes/console.php'))
        );
    }

    public function test_les_reprises_automatiques_restent_en_place(): void
    {
        $this->assertSame('standard', config('filesystems.disks.backups-s3.retries.mode'));
        $this->assertSame(6, config('filesystems.disks.backups-s3.retries.max_attempts'));
    }
}

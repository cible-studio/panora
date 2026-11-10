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

    public function test_l_archive_part_par_deux_morceaux_a_la_fois(): void
    {
        $this->assertSame(2, $this->optionsEnvoi()['concurrency']);
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

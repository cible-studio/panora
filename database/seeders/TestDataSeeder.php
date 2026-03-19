<?php
namespace Database\Seeders;

use App\Models\Client;
use App\Models\Panel;
use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\PanelCategory;
use Illuminate\Database\Seeder;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── Communes ─────────────────────────────────────────────
        $communes = [
            'Cocody', 'Marcory', 'Plateau', 'Adjamé',
            'Yopougon', 'Koumassi', 'Treichville', 'Abobo',
        ];
        foreach ($communes as $name) {
            Commune::firstOrCreate(['name' => $name]);
        }

        // ── Zones ─────────────────────────────────────────────────
        $zones = ['Abidjan Nord', 'Abidjan Sud', 'Abidjan Centre'];
        foreach ($zones as $name) {
            Zone::firstOrCreate(['name' => $name]);
        }

        // ── Formats ───────────────────────────────────────────────
        $formats = [
            ['name' => '12m²',  'width' => 4,  'height' => 3],
            ['name' => '20m²',  'width' => 5,  'height' => 4],
            ['name' => '36m²',  'width' => 6,  'height' => 6],
        ];
        foreach ($formats as $f) {
            PanelFormat::firstOrCreate(['name' => $f['name']], $f);
        }

        // ── Catégories ────────────────────────────────────────────
        $cats = ['Classique', 'Lumipub', 'Trivision'];
        foreach ($cats as $name) {
            PanelCategory::firstOrCreate(['name' => $name]);
        }

        // ── Clients ───────────────────────────────────────────────
        $clientsData = [
            [
                'name'         => 'ORANGE CÔTE D\'IVOIRE',
                'ncc'          => 'CLT-2026-0001',
                'sector'       => 'Télécommunications',
                'contact_name' => 'Jean Kouassi',
                'email'        => 'jean.kouassi@orange.ci',
                'phone'        => '+225 07 00 11 22',
            ],
            [
                'name'         => 'BRASSIVOIRE',
                'ncc'          => 'CLT-2026-0002',
                'sector'       => 'Agroalimentaire',
                'contact_name' => 'Marie Diallo',
                'email'        => 'marie.diallo@brassivoire.ci',
                'phone'        => '+225 05 33 44 55',
            ],
            [
                'name'         => 'MTN CÔTE D\'IVOIRE',
                'ncc'          => 'CLT-2026-0003',
                'sector'       => 'Télécommunications',
                'contact_name' => 'Paul Koffi',
                'email'        => 'paul.koffi@mtn.ci',
                'phone'        => '+225 01 66 77 88',
            ],
        ];

        foreach ($clientsData as $data) {
            Client::firstOrCreate(['ncc' => $data['ncc']], $data);
        }

        // ── Panneaux ──────────────────────────────────────────────
        $cocody     = Commune::where('name', 'Cocody')->first();
        $marcory    = Commune::where('name', 'Marcory')->first();
        $plateau    = Commune::where('name', 'Plateau')->first();
        $adjame     = Commune::where('name', 'Adjamé')->first();
        $yopougon   = Commune::where('name', 'Yopougon')->first();
        $koumassi   = Commune::where('name', 'Koumassi')->first();
        $treichville= Commune::where('name', 'Treichville')->first();
        $abobo      = Commune::where('name', 'Abobo')->first();

        $nord   = Zone::where('name', 'Abidjan Nord')->first();
        $sud    = Zone::where('name', 'Abidjan Sud')->first();
        $centre = Zone::where('name', 'Abidjan Centre')->first();

        $f12 = PanelFormat::where('name', '12m²')->first();
        $f20 = PanelFormat::where('name', '20m²')->first();
        $f36 = PanelFormat::where('name', '36m²')->first();

        $classique = PanelCategory::where('name', 'Classique')->first();
        $lumipub   = PanelCategory::where('name', 'Lumipub')->first();

        $userId = \App\Models\User::first()?->id ?? 1;

        $panneaux = [
            // Libres — testables
            [
                'reference'      => 'ABJ-001',
                'name'           => 'Carrefour Anono',
                'commune_id'     => $cocody->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f12->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 850000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 45000,
                'zone_description'=> 'Face au feu tricolore, sens Angré → Cocody',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-002',
                'name'           => 'Boulevard Latrille',
                'commune_id'     => $cocody->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f12->id,
                'category_id'    => $lumipub->id,
                'monthly_rate'   => 900000,
                'status'         => 'libre',
                'is_lit'         => true,
                'daily_traffic'  => 52000,
                'zone_description'=> 'Entrée Riviera 2, visible 200m',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-003',
                'name'           => 'Angré Petro Ivoire',
                'commune_id'     => $cocody->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f20->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 1200000,
                'status'         => 'libre',
                'is_lit'         => true,
                'daily_traffic'  => 38000,
                'zone_description'=> 'Face station Petro Ivoire Angré',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-004',
                'name'           => 'Zone 4 Carrefour',
                'commune_id'     => $marcory->id,
                'zone_id'        => $sud->id,
                'format_id'      => $f20->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 1100000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 61000,
                'zone_description'=> 'Carrefour principal Zone 4',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-005',
                'name'           => 'Adjamé 220 Logements',
                'commune_id'     => $adjame->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f12->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 750000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 75000,
                'zone_description'=> 'Entrée marché Adjamé',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-006',
                'name'           => 'Plateau Indénié',
                'commune_id'     => $plateau->id,
                'zone_id'        => $centre->id,
                'format_id'      => $f36->id,
                'category_id'    => $lumipub->id,
                'monthly_rate'   => 2000000,
                'status'         => 'libre',
                'is_lit'         => true,
                'daily_traffic'  => 90000,
                'zone_description'=> 'Avenue Franchet d\'Esperey, centre affaires',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-007',
                'name'           => 'Yopougon Selmer',
                'commune_id'     => $yopougon->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f12->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 700000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 80000,
                'zone_description'=> 'Entrée Selmer sens Yop Kouté',
                'created_by'     => $userId,
            ],
            [
                'reference'      => 'ABJ-008',
                'name'           => 'Koumassi Remblais',
                'commune_id'     => $koumassi->id,
                'zone_id'        => $sud->id,
                'format_id'      => $f20->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 950000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 55000,
                'zone_description'=> 'Bord de mer Koumassi',
                'created_by'     => $userId,
            ],
            // Maintenance — non réservable
            [
                'reference'      => 'ABJ-009',
                'name'           => 'Treichville Gare',
                'commune_id'     => $treichville->id,
                'zone_id'        => $sud->id,
                'format_id'      => $f12->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 800000,
                'status'         => 'maintenance',
                'is_lit'         => false,
                'daily_traffic'  => 95000,
                'zone_description'=> 'Face gare de Treichville — en travaux',
                'created_by'     => $userId,
            ],
            // Libre — grand format
            [
                'reference'      => 'ABJ-010',
                'name'           => 'Abobo Mairie',
                'commune_id'     => $abobo->id,
                'zone_id'        => $nord->id,
                'format_id'      => $f36->id,
                'category_id'    => $classique->id,
                'monthly_rate'   => 1800000,
                'status'         => 'libre',
                'is_lit'         => false,
                'daily_traffic'  => 110000,
                'zone_description'=> 'Face mairie Abobo, axe principal',
                'created_by'     => $userId,
            ],
        ];

        foreach ($panneaux as $data) {
            Panel::firstOrCreate(['reference' => $data['reference']], $data);
        }

        $this->command->info('✓ 3 clients, 10 panneaux, formats, communes créés.');
        $this->command->info('');
        $this->command->info('PANNEAUX :');
        $this->command->info('  ABJ-001 à ABJ-008, ABJ-010 → libre');
        $this->command->info('  ABJ-009                   → maintenance');
    }
}
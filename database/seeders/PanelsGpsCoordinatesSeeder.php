<?php

namespace Database\Seeders;

use App\Models\Panel;
use Illuminate\Database\Seeder;

/**
 * Seeder one-shot — coordonnées GPS terrain pour 110 panneaux du parc.
 *
 * Comportement par défaut : NE TOUCHE PAS les panneaux qui ont déjà des
 * coords (latitude ET longitude renseignées et non nulles). Ça évite
 * d'écraser une correction terrain plus récente.
 *
 * Pour forcer l'écrasement (utile si les coords actuelles sont fausses
 * et qu'on veut imposer celles d'ici), exporter avant exécution :
 *   PANELS_GPS_FORCE=1 php artisan db:seed --class=PanelsGpsCoordinatesSeeder
 *
 * Exécution simple :
 *   php artisan db:seed --class=PanelsGpsCoordinatesSeeder
 */
class PanelsGpsCoordinatesSeeder extends Seeder
{
    public function run(): void
    {
        $force = (bool) env('PANELS_GPS_FORCE', false);

        $updates = [
            ['ABG-001A', 6.74425, -3.5026667],
            ['ABG-002', 6.7301667, -3.2743667],
            ['ABG-004', 6.72955, -3.4909333],
            ['ABO-001A', 5.3957457, -4.0211121],
            ['ABO-002A', 5.3972263, -4.0207674],
            ['ABO-003', 5.4148241, -3.9953924],
            ['ABO-004', 5.3775461, -4.0076052],
            ['ADJ-004', 5.3401296, -4.0263085],
            ['ATB-001', 5.3634022, -4.0321994],
            ['ATB-003A', 5.3581927, -4.0518026],
            ['ATB-004A', 5.3587562, -4.0475372],
            ['BDK-001', 8.0368, -2.79355],
            ['BDK-002', 7.6532833, -2.8134],
            ['BDK-003', 8.0471833, -2.8026167],
            ['BING-001A', 5.3508929, -3.8744764],
            ['BING-002A', 5.3508792, -3.8710502],
            ['BKE-001', 7.68085, -2.0570333],
            ['BKE-002', 7.6842667, -5.0258167],
            ['BKE-003A', 7.68405, -5.030485],
            ['BKE-004', 7.649, -5.02755],
            ['BKE-005', 7.6857667, -5.21865],
            ['BKE-006', 7.6887833, -5.0103167],
            ['BKE-007', 7.6710333, -5.0183167],
            ['BKE-008', 7.6519667, -5.0276833],
            ['BKE-009', 7.6756833, -5.0159167],
            ['BKE-010', 7.6602, -5.0611667],
            ['BKE-012', 7.7003833, -5.0351],
            ['BKE-013', 7.6857667, -5.21865],
            ['BKE-014', 7.684623, -5.045064],
            ['BN-001A', 5.275259, -3.573167],
            ['CDY-004A', 5.3475197, -4.0208311],
            ['CDY-006A', 5.3416822, -4.0168802],
            ['CDY-008A', 5.3466715, -4.0112986],
            ['CDY-009A', 5.3464765, -4.0060059],
            ['CDY-010A', 5.3403693, -3.9988122],
            ['CDY-011A', 5.3488469, -3.9996447],
            ['CDY-012A', 5.3460993, -4.0028298],
            ['CDY-013', 5.3480341, -4.0103326],
            ['CDY-015A', 5.372444, -3.9904598],
            ['CDY-016A', 5.3542608, -4.0014582],
            ['CDY-017A', 5.3541967, -4.0014612],
            ['CDY-020A', 5.3497429, -4.0024243],
            ['CDY-021A', 5.3576179, -4.0011689],
            ['CDY-022A', 5.3697675, -3.9980947],
            ['CDY-023A', 5.3757786, -3.9983472],
            ['CDY-024A', 5.3795145, -3.9953773],
            ['CDY-025A', 5.387037, -3.9927588],
            ['CDY-026', 5.3905095, -3.992328],
            ['CDY-027A', 5.3928033, -3.9920138],
            ['CDY-028', 5.3958835, -3.9919085],
            ['CDY-031A', 5.3619147, -3.9577248],
            ['CDY-032A', 5.3711935, -3.9341284],
            ['CDY-033A', 5.3720421, -3.9311786],
            ['CDY-034A', 5.3708687, -3.9353508],
            ['CDY-036', 5.3478452, -3.9869029],
            ['CDY-037A', 5.3426426, -3.9744376],
            ['CDY-040A', 5.3315283, -3.961845],
            ['CDY-041A', 5.3368091, -3.9536599],
            ['CDY-043A', 5.3352698, -4.0069162],
            ['CDY-044A', 5.3548533, -4.0079005],
            ['CDY-046', 5.3542524, -4.0049354],
            ['CDY-047A', 5.3548783, -3.9861063],
            ['CDY-048A', 5.354913, -3.9922502],
            ['DIV-001', 5.835917, -5.366874],
            ['DLA-001', 6.8674333, -6.4443333],
            ['DLA-002', 6.8879833, -6.4479833],
            ['GB-001A', 5.210747, -3.766859],
            ['GNA-001', 6.11705, -5.9368333],
            ['KHG-001', 9.4094167, -5.6274167],
            ['KHG-002', 9.45015, -5.6310167],
            ['KHG-003', 9.426847, -5.627808],
            ['KHG-004', 9.426847, -5.627808],
            ['KSS-001', 5.289568, -3.9639519],
            ['KSS-002', 5.3004834, -3.9474576],
            ['KSS-003', 5.3005495, -3.9474432],
            ['MRY-001A', 5.2725806, -3.9749821],
            ['MRY-002', 5.2906527, -3.9823743],
            ['MRY-004A', 5.2859344, -3.986058],
            ['PTB-001A', 5.2573632, -3.9865962],
            ['PTB-003A', 5.257037, -3.9616153],
            ['PTB-004', 5.2598879, -3.9586448],
            ['PTB-005', 5.2460527, -3.939578],
            ['PLT-002A', 5.3371546, -4.0291597],
            ['PLT-003', 5.3407772, -4.0206092],
            ['PLT-004A', 5.3169259, -4.0189278],
            ['SBR-001', 5.7949167, -6.5955667],
            ['SM-001A', 5.277305, -3.509458],
            ['SM-002A', 5.275101, -3.50748],
            ['SP-001', 4.7492667, -6.6294833],
            ['SP-002', 4.7773333, -6.6640833],
            ['SP-003', 4.77455, -4.6626667],
            ['SP-004', 4.75815, -6.64735],
            ['TAND-001', 7.798045, -3.16086],
            ['TVIL-001', 5.3093666, -4.0059016],
            ['TVIL-002A', 5.3078556, -3.9991928],
            ['TVIL-003', 5.2966679, -4.0062617],
            ['TVIL-004A', 5.2971083, -3.9931048],
            ['VGE-001A', 5.2705667, -3.9626342],
            ['VGE-002A', 5.2926454, -3.9755705],
            ['VGE-003A', 5.3027018, -4.0131258],
            ['VGE-005A', 5.2838986, -3.9695386],
            ['VGE-006A', 5.2994702, -3.9877699],
            ['YKR-001', 6.81655, -3.2743667],
            ['YKR-002', 6.8164333, -3.2748],
            ['YOP-001A', 5.3596932, -4.0714267],
            ['YOP-002', 5.327961, -4.0623155],
            ['YOP-003', 5.347059, -4.0893106],
            ['YOP-004A', 5.3416852, -4.059297],
            ['YOP-006A', 5.3447911, -4.0632855],
        ];

        $updated  = 0;
        $skipped  = 0;
        $notfound = [];

        $this->command->info(sprintf(
            "PanelsGpsCoordinatesSeeder — %d coordonnées à appliquer · mode %s",
            count($updates),
            $force ? 'FORCE (écrase l\'existant)' : 'SAFE (skip les renseignés)'
        ));

        foreach ($updates as [$ref, $lat, $lng]) {
            // Tolérance de format : la prod utilise un infix "CH" après le
            // premier tiret (ex: MRY-CH002 au lieu de MRY-002). On essaie :
            //   1. le format brut (compat dev)
            //   2. avec "CH" inséré après le premier tiret (compat prod)
            //   3. en LIKE sur la partie numérique en fallback (très tolérant)
            $candidates = [$ref];
            if (preg_match('/^([A-Z]+)-(.+)$/', $ref, $m)) {
                $candidates[] = $m[1] . '-CH' . $m[2];
            }

            $panel = Panel::whereIn('reference', $candidates)->first();

            // Fallback : LIKE sur le préfixe + suffixe pour absorber les
            // variantes (espace, casse, séparateurs). Ne déclenche que si
            // les 2 formats ci-dessus n'ont rien donné.
            if (!$panel && preg_match('/^([A-Z]+)-(\d+)([A-Z]?)$/i', $ref, $m)) {
                $panel = Panel::where('reference', 'LIKE', $m[1] . '%' . $m[2] . $m[3])
                    ->first();
            }

            if (!$panel) {
                $notfound[] = $ref;
                continue;
            }

            // Skip si coords déjà présentes — sauf en mode FORCE.
            if (!$force && $panel->latitude && $panel->longitude
                && (float) $panel->latitude !== 0.0
                && (float) $panel->longitude !== 0.0) {
                $skipped++;
                continue;
            }

            // gps_source = 'manual' pour tracer que ces coords viennent
            // d'une saisie admin (cohérent avec le filtre de carte
            // PanelController::mapData).
            $panel->update([
                'latitude'        => $lat,
                'longitude'       => $lng,
                'gps_source'      => 'manual',
                'gps_computed_at' => now(),
            ]);
            $updated++;
        }

        $this->command->info("✅ Mis à jour       : {$updated}");
        $this->command->info("⏭  Déjà renseignés : {$skipped}");
        $this->command->info("❌ Non trouvés      : " . count($notfound));
        if (!empty($notfound)) {
            $this->command->warn("Références introuvables sur cet env :");
            foreach (array_chunk($notfound, 8) as $chunk) {
                $this->command->line('   ' . implode(', ', $chunk));
            }
        }
    }
}

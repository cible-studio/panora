<?php
namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tarifs ODP/m² 2025 — Côte d'Ivoire (Abidjan + grandes villes).
 *
 * Source : grille officielle CIBLE CI 2025.
 * Met à jour `communes.odp_rate` pour les communes existantes.
 * Crée les communes manquantes à la volée (region = "Côte d'Ivoire", city = nom).
 *
 * Usage :
 *   php artisan db:seed --class=OdpRates2025Seeder
 *
 * Idempotent : peut être exécuté plusieurs fois sans dupliquer.
 */
class OdpRates2025Seeder extends Seeder
{
    /**
     * Tarif ODP/m² 2025 par commune (en FCFA).
     * Format normalisé : nom en minuscule, sans accent, sans tiret/espace.
     */
    private array $rates = [
        'Abengourou'   => 1000,
        'Abobo'        => 1000,
        'Aboisso'      => 1000,
        'Adiamé'       => 3000,
        'Adzopé'       => 1000,
        'Anyama'       => 1000,
        'Assinie'      => 2000,
        'Attécoubé'    => 1000,
        'Bassam'       => 1000,
        'Bingerville'  => 2000,
        'Bondoukou'    => 1000,
        'Bonoua'       => 1000,
        'Bouaké'       => 1000,
        'Cocody'       => 5000,
        'Daloa'        => 1000,
        'Divo'         => 1000,
        'Ferké'        => 1000,
        'Gagnoa'       => 1000,
        'Korhogo'      => 1000,
        'Koumassi'     => 3000,
        'Man'          => 1000,
        'Marcory'      => 1000,
        'Odienné'      => 1000,
        'Plateau'      => 15000,
        'Port-Bouët'   => 1000,
        'Samo'         => 1000,
        'San Pedro'    => 3000,
        'Songon'       => 1000,
        'Soubré'       => 1000,
        'Toumodi'      => 1000,
        'Treichville'  => 1000,
        'Yakro'        => 1000,
        'Yopougon'     => 1000,
    ];

    public function run(): void
    {
        $year     = 2025;
        $updated  = 0;
        $created  = 0;
        $skipped  = 0;

        DB::transaction(function () use ($year, &$updated, &$created, &$skipped) {
            foreach ($this->rates as $name => $rate) {
                // Recherche tolérante : même commune avec ou sans accent / casse différente
                $normalizedTarget = $this->normalize($name);

                $commune = Commune::all()->first(function ($c) use ($normalizedTarget) {
                    return $this->normalize($c->name) === $normalizedTarget;
                });

                if (!$commune) {
                    // Création de la commune si absente
                    $commune = Commune::create([
                        'name'     => $name,
                        'city'     => $name,
                        'region'   => 'Côte d\'Ivoire',
                        'odp_rate' => $rate,
                        'tm_rate'  => 0,
                    ]);
                    $created++;
                    continue;
                }

                if ((float) $commune->odp_rate === (float) $rate) {
                    $skipped++;
                    continue;
                }

                $commune->odp_rate = $rate;
                $commune->save();
                $updated++;
            }
        });

        Log::info('odp_rates.2025.seeded', [
            'year'    => $year,
            'updated' => $updated,
            'created' => $created,
            'skipped' => $skipped,
            'total'   => count($this->rates),
        ]);

        $this->command->info(sprintf(
            "Tarifs ODP/m² %d — %d mis à jour, %d créés, %d inchangés (sur %d communes ciblées).",
            $year, $updated, $created, $skipped, count($this->rates)
        ));
    }

    /**
     * Normalise un nom de commune pour comparaison tolérante :
     * - Minuscules
     * - Sans accents
     * - Sans tirets / espaces
     * - Trim
     */
    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
        return preg_replace('/[\s\-_]/', '', $s);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Commune;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Tarifs ODP 2025 par commune (Abidjan + intérieur) — fournis par
 * CIBLE CI (image officielle "NOUVEAUX TARIFS DE L'ODP/m² EN 2025").
 *
 * Convention :
 *   odp_rate = montant FCFA par m² par mois (variable selon commune)
 *   tm_rate  = 1000 FCFA par m² par mois (fixe national)
 *
 * Les communes absentes du tableau utilisent le tarif standard 1000 F/m².
 *
 * Run idempotent : `php artisan db:seed --class=CommuneTaxRatesSeeder`.
 * Met à jour les tarifs sans toucher aux autres champs des communes.
 */
class CommuneTaxRatesSeeder extends Seeder
{
    /**
     * Tarifs spéciaux 2025 — toutes les autres communes sont à 1000.
     * On match sur le nom (case-insensitive, accents tolérés) car les IDs
     * communes peuvent varier d'un environnement à l'autre.
     */
    private const SPECIAL_RATES = [
        'Plateau'    => 15000,
        'Cocody'     => 5000,
        'Adjamé'     => 3000,
        'Adiamé'     => 3000,  // variante orthographique vue en DB
        'Koumassi'   => 3000,
        'San Pedro'  => 3000,
        'San-Pedro'  => 3000,
        'Assinie'    => 2000,
        'Bingerville'=> 2000,
    ];

    public const TM_RATE_FCFA_PER_M2 = 1000; // fixe pour toutes

    public function run(): void
    {
        $communes = Commune::all();
        $touched = 0;

        foreach ($communes as $commune) {
            $name = trim($commune->name);
            $rate = $this->resolveOdpRate($name);

            $needsUpdate = false;
            if ((float) $commune->odp_rate !== (float) $rate) {
                $commune->odp_rate = $rate;
                $needsUpdate = true;
            }
            if ((float) $commune->tm_rate !== (float) self::TM_RATE_FCFA_PER_M2) {
                $commune->tm_rate = self::TM_RATE_FCFA_PER_M2;
                $needsUpdate = true;
            }

            if ($needsUpdate) {
                $commune->save();
                $touched++;
            }
        }

        Log::info('seeder.commune_tax_rates.applied', [
            'communes_total' => $communes->count(),
            'updated'        => $touched,
        ]);

        $this->command?->info("Tarifs taxes : {$touched} commune(s) mise(s) à jour sur {$communes->count()}.");
    }

    private function resolveOdpRate(string $name): float
    {
        // Match exact d'abord (rapide pour 90% des cas)
        if (isset(self::SPECIAL_RATES[$name])) {
            return (float) self::SPECIAL_RATES[$name];
        }

        // Match insensible à la casse + accents (Cocody / cocody / COCODY OK)
        $needle = $this->normalize($name);
        foreach (self::SPECIAL_RATES as $key => $rate) {
            if ($this->normalize($key) === $needle) {
                return (float) $rate;
            }
        }

        // Tarif standard pour les communes non listées
        return 1000.00;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        // Suppression des accents les plus courants en français
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
            ' ' => '', '-' => '', '_' => '',
        ]);
        return $s;
    }
}

<?php

namespace App\Console\Commands;

use App\Exports\InterlocuteursAFournirExport;
use App\Mail\InterlocuteursAFournirMail;
use App\Services\Campagnes\CampagnesSansInterlocuteur;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as FormatExcel;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Prépare, par commercial, la liste des campagnes sans interlocuteur
 * (2026-11-09). Sans --mail : affiche seulement le décompte.
 *
 *   php artisan campagnes:sans-interlocuteur
 *   php artisan campagnes:sans-interlocuteur --mail=moi@cible-ci.com
 *   php artisan campagnes:sans-interlocuteur --toutes   (terminées et annulées comprises)
 */
class CampagnesSansInterlocuteurCommande extends Command
{
    protected $signature = 'campagnes:sans-interlocuteur
        {--mail= : Adresse qui reçoit un fichier Excel par commercial}
        {--toutes : Inclut les campagnes terminées et annulées}';

    protected $description = 'Liste, par commercial, les campagnes sans interlocuteur (un fichier Excel à remplir pour chacun)';

    public function handle(CampagnesSansInterlocuteur $service): int
    {
        $adresse = trim((string) $this->option('mail'));
        if ($adresse !== '' && !filter_var($adresse, FILTER_VALIDATE_EMAIL)) {
            $this->error("Adresse invalide : {$adresse}");

            return self::FAILURE;
        }

        $groupes = $service->parCommercial((bool) $this->option('toutes'));
        $total = $groupes->sum(fn ($lignes) => $lignes->count());

        foreach ($groupes as $commercial => $lignes) {
            $this->line(sprintf('  %s : %d campagne(s)', $commercial, $lignes->count()));
        }
        $this->info("Total : {$total} campagne(s) sans interlocuteur.");

        if ($adresse === '' || $total === 0) {
            return self::SUCCESS;
        }

        $fichiers = $groupes
            ->mapWithKeys(fn ($lignes, $commercial) => [
                'interlocuteurs-a-renseigner-' . Str::slug((string) $commercial) . '.xlsx'
                    => Excel::raw(new InterlocuteursAFournirExport($lignes), FormatExcel::XLSX),
            ])
            ->all();

        Mail::to($adresse)->send(new InterlocuteursAFournirMail($fichiers, $total));
        $this->info(count($fichiers) . " fichier(s) envoyé(s) à {$adresse}.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\CampaignPanelMouvement;
use App\Models\ExternalPanel;
use App\Models\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rattrape les mouvements de panneaux écrits sans identifiant
 * — correctif du 2026-11-16, le jour même de la mise en service.
 *
 * ═══ LE DÉFAUT ═══
 * MouvementsPanneaux construisait sa ligne ainsi :
 *
 *     $this->ligne(...) + ['panel_id' => $id, 'reference' => ...]
 *
 * `ligne()` pose déjà `panel_id => null` et `external_panel_id => null`
 * comme valeurs par défaut. Or l'opérateur « + » sur deux tableaux NE
 * REMPLACE PAS une clé déjà présente à gauche — contrairement à
 * array_merge(). Les deux identifiants restaient donc à NULL.
 *
 * Conséquence à l'écran : la référence s'affichait bien (elle, n'était
 * pas dans les valeurs par défaut), mais aucun lien vers la fiche du
 * panneau, et le badge « 🤝 régie » apparaissait sur TOUS les
 * mouvements — y compris sur des panneaux du parc CIBLE.
 *
 * ═══ CE QUE FAIT CETTE COMMANDE ═══
 * Pour chaque mouvement dont les deux identifiants sont nuls, elle
 * retrouve le panneau par sa RÉFÉRENCE (qui, elle, a bien été
 * enregistrée) : d'abord dans le parc, puis chez les régies partenaires.
 *
 * Une référence ambiguë ou introuvable est signalée et laissée telle
 * quelle — on ne devine pas.
 *
 * Simulation par défaut. Rien n'est écrit sans --appliquer.
 */
class ReparerMouvementsPanneaux extends Command
{
    protected $signature = 'campagnes:reparer-mouvements
                            {--appliquer : Écrire les corrections (sinon simulation)}';

    protected $description = "Rattache les mouvements de panneaux écrits sans identifiant (défaut du 16/11, simulation par défaut)";

    public function handle(): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('campaign_panel_mouvements')) {
            $this->error("Table campaign_panel_mouvements absente : joue d'abord php artisan migrate.");

            return self::FAILURE;
        }

        $appliquer = (bool) $this->option('appliquer');

        $orphelins = CampaignPanelMouvement::query()
            ->whereNull('panel_id')
            ->whereNull('external_panel_id')
            ->orderBy('id')
            ->get(['id', 'campaign_id', 'reference', 'sens', 'created_at']);

        if ($orphelins->isEmpty()) {
            $this->info('Aucun mouvement sans identifiant : rien à réparer.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info($orphelins->count() . ' mouvement(s) sans identifiant de panneau.');
        $this->line($appliquer ? '→ APPLICATION des corrections.' : '→ SIMULATION (ajoute --appliquer pour écrire).');
        $this->line('');

        // Une référence peut exister en double dans le parc (ce ne devrait
        // pas arriver, mais la base a divergé par le passé). On ne répare
        // que les références sans ambiguïté.
        $refs = $orphelins->pluck('reference')->filter()->unique()->values()->all();

        $parcParRef = Panel::withTrashed()
            ->whereIn('reference', $refs)
            ->get(['id', 'reference'])
            ->groupBy('reference');

        $regieParRef = ExternalPanel::whereIn('code_panneau', $refs)
            ->get(['id', 'code_panneau'])
            ->groupBy('code_panneau');

        $repares   = 0;
        $ambigus   = 0;
        $inconnus  = 0;

        foreach ($orphelins as $mvt) {
            $ref = $mvt->reference;

            if (!$ref) {
                $this->warn("  #{$mvt->id} — aucune référence enregistrée, impossible de retrouver le panneau.");
                $inconnus++;
                continue;
            }

            $dansParc  = $parcParRef->get($ref);
            $dansRegie = $regieParRef->get($ref);

            $nbParc  = $dansParc?->count()  ?? 0;
            $nbRegie = $dansRegie?->count() ?? 0;

            if ($nbParc + $nbRegie === 0) {
                $this->warn("  #{$mvt->id} — « {$ref} » introuvable (parc et régies).");
                $inconnus++;
                continue;
            }

            if ($nbParc + $nbRegie > 1) {
                $this->warn("  #{$mvt->id} — « {$ref} » correspond à plusieurs panneaux : laissé en l'état.");
                $ambigus++;
                continue;
            }

            $colonne = $nbParc === 1 ? 'panel_id' : 'external_panel_id';
            $valeur  = $nbParc === 1 ? $dansParc->first()->id : $dansRegie->first()->id;
            $origine = $nbParc === 1 ? 'parc' : 'régie';

            $this->line(sprintf(
                '  #%-5d %-14s %-8s → %s #%d (%s)',
                $mvt->id,
                $ref,
                $mvt->sens,
                $colonne,
                $valeur,
                $origine
            ));

            if ($appliquer) {
                $mvt->forceFill([$colonne => $valeur])->save();
            }

            $repares++;
        }

        $this->line('');
        $this->info("Rattachables : {$repares}" . ($appliquer ? ' (écrits)' : ' (simulation)'));
        if ($ambigus)  $this->warn("Ambigus, laissés en l'état : {$ambigus}");
        if ($inconnus) $this->warn("Introuvables, laissés en l'état : {$inconnus}");
        $this->line('');

        if ($appliquer) {
            Log::info('campagne.mouvements.reparation', [
                'repares'  => $repares,
                'ambigus'  => $ambigus,
                'inconnus' => $inconnus,
            ]);
        } else {
            $this->line('Relance avec --appliquer pour écrire.');
        }

        return self::SUCCESS;
    }
}

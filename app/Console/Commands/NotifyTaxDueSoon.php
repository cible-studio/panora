<?php

namespace App\Console\Commands;

use App\Services\AdminAlertNotifier;
use App\Services\AlertService;
use App\Services\Diffusion\JoursFeriesService;
use App\Services\Taxes\TaxeExerciceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Alerte de début de trimestre — taxes ODP/TM à prévoir pour les mairies.
 *
 * 2026-11-16 — Réécrite sur le Suivi des mairies. Avant, elle lisait les
 * échéances (due_date) de la table legacy `taxes`, que plus aucun écran ne
 * remplit : elle ne partait donc jamais. Désormais, le PREMIER JOUR OUVRÉ de
 * chaque trimestre, elle envoie aux comptables et aux administrateurs ce
 * que chaque mairie peut réclamer à ce jour (« exigible » : arriérés + ODP
 * et TM des trimestres commencés − déjà payé), d'après TaxeExerciceService.
 *
 * Planifiée chaque jour à 8 h 45 (routes/console.php) : elle ne fait rien
 * les autres jours. --forcer l'envoie tout de suite (contrôle en production).
 * L'option --days est conservée pour ne pas casser la planification ; elle
 * n'a plus d'effet.
 *
 * 2026-11-27 (point 18 validé) — Premier jour OUVRÉ fériés compris (mêmes
 * jours fériés que la diffusion : JoursFeriesService), une alerte dans le
 * centre d'alertes du comptable en plus du mail, et mail aux seuls comptes
 * actifs (AdminAlertNotifier).
 */
class NotifyTaxDueSoon extends Command
{
    protected $signature = 'taxes:notify-due-soon
                            {--days=15 : sans effet depuis le 2026-11-16 (conservée pour la planification)}
                            {--forcer : envoie même si ce n\'est pas le premier jour ouvré du trimestre}';

    protected $description = 'Alerte de début de trimestre : ce que chaque mairie peut réclamer (Suivi des mairies)';

    public function handle(TaxeExerciceService $suivi): int
    {
        if (!config('taxes.alerte_trimestre', true) && !$this->option('forcer')) {
            $this->info('Alerte de trimestre désactivée (TAXES_ALERTE_TRIMESTRE=false).');
            return self::SUCCESS;
        }

        $aujourdhui = now()->startOfDay();
        if (!$this->option('forcer') && !$aujourdhui->equalTo(self::premierJourOuvreDuTrimestre($aujourdhui))) {
            $this->info('Pas le premier jour ouvré du trimestre : rien à envoyer.');
            return self::SUCCESS;
        }

        $exercice = (int) $aujourdhui->year;
        if (!$suivi->estSuivi($exercice)) {
            $this->info("Exercice {$exercice} non suivi : rien à envoyer.");
            return self::SUCCESS;
        }

        $lignes = $suivi->exercices($exercice)
            ->filter(fn ($l) => $l['exigible'] > 0)
            ->sortByDesc('exigible');

        $trimestre = (int) $aujourdhui->quarter;
        if ($lignes->isEmpty()) {
            $this->info("Aucune somme exigible au T{$trimestre} {$exercice}.");
            return self::SUCCESS;
        }

        $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ');
        $total = (int) $lignes->sum('exigible');
        $detail = $lignes->take(15)->map(fn ($l) =>
            $l['commune']->name . ' — ' . $fmt($l['exigible']) . ' FCFA exigibles'
            . ($l['arrieres'] > 0 ? ' (dont arriérés ' . $fmt($l['arrieres']) . ')' : '')
        )->values()->all();
        if ($lignes->count() > 15) {
            $detail[] = '… et ' . ($lignes->count() - 15) . ' autre(s) mairie(s).';
        }

        AdminAlertNotifier::notify(
            to: ['comptable', 'admin'],
            severity: 'warning',
            // 2026-11-27 (point 16) : « T1 », plus « 1e trimestre ».
            title: "Taxes mairies — début du T{$trimestre} {$exercice}",
            summary: $lignes->count() . ' mairie(s) peuvent réclamer ' . $fmt($total)
                . ' FCFA à ce jour. Prévoir les règlements avant leur passage, et garder les quittances.',
            lines: $detail,
            ctaLabel: 'Ouvrir le Suivi des mairies →',
            ctaUrl: route('admin.taxes.suivi.index', ['exercice' => $exercice]),
            emoji: '🏛️',
            footer: 'Envoi automatique le premier jour ouvré de chaque trimestre',
            dedupKey: "taxes-trimestre-{$exercice}-T{$trimestre}",
        );

        // Centre d'alertes : diffusion sans destinataire nominatif, vue par
        // les comptables (broadcastTypesForRole) et les admins.
        AlertService::notify(
            'taxe_due_soon',
            "Taxes mairies — début du T{$trimestre} {$exercice}",
            $lignes->count() . ' mairie(s) peuvent réclamer ' . $fmt($total)
                . ' FCFA à ce jour. Prévoir les règlements et garder les quittances.',
            null,
            [
                'lien'        => route('admin.taxes.suivi.index', ['exercice' => $exercice]),
                'dedup_extra' => "{$exercice}-T{$trimestre}",
            ],
        );

        $this->info("Alerte envoyée : {$lignes->count()} mairie(s), {$fmt($total)} FCFA exigibles.");
        return self::SUCCESS;
    }

    /**
     * Premier jour ouvré du trimestre de $date : ni samedi, ni dimanche, ni
     * jour férié (le 1er janvier l'est toujours). Jours fériés : ceux de la
     * diffusion (JoursFeriesService), réglables dans Diffusion → Disponibilités.
     */
    public static function premierJourOuvreDuTrimestre(Carbon $date): Carbon
    {
        $debut = $date->copy()->firstOfQuarter()->startOfDay();

        return app(JoursFeriesService::class)->calendrier($debut)->premierOuvrableAPartirDe($debut);
    }
}

<?php

namespace App\Support;

use App\Enums\PoseTaskStatus;
use App\Models\PoseTask;
use App\Models\User;
use App\Services\GeoService;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Données d'affichage d'une pose dans l'espace technicien (refonte du
 * 2026-11-12). UNE seule source pour la ligne d'une pose, la ligne d'un
 * pied d'axe et la carte « Maintenant » : statut, retard, lien « Y aller »,
 * photo, position, recherche, seuils GPS.
 *
 * Les attributs data-* rendus restent ceux que lisent les modules JS
 * existants (pose-drawer, upload, status-changes, y-aller-modal…).
 */
final class TechCarte
{
    /** @return array<string, mixed> */
    public static function donnees(PoseTask $task): array
    {
        $panel  = $task->panel;
        $status = PoseTaskStatus::tryFrom((string) $task->status) ?? PoseTaskStatus::PLANNED;

        $sched   = $task->scheduled_at ?? $task->created_at;
        $isLate  = $sched && Carbon::parse($sched)->lt(PoseTask::lateThreshold());
        $isToday = $sched && Carbon::parse($sched)->isToday();

        // Position : celle du panneau, sinon celle de son axe (pied).
        $pos = $panel?->positionTerrain();
        if ($pos) {
            $goUrl = 'https://www.google.com/maps/dir/?api=1&destination=' . $pos['lat'] . ',' . $pos['lng'];
        } else {
            // Recherche Maps sur l'adresse ; pour un pied, sur l'axe (le
            // « — pied 3/11 » du nom n'aide pas Maps).
            $nom = $panel?->axe_id ? $panel?->axe?->name : $panel?->name;
            $q = trim(implode(' ', array_filter([$panel?->adresse, $nom, $panel?->commune?->name])));
            $goUrl = $q !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($q) : 'https://www.google.com/maps/';
        }

        $photo = $panel?->photoDeReference();
        $lastProblem = $task->lastProblemReport;
        $seuils = GeoService::seuilsPour($panel);

        return [
            'status'        => $status,
            'statusColor'   => $status->color(),
            'isLate'        => $isLate,
            'isToday'       => $isToday,
            'sched'         => $sched,
            'goUrl'         => $goUrl,
            'lat'           => $pos['lat'] ?? null,
            'lng'           => $pos['lng'] ?? null,
            'photoUrl'      => $photo ? asset('storage/' . $photo->path) : null,
            'problemLabel'  => $lastProblem?->effectiveMotif()?->label(),
            'problemAgo'    => $lastProblem?->created_at?->diffForHumans(null, true),
            'hasProblem'    => (bool) $lastProblem,
            'rejPige'       => $task->latestRejectedPige,
            'gpsOk'         => (int) $seuils['ok'],
            'gpsWarn'       => (int) $seuils['warn'],
            // Repère du contrôle : « axe » quand la position est celle d'un
            // axe (tolérance élargie) — l'aperçu photo dit « de l'axe ».
            'gpsRepere'     => ($pos['axe'] ?? false) ? 'axe' : 'panneau',
            'search'        => mb_strtolower(implode(' ', array_filter([
                $panel?->reference, $panel?->name, $panel?->axe?->reference,
                $panel?->commune?->name, $panel?->quartier, $panel?->adresse,
                $task->campaign?->name, $task->campaign?->client?->name,
            ]))),
        ];
    }

    /**
     * La journée du technicien, pour l'en-tête — UNE source : la page, la
     * réponse d'une photo et celle de « Terminé » (la barre et l'écran
     * « Bravo » se mettent à jour sans rechargement, sans recompter en JS).
     *   faites = poses terminées aujourd'hui ;
     *   total  = faites + encore à faire jusqu'à ce soir (retards compris).
     * L'ancienne barre comptait toutes les poses depuis toujours.
     *
     * @return array{faites: int, total: int, pct: int, message: string}
     */
    public static function journee(User $tech): array
    {
        $debut = Carbon::today();
        $fin   = Carbon::today()->endOfDay();

        $faites = PoseTask::where('assigned_user_id', $tech->id)
            ->where('status', PoseTaskStatus::COMPLETED->value)
            ->whereBetween('done_at', [$debut, $fin])
            ->count();

        $reste = PoseTask::where('assigned_user_id', $tech->id)
            ->whereNotNull('panel_id')->whereNotNull('campaign_id')
            ->whereNotIn('status', [PoseTaskStatus::COMPLETED->value, PoseTaskStatus::CANCELLED->value])
            ->onOperableCampaign()
            ->where(fn ($q) => $q->where('scheduled_at', '<=', $fin)->orWhereNull('scheduled_at'))
            ->count();

        $total = $faites + $reste;

        return [
            'faites'  => $faites,
            'total'   => $total,
            'pct'     => $total > 0 ? (int) round($faites / $total * 100) : 0,
            'message' => self::messageDuJour($faites, $total, self::prenom($tech)),
        ];
    }

    /** Message d'encouragement sous la barre du jour. */
    public static function messageDuJour(int $faites, int $total, string $prenom): string
    {
        $reste = max(0, $total - $faites);
        $pct   = $total > 0 ? (int) round($faites / $total * 100) : 0;

        return match (true) {
            $total === 0 => 'Rien de prévu aujourd\'hui',
            $reste === 0 => 'Journée terminée, bravo !',
            $reste <= 2  => 'Plus que ' . $reste . ' !',
            $pct >= 30   => 'Continue, tu avances bien',
            default      => 'Allez ' . $prenom . ' !',
        };
    }

    public static function prenom(User $tech): string
    {
        return (string) (Str::of((string) $tech->name)->explode(' ')->first() ?: $tech->name);
    }

    /**
     * Attributs data-* d'une pose (contrat des modules JS existants).
     */
    public static function attributs(PoseTask $task, array $d): string
    {
        $attrs = [
            'data-task-id'          => $task->id,
            'data-task-status'      => $d['status']->value,
            'data-search'           => $d['search'],
            'data-lat'              => $d['lat'],
            'data-lng'              => $d['lng'],
            'data-go-url'           => $d['goUrl'],
            'data-progress'         => (int) ($task->progress_percent ?? 0),
            'data-started-at'       => $task->started_at?->toIso8601String(),
            'data-arrived-at'       => $task->arrived_at?->toIso8601String(),
            'data-scheduled-today'  => $d['isToday'] ? '1' : '0',
            'data-late'             => $d['isLate'] ? '1' : '0',
            'data-has-problem'      => $d['hasProblem'] ? '1' : '0',
            'data-has-reject'       => $d['rejPige'] ? '1' : '0',
            'data-scheduled-at'     => $d['sched'] ? Carbon::parse($d['sched'])->toIso8601String() : '',
            'data-commune'          => $task->panel?->commune?->name,
            'data-gps-ok'           => $d['gpsOk'],
            'data-gps-warn'         => $d['gpsWarn'],
            'data-gps-repere'       => $d['gpsRepere'],
        ];
        if ($d['hasProblem']) {
            $attrs['data-blocking-signal-label'] = $d['problemLabel'];
        }

        return collect($attrs)
            ->map(fn ($v, $k) => $k . '="' . e((string) ($v ?? '')) . '"')
            ->implode(' ');
    }
}

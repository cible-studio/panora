<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Reservation;
use App\Services\AvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Page admin "Conflits détectés" — détection rétroactive des double-
 * bookings actuellement présents en BD.
 *
 * Pourquoi : même avec le nouveau filet (AvailabilityService::assertCanReserve),
 * il peut exister des conflits hérités d'avant le fix, ou créés via un
 * import / script SQL externe / migration manuelle. Cette page permet
 * de les détecter et de trancher.
 *
 * Workflow :
 *   1. Liste tous les panneaux engagés sur 2+ entités chevauchantes
 *   2. Pour chaque conflit, montre les engagements (résa OU campagne) +
 *      client, période, statut
 *   3. L'admin choisit quelle entité GARDER (les autres sont
 *      annulées via le flow standard d'annulation)
 *
 * Accès réservé aux admins (les MP/commerciaux peuvent voir mais ne
 * peuvent pas trancher → middleware role:admin sur l'action resolve).
 */
class ConflictsController extends Controller
{
    public function __construct(private AvailabilityService $availability) {}

    /**
     * GET /admin/conflicts
     * Liste tous les conflits actifs.
     */
    public function index(Request $request)
    {
        $conflicts = $this->availability->detectActiveConflicts();

        // Statistique haute pour le bandeau
        $total       = count($conflicts);
        $resaInvolved = collect($conflicts)
            ->flatMap(fn($c) => collect($c['engagements'])->where('type', 'reservation')->pluck('id'))
            ->unique()->count();
        $campInvolved = collect($conflicts)
            ->flatMap(fn($c) => collect($c['engagements'])->where('type', 'campaign')->pluck('id'))
            ->unique()->count();

        return view('admin.conflicts.index', compact(
            'conflicts',
            'total',
            'resaInvolved',
            'campInvolved',
        ));
    }

    /**
     * POST /admin/conflicts/resolve
     * L'admin choisit pour un panneau quelle entité garder.
     * Les autres engagements sur ce panneau sont retirés / annulés.
     *
     * Garde : action irréversible côté résa (annulation), réversible
     * côté campagne (pause/annulation). On force confirmation côté UI.
     */
    public function resolve(Request $request)
    {
        $data = $request->validate([
            'panel_id'        => 'required|integer|exists:panels,id',
            'keep_type'       => 'required|in:reservation,campaign',
            'keep_id'         => 'required|integer',
            'remove'          => 'required|array|min:1',
            'remove.*.type'   => 'required|in:reservation,campaign',
            'remove.*.id'     => 'required|integer',
        ]);

        $actor = auth()->user();
        $summary = ['removed' => [], 'errors' => []];

        DB::transaction(function () use ($data, $actor, &$summary) {
            foreach ($data['remove'] as $r) {
                try {
                    if ($r['type'] === 'reservation') {
                        // Retire CE panneau de cette réservation (detach)
                        $resa = Reservation::lockForUpdate()->find($r['id']);
                        if (!$resa) {
                            $summary['errors'][] = "Résa #{$r['id']} introuvable";
                            continue;
                        }
                        DB::table('reservation_panels')
                            ->where('reservation_id', $resa->id)
                            ->where('panel_id', $data['panel_id'])
                            ->delete();
                        $summary['removed'][] = "Résa {$resa->reference} : panneau retiré";
                    } else {
                        $camp = Campaign::lockForUpdate()->find($r['id']);
                        if (!$camp) {
                            $summary['errors'][] = "Camp. #{$r['id']} introuvable";
                            continue;
                        }
                        DB::table('campaign_panels')
                            ->where('campaign_id', $camp->id)
                            ->where('panel_id', $data['panel_id'])
                            ->delete();
                        $summary['removed'][] = "Campagne {$camp->name} : panneau retiré";
                    }
                } catch (\Throwable $e) {
                    $summary['errors'][] = "Erreur sur {$r['type']}#{$r['id']} : " . $e->getMessage();
                }
            }

            $this->availability->syncPanelStatuses([(int) $data['panel_id']]);

            Log::warning('conflict.resolved', [
                'panel_id' => $data['panel_id'],
                'kept'     => [$data['keep_type'] => $data['keep_id']],
                'removed'  => $data['remove'],
                'actor_id' => $actor?->id,
            ]);
        });

        // Invalide le cache du compteur sidebar pour que le badge
        // se mette à jour immédiatement après résolution.
        \Illuminate\Support\Facades\Cache::forget('admin.conflicts.count');

        $msg = count($summary['removed']) . ' engagement(s) retiré(s) pour ce panneau.';
        if (!empty($summary['errors'])) {
            $msg .= ' Erreurs : ' . implode(' · ', $summary['errors']);
        }

        return redirect()->route('admin.conflicts.index')->with('success', $msg);
    }
}

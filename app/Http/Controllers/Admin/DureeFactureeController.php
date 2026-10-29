<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Durée facturée d'une période (2026-10-29), pour les écrans qui calculent
 * un montant ou affichent une durée en direct (création de campagne,
 * modification de réservation, disponibilités, proposition). La règle reste
 * côté serveur (Campaign::dureeFacturee) : aucune copie en JavaScript.
 */
class DureeFactureeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'debut' => 'required|date_format:Y-m-d',
            'fin'   => 'required|date_format:Y-m-d|after_or_equal:debut',
        ]);

        $debut = Carbon::createFromFormat('Y-m-d', $data['debut'])->startOfDay();
        $fin   = Carbon::createFromFormat('Y-m-d', $data['fin'])->startOfDay();
        if ($debut->diffInDays($fin) > 366 * 5) {
            return response()->json(['message' => 'Période trop longue (5 ans au plus).'], 422);
        }

        return response()->json(Campaign::dureeFacturee($debut, $fin));
    }
}

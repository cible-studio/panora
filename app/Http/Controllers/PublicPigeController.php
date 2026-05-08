<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Panel;
use App\Models\Pige;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Page publique de prise de piges pour une campagne.
 *
 * Le commercial génère un token unique sur la fiche campagne, le partage
 * au technicien terrain (lien WhatsApp / SMS / QR papier) ; le technicien
 * ouvre l'URL sur son téléphone, voit la liste des panneaux de la
 * campagne et upload une photo (+ GPS auto si autorisé) pour chacun.
 *
 * Garde-fous :
 *   - throttle middleware appliqué côté routes pour éviter le scrape
 *   - status terminal (terminé / annulé) → page bloquée en lecture
 *   - photo size + mime types validés
 *   - aucun login requis (token = secret partagé)
 */
class PublicPigeController extends Controller
{
    public function show(string $token)
    {
        $campaign = Campaign::where('pige_token', $token)
            ->with([
                'client:id,name',
                'panels.commune:id,name',
                'panels.format:id,name',
                'panels.photos',
            ])
            ->first();

        if (!$campaign) {
            abort(404, 'Lien de pige invalide ou révoqué.');
        }

        $isClosed = in_array($campaign->status->value, ['termine', 'annule']);

        // Charge les piges existantes pour afficher l'état panneau par panneau.
        // On ne récupère que le minimum (id, panel_id, status, taken_at,
        // photo_path) pour rester léger sur smartphone.
        $existingPiges = Pige::where('campaign_id', $campaign->id)
            ->latest('taken_at')
            ->get(['id', 'panel_id', 'status', 'taken_at', 'photo_path', 'gps_lat', 'gps_lng', 'notes'])
            ->groupBy('panel_id');

        return view('public.pige-collect', [
            'campaign'      => $campaign,
            'token'         => $token,
            'isClosed'      => $isClosed,
            'existingPiges' => $existingPiges,
        ]);
    }

    public function upload(Request $request, string $token)
    {
        $campaign = Campaign::where('pige_token', $token)->first();

        if (!$campaign) {
            return response()->json(['ok' => false, 'message' => 'Lien invalide.'], 404);
        }

        if (in_array($campaign->status->value, ['termine', 'annule'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Cette campagne est ' . $campaign->status->label() . " — uploads désactivés.",
            ], 403);
        }

        $data = $request->validate([
            'panel_id' => ['required', 'integer', 'exists:panels,id'],
            // Plafond serveur à 50 MB pour couvrir les photos brutes
            // smartphone (jusqu'à 30-40 MB en HEIC/PRO mode). Le client
            // compresse à ~1-2 MB en JPEG avant upload (cf. canvas resize
            // côté JS) → l'upload reste rapide même en 4G faible.
            'photo'    => ['required', 'image', 'mimes:jpeg,jpg,png,webp,heic,heif', 'max:51200'], // 50 MB
            'gps_lat'  => 'nullable|numeric|between:-90,90',
            'gps_lng'  => 'nullable|numeric|between:-180,180',
            'notes'    => 'nullable|string|max:500',
            'tech_name'=> 'nullable|string|max:100', // nom du technicien (libre)
        ]);

        // Vérifier que le panel appartient bien à la campagne
        $belongsToCampaign = $campaign->panels()->where('panels.id', $data['panel_id'])->exists();
        if (!$belongsToCampaign) {
            return response()->json(['ok' => false, 'message' => 'Ce panneau n\'appartient pas à cette campagne.'], 422);
        }

        $folder = "piges/{$campaign->id}/{$data['panel_id']}";
        $filename = time() . '_' . \Illuminate\Support\Str::random(8) . '.' . $request->file('photo')->getClientOriginalExtension();
        $path = $request->file('photo')->storeAs($folder, $filename, 'public');

        // Notes : on préfixe pour identifier la source publique + nom du
        // technicien si fourni, utile pour la traçabilité côté admin.
        $noteParts = [];
        $noteParts[] = '[via lien public]';
        if (!empty($data['tech_name'])) {
            $noteParts[] = 'Tech: ' . $data['tech_name'];
        }
        if (!empty($data['notes'])) {
            $noteParts[] = $data['notes'];
        }

        $pige = Pige::create([
            'panel_id'    => $data['panel_id'],
            'campaign_id' => $campaign->id,
            'user_id'     => $campaign->user_id, // attribution au commercial créateur
            'photo_path'  => $path,
            'taken_at'    => now(),
            'gps_lat'     => $data['gps_lat'] ?? null,
            'gps_lng'     => $data['gps_lng'] ?? null,
            'notes'       => implode(' · ', $noteParts),
            'status'      => 'en_attente',
        ]);

        Log::info('pige.public.uploaded', [
            'pige_id'     => $pige->id,
            'campaign_id' => $campaign->id,
            'panel_id'    => $data['panel_id'],
            'tech_name'   => $data['tech_name'] ?? null,
            'ip'          => $request->ip(),
        ]);

        return response()->json([
            'ok'         => true,
            'message'    => 'Pige envoyée pour vérification.',
            'pige_id'    => $pige->id,
            'photo_url'  => Storage::url($path),
            'taken_at'   => $pige->taken_at->format('d/m/Y H:i'),
        ]);
    }
}

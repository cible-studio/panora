<?php
namespace App\Http\Controllers;

use App\Models\SatisfactionSurvey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * SatisfactionController — formulaire public d'avis client.
 *
 * Routes :
 *   GET  /satisfaction/{token}   → formulaire (5 critères + commentaire)
 *   POST /satisfaction/{token}   → enregistrement de la réponse
 *
 * Sécurité :
 *   - Token 64 chars validé par regex stricte (anti-injection)
 *   - Throttle côté routes
 *   - Pas de re-soumission possible (idempotent : si completed_at présent, page de remerciement)
 *   - IP loggée pour audit anti-spam léger
 */
class SatisfactionController extends Controller
{
    /**
     * Affiche le formulaire (ou la page de remerciement si déjà soumis).
     */
    public function show(string $token)
    {
        $survey = $this->resolveSurvey($token);

        $survey->load(['campaign:id,name,start_date,end_date,client_id', 'campaign.client:id,name', 'client:id,name']);

        if ($survey->isCompleted()) {
            return view('public.satisfaction-thanks', [
                'survey'      => $survey,
                'alreadyDone' => true,
            ]);
        }

        return view('public.satisfaction', [
            'survey' => $survey,
        ]);
    }

    /**
     * Enregistre la réponse au questionnaire.
     */
    public function submit(Request $request, string $token)
    {
        $survey = $this->resolveSurvey($token);

        if ($survey->isCompleted()) {
            return redirect()->route('satisfaction.show', $token);
        }

        $data = $request->validate([
            'score_global'                => 'required|integer|min:1|max:5',
            'score_qualite'               => 'required|integer|min:1|max:5',
            'score_delais'                => 'required|integer|min:1|max:5',
            'score_communication'         => 'required|integer|min:1|max:5',
            'score_rapport_qualite_prix'  => 'required|integer|min:1|max:5',
            'would_renew'                 => 'required|boolean',
            'commentaire'                 => 'nullable|string|max:2000',
        ], [
            'score_global.required' => 'Veuillez attribuer une note globale.',
            'score_global.min'      => 'La note minimale est 1.',
            'score_global.max'      => 'La note maximale est 5.',
            'would_renew.required'  => 'Indiquez si vous renouvelleriez cette campagne.',
        ]);

        $survey->update([
            'score_global'                => (int) $data['score_global'],
            'score_qualite'               => (int) $data['score_qualite'],
            'score_delais'                => (int) $data['score_delais'],
            'score_communication'         => (int) $data['score_communication'],
            'score_rapport_qualite_prix'  => (int) $data['score_rapport_qualite_prix'],
            'would_renew'                 => (bool) $data['would_renew'],
            'commentaire'                 => $data['commentaire'] ?? null,
            'completed_at'                => now(),
            'completed_ip'                => $request->ip(),
        ]);

        Log::info('satisfaction.completed', [
            'survey_id'   => $survey->id,
            'campaign_id' => $survey->campaign_id,
            'client_id'   => $survey->client_id,
            'score_global'=> $survey->score_global,
            'would_renew' => $survey->would_renew,
            'ip'          => $request->ip(),
        ]);

        // Alerte interne (non bloquante)
        if (class_exists(\App\Services\AlertService::class)) {
            \App\Services\AlertService::create(
                'satisfaction',
                $survey->score_global >= 4 ? 'info' : 'warning',
                '⭐ Avis client reçu — ' . ($survey->campaign?->name ?? '#' . $survey->campaign_id),
                ($survey->client?->name ?? 'Client') . ' a noté la campagne ' . $survey->score_global . '/5'
                    . ($survey->would_renew ? ' (renouvellerait)' : ' (pas de renouvellement)'),
                $survey->campaign,
            );
        }

        return view('public.satisfaction-thanks', [
            'survey'      => $survey->fresh(['campaign', 'client']),
            'alreadyDone' => false,
        ]);
    }

    /**
     * Résout le token (validation regex stricte avant query).
     */
    private function resolveSurvey(string $token): SatisfactionSurvey
    {
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            abort(404, 'Lien invalide.');
        }

        $survey = SatisfactionSurvey::where('token', $token)->first();
        if (!$survey) {
            abort(404, 'Lien invalide ou expiré.');
        }

        return $survey;
    }
}

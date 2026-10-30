<?php

namespace App\Http\Controllers;

use App\Models\Renouvellement;
use App\Services\Diffusion\ModelesMail;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Http\Request;

/**
 * Page des boutons de réponse des mails de renouvellement (2026-10-22).
 *
 * Lien personnel (jeton du dossier), sans connexion. Ouvrir le lien
 * n'enregistre rien : les antivirus de messagerie ouvrent les liens des
 * mails. C'est le bouton « Confirmer » (POST) qui enregistre la réponse.
 *
 * Une seule réponse par lien (2026-10-30) : une fois la réponse
 * enregistrée, la page la rappelle et n'offre plus de formulaire ; pour
 * changer d'avis, le client passe par son commercial.
 *
 * Liens « essai » (mails de test, aperçus) : même page, signée, mais
 * aucune réponse n'est enregistrée ; l'accusé et le mail à l'équipe
 * partent en test. Le blocage après réponse y est simulé pour la session
 * du navigateur (« ?refaire=1 » pour recommencer l'essai).
 */
class RenouvellementPublicController extends Controller
{
    public function __construct(private RenouvellementService $service) {}

    public function show(string $token, ?string $choix = null)
    {
        $dossier = Renouvellement::where('token', $token)->firstOrFail();

        return $this->page($dossier, $choix, essai: false);
    }

    public function store(Request $request, string $token)
    {
        $dossier = Renouvellement::where('token', $token)->firstOrFail();
        $data = $this->valider($request);

        if (!RenouvellementService::lienValable($dossier) || $dossier->statut === Renouvellement::CONFIRME
            || !$this->service->enregistrerReponse($dossier, $data['choix'], $data['message'] ?? null)) {
            // Lien expiré, renouvellement confirmé, ou réponse déjà donnée : rien n'est enregistré.
            return $this->page($dossier->fresh(), $data['choix'], essai: false);
        }

        return $this->merci($dossier, $data['choix'], essai: false);
    }

    public function essai(Request $request, int $dossier, string $sig, ?string $choix = null)
    {
        $d = $this->dossierEssai($dossier, $sig);
        if ($request->boolean('refaire')) {
            $request->session()->forget($this->cleEssai($d));
        }

        return $this->page($d, $choix, essai: true, reponseEssai: $request->session()->get($this->cleEssai($d)));
    }

    public function essaiStore(Request $request, int $dossier, string $sig)
    {
        $d = $this->dossierEssai($dossier, $sig);
        $data = $this->valider($request);

        // Même règle qu'en réel : une seule réponse (simulée pour ce navigateur).
        if ($deja = $request->session()->get($this->cleEssai($d))) {
            return $this->page($d, $data['choix'], essai: true, reponseEssai: $deja);
        }
        $this->service->simulerReponse($d, $data['choix'], $data['message'] ?? null);
        $request->session()->put($this->cleEssai($d), ['choix' => $data['choix'], 'le' => now()->toDateString()]);

        return $this->merci($d, $data['choix'], essai: true);
    }

    // ══════════════════════════════════════════════════════════════

    /** @param array{choix: string, le: string}|null $reponseEssai réponse simulée (liens essai) */
    private function page(Renouvellement $dossier, ?string $choix, bool $essai, ?array $reponseEssai = null)
    {
        $dossier->loadMissing(['campaign.client']);

        $etat = match (true) {
            !$essai && !RenouvellementService::lienValable($dossier) => 'expire',
            !$essai && $dossier->statut === Renouvellement::CONFIRME => 'confirme',
            !$essai && $dossier->reponse_at !== null                 => 'repondu',
            $essai && $reponseEssai !== null                         => 'repondu',
            default                                                  => 'formulaire',
        };

        return view('public.renouvellement', [
            'dossier'  => $dossier,
            'campagne' => $dossier->campaign,
            'etat'     => $etat,
            'essai'    => $essai,
            // Réponse déjà donnée (état « repondu ») : la vraie, ou la simulée en essai.
            'reponse'  => $etat === 'repondu'
                ? ($essai
                    ? ['choix' => $reponseEssai['choix'], 'le' => \Carbon\Carbon::parse($reponseEssai['le'])]
                    : ['choix' => $dossier->reponse_choix, 'le' => $dossier->reponse_at])
                : null,
            'choix'    => array_key_exists((string) $choix, ModelesMail::CHOIX) ? $choix : ($dossier->reponse_choix ?? 'renouveler'),
            'choixListe' => ModelesMail::CHOIX,
            'action'   => $essai
                ? route('renouvellement.essai.store', ['dossier' => $dossier->id, 'sig' => RenouvellementService::signatureEssai($dossier)])
                : route('renouvellement.store', ['token' => $dossier->token]),
        ]);
    }

    private function merci(Renouvellement $dossier, string $choix, bool $essai)
    {
        $dossier->loadMissing(['campaign.client']);

        return view('public.renouvellement', [
            'dossier'    => $dossier,
            'campagne'   => $dossier->campaign,
            'etat'       => 'merci',
            'essai'      => $essai,
            'choix'      => $choix,
            'choixListe' => ModelesMail::CHOIX,
            'action'     => null,
        ]);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'choix'   => 'required|in:' . implode(',', array_keys(ModelesMail::CHOIX)),
            'message' => 'nullable|string|max:2000',
        ]);
    }

    private function cleEssai(Renouvellement $dossier): string
    {
        return 'renouvellement_essai_reponse.' . $dossier->id;
    }

    private function dossierEssai(int $id, string $sig): Renouvellement
    {
        $dossier = Renouvellement::findOrFail($id);
        abort_unless(hash_equals(RenouvellementService::signatureEssai($dossier), $sig), 404);

        return $dossier;
    }
}

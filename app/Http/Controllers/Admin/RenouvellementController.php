<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ModeleMail;
use App\Models\RelanceFinCampagne;
use App\Models\Renouvellement;
use App\Models\User;
use App\Services\Diffusion\ModelesMail;
use App\Services\Diffusion\RelanceFinCampagneService;
use App\Services\Diffusion\RenouvellementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Écran « Renouvellements » des campagnes annuelles (2026-10-22).
 *
 *   · liste + compteurs : admin, MP, commercial (ses clients seulement) ;
 *   · statut et note après un appel : les mêmes ;
 *   · confirmation du renouvellement : MP et admin (route) ;
 *   · textes des mails (éditeur + aperçu immédiat + test) : admin.
 */
class RenouvellementController extends Controller
{
    public function __construct(
        private RenouvellementService $service,
        private RelanceFinCampagneService $socle,
        private ModelesMail $modeles,
    ) {}

    /** Groupes de statuts derrière les compteurs cliquables. */
    private const VUES = [
        'attente'    => [Renouvellement::EN_ATTENTE],
        'discussion' => [Renouvellement::RENOUVELER, Renouvellement::AJUSTER, Renouvellement::RAPPEL],
        'confirmes'  => [Renouvellement::CONFIRME],
        'perdus'     => [Renouvellement::REFUS],
    ];

    /**
     * Trois onglets (2026-10-23) : campagnes annuelles (renouvellement),
     * autres campagnes (mail J-7 simple), journal des envois. Par défaut
     * TOUTES les campagnes en cours : 30 / 60 / 90 jours sont des filtres.
     * ?campagne=ID ouvre directement la fiche du dossier.
     */
    public function index(Request $request)
    {
        $user   = $request->user();
        $role   = $user->role?->value;
        $estCom = $role === 'commercial';
        $onglet = in_array($request->query('onglet'), ['annuelles', 'courtes', 'journal'], true) ? $request->query('onglet') : 'annuelles';
        $horizon = in_array((int) $request->query('horizon', 0), [0, 30, 60, 90], true) ? (int) $request->query('horizon', 0) : 0;
        $vue    = array_key_exists((string) $request->query('vue'), self::VUES) ? $request->query('vue') : null;
        $statut = array_key_exists((string) $request->query('statut'), Renouvellement::STATUTS) ? $request->query('statut') : null;
        $q      = trim((string) $request->query('q'));
        $commercialId = $estCom ? $user->id : ((int) $request->query('commercial') ?: null);
        $ouvrir = (int) $request->query('campagne') ?: null;

        // ── Campagnes annuelles ──
        $lignes = $this->service->lignes($commercialId, $horizon ?: RenouvellementService::HORIZON_TOUTES);
        if ($ouvrir && !$lignes->contains(fn($l) => $l['campagne']->id === $ouvrir)) {
            $lignes = $lignes->merge($this->service->lignes($estCom ? $user->id : null, RenouvellementService::HORIZON_TOUTES, $ouvrir));
        }
        $compteurs = RenouvellementService::compteurs($lignes);

        $affichees = $lignes
            ->when($vue, fn($c) => $c->whereIn('statut', self::VUES[$vue]))
            ->when($statut, fn($c) => $c->where('statut', $statut))
            ->when($q !== '', fn($c) => $c->filter(fn($l) => str_contains(
                mb_strtolower($l['campagne']->name . ' ' . $l['campagne']->client?->name . ' ' . ($l['contact']['contact'] ?? '')),
                mb_strtolower($q)
            )))
            ->values();

        // ── Autres campagnes (mail J-7 simple) ──
        $courtes = $this->socle->prochaines($horizon ?: RenouvellementService::HORIZON_TOUTES, $commercialId)
            ->when($q !== '', fn($c) => $c->filter(fn($p) => str_contains(
                mb_strtolower($p['campagne']->name . ' ' . $p['campagne']->client?->name), mb_strtolower($q)
            )))
            ->values();

        // ── Journal des envois ──
        $journal = $onglet === 'journal'
            ? RelanceFinCampagne::with(['campaign:id,name', 'client:id,name'])
                ->when($commercialId, fn($j) => $j->whereIn('campaign_id', Campaign::forCommercialUser($commercialId)->pluck('id')))
                ->latest('updated_at')
                ->paginate(30)
                ->withQueryString()
            : null;

        return view('admin.renouvellements.index', [
            'onglet'        => $onglet,
            'lignes'        => $affichees,
            'nbAnnuelles'   => $lignes->count(),
            'compteurs'     => $compteurs,
            'courtes'       => $courtes,
            'journal'       => $journal,
            'horizon'       => $horizon,
            'vue'           => $vue,
            'q'             => $q,
            'commercialId'  => $estCom ? null : $commercialId,
            'commerciaux'   => $estCom ? collect() : User::where('role', 'commercial')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'ouvrir'        => $ouvrir,
            'peutConfirmer' => in_array($role, ['admin', 'mediaplanner'], true),
            'estAdmin'      => $role === 'admin',
            'estCom'        => $estCom,
            'modeTest'      => $this->socle->estModeTest(),
            // Liste vide : on dit quand arrive la prochaine échéance annuelle.
            'prochaine'     => $lignes->isEmpty() && $horizon
                ? $this->service->prochaineAnnuelle($commercialId, $horizon)
                : null,
        ]);
    }

    public function statut(Request $request, Campaign $campaign): RedirectResponse
    {
        $user = $request->user();
        $renouvellement = $this->dossierAutorise($request, $campaign);

        $permis = array_keys(Renouvellement::STATUTS);
        if (!in_array($user->role?->value, ['admin', 'mediaplanner'], true)) {
            // La confirmation reste au MP (règle validée le 2026-10-22).
            $permis = array_values(array_diff($permis, [Renouvellement::CONFIRME]));
            if ($renouvellement->statut === Renouvellement::CONFIRME) {
                $permis[] = Renouvellement::CONFIRME;
            }
        }

        $data = $request->validate([
            'statut'    => 'required|in:' . implode(',', $permis),
            'note'      => 'nullable|string|max:2000',
            'rappel_le' => 'nullable|date',
        ]);

        $this->service->changerStatut(
            $renouvellement,
            $data['statut'],
            $data['note'] ?? null,
            $user,
            $request->has('rappel_le') ? ($data['rappel_le'] ?? null) : false,
        );

        return redirect()->to(url()->previous())->with('success', 'Dossier mis à jour.');
    }

    /** Aperçu d'un mail de la campagne (frise de la fiche dossier, onglet J-7). */
    public function apercuMail(Request $request, Campaign $campaign, string $cle): Response
    {
        $user = $request->user();
        if ($user->role?->value === 'commercial') {
            abort_unless($campaign->belongsToCommercialUser($user->id), 403);
        }
        abort_unless($campaign->end_date && array_key_exists($cle, ModelesMail::ETAPES), 404);
        $campaign->loadMissing(['client.contacts', 'commercial', 'user']);

        $mail = $cle === RelanceFinCampagne::ETAPE_FIN ? $this->socle->mail($campaign) : $this->service->mail($campaign, $cle);

        return response($mail->render());
    }

    public function confirmer(Request $request, Campaign $campaign): RedirectResponse
    {
        $renouvellement = $this->dossierAutorise($request, $campaign);
        $this->service->changerStatut($renouvellement, Renouvellement::CONFIRME, $request->input('note'), $request->user());

        return back()->with('success', 'Renouvellement confirmé : les panneaux ne sont plus proposés dans le catalogue des disponibilités.');
    }

    // ══════════════════════════════════════════════════════════════
    // TEXTES DES MAILS (admin)
    // ══════════════════════════════════════════════════════════════

    public function modeles(Request $request)
    {
        $cle = array_key_exists((string) $request->query('cle'), ModelesMail::ETAPES) ? $request->query('cle') : 'j60';

        return view('admin.renouvellements.modeles', [
            'cle'        => $cle,
            'etapes'     => ModelesMail::ETAPES,
            'modele'     => $this->modeles->modele($cle),
            'defaut'     => ModelesMail::DEFAUTS[$cle],
            'champs'     => ModelesMail::CHAMPS,
            'ligne'      => ModeleMail::with('auteur:id,name')->where('cle', $cle)->first(),
            'modifies'   => ModeleMail::pluck('cle')->all(),
            'campagnes'  => $this->campagnesApercu($cle),
            'campagneId' => (int) $request->query('campagne'),
        ]);
    }

    public function enregistrerModele(Request $request, string $cle): RedirectResponse
    {
        abort_unless(array_key_exists($cle, ModelesMail::ETAPES), 404);

        if ($request->boolean('retablir')) {
            ModeleMail::where('cle', $cle)->delete();

            return redirect()->route('admin.renouvellements.modeles', ['cle' => $cle])
                ->with('success', 'Texte d\'origine rétabli.');
        }

        $data = $request->validate([
            'objet' => 'required|string|max:191', // colonne VARCHAR(191)
            'corps' => 'required|string|max:10000',
        ]);

        ModeleMail::updateOrCreate(['cle' => $cle], $data + ['modifie_par' => $request->user()->id]);

        return redirect()->route('admin.renouvellements.modeles', ['cle' => $cle, 'campagne' => $request->input('campagne')])
            ->with('success', 'Texte enregistré : il s\'applique dès les prochains envois.');
    }

    /** Aperçu immédiat de l'éditeur : le texte en cours de saisie, rendu par le serveur. */
    public function apercu(Request $request): Response
    {
        $data = $request->validate([
            'cle'      => 'required|in:' . implode(',', array_keys(ModelesMail::ETAPES)),
            'objet'    => 'nullable|string|max:191',
            'corps'    => 'nullable|string|max:10000',
            'campagne' => 'nullable|integer',
        ]);

        $campagne = $this->campagne($data['campagne'] ?? null);
        $modele   = ['objet' => (string) ($data['objet'] ?? ''), 'corps' => (string) ($data['corps'] ?? '')];
        $mail     = $data['cle'] === 'fin'
            ? $this->socle->mail($campagne, null, $modele)
            : $this->service->mail($campagne, $data['cle'], $modele);

        $sujet = e($mail->sujet());
        $html  = $mail->render();

        // Objet affiché au-dessus du mail, comme dans une messagerie.
        $entete = '<div style="font:13px Arial,sans-serif;color:#555;padding:12px 16px;border-bottom:1px solid #eee;background:#fafafa;">'
            . '<strong style="color:#111;">Objet :</strong> ' . $sujet . '</div>';

        return response(preg_replace('/<body([^>]*)>/i', '<body$1>' . $entete, $html, 1));
    }

    /** Envoi de test de l'étape, avec le texte ENREGISTRÉ, vers les adresses de test. */
    public function tester(Request $request, string $cle): RedirectResponse
    {
        abort_unless(array_key_exists($cle, ModelesMail::ETAPES), 404);
        $campagne = Campaign::with(['client.contacts', 'commercial', 'user'])->find((int) $request->input('campagne'));
        if (!$campagne || !$campagne->end_date) {
            return back()->with('error', 'Choisissez une campagne réelle pour l\'envoi de test.');
        }

        $ligne = match ($cle) {
            'fin'    => $this->socle->traiter($campagne, forcerTest: true),
            'accuse' => $this->testerAccuse($campagne),
            default  => $this->service->traiter($campagne, forcerTest: true, etape: $cle),
        };

        return back()->with(
            $ligne?->statut === RelanceFinCampagne::STATUT_ENVOYE ? 'success' : 'error',
            $ligne?->statut === RelanceFinCampagne::STATUT_ENVOYE
                ? "Mail de test envoyé. {$ligne->motif}"
                : 'Pas d\'envoi : ' . ($ligne?->motif ?? 'rien à envoyer pour cette campagne.')
        );
    }

    // ══════════════════════════════════════════════════════════════

    private function testerAccuse(Campaign $campagne): ?RelanceFinCampagne
    {
        $this->service->simulerReponse(
            $this->service->dossier($campagne),
            'renouveler',
            'Exemple : nous souhaitons garder les mêmes emplacements pour 12 mois.'
        );

        return $this->socle->ligne($campagne, RelanceFinCampagne::ETAPE_ACCUSE, RelanceFinCampagne::MODE_ESSAI);
    }

    /** Campagnes proposées pour l'aperçu : annuelles pour la séquence, courtes pour « fin ». */
    private function campagnesApercu(string $cle)
    {
        return Campaign::query()
            ->whereIn('status', ['actif', 'planifie'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', today()->toDateString())
            ->with('client:id,name')
            ->orderBy('end_date')
            ->limit(200)
            ->get()
            ->filter(fn(Campaign $c) => $cle === 'fin' ? !$c->estAnnuelle() : $c->estAnnuelle())
            ->take(40)
            ->values();
    }

    /** Campagne réelle choisie, ou exemple fictif (rien en base). */
    private function campagne(?int $id): Campaign
    {
        if ($id && ($c = Campaign::with(['client.contacts', 'commercial', 'user'])->find($id)) && $c->end_date) {
            return $c;
        }

        $client = new Client(['name' => 'Société Exemple', 'email' => 'contact@exemple.ci']);
        $client->setRelation('contacts', collect([new ClientContact(['name' => 'Awa Koné', 'email' => 'awa.kone@exemple.ci', 'is_primary' => true, 'receives_notifications' => true, 'statut' => 'client'])]));

        $c = new Campaign([
            'name'         => 'Campagne exemple',
            'start_date'   => today()->addDays(60)->subYear()->addDay(),
            'end_date'     => today()->addDays(60),
            'total_panels' => 12,
        ]);
        $c->setRelation('client', $client);
        $c->setRelation('commercial', null);
        $c->setRelation('user', null);

        return $c;
    }

    /** Dossier de la campagne (créé au besoin : un appel peut être noté avant J-60). */
    private function dossierAutorise(Request $request, Campaign $campagne): Renouvellement
    {
        $user = $request->user();
        if ($user->role?->value === 'commercial') {
            abort_unless($campagne->belongsToCommercialUser($user->id), 403);
        }
        abort_unless($campagne->end_date && $campagne->estAnnuelle(), 404);

        return $this->service->dossier($campagne);
    }
}

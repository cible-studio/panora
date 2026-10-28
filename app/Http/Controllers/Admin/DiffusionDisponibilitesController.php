<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiffusionDesinscription;
use App\Models\DiffusionEnvoi;
use App\Models\JourFerie;
use App\Models\RelanceFinCampagne;
use App\Services\Brevo\BrevoClient;
use App\Services\Diffusion\DiffusionCalendrier;
use App\Services\Diffusion\DiffusionDisponibilitesService;
use App\Services\Diffusion\JoursFeriesService;
use App\Services\Diffusion\RelanceFinCampagneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Écran « Diffusion des disponibilités » — réservé à l'admin (2026-09-29).
 *
 * Prochain envoi, bouton d'envoi manuel (avec rappel du dernier envoi),
 * envoi de test, journal, jours fériés, adresses exclues.
 */
class DiffusionDisponibilitesController extends Controller
{
    public function __construct(
        private DiffusionDisponibilitesService $diffusion,
        private JoursFeriesService $joursFeries,
        private BrevoClient $brevo,
        private RelanceFinCampagneService $finCampagne,
    ) {}

    public function index(Request $request)
    {
        // Journal (2026-10-28) : filtre, mois et pages courtes. Même avec des
        // milliers d'envois, l'écran reste lisible ; le choix suit les pages.
        $f = $request->query('journal');
        $filtre = is_string($f) && isset(DiffusionEnvoi::FILTRES[$f]) ? $f : 'tous';
        $m = $request->query('mois');
        $mois = is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : null;
        $compteurs = [];
        foreach (array_keys(DiffusionEnvoi::FILTRES) as $cle) {
            $compteurs[$cle] = DiffusionEnvoi::duMois($mois)->filtre($cle)->count();
        }

        // Adresses exclues : les 50 plus récentes, ou celles qui correspondent à la recherche.
        $x = $request->query('exclu');
        $rechercheExclu = is_string($x) ? trim($x) : '';
        $exclusions = DiffusionDesinscription::when($rechercheExclu !== '',
            fn($q) => $q->where('email', 'like', '%' . addcslashes($rechercheExclu, '%_\\') . '%'));

        $calendrier = $this->diffusion->calendrier();
        $prochain = $calendrier->prochainEnvoi(now());
        [$debut, $fin] = $calendrier->periode($prochain['creneau']);

        $annee = now()->year;

        return view('admin.diffusion.index', [
            'prochain'         => $prochain,
            'prochainePeriode' => DiffusionCalendrier::libellePeriode($debut, $fin),
            'modeTest'         => $this->diffusion->estModeTest(),
            'autoActif'        => (bool) config('diffusion.auto'),
            'brevoConfigure'   => $this->brevo->estConfigure(),
            'config'           => [
                'expediteur'   => config('brevo.sender.email'),
                'liste_clients' => config('brevo.lists.clients'),
                'liste_tests'  => config('brevo.lists.tests'),
                'modele'       => config('brevo.template_id'),
                'desinscription' => config('brevo.unsubscribe_page_id'),
                'webhook'      => (bool) config('brevo.webhook_token'),
            ],
            'nbDestinataires'  => $this->diffusion->destinataires()->count(),
            'confirmation'     => $this->diffusion->destinatairesInternes(
                new DiffusionEnvoi(['mode' => DiffusionEnvoi::MODE_MANUEL])
            ),
            'envois'           => DiffusionEnvoi::with('auteur:id,name')->duMois($mois)->filtre($filtre)
                                    ->latest('id')->paginate(10)->withQueryString()->fragment('journal'),
            'journalEnvois'    => [
                'filtre'     => $filtre,
                'mois'       => $mois,
                'compteurs'  => $compteurs,
                'moisDispos' => DiffusionEnvoi::orderByDesc('created_at')->pluck('created_at')
                                    ->map(fn($d) => $d->format('Y-m'))->unique()->values(),
            ],
            'joursFeries'      => JourFerie::whereBetween('date', ["{$annee}-01-01", ($annee + 1) . '-12-31'])
                                    ->orderBy('date')->get(),
            'musulmanesManquantes' => array_values(array_filter(
                [$annee, $annee + 1],
                fn($a) => !$this->joursFeries->musulmanesSaisies($a)
            )),
            'exclusions'       => (clone $exclusions)->orderByDesc('survenu_at')->limit(50)->get(),
            'exclusionsInfo'   => [
                'total'     => DiffusionDesinscription::count(),
                'trouvees'  => $rechercheExclu !== '' ? $exclusions->count() : null,
                'recherche' => $rechercheExclu,
            ],
            // Mail « Votre campagne se termine » J-7 (2026-10-21).
            'finCampagne'      => [
                'modeTest'   => $this->finCampagne->estModeTest(),
                'jours'      => $this->finCampagne->jours(),
                'copie'      => $this->finCampagne->copie(),
                'prochaines' => $this->finCampagne->prochaines(),
                'journal'    => RelanceFinCampagne::with(['campaign:id,name', 'client:id,name'])
                                    ->latest('updated_at')->limit(15)->get(),
            ],
        ]);
    }

    /** Ce que l'admin voit avant de confirmer l'envoi manuel. */
    public function apercu(): JsonResponse
    {
        return response()->json($this->diffusion->apercuEnvoiManuel());
    }

    /**
     * Envoi manuel. N'annule pas l'envoi automatique (règle validée).
     * Vérifications : case de confirmation cochée ; si un envoi a eu lieu
     * récemment, l'admin doit en plus taper ENVOYER.
     */
    public function envoyer(Request $request): RedirectResponse
    {
        $test = $request->boolean('test');

        $request->validate([
            'confirmation' => 'accepted',
        ], [
            'confirmation.accepted' => 'Cochez la case de confirmation avant d\'envoyer.',
        ]);

        if (!$test) {
            $apercu = $this->diffusion->apercuEnvoiManuel();
            if ($apercu['envoi_recent'] && mb_strtoupper(trim((string) $request->input('confirmation_texte'))) !== 'ENVOYER') {
                return back()->with('error',
                    "Un envoi a déjà eu lieu le {$apercu['dernier_envoi']['date']}. "
                    . 'Pour confirmer un nouvel envoi, tapez ENVOYER dans le champ prévu.');
            }
        }

        if (!$this->brevo->estConfigure()) {
            return back()->with('error', 'Brevo n\'est pas encore configuré (clé API ou expéditeur manquant dans le .env).');
        }

        // Anti double envoi : une demande déjà en file d'attente suffit.
        if ($enAttente = $this->diffusion->demandeEnAttente()) {
            return back()->with('error',
                'Un envoi est déjà en cours de préparation (demandé le '
                . $enAttente->created_at->format('d/m/Y à H\hi') . '). Attendez sa confirmation par mail.');
        }

        // Le PDF de tout le parc prend plusieurs minutes : on enregistre la
        // demande, le planificateur la traite dans la minute.
        $this->diffusion->demanderEnvoiManuel($request->user(), $test);

        return back()->with('success', $test
            ? 'Envoi de test demandé. Il part dans les prochaines minutes vers la liste « Tests internes » ; vous recevrez la confirmation par mail.'
            : 'Envoi demandé. Il part dans les prochaines minutes ; le service commercial et les media planners recevront la confirmation par mail.');
    }

    /** Vérifie la clé API sans rien envoyer. */
    public function testerConnexion(): JsonResponse
    {
        try {
            return response()->json(['ok' => true] + $this->brevo->compte());
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'erreur' => $e->getMessage()], 422);
        }
    }

    public function ajouterJourFerie(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date'    => 'required|date|unique:jours_feries,date',
            'libelle' => 'required|string|max:120',
            'source'  => 'required|in:musulman,manuel',
        ], [
            'date.unique' => 'Ce jour est déjà enregistré comme férié.',
        ]);

        JourFerie::create($data);

        return back()->with('success', 'Jour férié ajouté : ' . $data['libelle'] . '.');
    }

    public function supprimerJourFerie(JourFerie $jourFerie): RedirectResponse
    {
        $libelle = $jourFerie->libelle;
        $jourFerie->delete();

        return back()->with('success', "Jour férié retiré : {$libelle}.");
    }

    /** Exclure une adresse à la main (client qui l'a demandé par téléphone…). */
    public function exclure(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email|max:191']);

        DiffusionDesinscription::updateOrCreate(
            ['email' => mb_strtolower(trim($data['email']))],
            ['motif' => 'manuel', 'source' => 'panora', 'survenu_at' => now()]
        );

        return back()->with('success', 'Adresse exclue des prochains envois.');
    }

    /**
     * Réintégrer une adresse. Attention : si le client s'est désinscrit
     * lui-même via le lien du mail, Brevo le bloque de son côté aussi —
     * il faudra le réautoriser dans Brevo (l'écran le rappelle).
     */
    public function reintegrer(DiffusionDesinscription $exclusion): RedirectResponse
    {
        $email = $exclusion->email;
        // Désinscrit via le lien d'un mail Brevo : Brevo le bloque aussi. Via
        // le lien d'un mail Panora (source « lien ») : rien à faire dans Brevo.
        $etaitDesinscrit = $exclusion->motif === 'desinscription' && $exclusion->source === 'brevo';
        $exclusion->delete();

        return back()->with('success', "{$email} réintégré dans Panora."
            . ($etaitDesinscrit ? ' Ce contact s\'était désinscrit lui-même : réautorisez-le aussi dans Brevo, sinon Brevo continuera de le bloquer.' : ''));
    }
}

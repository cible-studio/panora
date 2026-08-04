<?php

namespace App\Http\Controllers;

use App\Mail\CibleContactMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Site vitrine CIBLE CI — régie publicitaire en Côte d'Ivoire.
 *
 * Contexte : CIBLE CI est la régie qui utilise Panora en interne. Ce
 * site vitrine est la face publique de la régie (annonceurs, agences,
 * partenaires). Panora est un outil que CIBLE utilise — pas un
 * patrimoine à mettre en avant. Simple mention dans le workflow.
 *
 * Routes :
 *   /cible                    → home (manifeste + preuves + CTA devis)
 *   /cible/qui-sommes-nous    → histoire 30 ans + distinctions + équipe
 *   /cible/services           → 3 pôles + 7 dispositifs + workflow
 *   /cible/reseau             → 364 panneaux · 31 communes
 *   /cible/references         → clients + campagnes + témoignages
 *   /cible/contact            → devis + coordonnées
 *   POST /cible/devis         → réception formulaire
 *
 * WIP : hébergé sur develop uniquement (comme /decouvrir landing Panora).
 * Merge main + hébergement dédié à décider après validation contenu.
 */
class CibleController extends Controller
{
    public function home()             { return view('public.cible.home',              $this->baseData('home')); }
    public function qui()              { return view('public.cible.qui-sommes-nous',   $this->baseData('qui')); }
    public function services()         { return view('public.cible.services',          $this->baseData('services')); }
    public function reseau()           { return view('public.cible.reseau',            $this->baseData('reseau')); }
    public function references()       { return view('public.cible.references',        $this->baseData('references')); }
    public function contact()          { return view('public.cible.contact',           $this->baseData('contact')); }

    /**
     * Endpoint public JSON pour la carte du réseau — /cible/api/reseau-map
     *
     * Retourne un agrégat par commune : 1 pin par commune (centroïde GPS
     * calculé depuis les panneaux) + nombre total de panneaux.
     *
     * ⚠ Sécurité — NE JAMAIS exposer ici :
     *   - Le rate / monthly_rate (info commerciale, cf. Panel.monthly_rate)
     *   - Le statut individuel (libre/occupé/maintenance — indique la
     *     disponibilité qui est du domaine commercial)
     *   - L'identifiant / la référence de chaque panneau
     *   - Le nom du client actuel
     * La position exacte de chaque panneau est anonymisée par
     * l'agrégation (centroïde AVG = position moyenne de la commune).
     *
     * Cache 1h : la donnée bouge peu (panneaux stables) → allège la BDD.
     */
    public function mapData()
    {
        $rows = \Illuminate\Support\Facades\Cache::remember(
            'cible.reseau_map.v1',
            now()->addHour(),
            fn() => \Illuminate\Support\Facades\DB::table('panels as p')
                ->join('communes as c', 'c.id', '=', 'p.commune_id')
                ->whereNotNull('p.latitude')
                ->whereNotNull('p.longitude')
                ->whereNull('p.deleted_at')
                ->groupBy('c.id', 'c.name', 'c.city', 'c.region')
                ->select(
                    'c.name as commune',
                    'c.city',
                    'c.region',
                    \Illuminate\Support\Facades\DB::raw('AVG(p.latitude)  as lat'),
                    \Illuminate\Support\Facades\DB::raw('AVG(p.longitude) as lng'),
                    \Illuminate\Support\Facades\DB::raw('COUNT(*) as total')
                )
                ->orderBy('c.name')
                ->get()
                ->map(fn($r) => [
                    'commune' => $r->commune,
                    'city'    => $r->city,
                    'region'  => $r->region,
                    'lat'     => round((float) $r->lat, 6),
                    'lng'     => round((float) $r->lng, 6),
                    'total'   => (int) $r->total,
                ])
                ->values()
                ->all()
        );

        return response()->json(['pins' => $rows], 200)
            ->header('Cache-Control', 'public, max-age=3600');
    }

    protected function baseData(string $current): array
    {
        return [
            'current' => $current,
            'nav' => [
                ['id' => 'home',       'route' => 'cible.home',       'label' => 'Accueil'],
                ['id' => 'qui',        'route' => 'cible.qui',        'label' => 'Qui sommes-nous'],
                ['id' => 'services',   'route' => 'cible.services',   'label' => 'Services'],
                ['id' => 'reseau',     'route' => 'cible.reseau',     'label' => 'Le réseau'],
                ['id' => 'references', 'route' => 'cible.references', 'label' => 'Références'],
                ['id' => 'contact',    'route' => 'cible.contact',    'label' => 'Contact', 'is_cta' => true],
            ],
        ];
    }

    /**
     * Réception du formulaire de demande de devis. Champs conçus pour
     * un annonceur ou une agence qui prépare une campagne — pas un
     * formulaire produit générique.
     */
    public function submitDevis(Request $request)
    {
        $data = $request->validate([
            'nom'         => ['required', 'string', 'max:100'],
            'entreprise'  => ['required', 'string', 'max:150'],
            'poste'       => ['nullable', 'string', 'max:100'],
            'tel'         => ['required', 'string', 'max:30'],
            'email'       => ['required', 'email', 'max:150'],
            'besoin'      => ['required', 'string', 'in:affichage,mobile,360,autre'],
            'zone'        => ['nullable', 'string', 'in:abidjan,interieur,national,autre'],
            'budget'      => ['nullable', 'string', 'in:moins1M,1a5M,5a20M,plus20M,pas-sur'],
            'periode'     => ['nullable', 'string', 'max:100'],
            'message'     => ['nullable', 'string', 'max:2000'],
            'website'     => ['nullable', 'string', 'max:0'],  // honeypot
        ], [
            'website.max' => 'Champ invalide.',
        ]);

        if (!empty($request->input('website'))) {
            Log::warning('cible.devis.honeypot_triggered', ['ip' => $request->ip()]);
            return back()->with('devis_sent', true);
        }

        try {
            Mail::to(config('mail.cible_devis_to', 'commercial@cible-ci.com'))
                ->send(new CibleContactMail([
                    ...$data,
                    'ip'          => $request->ip(),
                    'ua'          => substr((string) $request->userAgent(), 0, 200),
                    'received_at' => now()->format('d/m/Y H:i'),
                ]));

            Log::info('cible.devis.sent', [
                'nom' => $data['nom'], 'entreprise' => $data['entreprise'],
                'email' => $data['email'], 'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('cible.devis.mail_failed', [
                'error' => $e->getMessage(), 'data' => $data,
            ]);
            return back()->withInput()->with('devis_error',
                'Envoi impossible. Réessayez ou appelez le 07 98 49 66 74.'
            );
        }

        return back()->with('devis_sent', true);
    }
}

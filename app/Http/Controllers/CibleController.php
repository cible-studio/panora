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
    /* ═══════════════════════════════════════════════════════════════════
       Refonte V2 — 2026-10-08

       Les 6 pages d'origine (home, qui-sommes-nous, services, reseau,
       references, contact) totalisaient 44 sections et 5 069 mots, et
       affichaient encore « 364 panneaux » — chiffre proscrit depuis août,
       remplacé partout par « +400 ». Elles cèdent la place à la maquette
       V2 : 5 pages, 24 sections, 1 768 mots.

       Direction : « preuve terrain assumée ». Discipline de galerie reprise
       de McCann, la référence du client — navigation en capitales sans CTA,
       manifeste seul, travaux en position 2 — mais la preuve chiffrée est
       conservée, placée APRÈS les travaux : CIBLE possède un réseau
       physique, une galerie seule le cacherait.

       Les anciennes vues restent dans l'historique git (commit 3304e48 et
       antérieurs) si besoin de les relire.

       ⚠ Ceci est une MAQUETTE de revue. Le site de production vit dans son
       propre dépôt (cible-site, branche refonte-v2) : c'est lui qui part
       sur cible-ci.com. Rien ici ne doit devenir la source de vérité.
    ═══════════════════════════════════════════════════════════════════ */

    public function home()        { return view('public.cible.accueil',    $this->donnees('accueil')); }
    public function expertises()  { return view('public.cible.expertises', $this->donnees('expertises')); }
    public function reseau()      { return view('public.cible.reseau',     $this->donnees('reseau')); }
    public function travaux()     { return view('public.cible.travaux',    $this->donnees('travaux')); }
    public function contact()     { return view('public.cible.contact',    $this->donnees('contact')); }

    /**
     * Titres courts des réalisations — PROPOSITION À CORRIGER.
     *
     * Dérivés strictement des textes de config/contenu.php : aucune ville,
     * aucun chiffre, aucune durée n'a été ajouté, faute de connaître le
     * terrain. Justes mais abstraits — un titre nourri d'un fait réel les
     * battra tous.
     */
    public const TITRES_COURTS = [
        'orange'    => 'Aller chercher les gens',
        'cofina'    => "Ce qu'une institution a à dire",
        'snedai'    => 'Même voix, partout',
        'sgs-sicta' => 'Tenir la parole en ligne',
        'ifg'       => "Un stand qu'on n'évite pas",
        'sigfu'     => 'Donner un corps à une présence',
    ];


    /**
     * Visuels de TEST pour la revue — placeholders Unsplash, autorisés par
     * le client. Ils tiennent la place des prises de vue que la charte
     * demande (scènes de vie, personnages en joie, perroquet) et que CIBLE
     * n'a pas encore. ⚠ À remplacer avant toute mise en ligne publique.
     */
    public const VISUELS_TEST = [
        'orange'    => 'refonte/test/foule-festive.webp',
        'cofina'    => 'refonte/test/studio-lumiere.webp',
        'snedai'    => 'refonte/test/rue-afrique.webp',
        'sgs-sicta' => 'refonte/test/mobile.webp',
        'ifg'       => 'refonte/test/stand.webp',
        'sigfu'     => 'refonte/test/architecture.webp',
    ];
    /**
     * Données communes aux 5 pages. Le contenu vient de
     * App\Support\Contenu — défauts versionnés dans config/admin-schema.php
     * et config/contenu.php, exactement comme sur le site de production.
     */
    private function donnees(string $actuelle): array
    {
        $realisations = \App\Support\Contenu::section('realisations');

        foreach ($realisations as $slug => &$projet) {
            $projet['titre_court'] = self::TITRES_COURTS[$slug] ?? ($projet['titre'] ?? '');

            if (isset(self::VISUELS_TEST[$slug])) {
                $projet['image'] = self::VISUELS_TEST[$slug];
            }
        }
        unset($projet);

        return [
            'actuelle'     => $actuelle,
            'realisations' => $realisations,
            'options'      => self::formOptions(),
        ];
    }

    /**
     * Options du formulaire de contact.
     *
     * Recopiées depuis le site de production plutôt que liées : règle N°1 du
     * projet CIBLE — aucune dépendance entre les deux applications, on
     * duplique le code au besoin. Ici elles n'alimentent que l'affichage de
     * la maquette, le formulaire n'envoyant rien (cf. la vue contact).
     */
    public static function formOptions(): array
    {
        return [
            'objectif' => [
                'notoriete'   => "Accroître la notoriété d'une marque",
                'lancement'   => 'Lancer un produit ou un service',
                'trafic'      => 'Générer du trafic vers un point de vente',
                'zone'        => 'Toucher une audience dans une zone précise',
                'interaction' => 'Créer une interaction avec les consommateurs',
                'amplifier'   => 'Amplifier une campagne en ligne et sur le terrain',
                'valoriser'   => 'Valoriser une institution ou une entreprise',
                'nationale'   => 'Déployer une campagne nationale',
            ],
            'services' => [
                'classiques'  => 'Panneaux classiques',
                'lumipub'     => 'Lumipub (caissons éclairés)',
                'trivision'   => 'Trivision',
                'panoramique' => 'Panoramiques grand format',
                'digital'     => 'Écrans digitaux',
                'magasin'     => 'Affichage en magasin',
                'mobile'      => 'Communication mobile',
                'street'      => 'Street marketing',
                'stand'       => 'Stand ou architecture événementielle',
                'audiovisuel' => 'Production audiovisuelle',
                'reseaux'     => 'Réseaux sociaux',
            ],
            'zone' => [
                'abidjan'   => 'Abidjan',
                'interieur' => 'Intérieur du pays',
                'national'  => 'Couverture nationale',
                'precise'   => 'Une zone précise à définir',
            ],
            'budget' => [
                'moins1M' => 'Moins de 1 million FCFA',
                '1a5M'    => 'De 1 à 5 millions FCFA',
                '5a20M'   => 'De 5 à 20 millions FCFA',
                'plus20M' => 'Plus de 20 millions FCFA',
                'pas-sur' => 'Je ne sais pas encore',
            ],
        ];
    }

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

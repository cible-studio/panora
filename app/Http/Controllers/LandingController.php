<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Landing publique Panora — vitrine commerciale WIP develop uniquement.
 *
 * Refonte V2 (2026-07-15) : direction éditoriale premium (style Linear,
 * Stripe, Vercel) avec 5 sous-pages pour détailler ce que Panora fait
 * réellement. La V1 (1 page inspirée d'iOOH) a été abandonnée — trop
 * semblable au concurrent et pas assez dense pour convaincre un
 * directeur de régie.
 *
 * Routes :
 *   /decouvrir                     → home (manifeste + teasers)
 *   /decouvrir/produit             → tour produit détaillé
 *   /decouvrir/pour-directions     → persona direction régie
 *   /decouvrir/pour-commerciaux    → persona commercial
 *   /decouvrir/demo                → demande de démo (formulaire enrichi)
 *   POST /decouvrir/demande-demo   → réception formulaire
 *
 * Positionnement : Panora est un PRODUIT INDÉPENDANT à commercialiser,
 * pas une vitrine CIBLE. Aucun contenu ne cite CIBLE comme cas client
 * (c'est l'éditeur / créateur du produit).
 */
class LandingController extends Controller
{
    public function home()
    {
        return view('public.landing.home', $this->baseData('home'));
    }

    public function produit()
    {
        return view('public.landing.produit', $this->baseData('produit'));
    }

    public function pourDirections()
    {
        return view('public.landing.pour-directions', $this->baseData('directions'));
    }

    public function pourCommerciaux()
    {
        return view('public.landing.pour-commerciaux', $this->baseData('commerciaux'));
    }

    public function demo()
    {
        return view('public.landing.demo', $this->baseData('demo'));
    }

    /**
     * Données partagées par toutes les pages du micro-site landing.
     * Permet à _layout.blade.php de bien highlighter l'entrée nav active.
     */
    protected function baseData(string $current): array
    {
        return [
            'current' => $current,
            'nav' => [
                ['id' => 'home',        'route' => 'landing.show',            'label' => 'Panora',       'is_brand' => true],
                ['id' => 'produit',     'route' => 'landing.produit',         'label' => 'Le produit'],
                ['id' => 'directions',  'route' => 'landing.pour-directions', 'label' => 'Pour la direction'],
                ['id' => 'commerciaux', 'route' => 'landing.pour-commerciaux','label' => 'Pour les commerciaux'],
                ['id' => 'demo',        'route' => 'landing.demo',            'label' => 'Demander une démo', 'is_cta' => true],
            ],
        ];
    }

    /**
     * Réception du formulaire de demande de démo (version enrichie sur
     * la page /decouvrir/demo — champs supplémentaires nb_panneaux,
     * urgence, cover_message).
     */
    public function submitDemoRequest(Request $request)
    {
        $data = $request->validate([
            'nom'          => ['required', 'string', 'max:100'],
            'regie'        => ['required', 'string', 'max:150'],
            'role'         => ['required', 'string', 'in:direction,commercial,operations,autre'],
            'tel'          => ['required', 'string', 'max:30'],
            'email'        => ['required', 'email', 'max:150'],
            'nb_panneaux'  => ['nullable', 'string', 'max:50'],
            'urgence'      => ['nullable', 'string', 'in:immediat,3mois,exploration'],
            'message'      => ['nullable', 'string', 'max:2000'],
            'website'      => ['nullable', 'string', 'max:0'],  // honeypot
        ], [
            'website.max' => 'Champ invalide.',
        ]);

        if (!empty($request->input('website'))) {
            Log::warning('landing.demo_request.honeypot_triggered', [
                'ip' => $request->ip(),
                'ua' => substr((string) $request->userAgent(), 0, 200),
            ]);
            return back()->with('demo_sent', true);
        }

        try {
            Mail::to(config('mail.demo_request_to', 'studio@cible-ci.com'))
                ->send(new DemoRequestMail([
                    ...$data,
                    'ip'          => $request->ip(),
                    'ua'          => substr((string) $request->userAgent(), 0, 200),
                    'received_at' => now()->format('d/m/Y H:i'),
                ]));

            Log::info('landing.demo_request.sent', [
                'nom'   => $data['nom'],
                'regie' => $data['regie'],
                'email' => $data['email'],
                'ip'    => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::error('landing.demo_request.mail_failed', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            return back()->withInput()->with('demo_error',
                'Envoi impossible pour le moment. Réessayez ou contactez-nous directement à studio@cible-ci.com.'
            );
        }

        return back()->with('demo_sent', true);
    }
}

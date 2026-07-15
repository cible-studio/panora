<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Landing publique — vitrine commerciale Panora.
 *
 * Objectif : proposer Panora à d'autres régies OOH (pas une pub de CIBLE).
 * Positionnement : plateforme métier éprouvée en production, adaptée à
 * la réalité africaine (FNE, taxes communales, terrain PWA).
 *
 * WIP develop uniquement — pas de merge main tant que la patronne n'a
 * pas validé la version. Domaine final à décider plus tard, la landing
 * vit temporairement sur /decouvrir du domaine actuel.
 */
class LandingController extends Controller
{
    public function show()
    {
        return view('public.landing.index');
    }

    public function submitDemoRequest(Request $request)
    {
        $data = $request->validate([
            'nom'     => ['required', 'string', 'max:100'],
            'regie'   => ['required', 'string', 'max:150'],
            'role'    => ['required', 'string', 'in:direction,commercial,operations,autre'],
            'tel'     => ['required', 'string', 'max:30'],
            'email'   => ['required', 'email', 'max:150'],
            'message' => ['nullable', 'string', 'max:1500'],
            // Honeypot anti-bot : champ caché, doit rester vide
            'website' => ['nullable', 'string', 'max:0'],
        ], [
            'website.max' => 'Champ invalide.',
        ]);

        // Anti-spam : si le honeypot est rempli, on log et on renvoie succès
        // (le bot ne saura pas qu'il a été détecté)
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
                    'ip' => $request->ip(),
                    'ua' => substr((string) $request->userAgent(), 0, 200),
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
                'Envoi impossible pour le moment. Réessayez ou contactez-nous par WhatsApp.'
            );
        }

        return back()->with('demo_sent', true);
    }
}

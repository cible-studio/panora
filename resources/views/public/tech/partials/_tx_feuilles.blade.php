{{-- Refonte 2026-11-12, étape 2 — feuilles du bas (maquette validée),
     pilotées par features/refonte.js.

       · #tx-menu   : le menu de l'initiale — aide, appeler le chef, feuille
                      de route papier, texte plus gros, économie de batterie.
                      Les préférences gardent leurs clés (sm2c.js).
       · #tx-envoi  : « Photo envoyée » + la pose suivante, à la place de
                      l'écran plein « Bravo » et du rechargement. Les textes
                      viennent de la réponse du serveur (poseSuivante).

     Paramètre : $token. --}}
@php $chef = config('tech_space.chief_phone'); @endphp

<div class="tx-feuille-fond" id="tx-menu" hidden>
    <div class="tx-feuille" role="dialog" aria-modal="true" aria-label="Menu">
        <span class="tx-poignee" aria-hidden="true"></span>
        <nav class="tx-menu">
            <button type="button" data-action="open-help" data-tx-fermer>
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.3c-.6.3-1 .8-1 1.5v.4"/><circle cx="12" cy="17" r="1.1" fill="currentColor"/></svg>
                Aide : les 3 gestes
            </button>
            @if($chef)
                <a data-tx-fermer href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $chef) }}">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/></svg>
                    Appeler mon chef
                </a>
            @endif
            <a data-tx-fermer href="{{ route('tech.space.route-sheet', ['token' => $token]) }}" target="_blank" rel="noopener">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6"/></svg>
                Ma feuille de route papier
            </a>
            <button type="button" data-pref-key="sm2c_pref_large_text" role="switch" aria-checked="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M4 19 9 5l5 14M5.8 14h6.4M15 19l3-8 3 8M16 16.5h4"/></svg>
                Texte plus gros <i class="tx-interrupteur" aria-hidden="true"></i>
            </button>
            <button type="button" data-pref-key="sm2c_pref_low_power" role="switch" aria-checked="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="16" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M21 11v2M7 10v4"/></svg>
                Économie de batterie <i class="tx-interrupteur" aria-hidden="true"></i>
            </button>
        </nav>
        <button type="button" class="tx-btn" data-tx-fermer>Fermer</button>
    </div>
</div>

<div class="tx-feuille-fond" id="tx-envoi" hidden>
    <div class="tx-feuille" role="dialog" aria-modal="true" aria-labelledby="tx-envoi-titre">
        <span class="tx-poignee" aria-hidden="true"></span>
        <div class="tx-reussi">
            <span class="tx-rond" aria-hidden="true"><svg viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg></span>
            <div><strong id="tx-envoi-titre" data-tx-envoi-titre>Photo envoyée</strong><span data-tx-envoi-jour></span></div>
        </div>
        {{-- Photos multiples (validé le 12/11) : « 1/3 photos · réf. » et une
             photo de plus sur la MÊME pose. Le porteur reçoit les attributs de
             la pose (refonte.js) ; le bouton est un <label> : l'appareil photo
             s'ouvre au toucher, sans script. --}}
        <p class="tx-envoi-photos" data-tx-envoi-photos hidden></p>
        <div data-tx-envoi-porteur hidden>
            <span class="pose-ref" hidden></span>
            <label class="tx-btn tx-btn-orange tx-envoi-ajouter">
                <input type="file" accept="image/*" data-photo-input>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span data-tx-envoi-ajouter-txt>Ajouter une photo</span>
            </label>
        </div>
        <div class="tx-ligne tx-envoi-suivante" data-tx-envoi-suivante hidden>
            <div class="tx-ligne-corps">
                <span class="tx-vignette" data-tx-envoi-photo></span>
                <span class="tx-ligne-inf">
                    <span class="tx-ref" data-tx-envoi-ref></span>
                    <span class="tx-axe-nom" data-tx-envoi-nom></span>
                    <span class="tx-ligne-sous" data-tx-envoi-sous></span>
                </span>
                <span></span>
            </div>
        </div>
        <p class="tx-envoi-fini" data-tx-envoi-fini hidden>Plus rien à faire pour l'instant. Bravo !</p>
        <div class="tx-actions">
            <button type="button" class="tx-btn" data-tx-fermer>Terminer</button>
            <button type="button" class="tx-btn tx-btn-vert" data-tx-envoi-continuer>Continuer</button>
        </div>
    </div>
</div>

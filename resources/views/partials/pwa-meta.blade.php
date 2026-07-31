{{-- ═══════════════════════════════════════════════════════════════════
     Panora — Meta PWA
     Inclus dans les 3 layouts (admin, app, guest) pour que l'app soit
     installable depuis n'importe quelle page.

     Composants :
       - manifest.webmanifest → nom, icons, start_url, display standalone
       - theme-color         → couleur barre système Android + PWA header
       - apple-mobile-web-*  → iOS Safari "Ajouter à l'écran d'accueil"
       - apple-touch-icon    → icône home iOS (180×180)
       - script SW           → enregistre /sw.js (installabilité + offline)

     RÈGLE N°1 HARMONISATION — ce partial est la source unique pour tous
     les layouts. Ne pas dupliquer ces balises directement dans un layout.
     ═══════════════════════════════════════════════════════════════════ --}}

<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#0a0c10" media="(prefers-color-scheme: dark)">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="application-name" content="Panora">
<meta name="mobile-web-app-capable" content="yes">

{{-- iOS Safari — Ajouter à l'écran d'accueil --}}
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Panora">
<link rel="apple-touch-icon" href="{{ asset('images/apple-touch-180.png') }}">

{{-- Enregistrement du service worker.
     Silencieux si le navigateur ne supporte pas (vieux Safari, IE) ou si
     l'utilisateur est en http:// (SW nécessite HTTPS ou localhost).
     Note : Coolify sert la prod en HTTPS via Cloudflare → SW OK. --}}
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
        navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' })
            .catch(function (err) {
                // Silencieux — pas de bruit dans la console utilisateur.
                if (window.console && console.debug) console.debug('SW register failed:', err);
            });
    });
}
</script>

{{-- _pwa_install.blade.php — Phase 2 SM1 (rendu pixel-identique).
     Méta-tags d'installation PWA : manifeste + theme color + apple-touch-icon.
     Le Service Worker est enregistré côté JS plus bas dans tech-space.blade.php
     (lignes 2824-2835 du <script>) — la Phase 3 l'extraira en module séparé. --}}
<link rel="manifest" href="{{ asset('tech.webmanifest') }}">
<meta name="theme-color" content="#e8a020">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Panora Tech">
<link rel="apple-touch-icon" href="{{ asset('images/favicond.png') }}">

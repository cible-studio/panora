{{-- _styles.blade.php — SM2a Phase 6 Lot 6.1.
     Le contenu CSS de l'espace technicien (~2073 lignes) a été extrait
     vers public/css/tech/tech-app.css. Ce partial ne sert plus qu'à
     publier le <link> avec cache-busting via APP_VERSION du .env.

     Avantages de l'extraction :
       - HTML rendu allégé de ~82 KB par page tech (perf 2G/Edge)
       - Cache navigateur côté tech : le CSS est récupéré 1 fois puis
         servi depuis le SW (panora-tech-static-vX) pour les visites
         suivantes
       - Plus simple à éditer (les outils IDE syntax-checkent un .css
         pur)

     Cache-busting : ?v={app.version}. À chaque release qui touche
     tech-app.css, bump SW_VERSION (cf. tech-sw.js) pour purger l'ancien. --}}
<link rel="stylesheet"
      href="{{ asset('css/tech/tech-app.css') }}?v={{ config('app.version', '1') }}">

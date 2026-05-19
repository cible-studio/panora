{{-- ──────────────────────────────────────────────────────────────────
     Fallback anti-cache périmé après déploiement
     ──────────────────────────────────────────────────────────────────
     À inclure dans le <head> de tous les layouts.

     Problème résolu :
       Quand on déploie une nouvelle version, Vite génère de nouveaux
       noms de fichiers CSS/JS avec hash (app-aB1cD2.css). L'ancien
       HTML cached par le navigateur référence les ANCIENS noms qui
       n'existent plus → 404 → page rendue sans styles.

       Le middleware PreventStaleHtmlCache empêche le cache HTML côté
       serveur — mais si la page est DÉJÀ cachée (avant ce déploiement),
       le navigateur ne re-demande pas le HTML, on ne peut donc pas
       injecter de nouveau header. Le JS ci-dessous est la dernière
       ligne de défense côté client.

     Comportement :
       Si un <link rel=stylesheet> échoue au chargement (404 typique
       sur asset Vite périmé), on déclenche un reload UNIQUE (anti-
       boucle via sessionStorage) qui bypass le cache navigateur et
       récupère la nouvelle URL des assets.
─────────────────────────────────────────────────────────────────── --}}
<meta http-equiv="Cache-Control" content="no-cache, must-revalidate">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<script>
(function () {
    document.addEventListener('error', function (e) {
        var t = e.target;
        if (!t || t.tagName !== 'LINK') return;
        if ((t.rel || '').toLowerCase() !== 'stylesheet') return;
        if (sessionStorage.getItem('panora.cache-reload') === '1') return;
        sessionStorage.setItem('panora.cache-reload', '1');
        console.warn('[panora] CSS asset 404 → reload pour récupérer le HTML à jour');
        window.location.reload();
    }, true);
})();
</script>

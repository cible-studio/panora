{{--
    Durée facturée d'une période, demandée au serveur (2026-10-29) :
    Campaign::dureeFacturee via la route admin.outils.duree-facturee. La règle
    de facturation n'est JAMAIS recopiée en JavaScript (CLAUDE.md).

    window.PanoraDuree.charger(debut, fin)  → Promise de {jours, duree, mois, facture}
                                              ou null (dates absentes, invalides, erreur)
    window.PanoraDuree.differe(delai)       → fonction (debut, fin) anti-rafale : seule
                                              la dernière demande aboutit, les précédentes
                                              ne se résolvent jamais.
    Les montants se calculent ensuite : tarif mensuel × d.mois.

    Paramètre optionnel $url : la page publique d'une proposition passe sa
    propre route (proposition.duree-facturee), le client n'étant pas connecté.
--}}
@once
<script>
window.PanoraDuree = window.PanoraDuree || (function () {
    var url = @json($url ?? route('admin.outils.duree-facturee'));
    var cache = {};

    function charger(debut, fin) {
        if (!debut || !fin || fin < debut) return Promise.resolve(null);
        var cle = debut + '|' + fin;
        if (!cache[cle]) {
            cache[cle] = fetch(url + '?debut=' + encodeURIComponent(debut) + '&fin=' + encodeURIComponent(fin), {
                headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .catch(function () { return null; })
                .then(function (d) { if (!d) delete cache[cle]; return d; }); // une erreur se retente
        }
        return cache[cle];
    }

    function differe(delai) {
        var minuterie = null, derniere = null;
        return function (debut, fin) {
            var cle = debut + '|' + fin;
            derniere = cle;
            clearTimeout(minuterie);
            return new Promise(function (ok) {
                minuterie = setTimeout(function () {
                    charger(debut, fin).then(function (d) { if (cle === derniere) ok(d); });
                }, delai === undefined ? 200 : delai);
            });
        };
    }

    return { charger: charger, differe: differe };
})();
</script>
@endonce

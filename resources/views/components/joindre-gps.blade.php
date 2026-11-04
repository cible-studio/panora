{{--
    Case « Joindre ma position » à l'envoi d'une photo de pige (2026-11-04).

    Demande : cochée par défaut, mais le technicien peut la décocher — cas
    du tech qui a eu un souci de réseau et envoie la photo depuis chez lui,
    sans donner la position de son domicile. Décochée, la photo part SANS
    gps_lat / gps_lng ; le serveur classe alors la pige « Sans GPS »
    (geo_check = no_gps, cf. PigeObserver), comme quand le GPS ne répond pas.

    Une seule case, un seul libellé, une seule lecture pour les trois
    écrans d'envoi du technicien :
      - espace technicien : modale d'aperçu T3 (features/upload.js) ;
      - lien de pose      : public/pose-task ;
      - lien de campagne  : public/pige-collect (legacy).

    JS (classique, pas de module — les deux pages publiques n'en ont pas) :
      window.PanoraGps.joindre(racine)  → true s'il faut joindre la position :
          case cochée, OU absente (page restée en cache d'avant la case →
          comportement d'avant) ;
      window.PanoraGps.recocher(racine) → recoche la case : chaque photo
          repart cochée (on ne reste pas décoché par oubli).

    Aide derrière l'icône ⓘ : title au survol, une ligne au toucher.
--}}
<div {{ $attributes->class(['px-gps']) }}>
    <label class="px-gps-case">
        <input type="checkbox" data-joindre-gps checked>
        <span>📍 Joindre ma position</span>
    </label>
    <button type="button" class="px-gps-info" data-joindre-gps-aide aria-expanded="false"
            aria-label="Aide : position GPS"
            title="À décocher si l'envoi se fait loin du panneau (plus tard, depuis chez soi…) : la photo part alors sans position.">ⓘ</button>
    <span class="px-gps-aide" hidden>À décocher si l'envoi se fait loin du panneau (plus tard, depuis chez soi…) : la photo part alors sans position.</span>
</div>

@once
<style>
    /* Couleurs avec repli : les trois pages n'ont pas les mêmes variables. */
    .px-gps {
        display: flex; flex-wrap: wrap; align-items: center; gap: 4px 6px;
        margin: 10px 0 0; font-size: 14px; color: var(--text, #0f172a);
    }
    .px-gps-case {
        display: inline-flex; align-items: center; gap: 8px;
        min-height: 40px; cursor: pointer; font-weight: 600;
        -webkit-tap-highlight-color: transparent;
    }
    .px-gps-case input {
        width: 20px; height: 20px; margin: 0; flex: 0 0 auto;
        accent-color: var(--accent, #c2570d); cursor: pointer;
    }
    .px-gps-info {
        display: inline-flex; align-items: center; justify-content: center;
        width: 28px; height: 28px; padding: 0; border: 0; border-radius: 50%;
        background: transparent; color: var(--text3, #94a3b8);
        font-size: 15px; line-height: 1; cursor: help; font-family: inherit;
    }
    .px-gps-info[aria-expanded="true"] { color: var(--text2, #475569); background: var(--surface2, #f1f5f9); }
    .px-gps-aide {
        flex: 1 0 100%; font-size: 12px; line-height: 1.45;
        color: var(--text2, #475569);
    }
    .px-gps-aide[hidden] { display: none; }
</style>
<script>
(function () {
    function cases(racine) {
        return (racine || document).querySelector('[data-joindre-gps]');
    }
    window.PanoraGps = window.PanoraGps || {
        joindre: function (racine) {
            var c = cases(racine);
            return !c || c.checked;
        },
        recocher: function (racine) {
            var c = cases(racine);
            if (c) c.checked = true;
        },
    };
    // ⓘ : affiche / masque la ligne d'aide. Phase de capture : la modale
    // du lien de campagne arrête la propagation des clics.
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('[data-joindre-gps-aide]');
        if (!b) return;
        e.preventDefault();
        var aide = b.parentNode.querySelector('.px-gps-aide');
        if (!aide) return;
        aide.hidden = !aide.hidden;
        b.setAttribute('aria-expanded', aide.hidden ? 'false' : 'true');
    }, true);
})();
</script>
@endonce

{{-- ═══════════════════════════════════════════════════════════════════
     REFONTE V2 — coque de la maquette.

     Totalement séparée de resources/views/_layout.blade.php : la V1 reste
     en production pendant qu'on travaille ici. Aucun fichier de la V1
     n'est modifié par la refonte.

     Direction retenue avec le client (2026-10-08) : « preuve terrain
     assumée ». Les photos sont documentaires, pas cinématographiques —
     on ne cherche donc pas à imiter un site de réseau américain. Le
     premium vient de trois leviers qui ne dépendent pas de la photo :
     typographie à grande échelle, motion du perroquet, plumes animées.

     Charte respectée à la lettre : 5 couleurs + 3 neutres, Poppins pour
     les titres, Nunito pour le corps. Aucune couleur ajoutée.
════════════════════════════════════════════════════════════════════ --}}
<!DOCTYPE html>
<html lang="fr" class="v2">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- Maquette : on ne veut pas qu'elle soit indexée à la place du site. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $titre ?? 'CIBLE' }} — CIBLE · Vous visez juste</title>
    <link rel="icon" type="image/png" href="{{ asset('images/icone-32.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
    /* ═══════════════ 1. JETONS — charte CIBLE, verrouillée ═══════════════ */
    :root{
      /* ── Couleurs de la charte (docs/CHARTE-GRAPHIQUE.md) ──
         Rouge et jaune sont les PRINCIPALES. Vert, bleu et violet sont
         secondaires : ponctuels, jamais à parts égales avec les deux
         premières. Citation : « La couleur rouge doit rester prépondérante
         sur toutes les applications et peut être principalement accompagnée
         avec le jaune. » */
      --rouge:#E20613; --jaune:#FAB80B;
      --vert:#3AA835;  --bleu:#3F7FC0;  --violet:#81358A;
      --gris:#E6E6E6;  --noir:#111111;  --blanc:#FFFFFF;

      /* ── Fonds ──
         « En cas de doute, privilégier le blanc comme base neutre et
         structurante. » Le site est donc blanc, et non noir comme la
         première version de cette maquette. --gris-2 est une étape du
         dégradé gris → blanc, la seule variation que la charte autorise. */
      --fond:#FFFFFF;
      --fond-2:#F5F5F3;
      --fond-3:var(--gris);
      --ligne:rgba(17,17,17,.13);
      --texte:#111111;
      --texte-2:rgba(17,17,17,.70);
      --texte-3:rgba(17,17,17,.48);

      /* Aplat noir, réservé aux rares sections en inversion. Le texte y
         passe en blanc, jamais en couleur de marque : « N'appliquez pas le
         texte du titre en couleur à un arrière-plan non blanc. » */
      --encre:#111111;

      /* ⚠ MADE TOMMY est la police de titre de la charte. Police
         commerciale, absente de Google Fonts : les fichiers .woff2 sont à
         fournir. Poppins n'est ici qu'un REMPLAÇANT documenté, choisi pour
         sa parenté géométrique. Dès réception, déclarer un @font-face et
         remplacer la valeur ci-dessous — rien d'autre à changer. */
      --titre:'Poppins',system-ui,sans-serif;
      --corps:'Nunito',system-ui,sans-serif;
      /* Les chiffres ne se composent JAMAIS en Nunito (règle explicite de la
         charte) : ils prennent la police de titre. */
      --chiffres:var(--titre);

      --pad:clamp(20px,5vw,84px);
      --ease:cubic-bezier(.16,1,.3,1);
    }

    /* ═══════════════ 2. SOCLE ═══════════════ */
    *{box-sizing:border-box;margin:0;padding:0}
    [hidden]{display:none!important}
    html{-webkit-text-size-adjust:100%}
    body{
      background:var(--fond);color:var(--texte);
      font-family:var(--corps);font-size:17px;line-height:1.6;
      overflow-x:hidden;
      -webkit-font-smoothing:antialiased;
    }
    img,video{display:block;max-width:100%}
    a{color:inherit;text-decoration:none}
    ::selection{background:var(--rouge);color:#fff}
    :focus-visible{outline:3px solid var(--jaune);outline-offset:4px;border-radius:2px}

    /* Lenis : le scroll inertiel demande que html/body ne soient pas en
       hauteur fixe, sinon la piste de scroll disparaît. */
    html.lenis,html.lenis body{height:auto}
    .lenis.lenis-smooth{scroll-behavior:auto!important}

    /* ═══════════════ 3. TYPOGRAPHIE ═══════════════
       ⚠ Échelle revue à la baisse le 2026-10-08, après capture du client.
       Elle montait à 148 px : dans une colonne de héro large d'environ
       700 px, « Nous ne vendons pas de l'espace » tombait à un ou deux mots
       par ligne, sur huit lignes. Le titre n'était plus un titre mais une
       pile de mots.

       La règle qui tient : un titre doit pouvoir poser au moins quatre ou
       cinq mots par ligne dans sa colonne. 78 px au maximum sur un titre
       pleine largeur, 56 px sur un titre de section. Même leçon que la V1
       en août, où les tailles avaient déjà dû être réduites deux fois. */
    .t-geant{
      font-family:var(--titre);font-weight:900;
      font-size:clamp(32px,4.9vw,74px);
      line-height:1;letter-spacing:-.035em;
      text-wrap:balance;
    }
    .t-grand{
      font-family:var(--titre);font-weight:900;
      font-size:clamp(26px,3.8vw,56px);
      line-height:1.04;letter-spacing:-.028em;
      text-wrap:balance;
    }
    .t-moyen{
      font-family:var(--titre);font-weight:800;
      font-size:clamp(20px,2.2vw,30px);
      line-height:1.16;letter-spacing:-.018em;
    }
    .t-petit{
      font-family:var(--titre);font-weight:800;
      font-size:clamp(17px,1.5vw,21px);
      line-height:1.25;letter-spacing:-.01em;
    }
    .sur{
      font-family:var(--titre);font-weight:700;
      font-size:12px;letter-spacing:.2em;text-transform:uppercase;
      color:var(--texte-3);
    }
    .sur b{color:var(--c,var(--rouge));font-weight:700}
    .intro{
      font-size:clamp(17px,1.5vw,21px);line-height:1.6;
      color:var(--texte-2);max-width:62ch;
    }
    .corps{color:var(--texte-2);max-width:60ch}
    .num{font-family:var(--chiffres);font-variant-numeric:tabular-nums}
    /* Titre lu par les lecteurs d'écran mais absent à l'œil : certaines
       sections sont délibérément sans intertitre visible, elles ont
       quand même besoin d'un nom dans le plan du document. */
    .hors-ecran{
      position:absolute;width:1px;height:1px;overflow:hidden;
      clip-path:inset(50%);white-space:nowrap;
    }

    /* ═══════════════ 4. MISE EN PAGE ═══════════════ */
    .bloc{padding:clamp(72px,11vw,170px) var(--pad);position:relative}
    .bloc--serre{padding-block:clamp(54px,7vw,100px)}
    .large{max-width:1480px;margin-inline:auto}
    .entete{max-width:960px}
    .entete .t-grand{margin-top:18px}
    .entete .intro{margin-top:22px}

    /* ═══════════════ 5. BOUTONS ═══════════════ */
    .bt{
      display:inline-flex;align-items:center;gap:12px;
      font-family:var(--titre);font-weight:800;font-size:15px;
      padding:17px 30px;border-radius:999px;border:0;cursor:pointer;
      background:var(--rouge);color:#fff;
      transition:transform .4s var(--ease),background .3s;
      will-change:transform;
    }
    .bt:hover{transform:translateY(-3px);background:#B00510}
    .bt--creux{background:transparent;color:var(--noir);box-shadow:inset 0 0 0 1.5px rgba(17,17,17,.28)}
    .bt--creux:hover{background:rgba(17,17,17,.05);box-shadow:inset 0 0 0 1.5px var(--noir)}
    .bt--clair{background:var(--noir);color:#fff}
    .bt--clair:hover{background:var(--rouge)}
    .bt .fl{
      width:18px;height:18px;flex:0 0 auto;
      background:currentColor;
      -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
      mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
      transition:transform .4s var(--ease);
    }
    .bt:hover .fl{transform:translateX(5px)}

    /* ═══════════════ 6. CURSEUR VISEUR ═══════════════
       CIBLE veut dire cible, et la signature est « Vous visez juste ».
       Le curseur devient donc un viseur qui s'accroche aux éléments
       interactifs. Desktop à pointeur fin uniquement : sur tactile il
       n'y a pas de curseur à remplacer, et à la souris grossière
       (télé, trackpad bas de gamme) l'accrochage devient imprécis. */
    .viseur,.viseur-pt{
      position:fixed;top:0;left:0;z-index:9000;
      pointer-events:none;opacity:0;
      transform:translate(-50%,-50%);
    }
    .viseur{
      width:42px;height:42px;border-radius:50%;
      box-shadow:inset 0 0 0 1.5px rgba(17,17,17,.5);
      transition:width .35s var(--ease),height .35s var(--ease),
                 box-shadow .35s,opacity .3s,background .35s;
    }
    /* Les 4 traits du réticule */
    .viseur i{position:absolute;background:var(--rouge);transition:all .35s var(--ease)}
    .viseur i:nth-child(1),.viseur i:nth-child(2){width:1.5px;height:7px;left:calc(50% - .75px)}
    .viseur i:nth-child(1){top:-11px}
    .viseur i:nth-child(2){bottom:-11px}
    .viseur i:nth-child(3),.viseur i:nth-child(4){height:1.5px;width:7px;top:calc(50% - .75px)}
    .viseur i:nth-child(3){left:-11px}
    .viseur i:nth-child(4){right:-11px}
    .viseur-pt{width:5px;height:5px;border-radius:50%;background:var(--rouge)}
    /* Accroché sur une cible : le viseur s'ouvre et se remplit */
    body.accroche .viseur{
      width:76px;height:76px;
      background:rgba(226,6,19,.14);
      box-shadow:inset 0 0 0 1.5px var(--rouge);
    }
    body.accroche .viseur i{opacity:0}
    body.accroche .viseur-pt{opacity:0!important}
    @media (hover:none),(pointer:coarse){.viseur,.viseur-pt{display:none}}

    /* ═══════════════ 7. NAVIGATION ═══════════════ */
    .nav{
      position:fixed;inset:0 0 auto 0;z-index:200;
      display:flex;align-items:center;gap:22px;
      padding:18px var(--pad);
      transition:background .4s,backdrop-filter .4s,padding .4s;
    }
    .nav.pose{
      background:rgba(255,255,255,.88);backdrop-filter:blur(14px);
      padding-block:13px;border-bottom:1px solid var(--ligne);
    }
    /* Logo fixe : le logotype porte le slogan « Vous visez juste », il
       doit donc rester assez grand pour être lu. La charte fixe 24 px comme
       plancher absolu en digital — on prend de la marge.

       ⚠ Le motion a été essayé ici et retiré : son logotype n'occupe qu'une
       fraction du cadre 16/9, donc réduit à la hauteur d'une barre il
       devenait illisible, et le cadre blanc se voyait comme une boîte. Un
       logo est lisible avant d'être animé. */
    .nav__logo{display:block;line-height:0}
    /* 2026-10-09 — Plus de fond blanc : le PNG est transparent, c'est ce
       fond qui formait une boîte blanche sur le héro sombre. Deux versions
       du logo : texte noir (barre claire) et texte blanc (barre sombre). */
    .nav__logo video,.nav__logo img{
      height:52px;width:auto;display:block;
    }
    .nav__logo .logo-sombre{display:none}
    /* Barre « sur fond sombre » (page qui la demande, ex. accueil) tant
       qu'on n'a pas défilé : aplat noir, logo blanc, liens blancs. Sans
       cet aplat, les liens se perdaient dans les photos du héro. */
    .nav.sur-sombre:not(.pose){background:var(--noir)}
    .nav.sur-sombre:not(.pose) .logo-clair{display:none}
    .nav.sur-sombre:not(.pose) .logo-sombre{display:block}
    .nav.sur-sombre:not(.pose) .nav__liens a{color:rgba(255,255,255,.82)}
    .nav.sur-sombre:not(.pose) .nav__liens a:hover,
    .nav.sur-sombre:not(.pose) .nav__liens a[aria-current="page"]{color:var(--blanc);background:rgba(255,255,255,.1)}
    .nav.sur-sombre:not(.pose) .burger{border-color:rgba(255,255,255,.3);background:rgba(255,255,255,.06)}
    .nav.sur-sombre:not(.pose) .burger span{background:var(--blanc)}
    .nav.pose .nav__logo video,.nav.pose .nav__logo img{height:44px}
    .nav__logo video,.nav__logo img{transition:height .4s var(--ease)}
    @media(max-width:600px){
      .nav__logo video,.nav__logo img{height:40px}
      .nav.pose .nav__logo video,.nav.pose .nav__logo img{height:34px}
    }
    /* Navigation en capitales et sans bouton d'appel : la référence citée
       par le client (McCann) tient sa barre en 4 entrées capitalisées et
       n'y place aucun CTA — ça sonne moins « site qui vend ». L'appel reste
       présent en fin de chaque page, là où il a du sens. */
    .nav__liens{display:flex;gap:2px;margin-left:auto}
    .nav__liens a{
      font-family:var(--titre);font-weight:700;
      font-size:12px;letter-spacing:.13em;text-transform:uppercase;
      padding:10px 16px;border-radius:999px;color:var(--texte-2);
      transition:color .25s,background .25s;
    }
    .nav__liens a:hover{color:var(--noir);background:rgba(17,17,17,.055)}
    .nav__liens a[aria-current="page"]{color:var(--noir)}
    .nav__liens a[aria-current="page"]::after{
      content:"";display:block;height:2px;margin-top:5px;
      background:var(--rouge);border-radius:2px;
    }
    .nav .bt{padding:13px 22px;font-size:14px}
    /* Jauge de progression de lecture */
    .nav__jauge{
      position:absolute;bottom:0;left:0;height:2px;width:100%;
      transform:scaleX(0);transform-origin:0 50%;
      /* Aplat rouge, pas un dégradé rouge → jaune : la charte proscrit les
         dégradés, et le rouge doit rester prépondérant. */
      background:var(--rouge);
    }
    .burger{
      margin-left:auto;width:44px;height:44px;display:none;
      border:1.5px solid var(--ligne);border-radius:12px;
      background:rgba(17,17,17,.03);cursor:pointer;
      flex-direction:column;gap:5px;align-items:center;justify-content:center;
    }
    .burger span{width:18px;height:1.5px;background:var(--noir);transition:transform .3s,opacity .3s}
    body.menu .burger span:nth-child(1){transform:translateY(6.5px) rotate(45deg)}
    body.menu .burger span:nth-child(2){opacity:0}
    body.menu .burger span:nth-child(3){transform:translateY(-6.5px) rotate(-45deg)}
    @media(max-width:860px){
      .nav__liens{display:none}
      .burger{display:flex}
    }
    /* Tiroir plein écran */
    .tiroir{
      position:fixed;inset:0;z-index:190;background:var(--fond);
      display:flex;flex-direction:column;justify-content:center;
      padding:0 var(--pad);gap:6px;
      clip-path:inset(0 0 100% 0);
      transition:clip-path .7s var(--ease);
    }
    body.menu .tiroir{clip-path:inset(0 0 0 0)}
    .tiroir a{
      font-family:var(--titre);font-weight:900;
      font-size:clamp(30px,8vw,66px);letter-spacing:-.03em;
      padding:7px 0;border-bottom:1px solid var(--ligne);
      display:flex;align-items:baseline;gap:16px;
    }
    .tiroir a span{font-size:13px;font-weight:700;color:var(--texte-3);letter-spacing:.1em}
    .tiroir a:hover{color:var(--rouge)}

    /* ═══════════════ 8. DÉCOR — plumes de marque ═══════════════
       La plume est recolorée par mask-image : un seul SVG sert les
       5 couleurs de la charte, comme en V1. */
    .plume{
      position:absolute;pointer-events:none;z-index:0;
      background:var(--c,var(--rouge));opacity:var(--op,.07);
      -webkit-mask:url('{{ asset('images/plume.svg') }}') no-repeat center/contain;
      mask:url('{{ asset('images/plume.svg') }}') no-repeat center/contain;
      /* Ratio exact du viewBox de plume.svg (758,4 × 1031,77). Il était
         fixé à 1/1.1 au jugé : `contain` protégeait la forme de toute
         déformation, mais la boîte portait du vide invisible et la largeur
         déclarée ne donnait pas la taille visuelle attendue. La charte dit
         du symbole qu'« il ne doit être modifié en aucun cas » — autant
         que la boîte colle à la forme. */
      aspect-ratio:758.4/1031.77;
      will-change:transform;
    }
    .fleche-d{
      position:absolute;pointer-events:none;z-index:0;
      background:var(--c,var(--jaune));opacity:var(--op,.1);
      -webkit-mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
      mask:url('{{ asset('images/fleche.svg') }}') no-repeat center/contain;
      /* Ratio exact du viewBox de fleche.svg (282,95 × 195,83) : la flèche
         est nettement horizontale, elle était enfermée dans un carré. */
      aspect-ratio:282.95/195.83;
      will-change:transform;
    }

    /* ═══════════════ 9. PHOTO — bichromie révélée ═══════════════
       Demandé par le client le 2026-10-08 : « j'aime le style de cette
       section, remets-la ».

       Elle avait été retirée au nom de la charte. En la relisant, elle est
       en réalité conforme : la photo est d'abord désaturée en noir et
       blanc, puis teintée d'UNE seule couleur de marque. C'est donc « une
       couleur combinée avec du noir », ce que la charte autorise
       explicitement — et « une couleur dominante par visuel », ce qu'elle
       recommande. Ce qu'elle proscrit est le DÉGRADÉ entre deux couleurs,
       et il n'y en a pas ici.

       La photo revient en couleur quand on la regarde : le traitement dit
       « design », la révélation dit « c'est réel ». */
    .ph{position:relative;overflow:hidden;background:var(--fond-3)}
    .ph img{
      width:100%;height:100%;object-fit:cover;
      filter:grayscale(1) contrast(1.05);
      transition:filter .8s var(--ease),transform 1.1s var(--ease);
      will-change:transform,filter;
    }
    .ph::after{
      content:"";position:absolute;inset:0;z-index:1;pointer-events:none;
      background:var(--c,var(--rouge));mix-blend-mode:color;
      opacity:.82;transition:opacity .8s var(--ease);
    }
    /* .ph--vive est posée par le script quand la photo entre dans le
       champ ; le survol produit le même effet à la souris. */
    .ph--vive::after,.ph:hover::after{opacity:0}
    .ph--vive img,.ph:hover img{filter:none}
    .ph:hover img{transform:scale(1.03)}
    /* .ph--nue : une photo qui ne doit jamais être teintée (le perroquet
       du manifeste, par exemple — c'est le sujet, pas une vignette). */
    .ph--nue::after{display:none}
    .ph--nue img{filter:none}
    /* Légende : aplat blanc franc, pas un voile dégradé. */
    .ph__legende{
      position:absolute;z-index:2;inset:auto 12px 12px 12px;
      padding:11px 14px;border-radius:8px;
      font-family:var(--titre);font-weight:700;font-size:12.5px;
      background:var(--blanc);color:var(--noir);
      box-shadow:0 2px 10px rgba(17,17,17,.12);
    }

    /* ═══════════════ 10. ANIMATIONS D'ENTRÉE ═══════════════ */
    [data-rev]{opacity:0;transform:translateY(26px)}
    .ligne-masque{overflow:hidden;display:block}
    /* Lignes de titre posées à la main, révélées par [data-cascade] sans
       masque : une ligne qui déborde n'est donc jamais tronquée. */
    [data-cascade] > .l{display:block}
    .ligne-masque > span{display:block;will-change:transform}
    @media (prefers-reduced-motion:reduce){
      [data-rev]{opacity:1!important;transform:none!important}
      .ligne-masque > span{transform:none!important}
      *{animation-duration:.01ms!important;transition-duration:.01ms!important}
    }

    /* ═══════════════ 11. BANDEAU DÉFILANT ═══════════════ */
    .ruban{
      overflow:hidden;border-block:1px solid var(--ligne);
      padding:19px 0;background:var(--fond-2);
    }
    /* ⚠ Pas de `gap` sur la piste : l'animation translateX(-50%) suppose que
       la seconde moitié soit la copie exacte de la première. Un gap entre
       les deux moitiés ajoute un écart qui n'appartient à aucune des deux,
       et la boucle saute visiblement à chaque tour. L'écart est donc porté
       par un padding-right de chaque moitié, qui fait partie du motif. */
    .ruban__piste{display:flex;width:max-content;animation:defile 34s linear infinite}
    .ruban__piste > *{
      display:flex;align-items:center;gap:46px;flex:0 0 auto;padding-right:46px;
      font-family:var(--titre);font-weight:800;font-size:clamp(15px,1.5vw,19px);
      letter-spacing:-.01em;white-space:nowrap;
    }
    .ruban__piste em{font-style:normal;color:var(--c,var(--jaune))}
    .ruban__pt{width:7px;height:7px;border-radius:50%;background:var(--rouge);flex:0 0 auto}
    @keyframes defile{to{transform:translateX(-50%)}}
    @media (prefers-reduced-motion:reduce){.ruban__piste{animation:none}}

    /* ═══════════════ 12. PIED DE PAGE ═══════════════ */
    .pied{background:var(--fond-2);border-top:1px solid var(--ligne);padding:clamp(56px,7vw,96px) var(--pad) 34px}
    .pied__haut{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:clamp(28px,4vw,60px)}
    @media(max-width:880px){.pied__haut{grid-template-columns:1fr 1fr}}
    @media(max-width:560px){.pied__haut{grid-template-columns:1fr}}
    .pied h4{font-family:var(--titre);font-weight:800;font-size:12px;letter-spacing:.16em;
      text-transform:uppercase;color:var(--texte-3);margin-bottom:15px}
    .pied ul{list-style:none;display:grid;gap:9px;font-size:15px;color:var(--texte-2)}
    .pied a:hover{color:var(--rouge)}
    .pied__bas{
      display:flex;flex-wrap:wrap;gap:12px 26px;align-items:center;
      margin-top:clamp(36px,5vw,64px);padding-top:22px;
      border-top:1px solid var(--ligne);font-size:13.5px;color:var(--texte-3);
    }
    .pied__logo img{height:58px;width:auto;margin-bottom:18px}

    /* ═══════════════ 13. BANDEAU MAQUETTE ═══════════════ */
    .avis{
      position:fixed;z-index:9500;inset:auto auto 16px 16px;
      display:flex;align-items:center;gap:11px;
      padding:11px 15px;border-radius:999px;
      background:var(--noir);
      
      font-family:var(--titre);font-weight:700;font-size:12px;color:var(--jaune);
    }
    .avis b{
      width:7px;height:7px;border-radius:50%;background:var(--jaune);
      animation:bat 2.2s ease-in-out infinite;
    }
    @keyframes bat{50%{opacity:.25}}
    .avis button{
      border:0;background:none;color:var(--jaune);cursor:pointer;
      font-size:15px;line-height:1;padding:0 0 0 4px;opacity:.6;
    }
    .avis button:hover{opacity:1}
    @media(max-width:700px){.avis{display:none}}

    /* ═══════════════ CSS propre à la page ═══════════════
       Empilé ICI, avant la fermeture de la feuille : les pages poussent des
       règles brutes, pas des balises. Empilé après, le CSS s'afficherait en
       texte dans la page.

       ⚠ Ne JAMAIS écrire la balise de fermeture de style dans ce
       commentaire. Le contenu d'un élément style est du texte brut : il
       s'arrête à la première occurrence de cette balise, commentaire CSS
       compris. Une version de ce fichier coupait ainsi la feuille en deux
       et tout le CSS des pages se retrouvait hors style. Même famille que
       le composant Blade cité dans un commentaire, cf. CLAUDE.md. */
    @stack('css')
    </style>
    @stack('head')
</head>
<body>

{{-- Viseur : deux éléments séparés pour que le point suive la souris plus
     vite que l'anneau — c'est ce décalage qui donne la sensation de visée. --}}
<div class="viseur" id="viseur" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
<div class="viseur-pt" id="viseur-pt" aria-hidden="true"></div>

@php
    // Travaux en tête : on montre avant d'expliquer.
    $pages = [
        'travaux'    => ['Travaux',    route('cible.travaux')],
        'expertises' => ['Expertises', route('cible.expertises')],
        'reseau'     => ['Réseau',     route('cible.reseau')],
        'contact'    => ['Contact',    route('cible.contact')],
    ];
    $actuelle = $actuelle ?? 'accueil';
@endphp

<header class="nav{{ !empty($navSombre) ? ' sur-sombre' : '' }}" id="nav">
    <a class="nav__logo" href="{{ route('cible.home') }}" data-viseur aria-label="CIBLE — accueil">
        <img class="logo-clair" src="{{ \App\Support\Contenu::urlDatee('images/logol.png') }}" alt="CIBLE">
        <img class="logo-sombre" src="{{ \App\Support\Contenu::urlDatee('images/logon.png') }}" alt="CIBLE">
    </a>
    <nav class="nav__liens">
        @foreach($pages as $cle => [$nom, $url])
            <a href="{{ $url }}" data-viseur @if($actuelle === $cle) aria-current="page" @endif>{{ $nom }}</a>
        @endforeach
    </nav>
    <button class="burger" id="burger" type="button" aria-label="Menu" aria-expanded="false">
        <span></span><span></span><span></span>
    </button>
    <div class="nav__jauge" id="jauge"></div>
</header>

<div class="tiroir" id="tiroir">
    <a href="{{ route('cible.home') }}"><span>01</span>Accueil</a>
    @foreach($pages as [$nom, $url])
        <a href="{{ $url }}"><span>0{{ $loop->iteration + 1 }}</span>{{ $nom }}</a>
    @endforeach
</div>

<main id="principal">
    @yield('contenu')
</main>

<footer class="pied">
    <div class="large">
        <div class="pied__haut">
            <div>
                <div class="pied__logo"><img src="{{ asset('images/logol.png') }}" alt="CIBLE"></div>
                <p class="corps" style="max-width:34ch;font-size:15px">
                    Régie publicitaire ivoirienne depuis 1994. Nous possédons la rue :
                    +400 panneaux dans 31 communes.
                </p>
            </div>
            <div>
                <h4>Le site</h4>
                <ul>
                    <li><a href="{{ route('cible.home') }}" data-viseur>Accueil</a></li>
                    @foreach($pages as [$nom, $url])
                        <li><a href="{{ $url }}" data-viseur>{{ $nom }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4>Nous joindre</h4>
                <ul>
                    <li><a href="tel:+2250700780628" data-viseur class="num">+225 07 00 78 06 28</a></li>
                    <li><a href="tel:+2252722208008" data-viseur class="num">+225 27 22 20 80 08</a></li>
                    <li><a href="mailto:commercial@cible-ci.com" data-viseur>commercial@cible-ci.com</a></li>
                    <li style="color:var(--texte-3);margin-top:4px">
                        Rue des Ambassadeurs<br>Riviera M'Badon · 10 BP 1029<br>Abidjan, Côte d'Ivoire
                    </li>
                </ul>
            </div>
        </div>
        <div class="pied__bas">
            <span>© {{ date('Y') }} CIBLE</span>
            <span style="color:var(--jaune);font-family:var(--titre);font-weight:800">Vous visez juste</span>
            <span style="margin-left:auto">Maquette de refonte · le site en ligne reste www.cible-ci.com</span>
        </div>
    </div>
</footer>

<div class="avis" id="avis">
    <b></b> Maquette de refonte — le site actuel reste en ligne
    <button type="button" aria-label="Masquer">✕</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/lenis@1.1.18/dist/lenis.min.js"></script>
<script>
/* ═══════════════════════════════════════════════════════════════════
   Moteur d'animation de la refonte.

   Tout est conditionné à la présence de GSAP : si le CDN tombe, la page
   reste lisible — les éléments masqués par [data-rev] sont révélés par
   le repli en fin de fichier. Une maquette qui s'affiche vide parce
   qu'un CDN est lent n'est pas montrable à un client.
═══════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var doux = !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var gsapOk = typeof window.gsap !== 'undefined';

  /* ── 1. Scroll inertiel ─────────────────────────────────────────── */
  var lenis = null;
  if (doux && typeof window.Lenis !== 'undefined') {
    lenis = new Lenis({ duration: 1.1, smoothWheel: true });
    if (gsapOk) {
      lenis.on('scroll', ScrollTrigger.update);
      gsap.ticker.add(function (t) { lenis.raf(t * 1000); });
      gsap.ticker.lagSmoothing(0);
    } else {
      (function boucle(t) { lenis.raf(t); requestAnimationFrame(boucle); })(0);
    }
  }

  /* ── 2. Curseur viseur ──────────────────────────────────────────── */
  var fin = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  if (fin && gsapOk && doux) {
    var anneau = document.getElementById('viseur');
    var point  = document.getElementById('viseur-pt');
    var xA = gsap.quickTo(anneau, 'x', { duration: .5, ease: 'power3' });
    var yA = gsap.quickTo(anneau, 'y', { duration: .5, ease: 'power3' });
    var xP = gsap.quickTo(point, 'x', { duration: .12, ease: 'power2' });
    var yP = gsap.quickTo(point, 'y', { duration: .12, ease: 'power2' });

    window.addEventListener('mousemove', function (e) {
      xA(e.clientX); yA(e.clientY); xP(e.clientX); yP(e.clientY);
      gsap.to([anneau, point], { opacity: 1, duration: .3, overwrite: 'auto' });
    }, { passive: true });

    document.addEventListener('mouseleave', function () {
      gsap.to([anneau, point], { opacity: 0, duration: .3 });
    });

    // Accrochage : délégation, pour couvrir aussi ce qui est ajouté après.
    document.addEventListener('mouseover', function (e) {
      if (e.target.closest('[data-viseur], a, button')) {
        document.body.classList.add('accroche');
      }
    });
    document.addEventListener('mouseout', function (e) {
      if (e.target.closest('[data-viseur], a, button')) {
        document.body.classList.remove('accroche');
      }
    });
  }

  /* ── 3. Navigation : pose au scroll + jauge de lecture ──────────── */
  var nav = document.getElementById('nav');
  var jauge = document.getElementById('jauge');
  function auScroll() {
    var y = window.scrollY || document.documentElement.scrollTop;
    nav.classList.toggle('pose', y > 40);
    var h = document.documentElement.scrollHeight - window.innerHeight;
    if (jauge) { jauge.style.transform = 'scaleX(' + (h > 0 ? Math.min(y / h, 1) : 0) + ')'; }
  }
  window.addEventListener('scroll', auScroll, { passive: true });
  auScroll();

  /* ── 4. Tiroir mobile ───────────────────────────────────────────── */
  var burger = document.getElementById('burger');
  if (burger) {
    burger.addEventListener('click', function () {
      var ouvert = document.body.classList.toggle('menu');
      burger.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      if (lenis) { ouvert ? lenis.stop() : lenis.start(); }
    });
    document.getElementById('tiroir').addEventListener('click', function (e) {
      if (e.target.tagName === 'A') {
        document.body.classList.remove('menu');
        if (lenis) { lenis.start(); }
      }
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && document.body.classList.contains('menu')) {
      document.body.classList.remove('menu');
      burger.setAttribute('aria-expanded', 'false');
      if (lenis) { lenis.start(); }
    }
  });

  /* ── 5. Bandeau maquette ────────────────────────────────────────── */
  var avis = document.getElementById('avis');
  if (avis) {
    avis.querySelector('button').addEventListener('click', function () { avis.remove(); });
  }

  if (!gsapOk) { return; }
  gsap.registerPlugin(ScrollTrigger);

  /* ── 6. Titres : découpe en lignes, chaque ligne monte sous masque ──
     On n'utilise pas SplitText (plugin payant) : on enveloppe chaque mot,
     on relève sa position verticale, et on regroupe les mots qui
     partagent la même ligne. Recalculé au redimensionnement, sinon la
     découpe faite en desktop reste fausse après rotation d'un mobile.

     ⚠ La découpe reconstruit le HTML à partir du TEXTE seul : tout
     balisage interne est détruit. Cinq titres portaient un <em> coloré
     qui disparaissait ainsi en silence — visible sur la capture client du
     2026-10-08, où le mot censé ressortir en jaune s'affichait en noir.

     La fonction refuse donc désormais de toucher un titre qui contient des
     éléments, et la boucle ci-dessous lui applique une révélation d'un
     seul bloc. Un développeur qui ajoute un <em> à un titre [data-lignes]
     perd l'effet par ligne, jamais sa couleur. */
  /* Le verdict est pris UNE FOIS, avant toute découpe, et mémorisé : après
     un découpage réussi l'élément porte des enfants, donc un test fait
     après coup le croirait à tort porteur de balisage et refuserait de le
     recalculer au redimensionnement. */
  function estDecoupable(el) {
    if (el.dataset.brut === undefined) {
      el.dataset.brut = el.children.length === 0 ? '1' : '0';
    }
    return el.dataset.brut === '1';
  }

  function decouper(el) {
    if (!estDecoupable(el)) { return false; }
    if (el.dataset.decoupe === '1') { return false; }

    var brut = el.getAttribute('data-texte') || el.textContent;
    el.setAttribute('data-texte', brut);

    el.innerHTML = brut.trim().split(/\s+/).map(function (m) {
      return '<span class="mot" style="display:inline-block">' + m + '</span>';
    }).join(' ');

    var lignes = [], courante = null, haut = null;
    el.querySelectorAll('.mot').forEach(function (m) {
      var y = Math.round(m.getBoundingClientRect().top);
      if (haut === null || Math.abs(y - haut) > 4) { courante = []; lignes.push(courante); haut = y; }
      courante.push(m.outerHTML);
    });

    el.innerHTML = lignes.map(function (l) {
      return '<span class="ligne-masque"><span>' + l.join(' ') + '</span></span>';
    }).join('');
    el.dataset.decoupe = '1';
    return true;
  }

  var titres = document.querySelectorAll('[data-lignes]');
  titres.forEach(function (el) {
    var decoupable = decouper(el);

    // Titre porteur de balisage : révélation d'un seul bloc, l'emphase
    // colorée est préservée.
    if (!decoupable && !estDecoupable(el)) {
      gsap.set(el, { opacity: 0, y: 24 });
      ScrollTrigger.create({
        trigger: el, start: 'top 90%', once: true,
        onEnter: function () {
          gsap.to(el, { opacity: 1, y: 0, duration: .95, ease: 'expo.out' });
        },
      });
      return;
    }

    var lignes = el.querySelectorAll('.ligne-masque > span');
    gsap.set(lignes, { yPercent: 115 });
    ScrollTrigger.create({
      trigger: el,
      start: 'top 88%',
      once: true,
      onEnter: function () {
        gsap.to(lignes, {
          yPercent: 0, duration: 1.05, ease: 'expo.out', stagger: .08,
        });
      },
    });
  });

  /* ── 7. Révélations génériques ──────────────────────────────────── */
  document.querySelectorAll('[data-rev]').forEach(function (el) {
    gsap.to(el, {
      opacity: 1, y: 0, duration: .95, ease: 'expo.out',
      delay: parseFloat(el.dataset.rev) || 0,
      scrollTrigger: { trigger: el, start: 'top 90%', once: true },
    });
  });

  /* ── 8. Groupes en cascade ──────────────────────────────────────── */
  document.querySelectorAll('[data-cascade]').forEach(function (groupe) {
    var enfants = groupe.children;
    gsap.set(enfants, { opacity: 0, y: 30 });
    ScrollTrigger.create({
      trigger: groupe, start: 'top 86%', once: true,
      onEnter: function () {
        gsap.to(enfants, { opacity: 1, y: 0, duration: .9, ease: 'expo.out', stagger: .09 });
      },
    });
  });

  /* ── 9. Compteurs ───────────────────────────────────────────────── */
  document.querySelectorAll('[data-compte]').forEach(function (el) {
    var cible = parseFloat(el.dataset.compte);
    // data-pad : nombre minimal de chiffres, complete par des zeros.
    var pad = parseInt(el.dataset.pad || 0, 10);
    var ecrire = function (v) {
      var s = String(Math.round(v));
      while (s.length < pad) { s = '0' + s; }
      el.textContent = pad ? s : Math.round(v).toLocaleString('fr-FR');
    };
    var etat = { v: 0 };
    ScrollTrigger.create({
      trigger: el, start: 'top 92%', once: true,
      onEnter: function () {
        gsap.to(etat, {
          v: cible, duration: 1.9, ease: 'power2.out',
          onUpdate: function () { ecrire(etat.v); },
        });
      },
    });
  });

  /* ── 10. Parallaxe du décor ─────────────────────────────────────── */
  document.querySelectorAll('.plume,.fleche-d').forEach(function (f) {
    gsap.to(f, {
      yPercent: parseFloat(f.dataset.par || -22),
      rotation: parseFloat(f.dataset.rot || 0),
      ease: 'none',
      scrollTrigger: { trigger: f.closest('section, div'), scrub: true, start: 'top bottom', end: 'bottom top' },
    });
  });

  /* ── 10 bis. Bichromie : la photo reprend ses couleurs à l'approche ──
     Sur toute vignette portant .ph--scroll. Au survol, le CSS fait la
     même chose : les deux voies coexistent sans se gêner. */
  document.querySelectorAll('.ph--scroll').forEach(function (p) {
    ScrollTrigger.create({
      trigger: p, start: 'top 78%', end: 'bottom 22%',
      onEnter:     function () { p.classList.add('ph--vive'); },
      onLeave:     function () { p.classList.remove('ph--vive'); },
      onEnterBack: function () { p.classList.add('ph--vive'); },
      onLeaveBack: function () { p.classList.remove('ph--vive'); },
    });
  });

  /* ── 11. Titres en lignes masquées écrites à la main ─────────────
     Certains titres portent du balisage à préserver (un mot en couleur),
     que le découpeur automatique écraserait : il reconstruit le HTML à
     partir du texte seul. Ces titres déclarent donc leurs lignes dans la
     vue et portent [data-monte].

     On les anime en yPercent, jamais avec [data-rev] : [data-rev] décale
     de 26 px en pixels, et à l'intérieur d'un masque en overflow:hidden le
     texte apparaissait tronqué. C'était l'un des défauts d'affichage de la
     page d'accueil. */
  document.querySelectorAll('[data-monte]').forEach(function (el) {
    var lignes = el.querySelectorAll('.ligne-masque > span');
    if (!lignes.length) { return; }
    gsap.set(lignes, { yPercent: 115 });
    ScrollTrigger.create({
      trigger: el, start: 'top 92%', once: true,
      onEnter: function () {
        gsap.to(lignes, { yPercent: 0, duration: 1.05, ease: 'expo.out', stagger: .08 });
      },
    });
  });

  /* ── 12. Redécoupe des titres au redimensionnement ──────────────── */
  var minuteur;
  window.addEventListener('resize', function () {
    clearTimeout(minuteur);
    minuteur = setTimeout(function () {
      titres.forEach(function (el) {
        if (!estDecoupable(el)) { return; }
        el.dataset.decoupe = '0';
        decouper(el);
        gsap.set(el.querySelectorAll('.ligne-masque > span'), { yPercent: 0 });
      });
      ScrollTrigger.refresh();
    }, 260);
  });

  window.addEventListener('load', function () { ScrollTrigger.refresh(); });
})();

/* Repli : si GSAP n'a pas chargé, on rend visible tout ce que le CSS
   avait masqué en prévision de l'animation. */
window.addEventListener('load', function () {
  if (typeof window.gsap === 'undefined') {
    document.querySelectorAll('[data-rev]').forEach(function (el) {
      el.style.opacity = 1; el.style.transform = 'none';
    });
    document.querySelectorAll('[data-cascade] > *').forEach(function (el) {
      el.style.opacity = 1; el.style.transform = 'none';
    });
    document.querySelectorAll('[data-compte]').forEach(function (el) {
      var p = parseInt(el.dataset.pad || 0, 10);
      var s = String(Math.round(parseFloat(el.dataset.compte)));
      while (s.length < p) { s = '0' + s; }
      el.textContent = p ? s : parseFloat(el.dataset.compte).toLocaleString('fr-FR');
    });
  }
});
</script>
@stack('js')
</body>
</html>

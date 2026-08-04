@extends('public.cible._layout', [
    'seo_title'       => 'CIBLE — Régie publicitaire en Côte d\'Ivoire · 364 panneaux · Vous visez juste',
    'seo_description' => 'Régie publicitaire ivoirienne depuis 1994. Affichage grand format, publicité mobile, communication 360°. 364 panneaux dans 31 communes. Devis sous 24 h.',
    'current'         => 'home',
])

@push('page-css')
    /* ───── flèches ambiantes ───── */
    .fleche{position:absolute;pointer-events:none;z-index:0;opacity:0;animation:apparait .9s var(--del,0s) forwards, derive var(--dur,16s) var(--del,0s) ease-in-out infinite}
    .fleche svg{width:100%;height:auto;display:block;fill:var(--c,var(--rouge))}
    @keyframes apparait{to{opacity:var(--op,.9)}}
    @keyframes derive{
      0%,100%{transform:translate3d(0,0,0) rotate(var(--r,0deg))}
      50%{transform:translate3d(var(--dx,18px),var(--dy,-24px),0) rotate(calc(var(--r,0deg) + 7deg))}
    }
    .couche{position:absolute;inset:0;z-index:0;pointer-events:none}

    /* ───── HERO ───── */
    .hero-wrap{position:relative}
    .hero{display:grid;grid-template-columns:1.05fr .95fr;gap:clamp(24px,4vw,56px);align-items:center;padding:clamp(36px,5.5vw,80px) var(--pad) 0;position:relative}
    @media(max-width:920px){.hero{grid-template-columns:1fr}}
    .hero>*{position:relative;z-index:2}
    .hero .sur{color:var(--vert)}
    .hero h1{margin-top:16px;font-family:var(--titre);font-weight:900;line-height:.95;letter-spacing:-.038em;font-size:clamp(42px,7.2vw,96px)}
    .hero h1 .l{display:block;opacity:0;transform:translateY(26px);animation:monte .8s cubic-bezier(.2,.8,.3,1) forwards}
    .hero h1 .l:nth-child(1){animation-delay:.05s}
    .hero h1 .l:nth-child(2){animation-delay:.16s}
    .hero h1 .l:nth-child(3){animation-delay:.27s;color:var(--rouge)}
    @keyframes monte{to{opacity:1;transform:none}}
    .hero .sous-titre{margin-top:20px;max-width:52ch;font-family:var(--corps);font-weight:700;font-size:clamp(16px,1.6vw,20px);line-height:1.45;color:#3A3A3A;opacity:0;animation:monte .8s .36s cubic-bezier(.2,.8,.3,1) forwards}
    .hero .accroche{margin-top:16px;max-width:48ch;font-size:clamp(15px,1.4vw,18px);color:#4A4A4A;opacity:0;animation:monte .8s .46s cubic-bezier(.2,.8,.3,1) forwards}
    .hero .accroche strong{color:var(--rouge)}
    .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px;opacity:0;animation:monte .8s .52s cubic-bezier(.2,.8,.3,1) forwards}
    .hero-visuel{position:relative;aspect-ratio:1/1;border-radius:999px 28px 28px 999px;overflow:hidden;background:var(--gris);opacity:0;transform:scale(.94);animation:zoom .9s .3s cubic-bezier(.2,.8,.3,1) forwards}
    @keyframes zoom{to{opacity:1;transform:none}}
    @media(max-width:920px){.hero-visuel{border-radius:28px;aspect-ratio:4/3}}

    /* ───── marque (violet, 3 blocs récit) — refonte 2026-08-04 ─────
       Ancienne version : 2 cols 50/50, image bord-arrondi à droite
       collée au texte. Problème observé : les 2 éléments ne s'alignaient
       pas verticalement (texte trop haut, image trop grande).
       Nouvelle version : image ronde à gauche (ratio 40/60), plus petite
       et centrée verticalement ; le bloc texte respire mieux. */
    .marque{
        display:grid;
        grid-template-columns:0.85fr 1.15fr;
        align-items:center;
        gap:0;
        min-height:min(70vh,560px);
        background:var(--violet);
    }
    @media(max-width:900px){.marque{grid-template-columns:1fr;min-height:auto}}
    .marque-img{
        position:relative;
        aspect-ratio:1/1;
        max-width:min(90%,520px);
        margin:clamp(30px,4vw,60px) auto;
        border-radius:50%;
        overflow:hidden;
        background:var(--gris);
        box-shadow:0 30px 60px -20px rgba(0,0,0,.4);
    }
    @media(max-width:900px){.marque-img{max-width:min(75%,360px);margin:clamp(30px,5vw,50px) auto 0}}
    .marque-txt{
        color:var(--blanc);
        padding:clamp(30px,4vw,60px) clamp(30px,5vw,70px);
        display:flex;
        flex-direction:column;
        justify-content:center;
        position:relative;
        overflow:hidden;
    }
    .marque-txt > .rev {position:relative;z-index:2}
    .marque-txt p{margin-top:16px;max-width:52ch;opacity:.95}
    .marque-txt .fort{margin-top:24px;font-family:var(--titre);font-weight:800;font-size:clamp(17px,1.9vw,22px);border-top:1px solid rgba(255,255,255,.2);padding-top:20px}
    .recit{margin-top:22px;display:grid;gap:18px}
    .recit article h3{font-family:var(--titre);font-weight:800;font-size:clamp(15px,1.6vw,18px);margin-bottom:5px;color:var(--jaune)}
    .recit article p{margin:0;font-size:14.5px;opacity:.92;max-width:50ch;line-height:1.6}

    /* ───── territoires (onglets) ───── */
    .territoires{padding:clamp(56px,8vw,100px) var(--pad) 0}
    /* Centrage 2026-08-04 : trop d'espace vide à droite avant */
    .entete{max-width:64ch;position:relative;z-index:2;text-align:center;margin-left:auto;margin-right:auto}
    .entete .sur{color:var(--bleu)}
    .entete .t1{margin-top:14px}
    .entete p{margin-top:18px;color:#444}
    .onglets{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:38px;position:relative;z-index:2}
    .onglet{display:flex;flex-direction:column;align-items:center;gap:10px;background:none;border:0;padding:0;cursor:pointer;font-family:var(--titre);font-weight:800;font-size:14px;color:#9A9A9A;transition:color .25s}
    .onglet .forme{width:clamp(56px,7vw,88px);height:clamp(22px,3vw,30px);background:currentColor;opacity:.3;border-radius:46% 46% 6px 6px / 60% 60% 6px 6px;transition:opacity .3s,height .35s cubic-bezier(.2,.9,.3,1)}
    .onglet:hover .forme{opacity:.6}
    .onglet[aria-selected="true"] .forme{opacity:1;height:clamp(34px,4.4vw,48px)}
    .onglet[aria-selected="true"]#o-rue{color:var(--rouge)}
    .onglet[aria-selected="true"]#o-mouvement{color:var(--jaune)}
    .onglet[aria-selected="true"]#o-ecran{color:var(--vert)}
    .onglet[aria-selected="true"]#o-digital{color:var(--bleu)}
    .onglet[aria-selected="true"]#o-terrain{color:var(--violet)}

    .scene{position:relative;margin-top:34px;border-radius:34px 34px 0 0;overflow:hidden;transition:background .55s cubic-bezier(.4,0,.2,1)}
    .pan{position:relative;z-index:2;padding:clamp(28px,4vw,62px);color:var(--blanc);display:grid;grid-template-columns:1fr 1fr;gap:clamp(22px,3.5vw,52px);align-items:center}
    @media(max-width:900px){.pan{grid-template-columns:1fr}}
    .pan[hidden]{display:none}
    .pan.entre>*{animation:glisse .55s cubic-bezier(.2,.8,.3,1) both}
    .pan.entre>*:nth-child(2){animation-delay:.09s}
    @keyframes glisse{from{opacity:0;transform:translateX(22px)}to{opacity:1;transform:none}}
    .pan h3{font-family:var(--titre);font-weight:900;font-size:clamp(28px,3.8vw,50px);line-height:1;letter-spacing:-.025em;margin-top:12px}
    .pan p{margin-top:16px;max-width:46ch;opacity:.95}
    .pan .grosnum{font-family:var(--titre);font-weight:900;font-size:clamp(52px,7.6vw,104px);line-height:.9;letter-spacing:-.04em}
    .tags{list-style:none;display:flex;flex-wrap:wrap;gap:8px;margin-top:22px;padding:0}
    .tags li{border:1.5px solid currentColor;opacity:.85;padding:6px 13px;border-radius:999px;font-family:var(--titre);font-weight:600;font-size:13px}
    .preuve{margin-top:24px;padding-left:16px;border-left:4px solid currentColor;font-size:15px}
    .pan-visuel{aspect-ratio:4/3;border-radius:22px;overflow:hidden}
    #t-mouvement{color:var(--noir)}
    .balayage{position:absolute;inset:0;z-index:1;pointer-events:none;overflow:hidden}
    .balayage span{position:absolute;opacity:0}
    .scene.switch .balayage span{animation:traverse .95s cubic-bezier(.3,.7,.3,1) both}
    .scene.switch .balayage span:nth-child(2){animation-delay:.1s}
    .scene.switch .balayage span:nth-child(3){animation-delay:.2s}
    @keyframes traverse{
      0%{opacity:0;transform:translateX(-30%) rotate(var(--r,0deg)) scale(.9)}
      35%{opacity:.22}
      100%{opacity:0;transform:translateX(60%) rotate(calc(var(--r,0deg) + 20deg)) scale(1.1)}
    }
    .balayage svg{fill:#fff}

    /* ───── mission (gris) ───── */
    .mission{background:var(--gris);padding:clamp(46px,6vw,86px) var(--pad);text-align:center}
    .mission p{max-width:64ch;margin:0 auto;font-size:clamp(16px,1.5vw,19px);color:#333}
    .mission p + p{margin-top:20px}
    .mission .fort{margin-top:22px;font-family:var(--titre);font-weight:800;font-size:clamp(17px,2vw,23px);color:var(--noir);max-width:44ch}

    /* ───── travaux (grille 6) ───── */
    .travaux{padding:clamp(56px,8vw,100px) var(--pad)}
    .travaux-tete{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;margin-bottom:36px;position:relative;z-index:2}
    .travaux .sur{color:var(--jaune)}
    .grille{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(14px,2vw,26px);position:relative;z-index:2}
    @media(max-width:900px){.grille{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:560px){.grille{grid-template-columns:1fr}}
    .carte{transition:transform .28s cubic-bezier(.2,.8,.3,1)}
    .carte:hover{transform:translateY(-8px)}
    .carte .vign{aspect-ratio:4/3;border-radius:22px;overflow:hidden;position:relative}
    .carte .vign::after{content:"";position:absolute;inset:auto 0 0 0;height:7px;background:var(--c);transform:scaleX(0);transform-origin:left;transition:transform .35s cubic-bezier(.2,.8,.3,1)}
    .carte:hover .vign::after{transform:scaleX(1)}
    .carte h4{font-family:var(--titre);font-weight:800;font-size:18px;margin-top:14px}
    .carte span{font-size:14px;color:#666}
    .pastille{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:8px;vertical-align:middle;background:var(--c)}

    /* ───── réseau (bleu) ───── */
    .reseau{background:var(--bleu);color:var(--blanc);padding:clamp(56px,8vw,110px) var(--pad)}
    .reseau-grille{position:relative;z-index:2;display:grid;grid-template-columns:1fr 1fr;gap:clamp(26px,4vw,60px);align-items:center}
    @media(max-width:900px){.reseau-grille{grid-template-columns:1fr}}
    .reseau p{margin-top:20px;max-width:48ch;opacity:.95}
    .stats{display:flex;gap:44px;flex-wrap:wrap;margin-top:36px}
    .stats .v{font-family:var(--titre);font-weight:900;font-size:clamp(46px,6vw,80px);line-height:.86;letter-spacing:-.04em}
    .stats .l{font-family:var(--titre);font-weight:600;font-size:13px;opacity:.9;margin-top:10px}
    .carte-slot{aspect-ratio:16/11;border-radius:26px;overflow:hidden}
    .note{margin-top:14px;font-size:13px;opacity:.9}

    /* ───── distinctions ───── */
    .dist-sec{padding:clamp(50px,7vw,90px) var(--pad);text-align:center}
    .dist-sec .sur{color:var(--vert)}
    /* max-width élargi 24ch→38ch (2026-08-04) — évite la cassure serrée
       "Trois distinctions de / l'État ivoirien" avec letters qui touchent. */
    .dist-sec .t2{max-width:38ch;margin-left:auto;margin-right:auto}
    .dist-grille{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,3vw,44px);margin-top:40px;text-align:left}
    @media(max-width:820px){.dist-grille{grid-template-columns:1fr}}
    .dist{border-top:6px solid var(--c);padding-top:16px}
    .dist .an{font-family:var(--titre);font-weight:900;font-size:32px;color:var(--c)}
    .dist h4{font-family:var(--titre);font-weight:800;font-size:16px;margin-top:6px;line-height:1.3}
    .dist p{font-size:14px;color:#5F5F5F;margin-top:8px}

    /* ───── contact (jaune + motif plumes) ───── */
    .contact-home{background:var(--jaune);color:var(--noir);padding:clamp(60px,8vw,120px) var(--pad);position:relative;overflow:hidden}
    .motif{position:absolute;inset:-10% -10%;background-image:var(--tuile);background-size:340px 340px;animation:motifDerive 60s linear infinite;pointer-events:none;z-index:0}
    @keyframes motifDerive{from{background-position:0 0}to{background-position:340px 340px}}
    .contact-inner{position:relative;z-index:2}
    .contact-home .t1{max-width:16ch}
    .contact-home p{margin-top:20px;max-width:44ch;font-size:clamp(16px,1.6vw,20px)}
    .coord{margin-top:36px;display:flex;flex-wrap:wrap;gap:12px 40px;background:var(--blanc);padding:22px 28px;border-radius:22px;font-family:var(--titre);font-weight:700;font-size:15px}
    .coord a:hover{color:var(--rouge)}
@endpush

@section('content')

{{-- ═══ SVG symbols (flèches, plume) ═══ --}}
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>
<symbol id="fh-droite" viewBox="0 0 202 286"><path fill-rule="evenodd" d="M0.1 274.2C0.2 262.4 0.3 261.5 1.4 268.1C1.8 270.3 3.6 273.3 6.3 276.2C11.0 281.3 13.6 282.9 19.5 284.7C23.1 285.7 22.4 285.8 11.8 285.9L0.0 286.0L0.1 274.2ZM34.3 284.0C46.0 279.5 73.3 254.9 88.8 235.0C93.3 229.2 97.5 223.8 98.2 223.0C100.0 220.9 106.3 211.2 108.6 207.1C109.7 205.2 112.4 200.3 114.7 196.1C119.7 187.2 121.0 184.4 121.0 182.6C121.0 181.8 121.7 180.7 122.5 180.0C123.3 179.3 124.0 178.1 124.0 177.2C124.0 176.3 124.8 173.8 125.9 171.6C128.2 166.6 130.7 159.2 132.4 152.4C133.2 149.6 134.2 146.5 134.8 145.4C135.4 144.2 136.2 140.9 136.6 137.9C137.0 134.9 137.9 130.9 138.6 128.8C139.3 126.8 140.2 121.4 140.5 116.8C141.5 105.6 142.1 104.8 145.4 110.4C148.9 116.3 150.1 127.1 149.8 147.4C149.5 163.8 149.9 165.4 156.0 172.4C161.6 178.7 165.7 180.5 175.0 180.5C184.0 180.5 187.4 179.4 192.5 174.6C198.2 169.3 200.3 164.9 201.0 156.5C201.4 152.1 201.8 179.4 201.9 217.2L202.0 286.0L115.8 286.0C30.2 285.9 29.5 285.9 34.3 284.0ZM0.1 127.8L0.0 0.0L51.2 0.1C86.0 0.2 101.4 0.5 99.0 1.1C89.9 3.6 79.1 12.4 65.5 28.4C63.3 30.9 60.2 34.5 58.6 36.3C57.1 38.1 55.1 40.6 54.2 42.0C53.3 43.4 52.0 45.2 51.3 46.0C48.1 49.8 45.0 53.8 45.0 54.4C45.0 54.7 43.4 57.3 41.5 60.1C39.5 62.8 37.5 66.1 36.9 67.3C36.3 68.5 34.8 71.3 33.5 73.5C32.1 75.7 30.6 78.6 30.0 80.0C29.4 81.4 27.1 86.2 24.9 90.6C22.8 95.1 21.0 99.4 21.0 100.1C21.0 100.9 20.2 102.7 19.2 104.0C16.4 108.0 16.9 118.2 20.2 124.6C24.9 133.5 34.9 140.0 44.1 140.0C53.6 140.0 66.4 130.1 68.7 121.2C69.3 118.6 72.2 112.0 74.1 109.0C74.6 108.2 75.5 106.4 76.0 105.0C76.6 103.6 77.5 101.8 78.0 101.0C78.6 100.2 79.6 98.4 80.3 97.0C83.8 90.2 86.3 87.0 88.3 87.0C90.2 87.0 90.3 87.4 89.7 99.3C89.2 110.2 86.8 123.8 83.8 132.5C83.3 134.2 82.2 137.9 81.5 140.8C80.7 143.7 79.7 146.6 79.2 147.3C78.6 148.0 77.9 150.2 77.5 152.2C77.1 154.3 76.2 156.4 75.4 157.1C74.6 157.7 74.0 158.7 74.0 159.3C74.0 160.8 66.8 174.7 62.6 181.5C59.6 186.2 55.6 192.4 54.7 193.5C42.8 209.2 24.3 228.1 12.6 236.2C6.5 240.5 2.0 246.6 0.9 252.0C0.5 253.9 0.2 198.0 0.1 127.8ZM200.6 115.8C200.2 110.2 199.3 104.2 198.6 102.5C197.8 100.7 196.9 97.3 196.5 94.9C196.1 92.5 195.1 89.6 194.4 88.5C193.6 87.4 193.0 85.9 193.0 85.1C193.0 82.4 184.9 66.0 179.1 57.0C164.3 33.9 134.8 9.7 112.1 2.0L106.5 0.1L154.2 0.1L202.0 0.0L202.0 63.0C202.0 97.7 201.8 126.0 201.6 126.0C201.4 126.0 201.0 121.4 200.6 115.8Z"/></symbol>
<symbol id="fb-gauche" viewBox="0 0 189 297"><path fill-rule="evenodd" d="M0.1 242.8C0.2 212.9 0.4 189.4 0.7 190.5C1.2 193.0 4.7 202.5 6.6 206.5C7.3 208.2 8.4 210.6 9.0 212.0C9.6 213.4 11.1 216.3 12.5 218.5C13.8 220.7 15.3 223.4 15.8 224.5C17.2 227.4 25.5 239.7 29.7 245.0C48.4 268.6 81.9 288.7 111.0 293.9C114.6 294.5 119.5 295.4 122.0 295.9C124.8 296.5 102.7 296.8 63.2 296.9L0.0 297.0L0.1 242.8ZM151.6 294.6C154.4 293.3 157.9 291.0 159.4 289.4C161.9 286.8 163.2 284.3 169.1 271.5C171.3 266.7 173.7 260.1 175.5 254.0C176.3 251.5 177.6 247.5 178.4 245.0C181.1 237.1 184.0 225.6 184.0 223.1C184.0 221.8 184.7 218.0 185.6 214.6C186.5 211.3 187.5 202.4 188.0 195.0C188.4 186.0 188.7 200.9 188.8 239.2L189.0 297.0L167.8 296.9L146.5 296.9L151.6 294.6ZM73.5 212.1C73.2 211.5 72.1 211.0 71.1 211.0C69.4 211.0 67.8 209.0 62.4 200.5C58.9 194.9 52.0 180.5 52.0 178.7C52.0 175.4 46.2 164.5 42.8 161.4C37.4 156.3 33.3 154.7 25.5 154.7C19.9 154.6 17.5 155.1 13.4 157.3C8.0 160.1 2.4 166.1 1.0 170.8C0.5 172.3 0.1 134.5 0.1 86.8L0.0 0.0L59.8 0.1C92.6 0.2 118.4 0.5 117.0 0.7C108.7 2.0 103.5 6.8 95.3 20.5C92.8 24.6 88.0 34.7 88.0 35.7C88.0 36.3 87.3 37.3 86.5 38.0C85.7 38.7 85.0 40.0 85.0 40.9C85.0 41.8 84.3 43.9 83.4 45.5C78.5 54.7 70.7 81.0 68.5 96.0C67.8 100.7 66.7 106.3 66.2 108.5C64.6 114.6 63.8 144.0 64.8 158.0C66.0 173.0 68.4 188.9 71.1 198.5C72.1 202.3 73.0 206.7 73.0 208.2C73.0 209.6 73.6 211.0 74.2 211.3C74.9 211.5 75.2 212.0 74.9 212.4C74.5 212.8 73.9 212.7 73.5 212.1ZM129.1 210.1C128.5 209.0 128.0 207.3 128.0 206.3C127.9 205.3 127.1 202.7 126.0 200.5C124.9 198.3 124.0 195.4 124.0 194.0C123.9 192.6 123.2 189.7 122.4 187.5C120.9 183.5 118.6 171.4 117.0 159.0C115.1 144.8 115.9 126.8 119.4 106.5C120.2 101.5 121.6 95.7 122.4 93.5C123.2 91.3 123.9 88.4 124.0 87.0C124.0 85.6 124.9 82.7 126.0 80.5C127.1 78.3 127.9 75.7 128.0 74.8C128.0 73.2 131.1 65.4 133.1 62.0C133.6 61.2 134.3 59.8 134.7 59.0C137.7 52.0 144.1 39.8 146.1 37.4C147.8 35.4 148.5 21.2 147.1 18.0C142.6 8.2 136.4 2.6 128.0 0.9C126.1 0.5 139.0 0.2 156.8 0.1L189.0 0.0L188.9 84.2C188.8 130.6 188.4 166.0 188.0 163.0C186.5 151.5 176.5 142.2 164.5 141.2C154.3 140.4 144.5 146.3 139.4 156.3C137.1 160.7 136.9 162.5 136.4 179.8C135.9 194.6 135.4 199.8 133.8 204.7C131.6 211.8 130.6 212.9 129.1 210.1Z"/></symbol>
<symbol id="fh-gauche" viewBox="0 0 177 119"><path fill-rule="evenodd" d="M0.1 94.8C0.1 75.5 0.4 70.9 1.3 72.5C3.0 75.4 12.6 84.6 19.0 89.5C31.2 98.8 49.7 108.5 63.9 113.1C70.3 115.2 71.2 115.2 75.1 114.0C83.4 111.2 88.1 103.7 86.6 95.6C85.6 90.3 78.7 83.9 71.9 81.7C64.6 79.5 50.8 72.6 51.6 71.7C52.7 70.6 70.3 70.9 76.2 72.1C89.4 74.8 98.3 77.6 108.0 82.3C122.7 89.3 131.3 95.4 142.8 107.0C148.3 112.5 153.3 117.0 153.8 117.0C154.4 117.0 155.2 117.5 155.5 118.0C155.9 118.7 129.8 119.0 78.1 119.0L0.0 119.0L0.1 94.8ZM166.5 118.0C166.8 117.5 167.7 117.0 168.4 117.0C170.4 117.0 173.8 112.8 175.4 108.5L176.9 104.5L176.9 111.8L177.0 119.0L171.4 119.0C168.0 119.0 166.1 118.6 166.5 118.0ZM175.2 97.6C174.3 94.2 172.1 91.3 165.3 84.3C145.1 63.7 120.7 49.7 94.0 43.2C79.7 39.8 75.1 39.0 69.5 39.0C64.1 39.0 61.9 37.9 64.2 36.3C66.9 34.6 74.9 33.1 84.5 32.4C96.1 31.7 99.8 30.2 103.7 25.2C109.8 17.1 105.4 4.1 95.5 1.1C93.6 0.5 107.3 0.1 134.8 0.1L177.0 0.0L177.0 51.0C177.0 79.0 176.9 102.0 176.7 102.0C176.5 102.0 175.9 100.0 175.2 97.6ZM0.0 30.2L0.0 0.0L38.2 0.1C59.5 0.2 74.1 0.6 71.0 1.0C54.1 3.3 33.8 13.9 20.9 27.4C15.4 33.1 7.0 44.8 7.0 46.6C7.0 47.2 6.4 48.3 5.6 48.9C4.8 49.6 3.8 51.6 3.4 53.3C3.1 55.1 2.1 57.4 1.4 58.5C0.2 60.2 0.0 56.1 0.0 30.2Z"/></symbol>
<symbol id="plume" viewBox="0 0 204 442"><path fill-rule="evenodd" d="M0.1 335.2C0.1 267.4 0.5 229.6 1.1 231.5C1.6 233.2 2.1 240.6 2.1 248.0C2.2 256.5 2.7 262.4 3.5 264.0C4.2 265.4 5.0 268.1 5.4 270.1C5.8 272.2 6.7 274.3 7.5 275.0C8.3 275.7 9.2 277.8 9.6 279.9C10.4 284.3 12.6 288.8 15.4 291.7C19.9 296.3 25.5 305.0 29.4 313.7C30.3 315.7 32.0 317.2 34.3 318.1C37.7 319.3 37.9 319.5 37.8 324.2C37.8 327.3 38.4 329.8 39.3 330.8C40.2 331.8 41.3 334.5 41.9 336.8C42.4 339.2 43.5 341.9 44.4 342.9C45.3 343.9 46.0 345.1 46.0 345.6C46.0 346.1 48.4 348.9 51.4 351.8C56.1 356.2 57.0 357.7 57.6 361.8C57.9 364.4 58.6 367.0 59.1 367.6C59.6 368.2 60.7 372.2 61.6 376.4C63.2 384.4 64.9 388.8 68.8 395.1C70.9 398.5 72.1 399.4 78.3 401.9C79.8 402.5 81.4 403.8 81.7 404.7C82.9 407.9 86.9 413.1 89.7 415.3C91.2 416.5 92.7 417.8 92.9 418.3C93.8 420.5 102.3 428.0 104.3 428.4C105.5 428.7 109.3 429.1 112.8 429.3C118.1 429.6 119.5 430.1 121.6 432.3C123.0 433.8 124.9 435.0 126.0 435.0C128.6 435.0 129.3 436.5 127.5 438.0C126.7 438.7 126.0 439.9 126.0 440.6C126.0 441.8 115.8 442.0 63.0 442.0L0.0 442.0L0.1 335.2ZM128.0 441.0C128.0 439.2 132.7 438.1 137.7 438.6C143.6 439.3 151.7 436.1 157.7 430.6C159.9 428.6 162.7 427.0 163.9 427.0C166.3 427.0 170.0 430.3 170.0 432.4C170.0 433.1 170.6 434.3 171.4 434.9C172.2 435.6 173.3 437.4 173.9 439.1L174.9 442.0L151.4 442.0C136.1 442.0 128.0 441.6 128.0 441.0ZM178.6 441.1C183.1 440.0 183.7 439.4 184.5 435.4C185.2 431.7 183.0 427.4 177.2 421.2C174.1 417.8 174.3 417.1 180.0 411.0C182.8 408.1 185.0 405.4 185.0 405.1C185.0 404.8 187.3 403.0 190.0 401.1C194.6 397.9 195.0 397.4 195.0 393.6C195.0 389.1 197.0 381.6 198.5 380.1C199.1 379.6 200.5 378.9 201.8 378.5L204.0 377.9L204.0 409.9L204.0 442.0L189.8 441.9C181.3 441.8 176.8 441.5 178.6 441.1ZM199.2 372.4C197.5 371.0 196.0 369.3 196.0 368.7C196.0 368.1 194.1 365.7 191.8 363.3L187.6 359.0L188.9 352.6C190.3 345.8 189.7 338.8 187.7 337.2C187.0 336.7 183.9 335.5 180.7 334.5C174.2 332.5 172.9 331.3 175.3 329.5C178.3 327.3 177.2 324.2 171.0 317.7C164.6 311.1 163.3 307.0 166.8 304.4C168.3 303.2 168.2 303.0 165.2 302.0C163.5 301.3 162.0 300.2 162.0 299.4C162.0 298.7 160.5 297.7 158.8 297.2C156.1 296.5 155.5 295.9 155.8 293.9C155.9 292.6 157.0 290.7 158.3 289.6C160.3 288.0 160.5 286.8 160.5 279.4C160.5 271.8 160.3 270.9 158.2 269.4C156.1 267.9 155.9 267.2 156.5 263.3C157.1 259.4 156.9 258.6 154.6 256.5C152.2 254.2 152.0 253.5 152.0 244.5C152.0 238.5 151.6 235.0 150.9 235.0C150.4 235.0 149.3 233.5 148.5 231.6C147.7 229.8 145.8 227.2 144.3 225.9C141.1 223.2 138.6 216.5 140.0 214.1C140.6 213.0 140.0 211.6 137.8 209.2C135.6 206.6 135.0 205.2 135.5 203.6C135.9 202.5 136.5 199.7 136.7 197.5C137.0 195.3 137.6 193.0 138.0 192.5C138.5 191.9 138.2 190.6 137.4 189.6C135.2 186.5 135.6 180.5 138.2 178.8C140.4 177.3 140.4 177.1 139.2 170.1C138.4 165.2 138.3 162.3 139.0 161.0C140.4 158.3 140.2 154.2 138.7 153.3C137.9 152.9 136.7 151.2 135.9 149.5C135.1 147.9 132.9 145.5 131.0 144.2C129.1 142.9 127.6 141.4 127.8 140.8C128.0 140.2 127.4 139.0 126.5 138.0C125.5 136.9 125.0 134.5 125.0 130.9C125.1 127.9 124.7 121.7 124.2 117.0C122.9 106.1 123.9 102.3 128.5 99.1C130.4 97.8 132.3 95.6 132.6 94.3C133.3 91.8 132.9 90.8 128.2 82.9C123.9 75.6 123.7 75.4 120.2 73.6C116.6 71.8 116.3 70.8 118.5 69.0C119.4 68.3 120.0 66.7 119.8 65.6C119.6 63.9 118.8 63.5 116.2 63.5C112.1 63.5 109.0 60.2 109.0 55.9C109.0 54.3 108.2 52.2 107.2 51.2C104.0 48.0 101.8 40.1 100.9 28.7C100.1 17.7 99.1 15.0 95.7 15.0C95.2 15.0 93.2 13.2 91.1 11.0C89.1 8.8 87.0 7.0 86.5 7.0C86.1 7.0 84.1 5.8 82.1 4.2C80.2 2.7 77.9 1.2 77.0 0.8C76.2 0.4 104.4 0.1 139.8 0.1L204.0 0.0L204.0 187.5C204.0 290.6 203.7 375.0 203.2 375.0C202.8 375.0 201.0 373.8 199.2 372.4ZM0.1 111.8L0.0 0.0L32.5 -0.0L64.9 -0.0L62.7 2.3C61.5 3.5 58.6 6.0 56.3 7.8C52.4 10.9 46.0 19.0 46.0 20.9C46.0 21.4 45.3 22.3 44.5 23.0C43.7 23.7 42.2 26.1 41.2 28.4C40.1 30.6 38.6 33.1 37.7 33.9C36.7 34.6 36.0 36.1 36.0 37.1C36.0 38.1 35.6 39.1 35.0 39.5C34.5 39.8 34.3 40.7 34.6 41.5C35.5 43.7 33.1 60.0 31.4 63.1C30.6 64.6 30.0 67.2 30.0 68.7C30.0 70.3 29.1 73.3 28.1 75.4C27.0 77.5 25.8 80.6 25.4 82.3C25.1 84.1 24.2 86.2 23.5 87.0C22.8 87.8 22.0 90.7 21.6 93.3C21.0 98.3 16.9 105.0 14.4 105.0C13.7 105.0 11.8 106.1 10.3 107.4C7.7 109.6 7.6 110.1 8.7 112.9C9.7 115.6 9.6 116.3 8.1 117.8C4.4 121.4 4.7 122.8 11.0 128.8C14.3 131.9 17.0 135.1 17.0 135.9C17.0 138.0 13.2 145.6 11.4 147.0C10.7 147.7 10.0 149.7 10.0 151.4C10.0 153.1 9.3 155.8 8.4 157.5C6.8 160.4 6.3 174.8 7.5 181.1C7.8 182.4 8.4 185.8 8.9 188.6C10.1 194.8 13.3 201.4 17.3 205.7C19.9 208.5 20.1 209.1 19.0 210.5C16.9 213.0 12.4 215.0 8.8 215.0C4.5 215.0 2.8 216.2 1.3 220.2C0.4 222.8 0.1 199.7 0.1 111.8Z"/></symbol>
<symbol id="fh-droite-mini" viewBox="0 0 177 119"><path fill-rule="evenodd" transform="translate(177,0) scale(-1,1)" d="M0.1 94.8C0.1 75.5 0.4 70.9 1.3 72.5C3.0 75.4 12.6 84.6 19.0 89.5C31.2 98.8 49.7 108.5 63.9 113.1C70.3 115.2 71.2 115.2 75.1 114.0C83.4 111.2 88.1 103.7 86.6 95.6C85.6 90.3 78.7 83.9 71.9 81.7C64.6 79.5 50.8 72.6 51.6 71.7C52.7 70.6 70.3 70.9 76.2 72.1C89.4 74.8 98.3 77.6 108.0 82.3C122.7 89.3 131.3 95.4 142.8 107.0C148.3 112.5 153.3 117.0 153.8 117.0C154.4 117.0 155.2 117.5 155.5 118.0C155.9 118.7 129.8 119.0 78.1 119.0L0.0 119.0L0.1 94.8ZM166.5 118.0C166.8 117.5 167.7 117.0 168.4 117.0C170.4 117.0 173.8 112.8 175.4 108.5L176.9 104.5L176.9 111.8L177.0 119.0L171.4 119.0C168.0 119.0 166.1 118.6 166.5 118.0ZM175.2 97.6C174.3 94.2 172.1 91.3 165.3 84.3C145.1 63.7 120.7 49.7 94.0 43.2C79.7 39.8 75.1 39.0 69.5 39.0C64.1 39.0 61.9 37.9 64.2 36.3C66.9 34.6 74.9 33.1 84.5 32.4C96.1 31.7 99.8 30.2 103.7 25.2C109.8 17.1 105.4 4.1 95.5 1.1C93.6 0.5 107.3 0.1 134.8 0.1L177.0 0.0L177.0 51.0C177.0 79.0 176.9 102.0 176.7 102.0C176.5 102.0 175.9 100.0 175.2 97.6ZM0.0 30.2L0.0 0.0L38.2 0.1C59.5 0.2 74.1 0.6 71.0 1.0C54.1 3.3 33.8 13.9 20.9 27.4C15.4 33.1 7.0 44.8 7.0 46.6C7.0 47.2 6.4 48.3 5.6 48.9C4.8 49.6 3.8 51.6 3.4 53.3C3.1 55.1 2.1 57.4 1.4 58.5C0.2 60.2 0.0 56.1 0.0 30.2Z"/></symbol>
</defs></svg>

{{-- ═══ HERO ═══ --}}
<section class="hero-wrap">
    <div class="couche" data-parallaxe>
        <span class="fleche" style="--c:var(--vert);--op:.85;top:6%;left:40%;width:74px;--r:-12deg;--dur:17s;--del:.7s"><svg viewBox="0 0 177 119"><use href="#fh-gauche"/></svg></span>
        <span class="fleche" style="--c:var(--bleu);--op:.8;top:52%;left:4%;width:62px;--r:14deg;--dur:21s;--del:.95s;--dx:-14px;--dy:20px"><svg viewBox="0 0 189 297"><use href="#fb-gauche"/></svg></span>
        <span class="fleche" style="--c:var(--jaune);--op:.9;top:16%;right:3%;width:70px;--r:8deg;--dur:19s;--del:1.1s"><svg viewBox="0 0 202 286"><use href="#fh-droite"/></svg></span>
        <span class="fleche" style="--c:var(--violet);--op:.55;bottom:2%;left:26%;width:52px;--r:-24deg;--dur:24s;--del:1.3s;--dx:22px;--dy:14px"><svg viewBox="0 0 204 442"><use href="#plume"/></svg></span>
    </div>
    <div class="hero">
        <div>
            <span class="sur">Régie &amp; studio · Côte d'Ivoire · depuis 1994</span>
            <h1>
                <span class="l">Votre marque,</span>
                <span class="l">partout où vit</span>
                <span class="l">votre audience.</span>
            </h1>
            <h2 class="sous-titre">Régie publicitaire en Côte d'Ivoire — 364 panneaux d'affichage dans 31 communes, publicité mobile et communication 360°.</h2>
            <p class="accroche">Nous n'offrons pas seulement de la visibilité : nous offrons une <strong>performance mesurable, orientée résultats</strong> — de l'exposition dans la rue jusqu'à la visite en point de vente.</p>
            <div class="actions">
                <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Rendre ma marque visible<svg class="fl" viewBox="0 0 177 119"><use href="#fh-droite-mini"/></svg></a>
                <a class="bouton b-ligne" href="{{ route('cible.references') }}">Voir nos réalisations</a>
            </div>
        </div>
        <div class="hero-visuel">
            <img class="photo" src="{{ asset('images/perroquet-cible.jpg') }}" alt="Perroquet écarlate — symbole de la marque CIBLE" style="object-position:52% 42%">
        </div>
    </div>

    {{-- Bande 5 couleurs retirée 2026-08-04 — les couleurs étaient
         redondantes avec le ticker qui suit immédiatement + section
         territoires plus bas. Trop bruyant visuellement en début de page. --}}
</section>

{{-- ═══ TICKER ═══ --}}
<div class="ticker" aria-hidden="true" style="margin-top:clamp(24px,4vw,44px)">
    <div class="piste">
        <b style="background:var(--rouge)" class="num">364 panneaux</b>
        <b style="background:var(--jaune);color:#111" class="num">31 communes</b>
        <b style="background:var(--vert)" class="num">30 ans d'expertise</b>
        <b style="background:var(--violet)" class="num">5 territoires de visibilité</b>
        <b style="background:var(--bleu)" class="num">3 distinctions d'État</b>
        <b style="background:var(--noir)">De la rue au digital</b>
        <b style="background:var(--rouge)" class="num">364 panneaux</b>
        <b style="background:var(--jaune);color:#111" class="num">31 communes</b>
        <b style="background:var(--vert)" class="num">30 ans d'expertise</b>
        <b style="background:var(--violet)" class="num">5 territoires de visibilité</b>
        <b style="background:var(--bleu)" class="num">3 distinctions d'État</b>
        <b style="background:var(--noir)">De la rue au digital</b>
    </div>
</div>

{{-- ═══ MARQUE (violet, 3 blocs récit) ═══ --}}
<section class="marque" id="marque">
    <div class="marque-img">
        <img class="photo" src="{{ asset('images/perroquet-cible.jpg') }}" alt="Perroquet écarlate — identité visuelle CIBLE" style="object-position:62% 38%">
    </div>
    <div class="marque-txt">
        <div class="couche">
            <span class="fleche" style="--c:#fff;--op:.14;top:8%;right:8%;width:130px;--r:16deg;--dur:26s;--del:.2s"><svg viewBox="0 0 204 442"><use href="#plume"/></svg></span>
            <span class="fleche" style="--c:#fff;--op:.14;bottom:6%;left:6%;width:96px;--r:-14deg;--dur:22s;--del:.4s"><svg viewBox="0 0 202 286"><use href="#fh-droite"/></svg></span>
        </div>
        <div class="rev" style="position:relative;z-index:2">
            <span class="sur" style="opacity:.85">Opération plume rouge</span>
            <h2 class="t1" style="margin-top:12px;font-size:clamp(30px,4vw,54px)">Se faire remarquer, c'est un métier.</h2>
            <div class="recit">
                <article>
                    <h3>Notre origine — maîtres de la visibilité extérieure</h3>
                    <p>Née dans l'affichage publicitaire, CIBLE s'est imposée en trente ans comme un pilier de la publicité extérieure en Côte d'Ivoire : panneaux grand format, signalétique de carrefour, camions de parade, habillage de véhicules. Un patrimoine de 364 emplacements, construit un panneau à la fois.</p>
                </article>
                <article>
                    <h3>Notre évolution — la visibilité devient mesurable</h3>
                    <p>Les usages ont changé, les attentes des annonceurs aussi. Le digital 360° ne remplace pas notre réseau : il le prolonge et le rend mesurable. Nous n'offrons plus seulement de la visibilité, mais une performance vérifiable, orientée résultats.</p>
                </article>
                <article>
                    <h3>Notre force — le terrain et la donnée</h3>
                    <p>Trente ans de connaissance du terrain ivoirien fusionnés avec une approche moderne et une exigence de résultat. Nous sommes la seule régie du pays à posséder à la fois son réseau et l'outil qui le pilote.</p>
                </article>
            </div>
            <p class="fort">Créer l'impact. Construire la notoriété.</p>
        </div>
    </div>
</section>

{{-- ═══ TERRITOIRES (onglets interactifs) ═══ --}}
<section class="territoires" id="territoires">
    <div class="couche">
        <span class="fleche" style="--c:var(--gris);--op:1;top:6%;right:6%;width:150px;--r:12deg;--dur:28s;--del:.2s"><svg viewBox="0 0 189 297"><use href="#fb-gauche"/></svg></span>
    </div>
    <div class="entete rev">
        <span class="sur">Où votre marque apparaît</span>
        <h2 class="t1">Cinq territoires de visibilité, une seule audience.</h2>
        <p>Les cinq couleurs de notre symbole représentent des panneaux publicitaires. Elles représentent aussi les cinq espaces que traverse une journée abidjanaise — et dans lesquels nous vous rendons présent.</p>
    </div>

    <div class="onglets" role="tablist" aria-label="Territoires de visibilité">
        <button class="onglet" id="o-rue"       role="tab" aria-selected="true"  aria-controls="t-rue"><span class="forme"></span>La rue</button>
        <button class="onglet" id="o-mouvement" role="tab" aria-selected="false" aria-controls="t-mouvement"><span class="forme"></span>Le mouvement</button>
        <button class="onglet" id="o-ecran"     role="tab" aria-selected="false" aria-controls="t-ecran"><span class="forme"></span>L'écran</button>
        <button class="onglet" id="o-digital"   role="tab" aria-selected="false" aria-controls="t-digital"><span class="forme"></span>Le digital</button>
        <button class="onglet" id="o-terrain"   role="tab" aria-selected="false" aria-controls="t-terrain"><span class="forme"></span>Le terrain</button>
    </div>

    <div class="scene" id="scene" style="background:var(--rouge)">
        <div class="balayage" aria-hidden="true">
            <span style="top:4%;left:0;width:190px;--r:10deg"><svg viewBox="0 0 202 286"><use href="#fh-droite"/></svg></span>
            <span style="top:44%;left:0;width:120px;--r:-16deg"><svg viewBox="0 0 177 119"><use href="#fh-gauche"/></svg></span>
            <span style="bottom:2%;left:0;width:150px;--r:22deg"><svg viewBox="0 0 204 442"><use href="#plume"/></svg></span>
        </div>

        <div class="pan" id="t-rue" role="tabpanel" aria-labelledby="o-rue">
            <div>
                <div class="grosnum num" data-cible="364">0</div>
                <span class="sur">Panneaux en exploitation</span>
                <h3>Affichage grand format : le seul média qu'on ne peut pas fermer.</h3>
                <p>L'affichage grand format reste le seul média que personne ne peut sauter, bloquer ou faire défiler. Nous exploitons notre propre patrimoine : nous maîtrisons donc les emplacements, les délais et la preuve de pose.</p>
                <ul class="tags"><li>Classiques</li><li>Lumipub</li><li>Trivision</li><li>Panoramiques</li><li>Écrans digitaux</li><li>Affichage en magasin</li></ul>
                <p class="preuve">Chaque campagne se termine par une pige photo horodatée depuis le terrain. La visibilité se constate, elle ne se promet pas.</p>
            </div>
            <div class="pan-visuel"><img class="photo" src="{{ asset('images/cible/pole-1-affichage.jpg') }}" alt="Panneau grand format CIBLE"></div>
        </div>

        <div class="pan" id="t-mouvement" role="tabpanel" aria-labelledby="o-mouvement" hidden>
            <div>
                <div class="grosnum num" data-cible="31">0</div>
                <span class="sur">Communes atteintes</span>
                <h3>Publicité mobile : la ville devient votre support.</h3>
                <p>Camions publicitaires, tricycles, motos, taxis, chevalets. Le message va chercher l'audience là où elle est immobile et captive : embouteillages, marchés, sorties d'école, abords des zones commerciales.</p>
                <ul class="tags"><li>Camions publicitaires</li><li>Branding véhicules</li><li>Taxis &amp; motos</li><li>Chevalets</li><li>Régie mobile événementielle</li></ul>
                <p class="preuve">Itinéraires et créneaux définis avec vous, tracés et rapportés après diffusion.</p>
            </div>
            <div class="pan-visuel"><img class="photo" src="{{ asset('images/cible/mobile-camion.jpg') }}" alt="Camion publicitaire CIBLE"></div>
        </div>

        <div class="pan" id="t-ecran" role="tabpanel" aria-labelledby="o-ecran" hidden>
            <div>
                <div class="grosnum num" data-brut="Studio">Studio</div>
                <span class="sur">Production interne</span>
                <h3>Production audiovisuelle : l'image qui porte le message.</h3>
                <p>Films institutionnels, spots TV et radio, motion design, contenus de marque. Le studio produit ce que le réseau diffuse : une même équipe, de la conception jusqu'à l'affichage.</p>
                <ul class="tags"><li>Films institutionnels</li><li>Spots TV &amp; audio</li><li>Motion design</li><li>Identité visuelle</li><li>Contenu de marque</li></ul>
                <p class="preuve">Film institutionnel réalisé pour le Groupe Cofina.</p>
            </div>
            <div class="pan-visuel"><img class="photo" src="{{ asset('images/cible/campagne-3.jpg') }}" alt="Extrait de film institutionnel"></div>
        </div>

        <div class="pan" id="t-digital" role="tabpanel" aria-labelledby="o-digital" hidden>
            <div>
                <div class="grosnum num" data-brut="24/7">24/7</div>
                <span class="sur">Présence continue</span>
                <h3>Communication digitale : le prolongement naturel du panneau.</h3>
                <p>Une campagne d'affichage sans relais digital perd la moitié de son effet. Social media ads, SEO/SEA, activations interactives, drive-to-store : nous transformons l'exposition en interaction, puis en visite.</p>
                <ul class="tags"><li>Social media ads</li><li>SEO / SEA</li><li>Campagnes virales</li><li>Activations interactives</li><li>Drive-to-store</li></ul>
                <p class="preuve">Conception graphique et gestion des réseaux pour SGS / SICTA.</p>
            </div>
            <div class="pan-visuel"><img class="photo" src="{{ asset('images/cible/campagne-4.jpg') }}" alt="Campagne social media CIBLE"></div>
        </div>

        <div class="pan" id="t-terrain" role="tabpanel" aria-labelledby="o-terrain" hidden>
            <div>
                <div class="grosnum num" data-brut="Face à face">Face à face</div>
                <span class="sur">Le dernier mètre</span>
                <h3>Street marketing : là où la marque devient une rencontre.</h3>
                <p>Street marketing, pop-up stores, roadshows, stands expérientiels, architecture événementielle. Le moment où l'audience ne regarde plus la marque : elle lui parle.</p>
                <ul class="tags"><li>Street marketing</li><li>Pop-up store</li><li>Roadshow</li><li>Stand expérientiel</li><li>Architecture événementielle</li></ul>
                <p class="preuve">Brand experience déployée pour Orange · stand expérientiel pour IFG.</p>
            </div>
            <div class="pan-visuel"><img class="photo" src="{{ asset('images/cible/campagne-5.jpg') }}" alt="Activation street marketing CIBLE"></div>
        </div>
    </div>
</section>

{{-- ═══ MISSION ═══ --}}
<section class="mission rev">
    <p>Nous conceptualisons des campagnes audacieuses et créatives, pensées pour votre public, afin de maximiser visibilité et impact. Nous déposons votre message au cœur de l'expérience urbaine et digitale, là où il résonne le plus. Chaque levier est pensé pour engager vos audiences et amplifier votre visibilité.</p>
    <p class="fort">Notre mission : transformer chaque message en une rencontre mémorable, et chaque campagne en un moteur de croissance — jusqu'au point de vente.</p>
</section>

{{-- ═══ RÉALISATIONS ═══ --}}
<section class="travaux" id="travaux">
    <div class="couche">
        <span class="fleche" style="--c:var(--gris);--op:1;bottom:8%;left:-2%;width:170px;--r:-8deg;--dur:30s;--del:.3s"><svg viewBox="0 0 202 286"><use href="#fh-droite"/></svg></span>
    </div>
    <div class="travaux-tete rev">
        <div>
            <span class="sur">Réalisations</span>
            <h2 class="t1" style="margin-top:12px">Nos campagnes, en vrai.</h2>
        </div>
        <a class="bouton b-ligne" href="{{ route('cible.references') }}">Voir toutes les réalisations<svg class="fl" viewBox="0 0 177 119"><use href="#fh-droite-mini"/></svg></a>
    </div>
    <div class="grille rev">
        <article class="carte" style="--c:var(--violet)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-1.jpg') }}" alt="Campagne Orange — brand experience"></div><h4>Orange</h4><span><i class="pastille"></i>Brand experience</span></article>
        <article class="carte" style="--c:var(--vert)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-2.jpg') }}" alt="Cofina — film institutionnel"></div><h4>Cofina</h4><span><i class="pastille"></i>Film institutionnel</span></article>
        <article class="carte" style="--c:var(--bleu)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-3.jpg') }}" alt="Snedai — stratégie 360°"></div><h4>Snedai</h4><span><i class="pastille"></i>Stratégie 360°</span></article>
        <article class="carte" style="--c:var(--jaune)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-4.jpg') }}" alt="SGS / SICTA — création & réseaux sociaux"></div><h4>SGS · SICTA</h4><span><i class="pastille"></i>Création &amp; réseaux sociaux</span></article>
        <article class="carte" style="--c:var(--rouge)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-5.jpg') }}" alt="IFG — stand expérientiel"></div><h4>IFG</h4><span><i class="pastille"></i>Stand expérientiel</span></article>
        <article class="carte" style="--c:var(--violet)"><div class="vign"><img class="photo" src="{{ asset('images/cible/campagne-6.jpg') }}" alt="SIGFU — design & architecture"></div><h4>SIGFU</h4><span><i class="pastille"></i>Design &amp; architecture</span></article>
    </div>
</section>

{{-- ═══ RÉSEAU (bleu) ═══ --}}
<section class="reseau" id="reseau">
    <div class="couche">
        <span class="fleche" style="--c:#fff;--op:.13;top:4%;left:34%;width:150px;--r:22deg;--dur:25s;--del:.2s"><svg viewBox="0 0 189 297"><use href="#fb-gauche"/></svg></span>
        <span class="fleche" style="--c:#fff;--op:.12;bottom:6%;right:4%;width:120px;--r:-12deg;--dur:29s;--del:.5s"><svg viewBox="0 0 204 442"><use href="#plume"/></svg></span>
    </div>
    <div class="reseau-grille">
        <div class="rev">
            <span class="sur" style="opacity:.85">La preuve</span>
            <h2 class="t1" style="margin-top:14px">Un réseau d'affichage en propre, à Abidjan et dans tout le pays.</h2>
            <p>Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : 364 panneaux répartis dans 31 communes, de Bouaké à San-Pédro. C'est ce qui nous permet de vous garantir un emplacement, une date de pose et une preuve photo — pas une estimation.</p>
            <div class="stats">
                <div><div class="v num" data-cible="180">0</div><div class="l">Panneaux · Abidjan<br>14 communes</div></div>
                <div><div class="v num" data-cible="184">0</div><div class="l">Panneaux · Intérieur<br>17 villes</div></div>
            </div>
            <a class="bouton b-blanc" style="margin-top:34px" href="{{ route('cible.reseau') }}">Explorer la carte du réseau<svg class="fl" viewBox="0 0 177 119"><use href="#fh-droite-mini"/></svg></a>
        </div>
        <div class="rev">
            <div class="carte-slot"><img class="photo" src="{{ asset('images/cible/hero-plateau-night.jpg') }}" alt="Réseau CIBLE — Abidjan by night"></div>
            <p class="note">Réseau détaillé et carte interactive sur la page <a href="{{ route('cible.reseau') }}" style="text-decoration:underline">Notre réseau</a>.</p>
        </div>
    </div>
</section>

{{-- ═══ DISTINCTIONS ═══ --}}
<section class="dist-sec rev">
    <span class="sur">Reconnaissances officielles</span>
    <h2 class="t2" style="margin-top:12px">Trois distinctions de l'État ivoirien.</h2>
    <div class="dist-grille">
        <div class="dist" style="--c:var(--jaune)"><div class="an num">2016</div><h4>2<sup>e</sup> prix du meilleur publicitaire</h4><p>Distinction professionnelle du secteur de la publicité ivoirienne.</p></div>
        <div class="dist" style="--c:var(--vert)"><div class="an num">2019</div><h4>Chevalier de l'Ordre du Mérite de la Communication</h4><p>Reconnaissance de la contribution à la structuration du métier.</p></div>
        <div class="dist" style="--c:var(--violet)"><div class="an num">2020</div><h4>Officier de l'Ordre du Mérite National</h4><p>Distinction républicaine pour services rendus au pays.</p></div>
    </div>
</section>

{{-- ═══ CONTACT (jaune + motif plumes) ═══ --}}
<section class="contact-home" id="contact">
    <div class="motif" aria-hidden="true"></div>
    <div class="contact-inner rev">
        <h2 class="t1">Entrons en contact.</h2>
        <p>Décrivez votre besoin en deux minutes. Notre équipe commerciale vous rappelle dans la journée ouvrée avec une proposition chiffrée.</p>
        <a class="bouton b-rouge" style="margin-top:28px" href="{{ route('cible.contact') }}">Demander un devis<svg class="fl" viewBox="0 0 177 119"><use href="#fh-droite-mini"/></svg></a>
        <div class="coord">
            <a href="tel:+2250700780628" class="num">+225 07 00 78 06 28</a>
            <a href="mailto:commercial@cible-ci.com">commercial@cible-ci.com</a>
            <a href="{{ route('cible.contact') }}">Formulaire de contact</a>
        </div>
    </div>
</section>

@endsection

@push('page-js')
<script>
(function(){
    const doux = matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ─── territoires : couleur de scène + balayage flèches + glissement contenu ───
    const teintes = {'t-rue':'--rouge','t-mouvement':'--jaune','t-ecran':'--vert','t-digital':'--bleu','t-terrain':'--violet'};
    const scene = document.getElementById('scene');
    const onglets = [...document.querySelectorAll('.onglet')];
    function anime(el){
        if(el.dataset.brut){el.textContent=el.dataset.brut;return;}
        const fin=parseInt(el.dataset.cible,10);
        if(doux){el.textContent=fin;return;}
        const t0=performance.now(),d=1150;
        const pas=t=>{const p=Math.min((t-t0)/d,1);el.textContent=Math.round(fin*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(pas);};
        requestAnimationFrame(pas);
    }
    function activer(i,premier){
        onglets.forEach((o,j)=>{
            const a=i===j, id=o.getAttribute('aria-controls'), p=document.getElementById(id);
            o.setAttribute('aria-selected',a); o.tabIndex=a?0:-1; p.hidden=!a;
            if(a){
                scene.style.background=`var(${teintes[id]})`;
                p.querySelectorAll('[data-cible],[data-brut]').forEach(anime);
                if(!doux){
                    p.classList.remove('entre'); void p.offsetWidth; p.classList.add('entre');
                    if(!premier){scene.classList.remove('switch'); void scene.offsetWidth; scene.classList.add('switch');}
                }
            }
        });
    }
    onglets.forEach((o,i)=>{
        o.addEventListener('click',()=>activer(i));
        o.addEventListener('keydown',e=>{
            if(e.key!=='ArrowRight'&&e.key!=='ArrowLeft')return;
            e.preventDefault();
            const n=(i+(e.key==='ArrowRight'?1:-1)+onglets.length)%onglets.length;
            activer(n); onglets[n].focus();
        });
    });
    if(onglets.length) activer(0,true);

    // ─── parallaxe légère des flèches du héro ───
    if(!doux){
        const couches=[...document.querySelectorAll('[data-parallaxe] .fleche')];
        let tic=false;
        addEventListener('scroll',()=>{
            if(tic)return; tic=true;
            requestAnimationFrame(()=>{
                const y=scrollY;
                couches.forEach((el,k)=>{el.style.marginTop=(y*(0.05+k*0.028)*-1).toFixed(1)+'px';});
                tic=false;
            });
        },{passive:true});
    }
})();
</script>
@endpush

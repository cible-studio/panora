/*!
 * CIBLE — univers 3D procédural (Three.js r149, script classique : fonctionne aussi en file://).
 * Îles flottantes, dispositifs d'affichage, véhicules, créas, nuages et perroquet sont
 * générés en code. Les créas respectent la charte « Opération Plume Rouge » :
 * aplats rouge/jaune/blanc, logo officiel détouré de blanc (comme sur les BAT), flèches du
 * symbole en motif. Le panneau de l'ouverture affiche le BAT « Tout le monde te voit ici ».
 */
(function (global) {
  'use strict';

  var T = global.THREE;
  if (!T) { global.CibleMonde = null; return; }
  T.ColorManagement.legacyMode = false;

  var M = global.CIBLE_MARQUE || {};
  var TITRE = '"Cible Titre", "Poppins", "Segoe UI", Arial, sans-serif';
  var C = { rouge: '#E20613', jaune: '#FAB80B', vert: '#3AA835', bleu: '#3F7FC0', violet: '#81358A', noir: '#111111', gris: '#E6E6E6', blanc: '#FFFFFF' };

  /* ================================================================== */
  /* Outils                                                              */
  /* ================================================================== */
  function rng(seed) {
    var a = (Math.imul(seed | 0, 2654435761) ^ 0x9e3779b9) >>> 0;
    return function () {
      a = (a + 0x6D2B79F5) >>> 0;
      var t = a;
      t = Math.imul(t ^ (t >>> 15), t | 1);
      t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function hash3(x, y, z, s) {
    var h = Math.imul(x | 0, 374761393) ^ Math.imul(y | 0, 668265263) ^ Math.imul(z | 0, 1274126177) ^ Math.imul(s | 0, 2246822519);
    h = Math.imul(h ^ (h >>> 13), 3266489917);
    h ^= h >>> 16;
    return (h >>> 0) / 4294967296;
  }
  function clamp(x, a, b) { return x < a ? a : x > b ? b : x; }
  function smooth(a, b, x) { var t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function ease(t) { t = clamp(t, 0, 1); return t * t * t * (t * (t * 6 - 15) + 10); }

  function jitter(geo, amt, seed, ay) {
    var p = geo.attributes.position, v = new T.Vector3();
    for (var i = 0; i < p.count; i++) {
      v.fromBufferAttribute(p, i);
      var kx = Math.round(v.x * 1000), ky = Math.round(v.y * 1000), kz = Math.round(v.z * 1000);
      p.setXYZ(i,
        v.x + (hash3(kx, ky, kz, seed) - 0.5) * amt,
        v.y + (hash3(kx, ky, kz, seed + 17) - 0.5) * amt * (ay == null ? 1 : ay),
        v.z + (hash3(kx, ky, kz, seed + 41) - 0.5) * amt);
    }
    p.needsUpdate = true;
    return geo;
  }
  function smoothNormals(geo) {
    if (geo.index) geo = geo.toNonIndexed();
    var p = geo.attributes.position, n = p.count, acc = new Map();
    var a = new T.Vector3(), b = new T.Vector3(), c = new T.Vector3(), cb = new T.Vector3(), ab = new T.Vector3();
    function key(i) { return Math.round(p.getX(i) * 500) + ',' + Math.round(p.getY(i) * 500) + ',' + Math.round(p.getZ(i) * 500); }
    for (var i = 0; i < n; i += 3) {
      a.fromBufferAttribute(p, i); b.fromBufferAttribute(p, i + 1); c.fromBufferAttribute(p, i + 2);
      cb.subVectors(c, b); ab.subVectors(a, b); cb.cross(ab);
      for (var k = 0; k < 3; k++) {
        var kk = key(i + k), s = acc.get(kk);
        if (!s) { s = [0, 0, 0]; acc.set(kk, s); }
        s[0] += cb.x; s[1] += cb.y; s[2] += cb.z;
      }
    }
    var out = new Float32Array(n * 3);
    for (var j = 0; j < n; j++) {
      var s2 = acc.get(key(j)), l = Math.hypot(s2[0], s2[1], s2[2]) || 1;
      out[j * 3] = s2[0] / l; out[j * 3 + 1] = s2[1] / l; out[j * 3 + 2] = s2[2] / l;
    }
    geo.setAttribute('normal', new T.BufferAttribute(out, 3));
    return geo;
  }
  function cvs(w, h) { var c = document.createElement('canvas'); c.width = w; c.height = h; return c; }
  function tex(c, repeat) {
    var t = new T.CanvasTexture(c);
    t.encoding = T.sRGBEncoding;
    t.anisotropy = 8;
    if (repeat) { t.wrapS = t.wrapT = T.RepeatWrapping; t.repeat.set(repeat[0], repeat[1]); }
    return t;
  }

  /* ================================================================== */
  /* Marque : logo, flèches, typographie                                 */
  /* ================================================================== */
  var LOGO = new Image();
  if (M.logo) LOGO.src = M.logo.src;
  var LOGO_R = M.logo ? M.logo.w / M.logo.h : 1.875;
  var B = global.CIBLE_BAT || null, BAT = new Image();
  if (B) BAT.src = B.src;
  var SILHOUETTE = null;
  function silhouette() {
    if (SILHOUETTE || !(LOGO.complete && LOGO.naturalWidth)) return SILHOUETTE;
    var c = cvs(LOGO.naturalWidth, LOGO.naturalHeight), g = c.getContext('2d');
    g.drawImage(LOGO, 0, 0);
    g.globalCompositeOperation = 'source-in';
    g.fillStyle = C.blanc; g.fillRect(0, 0, c.width, c.height);
    return (SILHOUETTE = c);
  }
  var FLECHES = null;
  function fleches() {
    if (!FLECHES) FLECHES = (M.fleche && M.fleche.d || []).map(function (d) { return new Path2D(d); });
    return FLECHES;
  }
  function police(g, poids, px) { g.font = poids + ' ' + Math.round(px) + 'px ' + TITRE; }
  /* Logo officiel. mode « contour » : détouré d'un liseré blanc, tel qu'il figure sur les BAT CIBLE. */
  function logo(g, x, y, h, mode) {
    var w = h * LOGO_R;
    if (mode === 'contour' && silhouette()) {
      var e = h * 0.045;
      for (var i = 0; i < 24; i++) { var a = i / 24 * Math.PI * 2; g.drawImage(SILHOUETTE, x + Math.cos(a) * e, y + Math.sin(a) * e, w, h); }
    }
    if (LOGO.complete && LOGO.naturalWidth) g.drawImage(LOGO, x, y, w, h);
    return w;
  }
  /* Motif : flèches officielles du symbole graphique, en semis régulier. */
  function motif(g, W, H, couleur, alpha, taille) {
    var paths = fleches();
    if (!paths.length) return;
    var fw = M.fleche.w, fh = M.fleche.h, s = taille / fw, sx = taille * 1.85, sy = taille * 1.25, row = 0;
    g.save();
    g.globalAlpha = alpha; g.fillStyle = couleur;
    for (var y = -taille; y < H + taille; y += sy, row++) {
      for (var x = (row % 2) * sx / 2 - taille; x < W + taille; x += sx) {
        g.save();
        g.translate(x, y); g.rotate(row % 2 ? 2.7 : -0.45); g.scale(s, s); g.translate(-fw / 2, -fh / 2);
        for (var i = 0; i < paths.length; i++) g.fill(paths[i]);
        g.restore();
      }
    }
    g.restore();
  }
  /* Créa générique : aplat, motif, lignes de texte, logo. Coordonnées relatives (0–1). */
  function crea(o) {
    var W = o.w, H = o.h, c = cvs(W, H), g = c.getContext('2d'), U = Math.min(W, H);
    g.fillStyle = o.fond; g.fillRect(0, 0, W, H);
    if (o.motif) motif(g, W, H, o.motif.c || C.blanc, o.motif.a || 0.12, U * (o.motif.s || 0.2));
    if (o.bloc) { g.fillStyle = o.bloc.c; g.fillRect(o.bloc.x * W, o.bloc.y * H, o.bloc.w * W, o.bloc.h * H); }
    if (o.lignes && o.lignes.length) {
      var size = U * o.taille, maxW = W * (o.maxW || 0.6);
      police(g, 800, size);
      o.lignes.forEach(function (t) { while (g.measureText(t).width > maxW && size > 8) { size *= 0.96; police(g, 800, size); } });
      g.fillStyle = o.couleur || C.blanc; g.textBaseline = 'alphabetic'; g.textAlign = o.aligne || 'left';
      var x = W * o.x, y = H * o.y;
      o.lignes.forEach(function (t) { y += size * 0.92; g.fillText(t, x, y); y += size * 0.06; });
      if (o.sous) {
        var ss = U * (o.sousTaille || 0.06);
        police(g, 600, ss);
        g.fillStyle = o.sousCouleur || o.couleur || C.blanc;
        g.fillText(o.sous, x, y + ss * 1.45);
      }
    }
    if (o.triangle) {
      var t = o.triangle;
      g.fillStyle = t.c; g.beginPath();
      g.moveTo(W * t.x, H * t.y - U * t.s); g.lineTo(W * t.x + U * t.s, H * t.y + U * t.s * 0.7); g.lineTo(W * t.x - U * t.s, H * t.y + U * t.s * 0.7);
      g.closePath(); g.fill();
    }
    if (o.logo) logo(g, W * o.logo.x, H * o.logo.y, U * o.logo.h, o.logo.mode);
    if (o.pixel) {
      g.fillStyle = 'rgba(0,0,0,0.28)';
      for (var px = 0; px < W; px += o.pixel) g.fillRect(px, 0, o.pixel * 0.24, H);
      for (var py = 0; py < H; py += o.pixel) g.fillRect(0, py, W, o.pixel * 0.24);
    }
    return c;
  }

  /* ================================================================== */
  /* Déclinaisons du BAT « Tout le monde te voit ici »                   */
  /* ================================================================== */
  /* Toutes les faces publicitaires portent le BAT fourni par CIBLE. Quand la face
     a ses proportions (2:1 à 2,9:1), le BAT est affiché tel quel. Sinon, ses
     éléments (titre, numéros, plumes) sont détourés une fois depuis l'image du BAT
     et recomposés au format du support, avec le logo officiel et la grande flèche
     translucide : même lettrage, mêmes couleurs, même hiérarchie. */
  var BAT_ROUGE = 'rgb(227,18,27)', FLECHE_ALPHA = 0.19;
  var PIECES = null;
  function batPret() { return !!(B && BAT.complete && BAT.naturalWidth); }
  function pieces() {
    if (PIECES !== null || !batPret()) return PIECES;
    // Découpes relevées sur le BAT 1600 × 711 ; un autre BAT est affiché entier.
    if (B.w !== 1600 || B.h !== 711) return (PIECES = false);
    var src = cvs(B.w, B.h), gs = src.getContext('2d');
    gs.drawImage(BAT, 0, 0, B.w, B.h);
    // Le canal vert sépare le blanc (255) et le rose des plumes (207) du rouge du fond (18) ;
    // pour le texte, la flèche translucide (19 % de blanc) est retirée.
    function detourer(x, y, w, h, rgb, haut, retirerFleche) {
      var d = gs.getImageData(x, y, w, h), p = d.data;
      for (var i = 0; i < p.length; i += 4) {
        var a = (p[i + 1] - 18) / (haut - 18);
        if (retirerFleche) a = (a - FLECHE_ALPHA) / (1 - FLECHE_ALPHA);
        p[i] = rgb[0]; p[i + 1] = rgb[1]; p[i + 2] = rgb[2]; p[i + 3] = Math.round(Math.max(0, Math.min(1, a)) * 255);
      }
      var c = cvs(w, h); c.getContext('2d').putImageData(d, 0, 0);
      return c;
    }
    var BLANC = [255, 255, 255], ROSE = [249, 207, 207];
    PIECES = {
      titre: detourer(129, 112, 703, 175, BLANC, 255, true),      // TOUT LE MONDE / TE VOIT ICI
      numeros: detourer(73, 366, 858, 230, BLANC, 255, true),     // 07 98 49 66 74 / 27 22 20 80 08
      plumeHaut: detourer(1388, 0, 212, 145, ROSE, 207),
      plumeBas: detourer(0, 612, 150, 99, ROSE, 207)
    };
    return PIECES;
  }
  var FLECHE_PLEINE = null;
  function flecheFond(g, W, H) {
    var paths = fleches();
    if (!paths.length) return;
    if (!FLECHE_PLEINE) {
      FLECHE_PLEINE = cvs(Math.ceil(M.fleche.w * 4), Math.ceil(M.fleche.h * 4));
      var f = FLECHE_PLEINE.getContext('2d');
      f.scale(4, 4); f.fillStyle = C.blanc;
      paths.forEach(function (p) { f.fill(p); });
    }
    var r = M.fleche.w / M.fleche.h, vertical = W < H;
    var L = vertical ? Math.min(H * 0.8, W * 1.25 * r) : Math.min(W * 0.8, H * 1.25 * r);
    g.save();
    g.globalAlpha = FLECHE_ALPHA;
    g.translate(W * (vertical ? 0.5 : 0.58), H * (vertical ? 0.5 : 0.55));
    if (vertical) g.rotate(-Math.PI / 2);
    g.drawImage(FLECHE_PLEINE, -L / 2, -L / r / 2, L, L / r);
    g.restore();
  }
  /* Mise en page : éléments { w, h, dessin }, empilés ou alignés, puis ajustés au cadre. */
  function elImage(c, k) { k = k || 1; return { w: c.width * k, h: c.height * k, dessin: function (g, x, y, w, h) { g.drawImage(c, x, y, w, h); } }; }
  function elLogo(h) { return { w: h * LOGO_R, h: h, dessin: function (g, x, y, w, hh) { logo(g, x, y, hh, 'contour'); } }; }
  function pile(items, gap) {
    var w = 0, h = gap * (items.length - 1);
    items.forEach(function (it) { w = Math.max(w, it.w); h += it.h; });
    return { w: w, h: h, dessin: function (g, x, y, W2) {
      var s = W2 / w, yy = y;
      items.forEach(function (it) { it.dessin(g, x + (W2 - it.w * s) / 2, yy, it.w * s, it.h * s); yy += (it.h + gap) * s; });
    } };
  }
  function rangee(items, gap) {
    var w = gap * (items.length - 1), h = 0;
    items.forEach(function (it) { w += it.w; h = Math.max(h, it.h); });
    return { w: w, h: h, dessin: function (g, x, y, W2, H2) {
      var s = W2 / w, xx = x;
      items.forEach(function (it) { it.dessin(g, xx, y + (H2 - it.h * s) / 2, it.w * s, it.h * s); xx += (it.w + gap) * s; });
    } };
  }
  function placer(g, W, H, bloc, mx, my) {
    var s = Math.min(W * (1 - 2 * mx) / bloc.w, H * (1 - 2 * my) / bloc.h);
    bloc.dessin(g, (W - bloc.w * s) / 2, (H - bloc.h * s) / 2, bloc.w * s, bloc.h * s);
  }
  /* variante : 'complet' (titre + numéros + logo), 'titre' (titre + logo), 'numeros' (numéros + logo). */
  function batCrea(W, H, variante) {
    variante = variante || 'complet';
    var c = cvs(W, H), g = c.getContext('2d'), r = W / H, P = pieces();
    g.fillStyle = (B && B.fond) || BAT_ROUGE; g.fillRect(0, 0, W, H);
    if (!batPret()) {
      // BAT pas encore chargé : version dessinée du même message.
      return crea({ w: W, h: H, fond: BAT_ROUGE, lignes: ['TOUT LE MONDE', 'TE VOIT ICI'], taille: 0.14, x: 0.06, y: 0.12, maxW: 0.55, logo: { mode: 'contour', x: 0.62, y: 0.3, h: 0.34 } });
    }
    if (!P || (variante === 'complet' && r >= 1.85 && r <= 2.9)) {
      // Proportions proches du BAT (ou BAT d'une autre mise en page) : l'original, entier.
      var k = Math.min(W / B.w, H / B.h);
      g.drawImage(BAT, (W - B.w * k) / 2, (H - B.h * k) / 2, B.w * k, B.h * k);
      return c;
    }
    flecheFond(g, W, H);
    var kp = Math.min(H * 0.2 / P.plumeHaut.height, W * 0.36 / P.plumeHaut.width), hPlume = P.plumeHaut.height * kp;
    g.drawImage(P.plumeHaut, W - P.plumeHaut.width * kp, 0, P.plumeHaut.width * kp, hPlume);
    g.drawImage(P.plumeBas, 0, H - P.plumeBas.height * kp, P.plumeBas.width * kp, P.plumeBas.height * kp);
    var T = elImage(P.titre), N = elImage(P.numeros), bloc, my = 0.1;
    var texte = variante === 'titre' ? elImage(P.titre, 1.25) : variante === 'numeros' ? N : pile([T, N], 80);
    if (r >= 3.2) {
      // Bandeaux (bus, enseignes, flancs) : tout sur une ligne.
      bloc = rangee(variante === 'complet' ? [T, N, elLogo(300)] : [texte, elLogo(300)], 110);
      my = 0.14;
    } else if (r >= 1.45) {
      // Paysage : la mise en page du BAT (texte à gauche, logo à droite).
      bloc = rangee([texte, elLogo(variante === 'complet' ? 300 : 260)], 90);
    } else {
      // Carré et portrait : texte puis logo, empilés ; en portrait, la hauteur est répartie.
      var items = variante === 'complet' ? [T, N, elLogo(r < 0.8 ? 420 : 300)] : [texte, elLogo(r < 0.8 ? 420 : 300)];
      // Formats hauts et faces à gros titre : le texte reste sous la plume du coin haut.
      my = r < 1.2 || variante !== 'complet' ? Math.max(0.08, hPlume / H + 0.02) : 0.1;
      var gap = 70;
      if (r < 0.8) {
        var base = pile(items, 0), s = W * 0.86 / base.w;
        gap = Math.max(70, (H * (1 - 2 * my) / s - base.h) / (items.length - 1));
      }
      bloc = pile(items, gap);
    }
    placer(g, W, H, bloc, 0.07, my);
    return c;
  }

  var CREAS = {
    /* BAT « Tout le monde te voit ici » (panneau de l'ouverture, aux proportions du BAT). */
    bat: function () {
      if (!batPret()) return batCrea(1600, 711);
      var c = cvs(B.w, B.h);
      c.getContext('2d').drawImage(BAT, 0, 0, B.w, B.h);
      return c;
    },
    /* Chaque support reçoit le BAT à ses propres proportions (largeur × hauteur de la face). */
    pano: function () { return batCrea(2048, 731); },                 // panoramique 14 × 5
    classique: function () { return batCrea(1024, 768); },            // 4 × 3
    classiqueLarge: function () { return batCrea(2048, 820); },
    led1: function () { return batCrea(1024, 546); },                 // écrans 6 × 3,2 : le BAT en trois temps
    led2: function () { return batCrea(1024, 546, 'titre'); },
    led3: function () { return batCrea(1024, 546, 'numeros'); },
    triA: function () { return batCrea(1024, 725); },                 // trivision 4,8 × 3,4 : trois faces
    triB: function () { return batCrea(1024, 725, 'titre'); },
    triC: function () { return batCrea(1024, 725, 'numeros'); },
    lumipub: function () { return batCrea(1024, 654); },              // caisson 3,6 × 2,3
    magasin: function () { return batCrea(540, 960); },               // écran vertical en magasin
    camion: function () { return batCrea(1536, 640); },               // caisson 6 × 2,5
    camionArriere: function () { return batCrea(704, 768); },         // arrière 2,2 × 2,4
    coffre: function () { return batCrea(600, 528); },                // coffre de moto 0,5 × 0,44
    taxi: function () { return batCrea(1024, 310); },                 // lanterne de taxi
    enseigne: function () { return batCrea(2048, 302); },             // bandeau de vitrine 4,2 × 0,62
    van: function () { return batCrea(1600, 729); },                  // flanc 3,4 × 1,55
    bus: function () { return batCrea(2048, 274); },                  // flanc 8,6 × 1,15
    chevalet: function () { return batCrea(600, 805); },              // 1,46 × 1,96
    rollup: function () { return batCrea(400, 1024); },               // 0,82 × 2,1
    standFond: function () { return batCrea(1600, 667); },            // fond de stand 7,2 × 3
    jupe: function () { return batCrea(2048, 198); },                 // bordure de scène 6,4 × 0,62
    comptoir: function () { return batCrea(1600, 576); },             // 2,5 × 0,9
    decor: function () { return batCrea(2048, 444); }                 // flancs de voiture 3,6 × 0,78
  };

  /* ================================================================== */
  /* Textures de décor                                                   */
  /* ================================================================== */
  function wrapDraw(S, x, y, r, fn) {
    for (var ox = -1; ox <= 1; ox++) for (var oy = -1; oy <= 1; oy++) {
      var X = x + ox * S, Y = y + oy * S;
      if (X + r < 0 || X - r > S || Y + r < 0 || Y - r > S) continue;
      fn(X, Y);
    }
  }
  function grassMap() {
    var S = 512, c = cvs(S, S), g = c.getContext('2d'), r = rng(3);
    g.fillStyle = '#3f9535'; g.fillRect(0, 0, S, S);
    for (var i = 0; i < 80; i++) {
      var x = r() * S, y = r() * S, rad = 30 + r() * 90, col = r() > 0.5 ? '44,118,38' : '96,170,64';
      wrapDraw(S, x, y, rad, function (X, Y) {
        var gr = g.createRadialGradient(X, Y, 0, X, Y, rad);
        gr.addColorStop(0, 'rgba(' + col + ',0.30)'); gr.addColorStop(1, 'rgba(' + col + ',0)');
        g.fillStyle = gr; g.fillRect(X - rad, Y - rad, rad * 2, rad * 2);
      });
    }
    var cols = ['#378a2f', '#55a843', '#2f7f2a', '#64b44c', '#2a7426'];
    for (var j = 0; j < 12000; j++) { g.globalAlpha = 0.45 + r() * 0.55; g.fillStyle = cols[j % cols.length]; g.fillRect(r() * S, r() * S, 1.6, 2 + r() * 3.5); }
    g.globalAlpha = 1;
    return tex(c, [0.1, 0.1]);
  }
  var ROAD_CANVAS = {};
  function roadCanvas(lanes, median) {
    var key = lanes + (median ? 'm' : '');
    if (ROAD_CANVAS[key]) return ROAD_CANVAS[key];
    var W = 1024, H = 256, c = cvs(W, H), g = c.getContext('2d'), r = rng(9 + lanes);
    g.fillStyle = '#454a52'; g.fillRect(0, 0, W, H);
    for (var i = 0; i < 26000; i++) { g.fillStyle = r() > 0.5 ? 'rgba(255,255,255,0.07)' : 'rgba(0,0,0,0.13)'; g.fillRect(r() * W, r() * H, 1.5, 1.5); }
    for (var l = 0; l < lanes; l++) {
      var cy = H * (0.06 + 0.88 * (l + 0.5) / lanes), lw = H * 0.88 / lanes;
      g.fillStyle = 'rgba(18,20,24,0.16)'; g.fillRect(0, cy - lw * 0.32, W, lw * 0.14); g.fillRect(0, cy + lw * 0.18, W, lw * 0.14);
    }
    var e = Math.max(5, H * 0.026);
    g.fillStyle = '#f3f2ec';
    g.fillRect(0, H * 0.055, W, e); g.fillRect(0, H * 0.945 - e, W, e);
    for (var k = 1; k < lanes; k++) {
      var y = H * (0.055 + 0.89 * k / lanes) - e / 2;
      if (median && k === lanes / 2) {
        g.fillStyle = '#f0c232'; g.fillRect(0, y - e * 0.9, W, e * 0.75); g.fillRect(0, y + e * 1.15, W, e * 0.75); g.fillStyle = '#f3f2ec';
      } else for (var x = 0; x < W; x += W / 3) g.fillRect(x + W * 0.04, y, W * 0.11, e);
    }
    ROAD_CANVAS[key] = c;
    return c;
  }
  var PAVING;
  function pavingCanvas() {
    if (PAVING) return PAVING;
    var S = 256, c = cvs(S, S), g = c.getContext('2d'), r = rng(21);
    g.fillStyle = '#b9b3a8'; g.fillRect(0, 0, S, S);
    for (var y = 0; y < 8; y++) for (var x = 0; x < 8; x++) {
      var v = 208 + Math.floor(r() * 24);
      g.fillStyle = 'rgb(' + v + ',' + (v - 5) + ',' + (v - 14) + ')';
      g.fillRect(x * 32 + 1.5, y * 32 + 1.5, 29, 29);
    }
    PAVING = c;
    return c;
  }
  function facadeCanvas(o) {
    var W = 512, H = 512, c = cvs(W, H), g = c.getContext('2d'), r = rng(o.seed || 5);
    g.fillStyle = o.wall || '#efe5d3'; g.fillRect(0, 0, W, H);
    var cw = W / 4, rh = H / 4;
    for (var y = 0; y < 4; y++) {
      g.fillStyle = 'rgba(60,40,20,0.07)'; g.fillRect(0, y * rh + rh - 8, W, 8);
      for (var x = 0; x < 4; x++) {
        var wx = x * cw + cw * 0.17, wy = y * rh + rh * 0.18, ww = cw * 0.66, wh = rh * 0.56;
        g.fillStyle = o.trim || '#d9ccb4'; g.fillRect(wx - 5, wy - 5, ww + 10, wh + 10);
        var gr = g.createLinearGradient(wx, wy, wx + ww, wy + wh);
        gr.addColorStop(0, '#7eb3df'); gr.addColorStop(0.45, '#3a6c9e'); gr.addColorStop(1, '#203f63');
        g.fillStyle = gr; g.fillRect(wx, wy, ww, wh);
        g.fillStyle = 'rgba(255,255,255,0.22)'; g.beginPath(); g.moveTo(wx, wy + wh * 0.55); g.lineTo(wx + ww * 0.45, wy); g.lineTo(wx + ww * 0.7, wy); g.lineTo(wx, wy + wh); g.closePath(); g.fill();
        if (r() < 0.3) { g.fillStyle = 'rgba(246,240,226,0.9)'; g.fillRect(wx + ww * 0.52, wy, ww * 0.48, wh); }
      }
    }
    return c;
  }
  function cloudTexture(seed) {
    var W = 512, H = 256, c = cvs(W, H), g = c.getContext('2d'), r = rng(seed), puffs = [], n = 16;
    for (var i = 0; i < n; i++) {
      var t = i / (n - 1), x = W * (0.16 + 0.68 * t + (r() - 0.5) * 0.06), hump = Math.sin(Math.PI * t);
      var rad = H * (0.1 + 0.2 * hump * (0.6 + r() * 0.6)), y = H * 0.7 - rad * (0.55 + r() * 0.5) - hump * H * 0.05;
      puffs.push([x, y, rad]);
    }
    puffs.forEach(function (p) {
      var gr = g.createRadialGradient(p[0], p[1] + p[2] * 0.35, 0, p[0], p[1] + p[2] * 0.35, p[2] * 1.15);
      gr.addColorStop(0, 'rgba(178,204,232,0.95)'); gr.addColorStop(0.55, 'rgba(190,212,236,0.7)'); gr.addColorStop(1, 'rgba(200,220,240,0)');
      g.fillStyle = gr; g.fillRect(0, 0, W, H);
    });
    puffs.forEach(function (p) {
      var gr = g.createRadialGradient(p[0] - p[2] * 0.2, p[1] - p[2] * 0.28, 0, p[0], p[1] - p[2] * 0.1, p[2] * 1.02);
      gr.addColorStop(0, 'rgba(255,255,255,1)'); gr.addColorStop(0.5, 'rgba(255,255,255,0.9)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
      g.fillStyle = gr; g.fillRect(0, 0, W, H);
    });
    g.globalCompositeOperation = 'destination-out';
    var fade = g.createLinearGradient(0, H * 0.66, 0, H * 0.86);
    fade.addColorStop(0, 'rgba(0,0,0,0)'); fade.addColorStop(1, 'rgba(0,0,0,1)');
    g.fillStyle = fade; g.fillRect(0, H * 0.66, W, H * 0.34);
    var side = g.createLinearGradient(0, 0, W, 0);
    side.addColorStop(0, 'rgba(0,0,0,1)'); side.addColorStop(0.1, 'rgba(0,0,0,0)'); side.addColorStop(0.9, 'rgba(0,0,0,0)'); side.addColorStop(1, 'rgba(0,0,0,1)');
    g.fillStyle = side; g.fillRect(0, 0, W, H);
    return tex(c);
  }
  function envMap(renderer) {
    var c = cvs(512, 256), g = c.getContext('2d');
    var gr = g.createLinearGradient(0, 0, 0, 256);
    gr.addColorStop(0, '#1f63cf'); gr.addColorStop(0.3, '#4b97e8'); gr.addColorStop(0.47, '#bfe2fb'); gr.addColorStop(0.5, '#eef8ff');
    gr.addColorStop(0.53, '#9aa983'); gr.addColorStop(0.7, '#7b6a50'); gr.addColorStop(1, '#4e4436');
    g.fillStyle = gr; g.fillRect(0, 0, 512, 256);
    var s = g.createRadialGradient(150, 62, 0, 150, 62, 46);
    s.addColorStop(0, 'rgba(255,250,236,1)'); s.addColorStop(1, 'rgba(255,250,236,0)');
    g.fillStyle = s; g.fillRect(0, 0, 512, 256);
    var t = new T.CanvasTexture(c);
    t.mapping = T.EquirectangularReflectionMapping; t.encoding = T.sRGBEncoding;
    var pm = new T.PMREMGenerator(renderer), rt = pm.fromEquirectangular(t);
    pm.dispose(); t.dispose();
    return rt.texture;
  }

  /* ================================================================== */
  /* Matériaux partagés                                                  */
  /* ================================================================== */
  var KIT = null;
  function kit() {
    if (KIT) return KIT;
    var k = KIT = {}, cache = {};
    function std(color, rough, metal, extra) {
      var p = { color: color, roughness: rough, metalness: metal || 0 };
      if (extra) for (var key in extra) p[key] = extra[key];
      return new T.MeshStandardMaterial(p);
    }
    k.std = std;
    k.paint = function (color, rough, metal) {
      var key = color + '|' + rough + '|' + metal;
      return cache[key] || (cache[key] = std(color, rough == null ? 0.7 : rough, metal || 0));
    };
    k.grass = std('#ffffff', 1, 0, { map: grassMap(), envMapIntensity: 0.55 });
    k.grassSide = std('#3d8f33', 1, 0, { envMapIntensity: 0.6 });
    k.soil = std('#c4632f', 0.95, 0, { flatShading: true });
    k.soil2 = std('#a84b22', 0.95, 0, { flatShading: true });
    k.rock = std('#ffffff', 0.95, 0, { vertexColors: true, flatShading: true });
    k.pebble = std('#93431f', 0.95, 0, { flatShading: true });
    k.leaves = ['#26702c', '#2f8436', '#3c9a3d', '#22622a', '#47a843'].map(function (c) { return std(c, 0.85, 0, { envMapIntensity: 0.7 }); });
    k.trunk = std('#6b4a2e', 0.9);
    k.palmTrunk = std('#8c7558', 0.9);
    k.palmLeaf = std('#3e9a41', 0.7, 0, { side: T.DoubleSide });
    k.asphalt = std('#30343a', 0.92);
    k.curb = std('#dedad2', 0.85);
    k.concrete = std('#cbc5bb', 0.9);
    k.steel = std('#a3afbd', 0.36, 0.85);
    k.steelDark = std('#5a6470', 0.45, 0.8);
    k.frame = std('#2c333d', 0.5, 0.65);
    k.grating = std('#76818e', 0.6, 0.7);
    k.white = std('#f2f1ed', 0.5);
    k.wood = std('#b7783f', 0.75);
    k.glass = std('#c4e6fb', 0.05, 0.2, { transparent: true, opacity: 0.3, depthWrite: false });
    k.carGlass = std('#18212c', 0.1, 0.65);
    k.tyre = std('#17191c', 0.85);
    k.chrome = std('#d9dde2', 0.25, 0.9);
    k.lampHead = std('#e2e6ea', 0.35, 0.55);
    k.lampGlow = new T.MeshBasicMaterial({ color: '#fff6da' });
    k.skin = std('#5b3a26', 0.6);
    k.tail = new T.MeshBasicMaterial({ color: '#ff3b30' });
    k.head = new T.MeshBasicMaterial({ color: '#fffbea' });
    k.rouge = std(C.rouge, 0.55);
    k.jaune = std(C.jaune, 0.55);
    k.noir = std('#1a1a1a', 0.6);
    k.blanc = std('#ffffff', 0.55);
    k.faces = {};
    return k;
  }
  /* Matériau d'une créa : lit = rétroéclairé (écran, caisson) ; transparent pour les décors. */
  function creaMat(nom, lit, transparent) {
    var k = kit(), key = nom + (lit ? '|lit' : '') + (transparent ? '|t' : '');
    if (k.faces[key]) return k.faces[key];
    var t = tex(CREAS[nom]()), m;
    if (lit) m = new T.MeshBasicMaterial({ map: t, toneMapped: false, transparent: !!transparent });
    else m = new T.MeshBasicMaterial({ map: t, toneMapped: false, color: '#f2f2f2', transparent: !!transparent });
    k.faces[key] = m;
    return m;
  }

  /* ================================================================== */
  /* Briques                                                             */
  /* ================================================================== */
  function mesh(geo, mat, x, y, z, parent, o) {
    var m = new T.Mesh(geo, mat);
    m.position.set(x || 0, y || 0, z || 0);
    m.castShadow = !(o && o.noCast);
    m.receiveShadow = !(o && o.noReceive);
    if (parent) parent.add(m);
    return m;
  }
  function box(w, h, d, mat, x, y, z, parent, o) { return mesh(new T.BoxGeometry(w, h, d), mat, x, y, z, parent, o); }
  function cyl(rt, rb, h, seg, mat, x, y, z, parent, o) { return mesh(new T.CylinderGeometry(rt, rb, h, seg || 12), mat, x, y, z, parent, o); }
  function plan(w, h, mat, x, y, z, ry, parent) { var m = mesh(new T.PlaneGeometry(w, h), mat, x, y, z, parent, { noCast: true }); if (ry) m.rotation.y = ry; return m; }
  function at(obj, x, y, z, ry) { obj.position.set(x, y, z); if (ry) obj.rotation.y = ry; return obj; }

  function roundedRect(w, d, r) {
    var s = new T.Shape(), x = -w / 2, y = -d / 2;
    s.moveTo(x + r, y); s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
    s.lineTo(x + w, y + d - r); s.quadraticCurveTo(x + w, y + d, x + w - r, y + d);
    s.lineTo(x + r, y + d); s.quadraticCurveTo(x, y + d, x, y + d - r);
    s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
    return s;
  }
  function slab(shape, thick, bevel, mats) {
    var geo = new T.ExtrudeGeometry(shape, { depth: thick, bevelEnabled: bevel > 0, bevelThickness: bevel, bevelSize: bevel, bevelSegments: 2, curveSegments: 10 });
    geo.rotateX(-Math.PI / 2);
    geo.translate(0, -(thick + bevel), 0);
    return mesh(geo, mats);
  }
  function rockGeo(rx, rz, hgt, seed) {
    var geo = new T.ConeGeometry(1, 1, 16, 7, true);
    geo.rotateX(Math.PI);
    var p = geo.attributes.position, v = new T.Vector3(), cols = new Float32Array(p.count * 3);
    var top = new T.Color('#c3612e'), mid = new T.Color('#97421f'), bot = new T.Color('#5a2814'), tmp = new T.Color();
    for (var i = 0; i < p.count; i++) {
      v.fromBufferAttribute(p, i);
      var t = v.y + 0.5, kx = Math.round(v.x * 1000), ky = Math.round(v.y * 1000), kz = Math.round(v.z * 1000);
      var n1 = hash3(kx, ky, kz, seed), n2 = hash3(kx, ky, kz, seed + 9), ang = Math.atan2(v.z, v.x);
      var bulge = 1 + 0.14 * Math.sin(ang * 3 + seed) * (1 - t) + (n1 - 0.5) * 0.3 * (1 - t * 0.7);
      var R = Math.pow(t, 0.6) * bulge, len = Math.hypot(v.x, v.z);
      var dx = len > 1e-5 ? v.x / len : 0, dz = len > 1e-5 ? v.z / len : 0;
      var yy = (t - 1) * hgt + (t < 0.98 && t > 0.02 ? (n2 - 0.5) * hgt * 0.07 : 0);
      p.setXYZ(i, dx * R * rx, yy, dz * R * rz);
      if (t > 0.5) tmp.copy(mid).lerp(top, (t - 0.5) / 0.5); else tmp.copy(bot).lerp(mid, t / 0.5);
      var d = 0.88 + n1 * 0.2;
      cols[i * 3] = tmp.r * d; cols[i * 3 + 1] = tmp.g * d; cols[i * 3 + 2] = tmp.b * d;
    }
    geo.setAttribute('color', new T.BufferAttribute(cols, 3));
    geo.computeVertexNormals();
    return geo;
  }
  function island(k, o) {
    var g = new T.Group(), w = o.w, d = o.d, r = o.r || Math.min(w, d) * 0.16, seed = o.seed || 1;
    var top = slab(roundedRect(w, d, r), 0.3, 0.14, [k.grass, k.grassSide]); top.castShadow = false; g.add(top);
    var s1 = slab(roundedRect(w - 0.25, d - 0.25, r), 1.3, 0.12, k.soil); s1.position.y = -0.58; s1.castShadow = false; g.add(s1);
    var s2 = slab(roundedRect(w - 1.6, d - 1.6, r * 0.9), 0.9, 0.12, k.soil2); s2.position.y = -2.12; s2.castShadow = false; g.add(s2);
    var depth = o.depth || Math.min(w, d) * 0.52;
    var rock = mesh(rockGeo((w - 1.6) * 0.47, (d - 1.6) * 0.47, depth, seed), k.rock, 0, -3.1, 0, g); rock.castShadow = false;
    var R = rng(seed * 13 + 1), np = o.pebbles == null ? 4 : o.pebbles;
    for (var i = 0; i < np; i++) {
      var pr = (0.3 + R() * (i === 0 ? 0.8 : 0.5)) * Math.min(1.4, Math.min(w, d) / 12);
      var pg = jitter(new T.DodecahedronGeometry(pr, 0), pr * 0.35, seed + i * 7); pg.computeVertexNormals();
      var ang = R() * Math.PI * 2, rad = 0.3 + R() * 0.25, fy = 0.2 + R() * 0.5;
      mesh(pg, k.pebble, Math.cos(ang) * w * rad * (1.1 - fy), -3.4 - depth * fy, Math.sin(ang) * d * rad * (1.1 - fy), g, { noCast: true });
    }
    return g;
  }
  function road(k, o) {
    var g = new T.Group(), t = tex(roadCanvas(o.lanes, o.median));
    t.wrapS = T.RepeatWrapping; t.repeat.set(o.len / 24, 1);
    var topMat = new T.MeshStandardMaterial({ map: t, roughness: 0.86, color: '#9a9fa6', envMapIntensity: 0.6 });
    topMat.userData.temp = true;
    var m = new T.Mesh(new T.BoxGeometry(o.len, 0.16, o.width), [k.asphalt, k.asphalt, topMat, k.asphalt, k.asphalt, k.asphalt]);
    m.position.y = 0.03; m.receiveShadow = true; g.add(m);
    if (o.curbs !== false) {
      box(o.len, 0.24, 0.26, k.curb, 0, 0.07, o.width / 2 + 0.13, g, { noCast: true });
      box(o.len, 0.24, 0.26, k.curb, 0, 0.07, -o.width / 2 - 0.13, g, { noCast: true });
    }
    if (o.axis === 'z') g.rotation.y = Math.PI / 2;
    g.position.set(o.x || 0, 0, o.z || 0);
    return g;
  }
  function sidewalk(k, len, width, x, z) {
    var t = tex(pavingCanvas()); t.wrapS = t.wrapT = T.RepeatWrapping; t.repeat.set(len / 2.4, width / 2.4);
    var topMat = new T.MeshStandardMaterial({ map: t, roughness: 0.9 }); topMat.userData.temp = true;
    var m = new T.Mesh(new T.BoxGeometry(len, 0.24, width), [k.concrete, k.concrete, topMat, k.concrete, k.concrete, k.concrete]);
    m.position.set(x || 0, 0.07, z || 0); m.receiveShadow = true;
    return m;
  }
  function tree(k, s, seed) {
    var g = new T.Group(), r = rng(seed), th = (1.8 + r() * 0.8) * s;
    cyl(0.14 * s, 0.24 * s, th, 7, k.trunk, 0, th / 2, 0, g);
    var n = 3 + Math.floor(r() * 2);
    for (var i = 0; i < n; i++) {
      var rad = (1.15 + r() * 0.6) * s * (i === 0 ? 1.18 : 0.78);
      var geo = smoothNormals(jitter(new T.IcosahedronGeometry(rad, 2), rad * 0.16, seed * 31 + i));
      var m = mesh(geo, k.leaves[Math.floor(r() * k.leaves.length)], i === 0 ? 0 : (r() - 0.5) * 1.9 * s, th + rad * 0.55 + (i === 0 ? 0.5 * s : r() * 1.1 * s), i === 0 ? 0 : (r() - 0.5) * 1.9 * s, g);
      m.scale.y = 0.86 + r() * 0.2;
    }
    return g;
  }
  var LEAF_GEO = null;
  function palm(k, s, seed) {
    var g = new T.Group(), r = rng(seed), h = (5.6 + r() * 1.6) * s, lean = (r() - 0.5) * 1.6 * s, pts = [];
    for (var i = 0; i <= 6; i++) { var t = i / 6; pts.push(new T.Vector3(Math.sin(t * 1.4) * lean, t * h, 0)); }
    mesh(new T.TubeGeometry(new T.CatmullRomCurve3(pts), 18, 0.16 * s, 7, false), k.palmTrunk, 0, 0, 0, g);
    if (!LEAF_GEO) {
      var L = 3.1, geo = new T.PlaneGeometry(L, 1, 10, 2), p = geo.attributes.position;
      for (var j = 0; j < p.count; j++) {
        var x = p.getX(j) + L / 2, y = p.getY(j), u = x / L, wdt = Math.sin(Math.PI * Math.min(1, u * 1.08)) * 0.42, zz = y * 2 * wdt;
        p.setXYZ(j, x, -0.55 * u * u * L * 0.5 - Math.abs(zz) * 0.35, zz);
      }
      geo.computeVertexNormals();
      LEAF_GEO = geo;
    }
    var top = pts[6];
    for (var l = 0; l < 9; l++) {
      var leaf = mesh(LEAF_GEO, k.palmLeaf, top.x, top.y, top.z, g);
      leaf.rotation.order = 'YZX';
      leaf.rotation.y = l / 9 * Math.PI * 2 + r() * 0.4;
      leaf.rotation.z = 0.25 + r() * 0.35;
      leaf.scale.setScalar(s * (0.85 + r() * 0.3));
    }
    return g;
  }
  function scatterTrees(k, g, o) {
    var r = rng(o.seed), placed = [], tries = 0;
    while (placed.length < o.n && tries++ < 400) {
      var x = lerp(o.x[0], o.x[1], r()), z = lerp(o.z[0], o.z[1], r()), s = lerp(o.s[0], o.s[1], r()), ok = true;
      for (var i = 0; i < placed.length; i++) if (Math.hypot(placed[i][0] - x, placed[i][1] - z) < 2.6 * s) { ok = false; break; }
      if (ok && o.avoid) o.avoid.forEach(function (a) { if (Math.hypot(a.x - x, a.z - z) < a.r) ok = false; });
      if (!ok) continue;
      placed.push([x, z]);
      g.add(at(o.palms && r() < o.palms ? palm(k, s, o.seed * 100 + placed.length) : tree(k, s, o.seed * 100 + placed.length), x, 0, z, r() * 6));
    }
  }
  function streetLamp(k, h, arm) {
    var g = new T.Group();
    cyl(0.07, 0.11, h, 8, k.steel, 0, h / 2, 0, g);
    var curve = new T.QuadraticBezierCurve3(new T.Vector3(0, h - 0.6, 0), new T.Vector3(0, h + 0.35, 0), new T.Vector3(0, h + 0.3, arm));
    mesh(new T.TubeGeometry(curve, 10, 0.05, 6, false), k.steel, 0, 0, 0, g);
    box(0.32, 0.12, 0.75, k.lampHead, 0, h + 0.26, arm + 0.22, g);
    box(0.22, 0.02, 0.52, k.lampGlow, 0, h + 0.19, arm + 0.22, g, { noCast: true });
    return g;
  }
  var CAR_GEO = null;
  function carGeo() {
    if (CAR_GEO) return CAR_GEO;
    var b = new T.Shape();
    b.moveTo(-2.25, 0.3); b.lineTo(2.2, 0.3);
    b.quadraticCurveTo(2.38, 0.32, 2.34, 0.62); b.quadraticCurveTo(2.3, 0.9, 1.9, 0.94);
    b.lineTo(-1.9, 0.98); b.quadraticCurveTo(-2.32, 0.94, -2.32, 0.6); b.quadraticCurveTo(-2.32, 0.31, -2.25, 0.3);
    var bodyGeo = new T.ExtrudeGeometry(b, { depth: 1.56, bevelEnabled: true, bevelThickness: 0.14, bevelSize: 0.12, bevelSegments: 3, curveSegments: 8 });
    bodyGeo.translate(0, 0, -0.78);
    var c = new T.Shape();
    c.moveTo(1.0, 0.9); c.quadraticCurveTo(0.62, 1.02, 0.28, 1.4); c.lineTo(-1.0, 1.42); c.quadraticCurveTo(-1.55, 1.34, -1.9, 0.92); c.lineTo(1.0, 0.9);
    var cabGeo = new T.ExtrudeGeometry(c, { depth: 1.5, bevelEnabled: true, bevelThickness: 0.08, bevelSize: 0.06, bevelSegments: 2, curveSegments: 6 });
    cabGeo.translate(0, 0, -0.75);
    var wheel = new T.CylinderGeometry(0.34, 0.34, 0.28, 14); wheel.rotateX(Math.PI / 2);
    CAR_GEO = { body: bodyGeo, cab: cabGeo, wheel: wheel, roof: new T.BoxGeometry(1.15, 0.07, 1.46) };
    return CAR_GEO;
  }
  function car(k, color, o) {
    o = o || {};
    var G = carGeo(), g = new T.Group(), paint = k.paint(color, 0.28, 0.5);
    mesh(G.body, paint, 0, 0, 0, g);
    mesh(G.cab, k.carGlass, 0, 0, 0, g);
    mesh(G.roof, paint, -0.38, 1.47, 0, g);
    [[1.38, 0.86], [1.38, -0.86], [-1.4, 0.86], [-1.4, -0.86]].forEach(function (w) { mesh(G.wheel, k.tyre, w[0], 0.34, w[1], g); });
    box(0.06, 0.12, 0.36, k.head, 2.36, 0.68, 0.5, g, { noCast: true });
    box(0.06, 0.12, 0.36, k.head, 2.36, 0.68, -0.5, g, { noCast: true });
    box(0.06, 0.12, 0.4, k.tail, -2.4, 0.74, 0.52, g, { noCast: true });
    box(0.06, 0.12, 0.4, k.tail, -2.4, 0.74, -0.52, g, { noCast: true });
    if (o.taxi) {
      var sign = box(0.9, 0.3, 0.34, k.blanc, -0.38, 1.66, 0, g);
      plan(0.86, 0.26, creaMat('taxi', true), -0.38, 1.66, 0.175, 0, g);
      plan(0.86, 0.26, creaMat('taxi', true), -0.38, 1.66, -0.175, Math.PI, g);
      sign.castShadow = true;
    }
    if (o.decor) {
      // habillage : motif + logo détouré sur les flancs (charte p. 35)
      plan(3.6, 0.78, creaMat('decor', false, true), -0.1, 0.62, 0.935, 0, g);
      plan(3.6, 0.78, creaMat('decor', false, true), -0.1, 0.62, -0.935, Math.PI, g);
    }
    return g;
  }
  function person(k, color, seed) {
    var g = new T.Group(), r = rng(seed), h = 0.9 + r() * 0.2;
    mesh(new T.CapsuleGeometry(0.21, h * 0.75, 4, 10), k.paint(color, 0.8), 0, 0.55 + h * 0.2, 0, g);
    mesh(new T.CapsuleGeometry(0.1, 0.5, 3, 8), k.paint('#2a2f3a', 0.8), 0.09, 0.3, 0, g);
    mesh(new T.CapsuleGeometry(0.1, 0.5, 3, 8), k.paint('#2a2f3a', 0.8), -0.09, 0.3, 0, g);
    mesh(new T.SphereGeometry(0.16, 14, 12), k.skin, 0, 1.0 + h * 0.55, 0, g);
    g.scale.setScalar(1.05);
    return g;
  }
  function crowd(k, g, o) {
    var r = rng(o.seed), cols = [C.rouge, C.jaune, '#2a6f3a', '#1f6fe0', '#f2f2f0', C.violet, '#ff8a1c'];
    for (var i = 0; i < o.n; i++) g.add(at(person(k, cols[Math.floor(r() * cols.length)], o.seed * 10 + i), lerp(o.x[0], o.x[1], r()), o.y || 0.19, lerp(o.z[0], o.z[1], r()), (r() - 0.5) * 1.2 + (o.ry || 0)));
  }

  /* Tête de panneau : cadre, face, structure, passerelle, projecteurs */
  function panelHead(k, o) {
    var g = new T.Group(), fw = o.w + 0.34, fh = o.h + 0.34, fd = o.depth || 0.42;
    box(fw, fh, fd, k.frame, 0, 0, 0, g);
    mesh(new T.PlaneGeometry(o.w, o.h), o.face, 0, 0, fd / 2 + 0.012, g, { noCast: true });
    if (o.back) { var b = mesh(new T.PlaneGeometry(o.w, o.h), o.back, 0, 0, -fd / 2 - 0.012, g, { noCast: true }); b.rotation.y = Math.PI; }
    else {
      var nv = Math.max(2, Math.round(o.w / 3));
      for (var i = 0; i <= nv; i++) box(0.16, fh * 0.96, 0.16, k.steelDark, -fw / 2 + 0.25 + (fw - 0.5) * i / nv, 0, -fd / 2 - 0.12, g);
      box(fw - 0.2, 0.16, 0.16, k.steelDark, 0, fh * 0.3, -fd / 2 - 0.3, g);
      box(fw - 0.2, 0.16, 0.16, k.steelDark, 0, -fh * 0.3, -fd / 2 - 0.3, g);
    }
    if (o.catwalk !== false) {
      var cy = -fh / 2 - 0.2, cz = fd / 2 + 0.42;
      box(fw + 0.2, 0.06, 0.95, k.grating, 0, cy, cz, g);
      var np = Math.max(3, Math.round(fw / 1.15));
      for (var p = 0; p <= np; p++) {
        var px = -fw / 2 - 0.05 + (fw + 0.1) * p / np;
        cyl(0.024, 0.024, 0.95, 5, k.steel, px, cy + 0.48, cz + 0.44, g);
        box(0.07, 0.09, 1.0, k.steelDark, px, cy - 0.08, cz - 0.02, g);
      }
      box(fw + 0.16, 0.05, 0.05, k.steel, 0, cy + 0.95, cz + 0.44, g);
      box(fw + 0.16, 0.035, 0.035, k.steel, 0, cy + 0.5, cz + 0.44, g);
      box(fw + 0.2, 0.16, 0.03, k.steel, 0, cy + 0.08, cz + 0.47, g);
    }
    if (o.lamps) {
      for (var l = 0; l < o.lamps; l++) {
        var lx = -o.w / 2 + o.w * (l + 0.5) / o.lamps;
        var arm = box(0.07, 0.07, 1.25, k.steel, lx, fh / 2 + 0.22, fd / 2 + 0.48, g); arm.rotation.x = -0.45;
        var hd = box(0.62, 0.17, 0.36, k.lampHead, lx, fh / 2 + 0.48, fd / 2 + 1.04, g); hd.rotation.x = 0.55;
        var gl = mesh(new T.PlaneGeometry(0.5, 0.26), k.lampGlow, lx, fh / 2 + 0.38, fd / 2 + 0.99, g, { noCast: true }); gl.rotation.x = Math.PI / 2 + 0.55;
      }
    }
    return g;
  }

  /* ================================================================== */
  /* Dispositifs d'affichage                                             */
  /* ================================================================== */
  /* Les 6 formats officiels (largeur × hauteur modélisées, en mètres). */
  var FORMATS = {
    'petit': { w: 4, h: 2, label: 'Petit format' },
    'classique': { w: 4, h: 3, label: 'Classique 4×3' },
    'grande-dimension': { w: 6, h: 3.5, label: 'Grande dimension' },
    'grand-format': { w: 8, h: 4, label: 'Grand format' },
    'tres-grand': { w: 10, h: 5, label: 'Très grand format' },
    'panoramique': { w: 14, h: 5, label: 'Panoramique' }
  };
  /* Face de chaque format : le BAT à ses proportions exactes. */
  Object.keys(FORMATS).forEach(function (id) {
    var f = FORMATS[id];
    CREAS['format-' + id] = function () { var W = f.w / f.h > 2.5 ? 2048 : 1600; return batCrea(W, Math.round(W * f.h / f.w)); };
  });
  /* Panneau sur pied adapté à sa taille : 2 pieds, puis unipôle au-delà de 8 m. */
  function panneau(k, o) {
    var g = new T.Group(), w = o.w, h = o.h;
    if (w >= 8) {
      var H = o.H || (w >= 12 ? 9.5 : 8.5);
      cyl(1.0, 1.18, 0.5, 24, k.concrete, 0, 0.25, 0, g);
      cyl(0.5, 0.62, H, 28, k.steel, 0, H / 2, 0, g);
      cyl(0.64, 0.64, 0.24, 28, k.steelDark, 0, H * 0.46, 0, g);
      cyl(0.6, 0.6, 0.5, 28, k.steelDark, 0, H - 0.1, 0, g);
      box(w * 0.6, 0.42, 0.6, k.steelDark, 0, H + 0.2, 0, g);
      [-1, 1].forEach(function (sd) {
        var dx = w * 0.27, dy = 2.4, L = Math.hypot(dx, dy);
        var br = box(0.2, L, 0.2, k.steelDark, sd * dx / 2, H + 0.2 - dy / 2, -0.25, g);
        br.rotation.z = -Math.atan2(sd * dx, dy);
      });
      var head = panelHead(k, { w: w, h: h, face: o.face, back: o.back, lamps: Math.max(2, Math.round(w / 3.4)) });
      head.position.set(0, H + 0.42 + 0.2 + (h + 0.34) / 2 + 0.25, 0.1);
      g.add(head);
    } else {
      var Hl = o.H || (w <= 4 && h <= 2 ? 3.2 : 4.4), lx = w * 0.32, legW = w > 5 ? 0.34 : 0.26;
      [-lx, lx].forEach(function (x) {
        box(legW, Hl + 1.6, legW, k.steelDark, x, (Hl + 1.6) / 2, -0.15, g);
        box(legW * 2.6, 0.32, legW * 2.6, k.concrete, x, 0.16, -0.15, g);
      });
      var L = Math.hypot(lx * 2, 2.4);
      var d1 = box(0.1, L, 0.1, k.steelDark, 0, Hl - 1.6, -0.15, g); d1.rotation.z = Math.atan2(lx * 2, 2.4);
      var d2 = box(0.1, L, 0.1, k.steelDark, 0, Hl - 1.6, -0.15, g); d2.rotation.z = -Math.atan2(lx * 2, 2.4);
      var head2 = panelHead(k, { w: w, h: h, face: o.face, lamps: Math.max(2, Math.round(w / 2.4)) });
      head2.position.set(0, Hl + (h + 0.34) / 2 + 0.3, 0.12);
      g.add(head2);
    }
    return g;
  }
  function ledScreen(k, o) {
    var g = new T.Group(), H = 4.6, w = 6, h = 3.2, fw = w + 0.5, fh = h + 0.5;
    box(1.0, 0.5, 1.0, k.concrete, 0, 0.25, 0, g);
    box(0.66, H, 0.66, k.steelDark, 0, H / 2, 0, g);
    box(fw, fh, 0.7, k.frame, 0, H + fh / 2, 0, g);
    mesh(new T.PlaneGeometry(w, h), o.screen, 0, H + fh / 2, 0.36, g, { noCast: true });
    box(w * 0.8, h * 0.75, 0.45, k.steelDark, 0, H + fh / 2, -0.55, g);
    box(fw + 0.12, 0.12, 0.84, k.steel, 0, H + fh + 0.06, 0, g);
    box(fw + 0.12, 0.12, 0.84, k.steel, 0, H - 0.04, 0, g);
    return g;
  }
  /* Caisson lumineux double face sur mât (Lumipub). */
  function caisson(k, o) {
    var g = new T.Group(), w = o.w || 3.2, h = o.h || 2, H = o.H || 3.2;
    cyl(0.16, 0.2, H, 12, k.steelDark, 0, H / 2, 0, g);
    box(0.7, 0.3, 0.7, k.concrete, 0, 0.15, 0, g);
    box(w + 0.24, h + 0.24, 0.42, k.blanc, 0, H + h / 2, 0, g);
    mesh(new T.PlaneGeometry(w, h), o.face, 0, H + h / 2, 0.215, g, { noCast: true });
    var b = mesh(new T.PlaneGeometry(w, h), o.face, 0, H + h / 2, -0.215, g, { noCast: true }); b.rotation.y = Math.PI;
    box(w + 0.3, 0.1, 0.48, k.steel, 0, H + h + 0.17, 0, g);
    return g;
  }
  /* Trivision : prismes triangulaires montrant 3 créas, animés par vague. */
  function trivision(k, o) {
    var g = new T.Group(), w = 4.8, h = 3.4, N = 18, H = 4.2;
    var mats = [creaMat('triA'), creaMat('triB'), creaMat('triC')];
    [-1.5, 1.5].forEach(function (x) { box(0.3, H + 1.7, 0.3, k.steelDark, x, (H + 1.7) / 2, -0.4, g); box(0.8, 0.32, 0.8, k.concrete, x, 0.16, -0.4, g); });
    var cx = H + h / 2 + 0.35;
    box(w + 0.5, 0.3, 0.9, k.frame, 0, cx + h / 2 + 0.15, 0, g);
    box(w + 0.5, 0.3, 0.9, k.frame, 0, cx - h / 2 - 0.15, 0, g);
    box(0.25, h + 0.6, 0.9, k.frame, -w / 2 - 0.12, cx, 0, g);
    box(0.25, h + 0.6, 0.9, k.frame, w / 2 + 0.12, cx, 0, g);
    box(w, h, 0.1, k.steelDark, 0, cx, -0.32, g);
    var fw = w / N, R = fw / Math.sqrt(3), apo = R / 2, prismes = [];
    for (var i = 0; i < N; i++) {
      var p = new T.Group();
      for (var j = 0; j < 3; j++) {
        var geo = new T.PlaneGeometry(fw * 1.002, h);
        var uv = geo.attributes.uv;
        for (var q = 0; q < uv.count; q++) uv.setX(q, (i + uv.getX(q)) / N);
        var face = new T.Mesh(geo, mats[j]);
        var a = j * Math.PI * 2 / 3;
        face.position.set(Math.sin(a) * apo, 0, Math.cos(a) * apo);
        face.rotation.y = a;
        p.add(face);
      }
      p.position.set(-w / 2 + fw * (i + 0.5), cx, 0.05);
      p.userData.dynamic = true;
      g.add(p);
      prismes.push(p);
    }
    var lampY = cx + h / 2 + 0.3;
    for (var l = 0; l < 2; l++) {
      var lx = -w / 4 + l * w / 2;
      var arm = box(0.07, 0.07, 1.2, k.steel, lx, lampY + 0.2, 0.9, g); arm.rotation.x = -0.45;
      var hd = box(0.6, 0.16, 0.34, k.lampHead, lx, lampY + 0.45, 1.45, g); hd.rotation.x = 0.55;
    }
    /* phase : 0..3 (face A → B → C → A), vague de gauche à droite */
    function regler(phase) {
      for (var i = 0; i < N; i++) {
        var local = clamp(phase - i * 0.035, 0, 3);
        var etape = Math.floor(local), frac = ease(clamp((local - etape) * 3.5, 0, 1));
        prismes[i].rotation.y = -(etape + frac) * Math.PI * 2 / 3;
      }
    }
    regler(o.phase == null ? 0 : o.phase);
    return { group: g, regler: regler };
  }
  function boutique(k, o) {
    var g = new T.Group(), w = 9, h = 4.6, d = 6;
    var murs = k.paint('#f4f1ea', 0.85);
    box(w, 0.3, d, k.concrete, 0, 0.15, 0, g);
    box(w, h, 0.3, murs, 0, h / 2 + 0.3, -d / 2 + 0.15, g);
    box(0.3, h, d, murs, -w / 2 + 0.15, h / 2 + 0.3, 0, g);
    box(0.3, h, d, murs, w / 2 - 0.15, h / 2 + 0.3, 0, g);
    box(w + 0.4, 0.4, d + 0.4, k.paint('#e9e3d6', 0.85), 0, h + 0.5, 0, g);
    box(w, 0.9, 0.3, k.paint('#ffffff', 0.6), 0, h - 0.15, d / 2 - 0.15, g);
    plan(4.2, 0.62, creaMat('enseigne', true), 0, h - 0.15, d / 2 + 0.01, 0, g);
    mesh(new T.BoxGeometry(w - 0.6, h - 1.2, 0.06), k.glass, 0, (h - 1.2) / 2 + 0.3, d / 2 - 0.15, g, { noCast: true });
    // auvent rouge
    var auvent = box(w + 0.2, 0.08, 1.4, k.rouge, 0, h - 0.75, d / 2 + 0.6, g); auvent.rotation.x = 0.22;
    // rayonnages et écrans intérieurs
    for (var i = 0; i < 3; i++) box(1.8, 1.6, 0.6, k.paint('#d8d2c6', 0.8), -2.6 + i * 2.6, 1.1, -1.6, g);
    [-2.8, 2.8].forEach(function (x) {
      box(0.9, 1.7, 0.16, k.frame, x, 1.6, 0.6, g);
      plan(0.78, 1.38, o.screen, x, 1.6, 0.69, 0, g);
    });
    // totem digital à l'entrée
    var totem = new T.Group();
    box(1.2, 2.2, 0.32, k.frame, 0, 1.3, 0, totem);
    plan(1.0, 1.76, o.screen, 0, 1.36, 0.165, 0, totem);
    box(0.9, 0.2, 0.5, k.steelDark, 0, 0.1, 0, totem);
    g.add(at(totem, 3.1, 0.3, d / 2 + 1.5, -0.25));
    return g;
  }

  /* ================================================================== */
  /* Véhicules                                                           */
  /* ================================================================== */
  var CAB_GEO = null;
  function cabine(k, couleur) {
    if (!CAB_GEO) {
      var s = new T.Shape();
      s.moveTo(0, 0); s.lineTo(1.9, 0); s.lineTo(1.9, 1.15); s.quadraticCurveTo(1.86, 1.75, 1.45, 2.05);
      s.lineTo(0.15, 2.1); s.quadraticCurveTo(0, 2.1, 0, 1.9); s.lineTo(0, 0);
      var geo = new T.ExtrudeGeometry(s, { depth: 2.1, bevelEnabled: true, bevelThickness: 0.1, bevelSize: 0.1, bevelSegments: 3, curveSegments: 6 });
      geo.translate(0, 0, -1.05);
      CAB_GEO = geo;
    }
    var g = new T.Group();
    mesh(CAB_GEO, k.paint(couleur, 0.35, 0.3), 0, 0.85, 0, g);
    box(0.06, 0.75, 1.9, k.carGlass, 1.97, 2.25, 0, g, { noCast: true }).rotation.z = 0.32;
    box(0.9, 0.55, 0.05, k.carGlass, 1.05, 2.3, 1.16, g, { noCast: true });
    box(0.9, 0.55, 0.05, k.carGlass, 1.05, 2.3, -1.16, g, { noCast: true });
    box(0.1, 0.22, 2.0, k.chrome, 2.02, 1.05, 0, g, { noCast: true });
    box(0.06, 0.14, 0.4, k.head, 2.03, 1.32, 0.78, g, { noCast: true });
    box(0.06, 0.14, 0.4, k.head, 2.03, 1.32, -0.78, g, { noCast: true });
    return g;
  }
  function roues(k, g, xs, z, r) {
    var geo = new T.CylinderGeometry(r, r, 0.36, 16); geo.rotateX(Math.PI / 2);
    xs.forEach(function (x) { mesh(geo, k.tyre, x, r, z, g); mesh(geo, k.tyre, x, r, -z, g); });
  }
  /* Camion porteur avec caisson d'affichage (créas sur les deux flancs). */
  function camion(k, o) {
    var g = new T.Group();
    g.add(at(cabine(k, o.cabine || '#f4f4f2'), 4.4, 0, 0));
    box(8.6, 0.4, 1.9, k.frame, 2.2, 0.85, 0, g);
    roues(k, g, [5.4, 1.6, 0.4], 1.0, 0.55);
    var L = 6.2, H = 2.7, D = 2.4, y0 = 1.15;
    box(L, H, D, k.blanc, 0.95, y0 + H / 2, 0, g);
    plan(L - 0.2, H - 0.2, creaMat('camion'), 0.95, y0 + H / 2, D / 2 + 0.01, 0, g);
    plan(L - 0.2, H - 0.2, creaMat('camion'), 0.95, y0 + H / 2, -D / 2 - 0.01, Math.PI, g);
    plan(D - 0.2, H - 0.3, creaMat('camionArriere'), 0.95 - L / 2 - 0.01, y0 + H / 2, 0, -Math.PI / 2, g);
    box(L + 0.1, 0.12, D + 0.1, k.steel, 0.95, y0 + H + 0.06, 0, g);
    return g;
  }
  /* Camion podium (roadshows) : scène latérale dépliée + écran géant, comme le camion CIBLE. */
  function podium(k, o) {
    var g = new T.Group();
    g.add(at(cabine(k, '#f4f4f2'), 4.6, 0, 0));
    box(8.8, 0.4, 1.9, k.frame, 2.2, 0.85, 0, g);
    roues(k, g, [5.6, 1.4, 0.2], 1.0, 0.55);
    var L = 6.4, y0 = 1.15;
    box(L, 2.2, 2.3, k.blanc, 0.9, y0 + 1.1, -0.1, g);
    // scène dépliée côté +z
    box(L, 0.16, 2.6, k.paint('#3a3f46', 0.7), 0.9, y0 + 0.4, 2.35, g);
    plan(L, 0.62, creaMat('jupe'), 0.9, y0 + 0.02, 3.66, 0, g);
    for (var i = 0; i <= 6; i++) cyl(0.03, 0.03, 0.9, 6, k.steel, -2.2 + i * 1.03, y0 + 0.9, 3.6, g);
    box(L, 0.05, 0.05, k.steel, 0.9, y0 + 1.35, 3.6, g);
    // escalier
    for (var s = 0; s < 4; s++) box(0.9, 0.12, 0.42, k.steel, 4.4, 0.25 + s * 0.32, 2.2 + s * 0.38 - 0.4, g);
    // écran géant sur le toit
    box(5.2, 3.0, 0.4, k.frame, 0.9, y0 + 2.2 + 1.7, -0.1, g);
    plan(4.9, 2.7, o.ecran || creaMat('led1', true), 0.9, y0 + 2.2 + 1.7, 0.11, 0, g);
    // enceintes
    [-2.3, 4.0].forEach(function (x) { box(0.6, 1.1, 0.6, k.noir, x, y0 + 1.05, 2.9, g); });
    return g;
  }
  function moto(k, o) {
    var g = new T.Group();
    var roue = new T.TorusGeometry(0.33, 0.09, 8, 20);
    mesh(roue, k.tyre, 0.72, 0.42, 0, g);
    mesh(roue, k.tyre, -0.72, 0.42, 0, g);
    box(1.3, 0.3, 0.26, k.paint(o.couleur || C.rouge, 0.4, 0.3), 0, 0.8, 0, g);
    box(0.7, 0.14, 0.32, k.noir, -0.25, 1.0, 0, g);
    var fourche = box(0.07, 0.8, 0.07, k.chrome, 0.66, 0.78, 0, g); fourche.rotation.z = 0.35;
    box(0.06, 0.06, 0.7, k.chrome, 0.52, 1.18, 0, g);
    // coffre publicitaire
    box(0.62, 0.5, 0.54, k.blanc, -0.78, 1.32, 0, g);
    plan(0.5, 0.44, creaMat('coffre'), -0.78, 1.32, 0.275, 0, g);
    plan(0.5, 0.44, creaMat('coffre'), -0.78, 1.32, -0.275, Math.PI, g);
    plan(0.5, 0.44, creaMat('coffre'), -1.095, 1.32, 0, -Math.PI / 2, g);
    // pilote casqué
    var p = person(k, o.tenue || C.rouge, o.seed || 3);
    p.scale.setScalar(0.92);
    g.add(at(p, -0.15, 0.62, 0));
    mesh(new T.SphereGeometry(0.2, 14, 12), k.paint(C.rouge, 0.3, 0.2), -0.15, 1.82, 0, g);
    return g;
  }
  var VAN_GEO = null;
  function van(k, o) {
    if (!VAN_GEO) {
      var s = new T.Shape();
      s.moveTo(-2.6, 0.32); s.lineTo(2.3, 0.32); s.quadraticCurveTo(2.5, 0.36, 2.48, 0.8); s.lineTo(2.3, 1.4);
      s.quadraticCurveTo(2.0, 2.15, 1.4, 2.3); s.lineTo(-2.45, 2.32); s.quadraticCurveTo(-2.62, 2.3, -2.62, 2.1); s.lineTo(-2.62, 0.5); s.quadraticCurveTo(-2.62, 0.32, -2.6, 0.32);
      VAN_GEO = new T.ExtrudeGeometry(s, { depth: 1.8, bevelEnabled: true, bevelThickness: 0.08, bevelSize: 0.08, bevelSegments: 3, curveSegments: 8 });
      VAN_GEO.translate(0, 0, -0.9);
    }
    var g = new T.Group();
    mesh(VAN_GEO, k.paint('#f4f4f2', 0.35, 0.3), 0, 0, 0, g);
    box(0.06, 0.7, 1.7, k.carGlass, 2.33, 1.72, 0, g, { noCast: true }).rotation.z = 0.5;
    box(0.8, 0.55, 0.04, k.carGlass, 1.55, 1.75, 0.99, g, { noCast: true });
    box(0.8, 0.55, 0.04, k.carGlass, 1.55, 1.75, -0.99, g, { noCast: true });
    plan(3.4, 1.55, creaMat('van'), -0.75, 1.3, 0.99, 0, g);
    plan(3.4, 1.55, creaMat('van'), -0.75, 1.3, -0.99, Math.PI, g);
    roues(k, g, [1.55, -1.6], 0.9, 0.38);
    return g;
  }
  function bus(k, o) {
    var g = new T.Group(), L = 11, H = 2.9, D = 2.5;
    var corps = k.paint('#f4f4f2', 0.4, 0.2);
    var s = new T.Shape();
    s.moveTo(-L / 2, 0.45); s.lineTo(L / 2 - 0.3, 0.45); s.quadraticCurveTo(L / 2, 0.45, L / 2, 0.8); s.lineTo(L / 2, H - 0.2); s.quadraticCurveTo(L / 2, H + 0.3, L / 2 - 0.5, H + 0.3);
    s.lineTo(-L / 2 + 0.3, H + 0.3); s.quadraticCurveTo(-L / 2, H + 0.3, -L / 2, H); s.lineTo(-L / 2, 0.45);
    var geo = new T.ExtrudeGeometry(s, { depth: D, bevelEnabled: true, bevelThickness: 0.06, bevelSize: 0.06, bevelSegments: 2, curveSegments: 6 });
    geo.translate(0, 0, -D / 2);
    mesh(geo, corps, 0, 0, 0, g);
    box(L - 1.2, 0.9, D + 0.1, k.carGlass, -0.2, H - 0.25, 0, g, { noCast: true });
    box(0.06, 1.3, D - 0.3, k.carGlass, L / 2 + 0.04, H - 0.4, 0, g, { noCast: true });
    plan(L - 2.4, 1.15, creaMat('bus'), -0.6, 1.2, D / 2 + 0.07, 0, g);
    plan(L - 2.4, 1.15, creaMat('bus'), -0.6, 1.2, -D / 2 - 0.07, Math.PI, g);
    roues(k, g, [3.6, -3.4], 1.08, 0.5);
    return g;
  }
  /* Chevalet : cube de panneaux sur cadre métallique. */
  function chevalet(k, o) {
    var g = new T.Group(), w = 1.5, h = 2.0, H = 0.45;
    var m = creaMat('chevalet');
    [[-w / 2, -w / 2], [w / 2, -w / 2], [-w / 2, w / 2], [w / 2, w / 2]].forEach(function (c) { box(0.08, h + H, 0.08, k.steel, c[0], (h + H) / 2, c[1], g); });
    [H, H + h].forEach(function (y) {
      box(w + 0.08, 0.06, 0.08, k.steel, 0, y, w / 2, g); box(w + 0.08, 0.06, 0.08, k.steel, 0, y, -w / 2, g);
      box(0.08, 0.06, w + 0.08, k.steel, w / 2, y, 0, g); box(0.08, 0.06, w + 0.08, k.steel, -w / 2, y, 0, g);
    });
    plan(w - 0.04, h - 0.04, m, 0, H + h / 2, w / 2 + 0.01, 0, g);
    plan(w - 0.04, h - 0.04, m, w / 2 + 0.01, H + h / 2, 0, Math.PI / 2, g);
    plan(w - 0.04, h - 0.04, m, -w / 2 - 0.01, H + h / 2, 0, -Math.PI / 2, g);
    plan(w - 0.04, h - 0.04, m, 0, H + h / 2, -w / 2 - 0.01, Math.PI, g);
    return g;
  }
  function etal(k, o) {
    var g = new T.Group();
    box(2.2, 0.08, 1.1, k.wood, 0, 0.9, 0, g);
    [[-1, -0.5], [1, -0.5], [-1, 0.5], [1, 0.5]].forEach(function (c) { box(0.07, 0.9, 0.07, k.wood, c[0], 0.45, c[1], g); });
    cyl(0.03, 0.03, 2.4, 6, k.steel, 0, 1.2, 0, g);
    var para = new T.ConeGeometry(1.6, 0.5, 8, 1, true);
    var pm = mesh(para, k.paint(o.toile || C.jaune, 0.7, 0), 0, 2.45, 0, g); pm.material.side = T.DoubleSide;
    var r = rng(o.seed || 4);
    for (var i = 0; i < 8; i++) mesh(new T.SphereGeometry(0.13, 8, 6), k.paint(['#e9572d', '#f6c344', '#3a9a3e', '#e8e0c8'][i % 4], 0.7), -0.8 + r() * 1.6, 1.02, -0.35 + r() * 0.7, g);
    return g;
  }
  /* Studio de production (brand content) */
  function studio(k, o) {
    var g = new T.Group();
    // cyclorama
    var cyc = new T.Group();
    box(7, 0.06, 4.5, k.blanc, 0, 0.03, 0, cyc);
    var courbe = new T.CylinderGeometry(1.4, 1.4, 7, 16, 1, true, Math.PI, Math.PI / 2);
    courbe.rotateZ(Math.PI / 2);
    var cm = mesh(courbe, k.blanc, 0, 1.4, -2.25 + 1.4, cyc); cm.material.side = T.DoubleSide;
    box(7, 3.4, 0.06, k.blanc, 0, 1.4 + 1.7, -2.25, cyc);
    g.add(cyc);
    // écran de contrôle montrant la marque
    var moniteur = new T.Group();
    box(1.6, 1.0, 0.1, k.frame, 0, 1.6, 0, moniteur);
    plan(1.46, 0.86, creaMat('led1', true), 0, 1.6, 0.06, 0, moniteur);
    cyl(0.04, 0.04, 1.1, 6, k.steelDark, 0, 0.55, 0, moniteur);
    g.add(at(moniteur, 2.8, 0, 2.6, -0.5));
    // caméra sur trépied
    var cam = new T.Group();
    [0, 2.1, 4.2].forEach(function (a) { var j = box(0.05, 1.5, 0.05, k.noir, Math.sin(a) * 0.32, 0.72, Math.cos(a) * 0.32, cam); j.rotation.x = Math.cos(a) * 0.22; j.rotation.z = -Math.sin(a) * 0.22; });
    box(0.5, 0.42, 0.9, k.noir, 0, 1.62, 0, cam);
    cyl(0.16, 0.18, 0.45, 14, k.frame, 0, 1.64, 0.62, cam).rotation.x = Math.PI / 2;
    box(0.18, 0.12, 0.3, k.rouge, 0.18, 1.9, -0.2, cam);
    g.add(at(cam, -0.6, 0, 3.4, Math.PI));
    // boîtes à lumière
    [-2.6, 2.4].forEach(function (x, i) {
      var l = new T.Group();
      cyl(0.03, 0.05, 2.4, 6, k.steelDark, 0, 1.2, 0, l);
      var sb = box(1.0, 0.8, 0.5, k.noir, 0, 2.5, 0, l); sb.rotation.x = -0.3;
      var face = plan(0.9, 0.7, k.lampGlow, 0, 2.47, 0.27, 0, l); face.rotation.x = -0.3;
      g.add(at(l, x, 0, 1.6, i ? -0.6 : 0.6));
    });
    // présentatrice et clap
    g.add(at(person(k, C.rouge, 9), 0, 0.06, -0.6));
    var clap = box(0.5, 0.36, 0.05, k.noir, 0.9, 0.22, 1.2, g); clap.rotation.x = -1.2;
    return g;
  }
  /* Stand expérientiel (terrain / street marketing) */
  function stand(k, o) {
    var g = new T.Group();
    box(7.4, 0.2, 4.6, k.paint('#f2f2f2', 0.7), 0, 0.1, 0, g);
    box(7.4, 3.2, 0.24, k.blanc, 0, 1.8, -2.2, g);
    plan(7.2, 3.0, creaMat('standFond'), 0, 1.8, -2.07, 0, g);
    box(0.24, 3.2, 3.0, k.jaune, -3.6, 1.8, -0.8, g);
    box(7.6, 0.36, 0.36, k.rouge, 0, 3.55, -2.2, g);
    // comptoir
    box(2.6, 1.05, 0.8, k.blanc, 0.8, 0.72, 0.4, g);
    plan(2.5, 0.9, creaMat('comptoir'), 0.8, 0.72, 0.81, 0, g);
    // kakémonos
    [-2.4, 3.1].forEach(function (x) {
      box(0.9, 0.12, 0.3, k.steelDark, x, 0.26, 1.4, g);
      plan(0.82, 2.1, creaMat('rollup'), x, 1.38, 1.43, 0, g);
      box(0.84, 0.05, 0.05, k.steel, x, 2.45, 1.43, g);
    });
    // plantes
    [-3.1, 3.3].forEach(function (x) { cyl(0.28, 0.22, 0.5, 10, k.blanc, x, 0.45, -1.6, g); mesh(smoothNormals(jitter(new T.IcosahedronGeometry(0.55, 2), 0.1, 3)), k.leaves[1], x, 1.1, -1.6, g); });
    crowd(k, g, { seed: 41, n: 6, x: [-2.6, 2.8], z: [1.2, 3.4], y: 0.2, ry: Math.PI });
    g.add(at(person(k, C.jaune, 51), 0.8, 0.2, -0.4, 0));
    return g;
  }

  /* ================================================================== */
  /* Perroquet écarlate (mascotte de l'Opération Plume Rouge)           */
  /* ================================================================== */
  function perroquet(k) {
    var g = new T.Group(), corps = new T.Group();
    g.add(corps);
    var rouge = k.paint('#d8141c', 0.6), jaune = k.paint(C.jaune, 0.55), bleu = k.paint('#2f6fc0', 0.55), blanc = k.paint('#f6f3ec', 0.6), noir = k.paint('#151515', 0.4), bec = k.paint('#ece3cf', 0.45);
    var torse = mesh(new T.SphereGeometry(1, 24, 18), rouge, 0, 0, 0, corps); torse.scale.set(0.52, 0.56, 1.15);
    var tete = mesh(new T.SphereGeometry(0.5, 22, 16), rouge, 0, 0.42, 0.95, corps);
    [-1, 1].forEach(function (sd) {
      var joue = mesh(new T.SphereGeometry(0.3, 18, 12), blanc, sd * 0.3, 0.44, 1.12, corps); joue.scale.set(0.45, 0.85, 0.9);
      var oeil = mesh(new T.CylinderGeometry(0.11, 0.11, 0.04, 20), blanc, sd * 0.43, 0.55, 1.08, corps); oeil.rotation.z = Math.PI / 2;
      var anneau = mesh(new T.TorusGeometry(0.11, 0.025, 8, 24), noir, sd * 0.45, 0.55, 1.08, corps); anneau.rotation.y = Math.PI / 2;
      mesh(new T.SphereGeometry(0.045, 10, 8), noir, sd * 0.46, 0.56, 1.1, corps);
    });
    var haut = mesh(new T.ConeGeometry(0.2, 0.62, 14), bec, 0, 0.35, 1.5, corps); haut.rotation.x = Math.PI / 2 + 0.55;
    var bas = mesh(new T.ConeGeometry(0.13, 0.32, 12), noir, 0, 0.2, 1.38, corps); bas.rotation.x = Math.PI / 2 + 0.2;
    // queue
    [-0.12, 0, 0.12].forEach(function (x, i) {
      var q = new T.Group();
      box(0.16, 0.03, 1.5, rouge, 0, 0, -0.75, q);
      box(0.14, 0.03, 0.55, bleu, 0, 0.01, -1.6, q);
      q.position.set(x, -0.05, -0.95); q.rotation.set(0.25, (i - 1) * 0.16, 0);
      corps.add(q);
    });
    // ailes : épaule rouge, bande jaune, rémiges bleues
    function aile(sd) {
      var a = new T.Group(), b = new T.Group();
      box(0.75, 0.06, 0.95, rouge, sd * 0.375, 0, 0, a);
      box(0.55, 0.05, 0.85, jaune, sd * 0.95, 0, -0.06, a);
      a.add(b); b.position.set(sd * 1.2, 0, 0);
      for (var i = 0; i < 6; i++) {
        var f = box(0.22, 0.04, 0.9 - i * 0.05, bleu, sd * (0.12 + i * 0.17), 0, -0.1 - i * 0.07, b);
        f.rotation.y = sd * (0.15 + i * 0.09);
      }
      a.position.set(sd * 0.42, 0.18, 0.15);
      corps.add(a);
      return { a: a, b: b, sd: sd };
    }
    var ailes = [aile(-1), aile(1)];
    g.scale.setScalar(1.15);
    return {
      group: g,
      battre: function (t, intensite) {
        var w = Math.sin(t * 7.5), amp = 0.75 * intensite;
        ailes.forEach(function (x) {
          x.a.rotation.z = x.sd * (-0.15 + w * amp);
          x.b.rotation.z = x.sd * (w * amp * 0.45);
        });
        corps.position.y = -w * 0.08 * intensite;
      }
    };
  }

  /* ================================================================== */
  /* Dioramas                                                            */
  /* ================================================================== */
  function laneZ(center, width, lanes, i) { return center - width / 2 + width * (i + 0.5) / lanes; }

  /* Panneau de l'ouverture : 70 m² environ, aux proportions du BAT (2,25:1). */
  var HERO_H = 5.6, HERO_W = HERO_H * (B ? B.w / B.h : 2.25);
  var DIO = {
    'panneaux-classiques': function (k, o) {
      var fid = FORMATS[o && o.format] ? o.format : 'classique', f = FORMATS[fid];
      var g = new T.Group(), large = f.w >= 8;
      var iw = large ? 24 : 16, id = large ? 16 : 12.5;
      g.add(island(k, { w: iw, d: id, seed: 23 }));
      g.add(road(k, { len: iw + 0.28, width: 6.2, lanes: 2, z: id * 0.26 }));
      g.add(sidewalk(k, iw + 0.2, 1.6, 0, id * 0.26 - 3.9));
      var face = creaMat('format-' + fid);
      var p = panneau(k, { w: f.w, h: f.h, face: face });
      var sc = large ? 1 : 1.18;
      p.scale.setScalar(sc);
      g.add(at(p, 0, 0, -id * 0.24));
      g.add(at(palm(k, 0.85, 3), -iw / 2 + 2.4, 0, -id / 2 + 2.2));
      scatterTrees(k, g, { seed: 9, n: large ? 4 : 2, x: [iw / 2 - 5, iw / 2 - 1.6], z: [-id / 2 + 1.4, -id / 2 + 3], s: [0.7, 0.95] });
      g.add(at(streetLamp(k, 5.4, 1.3), iw / 2 - 2.4, 0, id * 0.26 - 4.3));
      g.add(at(car(k, '#ff8a1c', { taxi: true }), -3.2, 0.1, laneZ(id * 0.26, 6.2, 2, 1)));
      g.add(at(car(k, '#5b636d'), 3.6, 0.1, laneZ(id * 0.26, 6.2, 2, 0), Math.PI));
      return { root: g };
    },
    lumipub: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 20, d: 15, seed: 81 }));
      g.add(road(k, { len: 20.28, width: 4.6, lanes: 2, z: 4.5 }));
      g.add(road(k, { len: 20.28, width: 4.6, lanes: 2, z: -4.2 }));
      box(20.2, 0.3, 3.2, k.grassSide, 0, 0.12, 0.15, g, { noCast: true });
      box(20.2, 0.32, 0.25, k.curb, 0, 0.14, 1.75, g, { noCast: true });
      box(20.2, 0.32, 0.25, k.curb, 0, 0.14, -1.45, g, { noCast: true });
      var face = creaMat('lumipub', true);
      [-6.2, 0, 6.2].forEach(function (x) { var cs = caisson(k, { face: face, w: 3.6, h: 2.3, H: 3.4 }); cs.scale.setScalar(1.25); g.add(at(cs, x, 0.27, 0.15)); });
      [-3, 3].forEach(function (x) { g.add(at(palm(k, 0.65, 11 + x), x, 0.27, 0.15)); });
      g.add(at(car(k, '#c9d0d8'), -5, 0.1, laneZ(4.5, 4.6, 2, 1)));
      g.add(at(car(k, '#ff8a1c', { taxi: true }), 5.5, 0.1, laneZ(-4.2, 4.6, 2, 0), Math.PI));
      g.add(at(car(k, '#2b5fae'), -1.5, 0.1, laneZ(-4.2, 4.6, 2, 1), Math.PI));
      return { root: g };
    },
    trivision: function (k, o) {
      var g = new T.Group();
      g.add(island(k, { w: 15, d: 12, seed: 91 }));
      g.add(road(k, { len: 15.28, width: 6, lanes: 2, z: 3 }));
      g.add(sidewalk(k, 15.2, 1.6, 0, -0.85));
      var tri = trivision(k, { phase: o && o.phase != null ? o.phase : 0.56 });
      tri.group.scale.setScalar(1.12);
      g.add(at(tri.group, -0.2, 0, -3));
      g.add(at(palm(k, 0.85, 7), -5.6, 0, -3.9));
      scatterTrees(k, g, { seed: 12, n: 2, x: [4, 6.2], z: [-4.6, -3.4], s: [0.7, 0.9] });
      g.add(at(car(k, '#f2f2f0'), -3.2, 0.1, laneZ(3, 6, 2, 1)));
      return { root: g, trivision: tri };
    },
    panoramiques: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 26, d: 16, seed: 11 }));
      g.add(road(k, { len: 26.28, width: 7, lanes: 2, z: 3.6 }));
      var u = panneau(k, { w: 14, h: 5, face: creaMat('pano'), H: 8.5 });
      g.add(at(u, 0, 0, -3.4));
      scatterTrees(k, g, { seed: 5, n: 7, x: [-11.5, 11.5], z: [-7.2, -5], s: [0.8, 1.1], avoid: [{ x: 0, z: -3.4, r: 2.4 }] });
      g.add(at(tree(k, 0.6, 31), -11, 0, 7));
      [-8, 8].forEach(function (x) { g.add(at(streetLamp(k, 6, 1.5), x, 0, -0.5)); });
      g.add(at(car(k, '#c9d0d8'), -4, 0.1, laneZ(3.6, 7, 2, 1)));
      g.add(at(car(k, C.rouge, { decor: true }), 4.6, 0.1, laneZ(3.6, 7, 2, 0), Math.PI));
      return { root: g };
    },
    'ecrans-digitaux': function (k, o) {
      var g = new T.Group();
      g.add(island(k, { w: 16, d: 13, seed: 37 }));
      g.add(road(k, { len: 16.28, width: 6, lanes: 2, z: 3.3 }));
      g.add(sidewalk(k, 16.2, 2.1, 0, -0.75));
      for (var i = 0; i < 6; i++) box(0.42, 0.02, 2.4, k.curb, -5.2 + i * 0.8, 0.12, 3.3, g, { noCast: true });
      var screen = (o && o.screen) || creaMat('led1', true);
      var led = ledScreen(k, { screen: screen });
      led.scale.setScalar(1.15);
      g.add(at(led, 1.4, 0, -3));
      var b = new T.Group(), c = facadeCanvas({ wall: '#f1ebe0', trim: '#e2d6c2', seed: 4 });
      var bm = new T.MeshStandardMaterial({ map: tex(c, [0.45, 0.55]), roughness: 0.82 }); bm.userData.temp = true;
      var bat = new T.Mesh(new T.BoxGeometry(5.4, 7, 4.6), [bm, bm, k.paint('#d9d3c7', 0.9), k.paint('#d9d3c7', 0.9), bm, bm]);
      bat.position.y = 3.5; bat.castShadow = bat.receiveShadow = true; b.add(bat);
      g.add(at(b, -4.9, 0, -3.9));
      scatterTrees(k, g, { seed: 13, n: 2, x: [5.2, 6.6], z: [-4.8, -3.6], s: [0.7, 0.85] });
      g.add(at(person(k, C.rouge, 3), -1.4, 0.18, -0.8));
      g.add(at(person(k, C.bleu, 4), -0.6, 0.18, -0.5));
      g.add(at(car(k, '#f2f2f0'), 4.2, 0.1, laneZ(3.3, 6, 2, 1)));
      return { root: g, screen: screen };
    },
    'ecrans-magasins': function (k, o) {
      var g = new T.Group(), screen = (o && o.screen) || creaMat('magasin', true);
      g.add(island(k, { w: 16, d: 13, seed: 101 }));
      g.add(road(k, { len: 16.28, width: 4.8, lanes: 2, z: 4.1 }));
      g.add(sidewalk(k, 16.2, 3.4, 0, 0.1));
      var bq = boutique(k, { screen: screen }); bq.scale.setScalar(1.15); g.add(at(bq, -0.6, 0.19, -3.6));
      g.add(at(palm(k, 0.7, 21), 6.6, 0, -4.2));
      g.add(at(person(k, C.jaune, 21), -3.2, 0.19, 0.6));
      g.add(at(person(k, '#2a6f3a', 22), -2.4, 0.19, 1.1));
      g.add(at(car(k, '#c9d0d8'), 4.4, 0.1, laneZ(4.1, 4.8, 2, 1)));
      return { root: g, screen: screen };
    },
    camions: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 20, d: 13, seed: 111 }));
      g.add(road(k, { len: 20.28, width: 7.4, lanes: 2, z: 1.6 }));
      g.add(at(camion(k, {}), -1, 0.1, laneZ(1.6, 7.4, 2, 1) - 0.2));
      g.add(at(car(k, '#5b636d'), 6.6, 0.1, laneZ(1.6, 7.4, 2, 0), Math.PI));
      scatterTrees(k, g, { seed: 15, n: 5, x: [-9, 9], z: [-5.4, -3.6], s: [0.7, 1], palms: 0.3 });
      g.add(at(streetLamp(k, 5.6, 1.5), 4, 0, -2.6));
      return { root: g };
    },
    motos: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 13, d: 10.5, seed: 121, depth: 5.4 }));
      g.add(road(k, { len: 13.28, width: 5.2, lanes: 2, z: 1.4 }));
      g.add(sidewalk(k, 13.2, 1.8, 0, -2.2));
      [[C.rouge, 1, C.jaune, -2.6, 1, 0], ['#1f1f1f', 2, C.rouge, 1.0, 1, -0.3], [C.rouge, 3, '#f2f2f0', 3.8, 0, 0]].forEach(function (m) {
        var mt = moto(k, { couleur: m[0], seed: m[1], tenue: m[2] }); mt.scale.setScalar(1.3);
        g.add(at(mt, m[3], 0.1, laneZ(1.4, 5.2, 2, m[4]) + m[5], m[4] ? 0 : Math.PI));
      });
      g.add(at(etal(k, { toile: C.rouge, seed: 2 }), -3.4, 0.19, -2.6));
      g.add(at(tree(k, 0.75, 61), 4.6, 0, -3.6));
      crowd(k, g, { seed: 7, n: 2, x: [-1.4, 1.4], z: [-2.6, -1.8] });
      return { root: g };
    },
    'branding-vehicules': function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 16, d: 12, seed: 131 }));
      g.add(road(k, { len: 16.28, width: 6.4, lanes: 2, z: 2.2 }));
      g.add(sidewalk(k, 16.2, 1.8, 0, -1.9));
      g.add(at(car(k, C.rouge, { decor: true }), -2.6, 0.1, laneZ(2.2, 6.4, 2, 1)));
      g.add(at(van(k, {}), 3.2, 0.1, laneZ(2.2, 6.4, 2, 0), Math.PI));
      scatterTrees(k, g, { seed: 19, n: 4, x: [-7, 7], z: [-5, -3.4], s: [0.65, 0.9], palms: 0.4 });
      return { root: g };
    },
    'taxis-cars': function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 20, d: 13, seed: 141 }));
      g.add(road(k, { len: 20.28, width: 7.6, lanes: 2, z: 1.8 }));
      g.add(sidewalk(k, 20.2, 1.8, 0, -2.85));
      g.add(at(bus(k, {}), -2.2, 0.1, laneZ(1.8, 7.6, 2, 0) + 0.1, Math.PI));
      g.add(at(car(k, '#ff8a1c', { taxi: true }), 5.4, 0.1, laneZ(1.8, 7.6, 2, 1)));
      g.add(at(car(k, '#ff8a1c', { taxi: true }), -6.6, 0.1, laneZ(1.8, 7.6, 2, 1)));
      scatterTrees(k, g, { seed: 23, n: 5, x: [-9, 9], z: [-5.6, -4.2], s: [0.65, 0.95], palms: 0.3 });
      crowd(k, g, { seed: 8, n: 3, x: [-4, 4], z: [-3.1, -2.6] });
      return { root: g };
    },
    chevalets: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 13, d: 10.5, seed: 151, depth: 5.4 }));
      g.add(sidewalk(k, 13.2, 10.4, 0, 0));
      g.add(at(chevalet(k, {}), -2.6, 0.19, 0.6, 0.5));
      g.add(at(chevalet(k, {}), 2.4, 0.19, 1.6, -0.4));
      g.add(at(etal(k, { toile: C.jaune, seed: 5 }), -0.2, 0.19, -2.4));
      g.add(at(etal(k, { toile: C.rouge, seed: 6 }), 3.6, 0.19, -2.6));
      g.add(at(palm(k, 0.7, 31), -4.8, 0.19, -3.4));
      crowd(k, g, { seed: 12, n: 6, x: [-4.6, 4.6], z: [-1.2, 3.4] });
      return { root: g };
    },
    roadshows: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 20, d: 15, seed: 161 }));
      g.add(sidewalk(k, 20.2, 14.8, 0, 0));
      g.add(at(podium(k, {}), -1.5, 0.19, -3.6));
      crowd(k, g, { seed: 14, n: 12, x: [-6.5, 5.5], z: [2.4, 6], ry: Math.PI });
      g.add(at(palm(k, 0.8, 41), 8.2, 0.19, -5.4));
      g.add(at(palm(k, 0.7, 42), -8.4, 0.19, 5.4));
      return { root: g };
    },
    studio: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 12, d: 11, seed: 171, depth: 5.6 }));
      g.add(at(studio(k, {}), 0, 0.19, -0.5));
      return { root: g };
    },
    stand: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 13, d: 11, seed: 181, depth: 5.6 }));
      g.add(sidewalk(k, 12.8, 10.8, 0, 0));
      g.add(at(stand(k, {}), 0, 0.19, -0.8));
      return { root: g };
    },
    heroMain: function (k) {
      var g = new T.Group(), cars = [];
      g.add(island(k, { w: 46, d: 30, seed: 3, depth: 22, pebbles: 6 }));
      g.add(road(k, { len: 46.28, width: 13, lanes: 4, median: true, z: 6 }));
      var u = panneau(k, { w: HERO_W, h: HERO_H, face: creaMat('bat'), back: creaMat('bat'), H: 11 });
      g.add(at(u, 2, 0, -4.6));
      scatterTrees(k, g, { seed: 21, n: 18, x: [-21.5, 21.5], z: [-13.5, -7.2], s: [0.95, 1.45], avoid: [{ x: 2, z: -4.6, r: 2.6 }], palms: 0.22 });
      scatterTrees(k, g, { seed: 22, n: 5, x: [-20, 20], z: [13.2, 14.3], s: [0.55, 0.75] });
      [-17, -5, 9, 20].forEach(function (x) { g.add(at(streetLamp(k, 7.5, 1.8), x, 0, -1.3)); });
      var palette = ['#c9d0d8', C.rouge, '#5b636d', '#f2f2f0', '#ff8a1c', '#2b5fae'];
      for (var i = 0; i < 6; i++) {
        var lane = i % 4, dir = lane < 2 ? -1 : 1;
        var c = at(bake(car(k, palette[i], { decor: palette[i] === C.rouge, taxi: palette[i] === '#ff8a1c' })), -20 + i * 7.3, 0.1, laneZ(6, 13, 4, lane), dir < 0 ? Math.PI : 0);
        c.userData.dynamic = true;
        c.userData.speed = (7 + (i * 37 % 5)) * dir;
        g.add(c); cars.push(c);
      }
      // face : centre du panneau (pour le cadrage du héro)
      return { root: g, cars: cars, face: new T.Vector3(2, 11 + 0.62 + 0.25 + (HERO_H + 0.34) / 2, -4.6 + 0.33) };
    },
    mini: function (k) {
      var g = new T.Group();
      g.add(island(k, { w: 9, d: 7, seed: 91, depth: 6, pebbles: 2 }));
      g.add(at(palm(k, 0.9, 5), -1.6, 0, -0.8));
      g.add(at(tree(k, 0.8, 92), 1.8, 0, 0.6));
      g.add(at(tree(k, 0.55, 93), 2.6, 0, -1.8));
      return { root: g };
    }
  };

  /* ================================================================== */
  /* Fusion des géométries statiques                                     */
  /* ================================================================== */
  function sliceGeo(geo, start, count) {
    var out = new T.BufferGeometry();
    for (var name in geo.attributes) {
      var a = geo.attributes[name];
      out.setAttribute(name, new T.BufferAttribute(a.array.slice(start * a.itemSize, (start + count) * a.itemSize), a.itemSize));
    }
    return out;
  }
  function mergeGeos(geos) {
    var names = Object.keys(geos[0].attributes).filter(function (n) { return geos.every(function (g) { return g.attributes[n]; }); });
    var out = new T.BufferGeometry();
    names.forEach(function (n) {
      var size = geos[0].attributes[n].itemSize, total = 0;
      geos.forEach(function (g) { total += g.attributes[n].count; });
      var arr = new Float32Array(total * size), off = 0;
      geos.forEach(function (g) { arr.set(g.attributes[n].array, off); off += g.attributes[n].array.length; });
      out.setAttribute(n, new T.BufferAttribute(arr, size));
    });
    return out;
  }
  function bake(root, o) {
    o = o || {};
    root.updateMatrixWorld(true);
    var inv = new T.Matrix4().copy(root.matrixWorld).invert(), buckets = new Map(), victims = [];
    function push(mat, src, geo) {
      var key = mat.uuid + (src.castShadow ? 'c' : '') + (src.receiveShadow ? 'r' : '');
      var b = buckets.get(key);
      if (!b) { b = { mat: mat, cast: src.castShadow, rec: src.receiveShadow, geos: [] }; buckets.set(key, b); }
      b.geos.push(geo);
    }
    root.traverse(function (obj) {
      if (!obj.isMesh) return;
      for (var p = obj; p && p !== root; p = p.parent) if (p.userData.dynamic) return;
      var m = new T.Matrix4().multiplyMatrices(inv, obj.matrixWorld);
      var geo = obj.geometry.index ? obj.geometry.toNonIndexed() : obj.geometry.clone();
      geo.applyMatrix4(m);
      if (Array.isArray(obj.material)) {
        if (geo.groups.length) geo.groups.forEach(function (gr) { push(obj.material[gr.materialIndex], obj, sliceGeo(geo, gr.start, gr.count)); });
        else push(obj.material[0], obj, geo);
      } else push(obj.material, obj, geo);
      victims.push(obj);
    });
    victims.forEach(function (v) { v.parent.remove(v); });
    buckets.forEach(function (b) {
      var mm = new T.Mesh(mergeGeos(b.geos), b.mat);
      mm.castShadow = o.noShadow ? false : b.cast;
      mm.receiveShadow = o.noShadow ? false : b.rec;
      root.add(mm);
    });
    return root;
  }
  function disposeTree(root) {
    var seen = new Set();
    root.traverse(function (o) {
      if (!o.isMesh) return;
      if (o.geometry) o.geometry.dispose();
      (Array.isArray(o.material) ? o.material : [o.material]).forEach(function (m) {
        if (!m || !m.userData.temp || seen.has(m)) return;
        seen.add(m);
        if (m.map) m.map.dispose();
        m.dispose();
      });
    });
  }

  /* ================================================================== */
  /* Rendu                                                               */
  /* ================================================================== */
  function setupRenderer(r) {
    r.outputEncoding = T.sRGBEncoding;
    r.toneMapping = T.ACESFilmicToneMapping;
    r.toneMappingExposure = 1.02;
    r.shadowMap.enabled = true;
    r.shadowMap.type = T.PCFSoftShadowMap;
    r.setClearColor(0x000000, 0);
  }
  function addLights(scene, center, radius, mapSize) {
    scene.add(new T.HemisphereLight('#d8ecff', '#b2703f', 0.5));
    var sun = new T.DirectionalLight('#fff2df', 2.2);
    sun.position.copy(center).add(new T.Vector3(-0.55, 0.95, 0.5).normalize().multiplyScalar(radius * 2.5));
    sun.target.position.copy(center);
    sun.castShadow = true;
    sun.shadow.mapSize.set(mapSize || 2048, mapSize || 2048);
    var sc = sun.shadow.camera;
    sc.left = -radius; sc.right = radius; sc.top = radius; sc.bottom = -radius; sc.near = 1; sc.far = radius * 6;
    sun.shadow.bias = -0.0005; sun.shadow.normalBias = 0.04;
    scene.add(sun); scene.add(sun.target);
    return sun;
  }
  function fitCamera(cam, root, az, el, fill, yBias) {
    root.updateMatrixWorld(true);
    var pts = [], v = new T.Vector3();
    root.traverse(function (o) {
      if (!o.isMesh) return;
      var p = o.geometry.attributes.position, step = Math.max(1, Math.floor(p.count / 400));
      for (var i = 0; i < p.count; i += step) pts.push(v.fromBufferAttribute(p, i).applyMatrix4(o.matrixWorld).clone());
    });
    var bx = new T.Box3().setFromPoints(pts), target = bx.getCenter(new T.Vector3());
    var dir = new T.Vector3(Math.sin(az) * Math.cos(el), Math.sin(el), Math.cos(az) * Math.cos(el));
    var dist = bx.getSize(new T.Vector3()).length() * 1.4, right = new T.Vector3(), up = new T.Vector3();
    for (var it = 0; it < 6; it++) {
      cam.position.copy(target).addScaledVector(dir, dist);
      cam.lookAt(target); cam.updateMatrixWorld(); cam.updateProjectionMatrix();
      var minX = 1e9, maxX = -1e9, minY = 1e9, maxY = -1e9;
      for (var j = 0; j < pts.length; j++) {
        v.copy(pts[j]).project(cam);
        if (v.x < minX) minX = v.x; if (v.x > maxX) maxX = v.x;
        if (v.y < minY) minY = v.y; if (v.y > maxY) maxY = v.y;
      }
      var halfH = dist * Math.tan(cam.fov * Math.PI / 360), halfW = halfH * cam.aspect;
      right.setFromMatrixColumn(cam.matrixWorld, 0); up.setFromMatrixColumn(cam.matrixWorld, 1);
      target.addScaledVector(right, (minX + maxX) / 2 * halfW).addScaledVector(up, ((minY + maxY) / 2 - (yBias || 0)) * halfH);
      dist *= Math.max((maxX - minX) / 2, (maxY - minY) / 2) / fill;
    }
    cam.position.copy(target).addScaledVector(dir, dist);
    cam.lookAt(target);
    cam.updateMatrixWorld();
    return { target: target, dist: dist };
  }

  /* --- Rendu instantané (visuels des fiches) --- */
  var SNAP = null;
  function rendu(id, size, opts) {
    opts = opts || {};
    var ss = opts.supersample || 2, px = size * ss;
    if (!SNAP) {
      SNAP = new T.WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: true });
      setupRenderer(SNAP); SNAP.setPixelRatio(1); SNAP.__env = envMap(SNAP);
    }
    SNAP.setSize(px, px, false);
    var scene = new T.Scene(); scene.environment = SNAP.__env;
    var d = DIO[id](kit(), opts);
    scene.add(d.root);
    var cam = new T.PerspectiveCamera(opts.fov || 24, 1, 0.5, 3000);
    fitCamera(cam, d.root, (opts.az == null ? 32 : opts.az) * Math.PI / 180, (opts.el == null ? 17 : opts.el) * Math.PI / 180, opts.fill || 0.9, opts.yBias || 0);
    var sphere = new T.Box3().setFromObject(d.root).getBoundingSphere(new T.Sphere());
    addLights(scene, sphere.center, sphere.radius * 0.9, 4096);
    SNAP.render(scene, cam);
    var out = cvs(size, size), g = out.getContext('2d');
    g.imageSmoothingEnabled = true; g.imageSmoothingQuality = 'high';
    g.drawImage(SNAP.domElement, 0, 0, size, size);
    disposeTree(d.root);
    return out;
  }

  /* --- Nuages --- */
  var CLOUD_TEX = null;
  function cloudSprites(scene, list, specs) {
    if (!CLOUD_TEX) CLOUD_TEX = [cloudTexture(1), cloudTexture(2), cloudTexture(3), cloudTexture(4), cloudTexture(5)];
    specs.forEach(function (s, i) {
      var m = new T.SpriteMaterial({ map: CLOUD_TEX[i % CLOUD_TEX.length], transparent: true, depthWrite: false, opacity: s.o, fog: true });
      var sp = new T.Sprite(m);
      sp.position.set(s.x, s.y, s.z); sp.scale.set(s.s, s.s * 0.5, 1); sp.renderOrder = -1;
      scene.add(sp);
      list.push({ sp: sp, o: s.o, drift: s.drift || 0.4, near: s.near || 0 });
    });
  }

  /* ================================================================== */
  /* Visionneuse 3D (fiche dispositif)                                   */
  /* ================================================================== */
  function createViewer(canvas) {
    var renderer;
    try { renderer = new T.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true }); } catch (e) { return null; }
    setupRenderer(renderer);
    renderer.setPixelRatio(Math.min(global.devicePixelRatio || 1, 1.5));
    var env = envMap(renderer), scene = null, current = null, cam = new T.PerspectiveCamera(26, 1, 0.5, 3000), pivot = null, anim = null;
    var st = { yaw: 0, pitch: 0, vy: 0.0035, drag: false, lx: 0, ly: 0, raf: 0, running: false, last: 0, t: 0 };
    var LED = ['led1', 'led2', 'led3'], led = null;
    function size() { var w = canvas.clientWidth || 600, h = canvas.clientHeight || 450; renderer.setSize(w, h, false); cam.aspect = w / h; cam.updateProjectionMatrix(); }
    function show(id, opts) {
      opts = opts || {};
      if (current) disposeTree(current);
      scene = new T.Scene(); scene.environment = env; anim = null;
      var k = kit(), o = { format: opts.format };
      if (id === 'ecrans-digitaux') {
        if (!led) { led = { c: cvs(1024, 546), frames: LED.map(function (n) { return CREAS[n](); }), i: 0, t: 0 }; led.tex = tex(led.c); led.mat = new T.MeshBasicMaterial({ map: led.tex, toneMapped: false }); }
        led.c.getContext('2d').drawImage(led.frames[0], 0, 0); led.tex.needsUpdate = true; led.i = 0; led.t = 0;
        o.screen = led.mat;
      }
      var d = DIO[id](k, o);
      if (d.trivision) anim = { type: 'tri', tri: d.trivision, t: 0 };
      else if (id === 'ecrans-digitaux') anim = { type: 'led' };
      bake(d.root);
      var bx = new T.Box3().setFromObject(d.root), c = bx.getCenter(new T.Vector3());
      pivot = new T.Group(); d.root.position.sub(c); pivot.add(d.root); scene.add(pivot);
      addLights(scene, new T.Vector3(), bx.getSize(new T.Vector3()).length() * 0.5, 2048);
      size();
      fitCamera(cam, pivot, 32 * Math.PI / 180, 16 * Math.PI / 180, 0.9, 0);
      if (!opts.keepAngle) { st.yaw = 0; st.pitch = 0; }
      pivot.rotation.y = st.yaw;
      current = d.root;
    }
    function loop(now) {
      st.raf = 0;
      var dt = st.last ? clamp((now - st.last) / 1000, 0, 0.05) : 0.016; st.last = now;
      if (!st.drag) st.yaw += st.vy;
      if (pivot) { pivot.rotation.y = st.yaw; pivot.rotation.x = st.pitch; }
      if (anim && anim.type === 'tri') { anim.t += dt; var cyc = anim.t % 9; anim.tri.regler(cyc < 3 ? 0 : cyc < 6 ? (cyc - 3) * 0.6 : 1 + (cyc - 6) * 0.65); }
      if (anim && anim.type === 'led' && led) { led.t += dt; if (led.t > 2.6) { led.t = 0; led.i = (led.i + 1) % led.frames.length; led.c.getContext('2d').drawImage(led.frames[led.i], 0, 0); led.tex.needsUpdate = true; } }
      if (scene) renderer.render(scene, cam);
      if (st.running) st.raf = global.requestAnimationFrame(loop);
    }
    canvas.addEventListener('pointerdown', function (e) { st.drag = true; st.lx = e.clientX; st.ly = e.clientY; canvas.setPointerCapture(e.pointerId); });
    canvas.addEventListener('pointermove', function (e) {
      if (!st.drag) return;
      st.yaw += (e.clientX - st.lx) * 0.008;
      st.pitch = clamp(st.pitch + (e.clientY - st.ly) * 0.003, -0.12, 0.3);
      st.lx = e.clientX; st.ly = e.clientY;
    });
    function end() { st.drag = false; }
    canvas.addEventListener('pointerup', end);
    canvas.addEventListener('pointercancel', end);
    return {
      show: show,
      start: function () { if (st.running) return; st.running = true; st.last = 0; st.raf = global.requestAnimationFrame(loop); },
      stop: function () { st.running = false; if (st.raf) global.cancelAnimationFrame(st.raf); st.raf = 0; },
      resize: size
    };
  }

  /* ================================================================== */
  /* Scène d'ouverture (accueil)                                         */
  /* ================================================================== */
  /* Archipel : les 5 territoires de visibilité autour de l'île principale (affichage). */
  var ARCHIPEL = [
    { id: 'camions', ry: 0.55, s: 1.0, wide: [-0.63, 0.2, 0.9], tall: [-0.5, 0.66, 0.95] },
    { id: 'ecrans-digitaux', ry: -0.55, s: 1.1, wide: [0.64, 0.3, 0.95], tall: [0.52, 0.04, 0.85] },
    { id: 'stand', ry: -0.7, s: 1.25, wide: [0.7, -0.42, 0.62], tall: [-0.56, -0.02, 0.8] },
    { id: 'studio', ry: 0.35, s: 1.3, wide: [-0.3, 0.6, 1.35], tall: [0.5, 0.76, 1.3] },
    { id: 'trivision', ry: -0.35, s: 1.15, wide: [0.33, 0.62, 1.4], tall: [0.62, 0.42, 1.5] },
    { id: 'lumipub', ry: 0.6, s: 0.95, wide: [-0.74, -0.44, 0.75], tall: [0.6, -0.6, 0.7] }
  ];
  function framing(out, center, R, az, el, nx, ny, frac, aspect, tanV) {
    var dir = new T.Vector3(Math.sin(az) * Math.cos(el), Math.sin(el), Math.cos(az) * Math.cos(el));
    var D = Math.max(R / (frac * tanV), R / (frac * tanV * aspect));
    out.pos.copy(center).addScaledVector(dir, D);
    var fwd = dir.clone().negate(), right = new T.Vector3().crossVectors(fwd, new T.Vector3(0, 1, 0)).normalize(), up = new T.Vector3().crossVectors(right, fwd).normalize();
    var halfH = D * tanV, halfW = halfH * aspect;
    out.look.copy(center).addScaledVector(right, -nx * halfW).addScaledVector(up, -ny * halfH);
    out.D = D;
    return out;
  }
  function screenPoint(k, nx, ny, dist, aspect, tanV) {
    var fwd = new T.Vector3().subVectors(k.look, k.pos).normalize(), right = new T.Vector3().crossVectors(fwd, new T.Vector3(0, 1, 0)).normalize(), up = new T.Vector3().crossVectors(right, fwd).normalize();
    var d = fwd.clone().addScaledVector(right, nx * tanV * aspect).addScaledVector(up, ny * tanV).normalize();
    return k.pos.clone().addScaledVector(d, dist);
  }

  function createHero(canvas, opts) {
    opts = opts || {};
    var renderer;
    try { renderer = new T.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true, powerPreference: 'high-performance', preserveDrawingBuffer: !!opts.capture }); } catch (e) { return null; }
    setupRenderer(renderer);
    var lowPower = !!opts.lowPower;
    renderer.setPixelRatio(opts.pixelRatio || Math.min(global.devicePixelRatio || 1, lowPower ? 1.25 : 1.5));

    var k = kit(), scene = new T.Scene();
    scene.environment = envMap(renderer);
    scene.fog = new T.Fog('#a7dbfb', 140, 900);
    var main = DIO.heroMain(k);
    bake(main.root);
    scene.add(main.root);
    addLights(scene, new T.Vector3(0, 4, 0), 30, lowPower ? 1024 : 2048);
    var MAIN_C = new T.Vector3(0, -3, 0), MAIN_R = 26;

    var isles = ARCHIPEL.slice(0, lowPower ? 5 : 6).map(function (a, i) {
      var b = DIO[a.id](k, {});
      bake(b.root, { noShadow: true });
      b.root.rotation.y = a.ry; b.root.scale.setScalar(a.s);
      scene.add(b.root);
      return { obj: b.root, a: a, base: new T.Vector3(), ry: a.ry, ph: i * 1.7 };
    });
    var mini = DIO.mini(k);
    bake(mini.root, { noShadow: true });
    mini.root.scale.setScalar(1.4);
    scene.add(mini.root);
    isles.push({ obj: mini.root, a: { mini: true }, base: new T.Vector3(), ry: 0.4, ph: 4.2 });

    // perroquet en vol autour du panneau
    var oiseau = perroquet(k);
    scene.add(oiseau.group);
    var vol = new T.CatmullRomCurve3([
      new T.Vector3(-34, 19, 2), new T.Vector3(-14, 23, 12), new T.Vector3(10, 24, 13), new T.Vector3(30, 20, 4),
      new T.Vector3(26, 25, -18), new T.Vector3(2, 29, -24), new T.Vector3(-26, 24, -16)
    ], true, 'centripetal');
    var tmpA = new T.Vector3(), tmpB = new T.Vector3();

    var halo = new T.Group();
    [26, 44, 62, 80].forEach(function (r, i) {
      halo.add(new T.Mesh(new T.RingGeometry(r, r + 5 - i * 0.6, 96), new T.MeshBasicMaterial({ color: '#ffffff', transparent: true, opacity: 0.16 - i * 0.025, depthWrite: false, fog: false })));
    });
    halo.position.set(10, 30, -260);
    scene.add(halo);

    var clouds = [], r = rng(77), specs = [];
    for (var i = 0; i < (lowPower ? 18 : 30); i++) specs.push({ x: -420 + r() * 840, y: -48 - r() * 30, z: -480 + r() * 640, s: 100 + r() * 120, o: 0.96, drift: 0.6 + r() * 0.8 });
    for (var j = 0; j < (lowPower ? 8 : 14); j++) specs.push({ x: -240 + r() * 480, y: -10 + r() * 60, z: -360 + r() * 260, s: 30 + r() * 44, o: 0.92, drift: 0.8 + r() });
    var nearSpecs = [];
    for (var n = 0; n < 6; n++) nearSpecs.push({ x: 0, y: 0, z: 0, s: 34 + n * 3, o: 0.96, drift: 0, near: 1 });
    cloudSprites(scene, clouds, specs);
    var nearClouds = [];
    cloudSprites(scene, nearClouds, nearSpecs);
    clouds = clouds.concat(nearClouds);

    var cam = new T.PerspectiveCamera(32, 1, 0.5, 2400), face = main.face.clone();
    var K = [{ pos: new T.Vector3(), look: new T.Vector3() }, { pos: new T.Vector3(), look: new T.Vector3() }, { pos: new T.Vector3(), look: new T.Vector3() }];
    var state = { p: 0, ps: 0, px: 0, py: 0, sx: 0, sy: 0, active: true, raf: 0, t0: performance.now(), last: performance.now(), fly: opts.flyPhase || 0 };
    var reduce = !!opts.reducedMotion;

    function layout() {
      var w = canvas.clientWidth || global.innerWidth, h = canvas.clientHeight || global.innerHeight;
      if (w === state.w && h === state.h) return;
      state.w = w; state.h = h;
      renderer.setSize(w, h, false);
      var aspect = w / h, tall = aspect < 0.9;
      cam.aspect = aspect; cam.fov = tall ? 42 : 32; cam.updateProjectionMatrix();
      var tanV = Math.tan(cam.fov * Math.PI / 360);
      // Écrans 4:3 à 3:2 (tablettes paysage, 1024 × 768) : panneau un peu plus petit et plus à droite, pour laisser le titre lisible.
      var etroit = !tall && aspect <= 1.5;
      var frac = tall ? 0.9 : (aspect > 1.75 ? 0.44 : etroit ? 0.41 : 0.47);
      var D = (HERO_W + 0.8) / (2 * frac * tanV * aspect);
      var az = tall ? 0.12 : 0.2, el = tall ? -0.08 : -0.16;
      K[0].pos.set(face.x + Math.sin(az) * D, face.y + Math.sin(el) * D, face.z + Math.cos(az) * D);
      var sx = tall ? 0 : (aspect > 1.75 ? 0.36 : etroit ? 0.43 : 0.32), sy = tall ? 0.5 : 0.12;
      K[0].look.set(face.x - sx * D * tanV * aspect, face.y - sy * D * tanV, face.z);
      if (tall) framing(K[1], MAIN_C, MAIN_R, -0.42, 0.14, 0, -0.32, 0.8, aspect, tanV);
      else framing(K[1], MAIN_C, MAIN_R, -0.45, 0.12, 0.36, -0.04, 0.64, aspect, tanV);
      if (tall) framing(K[2], MAIN_C, MAIN_R, 0.42, 0.2, 0, 0.36, 0.42, aspect, tanV);
      else framing(K[2], MAIN_C, MAIN_R, 0.42, 0.2, 0, 0.32, 0.29, aspect, tanV);
      var D2 = K[2].D;
      isles.forEach(function (s) {
        if (s.a.mini) { var spot = tall ? [-0.62, 0.66, 150] : [0.8, 0.5, 170]; s.base.copy(screenPoint(K[0], spot[0], spot[1], spot[2], aspect, tanV)); }
        else { var sl = tall ? s.a.tall : s.a.wide; s.base.copy(screenPoint(K[2], sl[0], sl[1], sl[2] * D2, aspect, tanV)); }
        s.obj.position.copy(s.base);
      });
      nearClouds.forEach(function (c, ii) {
        c.sp.position.lerpVectors(K[1].pos, K[2].pos, 0.38 + ii * 0.06);
        c.sp.position.x += (ii % 2 ? 1 : -1) * (4 + ii * 1.5);
        c.sp.position.y += (ii % 3 - 1) * 4;
      });
    }

    var tmpPos = new T.Vector3(), tmpLook = new T.Vector3();
    function cameraAt(p) {
      var a = ease((p - 0.08) / 0.4), b = ease((p - 0.56) / 0.4);
      if (b <= 0) { tmpPos.lerpVectors(K[0].pos, K[1].pos, a); tmpLook.lerpVectors(K[0].look, K[1].look, a); tmpPos.y += Math.sin(a * Math.PI) * 10; }
      else { tmpPos.lerpVectors(K[1].pos, K[2].pos, b); tmpLook.lerpVectors(K[1].look, K[2].look, b); tmpPos.y += Math.sin(b * Math.PI) * 26; }
    }

    function frame(now) {
      state.raf = 0;
      var dt = clamp((now - state.last) / 1000, 0, 0.05), t = Math.max(0, (now - state.t0) / 1000);
      state.last = now;
      state.ps += (state.p - state.ps) * Math.min(1, dt * 5);
      state.sx += (state.px - state.sx) * Math.min(1, dt * 3);
      state.sy += (state.py - state.sy) * Math.min(1, dt * 3);
      if (reduce) { var kk = K[state.p < 0.33 ? 0 : state.p < 0.75 ? 1 : 2]; tmpPos.copy(kk.pos); tmpLook.copy(kk.look); } else cameraAt(state.ps);
      var par = reduce ? 0 : 1;
      tmpPos.x += state.sx * 2.2 * par; tmpPos.y += -state.sy * 1.2 * par;
      if (!reduce) tmpPos.y += Math.sin(t * 0.5) * 0.25;
      cam.position.copy(tmpPos); cam.lookAt(tmpLook);

      if (reduce) isles.forEach(function (s) { s.obj.visible = s.a.mini ? state.p < 0.33 : state.p >= 0.75; });
      if (!reduce) {
        main.root.position.y = Math.sin(t * 0.45) * 0.35;
        isles.forEach(function (s, ii) {
          var rise = s.a.mini ? 1 + ease((state.ps - 0.06) / 0.3) * 0.8 : ease((state.ps - 0.52 - ii * 0.025) / 0.3);
          s.obj.visible = s.a.mini ? rise < 1.79 : rise > 0.001;
          s.obj.position.y = s.base.y + Math.sin(t * 0.4 + s.ph) * 1.4 - (1 - rise) * 190;
          s.obj.rotation.y = s.ry + Math.sin(t * 0.15 + s.ph) * 0.06;
        });
        main.cars.forEach(function (c) {
          c.position.x += c.userData.speed * dt;
          if (c.position.x > 22.6) c.position.x = -22.6;
          if (c.position.x < -22.6) c.position.x = 22.6;
          c.scale.setScalar(clamp((23.1 - Math.abs(c.position.x)) / 1.6, 0.001, 1));
        });
      }
      // vol du perroquet
      state.fly = (((state.fly + (reduce ? 0 : dt * 0.042)) % 1) + 1) % 1;
      vol.getPointAt(state.fly, tmpA);
      vol.getPointAt((state.fly + 0.004) % 1, tmpB);
      oiseau.group.position.copy(tmpA);
      oiseau.group.lookAt(tmpB);
      var virage = vol.getTangentAt(state.fly).cross(vol.getTangentAt((state.fly + 0.03) % 1)).y;
      oiseau.group.rotateZ(clamp(-virage * 9, -0.6, 0.6));
      var plane = (Math.sin(t * 0.7) + 1) / 2;
      oiseau.battre(t, reduce ? 0.2 : 0.55 + plane * 0.45);

      halo.lookAt(cam.position);
      var seg = clamp((state.ps - 0.56) / 0.4, 0, 1), fly = reduce ? 0 : Math.sin(Math.PI * clamp((seg - 0.12) / 0.62, 0, 1));
      clouds.forEach(function (c) {
        if (!reduce && c.drift) { c.sp.position.x += c.drift * dt; if (c.sp.position.x > 440) c.sp.position.x = -440; }
        var d = c.sp.position.distanceTo(cam.position);
        c.sp.material.opacity = c.o * smooth(c.near ? 5 : 24, c.near ? 30 : 70, d) * (c.near ? fly : 1);
        c.sp.visible = c.sp.material.opacity > 0.005;
      });
      renderer.render(scene, cam);
      if (state.onFirst) { var cb = state.onFirst; state.onFirst = null; cb(); }
      if (state.active) state.raf = global.requestAnimationFrame(frame);
    }

    layout();
    var api = {
      setProgress: function (p) { state.p = clamp(p, 0, 1); if (reduce) state.ps = state.p; },
      setPointer: function (x, y) { state.px = x; state.py = y; },
      setActive: function (on) { state.active = on; if (on && !state.raf) { state.last = performance.now(); state.raf = global.requestAnimationFrame(frame); } },
      resize: layout,
      onFirstFrame: function (cb) { state.onFirst = cb; },
      renderer: renderer
    };
    state.raf = global.requestAnimationFrame(frame);
    return api;
  }

  /* ================================================================== */
  /* API publique                                                        */
  /* ================================================================== */
  var ready = (function () {
    var waits = [];
    if (document.fonts && document.fonts.load) {
      waits.push(Promise.all([
        document.fonts.load('800 100px "Cible Titre"', 'Vous visez juste ÉÀ+0123456789'),
        document.fonts.load('600 100px "Cible Titre"', 'Présence continue')
      ]).catch(function () {}));
    }
    if (LOGO.decode && M.logo) waits.push(LOGO.decode().catch(function () {}));
    if (BAT.decode && B) waits.push(BAT.decode().catch(function () {}));
    return Promise.race([Promise.all(waits), new Promise(function (res) { setTimeout(res, 3500); })]);
  })();

  global.CibleMonde = {
    ready: ready,
    createHero: createHero,
    createViewer: createViewer,
    rendu: rendu,
    formats: FORMATS,
    /* Aperçu d’une créa (outils de contrôle) : CibleMonde.crea('triA') renvoie un canvas. */
    crea: function (nom) { return CREAS[nom] ? CREAS[nom]() : null; },
    creas: function () { return Object.keys(CREAS); },
    dioramas: ['panneaux-classiques', 'lumipub', 'trivision', 'panoramiques', 'ecrans-digitaux', 'ecrans-magasins', 'camions', 'motos', 'branding-vehicules', 'taxis-cars', 'chevalets', 'roadshows', 'studio', 'stand']
  };
})(window);

@extends('public.cible._layout', [
    'seo_title'       => 'Notre réseau — CIBLE · 364 panneaux dans 31 communes',
    'seo_description' => 'Le réseau CIBLE : 364 panneaux publicitaires · 180 à Abidjan (14 communes) · 184 à l\'intérieur (17 villes) · Détail par commune.',
])

@push('page-css')
    .reseau-hero{background:var(--bleu);color:#fff;padding:clamp(60px,8vw,110px) var(--pad)}
    .reseau-hero .sur{color:rgba(255,255,255,.85)}
    .reseau-hero h1{margin-top:14px;color:#fff;max-width:22ch}
    .reseau-hero p{margin-top:22px;max-width:56ch;color:rgba(255,255,255,.9);font-size:18px}
    .stats{display:flex;gap:44px;flex-wrap:wrap;margin-top:44px}
    .stats .v{font-family:var(--titre);font-weight:900;font-size:clamp(46px,6vw,80px);line-height:.86;letter-spacing:-.04em}
    .stats .l{font-family:var(--titre);font-weight:600;font-size:13px;opacity:.9;margin-top:10px;text-transform:uppercase;letter-spacing:.08em}

    .carte-section{padding:clamp(56px,8vw,100px) var(--pad)}
    .carte-slot{aspect-ratio:16/10;border-radius:26px;overflow:hidden;background:var(--gris);position:relative}
    .carte-slot .note{position:absolute;bottom:16px;left:16px;right:16px;background:rgba(255,255,255,.94);padding:12px 16px;border-radius:10px;font-size:13px;color:#666;font-family:var(--titre);font-weight:600}

    .communes{padding:clamp(56px,8vw,100px) var(--pad);background:var(--gris)}
    .communes .entete{max-width:600px;margin-bottom:40px}
    .communes .sur{color:var(--vert)}
    .communes-grid{display:grid;grid-template-columns:1fr 1fr;gap:clamp(20px,3vw,40px)}
    @media(max-width:800px){.communes-grid{grid-template-columns:1fr}}
    .zone{background:#fff;border-radius:16px;padding:32px;border-top:5px solid var(--c)}
    .zone h3{font-family:var(--titre);font-weight:900;font-size:26px;color:var(--c);margin-bottom:6px}
    .zone .zone-sub{font-family:var(--titre);font-weight:600;font-size:14px;color:#666;margin-bottom:20px}
    .zone-list{list-style:none;display:grid;grid-template-columns:repeat(2,1fr);gap:8px 20px;font-size:14.5px;color:#333}
    .zone-list li{padding:6px 0;border-bottom:1px dashed #E4E4E4;font-family:var(--titre);font-weight:600}

    .qualite{padding:clamp(56px,8vw,100px) var(--pad)}
    .qualite .entete{max-width:600px;margin-bottom:44px}
    .qualite .sur{color:var(--rouge)}
    .q-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:clamp(18px,3vw,32px)}
    @media(max-width:900px){.q-grid{grid-template-columns:1fr}}
    .qcard{padding:28px;background:#fff;border:1px solid var(--gris);border-radius:16px}
    .qcard h4{font-family:var(--titre);font-weight:800;font-size:18px;margin-bottom:10px;color:var(--c)}
    .qcard p{font-size:14.5px;color:#555;line-height:1.6}
@endpush

@section('content')

<section class="reseau-hero">
    <div>
        <span class="sur">La preuve terrain</span>
        <h1 class="t1">Un réseau d'affichage en propre, à Abidjan et dans tout le pays.</h1>
        <p>Une agence loue l'espace d'un tiers. Nous exploitons le nôtre : 364 panneaux répartis dans 31 communes, de Bouaké à San-Pédro. C'est ce qui nous permet de vous garantir un emplacement, une date de pose et une preuve photo — pas une estimation.</p>
        <div class="stats">
            <div><div class="v num" data-cible="364">0</div><div class="l">Panneaux au total<br>31 communes</div></div>
            <div><div class="v num" data-cible="180">0</div><div class="l">Panneaux · Abidjan<br>14 communes</div></div>
            <div><div class="v num" data-cible="184">0</div><div class="l">Panneaux · Intérieur<br>17 villes</div></div>
        </div>
    </div>
</section>

<section class="carte-section">
    <div class="carte-slot rev">
        <div class="slot slot--sombre">
            Carte interactive du réseau CIBLE<br>
            (implémentation Leaflet · pins par commune)
            <small>À brancher sur la BDD Panora</small>
        </div>
        <div class="note">💡 La carte interactive complète est disponible pour votre commercial sur demande.</div>
    </div>
</section>

<section class="communes">
    <div class="entete rev">
        <span class="sur">Détail par zone</span>
        <h2 class="t1">Là où votre marque peut apparaître.</h2>
    </div>
    <div class="communes-grid">
        <div class="zone rev" style="--c:var(--rouge)">
            <h3>Zone Abidjan</h3>
            <div class="zone-sub">180 panneaux · 14 communes du Grand Abidjan</div>
            <ul class="zone-list">
                <li>Plateau</li><li>Cocody</li>
                <li>Yopougon</li><li>Abobo</li>
                <li>Marcory</li><li>Treichville</li>
                <li>Koumassi</li><li>Port-Bouët</li>
                <li>Attécoubé</li><li>Adjamé</li>
                <li>Riviera</li><li>Angré</li>
                <li>Bingerville</li><li>Songon</li>
            </ul>
        </div>
        <div class="zone rev" style="--c:var(--vert)">
            <h3>Zone Intérieur</h3>
            <div class="zone-sub">184 panneaux · 17 villes stratégiques du pays</div>
            <ul class="zone-list">
                <li>Bouaké</li><li>San-Pédro</li>
                <li>Yamoussoukro</li><li>Korhogo</li>
                <li>Man</li><li>Daloa</li>
                <li>Gagnoa</li><li>Divo</li>
                <li>Bondoukou</li><li>Odienné</li>
                <li>Séguéla</li><li>Ferkessédougou</li>
                <li>Dabou</li><li>Anyama</li>
                <li>Grand-Bassam</li><li>Aboisso</li>
                <li>Soubré</li>
            </ul>
        </div>
    </div>
</section>

<section class="qualite">
    <div class="entete rev">
        <span class="sur">Ce qui distingue notre réseau</span>
        <h2 class="t1">Un patrimoine géré, pas revendu.</h2>
    </div>
    <div class="q-grid">
        <div class="qcard rev" style="--c:var(--rouge)">
            <h4>Emplacements en propre</h4>
            <p>Nous n'agrégeons pas des panneaux tiers : nous exploitons notre patrimoine. Vous savez exactement où votre affiche apparaîtra, dans quel angle, à quel moment.</p>
        </div>
        <div class="qcard rev" style="--c:var(--vert)">
            <h4>Maintenance permanente</h4>
            <p>Équipes de pose sur toutes les zones. Une affiche déchirée ou taguée est remplacée dans les 48h. La qualité de votre visibilité ne dépend pas d'un sous-traitant.</p>
        </div>
        <div class="qcard rev" style="--c:var(--bleu)">
            <h4>Preuve photo horodatée</h4>
            <p>Chaque pose est documentée sur le terrain : photo, date, heure, GPS. Vous recevez le dossier complet à la fin de la campagne.</p>
        </div>
    </div>
</section>

<section style="padding:clamp(60px,8vw,100px) var(--pad);text-align:center">
    <h2 class="t2">Envie de repérer les emplacements pour votre marque&nbsp;?</h2>
    <p style="margin-top:16px;color:#666;max-width:56ch;margin-left:auto;margin-right:auto">Envoyez-nous vos critères (zone, format, période) — nous vous préparons une sélection dans la journée.</p>
    <div style="margin-top:28px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
        <a class="bouton b-rouge" href="{{ route('cible.contact') }}">Demander une sélection</a>
    </div>
</section>

@endsection

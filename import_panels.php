<?php
// Script d'import panneaux CIBLE CI
// Lancer avec : php artisan tinker < import_panels.php
// OU créer une commande Artisan et coller ce contenu

use App\Models\Commune;
use App\Models\Zone;
use App\Models\PanelFormat;
use App\Models\Panel;
use App\Enums\PanelStatus;

echo "\n=== IMPORT PANNEAUX CIBLE CI ===\n";

// ── 1. Créer les communes ──
$communes = [
    'Abengourou',
    'Abobo',
    'Aboisso',
    'Adjamé',
    'Adzopé',
    'Assinie',
    'Attécoubé',
    'Autoroute',
    'Bassam',
    'Bingerville',
    'Bondoukou',
    'Bonoua',
    'Bouaké',
    'Cocody',
    'Daloa',
    'Divo',
    'Ferké',
    'Gagnoa',
    'Korhogo',
    'Koumassi',
    'Koun Fao',
    'Man',
    'Marcory',
    'Odienné',
    'Plateau',
    'Port-Bouët',
    'Samo',
    'San-Pédro',
    'Soubré',
    'Tanda',
    'Treichville',
    'Yamoussoukro',
    'Yopougon',
];
echo "Création communes...\n";
$communeMap = [];
foreach ($communes as $name) {
    $c = Commune::firstOrCreate(['name' => $name]);
    $communeMap[$name] = $c->id;
    echo "  Commune: $name (id:{$c->id})\n";
}

// ── 2. Créer les zones ──
$zones = [
    'Abidjan',
    'Interieur',
];
echo "Création zones...\n";
$zoneMap = [];
foreach ($zones as $name) {
    $z = \App\Models\Zone::firstOrCreate(['name' => $name]);
    $zoneMap[$name] = $z->id;
    echo "  Zone: $name (id:{$z->id})\n";
}

// ── 3. Créer les formats ──
echo "Création formats...\n";
$formatMap = [];
$f = PanelFormat::firstOrCreate(['name' => '1.54x2.04m'], ['width' => 1.54, 'height' => 2.04, 'surface' => 3.14]);
$formatMap['1.54x2.04m'] = $f->id;
echo "  Format: 1.54x2.04m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '1.72x1.21m'], ['width' => 1.72, 'height' => 1.21, 'surface' => 2.08]);
$formatMap['1.72x1.21m'] = $f->id;
echo "  Format: 1.72x1.21m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '10x5m'], ['width' => 10.0, 'height' => 5.0, 'surface' => 50.0]);
$formatMap['10x5m'] = $f->id;
echo "  Format: 10x5m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '11x5m'], ['width' => 11.0, 'height' => 5.0, 'surface' => 55.0]);
$formatMap['11x5m'] = $f->id;
echo "  Format: 11x5m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '12x3m'], ['width' => 12.0, 'height' => 3.0, 'surface' => 36.0]);
$formatMap['12x3m'] = $f->id;
echo "  Format: 12x3m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '2x3m'], ['width' => 2.0, 'height' => 3.0, 'surface' => 6.0]);
$formatMap['2x3m'] = $f->id;
echo "  Format: 2x3m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.67x2.65m'], ['width' => 3.67, 'height' => 2.65, 'surface' => 9.73]);
$formatMap['3.67x2.65m'] = $f->id;
echo "  Format: 3.67x2.65m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.68x2.66m'], ['width' => 3.68, 'height' => 2.66, 'surface' => 9.79]);
$formatMap['3.68x2.66m'] = $f->id;
echo "  Format: 3.68x2.66m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.69x2.67m'], ['width' => 3.69, 'height' => 2.67, 'surface' => 9.85]);
$formatMap['3.69x2.67m'] = $f->id;
echo "  Format: 3.69x2.67m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.71x2.69m'], ['width' => 3.71, 'height' => 2.69, 'surface' => 9.98]);
$formatMap['3.71x2.69m'] = $f->id;
echo "  Format: 3.71x2.69m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.72x2.7m'], ['width' => 3.72, 'height' => 2.7, 'surface' => 10.04]);
$formatMap['3.72x2.7m'] = $f->id;
echo "  Format: 3.72x2.7m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.73x2.71m'], ['width' => 3.73, 'height' => 2.71, 'surface' => 10.11]);
$formatMap['3.73x2.71m'] = $f->id;
echo "  Format: 3.73x2.71m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.74x2.72m'], ['width' => 3.74, 'height' => 2.72, 'surface' => 10.17]);
$formatMap['3.74x2.72m'] = $f->id;
echo "  Format: 3.74x2.72m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.75x2.73m'], ['width' => 3.75, 'height' => 2.73, 'surface' => 10.24]);
$formatMap['3.75x2.73m'] = $f->id;
echo "  Format: 3.75x2.73m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.76x2.74m'], ['width' => 3.76, 'height' => 2.74, 'surface' => 10.3]);
$formatMap['3.76x2.74m'] = $f->id;
echo "  Format: 3.76x2.74m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3.7x2.68m'], ['width' => 3.7, 'height' => 2.68, 'surface' => 9.92]);
$formatMap['3.7x2.68m'] = $f->id;
echo "  Format: 3.7x2.68m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '3x2m'], ['width' => 3.0, 'height' => 2.0, 'surface' => 6.0]);
$formatMap['3x2m'] = $f->id;
echo "  Format: 3x2m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '4x3m'], ['width' => 4.0, 'height' => 3.0, 'surface' => 12.0]);
$formatMap['4x3m'] = $f->id;
echo "  Format: 4x3m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '4x5m'], ['width' => 4.0, 'height' => 5.0, 'surface' => 20.0]);
$formatMap['4x5m'] = $f->id;
echo "  Format: 4x5m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '6x3m'], ['width' => 6.0, 'height' => 3.0, 'surface' => 18.0]);
$formatMap['6x3m'] = $f->id;
echo "  Format: 6x3m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '6x9m'], ['width' => 6.0, 'height' => 9.0, 'surface' => 54.0]);
$formatMap['6x9m'] = $f->id;
echo "  Format: 6x9m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '7x3m'], ['width' => 7.0, 'height' => 3.0, 'surface' => 21.0]);
$formatMap['7x3m'] = $f->id;
echo "  Format: 7x3m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '9x4m'], ['width' => 9.0, 'height' => 4.0, 'surface' => 36.0]);
$formatMap['9x4m'] = $f->id;
echo "  Format: 9x4m (id:{$f->id})\n";
$f = PanelFormat::firstOrCreate(['name' => '9x6m'], ['width' => 9.0, 'height' => 6.0, 'surface' => 54.0]);
$formatMap['9x6m'] = $f->id;
echo "  Format: 9x6m (id:{$f->id})\n";

// ── 4. Importer les panneaux ──
echo "\nImport des panneaux...\n";
$created = 0;
$skipped = 0;

$panneaux = [
    ['ABG-001A', 'Route Agnibilekrou sens aller gare UTI', 'Abengourou', 'Interieur', 6.74425, -3.502667, 70000, '4x3m', 1],
    ['ABG-001B', 'Route Agnibilekrou-sens retour gare UTI', 'Abengourou', 'Interieur', null, null, 70000, '4x3m', 1],
    ['ABG-002', 'Rond point hôtel de ville carrefour route Niablé', 'Abengourou', 'Interieur', 6.730167, -3.274367, 70000, '4x3m', 1],
    ['ABG-004', 'Face station Oil Lybia - Orange', 'Abengourou', 'Interieur', 6.72955, -3.490933, 70000, '4x3m', 1],
    ['ABG-PAN-01', 'Entrée de ville', 'Abengourou', 'Interieur', null, null, 500000, '10x5m', 1],
    ['ABG-PAN-02', 'Sortie de ville', 'Abengourou', 'Interieur', null, null, 500000, '10x5m', 1],
    ['ABO-001A', 'Autoroute d\'Abobo Filtisac sens Adjamé face Abobo', 'Abobo', 'Abidjan', 5.3957457, -4.0211121, 90000, '4x3m', 1],
    ['ABO-001B', 'Autoroute d\'Abobo Filtisac sens Adjamé face Abobo', 'Abobo', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['ABO-002A', 'Autoroute d\'Abobo casse sens allé face Adjamé', 'Abobo', 'Abidjan', 5.3972263, -4.0207674, 90000, '4x3m', 1],
    ['ABO-003', 'Abobo Baoulé sortie Angré face nouvelle station Pétro Ivoire', 'Abobo', 'Abidjan', 5.4148241, -3.9953924, 90000, '4x3m', 1],
    ['ABO-004', 'Abobo route du Zoo après carrefour Zoo sens Abobo face Adjamé', 'Abobo', 'Abidjan', 5.3775461, -4.0076052, 90000, '4x3m', 1],
    ['ADJ-004', 'Adjamé, Bd Nangui Abrogoua avant BICICI et INSP face Plateau', 'Adjamé', 'Abidjan', 5.3401296, -4.0263085, 90000, '4x3m', 1],
    ['ADJCAIS-01A', 'Echangeur Indénié Sapeur Pompier face Adjamé', 'Adjamé', 'Abidjan', null, null, 600000, '6x3m', 1],
    ['ADJCAIS-01B', 'Echangeur Indénié Sapeur Pompier face Corniche Cocody', 'Adjamé', 'Abidjan', null, null, 600000, '6x3m', 1],
    ['ADJCAIS-02A', 'IMST face 220 Logements', 'Adjamé', 'Abidjan', null, null, 400000, '6x3m', 1],
    ['ADJCAIS-02B', 'IMST face Fraternité matin', 'Adjamé', 'Abidjan', null, null, 400000, '6x3m', 1],
    ['ADJCAIS-03A', 'Portique Adjamé Liberté face Adjamé', 'Adjamé', 'Abidjan', null, null, 850000, '12x3m', 1],
    ['ADJCAIS-03B', 'Portique Adjamé Liberté face Plateau', 'Adjamé', 'Abidjan', null, null, 850000, '12x3m', 1],
    ['ADZCAIS-01A', 'Entrée de ville Adzopé', 'Adzopé', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['ADZCAIS-01B', 'Sortie de ville Adzopé', 'Adzopé', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['ASS-001A', 'Assinie - A-côté-de-la villa-Blanca-Face-Assinie', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-001B', 'Assinie - A-côté-de-villa-Blanca-face-Assouinde', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-002A', 'Assinie - Après rond point d\'Assoindé Route d\'Assinie face Assouindé', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-002B', 'Assinie - Avant-rond-point-route-d\'assouindé-face-Abidjan', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-003A', 'Assinie - Entrée-Assoindé-avant-le-Panneau-Canal-Street-face Assouindé', 'Assinie', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['ASS-003B', 'Assinie - Entrée-Assoindé-avant-le-Panneau-Canal-Street-face-Assinie-sens-Assouindé', 'Assinie', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['ASS-004A', 'Assinie - Entrée Assouindé barrage de police face Bassam sens Assouindé', 'Assinie', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['ASS-004B', 'Assinie - Entrée Assouindé barrage de police face Bassam sens Bassam', 'Assinie', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['ASS-005A', 'Assinie - Après rond point Assouindé sens Assinie face rond point Assoindé face Abidjan', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-005B', 'Assinie - Après rond point Assouindé sens Assinie face rond point Assoindé face aAssouindé', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-008A', 'Assinie - Plaque-PK-18--Face-Abidjan', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-008B', 'Assinie - Plaque-PK-18-Face-Assinie', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-009A', 'Assinie - Plaque-P11-Face-Abidjan', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ASS-009B', 'Assinie - Plaque-P11-Face-Assinie', 'Assinie', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['ATB-001', 'Attécoubé-Autoroute-du-Nord-sens-Yopougon-Adjamé-avant-l\'échangeur-de-la-casse-face-Yopougon', 'Attécoubé', 'Abidjan', 5.3634022, -4.0321994, 90000, '4x3m', 1],
    ['ATB-003A', 'Attécoubé-Autoroute-du-Nord-sens-Adjamé-Yopougon-après-la-station-Shell-face-Adjamé', 'Attécoubé', 'Abidjan', 5.3581927, -4.0518026, 90000, '4x3m', 1],
    ['ATB-003B', 'Attécoubé-Autoroute-du-Nord-sens-Yopougon-Adjamé-avant-la-station-Shell-face-Yopougon', 'Attécoubé', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['ATB-004A', 'Attécoubé-Carrefour-policier-sens-aller-Mossikro-face-Adjamé-forêt-du-BANCO', 'Attécoubé', 'Abidjan', 5.3587562, -4.0475372, 90000, '4x3m', 1],
    ['ATB-004B', 'Attécoubé-Carrefour-policier-sens-Retour-Mossikro-face-Sable', 'Attécoubé', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['ATBCAIS-01A', 'Attécoubé, Route attécoubé , sens Adjamé face ONUCI', 'Attécoubé', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['ATBCAIS-01B', 'Attécoubé, Route attécoubé, sens ONUCI face Adjamé', 'Attécoubé', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['AUT-001A', 'Autoroute du Nord  - Avant Péage - KM30, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-001B', 'Autoroute du Nord  - Péage - KM30, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-002A', 'Autoroute du Nord  - KM32, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-002B', 'Autoroute du Nord  - KM32, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-003A', 'Autoroute du Nord  - GESCO - KM8, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-003B', 'Autoroute du Nord  - GESCO - KM8, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-004A', 'Autoroute du Nord  - Pk22, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-004B', 'Autoroute du Nord  - PK22, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-005A', 'Autoroute du Nord  - Pk24, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-005B', 'Autoroute du Nord  - Pk24, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-006A', 'Autoroute du Nord  - LRA - PK26, face 1', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['AUT-006B', 'Autoroute du Nord  - LRA - PK26, face 2', 'Autoroute', 'Interieur', null, null, 80000, '2x3m', 1],
    ['BDK-001', 'Bondoukou - Collège Moderne bondoukou, route du Ghana', 'Bondoukou', 'Interieur', 8.0368, -2.79355, 80000, '4x3m', 1],
    ['BDK-002', 'Bondoukou - Entré-de-ville-200m-après-le-portique-de-moov', 'Bondoukou', 'Interieur', 7.653283, -2.8134, 80000, '4x3m', 1],
    ['BDK-003', 'Bondoukou - Sortie-de-ville-en-allant-a-Bouna', 'Bondoukou', 'Interieur', 8.047183, -2.802617, 80000, '4x3m', 1],
    ['BDKP-001', 'Bondoukou - Carrefour Boulangerie du Plateau', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-002', 'Bondoukou - En-face-du-conseil-Général', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-003', 'Bondoukou - Entrée-rond-poind-a-droite-Immeuble-OIPR', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-004', 'Bondoukou - Nouveau-Ministère-de-la-Santé', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-005', 'Bondoukou - Route-de-Bouna-à-côté-de-l\'hôtel-Hadress', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-006', 'Bondoukou - Route-du-Marché', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-007', 'Bondoukou - Station-total', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-008', 'Bondoukou - Venant-d\'Abidjan-carrefour-nouvelle-gare', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-009', 'Bondoukou - Carrefour Boulangerie du Plateau', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-010', 'Bondoukou - En-face-du-conseil-Général', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-011', 'Bondoukou - Entrée-rond-poind-a-droite-Immeuble-OIPR', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-012', 'Bondoukou - Nouveau-Ministère-de-la-Santé', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-013', 'Bondoukou - Route-de-Bouna-à-côté-de-l\'hôtel-Hadress', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-014', 'Bondoukou - Route-du-Marché', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-015', 'Bondoukou - Station-total', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BDKP-016', 'Bondoukou - Venant-d\'Abidjan-carrefour-nouvelle-gare', 'Bondoukou', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BING-001A', 'Bingerville Hôpital Adjamé Bingerville face Adjamé Bingerville', 'Bingerville', 'Abidjan', 5.3508929, -3.8744764, 130000, '4x3m', 1],
    ['BING-001B', 'Bingerville Hôpital Adjamé Bingerville face Bingerville', 'Bingerville', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['BING-002A', 'Bingerville route Mbatto Bouaké avant fin goudron Adjamé Bingerville', 'Bingerville', 'Abidjan', 5.3508792, -3.8710502, 130000, '4x3m', 1],
    ['BING-002B', 'Bingerville route Mbatto Bouaké avant fin goudron Adjamé Bingerville face Bing', 'Bingerville', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['BKE-001', 'Bouaké - Ahougnanssou chateau face Camp commando', 'Bouaké', 'Interieur', 7.68085, -2.057033, 80000, '4x3m', 1],
    ['BKE-002', 'Bouaké - Jacques Aka', 'Bouaké', 'Interieur', 7.684267, -5.025817, 80000, '4x3m', 1],
    ['BKE-003A', 'Bouaké - Ran Hôtel Face Orange', 'Bouaké', 'Interieur', 7.68405, -5.030485, 80000, '4x3m', 1],
    ['BKE-003B', 'Bouaké - Ran Hôtel face dos Orange', 'Bouaké', 'Interieur', null, null, 80000, '4x3m', 1],
    ['BKE-004', 'Bouaké - Entrée-Corridor-de-Bouaké-sens-centre-ville', 'Bouaké', 'Interieur', 7.649, -5.02755, 80000, '4x3m', 1],
    ['BKE-005', 'Bouaké - Ahougnassou-chateau-face-sitab', 'Bouaké', 'Interieur', 7.685767, -5.21865, 80000, '4x3m', 1],
    ['BKE-006', 'Bouaké - Carrefour-Kennedy', 'Bouaké', 'Interieur', 7.688783, -5.010317, 80000, '4x3m', 1],
    ['BKE-007', 'Bouaké - Carrefour-Victor-Hugo-Air-France', 'Bouaké', 'Interieur', 7.671033, -5.018317, 80000, '4x3m', 1],
    ['BKE-008', 'Bouaké - Entrée-Carrefour-Lac-Vert', 'Bouaké', 'Interieur', 7.651967, -5.027683, 80000, '4x3m', 1],
    ['BKE-009', 'Bouaké - EPP-Nimbo', 'Bouaké', 'Interieur', 7.675683, -5.015917, 80000, '4x3m', 1],
    ['BKE-010', 'Bouaké - ex-ONUCI face Corridor sud', 'Bouaké', 'Interieur', 7.6602, -5.061167, 80000, '4x3m', 1],
    ['BKE-012', 'Bouaké - Chu de Bouaké', 'Bouaké', 'Interieur', 7.700383, -5.0351, 80000, '4x3m', 1],
    ['BKE-013', 'Bouaké - Entrée d\'Ahougnassou face SITAB', 'Bouaké', 'Interieur', 7.685767, -5.21865, 80000, '4x3m', 1],
    ['BKE-014', 'Bouaké - face Stade', 'Bouaké', 'Interieur', 7.684623, -5.045064, 80000, '4x3m', 1],
    ['BKECAIS-01A', 'Bouaké - Entrée de Ville', 'Bouaké', 'Interieur', null, null, 800000, '10x5m', 1],
    ['BKECAIS-01B', 'Bouaké - Sortie de Ville', 'Bouaké', 'Interieur', null, null, 800000, '10x5m', 1],
    ['BKECAIS-02A', 'Bouaké - Entrée de Ville', 'Bouaké', 'Interieur', null, null, 800000, '10x5m', 1],
    ['BKECAIS-02B', 'Bouaké - Sortie de Ville', 'Bouaké', 'Interieur', null, null, 800000, '10x5m', 1],
    ['BKEP-001', 'Bouaké - Alignement Bouaké Gasoil près de Mosquée', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-002', 'Bouaké - Alignement du CHU', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-003', 'Bouaké - Ancien texaco venant d\'Abidjan', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-004', 'Bouaké - Au feu de Kennedy', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-005', 'Bouaké - Avant Sama Transport', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-006', 'Bouaké - Carrefour Abattoir', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-007', 'Bouaké - Carrefour Boulangerie du Plateau', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-008', 'Bouaké - Carrefour Clinique du centre Dares', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-009', 'Bouaké - Carrefour Collège Victor Hugo', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-010', 'Bouaké - Carrefour des Usines près du Stage', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-011', 'Bouaké - Carrefour face à l\'Orient', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-012', 'Bouaké - Carrefour Hôtel Anouanzé', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-013', 'Bouaké - Carrefour Oil Lybia kôkô', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-014', 'Bouaké - Clôture de la Cathédrale', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-015', 'Bouaké - CNPS Boukaé face Shell', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-016', 'Bouaké - Commerce carrefour restaurant Verdy', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-017', 'Bouaké - Commerce devant Savana', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-018', 'Bouaké - Devant CIDT', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-019', 'Bouaké - Devant Discthèque Caviar', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-020', 'Bouaké - Devant Pharmacie Fatma', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-021', 'Bouaké - Djammourou Station Total', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-022', 'Bouaké - Face Agance MTN', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-023', 'Bouaké - Face BICICI', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-024', 'Bouaké - Face Campus 2', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-025', 'Bouaké - Face Pharmacie Fatima', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-026', 'Bouaké - Face Sicogi Ahougnanssou', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-027', 'Bouaké - Face  SOCCOCE', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-028', 'Bouaké - Face UTB Grand Marché', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-029', 'Bouaké - Feu de Bon Prix', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-030', 'Bouaké - Feu de la Poste', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-031', 'Bouaké - Feu du Chateau Ahougnanssou', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-032', 'Bouaké - Feu du Commerce', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-033', 'Bouaké - Feu Pharmacie St Jean', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-034', 'Bouaké - Feu Sama Transport', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-035', 'Bouaké - Gare de M\'Bahiakro', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-036', 'Bouaké - Marché de gros Djammourou', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-037', 'Bouaké - Nouvelle Gare Cité près Sapeur Pompier', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-038', 'Bouaké - Pharmacie N\'Gatakro', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-039', 'Bouaké - Près de la SODECI Kôkô', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-040', 'Bouaké - Près de la station SAWMM Air France', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-041', 'Bouaké - Rue des Quicailleries', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-042', 'Bouaké - SAARI face CFAO Kôkô', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-043', 'Bouaké - SHELL Bois des têcks venant d\'Abidjan', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BKEP-044', 'Bouaké - Tremou Air ivoire', 'Bouaké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['BN-001A', 'Bonoua-Olgane-Face-Bonoua', 'Bonoua', 'Interieur', 5.275259, -3.573167, 80000, '4x3m', 1],
    ['BN-001B', 'Bonoua-Olgane-Face-Samo', 'Bonoua', 'Interieur', null, null, 80000, '4x3m', 1],
    ['CDY-004A', 'Cocody-Centre - Sens-Plateau-Cocody-Avant-le-pont-du-Lycée-Technique-face-Plateaux', 'Cocody', 'Abidjan', 5.3475197, -4.0208311, 130000, '4x3m', 1],
    ['CDY-004B', 'Cocody-Centre - Sens-Cocody-Plateau-avant-le-pont-du-Lycée-Technique-face-Cocody', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-006A', 'Bd-Rocade-après-passerelle-Washington-sens-CocodyAdjamé,-face-Plateau Moov', 'Cocody', 'Abidjan', 5.3416822, -4.0168802, 100000, '4x3m', 1],
    ['CDY-006B', 'Bd-Rocade-avant-passerelle-Washington-sens-AdjaméCocody,-face-Adjamé', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-007A', 'Bd-Rocade-après-passerelle-Washington-sens-CocodyAdjamé, au dessus,-face-Plateau', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-007B', 'Bd-Rocade-avant-passerelle-Washington-sens-AdjaméCocody, au dessus ; face-Adjamé', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-008A', 'Cocody-Centre - Sens-Cocody-Plateau-après-pont-Lycée-Technique-;-face-Cocody', 'Cocody', 'Abidjan', 5.3466715, -4.0112986, 130000, '4x3m', 1],
    ['CDY-008B', 'Cocody-Centre - Sens-Cocody-Plateau - après-pont-Lycée-Technique-;-face-Plateau', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-009A', 'Cocody-Centre - Terre-plein-sens-Sodemi-pont-Lycée-Technique-à-gauche-face-Cocody', 'Cocody', 'Abidjan', 5.3464765, -4.0060059, 100000, '4x3m', 1],
    ['CDY-009B', 'Cocody-Centre - Terre-plein-sens-Sodemi-pont-Lycée-Technique-à-gauche, face Pont Lycée Technique', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-010A', 'Cocody-Centre - Route-INADES-sens-cité-des-arts-face-Mermoz', 'Cocody', 'Abidjan', 5.3403693, -3.9988122, 130000, '4x3m', 1],
    ['CDY-010B', 'Cocody-Centre - Route-INADES-sens-Cité-des-arts--face-Cité-des-Arts-FACE 2', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-011A', 'Cocody-Centre - Vers-Pharmacie-Mimosa-sens-Carrefour-la-vie-Cocody-face-Carrefour-la-vie', 'Cocody', 'Abidjan', 5.3488469, -3.9996447, 100000, '4x3m', 1],
    ['CDY-011B', 'Cocody-Centre - Vers-Pharmacie-Mimosa-sens-Carrefour-la-vieCocody-face-Ecole-de-Gendarmerie', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-012A', 'Cocody-Centre - BD-Latrille-avant-RTI-mur-de-la-Sodemi-face-Carrefour Lavie', 'Cocody', 'Abidjan', 5.3460993, -4.0028298, 130000, '4x3m', 1],
    ['CDY-012B', 'Cocody-Centre - BD-Latrille-avant-RTI-mur-de-la-Sodemi-face-RTI', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-013', 'Cocody-Centre - Au-dessus-du-Pont-du-Lycée-Technique,-Sens-Clinique--Providence,-face-Lycée-Techniq-Moov', 'Cocody', 'Abidjan', 5.3480341, -4.0103326, 100000, '4x3m', 1],
    ['CDY-015A', 'Cocody-IIPlateaux - Rue-des-Jardins-face Eglise Ste Cécile', 'Cocody', 'Abidjan', 5.372444, -3.9904598, 130000, '4x3m', 1],
    ['CDY-015B', 'Cocody-IIPlateaux - Rue-des-Jardins-face-Paco', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-016A', 'Cocody-IIPlateaux - Hauteur-pont-St-Jacques-échangeur-2Plateaux-face-Adjamé', 'Cocody', 'Abidjan', 5.3542608, -4.0014582, 100000, '4x3m', 1],
    ['CDY-016B', 'Cocody-IIPlateaux - Hauteur-pont-St-Jacques-échangeur-2Plateaux-face-Riviera dos Moov', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-017A', 'Cocody-IIPlateaux - Hauteur-pont-St-Jacques-échangeur-2Plateaux-face-Adjamé', 'Cocody', 'Abidjan', 5.3541967, -4.0014612, 100000, '4x3m', 1],
    ['CDY-017B', 'Cocody-IIPlateaux - Hauteur-pont-St-Jacques-échangeur-2Plateaux-face-Riviera', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-020A', 'Cocody Bd.Latrille - Carrefour la vie sens Cocody- face  2 Plateaux', 'Cocody', 'Abidjan', 5.3497429, -4.0024243, 100000, '4x3m', 1],
    ['CDY-020B', 'Cocody Bd.Latrille - Carrefour-la-vie-sens-2-Plateaux-face-Cocody', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-021A', 'Cocody Bd.Latrille - Carrefour-BMW-sens-2Plateaux-face-Cocody', 'Cocody', 'Abidjan', 5.3576179, -4.0011689, 130000, '4x3m', 1],
    ['CDY-021B', 'Cocody Bd.Latrille - Carrefour-BMW-sens-Cocody-face-2-Plateaux-Microcred', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-022A', 'Cocody Bd.Latrille - Carrefour-Oil-Lybia-sens-2-Plateaux-Sococé-face-Cocody', 'Cocody', 'Abidjan', 5.3697675, -3.9980947, 130000, '4x3m', 1],
    ['CDY-022B', 'Cocody Bd.Latrille - Carrefour-Oil-Lybia-sens-2-Plateaux-Sococé-face-SOCOCCE', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-023A', 'Cocody Bd.Latrille - Côté-Interbat-sens-Angré-face-Sococé', 'Cocody', 'Abidjan', 5.3757786, -3.9983472, 130000, '4x3m', 1],
    ['CDY-023B', 'Cocody Bd.Latrille - Côté-Interbat-sens-Cocody-face-Angré', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-024A', 'Cocody Bd.Latrille - Avant-le-feu-de-Las-Palmas-sens-Angré-face-Cocody', 'Cocody', 'Abidjan', 5.3795145, -3.9953773, 130000, '4x3m', 1],
    ['CDY-024B', 'Cocody Bd.Latrille - Avant-le-feu-de-Las-Palmas-sens-Cocody-face-Angré', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-025A', 'Cocody Bd.Latrille - GMC pharmacie St Gabriel face Cocody', 'Cocody', 'Abidjan', 5.387037, -3.9927588, 130000, '4x3m', 1],
    ['CDY-025B', 'Cocody Bd.Latrille - GMC-pharmacie-st-Gabriel-face-Angré', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-026', 'Cocody Bd.Latrille - Temple-Mahikari,-sens-Angré,-face-Cocody', 'Cocody', 'Abidjan', 5.3905095, -3.992328, 130000, '4x3m', 1],
    ['CDY-027A', 'Cocody Bd.Latrille - Après carrefour ex-Ambassade de Chine sens Angré face Cocody', 'Cocody', 'Abidjan', 5.3928033, -3.9920138, 130000, '4x3m', 1],
    ['CDY-027B', 'Cocody Bd.Latrille - Après-carrefour-ex-Ambassade-de-Chine-sens-Cocody-face-Angré', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-028', 'Cocody Bd.Latrille - Avant carrefour les Oscars-face-Cocody', 'Cocody', 'Abidjan', 5.3958835, -3.9919085, 100000, '4x3m', 1],
    ['CDY-029A', 'Bd-Mitterand - Carrefour-Ste-famille,-sens-CAP-NORD-Riviera-3-face-Riviera-2', 'Cocody', 'Abidjan', null, -3.9671092, 130000, '4x3m', 1],
    ['CDY-029B', 'Bd-Mitterand - Carrefour-Ste-famille,-sens-CAP-NORD-Riviera-3-face- PLAMERAIE', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-030A', 'Bd-Mitterand - Route-Bingerville-sens-aller-à-droite-face-Palmeraie', 'Cocody', 'Abidjan', null, -3.9494116, 130000, '4x3m', 1],
    ['CDY-030B', 'Bd-Mitterand - Route-Bingerville-sens-retour-à-gauche-face nouveau camp', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-031A', 'Bd-Mitterand - Route-de-Bingerville-sens-aller-après-le-feu-de-la-Palmeraie-1P face Cocody', 'Cocody', 'Abidjan', 5.3619147, -3.9577248, 130000, '4x3m', 1],
    ['CDY-031B', 'Bd-Mitterand - Route-de-Bingerville-sens-retour-avant-le-feu-de-la-Palmeraie-1P face socoprix', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-032A', 'Bd-Mitterand - Route-de-Bingerville-sens-aller-genie-2000-après-le-feu-de-faya,-face faya', 'Cocody', 'Abidjan', 5.3711935, -3.9341284, 130000, '4x3m', 1],
    ['CDY-032B', 'Bd-Mitterand - Route-de-Bingerville-sens-retour-genie-2000-avant-le-feu-de-faya,-face-Bingerville', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-033A', 'Bd-Mitterand - Route-de-Bingerville-sens-retour-avant playce, face-Bingerville', 'Cocody', 'Abidjan', 5.3720421, -3.9311786, 130000, '4x3m', 1],
    ['CDY-033B', 'Bd-Mitterand - Route-de-Bingerville-sens-aller-playce,-face-Riviera', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-034A', 'Bd-Mitterrand-Route-de-Bingerville-sens-retour-après Playce,-face-Bingerville', 'Cocody', 'Abidjan', 5.3708687, -3.9353508, 130000, null, 1],
    ['CDY-034B', 'Bd-Mitterrand-Route-de-Bingerville-sens-aller-après Playce,-face-Riviera face Riviera', 'Cocody', 'Abidjan', null, null, 130000, null, 1],
    ['CDY-036', 'COCODY-RIVIERA-Route-petite-Mosquée-face-Ecole-de-Police', 'Cocody', 'Abidjan', 5.3478452, -3.9869029, 130000, null, 1],
    ['CDY-037A', 'Cocody-Anono Riviera-Golf-route-de-la-Chefferie-d\'Anono-face-Anono', 'Cocody', 'Abidjan', 5.3426426, -3.9744376, 130000, '4x3m', 1],
    ['CDY-037B', 'Cocody-Anono Riviera-Golf,-route-de-la-Chefferie-d\'Anono-face-Ambassade-ds-Etats-Unis', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-040A', 'Cocody-Riviera Route-M’Pouto-après-Hôtel-du-Golf-à-droite-avant-Sol-Béni', 'Cocody', 'Abidjan', 5.3315283, -3.961845, 130000, '4x3m', 1],
    ['CDY-040B', 'Cocody-Riviera Route-M’Pouto-après-Hôtel-du-Golf-à-gauche-avant-Sol-Béni', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-041A', 'Cocody-Riviera Sens-M\'Pouto-à-gauche-face- Mpouto', 'Cocody', 'Abidjan', 5.3368091, -3.9536599, 130000, '4x3m', 1],
    ['CDY-041B', 'Cocody-Riviera sens-M\'Pouto-à-droite-,-face-Lycée-Français', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-043A', 'Cocody-Corniche - Saint-Jean-Plateau-par-corniche-face-St Jean', 'Cocody', 'Abidjan', 5.3352698, -4.0069162, 130000, '4x3m', 1],
    ['CDY-043B', 'Cocody-Corniche - Saint-Jean-Plateau-par-corniche-face-Corniche', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-044A', 'Terre Plein-Cocody IIPlateaux-Niveau ex-Inst. Allaba sens Cocod face Adj 1e P', 'Cocody', 'Abidjan', 5.3548533, -4.0079005, 130000, '4x3m', 1],
    ['CDY-044B', 'Terre Plein-Cocody IIPlateaux-Niveau ex-Inst. Allaba sens Cocod face Riviera 1eP', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-045A', 'Terre-plein-Cocody-2Plateaux,-Niveau-ex-Institut-Allaba-sens-Cocody-;-face-Adjamé-2èP', 'Cocody', 'Abidjan', 5.3548135, -40075988.0, 100000, '4x3m', 1],
    ['CDY-045B', 'Terre-plein-Cocody-2Plateaux,-Niveau-ex-Institut-Allaba-sens Adjamé face Pont st jacques', 'Cocody', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['CDY-046', 'Cocody-Voie-express - IIPlateaux-Adjamé-sens-Plateau-avant-ex-Inst-Allaba-;-face-Pont St-Jacques', 'Cocody', 'Abidjan', 5.3542524, -4.0049354, 100000, '4x3m', 1],
    ['CDY-047A', 'Cocody-Voie-express - Cocody Riviéra sens Plateau face Riviera 1eP Face', 'Cocody', 'Abidjan', 5.3548783, -3.9861063, 130000, '4x3m', 1],
    ['CDY-047B', 'Cocody-Voie-express - Riviéra-2-sens-Riviera-face-Vallon-FACE 2', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDY-048A', 'Cocody-Voie-express - Cocody Riviera sens Plateau après pont de l\'école de police face Riviera', 'Cocody', 'Abidjan', 5.354913, -3.9922502, 130000, '4x3m', 1],
    ['CDY-048B', 'Cocody-Voie-express - Cocody Riviera sens Plateau après pont de l\'école de police face Adjamé', 'Cocody', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['CDYCAIS-1A', 'Cocody-Carrefour-la-vie-face-Sodefor', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYCAIS-1B', 'Cocody-Carrefour-la-vie-face--Adjamé', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYCAIS-2A', 'Cocody-Corniche-face-St-Jean', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYCAIS-2B', 'Cocody-Corniche- face PISAM', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYCAIS-3', 'Cocody-Bd-Latrille,-Clôture-ENA-face-école-la-Farandole', 'Cocody', 'Abidjan', null, null, 800000, '4x5m', 1],
    ['CDYCAIS-4A', 'Cocody Rond-point-Carrefour-Palmeraie,-face-Riviera-2', 'Cocody', 'Abidjan', null, null, 1000000, '11x5m', 1],
    ['CDYCAIS-4B', 'Cocody Rond-point Carrefour Palmeraie face Riviera 3', 'Cocody', 'Abidjan', null, null, 1000000, '11x5m', 1],
    ['CDYCAIS-5A', 'Cocody-II-Plateaux,-face-station-Oil-Lybia;-Face-II-Plateaux-Angré', 'Cocody', 'Abidjan', null, null, 950000, '9x6m', 1],
    ['CDYCAIS-5B', 'Cocody-II-Plateaux,-face-station-Oil-Lybia;-Face-Cocody', 'Cocody', 'Abidjan', null, null, 950000, '9x6m', 1],
    ['CDYCAIS-6A', 'Cocody Portique-Cocody-Sococé, face Station oil Lybia', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYCAIS-6B', 'Cocody Portique-Cocody-Sococé-face-Sococé', 'Cocody', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['CDYLUP-001', 'Cocody Lumipub Axe Hôtel Ivoire - St Jean  (10P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-002', 'Cocody Lumipub Axe St Jean - Maison du Parti (6P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-003', 'Cocody Lumipub Axe St Jean - Corniche (7P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-004', 'Cocody Lumipub Axe St Jean - Carrefour Lavie (14P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-005', 'Cocody Lumipub Axe RTI - Pont Lycée Technique (4P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-006', 'Cocody Lumipub Axe RTI - CHU (10P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-007', 'Cocody Lumipub Axe7', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYLUP-008', 'Cocody Lumipub Axe Mahicari - 22è Arrondissement Angré (9P x 3F)', 'Cocody', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['CDYPL-001A', 'Trivision sens Paul Ecole de police face Ecole de Police', 'Cocody', 'Abidjan', null, null, 100000, '3.67x2.65m', 3],
    ['CDYPL-001B', 'Trivision,-sens-Paul-Ecole-de-police_-face-Cash-Center', 'Cocody', 'Abidjan', null, null, 100000, '3.68x2.66m', 3],
    ['CDYPL-002A', 'Trivision-après-SOCOCE-Vallon,-face-Sainte-Cécile', 'Cocody', 'Abidjan', null, null, 100000, '3.69x2.67m', 3],
    ['CDYPL-002B', 'Trivision-après-SOCOCE-Vallon,-vue-de-profil,-face-Paul', 'Cocody', 'Abidjan', null, null, 100000, '3.7x2.68m', 3],
    ['CDYPL-003A', 'Trivision-au-carrefour-de-Sainte-Cécile,-face-carrefour-Duncan-F1', 'Cocody', 'Abidjan', null, null, 100000, '3.71x2.69m', 3],
    ['CDYPL-003B', 'Trivision-au-carrefour-de-Sainte-Cécile,-face-Paco-F2', 'Cocody', 'Abidjan', null, null, 100000, '3.72x2.7m', 3],
    ['CDYPL-004A', 'Trivision-à-côté-de-la-RTI,-face-Carrefour-la-vie-F1', 'Cocody', 'Abidjan', null, null, 100000, '3.73x2.71m', 3],
    ['CDYPL-004B', 'Trivision-à-côté-de-la-RTI,-face-Saint-Jean-F1', 'Cocody', 'Abidjan', null, null, 100000, '3.74x2.72m', 3],
    ['CDYPL-005A', 'Trivision-au-carrefour--de-Cap-Nord-face-Riviera-2-F1', 'Cocody', 'Abidjan', null, null, 100000, '3.75x2.73m', 3],
    ['CDYPL-005B', 'Trivision-au-carrefour--de-Cap-Nord-face-Riviera-3-F2', 'Cocody', 'Abidjan', null, null, 100000, '3.76x2.74m', 3],
    ['DIV-001', 'Entrée-Divo-face-mairie', 'Divo', 'Interieur', 5.835917, -5.366874, 80000, '4x3m', 1],
    ['DLA-001', 'Daloa - Route d\'issia', 'Daloa', 'Interieur', 6.867433, -6.444333, 80000, '4x3m', 1],
    ['DLA-002', 'Daloa - Rond point', 'Daloa', 'Interieur', 6.887983, -6.447983, 80000, '10x5m', 1],
    ['DLACAIS-01A', 'Daloa - Entrée de ville', 'Daloa', 'Interieur', null, null, 800000, '10x5m', 1],
    ['DLACAIS-01B', 'Daloa - Sortie de ville', 'Daloa', 'Interieur', null, null, 800000, '10x5m', 1],
    ['DLAL-01', 'Daloa - Après brone Kilométrique Orange Panneau à gauche', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-02', 'Daloa - Après le Feu du Cadre CAD', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-03', 'Daloa - Carrefour Collège Principal entrée du marché devant Orange Money', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-04', 'Daloa - Carrefour de la Poste Cloture de la Préfecture', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-05', 'Daloa - Carrefour de la Poste', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-06', 'Daloa - Carrefour Marché Après le Feu', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-07', 'Daloa - Carrefour Petro Ivoire Sortie par vavoua Côté Station au Feu', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-08', 'Daloa - Carrefour Pharmacie Aneaud', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-09', 'Daloa - Carrefour Pharmacie LOBIA', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-10', 'Daloa - Clôture du Minsitère du Plan et du développement', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-11', 'Daloa - Clôture Maison PDCI', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-12', 'Daloa - Devant Agence Orange 2', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-13', 'Daloa - Devant Agence Orange Principale', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-14', 'Daloa - Entrée de ville Après le feu Face Prinet-Ci', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-15', 'Daloa - Entrée Rond Point Centre Ville à Droite', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-16', 'Daloa - Feu après carrefour BCEAO', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-17', 'Daloa - Feu de la BCEAO Carrefour Stade Municipal', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-18', 'Daloa - Gendarmerie Nationel de Daloa', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['DLAL-19', 'Daloa - Rond point monument aux morts à Côté CIE', 'Daloa', 'Interieur', null, null, 80000, '2x3m', 2],
    ['GB-001A', 'Entrée-de-la-Ville-de-Bassam-à-droite-sens-Abidjan--Bassam-face-Abidjan', 'Bassam', 'Abidjan', 5.210747, -3.766859, 100000, '4x3m', 1],
    ['GB-001B', 'Entrée-de-la-Ville-de-Bassam-à-droite-sens-Abidjan-Bassam-face-BAssam', 'Bassam', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['GBCAIS-001A', 'Bassam Entrée de ville', 'Bassam', 'Interieur', null, null, 800000, '10x5m', 1],
    ['GBCAIS-001B', 'Bassam - Sortie de ville', 'Bassam', 'Interieur', null, null, 800000, '10x5m', 1],
    ['FEKL-01', 'Ferké - Carrefour Marché à côté de BNI Banque', 'Ferké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['FEKL-02', 'Ferké - Carrefour NSIA Banque', 'Ferké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['FEKL-03', 'Ferké - Intérieur du Marché', 'Ferké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['FEKL-04', 'Ferké - Route de Kong par Marché', 'Ferké', 'Interieur', null, null, 80000, '2x3m', 2],
    ['GNA-001', 'Gagnoa - Rue-princesse-garagnhio', 'Gagnoa', 'Interieur', 6.11705, -5.936833, 80000, '4x3m', 1],
    ['GNACAIS-01A', 'Gagnoa - Entrée de ville', 'Gagnoa', 'Interieur', null, null, 800000, '10x5m', 1],
    ['GNACAIS-01B', 'Gagnoa - Sortie de ville', 'Gagnoa', 'Interieur', null, null, 800000, '10x5m', 1],
    ['GNAL-01', 'Gagnoa - Carrefour Babre a côté de l\'Eglise catholique dans le terre plain avant panneau MTN', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-02', 'Gagnoa - Carrefour entrée Djoulabougou après petro ivoire  sodecie a gauche carrefour gesco', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-03', 'Gagnoa - Carrefour sous prefecture a côté monument avenue FHB', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-04', 'Gagnoa - Cloture Lycée Professionnel de Gagnoa (50 m après panneau MTN)', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-05', 'Gagnoa - Gagnoa Ancien Publimat de la Cathédrale', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-06', 'Gagnoa - Rond point cloture Hôpital', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-07', 'Gagnoa - Rond point Djoulabougou a côte de la boutique orange', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-08', 'Gagnoa - Garayo carrefour Total a côte du maquis orange(DEPLACE)', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['GNAL-09', 'Gagnoa - SODECI à gauche Soleil carrefour GESCO', 'Gagnoa', 'Interieur', null, null, 80000, '3x2m', 2],
    ['KHG-001', 'Korhogo - Entrée-de-ville-avant-le-corridor-face-Kanawolo', 'Korhogo', 'Interieur', 9.409417, -5.627417, 80000, '4x3m', 1],
    ['KHG-002', 'Korhogo - Rond-point-de-la-mairie-face-Mairie', 'Korhogo', 'Interieur', 9.45015, -5.631017, 80000, '4x3m', 1],
    ['KHG-003', 'Korhogo - Entrée Korhogo face Université Gon Coulibaly', 'Korhogo', 'Interieur', 9.426847, -5.627808, 80000, '4x3m', 1],
    ['KHG-004', 'Korhogo - Sortie Korhogo face Boundiali', 'Korhogo', 'Interieur', 9.426847, -5.627808, 80000, '4x3m', 1],
    ['KHGCAIS-01A', 'Korhogo - Entrée de ville', 'Korhogo', 'Interieur', null, null, 500000, '10x5m', 1],
    ['KHGCAIS-01B', 'Korhogo - Sortie de ville', 'Korhogo', 'Interieur', null, null, 500000, '10x5m', 1],
    ['KHGL-01', 'Korhogo - Avant Station Total Carrefour Biato à Droite', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-02', 'Korhogo - Carrefour Aeroport a Droite sens Aeroport à la Ville', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-03', 'Korhogo - Carrefour Biato Rond Point Sortie par Ferké', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-04', 'Korhogo - Carrefour Bois Sacré route de boundiali', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-05', 'Korhogo - Carrefour Chambre Regional d\'agriculture du Poro', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-06', 'Korhogo - Carrefour CNPS Après panneau MTN', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-07', 'Korhogo - Carrefour de la grande Mosquée Angle à Gauche', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-08', 'Korhogo - Carrefour Kassirime', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-09', 'Korhogo - Carrefour Pharmacie Korhogo sens retour a droite', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-10', 'Korhogo - Clôture URECI-CI', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-11', 'Korhogo - Corridor Boundiali sortie du rond', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-12', 'Korhogo - Devant Agence Orange', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-13', 'Korhogo - Feu Bois Sacré Derrière Panneau Stop', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-14', 'Korhogo - Gare UTNA', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-15', 'Korhogo - Jardin Université ou Face Université', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-16', 'Korhogo - Rond point Mairie', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-17', 'Korhogo - Rond Point Marie N°2', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-18', 'Korhogo - Rue Tolbert P1', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KHGL-19', 'Korhogo - Station Shell avant Gare UTNA', 'Korhogo', 'Interieur', null, null, 80000, '2x3m', 2],
    ['KSS-001', 'Koumassi - Bvd-du-07-décembre--face-grand-carrefour-Koumassi-Moov', 'Koumassi', 'Abidjan', 5.289568, -3.9639519, 100000, '4x3m', 1],
    ['KSS-002', 'Koumassi - Résidence-Pangolin-face-In\'Challah 1erP à gauche', 'Koumassi', 'Abidjan', 5.3004834, -3.9474576, 100000, '4x3m', 1],
    ['KSS-003', 'Koumassi - Résidence-Pangolin-face-In\'Challah 2èP à Droite', 'Koumassi', 'Abidjan', 5.3005495, -3.9474432, 100000, '4x3m', 1],
    ['KSSCAIS-01A', 'Koumassi Place Inchallah F2', 'Koumassi', 'Interieur', null, null, 800000, '10x5m', 1],
    ['KSSCAIS-01B', 'Entrée koun-fao', 'Koun Fao', 'Interieur', null, null, 500000, '10x5m', 1],
    ['MANCAIS-01A', 'Man - Entrée de ville', 'Man', 'Interieur', null, null, 500000, '10x5m', 1],
    ['MANCAIS-01B', 'Man - Sortie de ville', 'Man', 'Interieur', null, null, 500000, '10x5m', 1],
    ['MANP-01', 'Man - Carrefour Agence Orange après BCEAO', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-02', 'Man - Carrefour DOYAGOUINE route de Biankouma', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-03', 'Man - Carrefour la SARI face Station Total', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-04', 'Man - Face Collège Privé Wondoh Loniya', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-05', 'Man - Rond Point Préfecture de Man Cantre de Formation Pépinière', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-06', 'Man - Route Hôtel la Cascade face CHR', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-07', 'Man - Rue princesse voie principale en face Station Technique', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-08', 'Man - Terre plein carrefour Cascade', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-09', 'Man - Voie carrefour AMOI BAI face maquis Cobra', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MANP-10', 'Man - Voie principale après maquis Classe A', 'Man', 'Interieur', null, null, 80000, '2x3m', 2],
    ['MRY-001A', 'Marcory-Collège-Notre-Dame-face-Pergola-sens-Pergola', 'Marcory', 'Abidjan', 5.2725806, -3.9749821, 130000, '4x3m', 1],
    ['MRY-001B', 'Marcory-Collège-Notre-Dame-face-OSER-sens-Ancien-Koumassi', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['MRY-002', 'Marcory,-Rue-mercedes-face-bicici', 'Marcory', 'Abidjan', 5.2906527, -3.9823743, 100000, '4x3m', 1],
    ['MRY-003A', 'Marcory-Boulevard-de-Marseille-avant-l\'hôtel-Pergola-à-droite-Face Pergola', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['MRY-003B', 'Marcory-Boulevard-de-Marseille-avant-l\'hôtel-Pergola-Face Ancien Koumassi', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['MRY-004A', 'Marcory Bd de Marseille Près de CASH GIFI zone 4 face Drocolor', 'Marcory', 'Abidjan', 5.2859344, -3.986058, 130000, '4x3m', 1],
    ['MRY-004B', 'Marcory Bd de Marseille Près de CASH GIFI zone 4 face Pergola', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['ODNCAISS-01A', 'Odienné - Entrée de ville', 'Odienné', 'Interieur', null, null, 500000, '7x3m', 1],
    ['ODNCAISS-02B', 'Odienné - Entrée de ville', 'Odienné', 'Interieur', null, null, 500000, '7x3m', 1],
    ['ODNP-01', 'Odienné - Boulevard ADO route de Boundiali', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-02', 'Odienné - Carrefour Stade', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-03', 'Odienné - Carrefour CDCI', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-04', 'Odienné - Carrefour CHR', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-05', 'Odienné - Carrefour CIE', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-06', 'Odienné - Carrefour INODICE', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-07', 'Odienné - Route de l\'Aéroport', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['ODNP-08', 'Odienné - Terre plein du rond-point ADO', 'Odienné', 'Interieur', null, null, 80000, '2x3m', 2],
    ['PTB-001A', 'Port-Bouet-Entree-village-RASTA-face-SIR', 'Port-Bouët', 'Abidjan', 5.2573632, -3.9865962, 100000, '4x3m', 1],
    ['PTB-001B', 'Port-Bouet-Entree-village-RASTA-face-Abatoire', 'Port-Bouët', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['PTB-002A', 'Port-Bouet-Autoroute Bassam, sens-Adjouffou -Port Bouêt face Bassam', 'Port-Bouët', 'Abidjan', 5.2420082, -39126952.0, 100000, '4x3m', 1],
    ['PTB-002B', 'Port-Bouet-Autoroute Bassam, sens-Bassam face PORT-BOUET', 'Port-Bouët', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['PTB-003A', 'Port-Bouet-face-pharmacie-océan', 'Port-Bouët', 'Abidjan', 5.257037, -3.9616153, 100000, '4x3m', 1],
    ['PTB-003B', 'Port-Bouet-Pharmacie Océan face-Centre-Pilote', 'Port-Bouët', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['PTB-004', 'Port-Bouët - Autoroute Bassam sens retour avant carrefour Akawaba à droite face 43ème BIMA', 'Port-Bouët', 'Abidjan', 5.2598879, -3.9586448, 100000, '4x3m', 1],
    ['PTB-005', 'Pout-Bouët Autoroute Bassam, sens Adjouffou carrefour aéroport Djéda face Port-bouet', 'Port-Bouët', 'Abidjan', 5.2460527, -3.939578, 100000, '4x3m', 1],
    ['PTB-006', 'Port-Bouêt-Autoroute-de-Bassam avant-carrefour-Ananie-Station-Total-face-Gonzague', 'Port-Bouët', 'Abidjan', 5.236175, -38770560.0, 100000, '4x3m', 1],
    ['PTBCAIS-01A', 'Port-Bouet-Route-Aéroport,-sens-aéroport-face-Akwaba', 'Port-Bouët', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['PTBCAIS-01B', 'Port-Bouet-Route-Aéroport-sens-Akwaba-face-aéroport', 'Port-Bouët', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['PTBCAIS-02A', 'Port-Bouët Akwaba- face Abattoire', 'Port-Bouët', 'Abidjan', null, null, 600000, '6x3m', 1],
    ['PTBCAIS-02B', 'Port-Bouët Akwaba- face ancien Koumassi', 'Port-Bouët', 'Abidjan', null, null, 600000, '6x3m', 1],
    ['PLT-001', 'Plateau-avenue-Reboul-angle-GSPM-panneau-à-droite--face-Mairie-d\'Adjamé', 'Plateau', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['PLT-002A', 'Plateau Après-le-Garage-Présidentiel-au-niveau-de-ONUCI-sens-Attécoubé-ONUCI-face-Plateau', 'Plateau', 'Abidjan', 5.3371546, -4.0291597, 130000, '4x3m', 1],
    ['PLT-002B', 'Plateau Avant-le-Garage-Présidentiel-au-niveau-de-ONUCI-sens-Plateau-ONUCI-face-Attécoubé', 'Plateau', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['PLT-003', 'Plateau Indénié-sapeurs-pompiers-face-Ecole-Primaire-Amon-d\'Aby Moov', 'Plateau', 'Abidjan', 5.3407772, -4.0206092, 130000, '4x3m', 1],
    ['PLT-004A', 'Plateau Place-de-la-République-face-Ecobank', 'Plateau', 'Abidjan', 5.3169259, -4.0189278, 130000, '4x3m', 1],
    ['PLT-004B', 'Plateau Place de la République face Pont FHB', 'Plateau', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['PLTCAIS-01A', 'Plateau Chardy, face pont Corniche', 'Plateau', 'Abidjan', null, null, 950000, '6x9m', 1],
    ['PLTCAIS-01B', 'Plateau Chardy, face pont Degaulle', 'Plateau', 'Abidjan', null, null, 950000, '6x9m', 1],
    ['PLTP-01A', 'Plateau - Carrefour-Immeuble-le-général-face-siège-Moov', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-01B', 'Plateau - Carrefour-Immeuble-le-général-Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-02A', 'Plateau - RTI face Pigier', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-02B', 'Plateau - RTI face RTI', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-03A', 'Plateau - Siège-BRS-carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-03B', 'Plateau - Siège-BRS-face-District-d\'Abidjan', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-04A', 'Plateau - Clôture-RASCOM-Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-04B', 'Plateau - Clôture-RASCOM-face-IBIS', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-05A', 'Plateau - Collège-Notre-Dame', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-05B', 'Plateau - Collège-Notre-Dame-Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-06A', 'Plateau - Carrefour-LDF-et-Galérie-du-Parc,Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-06B', 'Plateau - Carrefour-LDF-et-Galérie-du-Parc,-face-LDF', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-07A', 'Plateau - Carrefour-Radio-Nostalgie,-Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-07B', 'Plateau - Carrefour-Radio-Nostalgie,-face-Caistab', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-08A', 'Plateau - Siège-BHCI,-Carte-Plateau', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-08B', 'Plateau - Siège-BHCI,-face-BCEAO', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-09A', 'Plateau - Stade FHB Encien  Combattant Face stade', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-09B', 'Plateau - Stade FHB Encien Combattant', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-10A', 'Plateau - Cité Administrative face gare sotra', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-10B', 'Plateau - Cité Administrative face BAD', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-11A', 'Plateau - Polyclinique du Plateau Face Mosquée', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['PLTP-11B', 'Plateau - Polyclinique du Plateau-FACE 2', 'Plateau', 'Abidjan', null, null, 70000, '1.72x1.21m', 1],
    ['SBR-001', 'Soubre,-Entrée de ville par Gagnoa face centre-ville', 'Soubré', 'Interieur', 5.794917, -6.595567, 70000, '4x3m', 1],
    ['SBRCAIS-01A', 'Soubré - Entrée de Ville', 'Soubré', 'Interieur', null, null, 500000, '10x5m', 1],
    ['SBRCAIS-01B', 'Soubré - Sortie de Ville', 'Soubré', 'Interieur', null, null, 500000, '10x5m', 1],
    ['SBRP-01', '6m² - Soubré Carrefour Brigade', 'Soubré', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SBRP-02', '6m² - Soubré Carrefour Doboi', 'Soubré', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SBRP-03', '6m² - Soubré Carrefour Mairie', 'Soubré', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SBRP-04', '6m² - Soubré Carrefour Maracana', 'Soubré', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SM-001A', 'Samo - Carrefour-Adiaké- Face Assinie', 'Samo', 'Interieur', 5.277305, -3.509458, 90000, '4x3m', 1],
    ['SM-001B', 'Samo - Carrefour-Adiaké-Face Abidjan', 'Samo', 'Interieur', null, null, 90000, '4x3m', 1],
    ['SM-002A', 'Samo - Route-Assinie-Face-Assinie', 'Samo', 'Interieur', 5.275101, -3.50748, 90000, '4x3m', 1],
    ['SM-002B', 'Samo - Route-Assinie-Face-Samo', 'Samo', 'Interieur', null, null, 90000, '4x3m', 1],
    ['SP-001', 'San-Pedro Triangle', 'San-Pédro', 'Interieur', 4.749267, -6.629483, 90000, '4x3m', 1],
    ['SP-002', 'San-Pedro - Sortie-de-ville-par-marché', 'San-Pédro', 'Interieur', 4.777333, -6.664083, 90000, '4x3m', 1],
    ['SP-003', 'San-pedro - Marché-de-poulet', 'San-Pédro', 'Interieur', 4.77455, -4.662667, 90000, '4x3m', 1],
    ['SP-004', 'San Pedro - Route de l\'aéroport', 'San-Pédro', 'Interieur', 4.75815, -6.64735, 90000, '4x3m', 1],
    ['SPCAIS-01A', 'San-Pédro - Entrée de Ville', 'San-Pédro', 'Interieur', null, null, 800000, '10x5m', 1],
    ['SPCAIS-02B', 'San-Pedro - Sortie de Ville', 'San-Pédro', 'Interieur', null, null, 800000, '10x5m', 1],
    ['SPP-01', 'San-Pedro - Carrefour CIPEXI Cité', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-02', 'San-Pedro - Carrefour Collège la Fayette, route GRAND BEREBI', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-03', 'San-Pedro - Carrefour NITORO', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-04', 'San-Pedro - Carrefour Pharmacie du Lac', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-05', 'San-Pedro - Carrefour TANRY sur le Boulevard', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-06', 'San-Pedro - Clôture du Graoupe Scolaire San Pédro 2 (Lac)', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-07', 'San-Pedro - Clôture Ministère des Sports et Loisirs', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-08', 'San-Pedro - Corridor face SACO', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-09', 'San-Pedro - Corridor IROKO rue des grumiers', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-10', 'San-Pedro - Corridor SIFCA', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-11', 'San-Pedro - Hôtem SOFIA', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-12', 'San-Pedro - Rond-Point de la Cité côté Mosquée', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-13', 'San-Pedro - Rond-Pont de la Cité Côté Hôtpital', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-14', 'San-Pedro - Route BEREBY marché poulet en face de la maternité HKB', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-15', 'San-Pedro - San-Pedro Corridor', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-16', 'San-Pedro - San-Pédro Lycée Professionel', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-17', 'San-Pedro - Sab-Pedro rond point de la plage', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-18', 'San-Pedro - Terre plein de la Mairie', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-19', 'San-Pedro - Terre rouge quartie Bardo', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-20', 'San-Pedro - Terre plein, rond point de la gare à l\'entrée de chaque carrefour P2', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-21', 'San-Pedro - Terre plein, rond point de la gare à l\'entrée de chaque carrefour P3 - Route d\'Abidjan', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['SPP-22', 'San-Pedro - Carrefour gendarmerie du Port', 'San-Pédro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['TAND-001', 'Entrée-Tanda', 'Tanda', 'Interieur', 7.798045, -3.16086, 70000, '4x3m', 1],
    ['TVIL-001', 'Treichville - Avenue-8-rue-38', 'Treichville', 'Abidjan', 5.3093666, -4.0059016, 100000, '4x3m', 1],
    ['TVIL-002A', 'Treichville - Contre-Allée-Treichville-CNPS-après-la-piscine-d\'Etat-;-sens-Plateau-Solibra,-face-Plateau', 'Treichville', 'Abidjan', 5.3078556, -3.9991928, 130000, '4x3m', 1],
    ['TVIL-002B', 'Treichville - contre-allée-Treichville-CNPS-àprès-la-piscine-d\'Etat-sens-Solibra-Plateau-face-Solibra', 'Treichville', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['TVIL-003', 'Treichville - Bd-de-Marseille-Palais-des-Sports--face-CHU-de-Treichville', 'Treichville', 'Abidjan', 5.2966679, -4.0062617, 100000, '4x3m', 1],
    ['TVIL-004A', 'Treichville - Rue-du-Canal,-collé-au-mur-de-CAVA-sens-Bd-de-Marseille-Solibra-face-Bd-de-Marseille', 'Treichville', 'Abidjan', 5.2971083, -3.9931048, 100000, '4x3m', 1],
    ['TVIL-004B', 'Treichville - Rue-du-Canal,-collé-au-mur-de-CAVA-sens-Bd-de-Marseille-Solibra-face-Solibra', 'Treichville', 'Abidjan', null, null, 100000, '4x3m', 1],
    ['TVILUP-001', 'Lumipub axe Biafra - Solibra  (10P x 3F)', 'Treichville', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['TVILUP-002', 'Lumipub axe solibra - Drocolor- (11P x 3F)', 'Treichville', 'Abidjan', null, null, 70000, '1.54x2.04m', 3],
    ['VGE-001A', 'Koumassi - Entrée-Station-Oil-Lybia-venant-d’Akwaba-face-Port-Bouët-sens-Koumassi', 'Koumassi', 'Abidjan', 5.2705667, -3.9626342, 100000, '4x3m', 1],
    ['VGE-001B', 'Koumassi - Entrée-Station-Olybia-venant-d’Akwaba-face-Koumassi', 'Koumassi', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['VGE-002A', 'Marcory - Azur-Info-face-Marcory-sens-Koumassi', 'Marcory', 'Abidjan', 5.2926454, -3.9755705, 130000, '4x3m', 1],
    ['VGE-002B', 'Marcory - Azur-Info-face-Koumassi-sens-Marcory', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['VGE-003A', 'Treichville - Avant arrêt Bus, sens Treichville Pont FHB, face Plateau', 'Treichville', 'Abidjan', 5.3027018, -4.0131258, 130000, '4x3m', 1],
    ['VGE-003B', 'Treichville - Avant-arrêt-Bus-sens-Pont-FHB-Treichville-face-Treichville', 'Treichville', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['VGE-004A', 'Marcory  - Avant-Decoluxe--à-droite-sens-Port-Bouët-face-grand-carrefour-de-Koumassi', 'Marcory', 'Abidjan', null, -3.9683349, 130000, '4x3m', 1],
    ['VGE-004B', 'Marcory - Avant-Decoluxe--à-gauche-face-ancien-Koumassi,-sens-Koumassi-carrefour-camp-commando', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['VGE-005A', 'Koumassi - Clôture-château-avant-carref.-Camp-Commando-face-ancien-Koumassi', 'Koumassi', 'Abidjan', 5.2838986, -3.9695386, 130000, '4x3m', 1],
    ['VGE-005B', 'Koumassi - Clôture-château-avant-carref.-Camp-Commando,-face-Grand-Carrefour-de-Koumassi', 'Koumassi', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['VGE-006A', 'Marcory - Cap-sud,-face-Solibra', 'Marcory', 'Abidjan', 5.2994702, -3.9877699, 130000, '4x3m', 1],
    ['VGE-006B', 'Marcory - Cap-sud,-face-Orca-Deco', 'Marcory', 'Abidjan', null, null, 130000, '4x3m', 1],
    ['YKR-001', 'Yakro - Lac centre-ville', 'Yamoussoukro', 'Interieur', 6.81655, -3.274367, 90000, '4x3m', 1],
    ['YKR-002', 'Yakro - Lac,-face-hôpital', 'Yamoussoukro', 'Interieur', 6.816433, -3.2748, 90000, '4x3m', 1],
    ['YKR-003', 'Yakro - Lac-face-centre-ville', 'Yamoussoukro', 'Interieur', null, null, 90000, '4x3m', 1],
    ['YKRCAIS-001A', 'Yakro - Entrée de Ville', 'Yamoussoukro', 'Interieur', null, null, 800000, '10x5m', 1],
    ['YKRCAIS-001B', 'Yakro - Sortie de Ville', 'Yamoussoukro', 'Interieur', null, null, 800000, '10x5m', 1],
    ['YKRP-01', 'Yakro - A gauche corridor sud venant d\'Abidjan', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-02', 'Yakro - Après Banque Atlantique', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-03', 'Yakro - Après feu BICICI UTB', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-04', 'Yakro - Arpès quincaillerie St Thomas au carrefour', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-05', 'Yakro - Au feu CNPS NSIA', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-06', 'Yakro - Au feu maquis le Jardin', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-07', 'Yakro - Carrefour Abidjan Bouaflé corridor nord', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-08', 'Yakro - Carrefour après gare d\' D\'ATTIEGBAKRKO', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-09', 'Yakro - Carrefour CNPS au mûr du Trésor', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-10', 'Yakro - Carrefour DJEDRY(Déplace en face)', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-11', 'Yakro - Carrefour Fondation SODECI BENET', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-12', 'Yakro - Carrefour Hôtel Président venant d\'Abidjan', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-13', 'Yakro - Carrefour la boutique du Carmel', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-14', 'Yakro - Carrefour Lacs', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-15', 'Yakro - Carrefour lavage le ROI', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-16', 'Yakro - Carrefour Quincaillerie St Thomas 220', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-17', 'Yakro - Centre de formation Professionnelle', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-18', 'Yakro - COOPEC de Yamoussoukro MAMIE ADJOUA', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-19', 'Yakro - Corridor venant d\'Abidjan à Droite', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-20', 'Yakro - Côte d\'Ivoire TELECOM', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-21', 'Yakro - Direction Départementale de la Santé', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-22', 'Yakro - Face Hôtel HOLYDAY', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-23', 'Yakro - Feu Maison BOIGNY carrefour partant à Bouaké', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-24', 'Yakro - Fondation aller à droite', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-25', 'Yakro - Hôtel Grand Centre', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-26', 'Yakro - Lycée Mamie Adjoua', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-27', 'Yakro - Publimat à croite corridor sud', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-28', 'Yakro - Publimat feu de BRIGESTONE', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-29', 'Yakro - Route Fondation MOFAITAY', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-30', 'Yakro - SICTA face Maison BOIGNY', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YKRP-31', 'Yakro - Station OLIBYA', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 2],
    ['YOP-001A', 'Yopougon - Autoroute du Nord sens Yopougon Adjamé après le pont piéton face Yopougon', 'Yopougon', 'Abidjan', 5.3596932, -4.0714267, 90000, '4x3m', 1],
    ['YOP-001B', 'Yopougon - Autoroute du Nord sens Adjamé Yopougon avants le pont piéton face Adjamé', 'Yopougon', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['YOP-002', 'Yopougon - Carrefour-de-la-pharmacie-Nankoko-face-marché-de-Kouté', 'Yopougon', 'Abidjan', 5.327961, -4.0623155, 90000, '4x3m', 1],
    ['YOP-003', 'Yopougon - Carrefour Niangon à droite sens Anananeraie face Pharmacie Port-Bouët 2', 'Yopougon', 'Abidjan', 5.347059, -4.0893106, 90000, '4x3m', 1],
    ['YOP-004A', 'Yopougon - Nouveau quartier face Mossikro', 'Yopougon', 'Abidjan', 5.3416852, -4.059297, 90000, '4x3m', 1],
    ['YOP-004B', 'Yopougon - Nouveau quartier face marché', 'Yopougon', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['YOP-005', 'Yopougon - Carrefour Lokoua', 'Yopougon', 'Abidjan', 5.3193439, -40997260.0, 90000, '4x3m', 1],
    ['YOP-006A', 'Yopougon - Du-carrefour-Complexe-à-la-Phcie-Bel-Air-face-carrefour-Phcie-Bel-Air', 'Yopougon', 'Abidjan', 5.3447911, -4.0632855, 90000, '4x3m', 1],
    ['YOP-006B', 'Yopougon - Du carrefour Complexe à la Phcie Bel Air, face Complexe', 'Yopougon', 'Abidjan', null, null, 90000, '4x3m', 1],
    ['YOPCAIS-01A', 'Yopougon-Place-Figayo,-Face-Adjamé', 'Yopougon', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['YOPCAIS-01B', 'Yopougon-Place-Figayo,-Face-Yopougon', 'Yopougon', 'Abidjan', null, null, 850000, '9x4m', 1],
    ['KSSCAIS-01A', 'Koumassi Inchallah face 1', 'Koumassi', 'Abidjan', null, null, 0, '9x4m', 1],
    ['KSSCAIS-01B', 'Koumassi Inchallah face 2', 'Koumassi', 'Abidjan', null, null, 0, '9x4m', 1],
    ['TVILCAIS-01A', 'Treichville, Descente du Pont FHB à gauche sens Treichville face Plateau', 'Treichville', 'Abidjan', null, null, 0, '9x4m', 1],
    ['TVILCAIS-01B', 'Treichville, Montée du Pont FHB à gauche sens Plateau face Treichville', 'Treichville', 'Abidjan', null, null, 0, '9x4m', 1],
    ['TVILCAIS-02A', 'Treichville, Portique Biafra sens Plateau face Treichville', 'Treichville', 'Abidjan', null, null, 0, '9x4m', 1],
    ['TVILCAIS-02B', 'Treichville, Portique Biafra sens Treichville face Plateau', 'Treichville', 'Abidjan', null, null, 0, '12x3m', 1],
    ['TVILCAIS-03B', 'Treichville, Biafra avant pont Degaulle à gauche sens Plateau face Biafra', 'Treichville', 'Abidjan', null, null, 0, '12x3m', 1],
    ['TVILCAIS-03B', 'Treichville, Biafra descente pont Degaulle à gauche sens Biafra face Plateau', 'Treichville', 'Abidjan', null, null, 0, '9x4m', 1],
    ['YOPCAIS-02A', 'Yopougon entree de ville- face autoroute du nord', 'Yopougon', 'Abidjan', null, null, 800000, '10x5m', 1],
    ['YOPCAIS-02B', 'Yopougon sortie de ville face Yopougon', 'Yopougon', 'Abidjan', null, null, 800000, '10x5m', 1],
    ['BK-01', 'Axe Anani - Bassam', 'Bassam', 'Abidjan', null, null, 80000, '2x3m', 18],
    ['BK-02', 'Axe Carrefour Adiaké - Assinie', 'Assinie', 'Abidjan', null, null, 80000, '2x3m', 24],
    ['BK-03', 'Axe Aboisso - Noé', 'Aboisso', 'Abidjan', null, null, 80000, '2x3m', 32],
    ['BK-04', 'Axe Abidjan - Yamoussoukro', 'Yamoussoukro', 'Interieur', null, null, 80000, '2x3m', 128],
];

foreach ($panneaux as [$ref, $name, $commune, $zone, $lat, $lng, $montant, $fmt, $faces]) {
    if (Panel::where('reference', $ref)->exists()) {
        echo "  SKIP: $ref (déjà existant)\n";
        $skipped++;
        continue;
    }

    $communeId = $communeMap[$commune] ?? null;
    $zoneId = $zoneMap[$zone] ?? null;
    $formatId = $fmt ? ($formatMap[$fmt] ?? null) : null;
    $lat = ($lat && $lat >= -90 && $lat <= 90) ? $lat : null;
    $lng = ($lng && $lng >= -180 && $lng <= 180) ? $lng : null;

    Panel::create([
        'reference' => $ref,
        'name' => $name,
        'commune_id' => $communeId,
        'zone_id' => $zoneId,
        'format_id' => $formatId ?? 1, // ← format par défaut si null
        'latitude' => $lat,
        'longitude' => $lng,
        'monthly_rate' => $montant,
        'nombre_faces' => $faces,
        'status' => PanelStatus::LIBRE,
        'is_lit' => false,
        'created_by' => 1,
    ]);
    echo "  ✓ $ref — $name\n";
    $created++;
}

echo "\n=== RÉSULTAT ===\n";
echo "✅ Créés   : $created\n";
echo "⏭  Ignorés : $skipped\n";
echo "Total      : " . ($created + $skipped) . "\n";

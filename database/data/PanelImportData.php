<?php

/**
 * Données d'import des panneaux CIBLE CI — extraites des fichiers
 * "LISTE DES PANNEAUX ABIDJAN" et "PARC PANO INTERIEUR".
 *
 * Format : ['reference', 'name', 'commune', 'surface_m2']
 *   - reference : code unique (ex. ADJ-002)
 *   - name      : libellé / désignation telle qu'inscrite sur le bordereau
 *   - commune   : nom commune (sera créée si manquante, casse insensible)
 *   - surface_m2: surface en m² — les dimensions w×h sont déduites côté seeder
 *
 * Source : PDF officiels — toute correction se fait ici puis on relance le seeder.
 */

return [

    // ════════════════════════ ABIDJAN ════════════════════════

    // ──── ADJAMÉ ────
    ['ADJ-002',    'Carrefour Roxy',                        'Adjamé', 12],
    ['ADJ-003',    'Liberté Avenue 13 - Vers Mosquée',      'Adjamé', 12],
    ['ADJ-004A',   'Carrefour CIE Adjamé - Face',           'Adjamé', 12],
    ['ADJ-004B',   'Carrefour CIE Adjamé - Dos',            'Adjamé', 12],
    ['ADJ-005A',   'Boulevard Nangui Abrogoua - Face',      'Adjamé', 12],
    ['ADJ-005B',   'Boulevard Nangui Abrogoua - Dos',       'Adjamé', 12],
    ['ADJC-03A',   'Caisson Adjamé Mairie - Face',          'Adjamé', 36],
    ['ADJC-03B',   'Caisson Adjamé Mairie - Dos',           'Adjamé', 36],
    ['ADJC-05A',   'Caisson Adjamé 220 Logements - Face',   'Adjamé', 36],
    ['ADJC-05B',   'Caisson Adjamé 220 Logements - Dos',    'Adjamé', 36],

    // ──── ASSINIE ────
    ['ASS-001A',   'Route Assinie - Face',                  'Assinie', 12],
    ['ASS-001B',   'Route Assinie - Dos',                   'Assinie', 12],

    // ──── ATTÉCOUBÉ ────
    ['ATB-002A',   'Carrefour Locodjro - Face',             'Attécoubé', 12],
    ['ATB-002B',   'Carrefour Locodjro - Dos',              'Attécoubé', 12],
    ['ATB-004A',   'Boulevard de la Paix - Face',           'Attécoubé', 12],
    ['ATB-004B',   'Boulevard de la Paix - Dos',            'Attécoubé', 12],
    ['ATBC-01A',   'Caisson Attécoubé - Face',              'Attécoubé', 36],
    ['ATBC-01B',   'Caisson Attécoubé - Dos',               'Attécoubé', 36],

    // ──── AUTOROUTE NORD ────
    ['AUTN-001',   'Autoroute du Nord PK 12',               'Abidjan', 12],
    ['AUTN-002',   'Autoroute du Nord PK 18',               'Abidjan', 12],

    // ──── GRAND-BASSAM ────
    ['BSM-001',    'Entrée Bassam - Face Abidjan',          'Grand-Bassam', 12],
    ['BSM-002',    'Centre Bassam',                         'Grand-Bassam', 12],
    ['BSM-003',    'Bassam Plage',                          'Grand-Bassam', 12],
    ['BSM-004',    'Bassam Quartier France',                'Grand-Bassam', 12],
    ['BSM-005',    'Sortie Bassam vers Aboisso',            'Grand-Bassam', 12],
    ['BSM-006',    'Carrefour Bassam',                      'Grand-Bassam', 12],

    // ──── BINGERVILLE ────
    ['BING-001A',  'Entrée Bingerville - Face',             'Bingerville', 12],
    ['BING-001B',  'Entrée Bingerville - Dos',              'Bingerville', 12],
    ['BING-004A',  'Carrefour Bingerville Centre - Face',   'Bingerville', 12],
    ['BING-004B',  'Carrefour Bingerville Centre - Dos',    'Bingerville', 12],
    ['BING-005A',  'Bingerville Mairie - Face',             'Bingerville', 12],
    ['BING-005B',  'Bingerville Mairie - Dos',              'Bingerville', 12],
    ['BING-006',   'Bingerville École',                     'Bingerville', 12],
    ['BING-007',   'Bingerville Hôpital',                   'Bingerville', 12],
    ['BING-008',   'Bingerville Marché',                    'Bingerville', 12],
    ['BING-009',   'Bingerville Lycée',                     'Bingerville', 12],
    ['BING-010',   'Bingerville Carrefour Akouédo',         'Bingerville', 12],

    // ──── COCODY ────
    ['CDY-013',    'Boulevard Latrille - Face Riviera',     'Cocody', 12],
    ['CDY-037B',   'Cocody II Plateaux - Dos',              'Cocody', 12],
    ['CDY-038',    'Cocody Angré 7e tranche',               'Cocody', 12],
    ['CDY-039',    'Cocody Angré 8e tranche',               'Cocody', 12],
    ['CDY-041A',   'Carrefour Riviera Palmeraie - Face',    'Cocody', 12],
    ['CDY-041B',   'Carrefour Riviera Palmeraie - Dos',     'Cocody', 12],
    ['CDY-046',    'Cocody Danga',                          'Cocody', 12],
    ['CDY-047A',   'Cocody 2 Plateaux Vallons - Face',      'Cocody', 12],
    ['CDY-047B',   'Cocody 2 Plateaux Vallons - Dos',       'Cocody', 12],
    ['CDYCAIS-01A','Caisson Cocody Cité Universitaire - Face','Cocody', 36],
    ['CDYCAIS-01B','Caisson Cocody Cité Universitaire - Dos', 'Cocody', 36],
    ['CDYCAIS-04', 'Caisson Cocody Saint Jean',             'Cocody', 20],
    ['CDYCAIS-07A','Caisson Cocody Riviera 3 - Face',       'Cocody', 54],
    ['CDYCAIS-07B','Caisson Cocody Riviera 3 - Dos',        'Cocody', 54],
    ['CDYCAIS-08A','Caisson Cocody Angré Château - Face',   'Cocody', 36],
    ['CDYCAIS-08B','Caisson Cocody Angré Château - Dos',    'Cocody', 36],
    ['CDYLUP-001', 'Lampadaire Cocody Boulevard Latrille 1', 'Cocody', 3],
    ['CDYLUP-002', 'Lampadaire Cocody Boulevard Latrille 2', 'Cocody', 3],
    ['CDYLUP-003', 'Lampadaire Cocody Boulevard Latrille 3', 'Cocody', 3],
    ['CDYLUP-004', 'Lampadaire Cocody Boulevard Latrille 4', 'Cocody', 3],
    ['CDYLUP-005', 'Lampadaire Cocody Boulevard Latrille 5', 'Cocody', 3],
    ['CDYLUP-006', 'Lampadaire Cocody Boulevard Latrille 6', 'Cocody', 3],
    ['CDYLUP-007', 'Lampadaire Cocody Boulevard Latrille 7', 'Cocody', 3],
    ['CDYLUP-008', 'Lampadaire Cocody Boulevard Latrille 8', 'Cocody', 3],
    ['CDYPUB-09',  'Publicité Cocody 09',                   'Cocody', 3],
    ['CDYT1-001A', 'Cocody T1 - Face',                      'Cocody', 10],
    ['CDYT1-001B', 'Cocody T1 - Dos',                       'Cocody', 10],
    ['CDYT2-001A', 'Cocody T2 - Face',                      'Cocody', 10],
    ['CDYT3-001A', 'Cocody T3 - Face',                      'Cocody', 10],
    ['CDYT3-001B', 'Cocody T3 - Dos',                       'Cocody', 10],

    // ──── KOUMASSI ────
    ['KSSCAIS-01A','Caisson Koumassi Boulevard Giscard - Face','Koumassi', 36],
    ['KSSCAIS-01B','Caisson Koumassi Boulevard Giscard - Dos', 'Koumassi', 36],

    // ──── MARCORY ────
    ['MRY-002',    'Marcory Zone 4',                        'Marcory', 12],
    ['MRYLUP-001', 'Lampadaire Marcory 001',                'Marcory', 3],
    ['VGE-004A',   'Vridi Cité - Face',                     'Port-Bouët', 12],
    ['VGE-004B',   'Vridi Cité - Dos',                      'Port-Bouët', 12],

    // ──── PLATEAU ────
    ['PLA-002A',   'Plateau Avenue Chardy - Face',          'Plateau', 12],
    ['PLA-002B',   'Plateau Avenue Chardy - Dos',           'Plateau', 12],
    ['PLA-004A',   'Plateau Boulevard de la République - Face','Plateau', 12],
    ['PLA-004B',   'Plateau Boulevard de la République - Dos', 'Plateau', 12],
    ['PLTCAIS-01', 'Caisson Plateau 01',                    'Plateau', 32],
    ['PLTCAIS-02', 'Caisson Plateau 02',                    'Plateau', 32],
    ['PLTP-01A',   'Plateau Pylône 01 - Face',              'Plateau', 2],
    ['PLTP-01B',   'Plateau Pylône 01 - Dos',               'Plateau', 2],
    ['PLTP-02A',   'Plateau Pylône 02 - Face',              'Plateau', 2],
    ['PLTP-02B',   'Plateau Pylône 02 - Dos',               'Plateau', 2],
    ['PLTP-03A',   'Plateau Pylône 03 - Face',              'Plateau', 2],
    ['PLTP-03B',   'Plateau Pylône 03 - Dos',               'Plateau', 2],
    ['PLTP-04A',   'Plateau Pylône 04 - Face',              'Plateau', 2],
    ['PLTP-04B',   'Plateau Pylône 04 - Dos',               'Plateau', 2],
    ['PLTP-05A',   'Plateau Pylône 05 - Face',              'Plateau', 2],
    ['PLTP-05B',   'Plateau Pylône 05 - Dos',               'Plateau', 2],
    ['PLTP-06A',   'Plateau Pylône 06 - Face',              'Plateau', 2],
    ['PLTP-06B',   'Plateau Pylône 06 - Dos',               'Plateau', 2],
    ['PLTP-07A',   'Plateau Pylône 07 - Face',              'Plateau', 2],
    ['PLTP-07B',   'Plateau Pylône 07 - Dos',               'Plateau', 2],
    ['PLTP-08A',   'Plateau Pylône 08 - Face',              'Plateau', 2],
    ['PLTP-08B',   'Plateau Pylône 08 - Dos',               'Plateau', 2],
    ['PLTP-09A',   'Plateau Pylône 09 - Face',              'Plateau', 2],
    ['PLTP-09B',   'Plateau Pylône 09 - Dos',               'Plateau', 2],
    ['PLTP-10A',   'Plateau Pylône 10 - Face',              'Plateau', 2],
    ['PLTP-10B',   'Plateau Pylône 10 - Dos',               'Plateau', 2],
    ['PLTP-11A',   'Plateau Pylône 11 - Face',              'Plateau', 2],
    ['PLTP-11B',   'Plateau Pylône 11 - Dos',               'Plateau', 2],

    // ──── PORT-BOUËT ────
    ['PBT-001A',   'Port-Bouët Aéroport - Face',            'Port-Bouët', 12],
    ['PBT-001B',   'Port-Bouët Aéroport - Dos',             'Port-Bouët', 12],
    ['PBT-002A',   'Port-Bouët Carrefour Phare - Face',     'Port-Bouët', 12],
    ['PBT-002B',   'Port-Bouët Carrefour Phare - Dos',      'Port-Bouët', 12],
    ['PBT-003A',   'Port-Bouët Vridi Canal - Face',         'Port-Bouët', 12],
    ['PBT-003B',   'Port-Bouët Vridi Canal - Dos',          'Port-Bouët', 12],
    ['PBT-004A',   'Port-Bouët Boulevard VGE - Face',       'Port-Bouët', 12],
    ['PBT-004B',   'Port-Bouët Boulevard VGE - Dos',        'Port-Bouët', 12],
    ['PBT-005A',   'Port-Bouët Gonzagueville - Face',       'Port-Bouët', 12],
    ['PBT-005B',   'Port-Bouët Gonzagueville - Dos',        'Port-Bouët', 12],
    ['PBT-006A',   'Port-Bouët Anani - Face',               'Port-Bouët', 12],
    ['PBT-006B',   'Port-Bouët Anani - Dos',                'Port-Bouët', 12],
    ['PBTCAIS-01', 'Caisson Port-Bouët 01',                 'Port-Bouët', 12],
    ['PBTCAIS-02', 'Caisson Port-Bouët 02',                 'Port-Bouët', 18],
    ['PBTCAIS-03', 'Caisson Port-Bouët 03',                 'Port-Bouët', 36],
    ['VGE-001A',   'VGE 001 - Face',                        'Port-Bouët', 12],
    ['VGE-001B',   'VGE 001 - Dos',                         'Port-Bouët', 12],

    // ──── SONGON ────
    ['SGN-001',    'Songon Kassemblé',                      'Songon', 12],
    ['SGN-002',    'Songon Agban',                          'Songon', 12],
    ['SGN-003',    'Songon Centre',                         'Songon', 12],
    ['SGN-004',    'Songon Dagbé',                          'Songon', 12],

    // ──── TREICHVILLE ────
    ['TVIL-001',   'Treichville Avenue 13',                 'Treichville', 12],
    ['TVIL-002',   'Treichville Boulevard de Marseille',    'Treichville', 12],
    ['TVIL-003A',  'Treichville Zone 3 - Face',             'Treichville', 12],
    ['TVIL-003B',  'Treichville Zone 3 - Dos',              'Treichville', 12],
    ['TVILCAIS-01A','Caisson Treichville 01 - Face',        'Treichville', 36],
    ['TVILCAIS-01B','Caisson Treichville 01 - Dos',         'Treichville', 36],
    ['TVILCAIS-02A','Caisson Treichville 02 - Face',        'Treichville', 36],
    ['TVILCAIS-02B','Caisson Treichville 02 - Dos',         'Treichville', 36],
    ['TVILCAIS-03A','Caisson Treichville 03 - Face',        'Treichville', 36],
    ['TVILCAIS-03B','Caisson Treichville 03 - Dos',         'Treichville', 36],
    ['TVILUP-001', 'Lampadaire Treichville 001',            'Treichville', 3],
    ['TVILUP-002', 'Lampadaire Treichville 002',            'Treichville', 3],

    // ──── YOPOUGON ────
    ['YOP-001A',   'Yopougon Siporex - Face',               'Yopougon', 12],
    ['YOP-001B',   'Yopougon Siporex - Dos',                'Yopougon', 12],
    ['YOP-002A',   'Yopougon Niangon - Face',               'Yopougon', 12],
    ['YOP-002B',   'Yopougon Niangon - Dos',                'Yopougon', 12],
    ['YOP-003A',   'Yopougon Selmer - Face',                'Yopougon', 12],
    ['YOP-003B',   'Yopougon Selmer - Dos',                 'Yopougon', 12],
    ['YOP-004A',   'Yopougon Andokoi - Face',               'Yopougon', 12],
    ['YOP-004B',   'Yopougon Andokoi - Dos',                'Yopougon', 12],
    ['YOP-005',    'Yopougon Toits Rouges',                 'Yopougon', 12],
    ['YOP-006',    'Yopougon Wassakara',                    'Yopougon', 12],
    ['YOP-007',    'Yopougon Sideci',                       'Yopougon', 12],
    ['YOP-008',    'Yopougon Kouté',                        'Yopougon', 12],
    ['YOP-009',    'Yopougon Maroc',                        'Yopougon', 12],
    ['YOP-010',    'Yopougon Académie',                     'Yopougon', 12],
    ['YOPCAIS-01A','Caisson Yopougon 01 - Face',            'Yopougon', 36],
    ['YOPCAIS-01B','Caisson Yopougon 01 - Dos',             'Yopougon', 36],
    ['YOPCAIS-02A','Caisson Yopougon 02 - Face',            'Yopougon', 54],
    ['YOPCAIS-02B','Caisson Yopougon 02 - Dos',             'Yopougon', 54],
    ['YOP-PAN-01', 'Yopougon Panneau Géant 01',             'Yopougon', 50],
    ['YOP-PAN-02', 'Yopougon Panneau Géant 02',             'Yopougon', 50],
    ['YOP-PM01',   'Yopougon Pont-Maquis 01',               'Yopougon', 24],
    ['YOP-PM02',   'Yopougon Pont-Maquis 02',               'Yopougon', 70],

    // ════════════════════════ INTÉRIEUR ════════════════════════

    // ──── ABENGOUROU ────
    ['ABG-001A',   'Abengourou Centre - Face',              'Abengourou', 12],
    ['ABG-001B',   'Abengourou Centre - Dos',               'Abengourou', 12],
    ['ABG-002',    'Abengourou Mairie',                     'Abengourou', 12],
    ['ABG-004',    'Abengourou Sortie Agnibilékrou',        'Abengourou', 12],
    ['ABG-PAN-01', 'Abengourou Panneau Géant 01',           'Abengourou', 50],
    ['ABG-PAN-02', 'Abengourou Panneau Géant 02',           'Abengourou', 50],

    // ──── ADIAKÉ-ASSINIE ────
    ['BK-002',     'Adiaké-Assinie Bourkina',               'Adiaké', 6],

    // ──── ASSINIE (Intérieur) ────
    ['ASS-002A',   'Assinie Hôtel - Face',                  'Assinie', 12],
    ['ASS-002B',   'Assinie Hôtel - Dos',                   'Assinie', 12],

    // ──── BONDOUKOU ────
    ['BDK-001',    'Bondoukou Centre',                      'Bondoukou', 12],
    ['BDK-003',    'Bondoukou Sortie Bouna',                'Bondoukou', 12],
    ['BDKP-001',   'Bondoukou Pylône 001',                  'Bondoukou', 6],
    ['BDKP-002',   'Bondoukou Pylône 002',                  'Bondoukou', 6],
    ['BDKP-003',   'Bondoukou Pylône 003',                  'Bondoukou', 6],
    ['BDKP-004',   'Bondoukou Pylône 004',                  'Bondoukou', 6],
    ['BDKP-005',   'Bondoukou Pylône 005',                  'Bondoukou', 6],
    ['BDKP-006',   'Bondoukou Pylône 006',                  'Bondoukou', 6],

    // ──── BONOUA ────
    ['BNA-001A',   'Bonoua Centre - Face',                  'Bonoua', 12],
    ['BNA-001B',   'Bonoua Centre - Dos',                   'Bonoua', 12],

    // ──── BOUAFLÉ ────
    ['BFL-CHE-001','Bouaflé Carrefour Chef de l\'État',     'Bouaflé', 20],

    // ──── BOUAKÉ ────
    ['BKE-004',    'Bouaké Air France',                     'Bouaké', 12],
    ['BKE-005',    'Bouaké Commerce',                       'Bouaké', 12],
    ['BKE-007',    'Bouaké Belleville',                     'Bouaké', 12],
    ['BKE-009',    'Bouaké Koko',                           'Bouaké', 12],
    ['BKE-012A',   'Bouaké Liberté - Face',                 'Bouaké', 12],
    ['BKE-012B',   'Bouaké Liberté - Dos',                  'Bouaké', 12],
    ['BKE-013',    'Bouaké Air France 2',                   'Bouaké', 12],
    ['BKE-014',    'Bouaké Sortie Yamoussoukro',            'Bouaké', 12],
    ['BKE-PAN-01A','Bouaké Panneau Géant - Face',           'Bouaké', 50],
    ['BKE-PAN-01B','Bouaké Panneau Géant - Dos',            'Bouaké', 50],

    // BKEP-001 à BKEP-044 (44 pylônes)
    ['BKEP-001','Bouaké Pylône 001','Bouaké',6],['BKEP-002','Bouaké Pylône 002','Bouaké',6],
    ['BKEP-003','Bouaké Pylône 003','Bouaké',6],['BKEP-004','Bouaké Pylône 004','Bouaké',6],
    ['BKEP-005','Bouaké Pylône 005','Bouaké',6],['BKEP-006','Bouaké Pylône 006','Bouaké',6],
    ['BKEP-007','Bouaké Pylône 007','Bouaké',6],['BKEP-008','Bouaké Pylône 008','Bouaké',6],
    ['BKEP-009','Bouaké Pylône 009','Bouaké',6],['BKEP-010','Bouaké Pylône 010','Bouaké',6],
    ['BKEP-011','Bouaké Pylône 011','Bouaké',6],['BKEP-012','Bouaké Pylône 012','Bouaké',6],
    ['BKEP-013','Bouaké Pylône 013','Bouaké',6],['BKEP-014','Bouaké Pylône 014','Bouaké',6],
    ['BKEP-015','Bouaké Pylône 015','Bouaké',6],['BKEP-016','Bouaké Pylône 016','Bouaké',6],
    ['BKEP-017','Bouaké Pylône 017','Bouaké',6],['BKEP-018','Bouaké Pylône 018','Bouaké',6],
    ['BKEP-019','Bouaké Pylône 019','Bouaké',6],['BKEP-020','Bouaké Pylône 020','Bouaké',6],
    ['BKEP-021','Bouaké Pylône 021','Bouaké',6],['BKEP-022','Bouaké Pylône 022','Bouaké',6],
    ['BKEP-023','Bouaké Pylône 023','Bouaké',6],['BKEP-024','Bouaké Pylône 024','Bouaké',6],
    ['BKEP-025','Bouaké Pylône 025','Bouaké',6],['BKEP-026','Bouaké Pylône 026','Bouaké',6],
    ['BKEP-027','Bouaké Pylône 027','Bouaké',6],['BKEP-028','Bouaké Pylône 028','Bouaké',6],
    ['BKEP-029','Bouaké Pylône 029','Bouaké',6],['BKEP-030','Bouaké Pylône 030','Bouaké',6],
    ['BKEP-031','Bouaké Pylône 031','Bouaké',6],['BKEP-032','Bouaké Pylône 032','Bouaké',6],
    ['BKEP-033','Bouaké Pylône 033','Bouaké',6],['BKEP-034','Bouaké Pylône 034','Bouaké',6],
    ['BKEP-035','Bouaké Pylône 035','Bouaké',6],['BKEP-036','Bouaké Pylône 036','Bouaké',6],
    ['BKEP-037','Bouaké Pylône 037','Bouaké',6],['BKEP-038','Bouaké Pylône 038','Bouaké',6],
    ['BKEP-039','Bouaké Pylône 039','Bouaké',6],['BKEP-040','Bouaké Pylône 040','Bouaké',6],
    ['BKEP-041','Bouaké Pylône 041','Bouaké',6],['BKEP-042','Bouaké Pylône 042','Bouaké',6],
    ['BKEP-043','Bouaké Pylône 043','Bouaké',6],['BKEP-044','Bouaké Pylône 044','Bouaké',6],

    // ──── DALOA ────
    ['DLA-001A',   'Daloa Centre - Face',                   'Daloa', 12],
    ['DLA-001B',   'Daloa Centre - Dos',                    'Daloa', 12],
    ['DLAP-001','Daloa Pylône 001','Daloa',6],['DLAP-002','Daloa Pylône 002','Daloa',6],
    ['DLAP-003','Daloa Pylône 003','Daloa',6],['DLAP-004','Daloa Pylône 004','Daloa',6],
    ['DLAP-005','Daloa Pylône 005','Daloa',6],['DLAP-006','Daloa Pylône 006','Daloa',6],
    ['DLAP-007','Daloa Pylône 007','Daloa',6],['DLAP-008','Daloa Pylône 008','Daloa',6],
    ['DLAP-009','Daloa Pylône 009','Daloa',6],['DLAP-010','Daloa Pylône 010','Daloa',6],
    ['DLAP-011','Daloa Pylône 011','Daloa',6],['DLAP-012','Daloa Pylône 012','Daloa',6],
    ['DLAP-013','Daloa Pylône 013','Daloa',6],

    // ──── FERKESSÉDOUGOU ────
    ['FERK-01',    'Ferkessédougou 01',                     'Ferkessédougou', 6],
    ['FERK-02',    'Ferkessédougou 02',                     'Ferkessédougou', 6],
    ['FERK-03',    'Ferkessédougou 03',                     'Ferkessédougou', 6],
    ['FERK-04',    'Ferkessédougou 04',                     'Ferkessédougou', 6],

    // ──── GAGNOA ────
    ['GAG-001','Gagnoa Pylône 001','Gagnoa',6],['GAG-002','Gagnoa Pylône 002','Gagnoa',6],
    ['GAG-003','Gagnoa Pylône 003','Gagnoa',6],['GAG-004','Gagnoa Pylône 004','Gagnoa',6],
    ['GAG-005','Gagnoa Pylône 005','Gagnoa',6],['GAG-006','Gagnoa Pylône 006','Gagnoa',6],
    ['GAG-007','Gagnoa Pylône 007','Gagnoa',6],['GAG-008','Gagnoa Pylône 008','Gagnoa',6],
    ['GNA-001',    'Gagnoa Centre',                         'Gagnoa', 12],
    ['GNA-PAN-01', 'Gagnoa Panneau Géant 01',               'Gagnoa', 50],
    ['GNA-PAN-02', 'Gagnoa Panneau Géant 02',               'Gagnoa', 50],

    // ──── KORHOGO ────
    ['KHG-001',    'Korhogo Centre',                        'Korhogo', 12],
    ['KHG-002',    'Korhogo Sortie Boundiali',              'Korhogo', 12],
    ['KHGP-001','Korhogo Pylône 001','Korhogo',6],['KHGP-002','Korhogo Pylône 002','Korhogo',6],
    ['KHGP-003','Korhogo Pylône 003','Korhogo',6],['KHGP-004','Korhogo Pylône 004','Korhogo',6],
    ['KHGP-005','Korhogo Pylône 005','Korhogo',6],['KHGP-006','Korhogo Pylône 006','Korhogo',6],
    ['KHGP-007','Korhogo Pylône 007','Korhogo',6],['KHGP-008','Korhogo Pylône 008','Korhogo',6],
    ['KHGP-009','Korhogo Pylône 009','Korhogo',6],['KHGP-010','Korhogo Pylône 010','Korhogo',6],
    ['KHGP-011','Korhogo Pylône 011','Korhogo',6],['KHGP-012','Korhogo Pylône 012','Korhogo',6],
    ['KHG-PAN-01', 'Korhogo Panneau Géant 01',              'Korhogo', 50],
    ['KHG-PAN-02', 'Korhogo Panneau Géant 02',              'Korhogo', 50],

    // ──── MAN ────
    ['MANP-001',   'Man Pylône 001',                        'Man', 6],
    ['MANP-004',   'Man Pylône 004',                        'Man', 6],
    ['MANP-005',   'Man Pylône 005',                        'Man', 6],
    ['MAN-PAN-01', 'Man Panneau Géant 01',                  'Man', 50],
    ['MAN-PAN-02', 'Man Panneau Géant 02',                  'Man', 50],

    // ──── ODIENNÉ ────
    ['ODNP-001','Odienné Pylône 001','Odienné',6],['ODNP-002','Odienné Pylône 002','Odienné',6],
    ['ODNP-003','Odienné Pylône 003','Odienné',6],['ODNP-004','Odienné Pylône 004','Odienné',6],
    ['ODNP-005','Odienné Pylône 005','Odienné',6],['ODNP-006','Odienné Pylône 006','Odienné',6],
    ['ODNP-007','Odienné Pylône 007','Odienné',6],['ODNP-008','Odienné Pylône 008','Odienné',6],

    // ──── SAMO ────
    ['SM-001A',    'Samo 001 - Face',                       'Samo', 12],
    ['SM-001B',    'Samo 001 - Dos',                        'Samo', 12],
    ['SM-002A',    'Samo 002 - Face',                       'Samo', 12],
    ['SM-002B',    'Samo 002 - Dos',                        'Samo', 12],

    // ──── SAN PEDRO ────
    ['SP-001',     'San Pedro Port',                        'San Pedro', 12],
    ['SP-002',     'San Pedro Centre',                      'San Pedro', 12],
    ['SP-003A',    'San Pedro Bardot - Face',               'San Pedro', 12],
    ['SP-003B',    'San Pedro Bardot - Dos',                'San Pedro', 12],
    ['SP-004',     'San Pedro Cité',                        'San Pedro', 12],
    ['SPBS-01',    'San Pedro Boulevard Houphouët 01',      'San Pedro', 50],
    ['SPBS-02',    'San Pedro Boulevard Houphouët 02',      'San Pedro', 50],
    ['SPP-001','San Pedro Pylône 001','San Pedro',6],['SPP-002','San Pedro Pylône 002','San Pedro',6],
    ['SPP-003','San Pedro Pylône 003','San Pedro',6],['SPP-004','San Pedro Pylône 004','San Pedro',6],
    ['SPP-005','San Pedro Pylône 005','San Pedro',6],['SPP-006','San Pedro Pylône 006','San Pedro',6],
    ['SPP-007','San Pedro Pylône 007','San Pedro',6],

    // ──── SOUBRÉ ────
    ['SBR-001',    'Soubré Centre',                         'Soubré', 12],
    ['SOUBP-001',  'Soubré Pylône 001',                     'Soubré', 6],
    ['SOUBP-002',  'Soubré Pylône 002',                     'Soubré', 6],
    ['SOUBP-003',  'Soubré Pylône 003',                     'Soubré', 6],
    ['SOUBP-004',  'Soubré Pylône 004',                     'Soubré', 6],

    // ──── YAMOUSSOUKRO ────
    ['YKR-001',    'Yamoussoukro Basilique',                'Yamoussoukro', 12],
    ['YKR-002A',   'Yamoussoukro Carrefour Préfecture - Face','Yamoussoukro', 12],
    ['YKR-002B',   'Yamoussoukro Carrefour Préfecture - Dos', 'Yamoussoukro', 12],
    ['YKR-PAN-01', 'Yamoussoukro Panneau Géant 01',         'Yamoussoukro', 50],
    ['YKR-PAN-02', 'Yamoussoukro Panneau Géant 02',         'Yamoussoukro', 50],
    ['YAKRP-001','Yamoussoukro Pylône 001','Yamoussoukro',6],['YAKRP-002','Yamoussoukro Pylône 002','Yamoussoukro',6],
    ['YAKRP-003','Yamoussoukro Pylône 003','Yamoussoukro',6],['YAKRP-004','Yamoussoukro Pylône 004','Yamoussoukro',6],
    ['YAKRP-005','Yamoussoukro Pylône 005','Yamoussoukro',6],['YAKRP-006','Yamoussoukro Pylône 006','Yamoussoukro',6],
    ['YAKRP-007','Yamoussoukro Pylône 007','Yamoussoukro',6],['YAKRP-008','Yamoussoukro Pylône 008','Yamoussoukro',6],
    ['YAKRP-009','Yamoussoukro Pylône 009','Yamoussoukro',6],['YAKRP-010','Yamoussoukro Pylône 010','Yamoussoukro',6],
    ['YAKRP-011','Yamoussoukro Pylône 011','Yamoussoukro',6],['YAKRP-012','Yamoussoukro Pylône 012','Yamoussoukro',6],
    ['YAKRP-013','Yamoussoukro Pylône 013','Yamoussoukro',6],['YAKRP-014','Yamoussoukro Pylône 014','Yamoussoukro',6],
    ['YAKRP-015','Yamoussoukro Pylône 015','Yamoussoukro',6],['YAKRP-016','Yamoussoukro Pylône 016','Yamoussoukro',6],
    ['YAKRP-017','Yamoussoukro Pylône 017','Yamoussoukro',6],['YAKRP-018','Yamoussoukro Pylône 018','Yamoussoukro',6],
    ['YAKRP-019','Yamoussoukro Pylône 019','Yamoussoukro',6],['YAKRP-020','Yamoussoukro Pylône 020','Yamoussoukro',6],
    ['YAKRP-021','Yamoussoukro Pylône 021','Yamoussoukro',6],['YAKRP-022','Yamoussoukro Pylône 022','Yamoussoukro',6],
    ['YAKRP-023','Yamoussoukro Pylône 023','Yamoussoukro',6],['YAKRP-024','Yamoussoukro Pylône 024','Yamoussoukro',6],
    ['YAKRP-025','Yamoussoukro Pylône 025','Yamoussoukro',6],['YAKRP-026','Yamoussoukro Pylône 026','Yamoussoukro',6],
    ['YAKRP-027','Yamoussoukro Pylône 027','Yamoussoukro',6],

];

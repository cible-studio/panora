<?php

return [
    // ⚠ Clé historique NON lue par barryvdh/laravel-dompdf (le package lit
    // `dompdf.options`). Conservée telle quelle : l'activer changerait le
    // comportement de tous les PDF (notamment enable_php).
    'default' => [
        // Optimisations
        'enable_remote' => false,
        'chroot' => realpath(base_path()),
        'log_output_file' => null,
        'enable_html5_parser' => true,
        'font_cache' => storage_path('fonts/'),
        // Autorise <script type="text/php"> dans les templates — utilisé
        // par le PDF Devis pour la pagination "Page X sur Y" via page_text().
        // Sûr ici car aucun HTML utilisateur n'est rendu par DomPDF —
        // uniquement nos Blade contrôlés en dur.
        'enable_php' => true,
    ],

    // Options réellement appliquées : celles par défaut du package, avec
    // une seule différence — le sous-ensemble de polices (charte graphique,
    // 2026-10-01). Les PDF embarquent Poppins / Nunito (resources/fonts) :
    // sans sous-ensemble, chaque PDF transporterait les fichiers complets
    // (~1 Mo de plus) ; avec, seuls les glyphes utilisés sont intégrés.
    // Le cache des polices reste storage/fonts (créé si absent par
    // App\Support\PdfCharte).
    'options' => array_merge(
        (require base_path('vendor/barryvdh/laravel-dompdf/config/dompdf.php'))['options'],
        [
            'enable_font_subsetting' => true,
        ]
    ),
];

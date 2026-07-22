<?php

return [
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
];

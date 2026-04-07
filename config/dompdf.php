<?php

return [
    'default' => [
        // Optimisations
        'enable_remote' => false,
        'chroot' => realpath(base_path()),
        'log_output_file' => null,
        'enable_html5_parser' => true,
        'font_cache' => storage_path('fonts/'),
    ],
];

<?php
namespace App\Support;

/**
 * Helpers pour les assets embarqués dans les PDF (DomPDF n'accède pas aux URLs).
 * Toutes les images doivent être passées en data-URI base64.
 */
trait PdfAssets
{
    /**
     * Retourne le logo CIBLE CI en data-URI base64, prêt à être utilisé
     * dans un <img src="..."> de template PDF.
     * Fallback : SVG inline avec le texte "CIBLE CI".
     */
    protected function getLogoPdf(): string
    {
        $candidates = [
            public_path('images/logo-cible.png'),
            public_path('images/logo.png'),
            public_path('images/logon.png'),
            public_path('images/logob.png'),
            public_path('images/logol.png'),
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        // Fallback SVG inline (toujours embarquable)
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="50" viewBox="0 0 180 50">'
             . '<rect width="180" height="50" rx="6" fill="#0d1117"/>'
             . '<text x="90" y="34" font-family="Arial,sans-serif" font-weight="900" '
             . 'font-size="20" fill="#e8a020" text-anchor="middle">CIBLE CI</text>'
             . '</svg>';

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Convertit un chemin photo (storage public) en data-URI base64 pour PDF.
     * Retourne null si le fichier n'existe pas ou n'est pas lisible.
     */
    protected function photoToDataUri(?string $relativePath): ?string
    {
        if (!$relativePath) return null;

        // Chemin physique sur le disque public
        $path = storage_path('app/public/' . ltrim($relativePath, '/'));

        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';
        // Sécurité : refuser les fichiers > 4 Mo (DomPDF crash sinon)
        if (filesize($path) > 4 * 1024 * 1024) return null;

        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
}

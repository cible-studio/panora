<?php
namespace App\Support;

/**
 * Helpers pour les assets embarqués dans les PDF (DomPDF n'accède pas aux URLs).
 * Toutes les images doivent être passées en data-URI base64.
 */
trait PdfAssets
{
    /**
     * Logo Panora en data-URI — version foncée (panora.png).
     * À utiliser sur fond CLAIR (header blanc, footer beige…).
     */
    protected function getPanoraLogoDark(): string
    {
        return $this->logoDataUri([
            public_path('images/panora.png'),
        ], '#0f172a');
    }

    /**
     * Logo Panora en data-URI — version claire (panora-blanc.png).
     * À utiliser sur fond FONCÉ (bandeau noir, header sombre…).
     */
    protected function getPanoraLogoLight(): string
    {
        return $this->logoDataUri([
            public_path('images/panora-blanc.png'),
        ], '#ffffff');
    }

    /**
     * Helper interne : tente chaque candidat dans l'ordre, retourne le
     * premier trouvé en base64 ou un SVG fallback "Panora" coloré.
     */
    private function logoDataUri(array $candidates, string $fallbackColor): string
    {
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $mime = mime_content_type($path) ?: 'image/png';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="140" height="40" viewBox="0 0 140 40">'
             . '<text x="70" y="28" font-family="Arial,sans-serif" font-weight="900" '
             . 'font-size="22" fill="' . $fallbackColor . '" text-anchor="middle">Panora</text>'
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Retourne le logo CIBLE CI en data-URI base64 (legacy, conservé
     * pour rétro-compatibilité avec les anciens templates PDF).
     * Pour les nouveaux templates, préférer getPanoraLogoDark/Light.
     */
    protected function getLogoPdf(): string
    {
        // Ordre de priorité — pour les PDF (toujours fond CLAIR) :
        //   1. logol.png : logo officiel CIBLE light (texte noir + roue colorée)
        //   2. logo-cible.png : éventuel asset custom
        //   3. logo.png / logob.png : fallback
        //   4. logon.png : version fond noir (dernier recours, illisible sur fond clair)
        $candidates = [
            public_path('images/logol.png'),
            public_path('images/logo-cible.png'),
            public_path('images/logo.png'),
            public_path('images/logob.png'),
            public_path('images/logon.png'),
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
     * Convertit un chemin photo en data-URI base64 pour DomPDF (qui ne sait
     * pas faire de HTTP). Retourne null si le fichier est introuvable.
     *
     * Fallbacks de résolution (du plus probable au moins probable) :
     *   1. storage_path('app/public/' . $rel)              ← disque "public"
     *   2. storage_path('app/' . $rel)                     ← disque "local"
     *   3. public_path('storage/' . $rel)                  ← lien symbolique
     *   4. public_path($rel)                               ← chemin absolu sous public/
     *   5. $rel lui-même s'il est déjà absolu              ← rare, mais possible
     *
     * Log en cas d'échec → utile pour diagnostiquer en prod sans casser le PDF.
     */
    protected function photoToDataUri(?string $relativePath): ?string
    {
        if (!$relativePath) return null;

        $rel = ltrim($relativePath, '/');

        // Si on reçoit déjà une URL HTTP (asset()), on tente d'en extraire
        // le chemin relatif sous storage/ pour pouvoir le résoudre localement.
        if (preg_match('#^https?://[^/]+/storage/(.+)$#', $relativePath, $m)) {
            $rel = $m[1];
        }

        $candidates = [
            storage_path('app/public/' . $rel),
            storage_path('app/' . $rel),
            public_path('storage/' . $rel),
            public_path($rel),
        ];

        if (str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relativePath)) {
            // Chemin absolu (Linux ou Windows) — on l'essaie en premier.
            array_unshift($candidates, $relativePath);
        }

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                // Si l'image est trop grosse pour DomPDF (lent/timeout), on
                // tente un downscale via GD avant d'abandonner.
                if (filesize($path) > 2 * 1024 * 1024 && extension_loaded('gd')) {
                    $resized = $this->downscaleImage($path, 1200, 800);
                    if ($resized !== null) return $resized;
                }

                if (filesize($path) > 4 * 1024 * 1024) {
                    \Illuminate\Support\Facades\Log::warning('pdf.photo.too_large', [
                        'path' => $path,
                        'size' => filesize($path),
                    ]);
                    return null;
                }
                $mime = mime_content_type($path) ?: 'image/jpeg';
                return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
            }
        }

        \Illuminate\Support\Facades\Log::info('pdf.photo.not_found', [
            'requested' => $relativePath,
            'tried'     => $candidates,
        ]);
        return null;
    }

    /**
     * Redimensionne une image (JPG/PNG/GIF/WEBP) à $maxW × $maxH max en
     * conservant le ratio, puis renvoie un data-URI JPEG qualité 78.
     * Retourne null si GD n'arrive pas à lire le fichier.
     */
    private function downscaleImage(string $path, int $maxW, int $maxH): ?string
    {
        try {
            $info = @getimagesize($path);
            if (!$info) return null;
            [$srcW, $srcH] = $info;

            $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
            $dstW  = (int) round($srcW * $ratio);
            $dstH  = (int) round($srcH * $ratio);

            $src = match ($info[2]) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
                IMAGETYPE_PNG  => @imagecreatefrompng($path),
                IMAGETYPE_GIF  => @imagecreatefromgif($path),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
                default        => null,
            };
            if (!$src) return null;

            $dst = imagecreatetruecolor($dstW, $dstH);
            // Fond blanc pour les PNG transparents (évite le bandeau noir DomPDF)
            $white = imagecolorallocate($dst, 255, 255, 255);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

            ob_start();
            imagejpeg($dst, null, 78);
            $bin = ob_get_clean();

            imagedestroy($src);
            imagedestroy($dst);

            return 'data:image/jpeg;base64,' . base64_encode($bin);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('pdf.photo.downscale_failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}

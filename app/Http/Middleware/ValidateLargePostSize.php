<?php
// app/Http/Middleware/ValidateLargePostSize.php
//
// Remplace Illuminate\Http\Middleware\ValidatePostSize
// Augmente la limite à 350Mo (10 photos × 35Mo)

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateLargePostSize
{
    /**
     * Limite maximale du POST en octets
     * 350 Mo = suffisant pour 10 photos de 35 Mo
     */
    protected int $maxPostSizeBytes = 350 * 1024 * 1024; // 350 Mo

    public function handle(Request $request, Closure $next): Response
    {
        $contentLength = (int) $request->server('CONTENT_LENGTH');

        if ($contentLength > $this->maxPostSizeBytes) {
            return response()->json([
                'message' => "Les données envoyées sont trop volumineuses. Maximum autorisé : {$this->getMaxInMb()} Mo.",
            ], 413);
        }

        return $next($request);
    }

    protected function getMaxInMb(): int
    {
        return (int) ($this->maxPostSizeBytes / 1024 / 1024);
    }
}
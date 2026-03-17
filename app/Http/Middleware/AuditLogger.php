<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\AuditLog;

class AuditLogger
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && in_array($request->method(), [
            'POST', 'PUT', 'PATCH', 'DELETE'
        ])) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }
}

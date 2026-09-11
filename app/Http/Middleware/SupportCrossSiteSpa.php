<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupportCrossSiteSpa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origin = $request->header('Origin');

        if (! is_string($origin) || ! in_array($origin, config('cors.allowed_origins', []), true)) {
            return $response;
        }

        if ($request->is('sanctum/csrf-cookie') && $request->hasSession()) {
            $response->headers->set('X-CSRF-TOKEN', $request->session()->token());
        }

        if ($request->header('Access-Control-Request-Private-Network') === 'true') {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }

        return $response;
    }
}

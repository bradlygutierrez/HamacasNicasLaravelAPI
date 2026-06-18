<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.hamacas.api_key');

        if (!$configuredKey) {
            return $next($request);
        }

        $providedKey = $request->header('X-API-Key');

        if (!$providedKey || !hash_equals($configuredKey, $providedKey)) {
            return response()->json([
                'message' => 'API key inválida o ausente.',
            ], 401);
        }

        return $next($request);
    }
}

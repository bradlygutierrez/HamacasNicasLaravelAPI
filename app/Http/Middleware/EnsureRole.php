<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if (!$usuario || !in_array($usuario->rol, $roles, true)) {
            return response()->json([
                'message' => 'No autorizado.',
            ], 403);
        }

        return $next($request);
    }
}

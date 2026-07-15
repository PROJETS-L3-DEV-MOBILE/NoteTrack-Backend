<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

//creation du middleware pour verifier si role admin
class IsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        if ($user->role !== 'admin') {
            return response()->json([
                'message' => 'Accès interdit',
            ], 403);
        }

        return $next($request);
    }
}

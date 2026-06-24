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
    // app/Http/Middleware/IsAdmin.php
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Accès interdit'
            ], 403);
        }

        return $next($request);
    }
}

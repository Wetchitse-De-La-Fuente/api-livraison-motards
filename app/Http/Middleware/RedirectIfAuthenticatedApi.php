<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticatedApi
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('api')->check()) {
            return response()->json(['message' => 'Déjà authentifié'], 403);
        }

        return $next($request);
    }
}
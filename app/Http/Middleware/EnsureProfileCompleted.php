<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $hasLegacyPrivilegedRole = $user->isSuperAdmin() || $user->isKetuaLaboran() || $user->isLaboran();

        if (! $user->hasCompletedProfile() && ! $hasLegacyPrivilegedRole) {
            if ($request->routeIs('profile.complete') || $request->routeIs('profile.complete.store') || $request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('profile.complete');
        }

        return $next($request);
    }
}

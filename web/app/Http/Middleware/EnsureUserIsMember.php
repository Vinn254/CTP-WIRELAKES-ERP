<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['ctp', 'fundi'], true)) {
            abort(403, 'Member portal access only.');
        }

        if (! $user->active) {
            abort(403, 'This account has been deactivated.');
        }

        return $next($request);
    }
}

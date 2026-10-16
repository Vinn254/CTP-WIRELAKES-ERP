<?php

namespace App\Http\Middleware;

use App\Support\Departments;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, ['admin', 'superadmin'], true)) {
            abort(403, 'Programme staff access only.');
        }

        // Each admin opens only the departments assigned to them; a super admin opens every live one. A screen with
        // no owning department (the landing, the department overview) is gated in its own controller.
        $owner = Departments::owner($request->route()?->getName());

        if ($owner !== null && ! in_array($owner, Departments::visibleSlugs($user), true)) {
            $name = Departments::find($owner)['name'] ?? $owner;
            abort(403, 'The '.$name.' screens are outside your department access.');
        }

        return $next($request);
    }
}

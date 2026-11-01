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

        // A super admin who switches an account off closes it on the next request, not only at the next sign-in —
        // an open browser tab must not keep releasing payouts after the person has left the office.
        if (! $user->active) {
            abort(403, 'This account has been deactivated. Contact a super admin.');
        }

        // Each admin opens only the departments assigned to them; a super admin opens every live one. A screen with
        // no owning department (the landing, the department overview) is gated in its own controller.
        $name = $request->route()?->getName();

        // Which desk to hold open while this person reads their own screen (profile, papers, leave, time log).
        Departments::rememberDesk($request->route());

        // A shared record shown as a copy inside another department is gated on that department instead: the page has
        // to be one that department is given a copy of, and the staff member has to belong to that department. The
        // owning module's write routes are never registered under /in/, so a copy can only ever be read.
        if (str_starts_with((string) $name, 'admin.host.')) {
            $host = $request->route()?->parameter('department');

            abort_unless(
                Departments::canHost($name, $host) && in_array($host, Departments::visibleSlugs($user), true),
                403,
                'That record is not one of '.($host ?: 'this').' department’s screens.'
            );

            return $next($request);
        }

        $owner = Departments::owner($name);

        if ($owner !== null && ! in_array($owner, Departments::visibleSlugs($user), true)) {
            $ownerName = Departments::find($owner)['name'] ?? $owner;

            // Human Resources keeps the file of everyone who joins or works here, so correcting what a person typed
            // about themselves passes this gate on another desk's register too. The controller behind it checks the
            // same list again; every other write on this record still belongs to the desk that keeps it.
            if (Departments::memberDetailWrite($name) && Departments::mayEditMembers($user)) {
                return $next($request);
            }

            // A desk that is given its own read-only copy of this screen is taken there instead of being turned
            // around: it already reads these rows in its own office, so nothing new is opened by the redirect.
            if ($request->isMethodCacheable() && ($copy = Departments::copyFor($user, (string) $name))) {
                [$route, $slug] = $copy;
                $names = app('router')->getRoutes()->getByName($route)?->parameterNames() ?? [];
                $params = collect($request->route()->parameters())->only($names)->all();

                return redirect()
                    ->route($route, $params + ['department' => $slug])
                    ->with('status', 'This is the '.$ownerName.' page. Your desk reads its own copy, '
                        .'where nothing can be changed.');
            }

            abort(403, 'The '.$ownerName.' screens are outside your department access.');
        }

        return $next($request);
    }
}

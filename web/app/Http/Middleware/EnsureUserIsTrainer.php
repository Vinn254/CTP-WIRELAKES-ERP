<?php

namespace App\Http\Middleware;

use App\Support\Workspace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The teaching papers over the API. Whoever may open My teaching on the website may fill the same papers from the
 * phone — an office trainer and an E-Fundi trainer alike — and nobody else. The check is the website's own, so a
 * desk that opens on one surface cannot be shut on the other.
 */
class EnsureUserIsTrainer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'This account is suspended. Contact the System Administrator.');
        }

        abort_unless(Workspace::trainerDesk($user), 403,
            'The teaching papers open for the trainers the office has posted. Ask the System Administrator for the Trainer role.');

        return $next($request);
    }
}

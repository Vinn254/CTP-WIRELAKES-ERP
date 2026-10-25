<?php

namespace App\Http\Middleware;

use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * The website signs out after a few idle minutes through the session; a phone holds a token instead, so the same
 * window is kept here. The cache remembers the last call for each token — ask again after the window and the token
 * is spent, exactly like the browser tab that was left open.
 */
class ExpireIdleApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken || ! $token->created_at) {
            return $next($request);
        }

        $minutes = (int) config('account.idle_minutes');
        $key = 'idle-token-'.$token->id;
        $lastSeen = Cache::get($key);

        Cache::put($key, now()->timestamp, $minutes * 60 + 60);

        if ($lastSeen !== null || $token->created_at->gt(now()->subMinutes($minutes))) {
            return $next($request);
        }

        $token->delete();
        Audit::auth('signed_out', 'The app was left idle for '.$minutes.' minutes, so its token was spent.', $request->user());

        return response()->json([
            'expired' => true,
            'message' => 'You were signed out after '.$minutes.' minutes without using the app.',
        ], 401);
    }
}

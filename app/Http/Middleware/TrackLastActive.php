<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users.last_active_at up to date for every signed-in request (app and website alike).
 * Written at most once per 5 minutes per user, so it costs almost nothing.
 */
class TrackLastActive
{
    private const EVERY_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (!$user->last_active_at || $user->last_active_at->lt(now()->subMinutes(self::EVERY_MINUTES)))) {
            $user->last_active_at = now();
            // Straight to the table: this isn't an edit of the user, so updated_at stays as it is
            DB::table('users')->where('id', $user->id)->update(['last_active_at' => $user->last_active_at]);
        }

        return $next($request);
    }
}

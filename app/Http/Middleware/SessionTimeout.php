<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Session timeout middleware.
 * Checks last_activity timestamp against configured timeout and aborts 401 if expired.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $timeoutMinutes = config('biotracker.security.session_timeout_minutes', 15);

        if ($request->user()) {
            $lastActivity = session('last_activity');

            if ($lastActivity && (time() - $lastActivity) > ($timeoutMinutes * 60)) {
                // Revoke current token if using Sanctum
                if ($request->user()->currentAccessToken()) {
                    $request->user()->currentAccessToken()->delete();
                }

                session()->flush();

                return response()->json([
                    'message' => 'Session expired due to inactivity.',
                ], 401);
            }

            session(['last_activity' => time()]);
        }

        return $next($request);
    }
}

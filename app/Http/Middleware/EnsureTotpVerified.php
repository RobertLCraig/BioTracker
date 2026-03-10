<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures TOTP verification has been completed for users with MFA enabled.
 * If user has TOTP enabled but session hasn't been TOTP-verified, returns 403.
 */
class EnsureTotpVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->totp_enabled) {
            // For API token-based auth, check if the token has totp_verified ability
            $token = $user->currentAccessToken();

            if ($token && ! in_array('totp-verified', $token->abilities ?? ['*'])) {
                // Token exists but doesn't have totp-verified ability
                // This means the user logged in but hasn't verified TOTP yet
                if ($token->abilities !== ['*']) {
                    return response()->json([
                        'message' => 'TOTP verification required.',
                        'requires_totp' => true,
                    ], 403);
                }
            }
        }

        return $next($request);
    }
}

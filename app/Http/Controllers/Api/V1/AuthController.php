<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles authentication, TOTP MFA setup/verification, and session management.
 */
class AuthController extends Controller
{
    /**
     * Register a new user account.
     * Requires privacy consent and terms acceptance.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'privacy_consent' => 'required|accepted',
            'terms_accepted' => 'required|accepted',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'privacy_consented_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        AuditService::log('register', $user);

        return response()->json([
            'message' => 'Registration successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 201);
    }

    /**
     * Authenticate a user and issue a Sanctum token.
     * If TOTP is enabled, returns a temporary token for TOTP verification.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($validated)) {
            AuditService::log('login_failed', null, null, ['email' => $validated['email']]);

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        /** @var User $user */
        $user = Auth::user();

        if ($user->totp_enabled) {
            // Issue a temporary token with limited abilities for TOTP verification
            $tempToken = $user->createToken('totp-pending', ['totp-pending'])->plainTextToken;

            AuditService::log('login_totp_pending', $user);

            return response()->json([
                'message' => 'TOTP verification required.',
                'requires_totp' => true,
                'login_token' => $tempToken,
            ]);
        }

        // No TOTP — issue full token
        $token = $user->createToken('auth-token')->plainTextToken;

        AuditService::log('login', $user);

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Verify TOTP code after login for MFA-enabled accounts.
     * Accepts either a TOTP code or a recovery code.
     */
    public function verifyTotp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login_token' => 'required|string',
            'code' => 'required|string',
        ]);

        // Parse the token to authenticate the user
        $tokenParts = explode('|', $validated['login_token']);
        $tokenId = $tokenParts[0] ?? null;

        // Authenticate using the provided token
        $request->headers->set('Authorization', 'Bearer ' . $validated['login_token']);
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return response()->json(['message' => 'Invalid login token.'], 401);
        }

        // Check if the token has the totp-pending ability
        $currentToken = $user->currentAccessToken();
        if (! $currentToken || ! in_array('totp-pending', $currentToken->abilities)) {
            return response()->json(['message' => 'Invalid login token.'], 401);
        }

        // Try TOTP code first, then recovery code
        $verified = false;
        if (strlen($validated['code']) === 6 && ctype_digit($validated['code'])) {
            $verified = $user->verifyTotp($validated['code']);
        }

        if (! $verified) {
            // Try as recovery code
            $verified = $user->useRecoveryCode($validated['code']);
        }

        if (! $verified) {
            AuditService::log('totp_verification_failed', $user);
            return response()->json(['message' => 'Invalid TOTP code.'], 401);
        }

        // Delete the temporary token
        $currentToken->delete();

        // Issue full-access token
        $token = $user->createToken('auth-token')->plainTextToken;

        AuditService::log('login', $user);

        return response()->json([
            'message' => 'TOTP verified. Login successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Revoke the current access token (logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        AuditService::log('logout', $request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return the authenticated user's profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'totp_enabled' => $request->user()->totp_enabled,
            ],
        ]);
    }

    /**
     * Generate a TOTP secret and return the QR code provisioning URL.
     */
    public function setupTotp(Request $request): JsonResponse
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Store the secret temporarily (encrypted) on the user
        $request->user()->update([
            'totp_secret' => Crypt::encryptString($secret),
        ]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret
        );

        AuditService::log('totp_setup_initiated', $request->user());

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    /**
     * Confirm TOTP setup by verifying a code. Enables MFA and returns recovery codes.
     */
    public function confirmTotp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! $user->totp_secret) {
            return response()->json([
                'message' => 'TOTP setup not initiated. Call setup first.',
            ], 400);
        }

        if (! $user->verifyTotp($validated['code'])) {
            return response()->json([
                'message' => 'Invalid TOTP code.',
            ], 422);
        }

        // Enable TOTP and generate recovery codes
        $recoveryCodes = $user->generateAndStoreRecoveryCodes();
        $user->update([
            'totp_enabled' => true,
            'totp_confirmed_at' => now(),
        ]);

        AuditService::log('totp_enabled', $user);

        return response()->json([
            'message' => 'TOTP enabled successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable TOTP MFA. Requires password and current TOTP code.
     */
    public function disableTotp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password.',
            ], 401);
        }

        if (! $user->verifyTotp($validated['code'])) {
            return response()->json([
                'message' => 'Invalid TOTP code.',
            ], 422);
        }

        $user->disableTotp();

        AuditService::log('totp_disabled', $user);

        return response()->json([
            'message' => 'TOTP disabled successfully.',
        ]);
    }
}

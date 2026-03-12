<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PasskeyCredential;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyController extends Controller
{
    // ── Helpers ────────────────────────────────────────────────────────────

    private function rpId(): string
    {
        return parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }

    private function rpName(): string
    {
        return config('app.name', 'BioTracker');
    }

    // ── List passkeys (authenticated) ─────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $passkeys = PasskeyCredential::where('user_id', $request->user()->id)
            ->get(['id', 'name', 'created_at', 'last_used_at']);

        return response()->json(['data' => $passkeys]);
    }

    // ── Delete passkey (authenticated) ────────────────────────────────────

    public function destroy(Request $request, int $id): JsonResponse
    {
        PasskeyCredential::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return response()->json(['message' => 'Passkey removed.']);
    }

    // ── Registration: generate challenge (authenticated) ──────────────────

    public function registrationOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        $rpEntity   = PublicKeyCredentialRpEntity::create($this->rpName(), $this->rpId());
        $userEntity = PublicKeyCredentialUserEntity::create(
            $user->name,
            (string) $user->id,
            $user->name,
        );

        // Exclude already-registered credentials
        $existingDescriptors = PasskeyCredential::where('user_id', $user->id)
            ->get()
            ->map(fn ($pk) => PublicKeyCredentialDescriptor::create(
                PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                base64_decode(strtr($pk->credential_id, '-_', '+/')),
            ))
            ->toArray();

        $options = PublicKeyCredentialCreationOptions::create(
            rp:          $rpEntity,
            user:        $userEntity,
            challenge:   random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createRs256(),
                PublicKeyCredentialParameters::createEs256(),
            ],
        );

        $options->setAuthenticatorSelection(
            AuthenticatorSelectionCriteria::create(
                residentKey:             AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
                requireResidentKey:      true,
                userVerification:        AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            )
        );

        if ($existingDescriptors) {
            $options->excludeCredentials(...$existingDescriptors);
        }

        $token = Str::random(40);
        Cache::put("passkey_reg:{$token}", json_encode($options), now()->addMinutes(5));

        return response()->json([
            'challenge_token' => $token,
            'options'         => $options,
        ]);
    }

    // ── Registration: verify & store (authenticated) ──────────────────────

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'name'            => 'nullable|string|max:100',
            'credential'      => 'required|array',
        ]);

        $cacheKey = "passkey_reg:{$request->challenge_token}";
        $raw = Cache::pull($cacheKey);

        if (! $raw) {
            return response()->json(['message' => 'Challenge expired or invalid.'], 422);
        }

        try {
            $options = PublicKeyCredentialCreationOptions::createFromString($raw);

            $loader    = PublicKeyCredentialLoader::create();
            $publicKey = $loader->loadArray($request->credential);

            $response = $publicKey->getResponse();

            if (! $response instanceof AuthenticatorAttestationResponse) {
                return response()->json(['message' => 'Invalid attestation response.'], 422);
            }

            $validator = AuthenticatorAttestationResponseValidator::create();
            $source    = $validator->check($response, $options, $this->rpId());

            $credentialId = rtrim(strtr(base64_encode($source->getPublicKeyCredentialId()), '+/', '-_'), '=');

            PasskeyCredential::create([
                'user_id'           => $request->user()->id,
                'name'              => $request->name ?: 'Passkey',
                'credential_id'     => $credentialId,
                'credential_source' => $source->jsonSerialize(),
            ]);

            return response()->json(['message' => 'Passkey registered.']);

        } catch (\Throwable $e) {
            Log::warning('Passkey registration failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Registration failed: ' . $e->getMessage()], 422);
        }
    }

    // ── Authentication: generate challenge (public) ───────────────────────

    public function authenticationOptions(Request $request): JsonResponse
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge:        random_bytes(32),
            rpId:             $this->rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );

        $token = Str::random(40);
        Cache::put("passkey_auth:{$token}", json_encode($options), now()->addMinutes(5));

        return response()->json([
            'challenge_token' => $token,
            'options'         => $options,
        ]);
    }

    // ── Authentication: verify assertion & return token (public) ─────────

    public function authenticate(Request $request): JsonResponse
    {
        $request->validate([
            'challenge_token' => 'required|string',
            'credential'      => 'required|array',
        ]);

        $cacheKey = "passkey_auth:{$request->challenge_token}";
        $raw = Cache::pull($cacheKey);

        if (! $raw) {
            return response()->json(['message' => 'Challenge expired or invalid.'], 422);
        }

        try {
            $options = PublicKeyCredentialRequestOptions::createFromString($raw);

            $loader    = PublicKeyCredentialLoader::create();
            $publicKey = $loader->loadArray($request->credential);

            $response = $publicKey->getResponse();

            if (! $response instanceof AuthenticatorAssertionResponse) {
                return response()->json(['message' => 'Invalid assertion response.'], 422);
            }

            // Find stored credential by raw id
            $rawId = rtrim(strtr(base64_encode($publicKey->getRawId()), '+/', '-_'), '=');
            $storedPasskey = PasskeyCredential::where('credential_id', $rawId)->first();

            if (! $storedPasskey) {
                return response()->json(['message' => 'Passkey not found.'], 401);
            }

            $storedSource = PublicKeyCredentialSource::createFromArray($storedPasskey->credential_source);

            $validator    = AuthenticatorAssertionResponseValidator::create();
            $updatedSource = $validator->check(
                $storedSource,
                $response,
                $options,
                $this->rpId(),
                $storedSource->getUserHandle(),
            );

            // Update counter
            $storedPasskey->update([
                'credential_source' => $updatedSource->jsonSerialize(),
                'last_used_at'      => now(),
            ]);

            $user = User::find($storedPasskey->user_id);

            if (! $user) {
                return response()->json(['message' => 'User not found.'], 401);
            }

            $token = $user->createToken('passkey')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::warning('Passkey authentication failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Authentication failed: ' . $e->getMessage()], 401);
        }
    }
}

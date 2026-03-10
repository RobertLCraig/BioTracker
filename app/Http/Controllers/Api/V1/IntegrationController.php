<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ConnectedService;
use App\Services\AuditService;
use App\Services\Integrations\FitbitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function __construct(private FitbitService $fitbit) {}

    /**
     * List all connected services for the authenticated user.
     * Token fields are never exposed.
     */
    public function index(Request $request): JsonResponse
    {
        $services = ConnectedService::withoutGlobalScopes()
            ->where('user_id', $request->user()->id)
            ->get()
            ->map(fn ($s) => [
                'provider'       => $s->provider?->value,
                'last_synced_at' => $s->last_synced_at?->toIso8601String(),
                'token_expires_at' => $s->token_expires_at?->toIso8601String(),
                'scopes'         => $s->scopes,
                'is_expired'     => $s->isTokenExpired(),
            ]);

        return response()->json(['data' => $services]);
    }

    /**
     * Redirect the user to the OAuth provider's authorization page.
     */
    public function redirect(string $provider): JsonResponse|RedirectResponse
    {
        return match ($provider) {
            'fitbit' => redirect($this->fitbit->getAuthUrl()),
            default  => response()->json(['message' => "Provider '{$provider}' not supported."], 422),
        };
    }

    /**
     * Handle the OAuth callback, store tokens, and return connected service status.
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $user = $request->user();

        $service = match ($provider) {
            'fitbit' => $this->fitbit->handleCallback($user, $request->input('code')),
            default  => null,
        };

        if (! $service) {
            return response()->json(['message' => "Provider '{$provider}' not supported."], 422);
        }

        AuditService::log('integration_connected', $user, null, ['provider' => $provider]);

        return response()->json([
            'message'  => ucfirst($provider) . ' connected successfully.',
            'provider' => $provider,
        ]);
    }

    /**
     * Trigger a manual sync for a provider (dispatches a queued job).
     */
    public function sync(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();

        match ($provider) {
            'fitbit' => \App\Jobs\SyncFitbitDataJob::dispatch($user),
            default  => null,
        };

        if (! in_array($provider, ['fitbit', 'apple_health', 'health_connect'])) {
            return response()->json(['message' => "Provider '{$provider}' not supported."], 422);
        }

        return response()->json(['message' => ucfirst($provider) . ' sync queued.']);
    }

    /**
     * Disconnect a provider and delete the stored tokens.
     */
    public function disconnect(Request $request, string $provider): JsonResponse
    {
        $user = $request->user();

        ConnectedService::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->where('provider', $provider)
            ->delete();

        AuditService::log('integration_disconnected', $user, null, ['provider' => $provider]);

        return response()->json(['message' => ucfirst($provider) . ' disconnected.']);
    }
}

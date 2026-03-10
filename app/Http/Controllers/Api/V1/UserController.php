<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ExcretionLog;
use App\Models\MedicationLog;
use App\Models\SymptomLog;
use App\Models\VitalLog;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * User profile, GDPR data export, and account deletion.
 */
class UserController extends Controller
{
    /**
     * Return the authenticated user's full profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditService::log('view', $user);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'totp_enabled' => $user->totp_enabled,
                'privacy_consented_at' => $user->privacy_consented_at?->toIso8601String(),
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'created_at' => $user->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile details.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $request->user()->id,
        ]);

        $user = $request->user();
        $oldValues = $user->only(['name', 'email']);

        $user->update($validated);

        AuditService::log('update', $user, $oldValues, $validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Export all of the authenticated user's data as JSON (GDPR right to data portability).
     */
    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
                'privacy_consented_at' => $user->privacy_consented_at?->toIso8601String(),
                'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
            ],
            'activity_logs' => [],
            'excretion_logs' => [],
            'medication_logs' => [],
            'symptom_logs' => [],
            'vital_logs' => [],
        ];

        // Collect all user data — each model uses BelongsToUser so already scoped
        if (class_exists(ActivityLog::class)) {
            $data['activity_logs'] = ActivityLog::all()->toArray();
        }
        if (class_exists(ExcretionLog::class)) {
            $data['excretion_logs'] = ExcretionLog::all()->toArray();
        }
        if (class_exists(MedicationLog::class)) {
            $data['medication_logs'] = MedicationLog::all()->toArray();
        }
        if (class_exists(SymptomLog::class)) {
            $data['symptom_logs'] = SymptomLog::all()->toArray();
        }
        if (class_exists(VitalLog::class)) {
            $data['vital_logs'] = VitalLog::all()->toArray();
        }

        AuditService::log('export', $user);

        return response()->json([
            'message' => 'Data export generated.',
            'data' => $data,
        ]);
    }

    /**
     * Permanently delete the authenticated user's account and all associated data (GDPR right to erasure).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid password.',
            ], 401);
        }

        // Audit log the deletion before executing
        AuditService::log('delete', $user);

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user — cascading deletes will handle related data
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}

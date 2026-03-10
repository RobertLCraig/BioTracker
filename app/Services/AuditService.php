<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Service for recording audit log entries.
 * Captures user actions for HIPAA compliance and security auditing.
 */
class AuditService
{
    /**
     * Log an auditable action.
     *
     * @param string      $action    The action performed (view, create, update, delete, export, login)
     * @param Model|null  $auditable The model being acted upon (polymorphic)
     * @param array|null  $oldValues Previous values (for updates)
     * @param array|null  $newValues New values (for creates/updates)
     */
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        try {
            return AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'auditable_type' => $auditable ? get_class($auditable) : null,
                'auditable_id' => $auditable?->getKey(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit logging failures break the application
            Log::error('Audit logging failed: ' . $e->getMessage());

            return new AuditLog();
        }
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAppleHealthImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles third-party data file imports.
 *
 * POST /api/v1/imports/apple-health
 *   Accepts an Apple Health export.xml file (multipart).
 *   Small files (< 5 MB) are processed synchronously.
 *   Larger files are queued to avoid HTTP timeouts.
 */
class ImportController extends Controller
{
    private const SYNC_THRESHOLD_BYTES = 5 * 1024 * 1024; // 5 MB

    public function appleHealth(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xml|max:102400', // max 100 MB
        ]);

        $user = $request->user();
        $file = $request->file('file');

        // For large files, store and queue; for small files, process inline
        if ($file->getSize() > self::SYNC_THRESHOLD_BYTES) {
            $path = $file->store('apple-health-imports/' . $user->id, 'local');

            ProcessAppleHealthImportJob::dispatch($user, $path);

            return response()->json([
                'message' => 'Import queued. Your data will be processed shortly.',
                'queued'  => true,
            ]);
        }

        // Synchronous path for small files
        $xmlContent = file_get_contents($file->getRealPath());

        $importer = app(\App\Services\Integrations\AppleHealthImportService::class);
        $result   = $importer->import($user, $xmlContent);

        \App\Services\AuditService::log('apple_health_import', $user, null, $result);

        return response()->json(array_merge(
            ['message' => 'Apple Health data imported successfully.', 'queued' => false],
            $result
        ));
    }
}

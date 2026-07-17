<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessLabImportJob;
use App\Services\AuditService;
use App\Services\Lab\PkbTestImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PKB test-history JSON import (primary lab-results ingest path).
 *
 * POST /api/v1/lab-results/import
 *   Either a multipart `file` (the pkb-tests.json produced by the capture
 *   snippet) or an inline `payload` (JSON string or array). Small inputs are
 *   processed synchronously; large files are queued.
 */
class LabImportController extends Controller
{
    private const SYNC_THRESHOLD_BYTES = 2 * 1024 * 1024; // 2 MB

    public function pkb(Request $request, PkbTestImportService $importer): JsonResponse
    {
        $user = $request->user();

        if ($request->hasFile('file')) {
            $request->validate(['file' => 'required|file|mimes:json,txt|max:51200']); // 50 MB
            $file = $request->file('file');

            if ($file->getSize() > self::SYNC_THRESHOLD_BYTES) {
                $path = $file->store('lab-imports/' . $user->id, 'local');
                ProcessLabImportJob::dispatch($user, $path);

                return response()->json([
                    'message' => 'Import queued. Your results will be processed shortly.',
                    'queued' => true,
                ]);
            }

            $result = $importer->import($user, file_get_contents($file->getRealPath()));
        } else {
            $request->validate(['payload' => 'required']);
            $result = $importer->import($user, $request->input('payload'));
        }

        AuditService::log('lab_import_pkb', $user, null, $result);

        return response()->json(array_merge(
            ['message' => 'Lab results imported.', 'queued' => false],
            $result,
        ));
    }
}

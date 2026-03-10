<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditService;
use App\Services\Integrations\AppleHealthImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Processes a previously uploaded Apple Health export XML file.
 * Dispatched by ImportController when a file is uploaded.
 * The XML file is stored temporarily on disk and deleted after processing.
 */
class ProcessAppleHealthImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 600; // 10 minutes for large exports

    public function __construct(
        public readonly User   $user,
        public readonly string $storagePath,
    ) {}

    public function handle(AppleHealthImportService $importer): void
    {
        try {
            $xmlContent = Storage::get($this->storagePath);

            if ($xmlContent === null) {
                Log::error('Apple Health import file not found', [
                    'user_id' => $this->user->id,
                    'path'    => $this->storagePath,
                ]);
                return;
            }

            $result = $importer->import($this->user, $xmlContent);

            AuditService::log('apple_health_import', $this->user, null, $result);

            Log::info('Apple Health import complete', array_merge(
                ['user_id' => $this->user->id],
                $result
            ));
        } finally {
            // Always clean up the temporary file
            Storage::delete($this->storagePath);
        }
    }
}

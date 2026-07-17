<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AuditService;
use App\Services\Lab\PkbTestImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Processes a previously uploaded PKB test-history JSON file.
 * Dispatched by LabImportController for larger uploads. The temp file is
 * deleted after processing.
 */
class ProcessLabImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300;

    public function __construct(
        public readonly User $user,
        public readonly string $storagePath,
    ) {}

    public function handle(PkbTestImportService $importer): void
    {
        try {
            $json = Storage::get($this->storagePath);
            if ($json === null) {
                Log::error('Lab import file not found', [
                    'user_id' => $this->user->id,
                    'path' => $this->storagePath,
                ]);
                return;
            }

            $result = $importer->import($this->user, $json);

            AuditService::log('lab_import_pkb', $this->user, null, $result);
            Log::info('Lab import complete', array_merge(['user_id' => $this->user->id], $result));
        } finally {
            Storage::delete($this->storagePath);
        }
    }
}

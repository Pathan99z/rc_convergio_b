<?php

namespace App\Jobs;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ImportContactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $path, public int $tenantId, public int $userId) {}

    public function handle(): void
    {
        $fullPath = Storage::path($this->path);

        if (! is_file($fullPath)) {
            Log::warning('ImportContactsJob: file missing', ['path' => $fullPath]);
            return;
        }

        $csv = Reader::createFromPath($fullPath, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $offset => $record) {
            try {
                $data = [
                    'first_name' => trim((string) ($record['first_name'] ?? '')),
                    'last_name' => trim((string) ($record['last_name'] ?? '')),
                    'email' => $record['email'] ?? null,
                    'phone' => $record['phone'] ?? null,
                    'owner_id' => (int) ($record['owner_id'] ?? $this->userId),
                    'company_id' => $record['company_id'] !== '' ? (int) $record['company_id'] : null,
                    'lifecycle_stage' => $record['lifecycle_stage'] ?? null,
                    'source' => $record['source'] ?? null,
                    'tags' => isset($record['tags']) ? array_values(array_filter(array_map('trim', explode('|', (string) $record['tags'])))) : [],
                    'tenant_id' => $this->tenantId,
                ];

                // Basic per-row validation (mirrors FormRequest core checks)
                if ($data['first_name'] === '' || $data['last_name'] === '') {
                    throw new \RuntimeException('Missing first_name/last_name');
                }
                if ($data['email'] !== null && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email');
                }
                if ($data['phone'] !== null && ! preg_match('/^\+?[1-9]\d{1,14}$/', (string) $data['phone'])) {
                    throw new \RuntimeException('Invalid phone');
                }

                Contact::updateOrCreate(
                    ['email' => $data['email'], 'tenant_id' => $this->tenantId],
                    $data
                );
            } catch (\Throwable $e) {
                Log::warning('ImportContactsJob: row skipped', [
                    'row' => $offset,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}



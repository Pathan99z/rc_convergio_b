<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerN8n
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function handle(): void
    {
        $url    = config('services.n8n.webhook_url');
        $secret = config('services.n8n.secret');

        Log::info('n8n:job start', ['uuid' => $this->payload['uuid'] ?? null]);

        $resp = Http::timeout(8)
            ->retry(2, 300)
            ->withHeaders(['X-Shared-Secret' => $secret])
            ->post($url, $this->payload);

        Log::info('n8n:job done', [
            'uuid'   => $this->payload['uuid'] ?? null,
            'status' => $resp->status(),
            'body'   => mb_substr($resp->body(), 0, 250),
        ]);
    }
}

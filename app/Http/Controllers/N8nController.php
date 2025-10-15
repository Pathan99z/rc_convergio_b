<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class N8nController extends Controller
{
    public function trigger(Request $request)
    {
        $uuid = (string) Str::uuid();

        $payload = [
            'uuid'  => $uuid,
            'source'=> 'laravel',
            'env'   => app()->environment(),
            'user'  => optional($request->user())->id,
            'data'  => $request->only(['id','email','name']) ?: ['ping' => now()->toIso8601String()],
        ];

        Log::info('n8n:trigger queued (closure afterResponse)', ['uuid' => $uuid]);

        // Non-blocking, zero-setup: run AFTER the HTTP response is sent.
        dispatch(function () use ($payload) {
            $url    = config('services.n8n.webhook');
            $secret = config('services.n8n.secret');

            $resp = Http::timeout(8)
                ->retry(2, 300)
                ->withHeaders(['X-Shared-Secret' => $secret])
                ->post($url, $payload);

            Log::info('n8n:closure done', [
                'uuid'   => $payload['uuid'] ?? null,
                'status' => $resp->status(),
                'body'   => mb_substr($resp->body(), 0, 250),
            ]);
        })->afterResponse();

        return response()->json(['queued' => true, 'uuid' => $uuid], 202);
    }
}

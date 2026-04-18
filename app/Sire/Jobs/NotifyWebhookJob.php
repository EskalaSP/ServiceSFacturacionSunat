<?php

namespace App\Sire\Jobs;

use App\Models\Tenant;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Notifica al webhook_url del tenant un evento SIRE.
 * No falla el job si el webhook responde 4xx/5xx — solo loguea.
 */
class NotifyWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 15;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $event,
        public readonly array $payload = [],
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant?->webhook_url) {
            return;
        }

        $body = [
            'event'     => $this->event,
            'tenant_id' => $this->tenantId,
            'ruc'       => $tenant->ruc,
            'timestamp' => now()->toIso8601String(),
            'payload'   => $this->payload,
        ];

        try {
            (new Client(['timeout' => $this->timeout]))->post($tenant->webhook_url, [
                'json'    => $body,
                'headers' => ['User-Agent' => 'API-PRO-SIRE-Webhook/1.0'],
            ]);

            Log::channel('stack')->info('[SIRE][Webhook] enviado', [
                'tenant_id' => $tenant->id,
                'event'     => $this->event,
            ]);
        } catch (\Throwable $e) {
            Log::channel('stack')->warning('[SIRE][Webhook] falló', [
                'tenant_id' => $tenant->id,
                'event'     => $this->event,
                'url'       => $tenant->webhook_url,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}

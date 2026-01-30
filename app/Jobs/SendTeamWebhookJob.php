<?php

namespace App\Jobs;

use App\Models\TeamWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class SendTeamWebhookJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public TeamWebhook $webhook,
        public WebhookDelivery $delivery
    ) {
        $this->onQueue('high');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = microtime(true);

        // Security: Validate URL to prevent SSRF attacks
        $validation = validateWebhookUrl($this->webhook->url);
        if (! $validation['valid']) {
            $this->delivery->markAsFailed(
                0,
                "URL blocked for security reasons: {$validation['error']}",
                0
            );

            if (isDev()) {
                ray('Team webhook URL blocked for security reasons', [
                    'webhook_id' => $this->webhook->id,
                    'url' => $this->webhook->url,
                    'reason' => $validation['error'],
                ]);
            }

            return;
        }

        try {
            $payload = $this->delivery->payload;

            // Add signature to payload
            $signature = $this->generateSignature($payload);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Saturn-Signature' => $signature,
                    'X-Saturn-Event' => $this->delivery->event,
                    'X-Saturn-Delivery' => $this->delivery->uuid,
                ])
                ->post($this->webhook->url, $payload);

            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                $this->delivery->markAsSuccess(
                    $response->status(),
                    $response->body(),
                    $responseTimeMs
                );

                // Update last triggered
                $this->webhook->update(['last_triggered_at' => now()]);
            } else {
                $this->delivery->markAsFailed(
                    $response->status(),
                    $response->body(),
                    $responseTimeMs
                );
            }

            if (isDev()) {
                ray('Team webhook sent', [
                    'webhook_id' => $this->webhook->id,
                    'delivery_id' => $this->delivery->id,
                    'status' => $response->status(),
                    'response_time_ms' => $responseTimeMs,
                ]);
            }
        } catch (\Exception $e) {
            $responseTimeMs = (int) ((microtime(true) - $startTime) * 1000);

            $this->delivery->markAsFailed(
                0,
                $e->getMessage(),
                $responseTimeMs
            );

            if (isDev()) {
                ray('Team webhook failed', [
                    'webhook_id' => $this->webhook->id,
                    'delivery_id' => $this->delivery->id,
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Generate HMAC signature for the payload.
     */
    private function generateSignature(array $payload): string
    {
        $payloadJson = json_encode($payload);

        return hash_hmac('sha256', $payloadJson, $this->webhook->secret);
    }
}

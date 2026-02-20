<?php

namespace App\Jobs;

use App\Notifications\Dto\SlackMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMessageToSlackJob implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [10, 30, 60];

    public int $maxExceptions = 3;

    public function __construct(
        private SlackMessage $message,
        private string $webhookUrl
    ) {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        try {
            $response = Http::timeout(10)->post($this->webhookUrl, [
                'text' => $this->message->title,
                'blocks' => [
                    [
                        'type' => 'section',
                        'text' => [
                            'type' => 'plain_text',
                            'text' => 'Saturn Platform Notification',
                        ],
                    ],
                ],
                'attachments' => [
                    [
                        'color' => $this->message->color,
                        'blocks' => [
                            [
                                'type' => 'header',
                                'text' => [
                                    'type' => 'plain_text',
                                    'text' => $this->message->title,
                                ],
                            ],
                            [
                                'type' => 'section',
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => $this->message->description,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Slack notification failed with status '.$response->status());
            }
        } catch (\Exception $e) {
            Log::warning('SendMessageToSlackJob failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

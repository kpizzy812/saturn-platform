<?php

namespace App\Jobs;

use App\Events\AiChatMessageReceived;
use App\Models\AiChatSession;
use App\Services\AI\Chat\AiChatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiChatMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    public function __construct(
        public AiChatSession $session,
        public string $content,
        public bool $executeCommands = true
    ) {}

    public function handle(AiChatService $chatService): void
    {
        try {
            $message = $chatService->sendMessage(
                session: $this->session,
                content: $this->content,
                executeCommands: $this->executeCommands,
            );

            Log::info('AI Chat message processed', [
                'session_uuid' => $this->session->uuid,
                'message_uuid' => $message->uuid,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI Chat message processing failed', [
                'session_uuid' => $this->session->uuid,
                'error' => $e->getMessage(),
            ]);

            // Create error message
            $errorMessage = $this->session->messages()->create([
                'role' => 'assistant',
                'content' => 'Sorry, I encountered an error while processing your message. Please try again.',
            ]);

            broadcast(new AiChatMessageReceived($this->session, $errorMessage));

            throw $e;
        }
    }
}

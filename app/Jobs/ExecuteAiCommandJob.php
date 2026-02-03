<?php

namespace App\Jobs;

use App\Events\AiCommandExecuted;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use App\Services\AI\Chat\CommandExecutor;
use App\Services\AI\Chat\DTOs\IntentResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExecuteAiCommandJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    public function __construct(
        public AiChatSession $session,
        public AiChatMessage $message,
        public string $intent,
        public array $params
    ) {}

    public function handle(): void
    {
        try {
            // Update message status to executing
            $this->message->updateCommandStatus('executing');

            // Create intent result
            $intentResult = new IntentResult(
                intent: $this->intent,
                params: $this->params,
                confidence: 1.0,
                requiresConfirmation: false,
            );

            // Execute command
            $executor = new CommandExecutor($this->session->user, $this->session->team_id);
            $result = $executor->execute($intentResult);

            // Update message with result
            $status = $result->success ? 'completed' : 'failed';
            $this->message->updateCommandStatus($status, $result->message);

            // Broadcast result
            broadcast(new AiCommandExecuted(
                session: $this->session,
                message: $this->message,
                success: $result->success,
                result: $result->message,
            ));

            Log::info('AI command executed', [
                'session_uuid' => $this->session->uuid,
                'message_uuid' => $this->message->uuid,
                'intent' => $this->intent,
                'success' => $result->success,
            ]);
        } catch (\Throwable $e) {
            Log::error('AI command execution failed', [
                'session_uuid' => $this->session->uuid,
                'message_uuid' => $this->message->uuid,
                'error' => $e->getMessage(),
            ]);

            $this->message->updateCommandStatus('failed', $e->getMessage());

            broadcast(new AiCommandExecuted(
                session: $this->session,
                message: $this->message,
                success: false,
                result: 'Command execution failed: '.$e->getMessage(),
            ));

            throw $e;
        }
    }
}

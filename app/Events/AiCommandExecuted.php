<?php

namespace App\Events;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiCommandExecuted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AiChatSession $session,
        public AiChatMessage $message,
        public bool $success,
        public string $result
    ) {}

    public function broadcastWith(): array
    {
        return [
            'message_uuid' => $this->message->uuid,
            'session_uuid' => $this->session->uuid,
            'success' => $this->success,
            'result' => $this->result,
            'command_status' => $this->success ? 'completed' : 'failed',
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ai-chat.{$this->session->uuid}"),
        ];
    }
}

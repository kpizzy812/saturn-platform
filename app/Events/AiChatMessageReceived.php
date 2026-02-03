<?php

namespace App\Events;

use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AiChatMessageReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AiChatSession $session,
        public AiChatMessage $message
    ) {}

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'uuid' => $this->message->uuid,
                'session_id' => $this->message->session_id,
                'role' => $this->message->role,
                'content' => $this->message->content,
                'intent' => $this->message->intent,
                'intent_params' => $this->message->intent_params,
                'command_status' => $this->message->command_status,
                'command_result' => $this->message->command_result,
                'created_at' => $this->message->created_at->toIso8601String(),
            ],
            'session_uuid' => $this->session->uuid,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("ai-chat.{$this->session->uuid}"),
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeploymentLogEntry implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $deploymentUuid,
        public string $message,
        public string $timestamp,
        public string $type = 'stdout',
        public int $order = 1
    ) {}

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'timestamp' => $this->timestamp,
            'type' => $this->type,
            'order' => $this->order,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("deployment.{$this->deploymentUuid}.logs"),
        ];
    }
}

<?php

namespace App\Events;

use App\Models\Server;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerReachabilityChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Server $server
    ) {
        $this->server->isReachableChanged();
    }

    public function broadcastWith(): array
    {
        return [
            'serverId' => $this->server->id,
            'isReachable' => $this->server->is_reachable,
            'isUsable' => $this->server->is_usable,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("team.{$this->server->team_id}"),
        ];
    }
}

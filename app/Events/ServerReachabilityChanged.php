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
            'isReachable' => $this->server->isReachable(),
            'isUsable' => $this->server->isUsable(),
        ];
    }

    public function broadcastOn(): array
    {
        $teamId = $this->server->team_id;

        if (is_null($teamId)) {
            return [];
        }

        return [
            new PrivateChannel("team.{$teamId}"),
        ];
    }
}

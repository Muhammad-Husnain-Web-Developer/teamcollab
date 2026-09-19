<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserPresenceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $status;
    public string $tenantId;

    public function __construct(int $userId, string $status, string $tenantId)
    {
        $this->userId   = $userId;
        $this->status   = in_array($status, ['online', 'away', 'offline']) ? $status : 'offline';
        $this->tenantId = $tenantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("presence-tenant.{$this->tenantId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.presence.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'status'  => $this->status,
        ];
    }
}

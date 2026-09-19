<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageRead implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;
    public int $userId;
    public int $channelId;
    public string $tenantId;

    public function __construct(int $messageId, int $userId, int $channelId, string $tenantId)
    {
        $this->messageId = $messageId;
        $this->userId    = $userId;
        $this->channelId = $channelId;
        $this->tenantId  = $tenantId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->tenantId}.channel.{$this->channelId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.read';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'user_id'    => $this->userId,
            'channel_id' => $this->channelId,
            'tenant_id'  => $this->tenantId,
        ];
    }
}

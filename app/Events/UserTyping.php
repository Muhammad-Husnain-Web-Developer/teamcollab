<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserTyping implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public string $userName;
    public ?int $channelId;
    public ?int $conversationId;
    public string $tenantId;

    public function __construct(
        int $userId,
        string $userName,
        ?int $channelId,
        ?int $conversationId,
        string $tenantId
    ) {
        $this->userId         = $userId;
        $this->userName       = $userName;
        $this->channelId      = $channelId;
        $this->conversationId = $conversationId;
        $this->tenantId       = $tenantId;
    }

    public function broadcastOn(): array
    {
        // Channel typing → channel stream; DM typing → conversation stream.
        if ($this->channelId) {
            return [new PrivateChannel("tenant.{$this->tenantId}.channel.{$this->channelId}")];
        }

        return [new PrivateChannel("tenant.{$this->tenantId}.dm.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'user.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'         => $this->userId,
            'user_name'       => $this->userName,
            'channel_id'      => $this->channelId,
            'conversation_id' => $this->conversationId,
        ];
    }
}

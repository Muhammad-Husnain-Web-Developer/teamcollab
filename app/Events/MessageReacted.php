<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageReacted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $messageId;
    public string $emoji;
    public int $userId;
    public string $userName;
    public string $action;
    public ?int $channelId;
    public ?int $conversationId;
    public string $tenantId;

    public function __construct(
        int $messageId,
        string $emoji,
        int $userId,
        string $userName,
        string $action,
        ?int $channelId,
        ?int $conversationId,
        string $tenantId
    ) {
        $this->messageId      = $messageId;
        $this->emoji          = $emoji;
        $this->userId         = $userId;
        $this->userName       = $userName;
        $this->action         = in_array($action, ['added', 'removed']) ? $action : 'added';
        $this->channelId      = $channelId;
        $this->conversationId = $conversationId;
        $this->tenantId       = $tenantId;
    }

    public function broadcastOn(): array
    {
        // Channel messages → channel stream; DM messages → conversation stream.
        if ($this->channelId) {
            return [new PrivateChannel("tenant.{$this->tenantId}.channel.{$this->channelId}")];
        }

        return [new PrivateChannel("tenant.{$this->tenantId}.dm.{$this->conversationId}")];
    }

    public function broadcastAs(): string
    {
        return 'message.reacted';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id'      => $this->messageId,
            'emoji'           => $this->emoji,
            'user_id'         => $this->userId,
            'user_name'       => $this->userName,
            'action'          => $this->action,
            'channel_id'      => $this->channelId,
            'conversation_id' => $this->conversationId,
            'tenant_id'       => $this->tenantId,
        ];
    }
}

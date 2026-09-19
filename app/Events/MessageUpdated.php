<?php

namespace App\Events;

use App\Models\Tenant\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * An edit to an existing message. The client merges the payload into its
 * copy with the same generic updateMessage() it uses for pins and link
 * previews, so only the fields that changed are sent.
 */
class MessageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public string $tenantId,
    ) {}

    public function broadcastOn(): array
    {
        $channel = $this->message->channel_id
            ? "tenant.{$this->tenantId}.channel.{$this->message->channel_id}"
            : "tenant.{$this->tenantId}.dm.{$this->message->conversation_id}";

        return [new PrivateChannel($channel)];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id'        => $this->message->id,
                'body'      => $this->message->body,
                'is_edited' => (bool) $this->message->is_edited,
                'edited_at' => $this->message->edited_at?->toIso8601String(),
                'metadata'  => $this->message->metadata,
            ],
        ];
    }
}

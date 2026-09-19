<?php

namespace App\Events;

use App\Models\Tenant\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;
    public int $channelId;
    public string $tenantId;

    public function __construct(Message $message, string $tenantId)
    {
        $this->message   = $message->load(['reactions', 'files']);
        $this->channelId = $message->channel_id;
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
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $message = $this->message;

        return [
            'message' => [
                'id'          => $message->id,
                'body'        => $message->body,
                'type'        => $message->type,
                'is_edited'   => $message->is_edited,
                'is_pinned'   => $message->is_pinned,
                'parent_id'   => $message->parent_id,
                'thread_id'   => $message->thread_id,
                'mentions'    => $message->mentions,
                'attachments' => $message->attachments,
                'metadata'    => $message->metadata,
                'created_at'  => $message->created_at?->toIso8601String(),
                'user'        => $message->relationLoaded('user') ? [
                    'id'           => $message->user_id,
                    'name'         => optional($message->getRelation('user'))->name,
                    'display_name' => optional($message->getRelation('user'))->display_name,
                    'avatar_url'   => optional($message->getRelation('user'))->avatar_url,
                ] : ['id' => $message->user_id],
                'reactions'   => $message->grouped_reactions,
                'parent'      => $message->relationLoaded('parent') && $message->parent ? [
                    'id'   => $message->parent->id,
                    'body' => \Illuminate\Support\Str::limit($message->parent->body ?? '', 120),
                    'user' => [
                        'id'           => $message->parent->user?->id,
                        'name'         => $message->parent->user?->name,
                        'display_name' => $message->parent->user?->display_name,
                    ],
                ] : null,
                'files'       => $message->files->map(fn ($file) => [
                    'id'            => $file->id,
                    'original_name' => $file->original_name,
                    'type'          => $file->type,
                    'extension'     => $file->extension,
                    'mime_type'     => $file->mime_type,
                    'size'          => $file->size,
                    'size_human'    => $file->size_human,
                    'view_url'      => $file->view_url,
                    'download_url'  => $file->download_url,
                ])->toArray(),
            ],
            'channel_id' => $this->channelId,
            'tenant_id'  => $this->tenantId,
        ];
    }
}

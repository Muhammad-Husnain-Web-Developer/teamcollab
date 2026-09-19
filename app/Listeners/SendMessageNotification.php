<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Creates notifications for channel messages.
 *
 * Runs synchronously in the request cycle (no queue worker required):
 * mentioned users get a "mention" notification, all other non-muted
 * channel members get a "channel_message" notification.
 */
class SendMessageNotification
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle(MessageSent $event): void
    {
        $message   = $event->message;
        $channelId = $event->channelId;

        if (! $message->relationLoaded('user')) {
            $message->load('user');
        }

        $sender     = $message->getRelation('user');
        $senderName = $sender?->display_name ?? $sender?->name ?? 'Someone';

        $memberIds = ChannelMember::where('channel_id', $channelId)
            ->where('user_id', '!=', $message->user_id)
            ->where('is_muted', false)
            ->pluck('user_id');

        if ($memberIds->isEmpty()) {
            return;
        }

        $mentionedIds = $this->resolveMentionedUserIds($message->body, $message->mentions ?? []);

        $channel = Channel::find($channelId);

        $data = [
            'message_id'    => $message->id,
            'channel_id'    => $channelId,
            'channel_name'  => $channel?->name,
            'sender_id'     => $message->user_id,
            'sender_name'   => $senderName,
            'sender_avatar' => $sender?->avatar_url,
            'preview'       => $this->preview($message->body),
            'tenant_id'     => $event->tenantId,
        ];

        foreach ($memberIds as $userId) {
            $type = in_array((int) $userId, $mentionedIds, true) ? 'mention' : 'channel_message';

            $this->notifications->notify((int) $userId, $type, $data);
        }
    }

    /**
     * Combine explicit mention ids from the message with @username
     * mentions parsed out of the body.
     *
     * @return int[]
     */
    private function resolveMentionedUserIds(?string $body, array $explicitIds): array
    {
        $ids = array_map('intval', $explicitIds);

        if ($body) {
            preg_match_all('/@([a-zA-Z0-9_.\-]+)/', $body, $matches);

            $usernames = array_unique($matches[1] ?? []);

            if ($usernames !== []) {
                $parsedIds = User::whereIn('name', $usernames)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                $ids = array_merge($ids, $parsedIds);
            }
        }

        return array_values(array_unique($ids));
    }

    private function preview(?string $body): string
    {
        $body = $body ?? '';

        return mb_strlen($body) > 200 ? mb_substr($body, 0, 197) . '...' : $body;
    }
}

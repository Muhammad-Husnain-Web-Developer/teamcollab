<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Tenant\TenantNotification;
use App\Models\User;
use App\Notifications\ChatEventPushNotification;

class NotificationService
{
    /**
     * Create a notification for a user and broadcast it in real time.
     *
     * Runs synchronously inside the request (tenancy already initialized),
     * so it works without a queue worker.
     */
    public function notify(int $userId, string $type, array $data): TenantNotification
    {
        $notification = TenantNotification::create([
            'user_id'      => $userId,
            'from_user_id' => $data['sender_id'] ?? null,
            'type'         => $type,
            'title'        => $this->title($type, $data),
            'body'         => $data['preview'] ?? null,
            'link'         => $this->link($type, $data),
            'data'         => $data,
            'is_read'      => false,
        ]);

        try {
            event(new NotificationCreated($userId, [
                'id'         => $notification->id,
                'type'       => $notification->type,
                'data'       => $data,
                'read_at'    => null,
                'created_at' => $notification->created_at->toISOString(),
            ]));
        } catch (\Throwable) {
            // Broadcast failure must never break the request; the notification
            // row is already persisted and will appear on next fetch.
        }

        $this->sendPush($userId, $type, $data);

        return $notification;
    }

    /**
     * Web Push delivery for when the recipient has no tab open at all (the
     * in-app broadcast above only reaches an already-open tab). A dead
     * subscription or webpush misconfiguration must never break notification
     * creation, so this is entirely best-effort.
     */
    private function sendPush(int $userId, string $type, array $data): void
    {
        try {
            $user = User::find($userId);

            if (! $user || $user->pushSubscriptions()->doesntExist()) {
                return;
            }

            $user->notify(new ChatEventPushNotification(
                $this->title($type, $data),
                $data['preview'] ?? null,
                $this->link($type, $data),
            ));
        } catch (\Throwable) {
            // Non-fatal — see docblock above.
        }
    }

    /**
     * Notify several users at once.
     *
     * @param iterable<int> $userIds
     */
    public function notifyMany(iterable $userIds, string $type, array $data): void
    {
        foreach ($userIds as $userId) {
            $this->notify((int) $userId, $type, $data);
        }
    }

    private function title(string $type, array $data): string
    {
        $sender = $data['sender_name'] ?? 'Someone';

        return match ($type) {
            'mention'         => "{$sender} mentioned you in #" . ($data['channel_name'] ?? 'a channel'),
            'channel_message' => "{$sender} posted in #" . ($data['channel_name'] ?? 'a channel'),
            'dm_message'      => "{$sender} sent you a message",
            'missed_call'     => 'Missed ' . (($data['call_type'] ?? 'audio') === 'video' ? 'video' : 'audio') . " call from {$sender}",
            default           => "{$sender} sent a notification",
        };
    }

    private function link(string $type, array $data): ?string
    {
        return match ($type) {
            'mention', 'channel_message' => isset($data['channel_id']) ? "/channels/{$data['channel_id']}" : null,
            'dm_message'                 => isset($data['conversation_id']) ? "/dm/{$data['conversation_id']}" : null,
            'missed_call'                => '/dm',
            default                      => null,
        };
    }
}

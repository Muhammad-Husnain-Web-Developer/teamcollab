<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * The background/tab-closed delivery path — NotificationService already
 * creates the in-app TenantNotification row and broadcasts it for anyone
 * with the tab open; this is what reaches a user when it's not.
 */
class ChatEventPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
    ) {
        $this->onQueue('push-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, self $notification): WebPushMessage
    {
        return (new WebPushMessage())
            ->title($this->title)
            ->body($this->body ?? '')
            ->tag($this->url ?? 'teamcollab')
            ->data(['url' => $this->url])
            ->options(['TTL' => 3600]);
    }
}

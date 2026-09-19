<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Jobs\LogActivityJob;
use App\Models\Tenant\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogMessageActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'activity';

    public function __construct()
    {
    }

    public function handle(MessageSent $event): void
    {
        $message  = $event->message;
        $tenantId = $event->tenantId;

        if (! $message->relationLoaded('user')) {
            $message->load('user');
        }

        $senderName = optional($message->getRelation('user'))->display_name
            ?? optional($message->getRelation('user'))->name
            ?? 'Unknown';

        LogActivityJob::dispatch(
            $message->user_id,
            'message.sent',
            Message::class,
            $message->id,
            "{$senderName} sent a message in channel #{$event->channelId}",
            [
                'channel_id' => $event->channelId,
                'tenant_id'  => $tenantId,
                'message_id' => $message->id,
                'type'       => $message->type,
            ]
        )->onQueue('activity');
    }
}

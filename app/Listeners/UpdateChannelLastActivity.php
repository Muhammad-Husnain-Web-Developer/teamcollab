<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Models\Tenant\Channel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateChannelLastActivity implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'default';

    public function __construct()
    {
    }

    public function handle(MessageSent $event): void
    {
        $message   = $event->message;
        $channelId = $event->channelId;

        Channel::where('id', $channelId)->update([
            'last_message_id'  => $message->id,
            'last_activity_at' => $message->created_at ?? now(),
            'messages_count'   => \Illuminate\Support\Facades\DB::raw('messages_count + 1'),
        ]);
    }
}

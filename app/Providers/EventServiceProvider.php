<?php

namespace App\Providers;

use App\Events\MessageSent;
use App\Events\UserPresenceUpdated;
use App\Events\ChannelCreated;
use App\Events\MemberJoined;
use App\Listeners\SendMessageNotification;
use App\Listeners\LogMessageActivity;
use App\Listeners\UpdateChannelLastActivity;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageSent::class => [
            SendMessageNotification::class,
            LogMessageActivity::class,
            UpdateChannelLastActivity::class,
        ],
        UserPresenceUpdated::class => [],
        ChannelCreated::class      => [],
        MemberJoined::class        => [],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}

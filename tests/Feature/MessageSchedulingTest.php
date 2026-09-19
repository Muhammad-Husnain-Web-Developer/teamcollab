<?php

namespace Tests\Feature;

use App\Jobs\SendScheduledMessageJob;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\ScheduledMessage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

class MessageSchedulingTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    private function channelWith(User ...$users): Channel
    {
        $channel = Channel::create([
            'name'       => 'general-' . uniqid(),
            'slug'       => 'general-' . uniqid(),
            'type'       => 'public',
            'created_by' => $users[0]->id,
        ]);

        foreach ($users as $user) {
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id'    => $user->id,
                'role'       => 'member',
                'joined_at'  => now(),
            ]);
        }

        return $channel;
    }

    public function test_scheduling_a_channel_message_queues_a_delayed_job(): void
    {
        Queue::fake();

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);
        $when    = now()->addMinutes(5);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages/schedule"), [
            'body'          => 'reminder: standup',
            'scheduled_for' => $when->toIso8601String(),
        ]);

        $response->assertCreated()->assertJsonPath('scheduled_message.status', 'pending');

        $scheduled = ScheduledMessage::first();
        $this->assertNotNull($scheduled);
        $this->assertSame('pending', $scheduled->status);
        $this->assertSame('reminder: standup', $scheduled->body);
        $this->assertSame(0, Message::count());

        Queue::assertPushed(SendScheduledMessageJob::class, function ($job) use ($scheduled) {
            return $job->scheduledMessageId === $scheduled->id;
        });
    }

    public function test_running_the_job_sends_the_message_and_marks_it_sent(): void
    {
        Queue::fake();

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages/schedule"), [
            'body'          => 'due now',
            'scheduled_for' => now()->addMinute()->toIso8601String(),
        ])->assertCreated();

        $scheduled = ScheduledMessage::first();

        (new SendScheduledMessageJob($scheduled->id, $this->tenant->id))->handle(app(\App\Services\MessageService::class));

        $scheduled->refresh();
        $this->assertSame('sent', $scheduled->status);
        $this->assertNotNull($scheduled->sent_message_id);

        $message = Message::find($scheduled->sent_message_id);
        $this->assertNotNull($message);
        $this->assertSame('due now', $message->body);
        $this->assertSame($channel->id, $message->channel_id);
    }

    public function test_cancelling_before_it_fires_prevents_the_send(): void
    {
        Queue::fake();

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages/schedule"), [
            'body'          => 'never sent',
            'scheduled_for' => now()->addMinutes(10)->toIso8601String(),
        ])->assertCreated();

        $scheduled = ScheduledMessage::first();

        $this->actingAs($user)
            ->deleteJson($this->tenantUrl("/scheduled-messages/{$scheduled->id}"))
            ->assertOk()
            ->assertJsonPath('cancelled', true);

        $this->assertSame('cancelled', $scheduled->fresh()->status);

        // The job may still be sitting on the queue — its own status guard
        // must be what actually stops the send, not queue-level removal.
        (new SendScheduledMessageJob($scheduled->id, $this->tenant->id))->handle(app(\App\Services\MessageService::class));

        $this->assertSame(0, Message::count());
        $this->assertSame('cancelled', $scheduled->fresh()->status);
    }

    public function test_a_past_scheduled_time_is_rejected(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages/schedule"), [
            'body'          => 'too late',
            'scheduled_for' => now()->subMinute()->toIso8601String(),
        ])->assertUnprocessable();

        $this->assertSame(0, ScheduledMessage::count());
    }

    public function test_another_user_cannot_cancel_someone_elses_scheduled_message(): void
    {
        Queue::fake();

        $owner   = $this->tenantUser();
        $other   = $this->tenantUser();
        $channel = $this->channelWith($owner, $other);

        $this->actingAs($owner)->postJson($this->tenantUrl("/channels/{$channel->id}/messages/schedule"), [
            'body'          => 'mine',
            'scheduled_for' => now()->addMinutes(5)->toIso8601String(),
        ])->assertCreated();

        $scheduled = ScheduledMessage::first();

        $this->actingAs($other)
            ->deleteJson($this->tenantUrl("/scheduled-messages/{$scheduled->id}"))
            ->assertForbidden();

        $this->assertSame('pending', $scheduled->fresh()->status);
    }

    public function test_scheduling_a_dm_message_works(): void
    {
        Queue::fake();

        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $response = $this->actingAs($a)->postJson($this->tenantUrl("/dm/{$conversation->id}/messages/schedule"), [
            'body'          => 'dm later',
            'scheduled_for' => now()->addMinutes(5)->toIso8601String(),
        ]);

        $response->assertCreated();

        $scheduled = ScheduledMessage::first();
        $this->assertSame($conversation->id, $scheduled->conversation_id);

        (new SendScheduledMessageJob($scheduled->id, $this->tenant->id))->handle(app(\App\Services\MessageService::class));

        $scheduled->refresh();
        $this->assertSame('sent', $scheduled->status);

        $message = Message::find($scheduled->sent_message_id);
        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertSame('dm later', $message->body);
    }
}

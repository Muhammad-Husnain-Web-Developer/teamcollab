<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\User;
use App\Services\MessageService;
use App\Services\UnreadService;
use Tests\TenantTestCase;

class UnreadTest extends TenantTestCase
{
    private UnreadService $unread;
    private MessageService $messages;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unread   = app(UnreadService::class);
        $this->messages = app(MessageService::class);
    }

    private function channelWith(User ...$users): Channel
    {
        $channel = Channel::create([
            'name'       => 'general',
            'slug'       => 'general',
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

    private function sendTo(Channel $channel, User $user, string $body): void
    {
        $this->messages->send(['channel_id' => $channel->id, 'body' => $body], $user);
    }

    public function test_counts_messages_from_others_only(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        $this->sendTo($channel, $sender, 'one');
        $this->sendTo($channel, $sender, 'two');
        $this->sendTo($channel, $reader, 'mine, should not count');

        $this->assertSame(2, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
        $this->assertSame(1, $this->unread->channelCounts($sender)[$channel->id] ?? 0);
    }

    public function test_marking_read_clears_the_count(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        $this->sendTo($channel, $sender, 'unread');
        $this->unread->markChannelRead($channel->id, $reader);

        $this->assertSame(0, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
    }

    public function test_message_sent_in_the_same_second_as_the_read_marker_still_counts(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        // Regression: read position used to be a whole-second timestamp, so a
        // message posted in the same second tied the comparison and vanished.
        $this->unread->markChannelRead($channel->id, $reader);
        $this->sendTo($channel, $sender, 'same second');

        $this->assertSame(1, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
    }

    public function test_deleted_messages_do_not_count(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        $message = $this->messages->send(['channel_id' => $channel->id, 'body' => 'oops'], $sender);
        $message->delete();

        $this->assertSame(0, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
    }

    public function test_never_opened_channel_counts_every_message(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        $this->sendTo($channel, $sender, 'a');
        $this->sendTo($channel, $sender, 'b');
        $this->sendTo($channel, $sender, 'c');

        // last_read_message_id is null — nothing has been read yet.
        $this->assertSame(3, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
    }

    public function test_direct_message_unread_counts_and_clears(): void
    {
        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $conversation->messages()->create(['user_id' => $a->id, 'body' => 'hello']);

        $this->assertSame(1, $this->unread->conversationCounts($b)[$conversation->id] ?? 0);
        $this->assertSame(0, $this->unread->conversationCounts($a)[$conversation->id] ?? 0);

        $this->unread->markConversationRead($conversation->id, $b);
        $this->assertSame(0, $this->unread->conversationCounts($b)[$conversation->id] ?? 0);
    }

    public function test_read_endpoint_persists_the_marker(): void
    {
        $reader  = $this->tenantUser();
        $sender  = $this->tenantUser();
        $channel = $this->channelWith($reader, $sender);

        $this->sendTo($channel, $sender, 'unread');

        $this->actingAs($reader)
            ->postJson('http://' . $this->tenant->id . ".localhost/channels/{$channel->id}/read")
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertSame(0, $this->unread->channelCounts($reader)[$channel->id] ?? 0);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\MessageThread;
use App\Models\User;
use Tests\TenantTestCase;

class ThreadTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
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

    private function postMessage(Channel $channel, User $user, string $body, ?int $parentId = null): Message
    {
        return app(\App\Services\MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => $body,
            'parent_id'  => $parentId,
        ], $user);
    }

    public function test_thread_returns_root_and_replies_oldest_first(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $root = $this->postMessage($channel, $user, 'root message');
        $this->postMessage($channel, $user, 'first reply', $root->id);
        $this->postMessage($channel, $user, 'second reply', $root->id);

        $response = $this->actingAs($user)
            ->getJson($this->tenantUrl("/messages/{$root->id}/thread"));

        $response->assertOk()
            ->assertJsonPath('root.id', $root->id)
            ->assertJsonPath('replies_count', 2)
            ->assertJsonPath('replies.0.body', 'first reply')
            ->assertJsonPath('replies.1.body', 'second reply');
    }

    public function test_opening_a_reply_returns_the_whole_thread(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $root  = $this->postMessage($channel, $user, 'root message');
        $reply = $this->postMessage($channel, $user, 'a reply', $root->id);

        // Clicking a reply should open the thread it belongs to, not an empty one.
        $this->actingAs($user)
            ->getJson($this->tenantUrl("/messages/{$reply->id}/thread"))
            ->assertOk()
            ->assertJsonPath('root.id', $root->id)
            ->assertJsonPath('replies_count', 1);
    }

    public function test_posting_a_reply_links_it_and_updates_counters(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);
        $root    = $this->postMessage($channel, $user, 'root message');

        $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$root->id}/thread"), ['body' => 'replied via panel'])
            ->assertCreated()
            ->assertJsonPath('message.parent_id', $root->id);

        $this->assertSame(1, Message::find($root->id)->replies_count);

        $summary = MessageThread::where('parent_message_id', $root->id)->first();
        $this->assertNotNull($summary, 'thread summary row should be created');
        $this->assertSame(1, $summary->replies_count);
        $this->assertSame([$user->id], $summary->participant_ids);
    }

    public function test_threads_stay_one_level_deep(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $root  = $this->postMessage($channel, $user, 'root message');
        $reply = $this->postMessage($channel, $user, 'a reply', $root->id);

        // Replying to a reply must attach to the root, not nest further.
        $response = $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$reply->id}/thread"), ['body' => 'reply to reply']);

        $response->assertCreated()->assertJsonPath('message.parent_id', $root->id);
    }

    public function test_thread_summary_tracks_multiple_participants(): void
    {
        $author  = $this->tenantUser();
        $other   = $this->tenantUser();
        $channel = $this->channelWith($author, $other);

        $root = $this->postMessage($channel, $author, 'root message');
        $this->postMessage($channel, $author, 'r1', $root->id);
        $this->postMessage($channel, $other, 'r2', $root->id);
        $this->postMessage($channel, $other, 'r3', $root->id);

        $summary = MessageThread::where('parent_message_id', $root->id)->first();

        $this->assertSame(3, $summary->replies_count);
        $this->assertEqualsCanonicalizing([$author->id, $other->id], $summary->participant_ids);
    }

    public function test_non_member_cannot_read_a_private_channel_thread(): void
    {
        $owner    = $this->tenantUser();
        $outsider = $this->tenantUser();

        $channel = Channel::create([
            'name'       => 'secret',
            'slug'       => 'secret',
            'type'       => 'private',
            'created_by' => $owner->id,
        ]);
        ChannelMember::create([
            'channel_id' => $channel->id,
            'user_id'    => $owner->id,
            'role'       => 'admin',
            'joined_at'  => now(),
        ]);

        $root = $this->postMessage($channel, $owner, 'private root');

        $this->actingAs($outsider)
            ->getJson($this->tenantUrl("/messages/{$root->id}/thread"))
            ->assertForbidden();
    }

    public function test_direct_message_threads_work(): void
    {
        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $root = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'dm root']);

        // DM messages have a null channel_id — this used to break the thread
        // summary insert, which required channel_id to be set.
        $this->actingAs($b)
            ->postJson($this->tenantUrl("/messages/{$root->id}/thread"), ['body' => 'dm reply'])
            ->assertCreated()
            ->assertJsonPath('message.parent_id', $root->id);

        $this->assertSame(1, MessageThread::where('parent_message_id', $root->id)->first()->replies_count);
    }

    public function test_outsider_cannot_read_a_direct_message_thread(): void
    {
        $a        = $this->tenantUser();
        $b        = $this->tenantUser();
        $outsider = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $root = $conversation->messages()->create(['user_id' => $a->id, 'body' => 'private dm']);

        $this->actingAs($outsider)
            ->getJson($this->tenantUrl("/messages/{$root->id}/thread"))
            ->assertForbidden();
    }
}

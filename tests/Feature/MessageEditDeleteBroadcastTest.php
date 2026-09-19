<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageUpdated;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Event;
use Tests\TenantTestCase;

/**
 * ChatArea.vue and DirectMessage/Show.vue listen for `.message.updated` and
 * `.message.deleted`; these tests pin down that the backend actually emits
 * them (it did not, so edits and deletes never reached other clients).
 */
class MessageEditDeleteBroadcastTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    /** Only fake these two — a bare Event::fake() swallows Stancl's TenantCreated. */
    private function fakeMessageEvents(): void
    {
        Event::fake([MessageUpdated::class, MessageDeleted::class]);
    }

    private function channelWith(User ...$users): Channel
    {
        $channel = Channel::create([
            'name' => 'general', 'slug' => 'general', 'type' => 'public', 'created_by' => $users[0]->id,
        ]);
        foreach ($users as $user) {
            ChannelMember::create([
                'channel_id' => $channel->id, 'user_id' => $user->id, 'role' => 'member', 'joined_at' => now(),
            ]);
        }

        return $channel;
    }

    private function channelMessage(Channel $channel, User $user, string $body, ?int $parentId = null): Message
    {
        return app(MessageService::class)->send([
            'channel_id' => $channel->id, 'body' => $body, 'parent_id' => $parentId,
        ], $user);
    }

    public function test_editing_a_channel_message_broadcasts_the_new_body_to_the_channel(): void
    {
        $this->fakeMessageEvents();

        $author  = $this->tenantUser();
        $channel = $this->channelWith($author, $this->tenantUser());
        $message = $this->channelMessage($channel, $author, 'typo hear');

        $this->actingAs($author)
            ->putJson($this->tenantUrl("/messages/{$message->id}"), ['body' => 'typo here'])
            ->assertOk()
            ->assertJsonPath('message.body', 'typo here')
            ->assertJsonPath('message.is_edited', true);

        $tenantId = $this->tenant->id;
        Event::assertDispatched(MessageUpdated::class, function (MessageUpdated $e) use ($message, $channel, $tenantId) {
            $payload  = $e->broadcastWith()['message'];
            $channels = array_map(fn ($c) => (string) $c, $e->broadcastOn());

            return $e->broadcastAs() === 'message.updated'
                && $payload['id'] === $message->id
                && $payload['body'] === 'typo here'
                && $payload['is_edited'] === true
                && $channels === ["private-tenant.{$tenantId}.channel.{$channel->id}"];
        });
    }

    public function test_deleting_a_channel_message_broadcasts_its_id_and_thread_parent(): void
    {
        $this->fakeMessageEvents();

        $author  = $this->tenantUser();
        $channel = $this->channelWith($author);
        $root    = $this->channelMessage($channel, $author, 'root');
        $reply   = $this->channelMessage($channel, $author, 'reply', $root->id);

        $this->actingAs($author)
            ->deleteJson($this->tenantUrl("/messages/{$reply->id}"))
            ->assertOk();

        $this->assertSoftDeleted('messages', ['id' => $reply->id]);
        $this->assertSame(0, $root->fresh()->replies_count);

        $tenantId = $this->tenant->id;
        Event::assertDispatched(MessageDeleted::class, function (MessageDeleted $e) use ($reply, $root, $channel, $tenantId) {
            $channels = array_map(fn ($c) => (string) $c, $e->broadcastOn());

            return $e->broadcastAs() === 'message.deleted'
                && $e->broadcastWith() === ['messageId' => $reply->id, 'parent_id' => $root->id]
                && $channels === ["private-tenant.{$tenantId}.channel.{$channel->id}"];
        });
    }

    public function test_dm_edits_and_deletes_go_to_the_conversation_channel(): void
    {
        $this->fakeMessageEvents();

        [$a, $b] = [$this->tenantUser(), $this->tenantUser()];
        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $message = app(MessageService::class)->sendToConversation([
            'conversation_id' => $conversation->id, 'body' => 'hi',
        ], $a);

        $this->actingAs($a)->putJson($this->tenantUrl("/messages/{$message->id}"), ['body' => 'hi!'])->assertOk();
        $this->actingAs($a)->deleteJson($this->tenantUrl("/messages/{$message->id}"))->assertOk();

        $expected = ["private-tenant.{$this->tenant->id}.dm.{$conversation->id}"];
        Event::assertDispatched(MessageUpdated::class, fn (MessageUpdated $e) =>
            array_map(fn ($c) => (string) $c, $e->broadcastOn()) === $expected);
        Event::assertDispatched(MessageDeleted::class, fn (MessageDeleted $e) =>
            array_map(fn ($c) => (string) $c, $e->broadcastOn()) === $expected);
    }

    public function test_someone_else_cannot_edit_your_message_and_nothing_is_broadcast(): void
    {
        $this->fakeMessageEvents();

        $author   = $this->tenantUser();
        $intruder = $this->tenantUser();
        $channel  = $this->channelWith($author, $intruder);
        $message  = $this->channelMessage($channel, $author, 'mine');

        $this->actingAs($intruder)
            ->putJson($this->tenantUrl("/messages/{$message->id}"), ['body' => 'hijacked'])
            ->assertForbidden();

        $this->assertSame('mine', $message->fresh()->body);
        Event::assertNotDispatched(MessageUpdated::class);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\Tenant\ScheduledMessage;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

class SlashCommandTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    private function channelWith(array $roles, User ...$users): Channel
    {
        $channel = Channel::create([
            'name'       => 'general-' . uniqid(),
            'slug'       => 'general-' . uniqid(),
            'type'       => 'public',
            'created_by' => $users[0]->id,
        ]);

        foreach ($users as $i => $user) {
            ChannelMember::create([
                'channel_id' => $channel->id,
                'user_id'    => $user->id,
                'role'       => $roles[$i] ?? 'member',
                'joined_at'  => now(),
            ]);
        }

        return $channel;
    }

    public function test_mute_command_toggles_channel_mute_and_creates_no_message(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith(['admin'], $user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/mute',
        ])->assertOk()
            ->assertJsonPath('handled', true)
            ->assertJsonPath('is_error', false)
            ->assertJsonPath('message', "Muted #{$channel->name}.");

        $this->assertTrue(
            ChannelMember::where('channel_id', $channel->id)->where('user_id', $user->id)->first()->is_muted
        );
        $this->assertSame(0, Message::count());

        // Toggling again unmutes.
        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/mute',
        ])->assertJsonPath('message', "Unmuted #{$channel->name}.");

        $this->assertFalse(
            ChannelMember::where('channel_id', $channel->id)->where('user_id', $user->id)->first()->is_muted
        );
    }

    public function test_invite_command_adds_the_named_member_when_caller_is_admin(): void
    {
        $admin  = $this->tenantUser('admin', ['name' => 'Ada Admin']);
        $target = $this->tenantUser('member', ['name' => 'Charlie Newbie']);
        $channel = $this->channelWith(['admin'], $admin);

        $response = $this->actingAs($admin)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/invite @Charlie Newbie',
        ]);

        $response->assertOk()
            ->assertJsonPath('handled', true)
            ->assertJsonPath('is_error', false);

        $this->assertTrue(
            ChannelMember::where('channel_id', $channel->id)->where('user_id', $target->id)->exists()
        );
        $this->assertSame(0, Message::count());
    }

    public function test_invite_command_is_rejected_for_a_non_admin_caller(): void
    {
        $owner  = $this->tenantUser('admin', ['name' => 'Owner Person']);
        $member = $this->tenantUser('member', ['name' => 'Regular Member']);
        $target = $this->tenantUser('member', ['name' => 'Target Person']);
        $channel = $this->channelWith(['admin', 'member'], $owner, $member);

        $response = $this->actingAs($member)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/invite @Target Person',
        ]);

        $response->assertOk()->assertJsonPath('is_error', true);

        $this->assertFalse(
            ChannelMember::where('channel_id', $channel->id)->where('user_id', $target->id)->exists()
        );
    }

    public function test_remind_command_creates_a_scheduled_message_and_no_immediate_message(): void
    {
        Queue::fake();

        $user    = $this->tenantUser();
        $channel = $this->channelWith(['admin'], $user);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/remind me "standup" in 1m',
        ]);

        $response->assertOk()->assertJsonPath('is_error', false);

        $scheduled = ScheduledMessage::first();
        $this->assertNotNull($scheduled);
        $this->assertSame($channel->id, $scheduled->channel_id);
        $this->assertTrue($scheduled->metadata['is_reminder']);
        $this->assertStringContainsString('standup', $scheduled->body);

        // ~1 minute out (allow a couple seconds of test-runtime slack).
        $this->assertTrue($scheduled->scheduled_for->diffInSeconds(now()->addMinute()) < 5);

        $this->assertSame(0, Message::count());
    }

    public function test_an_unknown_slash_looking_message_is_posted_as_literal_text(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith(['admin'], $user);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '/notarealcommand this stays literal',
        ]);

        $response->assertCreated();
        $this->assertArrayNotHasKey('handled', $response->json());

        $message = Message::first();
        $this->assertNotNull($message);
        $this->assertSame('/notarealcommand this stays literal', $message->body);
    }

    public function test_mute_command_is_rejected_in_a_direct_message(): void
    {
        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $response = $this->actingAs($a)->postJson($this->tenantUrl("/dm/{$conversation->id}/messages"), [
            'body' => '/mute',
        ]);

        $response->assertOk()->assertJsonPath('is_error', true);
        $this->assertSame(0, Message::count());
    }
}

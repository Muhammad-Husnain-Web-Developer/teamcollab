<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\File;
use App\Models\Tenant\Message;
use App\Models\User;
use Tests\TenantTestCase;

class MessageForwardTest extends TenantTestCase
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

    private function privateChannelWith(User ...$users): Channel
    {
        $channel = Channel::create([
            'name'       => 'secret-' . uniqid(),
            'slug'       => 'secret-' . uniqid(),
            'type'       => 'private',
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

    public function test_forwarding_a_message_with_an_attachment_creates_a_tagged_copy_and_preserves_original_ownership(): void
    {
        $user   = $this->tenantUser();
        $source = $this->channelWith($user);
        $target = $this->channelWith($user);

        $original = app(\App\Services\MessageService::class)->send([
            'channel_id' => $source->id,
            'body'       => 'the original body',
        ], $user);

        $file = File::create([
            'uploaded_by'   => $user->id,
            'message_id'    => $original->id,
            'channel_id'    => $source->id,
            'original_name' => 'notes.txt',
            'stored_name'   => 'notes.txt',
            'disk'          => 'local',
            'path'          => 'tenants/test/files/notes.txt',
            'mime_type'     => 'text/plain',
            'extension'     => 'txt',
            'size'          => 10,
            'type'          => 'document',
        ]);

        $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$original->id}/forward"), [
                'channel_ids' => [$target->id],
                'comment'     => 'check this out',
            ])
            ->assertOk()
            ->assertJsonPath('forwarded_to_channels', 1);

        $forwarded = Message::where('channel_id', $target->id)
            ->where('id', '!=', $original->id)
            ->latest()
            ->first();

        $this->assertNotNull($forwarded);
        $this->assertSame('check this out', $forwarded->body);
        $this->assertSame($original->id, $forwarded->metadata['forwarded_from']['message_id']);
        $this->assertSame('the original body', $forwarded->metadata['forwarded_from']['body']);
        $this->assertCount(1, $forwarded->metadata['forwarded_from']['files']);

        // The original file must still belong to the source message.
        $this->assertSame($original->id, $file->fresh()->message_id);
    }

    public function test_forwarding_to_a_channel_the_user_cannot_view_is_forbidden(): void
    {
        $author   = $this->tenantUser();
        $outsider = $this->tenantUser();
        $source   = $this->channelWith($author, $outsider);
        $private  = $this->privateChannelWith($author); // outsider is NOT a member

        $original = app(\App\Services\MessageService::class)->send([
            'channel_id' => $source->id,
            'body'       => 'hello',
        ], $author);

        $this->actingAs($outsider)
            ->postJson($this->tenantUrl("/messages/{$original->id}/forward"), [
                'channel_ids' => [$private->id],
            ])
            ->assertForbidden();

        $this->assertSame(0, Message::where('channel_id', $private->id)->count());
    }

    public function test_forwarding_to_a_dm_target_creates_or_reuses_the_conversation(): void
    {
        $user   = $this->tenantUser();
        $other  = $this->tenantUser();
        $source = $this->channelWith($user, $other);

        $original = app(\App\Services\MessageService::class)->send([
            'channel_id' => $source->id,
            'body'       => 'dm me this',
        ], $user);

        $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$original->id}/forward"), [
                'user_ids' => [$other->id],
            ])
            ->assertOk()
            ->assertJsonPath('forwarded_to_conversations', 1);

        $this->assertSame(
            1,
            Message::whereNotNull('conversation_id')
                ->whereJsonContains('metadata->forwarded_from->message_id', $original->id)
                ->count(),
        );
    }

    public function test_forward_request_requires_at_least_one_target(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $original = app(\App\Services\MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => 'hello',
        ], $user);

        $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$original->id}/forward"), [])
            ->assertUnprocessable();
    }
}

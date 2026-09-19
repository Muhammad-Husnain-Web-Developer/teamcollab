<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Conversation;
use App\Models\Tenant\Message;
use App\Models\User;
use Tests\TenantTestCase;

class GifMessageTest extends TenantTestCase
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

    public function test_sending_a_gif_message_sets_type_and_metadata(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body'            => '',
            'type'            => 'gif',
            'gif_url'         => 'https://media.giphy.com/media/abc123/giphy.gif',
            'gif_preview_url' => 'https://media.giphy.com/media/abc123/200w.gif',
            'gif_provider'    => 'giphy',
            'gif_width'       => 480,
            'gif_height'      => 270,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.type', 'gif')
            ->assertJsonPath('message.metadata.gif_url', 'https://media.giphy.com/media/abc123/giphy.gif')
            ->assertJsonPath('message.metadata.gif_provider', 'giphy')
            ->assertJsonPath('message.metadata.gif_width', 480);

        $message = Message::find($response->json('message.id'));
        $this->assertSame('gif', $message->type);
        $this->assertSame('https://media.giphy.com/media/abc123/giphy.gif', $message->metadata['gif_url']);
    }

    public function test_a_gif_message_without_a_gif_url_is_rejected(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '',
            'type' => 'gif',
        ])->assertUnprocessable();

        $this->assertSame(0, Message::count());
    }

    public function test_a_plain_text_message_still_requires_a_body_or_files(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => '',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => 'hello',
        ])->assertCreated();
    }

    public function test_gif_messages_work_in_direct_messages_too(): void
    {
        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $response = $this->actingAs($a)->postJson($this->tenantUrl("/dm/{$conversation->id}/messages"), [
            'body'         => '',
            'type'         => 'gif',
            'gif_url'      => 'https://media.giphy.com/media/xyz789/giphy.gif',
            'gif_provider' => 'giphy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.type', 'gif')
            ->assertJsonPath('message.metadata.gif_url', 'https://media.giphy.com/media/xyz789/giphy.gif');
    }

    public function test_a_workspace_sticker_message_round_trips(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body'         => '',
            'type'         => 'gif',
            'gif_url'      => 'https://example.test/emojis/1/image',
            'gif_provider' => 'workspace',
        ]);

        $response->assertCreated()->assertJsonPath('message.metadata.gif_provider', 'workspace');
    }

    /**
     * WorkspaceEmoji::getImageUrlAttribute() deliberately returns a relative
     * path ("/emojis/{id}/image"), matching File::getViewUrlAttribute()'s
     * convention — a sticker message's gif_url is therefore relative, not an
     * absolute URL, and must not be rejected as an invalid URL.
     */
    public function test_a_sticker_message_with_a_relative_image_path_is_accepted(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body'            => '',
            'type'            => 'gif',
            'gif_url'         => '/emojis/1/image',
            'gif_preview_url' => '/emojis/1/image',
            'gif_provider'    => 'workspace',
        ]);

        $response->assertCreated()->assertJsonPath('message.metadata.gif_url', '/emojis/1/image');
    }
}

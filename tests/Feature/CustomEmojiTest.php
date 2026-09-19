<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\MessageReaction;
use App\Models\Tenant\WorkspaceEmoji;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

class CustomEmojiTest extends TenantTestCase
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

    public function test_admin_can_upload_a_custom_emoji_and_a_thumbnail_is_generated(): void
    {
        Storage::fake('local');

        $admin = $this->tenantUser('admin');
        $image = UploadedFile::fake()->image('party_parrot.png', 100, 100);

        $response = $this->actingAs($admin)->postJson($this->tenantUrl('/emojis'), [
            'shortcode' => 'party_parrot',
            'file'      => $image,
        ]);

        $response->assertCreated()->assertJsonPath('emoji.shortcode', 'party_parrot');

        $emoji = WorkspaceEmoji::where('shortcode', 'party_parrot')->first();
        $this->assertNotNull($emoji);
        $this->assertTrue($emoji->is_active);
        Storage::disk('local')->assertExists($emoji->file_path);
        Storage::disk('local')->assertExists($emoji->thumb_path);
    }

    public function test_non_admin_cannot_upload_a_custom_emoji(): void
    {
        Storage::fake('local');

        $member = $this->tenantUser('member');
        $image  = UploadedFile::fake()->image('emoji.png', 100, 100);

        $this->actingAs($member)->postJson($this->tenantUrl('/emojis'), [
            'shortcode' => 'nope',
            'file'      => $image,
        ])->assertForbidden();

        $this->assertSame(0, WorkspaceEmoji::count());
    }

    public function test_duplicate_shortcode_is_rejected(): void
    {
        Storage::fake('local');

        $admin = $this->tenantUser('admin');

        $this->actingAs($admin)->postJson($this->tenantUrl('/emojis'), [
            'shortcode' => 'dupe',
            'file'      => UploadedFile::fake()->image('a.png', 100, 100),
        ])->assertCreated();

        $this->actingAs($admin)->postJson($this->tenantUrl('/emojis'), [
            'shortcode' => 'dupe',
            'file'      => UploadedFile::fake()->image('b.png', 100, 100),
        ])->assertUnprocessable();

        $this->assertSame(1, WorkspaceEmoji::where('shortcode', 'dupe')->count());
    }

    public function test_non_admin_cannot_delete_a_custom_emoji(): void
    {
        Storage::fake('local');

        $emoji = WorkspaceEmoji::create([
            'shortcode'   => 'keepme',
            'kind'        => 'emoji',
            'uploaded_by' => $this->tenantUser('admin')->id,
            'file_path'   => 'tenants/test/emojis/keepme.png',
            'mime_type'   => 'image/png',
            'is_active'   => true,
        ]);

        $member = $this->tenantUser('member');

        $this->actingAs($member)
            ->deleteJson($this->tenantUrl("/emojis/{$emoji->id}"))
            ->assertForbidden();

        $this->assertTrue($emoji->fresh()->is_active);
    }

    public function test_reacting_with_a_custom_emoji_shortcode_round_trips(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        WorkspaceEmoji::create([
            'shortcode'   => 'party_parrot',
            'kind'        => 'emoji',
            'uploaded_by' => $user->id,
            'file_path'   => 'tenants/test/emojis/party_parrot.png',
            'mime_type'   => 'image/png',
            'is_active'   => true,
        ]);

        $message = app(MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => 'hello',
        ], $user);

        $this->actingAs($user)
            ->postJson($this->tenantUrl("/messages/{$message->id}/react"), ['emoji' => ':party_parrot:'])
            ->assertOk()
            ->assertJsonPath('action', 'added');

        $reaction = MessageReaction::where('message_id', $message->id)->first();
        $this->assertSame(':party_parrot:', $reaction->emoji);
    }
}

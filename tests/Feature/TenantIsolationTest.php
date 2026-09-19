<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\Message;
use App\Services\MessageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * The whole product rests on one workspace never seeing another's data.
 * These are the tests that would catch a leak.
 */
class TenantIsolationTest extends TenantTestCase
{
    public function test_channels_do_not_leak_between_tenants(): void
    {
        $user = $this->tenantUser();

        Channel::create([
            'name' => 'tenant-a-only', 'slug' => 'tenant-a-only',
            'type' => 'public', 'created_by' => $user->id,
        ]);

        $this->assertSame(1, Channel::count());

        // Switch into a second, unrelated workspace.
        $otherId = 'test' . Str::lower(Str::random(8));
        $other   = Tenant::create([
            'id' => $otherId, 'name' => 'Other', 'slug' => $otherId, 'is_active' => true,
        ]);
        $other->domains()->create(['domain' => $otherId . '.localhost']);

        tenancy()->end();
        tenancy()->initialize($other);

        try {
            $this->assertSame(0, Channel::count(), 'a channel leaked across tenants');
        } finally {
            tenancy()->end();
            try {
                DB::purge('tenant');
                $other->database()->manager()->deleteDatabase($other);
            } catch (\Throwable) {
                // best effort cleanup
            }
            tenancy()->initialize($this->tenant);
        }
    }

    public function test_a_user_from_another_workspace_is_refused(): void
    {
        $insider = $this->tenantUser();

        // A real user, but never attached to this tenant via tenant_users.
        $outsider = \App\Models\User::create([
            'name'              => 'Outsider',
            'email'             => Str::lower(Str::random(10)) . '@example.test',
            'password'          => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $channel = Channel::create([
            'name' => 'general', 'slug' => 'general',
            'type' => 'public', 'created_by' => $insider->id,
        ]);

        $this->actingAs($outsider)
            ->getJson('http://' . $this->tenant->id . ".localhost/channels/{$channel->id}/messages")
            ->assertForbidden();
    }

    public function test_private_channel_messages_are_hidden_from_non_members(): void
    {
        $owner    = $this->tenantUser();
        $outsider = $this->tenantUser();

        $channel = Channel::create([
            'name' => 'leadership', 'slug' => 'leadership',
            'type' => 'private', 'created_by' => $owner->id,
        ]);
        ChannelMember::create([
            'channel_id' => $channel->id, 'user_id' => $owner->id,
            'role' => 'admin', 'joined_at' => now(),
        ]);

        app(MessageService::class)->send(
            ['channel_id' => $channel->id, 'body' => 'confidential'],
            $owner
        );

        $this->actingAs($outsider)
            ->getJson('http://' . $this->tenant->id . ".localhost/channels/{$channel->id}/messages")
            ->assertForbidden();
    }

    public function test_a_user_cannot_edit_someone_elses_message(): void
    {
        $author  = $this->tenantUser();
        $other   = $this->tenantUser();

        $channel = Channel::create([
            'name' => 'general', 'slug' => 'general',
            'type' => 'public', 'created_by' => $author->id,
        ]);
        foreach ([$author, $other] as $user) {
            ChannelMember::create([
                'channel_id' => $channel->id, 'user_id' => $user->id,
                'role' => 'member', 'joined_at' => now(),
            ]);
        }

        $message = app(MessageService::class)->send(
            ['channel_id' => $channel->id, 'body' => 'original'],
            $author
        );

        $this->actingAs($other)
            ->putJson('http://' . $this->tenant->id . ".localhost/messages/{$message->id}", ['body' => 'hijacked'])
            ->assertForbidden();

        $this->assertSame('original', Message::find($message->id)->body);
    }

    public function test_a_user_cannot_delete_someone_elses_message(): void
    {
        $author = $this->tenantUser();
        $other  = $this->tenantUser();

        $channel = Channel::create([
            'name' => 'general', 'slug' => 'general',
            'type' => 'public', 'created_by' => $author->id,
        ]);
        foreach ([$author, $other] as $user) {
            ChannelMember::create([
                'channel_id' => $channel->id, 'user_id' => $user->id,
                'role' => 'member', 'joined_at' => now(),
            ]);
        }

        $message = app(MessageService::class)->send(
            ['channel_id' => $channel->id, 'body' => 'keep me'],
            $author
        );

        $this->actingAs($other)
            ->deleteJson('http://' . $this->tenant->id . ".localhost/messages/{$message->id}")
            ->assertForbidden();

        $this->assertNotNull(Message::find($message->id));
    }
}

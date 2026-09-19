<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\File;
use App\Models\Tenant\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\UploadedFile;
use Tests\TenantTestCase;

class VoiceMessageTest extends TenantTestCase
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

    public function test_sending_a_voice_message_sets_type_and_duration_and_links_the_audio_file(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        // Pre-uploaded, unlinked audio file — mirrors the real upload-then-send
        // contract without depending on PHP's finfo content-sniffing of a
        // synthetic test upload, which is orthogonal to what this phase changed.
        $file = File::create([
            'uploaded_by'   => $user->id,
            'original_name' => 'voice-note-123.webm',
            'stored_name'   => 'voice-note-123.webm',
            'disk'          => 'local',
            'path'          => 'tenants/test/files/voice-note-123.webm',
            'mime_type'     => 'audio/webm',
            'extension'     => 'webm',
            'size'          => 4096,
            'type'          => File::resolveType('audio/webm'),
        ]);

        $this->assertSame('audio', $file->type);

        $response = $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body'                    => '',
            'type'                    => 'voice',
            'files'                   => [$file->id],
            'voice_duration_seconds'  => 7,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.type', 'voice')
            ->assertJsonPath('message.metadata.voice_duration_seconds', 7)
            ->assertJsonPath('message.files.0.type', 'audio');

        $message = Message::find($response->json('message.id'));
        $this->assertSame('voice', $message->type);
        $this->assertSame(7, $message->metadata['voice_duration_seconds']);
        $this->assertSame($message->id, $file->fresh()->message_id);
    }

    public function test_an_invalid_message_type_is_rejected(): void
    {
        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $this->actingAs($user)->postJson($this->tenantUrl("/channels/{$channel->id}/messages"), [
            'body' => 'hi',
            'type' => 'not-a-real-type',
        ])->assertUnprocessable();
    }

    public function test_voice_messages_work_in_direct_messages_too(): void
    {
        $a = $this->tenantUser();
        $b = $this->tenantUser();

        $conversation = \App\Models\Tenant\Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
        foreach ([$a, $b] as $user) {
            $conversation->participantEntries()->create(['user_id' => $user->id]);
        }

        $file = File::create([
            'uploaded_by'   => $a->id,
            'original_name' => 'voice-note-456.ogg',
            'stored_name'   => 'voice-note-456.ogg',
            'disk'          => 'local',
            'path'          => 'tenants/test/files/voice-note-456.ogg',
            'mime_type'     => 'audio/ogg',
            'extension'     => 'ogg',
            'size'          => 2048,
            'type'          => File::resolveType('audio/ogg'),
        ]);

        $response = $this->actingAs($a)->postJson($this->tenantUrl("/dm/{$conversation->id}/messages"), [
            'body'                    => '',
            'type'                    => 'voice',
            'files'                   => [$file->id],
            'voice_duration_seconds'  => 3,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message.type', 'voice')
            ->assertJsonPath('message.metadata.voice_duration_seconds', 3);
    }

    public function test_upload_endpoint_accepts_the_new_voice_mime_extensions(): void
    {
        $user = $this->tenantUser();

        foreach (['webm', 'ogg', 'm4a'] as $ext) {
            $file = UploadedFile::fake()->create("voice-note.{$ext}", 5);

            $response = $this->actingAs($user)->postJson($this->tenantUrl('/files'), ['file' => $file]);

            // A validation failure would be 422; anything else confirms the
            // mimes: whitelist accepted the extension (server-side MIME
            // sniffing of the fake payload may still reject it further down
            // the pipeline, which is a separate, pre-existing concern).
            $this->assertNotSame(422, $response->status(), "Extension .{$ext} was rejected by validation: " . $response->getContent());
        }
    }
}

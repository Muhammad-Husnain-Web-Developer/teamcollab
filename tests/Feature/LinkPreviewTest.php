<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\Tenant\LinkPreview;
use App\Models\Tenant\Message;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class LinkPreviewTest extends TenantTestCase
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

    public function test_sending_a_message_with_a_url_fetches_and_attaches_a_link_preview(): void
    {
        // A literal public IP — no DNS resolution needed, keeping the test
        // hermetic — is treated as safe by isUrlSafe() and gets fetched.
        $url = 'http://8.8.8.8/article';

        Http::fake([
            $url => Http::response(
                '<html><head>'
                . '<meta property="og:title" content="Great Article">'
                . '<meta property="og:description" content="A description of the article.">'
                . '<meta property="og:image" content="http://8.8.8.8/cover.jpg">'
                . '<meta property="og:site_name" content="Example Site">'
                . '</head><body></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        $message = app(MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => "check this out: {$url}",
        ], $user);

        $message->refresh();

        $preview = LinkPreview::where('url', $url)->first();
        $this->assertNotNull($preview);
        $this->assertFalse($preview->fetch_failed);
        $this->assertSame('Great Article', $preview->title);
        $this->assertSame('Example Site', $preview->site_name);

        $this->assertCount(1, $message->metadata['link_previews'] ?? []);
        $this->assertSame('Great Article', $message->metadata['link_previews'][0]['title']);
        $this->assertStringContainsString('/link-previews/', $message->metadata['link_previews'][0]['image_url']);
    }

    public function test_repeating_the_same_url_reuses_the_cached_preview(): void
    {
        $url = 'http://8.8.8.8/cached-article';

        Http::fake([
            $url => Http::response(
                '<html><head><meta property="og:title" content="Cached"></head></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);
        $service = app(MessageService::class);

        $service->send(['channel_id' => $channel->id, 'body' => $url], $user);
        $service->send(['channel_id' => $channel->id, 'body' => $url], $user);

        $this->assertSame(1, LinkPreview::where('url', $url)->count());
        Http::assertSentCount(1);
    }

    public function test_a_url_that_resolves_to_a_private_ip_is_never_fetched(): void
    {
        Http::fake(); // no responses defined — any request would throw

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        app(MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => 'internal link: http://127.0.0.1/secret and http://169.254.169.254/latest/meta-data',
        ], $user);

        Http::assertNothingSent();

        $this->assertTrue(
            LinkPreview::where('url', 'http://127.0.0.1/secret')->first()?->fetch_failed,
        );
        $this->assertTrue(
            LinkPreview::where('url', 'http://169.254.169.254/latest/meta-data')->first()?->fetch_failed,
        );
    }

    public function test_a_message_without_a_url_never_creates_a_link_preview(): void
    {
        Http::fake();

        $user    = $this->tenantUser();
        $channel = $this->channelWith($user);

        app(MessageService::class)->send([
            'channel_id' => $channel->id,
            'body'       => 'just a normal message, no links here',
        ], $user);

        Http::assertNothingSent();
        $this->assertSame(0, LinkPreview::count());
    }

    public function test_link_preview_image_proxy_streams_the_image_and_never_exposes_the_raw_url(): void
    {
        $imageUrl = 'http://8.8.8.8/cover.jpg';

        Http::fake([
            $imageUrl => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $user = $this->tenantUser();

        $preview = LinkPreview::create([
            'url'          => 'http://8.8.8.8/article',
            'url_hash'     => hash('sha256', 'http://8.8.8.8/article'),
            'image_url'    => $imageUrl,
            'fetched_at'   => now(),
            'fetch_failed' => false,
        ]);

        $response = $this->actingAs($user)
            ->get($this->tenantUrl("/link-previews/{$preview->id}/image"));

        $response->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame('fake-image-bytes', $response->getContent());
    }
}

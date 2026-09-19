<?php

namespace Tests\Feature;

use App\Models\Tenant\Channel;
use App\Models\Tenant\ChannelMember;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TenantTestCase;

class GifSearchTest extends TenantTestCase
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

    public function test_search_returns_not_configured_when_no_api_key_is_set(): void
    {
        config(['services.giphy.key' => null]);
        Http::fake(); // any request would throw — proves nothing was called

        $user = $this->tenantUser();
        $this->channelWith($user); // ensures tenant DB is touched, matches other tests' setup

        $response = $this->actingAs($user)->getJson($this->tenantUrl('/gifs/search?q=cat'));

        $response->assertOk()
            ->assertJsonPath('configured', false)
            ->assertJsonPath('gifs', []);

        Http::assertNothingSent();
    }

    public function test_search_proxies_giphy_and_shapes_the_response_when_configured(): void
    {
        config(['services.giphy.key' => 'test-key']);

        Http::fake([
            'api.giphy.com/*' => Http::response([
                'data' => [
                    [
                        'id'     => 'abc123',
                        'title'  => 'Cat GIF',
                        'images' => [
                            'fixed_height'       => ['url' => 'https://media.giphy.com/abc123/giphy.gif', 'width' => '480', 'height' => '270'],
                            'fixed_height_small' => ['url' => 'https://media.giphy.com/abc123/200w.gif'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->tenantUser();

        $response = $this->actingAs($user)->getJson($this->tenantUrl('/gifs/search?q=cat'));

        $response->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('gifs.0.id', 'abc123')
            ->assertJsonPath('gifs.0.provider', 'giphy')
            ->assertJsonPath('gifs.0.url', 'https://media.giphy.com/abc123/giphy.gif')
            ->assertJsonPath('gifs.0.width', 480);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.giphy.com/v1/gifs/search?q=cat&api_key=test-key&limit=24&rating=pg-13'
            || str_contains($request->url(), 'api.giphy.com/v1/gifs/search'));
    }

    public function test_an_empty_query_returns_no_results_without_calling_giphy(): void
    {
        config(['services.giphy.key' => 'test-key']);
        Http::fake();

        $user = $this->tenantUser();

        $this->actingAs($user)->getJson($this->tenantUrl('/gifs/search?q='))
            ->assertOk()
            ->assertJsonPath('configured', true)
            ->assertJsonPath('gifs', []);

        Http::assertNothingSent();
    }

    public function test_trending_is_cached_across_requests(): void
    {
        config(['services.giphy.key' => 'test-key']);

        Http::fake([
            'api.giphy.com/*' => Http::response([
                'data' => [
                    [
                        'id'     => 'trend1',
                        'title'  => 'Trending',
                        'images' => [
                            'fixed_height' => ['url' => 'https://media.giphy.com/trend1/giphy.gif', 'width' => '300', 'height' => '300'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = $this->tenantUser();

        $this->actingAs($user)->getJson($this->tenantUrl('/gifs/trending'))->assertOk();
        $this->actingAs($user)->getJson($this->tenantUrl('/gifs/trending'))->assertOk();

        Http::assertSentCount(1);
    }
}

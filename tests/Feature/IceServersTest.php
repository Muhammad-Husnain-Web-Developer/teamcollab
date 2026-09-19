<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TenantTestCase;

class IceServersTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    public function test_stun_only_when_no_turn_is_configured(): void
    {
        config([
            'services.stun.urls'      => 'stun:stun.example.test:3478',
            'services.turn.urls'      => null,
            'services.turn.secret'    => null,
            'services.turn.username'  => null,
            'services.turn.credential' => null,
        ]);

        $this->actingAs($this->tenantUser())
            ->getJson($this->tenantUrl('/calls/ice-servers'))
            ->assertOk()
            ->assertExactJson([
                'iceServers' => [['urls' => ['stun:stun.example.test:3478']]],
                'ttl'        => 3600,
            ]);
    }

    public function test_a_shared_secret_mints_expiring_per_user_turn_credentials(): void
    {
        Carbon::setTestNow('2026-09-19 12:00:00');
        config([
            'services.turn.urls'   => 'turn:turn.example.test:3478?transport=udp, turns:turn.example.test:5349',
            'services.turn.secret' => 'top-secret',
            'services.turn.ttl'    => 600,
        ]);

        $user = $this->tenantUser();

        $response = $this->actingAs($user)
            ->getJson($this->tenantUrl('/calls/ice-servers'))
            ->assertOk()
            ->assertJsonPath('ttl', 600)
            ->assertJsonCount(2, 'iceServers');

        $turn = $response->json('iceServers.1');
        $expectedUsername = (now()->timestamp + 600) . ':' . $user->id;

        $this->assertSame(
            ['turn:turn.example.test:3478?transport=udp', 'turns:turn.example.test:5349'],
            $turn['urls'],
        );
        $this->assertSame($expectedUsername, $turn['username']);
        // Exactly what coturn recomputes with `use-auth-secret`.
        $this->assertSame(
            base64_encode(hash_hmac('sha1', $expectedUsername, 'top-secret', true)),
            $turn['credential'],
        );
        // The secret itself never leaves the server.
        $this->assertStringNotContainsString('top-secret', $response->getContent());

        Carbon::setTestNow();
    }

    public function test_static_turn_credentials_are_passed_through_when_no_secret_is_set(): void
    {
        config([
            'services.turn.urls'       => 'turn:relay.example.test:3478',
            'services.turn.secret'     => null,
            'services.turn.username'   => 'fixed-user',
            'services.turn.credential' => 'fixed-pass',
        ]);

        $this->actingAs($this->tenantUser())
            ->getJson($this->tenantUrl('/calls/ice-servers'))
            ->assertOk()
            ->assertJsonPath('iceServers.1', [
                'urls'       => ['turn:relay.example.test:3478'],
                'username'   => 'fixed-user',
                'credential' => 'fixed-pass',
            ]);
    }

    public function test_ice_servers_require_a_signed_in_workspace_member(): void
    {
        $this->getJson($this->tenantUrl('/calls/ice-servers'))->assertUnauthorized();
    }
}

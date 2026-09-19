<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_root_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_page_is_reachable(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_login_is_rate_limited(): void
    {
        // 10 attempts per minute; the 11th must be blocked so the endpoint
        // cannot be used for credential stuffing.
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', [
                'email'    => 'nobody@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email'    => 'nobody@example.test',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}

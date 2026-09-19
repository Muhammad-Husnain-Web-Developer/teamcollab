<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RegistrationVerificationMailTest extends TestCase
{
    use RefreshDatabase;

    private function register(): void
    {
        $this->post('/register', [
            'name'                  => 'New Person',
            'email'                 => 'new.person@example.test',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect();
    }

    public function test_registration_queues_the_verification_mail_instead_of_sending_inline(): void
    {
        Notification::fake();

        $this->register();

        $user = User::where('email', 'new.person@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);

        Notification::assertSentTo($user, QueuedVerifyEmail::class);
        $this->assertInstanceOf(ShouldQueue::class, new QueuedVerifyEmail());
    }

    public function test_the_account_is_created_even_if_the_mail_job_can_never_run(): void
    {
        // Nothing is dispatched to a worker, so a dead SMTP provider can't
        // reach back into the registration request any more.
        Queue::fake();

        $this->register();

        $this->assertDatabaseHas('users', ['email' => 'new.person@example.test']);
        Queue::assertPushedOn('notifications', \Illuminate\Notifications\SendQueuedNotifications::class);
    }
}

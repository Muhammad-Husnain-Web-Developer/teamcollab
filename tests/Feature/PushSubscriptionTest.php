<?php

namespace Tests\Feature;

use App\Notifications\ChatEventPushNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\PushSubscription;
use Tests\TenantTestCase;

class PushSubscriptionTest extends TenantTestCase
{
    private function tenantUrl(string $path): string
    {
        return 'http://' . $this->tenant->id . '.localhost' . $path;
    }

    public function test_storing_a_subscription_creates_a_row_on_the_central_connection(): void
    {
        $user = $this->tenantUser();

        $response = $this->actingAs($user)->postJson($this->tenantUrl('/push-subscriptions'), [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/test-endpoint-123',
            'keys'     => [
                'p256dh' => 'test-p256dh-key',
                'auth'   => 'test-auth-token',
            ],
        ]);

        $response->assertOk()->assertJsonPath('subscribed', true);

        $this->assertSame(1, PushSubscription::count());

        $subscription = PushSubscription::first();
        $this->assertSame($user->id, (int) $subscription->subscribable_id);
        $this->assertSame($user->getMorphClass(), $subscription->subscribable_type);
        $this->assertSame('https://fcm.googleapis.com/fcm/send/test-endpoint-123', $subscription->endpoint);
    }

    public function test_deleting_a_subscription_removes_it(): void
    {
        $user = $this->tenantUser();

        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/to-remove', 'key', 'token');
        $this->assertSame(1, PushSubscription::count());

        $this->actingAs($user)
            ->deleteJson($this->tenantUrl('/push-subscriptions'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/to-remove'])
            ->assertOk()
            ->assertJsonPath('unsubscribed', true);

        $this->assertSame(0, PushSubscription::count());
    }

    public function test_notify_sends_a_push_notification_when_the_user_has_a_subscription(): void
    {
        Notification::fake();

        $user = $this->tenantUser();
        $user->updatePushSubscription('https://fcm.googleapis.com/fcm/send/subscribed-user', 'key', 'token');

        app(NotificationService::class)->notify($user->id, 'dm_message', [
            'sender_id'   => $user->id,
            'sender_name' => 'Someone',
            'preview'     => 'hey there',
            'conversation_id' => 1,
        ]);

        Notification::assertSentTo($user, ChatEventPushNotification::class, function ($notification) {
            return $notification->body === 'hey there';
        });
    }

    public function test_notify_skips_push_when_the_user_has_no_subscription(): void
    {
        Notification::fake();

        $user = $this->tenantUser();

        app(NotificationService::class)->notify($user->id, 'dm_message', [
            'sender_id'   => $user->id,
            'sender_name' => 'Someone',
            'preview'     => 'hey there',
            'conversation_id' => 1,
        ]);

        Notification::assertNotSentTo($user, ChatEventPushNotification::class);
    }
}

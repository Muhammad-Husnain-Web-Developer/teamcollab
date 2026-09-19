<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $token,
        public readonly string $role = 'member',
    ) {
        $this->onQueue('notifications');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = url("/workspaces/{$this->tenant->id}/invites/accept?token={$this->token}");
        $tenantName = $this->tenant->name;
        $role = ucfirst($this->role);

        return (new MailMessage)
            ->subject("You've been invited to {$tenantName} on TeamCollab")
            ->greeting("Hello!")
            ->line("You have been invited to join **{$tenantName}** as a **{$role}**.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("This invitation link will expire in 7 days.")
            ->line("If you did not expect this invitation, you can safely ignore this email.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tenant_id'   => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'role'        => $this->role,
            'token'       => $this->token,
        ];
    }
}

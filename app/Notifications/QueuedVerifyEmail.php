<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's stock VerifyEmail is sent inline, so a mail provider hiccup
 * (auth failure, exhausted credits, timeout) surfaced as a 500 on the
 * registration form itself. Queued, the account is created regardless and
 * the mail is retried; "Resend verification email" covers the rest.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] seconds between attempts */
    public array $backoff = [30, 120, 600];

    public function __construct()
    {
        $this->onQueue('notifications');
    }
}

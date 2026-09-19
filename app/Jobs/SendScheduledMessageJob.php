<?php

namespace App\Jobs;

use App\Models\Tenant\ScheduledMessage;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendScheduledMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 60;

    public function __construct(
        public readonly int $scheduledMessageId,
        public readonly string $tenantId,
    ) {
        $this->onQueue('scheduled-messages');
    }

    public function handle(MessageService $messages): void
    {
        $scheduled = ScheduledMessage::find($this->scheduledMessageId);

        if (! $scheduled) {
            Log::warning("SendScheduledMessageJob: ScheduledMessage #{$this->scheduledMessageId} not found.");
            return;
        }

        // Guards a cancel-just-before-fire race and a queue retry from
        // sending the same scheduled message twice.
        if ($scheduled->status !== 'pending') {
            return;
        }

        $user = User::find($scheduled->user_id);
        if (! $user) {
            $scheduled->update(['status' => 'failed']);
            return;
        }

        $data = [
            'channel_id'      => $scheduled->channel_id,
            'conversation_id' => $scheduled->conversation_id,
            'body'            => $scheduled->body ?? '',
            'type'            => $scheduled->type ?? 'text',
            'files'           => $scheduled->files ?? [],
            'mentions'        => $scheduled->mentions ?? [],
            'metadata'        => $scheduled->metadata ?? [],
        ];

        try {
            if ($scheduled->channel_id) {
                $message = $messages->send($data, $user);
            } elseif ($scheduled->conversation_id) {
                $message = $messages->sendToConversation($data, $user);
            } else {
                Log::warning("SendScheduledMessageJob: ScheduledMessage #{$scheduled->id} has neither channel_id nor conversation_id.");
                $scheduled->update(['status' => 'failed']);
                return;
            }

            $scheduled->update(['status' => 'sent', 'sent_message_id' => $message->id]);
        } catch (\Throwable $e) {
            Log::error("SendScheduledMessageJob: failed to send scheduled message #{$scheduled->id}: {$e->getMessage()}");
            $scheduled->update(['status' => 'failed']);
        }
    }
}

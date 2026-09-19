<?php

namespace App\Jobs;

use App\Events\MessageLinkPreviewReady;
use App\Models\Tenant\Message;
use App\Services\LinkPreviewService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchLinkPreviewJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $backoff = 10;
    public int $timeout = 30;

    /**
     * @param  string[]  $urls
     */
    public function __construct(
        public readonly int $messageId,
        public readonly string $tenantId,
        public readonly array $urls,
    ) {
        $this->onQueue('link-previews');
    }

    public function handle(LinkPreviewService $previews): void
    {
        $message = Message::find($this->messageId);

        if (! $message) {
            Log::warning("FetchLinkPreviewJob: Message #{$this->messageId} not found.");
            return;
        }

        $fetched = [];

        foreach ($this->urls as $url) {
            $preview = $previews->fetch($url);

            if (! $preview->fetch_failed) {
                $fetched[] = $preview->toPreviewArray();
            }
        }

        if (empty($fetched)) {
            return;
        }

        $metadata = $message->metadata ?? [];
        $metadata['link_previews'] = $fetched;
        $message->update(['metadata' => $metadata]);

        try {
            event(new MessageLinkPreviewReady($message->fresh(), $this->tenantId));
        } catch (\Throwable) {
            // Broadcast failure is non-fatal — the preview is already saved
            // and will show up next time the message list is fetched.
        }
    }
}

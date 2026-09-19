<?php

namespace App\Jobs;

use App\Models\Tenant\ActivityLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class LogActivityJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public readonly int $userId,
        public readonly string $action,
        public readonly string $subjectType,
        public readonly int $subjectId,
        public readonly string $description,
        public readonly array $properties = [],
    ) {
        $this->onQueue('activity');
    }

    public function handle(): void
    {
        ActivityLog::create([
            'user_id'      => $this->userId,
            'action'       => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id'   => $this->subjectId,
            'description'  => $this->description,
            'properties'   => $this->properties,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}

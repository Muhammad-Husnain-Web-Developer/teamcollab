<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Thread summaries were channel-only: `channel_id` was NOT NULL, so replying to
 * a direct message (where channel_id is null) failed on insert. DMs support
 * replies exactly like channels do, so the column has to allow null.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE message_threads MODIFY channel_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE message_threads MODIFY channel_id BIGINT UNSIGNED NOT NULL');
    }
};

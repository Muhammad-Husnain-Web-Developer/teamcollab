<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unread counts used to compare `messages.created_at > last_read_at`, but
 * Laravel writes timestamps at whole-second precision: a message posted in the
 * same second as the read marker ties, fails the strict `>`, and vanishes from
 * the badge. Message ids are monotonic, so marking a read position by id is
 * exact regardless of clock granularity.
 *
 * last_read_at is kept — it still records *when* the user last looked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channel_members', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('channel_members', function (Blueprint $table) {
            $table->dropColumn('last_read_message_id');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('last_read_message_id');
        });
    }
};

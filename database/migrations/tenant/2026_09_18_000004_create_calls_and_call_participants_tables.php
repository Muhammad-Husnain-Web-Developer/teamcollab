<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * First persisted call state — calls were 100% ephemeral (Pinia + event
     * payloads) while they were strictly 1-on-1. A mesh call needs a
     * server-side roster so a late joiner can discover who to connect to.
     */
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('initiated_by');
            $table->string('call_type', 10); // audio | video
            $table->string('context_type', 16)->nullable(); // dm | channel | adhoc
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('status', 16)->default('ringing'); // ringing | active | ended
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('call_participants', function (Blueprint $table) {
            $table->id();
            $table->uuid('call_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status', 16)->default('invited'); // invited | joined | declined | left
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['call_id', 'user_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('call_participants');
        Schema::dropIfExists('calls');
    }
};

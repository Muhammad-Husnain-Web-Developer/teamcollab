<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_message_id');
            $table->unsignedBigInteger('channel_id');
            $table->integer('replies_count')->default(0);
            $table->unsignedBigInteger('last_reply_id')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->json('participant_ids')->nullable();
            $table->timestamps();

            $table->unique('parent_message_id');
            $table->index('channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_threads');
    }
};

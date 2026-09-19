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
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->text('body')->nullable();
            $table->string('type')->default('text');
            $table->json('files')->nullable();
            $table->json('mentions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scheduled_for');
            $table->string('status')->default('pending'); // pending, sent, cancelled, failed
            $table->unsignedBigInteger('sent_message_id')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['status', 'scheduled_for']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};

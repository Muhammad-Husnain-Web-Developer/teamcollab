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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_id')->nullable();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('thread_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->longText('body')->nullable();
            $table->string('type')->default('text'); // text, file, system, giphy
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->unsignedBigInteger('pinned_by')->nullable();
            $table->json('mentions')->nullable();
            $table->json('attachments')->nullable();
            $table->json('metadata')->nullable();
            $table->integer('replies_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('channel_id');
            $table->index('conversation_id');
            $table->index('user_id');
            $table->index('thread_id');
            $table->index('parent_id');
            $table->index('created_at');
            $table->index('is_pinned');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

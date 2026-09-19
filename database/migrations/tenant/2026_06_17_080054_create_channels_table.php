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
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type')->default('public'); // public, private
            $table->unsignedBigInteger('created_by');
            $table->string('topic')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_read_only')->default(false);
            $table->boolean('is_default')->default(false);
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('members_count')->default(0);
            $table->integer('messages_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_archived');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};

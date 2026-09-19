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
        Schema::create('workspace_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('allow_guest_access')->default(false);
            $table->unsignedBigInteger('default_channel_id')->nullable();
            $table->integer('message_retention_days')->default(365);
            $table->integer('file_storage_limit_mb')->default(5120);
            $table->text('allowed_file_types')->nullable();
            $table->integer('max_file_size_mb')->default(25);
            $table->boolean('enable_emoji_reactions')->default(true);
            $table->boolean('enable_thread_replies')->default(true);
            $table->boolean('enable_direct_messages')->default(true);
            $table->boolean('require_email_verification')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_settings');
    }
};

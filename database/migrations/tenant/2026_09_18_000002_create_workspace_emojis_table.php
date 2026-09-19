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
        Schema::create('workspace_emojis', function (Blueprint $table) {
            $table->id();
            $table->string('shortcode', 32);
            // 'sticker' is unused until Phase 5 (GIF/sticker support) reuses
            // this same table rather than adding a sibling one.
            $table->string('kind', 16)->default('emoji');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('file_path');
            $table->string('thumb_path')->nullable();
            $table->string('mime_type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('shortcode');
            $table->index('kind');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_emojis');
    }
};

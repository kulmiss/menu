<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The website footer (owner, 2026-11-18, QUESTIONS #230): every published
 * version, newest = live. The unpublished draft is a platform setting
 * (`footer_draft`). Restoring an old version publishes it again as a new row,
 * so nothing is ever lost.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('footer_versions', function (Blueprint $table) {
            $table->id();
            $table->json('content');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            // Set when this version was made by "Restore" (the version it came from).
            $table->unsignedBigInteger('restored_from')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_versions');
    }
};
